# AEO File Generator — SEO + AEO + GEO Upgrade PRD

**Version:** 2.0  
**Date:** April 16, 2026  
**Prepared for:** Claude Code Implementation  
**Owner:** Hot Hand Media

---

## 1. Executive Summary

The AEO File Generator is an existing Base44-built SaaS application that scrapes a user's website, conducts a short interview, and generates a downloadable zip of AI-optimized files (llm.txt, robots.txt, schema.org JSON, etc.). It currently addresses **Answer Engine Optimization (AEO)** only.

This PRD defines the upgrade to a **three-discipline tool** covering SEO, AEO, and GEO (Generative Engine Optimization). The tool will be expanded so that a single generation produces a comprehensive file package addressing all three areas of AI and search visibility.

### 1.1 Definitions

| Term | Full Name | What It Optimizes For |
|------|-----------|----------------------|
| SEO | Search Engine Optimization | Traditional search rankings (Google, Bing organic results) |
| AEO | Answer Engine Optimization | AI answer engines (ChatGPT, Claude, Perplexity direct answers) |
| GEO | Generative Engine Optimization | AI-generated search results (Google AI Overviews, Bing Copilot, Perplexity citations) |

### 1.2 Goal

Upgrade the tool so that a single generation produces files, schema, and content briefs that cover all three disciplines. No new infrastructure is needed. The changes are: additional files in the generator, expanded questionnaire fields, and smarter use of scraped data.

---

## 2. Current State Audit

### 2.1 Current File Manifest

The generator currently produces the following files across two tiers:

| File | Tier | Discipline | Status |
|------|------|-----------|--------|
| llm.txt | Basic | AEO | ✅ Keep as-is |
| robots.txt | Basic | AEO + SEO | 🟡 Enhance |
| ai-sitemap.xml | Basic | AEO | 🟡 Replace with full sitemap |
| schema-organization.json | Basic | SEO + AEO | 🟡 Enhance |
| schema-website.json | Basic | SEO | ✅ Keep as-is |
| README.md | Basic | Docs | 🟡 Update for new files |
| Implementation_Guide.md | Basic | Docs | 🔴 Major rewrite needed |
| llms.txt | Complete | AEO | ✅ Keep as-is |
| llms-full.txt | Complete | AEO | ✅ Keep as-is |
| humans.txt | Complete | SEO | ✅ Keep as-is |
| security.txt | Complete | SEO | ✅ Keep as-is |
| .well-known/ai.json | Complete | AEO | ✅ Keep as-is |
| schema-webpage.json | Complete | SEO | ✅ Keep as-is |
| schema-localbusiness.json | Complete | SEO | 🟡 Enhance |
| Verification_Checklist.md | Complete | Docs | 🟡 Update for new files |

### 2.2 Current Questionnaire

9 questions across 3 sections (Your Business, Your Authority, For AI Systems). Only 2 are required (businessName, coreDescription). The rest are optional with skip buttons.

### 2.3 Current Scraper Capabilities

The `analyzeWebsite` function (in `base44/functions/analyzeWebsite/entry.ts`) already extracts:

- businessName, pageTitle, tagline, metaDescription
- industryKeywords, contactEmail, phoneNumber, address
- platform (WordPress, Shopify, Wix, Squarespace, Webflow, Framer, Kajabi, Ghost, Custom)
- socialLinks, siteStructure (hasProducts, hasBlog, hasServices, hasTeam, hasAbout, hasContact)
- pageCount, logoUrl, founderName, founderRole
- namedProducts, namedProductsWithDesc, frameworks
- targetAudience, socialProofSignals, faqItems, blogPosts

**Many of these fields are already scraped but underutilized in file generation.**

---

## 3. Gap Analysis by Discipline

### 3.1 SEO Gaps

| Gap | Impact | Fix |
|-----|--------|-----|
| No FAQPage schema | High | Generate `schema-faqpage.json` from scraped faqItems + Q8 commonQuestion |
| No Person schema (E-E-A-T) | High | Generate `schema-person.json` from Q4 founderExpert + scraped founderName/Role |
| No BreadcrumbList schema | Medium | Generate `schema-breadcrumb.json` from scraped siteStructure nav links |
| No Article/BlogPosting schema | Medium | Generate `schema-article.json` template from scraped blogPosts |
| Sitemap is stub (4 URLs max) | High | Build real `sitemap.xml` from scraper link discovery + siteStructure |
| No Open Graph meta tag output | Medium | Generate `meta-tags.html` with OG + Twitter Card copy-paste block |
| No technical SEO audit output | Medium | Generate `seo-audit.md` flagging missing meta descriptions, missing schema, no canonical, etc. |
| robots.txt missing standard entries | Low | Add standard Sitemap directive, crawl-delay guidance |
| LocalBusiness schema is thin | Medium | Add openingHours, geo coordinates, priceRange, aggregateRating if scraped |

