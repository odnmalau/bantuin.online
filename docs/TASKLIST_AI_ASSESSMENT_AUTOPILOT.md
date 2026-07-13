# Tasklist: AI Hiring Assessment Autopilot

> **Status: Historical and partially superseded (2026-07-13).** This checklist
> records the original assessment-autopilot delivery phases; its global-role,
> question-library, and demo-seeding tasks are not current architecture. Use
> [`CONTEXT.md`](../CONTEXT.md) and
> [ADR 0001](adr/0001-use-contextual-identities-for-team-tenancy.md) for current
> domain and authorization decisions. Use git history and the current test suite
> for delivery status rather than these checkboxes.

## Related docs

- [Current product and domain context](../CONTEXT.md)
- [ADR 0001: contextual identities for Team tenancy](adr/0001-use-contextual-identities-for-team-tenancy.md)
- [How it works](HOW_IT_WORKS_AI_ASSESSMENT_AUTOPILOT.md)
- [Product requirements (PRD)](PRD_AI_ASSESSMENT_AUTOPILOT.md)

## Status legend

- `[ ]` Not started
- `[x]` Done
- `[~]` In progress or partially complete

## Phase 1: Role Auth Foundation (Historical, Superseded)

Historical delivery status: `[x]` Google OAuth (Socialite), global `admin` /
`candidate` roles, `role` middleware, role redirects, and role-based navigation
were delivered and later replaced by contextual Team Membership, Campaign
participation, and Platform Operator authority.

### Backend

- [x] Add migration for `role` on `users`.
- [x] Default new Google users to role `candidate` (`AuthenticateGoogleUser`).
- [x] Update `UserFactory` to create `admin` and `candidate` users.
- [x] Add enum or constants for user roles.
- [x] Create `role` middleware.
- [x] Register middleware alias `role`.
- [x] Historical: protect hiring routes with the legacy global-role middleware (later removed).
- [x] Protect candidate routes with `auth`, `role:candidate`.
- [x] Add seeder or command for initial Admin user.
- [x] Implement role-based redirect after login:
  - Admin → `/admin/assessments`.
  - Candidate → `/candidate/exam`.

### Frontend

- [x] Update shared `auth.user` type to include `role`.
- [x] Update navigation so Admin and Candidate items show by role.
- [x] Ensure settings pages are reachable for any authenticated user (`auth` only, no role gate).

### Tests

- [x] New Google sign-in creates role `candidate` (`GoogleAuthenticationTest`, `RoleAccessTest`).
- [x] OAuth does not promote users to admin.
- [x] Admin routes reject candidates.
- [x] Candidate routes reject admins.
- [x] Guests are redirected to login for admin/candidate routes.
- [x] Login redirect depends on role.
- [x] Authenticated admin and candidate users can access `settings/profile` (`auth` middleware only).

Acceptance criteria:

- Admin and Candidate sign in via Google OAuth.
- Public registration / password login is not available.
- Route access is separated by role.

## Phase 3: Candidate Exam and Assessment Submission

Implementation status: `[x]` Exam and submission are tied to the **active campaign** with `campaign_questions` snapshots, required resume PDF upload, one submission per `(user, campaign)`, and a queue chain of resume screening then AI evaluation. Extended assessment columns (ranking, critic, etc.) are added in later-phase migrations.

### Backend

- [x] Create `Assessment` model.
- [x] Create `assessments` migration (foundation) and follow-up migration for `campaign_id`, resume, and ranking scores.
- [x] Add foundation columns:
  - `user_id`
  - `campaign_id`
  - `answers_payload`
  - `ai_score`
  - `ai_justification`
  - `ai_email_subject`
  - `ai_email_body`
  - `approved_email_subject`
  - `approved_email_body`
  - `status`
  - `evaluated_at`
  - `approved_by`
  - `approved_at`
  - `rejected_at`
  - `email_sent_at`
- [x] Add exam submit (resume) columns: `resume_path`, `resume_original_name` (plus resume screening fields on the later pipeline).
- [x] Add unique constraint on `user_id` + `campaign_id` (one assessment per candidate per campaign).
- [x] Add indexes for `status`, `ai_score`, `approved_by`, and related score/ranking columns.
- [x] Create `AssessmentFactory`.
- [x] Add enum or constants for assessment status.
- [x] Create Candidate controllers for exam and assessment submission.
- [x] Create form request for assessment submit (including resume PDF validation).
- [x] On exam/submit, resolve **active campaign** (`status = active`, latest `activated_at` when multiple).
- [x] Load all **approved** `campaign_questions` per section, ordered by `sort_order` (`draft` questions are hidden).
- [x] Validate every approved question has an answer.
- [x] Persist auditable snapshot in `answers_payload` at minimum:
  - `question_id`, `question`, `rubric`, `answer`
  - plus snapshot metadata: `type`, `grading_mode`, `options`, `correct_answer`, `points`, section, skill tags, etc.
- [x] Enforce one submission per candidate **per active campaign** (assessments on other campaigns are allowed).
- [x] After assessment is created, dispatch queue chain:
  - `ScreenResumeWithAi`
  - `EvaluateAssessmentWithAi`
