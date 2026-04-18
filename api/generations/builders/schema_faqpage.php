<?php
/**
 * Builder: schema-faqpage.json (conditional).
 *
 * Port of base44/functions/generateFiles/entry.ts lines 333-364.
 * Uses the pre-built $ctx['faqMainEntity'] from the orchestrator so
 * schema-faqpage.json and seo-audit.md agree on whether FAQ content
 * exists. Returns an empty array when no Q&A pairs are available —
 * the file is not emitted in that case.
 */

declare(strict_types=1);

/**
 * @param array<string, mixed> $ctx
 * @return array<string, string>
 */
function build_schema_faqpage(array $ctx): array
{
    $faq = $ctx['faqMainEntity'] ?? [];
    if (!is_array($faq) || count($faq) === 0) return [];

    $schema = [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $faq,
    ];

    return ['schema-faqpage.json' => prettyJson($schema)];
}
