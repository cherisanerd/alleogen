<?php
/**
 * Builder: .well-known/ai.json (Complete tier).
 *
 * Port of base44/functions/generateFiles/entry.ts lines 878-907.
 * Machine-readable AI crawler configuration. ZipArchive handles the
 * '.well-known/' path prefix automatically.
 */

declare(strict_types=1);

/**
 * @param array<string, mixed> $ctx
 * @return array{'.well-known/ai.json': string}
 */
function build_ai_json(array $ctx): array
{
    $data = $ctx['data'];
    $crawlerNameMap = [
        'chatgpt'    => 'GPTBot (OpenAI / ChatGPT)',
        'claude'     => 'anthropic-ai (Claude)',
        'perplexity' => 'PerplexityBot',
        'gemini'     => 'Google-Extended (Gemini)',
        'others'     => 'All other AI crawlers',
    ];

    $allowedCrawlerNames = [];
    $crawlers = is_array($data['aiCrawlers'] ?? null) ? $data['aiCrawlers'] : [];
    if (count($crawlers) > 0) {
        foreach ($crawlers as $k => $v) {
            if (!$v) continue;
            $allowedCrawlerNames[] = $crawlerNameMap[(string) $k] ?? (string) $k;
        }
    } else {
        $allowedCrawlerNames = array_values($crawlerNameMap);
    }

    // primary_services — parse Q3, keep items 3..79 chars, cap at 8.
    $primary = [];
    foreach (splitList($ctx['productsLine']) as $s) {
        if (strlen($s) > 2 && strlen($s) < 80 && count($primary) < 8) {
            $primary[] = $s;
        }
    }

    $aiJson = [
        'schema_version'     => '1.0',
        'business_name'      => $ctx['businessNameFinal'],
        'website'            => $ctx['website_url'],
        'description'        => hasText($ctx['coreDescription']) ? $ctx['coreDescription'] : ($ctx['productsLine'] ?: ''),
        'primary_services'   => $primary,
        'contact_email'      => (string) ($data['contactEmail'] ?? ''),
        'ai_crawlers_allowed'=> $allowedCrawlerNames,
        'last_updated'       => date('c'),
    ];

    return ['.well-known/ai.json' => prettyJson($aiJson)];
}
