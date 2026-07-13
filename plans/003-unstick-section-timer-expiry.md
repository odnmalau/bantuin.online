# Plan 003: Unstick exams when a section timer expires with incomplete answers

> **Executor instructions**: Follow this plan step by step. Run every verification command and confirm the expected result before moving to the next step. If anything in the "STOP conditions" section occurs, stop and report — do not improvise. When done, update the status row for this plan in `plans/README.md` — unless a reviewer dispatched you and told you they maintain the index.
>
> **Drift check (run first)**: `git diff --stat 83f7e7e..HEAD -- app/Services/ExamSessionService.php app/Services/ExamSessionFinalizer.php app/Services/CandidateExamPage.php resources/js/hooks/use-exam-timer.ts tests/Feature/SecureExamSessionTest.php tests/Feature/ExamSessionFinalizerTest.php`
> If any in-scope file changed since this plan was written, compare the "Current state" excerpts against the live code before proceeding; on a mismatch, treat it as a STOP condition.

## Status

- **Priority**: P1
- **Effort**: M
- **Risk**: MED
- **Depends on**: none
- **Category**: bug
- **Planned at**: commit `83f7e7e`, 2026-07-12

## Why this matters

When a section timer expires and the candidate still has unanswered questions, `syncSectionExpiry` throws a validation error instead of advancing or finalizing. That sync runs on save, advance, finalize, start-of-existing-session, and **Inertia exam page payload** (`sessionPayloadForInertia`). The client also reloads on expiry (`useSectionExpiryReload`). Result: a soft-locked `InProgress` Exam Session with no recovery path. Copy for `section_timer_expired` already exists in `ExamSessionFinalizer` but nothing calls finalize with that reason (only integrity auto-submit uses `allowIncompleteAnswers: true`).

Additionally, `advanceSection` always calls `syncSectionExpiry` then always continues into `completeSectionAndAdvance`. When expiry already advanced a completed section, the follow-on advance logic can mis-behave on multi-section exams. Fix composition in the same plan.

## Current state

- Soft-lock throw (`app/Services/ExamSessionService.php`):

```php
try {
    $this->assertSectionAnswersComplete($session, $section);
} catch (ValidationException) {
    throw ValidationException::withMessages([
        'session' => __('Time expired for this section. Answer every question before the timer ends.'),
    ]);
}
$this->completeSectionAndAdvance($session, $campaign, $section);
```

- Payload path always syncs: `sessionPayloadForInertia` → `syncSectionExpiry` (same file ~201–203).
- Client reload: `resources/js/hooks/use-exam-timer.ts` `useSectionExpiryReload` calls `router.reload(...)` when expired.
- Finalizer already documents the reason string `'section_timer_expired'` in `recordSubmissionEvents`.
- Integrity incomplete path exemplar (`recordViolation` → finalize with `allowIncompleteAnswers: true`).
- **Critical**: read `ExamSessionFinalizer::finalize` / `finalizeLocked` for resume requirements — auto-submit on timer expiry must not deadlock because a resume file is still required. If the finalizer unconditionally requires `resume_path`, extend it so integrity/timer auto-submit paths may finalize without a resume (preserve drafts; record `resume_uploaded: false`). Do not invent a fake resume upload.
- Existing test `candidate cannot advance after the section timer expires` (`SecureExamSessionTest.php`) expects `section` errors when answers are **complete** after expiry — update carefully when changing composition.
- Domain vocab: **Exam Session**, **Campaign**, **Candidate** (`CONTEXT.md`).

## Commands you will need

| Purpose | Command | Expected on success |
|---------|---------|---------------------|
| Exam tests | `php artisan test --compact tests/Feature/SecureExamSessionTest.php tests/Feature/ExamSessionFinalizerTest.php tests/Feature/CandidateExamPageTest.php` | all pass |
| Pint | `vendor/bin/pint --dirty --format agent` | exit 0 |

## Suggested executor toolkit

- `laravel-best-practices`, `pest-testing`, `inertia-react-development` (only if you must tweak client expiry UX).

## Scope

