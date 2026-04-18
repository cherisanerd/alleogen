<?php
/**
 * Builder: schema-webpage.json (Complete tier).
 *
 * Port of base44/functions/generateFiles/entry.ts lines 909-922.
 * Mirrors the WebSite schema but uses @type WebPage, for pages that
 * want their own per-page structured data block.
 */

declare(strict_types=1);

/**
 * @param array<string, mixed> $ctx
 * @return array{'schema-webpage.json': string}
 */
function build_schema_webpage(array $ctx): array
{
    $schema = [
        '@context'    => 'https://schema.org',
        '@type'       => 'WebPage',
        'name'        => $ctx['businessNameFinal'],
        'url'         => $ctx['website_url'],
        'description' => hasText($ctx['coreDescription']) ? $ctx['coreDescription'] : ($ctx['productsLine'] ?: ''),
    ];
    if (hasText((string) ($ctx['ex']['pageTitle'] ?? ''))) {
        $schema['headline'] = (string) $ctx['ex']['pageTitle'];
    }
    $schema['publisher'] = [
        '@type' => 'Organization',
        'name'  => $ctx['businessNameFinal'],
    ];

    return ['schema-webpage.json' => prettyJson($schema)];
}
