# Product Requirements Document: AI Hiring Assessment Autopilot

> **Status: Partially superseded (2026-07-13).** For current domain vocabulary
> and authorization, use [`CONTEXT.md`](../CONTEXT.md) and
> [ADR 0001](adr/0001-use-contextual-identities-for-team-tenancy.md). Users no
> longer have global Admin or Candidate roles. Hiring access comes from Team
> Membership in the Current Team, Candidate access comes from Campaign
> participation, and Platform Operator authority is independent. Reusable
> question libraries were removed; questions are authored within Campaigns.

## Related docs

- [Current product and domain context](../CONTEXT.md)
- [ADR 0001: contextual identities for Team tenancy](adr/0001-use-contextual-identities-for-team-tenancy.md)
- [How it works](HOW_IT_WORKS_AI_ASSESSMENT_AUTOPILOT.md)
- [Implementation task list](TASKLIST_AI_ASSESSMENT_AUTOPILOT.md)

## 1. Product Summary

AI Hiring Assessment Autopilot is an end-to-end technical hiring platform. Team Members define role, seniority, target skills, and job description. Qwen Cloud–based agents help build assessment packages, screen PDF resumes, evaluate candidate answers, rank candidates, and prepare interview email drafts.

The product is not positioned as a general quiz/CAT tool. Its core value is turning ambiguous hiring inputs—such as PDF resumes and free-form technical answers—into an auditable decision package: scores, justifications, skill match, risk flags, interview recommendations, ranking, and email drafts.

Final decisions remain with authorized Team Members through human-in-the-loop. AI only provides recommendations and email drafts. Email is sent only after a Team Member clicks Approve.

Human-in-the-loop occurs at two critical points:

- An authorized Team Member reviews and approves AI-generated assessments before they are published.
- An authorized Team Member reviews ranking, justifications, and email drafts before interview emails are sent.

## 2. Current Codebase Context

The current codebase is a Laravel 13 application with an Inertia React 3 frontend.

Primary stack:

- Backend: Laravel 13, PHP 8.5
- Frontend: Inertia React 3, React 19, Tailwind CSS 4
- Auth: Google OAuth (Socialite) + session web guard
- Frontend routing: Laravel Wayfinder
- Testing: Pest 4
- Local and production target database: PostgreSQL
- Queue: Laravel database queue
- Mail execution: Laravel Mail with Resend

Technical implications:

- UI authentication uses session-based auth (Laravel login/logout + Socialite), not JWT.
- Internal APIs called from React use session + CSRF.
- Sanctum/JWT is only needed if a mobile app, public API, or external client is added later.
- Main application pages are built as Inertia pages in `resources/js/pages`.
- Frontend forms use Inertia `<Form>` and Wayfinder-generated routes/actions.

Assessment-pipeline implementation snapshot as of 2026-06-22. The banner above
describes the later Team-tenancy and campaign-authoring changes.

- Core assessment autopilot modules are operational: Google OAuth, Campaign questions, Candidate exam, queue-based Qwen evaluation, Team Member approval, and invitation email.
- Candidate exam runtime uses **secure exam sessions** (`exam_sessions`): one section at a time, server-enforced section timers, integrity warnings, fullscreen/back-navigation guardrails on the client, and final submission that creates the audited `assessments` record.
- Campaign foundation is in place: `campaigns` table/model, Team-scoped Campaign UI, routes, form requests, and tests.
- Question type and section support is implemented through `campaign_sections`, `campaign_questions`, the `QuestionType` enum, and Campaign question snapshots.
- Authorized Team Members can create and edit Campaigns, view Campaign detail, add sections and questions, and run the AI assessment generator from Campaign detail.
- AI assessment generator is available to create draft `campaign_sections` and `campaign_questions` from role/JD/skills via Qwen structured output. Generator output enters `draft` status.
- Resume PDF screening is available for Candidate exams tied to an active Campaign: PDF upload required, private storage, best-effort text extraction, Qwen structured screening, and Team Member resume summary.
- Hybrid scoring runtime is available on assessment evaluation: candidate answers store typed campaign question snapshots, deterministic grader computes objective snapshots when available, Qwen evaluator fills `essay_score`, and the backend computes `ranking_score` and `ranking_payload` with a transparent formula.
- The Team-scoped ranking dashboard, critic pass, audit timeline UI, and retry/override workflows are available.
- Per-question approval for AI-generated drafts, Campaign publish workflow, and Candidate exam tied to an active Campaign are available. MCQ regeneration and text-to-MCQ conversion are available from Campaign detail.

## 3. Product Objectives

Build a hiring workflow autopilot platform that enables:

- Team Members to create Campaigns from role, seniority, required skills, and job description.
- Qwen Cloud to assist in creating assessment sections, question types, rubrics, answer keys, and scoring weights.
- Authorized Team Members to review, edit, and approve AI-generated questions before the assessment goes live.
- Candidates to sign in with Google, upload a PDF resume, and complete the assessment.
- The system to perform resume PDF screening, deterministic grading, AI essay grading, self-correction, and candidate ranking.
- The system to store scores, AI justifications, risk flags, interview probes, ranking, and interview email drafts.
- Authorized Team Members to review AI results and Approve, Reject, Override, or Retry.
- Team Member approval to trigger interview invitation email delivery via Resend.

## 4. Success Criteria

The product meets success criteria when:

- Authorization derives hiring access from Team Membership and Candidate access from Campaign participation.
- Candidates can only access their own exam and result pages.
- Authorized Team Members can access Campaign question management and the assessment results workstation for their Current Team.
- Authorized Team Members can create, update, and delete Campaign questions and rubrics.
- Authorized Team Members can create Campaign setup with role title, seniority, skill targets, and job description.
- Qwen Cloud can produce draft assessments with sections, question types, rubrics, answer keys, difficulty, points, and skill tags.
- Authorized Team Members can approve/edit AI-generated questions before Candidates use them.
- Candidates must upload a PDF resume before or during assessment submission.
- The system can extract text from PDF resumes and run AI resume screening.
- Candidates can submit answers for all approved questions in the active campaign.
- Submission creates an assessment and runs AI evaluation via a queue job.
- Qwen Cloud returns valid structured output with score, justification, and email draft.
- The system supports hybrid grading:
  - deterministic grading for multiple choice, yes/no, fill blank, and matching pairs.
  - AI-assisted grading for short text, long text, and resume screening.
- The system computes candidate ranking from a transparent formula combining resume score, MCQ score, and essay score.
- The system runs self-correction/critic pass to check score consistency, justification, status routing, and email draft.
- Assessments with **ranking score** at or above the effective threshold (Campaign or configured default) enter `pending_approval` if the critic does not block; below threshold or blocked by critic enter `evaluated` and remain reviewable by authorized Team Members.
- Authorized Team Members can view answer detail, scores, AI justification, and email draft.
- Authorized Team Members can view the AI audit panel and agent activity timeline.
- Authorized Team Members can override a Candidate from `evaluated` to interview candidate if AI is considered a false negative.
- Authorized Team Members can retry evaluation for `evaluation_failed` status.
- Approve sends interview invitation email via Resend.
- Reject marks the assessment rejected without sending email.
- Main flows are covered by Pest feature tests.

## 5. Contextual Identities and Authorization

### 5.1 Identities

A single `users` table is used for all accounts, but authority is contextual:

- A Team Member has an active Team Membership as Owner, Administrator, or Collaborator.
- A Candidate participates in a Campaign after accepting its Campaign Invitation.
- A Platform Operator has independent support authority and no implicit access to Candidate content or hiring decisions.

One account may be a Team Member in one Team and a Candidate in another. Team
Membership history and Candidate history are mutually exclusive within the same
Team. Public password registration is not available; Google OAuth creates or
updates the account without assigning a global role.

### 5.2 Middleware and policies

Route protection in the current implementation:

- Current Team hiring routes: `auth`, `current-team`, plus policies scoped to the Current Team.
- Candidate routes: `auth`, with Campaign participation and ownership checks in policies and services.
- Platform Operator support routes: `auth`, `platform-operator`.
- Shared account and Team selection routes: `auth`.

## 6. Functional Modules

### 6.1 Auth

Sign-in is **Google OAuth** only (Laravel Socialite). No form registration, password login, or password reset.

Features:

- First Google sign-in creates a local user without assigning a global role.
- Team Invitations establish Team Membership after acceptance by the invited account.
- Campaign Invitations establish Candidate participation after acceptance by the invited account.
- Logout via session web guard.

Technical changes:

- `google_id` and `current_team_id` columns on `users`; the legacy `role` column was removed.
- `current-team` and `platform-operator` middleware aliases.
- `PostLoginRedirect` completes pending Team, ownership-transfer, or Campaign invitation redemption before falling back to `/dashboard`.

### 6.2 Current Team: Campaign Questions

Questions are authored or generated directly within Campaign sections. Candidate
exam questions are snapshots in **Campaign questions**; there is no current
reusable question-library model or global `questions` table.

Campaign question snapshot fields (implementation):

- `prompt`: question text
- `expected_rubric`: AI grading rubric
- `type`, `options`, `correct_answer`, `points`, `difficulty`, `skill_tags`, `grading_mode`
- `status`: `draft`, `approved`, `archived`
- `sort_order` on campaign questions

### 6.3 Candidate: Exam

Candidates start a **secure exam session** for the assigned active campaign, then complete **one section at a time** before uploading a resume PDF and finalizing the attempt.

Features:

- Start or resume an `exam_sessions` record per `(user, campaign)` while the attempt is in progress.
- Display only the **current** approved section and its questions (ordered by `sort_order`).
- Enforce per-section time limits using `campaign_sections.duration_minutes` and server-side `current_section_expires_at`.
- Save section answers incrementally into `exam_sessions.answer_drafts`.
- Advance sections only when every question in the current section is answered and the timer has not expired (server-validated).
- Client guardrails: dedicated exam layout (no app sidebar), back-navigation warnings, optional fullscreen requirement, blocked copy/paste/context menu, and integrity violation reporting.
- Track `warning_count` and optional auto-submit when `config('assessment.secure_exam.max_integrity_warnings')` is reached.
- Finalize session: require resume PDF, build canonical `answers_payload` snapshot, create `assessments`, dispatch `ScreenResumeWithAi` → `EvaluateAssessmentWithAi`, record timeline events (including `exam_integrity_summary` when warnings occurred).

Product rules:

- One candidate may submit only one assessment **per campaign**.
- After an assessment is created for a campaign, the candidate cannot resubmit for the same campaign, including when status is `evaluated`, `pending_approval`, `rejected`, or `email_sent`.
- Retake is not available in the product.

### 6.4 AI Evaluation

Evaluation runs in a Laravel queue job so the submit request does not wait on Qwen.

Flow:

1. Candidate submits answers and resume PDF.
2. Backend creates `assessments` record with status `submitted`.
3. Backend stores question, rubric, and answer snapshot in `answers_payload`.
4. Backend dispatches queue chain: `ScreenResumeWithAi` → `EvaluateAssessmentWithAi`.
5. `ScreenResumeWithAi` extracts PDF text, runs Qwen resume screening, and persists resume fields.
6. `EvaluateAssessmentWithAi` sets status to `evaluating`.
7. Job calls Qwen via service class for essay/text grading.
8. Job validates structured response from Qwen.
9. Job stores score, justification, and email draft.
10. Job computes `ranking_score` (hybrid) and runs critic pass.
11. If `ranking_score >=` effective threshold and critic does not block autopilot, status becomes `pending_approval`.
12. If below threshold or critic blocks, status becomes `evaluated` (or `needs_manual_review` where applicable).
13. If Qwen evaluator fails after repair, status becomes `evaluation_failed`.