**In scope**:
- `app/Services/ExamSessionService.php` (sync expiry behavior + advance composition)
- `app/Services/ExamSessionFinalizer.php` to prevent duplicate expiry validation on incomplete auto-submit and, if required by the selected policy, narrowly support résumé-less timer auto-submit
- `tests/Feature/SecureExamSessionTest.php` (new + updated timer tests; multi-section cases)
- Optionally `tests/Feature/CandidateExamPageTest.php` if page render after incomplete expiry needs coverage
- `resources/js/hooks/use-exam-timer.ts` only if reload must change to a finalize/visit URL — prefer fixing server so reload works
- `plans/README.md`

**Out of scope**:
- Changing max integrity warnings / proctoring UI
- Plan 006 locking (land this timer fix before Plan 006)
- Admin assessment workstation
- Changing default timer config keys

## Git workflow

- Branch: `advisor/003-unstick-section-timer-expiry`
- Commit style: `Unstick exam sessions when section timers expire incomplete.`
- Do NOT push/PR unless asked.

## Steps

### Step 1: Define the intended expiry policy (implement this policy; do not invent another)

**Policy (required)**:

1. If section timer is past and answers for that section are **complete** → advance via `completeSectionAndAdvance` (keep today’s happy path).
2. If section timer is past and answers are **incomplete**:
   - If this is the **last** incomplete section path toward finishing the exam (no further sections after this one, or product-equivalent: candidate cannot continue), **auto-finalize** the session with `submissionReason: 'section_timer_expired'`, status appropriate for auto-submit (reuse `ExamSessionStatus::AutoSubmitted` like integrity max warnings), and `allowIncompleteAnswers: true`.
   - If there **are** later sections: still auto-finalize the whole exam incomplete on section expiry (do **not** skip ahead with blank later sections). Rationale: section timers gate that section; skipping would create CORRECTNESS-02 skip risk. Prefer one consistent rule: **incomplete expiry always auto-finalizes**.
3. **Never throw** from `sessionPayloadForInertia` / read paths because of incomplete expiry — finalize or return a terminal payload instead.

If you believe last-section-only finalize vs always-finalize is a product fork, implement **always auto-finalize on incomplete expiry** (simplest, matches integrity soft-fail) and document it in the PR/commit body.

**Verify**: Write the policy as a short comment above `syncSectionExpiry` (2–4 lines max).

### Step 2: Refactor `syncSectionExpiry` to return what it did

Change signature to return a small result the callers can use, e.g.:

```php
/**
 * @return array{advanced: bool, finalized: bool, assessment: ?\App\Models\Assessment}
 */
public function syncSectionExpiry(ExamSession $session, Campaign $campaign): array
```

Behavior:

- No-op cases return `advanced: false, finalized: false, assessment: null`.
- Complete+expired → `completeSectionAndAdvance`; return `advanced: true`.
- Incomplete+expired → call `$this->finalizeSession(..., submissionReason: 'section_timer_expired', status: AutoSubmitted, allowIncompleteAnswers: true)`; return `finalized: true` + assessment.
- Do **not** rethrow the “Answer every question before the timer ends” soft-lock message.

Update all callers (`startSession` existing branch, `saveCurrentSectionAnswers`, `advanceSection`, `sessionPayloadForInertia`, and any others from `rg syncSectionExpiry`).

For `advanceSection` specifically:

```php
$result = $this->syncSectionExpiry($session, $campaign);
if ($result['finalized']) {
    return ['session' => $session->fresh(), 'completed' => true /* or shape controllers expect */];
}
if ($result['advanced']) {
    // Do NOT call completeSectionAndAdvance again for the section sync already completed.
    return ['session' => $session->fresh(), 'completed' => $session->fresh()->current_section_id === null /* adjust to real completed meaning */];
}
// existing advance path...
```

Match the existing return shape `array{session: ExamSession, completed: bool}` expected by `ExamSessionController` — read that controller before changing.

For `sessionPayloadForInertia`: if sync finalizes, return payload reflecting finalized/auto-submitted state (or let CandidateExamPage detect submitted assessment). Ensure GET exam no longer 500s/422s.