### 3.2 AEO Gaps (Minor)

The AEO layer is the tool's strength. Gaps are minor:

- **llm.txt could include a version/date header** so crawlers know when to re-fetch.
- **No .well-known/llm.txt symlink guidance** in the implementation guide (some crawlers look there).
- **No guidance on re-generation cadence.** The tool should recommend quarterly refreshes.

### 3.3 GEO Gaps (Major)

GEO is the largest gap. The tool produces zero GEO-specific output today.

| Gap | Impact | Fix |
|-----|--------|-----|
| No citation-ready content blocks | Critical | Generate `geo-content-brief.md` with structured, quotable claim statements |
| No entity relationship mapping | High | Generate `entity-map.json` linking business, founder, products, topics |
| No topical authority plan | High | Generate `topical-authority-plan.md` with 5-8 topic clusters to own |
| No "quotable answers" for AI Overviews | Critical | Generate `geo-qa-snippets.json` with structured Q&A pairs formatted for citation |
| No E-E-A-T content signals guidance | Medium | Include E-E-A-T checklist in the GEO content brief |
| No structured claims with evidence | High | Use Q6 credibilitySignals + socialProofSignals to build evidence-backed claims |

---

## 4. Updated Questionnaire Design

Expand from 3 sections / 9 questions to **4 sections / 13 questions**. All new questions are optional with skip buttons, maintaining the existing UX pattern. The new section (Section 4) is titled **"For Search & Citations"**.

### 4.1 Existing Questions (Unchanged)

Q1 through Q9 remain exactly as they are. No changes to existing user flow.

### 4.2 New Questions

#### Q10: Proof Points (Section 4)

- **Label:** "What specific numbers or results can you share?"
- **Hint:** "Revenue generated for clients, years in business, number of students served, certifications held. These become citable statistics in your AI files."
- **Field type:** Textarea, maxLength 500
- **Variable name:** `proofPoints`
- **Feeds:** `geo-content-brief.md` claim blocks, `entity-map.json`, `schema-organization.json` (enhanced)

#### Q11: Topic Ownership (Section 4)

- **Label:** "What 3-5 topics should you be THE go-to expert on?"
- **Hint:** "Think about what you want AI systems to recommend you for. These become your topical authority clusters."
- **Field type:** Textarea, maxLength 400
- **Variable name:** `topicOwnership`
- **Feeds:** `topical-authority-plan.md`, `entity-map.json` knowsAbout, `schema-person.json` knowsAbout

#### Q12: Competitor Differentiation (Section 4)

- **Label:** "What makes you different from others in your space?"
- **Hint:** "AI systems compare options when recommending. This helps them position you accurately."
- **Field type:** Textarea, maxLength 400
- **Variable name:** `competitorDiff`
- **Feeds:** `geo-content-brief.md` differentiation claims, `llm.txt` (new section), `llms-full.txt` (new section)

#### Q13: Knowledge Panel / Search Presence (Section 4)

- **Label:** "What should appear when someone Googles your name or business name?"
- **Hint:** "This shapes the Person and Organization schema that feeds Google's Knowledge Panel."
- **Field type:** Textarea, maxLength 400
- **Variable name:** `knowledgePanel`
- **Feeds:** `schema-person.json` description, `schema-organization.json` description, `geo-content-brief.md` brand positioning block

### 4.3 Updated Section Navigation

Update the `SECTIONS` constant in `src/pages/Questionnaire.jsx`:

```javascript
const SECTIONS = [
  { label: 'Your Business', number: 1 },
  { label: 'Your Authority', number: 2 },
  { label: 'For AI Systems', number: 3 },
  { label: 'For Search & Citations', number: 4 },  // NEW
];
```

