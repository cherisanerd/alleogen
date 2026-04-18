<?php
/**
 * Pure-PHP file generator orchestrator.
 *
 * Replaces the Deno-subprocess approach with a native PHP pipeline so
 * the app runs on standard shared hosting (no deno binary, no
 * shell_exec required).
 *
 * Public entry: generateFiles(array $generation, array $analysis, array $data): array
 *   Returns [
 *     'files'        => [ 'filename' => 'content', ... ],
 *     'zip_bytes'    => <string>,
 *     'zip_filename' => '<safe>.zip',
 *     'file_count'   => <int>,
 *   ]
 *
 * The per-file builders live in builders/ — one small file per output.
 * Add a new output by creating a builder and `require_once`-ing it here.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

// ------------------------------------------------------------
// Shared helpers used by every builder. Keeping them here (instead
// of in helpers.php) keeps the generator pipeline self-contained
// and unit-testable.
// ------------------------------------------------------------

/**
 * Pretty JSON, matching the TS `JSON.stringify(obj, null, 2)` shape.
 *
 * Using JSON_UNESCAPED_SLASHES so URLs and schema.org "@context"
 * values round-trip cleanly; JSON_UNESCAPED_UNICODE so typographic
 * characters (em-dashes etc.) come out as UTF-8 not \uXXXX.
 */
function prettyJson(mixed $value): string
{
    return (string) json_encode(
        $value,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
}

/**
 * Split a comma/newline-delimited string into a deduplicated list of
 * trimmed non-empty values.
 *
 * @return list<string>
 */
function splitList(?string $s): array
{
    if ($s === null || $s === '') return [];
    $parts = preg_split('/[,\n]/', $s) ?: [];
    $out = [];
    foreach ($parts as $p) {
        $t = trim($p);
        if ($t !== '' && !in_array($t, $out, true)) $out[] = $t;
    }
    return $out;
}

/**
 * True if the string looks non-empty after trim — used for the
 * many `${value ? 'section' : ''}` patterns in the original TS.
 */
function hasText(?string $s): bool
{
    return $s !== null && trim($s) !== '';
}

/**
 * Safe reader for nested scraped values. `pluck($ex, 'ogTags.title')`
 * returns the nested bool, or $default if any segment is missing.
 */
function pluck(array $source, string $path, mixed $default = null): mixed
{
    $cur = $source;
    foreach (explode('.', $path) as $seg) {
        if (!is_array($cur) || !array_key_exists($seg, $cur)) return $default;
        $cur = $cur[$seg];
    }
    return $cur;
}

// ------------------------------------------------------------
// Context builder. Produces the $ctx array that every builder
// consumes. Pre-computes values used by multiple builders
// (faqMainEntity, personName, today) so we only do the work once.
// ------------------------------------------------------------

/**
 * @param array<string, mixed> $generation
 * @param array<string, mixed> $analysis
 * @param array<string, mixed> $data
 * @return array<string, mixed>
 */
function buildGeneratorContext(array $generation, array $analysis, array $data): array
{
    // Decode extracted_data — may arrive as JSON text from MySQL.
    $ex = [];
    $ed = $analysis['extracted_data'] ?? null;
    if (is_string($ed) && $ed !== '') {
        $decoded = json_decode($ed, true);
        if (is_array($decoded)) $ex = $decoded;
    } elseif (is_array($ed)) {
        $ex = $ed;
    }

    $websiteUrl  = (string) ($generation['website_url'] ?? '');
    $businessDb  = (string) ($generation['business_name'] ?? '');
    $packageTier = (string) ($generation['package_tier'] ?? 'basic');

    // Q1–Q13 with the TS naming preserved for easy diff review.
    $businessNameFinal  = hasText((string) ($data['businessName'] ?? '')) ? (string) $data['businessName'] : $businessDb;
    $coreDescription    = hasText((string) ($data['coreDescription']       ?? '')) ? (string) $data['coreDescription']
                          : (string) ($ex['tagline'] ?? '');
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

    // Pre-build the FAQPage mainEntity so schema-faqpage and seo-audit
    // agree on whether FAQ content exists.
    $faqItemsScraped = is_array($ex['faqItems'] ?? null) ? $ex['faqItems'] : [];
    $faqMainEntity = [];
    foreach ($faqItemsScraped as $item) {
        if (count($faqMainEntity) >= 10) break;
        $q = trim((string) ($item['q'] ?? ''));
        $a = trim((string) ($item['a'] ?? ''));
        if ($q !== '' && $a !== '') {
            $faqMainEntity[] = [
                '@type' => 'Question',
                'name'  => $q,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a],
            ];
        }
    }
    if (hasText($commonQuestion) && count($faqMainEntity) < 10) {
        $faqMainEntity[] = [
            '@type' => 'Question',
            'name'  => $commonQuestion,
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => "See {$websiteUrl} for the full answer from {$businessNameFinal}.",
            ],
        ];
    }

    // Resolve the founder/person name for Person schema + cross-refs.
    $founderNameFromQ4 = hasText($founderLine) ? trim(explode(',', $founderLine)[0]) : '';
    $personName = $founderNameFromQ4 !== '' ? $founderNameFromQ4 : (string) ($ex['founderName'] ?? '');

    return [
        'generation'         => $generation,
        'data'               => $data,
        'ex'                 => $ex,
        'website_url'        => $websiteUrl,
        'business_name'      => $businessDb,
        'package_tier'       => $packageTier,
        'today'              => date('Y-m-d'),

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

        'faqMainEntity'      => $faqMainEntity,
        'personName'         => $personName,
    ];
}

