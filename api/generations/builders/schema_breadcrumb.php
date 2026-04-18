<?php
/**
 * Builder: schema-breadcrumb.json (Complete tier).
 *
 * Port of base44/functions/generateFiles/entry.ts lines 924-952.
 * BreadcrumbList built from scraped siteStructure flags. Home is
 * always position 1, then up to 4 more entries chosen by which
 * sections the scraper found.
 */

declare(strict_types=1);

/**
 * @param array<string, mixed> $ctx
 * @return array{'schema-breadcrumb.json': string}
 */
function build_schema_breadcrumb(array $ctx): array
{
    $url   = $ctx['website_url'];
    $struct = is_array($ctx['ex']['siteStructure'] ?? null) ? $ctx['ex']['siteStructure'] : [];

    $items = [[
        '@type'    => 'ListItem',
        'position' => 1,
        'name'     => 'Home',
        'item'     => $url,
    ]];

    $addCrumb = function(string $name, string $path) use (&$items, $url): void {
        if (count($items) >= 5) return;
        $items[] = [
            '@type'    => 'ListItem',
            'position' => count($items) + 1,
            'name'     => $name,
            'item'     => rtrim($url, '/') . $path,
        ];
    };

    if (!empty($struct['hasProducts']))       $addCrumb('Products', '/products');
    elseif (!empty($struct['hasServices']))   $addCrumb('Services', '/services');
    if (!empty($struct['hasBlog']))           $addCrumb('Blog',     '/blog');
    if (!empty($struct['hasAbout']))          $addCrumb('About',    '/about');
    if (!empty($struct['hasContact']))        $addCrumb('Contact',  '/contact');

    $schema = [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $items,
    ];

    return ['schema-breadcrumb.json' => prettyJson($schema)];
}