- [x] Record submit/upload/queue events via `AssessmentEventRecorder`.
- [x] Add Candidate routes:
  - `GET /invites/{token}`
  - `GET /candidate/exam`
  - `GET /candidate/campaigns/{campaign}/exam`
  - `POST|PATCH|POST ... /candidate/campaigns/{campaign}/exam-sessions/*` (secure exam runtime; see Phase 17)
  - `GET /candidate/assessments/{assessment}`
  - `POST /admin/campaigns/{campaign}/invitations`
- [x] Enforce assigned-only campaign access via accepted `campaign_invitations`.
- [x] Ensure Candidate can only view their own assessments.
- [x] Regenerate or ensure Wayfinder helpers exist (`@/routes/candidate`, `@/actions/.../ExamSessionController`).

### Frontend

- [x] Create page `resources/js/pages/candidate/exam.tsx`.
- [x] Answer inputs per question type (textarea, MCQ/yes-no radio, fill blank, matching pairs).
- [x] Add resume PDF upload on exam form.
- [x] Show empty state when no active campaign has approved questions.
- [x] Disable submit while processing (spinner + upload progress).
- [x] After submit, redirect to assessment detail/status.
- [x] Create page `resources/js/pages/candidate/assessments/show.tsx`.
- [x] Show assessment status (badge), including at least:
  - `submitted`
  - `resume_processing`
  - `resume_screening`
  - `evaluating`
  - `pending_approval`
  - `evaluated`
  - `rejected`
  - `email_sent`
  - `evaluation_failed`
- [x] Poll status while assessment is still processing (Inertia `usePoll`).
- [x] If candidate already submitted for the active campaign, show existing state and hide form (link to detail).

### Tests

- [x] Candidate can see approved questions for the active campaign.
- [x] `draft` questions do not appear on the exam.
- [x] Candidate can submit a complete assessment (answers + resume PDF).
- [x] `answers_payload` stores question, rubric, and answer snapshot.
- [x] Candidate cannot submit twice for the same campaign.
- [x] Candidate can submit a different active campaign (when one exists).
- [x] Candidate cannot submit for a non-active campaign.
- [x] Candidate cannot view another candidate’s assessment.
- [x] Submission queues chain `ScreenResumeWithAi` then `EvaluateAssessmentWithAi`.

Acceptance criteria:

- Candidate takes the **active campaign** and submits **once per campaign**.
- Resume PDF upload is required; answers are stored as an auditable snapshot independent of Campaign edits after submit.
- Submission triggers the AI pipeline asynchronously (resume screening, then assessment evaluation).

## Phase 4: AI Evaluation Pipeline

Implementation status: `[x]` Qwen evaluator via Laravel AI SDK runs with strict validation, repair, and queue job. Job runtime also integrates deterministic grading, ranking, and critic (phases 13–14); final review status uses **ranking score** (+ critic gate), not raw `ai_score` alone.

### Configuration

- [x] Create assessment config, e.g. `config/assessment.php`.
- [x] Add default threshold `75` (`ASSESSMENT_PASSING_SCORE`).
- [x] Add Qwen config (provider/model/timeout in `config/assessment.php` + provider in `config/ai.php`):
  - `QWEN_API_KEY`
  - `DASHSCOPE_API_KEY` as fallback key
  - `QWEN_BASE_URL`
  - `QWEN_MODEL`
  - `QWEN_TIMEOUT`
- [x] Ensure secrets are read only from `.env` (via `env()` in config).

### Backend service

- [x] Create service `App\Services\Ai\QwenAssessmentEvaluator`.
- [x] Install and configure Laravel AI SDK `laravel/ai`.
- [x] Create structured output agent `App\Ai\Agents\AssessmentEvaluatorAgent`.
- [x] Create custom Laravel AI SDK provider `qwen` (`App\Ai\Providers\QwenProvider`).
- [x] Create custom Qwen gateway for `chat/completions` (`App\Ai\Gateway\QwenGateway`).
- [x] Design evaluation result DTO/contract (`AssessmentEvaluationResult`).
- [x] Implement prompt assembly from assessment context:
  - candidate & campaign (role/JD/skills)
  - per answer: question, rubric, type, grading mode, answer
  - effective threshold (`AssessmentThreshold`: config default or `campaign.threshold_score`)
- [x] Call Qwen via Laravel AI SDK.
- [x] Qwen structured output:
  - JSON Object mode `response_format: {"type": "json_object"}`
  - agent instructions include the word `JSON`
  - `enable_thinking=false` for structured output requests
  - backend schema validation (`AssessmentEvaluationResult::fromStructuredOutput`)
- [x] Validate response:
  - `score` integer 0–100
  - `justification` non-empty string
  - `email.subject` / `email.body` required when `score >=` effective threshold
- [x] Remove fake evaluator based on answer length.
- [x] Implement limited HTTP transport retries in Qwen gateway.
- [x] Implement repair flow for parse/validation failures (`ASSESSMENT_EVALUATION_REPAIR_ATTEMPTS`).
- [x] Ensure raw sensitive responses / API keys do not leak to candidate UI.

### Queue job

