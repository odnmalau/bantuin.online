# Plan 011: Centralize duplicated team-capability authorization rules

> **Executor instructions**: Follow this plan step by step. Run every verification command and confirm the expected result before moving to the next step. If anything in the "STOP conditions" section occurs, stop and report — do not improvise. When done, update the status row for this plan in `plans/README.md` — unless a reviewer dispatched you and told you they maintain the index.
>
> **Drift check (run first)**: `git diff --stat 83f7e7e..HEAD -- app/Policies/TeamPolicy.php app/Policies/TeamMembershipPolicy.php app/Policies/TeamInvitationPolicy.php app/Services/TeamInvitationService.php app/Notifications/TeamInvitationNotification.php app/Http/Controllers/Settings/TeamController.php tests/Feature/TeamInvitationTest.php tests/Feature/TeamMembershipAdministrationTest.php`
> If any in-scope file changed since this plan was written, compare the "Current state" excerpts against the live code before proceeding; on a mismatch, treat it as a STOP condition.

## Status

- **Priority**: P2
- **Effort**: M
- **Risk**: MED
- **Depends on**: none
- **Category**: tech-debt
- **Planned at**: commit `83f7e7e`, 2026-07-12

## Why this matters

Who may invite/remove/resend which Team Membership roles is re-encoded in policies, `TeamInvitationService`, `TeamInvitationNotification`, and hand-rolled `can_*` props in `Settings\TeamController`. Drift causes the UI to offer actions the service rejects (or the reverse). One capability helper keeps Team administration consistent.

## Current state

- `TeamPolicy::invite` — Owner any role; Administrator → Collaborator only (`TeamPolicy.php` ~51–64).
- `TeamMembershipPolicy` — same matrix for remove (~26–38).
- `TeamInvitationService` — re-checks matrix inline (~55–57, resend ~183+).
- `Settings\TeamController` — hand-rolls `can_change_role` / `can_remove` / invitation `can_revoke` / `can_resend` while also using `$user->can(...)` for some `can` bag keys (~44–85).
- Existing tests: `tests/Feature/TeamInvitationTest.php`, `TeamMembershipAdministrationTest.php`, `TeamSettingsTest.php`.

**Vocabulary**: Owner, Administrator, Collaborator, Team Invitation, Team Membership (`CONTEXT.md`). Platform Operator bypasses in services must remain (`actorContext === 'platform_operator'`).

## Commands you will need

| Purpose | Command | Expected on success |
|---------|---------|---------------------|
| Team tests | `php artisan test --compact tests/Feature/TeamInvitationTest.php tests/Feature/TeamMembershipAdministrationTest.php tests/Feature/TeamSettingsTest.php tests/Feature/OwnershipTransferTest.php` | all pass |
| Pint | `vendor/bin/pint --dirty --format agent` | exit 0 |

## Suggested executor toolkit

- `laravel-best-practices`, `pest-testing`.

## Scope

**In scope**:
- New helper class e.g. `app/Support/TeamCapability.php` or `app/Services/TeamAuthorization.php` (use `php artisan make:class` — match repo Support/ vs Services/ conventions; prefer `app/Support/` for pure auth matrices)
- `app/Policies/TeamPolicy.php`, `TeamMembershipPolicy.php`, `TeamInvitationPolicy.php`
- `app/Services/TeamInvitationService.php`
- `app/Notifications/TeamInvitationNotification.php` (if it duplicates send gates)
- `app/Http/Controllers/Settings/TeamController.php`
- Related feature tests if assertions need updates
- `plans/README.md`

**Out of scope**:
- Campaign Invitation capabilities (plan 014)
- Renaming `/admin` routes
- Changing the actual matrix rules (Owner/Admin/Collaborator) — **preserve behavior**, only dedupe

## Git workflow

- Branch: `advisor/011-centralize-team-capabilities`
- Commit: `Centralize Team invitation and membership capability checks.`
- Do NOT push/PR unless asked.

## Steps

### Step 1: Characterization first (optional but recommended)

Run the team test files above and confirm green on current HEAD before editing.

**Verify**: all pass.

### Step 2: Extract capability helper

Create a focused class with explicit methods matching existing rules, e.g.:

- `canInvite(User $actor, Team $team, TeamMembershipRole $targetRole): bool`
- `canRemove(User $actor, TeamMembership $target): bool`
- `canResendInvitation(...)` / `canRevokeInvitation(...)`
- `canChangeRole(...)`

Implement by **moving** logic from `TeamPolicy` (single source), then call the helper from policies (policies stay the Gate entrypoint).

Platform Operator exceptions stay in **services** (as today), not in the helper, unless already encoded in policies — do not grant Platform Operator Team Member UI capabilities accidentally.

**Verify**: unit-level Pest tests for the helper matrix (Owner/Admin/Collaborator × target role) in `tests/Unit/` or feature — small and exhaustive.

### Step 3: Rewire callers

1. Policies delegate to helper.
2. `TeamInvitationService` authorization checks call helper (keep Platform Operator branch).
3. `Settings\TeamController` uses `$user->can(...)` / helper for **all** `can_*` props — delete duplicated role comparisons.

**Verify**: team feature tests → pass.

### Step 4: Pint

`vendor/bin/pint --dirty --format agent`

## Test plan

- Helper matrix unit/feature coverage for invite/remove.
- Existing invitation/membership/settings/ownership suites green (behavior preserved).
- Pattern: `TeamInvitationTest` / `TeamMembershipAdministrationTest`.

## Done criteria

- [ ] Single helper owns the Owner/Admin/Collaborator invite-remove matrix
- [ ] Policies + service + settings props call it (no duplicated `Administrator && Collaborator` chains left in those files — `rg` clean)
- [ ] Tests pass; Pint clean; README DONE

## STOP conditions

- Discover intentional divergence between UI props and service (UI stricter) — stop and report before “fixing” by always using the looser rule.
- Platform Operator paths regress — stop; re-read Support controllers before changing operator gates.

## Maintenance notes

- Reviewers: diff should be mostly moves; behavior changes are bugs.
- Campaign invite lifecycle (014) should mirror this pattern later.
