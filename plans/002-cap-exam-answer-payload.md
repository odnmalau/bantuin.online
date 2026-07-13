# Plan 002: Cap candidate exam answer payload size

> **Executor instructions**: Follow this plan step by step. Run every verification command and confirm the expected result before moving to the next step. If anything in the "STOP conditions" section occurs, stop and report — do not improvise. When done, update the status row for this plan in `plans/README.md` — unless a reviewer dispatched you and told you they maintain the index.
>
> **Drift check (run first)**: `git diff --stat 83f7e7e..HEAD -- app/Http/Requests/Candidate/SaveExamSectionRequest.php config/assessment.php tests/Feature/SecureExamSessionTest.php`
> If any in-scope file changed since this plan was written, compare the "Current state" excerpts against the live code before proceeding; on a mismatch, treat it as a STOP condition.

## Status

- **Priority**: P1
- **Effort**: S
- **Risk**: LOW
- **Depends on**: none
- **Category**: security
- **Planned at**: commit `83f7e7e`, 2026-07-12

## Why this matters

Exam section saves accept `answers.*` as unbounded nullable strings. Oversized payloads bloat `exam_sessions.answer_drafts` JSON, inflate AI evaluation prompts/cost, and can DoS the evaluation pipeline. A request-level `max:` aligned with a config value closes the hole cheaply.

## Current state

- `app/Http/Requests/Candidate/SaveExamSectionRequest.php`:

```php
return [
    'answers' => ['required', 'array'],
    'answers.*' => ['nullable', 'string'],
];
```

- `config/assessment.php` already has size limits for resumes (`resume.max_kilobytes`) — add a sibling key for answer max characters rather than hardcoding only in the FormRequest.
- Answers later flow into AI prompts via `QwenAssessmentEvaluator` (`answer` field in payload). Do **not** change the evaluator in this plan.

## Commands you will need

| Purpose | Command | Expected on success |
|---------|---------|---------------------|
| Tests | `php artisan test --compact tests/Feature/SecureExamSessionTest.php` | all pass |
| Pint | `vendor/bin/pint --dirty --format agent` | exit 0 |

## Suggested executor toolkit

- `pest-testing`, `laravel-best-practices` skills if available.

## Scope

**In scope**:
- `app/Http/Requests/Candidate/SaveExamSectionRequest.php`
- `config/assessment.php` (add `secure_exam.max_answer_characters` or similar)
- `.env.example` (document the new env key if you wire one; no secret values)
- `tests/Feature/SecureExamSessionTest.php` (validation rejection test)
- `plans/README.md` (status)

**Out of scope**:
- Changing AI prompts or evaluation pipeline
- Frontend character counters (nice-to-have; not required)
- Resume upload size limits (already configured)

## Git workflow

- Branch: `advisor/002-cap-exam-answer-payload`
- Commit style: `Cap candidate exam answer payload size.`
- Do NOT push/PR unless asked.

## Steps

### Step 1: Add config key

In `config/assessment.php` under `secure_exam`, add something like:

```php
'max_answer_characters' => (int) env('ASSESSMENT_EXAM_MAX_ANSWER_CHARACTERS', 20000),
```

Add the same key (commented or with default) to `.env.example` next to other `ASSESSMENT_EXAM_*` vars if they are listed there; if not listed, skip `.env.example` rather than inventing a large new block.

**Verify**: `php artisan config:show assessment.secure_exam.max_answer_characters` → prints `20000` (or your chosen default).

### Step 2: Wire FormRequest validation

Update `SaveExamSectionRequest::rules()`:

```php
'answers.*' => ['nullable', 'string', 'max:'.config('assessment.secure_exam.max_answer_characters')],
```

Use a local variable if needed for readability. Keep `authorize()` returning `true` (route auth is elsewhere).

**Verify**: `rg -n "max_answer_characters" app/Http/Requests/Candidate/SaveExamSectionRequest.php config/assessment.php` → both hit.

### Step 3: Regression test

In `tests/Feature/SecureExamSessionTest.php`, add a test that:

1. Starts a session with an approved question (reuse helpers from existing tests: `assignCandidateToCampaignExam`, `startCandidateExamSession`).
2. POSTs to the save-answers route with one answer string longer than the configured max (set config in the test to a small number like `100` for speed).
3. Asserts validation error on `answers` / `answers.*`.

Pattern: mirror `candidate cannot advance a section without answering every question` setup.

**Verify**: `php artisan test --compact tests/Feature/SecureExamSessionTest.php` → all pass.

### Step 4: Pint

`vendor/bin/pint --dirty --format agent`

**Verify**: exit 0.

## Test plan

- Oversized answer rejected (config overridden to small max).
- Existing save/advance/finalize tests still pass (happy path under limit).
- Model after existing SecureExamSession feature tests.

## Done criteria

- [ ] Config key exists with a sane default (≥ UI needs; 20_000 is fine unless the UI documents another limit)
- [ ] FormRequest enforces `max:`
- [ ] New rejection test passes
- [ ] `php artisan test --compact tests/Feature/SecureExamSessionTest.php` exits 0
- [ ] No out-of-scope files modified
- [ ] `plans/README.md` status → DONE

## STOP conditions

- Save endpoint no longer uses `SaveExamSectionRequest` — stop and report.
- Default max would break legitimate long-text questions that already exist in factories/tests — raise default rather than inventing a separate per-type limit unless tests force it; if per-type limits are required, stop and report (out of scope).

## Maintenance notes

- Reviewers: ensure the default is high enough for long_text questions but low enough to block multi-MB dumps.
- If a future UI adds a visible character limit, keep it ≤ this config value.
