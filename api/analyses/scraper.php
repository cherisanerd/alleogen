<?php
/**
 * Website scraper. Faithful PHP port of
 * base44/functions/analyzeWebsite/entry.ts.
 *
 * One public function: analyzeWebsite(string $url): array
 *
 * Returns an associative array matching the shape the generator
 * expects: businessName, pageTitle, tagline, metaDescription,
 * industryKeywords, contactEmail, phoneNumber, address, platform,
 * socialLinks, siteStructure, pageCount, logoUrl, founderName,
 * founderRole, namedProducts, namedProductsWithDesc, frameworks,
 * targetAudience, socialProofSignals, faqItems, blogPosts,
 * existingSchema, ogTags, canonical, internalLinks, headingStructure,
 * existingRobotsTxt, existingSitemap.
 *
 * Throws on unrecoverable errors (bad URL, fetch failure).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

/** Primary entry point. */
function analyzeWebsite(string $websiteUrl): array
{
    if (!preg_match('#^https?://#i', $websiteUrl)) {
        $websiteUrl = 'https://' . $websiteUrl;
    }
    $parsed = parse_url($websiteUrl);
    if ($parsed === false || empty($parsed['host'])) {
        throw new InvalidArgumentException('Invalid URL.');
    }
    $origin = ($parsed['scheme'] ?? 'https') . '://' . $parsed['host']
            . (!empty($parsed['port']) ? ':' . $parsed['port'] : '');

    $html = scrapeFetch($websiteUrl, 20);

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    // DOMDocument needs the encoding hint or it mangles UTF-8.
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    $xpath = new DOMXPath($doc);

    // ---------- Core page extraction ----------

    $pageTitle = textOf($xpath, '//title');
    $h1Text    = textOf($xpath, '//h1');
    $ogDesc    = attrOf($xpath, '//meta[@property="og:description"]', 'content');
    $tagline   = $h1Text !== '' ? $h1Text : $ogDesc;

    // businessName: og:site_name → microdata Organization name → last segment of title → domain
    $businessName = attrOf($xpath, '//meta[@property="og:site_name"]', 'content');
    if ($businessName === '') {
        $orgName = textOf($xpath, '//*[contains(@itemtype, "Organization") or contains(@itemtype, "LocalBusiness")]//*[@itemprop="name"]');
        if ($orgName !== '') $businessName = $orgName;
    }
    if ($businessName === '' && $pageTitle !== '') {
        $pipeIdx = strrpos($pageTitle, '|');
        $dashIdx = strrpos($pageTitle, ' - ');
        if ($pipeIdx !== false && $pipeIdx > 0) {
            $businessName = trim(substr($pageTitle, $pipeIdx + 1));
        } elseif ($dashIdx !== false && $dashIdx > 0) {
            $businessName = trim(substr($pageTitle, $dashIdx + 3));
        } else {
            $first = explode('|', $pageTitle)[0];
            $businessName = trim(explode('-', $first)[0]);
        }
    }
    if ($businessName === '') {
        $host = preg_replace('/^www\./', '', (string) $parsed['host']);
        $first = explode('.', (string) $host)[0];
        $businessName = ucwords(str_replace('-', ' ', $first));
    }

    // logoUrl
    $logoUrl = attrOf($xpath, '//meta[@property="og:image"]', 'content');
    if ($logoUrl === '') {
        $schemaLogo = $xpath->query('//*[@itemprop="logo"]')->item(0);
        if ($schemaLogo instanceof DOMElement) {
            $logoUrl = $schemaLogo->getAttribute('content');
            if ($logoUrl === '') $logoUrl = $schemaLogo->getAttribute('src');
        }
    }
    $logoUrl = absolutize($logoUrl, $websiteUrl);

    $metaDescription = attrOf($xpath, '//meta[@name="description"]', 'content');

    // founder/owner
    $founderName = '';
    $founderRole = '';
    $personNodes = $xpath->query('//*[contains(@itemtype, "Person")]');
    foreach ($personNodes as $p) {
        if (!$p instanceof DOMElement) continue;
        $title = '';
        $name  = '';
        $jt = $xpath->query('.//*[@itemprop="jobTitle"]', $p)->item(0);
        $nm = $xpath->query('.//*[@itemprop="name"]', $p)->item(0);
        if ($jt) $title = trim((string) $jt->textContent);
        if ($nm) $name  = trim((string) $nm->textContent);
        if ($title !== '' && $name !== '' && $founderName === ''
            && preg_match('/founder|owner|ceo|director|president|creator/i', $title) === 1) {
            $founderName = $name;
            $founderRole = $title;
        }
    }
    if ($founderName === '') {
        $bodyText = textOf($xpath, '//body');
        if (preg_match('/(?:founded by|created by|by)\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+){1,2})/', $bodyText, $m)) {
            $founderName = trim($m[1]);
            $founderRole = 'Founder';
        }
    }

    // Named products (Product itemtype)
    $namedProducts = [];
    foreach ($xpath->query('//*[contains(@itemtype, "Product")]//*[@itemprop="name"]') as $el) {
        $name = trim((string) $el->textContent);
        if ($name !== '' && strlen($name) < 80 && !in_array($name, $namedProducts, true)) {
            $namedProducts[] = $name;
        }
    }

    // Frameworks (trademark / registered marks)
    $frameworks = [];
    if (preg_match_all('/([A-Z][a-zA-Z\x{00C0}-\x{024F}\s]{1,40}?)[™®]/u', $html, $mm)) {
        foreach ($mm[1] as $name) {
            $clean = trim($name);
            if ($clean !== '' && strlen($clean) > 2 && !in_array($clean, $frameworks, true)) {
                $frameworks[] = $clean;
            }
        }
    }

    // Target audience
    $targetAudience = '';
    $bodyText = textOf($xpath, '//body');
    $audiencePatterns = [
        '/designed for ([^.]{5,80})/i',
        '/built for ([^.]{5,80})/i',
        '/perfect for ([^.]{5,80})/i',
        '/made for ([^.]{5,80})/i',
        '/helping ([^.]{5,80}) (?:to|grow|achieve|build|scale)/i',
        '/for ([^.]{5,60}) who (?:want|need|are|have)/i',
    ];
    foreach ($audiencePatterns as $pat) {
        if (preg_match($pat, $bodyText, $m)) {
            $targetAudience = substr(trim(preg_replace('/\s+/', ' ', $m[1]) ?? ''), 0, 120);
            break;
        }
    }

    // Social proof signals
    $socialProofSignals = [];
    $proofPatterns = [
        '/(\d[\d,]+\+?)\s+(members|students|customers|clients|users|followers|subscribers|downloads|reviews|businesses)/i',
        '/(?:trusted by|joined by|used by)\s+(\d[\d,]+\+?\s+\w+)/i',
        '/(\d[\d,]+\+?)\s+(?:5[\-\s]?star|happy|satisfied)/i',
    ];
    foreach ($proofPatterns as $pat) {
        if (preg_match_all($pat, $bodyText, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $signal = trim(preg_replace('/\s+/', ' ', $m[0]) ?? '');
                if ($signal !== '' && !in_array($signal, $socialProofSignals, true) && count($socialProofSignals) < 5) {
                    $socialProofSignals[] = $signal;
                }
            }
        }
    }

    // FAQ items — schema.org first, DOM heuristics second
    $faqItems = [];
    foreach ($xpath->query('//*[contains(@itemtype, "Question")]') as $q) {
        if (!$q instanceof DOMElement) break;
        if (count($faqItems) >= 10) break;
        $qEl = $xpath->query('.//*[@itemprop="name"]', $q)->item(0);
        $aEl = $xpath->query('.//*[@itemprop="acceptedAnswer"]//*[@itemprop="text"]', $q)->item(0);
        $question = $qEl ? trim((string) $qEl->textContent) : '';
        $answer   = $aEl ? trim((string) $aEl->textContent) : '';
        if ($question !== '' && $answer !== '') {
            $faqItems[] = ['q' => $question, 'a' => substr($answer, 0, 400)];
        }
    }
    if (count($faqItems) === 0) {
        $faqXpath =
            '//*[contains(@class, "faq")]//dt | //*[contains(@class, "faq")]//h3 | '
          . '//*[contains(@id,    "faq")]//h3 | '
          . '//*[contains(@class, "accordion")]//*[contains(@class, "title")] | '
          . '//*[contains(@class, "accordion")]//summary';
        foreach ($xpath->query($faqXpath) as $el) {
            if (count($faqItems) >= 10) break;
            $q = trim((string) $el->textContent);
            $next = $el->nextSibling;
            while ($next !== null && !($next instanceof DOMElement)) {
                $next = $next->nextSibling;
            }
            $a = $next instanceof DOMElement ? trim((string) $next->textContent) : '';
            if ($q !== '' && strlen($q) > 10 && strlen($q) < 200 && $a !== '') {
                $faqItems[] = ['q' => $q, 'a' => substr($a, 0, 400)];
            }
        }
    }

    // Blog posts
    $blogPosts = [];
    $articleXpath = '//article | //*[contains(@class, "blog-post")] | //*[contains(@class, "blog-card")] | //*[contains(@class, "post-card")]';
    foreach ($xpath->query($articleXpath) as $article) {
        if (count($blogPosts) >= 8) break;
        if (!$article instanceof DOMElement) continue;
        $titleEl = $xpath->query('.//h2 | .//h3 | .//*[contains(@class, "title")] | .//*[contains(@class, "heading")]', $article)->item(0);
        $excEl   = $xpath->query('.//p | .//*[contains(@class, "excerpt")] | .//*[contains(@class, "summary")] | .//*[contains(@class, "description")]', $article)->item(0);
        $title = $titleEl ? trim((string) $titleEl->textContent) : '';
        $excerpt = $excEl ? substr(trim((string) $excEl->textContent), 0, 200) : '';
        if ($title !== '' && strlen($title) > 5 && strlen($title) < 150) {
            $blogPosts[] = ['title' => $title, 'excerpt' => $excerpt];
        }
    }

    // Named products with descriptions
    $namedProductsWithDesc = [];
    foreach ($xpath->query('//*[contains(@itemtype, "Product")]') as $el) {
        if (count($namedProductsWithDesc) >= 8) break;
        if (!$el instanceof DOMElement) continue;
        $nameEl = $xpath->query('.//*[@itemprop="name"]', $el)->item(0);
        $descEl = $xpath->query('.//*[@itemprop="description"]', $el)->item(0);
        $name = $nameEl ? trim((string) $nameEl->textContent) : '';
        $desc = $descEl ? substr(trim((string) $descEl->textContent), 0, 300) : '';
        if ($name !== '' && strlen($name) < 80) {
            $namedProductsWithDesc[] = ['name' => $name, 'description' => $desc];
        }
    }

    // Industry keywords — from meta description
    $industryKeywords = [];
    if ($metaDescription !== '') {
        $stopWords = ['the', 'and', 'for', 'with', 'your', 'our', 'we', 'are', 'is'];
        foreach (preg_split('/\s+/', strtolower($metaDescription)) ?: [] as $word) {
            $word = preg_replace('/[^a-z0-9\-]/', '', (string) $word) ?? '';
            if (strlen($word) > 4 && !in_array($word, $stopWords, true) && count($industryKeywords) < 7) {
                $industryKeywords[] = $word;
            }
        }
    }

    // Contact email, phone, address
    $contactEmail = '';
    $mail = $xpath->query('//a[starts-with(@href, "mailto:")]')->item(0);
    if ($mail instanceof DOMElement) {
        $contactEmail = trim(explode('?', substr($mail->getAttribute('href'), 7))[0]);
    }

    $phoneNumber = '';
    $tel = $xpath->query('//a[starts-with(@href, "tel:")]')->item(0);
    if ($tel instanceof DOMElement) {
        $phoneNumber = trim(substr($tel->getAttribute('href'), 4));
    }

    $address = '';
    $addrEl = $xpath->query('//*[contains(@itemtype, "PostalAddress")] | //*[contains(@class, "address")]')->item(0);
    if ($addrEl instanceof DOMElement) {
        $address = trim(preg_replace('/\s+/', ' ', (string) $addrEl->textContent) ?? '');
    }

    // Platform detection (substring match on HTML source)
    $platform = 'Custom/Other';
    $platformChecks = [
        'WordPress'   => ['wp-content', 'wordpress'],
        'Shopify'     => ['shopify', 'cdn.shopify.com'],
        'Wix'         => ['wix.com', 'static.wixstatic.com'],
        'Squarespace' => ['squarespace'],
        'Webflow'     => ['webflow.io', 'webflow.com', 'data-wf-'],
        'Framer'      => ['framer.com', 'framerusercontent.com'],
        'Kajabi'      => ['kajabi'],
        'Ghost'       => ['ghost.io', 'ghost-theme'],
    ];
    foreach ($platformChecks as $name => $needles) {
        foreach ($needles as $n) {
            if (stripos($html, $n) !== false) {
                $platform = $name;
                break 2;
            }
        }
    }

    // Social links
    $socialLinks = [];
    $socialDomains = ['facebook.com', 'twitter.com', 'linkedin.com', 'instagram.com', 'youtube.com'];
    foreach ($xpath->query('//a[@href]') as $a) {
        if (!$a instanceof DOMElement) continue;
        if (count($socialLinks) >= 5) break;
        $href = $a->getAttribute('href');
        foreach ($socialDomains as $d) {
            if (stripos($href, $d) !== false && !in_array($href, $socialLinks, true)) {
                $socialLinks[] = $href;
                break;
            }
        }
    }

    // Site structure
    $navLinkTexts = [];
    foreach ($xpath->query('//nav//a | //*[contains(@class, "menu")]//a | //*[contains(@class, "nav")]//a') as $a) {
        $navLinkTexts[] = strtolower(trim((string) $a->textContent));
    }
    $htmlLower = strtolower($html);
    $siteStructure = [
        'hasProducts' => str_contains($htmlLower, 'product') || str_contains($htmlLower, 'shop') || str_contains($htmlLower, 'store'),
        'hasBlog'     => anyContains($navLinkTexts, 'blog') || str_contains($htmlLower, 'blog'),
        'hasServices' => anyContains($navLinkTexts, 'service') || str_contains($htmlLower, 'services'),
        'hasTeam'     => anyContains($navLinkTexts, 'team') || anyContains($navLinkTexts, 'about') || str_contains($htmlLower, 'our team'),
        'hasAbout'    => anyContains($navLinkTexts, 'about'),
        'hasContact'  => anyContains($navLinkTexts, 'contact') || $contactEmail !== '',
    ];

    // Page count estimate
    $uniqueInternal = [];
    foreach ($xpath->query('//a[@href]') as $a) {
        if (!$a instanceof DOMElement) continue;
        $href = $a->getAttribute('href');
        if ($href !== '' && str_starts_with($href, '/') && !str_contains($href, '#')) {
            $uniqueInternal[$href] = true;
        }
    }
    $pageCount = max(count($uniqueInternal), 5);

    // ---------- Phase 2 additions ----------

    // Existing schema markup
    $existingSchemaTypes = [];
    $schemaRawBlocks = [];
    foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
        $raw = trim((string) $script->textContent);
        if ($raw === '') continue;
        if (count($schemaRawBlocks) < 5) $schemaRawBlocks[] = substr($raw, 0, 2000);
        $parsed = json_decode($raw, true);
        if (!is_array($parsed)) continue;
        $items = [];
        if (isset($parsed['@graph']) && is_array($parsed['@graph'])) {
            $items = $parsed['@graph'];
        } elseif (array_keys($parsed) === range(0, count($parsed) - 1)) {
            $items = $parsed;
        } else {
            $items = [$parsed];
        }
        foreach ($items as $item) {
            $t = $item['@type'] ?? null;
            if ($t === null) continue;
            $types = is_array($t) ? $t : [$t];
            foreach ($types as $tt) {
                if (!in_array($tt, $existingSchemaTypes, true)) $existingSchemaTypes[] = $tt;
            }
        }
    }
    foreach ($xpath->query('//*[@itemtype]') as $el) {
        if (!$el instanceof DOMElement) continue;
        if (preg_match('#schema\.org/(\w+)#', $el->getAttribute('itemtype'), $m)) {
            if (!in_array($m[1], $existingSchemaTypes, true)) $existingSchemaTypes[] = $m[1];
        }
    }

    // OG / Twitter card presence
    $ogTags = [
        'title'       => hasNode($xpath, '//meta[@property="og:title"]'),
        'description' => hasNode($xpath, '//meta[@property="og:description"]'),
        'image'       => hasNode($xpath, '//meta[@property="og:image"]'),
        'type'        => hasNode($xpath, '//meta[@property="og:type"]'),
        'twitterCard' => hasNode($xpath, '//meta[@name="twitter:card"]'),
    ];

    // Canonical
    $canonicalEl = $xpath->query('//link[@rel="canonical"]')->item(0);
    $canonical = [
        'present' => $canonicalEl instanceof DOMElement,
        'url'     => $canonicalEl instanceof DOMElement ? $canonicalEl->getAttribute('href') : '',
    ];

    // Internal link inventory
    $internalLinks = [];
    $seenInternal = [];
    foreach ($xpath->query('//a[@href]') as $a) {
        if (!$a instanceof DOMElement) continue;
        if (count($internalLinks) >= 50) break;
        $href = $a->getAttribute('href');
        if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'mailto:')
            || str_starts_with($href, 'tel:') || str_starts_with($href, 'javascript:')) continue;
        $abs = absolutize($href, $websiteUrl);
        if ($abs === '') continue;
        $abs = explode('#', $abs)[0];
        if (!str_starts_with($abs, $origin)) continue;
        if (isset($seenInternal[$abs])) continue;
        $seenInternal[$abs] = true;
        $anchorText = substr(trim(preg_replace('/\s+/', ' ', (string) $a->textContent) ?? ''), 0, 120);
        $internalLinks[] = ['url' => $abs, 'anchorText' => $anchorText];
    }

    // Heading structure
    $headingStructure = [];
    foreach ($xpath->query('//h1 | //h2 | //h3') as $h) {
        if (count($headingStructure) >= 50) break;
        if (!$h instanceof DOMElement) continue;
        $level = (int) substr($h->tagName, 1);
        $text = substr(trim(preg_replace('/\s+/', ' ', (string) $h->textContent) ?? ''), 0, 200);
        if ($text !== '') {
            $headingStructure[] = ['level' => $level, 'text' => $text];
        }
    }

    // Ancillary fetches — robots.txt + sitemap.xml. 5s each, failures ignored.
    $existingRobotsTxt = ['present' => false, 'content' => ''];
    $existingSitemap   = ['present' => false, 'urlCount' => 0];
    try {
        $robots = scrapeFetch($origin . '/robots.txt', 5);
        $existingRobotsTxt = ['present' => true, 'content' => substr($robots, 0, 4000)];
    } catch (Throwable $e) { /* ignore */ }
    try {
        $sitemap = scrapeFetch($origin . '/sitemap.xml', 5);
        $urlCount = substr_count($sitemap, '<loc>');
        $existingSitemap = ['present' => true, 'urlCount' => $urlCount];
    } catch (Throwable $e) { /* ignore */ }

    return [
        'businessName'         => $businessName,
        'pageTitle'            => $pageTitle,
        'tagline'              => $tagline,
        'metaDescription'      => $metaDescription,
        'industryKeywords'     => $industryKeywords,
        'contactEmail'         => $contactEmail,
        'phoneNumber'          => $phoneNumber,
        'address'              => $address,
        'platform'             => $platform,
        'socialLinks'          => $socialLinks,
        'siteStructure'        => $siteStructure,
        'pageCount'            => $pageCount,
        'logoUrl'              => $logoUrl,
        'founderName'          => $founderName,
        'founderRole'          => $founderRole,
        'namedProducts'        => $namedProducts,
        'namedProductsWithDesc'=> $namedProductsWithDesc,
        'frameworks'           => $frameworks,
        'targetAudience'       => $targetAudience,
        'socialProofSignals'   => $socialProofSignals,
        'faqItems'             => $faqItems,
        'blogPosts'            => $blogPosts,
        'existingSchema'       => ['types' => $existingSchemaTypes, 'raw' => $schemaRawBlocks],
        'ogTags'               => $ogTags,
        'canonical'            => $canonical,
        'internalLinks'        => $internalLinks,
        'headingStructure'     => $headingStructure,
        'existingRobotsTxt'    => $existingRobotsTxt,
        'existingSitemap'      => $existingSitemap,
    ];
}

