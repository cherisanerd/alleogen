<?php
/**
 * Builder: robots.txt
 *
 * Port of base44/functions/generateFiles/entry.ts lines 151-154.
 * AI crawler directives + Sitemap entries. Respects data.aiCrawlers
 * toggles (chatgpt/google/anthropic/perplexity) so users can opt
 * specific bots out.
 */

declare(strict_types=1);

/**
 * @param array<string, mixed> $ctx
 * @return array{'robots.txt': string}
 */
function build_robots_txt(array $ctx): array
{
    $crawlers = is_array($ctx['data']['aiCrawlers'] ?? null) ? $ctx['data']['aiCrawlers'] : [];

    // Default to allow unless explicitly toggled off.
    $allow = fn(string $k): bool => ($crawlers[$k] ?? true) !== false;

    $line = fn(string $label, string $agent, bool $allowed): string =>
        "# {$label}\nUser-agent: {$agent}\n" . ($allowed ? 'Allow: /' : 'Disallow: /') . "\n";

    $url = $ctx['website_url'];

    $out  = "User-agent: *\nAllow: /\n\n";
    $out .= $line('ChatGPT',    'GPTBot',            $allow('chatgpt'));
    $out .= $line('Google Bard','Google-Extended',   $allow('google'));
    $out .= $line('Claude',     'anthropic-ai',      $allow('anthropic'));
    $out .= $line('Perplexity', 'PerplexityBot',     $allow('perplexity'));
    $out .= "\n# Meta\nUser-agent: Meta-ExternalAgent\nAllow: /\n\n";
    $out .= "# Amazon\nUser-agent: Amazonbot\nAllow: /\n\n";
    $out .= "# Apple\nUser-agent: Applebot\nAllow: /\n\n";
    $out .= "# Cohere\nUser-agent: cohere-ai\nAllow: /\n\n";
    $out .= "# AI2\nUser-agent: AI2Bot\nAllow: /\n\n";
    $out .= "Sitemap: {$url}/sitemap.xml\nSitemap: {$url}/ai-sitemap.xml";

    return ['robots.txt' => $out];
}
