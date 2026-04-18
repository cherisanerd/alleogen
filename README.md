# AEO File Generator (alleogen)

Self-hosted SaaS that scrapes a website, runs a short interview, and generates a downloadable ZIP of AI- and search-optimized files covering three disciplines:

- **SEO** — Traditional search (Google, Bing organic): sitemap, schema, meta tags, technical audit.
- **AEO** — AI answer engines (ChatGPT, Claude, Perplexity direct answers): `llm.txt`, `llms.txt`, `llms-full.txt`, `.well-known/ai.json`.
- **GEO** — AI-generated search results (Google AI Overviews, Bing Copilot, Perplexity citations): content brief, entity map, quotable Q&A snippets, topical authority plan.

Ships at `cherisanerd.com/tools/alleogen/`. PRDs: `AEO_File_Generator_v2_PRD.md` (product) and the v3 migration PRD (architecture).

## Architecture

- **Frontend** — Vite + React + Tailwind + shadcn/ui in `src/`, built to `dist/`.
- **Backend** — PHP 8.0+ REST API under `api/`, MySQL for state. One Composer dep (`stripe/stripe-php`) committed under `vendor/`.
- **File generator** — `api/generations/generator.php` + one PHP builder per output file under `api/generations/builders/`. Pure PHP, no subprocess, no external runtime. Outputs an in-memory `[filename => content]` map that's zipped with `ZipArchive`.
- **Billing** — Stripe for one-time purchases (direct integration), GoHighLevel for subscription billing (webhooks in both directions).
- **Storage** — Generated zips live in `storage/zips/` (web-denied by `.htaccess`), served by `api/generations/download.php` after auth.
- **Secrets** — `api/config.local.php` (gitignored) holds DB password, Stripe keys, GHL API key + webhook secret. Non-secret admin knobs (prices, credits, GHL product IDs) live in the `alleogen_settings` DB table and are editable at `/admin/settings`.

## Package tiers

**Basic (one-time $97 via Stripe)** — 11 core SEO + AEO files: `llm.txt`, `robots.txt`, `sitemap.xml`, `ai-sitemap.xml`, `schema-organization.json`, `schema-website.json`, `schema-faqpage.json`, `schema-person.json`, `meta-tags.html`, `seo-audit.md`, plus `README.md` and `Implementation_Guide.md`.

**Complete (one-time $147 via Stripe)** — Everything in Basic plus the full AEO context (`llms.txt`, `llms-full.txt`, `humans.txt`, `security.txt`, `.well-known/ai.json`), additional schema (`schema-webpage.json`, `schema-breadcrumb.json`, `schema-article.json`, `schema-localbusiness.json` when applicable), and the full GEO layer: `geo-content-brief.md`, `entity-map.json`, `geo-qa-snippets.json`, `topical-authority-plan.md`, plus `Verification_Checklist.md`.

**Pro ($49/mo, 5 credits) and Agency ($99/mo, 15 credits)** — recurring subscriptions provisioned through GoHighLevel. Credits replenish monthly, no rollover. Canceling starts a 30-day file retention clock; re-subscribing before deletion preserves everything.

## Server requirements

- PHP 8.0+ with `pdo_mysql`, `curl`, `zip`, `json`
- MySQL 5.7+ or 8.0
- Cron access (hourly reminders + daily cleanup — see `api/cron/README.md`)
- Write access to `storage/zips/`
- Mail function or SMTP

That's it. No Node.js, no Deno, no `shell_exec`, no extra binaries. Runs on standard shared hosting (Bluehost, SiteGround, DreamHost, cPanel hosts, etc.).

## Deploy

```bash
# Database
mysql -u root -p DATABASE_NAME < schema.sql

# Backend
cp api/config.local.example.php api/config.local.php
# fill in DB creds, Stripe keys, GHL creds

# Frontend
npm install
npm run build                 # produces dist/
# Point web server at the repo root; dist/index.html serves the SPA
# and api/*.php serves the REST endpoints. Apache config example:
#   <Directory /var/www/tools/alleogen>
#       AllowOverride All
#   </Directory>
# Then the repo's .htaccess takes over (SPA fallback + /api passthrough).

# Cron
crontab -e
# Paste entries from api/cron/README.md (two lines: hourly + daily)

# Sanity check
php -l api/config.php
php api/cron/cleanup.php        # safe to run — reports stats
```

## Development

```bash
# One-time
cp api/config.local.example.php api/config.local.php  # edit with dev DB creds
npm install

# Two terminals
php -S 127.0.0.1:8000 -t .                 # PHP + built SPA on :8000
npm run dev                                 # Vite HMR on :5173, proxies /api to :8000
```

Scripts: `npm run dev`, `npm run build`, `npm run lint`, `npm run typecheck`.

## Questionnaire

13 questions across 4 sections. Only Q1 (business name) and Q2 (core description) are required; all others are optional with skip buttons.

1. **Your Business** — Q1–Q3
2. **Your Authority** — Q4–Q6
3. **For AI Systems** — Q7–Q9
4. **For Search & Citations** — Q10 proof points, Q11 topic ownership, Q12 differentiation, Q13 knowledge panel

## Repository layout

```
.
├── api/                              PHP REST API
│   ├── config.php                    loads config.local.php + PDO factory
│   ├── config.local.example.php      secrets template (real file gitignored)
│   ├── helpers.php, middleware.php, mailer.php
│   ├── auth/                         login, logout, me, change-password
│   ├── analyses/                     scraper + CRUD
│   ├── generations/
│   │   ├── generator.php             orchestrator + shared helpers
│   │   ├── builders/                 one PHP file per generated output
│   │   └── generate.php, CRUD, download
│   ├── payments/                     Stripe checkout + webhook + apply-coupon
│   ├── ghl/                          webhook, client, subscribe-url, request-cancel
│   ├── admin/                        users, coupons, generations, settings, error-logs, add-credits
│   └── cron/                         send-reminders.php, cleanup.php
├── src/                              Vite + React frontend
├── storage/zips/                     generated zips (web-denied)
├── vendor/                           Composer deps (stripe/stripe-php, committed)
├── schema.sql                        MySQL schema + seed
├── composer.json, package.json, vite.config.js, .htaccess
└── AEO_File_Generator_v2_PRD.md      product PRD
```

## Support

Publish to `cherisanerd.com/tools/alleogen/` after `npm run build`. Server logs live alongside the PHP API; database errors land in `alleogen_error_logs` (browsable at `/admin` once built).
