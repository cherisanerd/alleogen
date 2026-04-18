<?php
/**
 * Builder: humans.txt (Complete tier).
 *
 * Port of base44/functions/generateFiles/entry.ts line 871.
 * Classic humans.txt format for human site visitors.
 */

declare(strict_types=1);

/**
 * @param array<string, mixed> $ctx
 * @return array{'humans.txt': string}
 */
function build_humans_txt(array $ctx): array
{
    $data     = $ctx['data'];
    $business = $ctx['business_name']; // original DB value, matches TS
    $email    = (string) ($data['contactEmail'] ?? 'Not provided');
    $address  = hasText((string) ($data['address'] ?? '')) ? (string) $data['address'] : 'Online';
    $platform = (string) ($data['platform'] ?? 'Custom');

    $txt = "/* TEAM */\n"
         . "Business: {$business}\n"
         . "Contact: {$email}\n"
         . "Location: {$address}\n\n"
         . "/* SITE */\n"
         . "Platform: {$platform}\n"
         . "Language: English\n"
         . "Standards: HTML5, CSS3, Schema.org\n"
         . "Components: AI-optimized content\n\n"
         . "/* THANKS */\n"
         . "AEO Package Generator - https://aeofilegenerator.com\n";

    return ['humans.txt' => $txt];
}
