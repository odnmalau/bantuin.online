# Plan 021: Release database locks before external I/O

> **Executor instructions**: This supersedes Plan 005. Preserve Team checks and Plan 020 idempotency; do not merely remove transactions. Implement a claim/execute/finalize protocol and update the index.
>
> **Drift check (run first)**: `git diff --stat 83f7e7e..HEAD -- app/Jobs/ScreenResumeWithAi.php app/Jobs/EvaluateAssessmentWithAi.php app/Jobs/SendInterviewInvitationEmail.php app/Services/AssessmentEvaluationPipeline.php app/AssessmentStatus.php app/Models/Assessment.php app/Ai/Gateway/QwenGateway.php config/assessment.php config/queue.php .env.example database/migrations tests`

## Status

- **Priority**: P1
- **Effort**: L
- **Risk**: HIGH
- **Depends on**: `plans/016-run-postgresql-tenancy-tests-in-ci.md`, `plans/020-make-assessment-transitions-atomic.md`
- **Category**: tech-debt
- **Planned at**: commit `83f7e7e`, 2026-07-12

## Why this matters

PDF extraction, Qwen screening/evaluation/criticism, and SMTP delivery currently run in transactions holding Team locks. Provider latency blocks unrelated Team writes, while mail may be externally delivered before local commit. Evaluation's 30-second job timeout is also shorter than its supported multi-call provider budget. Replace the current design with short claim/finalize transactions, attempt-aware persistence, and tested timeout/visibility budgets.

## Current state

- `ScreenResumeWithAi` wraps PDF and Qwen work in a Team-locking transaction.
- `EvaluateAssessmentWithAi` wraps the full pipeline in a Team-locking transaction.
- `SendInterviewInvitationEmail` sends before its Team-locking transaction commits.
- `AssessmentEvaluationPipeline` interleaves external calls, events, and persistence.
- Qwen timeout defaults to 30 seconds per call, transport uses `retry(2, 300)`, evaluation may repair once and then call critic, while the job timeout is 30 and database `retry_after` is 90.
- Plan 020 establishes atomic Admin transitions and stale-job no-ops.

Target:

```text
short Team->Assessment claim transaction
-> external work with DB::transactionLevel() === 0
-> short Team->Assessment finalize transaction guarded by status + attempt id
```

## Commands you will need

| Purpose | Command | Expected on success |
|---|---|---|
| Budget test | `php artisan test --compact tests/Unit/AssessmentQueueBudgetTest.php` | all pass |
| Job/workflow tests | `php artisan test --compact --filter="resume screening|evaluation job|interview email"` | all matching tests pass |
| PostgreSQL integration | command from Plan 016 | all pass, zero skipped |
| Style | `vendor/bin/pint --dirty --format agent` | exit 0 |
| Full checks | `composer ci:check` | exit 0 |

## Suggested executor toolkit

- Invoke `laravel-best-practices`, `ai-sdk-development`, and `pest-testing`.
- Search docs for queue timeouts/`retry_after`, HTTP retry semantics, locks, and after-commit dispatch.

## Scope

**In scope**:
- the three jobs named above
- `app/Services/AssessmentEvaluationPipeline.php`
- small immutable outcome/coordinator classes
- `app/AssessmentStatus.php`
- `app/Models/Assessment.php`
- `app/Ai/Gateway/QwenGateway.php`
- `config/assessment.php`, `config/queue.php`, `.env.example`
- one migration for attempt identifiers/timestamps
- status UI/types only for `email_sending`
- focused Unit, Feature, and PostgreSQL tests
- `plans/README.md`

**Out of scope**:
- Provider/model/prompt/ranking changes.
- Queue backend replacement.
- Parallel AI calls.
- Claiming exactly-once mail without provider support.
- Broad event-schema redesign.

## Git workflow

- Suggested branch: `advisor/021-external-io-boundaries`
- Suggested commit: `Release database locks before external calls.`
- Do not push or open a PR unless instructed.

## Steps

### Step 1: Establish lock order, attempt IDs, and processing status

Use one lock order everywhere: **Team then Assessment**.

Generate a migration for nullable:

- `resume_screening_attempt_id`;
- `evaluation_attempt_id`;
- `email_delivery_attempt_id`;
- corresponding started timestamps only if operationally useful.

Add `AssessmentStatus::EmailSending` and update status labels/types/badges.

Attempt IDs are internal coordination identifiers, not credentials.

**Verify**: fresh SQLite and PostgreSQL migrations pass; frontend typecheck passes if status UI changes.

### Step 2: Build guarded claim/finalize operations

Implement:

- claim: short transaction, locks Team then Assessment, validates active Team/exact source state, stores processing state + attempt ID + start event;
- finalize success/failure: same locks, requires expected state and matching attempt ID, persists fields/events;
- stale/mismatched attempt: no-op.

Reuse Plan 020's workflow vocabulary; do not introduce a second generic state machine.

**Verify**: a stale attempt cannot overwrite a newer retry.

### Step 3: Separate evaluation computation from persistence

Refactor `AssessmentEvaluationPipeline` so an external compute phase returns an immutable outcome:

- evaluator result;
- deterministic score/ranking;
- critic result/fallback;
- final fields/status;
- safe event metadata.

The compute phase must not update models or insert events. Apply the outcome only in guarded finalize transactions. Provider and critic fakes must assert `DB::transactionLevel() === 0`.

Preserve current output fields, status resolution, repair behavior, critic fallback, and event names.

**Verify**: existing AI/critic suites and new transaction-level assertions pass.

### Step 4: Refactor résumé screening

Claim `Submitted -> ResumeProcessing`; release locks; extract PDF and screen outside any transaction; finalize to `Submitted` under matching attempt guard. Failure still requires manual review and lets the chain continue. Deactivated Team or stale attempt discards output.

Both extractor and screener test doubles must see transaction level zero.

**Verify**: success, failure, deactivation, and stale-attempt cases pass.

### Step 5: Refactor mail delivery with explicit claim

Claim `Approved -> EmailSending`; commit; send at transaction level zero; finalize to `EmailSent`/`EmailFailed` only for matching attempt.

Concurrent duplicate jobs that cannot claim send nothing. If a retry finds its own attempt still `EmailSending` after a crash, do not blindly resend: record an outcome-unknown/manual-retry failure unless the configured transport supports a stable idempotency key. If provider idempotency exists, use the attempt ID.

**Verify**:

- two duplicate jobs send once;
- stale attempts do not mutate;
- simulated post-send persistence failure never automatically sends a second email.

### Step 6: Align end-to-end queue budgets

Confirm installed Laravel HTTP retry semantics. Make transport attempt count explicit in config and use it in `QwenGateway`.

Add `AssessmentQueueBudgetTest` asserting:

- `attemptSeconds = qwen_timeout * transport_attempt_count`;
- supported calls = initial evaluator + repair attempts + critic;
- evaluation job timeout exceeds supported calls plus processing margin;
- database `retry_after` exceeds job timeout plus safety margin.

Drive job timeout and queue retry-after from documented configuration/`.env.example`. Do not use sleeps.

**Verify**: budget test fails against old 30/90 defaults and passes with new coordinated values.

### Step 7: Prove lock release

At minimum, external fakes assert `DB::transactionLevel() === 0`. Add a PostgreSQL test using explicit synchronization—not arbitrary sleeps—if needed to prove an unrelated same-Team write can complete while a fake external callback is paused.

Final persistence must still recheck Team active status.

**Verify**: PostgreSQL tests pass repeatedly with zero skips.

### Step 8: Run all gates

**Verify**:
- `vendor/bin/pint --dirty --format agent` → exit 0.
- budget, job, workflow, and PostgreSQL tests → exit 0.
- frontend checks if status UI changed → exit 0.
- `composer ci:check` → exit 0.

## Test plan

- all external callbacks run at transaction level zero;
- Team/Assessment revalidated at claim and finalize;
- attempt mismatch discards stale outcomes;
- duplicate mail sends once;
- ambiguous post-send crash never auto-resends;
- timeout budget and queue visibility invariant;
- existing result fields/events and failure recovery remain.

## Done criteria

- [x] No PDF, Qwen, critic, or Mail call runs inside a DB transaction.
- [x] All job transitions use Team-then-Assessment lock order.
- [x] Attempt IDs guard persistence.
- [x] `EmailSending` prevents concurrent duplicate sends.
- [x] Job timeout covers supported AI calls and remains below `retry_after`.
- [x] Stale/ambiguous outcomes are deterministic.
- [x] SQLite, PostgreSQL, frontend (if touched), and full checks pass.
- [x] Only in-scope files changed.

## STOP conditions

Stop and report if:

- Plan 020 is incomplete.
- Installed retry semantics cannot be confirmed.
- Mail transport lacks idempotency while requirements demand exactly-once delivery.
- Computation cannot be separated from database writes without changing scoring semantics.
- Lock ordering conflicts with another transaction path.

## Maintenance notes

- Plan 005 is superseded by this plan; do not execute both.
- New AI passes must update the budget test.
- Exactly-once external effects require provider cooperation; reviewers must not accept stronger claims than the implementation proves.
