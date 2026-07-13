# Plan 008: Paginate campaign index and rankings

> **Executor instructions**: Follow this plan step by step. Run every verification command and confirm the expected result before moving to the next step. If anything in the "STOP conditions" section occurs, stop and report — do not improvise. When done, update the status row for this plan in `plans/README.md` — unless a reviewer dispatched you and told you they maintain the index.
>
> **Drift check (run first)**: `git diff --stat 83f7e7e..HEAD -- app/Http/Controllers/Admin/CampaignController.php app/Http/Controllers/Admin/RankingController.php resources/js/pages/admin/campaigns/index.tsx resources/js/pages/admin/rankings tests/Feature/AdminCampaignTest.php tests/Feature/AdminRankingDashboardTest.php`
> If any in-scope file changed since this plan was written, compare the "Current state" excerpts against the live code before proceeding; on a mismatch, treat it as a STOP condition.

## Status

- **Priority**: P2
- **Effort**: M
- **Risk**: MED
- **Depends on**: none
- **Category**: perf
- **Planned at**: commit `83f7e7e`, 2026-07-12

## Why this matters

Campaign index loads every campaign for the Current Team with full `job_description` via `->get()`. Rankings load every scored assessment, assign ranks in PHP, then filter search/status/date on Collections. Both ship unbounded Inertia payloads. Support teams index already paginates — match that pattern.

## Current state

- `app/Http/Controllers/Admin/CampaignController.php` index (~45–70): `Inertia::defer` → `->latest()->get()->map(...)` including `'job_description' => $campaign->job_description`.
- `app/Http/Controllers/Admin/RankingController.php` `rankingsForCampaign` (~128–151): `->get()` then Collection `when` filters.
- Exemplar pagination: `app/Http/Controllers/Support/TeamController.php` uses `paginate`.
- Frontend: `resources/js/pages/admin/campaigns/index.tsx` and ranking pages — inspect props; update to LengthAware paginator shape Inertia expects (Laravel paginator JSON).

## Commands you will need

| Purpose | Command | Expected on success |
|---------|---------|---------------------|
| Tests | `php artisan test --compact tests/Feature/AdminCampaignTest.php tests/Feature/AdminRankingDashboardTest.php` | all pass |
| Types (if TS props change) | `pnpm run types:check` | exit 0 |
| Pint | `vendor/bin/pint --dirty --format agent` | exit 0 |

## Suggested executor toolkit

- `laravel-best-practices`, `inertia-react-development`, `wayfinder-development` (only if routes change — prefer query `page` only), `pest-testing`, `tailwindcss-development` if UI pagination controls needed.

## Scope

**In scope**:
- `app/Http/Controllers/Admin/CampaignController.php` (index)
- `app/Http/Controllers/Admin/RankingController.php` (list query)
- Matching React pages/components that consume these props
- `tests/Feature/AdminCampaignTest.php`, `tests/Feature/AdminRankingDashboardTest.php`
- `plans/README.md`

**Out of scope**:
- `RankingOverview` dashboard aggregates (separate MED-confidence finding)
- Campaign show invitations list pagination (related but separate; optional small limit only if trivial)
- Changing ranking score formulas

## Git workflow

- Branch: `advisor/008-paginate-campaigns-rankings`
- Commit: `Paginate campaign index and ranking lists.`
- Do NOT push/PR unless asked.

## Steps

### Step 1: Paginate campaign index + trim payload

1. Replace `->get()` with `->paginate(15)` (or existing app convention if one exists — search `paginate(` in Admin controllers).
2. Remove `job_description` (and bulky fields not shown in the list UI — verify UI columns first) from the index DTO.
3. Preserve search/status filters in SQL (already mostly there).
4. Return paginator through Inertia (deferred paginator is fine if already deferred — keep defer if present).

Update React index to read `campaigns.data` / links if shape changes; follow other paginated Inertia pages in this repo if any.

**Verify**: `php artisan test --compact tests/Feature/AdminCampaignTest.php` — update assertions for missing `job_description` and pagination keys.

### Step 2: Push ranking filters into SQL + paginate

In `rankingsForCampaign`:

1. Apply search/status/date filters on the **query builder** before `paginate`.
2. Decide rank semantics: **global rank among all scored assessments** (recommended) vs rank within filtered page. Implement global rank via SQL window or a subquery count of higher scores when selecting the page — do not assign `$index + 1` only within the filtered collection if product shows “Rank #N” as overall standing.
3. Paginate results (e.g. 25 per page).

Update ranking UI for pagination controls.

**Verify**: `php artisan test --compact tests/Feature/AdminRankingDashboardTest.php` → pass; add a test with >page-size assessments asserting only one page of rows is returned in props.

### Step 3: Frontend + types

Wire simple pagination UI consistent with existing shadcn/table patterns in the repo. Run `pnpm run types:check`.

**Verify**: types:check exit 0.

### Step 4: Pint

`vendor/bin/pint --dirty --format agent`

## Test plan

- Campaign index does not include `job_description`.
- Campaign index props include pagination meta.
- Rankings respect filters in SQL (seed two statuses; filter returns one).
- Rank numbers remain globally correct under filter (assert explicitly).
- Pattern: existing AdminCampaign / AdminRankingDashboard tests.

## Done criteria

- [x] No unbounded `->get()` on these two list endpoints
- [x] Index DTO omits full job descriptions
- [x] Feature tests + types:check pass
- [x] Scope respected; README DONE

## STOP conditions

- Ranking UI requires infinite scroll / Inertia merge props and would expand far beyond pagination — stop and report; do not invent infinite scroll in this plan.
- Global rank SQL dialect issues on SQLite tests vs Postgres — use a portable approach (subquery count) or skip window functions.

## Maintenance notes

- Invitations deferred list on campaign show remains unbounded — candidate for a follow-up.
- Reviewers: verify rank meaning with product owner if ambiguous.