Threshold:

- Global default threshold: `75` (`config/assessment.php`), overridden by each Campaign's `threshold_score`.
- Assessments with `campaign_id` use `campaign.threshold_score` as the effective threshold.

### 6.5 Team Member: Human-in-the-Loop Workstation

Authorized Team Members view assessments that are complete or awaiting approval within their Current Team.

Main columns:

- Candidate name.
- Candidate email.
- AI score.
- Status.
- Submit date.
- Evaluation date.

Assessment detail:

- Candidate answer per question.
- Rubric snapshot per question.
- AI score.
- AI justification.
- Email subject draft.
- Email body draft.

Actions:

- Approve: save final subject/body, dispatch email job, update status.
- Reject: update status to `rejected`.

Rules:

- Approve is allowed for reviewable statuses: `pending_approval`, `evaluated`, `needs_manual_review`, `overridden`.
- Reject is allowed for the same reviewable statuses.
- Email must not be sent directly by AI.
- Email is sent only after explicit Team Member approval.
- The Team Member may edit the draft subject/body before Approve.
- Assessments below threshold remain `evaluated`, not automatically `rejected`, so Team Members can review false negatives manually.

### 6.6 Email Invitation

Email is sent using Laravel Mail with Resend.

Flow:

1. An authorized Team Member reviews and may edit the email draft.
2. The Team Member clicks Approve.
3. Backend saves final subject/body, `approved_by`, and `approved_at`.
4. Backend dispatches `SendInterviewInvitationEmail` job.
5. Job sends email to candidate address.
6. On success, assessment becomes `email_sent` and `email_sent_at` is set.
7. On failure, assessment becomes `email_failed`.

The product uses a simple text editor for subject/body. Email is a generic invitation without specific interview date or time.

### 6.7 Campaign / Role Setup

A campaign is the hiring context binding role, skill targets, resume screening, assessment, scoring, and ranking.

Campaign fields:

- `title`: campaign name, e.g. "Backend Engineer - Mid Level".
- `role_title`: open position.
- `seniority`: intern, junior, mid, senior, lead.
- `job_description`: role description.
- `required_skills`: required skills, stored as array/json.
- `language`: assessment language.
- `status`: draft, question_review, active, archived.
- `threshold_score`: interview threshold (campaign column; not `passing_score`).
- `ranking_weights`: weights for resume, MCQ, and essay.

Features:

- Team Members create Campaigns manually.
- Team Members can ask Qwen to generate an assessment draft from role/JD/skills.
- Candidate submission is tied to one campaign.
- Active campaign defines questions, sections, resume requirement, and scoring formula.

### 6.8 Question Types

Question types must support mixed assessments while staying focused on hiring.

Platform priority:

| Type | Use Case | Grading |
| --- | --- | --- |
| `multiple_choice` | quick knowledge check | deterministic |
| `yes_no` | concept verification | deterministic |
| `short_text` | brief reasoning | Qwen + rubric |
| `long_text` | essay/case study/system design | Qwen + rubric |
| `fill_blank` | terminology/context completion | exact/fuzzy deterministic |
| `matching_pairs` | concepts and relationships | deterministic |

Campaign question fields:

- `type`
- `prompt` (question text; not a `content` column)
- `options` json/jsonb for MCQ/matching.
- `correct_answer` for deterministic grading (not `answer_key` in current implementation).
- `expected_rubric` for AI-assisted grading.
- `points`
- `difficulty`
- `skill_tags`
- `grading_mode`: deterministic, ai, manual.
- `ai_generated`
- `status`: draft, approved, archived.

Notes:

- Text/essay questions are not manual-only. For HirePilot, text questions are AI-graded with a rubric and remain reviewable by authorized Team Members.
- Resume PDF is not a normal question. Resume is a required candidate artifact used for screening and ranking.

### 6.9 Removed: Question Library / Skill Bank

The earlier reusable library design was removed. The current product authors,
generates, regenerates, converts, reviews, and approves questions directly on
Campaign detail. Any future Team-scoped question reuse requires a separate
product and tenancy design; it must not revive the removed global library model.

### 6.10 AI Assessment Generator

Qwen is used to create assessment drafts from role/JD/skills.

Generation input:

- Role title.
- Seniority.
- Required skills.
- Job description.
- Target language.
- Number of questions.
- Desired question type mix.
- Difficulty distribution.
- Section target, when generation is run from a section.

Generation output:

- Sections.
- Questions.
- Rubrics for AI-graded questions.
- Answer key for deterministic questions.
- Options and plausible distractors for MCQ.
- Points.
- Difficulty.
- Skill tags.
- Suggested duration.
- Section weight.

Human-in-the-loop:

- All AI-generated questions enter `draft` status.
- An authorized Team Member must review/edit/delete/approve before the Campaign can be published.
- AI-generated answer keys and rubrics are treated as untrusted drafts until a Team Member approves.

Current implementation status:

- Campaign generator is available via `AssessmentGeneratorAgent` and `QwenAssessmentGenerator`.
- The Team-scoped endpoint `POST /admin/campaigns/{campaign}/generate-assessment` creates section and question drafts.
- Backend validates Qwen output: deterministic questions must have `correct_answer`, multiple choice must have options, and text questions must have rubric.
- Per-question approval, publish gate, and campaign question snapshot edit are available on campaign detail page (`PATCH .../questions/{question}`).