- [x] Create job `EvaluateAssessmentWithAi`.
- [x] When job starts, set status to `evaluating` and record event.
- [x] Call `QwenAssessmentEvaluator` (essay / AI-graded answers).
- [x] Run **hybrid runtime** in the same job:
  - `DeterministicAssessmentGrader` → `mcq_score`
  - `CandidateRankingCalculator` → `ranking_score` + `ranking_payload`
  - `QwenAssessmentCritic` → `critic_payload`, `needs_manual_review`, repaired email draft when the critic outcome is `repaired`
- [x] Persist evaluation results:
  - `ai_score`, `essay_score`, `ai_justification`
  - `ai_email_subject`, `ai_email_body` (after critic repair when applicable)
  - `mcq_score`, `ranking_score`, `ranking_payload`, `critic_payload`
  - `evaluated_at`
- [x] Determine review status:
  - `pending_approval` when `ranking_score >= threshold` **and** critic does not block autopilot approval
  - `evaluated` when below threshold, critic blocks, or component scores fail review gates
- [x] On Qwen evaluator failure (timeout/API/invalid after repair), set status `evaluation_failed`.
- [x] Set job timeout, retries, and backoff explicitly (`tries`, `timeout`, `backoff()`).

### Tests

- [x] Evaluator parses valid structured response.
- [x] Evaluator rejects score outside 0–100 / invalid types.
- [x] Evaluator requires email draft when score meets threshold.
- [x] Qwen evaluator sends requests through Laravel AI SDK provider.
- [x] Invalid structured response triggers repair.
- [x] Final failure yields status `evaluation_failed`.
- [x] Score meeting threshold → `pending_approval` (via job + ranking gate in test data).
- [x] Low score → `evaluated`.
- [x] Config / campaign threshold affects review status.
- [x] Raw secrets are not in prompt payload or candidate UI response.
- [x] Qwen HTTP request uses `json_object`, `enable_thinking=false`, bearer key, `chat/completions` endpoint (`AiAssessmentEvaluationTest`).
- [x] Critic blocks `pending_approval` / forces `evaluated` (`AssessmentCriticTest` + job integration).
- [x] Job sets status `evaluating` on start (`AiAssessmentEvaluationTest`).

Acceptance criteria:

- AI evaluation runs via queue (usually after `ScreenResumeWithAi` on candidate submit).
- AI output is strictly validated; invalid output does not crash the app (`evaluation_failed` or manual review via critic).
- Assessment status reflects hybrid evaluation (AI essay + deterministic + ranking + critic).

## Phase 5: Admin Workstation

Implementation status: `[x]` Ranking collection/detail, approve/reject, and final email validation are available. UI/runtime extended (ranking, resume, timeline/audit, promote/retry/override — phases 12–16). Approve/reject use `AssessmentStatus::isReviewable()`; reject requires `reason`.

### Backend

- [x] Create Admin assessment controller (`App\Http\Controllers\Admin\AssessmentController`).
- [x] Core routes:
  - `GET /admin/assessments/{assessment}`
  - `POST /admin/assessments/{assessment}/approve`
  - `POST /admin/assessments/{assessment}/reject`
- [x] Recovery/expansion routes (phase 16, same workstation):
  - `POST .../retry-evaluation`, `retry-email`, `promote`, `override-score`
- [x] Ranking page (`GET /admin/rankings`) shows:
  - Candidate name & email
  - Scores (`ai_score` / essay, resume, MCQ, **ranking** components)
  - Status (badge)
  - Submitted (`created_at`) & evaluated (`evaluated_at`)
- [x] Assessment detail shows:
  - Answers per question & rubric snapshot (`answers_payload`)
  - Resume screening summary (phase 12)
  - AI score & ranking components
  - AI justification
  - Draft subject/body (`ai_email_*`) and final email when approved
  - Event timeline & AI audit panel (phase 15)
- [x] Approve/reject only for **reviewable** statuses (`isReviewable()`):
  - `pending_approval`
  - `evaluated`
  - `needs_manual_review`
  - `overridden`
- [x] Approve persists:
  - `approved_email_subject`
  - `approved_email_body`
  - `approved_by`
  - `approved_at`
- [x] Approve sets status `approved`.
- [x] Approve dispatches `SendInterviewInvitationEmail`.
- [x] Reject sets `rejected_at`, status `rejected`, **reason** (required, recorded in events).
- [x] Reject does not send email.
- [x] Approve payload validation:
  - `email_subject` required string
  - `email_body` required string
- [x] Reject payload validation:
  - `reason` required string

### Frontend

- [x] Create page `resources/js/pages/admin/rankings/index.tsx`.
- [x] Create page `resources/js/pages/admin/assessments/show.tsx`.
- [x] Status badge per row (no separate filter dropdown).
- [x] Empty state on ranking list.
- [x] Show AI justification, answer/rubric snapshot, email draft.
- [x] Edit subject/body before approve (prefill from AI/approved draft).
- [x] Approve & Reject buttons (reason dialog); disabled when `!can_review`.
- [x] Extra actions: promote, retry evaluation/email, override score (phase 16).

### Tests

