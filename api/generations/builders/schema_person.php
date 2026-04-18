<?php
/**
 * Builder: schema-person.json (conditional).
 *
 * Port of base44/functions/generateFiles/entry.ts lines 366-413.
 * E-E-A-T signal + Knowledge Panel feed. Emitted only when a person
 * name is resolvable from Q4 or scraped founder data.
 *
 * knowsAbout: merges Q11 topicOwnership + scraped industryKeywords.
 * sameAs:     merges scraped socialLinks + Q-provided socialLinks.
 */

declare(strict_types=1);

/**
 * @param array<string, mixed> $ctx
 * @return array<string, string>
 */
function build_schema_person(array $ctx): array
{
    $personName = (string) ($ctx['personName'] ?? '');
    if ($personName === '') return [];

    $ex   = $ctx['ex'];
    $data = $ctx['data'];

    $schema = [
        '@context' => 'https://schema.org',
        '@type'    => 'Person',
        'name'     => $personName,
    ];

    if (hasText((string) ($ex['founderRole'] ?? ''))) {
        $schema['jobTitle'] = (string) $ex['founderRole'];
    }

    // Description: Q13 knowledgePanel > Q4 founderLine > generic fallback.
    if (hasText($ctx['knowledgePanel'])) {
        $schema['description'] = $ctx['knowledgePanel'];
    } elseif (hasText($ctx['founderLine'])) {
        $schema['description'] = $ctx['founderLine'];
    } else {
        $schema['description'] = "{$personName} of {$ctx['businessNameFinal']}.";
    }

    $schema['worksFor'] = [
        '@type' => 'Organization',
        'name'  => $ctx['businessNameFinal'],
        'url'   => $ctx['website_url'],
    ];

    // knowsAbout: Q11 first, then scraped industry keywords (cap 5).
    $knows = splitList($ctx['topicOwnership']);
    $keywords = is_array($ex['industryKeywords'] ?? null) ? $ex['industryKeywords'] : [];
    foreach (array_slice($keywords, 0, 5) as $k) {
        $k = (string) $k;
        if ($k !== '' && !in_array($k, $knows, true)) $knows[] = $k;
    }
    if (count($knows) > 0) $schema['knowsAbout'] = $knows;

    // sameAs: scraped social links + Q-provided, de-duplicated.
    $sameAs = [];
    $scrapedSocial = $ex['socialLinks'] ?? null;
    if (is_array($scrapedSocial)) {
        foreach ($scrapedSocial as $link) {
            $link = (string) $link;
            if ($link !== '' && !in_array($link, $sameAs, true)) $sameAs[] = $link;
        }
    }
    if (hasText((string) ($data['socialLinks'] ?? ''))) {
        foreach (explode("\n", (string) $data['socialLinks']) as $l) {
            $l = trim($l);
            if ($l !== '' && !in_array($l, $sameAs, true)) $sameAs[] = $l;
        }
    }
    if (count($sameAs) > 0) $schema['sameAs'] = $sameAs;

    if (!empty($ex['siteStructure']['hasAbout'])) {
        $schema['url'] = rtrim($ctx['website_url'], '/') . '/about';
    }

    return ['schema-person.json' => prettyJson($schema)];
}
