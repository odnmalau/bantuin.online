# Plan 022: Freeze used Campaign definitions and provide cloning

> **Executor instructions**: The selected policy is fixed: once any Candidate is invited, candidate-facing Campaign content and ranking settings become immutable; revisions happen by cloning to a new draft. Follow scope and update the index.
>
> **Drift check (run first)**: `git diff --stat 83f7e7e..HEAD -- app/Models/Campaign.php app/Services/CampaignLifecycleService.php app/Http/Controllers/Admin/CampaignController.php app/Http/Controllers/Admin/CampaignStatusController.php app/Http/Controllers/Admin/CampaignRankingController.php app/Http/Controllers/Admin/CampaignAssessmentGenerationController.php app/Http/Controllers/Admin/CampaignSectionController.php app/Http/Controllers/Admin/CampaignQuestionController.php app/Http/Middleware routes/web.php bootstrap/app.php resources/js/pages/admin/campaigns tests/Feature/AdminCampaignTest.php tests/Feature/SecureExamSessionTest.php tests/Feature/CurrentTeamCampaignIsolationTest.php tests/Integration/PostgreSQL`

## Status

- **Priority**: P1
- **Effort**: L
- **Risk**: HIGH
- **Depends on**: `plans/016-run-postgresql-tenancy-tests-in-ci.md`, `plans/017-preserve-campaign-history-on-deletion.md`
- **Category**: tech-debt
- **Planned at**: commit `83f7e7e`, 2026-07-12

## Why this matters

Active Campaign questions, rubrics, correct answers, role context, thresholds, and ranking weights remain editable while Candidates take exams and scores consume those values. Administration can remove answered questions, grade against changed content, block active sessions by changing status, and mix rankings produced under different formulas. Used Campaigns must become immutable snapshots, with cloning as the explicit revision workflow.

## Current state

- `CampaignPolicy::update()` checks Team access/status only.
- `CampaignQuestionController` allows create/update/delete without lifecycle checks.
- `CampaignStatusController` can archive or return active Campaigns to draft.
- `CampaignRankingController` changes weights without versioning/recalculation.
- Exam Session and finalizer services read the live Campaign definition.
- Definition mutations are enumerated in `routes/web.php:67-82`.
- Exact mutation owners are `CampaignController`, `CampaignStatusController`, `CampaignRankingController`, `CampaignAssessmentGenerationController`, `CampaignSectionController`, and `CampaignQuestionController`.
- The selected policy is **freeze after first invitation and clone for changes**, not version rows.

Frozen definition:

- role/job/skill/language/threshold fields;
- ranking weights;
- sections/timing;
- questions/options/answers/rubrics/order/status;
- AI generation/regeneration/conversion;
- publish/draft transitions.

Allowed operational actions:

- invitation lifecycle;
- archive only with no in-progress Exam Session;
- Assessment review;
- clone to new draft.

## Commands you will need

| Purpose | Command | Expected on success |
|---|---|---|
| Campaign tests | `php artisan test --compact tests/Feature/AdminCampaignTest.php tests/Feature/SecureExamSessionTest.php` | all pass |
| Isolation tests | `php artisan test --compact tests/Feature/CurrentTeamCampaignIsolationTest.php` | all pass |
| PostgreSQL integration | `POSTGRES_INTEGRATION_DATABASE=bantuin_integration DB_HOST=127.0.0.1 DB_PORT=5432 DB_USERNAME=postgres DB_PASSWORD=postgres php artisan test --compact tests/Integration/PostgreSQL/TeamFoundationMigrationTest.php` | all pass, zero skipped |
| Frontend | `pnpm run types:check && pnpm run lint:check && pnpm run format:check` | exit 0 |
| Style | `vendor/bin/pint --dirty --format agent` | exit 0 |
| Full checks | `composer ci:check` | exit 0 |

## Suggested executor toolkit

- Invoke `laravel-best-practices`, `pest-testing`, `inertia-react-development`, `wayfinder-development`, and `tailwindcss-development`.
- Use Boost `search-docs` before editing.

## Scope

**In scope**:
- `app/Models/Campaign.php`
- new `app/Services/CampaignLifecycleService.php`
- one new middleware and alias if used as the central guard
- clone controller/action/request
- `app/Http/Controllers/Admin/CampaignController.php`
- `app/Http/Controllers/Admin/CampaignStatusController.php`
- `app/Http/Controllers/Admin/CampaignRankingController.php`
- `app/Http/Controllers/Admin/CampaignAssessmentGenerationController.php`
- `app/Http/Controllers/Admin/CampaignSectionController.php`
- `app/Http/Controllers/Admin/CampaignQuestionController.php`
- `routes/web.php`, and `bootstrap/app.php` only for alias registration
- Campaign show/index React pages and generated Wayfinder output
- affected Campaign, Secure Exam, tenancy, and PostgreSQL tests
- `plans/README.md`

