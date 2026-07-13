# Use Team-owned Question Packs for reusable questions

## Status

Proposed on 2026-07-13. This ADR records the result of a design spike; it does not ship a Question Pack product.

## Context

Questions are currently authored or generated inside a Campaign. A Campaign belongs to exactly one Team, and `CampaignPolicy` limits access to an active membership in the Current Team. `AuthoredQuestion` normalizes and validates question content before `CampaignQuestionController` persists a Campaign Question. When a Candidate submits, `AssessmentSubmissionBuilder` copies the Campaign Question fields into `assessments.answers_payload`, so later authoring changes do not rewrite the Candidate's assessment snapshot.

Commit `5393884` consolidated assessment management into Campaigns and removed the pre-Team question-library implementation. The deleted surface included:

- global `QuestionBank` and `BankQuestion` models, factories, migrations, and CRUD pages;
- Question Bank and Bank Question controllers, requests, and routes;
- the Campaign Question import controller and `source_bank_question_id` relationship;
- the Question Bank AI agent and generator service;
- Question Bank CRUD, generation, import-copy, and mutation tests.

That implementation was creator/global scoped rather than Team scoped. Its removal also reduced duplicate authoring, AI-generation, settings, and navigation paths in favor of Campaign detail. Reuse remains valuable for Teams that run several Campaigns, but restoring those deleted surfaces unchanged would reintroduce the tenancy and product-shape problems that the consolidation removed.

## Options considered

### A. Team-owned Question Packs

A Question Pack is reusable question content owned by one Team. A later implementation would minimally use a `question_packs.team_id` boundary and Pack Questions with the same normalized content fields as Campaign Questions. Import would select Pack Questions and an explicit target Campaign section, validate the content through the existing authored-question contract, and create independent draft Campaign Questions.

- **Effort:** Medium to large. It needs Team-scoped persistence, policies, CRUD, import, and isolation tests. Pack-specific AI generation and a polished import UI can be separate follow-ups.
- **Tenancy:** The pack and destination Campaign must both belong to the authenticated user's Current Team. Queries must start from that Team rather than accepting an unscoped Pack Question identifier. There is no cross-Team import path.
- **AI generation:** Existing Campaign generation remains unchanged. Pack generation may be added later, but should reuse the same normalized output validation instead of reviving the deleted Question Bank generator stack.
- **Publish workflow:** Imported questions enter `draft`, even if the Pack Question was previously reviewed. Team Members can adapt and approve the Campaign copy using the existing Campaign review and publish gate.
- **Snapshots:** Import is copy-only. Editing or deleting a pack never updates an imported Campaign Question. Candidate submission continues to copy Campaign Question content into `answers_payload`, preserving the existing second snapshot boundary.

This option directly solves question reuse without copying role descriptions, ranking weights, invitations, or other Campaign state.

### B. Duplicate a Campaign as draft

A definition-only clone would create a new draft Campaign in the same Team and copy its sections and questions. It must assign new identifiers and reset question review state as required by the current publish workflow.

- **Effort:** Small to medium for a safe definition clone because the Campaign, section, and question schemas already exist. Transactional deep-copy behavior and tests are still required.
- **Tenancy:** The source and destination must remain in the Current Team. A clone must never be a mechanism for moving definitions between Teams.
- **AI generation:** The clone can be edited or regenerated through the existing Campaign flow, but it also carries source role context and may encourage stale job descriptions or generation assumptions.
- **Publish workflow:** The destination must always be `draft` with `activated_at` cleared. It must pass the normal question review and publish checks independently.
- **Snapshots:** Copy only Campaign definition data. Never copy Campaign Invitations, Exam Sessions, Assessments, Candidate answers, ranking results, activation state, or AI-generation audit history. Existing Candidate snapshots remain attached to the source Campaign.

This option is useful when a Team wants to repeat an entire hiring setup. It is too coarse when the Team wants a shared set of questions across otherwise different roles, scoring configurations, or section layouts. Accidentally cloning participation or result records would also violate invitation uniqueness and the one-assessment-per-Candidate-per-Campaign assumptions.

## Decision

Use **Team-owned Question Packs** as the default direction for reusable questions. Keep definition-only Campaign duplication as a separate convenience operation for whole-Campaign reuse, not as the question-library abstraction.

The required constraint is **not global banks**. Every Question Pack is owned by exactly one Team for its lifetime, all reads and writes derive authority from the Current Team, and no public or cross-Team lookup/import API exists. The pre-Team `QuestionBank` and `BankQuestion` design must not be restored unchanged.

Use the terms **Question Pack** and **Pack Question** rather than reviving **Question Bank** and **Bank Question**. If this ADR is accepted and implemented, update the `CONTEXT.md` Authored Question definition to say that normalized content may become a Pack Question or Campaign Question.

## Proposed API boundary

The first build should remain narrow:

- Team-scoped Question Pack list, detail, create, update, and delete operations under the existing authenticated Current Team route group.
- Pack Question create and update operations that use `AuthoredQuestion` as the shared normalization and validation boundary.
- `POST /admin/campaigns/{campaign}/questions/import` with selected Pack Question identifiers and an explicit destination Campaign section.
- A transactional import that rechecks Team ownership for the Campaign, Pack, Pack Questions, and destination section, then creates draft Campaign Question copies.
- One required feature test proving a Team Member cannot read or import another Team's pack.

The import may retain nullable provenance for diagnostics, but behavior must not depend on that relationship and source deletion must not delete Campaign Questions. There is no synchronization, linked editing, or update propagation after import.

## Consequences

- Teams can curate reusable content independently from Campaign-specific role, ranking, and Candidate state.
- Existing Campaign AI generation, review, publishing, exam delivery, and assessment snapshot behavior remain unchanged.
- Question content has two explicit copy boundaries: Question Pack to draft Campaign Question, then Campaign Question to Candidate `answers_payload` at submission.
- The product gains another Team-owned aggregate and therefore needs explicit policies, Current Team query scoping, deactivated-Team behavior, and database foreign keys.
- A future full build is larger than a clone button, but it addresses the actual reuse need without coupling unrelated Campaign settings.

## Open questions

- Should all Owner, Administrator, and Collaborator memberships manage Question Packs, matching current Campaign permissions, or should Pack mutation be limited to Owner and Administrator?
- Does a Pack contain only questions imported into an existing section, or should it preserve reusable section structure, duration, scoring mode, and weight?
- Is simple mutable content sufficient, or do Teams need Pack versions and an import record identifying the exact source revision?
- Should imported questions always require Campaign approval, or may a Team explicitly trust reviewed Pack Questions while still creating independent snapshots?
- Should provenance survive Pack or Pack Question deletion as denormalized metadata, a nullable foreign key, or not at all?
- Is AI generation for Packs valuable enough for the first build, or should Teams generate in a Campaign and explicitly save selected questions to a Pack?
- Should a definition-only Campaign clone reset every copied question to `draft`, or preserve approval while still requiring an explicit publish action?

## Non-goals

- Public Question Pack marketplace or discovery.
- Cross-Team sharing, copying, transfer, or global templates.
- Automatic synchronization from Pack Questions into Campaign Questions.
- Rebuilding the full deleted Question Bank CRUD, AI generator, and import wizard in this spike.
- Changing the mutability rules of live Campaign definitions or Candidate assessment snapshots.
- Copying Campaign Invitations, Exam Sessions, Assessments, Candidate answers, rankings, or audit history during Campaign duplication.
