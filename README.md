# Bantuin Online

### AI Hiring Assessment Autopilot powered by Qwen Cloud

[![Tests](https://github.com/odnmalau/bantuin.online/actions/workflows/tests.yml/badge.svg?branch=master)](https://github.com/odnmalau/bantuin.online/actions/workflows/tests.yml)
[![Lint](https://github.com/odnmalau/bantuin.online/actions/workflows/lint.yml/badge.svg?branch=master)](https://github.com/odnmalau/bantuin.online/actions/workflows/lint.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE.md)

- **Hackathon:** [Global AI Hackathon Series with Qwen Cloud](https://qwencloud-hackathon.devpost.com/)
- **Track:** Track 4 — Autopilot Agent
- **Live application:** [app.bantuin.online](https://app.bantuin.online)

Bantuin Online is a production-oriented hiring assessment platform that turns a
role description into a complete, auditable technical screening workflow. Qwen
Cloud agents help a hiring team design an assessment, screen a candidate's
resume, evaluate open-ended answers, challenge the result with a critic pass,
and prepare an interview email. Humans remain in control of every consequential
decision: generated questions are drafts until approved, and no interview email
is sent until a hiring team member reviews the evidence and explicitly approves
the outcome.

<!-- Add the public three-minute demo video URL near the live application link before the final Devpost submission. -->

## The problem

Technical hiring is often fragmented across documents, form builders,
spreadsheets, manual scoring, and email. That fragmentation creates three
practical problems:

- assessment quality varies between reviewers and campaigns;
- open-ended answers and resumes take significant time to review consistently;
- automated screening can become opaque or unsafe when there is no audit trail
  or human checkpoint.

Bantuin Online combines these steps into one workflow while treating AI output
as reviewable evidence rather than an autonomous hiring decision.

## What the autopilot does

### 1. Designs role-specific assessments

A hiring team supplies the role title, seniority, job description, required
skills, language, and passing threshold. A Qwen reasoning agent designs
practical questions and observable rubrics. A separate structured-output agent
converts that reasoning into validated sections and questions.

Every generated question is stored as a **draft**. A human must review, edit,
and approve the questions before the campaign can be published.

### 2. Runs a secure candidate workflow

Candidates enter through campaign-specific invitations, upload a PDF resume,
and complete a section-based assessment. The exam enforces server-owned section
timers and can record fullscreen exits, tab changes, and blocked copy/paste
events. Submitted questions and rubrics are snapshotted with the answers so
later campaign edits cannot change the evidence that was evaluated.

### 3. Screens and evaluates with Qwen Cloud

Background jobs extract bounded text from the resume, screen it against the role
context, and evaluate every answer against its question-specific rubric. The
evaluation pipeline separates advanced reasoning from structured output:

1. a reasoning model produces a detailed rubric-grounded report;
2. a structured agent converts it into a strictly validated result;
3. the backend rejects missing, duplicate, or unexpected question IDs and can
   request a bounded repair;
4. the backend calculates the weighted score and review gates;
5. a second Qwen reasoner and structured critic check consistency, confidence,
   protected-attribute handling, and email safety.

### 4. Stops at the human decision boundary

Passing, borderline, low-confidence, and critic-flagged results are routed to a
team-scoped review workstation. An authorized team member can inspect the resume
analysis, per-question evidence, section scores, ranking breakdown, critic
findings, and the complete event timeline before choosing to:

- approve and edit the interview email;
- promote a false negative with a reason;
- override a ranking score with an audit reason;
- reject the candidate; or
- retry a failed AI evaluation or email delivery.

Only an explicit approval queues the interview email.

## Architecture

![Bantuin Online architecture: users access an Inertia React application through Cloudflare, Laravel and queue workers run on Alibaba Cloud, and a Qwen reasoner-structurer-critic pipeline stops at human checkpoints.](docs/architecture.svg)

[Open the full-size architecture diagram](docs/architecture.svg).

The production container topology is defined in
[`compose.production.yaml`](compose.production.yaml). It separates the web
application, queue worker, migration command, and PostgreSQL database, with
health checks and persistent volumes. Images are verified in CI and published
to GHCR with provenance metadata and an SBOM by
[`docker-publish.yml`](.github/workflows/docker-publish.yml).

<!-- Add the direct repository link to the Alibaba Cloud deployment proof here before the final Devpost submission. -->

## Why the Qwen integration is technically different

The application does not treat Qwen as a single chat-completion call. It extends
the Laravel AI SDK with a custom provider and an OpenAI-compatible DashScope
gateway, then composes specialized agents around explicit trust boundaries.

- **Advanced reasoning:** `qwen3.7-max` is used for assessment design,
  answer evaluation, and critic reasoning.
- **Structured output:** `qwen3.7-plus` converts reasoning reports into
  schema-constrained application data.
- **Prompt-injection isolation:** campaign content, resumes, answers, and prior
  model output are marked as untrusted data and never treated as instructions.
- **Backend validation:** scoring, identifiers, thresholds, email presence, and
  allowed state transitions are enforced outside the model.
- **Bounded cost and failure behavior:** transport retries, repair attempts,
  HTTP timeouts, queue timeouts, and stale-work recovery are configurable.
- **Critic pass:** a second agent challenges the evaluation package before it
  can reach an approval state.
- **Auditability:** model metadata, prompt versions, review reasons, manual
  actions, and delivery outcomes are recorded without exposing API keys.

Representative implementation files:

- [`QwenProvider.php`](app/Ai/Providers/QwenProvider.php)
- [`QwenGateway.php`](app/Ai/Gateway/QwenGateway.php)
- [`AssessmentGenerationReasonerAgent.php`](app/Ai/Agents/AssessmentGenerationReasonerAgent.php)
- [`AssessmentEvaluationReasonerAgent.php`](app/Ai/Agents/AssessmentEvaluationReasonerAgent.php)
- [`AssessmentCriticReasonerAgent.php`](app/Ai/Agents/AssessmentCriticReasonerAgent.php)
- [`AssessmentEvaluationPipeline.php`](app/Services/AssessmentEvaluationPipeline.php)
- [`AssessmentExternalWorkCoordinator.php`](app/Services/AssessmentExternalWorkCoordinator.php)

## Safety, privacy, and production controls

- Team tenancy scopes campaigns, assessments, rankings, and review actions.
- Authorization uses policies, current-team middleware, and contextual team
  membership rather than a global administrator flag.
- Platform support authority is separated from candidate data and hiring
  decisions.
- Resume PDFs are stored in private S3-compatible object storage and only
  bounded extracted text is sent for screening.
- Protected attributes are explicitly excluded from resume and assessment
  decisions.
- AI-generated questions cannot be published without human approval.
- Low confidence, borderline scores, truncated input, critic concerns, and AI
  failures route to human review.
- External AI calls happen outside database transactions; claim/finalize
  boundaries and row locks prevent duplicate or stale state transitions.
- Queue jobs use explicit retry, backoff, timeout, uniqueness, and recovery
  behavior.
- Invitation tokens are hashed at rest; queued campaign invitation secrets are
  encrypted.
- Demo accounts are protected from profile and team-lifecycle mutations.

## Judge testing guide

No password or private credential is required. The login page provides
**Demo Admin** and **Demo Candidate** buttons.

### Fast admin walkthrough

1. Open [app.bantuin.online](https://app.bantuin.online) and choose
   **Demo Admin**.
2. Open **Campaigns** and create a campaign with a role, job description,
   required skills, and passing threshold.
3. Choose **Generate assessment with AI**. Inspect the Qwen-generated draft
   sections, questions, and rubrics.
4. Edit or approve the draft questions, then publish the campaign.
5. Invite `demo-candidate@bantuin.online`. Disable email delivery and copy the
   latest invite link shown by the application.

### End-to-end candidate and review walkthrough

1. Open the copied invite link in a private browser window.
2. Choose **Demo Candidate** to redeem the invitation.
3. Upload a PDF resume, start the secure exam, answer each section, and submit.
4. Return to the Demo Admin session and open **Rankings**. Processing statuses
   update automatically while the queued Qwen pipeline runs.
5. Open the candidate assessment to inspect resume evidence, question scores,
   confidence, ranking, critic findings, and the audit timeline.
6. Review the proposed email and approve, override, promote, or reject the
   result. The interview email job is only dispatched after approval.

The public demo is shared. If an existing campaign or invitation has already
been used, create a new campaign with a distinct title.

## Technology stack

| Layer              | Technology                                                 |
| ------------------ | ---------------------------------------------------------- |
| Backend            | PHP 8.4/8.5, Laravel 13, Laravel AI SDK                    |
| AI                 | Qwen Cloud through the DashScope OpenAI-compatible API     |
| Frontend           | Inertia.js 3, React 19, TypeScript, Tailwind CSS 4         |
| Database and queue | PostgreSQL 17, Laravel database queue                      |
| Authentication     | Google OAuth with Laravel Socialite, protected demo access |
| Resume processing  | Private R2 storage, Poppler `pdftotext`                    |
| Email              | Laravel Mail and Resend                                    |
| Deployment         | Docker, FrankenPHP, Alibaba Cloud compute, Cloudflare edge |
| Quality            | Pest 4, PHPUnit 12, Pint, ESLint, Prettier, TypeScript     |

## Local installation

### Requirements

- PHP 8.4 or 8.5 with Composer 2
- Node.js 22 and pnpm 11
- PostgreSQL
- `poppler-utils` for PDF text extraction
- S3-compatible private object storage for candidate resumes
- Qwen Cloud API credentials

On Debian or Ubuntu, install Poppler with:

```bash
sudo apt-get install poppler-utils
```

### Setup

Clone the repository and run:

```bash
composer setup
```

The setup command installs PHP and JavaScript dependencies, copies
`.env.example` when needed, generates an application key, runs migrations, and
builds frontend assets.

Configure these values in `.env` before exercising the full workflow:

```dotenv
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5433
DB_DATABASE=HirePilot
DB_USERNAME=postgres
DB_PASSWORD=

QWEN_API_KEY=
QWEN_BASE_URL=https://dashscope-intl.aliyuncs.com/compatible-mode/v1
QWEN_MODEL=qwen3.7-plus
QWEN_REASONER_MODEL=qwen3.7-max
QWEN_STRUCTURED_MODEL=qwen3.7-plus

R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
R2_PRIVATE_BUCKET=
R2_ENDPOINT=

RESEND_API_KEY=
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=${APP_URL}/auth/google/callback
```

Never commit `.env` or production secret values.

### Development

Start the Laravel server, queue worker, log stream, and Vite development server:

```bash
composer dev
```

The queue worker is required for resume screening, assessment evaluation, and
email delivery.

## Verification

Run the backend test and PHP formatting checks:

```bash
composer test
```

Run the complete CI quality gate:

```bash
composer ci:check
```

Useful focused checks:

```bash
php artisan test --compact
pnpm run lint:check
pnpm run format:check
pnpm run types:check
pnpm run build
```

The latest local audit completed 499 tests: 494 passed and five optional local
PostgreSQL integration cases were skipped because the integration database was
not running. The GitHub Actions workflow runs the suite on PHP 8.4 and 8.5 and
executes the PostgreSQL migration coverage against a real PostgreSQL service.

## Built during the hackathon

Development began during the hackathon submission period. The project evolved
from an initial Laravel application into the current end-to-end autopilot,
including:

- a custom Qwen Cloud provider and gateway for the Laravel AI SDK;
- reasoner/structurer pipelines for generation, evaluation, and criticism;
- secure campaign-specific candidate exams and private resume handling;
- team tenancy, contextual authorization, and platform support boundaries;
- deterministic ranking, human review, recovery, and audit timelines;
- production containers, health checks, CI verification, and Alibaba Cloud
  deployment support; and
- protected, credential-free demo access for judges.

Git history is retained to make the delivery timeline and technical evolution
reviewable.

## Documentation

- [Architecture diagram](docs/architecture.svg)
- [How the assessment autopilot works](docs/HOW_IT_WORKS_AI_ASSESSMENT_AUTOPILOT.md)
- [Product requirements](docs/PRD_AI_ASSESSMENT_AUTOPILOT.md)
- [Team tenancy architecture decision](docs/adr/0001-use-contextual-identities-for-team-tenancy.md)

## License

Bantuin Online is open source software available under the
[MIT License](LICENSE.md).
