<?php
/**
 * Builder: schema-website.json
 *
 * Port of base44/functions/generateFiles/entry.ts lines 318-331.
 * WebSite schema — simpler than Organization, mostly metadata.
 */

declare(strict_types=1);

/**
 * @param array<string, mixed> $ctx
 * @return array{'schema-website.json': string}
 */
function build_schema_website(array $ctx): array
{
    $schema = [
        '@context'    => 'https://schema.org',
        '@type'       => 'WebSite',
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

    return ['schema-website.json' => prettyJson($schema)];
}
