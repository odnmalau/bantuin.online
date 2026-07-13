# Plan 017: Preserve campaign history on deletion

> **Executor instructions**: Follow this plan exactly, including migration verification on both SQLite and PostgreSQL. Stop on any mismatch. Update the plan index row when done unless instructed otherwise.
>
> **Drift check (run first)**: `git diff --stat 83f7e7e..HEAD -- app/Http/Controllers/Admin/CampaignController.php app/Models/Campaign.php database/migrations tests/Feature/AdminCampaignTest.php tests/Integration/PostgreSQL/TeamFoundationMigrationTest.php resources/js/pages/admin/campaigns/index.tsx`

## Status

- **Priority**: P1
- **Effort**: M
- **Risk**: MED
- **Depends on**: `plans/016-run-postgresql-tenancy-tests-in-ci.md`
- **Category**: migration
- **Planned at**: commit `83f7e7e`, 2026-07-12

## Why this matters

Campaign deletion is blocked only after an assessment exists. Deleting a campaign with an accepted invitation or active exam cascades both records, erasing candidate history used to enforce the ADR's Team-Member/Candidate exclusivity and discarding in-progress answers. Hard deletion must be limited to pristine campaigns, with database constraints protecting history from non-controller deletion paths.

## Current state

- `app/Http/Controllers/Admin/CampaignController.php:238-246` checks only `$campaign->assessments()->exists()`.
- `campaign_invitations.campaign_id` and `exam_sessions.campaign_id` use `cascadeOnDelete()`.
- `app/Services/TeamInvitationService.php:81-94` derives candidate history from campaign invitations.
- ADR `docs/adr/0001-use-contextual-identities-for-team-tenancy.md` states identity context is mutually exclusive within a Team; historical candidacy is therefore durable domain data.
- `tests/Feature/AdminCampaignTest.php:580-614` covers pristine deletion and assessment-blocked deletion.

Current controller:

```php
if ($campaign->assessments()->exists()) {
    throw ValidationException::withMessages([
        'campaign' => __('Campaigns with submitted assessments cannot be deleted. Archive the campaign instead.'),
    ]);
}

$campaign->delete();
```

Migration convention: create a new timestamped migration with `php artisan make:migration ... --no-interaction`; never edit already-applied migrations.

## Commands you will need

| Purpose | Command | Expected on success |
|---|---|---|
| Generate migration | `php artisan make:migration restrict_campaign_history_deletion --no-interaction` | one migration created |
| Feature tests | `php artisan test --compact tests/Feature/AdminCampaignTest.php tests/Feature/TeamInvitationTest.php` | all pass |
| PostgreSQL tests | `POSTGRES_INTEGRATION_DATABASE=bantuin_integration DB_HOST=127.0.0.1 DB_PORT=5432 DB_USERNAME=postgres DB_PASSWORD=postgres php artisan test --compact tests/Integration/PostgreSQL/TeamFoundationMigrationTest.php` | all pass, zero skipped |
| Style | `vendor/bin/pint --dirty --format agent` | exit 0 |
| Full checks | `composer ci:check` | exit 0 |

## Suggested executor toolkit

- Invoke `laravel-best-practices`, `pest-testing`, and `inertia-react-development`.
- Use Boost `search-docs` for `migrations change foreign key restrictOnDelete` and `validation exception redirect`.

## Scope

**In scope**:
- `app/Http/Controllers/Admin/CampaignController.php`
- `app/Models/Campaign.php` only if the missing `examSessions()` relation is required
- one new migration under `database/migrations/`
- `tests/Feature/AdminCampaignTest.php`
- `tests/Integration/PostgreSQL/TeamFoundationMigrationTest.php` or a new focused PostgreSQL integration test
- `resources/js/pages/admin/campaigns/index.tsx` only to correct deletion confirmation copy
- `plans/README.md`

**Out of scope**:
- Soft-deleting Campaigns.
- Campaign cloning/versioning; Plan 022 handles lifecycle freeze and clone.
- Changing assessment retention.
- Purging existing invitations or sessions.
- Deleting or anonymizing candidates.

## Git workflow

- Suggested branch: `advisor/017-preserve-campaign-history`
- Suggested commit: `Preserve campaign history during deletion.`
- Do not push or open a PR unless instructed.

## Steps

### Step 1: Characterize every deletion boundary

Extend `AdminCampaignTest.php` with separate cases:

