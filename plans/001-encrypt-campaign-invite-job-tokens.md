# Plan 001: Encrypt campaign invite tokens in queued jobs

> **Executor instructions**: Follow this plan step by step. Run every verification command and confirm the expected result before moving to the next step. If anything in the "STOP conditions" section occurs, stop and report — do not improvise. When done, update the status row for this plan in `plans/README.md` — unless a reviewer dispatched you and told you they maintain the index.
>
> **Drift check (run first)**: `git diff --stat 83f7e7e..HEAD -- app/Jobs/SendCampaignExamInvitationEmail.php app/Notifications/TeamInvitationNotification.php app/Notifications/OwnershipTransferNotification.php tests/Feature/CampaignInvitationTest.php`
> If any in-scope file changed since this plan was written, compare the "Current state" excerpts against the live code before proceeding; on a mismatch, treat it as a STOP condition.

## Status

- **Priority**: P1
- **Effort**: S
- **Risk**: LOW
- **Depends on**: none
- **Category**: security
- **Planned at**: commit `83f7e7e`, 2026-07-12

## Why this matters

Campaign exam invitation emails are sent by a queued job that serializes the plaintext invite token. The default queue driver is the database (`QUEUE_CONNECTION=database` in `.env.example`), so plaintext tokens land in `jobs` / `failed_jobs`. Team Invitation and Ownership Transfer notifications already implement `ShouldBeEncrypted` for the same class of secret. Anyone with DB/queue admin access can redeem pending Campaign Invitations and sit exams as the invited email after Google sign-in.

## Current state

- `app/Jobs/SendCampaignExamInvitationEmail.php` — queued mailer; holds `public string $plainToken`; does **not** implement `ShouldBeEncrypted`:

```php
class SendCampaignExamInvitationEmail implements ShouldQueue
{
    use Queueable;
    // ...
    public function __construct(
        public CampaignInvitation $invitation,
        public string $plainToken,
    ) {
```

- `app/Notifications/TeamInvitationNotification.php` — exemplar pattern to copy:

```php
class TeamInvitationNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
```

- Same pattern: `app/Notifications/OwnershipTransferNotification.php` implements `ShouldBeEncrypted, ShouldQueue`.
- DB stores only `token_hash` (`CampaignInvitationService` hashes with SHA-256); the queue is the plaintext leak.
- Vocabulary (from `CONTEXT.md`): use **Campaign Invitation**, not “invite” / “campaign invitation link” in commit messages and test names.

## Commands you will need

| Purpose | Command | Expected on success |
|---------|---------|---------------------|
| Tests (scoped) | `php artisan test --compact tests/Feature/CampaignInvitationTest.php` | all pass |
| Pint | `vendor/bin/pint --dirty --format agent` | exit 0 |
| Full related | `php artisan test --compact --filter=CampaignInvitation` | all pass |

## Suggested executor toolkit

- Activate `laravel-best-practices` and `pest-testing` skills if available.
- Prefer Laravel Boost `search-docs` for `ShouldBeEncrypted` / queue encryption if unsure of the import.

## Scope

**In scope**:
- `app/Jobs/SendCampaignExamInvitationEmail.php`
- `tests/Feature/CampaignInvitationTest.php` (add assertion that the job implements encryption / is encrypted when queued — see Test plan)
- `plans/README.md` (status row only)

**Out of scope**:
- Changing how tokens are generated or hashed
- Reworking Team Invitation / Ownership Transfer notifications (already correct)
- Rotating tokens in production DB (document in Maintenance; do not invent a migration unless the operator asks)
- Any frontend changes

## Git workflow

- Branch: `advisor/001-encrypt-campaign-invite-job-tokens`
- Commit message style (from recent history): imperative sentences, e.g. `Encrypt campaign exam invitation tokens on the queue.`
- Do NOT push or open a PR unless the operator instructed it.

## Steps

### Step 1: Implement `ShouldBeEncrypted` on the job

In `app/Jobs/SendCampaignExamInvitationEmail.php`:

1. Add `use Illuminate\Contracts\Queue\ShouldBeEncrypted;`
2. Change the class declaration to:
   `class SendCampaignExamInvitationEmail implements ShouldBeEncrypted, ShouldQueue`
3. Keep constructor signature unchanged.

**Verify**: `rg -n "ShouldBeEncrypted" app/Jobs/SendCampaignExamInvitationEmail.php` → shows the import and `implements ShouldBeEncrypted, ShouldQueue`.

### Step 2: Reject stale queued tokens before mail delivery

Mirror `TeamInvitationNotification`’s freshness check. After refreshing the invitation and confirming Team/status validity, compare:

`hash('sha256', $this->plainToken)` against `$invitation->token_hash` using `hash_equals`.

If they do not match: return without sending; do not change `sent_at`; log only invitation/Team ids + a stable reason — **never** log the token or hash. Place the check before building the invite URL or mailable.

Exemplar: `app/Notifications/TeamInvitationNotification.php` (hash_equals against fresh row).

**Verify**: `rg -n "hash_equals" app/Jobs/SendCampaignExamInvitationEmail.php` → hit inside `handle()`.

### Step 3: Regression tests

In `tests/Feature/CampaignInvitationTest.php`, modeled on existing job tests (`createWithPlainToken`):

1. Job implements `ShouldBeEncrypted` (`toBeInstanceOf`).
2. Stale token: create invitation + job with token A; rotate to token B; handle job A → no mail, `sent_at` unchanged; handle job with token B → one mail.
3. Existing deactivated-Team / non-pending no-ops remain green.

Use `Mail::fake()`. Never print token values.

**Verify**: `php artisan test --compact tests/Feature/CampaignInvitationTest.php` → all pass.

### Step 4: Format PHP

Run `vendor/bin/pint --dirty --format agent`.

**Verify**: exit 0; `git diff --name-only` shows only in-scope files (plus possibly pint-touched in-scope PHP).

## Test plan

- Job implements `ShouldBeEncrypted`.
- Stale token job is a no-op; current token still sends.
- Pattern: existing tests in `tests/Feature/CampaignInvitationTest.php`.
- Verification: `php artisan test --compact tests/Feature/CampaignInvitationTest.php` → all pass.

## Done criteria

- [ ] `SendCampaignExamInvitationEmail` implements `ShouldBeEncrypted` and `ShouldQueue`
- [ ] Stale `plainToken` vs `token_hash` rejected via `hash_equals` before mail
- [ ] New Pest tests assert encryption interface + stale-token no-op
- [ ] `php artisan test --compact tests/Feature/CampaignInvitationTest.php` exits 0
- [ ] `vendor/bin/pint --dirty --format agent` exits 0
- [ ] No files outside the in-scope list are modified (`git status`)
- [ ] `plans/README.md` status row updated to DONE

## STOP conditions

- The job no longer takes a `plainToken` constructor arg (architecture changed) — stop and report.
- Laravel’s queue encryption appears disabled or `ShouldBeEncrypted` is unavailable in this framework version — stop and report; do not invent a custom encryption wrapper.
- Existing Campaign Invitation tests fail for reasons unrelated to this change after two fix attempts.

## Maintenance notes

- After deploy: rotate any **pending** Campaign Invitation tokens that may have been serialized to the queue before this fix (re-issue / resend). Do not leave a note that includes real token values.
- Reviewers: confirm the job matches Team/Ownership notification encryption, and that payload still builds the invite URL correctly in `handle()`.
- Follow-up: Campaign Invitation revoke/resend (plan 014) should keep using encrypted notifications/jobs for any new plaintext token.
