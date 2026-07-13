# Plan 015: Spike Team-scoped question reuse (library vs campaign clone)

> **Executor instructions**: Follow this plan step by step. Run every verification command and confirm the expected result before moving to the next step. If anything in the "STOP conditions" section occurs, stop and report — do not improvise. When done, update the status row for this plan in `plans/README.md` — unless a reviewer dispatched you and told you they maintain the index.
>
> **Drift check (run first)**: `git diff --stat 83f7e7e..HEAD -- app/Services/AuthoredQuestion.php app/Models/CampaignQuestion.php routes/web.php docs/PRD_AI_ASSESSMENT_AUTOPILOT.md CONTEXT.md docs/adr`
> If any in-scope file changed since this plan was written, compare the "Current state" excerpts against the live code before proceeding; on a mismatch, treat it as a STOP condition.

## Status

- **Priority**: P3
- **Effort**: L (spike / design — **not** a full rebuild)
- **Risk**: MED
- **Depends on**: plans/010-reconcile-autopilot-docs.md (so spike doesn’t fight stale PRD library sections)
- **Category**: direction
- **Planned at**: commit `83f7e7e`, 2026-07-12

## Why this matters

The Autopilot PRD still requires reusable question libraries with copy/snapshot import, but commit `5393884` removed the global QuestionBank stack. Authoring is campaign-local (`AuthoredQuestion`, campaign question routes, AI generate on campaign). Multi-campaign Teams re-author or re-generate the same packs. Teams now provide the tenancy boundary old global banks lacked — reuse should be **Team-scoped**, never cross-Team.

This plan is a **design/spike plan**: investigate, prototype lightly if useful, define the API, list open questions. Do **not** rebuild the full pre-Team bank product in this plan.

## Current state

- No `QuestionBank` / `BankQuestion` models under `app/Models/`.
- Empty leftover dirs: `resources/js/pages/admin/question-banks/` (optional cleanup while spiking).
- Snapshot semantics live in campaign questions + `AuthoredQuestion` validation.
- ADR-0001 / `CONTEXT.md`: Campaign owned by exactly one Team; Authored Question is normalized content before bank **or** campaign question — glossary still mentions bank question as a term; spike must decide if that term returns under Team ownership.

## Commands you will need

| Purpose | Command | Expected on success |
|---------|---------|---------------------|
| Explore | read-only `rg` / `git show 5393884 --stat` | understand removal blast radius |
| Optional prototype tests | only if you add a vertical slice | pass |

## Scope

**In scope**:
- An ADR under `docs/adr/0002-...md` containing the spike evidence and recommendation
- Optional tiny prototype behind a feature flag **only if** it fits <1 day and proves API shape — otherwise docs-only
- Optional delete of empty `question-banks` / `assessment-settings` page dirs
- `plans/README.md`

**Out of scope**:
- Full Team library CRUD UI + import wizard + AI bank generator revival
- Cross-Team sharing
- Changing snapshot immutability of live Campaign Questions without an ADR

## Git workflow

- Branch: `advisor/015-team-question-reuse-spike`
- Commit: `Document Team-scoped question reuse options.`
- Do NOT push/PR unless asked.

## Steps

### Step 1: Reconstruct why banks were removed

```bash
git show 5393884 --stat
git log --oneline -5 5393884
```

Summarize motivations in the spike notes (consolidation into campaigns, etc.).

**Verify**: notes cite the commit hash and list deleted surfaces.

### Step 2: Compare two options with evidence

Evaluate at least:

**A. Team-owned reusable packs** (library 2.0): Team-scoped tables, snapshot import into Campaign sections via existing `AuthoredQuestion` pipeline.

**B. Duplicate Campaign as draft**: clone sections/questions into a new draft Campaign; no library entity.

For each: effort (coarse), tenancy risks, impact on AI generate, publish workflow, and how Candidate/exam snapshots stay immutable.

Recommend **one** default with rationale grounded in HirePilot’s Current Team workspace.

**Verify**: recommendation section exists with explicit “not global banks” constraint.

### Step 3: Open questions + non-goals

List open questions for the maintainer (e.g. versioning, editing pack after import, permissions: Collaborator vs Administrator). Non-goals: public marketplace, cross-Team templates.

**Verify**: `git status --short -- docs/adr plans/README.md` → exactly the new ADR and `plans/README.md`; README status is DONE because the spike is complete even without implementation.

### Step 4 (optional): empty dir cleanup

Delete empty `resources/js/pages/admin/question-banks/` (and nested empty `questions/`) and `assessment-settings/` if still empty. Keep negative route tests in `RoleAccessTest` if present.

## Test plan

- Spike is docs/ADR-first; tests only if a prototype lands.
- If prototype: one feature test proving Team isolation on pack read.

## Done criteria

- [ ] Written ADR with recommendation A vs B
- [ ] Explicit rejection of pre-Team global banks
- [ ] Open questions listed
- [ ] No full library product shipped under this plan’s DONE status
- [ ] README DONE (or DONE — docs only)

## STOP conditions

- Operator orders full library rebuild inside this plan — refuse expansion; split a new build plan after ADR acceptance.
- Discovery that campaign clone would break ranking/invitation assumptions badly — document and recommend packs instead; do not force clone.

## Maintenance notes

- After ADR acceptance, create a separate build plan using the next unused canonical number (currently 023) and add it to `plans/README.md`; do not treat 015 DONE as “libraries shipped.”
- Reviewers: focus on tenancy boundary and snapshot immutability.