| Section | Title | Questions | New? |
|---------|-------|-----------|------|
| 1 | Your Business | Q1, Q2, Q3 | No |
| 2 | Your Authority | Q4, Q5, Q6 | No |
| 3 | For AI Systems | Q7, Q8, Q9 | No |
| 4 | For Search & Citations | Q10, Q11, Q12, Q13 | **YES - NEW** |

---

## 5. Scraper Improvements

The `analyzeWebsite` function (`base44/functions/analyzeWebsite/entry.ts`) needs the following additions. All are additive and do not change existing extraction logic.

### 5.1 New Data to Extract

1. **Existing schema markup detection.** Check if the site already has JSON-LD or microdata schema. Store as `existingSchema: { types: ['Organization', 'LocalBusiness', ...], raw: [...] }`. This feeds the SEO audit to flag what's present vs. missing.

2. **Existing robots.txt.** Fetch `{website_url}/robots.txt` and store the raw content. The audit can compare what exists to what the tool generates.

3. **Existing sitemap.xml.** Fetch `{website_url}/sitemap.xml`. If found, parse URL count and store. If not found, flag as missing.

4. **Open Graph tag presence.** Check for og:title, og:description, og:image, og:type, twitter:card. Store as `ogTags: { title: bool, description: bool, image: bool, type: bool, twitterCard: bool }`.

5. **Canonical URL detection.** Check for `<link rel="canonical">`. Store presence and value as `canonical: { present: bool, url: string }`.

6. **Internal link inventory.** Expand the existing link discovery to store up to 50 unique internal URLs with their anchor text. Store as `internalLinks: [{ url: string, anchorText: string }]`. This feeds the real `sitemap.xml` generation.

7. **Heading structure analysis.** Extract all H1, H2, H3 tags and their text. Store as `headingStructure: [{ level: number, text: string }]`. Flags SEO issues (multiple H1s, missing H1, etc.).

### 5.2 Implementation Note

The scraper runs in a Deno serverless function with a 30-second timeout. The additional fetches (robots.txt, sitemap.xml) should be wrapped in `Promise.allSettled` with individual 5-second timeouts so they don't block the primary HTML analysis.

```javascript
// Example pattern for non-blocking auxiliary fetches
const [robotsResult, sitemapResult] = await Promise.allSettled([
  fetchWithTimeout(`${website_url}/robots.txt`, 5000),
  fetchWithTimeout(`${website_url}/sitemap.xml`, 5000),
]);
```

---

## 6. New Files to Generate

All new file generation goes in `base44/functions/generateFiles/entry.ts`. Each entry specifies the filename, tier, discipline, data sources, and content structure.

### 6.1 schema-faqpage.json

- **Tier:** Basic
- **Discipline:** SEO + GEO
- **Data sources:** Q8 `commonQuestion`, scraped `faqItems`
- **Why:** FAQPage schema is the single highest-impact schema type for both traditional rich results and AI Overview citations. Google explicitly uses FAQ schema to populate AI Overviews.

