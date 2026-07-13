# How the AI Assessment Autopilot Application Works

> **Status: Partially superseded (2026-07-13).** Use
> [`CONTEXT.md`](../CONTEXT.md) and
> [ADR 0001](adr/0001-use-contextual-identities-for-team-tenancy.md) for the
> current domain and authorization model. Hiring access now derives from Team
> Membership in the Current Team, Candidate access derives from Campaign
> participation, and Platform Operator authority is separate. Question
> libraries and demo seed data were removed; question authoring is Campaign-local.

**Related docs:** [Domain context](../CONTEXT.md) · [Tenancy ADR](adr/0001-use-contextual-identities-for-team-tenancy.md) · [PRD](PRD_AI_ASSESSMENT_AUTOPILOT.md) · [Task list](TASKLIST_AI_ASSESSMENT_AUTOPILOT.md)

This historical guide describes the assessment-autopilot implementation baseline from 2026-06-22, amended where the later Team model replaced authentication, authorization, seeding, and question authoring. HirePilot supports an end-to-end product flow with a real Qwen evaluator via the Laravel AI SDK. Authentication is Google OAuth only.

## Summary

The app is a technical assessment platform built on Laravel, Inertia React, Google OAuth (Socialite), PostgreSQL, the database queue, and Laravel Mail.

Main goals:

- Team Members manage hiring **Campaigns** (role, JD, skills), sections, and per-Campaign question snapshots within their Current Team.
- Team Members author or generate questions directly on Campaign detail.
- Team Members can generate draft assessments with Qwen, review/edit/approve questions, then publish the Campaign.
- Candidates take an assigned Campaign assessment once (PDF resume upload + answers).
- The system screens resumes and evaluates answers via queued jobs (Qwen + deterministic grading + ranking + critic).
- Results land in the Team-scoped workstation; audit timeline and AI panel are on the assessment detail page.
- Team Members review outcomes, edit the final email, then approve or reject; interview email sends only after approval.

## Contextual identities and access

A single `users` table stores accounts, but users do not have global roles.
Sign-in is **Google OAuth only** (Laravel Socialite). There is no password login
or public registration form.

- Hiring authority comes from an active Team Membership in the Current Team.
- Owner, Administrator, and Collaborator are Team Membership roles.
- Candidate access comes from accepting a Campaign Invitation and is scoped to that Campaign.
- Platform Operator authority is independent and does not grant access to Candidate content or hiring decisions.
- `DatabaseSeeder` does not create users, Campaigns, assessments, or credentials.

## Current Team hiring flow

### 1. Team Member login

Team Members use the same Google OAuth login page. Hiring access comes from an
active Team Membership, and `/dashboard` uses the selected Current Team.

After login, users are sent to:

```text
/dashboard
```

### 2. Exam content

Candidate questions come from the assigned **Campaign**:

1. Add or generate questions directly on Campaign detail.
2. **Publish** the Campaign (`active`) after all AI/`draft` questions are approved.
3. Team Members invite assigned Candidates per Campaign; invite links land them on `/candidate/campaigns/{campaign}/exam` after Google sign-in.
4. `/candidate/exam` redirects when exactly one assigned active Campaign is available; otherwise Candidates see their available Campaigns.

Answer snapshots are stored in `answers_payload` on submit, making the attempt auditable and independent of later Campaign question edits.

### 3. Assessment threshold settings

Each Campaign has a `threshold_score`. If no Campaign threshold is available,
the application uses `ASSESSMENT_PASSING_SCORE` from configuration.

Autopilot decisions are **not** based on raw `ai_score` alone. After hybrid
scoring, the evaluation job compares **`ranking_score`** to the effective
threshold. The critic pass can block autopilot approval even when ranking meets
the threshold.

- If `ranking_score >= threshold` **and** the critic does not block → `pending_approval`
- If below threshold, critic blocks, or other review gates apply → `evaluated` (or `needs_manual_review` when critic/resume signals risk)

`evaluated` assessments can still be reviewed and manually approved to handle false negatives.

The application default is configured in:

```text
config/assessment.php
```

Default:

```text
75
```

`pending_approval` versus `evaluated` uses `campaign.threshold_score` when the
assessment has a Campaign and the configured default otherwise.

### 4. Managing Campaigns

```text
/admin/campaigns
```

Current features:

- List, create, edit campaigns.
- Delete campaigns with no assessments.
- Campaign detail.
- Generate assessment draft with Qwen from role/JD/skills.
- Add/remove campaign sections.
- Add question snapshots to sections.
- Edit, approve (per question or approve all), delete snapshots on campaign detail.
- Publish (blocked while any question remains `draft`).
- Delete campaign question snapshots.

Campaign fields include: `title`, `role_title`, `seniority`, `job_description`, `required_skills`, `threshold_score`, `status`, `ai_generation_notes`, `created_by`, `activated_at`, plus ranking weights and language fields from migrations.

