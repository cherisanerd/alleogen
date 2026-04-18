<?php
/**
 * Builder: schema-localbusiness.json (Complete tier — conditional).
 *
 * Port of base44/functions/generateFiles/entry.ts lines 954-989.
 * Only emitted when data.hasPhysicalLocation === 'yes'. Ships with
 * placeholder geo coordinates + opening hours + priceRange so the
 * user has every field Google wants with clear replace-me instructions.
 */

declare(strict_types=1);

/**
 * @param array<string, mixed> $ctx
 * @return array<string, string>
 */
function build_schema_localbusiness(array $ctx): array
{
    $data = $ctx['data'];
    if (($data['hasPhysicalLocation'] ?? '') !== 'yes') return [];

    $ex    = $ctx['ex'];
    $name  = $ctx['businessNameFinal'];

    $schema = [
        '@context'    => 'https://schema.org',
        '@type'       => 'LocalBusiness',
        'name'        => $name,
        'url'         => $ctx['website_url'],
        'description' => hasText($ctx['coreDescription']) ? $ctx['coreDescription'] : ($ctx['productsLine'] ?: ''),
        'email'       => (string) ($data['contactEmail'] ?? ''),
        'address' => [
            '@type'         => 'PostalAddress',
            'streetAddress' => (string) ($data['address'] ?? ''),
        ],
        'geo' => [
            '@type'    => 'GeoCoordinates',
            '_comment' => 'Replace with your latitude and longitude. Use https://www.latlong.net to look them up.',
            'latitude' => '0.0',
            'longitude'=> '0.0',
        ],
        'openingHoursSpecification' => [[
            '@type'    => 'OpeningHoursSpecification',
            '_comment' => 'Replace with your actual opening hours. Remove days you are closed.',
            'dayOfWeek'=> ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            'opens'    => '09:00',
            'closes'   => '17:00',
        ]],
        'priceRange' => '$$',
    ];

    if (hasText((string) ($ex['phoneNumber'] ?? ''))) {
        $schema['telephone'] = (string) $ex['phoneNumber'];
    }

    $scrapedSocial = $ex['socialLinks'] ?? null;
    if (is_array($scrapedSocial) && count($scrapedSocial) > 0) {
        $schema['sameAs'] = array_values($scrapedSocial);
    } elseif (hasText((string) ($data['socialLinks'] ?? ''))) {
        $links = array_values(array_filter(array_map('trim', explode("\n", (string) $data['socialLinks']))));
        if (count($links) > 0) $schema['sameAs'] = $links;
    }

    return ['schema-localbusiness.json' => prettyJson($schema)];
}
