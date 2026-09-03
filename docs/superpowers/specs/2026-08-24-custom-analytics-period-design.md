# Custom Analytics Period Design

## Goal

Allow a SellerScope user to choose any inclusive date range for which the
selected seller account has imported analytics data. The range may span days or
many years. The UI must never imply that unavailable historical Wildberries
data is zero.

## Data Coverage

`AnalyticsDataCoverageQuery` derives coverage from SellerScope's normalized
facts, daily aggregates, and stock snapshots.

The shared analytics context exposes:

- `overall.start` and `overall.end`: the earliest and latest dates covered by
  any analytics resource;
- resource ranges for `orders`, `sales`, `returns`, and `stocks`;
- `null` bounds when an account has no imported analytics data.

Overall coverage is the union of resource coverage. A short stock history must
not hide an older sales history. Resource coverage is used by each page to
distinguish a valid empty result from an unavailable historical interval.

Coverage is computed from authoritative database data rather than stored on
`SellerAccount`. Existing account/date indexes are used where possible. A short
cache may be added only if measurement shows that the aggregate boundary
queries are material.

## Period Model And URL State

The existing presets remain available:

- `last_30_days`;
- `current_month`;
- `previous_month`.

A manually selected range uses:

- `period_preset = custom`;
- `period_start` and `period_end` in the seller account timezone;
- a comparison interval immediately preceding the selected range and having
  the same number of inclusive calendar days.

The URL is the source of navigation state:

```text
?period=custom&from=2024-03-10&to=2026-08-24
```

The selected range is also persisted to `user_preferences`, so it survives
navigation between analytics pages. The backend validates tenant ownership,
date format, inclusive ordering, and overall coverage. The frontend does not
define authoritative boundaries.

An outdated URL outside current coverage is clamped to the nearest valid
range. The canonical range is returned in Inertia props and reflected in the
next generated navigation URL.

## Range Picker UX

The period menu keeps the three quick presets and adds `Выбрать даты`.

The custom range picker provides:

- two visible months on desktop and one month on mobile;
- start and end selection as a single inclusive range;
- disabled calendar days outside `overall` coverage;
- explicit start and end date fields for fast navigation across years;
- `Применить` and `Отмена` actions;
- a visible selected-range treatment matching the approved design tokens;
- no maximum selected duration.

When `overall` coverage is empty, the period control is disabled and explains
that analytics data has not been loaded yet.

## Resource Availability

A range is selectable when it intersects overall coverage. Each analytics page
checks its required resource coverage.

If the selected range does not intersect a required resource, the page shows
the standard empty-state message `За выбранный период данных нет` and includes
the first available date for that resource. It must not render zero KPI values
as if they were measured facts.

If only part of the selected range is covered, the page shows a partial-data
notice with the covered dates. Calculations use only authoritative stored data,
and comparisons are marked unavailable unless both intervals have complete
coverage for the required resources.

## Comparison

For a custom range, comparison uses the immediately preceding interval of the
same inclusive length. It is never shortened or shifted to manufacture a
comparison.

When the comparison interval is not fully covered for a required resource:

- current KPI values remain available;
- relative changes and comparison series are unavailable;
- the UI shows `Нет полного набора данных за предыдущий период`.

## Aggregation And Performance

KPI queries always use the exact selected start and end dates. Chart grouping
changes only presentation density:

- up to 90 inclusive days: day;
- 91 to 730 inclusive days: week;
- more than 730 inclusive days: month.

Long ranges must be grouped in SQL or from existing daily aggregate tables
before data reaches React. React must not receive one point per day for a
multi-year range.

Backend filtering, sorting, and pagination continue to operate on the full
selected range. Cache keys include account ID, exact current/comparison bounds,
granularity, filters, and data revision.

## Error Handling

- Invalid date formats return validation errors without changing preferences.
- A start after the end returns a validation error.
- A range entirely outside overall coverage is clamped only when loaded from a
  stale URL; an explicit form submission is rejected and keeps the picker open.
- Missing resource history is a data-availability state, not a synchronization
  failure.
- A failed context update retains the existing period and offers retry.

## Testing

Backend feature and unit tests cover:

- coverage boundaries for each resource and their overall union;
- a multi-year custom range and equal-length comparison;
- validation, clamping of stale URL state, and tenant isolation;
- persistence to preferences and canonical query-string state;
- missing and partially covered resources;
- automatic day, week, and month granularity;
- analytics queries that do not emit fake zero values for unavailable periods.

Frontend and browser checks cover:

- preset and custom selection;
- disabled dates outside coverage;
- manual date entry across years;
- apply, cancel, validation, and failed-update states;
- desktop two-month and mobile one-month layouts;
- keyboard focus and absence of console errors.

## Out Of Scope

- Fabricating analytics for dates Wildberries does not expose;
- importing history from user-uploaded files;
- manually overriding resource coverage;
- changing the approved metric formulas;
- adding a second frontend API alongside Inertia.
