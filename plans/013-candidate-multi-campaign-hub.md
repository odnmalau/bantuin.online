# Plan 013: Ship a candidate multi-campaign exam hub

> **Executor instructions**: Follow this plan step by step. Run every verification command and confirm the expected result before moving to the next step. If anything in the "STOP conditions" section occurs, stop and report — do not improvise. When done, update the status row for this plan in `plans/README.md` — unless a reviewer dispatched you and told you they maintain the index.
>
> **Drift check (run first)**: `git diff --stat 83f7e7e..HEAD -- app/Http/Controllers/Candidate/AssessmentController.php app/Services/CampaignInvitationService.php app/Services/CandidateExamPage.php resources/js/pages/candidate/exam.tsx tests/Feature/CandidateExamPageTest.php tests/Feature/CandidateAssessmentTest.php`
> If any in-scope file changed since this plan was written, compare the "Current state" excerpts against the live code before proceeding; on a mismatch, treat it as a STOP condition.

## Status

- **Priority**: P2
- **Effort**: M
- **Risk**: LOW
- **Depends on**: none
- **Category**: direction
- **Planned at**: commit `83f7e7e`, 2026-07-12

## Why this matters

ADR-0001 and `CONTEXT.md` allow a user to be a Candidate in multiple Campaigns. `CampaignInvitationService::accessibleCampaignsForUser()` already returns every accepted active campaign, and `AssessmentController::redirectExam` auto-redirects when count === 1. When N>1 it renders the exam page with `campaign = null`, which becomes `no_campaign` / “reopen your email invite” — a dead end even though assignments exist. Completing this surface is product completion of contextual candidacy, not a speculative feature.

## Current state

- `AssessmentController::redirectExam` (~28–36): count === 1 redirect; else `renderExam($request, null)`.
- `CandidateExamPage` returns `state: 'no_campaign'` when campaign is null (~26).
- `resources/js/pages/candidate/exam.tsx` `no_campaign` → `EmptyQuestionsState`.
- `accessibleCampaignsForUser` (~249–259) already filters Active team + Approved questions + accepted invitations.
- Nav: single “Exam” item in `resources/js/lib/main-nav-items.ts`.

## Commands you will need

| Purpose | Command | Expected on success |
|---------|---------|---------------------|
| Tests | `php artisan test --compact tests/Feature/CandidateExamPageTest.php tests/Feature/CandidateAssessmentTest.php` | all pass |
| Types | `pnpm run types:check` | exit 0 |
| Pint | `vendor/bin/pint --dirty --format agent` | exit 0 |

## Suggested executor toolkit

- `inertia-react-development`, `wayfinder-development`, `pest-testing`, `tailwindcss-development`, `laravel-best-practices`.

## Scope

**In scope**:
- `app/Http/Controllers/Candidate/AssessmentController.php`
- `app/Services/CandidateExamPage.php` (new state e.g. `campaign_picker` **or** pass list via controller props)
- `resources/js/pages/candidate/exam.tsx` (picker UI)
- Feature tests for 0 / 1 / N campaigns
- `plans/README.md`

**Out of scope**:
- Changing invitation acceptance rules
- Bulk campaign actions
- Redesigning main nav beyond linking to the same exam entry
- Question library work (015)

## Git workflow

- Branch: `advisor/013-candidate-multi-campaign-hub`
- Commit: `Show a campaign picker when candidates have multiple exams.`
- Do NOT push/PR unless asked.

## Steps

### Step 1: Backend — pass accessible campaigns on N≠1

When `accessibleCampaignsForUser` count is 0: keep empty/`no_campaign` behavior.
When count === 1: keep redirect.
When count > 1: render Inertia with a dedicated state (prefer `campaign_picker`) including a compact list:

- `id`, `title`, `role_title`, `team` name if cheap, status badge (not started / in progress / submitted) via existing assessment lookup patterns already used on candidate pages.

Deep links must use existing named route `candidate.campaigns.exam` (confirm via `php artisan route:list --name=candidate`).

**Verify**: feature test with two accepted campaigns asserts Inertia component props include both ids and does not redirect.

### Step 2: Frontend picker

Replace `EmptyQuestionsState` for the multi-campaign case with a simple list of links/buttons to each campaign exam (match existing candidate page visual language — no new card-heavy dashboard). Keep empty state for true zero campaigns.

**Verify**: `pnpm run types:check` exit 0.

### Step 3: Tests

Cover:

1. 0 campaigns → empty state.
2. 1 campaign → redirect (existing).
3. 2 campaigns → picker props, no redirect.
4. Submitted vs in-progress badge if you add them.

Pattern: `CandidateExamPageTest` / `CandidateAssessmentTest`.

**Verify**: Pest command → pass; Pint.

## Test plan

- As above; use factories for invitations/campaigns already in candidate tests.

## Done criteria

- [ ] N>1 no longer shows false `no_campaign` empty state
- [ ] Single-campaign redirect preserved
- [ ] Tests + types + pint pass
- [ ] README DONE

## STOP conditions

- `accessibleCampaignsForUser` semantics change to exclude in-progress needs — stop and align with product rather than filtering ad hoc in the controller.
- Exam page is being split into multiple routes in parallel work — stop and reconcile.

## Maintenance notes

- Reviewers: ensure Team names don’t leak cross-tenant beyond campaigns the user was invited to (query already invitation-scoped).
- Follow-up: show expired invitations separately if needed.
