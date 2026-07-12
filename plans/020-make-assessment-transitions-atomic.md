# Plan 020: Make assessment transitions atomic and stale jobs idempotent

> **Executor instructions**: Centralize state transitions; do not patch individual race symptoms in controllers. Follow lock ordering and verification exactly. Update the plan index when complete.
>
> **Drift check (run first)**: `git diff --stat 83f7e7e..HEAD -- app/AssessmentStatus.php app/Http/Controllers/Admin/AssessmentController.php app/Jobs/EvaluateAssessmentWithAi.php app/Jobs/SendInterviewInvitationEmail.php app/Services/AssessmentEvaluationPipeline.php app/Services/AssessmentEventRecorder.php tests/Feature`

## Status

- **Priority**: P1
- **Effort**: L
- **Risk**: HIGH
- **Depends on**: `plans/016-run-postgresql-tenancy-tests-in-ci.md`
- **Category**: bug
- **Planned at**: commit `83f7e7e`, 2026-07-12

## Why this matters

Approval, rejection, retries, promotion, and overrides currently perform check-then-update/event/dispatch sequences without one transaction or row lock. Concurrent decisions can overwrite each other. Queued jobs also accept stale state: duplicate email work can turn an already-sent or rejected assessment into `email_failed`, and stale evaluation work can rerun a completed decision. One row-locked transition service must own allowed source states, event creation, and after-commit dispatch.

## Current state

- `AssessmentController::approve()` and `reject()` update, record, and dispatch separately after an unlocked check.
- Retry methods reset/update, record, and dispatch separately.
- `EvaluateAssessmentWithAi` checks Team state but not exact Assessment source status.
- `SendInterviewInvitationEmail` marks every non-sendable status as `EmailFailed`.
- `AssessmentStatus::isReviewable()` defines current review source states.
- `TeamMembershipService` is the local transaction + row-lock + revalidation exemplar.

Current stale-job bug:

```php
if (! $this->assessmentIsSendable($assessment)) {
    $this->markEmailFailed(...);
    return;
}
```

`EmailSent` or `Rejected` means stale/inapplicable work, not failed delivery.

## Commands you will need

| Purpose | Command | Expected on success |
|---|---|---|
| Locate tests | `rg "Admin approved|email_sent|retry evaluation" tests -l` | prints existing focused files |
| Workflow/job tests | `php artisan test --compact <files found above>` | all pass |
| PostgreSQL integration | command from Plan 016 | all pass, zero skipped |
| Style | `vendor/bin/pint --dirty --format agent` | exit 0 |
| Full checks | `composer ci:check` | exit 0 |

## Suggested executor toolkit

- Invoke `laravel-best-practices` and `pest-testing`.
- Use Boost `search-docs` for `lockForUpdate transaction afterCommit queued job`.

## Scope

**In scope**:
- new `app/Services/AssessmentWorkflowService.php`
- `app/Http/Controllers/Admin/AssessmentController.php`
- `app/Jobs/EvaluateAssessmentWithAi.php`
- `app/Jobs/SendInterviewInvitationEmail.php`
- `app/Services/AssessmentEvaluationPipeline.php` only for source-status cooperation
- `app/AssessmentStatus.php` only for transition helpers; no new delivery status here
- existing assessment workflow/job tests
- one focused PostgreSQL locking test if needed
- `plans/README.md`

**Out of scope**:
- Moving external calls out of locks (Plan 021).
- Campaign lifecycle/versioning.
- Changing thresholds or reviewable-state business rules.
- Exactly-once external delivery.
- UI redesign.

## Git workflow

- Suggested branch: `advisor/020-assessment-transitions`
- Suggested commit: `Make assessment transitions atomic.`
- Do not push or open a PR unless instructed.

## Steps

### Step 1: Characterize legal transitions and stale jobs

Add failing tests:

- two stale Assessment instances attempt approve then reject; first committed transition wins;
- approve creates one event and queues one email job;
- duplicate email job after `EmailSent` is a no-op;
- email job after `Rejected` is a no-op;
- `Approved` with missing recipient/subject/body becomes `EmailFailed`;
- evaluation job from any status except `Submitted` is a no-op;
- retry evaluation from `EvaluationFailed` resets and queues once.

Use fakes and stale model instances. Use PostgreSQL only for a behavior that depends on real lock blocking.

**Verify before implementation**: stale decision and duplicate email tests fail.

### Step 2: Create one row-locked workflow service

Generate `AssessmentWorkflowService` using Artisan. Move orchestration for retry evaluation, retry email, promote, override score, approve, and reject from the controller.

Each method:

1. starts `DB::transaction`;
2. locks/reloads the current Team/Assessment in the repository's established order;
3. rechecks Team writability and exact source state on the locked row;
4. updates Assessment and records one event in the same transaction;
5. dispatches only after commit;
6. returns the updated Assessment.

Keep validation messages/event names stable. Controllers validate input, call the service, and redirect.

**Verify**: controller contains no direct Assessment transition updates for these six actions.

### Step 3: Make stale evaluation jobs no-ops

At job claim:

- reload under the lock protocol;
- require `Submitted`;
- wrong Team, inactive Team, missing Assessment, or any other status returns without mutation;
- claim `Evaluating` once before pipeline work.

Make the pipeline cooperate with a claimed `Evaluating` row instead of blindly re-transitioning any stale model. Final persistence must also require the expected processing state.

**Verify**: duplicate invocation after terminal evaluation leaves fields and events unchanged.

### Step 4: Distinguish stale mail from delivery failure

In `SendInterviewInvitationEmail`:

- only complete `Approved` data is sendable;
- incomplete `Approved` data is a genuine `EmailFailed`;
- every other status is a logged no-op;
- logs contain identifiers/status only.

The current Team lock serializes duplicate jobs until Plan 021 replaces it. The second job must observe `EmailSent` and return.

**Verify**: duplicate/rejected no-op tests and existing transport-failure tests pass.

### Step 5: Prove atomic state/event/dispatch behavior

Assert each accepted action writes exactly one transition event. Introduce a controlled test-only event-recorder failure through container binding and assert state rolls back and no job dispatches.

Do not add production fault-injection code.

**Verify**: atomicity test passes; PostgreSQL test passes if added.

### Step 6: Run all gates

**Verify**:
- `vendor/bin/pint --dirty --format agent` → exit 0.
- all discovered workflow/job suites → exit 0.
- PostgreSQL test, if added → exit 0, zero skipped.
- `composer ci:check` → exit 0.

## Test plan

- stale approve versus reject;
- one event per accepted transition;
- rollback prevents state, event, and dispatch;
- stale evaluation job no-op;
- stale email job no-op for `EmailSent` and `Rejected`;
- malformed `Approved` delivery fails;
- retry source-state rules remain unchanged.

## Done criteria

- [x] One service owns all six Admin transitions.
- [x] Every transition locks and revalidates current state.
- [x] State and event commit atomically.
- [x] Jobs dispatch after commit.
- [x] Stale evaluation/mail jobs are no-ops.
- [x] Genuine failures retain current recovery behavior.
- [x] Targeted, PostgreSQL (if used), and full checks pass.
- [x] Only in-scope files changed.

## STOP conditions

Stop and report if:

- Plan 016 is incomplete and a lock invariant cannot be proven on SQLite.
- A new public delivery status is required; that belongs to Plan 021.
- Lock ordering conflicts with another transaction path.
- Existing consumers intentionally depend on contradictory duplicate events.

## Maintenance notes

- Plan 021 depends on this service and stale-job behavior.
- Reviewers should inspect source-state checks after lock acquisition.
- Application idempotency does not itself prove exactly-once external delivery.