- [x] Admin can view the ranking list.
- [x] Admin can view assessment detail.
- [x] Admin can approve reviewable assessments (`pending_approval`, `evaluated`, `needs_manual_review`, `overridden`).
- [x] Approve saves Admin’s final subject/body.
- [x] Approve dispatches email job.
- [x] Admin can reject reviewable assessments (with `reason`).
- [x] Candidate cannot access workstation (index/show/approve/reject).
- [x] Approve/reject rejected for invalid statuses.
- [x] Approve validation requires email subject and body.
- [x] Recovery/override: `AdminAssessmentRecoveryTest` (retry, promote, override).

Acceptance criteria:

- Admin has a workstation to review AI results (including hybrid scoring & resume).
- False negatives can be handled via approve from reviewable status (`evaluated`, etc.) and/or **Promote to Interview**.
- Final email content is what Admin approved before the email job runs.

## Phase 6: Email Invitation Execution

Implementation status: `[x]` Interview invitation email is sent via queue after Admin approve (or official retry from `email_failed`). Mailable uses Admin-approved final subject/body; status and timeline events are recorded. Job refuses send without status `approved` + complete final content.

### Backend

- [x] Create mailable `InterviewInvitationMail` (markdown `mail.interview-invitation`, body per line).
- [x] Use `approved_email_subject` as subject (passed to mailable when job runs).
- [x] Use `approved_email_body` as body.
- [x] Create job `SendInterviewInvitationEmail` (`ShouldQueue`, `tries`, `timeout`, `backoff()`).
- [x] Job sends to candidate email (`Mail::to($user->email)`).
- [x] Send guard: status must be `approved`, final subject/body present, candidate email exists.
- [x] On success: status `email_sent`, set `email_sent_at`, event `email_sent`.
- [x] On transport failure: status `email_failed`, log warning, event `email_failed`.
- [x] When not sendable (e.g. called before approve): no mail sent, status `email_failed`, log skip, event `email_failed`.
- [x] Dispatch job only from Admin flows:
  - `POST .../approve` after saving final email
  - `POST .../retry-email` for `email_failed` (recovery, phase 16)
- [x] Email is never sent without explicit Admin approval (no dispatch from candidate/submit/evaluation).

### Frontend

- [x] Show `approved`, `email_sent`, and `email_failed` on workstation detail (badge + metadata).
- [x] Show final subject/body (**Interview Email** form; prefill `approved_*` or AI draft if not yet approved).
- [x] Show `approved_at` and `email_sent_at` timestamps.
- [x] Show **Retry email** when `can_retry_email` (`email_failed` + final subject/body still present).

### Tests

- [x] `approved` assessment sends `InterviewInvitationMail` to candidate email.
- [x] Mailable uses Admin’s final subject/body.
- [x] Successful job → status `email_sent` + `email_sent_at`.
- [x] Transport failure → status `email_failed`.
- [x] Job not sendable before approve → no mail sent + status `email_failed` (`InterviewInvitationEmailTest`).
- [x] Admin approve queues `SendInterviewInvitationEmail` (`AdminAssessmentWorkstationTest`).
- [x] Timeline includes `admin_approved` and `email_sent` (`AssessmentEventTimelineTest`).
- [x] Autopilot approve → job → sent flow (`AssessmentAutopilotFlowTest`).
- [x] Admin retry email from `email_failed` (`AdminAssessmentRecoveryTest`).

Acceptance criteria:

- Email is sent only after Admin approval (final content stored on assessment).
- Email status (`approved` → `email_sent` / `email_failed`) and send time are clearly recorded.
- Flow is testable with `Mail::fake` / mock transport and Pest feature tests.

## Phase 7: Threshold Configuration

Implementation status: `[x]` Default threshold via config plus per-**campaign** override (`threshold_score`). The former database-backed Admin settings UI was removed. Evaluation uses `AssessmentThreshold::passingScoreFor()`; the review status gate uses **ranking score** vs the effective threshold.

### Minimum requirements

- [x] Store default threshold in `config/assessment.php` (`ASSESSMENT_PASSING_SCORE`, default 75).
- [x] Use effective threshold in `EvaluateAssessmentWithAi` via `AssessmentThreshold` (not literals in job).
- [x] Use effective threshold in `QwenAssessmentEvaluator` (prompt + required email draft when threshold met).
- [x] Add tests that config and Campaign thresholds affect evaluation/review status.

### Removed Admin UI option

The earlier `/admin/assessment-settings` page and `application_settings`
persistence were removed. The current product has no database-backed global
threshold editor.

### Campaign override (related to Phase 9)

- [x] Assessments with `campaign_id` use `campaign.threshold_score` as effective threshold (`passingScoreSource: campaign`).
- [x] Assessments without a Campaign use config (`passingScoreSource: config`).
- [x] Workstation audit panel shows effective threshold, source, and config default.

### Tests

- [x] `AiAssessmentEvaluationTest` — config and campaign threshold affect status.

Acceptance criteria:

- Threshold is not hardcoded in job/evaluator.
- Threshold can be changed through `.env`/config and per Campaign.

## Phase 8: Polish, Observability, and QA

Implementation status: `[x]` Empty states, processing UI, status badges, AI/email failure logging, event payload sanitization, and backend smoke tests. Tooling checks: `tsc --noEmit`, `eslint`, `pint`, plus `AssessmentAutopilotFlowTest` + `CandidateAssessmentTest`.