For `saveCurrentSectionAnswers`: if sync finalizes, return fresh session (controller should handle terminal state — read controller).

The auto-finalize call re-enters `ExamSessionFinalizer::finalizeLocked()`. Update its private expiry synchronization explicitly so `allowIncompleteAnswers: true` does not run the duplicate incomplete-expiry validation before that flag is honored. Preserve the current complete-expiry synchronization for normal manual submissions. Do not solve this by catching and discarding an arbitrary `ValidationException`.

Add a finalizer regression test that calls the public finalizer with an expired, incomplete session and `allowIncompleteAnswers: true`; it must reach the incomplete-answer/resume policy rather than throw the section-expiry validation message. If timer auto-submit is permitted without a résumé, scope that exception narrowly to documented auto-submit reasons and assert `resume_uploaded: false`; normal manual submission must still require a résumé.

**Verify**: `rg -n "Answer every question before the timer ends" app/` → no matches (message removed or unused).

### Step 3: Characterization + regression tests

Extend `tests/Feature/SecureExamSessionTest.php` (model after existing timer/integrity tests):

1. **Incomplete expiry auto-finalizes**: expire section with missing answers; GET or POST that triggers sync; assert session is auto-submitted / assessment created; assert submission reason / event copy path if easy.
2. **Exam page loads after incomplete expiry**: actingAs candidate `get` campaign exam after expiry incomplete → 200 Inertia, not validation exception.
3. **Complete expiry still advances** (multi-section): two sections; expire section 1 with all answers; sync/advance once; assert now on section 2 (not skipped to end); assert section 1 in `completed_section_ids`.
4. Update `candidate cannot advance after the section timer expires` if its expectations no longer match the new composition — do not delete coverage; rewrite to the new contract.
5. **Finalizer re-entry regression**: expired incomplete session finalized with `allowIncompleteAnswers: true` does not throw the old section-expiry validation and does not recursively synchronize expiry.

Also run `tests/Feature/ExamSessionFinalizerTest.php` to ensure finalize still idempotent.

**Verify**:
`php artisan test --compact tests/Feature/SecureExamSessionTest.php tests/Feature/ExamSessionFinalizerTest.php tests/Feature/CandidateExamPageTest.php` → all pass.

### Step 4: Pint + scope check

`vendor/bin/pint --dirty --format agent`
`git status` — only in-scope files.

## Test plan

- Incomplete expiry → auto-finalize (new).
- Page load after incomplete expiry → 200 (new).
- Multi-section complete expiry → advance exactly one section (new).
- Existing finalize / integrity tests still pass.
- Pattern: `tests/Feature/SecureExamSessionTest.php`.

## Done criteria

- [ ] Incomplete section expiry never soft-locks via thrown validation from `syncSectionExpiry`
- [ ] Auto-finalize uses `section_timer_expired` reason (or equivalent wired into finalizer events)
- [ ] `advanceSection` does not double-advance after sync already advanced
- [ ] `ExamSessionFinalizer` does not re-run incomplete-expiry validation on the `allowIncompleteAnswers` auto-submit path
- [ ] New/updated Pest tests pass as above
- [ ] Pint clean; no out-of-scope files
- [ ] `plans/README.md` → DONE

## STOP conditions

- Finalizer API no longer supports `allowIncompleteAnswers` / AutoSubmitted — stop and report.
- Controllers expect a different return shape than documented and fixing them requires large UI redesign — stop and report with the controller excerpt.
- You conclude product wants “move to next section with blanks” instead of auto-finalize — stop; do not implement skip-ahead without operator confirmation (contradicts this plan’s required policy).
- Auto-finalize still cannot complete because resume is mandatory and changing that touches unrelated assessment UX beyond timer/integrity paths — stop and report with the finalizer excerpt.

## Maintenance notes

- Reviewers: focus on multi-section fixtures and that GET exam never throws on expiry.
- Plan 006 will add locking around violation/draft paths — keep `syncSectionExpiry` free of nested lock ambiguity (finalize already locks).
- Client `useSectionExpiryReload` should keep working once server finalizes; if UX should show a dedicated “time expired” flash, that can be a follow-up.
