# Plan 018: Enter fullscreen before starting the secure exam

> **Executor instructions**: Preserve the selected policy: failure to enter required fullscreen must block exam start and must not record candidate misconduct. Follow all gates and update the plan index when complete.
>
> **Drift check (run first)**: `git diff --stat 83f7e7e..HEAD -- resources/js/pages/candidate/exam.tsx resources/js/hooks/use-exam-proctoring.ts app/Services/CandidateExamPage.php tests/Feature/SecureExamSessionTest.php`

## Status

- **Priority**: P1
- **Effort**: M
- **Risk**: MED
- **Depends on**: none
- **Category**: bug
- **Planned at**: commit `83f7e7e`, 2026-07-12

## Why this matters

Browsers require `requestFullscreen()` to run within a user activation. The current hook calls it after the POST/redirect has rendered an active session; rejection is then recorded as `fullscreen_exit`, falsely consuming an integrity warning. The Start button must acquire fullscreen before the Inertia POST, and technical inability to acquire it must be shown as a setup error rather than misconduct.

## Current state

- `StartExamState` submits an Inertia `<Form>` directly.
- `useExamProctoring` calls `requestFullscreen()` in `useEffect()` and reports rejection as `fullscreen_exit`.
- `ready_to_start` props do not include secure-exam configuration; only active session payload does.
- Inertia navigation remains in the same document, so fullscreen entered before `router.post()` should survive the page update.
- This repo has no committed JavaScript/browser test runner. Existing UI contract tests sometimes inspect source, e.g. `AdminCampaignTest.php:592-600`.

Current invalid timing:

```tsx
if (
    secureExam.require_fullscreen &&
    document.fullscreenElement === null
) {
    void document.documentElement.requestFullscreen?.().catch(() => {
        report('fullscreen_exit');
    });
}
```

## Commands you will need

| Purpose | Command | Expected on success |
|---|---|---|
| PHP contract tests | `php artisan test --compact tests/Feature/SecureExamSessionTest.php` | all pass |
| Typecheck | `pnpm run types:check` | exit 0 |
| Frontend checks | `pnpm run lint:check && pnpm run format:check` | exit 0 |
| Full checks | `composer ci:check` | exit 0 |

## Suggested executor toolkit

- Invoke `inertia-react-development`, `wayfinder-development`, `tailwindcss-development`, and `pest-testing`.
- Use Boost `search-docs` for `Inertia router post callbacks`.
- Use browser automation for the manual end-to-end gate after automated tests.

## Scope

**In scope**:
- `resources/js/pages/candidate/exam.tsx`
- `resources/js/hooks/use-exam-proctoring.ts`
- `app/Services/CandidateExamPage.php`
- `tests/Feature/SecureExamSessionTest.php`
- `plans/README.md`

**Out of scope**:
- Adding frontend/browser dependencies.
- Changing warning thresholds or server violation types.
- Disabling fullscreen enforcement.
- Timer and auto-submit behavior.
- Redesigning the exam page.

## Git workflow

- Suggested branch: `advisor/018-fullscreen-entry`
- Suggested commit: `Enter fullscreen before secure exams.`
- Do not push or open a PR unless instructed.

## Steps

### Step 1: Expose pre-session secure-exam configuration

Add the same `require_fullscreen` and `block_copy_paste` shape used by `sessionPayloadForInertia()` to the `ready_to_start` payload from `CandidateExamPage`.

Update the discriminated TypeScript `ReadyToStartProps` and pass the config into `StartExamState`. Keep naming consistent with the existing `ExamSessionProps['secure_exam']` shape; do not create a third naming convention.

Add an Inertia assertion that `ready_to_start` includes the configured boolean values.

**Verify**: `php artisan test --compact tests/Feature/SecureExamSessionTest.php --filter="start an exam|ready"` → pass.

### Step 2: Acquire fullscreen inside the Start button activation

Replace direct form submission with an explicit button handler:

