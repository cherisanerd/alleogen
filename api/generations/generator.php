<?php
/**
 * File generator orchestrator. Port of base44/functions/generateFiles.
 *
 * Two public functions:
 *   - buildGeneratorContext(array $generation, array $analysis, array $data): array
 *     Returns the shared $ctx that both tier-specific generators consume.
 *   - generateFiles(array $generation, array $analysis, array $data): array
 *     Returns ['files_data' => [...], 'zip_bytes' => string, 'zip_filename' => string].
 *
 * Tier-specific file generation lives in generator_basic.php and
 * generator_complete.php. Both are pure: given $ctx they return an
 * associative array [filename => content].
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/generator_basic.php';
require_once __DIR__ . '/generator_complete.php';

/**
 * Compute the shared context. All values are plain strings/arrays so
 * the two tier generators don't need DB access.
 *
 * @param array<string, mixed> $generation  alleogen_generations row
 * @param array<string, mixed> $analysis    alleogen_analyses row (or [])
 * @param array<string, mixed> $data        questionnaire_data
 * @return array<string, mixed>
 */
function buildGeneratorContext(array $generation, array $analysis, array $data): array
{
    $ex = is_array($analysis['extracted_data'] ?? null) ? $analysis['extracted_data'] : [];
    if (is_string($analysis['extracted_data'] ?? null)) {
        $decoded = json_decode((string) $analysis['extracted_data'], true);
        $ex = is_array($decoded) ? $decoded : [];
    }

    $websiteUrl = (string) ($generation['website_url'] ?? '');
    $businessName = (string) ($generation['business_name'] ?? '');
    $packageTier = (string) ($generation['package_tier'] ?? 'basic');

    // Interview / scraped values — mirrors the TS naming verbatim.
    $businessNameFinal  = (string) ($data['businessName']          ?? $businessName);
    $coreDescription    = (string) ($data['coreDescription']       ?? $ex['tagline']        ?? '');
    $productsLine       = (string) ($data['productsServices']      ?? '');
    $founderLine        = (string) ($data['founderExpert']         ?? '');
    $frameworksLine     = (string) ($data['proprietaryFrameworks'] ?? '');
    $credibilitySignals = (string) ($data['credibilitySignals']    ?? '');
    $priorityMessage    = (string) ($data['priorityMessage']       ?? '');
    $commonQuestion     = (string) ($data['commonQuestion']        ?? '');
    $misconceptions     = (string) ($data['misconceptions']        ?? '');
    $proofPoints        = (string) ($data['proofPoints']           ?? '');
    $topicOwnership     = (string) ($data['topicOwnership']        ?? '');
    $competitorDiff     = (string) ($data['competitorDiff']        ?? '');
    $knowledgePanel     = (string) ($data['knowledgePanel']        ?? '');
    $audienceLine       = (string) ($ex['targetAudience']          ?? '');

    // Pre-compute the FAQPage entries once — the Basic tier needs them
    // for both schema-faqpage.json and seo-audit.md, and the Complete
    // tier reuses for geo-qa-snippets / geo-content-brief shaping.
    $faqItemsScraped = is_array($ex['faqItems'] ?? null) ? $ex['faqItems'] : [];
    $faqMainEntity = [];
    foreach ($faqItemsScraped as $item) {
        if (count($faqMainEntity) >= 10) break;
        $q = (string) ($item['q'] ?? '');
        $a = (string) ($item['a'] ?? '');
        if ($q !== '' && $a !== '') {
            $faqMainEntity[] = [
                '@type' => 'Question',
                'name'  => trim($q),
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim($a)],
            ];
        }
    }
    if ($commonQuestion !== '' && count($faqMainEntity) < 10) {
        $faqMainEntity[] = [
            '@type' => 'Question',
            'name'  => $commonQuestion,
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => "See {$websiteUrl} for the full answer from {$businessNameFinal}.",
            ],
        ];
    }

    // Resolve the founder/person name once for Person schema + cross-refs.
    $founderNameFromQ4 = $founderLine !== '' ? trim(explode(',', $founderLine)[0]) : '';
    $personName = $founderNameFromQ4 !== '' ? $founderNameFromQ4 : (string) ($ex['founderName'] ?? '');

    return [
        // Raw / DB
        'generation'         => $generation,
        'data'               => $data,
        'ex'                 => $ex,
        'website_url'        => $websiteUrl,
        'business_name'      => $businessName,
        'package_tier'       => $packageTier,
        'today'              => date('Y-m-d'),

        // Interview + scraped
        'businessNameFinal'  => $businessNameFinal,
        'coreDescription'    => $coreDescription,
        'productsLine'       => $productsLine,
        'founderLine'        => $founderLine,
        'frameworksLine'     => $frameworksLine,
        'credibilitySignals' => $credibilitySignals,
        'priorityMessage'    => $priorityMessage,
        'commonQuestion'     => $commonQuestion,
        'misconceptions'     => $misconceptions,
        'proofPoints'        => $proofPoints,
        'topicOwnership'     => $topicOwnership,
        'competitorDiff'     => $competitorDiff,
        'knowledgePanel'     => $knowledgePanel,
        'audienceLine'       => $audienceLine,

        // Pre-computed shared structures
        'faqMainEntity'      => $faqMainEntity,
        'personName'         => $personName,
    ];
}

/**
 * Run the full pipeline: build ctx → call tier functions → zip → return.
 *
 * @param array<string, mixed> $generation
 * @param array<string, mixed> $analysis
 * @param array<string, mixed> $data
 * @return array{files_data: array<string, string>, zip_bytes: string, zip_filename: string}
 */
function generateFiles(array $generation, array $analysis, array $data): array
{
    $ctx = buildGeneratorContext($generation, $analysis, $data);

    $files = generateBasicFiles($ctx);
    if (($ctx['package_tier'] ?? 'basic') === 'complete') {
        $files = array_merge($files, generateCompleteFiles($ctx));
    }

    // Zip everything.
    $safeBusinessName = preg_replace('/[^A-Za-z0-9]+/', '_', (string) $ctx['business_name']) ?? 'generation';
    if ($safeBusinessName === '' || $safeBusinessName === '_') $safeBusinessName = 'alleogen_' . (int) ($generation['id'] ?? 0);
    $zipFilename = "{$safeBusinessName}_AEO_Package_{$ctx['package_tier']}.zip";

    $tmpPath = tempnam(sys_get_temp_dir(), 'alleogen_');
    if ($tmpPath === false) {
        throw new RuntimeException('Could not create temp file for zip.');
    }
    $zip = new ZipArchive();
    $opened = $zip->open($tmpPath, ZipArchive::OVERWRITE);
    if ($opened !== true) {
        throw new RuntimeException("ZipArchive open failed: {$opened}");
    }
    foreach ($files as $name => $content) {
        // Nested paths like .well-known/ai.json work because ZipArchive
        // treats forward slashes as folder separators automatically.
        $zip->addFromString($name, (string) $content);
    }
    $zip->close();

    $zipBytes = file_get_contents($tmpPath);
    @unlink($tmpPath);
    if ($zipBytes === false) {
        throw new RuntimeException('Could not read generated zip file.');
    }

    return [
        'files_data'   => $files,
        'zip_bytes'    => $zipBytes,
        'zip_filename' => $zipFilename,
    ];
}