### UX polish

- [x] Empty states for Campaigns and rankings.
- [x] Additional empty states: campaigns, rankings, sections/questions on campaign detail.
- [x] Loading/processing on form submit (Spinner + `disabled={processing}`) — Candidate exam, workstation, and Campaign forms.
- [x] Consistent assessment status badges (`AssessmentStatusBadge` — admin + candidate).
- [x] Responsive layout (`md:` / `lg:` grids, `flex-wrap`, horizontal scroll tables).
- [x] Validation errors via `InputError` + Inertia session errors.

### Operational safety

- [x] Qwen API key not exposed to frontend / candidate prompt payload (`AiAssessmentEvaluationTest`, `ResumeScreeningTest`).
- [x] Raw internal audit not exposed to candidate (limited props on `candidate/assessments/show`; timeline/critic admin-only).
- [x] Event payload sanitized (`AssessmentEventRecorder` redacts secret/api_key/token).
- [x] AI exceptions not exposed on candidate UI (safe status + fields; server-side logs only).
- [x] Concise evaluation failure logs (`EvaluateAssessmentWithAi`, resume screening).
- [x] Concise email failure logs (`SendInterviewInvitationEmail`).

### Final verification

- [x] Pest: product E2E smoke (`AssessmentAutopilotFlowTest` — submit → eval → approve → email sent).
- [x] Pest: candidate smoke (`CandidateAssessmentTest`).
- [x] Pest: timeline + payload redaction (`AssessmentEventTimelineTest`).
- [x] PHP formatter: `vendor/bin/pint`.
- [x] TypeScript: `pnpm run types:check`.
- [x] ESLint: `pnpm run lint:check`.

Acceptance criteria:

- End-to-end product flow is tested (automated):
  - Campaign/questions prepared (factory in `AssessmentAutopilotFlowTest`; UI question creation in phases 9–10).
  - Candidate submits assessment (once per campaign) + resume.
  - AI jobs evaluate (resume chain + evaluation).
  - Admin reviews & approves with final email.
  - Email sent via job (`email_sent`).

## Suggested implementation order

Product foundation:

1. Role Auth Foundation.
2. Campaign sections and questions (Phase 10).
3. Candidate Exam and Assessment Submission.
4. AI Evaluation Pipeline with Laravel AI SDK Qwen provider.
5. Admin Workstation.
6. Email Invitation Execution.
7. Threshold Configuration.
8. Polish and QA.

Autopilot hiring flow:

1. Campaign / Role Setup.
2. Question Types and Sections.
3. AI Assessment Generator.
4. Resume PDF Screening.
5. Hybrid Grading and Candidate Ranking.
6. Self-Correction / Critic Agent.
7. Agent Activity Timeline and Audit Panel.
8. Admin Recovery and Override.
9. Product demo and documentation.

## Definition of Done — Product foundation

- [x] Team Member and Candidate access boundaries work and are tested.
- [x] Campaign questions work and are tested.
- [x] Candidate can submit only one assessment **per campaign**.
- [x] Candidate status page polls while assessment is processing.
- [x] Assessment stores snapshot of answers, questions, and rubrics.
- [x] AI evaluation runs through queue job and Laravel AI SDK.
- [x] AI structured output is strictly validated.
- [x] Invalid AI output → `evaluation_failed`.
- [x] Effective threshold (ranking) + critic gate → `pending_approval` (not raw `ai_score` alone).
- [x] Score below threshold → `evaluated`.
- [x] Admin can approve `pending_approval` and `evaluated`.
- [x] Admin can edit final email before approve.
- [x] Email is sent only after approval.
- [x] Core tests pass.
- [x] No API secrets or sensitive AI responses leak to frontend.

## Definition of Done — Autopilot hiring flow

- [x] Admin can create campaigns from role/JD/skills.
- [x] Qwen can generate assessment drafts with sections, question types, rubrics, answer keys, skill tags, and points.
- [x] AI-generated questions must be reviewed/approved by Admin before going active.
- [x] Campaign questions are snapshotted into Candidate answers.
- [x] Candidate must upload resume PDF.
- [x] Resume PDF is extracted to text and scored by Qwen.
- [x] Deterministic question types are graded without AI.
- [x] Short/long text is graded by Qwen using rubric.
- [x] Candidate ranking is computed backend-side with a transparent formula.
- [x] Critic/self-correction pass runs on main AI output.
- [x] Admin detail shows agent activity timeline and AI audit panel.
- [x] Admin can retry failed evaluation.
- [x] Admin can promote/override false negatives with reason.
- [x] Final email is still sent only after Admin approval.
- [x] Core backend tests pass.
- [x] TypeScript check and frontend build pass.

## Addendum: Demo data (Historical, Removed)

The dedicated HirePilot demo users, Campaigns, assessments, and idempotency
test were removed. `DatabaseSeeder` no longer installs that historical fixture
set; tests create isolated data with factories.

## Autopilot hiring flow coverage

This section describes end-to-end hiring workflow: from role/JD/skills through ranking, Admin review, and interview email.

