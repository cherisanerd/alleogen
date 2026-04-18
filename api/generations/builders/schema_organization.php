<?php
/**
 * Builder: schema-organization.json
 *
 * Port of base44/functions/generateFiles/entry.ts lines 211-316.
 * Organization schema with the Phase 2 enhancements:
 *   - knowsAbout prefers Q11 topicOwnership → industryKeywords → contentFocus
 *   - award parsed from credibilitySignals via regex
 *   - numberOfEmployees parsed from proofPoints via regex
 *   - hasOfferCatalog built from productsLine (Q3)
 */

declare(strict_types=1);

/**
 * @param array<string, mixed> $ctx
 * @return array{'schema-organization.json': string}
 */
function build_schema_organization(array $ctx): array
{
    $ex          = $ctx['ex'];
    $data        = $ctx['data'];
    $websiteUrl  = $ctx['website_url'];
    $name        = $ctx['businessNameFinal'];

    $schema = [
        '@context'    => 'https://schema.org',
        '@type'       => 'Organization',
        'name'        => $name,
        'url'         => $websiteUrl,
        'description' => hasText($ctx['coreDescription']) ? $ctx['coreDescription'] : ($ctx['productsLine'] ?: ''),
        'email'       => (string) ($data['contactEmail'] ?? ''),
    ];

    if (($data['hasPhysicalLocation'] ?? '') === 'yes' && hasText((string) ($data['address'] ?? ''))) {
        $schema['address'] = [
            '@type'         => 'PostalAddress',
            'streetAddress' => (string) $data['address'],
        ];
    }

    if (hasText((string) ($data['yearEstablished'] ?? ''))) {
        $schema['foundingDate'] = (string) $data['yearEstablished'];
    }

    // Founder resolution matches the TS: Q4 first-name wins, then scraped founder.
    if (hasText($ctx['founderLine'])) {
        $firstName = trim(explode(',', $ctx['founderLine'])[0]);
        $schema['founder'] = [
            '@type'       => 'Person',
            'name'        => $firstName,
            'description' => $ctx['founderLine'],
        ];
    } elseif (hasText((string) ($ex['founderName'] ?? ''))) {
        $founder = ['@type' => 'Person', 'name' => (string) $ex['founderName']];
        if (hasText((string) ($ex['founderRole'] ?? ''))) {
            $founder['jobTitle'] = (string) $ex['founderRole'];
        }
        $schema['founder'] = $founder;
    }

    if (hasText((string) ($ex['pageTitle'] ?? ''))) {
        $schema['headline'] = (string) $ex['pageTitle'];
    }

    if (hasText((string) ($ex['logoUrl'] ?? ''))) {
        $schema['logo'] = ['@type' => 'ImageObject', 'url' => (string) $ex['logoUrl']];
    }

    if (($data['hasPhysicalLocation'] ?? '') === 'no' || !hasText((string) ($data['address'] ?? ''))) {
        $schema['areaServed'] = 'Worldwide';
    } elseif (hasText((string) ($data['address'] ?? ''))) {
        $schema['areaServed'] = (string) $data['address'];
    }

    // knowsAbout — prefer Q11, then industryKeywords, then contentFocus, then data.industry.
    $knowsAbout = splitList($ctx['topicOwnership']);
    $keywords = is_array($ex['industryKeywords'] ?? null) ? $ex['industryKeywords'] : [];
    foreach (array_slice($keywords, 0, 5) as $k) {
        $k = (string) $k;
        if ($k !== '' && !in_array($k, $knowsAbout, true)) $knowsAbout[] = $k;
    }
    if (is_array($data['contentFocus'] ?? null)) {
        $focusMap = [
            'products'  => 'Products',
            'expertise' => 'Industry Expertise',
            'story'     => 'Company Story',
            'team'      => 'Team',
        ];
        foreach ($data['contentFocus'] as $k => $v) {
            if (!$v) continue;
            $label = $focusMap[(string) $k] ?? null;
            if ($label !== null && !in_array($label, $knowsAbout, true)) $knowsAbout[] = $label;
        }
    }
    if (hasText((string) ($data['industry'] ?? '')) && !in_array($data['industry'], $knowsAbout, true)) {
        $knowsAbout[] = (string) $data['industry'];
    }
    if (count($knowsAbout) > 0) $schema['knowsAbout'] = $knowsAbout;

    // award — mine credibilitySignals for award-like phrases.
    if (hasText($ctx['credibilitySignals'])) {
        if (preg_match_all(
            '/(?:awarded?|winner of|featured in|honored with|named [a-z]+)[^.,;\n]{3,80}/i',
            $ctx['credibilitySignals'],
            $matches
        )) {
            $awards = array_map('trim', array_slice($matches[0], 0, 5));
            if (count($awards) > 0) $schema['award'] = $awards;
        }
    }

    // numberOfEmployees — detect headcount in proofPoints.
    if (hasText($ctx['proofPoints'])) {
        if (preg_match(
            '/(\d[\d,]*)\s*(?:employees|team members|staff|contractors)/i',
            $ctx['proofPoints'],
            $m
        )) {
            $schema['numberOfEmployees'] = str_replace(',', '', $m[1]);
        }
    }

    if (hasText((string) ($data['socialLinks'] ?? ''))) {
        $social = array_values(array_filter(array_map('trim', explode("\n", (string) $data['socialLinks']))));
        if (count($social) > 0) $schema['sameAs'] = $social;
    }

    // hasOfferCatalog from Q3 productsServices.
    if (hasText($ctx['productsLine'])) {
        $items = [];
        foreach (splitList($ctx['productsLine']) as $itemName) {
            if (strlen($itemName) > 2 && strlen($itemName) < 120 && count($items) < 10) {
                $items[] = [
                    '@type'        => 'Offer',
                    'itemOffered'  => ['@type' => 'Service', 'name' => $itemName],
                ];
            }
        }
        if (count($items) > 0) {
            $schema['hasOfferCatalog'] = [
                '@type'           => 'OfferCatalog',
                'name'            => "{$name} — Products & Services",
                'itemListElement' => $items,
            ];
        }
    }

    return ['schema-organization.json' => prettyJson($schema)];
}
