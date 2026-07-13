# HirePilot

HirePilot is a hiring assessment platform for creating campaigns, authoring
questions, inviting candidates, running secure exams, and reviewing AI-assisted
assessment outcomes.

## Stack

- PHP 8.4 or 8.5 and Laravel 13
- Inertia.js 3, React 19, TypeScript, and Vite
- Pest 4
- pnpm 11 and Node.js 22
- PostgreSQL for local development
- SQLite in-memory databases for the default test suite

## Requirements

Install these dependencies before setting up the application:

- PHP 8.4+ with Composer 2
- Node.js 22
- pnpm 11 (the repository pins `pnpm@11.6.0`)
- PostgreSQL
- `poppler-utils`, which provides `pdftotext` for resume screening

On Debian or Ubuntu, install Poppler with:

```bash
sudo apt-get install poppler-utils
```

The application also needs credentials for its external services. Configure
these environment keys without committing their values:

- Google OAuth: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, and
  `GOOGLE_REDIRECT_URI`
- Qwen: `QWEN_API_KEY`, `DASHSCOPE_API_KEY`, `QWEN_BASE_URL`, `QWEN_MODEL`, and
  `QWEN_TIMEOUT`
- Resend email: `RESEND_API_KEY`

## Setup

Create the PostgreSQL database described by `.env.example`. If your local
database host, port, database name, or credentials differ, copy `.env.example`
to `.env` and configure the `DB_*` values before running setup because the
setup script runs migrations.

Then run:

```bash
composer setup
```

This installs PHP and JavaScript dependencies, creates `.env` when needed,
generates the application key, runs migrations, and builds frontend assets.
After setup, finish configuring the Google OAuth, Qwen, email, and application
URL values in `.env`.

Never commit `.env` or secret values.

## Development

Start the Laravel server, queue worker, application log stream, and Vite server:

```bash
composer dev
```

The queue worker is required for background assessment processing.

## Verification

Run the backend test and PHP formatting checks:

```bash
composer test
```

Run the complete CI quality gate:

```bash
composer ci:check
```

Run the TypeScript check independently when working on frontend code:

```bash
pnpm run types:check
```

The default Pest configuration uses an in-memory SQLite database, so the test
suite does not use the local PostgreSQL database unless a test explicitly opts
into PostgreSQL integration coverage.

## Domain Documentation

Use these documents as the current source of truth for product vocabulary and
team tenancy:

- [Product and domain context](CONTEXT.md)
- [ADR 0001: contextual identities for team tenancy](docs/adr/0001-use-contextual-identities-for-team-tenancy.md)

The Autopilot documents matching `docs/*AUTOPILOT*` may still describe the
pre-Team authentication model and can lag behind the current codebase until
they are refreshed. Prefer `CONTEXT.md` and ADR 0001 when they conflict.
