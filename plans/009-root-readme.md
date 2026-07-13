# Plan 009: Add a root README with the real setup path

> **Executor instructions**: Follow this plan step by step. Run every verification command and confirm the expected result before moving to the next step. If anything in the "STOP conditions" section occurs, stop and report — do not improvise. When done, update the status row for this plan in `plans/README.md` — unless a reviewer dispatched you and told you they maintain the index.
>
> **Drift check (run first)**: `git diff --stat 83f7e7e..HEAD -- README.md composer.json .env.example .github/workflows/tests.yml docs/README.md`
> If any in-scope file changed since this plan was written, compare the "Current state" excerpts against the live code before proceeding; on a mismatch, treat it as a STOP condition.

## Status

- **Priority**: P2
- **Effort**: S
- **Risk**: LOW
- **Depends on**: none
- **Category**: dx
- **Planned at**: commit `83f7e7e`, 2026-07-12

## Why this matters

There is no root `README.md`. Setup lives in `composer setup` and `.env.example`, while CI installs `poppler-utils` for resume PDF text extraction without documenting it for local dev. Agents and humans invent incomplete setup steps. Branding also splits (`APP_NAME` vs HirePilot in `CONTEXT.md`) — README should name the product clearly.

## Current state

- No root `README.md` (`test -f README.md` → missing).
- `composer.json` `setup` script: composer install, copy `.env`, key generate, migrate, pnpm install, pnpm build.
- `composer dev` runs serve + queue + pail + vite.
- `.github/workflows/tests.yml` installs `poppler-utils` for `pdftotext`.
- Domain source of truth: `CONTEXT.md`, ADR `docs/adr/0001-use-contextual-identities-for-team-tenancy.md`.
- Autopilot docs under `docs/` are **stale** (plan 010) — README must point to `CONTEXT.md` + ADR first, and warn that Autopilot PRD/HOW_IT_WORKS may lag until 010 lands.

## Commands you will need

| Purpose | Command | Expected on success |
|---------|---------|---------------------|
| Exists | `test -f README.md && wc -l README.md` | file exists, >30 lines |

## Scope

**In scope**:
- `README.md` (create)
- Optionally one-line tweak to `docs/README.md` linking up to root README — only if needed
- `plans/README.md`

**Out of scope**:
- Rewriting Autopilot PRD (plan 010)
- Changing `APP_NAME` in `.env.example`
- Installing system packages on the operator’s machine

## Git workflow

- Branch: `advisor/009-root-readme`
- Commit: `Add root README with local setup and verification commands.`
- Do NOT push/PR unless asked.

## Steps

### Step 1: Write `README.md`

Include at minimum:

1. **Product name**: HirePilot (hiring assessment platform) — one paragraph from CONTEXT purpose.
2. **Stack**: Laravel 13, Inertia React 3, Pest, pnpm, Postgres (note if sqlite used in tests — check `phpunit.xml`).
3. **Requirements**: PHP version from `composer.json`, Node 22 (CI), pnpm, Postgres, Google OAuth creds, Qwen API key vars from `.env.example` (**name the env keys only; never paste secret values**), **`poppler-utils`** (`pdftotext`) for resume screening.
4. **Setup**: `composer setup` then configure `.env`.
5. **Dev**: `composer dev`.
6. **Verify**: `composer test`, `composer ci:check`, `pnpm run types:check`.
7. **Domain docs**: link `CONTEXT.md`, `docs/adr/0001-use-contextual-identities-for-team-tenancy.md`.
8. **Warning**: Autopilot docs in `docs/*AUTOPILOT*` may describe pre-Team auth until refreshed.

Keep it concise (roughly 80–150 lines max). No marketing fluff.

**Verify**: `test -f README.md`; `rg -n "poppler|composer setup|CONTEXT.md" README.md` → all hit.

## Test plan

- Documentation only; no Pest tests.

## Done criteria

- [ ] Root README exists with setup, poppler, and verification commands
- [ ] Links domain docs; warns about stale Autopilot docs
- [ ] No secrets pasted
- [ ] `plans/README.md` → DONE

## STOP conditions

- Operator already added a README with different product naming that conflicts — stop and reconcile rather than overwrite silently.

## Maintenance notes

- After plan 010, remove the Autopilot staleness warning or narrow it.
