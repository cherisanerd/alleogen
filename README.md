# AEO File Generator

A Base44 + Vite + React SaaS that scrapes a website, runs a short interview, and generates a downloadable ZIP of AI- and search-optimized files covering three disciplines:

- **SEO** — Traditional search (Google, Bing organic): sitemap, schema, meta tags, technical audit.
- **AEO** — AI answer engines (ChatGPT, Claude, Perplexity direct answers): `llm.txt`, `llms.txt`, `llms-full.txt`, `.well-known/ai.json`.
- **GEO** — AI-generated search results (Google AI Overviews, Bing Copilot, Perplexity citations): content brief, entity map, quotable Q&A snippets, topical authority plan.

See `AEO_File_Generator_v2_PRD.md` for the full product spec.

## Package tiers

**Basic** — 11 core SEO + AEO files: `llm.txt`, `robots.txt`, `sitemap.xml`, `ai-sitemap.xml` (backward compat), `schema-organization.json`, `schema-website.json`, `schema-faqpage.json`, `schema-person.json`, `meta-tags.html`, `seo-audit.md`, plus a platform-specific `Implementation_Guide.md` and `README.md`.

**Complete** — Everything in Basic plus the full AEO context (`llms.txt`, `llms-full.txt`, `humans.txt`, `security.txt`, `.well-known/ai.json`), additional schema (`schema-webpage.json`, `schema-breadcrumb.json`, `schema-article.json`, `schema-localbusiness.json` when applicable), and the flagship GEO layer: `geo-content-brief.md`, `entity-map.json`, `geo-qa-snippets.json`, `topical-authority-plan.md`, plus a `Verification_Checklist.md`.

## Architecture

- **Frontend** — Vite + React + Tailwind + shadcn/ui in `src/`. Built to `dist/` and served at `cherisanerd.com/tools/alleogen/`.
- **Backend** — PHP 8.0+ REST API under `api/` against MySQL. One Composer dep (Stripe PHP SDK) committed under `vendor/`. Admin-configurable knobs in `alleogen_settings`; secrets in `api/config.local.php` (gitignored).
- **File generator** — `api/generations/generator.ts` runs as a Deno subprocess spawned from `api/generations/generate.php`. The TS file is the single source of truth for generated file content. Deno pulls `jszip` on first run via the `npm:` specifier — no `npm install` / `node_modules` required.
- **Billing** — Stripe for one-time purchases (direct), GoHighLevel as the source of truth for subscription billing (bidirectional webhooks).
- **Storage** — Generated zips live in `storage/zips/` (web-denied by `.htaccess`) and are streamed through `api/generations/download.php`.

### Server requirements

- PHP 8.0+ with `pdo_mysql`, `curl`, `zip`, `json` extensions
- MySQL 5.7+ or 8.0
- `deno` on PATH (or set `deno_path` in `config.local.php`). Install: `curl -fsSL https://deno.land/install.sh | sh`
- Cron (two jobs: reminders hourly, cleanup daily)
- `shell_exec` / `proc_open` enabled (required to invoke Deno)
- Write access to `storage/zips/`
- Mail function or SMTP

## Development

```bash
# Frontend
npm install
npm run dev                       # Vite dev server
npm run build                     # build to dist/

# Backend
cp api/config.local.example.php api/config.local.php
# edit api/config.local.php with DB + Stripe + GHL credentials
mysql -u root -p < schema.sql     # apply DB schema
composer install                  # if vendor/ was not pulled from git
php -S 127.0.0.1:8000 -t .        # serve PHP + built SPA locally
```

Scripts: `npm run dev`, `npm run build`, `npm run lint`, `npm run typecheck`.

## Questionnaire

13 questions across 4 sections. Only Q1 (business name) and Q2 (core description) are required; all others are optional with skip buttons.

1. **Your Business** — Q1–Q3
2. **Your Authority** — Q4–Q6
3. **For AI Systems** — Q7–Q9
4. **For Search & Citations** — Q10 proof points, Q11 topic ownership, Q12 differentiation, Q13 knowledge panel

## Support

Publish changes from the Base44 Builder after pushing to this repo. Docs: https://docs.base44.com/Integrations/Using-GitHub
