# Plan 004: Make CI run check-mode quality gates

> **Executor instructions**: Follow this plan step by step. Run every verification command and confirm the expected result before moving to the next step. If anything in the "STOP conditions" section occurs, stop and report — do not improvise. When done, update the status row for this plan in `plans/README.md` — unless a reviewer dispatched you and told you they maintain the index.
>
> **Drift check (run first)**: `git diff --stat 83f7e7e..HEAD -- .github/workflows/lint.yml .github/workflows/tests.yml composer.json`
> If any in-scope file changed since this plan was written, compare the "Current state" excerpts against the live code before proceeding; on a mismatch, treat it as a STOP condition.

## Status

- **Priority**: P1
- **Effort**: S
- **Risk**: LOW
- **Depends on**: none
- **Category**: dx
- **Planned at**: commit `83f7e7e`, 2026-07-12

## Why this matters

`composer ci:check` already defines the intended gate (`pnpm lint:check`, `format:check`, `types:check`, then tests), but GitHub Actions does not use it. The lint workflow runs **mutating** `composer lint` / `pnpm run format` / `pnpm run lint` with `permissions: contents: write` while the auto-commit step is commented out — so CI can rewrite the checkout without committing, and type errors can merge unnoticed. Both workflows also trigger on a ghost `workos` branch that is not part of this product.

## Current state

- `composer.json` scripts:

```json
"ci:check": [
    "Composer\\Config::disableProcessTimeout",
    "pnpm run lint:check",
    "pnpm run format:check",
    "pnpm run types:check",
    "@test"
],
"lint:check": ["pint --parallel --test"],
```

- `.github/workflows/lint.yml` (excerpt): branches include `workos`; `permissions: contents: write`; steps run `composer lint`, `pnpm run format`, `pnpm run lint` (mutating / fix mode).
- `.github/workflows/tests.yml`: Pest after build; also lists `workos`; does not run `types:check`.

## Commands you will need

| Purpose | Command | Expected on success |
|---------|---------|---------------------|
| Local gate (optional long) | `composer ci:check` | exit 0 — if it fails, fix only workflow in this plan unless failures are trivial formatting already in dirty tree; see STOP |
| YAML sanity | `python -c "import yaml,sys; yaml.safe_load(open('.github/workflows/lint.yml')); yaml.safe_load(open('.github/workflows/tests.yml')); print('ok')"` or `actionlint` if installed | ok / exit 0 |

## Scope

**In scope**:
- `.github/workflows/lint.yml`
- `.github/workflows/tests.yml` (only to remove `workos` and optionally note that types live in lint job — do not duplicate full `ci:check` if that double-runs tests)
- `plans/README.md`

**Out of scope**:
- Fixing all pre-existing lint/format/type failures across the whole repo **unless** they block you from validating the workflow change locally with a dry-run of the same commands — if the repo currently fails `types:check`, report that as BLOCKED with the first errors; do not mass-fix unrelated TS in this plan.
- Adding husky/pre-commit hooks (deferred)
- Changing `composer ci:check` script definition (keep it; make CI call the check variants)

## Git workflow

- Branch: `advisor/004-ci-check-mode-gates`
- Commit: `Run non-mutating CI quality gates and drop workos triggers.`
- Do NOT push/PR unless asked.

## Steps

### Step 1: Rewrite lint workflow to check mode

Edit `.github/workflows/lint.yml`:

1. Remove `workos` from `on.push.branches` and `on.pull_request.branches`.
2. Set `permissions: contents: read` (drop `write` unless you re-enable auto-commit — do not re-enable auto-commit in this plan).
3. Replace mutating steps with check equivalents:
   - `composer lint:check` **or** `vendor/bin/pint --parallel --test` (match how other steps install PHP deps — keep existing composer install steps).
   - `pnpm run format:check`
   - `pnpm run lint:check`
   - `pnpm run types:check` (needs Wayfinder generate — the npm script already runs `php artisan wayfinder:generate --with-form --no-interaction && tsc --noEmit`; ensure PHP deps + `.env` / app key expectations match `tests.yml` if types:check needs them — copy whatever setup `tests.yml` already uses for artisan if required).
4. Delete or leave commented the auto-commit action; do not leave `contents: write` “just in case”.

**Verify**: `rg -n "workos|contents: write|composer lint$|pnpm run format$|pnpm run lint$" .github/workflows/lint.yml` → no `workos`, no `contents: write`, no bare mutating format/lint scripts (check variants only).

### Step 2: Clean tests workflow branch list

In `.github/workflows/tests.yml`, remove `workos` from push/PR branch lists. Do not change the Pest job otherwise.

**Verify**: `rg -n workos .github/workflows/` → no matches.

### Step 3: Local dry-run of the same checks (best effort)

Run individually (faster feedback than full `composer ci:check` if tests are slow):

```bash
composer lint:check
pnpm run format:check
pnpm run lint:check
pnpm run types:check
```

**Verify**: each exits 0.
If any fails due to **pre-existing** repo debt unrelated to your YAML edits: mark plan status BLOCKED with the command output summary; do not expand scope into a repo-wide format PR unless the operator expands the plan.

## Test plan

- No new Pest tests (CI config only).
- Verification is the workflow file content checks + local check commands.

## Done criteria

- [ ] Lint workflow uses check-mode commands including `types:check`
- [ ] `permissions: contents: read` (or omitted default read)
- [ ] No `workos` branch triggers
- [ ] Local check commands pass **or** plan marked BLOCKED with evidence of pre-existing failures
- [ ] `plans/README.md` updated

## STOP conditions

- `types:check` requires secrets/services not available in CI and cannot run — stop and report; leave a `types:check` step commented with reason rather than silently omitting without note.
- Operator wants auto-commit restored — out of scope; stop rather than re-adding write perms.

## Maintenance notes

- Prefer calling `composer ci:check` in a single job later once runtime is acceptable; this plan splits lint/types from Pest to match current two-workflow layout.
- Reviewers: confirm CI cannot mutate the tree.
