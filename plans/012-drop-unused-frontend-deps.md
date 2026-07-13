# Plan 012: Drop unused frontend dependency weight

> **Executor instructions**: Follow this plan step by step. Run every verification command and confirm the expected result before moving to the next step. If anything in the "STOP conditions" section occurs, stop and report — do not improvise. When done, update the status row for this plan in `plans/README.md` — unless a reviewer dispatched you and told you they maintain the index.
>
> **Drift check (run first)**: `git diff --stat 83f7e7e..HEAD -- package.json pnpm-lock.yaml resources/js/components/ui resources/css/app.css resources/js/components/ui/sonner.tsx`
> If any in-scope file changed since this plan was written, compare the "Current state" excerpts against the live code before proceeding; on a mismatch, treat it as a STOP condition.

## Status

- **Priority**: P3
- **Effort**: S
- **Risk**: LOW
- **Depends on**: none
- **Category**: migration
- **Planned at**: commit `83f7e7e`, 2026-07-12

## Why this matters

`package.json` lists 13 direct `@radix-ui/react-*` packages while all UI primitives import the umbrella `radix-ui`. `@fontsource-variable/instrument-sans` is unused (Instrument Sans comes from Bunny in `vite.config.ts`; CSS uses Geist). `shadcn` CLI sits in `dependencies` instead of `devDependencies`. Also, `sonner.tsx` imports `next-themes` without a `ThemeProvider` while the app uses `use-appearance` — fix or drop as part of the same cleanup if still true.

## Current state

- UI imports: `from "radix-ui"` / `from 'radix-ui'` across `resources/js/components/ui/*`.
- Zero `from '@radix-ui/...'` under `resources/js` (verify with rg).
- `package.json` dependencies include both `radix-ui` and many `@radix-ui/*`, plus `shadcn`, `@fontsource-variable/instrument-sans`, `next-themes`.
- `resources/js/components/ui/sonner.tsx` uses `useTheme` from `next-themes`.

## Commands you will need

| Purpose | Command | Expected on success |
|---------|---------|---------------------|
| Import scan | `rg -n "from '@radix-ui|from \"@radix-ui" resources/js` | no matches |
| Install | `pnpm install` | exit 0 |
| Build | `pnpm run build` | exit 0 |
| Types | `pnpm run types:check` | exit 0 |
| Lint | `pnpm run lint:check` | exit 0 |

## Scope

**In scope**:
- `package.json` / `pnpm-lock.yaml`
- `resources/js/components/ui/sonner.tsx` (wire to `useAppearance` / remove `next-themes` if unused elsewhere)
- `plans/README.md`

**Out of scope**:
- Renaming Composer package `laravel/react-starter-kit` (cosmetic; optional tiny extra commit only if operator wants)
- Changing Bunny font loading
- Upgrading radix major versions beyond removal

## Git workflow

- Branch: `advisor/012-drop-unused-frontend-deps`
- Commit: `Drop unused Radix and fontsource dependencies.`
- Do NOT push/PR unless asked.

## Steps

### Step 1: Confirm unused

```bash
rg -n "from '@radix-ui|from \"@radix-ui" resources/js
rg -n "instrument-sans|Instrument Sans" resources/js resources/css
rg -n "from 'next-themes'|from \"next-themes\"" resources/js
rg -n "from 'shadcn'|from \"shadcn\"" resources/js
```

**Verify**: `@radix-ui` imports none; `shadcn` none; instrument-sans only in package.json / vite Bunny; next-themes only sonner (or also nowhere).

### Step 2: Fix Sonner theme wiring

Update `sonner.tsx` to use the app appearance hook (`resources/js/hooks/use-appearance.tsx`) so toast theme tracks light/dark. Remove `next-themes` from package.json if no remaining imports.

**Verify**: `rg -n next-themes resources/js` → no matches after removal.

### Step 3: Edit package.json

1. Remove all unused `@radix-ui/react-*` direct dependencies; **keep** `radix-ui`.
2. Remove `@fontsource-variable/instrument-sans`.
3. Move `shadcn` to `devDependencies`.
4. Remove `next-themes` if unused.

Run `pnpm install` to refresh the lockfile.

**Verify**: install + build + types:check + lint:check all exit 0.

## Test plan

- No Pest tests required; frontend build/types are the gate.
- Manually smoke: app still builds; Sonner compiles.

## Done criteria

- [ ] No unused `@radix-ui/*` in package.json dependencies
- [ ] No instrument-sans fontsource package
- [ ] `shadcn` in devDependencies
- [ ] `next-themes` removed or properly provided
- [ ] build + types:check pass
- [ ] README DONE

## STOP conditions

- A `@radix-ui` import appears outside `resources/js` but is required at runtime — stop and keep that package.
- Sonner breaks without next-themes and appearance hook API differs — stop and report rather than adding ThemeProvider for next-themes.

## Maintenance notes

- shadcn codegen may re-add `@radix-ui/*` — prefer configuring for umbrella `radix-ui` per project `components.json`.