- pristine draft with no invitations, sessions, or assessments can be deleted;
- pending invitation blocks deletion;
- accepted invitation blocks deletion;
- in-progress session blocks deletion;
- finalized/auto-submitted session blocks deletion even if no assessment is attached;
- assessment continues to block deletion.

Each blocked response must contain one stable validation key (`campaign`) and leave the Campaign plus related record unchanged.

**Verify before implementation**: invitation/session cases fail against current code because cascades remove history.

### Step 2: Enforce the application-level pristine rule atomically

Update `CampaignController::destroy()` to perform its decision and deletion in a transaction:

1. Reload the Campaign with `lockForUpdate()`.
2. Check existence of assessments, invitations, and exam sessions from the locked Campaign.
3. If any exist, throw one clear validation error instructing the administrator to archive instead.
4. Delete only if all three relations are empty.

Add an `examSessions()` relation to `Campaign` only if no equivalent exists; the model is already included in Scope and the drift check for this conditional change.

Do not distinguish pending from accepted invitations: issuance itself creates candidate-related history and must make the Campaign non-pristine.

**Verify**: `php artisan test --compact tests/Feature/AdminCampaignTest.php --filter="delete a campaign"` → all deletion cases pass.

### Step 3: Replace cascading historical foreign keys

Generate one forward migration that:

- drops the existing foreign key on `campaign_invitations.campaign_id`;
- recreates it referencing `campaigns.id` with restricted/no-action deletion;
- does the same for `exam_sessions.campaign_id`;
- leaves assessment `nullOnDelete` unchanged because the controller already blocks assessed Campaigns and historical assessment behavior is outside this plan.

The `down()` method must restore the original cascades.

Use schema builder APIs, not raw engine-specific SQL, unless PostgreSQL testing proves a framework limitation. Verify generated constraint names rather than guessing.

**Verify**:
- fresh SQLite migration: `php artisan test --compact tests/Feature/AdminCampaignTest.php`.
- PostgreSQL command from the command table → zero skips and all pass.

### Step 4: Add database-level regression coverage

Against PostgreSQL, directly attempt to delete a Campaign that has:

- a Campaign Invitation;
- an Exam Session.

Assert `QueryException` and that related rows remain. Also assert a pristine Campaign can still be deleted.

Keep these checks isolated in a disposable schema using the helper pattern already in `TeamFoundationMigrationTest.php`.

In the same disposable schema, exercise both migration directions: apply `up()`, prove invitation/session foreign keys restrict Campaign deletion, apply `down()`, and prove the original cascade behavior is restored. Re-apply `up()` and prove the restrictions return. Never run this rollback verification against the normal development, shared, or production schema.

**Verify**: targeted PostgreSQL test passes.

### Step 5: Correct administrator-facing copy

Change deletion confirmation copy from “Campaigns with submitted assessments cannot be deleted” to state that Campaigns with invitations, exam attempts, or assessments must be archived. Reuse existing dialog components and design conventions.

**Verify**: update the existing source assertion in `AdminCampaignTest.php`; targeted test passes.

### Step 6: Run all gates

**Verify**:
- `vendor/bin/pint --dirty --format agent` → exit 0.
- targeted feature and PostgreSQL tests → exit 0.
- `pnpm run format:check && pnpm run lint:check && pnpm run types:check` → exit 0.
- `composer ci:check` → exit 0.

## Test plan

Follow `AdminCampaignTest.php:580-614` for HTTP assertions and the existing PostgreSQL schema helper for FK assertions. Cover pristine, invitation, session, and assessment states separately; do not use one dataset that hides which retention rule failed.

## Done criteria

- [ ] Only pristine Campaigns can be hard-deleted.
- [ ] Controller check and delete are in one transaction on a locked Campaign.
- [ ] Invitation and session foreign keys reject Campaign deletion.
- [ ] Migration `down()` restores prior constraints, proven in the disposable PostgreSQL schema test.
- [ ] UI copy accurately states retention rules.
- [ ] SQLite and PostgreSQL tests pass.
- [ ] Full checks pass and only in-scope files changed.

## STOP conditions

Stop and report if:

- Plan 016 is not complete and its PostgreSQL verification command is unavailable.
- Existing production data contains orphaned invitation/session rows that prevent constraint recreation.
- Schema builder generates different constraint names than the migration expects.
- Product requirements explicitly require destructive deletion of candidate history.

## Maintenance notes

- Archive remains the lifecycle action for used Campaigns.
- Plan 022 should reuse the same “has candidate activity” definition rather than creating a second predicate.
- Reviewers should reject any migration that silently deletes rows to make the new FK succeed.
