# General Ledger Pagination Design

**Date:** 2026-05-30
**Scope:** Add server-side pagination to the General Ledger journal lines table.

---

## Context

Journal Entries already has full pagination: the backend uses `paginate(15)`, and the frontend renders the shared `Pagination` component wired to `meta`/`links` from the paginator response. No changes needed there.

General Ledger is missing pagination entirely. The backend `buildLedgerLines` method returns up to 200 lines via `.limit(200)`, and `GeneralLedger.vue` loads them all into a flat array with no page controls.

---

## Approach

Server-side pagination (Option A). The backend paginates the DB query; the frontend sends `per_page` + `page` params and renders the existing `Pagination` component. This matches the Journal Entries pattern exactly.

---

## Backend Changes

**File:** `app/Http/Controllers/Modules/AccountingController.php`

### `buildLedgerLines(Request $request, ?string $dateFrom, ?string $dateTo)`

- Read `$perPage = max(1, min(100, (int) ($request->per_page ?? 10)))` from the request (clamp 1–100).
- Replace `->limit(200)->get()` with `->paginate($perPage)`.
- Map over `$paginator->getCollection()` (instead of `$lines`).
- Return the paginator's `toArray()` envelope merged with the stats:
  ```php
  return array_merge(
      $paginator->toArray(),  // provides data, meta, links
      ['stats' => [...]]
  );
  ```

### `generalLedger(Request $request)` — `journal_lines` branch

- Already returns `response()->json($this->buildLedgerLines(...))`.
- No structural change needed; shape becomes `{ data, meta, links, stats }`.

### Initial Inertia page load

- `buildLedgerLines` is called and its `data` key is passed as `ledgerLines`, `stats` as `ledgerStats`.
- Change the Inertia props to also pass `ledgerMeta` and `ledgerLinks` from the paginator so the first page renders with correct pagination state.

---

## Frontend Changes

**File:** `resources/js/Pages/Modules/Accounting/GeneralLedger.vue`

### New props

- `ledgerMeta` — initial paginator `meta` object (from Inertia).
- `ledgerLinks` — initial paginator `links` object (from Inertia).

### New `data()` fields

```js
llPerPage: 10,
llMeta:    this.ledgerMeta  || {},
llLinks:   this.ledgerLinks || {},
```

### Import `Pagination` component

Same import as `JournalEntries.vue`:
```js
import Pagination from "@/Shared/Components/Pagination.vue";
```

### Filter row addition — per-page selector

Add a `<select>` to the existing `.ll-filter-row` with options `[10, 25, 50, 100]` bound to `llPerPage`. On `change`, reset `llMeta` current page to 1 and re-fetch.

### `fetchLedgerLines(pageUrl?)` update

- Accept an optional `pageUrl` parameter (passed by `Pagination` component on prev/next/first/last click).
- If `pageUrl` is a string URL, use it as the axios base URL; otherwise default to `/accounting/general-ledger`.
- Pass `per_page: this.llPerPage` in params.
- On success: set `this.llLines`, `this.llStats`, `this.llMeta`, `this.llLinks` from response.

### Pagination component

Below the `</table>` closing tag, inside a `<div class="px-3 pb-3">`:
```html
<Pagination
    v-if="llMeta && llMeta.links"
    :lists="llLines.length"
    :links="llLinks"
    :pagination="llMeta"
    @fetch="fetchLedgerLines"
/>
```

### Auto-fetch on `created()`

Add `created() { this.fetchLedgerLines(); }` so the first page loads immediately without requiring the user to click "Search" — matching Journal Entries behavior.

---

## Stats Accuracy

The stats bar (Total Debits / Total Credits / Net Balance / Journal Entries count) uses an independent `(clone $base)->selectRaw(...)->first()` aggregate that runs across the full filtered dataset. It is not scoped to the current page — no change needed.

---

## Constraints

- `per_page` is clamped server-side to 1–100.
- Changing `per_page` resets to page 1.
- Filter changes (search, source, date range) also reset to page 1.
- The "Search" button continues to work as before (triggers `fetchLedgerLines()` from page 1).

---

## Files Changed

| File | Change |
|------|--------|
| `app/Http/Controllers/Modules/AccountingController.php` | `buildLedgerLines`: replace limit with paginate; return paginator envelope |
| `app/Http/Controllers/Modules/AccountingController.php` | `generalLedger`: pass `ledgerMeta`, `ledgerLinks` as Inertia props |
| `resources/js/Pages/Modules/Accounting/GeneralLedger.vue` | Add `Pagination` component, `llPerPage` selector, `llMeta`/`llLinks` state |