1. Set local processing state and clear any previous setup error.
2. If fullscreen is required and not active, call `document.documentElement.requestFullscreen()` directly from the click handler before any asynchronous navigation.
3. If the API is unavailable or rejects, show a concise `InputError`/alert, reset processing, and do not POST.
4. On success—or immediately when fullscreen is disabled—call `router.post()` using `ExamSessionController.store.url(campaign.id)`.
5. Use Inertia lifecycle callbacks to keep the button disabled while the request is active.
6. If the POST fails after fullscreen succeeds, exit fullscreen if this handler entered it, then show the server error through existing Inertia behavior.

Do not hardcode the route URL; use Wayfinder.

**Verify**: `pnpm run types:check` → exit 0.

### Step 3: Make proctoring observe exits only

In `useExamProctoring`:

- remove the mount-time fullscreen request and its rejection handler;
- retain the `fullscreenchange` listener;
- report `fullscreen_exit` only when the exam was already active and a change event leaves `document.fullscreenElement === null`;
- do not report missing API support or initial entry failure from this hook.

Keep copy/paste, blur, visibility, and context-menu behavior unchanged.

**Verify**: `rg "requestFullscreen" resources/js/hooks/use-exam-proctoring.ts` → no matches; `rg "fullscreenchange" resources/js/hooks/use-exam-proctoring.ts` → one listener registration and one cleanup.

### Step 4: Add automated source-contract regression

Because the repo has no browser test harness and dependency changes are out of scope, add a focused test in `SecureExamSessionTest.php` that reads the two frontend sources and asserts:

- `exam.tsx` contains `requestFullscreen` in `StartExamState`;
- the request occurs in the same handler path before `ExamSessionController.store.url`/`router.post`;
- `use-exam-proctoring.ts` does not contain `requestFullscreen`;
- the proctoring hook still contains `fullscreenchange` and `report('fullscreen_exit')`.

Keep this test narrow and explain in its name that it is a source contract. Do not build a large source-parser test.

**Verify**: targeted PHP test passes.

### Step 5: Run browser automation

Against a local app with an assigned Candidate:

1. Open the ready-to-start page outside fullscreen.
2. Click Start once.
3. Confirm the document enters fullscreen before the session POST completes.
4. Confirm the active section renders with warning count 0.
5. Exit fullscreen intentionally.
6. Confirm one `fullscreen_exit` violation and warning count 1.
7. Repeat with `ASSESSMENT_EXAM_REQUIRE_FULLSCREEN=false`; confirm no fullscreen request is required.
8. Simulate/deny fullscreen support; confirm no session is created and no violation is recorded.

Capture screenshots or browser logs as execution evidence; do not add them to the repo.

**Verify**: all eight observations match.

### Step 6: Run gates

**Verify**:
- `pnpm run types:check` → exit 0.
- `pnpm run lint:check && pnpm run format:check` → exit 0.
- targeted PHP tests → exit 0.
- `composer ci:check` → exit 0.

## Test plan

- Inertia payload exposes pre-start config.
- Source contract pins request ownership to the click handler.
- Browser automation verifies initial entry, intentional exit, disabled enforcement, and unavailable API.
- Existing server violation test remains green.

## Done criteria

- [ ] Required fullscreen is requested from the Start click handler.
- [ ] Session creation begins only after successful entry.
- [ ] Entry failure creates neither session nor violation.
- [ ] Proctoring hook observes exits but never initiates fullscreen.
- [ ] Intentional exit records exactly one warning.
- [ ] TypeScript, frontend checks, targeted tests, browser gate, and full checks pass.
- [ ] Only in-scope files changed.

## STOP conditions

Stop and report if:

- Inertia navigation exits fullscreen in a supported target browser.
- Browser policy forbids fullscreen even under a direct user gesture.
- Wayfinder's generated action signature has drifted.
- A persistent browser test is required by the reviewer; adding one needs explicit dependency approval and a separate plan amendment.

## Maintenance notes

- Browser behavior is the source of truth; the PHP source-contract test only prevents accidental relocation of the request.
- If a frontend test harness is added later, replace the source assertion with an interaction test.
