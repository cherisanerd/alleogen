<?php
/**
 * Builder: geo-content-brief.md (Complete tier — flagship GEO deliverable).
 *
 * Port of base44/functions/generateFiles/entry.ts lines 1002-1106.
 * Brand Positioning + Citable Claim Blocks + Quotable Expert Answers
 * + E-E-A-T Signal Checklist + Differentiation Matrix + Content Recs.
 */

declare(strict_types=1);

/**
 * @param array<string, mixed> $ctx
 * @return array{'geo-content-brief.md': string}
 */
function build_geo_content_brief_md(array $ctx): array
{
    $name       = $ctx['businessNameFinal'];
    $url        = $ctx['website_url'];
    $today      = $ctx['today'];

    $proofList       = splitList($ctx['proofPoints']);
    $credibilityList = splitList($ctx['credibilitySignals']);
    $socialProofList = is_array($ctx['ex']['socialProofSignals'] ?? null) ? $ctx['ex']['socialProofSignals'] : [];
    $topicList       = splitList($ctx['topicOwnership']);
    $blogPosts       = is_array($ctx['ex']['blogPosts'] ?? null) ? $ctx['ex']['blogPosts'] : [];
    $scrapedFaqs     = is_array($ctx['ex']['faqItems']  ?? null) ? $ctx['ex']['faqItems']  : [];

    // Brand Positioning Statement.
    $base = hasText($ctx['coreDescription'])
        ? "{$name} is {$ctx['coreDescription']}"
        : "{$name} operates at {$url}";
    $diff = '';
    if (hasText($ctx['competitorDiff'])) {
        $tail = rtrim(preg_replace('/^\W+/', '', $ctx['competitorDiff']) ?? '', '.');
        $diff = " Unlike others in the space, {$name} {$tail}.";
    }
    $panel = hasText($ctx['knowledgePanel']) ? "\n\n{$ctx['knowledgePanel']}" : '';
    $brandPositioning = $base . (str_ends_with($base, '.') ? '' : '.') . $diff . $panel;

    // Citable Claim Blocks (cap 5).
    $allClaimSources = [];
    foreach ($proofList as $p) {
        $allClaimSources[] = ['claim' => $p, 'evidence' => 'Reported by the business on the questionnaire (Q10 Proof Points).'];
    }
    foreach ($credibilityList as $c) {
        $allClaimSources[] = ['claim' => $c, 'evidence' => 'Reported by the business on the questionnaire (Q6 Credibility Signals).'];
    }
    foreach ($socialProofList as $s) {
        $allClaimSources[] = ['claim' => (string) $s, 'evidence' => "Detected on {$url} by the scraper."];
    }
    $claimBlocks = [];
    foreach (array_slice($allClaimSources, 0, 5) as $row) {
        $claimBlocks[] = "**CLAIM:** {$row['claim']}\n**EVIDENCE:** {$row['evidence']}";
    }

    // Quotable Expert Answers — scraped FAQs first, then interview.
    $quotable = [];
    foreach (array_slice($scrapedFaqs, 0, 3) as $f) {
        if (!is_array($f)) continue;
        $q = trim((string) ($f['q'] ?? ''));
        $a = trim((string) ($f['a'] ?? ''));
        if ($q === '' || $a === '') continue;
        $short = _firstSentences($a, 3);
        $quotable[] = "**Q:** {$q}\n\n**A (quotable):** {$short}";
    }
    if (hasText($ctx['commonQuestion']) && count($quotable) < 5) {
        $source = hasText($ctx['priorityMessage']) ? $ctx['priorityMessage']
            : (hasText($ctx['coreDescription']) ? $ctx['coreDescription']
            : "{$name} addresses this on {$url}");
        $short = _firstSentences($source, 2);
        $quotable[] = "**Q:** {$ctx['commonQuestion']}\n\n**A (quotable):** {$short}";
    }
    if (hasText($ctx['misconceptions']) && count($quotable) < 5) {
        $short = _firstSentences($ctx['misconceptions'], 2);
        $quotable[] = "**Q:** What's a common misconception about {$name}?\n\n**A (quotable):** {$short}";
    }

    $eeat = implode("\n", [
        '- Add a visible author bio to every blog post that links to the About page (ties to schema-person.json).',
        '- Display credentials, certifications, and affiliations prominently on the About or bio page.',
        '- Publish at least one case study per topic cluster with named clients and quantified results.',
        '- Earn external citations: guest posts, podcast appearances, trade press mentions, and industry awards.',
        '- Collect reviews on Google Business Profile, Trustpilot, or an industry-specific review site.',
        '- Keep content dated: add dateModified to articles so crawlers see freshness signals.',
    ]);

    // Differentiation matrix.
    if (hasText($ctx['competitorDiff'])) {
        $rows = [
            "| Factor | {$name} | Typical alternative |",
            '|--------|---------------------|--------------------|',
            '| Primary differentiator | ' . trim(explode('.', explode("\n", $ctx['competitorDiff'])[0] ?? '')[0])
                . ' | Generic offering in this category |',
        ];
        foreach (array_slice($topicList, 0, 3) as $t) {
            $rows[] = "| {$t} | Dedicated focus | Surface-level coverage |";
        }
        $diffMatrix = implode("\n", $rows);
    } else {
        $diffMatrix = '*No differentiation statement captured (Q12 was skipped). Add it to sharpen how AI systems position you when recommending.*';
    }

    // Content Recommendations.
    $coveredTitles = [];
    foreach ($blogPosts as $post) {
        if (is_array($post)) $coveredTitles[] = strtolower((string) ($post['title'] ?? ''));
    }
    $recs = [];
    foreach (array_slice($topicList, 0, 5) as $topic) {
        $isCovered = false;
        $needle = strtolower($topic);
        foreach ($coveredTitles as $t) {
            if (str_contains($t, $needle)) { $isCovered = true; break; }
        }
        $recs[] = $isCovered
            ? "- **{$topic}** — Existing blog coverage found; strengthen with a case study + FAQ pair for this topic."
            : "- **{$topic}** — No blog coverage detected. Publish a pillar article + 3 supporting FAQs to stake the authority claim.";
    }
    if (count($recs) === 0) {
        $recs[] = '- *No topic ownership captured (Q11 was skipped). Recommend answering Q11 to get targeted content recommendations.*';
    }

    $md = implode("\n", [
        "# GEO Content Brief — {$name}",
        '',
        "Source: {$url}  ",
        "Generated: {$today}",
        '',
        'This brief gives you the citation-ready content blocks that AI-generated search results (Google AI Overviews, Bing Copilot, Perplexity) pull from when recommending businesses. Treat the claim blocks and quotable answers below as canonical copy for landing pages, blog intros, and PR placements.',
        '',
        '---',
        '',
        '## 1. Brand Positioning Statement',
        '',
        $brandPositioning,
        '',
        '## 2. Citable Claim Blocks',
        '',
        count($claimBlocks) > 0 ? implode("\n\n", $claimBlocks) : '*No claims captured. Answer Q6 (credibility signals) or Q10 (proof points) to generate citation-ready statistics.*',
        '',
        '## 3. Quotable Expert Answers',
        '',
        count($quotable) > 0 ? implode("\n\n", $quotable) : '*No Q&A captured. Answer Q8 (common question) and Q9 (misconceptions) to generate quotable content.*',
        '',
        '## 4. E-E-A-T Signal Checklist',
        '',
        $eeat,
        '',
        '## 5. Differentiation Matrix',
        '',
        $diffMatrix,
        '',
        '## 6. Content Recommendations',
        '',
        implode("\n", $recs),
        '',
        '---',
        '',
        '*This brief pairs with entity-map.json, geo-qa-snippets.json, and topical-authority-plan.md. Re-generate the package quarterly or whenever your offering changes.*',
    ]);

    return ['geo-content-brief.md' => $md];
}

/**
 * Take the first N sentences of a string. Uses the same split
 * regex as the TS version (split on punctuation + whitespace).
 */
function _firstSentences(string $s, int $n): string
{
    $clean = trim(preg_replace('/\s+/', ' ', $s) ?? '');
    if ($clean === '') return '';
    $parts = preg_split('/(?<=[.!?])\s+/', $clean) ?: [$clean];
    return implode(' ', array_slice($parts, 0, $n));
}