Campaign statuses:

- `draft`
- `question_review` (e.g. after AI generator adds draft questions)
- `active`
- `archived`

Campaign detail shows role context, skills, threshold, status, sections,
questions, types, difficulty, points, tags, rubrics, and question status
(`draft`, `approved`, `archived`).

Qwen-generated questions start as `draft` until approved; publish is blocked while drafts remain. After `active`, candidates can take the exam (one submit per user per campaign).

### 5. Campaign question authoring

Reusable question libraries were removed. Team Members add questions, generate
assessment drafts, regenerate MCQ options, convert text questions to MCQ, and
approve question snapshots directly on Campaign detail.

### 6. Reviewing assessments

Workstation:

```text
/admin/rankings
```

The ranking page lists candidate name/email, scores, status, and evaluation time,
and links each row to `/admin/assessments/{assessment}`. There is no separate
assessment index route. Detail shows answers, question/rubric snapshots, AI
scores and justification, resume screening, ranking breakdown, critic result,
agent timeline, AI audit panel, email drafts, and approval/email timestamps.

Timeline examples: submit, resume pipeline, queue, evaluation, deterministic grading, ranking, critic, email draft, admin actions, email sent/failed.

Event payloads are sanitized server-side so secrets are not stored or shown in the UI.

### 7. Approve assessment

Reviewable statuses (`AssessmentStatus::isReviewable()`):

- `pending_approval`
- `evaluated`
- `needs_manual_review`
- `overridden`

On approve: the Team Member edits the final email subject/body; the system stores `approved_*`, sets `approved`, and dispatches `SendInterviewInvitationEmail`.

### 8. Reject assessment

Same reviewable statuses as approve. Reject requires a reason, sets `rejected`, records the reason in `assessment_events`, no email.

### 9. Recovery and override

The **Recovery and Override** panel on assessment detail handles AI failures and false negatives without manual DB edits.

- **Retry Evaluation:** `evaluation_failed` only; resets evaluation fields, status `submitted`, records the retry event, and dispatches `EvaluateAssessmentWithAi`.
- **Retry Email:** `email_failed` only; sets status back to `approved`, records the retry event, and dispatches `SendInterviewInvitationEmail` again using the stored approved subject/body.
- **Promote:** `evaluated` or `needs_manual_review`; reason required; manual email if no AI draft.
- **Override Score:** reviewable assessments; new ranking 0–100 + reason; status `overridden`; event `admin_overrode_ranking_score`.
- **Reject:** reason required (auditable).

## Candidate flow

### 1. Login

Select **Continue with Google** on the login page. A Campaign Invitation is
redeemed for the matching account and sends the Candidate to that Campaign's
exam. Otherwise the user lands on `/dashboard`.

### 2. Taking the assessment

The campaign exam at `/candidate/campaigns/{campaign}/exam` runs in **secure exam mode**:

1. Candidate clicks **Start secure exam** → `POST /candidate/campaigns/{campaign}/exam-sessions` creates or resumes `exam_sessions`.
2. Only the **current section** and its approved questions are shown. Section metadata includes `duration_minutes`; the server sets `current_section_expires_at` when a timed section starts.
3. Candidate answers are saved with `PATCH .../exam-sessions/{session}`; moving forward uses `POST .../advance` (server validates answers + timer).
4. Integrity events post to `POST .../violations` (tab blur, fullscreen exit, copy/paste, etc.). Warnings increment `warning_count`; at the configured maximum, the session may auto-finalize into an assessment.
5. After all sections are complete, the candidate uploads a resume PDF and **Submit assessment** via `POST .../finalize`, which creates the `assessments` row and queues AI jobs.

Required before finalize: PDF resume and all approved questions answered (canonical text stored in `answers_payload` at finalize time).

On submit: one assessment per user per campaign; job chain:

```text
ScreenResumeWithAi → EvaluateAssessmentWithAi
```

### 3. Retake policy

One submission per candidate per campaign. If already submitted, the exam form is hidden and the candidate is directed to status.

### 4. Assessment status page

Own assessments only. Shows status, scores, timestamps, justification, submitted answers.

Inertia polling every 2s while status is in processing (`submitted`, `resume_processing`, `resume_screening`, `evaluating`); stops on terminal statuses. Reloads only the `assessment` prop. No WebSockets.

## AI evaluation flow

### Resume screening

Job `ScreenResumeWithAi`: extract PDF text → Qwen screening → persist resume fields. Temporary statuses `resume_processing` / `resume_screening`. Failures may set manual review flags; essay evaluation still runs in the chain.

### Answers, ranking, critic

Job `EvaluateAssessmentWithAi`:

1. Submit → `submitted`.
2. After resume job, evaluation runs → `evaluating`.
3. `QwenAssessmentEvaluator` (essay score, justification, email draft).
4. `DeterministicAssessmentGrader` (objective types).
5. `CandidateRankingCalculator` (`ranking_score`, `ranking_payload`).
6. `QwenAssessmentCritic` → `critic_payload`, with repaired email content when the critic outcome is `repaired`.
7. Effective threshold + **ranking score** + critic → final status.

Evaluator DTO `AssessmentEvaluationResult`: score 0–100, justification, email when above threshold. Persistent failure → `evaluation_failed` (an authorized Team Member can retry).

## Assessment statuses

| Status | Meaning |
| --- | --- |
| `submitted` | Just submitted. |
| `evaluating` | Evaluation job running. |
| `resume_processing` | PDF extraction. |
| `resume_screening` | Qwen resume screening. |
| `pending_approval` | Meets threshold gate; awaiting Team Member review. |
| `evaluated` | Below threshold or blocked; still reviewable. |
| `needs_manual_review` | Manual review flagged. |
| `overridden` | A Team Member overrode the ranking score. |
| `approved` | Approved; email job dispatched. |
| `rejected` | Rejected by admin. |
| `email_sent` | Invitation sent. |
| `email_failed` | Send failed or not sendable. |
| `evaluation_failed` | AI evaluation failed. |

## Qwen integration

Real Qwen via Laravel AI SDK: custom `qwen` provider, OpenAI-compatible `chat/completions` gateway, structured JSON object mode, tests use fakes.

Key env: `QWEN_API_KEY`, `DASHSCOPE_API_KEY` (fallback), `QWEN_BASE_URL`, `QWEN_MODEL`, `QWEN_TIMEOUT`, `ASSESSMENT_RESUME_MAX_KB`.

Backend validates schema after JSON object mode; `enable_thinking=false` for structured calls; instructions must include the word `JSON`.

Key files:

```text
app/Ai/Agents/AssessmentEvaluatorAgent.php
app/Ai/Agents/AssessmentGeneratorAgent.php
app/Ai/Agents/ResumeScreeningAgent.php
app/Ai/Agents/AssessmentCriticAgent.php
app/Ai/Gateway/QwenGateway.php
app/Ai/Providers/QwenProvider.php
app/Services/Ai/QwenAssessmentEvaluator.php
app/Services/Ai/QwenAssessmentGenerator.php
app/Services/Ai/QwenResumeScreener.php
app/Services/ResumeTextExtractor.php
config/ai.php
config/assessment.php
```

## Important application files

Backend models and services include `Assessment`, `Campaign`, `CampaignSection`,
`CampaignQuestion`, and `AssessmentEvent`, plus the assessment pipeline services
and jobs under `app/Jobs/`. Hiring controllers retain the `Admin` namespace for
the `/admin` URL namespace, while authorization is based on Current Team context.

Frontend pages include Team-scoped Campaign, assessment, and ranking pages under
`resources/js/pages/admin`, plus Candidate exam and assessment pages.

Representative tests: `AssessmentAutopilotFlowTest`, `CandidateAssessmentTest`, `AiAssessmentEvaluationTest`, `AdminAssessmentWorkstationTest`, `AssessmentEventTimelineTest`.

## Email flow

`SendInterviewInvitationEmail` runs only for `approved` assessments with approved subject/body and a candidate email. Success → `email_sent`; failure → `email_failed`.

## Queue

Driver: `database`. Jobs: `ScreenResumeWithAi`, `EvaluateAssessmentWithAi`, `SendInterviewInvitationEmail`.

Local dev: `composer run dev` includes `queue:listen`, or run `php artisan queue:work`.

## Main database tables

| Table | Purpose |
| --- | --- |
| `users` | Accounts, Google identity, and Current Team selection. |
| `teams` / `team_memberships` | Team ownership, membership roles, and lifecycle context. |
| `assessments` | Submissions, scores, status, approval, email. |
| `campaigns` | Team-owned hiring context, threshold, weights, status. |
| `campaign_invitations` | Candidate participation in a Campaign. |
| `campaign_sections` / `campaign_questions` | Exam structure and snapshots. |
| `assessment_events` | Timeline (`type`, `title`, `description`, `payload`, `occurred_at`, `actor_id`). |
| `jobs` | Laravel queue. |

Extra `assessments` columns include resume fields, hybrid scores, `ranking_payload`, `critic_payload`, `needs_manual_review`.
The default passing score comes from `config/assessment.php`; a Campaign's
`threshold_score` overrides it for Campaign assessments.

## Verification

```bash
php artisan test --compact
pnpm run types:check
pnpm run lint:check
pnpm run build
```

## Implementation status

**Current:** Team tenancy and contextual identities, Campaign-local authoring, AI generation, secure Candidate exam, resume screening, hybrid scoring, critic, Team-scoped workstation, timeline/audit, recovery actions, email job, and feature tests. Delivery details should be verified from git history and the current test suite.