```text
Admin input role/JD/skills
→ Qwen generate assessment draft
→ Admin approve questions
→ Candidate upload resume PDF + take assessment
→ Qwen screen resume + grade essays
→ Backend grade deterministic questions
→ Critic agent validates output
→ Backend ranks candidates transparently
→ Admin reviews/overrides/approves
→ Resend sends interview email
```

## Phase 9: Campaign / Role Setup

Implementation status: `[x]` Campaign as hiring context (role/JD/skills, threshold, ranking weights, language). Team Member CRUD + publish, default section on create, and Wayfinder are present. Runtime: assessment `campaign_id`, evaluation threshold, ranking weights (`HybridScoringTest`), and resume/AI prompts use Campaign context.

### Backend

- [x] Model `Campaign` + factory + enum `CampaignStatus` (`draft`, `question_review`, `active`, `archived`).
- [x] Migration `campaigns` + follow-up columns `ranking_weights`, `language`, `activated_at`, `ai_generation_notes`.
- [x] Core columns: `title`, `role_title`, `seniority`, `job_description`, `required_skills`, `threshold_score`, `status`, `created_by`.
- [x] Admin controller `CampaignController` (index/create/store/show/edit/update/destroy/publish).
- [x] Form requests `StoreCampaignRequest` / `UpdateCampaignRequest` + `ValidatesCampaignRankingWeights` (weights sum to 100).
- [x] Create campaign auto-creates default section **Knowledge Check**.
- [x] Publish: status → `active`, set `activated_at`; reject if any `campaign_questions` remain `draft`.
- [x] Destroy: allowed when no assessments; reject when submissions exist.
- [x] Relation `Assessment` → `campaign_id`.
- [x] Historical: resource and publish routes used the legacy global-role middleware (later removed).
- [x] Wayfinder (`CampaignController`, `@/routes/admin`).

### Frontend

- [x] Pages `admin/campaigns/{index,create,edit,show}.tsx`.
- [x] Form `campaign-form.tsx`: role, seniority, JD, required skills, language, threshold, ranking weights, status.
- [x] Status badge on index (`status_label`).
- [x] Sidebar nav **Campaigns**.
- [x] Campaign show: sections, questions, publish, and generate (phase 11).

### Tests

- [x] `AdminCampaignTest` — view/create (+ default section), update, publish + draft guard, ranking weights, language, candidate RBAC, and section/question authoring.
- [x] `CandidateAssessmentTest` — **active** campaign used for exam/submit; non-active rejected.
- [x] `HybridScoringTest` — campaign `ranking_weights` used by ranking calculator.
- [x] `AiAssessmentGenerationTest` — campaign status → `question_review` after generate.
- [x] Admin can delete campaign without assessments; cannot delete when assessments exist (`AdminCampaignTest`).

### Related phases

- `question_review`: usually after AI assessment generator (Phase 11).
- Campaign threshold: Phase 7 (`passingScoreFor`).
- Active demo campaign: `DemoCampaignSeeder` + `DemoSeederTest`.

Acceptance criteria:

- Admin can create/manage hiring campaigns from role/JD/skills (including threshold & ranking weights).
- Campaign is context for candidate exam, resume screening, AI evaluation, and transparent ranking.

## Phase 10: Question Types and Sections (Libraries Removed)

Implementation status: `[x]` Campaign sections, Campaign-local questions, type
and grading mode enums, and answer key/rubric validation remain. The reusable
Question Bank models, pages, import flow, provenance column, and tests were
removed. Candidate runtime sees only **approved** Campaign questions; submitted
answers are snapshotted in `answers_payload`.

### Backend

- [x] Models `CampaignSection` and `CampaignQuestion`.
- [x] Enum `QuestionType`: `multiple_choice`, `yes_no`, `short_text`, `long_text`, `fill_blank`, `matching_pairs`.
- [x] Enum `QuestionGradingMode`: `deterministic`, `ai`, `manual` (+ default from type).
- [x] Campaign question fields: `type`, `prompt`, `options`, `correct_answer`, `expected_rubric`, `points`, `difficulty`, `skill_tags`, `grading_mode`, `ai_generated`, `status`, `sort_order`.
- [x] Validation: deterministic requires answer key; AI text requires rubric; supported grading modes.
- [x] Section store/update with `sort_order` (section order on campaign).

### Frontend

- [x] Campaign section and question authoring on `admin/campaigns/show.tsx`.
- [x] Show type, difficulty, points, skill tags, and grading mode on Campaign detail.

### Tests

- [x] `AdminCampaignTest` — add section (+ **`sort_order`**), add campaign MCQ/AI questions, section must belong to campaign.
- [x] `CandidateAssessmentTest` — only **approved** questions on exam; submit snapshot.
- [x] `HybridScoringTest` — deterministic grading by objective type (runtime).

Acceptance criteria:

- Campaign has auditable sections and questions (manual/generate phase 11).
- Question types support deterministic and AI-assisted grading per `QuestionType` / `QuestionGradingMode`.

## Phase 11: AI Assessment Generator

### Backend

- [x] Create agent `AssessmentGeneratorAgent`.
- [x] Create service `QwenAssessmentGenerator`.
- [x] Design structured output contract for sections + questions.
- [x] Generator prompt uses:
  - role title
  - seniority
  - job description
  - required skills
  - language
  - desired question type mix
  - number of questions
  - difficulty distribution
