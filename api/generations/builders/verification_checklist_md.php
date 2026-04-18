<?php
/**
 * Builder: Verification_Checklist.md (Complete tier).
 *
 * Port of base44/functions/generateFiles/entry.ts lines 1308-1374.
 * v2.0 post-implementation checklist covering the full SEO + AEO +
 * GEO rollout.
 */

declare(strict_types=1);

/**
 * @param array<string, mixed> $ctx
 * @return array{'Verification_Checklist.md': string}
 */
function build_verification_checklist_md(array $ctx): array
{
    $name  = $ctx['businessNameFinal'];
    $today = $ctx['today'];
    $url   = $ctx['website_url'];
    $hasLocal = ($ctx['data']['hasPhysicalLocation'] ?? '') === 'yes';

    $lines = [
        "# Implementation Verification Checklist — {$name}",
        '',
        "Package: **Complete**  |  Generated: {$today}",
        '',
        'Work top to bottom. Everything below should be checked before you consider the rollout done.',
        '',
        '## Pre-Implementation',
        '- [ ] Back up current site files and existing schema/meta tags',
        '- [ ] Review `seo-audit.md` and note which quick wins apply',
        '- [ ] Read `geo-content-brief.md` and identify which claims need copy updates',
        '',
        '## File Upload Verification (AEO + SEO)',
        "- [ ] llm.txt accessible at {$url}/llm.txt",
        "- [ ] llms.txt accessible at {$url}/llms.txt",
        "- [ ] llms-full.txt accessible at {$url}/llms-full.txt",
        "- [ ] robots.txt accessible at {$url}/robots.txt",
        "- [ ] sitemap.xml accessible at {$url}/sitemap.xml",
        "- [ ] ai-sitemap.xml accessible at {$url}/ai-sitemap.xml (backward compat)",
        "- [ ] humans.txt accessible at {$url}/humans.txt",
        "- [ ] security.txt accessible at {$url}/security.txt",
        "- [ ] .well-known/ai.json accessible at {$url}/.well-known/ai.json",
        '',
        '## Schema Implementation (SEO)',
        '- [ ] schema-organization.json installed (enhanced with knowsAbout + hasOfferCatalog)',
        '- [ ] schema-website.json installed',
        '- [ ] schema-webpage.json installed',
        '- [ ] schema-faqpage.json installed (if FAQ content is present)',
        '- [ ] schema-person.json installed (E-E-A-T signal)',
        '- [ ] schema-breadcrumb.json installed',
        '- [ ] schema-article.json template applied to at least one blog post',
    ];
    if ($hasLocal) {
        $lines[] = '- [ ] schema-localbusiness.json installed (geo coordinates + opening hours updated from placeholders)';
    }
    $lines = array_merge($lines, [
        '- [ ] meta-tags.html Open Graph + Twitter Card block pasted into <head>',
        '- [ ] Every schema file passes https://validator.schema.org',
        '- [ ] FAQ + Person + Organization validated at https://search.google.com/test/rich-results',
        '',
        '## AI Crawler Configuration (AEO)',
        '- [ ] robots.txt allows your chosen AI crawlers',
        "- [ ] Sitemap directive in robots.txt points at {$url}/sitemap.xml",
        '- [ ] Sitemap submitted to Google Search Console and Bing Webmaster Tools',
        '',
        '## GEO Rollout',
        '- [ ] Brand Positioning Statement from geo-content-brief.md used on About + hero',
        '- [ ] Citable Claim Blocks placed in PR boilerplate, press kit, and key landing pages',
        '- [ ] Quotable Expert Answers published as a FAQ page',
        '- [ ] Topical Authority Plan Month 1 pillar content scheduled or drafted',
        '- [ ] entity-map.json reviewed for accuracy (names, roles, relationships, claims)',
        '- [ ] geo-qa-snippets.json reviewed and approved for citation',
        '',
        '## Testing',
        '- [ ] All generated files return HTTP 200 in a private window',
        '- [ ] Schema validates without errors',
        '- [ ] Mobile-friendly test passed',
        '- [ ] Lighthouse SEO score ≥ 90',
        '- [ ] Open Graph preview verified at https://www.opengraph.xyz',
        '',
        '## Post-Implementation',
        '- [ ] Calendar reminder set for quarterly re-generation',
        '- [ ] Search Console monitoring configured',
        '- [ ] Team knows where llm.txt / llms.txt / the GEO brief live',
        '',
        '---',
        '',
        'Completed: ___/___/___    By: _______________',
    ]);

    return ['Verification_Checklist.md' => implode("\n", $lines)];
}