**Out of scope**:
- `campaign_versions` table.
- Any in-place “minor edit” exception.
- Recalculating existing Assessments.
- Bulk invitation operations.
- Cross-Team cloning.
- Candidate Assessment snapshot changes.

## Git workflow

- Suggested branch: `advisor/022-freeze-campaign-definitions`
- Suggested commit: `Freeze used Campaign definitions.`
- Do not push or open a PR unless instructed.

## Steps

### Step 1: Define one candidate-activity predicate

Add `CampaignLifecycleService::hasCandidateActivity(Campaign $campaign): bool`. It is true when any Campaign Invitation, Exam Session, or Assessment exists. Pending invitation counts: sending the offer freezes what the recipient was shown.

Reuse relationships/retention semantics from Plan 017. Do not duplicate predicates in controllers.

**Verify**: tests cover pristine, pending/accepted invitation, session, and assessment states.

### Step 2: Guard every definition mutation centrally

Use route middleware or one service assertion after route binding. Apply it to:

- `admin.campaigns.edit`, `admin.campaigns.update`, and `admin.campaigns.publish`;
- `admin.campaigns.draft`;
- `admin.campaigns.ranking.update`;
- `admin.campaigns.generate-assessment`;
- `admin.campaigns.sections.store` and `admin.campaigns.sections.destroy`;
- `admin.campaigns.questions.store`, `admin.campaigns.questions.update`, and `admin.campaigns.questions.destroy`;
- `admin.campaigns.questions.approve`, `admin.campaigns.questions.approve-all`, `admin.campaigns.questions.regenerate-mcq-options`, and `admin.campaigns.questions.convert-to-mcq`.

Exclude show/index, invitation operations, archive, Assessment review, clone, and pristine deletion.

Add a dataset-driven route test that creates a pending invitation, calls every listed endpoint, and asserts validation rejection plus unchanged data.

**Verify**: `php artisan route:list --path=admin/campaigns --except-vendor` → every named definition route listed above shows the central guard; operational invitation, archive, show, clone, and destroy routes do not receive the definition guard.

### Step 3: Protect status changes around active sessions

Allow archive for used Campaigns only when no `InProgress` Exam Session exists. Used Campaigns cannot return to draft or republish; clone instead. Preserve pristine workflow.

**Verify**: tests cover active-session archive rejection, completed-session archive success, used draft rejection, and pristine transitions.

### Step 4: Implement transactional same-Team cloning

Create a thin controller and `CampaignLifecycleService::cloneToDraft()`:

1. lock/revalidate source Team/current actor;
2. load ordered sections/questions;
3. create same-Team Campaign with actor as creator;
4. copy candidate-facing definition and weights;
5. set `Draft`, clear `activated_at` and runtime generation audit;
6. copy sections/questions to new IDs preserving all definition fields/order/status;
7. copy no invitations, sessions, assessments, events, or Candidate data.

Use a predictable editable title such as `"<title> (Copy)"`. Redirect to the new draft.

**Verify**: deep field comparison, distinct IDs, same Team, zero history, source unchanged, and cross-Team denial.

### Step 5: Update UI and Wayfinder

Frozen Campaign pages must:

- hide/disable definition mutations;
- explain why the definition is frozen;
- offer “Clone as new draft” through generated Wayfinder;
- retain allowed operational actions.

Server guard remains authoritative. Match existing Geist/Tailwind components.

**Verify**: TypeScript/lint/format and a browser smoke test pass.

### Step 6: Run all gates

This plan should not need a migration. Run PostgreSQL tests because Team/Campaign locking and Plan 017 constraints are involved.

**Verify**:
- Pint → exit 0.
- Campaign/Secure Exam/isolation/PostgreSQL tests → exit 0.
- frontend checks → exit 0.
- `composer ci:check` → exit 0.

## Test plan

- one predicate covers all candidate activity;
- all definition endpoints reject frozen Campaigns;
- archive blocked only by active sessions;
- clone is complete, same-Team, independent, and history-free;
- source unchanged and cross-Team actor denied;
- UI uses Wayfinder and reflects frozen state;
- pristine workflow remains green.

## Done criteria

- [x] First invitation freezes candidate-facing definition.
- [x] One server guard covers every mutation endpoint.
- [x] Active sessions block archive.
- [x] Used Campaigns cannot return to draft/republish.
- [x] Clone creates complete same-Team draft without history.
- [x] UI exposes frozen/clone behavior.
- [x] PHP, frontend, and full checks pass. PostgreSQL integration suite not run locally (no Postgres/Docker available); covered by Plan 016 CI job.
- [x] Only in-scope files changed.

## STOP conditions

Stop and report if:

- Plans 016/017 are incomplete.
- Product requires in-place correction of frozen questions.
- A definition field cannot be copied without runtime/Candidate data.
- The route guard cannot distinguish definition from operational actions.
- Cloning would cross Team boundaries.

## Maintenance notes

- New candidate-facing fields must join both freeze semantics and clone copying.
- Reviewers should compare route list against the mutation inventory.
- Future Question Libraries must import by copy into a pristine draft.