// ------------------------------------------------------------
// Builder autoloader. Each file in builders/ registers a function
// named buildX($ctx): array  and we call them all in order.
// ------------------------------------------------------------

$BUILDERS_DIR = __DIR__ . '/builders';

// Basic tier (always runs). Order matters for README / seo-audit
// which reference whether FAQ / Person content is present.
$BUILDERS_BASIC = [
    'build_llm_txt',
    'build_robots_txt',
    'build_sitemap_xml',        // emits sitemap.xml + ai-sitemap.xml
    'build_schema_organization',
    'build_schema_website',
    'build_schema_faqpage',     // conditional inside
    'build_schema_person',      // conditional inside
    'build_meta_tags_html',
    'build_seo_audit_md',
    'build_readme_md',
    'build_implementation_guide_md',
];

// Complete tier.
$BUILDERS_COMPLETE = [
    'build_llms_txt',
    'build_llms_full_txt',
    'build_humans_txt',
    'build_security_txt',
    'build_ai_json',            // .well-known/ai.json
    'build_schema_webpage',
    'build_schema_breadcrumb',
    'build_schema_article',
    'build_schema_localbusiness', // conditional
    'build_geo_content_brief_md',
    'build_entity_map_json',
    'build_geo_qa_snippets_json',
    'build_topical_authority_md',
    'build_verification_checklist_md',
];

// Load every builder in both arrays. Missing files are tolerated so
// we can ship per-phase (P5-PHP-b, -c, -d, ...) without breaking
// earlier phases.
foreach (array_merge($BUILDERS_BASIC, $BUILDERS_COMPLETE) as $fn) {
    $file = $BUILDERS_DIR . '/' . str_replace('build_', '', $fn) . '.php';
    if (is_file($file)) require_once $file;
}

/**
 * Public entry point. Returns the file map + a zip of the same files.
 *
 * @param array<string, mixed> $generation
 * @param array<string, mixed> $analysis
 * @param array<string, mixed> $data
 * @return array{files: array<string,string>, zip_bytes: string, zip_filename: string, file_count: int}
 */
function generateFiles(array $generation, array $analysis, array $data): array
{
    global $BUILDERS_BASIC, $BUILDERS_COMPLETE;

    $ctx = buildGeneratorContext($generation, $analysis, $data);

    $files = [];
    foreach ($BUILDERS_BASIC as $fn) {
        if (!function_exists($fn)) continue; // phase not yet shipped
        $out = $fn($ctx);
        if (is_array($out)) $files = array_merge($files, $out);
    }
    if (($ctx['package_tier'] ?? 'basic') === 'complete') {
        foreach ($BUILDERS_COMPLETE as $fn) {
            if (!function_exists($fn)) continue;
            $out = $fn($ctx);
            if (is_array($out)) $files = array_merge($files, $out);
        }
    }

    // Zip the collected files.
    $tmp = tempnam(sys_get_temp_dir(), 'alleogen_');
    if ($tmp === false) throw new RuntimeException('Could not allocate temp file for zip.');
    $zip = new ZipArchive();
    $ok = $zip->open($tmp, ZipArchive::OVERWRITE);
    if ($ok !== true) throw new RuntimeException('ZipArchive open failed: ' . (string) $ok);
    foreach ($files as $name => $content) {
        $zip->addFromString($name, (string) $content);
    }
    $zip->close();
    $bytes = file_get_contents($tmp);
    @unlink($tmp);
    if ($bytes === false) throw new RuntimeException('Could not read zip bytes.');

    $safeName = preg_replace('/[^A-Za-z0-9]+/', '_', (string) ($ctx['business_name'] ?: 'package')) ?: 'package';
    $zipFilename = $safeName . '_AEO_Package_' . $ctx['package_tier'] . '.zip';

    return [
        'files'        => $files,
        'zip_bytes'    => $bytes,
        'zip_filename' => $zipFilename,
        'file_count'   => count($files),
    ];
}