### 6.11 Campaign Sections

Campaign sections organize the assessment into clear parts.

Section fields:

- `campaign_id`
- `title`
- `description`
- `duration_minutes` nullable.
- `scoring_mode`: percentage, points, weighted.
- `weight`
- `sort_order`

Recommended section patterns:

- Knowledge Check: MCQ/yes-no/fill blank.
- Technical Reasoning: short/long text.
- System Design Case Study: long text.
- Debugging Scenario: short/long text or matching pairs.

Platform capabilities:

- Show section title, instruction, progress, and section duration when configured.
- Compute score per section.
- Use section weight in ranking.

Deferred:

- Auto advance when timer expires.
- Lock previous section.
- Polished drag reorder UI.

### 6.12 Resume PDF Screening

Candidates must upload a PDF resume.

Flow:

1. Candidate uploads `resume.pdf`.
2. Backend validates MIME, extension, and file size.
3. Backend stores file on private disk.
4. Backend extracts text from PDF.
5. Qwen performs resume screening against campaign role/JD/skills.
6. Backend stores structured result for audit and ranking.

Current implementation status:

- Candidate exam tied to active campaign already requires PDF upload on submit.
- Resume is stored on private `local` disk and not exposed to the frontend.
- `ResumeTextExtractor` uses `spatie/pdf-to-text` (Poppler `pdftotext`) for PDF text extraction.
- `ResumeScreeningAgent` and `QwenResumeScreener` use Qwen structured output JSON Object mode.
- Job `ScreenResumeWithAi` runs before answer evaluation job via queue chain.

Structured output for resume screening:

```json
{
  "resume_score": 82,
  "summary": "Candidate has relevant Laravel and PostgreSQL experience.",
  "matched_skills": ["Laravel", "PostgreSQL", "Queue"],
  "missing_skills": ["Kubernetes"],
  "risk_flags": ["No explicit production scale experience"],
  "interview_probes": [
    "Ask about queue failure handling",
    "Ask about PostgreSQL indexing tradeoffs"
  ],
  "confidence": 0.78,
  "justification": "Strong alignment with backend role requirements."
}
```

Fairness constraints:

- Resume screening must not use protected/sensitive attributes such as gender, age, marital status, religion, ethnicity, photo, or detailed address.
- Prompts must instruct Qwen to assess only skills, relevant experience, project evidence, and assessment-related signals.
- An authorized Team Member remains the final decision maker.

### 6.13 Hybrid Scoring and Ranking

Scoring consists of:

- `resume_score`: Qwen resume screening.
- `mcq_score`: deterministic grading.
- `essay_score`: Qwen rubric-based grading.
- `ranking_score`: transparent formula for ranking.

Initial formula:

```text
ranking_score =
  resume_score * 0.35 +
  essay_score * 0.50 +
  mcq_score * 0.15
```

Current implementation stores weights in `config/assessment.php` and `.env.example`:

- `ASSESSMENT_RANKING_RESUME_WEIGHT=35`
- `ASSESSMENT_RANKING_ESSAY_WEIGHT=50`
- `ASSESSMENT_RANKING_MCQ_WEIGHT=15`

If a component is unavailable, the backend normalizes weights from available components and records `missing_components` in `ranking_payload`. `needs_manual_review` is retained for failure/low-confidence signals from other stages, not automatically enabled solely because an assessment lacks one score component.

Section score calculation is also available for objective answer snapshots that carry `section_id`, `section_title`, and `section_weight` metadata. When section metadata is available, `mcq_score` is computed from weighted section scores and breakdown is stored in `ranking_payload.section_scores`. When unavailable, the system falls back to point-based deterministic grading.

Qwen may provide explanation and ranking rationale, but primary ranking must still be computed by the backend with an auditable formula.

### 6.14 Self-Correction / Critic Agent

After the Qwen evaluator produces initial results, run a critic pass.

The critic checks:

- Whether score is consistent with justification.
- Whether score components are in valid range.
- Whether email draft is created only if the candidate meets threshold.
- Whether email draft does not contain fake schedule/interviewer/link/salary/hiring commitment.
- Whether resume screening avoids protected attributes.
- Whether low confidence requires manual review.
- Whether output JSON is valid but schema/business-rule invalid.

Critic outcomes:

- `passed`: result may be used.
- `repaired`: critic fixes minor output issues.
- `needs_manual_review`: result is stored but requires Team Member review.
- `failed`: assessment enters `evaluation_failed` or may be retried.

Current implementation:

- `AssessmentCriticAgent` uses Qwen structured output for quality gate.
- `QwenAssessmentCritic` checks the assessment package after essay score, deterministic MCQ score, and ranking score are computed.
- Output is stored in `assessments.critic_payload`.
- Outcome `repaired` may replace email draft with safe generic subject/body.
- Outcomes `needs_manual_review`, `failed`, or critic exception set `needs_manual_review` and route status to `evaluated` for manual Team Member review.
- Critic exception does not crash the evaluation job; error is summarized in `critic_payload`.

### 6.15 Agent Activity Timeline and Audit Panel

The Team-scoped assessment detail must show the autopilot timeline:

- Candidate submitted.
- Resume uploaded.
- Resume text extracted.
- Resume screened by Qwen.
- Assessment queued.
- MCQ graded.
- Essay evaluated by Qwen.
- Critic pass completed.
- Ranking calculated.
- Draft email generated.
- Waiting for Team Member approval.
- Approved/rejected/overridden.
- Email sent/failed.

Audit panel displays:

- Model used.
- Endpoint/provider.
- Threshold.
- Ranking formula.
- Score components.
- Justification.
- Risk flags.
- Interview probes.
- Critic result.
- Error state and retry action on failure.