**Structure:**
- `@context`: "https://schema.org"
- `@type`: "FAQPage"
- `mainEntity`: array of Question objects, each with `name` (question text) and `acceptedAnswer` (Answer object with `text`)
- Build from scraped `faqItems` first, then append Q8 `commonQuestion` as final item if provided
- Cap at 10 FAQ entries (Google's practical limit for rich results)

### 6.2 schema-person.json

- **Tier:** Basic
- **Discipline:** SEO (E-E-A-T) + GEO
- **Data sources:** Q4 `founderExpert`, Q11 `topicOwnership`, Q13 `knowledgePanel`, scraped `founderName`/`founderRole`, scraped `socialLinks`
- **Why:** Person schema is Google's primary signal for E-E-A-T. It directly feeds Knowledge Panels and is used by AI systems to attribute expertise. This is the single most important new file for GEO.

**Structure:**
- `@type`: "Person"
- `name`: from Q4 (split on comma for name vs. description)
- `jobTitle`: from scraped `founderRole` or parsed from Q4
- `description`: from Q13 `knowledgePanel` or Q4 full text
- `worksFor`: `{ @type: "Organization", name: businessName, url: website_url }`
- `knowsAbout`: from Q11 `topicOwnership` (split on comma/newline)
- `sameAs`: from scraped `socialLinks` (LinkedIn, Twitter, etc.)
- `url`: `website_url + '/about'` (if `siteStructure.hasAbout` is true)

### 6.3 schema-breadcrumb.json

- **Tier:** Complete
- **Discipline:** SEO
- **Data sources:** scraped `siteStructure`, scraped internal links
- **Structure:** BreadcrumbList with `itemListElement` array. Build from siteStructure flags: Home > [Products|Services] > [Blog] > [About] > [Contact]. Each item has `position`, `name`, and `item` (URL).

### 6.4 schema-article.json (template)

- **Tier:** Complete
- **Discipline:** SEO + GEO
- **Data sources:** scraped `blogPosts`, Q4 `founderExpert`, `businessName`
- **Why:** Article schema with author Person reference is a strong GEO signal. Providing a reusable template is more valuable than generating one per scraped post.

**Structure:** Template with placeholder fields marked `[REPLACE]`:
- `@type`: "Article"
- `headline`: `[REPLACE WITH POST TITLE]`
- `author`: `{ @type: "Person", name: founderName }`
- `publisher`: `{ @type: "Organization", name: businessName }`
- `datePublished`: `[REPLACE]`
- `dateModified`: `[REPLACE]`

### 6.5 meta-tags.html

- **Tier:** Basic
- **Discipline:** SEO
- **Data sources:** Q2 `coreDescription`, `businessName`, scraped `logoUrl`, `website_url`
- **Why:** Open Graph and Twitter Card meta tags are the most commonly missing SEO element on solopreneur sites. A copy-paste HTML block removes the barrier.

**Structure:** Raw HTML comment block containing:
```html
<!-- ============================================
     OPEN GRAPH & TWITTER CARD META TAGS
     Copy and paste this block into your <head> tag
     ============================================ -->
<meta property="og:title" content="{businessName}" />
<meta property="og:description" content="{coreDescription}" />
<meta property="og:type" content="website" />
<meta property="og:url" content="{website_url}" />
<meta property="og:image" content="{logoUrl}" />
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="{businessName}" />
<meta name="twitter:description" content="{coreDescription}" />
<meta name="twitter:image" content="{logoUrl}" />
```

### 6.6 sitemap.xml (replaces ai-sitemap.xml)

- **Tier:** Basic
- **Discipline:** SEO + AEO
- **Data sources:** scraped internal link inventory (new), `siteStructure`, `website_url`
- **Why:** The current `ai-sitemap.xml` is a stub with 3-5 hardcoded URLs. A real sitemap built from the scraper's link discovery is dramatically more useful.

**Structure:** Standard urlset format:
- Include all discovered internal URLs (up to 50)
- `lastmod` set to generation date
- `changefreq` based on page type (blog = weekly, product = daily, about = monthly)
- `priority` based on depth (homepage = 1.0, top-level = 0.8, deeper = 0.6)
- Point `robots.txt` Sitemap directive at this file

**Backward compatibility:** Keep generating `ai-sitemap.xml` as a copy for users who already have robots.txt pointing there. Include both filenames in the zip.

### 6.7 geo-content-brief.md

- **Tier:** Complete
- **Discipline:** GEO (primary) — this is the flagship GEO deliverable
- **Data sources:** Q2 `coreDescription`, Q5 `proprietaryFrameworks`, Q6 `credibilitySignals`, Q7 `priorityMessage`, Q8 `commonQuestion`, Q9 `misconceptions`, Q10 `proofPoints`, Q11 `topicOwnership`, Q12 `competitorDiff`, scraped `socialProofSignals`, scraped `targetAudience`
- **Why:** Provides structured, citation-ready content blocks that AI-generated search results (Google AI Overviews, Bing Copilot, Perplexity) can directly quote or paraphrase when recommending the business.

**Content structure:**

1. **Brand Positioning Statement.** A single clear claim paragraph that AI systems can cite. Built from Q2 + Q12 + Q13. Format: `"[Business] is [core description]. Unlike [implied competitors], [Business] [differentiator]."`

2. **Citable Claim Blocks.** 3-5 evidence-backed statements formatted as `[CLAIM]` + `[EVIDENCE]`. Claims built from Q6 `credibilitySignals` + Q10 `proofPoints` + scraped `socialProofSignals`. Example:
   ```
   CLAIM: [Business] has helped over 500 coaches systematize their operations.
   EVIDENCE: [proof point from Q10]
   ```

3. **Quotable Expert Answers.** 3-5 Q&A pairs formatted so AI Overviews can pull them directly. Built from Q8 `commonQuestion` + Q9 `misconceptions` + scraped `faqItems`. Each answer should be 2-3 sentences, factual, and attributable.

4. **E-E-A-T Signal Checklist.** Actionable items:
   - Author bio on every blog post (link to schema-person.json)
   - Credentials displayed on about page
   - Case studies with named results
   - External citations and backlinks to earn
   - Review collection strategy

5. **Differentiation Matrix.** Built from Q12. A structured comparison of what makes this business different, formatted as claims AI can use when comparing options.

6. **Content Recommendations.** 3-5 specific content pieces the business should create to strengthen GEO signals, based on Q11 `topicOwnership` and identified gaps.

### 6.8 entity-map.json

- **Tier:** Complete
- **Discipline:** GEO + AEO
- **Data sources:** All questionnaire answers + all scraped data
- **Why:** AI systems build knowledge graphs of entities and relationships. This file provides a structured map designed for LLM consumption.

**Structure:**
```json
{
  "primaryEntity": {
    "type": "Organization",
    "name": "",
    "url": "",
    "description": ""
  },
  "people": [{
    "type": "Person",
    "name": "",
    "role": "",
    "expertise": [],
    "credentials": []
  }],
  "products": [{
    "name": "",
    "description": ""
  }],
  "topics": [{
    "name": "",
    "relationship": "authority"
  }],
  "frameworks": [{
    "name": "",
    "description": ""
  }],
  "claims": [{
    "statement": "",
    "evidence": "",
    "source": ""
  }],
  "relationships": [{
    "from": "",
    "to": "",
    "type": ""
  }]
}
```

### 6.9 topical-authority-plan.md

- **Tier:** Complete
- **Discipline:** GEO + SEO
- **Data sources:** Q11 `topicOwnership`, Q5 `proprietaryFrameworks`, Q3 `productsServices`, scraped `industryKeywords`, scraped `blogPosts`

**Structure:**

1. **Topic Clusters.** 5-8 clusters built from Q11, each with: pillar topic name, 3-5 subtopics, content gap assessment (does the blog already cover this based on scraped `blogPosts`?), recommended content types (blog, FAQ, case study).

2. **Authority Signals to Build.** Specific backlink targets, guest post opportunities, and citation sources for each cluster.

3. **Internal Linking Map.** How the topic clusters should cross-reference each other on the site.

4. **90-Day Action Plan.** Month-by-month content creation priorities to establish topical authority.

### 6.10 geo-qa-snippets.json

- **Tier:** Complete
- **Discipline:** GEO
- **Data sources:** Q8 `commonQuestion`, Q9 `misconceptions`, scraped `faqItems`, Q7 `priorityMessage`, Q12 `competitorDiff`
- **Why:** Google AI Overviews and Perplexity actively pull structured Q&A content for citation.

**Structure:**
```json
[
  {
    "question": "",
    "answer": "",
    "category": "faq | misconception | comparison | how-it-works",
    "source": "{businessName}",
    "citationText": "A single sentence designed to be quoted by AI systems."
  }
]
```

### 6.11 seo-audit.md

- **Tier:** Basic
- **Discipline:** SEO
- **Data sources:** All scraped data, especially new scraper additions (existing schema, robots.txt, sitemap.xml, OG tags, canonical, heading structure)

**Structure:**

1. **Technical SEO Status.** Table of pass/fail checks:
   - Has robots.txt
   - Has sitemap.xml
   - Has canonical URL
   - Has meta description
   - Has OG tags (og:title, og:description, og:image)
   - Has schema markup (list types found)
   - H1 count (should be exactly 1)
   - HTTPS check

2. **Schema Markup Status.** What schema types are already on the site vs. what this package provides. Flag gaps.

3. **Missing Quick Wins.** Prioritized list of easy fixes based on what the scraper found missing.

4. **Platform-Specific Instructions.** Based on detected platform (WordPress, Shopify, Wix, etc.), specific instructions for implementing each fix. Use the platform detection the scraper already does.

---

## 7. Complete File Manifest v2.0

### 7.1 Basic Tier (12 files, up from 7)

| # | File | Discipline | New? |
|---|------|-----------|------|
| 1 | llm.txt | AEO | Existing (minor update) |
| 2 | robots.txt | AEO + SEO | Existing (enhanced) |
| 3 | sitemap.xml | SEO + AEO | **UPDATED** (replaces ai-sitemap.xml) |
| 4 | ai-sitemap.xml | AEO | Kept for backward compat (copy of sitemap.xml) |
| 5 | schema-organization.json | SEO + AEO | Existing (enhanced) |
| 6 | schema-website.json | SEO | Existing |
| 7 | schema-faqpage.json | SEO + GEO | **NEW** |
| 8 | schema-person.json | SEO + GEO | **NEW** |
| 9 | meta-tags.html | SEO | **NEW** |
| 10 | seo-audit.md | SEO | **NEW** |
| 11 | README.md | Docs | Existing (updated) |
| 12 | Implementation_Guide.md | Docs | Existing (rewritten) |

### 7.2 Complete Tier (26 files, up from 15 — includes all Basic files plus)

| # | File | Discipline | New? |
|---|------|-----------|------|
| 13 | llms.txt | AEO | Existing |
| 14 | llms-full.txt | AEO | Existing (add differentiation section) |
| 15 | humans.txt | SEO | Existing |
| 16 | security.txt | SEO | Existing |
| 17 | .well-known/ai.json | AEO | Existing |
| 18 | schema-webpage.json | SEO | Existing |
| 19 | schema-localbusiness.json | SEO | Existing (enhanced, conditional) |
| 20 | schema-breadcrumb.json | SEO | **NEW** |
| 21 | schema-article.json | SEO + GEO | **NEW** (template) |
| 22 | geo-content-brief.md | GEO | **NEW** (flagship) |
| 23 | entity-map.json | GEO + AEO | **NEW** |
| 24 | topical-authority-plan.md | GEO + SEO | **NEW** |
| 25 | geo-qa-snippets.json | GEO | **NEW** |
| 26 | Verification_Checklist.md | Docs | Existing (updated) |

---

## 8. Enhancements to Existing Files

### 8.1 llm.txt

- Add version header: `# Generated by AEO File Generator v2.0 | {date}`
- Add new section: `## What Makes Us Different` (from Q12 `competitorDiff`)
- Add new section: `## Proof Points` (from Q10 `proofPoints`)

### 8.2 llms-full.txt

- Add `## Differentiation` section from Q12
- Add `## Proof Points` section from Q10
- Add `## Topical Authority` section listing Q11 topics with brief descriptions

### 8.3 robots.txt

- Add `Sitemap: {website_url}/sitemap.xml` directive (in addition to AI-specific directives)
- Add comment blocks explaining each crawler section

### 8.4 schema-organization.json

- Add `numberOfEmployees` if mentioned in Q10 `proofPoints`
- Add `award` if mentioned in Q6 `credibilitySignals`
- Populate `knowsAbout` from Q11 `topicOwnership` (currently only uses scraped `industryKeywords`)
- Add `hasOfferCatalog` from Q3 `productsServices`

### 8.5 schema-localbusiness.json

- Add `geo: { @type: GeoCoordinates }` if address is provided (leave lat/lng as placeholders with comment)
- Add `priceRange` if detectable from scraped data
- Add `openingHoursSpecification` placeholder with comment

### 8.6 Implementation_Guide.md

Complete rewrite. New structure:

1. **Quick Start (5 minutes).** Upload the core files: llm.txt, robots.txt, sitemap.xml, schema JSONs. Copy meta-tags.html into your site `<head>`.
2. **SEO Implementation.** Platform-specific instructions for schema installation, sitemap submission, and meta tag placement. Use detected platform from scraper.
3. **AEO Implementation.** Where to place llm.txt, llms.txt, llms-full.txt, and .well-known/ai.json. How to verify AI crawlers can find them.
4. **GEO Implementation.** How to use the content brief, where to publish quotable answers, how to implement the topical authority plan.
5. **Verification Steps.** Links to Google Rich Results Test, Google Search Console sitemap submission, and manual AI crawler verification.
6. **Maintenance Schedule.** Recommended quarterly refresh cadence.

---

## 9. Pricing Consideration

The current pricing is $47 (Basic, 7 files) and $87 (Complete, 15 files). With the upgrade:

- **Basic** goes from 7 to 12 files, adding high-value SEO outputs (FAQ schema, Person schema, meta tags, SEO audit). The $47 price becomes significantly more competitive.
- **Complete** goes from 15 to 26 files, adding the entire GEO layer (content brief, entity map, topical authority plan, QA snippets) plus additional schema types. The $87 price point is underpriced for what this delivers but may be correct for market entry.

> **Future consideration:** A third tier at $147-197 that uses the Anthropic API to generate a custom, AI-written GEO content brief rather than templating it. This would differentiate from static file generation and justify the higher price with genuinely personalized strategic output. Out of scope for this PRD.

---

## 10. Implementation Priority

Recommended build order based on value delivered per effort:

### Phase 1: High-Impact SEO (do first)

- `schema-faqpage.json` (uses data already being scraped)
- `schema-person.json` (uses Q4 data already collected)
- `sitemap.xml` (expand existing ai-sitemap.xml logic)
- `meta-tags.html` (simple template from existing data)
- Enhance `schema-organization.json`

*These use existing data. No new questionnaire questions needed. Can ship as v1.5.*

### Phase 2: New Questionnaire + SEO Audit

- Add Section 4 (Q10-Q13) to questionnaire
- Add scraper improvements (existing schema detection, OG tags, etc.)
- `seo-audit.md` (depends on new scraper data)
- `schema-breadcrumb.json`
- Enhance `llm.txt` and `llms-full.txt` with new sections

### Phase 3: GEO Layer (flagship)

- `geo-content-brief.md` (the big one, needs Q10-Q12 data)
- `entity-map.json`
- `geo-qa-snippets.json`
- `topical-authority-plan.md`
- `schema-article.json` template
- Rewrite `Implementation_Guide.md`

### Phase 4: Polish

- Update `README.md` for full file manifest
- Update `Verification_Checklist.md`
- Update Pricing page copy to reflect three disciplines
- Update Dashboard/MyGenerations to show SEO/AEO/GEO coverage badges

---

## 11. Architecture Notes for Claude Code

### 11.1 Where Changes Go

| Change | File(s) to Modify |
|--------|-------------------|
| New questionnaire questions (Q10-Q13) | `src/pages/Questionnaire.jsx` |
| New scraper extractions | `base44/functions/analyzeWebsite/entry.ts` |
| All new file generation | `base44/functions/generateFiles/entry.ts` |
| Updated SECTIONS constant | `src/pages/Questionnaire.jsx` (SECTIONS array) |
| Updated progress bar | `src/pages/Questionnaire.jsx` (section count) |
| Updated review page | `src/pages/Review.jsx` (show new fields) |
| Updated pricing page copy | `src/pages/Pricing.jsx` |
| Updated README in zip | `generateFiles/entry.ts` (README.md template) |

### 11.2 Entity Changes

The Generation entity's `questionnaire_data` field is a JSON blob, so new fields (`proofPoints`, `topicOwnership`, `competitorDiff`, `knowledgePanel`) do **not** require schema changes. They are simply additional keys in the existing `questionnaire_data` object.

### 11.3 No New Dependencies

All new file generation is string templating and JSON construction. No new npm packages are needed. The JSZip dependency already handles zip creation. The existing `addFile()` helper works for all new files.

### 11.4 Base44 Migration Consideration

This PRD is written against the current Base44 architecture. If the tool is migrated to PHP/MySQL before implementation, the same logic applies:
- File generation is pure JavaScript string templating → ports to PHP trivially
- Questionnaire is standard React form state → no backend dependency
- Scraper is fetch + DOM parse → PHP's DOMDocument or similar

---

## 12. Acceptance Criteria

The upgrade is complete when:

- [ ] Basic tier generates 12 files (all pass manual review for correct content)
- [ ] Complete tier generates 26 files
- [ ] All schema JSON files validate at schema.org/validator
- [ ] FAQ schema validates at Google Rich Results Test
- [ ] The questionnaire has 4 sections and 13 questions
- [ ] New questions are all optional with skip buttons (matching existing UX)
- [ ] Scraper detects existing schema, OG tags, and sitemap presence
- [ ] SEO audit correctly flags missing elements based on scraper findings
- [ ] GEO content brief contains citation-ready claim blocks with evidence
- [ ] Entity map correctly links business, founder, products, and topics
- [ ] Implementation guide covers all three disciplines with platform-specific instructions
- [ ] All existing tests still pass (no regressions)
- [ ] ZIP file size stays under 500KB

---

*End of PRD*
