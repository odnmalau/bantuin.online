# Plan 019: Minimize and bound résumé data sent to AI

> **Executor instructions**: Execute after Plans 002 and 007. This plan completes the candidate-to-AI boundary by bounding extracted résumé text and removing direct identifiers. Run every verification and update the index row.
>
> **Drift check (run first)**: `git diff --stat 83f7e7e..HEAD -- app/Services/ResumeTextExtractor.php app/Jobs/ScreenResumeWithAi.php app/Services/Ai/QwenAssessmentEvaluator.php app/Services/Ai/QwenResumeScreener.php app/Services/Ai/QwenAssessmentCritic.php config/assessment.php .env.example tests/Feature`

## Status

- **Priority**: P1
- **Effort**: M
- **Risk**: MED
- **Depends on**: `plans/002-cap-exam-answer-payload.md`, `plans/007-isolate-untrusted-ai-content.md`
- **Category**: security
- **Planned at**: commit `83f7e7e`, 2026-07-12

## Why this matters

The upload byte limit does not bound extracted text, so a compact PDF can create oversized database text and provider prompts. Evaluator, screener, and critic payloads also send candidate name, email, and original filename even though scoring needs role evidence, not direct identity. Plans 002 and 007 cap answers and isolate untrusted content; this plan closes the remaining résumé-size and PII-minimization gaps.

## Current state

- `ResumeTextExtractor::extract()` returns the complete normalized `pdftotext` output.
- `ScreenResumeWithAi` persists that output and logs its character count before screening.
- `QwenAssessmentEvaluator::promptPayload()` includes candidate name/email.
- `QwenResumeScreener::promptPayload()` includes name/email/original filename/full text.
- `QwenAssessmentCritic::promptPayload()` repeats name/email and resume screening payload.
- `config/assessment.php` limits PDF bytes but not extracted characters.

Current identifier shape:

```php
'candidate' => [
    'name' => $assessment->user?->name,
    'email' => $assessment->user?->email,
],
```

Do not remove job-related résumé evidence or Campaign/rubric context. The objective is data minimization, not disabling screening.

## Commands you will need

| Purpose | Command | Expected on success |
|---|---|---|
| Resume tests | `php artisan test --compact tests/Feature/ResumeScreeningTest.php` | all pass |
| AI tests | `php artisan test --compact tests/Feature/AiAssessmentEvaluationTest.php tests/Feature/AssessmentCriticTest.php` | all pass |
| Style | `vendor/bin/pint --dirty --format agent` | exit 0 |
| Full checks | `composer ci:check` | exit 0 |

## Suggested executor toolkit

- Invoke `ai-sdk-development`, `laravel-best-practices`, and `pest-testing`.
- Search version-specific docs for configuration and immutable DTO conventions.

## Scope

**In scope**:
- `config/assessment.php`
- `.env.example`
- `app/Services/ResumeTextExtractor.php`
- one small immutable extraction-result DTO under `app/Services/` or `app/Data/`
- `app/Jobs/ScreenResumeWithAi.php`
- `app/Services/Ai/QwenAssessmentEvaluator.php`
- `app/Services/Ai/QwenResumeScreener.php`
- `app/Services/Ai/QwenAssessmentCritic.php`
- directly affected Resume Screening, AI Evaluation, and Critic tests
- `plans/README.md`

**Out of scope**:
- Answer limits and prompt nesting already covered by Plans 002/007.
- Automated redaction of every protected attribute inside résumé prose.
- Provider/model changes.
- File retention/deletion policy.
- New moderation vendors.

## Git workflow

- Suggested branch: `advisor/019-minimize-resume-ai-input`
- Suggested commit: `Minimize and bound resume AI input.`
- Do not push or open a PR unless instructed.

## Steps

### Step 1: Configure an extracted-text budget

Add `assessment.resume.max_extracted_characters`, sourced from an environment key documented in `.env.example`. Recommended default: 30,000 characters.

Choose the limit without requiring production access:

1. Measure only committed synthetic/test résumé fixtures and record their maximum normalized character count in the implementation notes.
2. Use 30,000 characters when all committed fixtures fit. This is the required fallback when no approved aggregate production metrics are available.
3. If approved aggregate production length metrics are already available to the operator, they may justify a higher limit; never fetch, copy, or inspect real Candidate résumé content for this plan.
4. If a committed legitimate fixture exceeds 30,000 characters, stop and report its path and normalized character count before changing the default.

**Verify**: `php artisan config:show assessment.resume.max_extracted_characters` → prints the configured integer.

### Step 2: Return bounded extraction metadata

Change `ResumeTextExtractor` to return a small immutable result containing:

- retained normalized text;
- original normalized character count;
- retained character count;
- `wasTruncated`.

Use multibyte-safe length/substr functions. Never persist or log discarded content.

Update all extractor callers. If there is more than one caller, preserve compatibility explicitly rather than adding an implicit string cast.

**Verify**: focused extractor tests cover below-limit, exact-limit, over-limit, and multibyte content.

### Step 3: Persist truncation safely and require manual review

Update `ScreenResumeWithAi`:

- persist only retained text;
- event payload contains counts and `was_truncated`, never content;
- merge a stable `input_truncated` marker into safe resume payload;
- force `needs_manual_review = true` when truncated, regardless of model confidence;
- preserve current failure behavior and job chain.

Do not reject the private PDF solely because extraction exceeds the AI budget.

**Verify**: Resume Screening tests prove discarded text is absent from Assessment fields/events and truncation requires manual review.

### Step 4: Remove direct identifiers from all scoring prompts

Remove candidate name, candidate email, and original filename from evaluator, screener, and critic payloads. If correlation is needed, use a neutral internal Assessment reference.

Keep:

- role title, seniority, job description, required skills;
- question, rubric, answer evidence under Plan 007's untrusted container;
- bounded résumé text;
- score components and safe screening result needed by critic.

Add explicit tests using unique sentinel values for name/email/filename and assert none occur in encoded prompt payloads.

**Verify**: all three prompt test suites pass.

### Step 5: Preserve manual-review and structured-output behavior

Run existing high-score, low-score, critic-block, repair, and resume-risk tests. A truncated résumé must never reach automatic approval without `needs_manual_review`.

**Verify**: targeted AI tests pass with fakes and no live network.

### Step 6: Run all gates

**Verify**:
- `vendor/bin/pint --dirty --format agent` → exit 0.
- Resume and AI test commands → exit 0.
- `composer ci:check` → exit 0.

## Test plan

- extraction below/exact/above limits;
- multibyte-safe truncation;
- discarded text absent from storage/events;
- truncation forces manual review;
- name/email/original filename absent from evaluator, screener, and critic prompts;
- normal scoring behavior remains green.

## Done criteria

- [x] Extracted résumé text has a configurable character budget.
- [x] Only bounded text is stored and sent.
- [x] Truncation metadata is safe and forces manual review.
- [x] Direct identifiers are absent from scoring prompts.
- [x] Existing structured-output and critic behavior remains.
- [x] Targeted and full checks pass.
- [x] Only in-scope files changed.

## STOP conditions

Stop and report if:

- Plans 002 or 007 are incomplete or changed payload structure incompatibly.
- A committed legitimate synthetic/test résumé fixture exceeds the proposed budget.
- The only available way to choose a limit would require reading real Candidate résumé content.
- Removing an identifier breaks a documented provider contract.
- A test would require a live provider or real candidate data.

## Maintenance notes

- Revisit the character budget when provider context windows or résumé parsing changes.
- Full protected-attribute redaction and consent/retention review are separate product/legal work.
