# Plan 010: Reconcile Autopilot product docs with post-Team codebase

> **Executor instructions**: Follow this plan step by step. Run every verification command and confirm the expected result before moving to the next step. If anything in the "STOP conditions" section occurs, stop and report — do not improvise. When done, update the status row for this plan in `plans/README.md` — unless a reviewer dispatched you and told you they maintain the index.
>
> **Drift check (run first)**: `git diff --stat 83f7e7e..HEAD -- docs/README.md docs/PRD_AI_ASSESSMENT_AUTOPILOT.md docs/HOW_IT_WORKS_AI_ASSESSMENT_AUTOPILOT.md docs/TASKLIST_AI_ASSESSMENT_AUTOPILOT.md CONTEXT.md docs/adr/0001-use-contextual-identities-for-team-tenancy.md`
> If any in-scope file changed since this plan was written, compare the "Current state" excerpts against the live code before proceeding; on a mismatch, treat it as a STOP condition.

## Status

- **Priority**: P2
- **Effort**: M
- **Risk**: LOW
- **Depends on**: none (nice after 009 so README can drop the warning)
- **Category**: docs
- **Planned at**: commit `83f7e7e`, 2026-07-12

## Why this matters

`docs/README.md` claims Autopilot docs “match the current codebase,” but PRD/HOW_IT_WORKS still describe global `admin`/`candidate` roles, `question_banks`, demo seeders, and list Proctoring / Anti-cheat / Multi-tenant as out of scope — all contradicted by ADR-0001, `CONTEXT.md`, secure exam delivery, and Team tenancy commits. Agents following these docs will implement the wrong auth model and chase deleted features.

## Current state

- `docs/README.md:3` — currency claim.
- `docs/PRD_AI_ASSESSMENT_AUTOPILOT.md` — `question_banks`, Admin/Candidate global roles; § out of scope still lists Proctoring, Anti-cheat, Multi-tenant (~1140–1147).
- `docs/HOW_IT_WORKS_AI_ASSESSMENT_AUTOPILOT.md` — `/admin/question-banks`, demo seed users, `admin`/`candidate` roles.
- Live model: `CONTEXT.md` glossary; `docs/adr/0001-use-contextual-identities-for-team-tenancy.md`; routes use `current-team` / `platform-operator`; question banks removed (commit `5393884`); demo seeders removed (`5055c17`).

**Vocabulary to use** (from `CONTEXT.md`): Team, Team Member, Owner, Administrator, Collaborator, Candidate, Campaign, Platform Operator, Current Team — never “organization”, “workspace”, or global “admin role”.

## Commands you will need

| Purpose | Command | Expected on success |
|---------|---------|---------------------|
| Stale claim scan | `rg -n "question_banks|role:admin|AdminUserSeeder|/admin/question-banks" docs/*.md` | no matches **or** only inside an explicit Historical section |
| Scope scan | `rg -n "Proctoring|Anti-cheat|Multi-tenant company support" docs/PRD_AI_ASSESSMENT_AUTOPILOT.md` | not listed as current out-of-scope without “delivered” note |

## Scope

**In scope**:
- `docs/README.md`
- `docs/PRD_AI_ASSESSMENT_AUTOPILOT.md`
- `docs/HOW_IT_WORKS_AI_ASSESSMENT_AUTOPILOT.md`
- `docs/TASKLIST_AI_ASSESSMENT_AUTOPILOT.md` (mark historical / update status headers at minimum)
- `plans/README.md`

**Out of scope**:
- Rewriting the entire PRD into a brand-new PRODUCT.md from scratch (optional later)
- Code/route renames away from `/admin` URL prefix (explicitly rejected as high-risk debt)
- Restoring question banks (direction plan 015 is a spike)

## Git workflow

- Branch: `advisor/010-reconcile-autopilot-docs`
- Commit: `Reconcile Autopilot docs with Team tenancy and current scope.`
- Do NOT push/PR unless asked.

## Steps

### Step 1: Fix `docs/README.md` source-of-truth ordering

1. Remove or rewrite the “match the current codebase” claim.
2. Put `CONTEXT.md` + ADR-0001 first as domain/auth source of truth.
3. Mark Autopilot PRD / HOW_IT_WORKS / TASKLIST as **historical assessment-autopilot docs** partially superseded by Team tenancy, with a short “still useful for AI evaluation pipeline behavior” note if accurate.

**Verify**: `rg -n "match the current codebase" docs/README.md` → no matches.

### Step 2: Patch PRD scope + auth/library claims

Minimum viable reconcile (prefer surgical edits over 50k rewrite):

1. Add a banner at top of PRD: **Status: partially superseded (YYYY-MM-DD). Auth/tenancy: see CONTEXT.md + ADR-0001. Question libraries: removed; campaign-local authoring only.**
2. Rewrite or strike global Admin/Candidate role sections to Team Membership + Candidate-via-Campaign Invitation.
3. Mark question bank / library sections as **removed**.
4. Out-of-scope list: remove or retitle Proctoring, Anti-cheat, Multi-tenant as **delivered** (secure exam integrity; Team tenancy). Leave true deferrals (interview scheduling, public API, etc.) intact — read the list before editing.

**Verify**: scope scan command in table.

### Step 3: Patch HOW_IT_WORKS similarly

1. Banner + remove demo seeder instructions (DatabaseSeeder is empty).
2. Replace `/admin/question-banks` flows with campaign detail authoring.
3. Replace role middleware mentions with `auth` + `current-team` / candidate routes / `platform-operator`.

**Verify**: `rg -n "question_banks|AdminUserSeeder|role:admin" docs/HOW_IT_WORKS_AI_ASSESSMENT_AUTOPILOT.md` → only historical callouts if any.

### Step 4: Tasklist header

Add the same superseded banner; do not rewrite every checkbox unless trivial. Point readers to git history for delivery status.

**Verify**: banner present near top of TASKLIST file.

## Test plan

- Docs only; use `rg` scans above as verification.

## Done criteria

- [ ] docs/README no longer claims full currency for Autopilot trio
- [ ] CONTEXT + ADR linked as auth/tenancy truth
- [ ] Question bank / global role / demo seeder instructions not presented as current
- [ ] Proctoring/tenancy not listed as undelivered out-of-scope
- [ ] README plan status DONE

## STOP conditions

- Operator wants Autopilot docs deleted entirely vs reconciled — ask; default is reconcile with banners + surgical fixes.
- Conflict with a newer PRODUCT.md that appears after this plan’s SHA — stop and merge carefully.

## Maintenance notes

- Empty `resources/js/pages/admin/question-banks/` dirs are code debt (cleanup can be a tiny follow-up commit outside this plan if operator allows).
- Reviewers: skim banners and scope section only if time-boxed.