// ---------- Helpers ----------

/** cURL fetch with timeout. Throws on failure. */
function scrapeFetch(string $url, int $timeoutSeconds): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_CONNECTTIMEOUT => min(10, $timeoutSeconds),
        CURLOPT_TIMEOUT        => $timeoutSeconds,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; AEOBot/1.0; +https://cherisanerd.com/tools/alleogen)',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en',
        ],
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        if (stripos($err, 'timed out') !== false || stripos($err, 'timeout') !== false) {
            throw new RuntimeException("Request timed out: {$err}");
        }
        throw new RuntimeException("Fetch failed: {$err}");
    }
    if ($status >= 400) {
        throw new RuntimeException("HTTP {$status} from {$url}");
    }
    return (string) $body;
}

function textOf(DOMXPath $xpath, string $expr): string
{
    $node = $xpath->query($expr)->item(0);
    return $node ? trim((string) $node->textContent) : '';
}

function attrOf(DOMXPath $xpath, string $expr, string $attr): string
{
    $node = $xpath->query($expr)->item(0);
    if (!$node instanceof DOMElement) return '';
    return trim($node->getAttribute($attr));
}

function hasNode(DOMXPath $xpath, string $expr): bool
{
    return $xpath->query($expr)->length > 0;
}

/**
 * @param array<int, string> $haystack
 */
function anyContains(array $haystack, string $needle): bool
{
    foreach ($haystack as $h) {
        if (str_contains($h, $needle)) return true;
    }
    return false;
}

function absolutize(string $maybeRelative, string $base): string
{
    if ($maybeRelative === '') return '';
    if (preg_match('#^[a-z]+://#i', $maybeRelative)) return $maybeRelative;
    $b = parse_url($base);
    if (!is_array($b) || empty($b['host'])) return '';
    $scheme = $b['scheme'] ?? 'https';
    $host = $b['host'];
    $port = !empty($b['port']) ? ':' . $b['port'] : '';
    if (str_starts_with($maybeRelative, '//')) return "{$scheme}:{$maybeRelative}";
    if (str_starts_with($maybeRelative, '/'))  return "{$scheme}://{$host}{$port}{$maybeRelative}";
    $basePath = $b['path'] ?? '/';
    if (!str_ends_with($basePath, '/')) $basePath = dirname($basePath) . '/';
    return "{$scheme}://{$host}{$port}{$basePath}{$maybeRelative}";
}
