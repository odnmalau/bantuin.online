# Plan 014: Add Campaign Invitation revoke and resend lifecycle

> **Executor instructions**: Follow this plan step by step. Run every verification command and confirm the expected result before moving to the next step. If anything in the "STOP conditions" section occurs, stop and report — do not improvise. When done, update the status row for this plan in `plans/README.md` — unless a reviewer dispatched you and told you they maintain the index.
>
> **Drift check (run first)**: `git diff --stat 83f7e7e..HEAD -- app/Services/CampaignInvitationService.php app/Services/TeamInvitationService.php app/Http/Controllers/Admin/CampaignInvitationController.php routes/web.php resources/js/pages/admin/campaigns/show.tsx tests/Feature/CampaignInvitationTest.php app/Jobs/SendCampaignExamInvitationEmail.php`
> If any in-scope file changed since this plan was written, compare the "Current state" excerpts against the live code before proceeding; on a mismatch, treat it as a STOP condition.

## Status

- **Priority**: P2
- **Effort**: M
- **Risk**: LOW
- **Depends on**: plans/001-encrypt-campaign-invite-job-tokens.md (resend must keep encrypted job tokens)
- **Category**: direction
- **Planned at**: commit `83f7e7e`, 2026-07-12

## Why this matters

Team Invitations support revoke/resend with UI and encrypted notifications. Campaign Invitations only have create (`POST admin/campaigns/{campaign}/invitations`); status enum already includes `Revoked`, but `CampaignInvitationService` has no revoke/resend API. Hiring Teams must hack ops by re-POSTing create. Parity unblocks reliable candidate funnel administration next to collaboration surfaces.

## Current state

- Team exemplar: `TeamInvitationService::revoke` / `resend`; routes in `web.php` for destroy/resend; UI in `resources/js/pages/settings/team.tsx`.
- Campaign: `CampaignInvitationController` store only; `CampaignInvitationStatus::Revoked` exists; create rotates token via service.
- Job `SendCampaignExamInvitationEmail` must remain `ShouldBeEncrypted` after plan 001.
- Vocabulary: **Campaign Invitation** (`CONTEXT.md`).

## Commands you will need

| Purpose | Command | Expected on success |
|---------|---------|---------------------|
| Routes | `php artisan route:list --name=admin.campaigns.invitations` | shows store + new destroy/resend |
| Tests | `php artisan test --compact tests/Feature/CampaignInvitationTest.php` | all pass |
| Types | `pnpm run types:check` | exit 0 |
| Wayfinder | (types:check already regenerates) | exit 0 |
| Pint | `vendor/bin/pint --dirty --format agent` | exit 0 |

## Suggested executor toolkit

- `laravel-best-practices`, `wayfinder-development`, `inertia-react-development`, `pest-testing`, `tailwindcss-development`.

## Scope

**In scope**:
- `app/Services/CampaignInvitationService.php` (revoke + resend)
- `app/Http/Controllers/Admin/CampaignInvitationController.php`
- Policies / Form requests if campaign invitation authorize patterns exist — mirror CampaignPolicy gates used by store
- `routes/web.php` admin campaign invitation routes
- Campaign show invitations UI (`resources/js/pages/admin/campaigns/show.tsx` invitations section only — avoid drive-by refactors of the 2500-line file)
- `tests/Feature/CampaignInvitationTest.php`
- `plans/README.md`

**Out of scope**:
- Bulk email ingest / multiline invite (explicit follow-up)
- Team Activity recording unless Campaign invitations already record activity on create — match existing create behavior only
- Rewriting the whole campaign show page

## Git workflow

- Branch: `advisor/014-campaign-invitation-lifecycle`
- Commit: `Add revoke and resend for Campaign Invitations.`
- Do NOT push/PR unless asked.

## Steps

### Step 1: Service methods

Mirror `TeamInvitationService` carefully, adapted to Campaign rules:

**revoke(pending invitation)**:
- Only `Pending` → `Revoked`
- Do not erase accepted candidacy history
- Idempotent or 422 on non-pending — match Team behavior

**resend(pending invitation)**:
- Rotate token (existing `CampaignInvitation::issueToken` / service pattern)
- Dispatch `SendCampaignExamInvitationEmail` with new plaintext token (**encrypted job**)
- Refresh expiry/sent_at as Team resend does analogously

Authorize via existing campaign update/invite abilities (read how `store` authorizes).

**Verify**: unit/feature coverage in CampaignInvitationTest for revoke + resend.

### Step 2: Routes + controller

Add:

- `DELETE admin/campaigns/{campaign}/invitations/{invitation}` (or nested name matching resource style already used)
- `POST .../invitations/{invitation}/resend`

Use Wayfinder-friendly controller methods `destroy` / `resend`. Ensure invitation belongs to campaign (scope binding or abort 404).

**Verify**: `php artisan route:list --name=admin.campaigns.invitations` shows new routes.

### Step 3: UI on campaign show

In the invitations deferred section only: add Revoke / Resend actions for pending rows (match settings/team invitation button patterns lightly). Use Wayfinder actions from `@/actions/...`.

**Verify**: types:check exit 0.

### Step 4: Tests + Pint

Feature tests:

1. Owner/collaborator can revoke pending → status revoked; old token URL fails.
2. Resend rotates token; mail/job dispatched; old token invalid.
3. Cannot revoke accepted invitation.
4. Authorization: user without campaign permission cannot resend.

**Verify**: Pest + Pint.

## Test plan

- Pattern: `tests/Feature/CampaignInvitationTest.php` and Team invitation tests for behavioral reference (`TeamInvitationTest.php`).

## Done criteria

- [x] Service revoke + resend exist and are authorized
- [x] Routes + UI for pending invitations
- [x] Resend uses encrypted job (plan 001)
- [x] Tests + types + pint pass
- [x] README DONE

## STOP conditions

- Accepting a Campaign Invitation creates membership-like constraints that revoke must also unwind — stop and read `CampaignInvitationService` accept path before inventing cascading deletes.
- Bulk invite requested mid-flight — defer; do not expand this plan.

## Maintenance notes

- Reviewers: token rotation security + campaign ownership scoping.
- Optional follow-up: multiline bulk emails onto the same `create` service.
