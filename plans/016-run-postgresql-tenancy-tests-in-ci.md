# Plan 016: Run PostgreSQL tenancy integration tests in CI

> **Executor instructions**: Follow this plan step by step. Run every verification command and confirm the expected result before moving to the next step. If a STOP condition occurs, stop and report; do not improvise. When done, update this plan's row in `plans/README.md` unless a reviewer says they maintain the index.
>
> **Drift check (run first)**: `git diff --stat 83f7e7e..HEAD -- .github/workflows/tests.yml tests/Integration/PostgreSQL/TeamFoundationMigrationTest.php config/database.php`
> If an in-scope file changed, compare the excerpts below with live code. Stop on a semantic mismatch.

## Status

- **Priority**: P1
- **Effort**: M
- **Risk**: MED
- **Depends on**: none
- **Category**: tests
- **Planned at**: commit `83f7e7e`, 2026-07-12

## Why this matters

Normal tests force in-memory SQLite, but production tenancy invariants rely on PostgreSQL triggers and migration behavior. The existing PostgreSQL suite silently skips when no server is available, and CI provisions none. A dedicated CI job must fail—not skip—when the production-shaped migration and constraint tests cannot run.

## Current state

- `.github/workflows/tests.yml:20-68` has one PHP 8.4/8.5 matrix job and no PostgreSQL service.
- `phpunit.xml:29-33` forces SQLite and a synchronous queue for normal tests.
- `tests/Integration/PostgreSQL/TeamFoundationMigrationTest.php:15-34` uses `POSTGRES_INTEGRATION_DATABASE`; when it is set, connection failure is rethrown instead of skipped.
- `tests/Integration/PostgreSQL/TeamFoundationMigrationTest.php:36-55` creates and removes a random schema, so the dedicated database can be reused safely within one CI job.
- `database/migrations/2026_07_10_120347_enforce_team_foundation_constraints.php` contains production-only PL/pgSQL trigger logic.

Current skip behavior:

```php
$integrationDatabase = env('POSTGRES_INTEGRATION_DATABASE');

if (filled($integrationDatabase)) {
    $baseConnection['database'] = $integrationDatabase;
}

try {
    $postgres = DB::connection('pgsql');
    $postgres->getPdo();
} catch (Throwable $exception) {
    if (filled($integrationDatabase)) {
        throw $exception;
    }

    test()->markTestSkipped('PostgreSQL is unavailable: '.$exception->getMessage());
}
```

Follow the existing workflow conventions: pin third-party actions to the same versions already used, keep `persist-credentials: false`, and retain `permissions: contents: read`.

## Commands you will need

| Purpose | Command | Expected on success |
|---|---|---|
| Targeted local test | `POSTGRES_INTEGRATION_DATABASE=bantuin_integration DB_HOST=127.0.0.1 DB_PORT=5432 DB_USERNAME=postgres DB_PASSWORD=postgres php artisan test --compact tests/Integration/PostgreSQL/TeamFoundationMigrationTest.php` | all PostgreSQL tests pass; zero skipped |
| Fast regression | `php artisan test --compact tests/Feature/TeamFoundationTest.php` | all tests pass |
| Style | `vendor/bin/pint --dirty --format agent` | exit 0 |
| Full checks | `composer ci:check` | exit 0 |

The targeted local command requires a disposable PostgreSQL database named `bantuin_integration`. Do not run it against a shared or production database.

## Suggested executor toolkit

- Use the `laravel-best-practices` and `pest-testing` skills.
- Before editing, use Laravel Boost `search-docs` for `testing database configuration` and `postgresql testing`.

## Scope

**In scope**:
- `.github/workflows/tests.yml`
- `tests/Integration/PostgreSQL/TeamFoundationMigrationTest.php` only if a small assertion is needed to make skip/fail behavior machine-checkable
- `plans/README.md` status row

**Out of scope**:
- Replacing SQLite for the fast Unit and Feature suites.
- Rewriting the team-foundation migrations or PostgreSQL triggers.
- Running the full suite twice against PostgreSQL.
- Adding Docker or Sail configuration.
- Changing production database credentials or `.env`.

## Git workflow

- Suggested branch: `advisor/001-postgresql-ci`
- Match recent commit style: sentence case with a period, e.g. `Run PostgreSQL tenancy tests in CI.`
- Do not push or open a PR unless instructed.

## Steps