### 6.16 Team Member Recovery and Override

Additional authorized Team Member actions:

- Retry evaluation for `evaluation_failed` is available.
- Retry email for `email_failed` is available and reuses the stored approved subject/body.
- Promote to interview for `evaluated` or `needs_manual_review` is available.
- Override ranking score with reason is available.
- Reject candidate with reason is available.
- Approve generated questions.
- Reopen generated question draft before campaign is active.

All retry, promote, override, and reject reasons are recorded in the assessment audit trail.

## 7. Database Schema

Production target database is PostgreSQL. Local development currently also uses PostgreSQL.

### 7.1 `users`

Additional columns:

| Column | Type | Notes |
| --- | --- | --- |
| `google_id` | string nullable | Google OAuth subject |
| `current_team_id` | foreign id nullable | selected Current Team |

### 7.2 Campaign Questions

Global and reusable-library question tables are not used. Candidate exam
questions are authored or generated as snapshots in `campaign_questions` (see
section 7.5).

### 7.3 `assessments`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint | primary key |
| `user_id` | foreign id | candidate owner |
| `answers_payload` | json/jsonb | snapshot of questions, rubrics, and answers |
| `ai_score` | integer nullable | total score 0-100 |
| `ai_justification` | text nullable | AI justification |
| `ai_email_subject` | string nullable | email subject draft |
| `ai_email_body` | text nullable | email body draft |
| `approved_email_subject` | string nullable | final subject approved by a Team Member |
| `approved_email_body` | text nullable | final body approved by a Team Member |
| `status` | string/enum | assessment status |
| `evaluated_at` | timestamp nullable | evaluation completion time |
| `approved_by` | foreign id nullable | Team Member approver |
| `approved_at` | timestamp nullable | approval time |
| `rejected_at` | timestamp nullable | reject time |
| `email_sent_at` | timestamp nullable | successful email send time |
| `created_at` | timestamp |  |
| `updated_at` | timestamp |  |

Recommended indexes:

- `assessments.user_id`
- `assessments.status`
- `assessments.ai_score`
- `assessments.approved_by`

### 7.4 `answers_payload` Shape

Payload stores snapshots so past assessments remain auditable even if questions/rubrics change.

```json
[
  {
    "question_id": 1,
    "question": "Explain database indexing tradeoffs.",
    "rubric": "Candidate should mention lookup speed, write overhead, storage, and query planner.",
    "answer": "..."
  }
]
```

### 7.5 Extended campaign and assessment schema

Additional schema recommended for the AI Hiring Autopilot direction.

#### `campaigns`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint | primary key |
| `title` | string | campaign name |
| `role_title` | string | job title |
| `seniority` | string nullable | junior/mid/senior/etc |
| `job_description` | text nullable | role context |
| `required_skills` | jsonb | required skill array |
| `language` | string | default `id` or `en` |
| `passing_score` | integer | campaign threshold (implementation: `threshold_score` column) |
| `ranking_weights` | jsonb | resume/mcq/essay weights |
| `status` | string/enum | draft, question_review, active, archived |
| `created_by` | foreign id | authoring Team Member |
| timestamps | timestamp |  |

The earlier reusable-library tables were removed. Campaign questions are the
only current question records.

#### `campaign_sections`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint | primary key |
| `campaign_id` | foreign id | campaign owner |
| `title` | string | section title |
| `description` | text nullable | participant instruction |
| `duration_minutes` | integer nullable | per-section duration in minutes |
| `scoring_mode` | string | percentage, points, weighted |
| `weight` | decimal/integer | section score weight |
| `sort_order` | integer | section order |
| timestamps | timestamp |  |

#### `campaign_questions`

Question snapshots authored or generated for a Campaign.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint | primary key |
| `campaign_id` | foreign id | campaign owner |
| `campaign_section_id` | foreign id nullable | section owner |
| `type` | string | question type |
| `prompt` | text | copied question text |
| `options` | jsonb nullable | copied options |
| `correct_answer` | jsonb/string nullable | copied answer key |
| `expected_rubric` | text nullable | copied rubric |
| `points` | integer | point value |
| `difficulty` | string nullable | difficulty |
| `skill_tags` | jsonb nullable | skill mapping |
| `grading_mode` | string | deterministic, ai, manual |
| `ai_generated` | boolean | whether generated by AI |
| `status` | string | draft, approved, archived |
| `sort_order` | integer | question order |
| timestamps | timestamp |  |

#### `assessments` expansion columns

| Column | Type | Notes |
| --- | --- | --- |
| `campaign_id` | foreign id nullable | campaign context |
| `resume_path` | string nullable | private PDF path |
| `resume_text` | text nullable | extracted PDF text |
| `resume_score` | integer nullable | 0-100 |
| `resume_justification` | text nullable | AI screening justification |
| `resume_payload` | jsonb nullable | structured resume screening |
| `mcq_score` | integer nullable | deterministic score 0-100 |
| `essay_score` | integer nullable | AI essay score 0-100 |
| `ranking_score` | decimal nullable | final transparent ranking score |
| `ranking_payload` | jsonb nullable | score components and explanation |
| `critic_payload` | jsonb nullable | self-correction result |
| `needs_manual_review` | boolean | default false |

#### `assessment_events`

Audit trail for Agent Activity Timeline (actual implementation).

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint | primary key |
| `assessment_id` | foreign id | assessment owner |
| `actor_id` | foreign id nullable | user for human action (Team Member/Candidate); null for job/system |
| `type` | string | machine slug, e.g. `candidate_submitted`, `resume_screened`, `ranking_calculated`, `admin_approved` |
| `title` | string | UI display title |
| `description` | text nullable | human-readable summary |
| `payload` | jsonb nullable | non-sensitive metadata (sanitized by `AssessmentEventRecorder`) |
| `occurred_at` | timestamp | event time (timeline order) |
| `created_at`, `updated_at` | timestamp | Eloquent timestamps |

