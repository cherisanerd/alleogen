<?php
/**
 * Builder: entity-map.json (Complete tier).
 *
 * Port of base44/functions/generateFiles/entry.ts lines 1108-1154.
 * Structured knowledge graph: primaryEntity, people, products, topics,
 * frameworks, claims (capped at 10), relationships.
 */

declare(strict_types=1);

/**
 * @param array<string, mixed> $ctx
 * @return array{'entity-map.json': string}
 */
function build_entity_map_json(array $ctx): array
{
    $name  = $ctx['businessNameFinal'];
    $url   = $ctx['website_url'];
    $ex    = $ctx['ex'];

    $proofList       = splitList($ctx['proofPoints']);
    $credibilityList = splitList($ctx['credibilitySignals']);
    $socialProofList = is_array($ex['socialProofSignals'] ?? null) ? $ex['socialProofSignals'] : [];
    $topicList       = splitList($ctx['topicOwnership']);

    // Collect all claim sources (same shape as the GEO content brief).
    $allClaims = [];
    foreach ($proofList as $p) {
        $allClaims[] = ['claim' => $p, 'evidence' => 'Reported by the business on the questionnaire (Q10 Proof Points).'];
    }
    foreach ($credibilityList as $c) {
        $allClaims[] = ['claim' => $c, 'evidence' => 'Reported by the business on the questionnaire (Q6 Credibility Signals).'];
    }
    foreach ($socialProofList as $s) {
        $allClaims[] = ['claim' => (string) $s, 'evidence' => "Detected on {$url} by the scraper."];
    }

    $frameworks = [];
    foreach (splitList($ctx['frameworksLine']) as $fw) {
        $frameworks[] = [
            'name'        => $fw,
            'description' => "{$fw} is a proprietary methodology used by {$name}.",
        ];
    }

    $topics = array_map(fn($t) => ['name' => $t, 'relationship' => 'authority'], $topicList);

    $entityMap = [
        'primaryEntity' => [
            'type'        => 'Organization',
            'name'        => $name,
            'url'         => $url,
            'description' => hasText($ctx['coreDescription'])
                ? $ctx['coreDescription']
                : (hasText($ctx['knowledgePanel']) ? $ctx['knowledgePanel'] : ''),
        ],
        'people'       => [],
        'products'     => [],
        'topics'       => $topics,
        'frameworks'   => $frameworks,
        'claims'       => array_values(array_map(
            fn($c) => ['statement' => $c['claim'], 'evidence' => $c['evidence'], 'source' => $name],
            array_slice($allClaims, 0, 10)
        )),
        'relationships'=> [],
    ];

    if (hasText($ctx['personName'])) {
        $entityMap['people'][] = [
            'type'        => 'Person',
            'name'        => $ctx['personName'],
            'role'        => (string) ($ex['founderRole'] ?? ''),
            'expertise'   => $topicList,
            'credentials' => array_slice($credibilityList, 0, 10),
        ];
        $entityMap['relationships'][] = [
            'from' => $ctx['personName'],
            'to'   => $name,
            'type' => hasText((string) ($ex['founderRole'] ?? ''))
                ? "{$ex['founderRole']} of"
                : 'founder of',
        ];
    }

    $scrapedProducts = is_array($ex['namedProductsWithDesc'] ?? null) ? $ex['namedProductsWithDesc'] : [];
    if (count($scrapedProducts) > 0) {
        foreach ($scrapedProducts as $p) {
            if (!is_array($p) || !hasText((string) ($p['name'] ?? ''))) continue;
            $entityMap['products'][] = [
                'name'        => (string) $p['name'],
                'description' => (string) ($p['description'] ?? ''),
            ];
        }
    } elseif (hasText($ctx['productsLine'])) {
        foreach (splitList($ctx['productsLine']) as $p) {
            $entityMap['products'][] = ['name' => $p, 'description' => ''];
        }
    }

    foreach ($entityMap['products'] as $p) {
        $entityMap['relationships'][] = ['from' => $name, 'to' => $p['name'], 'type' => 'offers'];
    }
    foreach ($entityMap['topics'] as $t) {
        $entityMap['relationships'][] = ['from' => $name, 'to' => $t['name'], 'type' => 'authority on'];
    }

    return ['entity-map.json' => prettyJson($entityMap)];
}
