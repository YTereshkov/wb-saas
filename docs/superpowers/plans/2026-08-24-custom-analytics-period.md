# Custom Analytics Period Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a calendar range picker that allows any inclusive interval covered by stored SellerScope data and persists exact custom dates across all analytics pages.

**Architecture:** Laravel computes authoritative per-resource and overall data coverage, validates custom ranges, resolves equal-length comparisons, and shares the canonical context through Inertia. React renders a dependency-free range calendar using those bounds and sends exact dates through the existing context action. Existing daily aggregates remain the analytics source; long ranges select day, week, or month chart density on the backend.

**Tech Stack:** Laravel 13, Eloquent query builder, Inertia 3, React 19, TypeScript, Tailwind 4, existing shadcn primitives and Lucide icons.

---

### Task 1: Authoritative data coverage

**Files:**
- Create: `app/Modules/Analytics/Queries/AnalyticsDataCoverageQuery.php`
- Modify: `app/Modules/Analytics/Queries/AnalyticsContextQuery.php`
- Test: `tests/Feature/Frontend/AppShellTest.php`

- [ ] Add a failing feature test with an order in 2024, sale in 2025, and stock snapshot in 2026. Assert shared props contain the union `overall` range and separate `orders`, `sales`, `returns`, and `stocks` ranges.
- [ ] Run `php artisan test --compact tests/Feature/Frontend/AppShellTest.php` and confirm the missing `analyticsContext.coverage` assertion fails.
- [ ] Implement indexed `MIN`/`MAX` boundary queries scoped by `seller_account_id`, converting timestamps to the seller timezone before exposing `YYYY-MM-DD` dates.
- [ ] Return nullable resource ranges and the union range in `AnalyticsContextQuery`.
- [ ] Re-run the focused feature test and confirm it passes.

### Task 2: Custom range resolution and validation

**Files:**
- Modify: `app/Modules/Analytics/Services/AnalyticsPeriodResolver.php`
- Modify: `app/Modules/Analytics/Data/AnalyticsPeriod.php`
- Modify: `app/Http/Requests/UpdateAnalyticsContextRequest.php`
- Modify: `app/Http/Controllers/AnalyticsContextController.php`
- Test: `tests/Feature/Frontend/AppShellTest.php`

- [ ] Add failing tests for `period_preset=custom`, exact inclusive dates, equal-length preceding comparison, preference persistence, URL parameters, outside-coverage rejection, reversed dates, and foreign account isolation.
- [ ] Run the focused tests and confirm failures occur at validation/resolution.
- [ ] Allow `custom`, require `period_start` and `period_end` for it, and parse both in the account timezone.
- [ ] Add `forCustom()` to produce comparison bounds and automatic granularity: `day` through 90 days, `week` through 730, otherwise `month`.
- [ ] Reject explicit custom submissions whose endpoints fall outside `coverage.overall`; do not change the stored preference on failure.
- [ ] Merge canonical `period`, `from`, `to`, and `cabinet` parameters into `return_to` without duplicating existing query keys.
- [ ] Re-run focused tests and confirm all cases pass.

### Task 3: Shared TypeScript contract and range calendar

**Files:**
- Modify: `resources/js/types/analytics-context.ts`
- Create: `resources/js/features/analytics-context/date-range-picker.tsx`
- Create: `resources/js/features/analytics-context/analytics-period-control.tsx`
- Modify: `resources/js/components/app-topbar.tsx`

- [ ] Extend `AnalyticsContext` with `coverage`, `period.granularity`, and comparison completeness.
- [ ] Implement UTC-safe calendar helpers for ISO parsing, month navigation, grid construction, inclusive selection, and Russian month/day labels.
- [ ] Render two months at `md` and above, one month below `md`; disable days outside overall coverage.
- [ ] Add start/end date inputs with `min`/`max`, preset actions, apply/cancel, keyboard labels, and inline server error handling.
- [ ] Keep the compact calendar icon reachable on mobile and the full selected range label on desktop.
- [ ] Send `period_start` and `period_end` only for custom selection through the existing Inertia PATCH.
- [ ] Run `npm run types:check`, `npm run lint:check`, and `npm run format:check`; fix all failures.

### Task 4: Long-range chart density and comparison availability

**Files:**
- Modify: `app/Modules/Analytics/Queries/OverviewQuery.php`
- Modify: `app/Modules/Analytics/Queries/SalesAnalyticsQuery.php`
- Modify: `app/Modules/Analytics/Queries/ProductDetailsQuery.php`
- Modify: `resources/js/components/analytics/revenue-chart.tsx`
- Modify: `resources/js/components/analytics/multi-line-chart.tsx`
- Modify: `resources/js/components/sales/sales-quality-chart.tsx`
- Modify: applicable types under `resources/js/types/`
- Test: analytics feature tests under `tests/Feature/Analytics/`

- [ ] Add failing analytics tests asserting that a 3-year range returns monthly chart points, exact full-range KPI totals, and `null` comparison values when comparison coverage is incomplete.
- [ ] Implement a shared time-series grouper over daily metrics for day/week/month buckets with aligned comparison buckets.
- [ ] Return only the selected density for multi-year ranges and expose the active granularity in the view model.
- [ ] Update chart controls to show available densities and default to the backend-selected density without storing authoritative metric state in React.
- [ ] Add the comparison-unavailable and partial-resource notices using shared coverage props; never replace unavailable facts with measured zeroes.
- [ ] Re-run analytics feature tests and frontend checks.

### Task 5: Full verification

**Files:**
- No new files expected.

- [ ] Run focused backend tests for analytics context and analytics queries.
- [ ] Run `composer ci:check` and confirm PHPStan, Pint, frontend checks, and all PHPUnit tests pass.
- [ ] Run `npm run build`.
- [ ] Rebuild and recreate local Docker services.
- [ ] In the in-app browser verify presets, a manual multi-year range, disabled out-of-coverage dates, apply/cancel, URL state, desktop two-month layout, mobile `390x844` layout, and console errors.
- [ ] Do not click destructive cabinet actions and do not use real WB credentials.

No Git commit or push is part of this plan; the user did not request either.
