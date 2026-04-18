<?php
/**
 * Builder: security.txt (Complete tier).
 *
 * Port of base44/functions/generateFiles/entry.ts line 875.
 * RFC 9116 security.txt with a one-year expiration.
 */

declare(strict_types=1);

/**
 * @param array<string, mixed> $ctx
 * @return array{'security.txt': string}
 */
function build_security_txt(array $ctx): array
{
    $email = (string) ($ctx['data']['contactEmail'] ?? 'security@example.com');
    $expires = date('c', time() + 365 * 24 * 60 * 60); // ISO 8601
    $canonical = rtrim($ctx['website_url'], '/') . '/.well-known/security.txt';

    $txt = "Contact: mailto:{$email}\n"
         . "Expires: {$expires}\n"
         . "Preferred-Languages: en\n"
         . "Canonical: {$canonical}\n";

    return ['security.txt' => $txt];
}
