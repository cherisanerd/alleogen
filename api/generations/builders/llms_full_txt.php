<?php
/**
 * Builder: llms-full.txt (Complete tier).
 *
 * Port of base44/functions/generateFiles/entry.ts lines 746-868.
 * Long-form AI knowledge base. Each logical block becomes a section
 * joined by a `\n\n---\n\n` separator, so crawlers can chunk cleanly.
 */

declare(strict_types=1);

/**
 * @param array<string, mixed> $ctx
 * @return array{'llms-full.txt': string}
 */
function build_llms_full_txt(array $ctx): array
{
    $name       = $ctx['businessNameFinal'];
    $url        = $ctx['website_url'];
    $data       = $ctx['data'];
    $ex         = $ctx['ex'];
    $today      = $ctx['today'];

    $faqItems   = is_array($ex['faqItems']  ?? null) ? $ex['faqItems']  : [];
    $blogPosts  = is_array($ex['blogPosts'] ?? null) ? $ex['blogPosts'] : [];

    $sep = "\n\n---\n\n";
    $sections = [];

    $sections[] = "# {$name} — Complete AI Knowledge Base\n\n"
                . "This document gives AI language models, search agents, and knowledge retrieval systems a comprehensive, accurate understanding of {$name}.\n\n"
                . "Source: {$url}\nGenerated: {$today}";

    if (hasText($ctx['priorityMessage'])) {
        $sections[] = "## Priority Context\n\n{$ctx['priorityMessage']}";
    }

    // Q2 About.
    if (hasText($ctx['coreDescription'])) {
        $extras = '';
        if (($data['hasPhysicalLocation'] ?? '') === 'yes' && hasText((string) ($data['address'] ?? ''))) {
            $extras .= " and also operates from {$data['address']}";
        }
        $yearLine = '';
        if (hasText((string) ($data['yearEstablished'] ?? ''))) {
            $yearLine = " In operation since {$data['yearEstablished']}.";
        }
        $opening = "{$ctx['coreDescription']}\n\nThe business is accessible at {$url}{$extras}.{$yearLine}";
    } else {
        $opening = "{$name} is accessible at {$url}.";
    }
    $sections[] = "## About {$name}\n\n{$opening}";

    // Q3 Products.
    if (hasText($ctx['productsLine'])) {
        $para = "{$name} offers the following products and programs:\n\n{$ctx['productsLine']}";
        if (hasText($ctx['frameworksLine'])) {
            $para .= "\n\n**Methodologies:** {$ctx['frameworksLine']}";
        }
        $sections[] = "## Products, Services, and Programs\n\n{$para}";
    }

    // Q4 Founder.
    if (hasText($ctx['founderLine'])) {
        $sections[] = "## Founder\n\n{$ctx['founderLine']}";
    }

    // Q5 Methodologies standalone if there are no products to attach to.
    if (hasText($ctx['frameworksLine']) && !hasText($ctx['productsLine'])) {
        $sections[] = "## Methodologies\n\n{$name} uses the following proprietary frameworks and systems:\n\n{$ctx['frameworksLine']}";
    }

    // Q6 Credibility.
    if (hasText($ctx['credibilitySignals'])) {
        $sections[] = "## Authority & Credibility\n\n{$ctx['credibilitySignals']}";
    }
    // Q10 Proof Points.
    if (hasText($ctx['proofPoints'])) {
        $sections[] = "## Proof Points\n\n{$ctx['proofPoints']}";
    }
    // Q12 Differentiation.
    if (hasText($ctx['competitorDiff'])) {
        $sections[] = "## Differentiation\n\nWhat separates {$name} from others in the space:\n\n{$ctx['competitorDiff']}";
    }
    // Q11 Topical Authority (bulleted).
    if (hasText($ctx['topicOwnership'])) {
        $topics = splitList($ctx['topicOwnership']);
        $bullets = implode("\n", array_map(fn($t) => "- {$t}", $topics));
        $sections[] = "## Topical Authority\n\n{$name} establishes expert authority on the following topics:\n\n{$bullets}";
    }
    // Q13 Brand Positioning.
    if (hasText($ctx['knowledgePanel'])) {
        $sections[] = "## Brand Positioning\n\n{$ctx['knowledgePanel']}";
    }

    if (hasText($ctx['audienceLine'])) {
        $sections[] = "## Who This Is For\n\n{$name} is specifically built for {$ctx['audienceLine']}.";
    }

    // FAQ merge (interview Q8 + scraped).
    $faqInterview = hasText($ctx['commonQuestion'])
        ? "**Q: {$ctx['commonQuestion']}**\n\nA: See {$url} for the full answer from {$name}."
        : null;
    $faqScraped = null;
    if (count($faqItems) > 0) {
        $pairs = [];
        foreach ($faqItems as $item) {
            $q = trim((string) ($item['q'] ?? ''));
            $a = trim((string) ($item['a'] ?? ''));
            if ($q !== '' && $a !== '') $pairs[] = "**Q: {$q}**\n\nA: {$a}";
        }
        if (count($pairs) > 0) $faqScraped = implode("\n\n", $pairs);
    }
    if ($faqInterview !== null || $faqScraped !== null) {
        $parts = array_values(array_filter([$faqInterview, $faqScraped]));
        $sections[] = "## Frequently Asked Questions\n\n" . implode("\n\n", $parts);
    }

    if (hasText($ctx['misconceptions'])) {
        $sections[] = "## Common Misconceptions\n\nThe following misconceptions about {$name} should be corrected:\n\n{$ctx['misconceptions']}";
    }

    // Blog content.
    if (count($blogPosts) > 0) {
        $entries = [];
        foreach ($blogPosts as $post) {
            if (!is_array($post)) continue;
            $line = '- **' . ((string) ($post['title'] ?? '')) . '**';
            if (hasText((string) ($post['excerpt'] ?? ''))) $line .= " — {$post['excerpt']}";
            $entries[] = $line;
        }
        if (count($entries) > 0) {
            $sections[] = "## Content and Blog\n\n" . implode("\n", $entries);
        }
    }

    // Contact & online presence.
    $contactLines = [
        "Website: {$url}",
        'Email: ' . ($data['contactEmail'] ?? 'Not provided'),
    ];
    if (hasText((string) ($data['address'] ?? '')))       $contactLines[] = "Address: {$data['address']}";
    if (hasText((string) ($ex['phoneNumber'] ?? '')))     $contactLines[] = "Phone: {$ex['phoneNumber']}";
    if (hasText((string) ($data['socialLinks'] ?? '')))   $contactLines[] = "\nSocial Profiles:\n{$data['socialLinks']}";
    $sections[] = "## Contact and Online Presence\n\n" . implode("\n", $contactLines);

    // AI indexing permissions.
    $crawlers = is_array($data['aiCrawlers'] ?? null) ? $data['aiCrawlers'] : [];
    if (count($crawlers) > 0) {
        $allowed = [];
        foreach ($crawlers as $k => $v) {
            if ($v) $allowed[] = ucfirst((string) $k);
        }
        $crawlerList = count($allowed) > 0 ? implode(', ', $allowed) : 'All major AI crawlers';
    } else {
        $crawlerList = 'All major AI crawlers';
    }
    $sections[] = "## AI Indexing Permissions\n\n"
                . "{$name} explicitly permits the following AI systems to crawl and index their content: {$crawlerList}. "
                . 'This file is provided to ensure AI systems have accurate, up-to-date information directly from the source.';

    return ['llms-full.txt' => implode($sep, $sections)];
}