- [x] Generate draft `campaign_sections` and `campaign_questions`.
- [x] Set AI-generated questions to status `draft`.
- [x] Add generate action from campaign.
- [x] Add regenerate MCQ options action.
- [x] Add convert text question to MCQ action.
- [x] Add approve generated question action.
- [x] Add retry/error handling for generator.
- [x] Record model, prompt version, and generation metadata in audit payload.

### Frontend

- [x] Add **Generate Assessment** button on campaign show.
- [x] Review screen for generated sections/questions.
- [x] Admin can edit/delete generated questions before approve.
- [x] Admin can approve per question or approve all.
- [x] Show `draft` status on generated questions.
- [x] Loading/progress state while generation runs.

### Tests

- [x] Qwen generator sends campaign context to provider.
- [x] Generated MCQ has options and answer key.
- [x] Generated essay has expected rubric.
- [x] Generated questions start as `draft`.
- [x] Campaign cannot publish while generated questions remain draft.
- [x] Admin approval sets question to `approved`.
- [x] Invalid generated answer key rejected and draft not saved.

Acceptance criteria:

- Admin can generate assessment draft from role/JD/skills.
- AI output is not approved automatically.

## Phase 12: Resume PDF Screening

### Backend

- [x] Add assessment columns:
  - `resume_path`
  - `resume_text`
  - `resume_score`
  - `resume_justification`
  - `resume_payload`
- [x] Form request for resume PDF upload.
- [x] Validation:
  - file required PDF
  - PDF MIME
  - max size per configuration
- [x] Store resume on private disk.
- [x] PDF text extraction via `spatie/pdf-to-text` (Poppler `pdftotext`).
- [x] Create service `ResumeTextExtractor`.
- [x] Create agent `ResumeScreeningAgent`.
- [x] Create service `QwenResumeScreener`.
- [x] Resume screening prompt uses campaign role/JD/skills and extracted resume text.
- [x] Validate resume structured output:
  - `resume_score`
  - `summary`
  - `matched_skills`
  - `missing_skills`
  - `risk_flags`
  - `interview_probes`
  - `confidence`
  - `justification`
- [x] Prompt forbids use of protected attributes.
- [x] Add status/events for resume uploaded, extracted, and screened.

### Frontend

- [x] Resume PDF upload on candidate exam flow.
- [x] Show file name and upload state.
- [x] Show PDF validation errors.
- [x] Show resume processing status on candidate status page.
- [x] Show resume screening summary on Admin detail.

### Tests

- [x] Candidate can upload valid PDF.
- [x] Non-PDF rejected.
- [x] Resume stored on private disk.
- [x] Extracted text persisted.
- [x] Qwen resume screener receives extracted text and campaign context.
- [x] Resume screening result persisted.
- [x] Sensitive API key/file path not leaked to candidate UI.

Acceptance criteria:

- Candidate must upload resume PDF.
- Resume screening is part of assessment and feeds ranking (Phase 13).

## Phase 13: Hybrid Grading and Candidate Ranking

### Backend

- [x] Implement deterministic grader for:
  - multiple choice
  - yes/no
  - fill blank
  - matching pairs
- [x] Implement section score calculation.
- [x] Implement `mcq_score`.
- [x] Implement `essay_score`.
- [x] Implement `ranking_score`.
- [x] Implement `ranking_payload`.
- [x] Use initial formula:
  - resume score 35%
  - essay score 50%
  - MCQ score 15%
- [x] When a component is missing, normalize weights or set `needs_manual_review`.
- [x] Add ranking query for Admin dashboard.
- [x] Ensure Qwen provides explanation only, not the sole final ranking decision.

### Frontend

- [x] Admin ranking dashboard.
- [x] Show candidate ranking, score components, and status on Assessment Workstation.
- [x] Show ranking formula in use on assessment detail.
- [x] Show skill match and interview probes on assessment detail.

### Tests

- [x] MCQ deterministic grading correct.
- [x] Fill blank matches accepted answers.
- [x] Matching pairs grading correct.
- [x] Section score calculation.
- [x] Ranking formula produces expected values.
- [x] Missing component → normalized score or manual review.
- [x] Ranking dashboard Admin-only.

Acceptance criteria:

- Candidate ranking is transparent, auditable, and not a black box. Runtime scoring, section scoring, normalized missing components, and Admin ranking dashboard are implemented.

## Phase 14: Self-Correction / Critic Agent

### Backend

- [x] Create agent `AssessmentCriticAgent`.
- [x] Create service `QwenAssessmentCritic`.
- [x] Critic checks:
  - score consistent with justification
  - score components valid
  - email draft only when threshold met
  - email does not contain fake schedule/interviewer/link/salary/hiring commitment
  - resume screening does not use protected attributes
  - low confidence flagged for manual review
- [x] Persist result in `critic_payload`.
- [x] Outcomes:
  - `passed`
  - `repaired`
  - `needs_manual_review`
  - `failed`
- [x] Minor repair when critic returns valid output.
- [x] Fatal critic failure → `evaluation_failed` or `needs_manual_review` by error type.
- [x] Limit retries to control Qwen cost.

