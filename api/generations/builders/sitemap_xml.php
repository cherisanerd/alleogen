<?php
/**
 * Builder: sitemap.xml + ai-sitemap.xml
 *
 * Port of base44/functions/generateFiles/entry.ts lines 156-209.
 * Real sitemap built from scraped internalLinks (up to 50) with
 * fallback to siteStructure-derived top-level URLs. ai-sitemap.xml
 * is emitted as an identical copy for backward compatibility.
 */

declare(strict_types=1);

/**
 * @param array<string, mixed> $ctx
 * @return array{'sitemap.xml': string, 'ai-sitemap.xml': string}
 */
function build_sitemap_xml(array $ctx): array
{
    $websiteUrl = rtrim((string) $ctx['website_url'], '/');
    $today      = $ctx['today'];
    $site       = is_array($ctx['ex']['siteStructure'] ?? null) ? $ctx['ex']['siteStructure'] : [];

    $urls = [$websiteUrl];
    $seen = [$websiteUrl => true];

    $internalLinks = $ctx['ex']['internalLinks'] ?? null;
    if (is_array($internalLinks)) {
        foreach ($internalLinks as $link) {
            if (count($urls) >= 50) break;
            $href = is_array($link) ? ((string) ($link['url'] ?? '')) : (string) $link;
            $abs = _absolutizeUrl($href, $websiteUrl);
            if ($abs === null || isset($seen[$abs])) continue;
            $seen[$abs] = true;
            $urls[] = $abs;
        }
    }

    // Fallback: seed from siteStructure flags when no internal links exist.
    if (count($urls) <= 1) {
        $seed = [
            ['hasProducts', '/products'],
            ['hasServices', '/services'],
            ['hasBlog',     '/blog'],
            ['hasAbout',    '/about'],
            ['hasContact',  '/contact'],
        ];
        foreach ($seed as [$flag, $path]) {
            if (!empty($site[$flag])) {
                $abs = $websiteUrl . $path;
                if (!isset($seen[$abs])) { $urls[] = $abs; $seen[$abs] = true; }
            }
        }
    }

    $entries = [];
    foreach ($urls as $loc) {
        $parsed = parse_url($loc);
        $pathOnly = $parsed['path'] ?? '/';
        if ($pathOnly === '') $pathOnly = '/';

        $changefreq = _sitemapChangefreq($pathOnly);
        $priority   = _sitemapPriority($loc, $pathOnly, $websiteUrl);

        $entries[] = "  <url>\n"
                   . "    <loc>" . htmlspecialchars($loc, ENT_QUOTES | ENT_XML1) . "</loc>\n"
                   . "    <lastmod>{$today}</lastmod>\n"
                   . "    <changefreq>{$changefreq}</changefreq>\n"
                   . "    <priority>{$priority}</priority>\n"
                   . "  </url>";
    }

    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
         . "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n"
         . implode("\n", $entries) . "\n"
         . "</urlset>";

    return [
        'sitemap.xml'     => $xml,
        'ai-sitemap.xml'  => $xml,
    ];
}

function _sitemapChangefreq(string $path): string
{
    $p = strtolower($path);
    if ($p === '/' || $p === '') return 'weekly';
    if (preg_match('#/(blog|news|articles?)(/|$)#', $p) === 1) return 'weekly';
    if (preg_match('#/(product|shop|store|pricing)(/|$)#', $p) === 1) return 'daily';
    if (preg_match('#/(about|contact|team|privacy|terms)(/|$)#', $p) === 1) return 'monthly';
    return 'weekly';
}

function _sitemapPriority(string $loc, string $path, string $baseUrl): string
{
    if ($path === '/' || $path === '' || rtrim($loc, '/') === $baseUrl) return '1.0';
    $depth = substr_count($path, '/');
    if ($depth <= 1) return '0.8';
    if ($depth === 2) return '0.7';
    return '0.6';
}

/**
 * Minimal URL absolutizer — good enough for sitemap entries where the
 * input is either a full URL or a root-relative path.
 */
function _absolutizeUrl(string $href, string $baseUrl): ?string
{
    $href = trim($href);
    if ($href === '') return null;
    if (preg_match('#^https?://#i', $href) === 1) {
        return rtrim($href, '/') ?: $baseUrl;
    }
    if (str_starts_with($href, '//')) {
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
        return rtrim("{$scheme}:{$href}", '/');
    }
    if (str_starts_with($href, '/')) {
        return rtrim($baseUrl . $href, '/') ?: $baseUrl;
    }
    // Relative paths — resolve against the base.
    return rtrim($baseUrl . '/' . ltrim($href, '/'), '/');
}
