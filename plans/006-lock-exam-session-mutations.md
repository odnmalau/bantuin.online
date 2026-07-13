# Plan 006: Lock exam sessions when recording violations and saving drafts

> **Executor instructions**: Follow this plan step by step. Run every verification command and confirm the expected result before moving to the next step. If anything in the "STOP conditions" section occurs, stop and report — do not improvise. When done, update the status row for this plan in `plans/README.md` — unless a reviewer dispatched you and told you they maintain the index.
>
> **Drift check (run first)**: `git diff --stat 83f7e7e..HEAD -- app/Services/ExamSessionService.php app/Services/ExamSessionFinalizer.php tests/Feature/SecureExamSessionTest.php tests/Feature/ExamSessionFinalizerTest.php`
> If any in-scope file changed since this plan was written, compare the "Current state" excerpts against the live code before proceeding; on a mismatch, treat it as a STOP condition.

## Status

- **Priority**: P1
- **Effort**: M
- **Risk**: MED
- **Depends on**: plans/003-unstick-section-timer-expiry.md (land 003 first — both edit `ExamSessionService`)
- **Category**: bug
- **Planned at**: commit `83f7e7e`, 2026-07-12

## Why this matters

`recordViolation` and `saveCurrentSectionAnswers` read-modify-write `warning_count` / `integrity_events` / `answer_drafts` without locking the `exam_sessions` row. Concurrent proctoring events can lose warnings and miss auto-submit at max warnings; concurrent autosaves can drop answers (last write wins). `startSession` and `ExamSessionFinalizer` already use `lockForUpdate` — match that pattern.

## Current state

- Unlocked paths in `app/Services/ExamSessionService.php`:
  - `saveCurrentSectionAnswers` (~89–110): loads drafts, merges, `update`
  - `recordViolation` (~136–177): increments warnings, maybe finalize — no lock
- Locked exemplar: `startSession` uses `DB::transaction` + `Team::lockForUpdate()`; finalizer locks the session row (`ExamSessionFinalizer.php` ~41–69).
- Integrity auto-submit already calls finalize with `allowIncompleteAnswers: true`.

## Commands you will need

| Purpose | Command | Expected on success |
|---------|---------|---------------------|
| Tests | `php artisan test --compact tests/Feature/SecureExamSessionTest.php tests/Feature/ExamSessionFinalizerTest.php` | all pass |
| Pint | `vendor/bin/pint --dirty --format agent` | exit 0 |

## Suggested executor toolkit

- `laravel-best-practices`, `pest-testing`.

## Scope

**In scope**:
- `app/Services/ExamSessionService.php`
- `tests/Feature/SecureExamSessionTest.php` and/or `tests/Feature/ExamSessionFinalizerTest.php`
- `plans/README.md`

**Out of scope**:
- Frontend proctoring event batching
- Changing max warning defaults
- Re-architecting finalizer beyond what’s needed to avoid double-finalize races (finalizer already locks)

## Git workflow

- Branch: `advisor/006-lock-exam-session-mutations`
- Commit: `Lock exam sessions for draft saves and integrity violations.`
- Do NOT push/PR unless asked.

## Steps

### Step 1: Wrap `saveCurrentSectionAnswers` in a session row lock

Use a two-phase flow; do not invoke the finalizer while this method owns the session row lock:

1. In a short `DB::transaction`, reload the session with `lockForUpdate()` and re-run `assertSessionActive`.
2. Under that lock, determine whether section-expiry synchronization is due. If due, return an internal `needs_expiry_sync` result without mutating drafts, then commit.
3. Outside the transaction, call the post-Plan-003 `syncSectionExpiry`. If it finalized or advanced, return the fresh session without merging the original request payload. The payload belongs to the expired section and must never be applied to the next section; the client can submit a new request for the newly current section.
4. When expiry is not due, merge drafts and update **the locked instance** inside the transaction.

Keep the transaction limited to database reads/writes. Do not lock Team or Campaign rows in this path and do not call `ExamSessionFinalizer` while the session lock is held.

**Verify**: `rg -n "lockForUpdate|DB::transaction" app/Services/ExamSessionService.php` → both patterns occur in the save path; targeted expiry and save tests pass.

### Step 2: Wrap `recordViolation` similarly

1. In a short transaction, lock the session row and re-check active state.
2. Append the event and increment the warning from **locked** current values, not stale caller state.
3. Persist the warning/event and return a `should_finalize` boolean from the transaction.
4. Commit the warning transaction.
5. Only when `should_finalize` is true, invoke the existing public finalizer outside the transaction. Rely on its session lock and idempotent terminal-session behavior if another request finalized first.

Do not use a nested finalizer transaction and do not hold the session lock during queue dispatch or other external work.

**Verify**: `php artisan test --compact tests/Feature/SecureExamSessionTest.php tests/Feature/ExamSessionFinalizerTest.php --filter="violation|max warnings|auto-submit"` → all matching tests pass and one Assessment exists.

### Step 3: Tests

Add tests (SQLite-friendly if that’s the default test DB — check `phpunit.xml` / `phpunit.xml.dist`):

1. Sequential double-save merges both keys (sanity).
2. If concurrent tests are hard on SQLite: document limitation and add a regression test that `recordViolation` uses a transaction (feature-level: fire max warnings and assert single assessment). Optionally use `DB::transaction` nesting simulation.
3. Prefer at least: two rapid violation posts reaching max warnings create **one** assessment (idempotent finalize).

Pattern: existing integrity test in `SecureExamSessionTest.php`.

**Verify**: test command in table → pass.

### Step 4: Pint

`vendor/bin/pint --dirty --format agent`

## Test plan

- Max warnings still auto-submits once.
- Save after lock still persists drafts.
- Finalizer suite still green.
- True parallel concurrency may be environment-limited — do not fail the plan solely because SQLite cannot express two writers; cover sequential race-equivalent paths.

## Done criteria

- [ ] `saveCurrentSectionAnswers` and `recordViolation` lock the exam session row inside a transaction
- [ ] Finalizer is called only after the mutation transaction commits
- [ ] A concurrent/stale second finalization resolves idempotently to one Assessment
- [ ] Pest suites above pass
- [ ] Scope respected; README DONE

## STOP conditions

- Plan 003 not yet applied and soft-lock still throws inside the new transaction on common paths — stop and land 003 first.
- Post-Plan-003 expiry synchronization cannot be split from the locked draft mutation without invoking the finalizer while the lock is held — stop and report the live method shape.
- Finalizer is not idempotent when two callers finalize the same session — stop and coordinate with the Assessment/session transition work before proceeding.

## Maintenance notes

- Reviewers: lock duration should stay short (no HTTP inside these transactions).
- Chatty proctoring clients may contend — acceptable vs lost warnings.