### Frontend

- [x] Show critic result in AI audit panel.
- [x] Warning when `needs_manual_review`.
- [x] Show repaired fields when present.

### Tests

- [x] Critic pass persists `critic_payload`.
- [x] Invalid email draft flagged.
- [x] Low confidence → `needs_manual_review`.
- [x] Repaired output used when valid.
- [x] Critic failure does not crash application.

Acceptance criteria:

- AI output passes a quality gate before becoming final recommendation. Critic runs after ranking calculation; risky outcomes route to `evaluated` and `needs_manual_review`.

## Phase 15: Agent Activity Timeline and Audit Panel

Implementation status as of 2026-06-12: `[x]` Timeline/audit panel on Admin assessment detail. Backend records main lifecycle events from candidate submit through email sent/failed, including admin promote/override/reject/retry, with sanitized payloads.

### Backend

- [x] Table `assessment_events` (columns: `type`, `title`, `description`, `payload`, `occurred_at`, `actor_id`; not `event` / `actor_type`).
- [x] Model `AssessmentEvent`.
- [x] Helper/service to write events.
- [x] Record events:
  - [x] candidate submitted
  - [x] resume uploaded
  - [x] resume extracted
  - [x] resume screened
  - [x] assessment queued
  - [x] deterministic grading completed
  - [x] Qwen essay evaluation completed
  - [x] critic completed
  - [x] ranking calculated
  - [x] draft email generated
  - [x] Admin approved/rejected
  - [x] Admin overrode
  - [x] email sent/failed
- [x] Event payload must not store secrets.

### Frontend

- [x] Agent activity timeline on Admin assessment detail.
- [x] AI audit panel:
  - [x] model
  - [x] provider
  - [x] threshold
  - [x] ranking formula
  - [x] score components
  - [x] risk flags
  - [x] interview probes
  - [x] critic result
  - [x] error state via failed timeline events
- [x] Timeline responsive and demo-friendly.

### Tests

- [x] Events created for important status transitions.
- [x] Event payload does not contain API key.
- [x] Admin can view timeline.
- [x] Candidate cannot view internal audit payload.

Acceptance criteria:

- Demo can show autopilot step by step. Admin assessment detail shows event timeline and AI audit panel; Candidate does not receive internal audit payload.

## Phase 16: Admin Recovery and Override

Implementation status as of 2026-06-12: `[x]` Admin recovery on assessment detail. Admin can retry failed evaluation, promote false negative to pending approval, override ranking score with reason, and reject with reason. All actions record audit events.

### Backend

- [x] Retry evaluation for status `evaluation_failed`.
- [x] Promote to interview for `evaluated` or `needs_manual_review`.
- [x] Override ranking score with reason.
- [x] Reject with reason.
- [x] Persist all reasons in `assessment_events`.
- [x] Retry dispatches new job without duplicate assessment.
- [x] Promote produces email draft if missing, or requires Admin to fill manually.

### Frontend

- [x] **Retry Evaluation** when status `evaluation_failed`.
- [x] **Promote to Interview** for reviewable statuses.
- [x] Reason modal for override/reject.
- [x] Override reason shown in audit panel.

### Tests

- [x] Retry evaluation dispatches job.
- [x] Retry rejected for invalid status.
- [x] Promote sets `pending_approval` or equivalent review state.
- [x] Override reason required.
- [x] Candidate cannot run recovery/override actions.

Acceptance criteria:

- Admin can handle false negatives and AI failures without manual DB intervention. Retry reuses the same assessment; promote/override/reject store reason in audit timeline.

## Phase 17: Secure Exam Runtime

Implementation status: `[x]` Server-owned `exam_sessions`, section timers, integrity warnings, candidate exam UX (single-section flow, exam layout, navigation/proctoring hooks), finalize into existing assessment pipeline, Pest coverage, and docs update.

### Backend

- [x] Add `exam_sessions` table/model and `ExamSessionStatus` enum.
- [x] Add `ExamSessionService` for start, save section, advance, violations, finalize.
- [x] Add candidate routes under `/candidate/campaigns/{campaign}/exam-sessions/*`.
- [x] Extend `config/assessment.php` `secure_exam` settings (fullscreen, warnings, copy/paste block, timer enforcement).
- [x] Finalize creates `assessments` + existing job chain and records `exam_integrity_summary` when warnings exist.

### Frontend

- [x] Refactor `candidate/exam.tsx` for start → section flow → finalize resume upload.
- [x] Add exam-only layout (no sidebar) via `resources/js/layouts/exam-layout.tsx`.
- [x] Add hooks: timer, navigation guard, proctoring violation reporting.

### Tests

- [x] `tests/Feature/SecureExamSessionTest.php` for session lifecycle, timer rejection, warnings, finalize.
- [x] Update `CandidateAssessmentTest` for secure exam props and submission path.

Acceptance criteria:

- Section timers and section order are enforced on the server, not only in the browser.
- Candidates cannot see all sections at once during an active attempt.
- Integrity warnings are persisted on the session and summarized on the assessment timeline after finalize.
- Submission creates the `assessments` record only through exam session finalize (resume PDF + canonical `answers_payload` snapshot).
