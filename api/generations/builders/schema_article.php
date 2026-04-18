<?php
/**
 * Builder: schema-article.json (Complete tier — template).
 *
 * Port of base44/functions/generateFiles/entry.ts lines 1281-1306.
 * Not a one-size-fits-all Article — it's a reusable template with
 * [REPLACE] placeholders, wired with the resolved author Person +
 * Organization publisher so the user only has to fill in headline,
 * dates, image, and canonical URL for each post.
 */

declare(strict_types=1);

/**
 * @param array<string, mixed> $ctx
 * @return array{'schema-article.json': string}
 */
function build_schema_article(array $ctx): array
{
    $url        = rtrim($ctx['website_url'], '/');
    $author     = hasText($ctx['personName'])
                    ? $ctx['personName']
                    : (string) ($ctx['ex']['founderName'] ?? '[REPLACE WITH AUTHOR NAME]');
    $publisher  = [
        '@type' => 'Organization',
        'name'  => $ctx['businessNameFinal'],
        'url'   => $ctx['website_url'],
    ];
    if (hasText((string) ($ctx['ex']['logoUrl'] ?? ''))) {
        $publisher['logo'] = ['@type' => 'ImageObject', 'url' => (string) $ctx['ex']['logoUrl']];
    }

    $schema = [
        '@context'      => 'https://schema.org',
        '@type'         => 'Article',
        'headline'      => '[REPLACE WITH POST TITLE]',
        'description'   => '[REPLACE WITH 150-CHARACTER SUMMARY]',
        'image'         => '[REPLACE WITH ARTICLE HERO IMAGE URL]',
        'datePublished' => '[REPLACE WITH ISO DATE e.g. 2026-01-15]',
        'dateModified'  => '[REPLACE WITH ISO DATE e.g. 2026-01-15]',
        'author' => [
            '@type' => 'Person',
            'name'  => $author,
            'url'   => $url . '/about',
        ],
        'publisher' => $publisher,
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id'   => '[REPLACE WITH CANONICAL POST URL]',
        ],
    ];

    return ['schema-article.json' => prettyJson($schema)];
}
