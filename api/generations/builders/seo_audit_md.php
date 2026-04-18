<?php
/**
 * Builder: seo-audit.md
 *
 * Port of base44/functions/generateFiles/entry.ts lines 422-543.
 * Technical SEO audit built from scraped findings: robots/sitemap
 * presence, canonical URL, meta description, OG tags, H1 count,
 * existing schema inventory, platform-specific fixes.
 */

declare(strict_types=1);

/**
 * @param array<string, mixed> $ctx
 * @return array{'seo-audit.md': string}
 */
function build_seo_audit_md(array $ctx): array
{
    $ex    = $ctx['ex'];
    $data  = $ctx['data'];
    $url   = $ctx['website_url'];
    $pass  = '✅';
    $fail  = '❌';
    $warn  = '⚠️';

    $auditRobots   = pluck($ex, 'existingRobotsTxt.present', false) ? $pass : $fail;
    $auditSitemap  = pluck($ex, 'existingSitemap.present', false)
        ? "{$pass} (" . (int) pluck($ex, 'existingSitemap.urlCount', 0) . " URLs)"
        : $fail;
    $canonicalUrl  = (string) pluck($ex, 'canonical.url', '');
    $auditCanonical = pluck($ex, 'canonical.present', false)
        ? "{$pass} (" . ($canonicalUrl !== '' ? $canonicalUrl : 'present') . ")"
        : $fail;
    $auditMetaDesc     = hasText((string) ($ex['metaDescription'] ?? ''))  ? $pass : $fail;
    $auditOgTitle      = pluck($ex, 'ogTags.title', false)       ? $pass : $fail;
    $auditOgDesc       = pluck($ex, 'ogTags.description', false) ? $pass : $fail;
    $auditOgImage      = pluck($ex, 'ogTags.image', false)       ? $pass : $fail;
    $auditOgType       = pluck($ex, 'ogTags.type', false)        ? $pass : $fail;
    $auditTwitterCard  = pluck($ex, 'ogTags.twitterCard', false) ? $pass : $fail;
    $auditHttps        = str_starts_with($url, 'https://') ? $pass : $fail;

    $headings = is_array($ex['headingStructure'] ?? null) ? $ex['headingStructure'] : [];
    $h1Count  = 0;
    foreach ($headings as $h) {
        if (is_array($h) && ((int) ($h['level'] ?? 0)) === 1) $h1Count++;
    }
    $auditH1 = $h1Count === 1 ? $pass : ($h1Count === 0 ? $fail : $warn);

    $existingSchemaTypes = is_array(pluck($ex, 'existingSchema.types', [])) ? pluck($ex, 'existingSchema.types', []) : [];
    $hasExistingSchema = count($existingSchemaTypes) > 0;

    $providedSchemas = ['Organization', 'WebSite'];
    if (is_array($ctx['faqMainEntity']) && count($ctx['faqMainEntity']) > 0) $providedSchemas[] = 'FAQPage';
    if (hasText($ctx['personName'])) $providedSchemas[] = 'Person';
    if ($ctx['package_tier'] === 'complete') {
        $providedSchemas[] = 'WebPage';
        $providedSchemas[] = 'BreadcrumbList';
        if (($data['hasPhysicalLocation'] ?? '') === 'yes') $providedSchemas[] = 'LocalBusiness';
    }
    $schemaGaps = [];
    foreach ($providedSchemas as $s) {
        if (!in_array($s, $existingSchemaTypes, true)) $schemaGaps[] = $s;
    }

    // Prioritized quick wins.
    $quickWins = [];
    if (!pluck($ex, 'ogTags.title', false) || !pluck($ex, 'ogTags.description', false) || !pluck($ex, 'ogTags.image', false)) {
        $quickWins[] = 'Paste the provided meta-tags.html block into your site <head> — this adds the missing Open Graph tags.';
    }
    if (!pluck($ex, 'canonical.present', false)) {
        $quickWins[] = 'Add a canonical URL tag to every page: <link rel="canonical" href="YOUR_CANONICAL_URL" />.';
    }
    if (!pluck($ex, 'existingSitemap.present', false)) {
        $quickWins[] = 'Upload the provided sitemap.xml to your site root and submit it to Google Search Console.';
    }
    if (!pluck($ex, 'existingRobotsTxt.present', false)) {
        $quickWins[] = 'Upload the provided robots.txt to your site root.';
    }
    if (!hasText((string) ($ex['metaDescription'] ?? ''))) {
        $quickWins[] = 'Add a <meta name="description"> tag of 150–160 characters to every page.';
    }
    if ($h1Count === 0) {
        $quickWins[] = "Add exactly one <h1> tag per page describing the page's primary topic.";
    } elseif ($h1Count > 1) {
        $quickWins[] = "You have {$h1Count} <h1> tags on the homepage. Use only one <h1> per page; downgrade the others to <h2>.";
    }
    if (count($schemaGaps) > 0) {
        $quickWins[] = 'Install these schema types (not currently detected on your site): ' . implode(', ', $schemaGaps) . '.';
    }

    $platform = (string) ($ex['platform'] ?? 'Custom/Other');
    $platformInstructions = [
        'WordPress'   => 'Use a plugin like RankMath or Yoast SEO to install the provided schema JSON-LD. Upload the static files (robots.txt, sitemap.xml, llm.txt) via your host\'s file manager or an FTP client to /public_html/.',
        'Shopify'     => 'Paste the schema JSON inside a <script type="application/ld+json"> block in theme.liquid within <head>. Static files like robots.txt are managed in Online Store → Preferences → robots.txt customization.',
        'Wix'         => 'In Wix, go to Settings → Custom Code and paste the meta-tags.html + each schema JSON as a Custom Code snippet targeting <head> on all pages.',
        'Squarespace' => 'Paste the schema JSON in Settings → Advanced → Code Injection → Header. Meta tags go in the same place.',
        'Webflow'     => 'Paste schema JSON in Project Settings → Custom Code → Head. For page-specific schema, use Page Settings → Custom Code → Head.',
        'Framer'      => 'In Site Settings → General → Custom Code → End of <head>, paste the meta tags and schema JSON blocks.',
        'Kajabi'      => 'In Site Settings → Advanced Settings → Site header code, paste the schema JSON and meta tags.',
        'Ghost'       => 'In Admin → Settings → Code Injection → Site Header, paste the schema JSON and meta tags.',
        'Custom/Other'=> 'Paste the schema JSON inside a <script type="application/ld+json"> block in your site <head>. Upload robots.txt, sitemap.xml, and the other static files to your site root directory.',
    ];

    $schemaMarkupStatus = $hasExistingSchema
        ? "Your site already has the following schema types:\n\n" . implode("\n", array_map(fn($t) => "- {$t}", $existingSchemaTypes))
        : 'No JSON-LD or microdata schema was detected on your homepage.';

    $schemaGapLine = count($schemaGaps) > 0
        ? '**Schemas to install (not currently on your site):** ' . implode(', ', $schemaGaps) . '.'
        : 'All provided schema types are already present on your site — use the generated files to refresh or extend the existing markup.';

    $quickWinsBlock = count($quickWins) > 0
        ? implode("\n", array_map(fn($w, $i) => ($i + 1) . '. ' . $w, $quickWins, array_keys($quickWins)))
        : 'No quick wins found — your technical SEO baseline looks strong. Focus on content and GEO signals.';

    $lines = [
        "# SEO Audit — {$ctx['businessNameFinal']}",
        '',
        "Source: {$url}  ",
        "Generated: {$ctx['today']}  ",
        "Detected platform: **{$platform}**",
        '',
        '---',
        '',
        '## 1. Technical SEO Status',
        '',
        '| Check | Status |',
        '|-------|--------|',
        "| HTTPS | {$auditHttps} |",
        "| robots.txt present | {$auditRobots} |",
        "| sitemap.xml present | {$auditSitemap} |",
        "| Canonical URL | {$auditCanonical} |",
        "| Meta description | {$auditMetaDesc} |",
        "| Exactly one H1 | {$auditH1} ({$h1Count} found) |",
        "| og:title | {$auditOgTitle} |",
        "| og:description | {$auditOgDesc} |",
        "| og:image | {$auditOgImage} |",
        "| og:type | {$auditOgType} |",
        "| twitter:card | {$auditTwitterCard} |",
        '',
        '## 2. Schema Markup Status',
        '',
        $schemaMarkupStatus,
        '',
        'This package provides schema JSON for: ' . implode(', ', $providedSchemas) . '.',
        '',
        $schemaGapLine,
        '',
        '## 3. Missing Quick Wins',
        '',
        $quickWinsBlock,
        '',
        "## 4. Platform-Specific Instructions ({$platform})",
        '',
        $platformInstructions[$platform] ?? $platformInstructions['Custom/Other'],
        '',
        '## 5. Next Steps',
        '',
        '1. Address the quick wins above first — they unlock the largest gains for the least effort.',
        '2. Validate every schema file at https://validator.schema.org and https://search.google.com/test/rich-results.',
        '3. Submit your sitemap.xml to Google Search Console and Bing Webmaster Tools.',
        '4. Re-run this generator every 90 days or when your business offering changes significantly.',
    ];

    return ['seo-audit.md' => implode("\n", $lines)];
}
