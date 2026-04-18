<?php
/**
 * Builder: llms.txt (Complete tier).
 *
 * Port of base44/functions/generateFiles/entry.ts lines 676-744.
 * Extended AI context — longer than llm.txt with Priority / About /
 * Leadership / Products / Authority / Proof Points / Differentiation /
 * Topical Authority / Audience / FAQ / Misconceptions / Contact blocks.
 */

declare(strict_types=1);

/**
 * @param array<string, mixed> $ctx
 * @return array{'llms.txt': string}
 */
function build_llms_txt(array $ctx): array
{
    $name   = $ctx['businessNameFinal'];
    $url    = $ctx['website_url'];
    $data   = $ctx['data'];

    $parts = [];
    $parts[] = "# {$name} — Extended AI Context";

    if (hasText($ctx['priorityMessage'])) {
        $parts[] = "\n## Priority Context";
        $parts[] = $ctx['priorityMessage'];
    }

    $parts[] = "\n## About the Organization";
    $about = hasText($ctx['coreDescription'])
        ? $ctx['coreDescription']
        : "{$name} is accessible at {$url}.";
    if (($data['hasPhysicalLocation'] ?? '') === 'yes' && hasText((string) ($data['address'] ?? ''))) {
        $about .= " Based in {$data['address']}.";
    }
    if (hasText((string) ($data['yearEstablished'] ?? ''))) {
        $about .= " In operation since {$data['yearEstablished']}.";
    }
    $parts[] = $about;

    if (hasText($ctx['founderLine'])) {
        $parts[] = "\n## Leadership";
        $parts[] = $ctx['founderLine'];
    }

    if (hasText($ctx['productsLine'])) {
        $parts[] = "\n## Products, Services, and Programs";
        $services = $ctx['productsLine'];
        if (hasText($ctx['frameworksLine'])) {
            $services .= "\n\nProprietary methodologies and frameworks: {$ctx['frameworksLine']}.";
        }
        $parts[] = $services;
    }

    if (hasText($ctx['credibilitySignals'])) {
        $parts[] = "\n## Authority & Credibility";
        $parts[] = $ctx['credibilitySignals'];
    }
    if (hasText($ctx['proofPoints'])) {
        $parts[] = "\n## Proof Points";
        $parts[] = $ctx['proofPoints'];
    }
    if (hasText($ctx['competitorDiff'])) {
        $parts[] = "\n## Differentiation";
        $parts[] = $ctx['competitorDiff'];
    }
    if (hasText($ctx['topicOwnership'])) {
        $parts[] = "\n## Topical Authority";
        $parts[] = "{$name} establishes expert authority on the following topics:\n\n{$ctx['topicOwnership']}";
    }
    if (hasText($ctx['audienceLine'])) {
        $parts[] = "\n## Target Audience";
        $parts[] = "{$name} is specifically designed for {$ctx['audienceLine']}.";
    }
    if (hasText($ctx['commonQuestion'])) {
        $parts[] = "\n## Frequently Asked";
        $parts[] = "Q: {$ctx['commonQuestion']}";
    }
    if (hasText($ctx['misconceptions'])) {
        $parts[] = "\n## Common Misconceptions";
        $parts[] = $ctx['misconceptions'];
    }

    $parts[] = "\n## Contact and Presence";
    $presence = [
        "Website: {$url}",
        'Email: ' . ($data['contactEmail'] ?? 'Not provided'),
    ];
    if (hasText((string) ($data['address'] ?? ''))) {
        $presence[] = "Address: {$data['address']}";
    }
    if (hasText((string) ($data['socialLinks'] ?? ''))) {
        $presence[] = "Social profiles:\n{$data['socialLinks']}";
    }
    $parts[] = implode("\n", $presence);

    return ['llms.txt' => implode("\n", $parts)];
}
