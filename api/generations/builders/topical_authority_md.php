<?php
/**
 * Builder: topical-authority-plan.md (Complete tier).
 *
 * Port of base44/functions/generateFiles/entry.ts lines 1212-1279.
 * 5-8 topic clusters keyed off Q11 topicOwnership (fallback to
 * scraped industryKeywords), Authority Signals, Internal Linking
 * Map, 90-Day Action Plan.
 */

declare(strict_types=1);

/**
 * @param array<string, mixed> $ctx
 * @return array{'topical-authority-plan.md': string}
 */
function build_topical_authority_md(array $ctx): array
{
    $name  = $ctx['businessNameFinal'];
    $today = $ctx['today'];

    $topicList = splitList($ctx['topicOwnership']);
    if (count($topicList) === 0 && is_array($ctx['ex']['industryKeywords'] ?? null)) {
        $topicList = array_slice($ctx['ex']['industryKeywords'], 0, 5);
    }
    $topicList = array_slice($topicList, 0, 8);

    $blogPosts = is_array($ctx['ex']['blogPosts'] ?? null) ? $ctx['ex']['blogPosts'] : [];

    $clusters = [];
    foreach ($topicList as $topic) {
        $needle  = strtolower((string) $topic);
        $covered = false;
        foreach ($blogPosts as $post) {
            if (!is_array($post)) continue;
            if (str_contains(strtolower((string) ($post['title'] ?? '')), $needle)) {
                $covered = true;
                break;
            }
        }
        $gap = $covered
            ? 'Existing blog coverage found. Strengthen with pillar depth and internal links.'
            : 'No blog coverage detected. Publish at least 1 pillar article + 3 supporting FAQs.';

        $clusters[] = implode("\n", [
            "### {$topic}",
            '',
            "**Pillar topic:** {$topic}",
            '**Suggested subtopics:**',
            "- Fundamentals and core definitions for {$topic}",
            "- {$name}'s point of view on {$topic}",
            "- Case studies and proof points in {$topic}",
            "- Common questions and misconceptions about {$topic}",
            "- Comparison: {$topic} vs adjacent topics",
            '',
            "**Content gap:** {$gap}",
            '**Recommended content types:** pillar blog post, FAQ page, case study, comparison article.',
        ]);
    }

    $firstTopic  = $topicList[0] ?? 'your niche';
    $secondTopic = $topicList[1] ?? 'your space';

    $authoritySignals = implode("\n", [
        "- Guest appearances on podcasts within {$firstTopic}.",
        '- Backlinks from trade publications, industry blogs, and partner sites.',
        "- Quotes in press or newsletters focused on {$secondTopic}.",
        '- Speaker slots at relevant conferences or virtual summits.',
        '- Citations in research reports, whitepapers, or "best of" roundups.',
    ]);

    $ninetyDay = implode("\n\n", [
        '**Month 1 — Pillar content.** Publish one pillar article per top-priority topic cluster (aim for 1,500–2,500 words). Link to existing pages and to schema-faqpage.json content.',
        '**Month 2 — Supporting content.** Produce 3–5 supporting FAQs or short posts per pillar. Update entity-map.json if new products, topics, or people emerge.',
        '**Month 3 — Authority building.** Pitch guest posts, podcast appearances, and press mentions targeting the authority signals above. Update geo-qa-snippets.json with any new quotable answers you produce.',
    ]);

    $md = implode("\n", [
        "# Topical Authority Plan — {$name}",
        '',
        "Generated: {$today}",
        '',
        'This plan is the content roadmap that pairs with your schema, llm.txt, and GEO brief. Execute in order. AI systems reward consistent, deep coverage of a focused topic set — not broad, shallow coverage.',
        '',
        '---',
        '',
        '## 1. Topic Clusters',
        '',
        count($clusters) > 0 ? implode("\n\n", $clusters) : '*No topics captured. Answer Q11 (topic ownership) to generate a targeted cluster plan.*',
        '',
        '## 2. Authority Signals to Build',
        '',
        $authoritySignals,
        '',
        '## 3. Internal Linking Map',
        '',
        '- Every pillar article links to its supporting FAQs and case studies, and vice versa.',
        '- Each topic cluster links to the About page (schema-person.json) to reinforce E-E-A-T.',
        '- Cross-link related topics with descriptive anchor text, not "click here".',
        '',
        '## 4. 90-Day Action Plan',
        '',
        $ninetyDay,
        '',
        '---',
        '',
        '*Refresh this plan every quarter. Re-run the generator after publishing new pillar content so the scraper picks up your progress.*',
    ]);

    return ['topical-authority-plan.md' => $md];
}
