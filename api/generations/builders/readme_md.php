<?php
/**
 * Builder: README.md (inside the generated zip).
 *
 * Port of base44/functions/generateFiles/entry.ts lines 545-547.
 * Short onboarding README listing the files in the tier.
 */

declare(strict_types=1);

/**
 * @param array<string, mixed> $ctx
 * @return array{'README.md': string}
 */
function build_readme_md(array $ctx): array
{
    $tier       = $ctx['package_tier'];
    $data       = $ctx['data'];
    $hasLocal   = ($data['hasPhysicalLocation'] ?? '') === 'yes';
    $today      = $ctx['today'];
    $businessDb = $ctx['business_name'];
    $url        = $ctx['website_url'];
    $email      = (string) ($data['contactEmail'] ?? 'support');

    $fileBlock = $tier === 'basic'
        ? "### Basic Package:\n"
          . "- llm.txt - AI-readable business information\n"
          . "- robots.txt - AI crawler permissions + sitemap directive\n"
          . "- sitemap.xml - Standard sitemap (submit to search engines)\n"
          . "- ai-sitemap.xml - Legacy filename kept for backward compatibility\n"
          . "- schema-organization.json - Organization structured data (SEO + AEO)\n"
          . "- schema-website.json - Website structured data (SEO)\n"
          . "- schema-faqpage.json - FAQ schema for rich results + AI Overview citations (when FAQ content is available)\n"
          . "- schema-person.json - Person/E-E-A-T schema (when founder data is available)\n"
          . "- meta-tags.html - Open Graph + Twitter Card copy-paste block\n"
          . "- seo-audit.md - Technical SEO audit with platform-specific fixes\n"
          . "- README.md - This file\n"
          . "- Implementation_Guide.md - Step-by-step implementation instructions"
        : "### Complete Package (includes all Basic files plus):\n"
          . "- llms.txt - Extended AI context\n"
          . "- llms-full.txt - Comprehensive AI dataset\n"
          . "- humans.txt - Human-readable site info\n"
          . "- security.txt - Security contact information\n"
          . "- .well-known/ai.json - AI configuration (business name, services, allowed crawlers)\n"
          . "- schema-webpage.json - WebPage structured data\n"
          . "- schema-breadcrumb.json - Breadcrumb navigation schema\n"
          . ($hasLocal ? "- schema-localbusiness.json - LocalBusiness structured data\n" : '')
          . "- Verification_Checklist.md - Post-implementation checklist";

    $md = "# AEO Package - {$businessDb}\n\n"
        . "## Package Type: " . strtoupper($tier) . "\n\n"
        . "This package contains SEO + AEO + GEO optimized files for your website: {$url}\n\n"
        . "## Files Included\n\n"
        . $fileBlock . "\n\n"
        . "## Quick Start\n\n"
        . "1. Read the Implementation_Guide.md file\n"
        . "2. Upload files to your website root directory\n"
        . "3. Paste meta-tags.html into your site's <head> section\n"
        . "4. Submit sitemap.xml to Google Search Console\n"
        . "5. Verify schema with Google Rich Results Test\n\n"
        . "## Support\n\n"
        . "For questions or issues, contact: {$email}\n\n"
        . "Generated: {$today}\n"
        . "Expires: 90 days from generation\n";

    return ['README.md' => $md];
}
