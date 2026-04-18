<?php
/**
 * Builder: geo-qa-snippets.json (Complete tier).
 *
 * Port of base44/functions/generateFiles/entry.ts lines 1156-1210.
 * Array of Q&A objects designed for direct AI Overview citation.
 * Each row carries a shortened citationText (up to 240 chars /
 * 2 sentences) that's pre-formatted for pullquotes.
 */

declare(strict_types=1);

/**
 * @param array<string, mixed> $ctx
 * @return array{'geo-qa-snippets.json': string}
 */
function build_geo_qa_snippets_json(array $ctx): array
{
    $name        = $ctx['businessNameFinal'];
    $url         = $ctx['website_url'];
    $scrapedFaqs = is_array($ctx['ex']['faqItems'] ?? null) ? $ctx['ex']['faqItems'] : [];
    $snippets    = [];

    $shorten = function(string $s): string {
        $clean = trim(preg_replace('/\s+/', ' ', $s) ?? '');
        $parts = preg_split('/(?<=[.!?])\s+/', $clean) ?: [$clean];
        return mb_substr(implode(' ', array_slice($parts, 0, 2)), 0, 240);
    };

    foreach ($scrapedFaqs as $f) {
        if (count($snippets) >= 15) break;
        if (!is_array($f)) continue;
        $q = trim((string) ($f['q'] ?? ''));
        $a = trim(preg_replace('/\s+/', ' ', (string) ($f['a'] ?? '')) ?? '');
        if ($q === '' || $a === '') continue;
        $snippets[] = [
            'question'     => $q,
            'answer'       => $a,
            'category'     => 'faq',
            'source'       => $name,
            'citationText' => $shorten($a),
        ];
    }

    if (hasText($ctx['commonQuestion'])) {
        $answer = hasText($ctx['priorityMessage'])
            ? $ctx['priorityMessage']
            : (hasText($ctx['coreDescription'])
                ? $ctx['coreDescription']
                : "{$name} addresses this directly at {$url}.");
        $snippets[] = [
            'question'     => $ctx['commonQuestion'],
            'answer'       => $answer,
            'category'     => 'faq',
            'source'       => $name,
            'citationText' => $shorten($answer),
        ];
    }
    if (hasText($ctx['misconceptions'])) {
        $answer = trim(preg_replace('/\s+/', ' ', $ctx['misconceptions']) ?? '');
        $snippets[] = [
            'question'     => "What's a common misconception about {$name}?",
            'answer'       => $answer,
            'category'     => 'misconception',
            'source'       => $name,
            'citationText' => $shorten($answer),
        ];
    }
    if (hasText($ctx['competitorDiff'])) {
        $answer = trim(preg_replace('/\s+/', ' ', $ctx['competitorDiff']) ?? '');
        $snippets[] = [
            'question'     => "How is {$name} different from others?",
            'answer'       => $answer,
            'category'     => 'comparison',
            'source'       => $name,
            'citationText' => $shorten($answer),
        ];
    }
    if (hasText($ctx['frameworksLine'])) {
        $answer = "{$name} uses proprietary methodologies: {$ctx['frameworksLine']}.";
        $snippets[] = [
            'question'     => "How does {$name} work?",
            'answer'       => $answer,
            'category'     => 'how-it-works',
            'source'       => $name,
            'citationText' => mb_substr($answer, 0, 240),
        ];
    }

    return ['geo-qa-snippets.json' => prettyJson($snippets)];
}