### Step 1: Add a dedicated PostgreSQL integration job

In `.github/workflows/tests.yml`, keep the existing `ci` matrix unchanged and add a separate job named `postgresql-integration`:

1. Use `ubuntu-latest`.
2. Add a `postgres:17` service with a test-only database, user, and password.
3. Add a health check using `pg_isready`; configure interval, timeout, and retries.
4. Check out with `persist-credentials: false`.
5. Set up PHP 8.5 with Composer and the `pdo_pgsql` extension.
6. Install Composer dependencies. Node, pnpm, Poppler, and asset compilation are not needed for this targeted PHP integration test.
7. Copy `.env.example` and generate an application key.
8. Run only `tests/Integration/PostgreSQL/TeamFoundationMigrationTest.php`.
9. Pass `POSTGRES_INTEGRATION_DATABASE`, `DB_HOST`, `DB_PORT`, `DB_USERNAME`, and `DB_PASSWORD` as step or job environment variables. Do not set `DB_URL`; `phpunit.xml` intentionally clears it.

The database name must be disposable and clearly test-only. The job should execute once, not once per PHP matrix version.

**Verify**: inspect the rendered workflow with `ruby -e 'require "yaml"; YAML.load_file(".github/workflows/tests.yml", aliases: true); puts "valid"'` → prints `valid`. If Ruby's YAML parser rejects the workflow solely because it parses the `on` key as boolean, use `python -c 'import yaml; yaml.safe_load(open(".github/workflows/tests.yml")); print("valid")'` only if PyYAML is already available; do not add a dependency just for this check.

### Step 2: Ensure unavailability cannot become a skip in CI

Keep the existing contract in `createTeamFoundationPostgresSchema()`: a populated `POSTGRES_INTEGRATION_DATABASE` must rethrow connection errors. If the current code still matches the excerpt, no PHP change is required.

If you change the test, limit the change to an assertion or helper naming that makes the non-skip contract explicit. Do not duplicate migration logic in the workflow.

**Verify**: run the targeted test with a deliberately invalid port while `POSTGRES_INTEGRATION_DATABASE` is set:

`POSTGRES_INTEGRATION_DATABASE=bantuin_integration DB_HOST=127.0.0.1 DB_PORT=1 DB_USERNAME=postgres DB_PASSWORD=postgres php artisan test --compact tests/Integration/PostgreSQL/TeamFoundationMigrationTest.php`

Expected: non-zero exit caused by connection failure; the output must not report all tests as skipped.

### Step 3: Run the real targeted suite

Start or use a disposable local PostgreSQL database and run the targeted command from the command table. Confirm all tests pass and none skip. Then run the fast SQLite regression and full checks.

**Verify**:
- Targeted PostgreSQL command → exit 0, zero skipped.
- `php artisan test --compact tests/Feature/TeamFoundationTest.php` → exit 0.
- `composer ci:check` → exit 0.

## Test plan

- Existing `TeamFoundationMigrationTest.php` remains the authoritative production-database test.
- Verify both modes:
  - without `POSTGRES_INTEGRATION_DATABASE`, local absence may skip;
  - with `POSTGRES_INTEGRATION_DATABASE`, absence must fail;
  - with the CI service available, all integration cases pass.
- Do not weaken or delete the existing trigger assertions.

## Done criteria

- [ ] `.github/workflows/tests.yml` contains a single dedicated PostgreSQL integration job.
- [ ] The job has a healthy PostgreSQL service and runs only `TeamFoundationMigrationTest.php`.
- [ ] The job sets `POSTGRES_INTEGRATION_DATABASE`, making connection failure fatal.
- [ ] The targeted suite passes with zero skips against PostgreSQL.
- [ ] Existing SQLite tests still pass.
- [ ] `composer ci:check` exits 0.
- [ ] No files outside Scope are modified.
- [ ] `plans/README.md` status is updated.

## STOP conditions

Stop and report if:

- The CI environment cannot run service containers.
- The integration database is not disposable.
- The test creates objects outside its random schema.
- Passing the suite requires changing production trigger semantics.
- An in-scope file has drifted semantically since `83f7e7e`.

## Maintenance notes

- Keep this job targeted; the fast SQLite matrix remains valuable.
- Any future PostgreSQL-specific migration or constraint test should join this job and must fail when PostgreSQL is unavailable.
- Reviewers should verify the service credentials are test-only literals and no real credential is introduced.