Index: `type`, `occurred_at`, `(assessment_id, occurred_at)`.

Example `type` values (non-exhaustive): `candidate_submitted`, `resume_uploaded`, `resume_extracted`, `resume_screened`, `resume_screening_failed`, `assessment_queued`, `evaluation_started`, `qwen_essay_evaluation_completed`, `deterministic_grading_completed`, `ranking_calculated`, `critic_completed`, `critic_failed`, `draft_email_generated`, `evaluation_completed`, `evaluation_failed`, `admin_approved`, `admin_rejected`, `admin_promoted`, `admin_overrode_ranking_score`, `admin_retried_evaluation`, `admin_retried_email`, `email_sent`, `email_failed`.

## 8. Assessment Status

Core statuses:

- `submitted`: answers newly received.
- `evaluating`: being graded by AI.
- `pending_approval`: score meets threshold and awaits a Team Member decision.
- `evaluated`: evaluation complete but below threshold.
- `rejected`: rejected by a Team Member.
- `approved`: approved by a Team Member and email job scheduled.
- `email_sent`: invitation email sent successfully.
- `email_failed`: email send failed.
- `evaluation_failed`: AI evaluation failed or response invalid.

Additional platform statuses:

- `resume_processing`: PDF resume being extracted.
- `resume_screening`: resume being scored by Qwen.
- `needs_manual_review`: critic or business rule requires additional review.
- `overridden`: a Team Member overrode the AI recommendation.

## 9. Qwen Cloud Integration

### 9.1 Service Layer

Service classes include:

- `App\Services\Ai\QwenAssessmentEvaluator`

Responsibilities:

- Accept assessment data.
- Assemble prompt.
- Call Qwen Cloud via Laravel AI SDK.
- Return structured DTO/array.
- Throw exception if response invalid.

Configuration:

- `QWEN_API_KEY`
- `DASHSCOPE_API_KEY` as fallback for DashScope key
- `QWEN_BASE_URL`
- `QWEN_MODEL`
- `QWEN_TIMEOUT`

Store mapping in `config/services.php` or `config/assessment.php`.

Current implementation uses package `laravel/ai` with custom provider `qwen` because Qwen DashScope compatible-mode endpoint uses OpenAI-compatible `chat/completions` pattern.

### 9.2 Structured Output Contract

Qwen integration must not rely only on text prompts such as "reply JSON only". Primary strategy is official Qwen Cloud structured output when available, e.g. JSON mode, response schema, function calling, or tool calling.

Current Qwen Cloud implementation notes:

- OpenAI-compatible endpoint used is `POST /compatible-mode/v1/chat/completions`.
- Structured output uses `response_format: {"type": "json_object"}`.
- Prompt/instructions must contain the word `JSON` because Qwen requires it for JSON Object mode.
- `enable_thinking` must be `false` for structured output requests because JSON mode is incompatible with thinking mode.
- `max_tokens` is not sent so JSON output is not truncated.
- JSON Object mode guarantees valid JSON but not schema conformance. Backend must still validate schema, data types, score ranges, and email draft completeness.

Implementation priority:

1. Use structured output/schema/tool calling from Qwen Cloud when the API supports it.
2. Backend still validates shape and data types.
3. If structured output is unavailable, use JSON prompt fallback with strict parsing, limited retry, and repair flow.

Expected output schema:

```json
{
  "score": 82,
  "justification": "Candidate explains key technical tradeoffs clearly.",
  "email": {
    "subject": "Interview Invitation",
    "body": "Hello ..."
  }
}
```

Backend validation:

- `score` required integer 0-100.
- `justification` required string.
- `email.subject` required string if score meets threshold.
- `email.body` required string if score meets threshold.
- Additional AI fields ignored unless explicitly supported by backend.

### 9.3 Fallback Parsing and Repair Flow

If Qwen API does not yet support strong structured output, the evaluator service uses this fallback:

1. First request asks for JSON schema output.
2. Backend attempts decode and validation.
3. If parsing fails or fields invalid, perform at most 1-2 retries.
4. Retry may use repair prompt sending prior raw response and asking the model to convert to schema-compliant JSON.
5. If still failing, assessment enters `evaluation_failed`.

Notes:

- Retries must be bounded to avoid uncontrolled cost.
- Raw response may be stored for internal debugging only if needed.
- Raw response is not shown to candidates.
- API keys, auth headers, and sensitive information must not appear in logs.

### 9.4 Failure Handling

If Qwen times out, API errors, structured response invalid, or retry/repair fails:

- Set status `evaluation_failed`.
- Store brief error for internal troubleshooting if needed.
- Do not expose API key or raw sensitive response in candidate UI.
- An authorized Team Member can see `evaluation_failed` status and trigger manual retry from the workstation.

## 10. Routes and Pages

### 10.1 Current Team Hiring Routes

Prefix: `/admin` (retained as a URL namespace; it does not represent a global role)

Middleware: `auth`, `current-team`, plus Team-scoped policies

Pages:

- `GET /admin/assessments/{assessment}`
- `POST /admin/assessments/{assessment}/approve`
- `POST /admin/assessments/{assessment}/reject`
- `POST /admin/assessments/{assessment}/retry-evaluation`
- `POST /admin/assessments/{assessment}/override-score`

Campaign and question generation routes:

- `GET|POST|PATCH|DELETE /admin/campaigns` (+ standard resource)
- `POST /admin/campaigns/{campaign}/publish`
- `POST /admin/campaigns/{campaign}/generate-assessment`
- `POST /admin/campaigns/{campaign}/sections`
- `DELETE /admin/campaigns/{campaign}/sections/{section}`
- `POST|PATCH|DELETE /admin/campaigns/{campaign}/questions` (+ approve and approve-all)
- `GET /admin/rankings`

The ranking page is the assessment collection view and links to assessment
detail. There is no separate `/admin/assessments` index route.

Note: campaign question approve uses `POST /admin/campaigns/{campaign}/questions/{question}/approve`, not flat path `campaign-questions`.

Approve payload:

- `email_subject`
- `email_body`

Both fields are required because they become the final sent email.

### 10.2 Candidate Routes

Prefix: `/candidate`

Middleware: `auth`, with Campaign participation and resource ownership checks

Pages:

- `GET /invites/{token}` (guest invite entry; stores pending redemption, then Google sign-in)
- `GET /candidate/exam` (redirects when exactly one assigned campaign is accessible)
- `GET /candidate/campaigns/{campaign}/exam`
- `POST /candidate/campaigns/{campaign}/exam-sessions` (start or resume secure exam session)
- `PATCH /candidate/campaigns/{campaign}/exam-sessions/{examSession}` (save current section answers)
- `POST /candidate/campaigns/{campaign}/exam-sessions/{examSession}/advance`
- `POST /candidate/campaigns/{campaign}/exam-sessions/{examSession}/violations`
- `POST /candidate/campaigns/{campaign}/exam-sessions/{examSession}/finalize` (resume PDF + create assessment)
- `GET /candidate/assessments/{assessment}`

Team Member Campaign Invitations:

- `POST /admin/campaigns/{campaign}/invitations`

Candidates must accept a campaign invitation (assigned-only access) before opening a campaign exam. Resume PDF is uploaded on **exam session finalize**, after all sections are complete.

### 10.3 Redirect Rules

After login, pending ownership transfers, Team Invitations, and Campaign
Invitations are redeemed first. Otherwise the user returns to an authorized
intended URL or `/dashboard`.

Unauthorized contextual access returns 403 or redirects to a safe page.

## 11. Frontend Structure

Main Inertia pages:

- `resources/js/pages/admin/campaigns/*`
- `resources/js/pages/admin/assessments/show.tsx`
- `resources/js/pages/admin/rankings/index.tsx`
- `resources/js/pages/candidate/exam.tsx`
- `resources/js/pages/candidate/assessments/show.tsx`

Review of AI-generated questions is inline on `admin/campaigns/show.tsx` (not a separate page).

Layout:

- Reuse existing `AppLayout`.
- Navigation reflects Current Team capabilities, Candidate participation, and Platform Operator authority.

Frontend conventions:

- Use Inertia `<Form>`.
- Use Wayfinder imports from `@/actions` or `@/routes`.
- Reuse existing UI components in `resources/js/components/ui`.
- Avoid hardcoded URLs where Wayfinder route helpers exist.

## 12. Jobs and Mail

### 12.1 Jobs

Jobs:

- `ScreenResumeWithAi`
- `EvaluateAssessmentWithAi`
- `SendInterviewInvitationEmail`

Queue behavior:

- Evaluation job should have timeout and retry limits.
- Email job should be retryable.
- Job failure should update assessment status where appropriate.

### 12.2 Mail

Mailable:

- `InterviewInvitationMail`

Input:

- Candidate name.
- Candidate email.
- Team-Member-approved subject.
- Team-Member-approved body.

Example invitation content:

- Plain text or simple Markdown mail.
- Generic invitation without specific interview date or time.

## 13. Security and Audit

Security requirements:

- Candidate can only access their own Campaign participation and assessments.
- Team Members can access hiring data only within their Current Team and capabilities.
- Platform Operators do not gain Candidate-content or hiring-decision access.
- Context middleware and policies protect route groups and resources.
- Team Member approval endpoints validate status transitions.
- AI-generated email is treated as untrusted text.
- Team-Member-approved email subject/body is used for actual sending.
- API secrets stored only in `.env`.
- Do not log Qwen API key.

Audit fields:

- Direct columns on `assessments`: `approved_by`, `approved_at`, `rejected_at`, `email_sent_at`, `evaluated_at`.
- Full timeline (resume screened, ranking calculated, critic, Team Member retry, etc.) is recorded in **`assessment_events`** (`type`, `title`, `occurred_at`, `payload`), not separate per-stage timestamp columns on `assessments`.

## 14. Testing Requirements

Use Pest feature tests.

Minimum tests:

- Google sign-in creates or updates a user without a global role.
- Current Team routes reject users without an active Team Membership in that Team.
- Candidate routes enforce Campaign participation and assessment ownership.
- Platform Operator routes require independent support authority.
- Authorized Team Members can create/update/delete Campaign questions.
- Candidate can view approved questions for the active campaign.
- Candidate can submit assessment.
- Submission dispatches AI evaluation job.
- AI job stores score, justification, email draft.
- Effective threshold (ranking + critic gate) changes status to `pending_approval` when allowed.
- Below-threshold or critic-blocked results change status to `evaluated`.
- Authorized Team Members can approve reviewable assessments.
- Authorized Team Members can edit email subject/body during approval.
- Approve dispatches email job.
- Authorized Team Members can reject reviewable assessments.
- Candidate cannot view another candidate's assessment.
- Candidate cannot submit more than one assessment for the same campaign.
- Authorized Team Members can create Campaigns with role/JD/skills.
- Qwen question generator returns sections/questions/rubrics/answer keys in valid shape.
- Authorized Team Members can approve AI-generated Campaign questions.
- Candidate can upload valid resume PDF.
- Invalid resume upload is rejected.
- Resume text extraction result is persisted.
- Resume screening stores structured result.
- Deterministic questions are graded without Qwen.
- Essay questions are graded through Qwen with rubric context.
- Ranking score is calculated from configured weights.
- Critic pass can mark assessment as `needs_manual_review`.
- An authorized Team Member can retry `evaluation_failed`.
- An authorized Team Member can promote/override a Candidate with an override reason.
- The assessment event timeline records major system and Team Member actions.

Use Laravel AI SDK fake and HTTP fake for Qwen API tests.
Use Mail fake for email tests.
Use Queue fake where appropriate for controller tests, and run job directly for job behavior tests.

## 15. Product Implementation Plan

### Phase 1: Foundation (historical, superseded)

The original global-role foundation was replaced by contextual identities:
Team Membership, Candidate Campaign participation, Current Team selection, and
independent Platform Operator authority. The legacy user-role column and role
middleware were removed.

### Phase 2: Question Libraries (historical, removed)

The reusable-library implementation was removed. Current authoring uses
Campaign sections and Campaign questions only.

### Phase 3: Candidate Exam

- Create `Assessment` model, migration, factory.
- Build candidate exam page.
- Implement assessment submit.
- Store `answers_payload` snapshot.
- Add tests.

### Phase 4: AI Evaluation

- Add Laravel AI SDK.
- Add Qwen service.
- Add Qwen AI SDK provider/gateway.
- Add evaluation job.
- Add config/env values.
- Validate AI structured response.
- Add fallback parsing, retry, and repair handling for invalid output.
- Persist score, justification, and email draft.
- Apply configurable threshold.
- Add tests with HTTP fake.

### Phase 5: Team Member Workstation

- Build the ranking collection page and assessment detail page.
- Add approve/reject endpoints.
- Add status transition validation.
- Add tests.

### Phase 6: Email Execution

- Add mailable.
- Add email job.
- Wire approve action to save Team-Member-approved email content and dispatch email job.
- Add Mail fake tests.

### Phase 7: Campaign-based hiring flow

- Add campaign/role setup.
- Add question types and campaign sections.
- Author questions within Campaign sections.
- Add AI assessment generator draft flow.
- Add Team Member review/approve generated questions.
- Add resume PDF upload and extraction.
- Add Qwen resume screening.
- Add hybrid scoring and ranking.
- Add self-correction/critic pass.
- Add agent activity timeline and audit panel.
- Add retry evaluation and Team Member override/promote.

## 16. Product Scope

Delivered since the original scope was written:

- Secure exam integrity controls, including server-enforced section timers and integrity warnings.
- Anti-cheat guardrails such as fullscreen, navigation, clipboard, and context-menu controls.
- Multi-tenant Team support with contextual Team Membership and Current Team authorization.

The following remain out of scope for the current product:

- Scheduling interview time slots.
- Complex rich text editor for email.
- Retake assessment.
- Public external API with JWT.
- Advanced analytics beyond basic ranking dashboard.
- Audio/video response grading.
- Arbitrary file upload other than resume PDF.

## 17. Risks and Mitigation

| Risk | Mitigation |
| --- | --- |
| Qwen structured response invalid | Use schema/tool calling when available, strict validation, bounded retry/repair, then `evaluation_failed` status |
| AI gives inconsistent scores | Use question-level rubrics and manual Team Member review before approval |
| Submit request timeout | Evaluation via queue job |
| Email sent without review | AI drafts only, manual approve required |
| Candidate sees others' data | Policy or explicit ownership check |
| Rubric changes after submit | Snapshot in `answers_payload` |
| Hiring access granted globally | Derive authority from Team Membership and Current Team context |
| AI-generated question wrong or ambiguous | Initial draft status, Team Member must review/approve, shape validation tests |
| AI uses protected attributes from resume | Fairness prompts, structured validation, audit panel, Team Member final decision |
| Ranking perceived as black box | Backend ranking with transparent formula and score components |
| PDF text extraction fails | Clear error status, retry/manual review |
| Self-correction adds cost and latency | Run via queue, limit retries, store event timing |

## 18. Follow-Up Product Decisions

- Candidates may not retake an assessment for the same campaign. One submission per candidate per campaign.
- Score threshold is configured per Campaign, with an application default of `75`.
- An authorized Team Member may edit the email draft before approval.
- Results below threshold enter `evaluated`, not `rejected`, so a Team Member can review false negatives manually.
- Interview email uses generic invitation text without specific date or time.
- Product is positioned as AI hiring workflow autopilot, not a general CAT/quiz builder.
- Resume must be PDF.
- MCQ/yes-no/fill/matching graded deterministically.
- Short/long text and resume graded AI-assisted with rubric/role context.
- Primary ranking computed by backend with transparent formula; Qwen provides explanation, not the sole ranking source.
- Authorized Team Member approval is required for AI-generated questions and the final interview email.

## 19. Initial Technical Decisions

- Use session auth web guard + Socialite, not JWT, for this web product.
- Use a single `users` table with contextual Team Membership, Campaign participation, and Platform Operator authority instead of a global role.
- Use queue jobs for AI evaluation and email sending.
- Use Qwen Cloud via Laravel AI SDK with custom Qwen provider/gateway.
- Use PostgreSQL for deployment target.
- Use `answers_payload` JSON snapshot for simple auditable design.
- Use human-in-the-loop as explicit boundary before email is sent.
- Use `campaigns`, `campaign_sections`, and `campaign_questions` for extended scope.
- Author and snapshot questions directly within Campaigns; reusable question libraries are not part of the current product.
- Use private storage for resume PDF.
- Use PDF text extraction for resume processing.
- Use `assessment_events` for timeline and audit trail.
