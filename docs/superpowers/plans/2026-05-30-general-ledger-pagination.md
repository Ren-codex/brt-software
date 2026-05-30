# General Ledger Pagination Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add server-side pagination with a configurable per-page selector to the General Ledger journal lines table.

**Architecture:** The backend `buildLedgerLines` method replaces `->limit(200)->get()` with `->paginate($perPage)` and returns a `{data, meta, links, stats}` envelope. The frontend `GeneralLedger.vue` gains a per-page dropdown, imports the shared `Pagination` component, and auto-fetches on `created()`.

**Tech Stack:** Laravel 11 (PHP), Vue 3 (Options API), Axios, Bootstrap 5, existing `Pagination.vue` component.

---

## File Map

| File | Action | Responsibility |
|------|--------|---------------|
| `app/Http/Controllers/Modules/AccountingController.php` | Modify | Replace `->limit(200)` with `->paginate()`, update return shape, update Inertia props |
| `resources/js/Pages/Modules/Accounting/GeneralLedger.vue` | Modify | Add `Pagination` component, per-page selector, auto-fetch on `created()` |
| `tests/Feature/Accounting/GeneralLedgerPaginationTest.php` | Create | Feature tests for paginated `journal_lines` endpoint |

---

## Task 1: Write the Failing Test

**Files:**
- Create: `tests/Feature/Accounting/GeneralLedgerPaginationTest.php`

- [ ] **Step 1.1: Create the test file**

```php
<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralLedgerPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->account = Account::create([
            'code'      => '1001',
            'slug'      => 'cash',
            'name'      => 'Cash',
            'type'      => 'asset',
            'is_active' => true,
        ]);

        for ($i = 1; $i <= 12; $i++) {
            $entry = JournalEntry::create([
                'journal_number' => 'JE-TEST-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'entry_date'     => '2026-01-' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'entry_type'     => 'manual',
                'status'         => 'posted',
                'posted_at'      => now(),
            ]);
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $this->account->id,
                'line_type'        => 'debit',
                'amount'           => 100.00,
                'line_order'       => 1,
            ]);
        }
    }

    public function test_journal_lines_returns_paginated_envelope(): void
    {
        $response = $this->getJson('/accounting/general-ledger?option=journal_lines&per_page=10');

        $response->assertOk();
        $response->assertJsonStructure([
            'data'  => [['id', 'journal_number', 'entry_date', 'account_code', 'account_name', 'debit', 'credit']],
            'meta'  => ['current_page', 'last_page', 'per_page', 'total'],
            'links' => ['first', 'last', 'prev', 'next'],
            'stats' => ['total_debits', 'total_credits', 'is_balanced', 'entry_count', 'line_count'],
        ]);
        $response->assertJsonPath('meta.per_page', 10);
        $response->assertJsonPath('meta.total', 12);
        $response->assertJsonPath('meta.last_page', 2);
        $response->assertJsonCount(10, 'data');
    }

    public function test_per_page_param_is_respected(): void
    {
        $response = $this->getJson('/accounting/general-ledger?option=journal_lines&per_page=5');

        $response->assertOk();
        $response->assertJsonPath('meta.per_page', 5);
        $response->assertJsonPath('meta.last_page', 3);
        $response->assertJsonCount(5, 'data');
    }

    public function test_per_page_is_clamped_to_100(): void
    {
        $response = $this->getJson('/accounting/general-ledger?option=journal_lines&per_page=500');

        $response->assertOk();
        $response->assertJsonPath('meta.per_page', 100);
    }

    public function test_stats_reflect_full_dataset_not_current_page(): void
    {
        $response = $this->getJson('/accounting/general-ledger?option=journal_lines&per_page=10');

        $response->assertOk();
        $response->assertJsonPath('stats.line_count', 12);
    }
}
```

- [ ] **Step 1.2: Run the tests to confirm they fail**

```bash
php artisan test --filter=GeneralLedgerPaginationTest
```

Expected output: 4 failures. The `journal_lines` endpoint currently returns `{lines: [...], stats: {...}}`, not `{data, meta, links, stats}`.

---

## Task 2: Update `buildLedgerLines` — Backend Pagination

**Files:**
- Modify: `app/Http/Controllers/Modules/AccountingController.php` (lines 608–719)

- [ ] **Step 2.1: Replace the query and return in `buildLedgerLines`**

Find the section in `buildLedgerLines` that starts with `$lines = (clone $base)` (around line 663) and runs through the `return [...]` at the end of the method (around line 719). Replace that entire block with the following:

```php
        $perPage = max(1, min(100, (int) ($request->per_page ?? 10)));

        $paginator = (clone $base)
            ->select([
                'jel.id',
                'je.journal_number',
                'je.entry_date',
                'je.posted_at',
                'je.entry_type',
                'je.source_type',
                'je.source_id',
                'a.code as account_code',
                'a.name as account_name',
                'a.type as account_type',
                'jel.line_type',
                'jel.amount',
                'jel.description',
                'u.username as posted_by',
            ])
            ->orderBy('je.entry_date')
            ->orderBy('je.id')
            ->orderBy('jel.id')
            ->paginate($perPage);

        $sourceMap = $this->batchResolveSourceRefs($paginator->getCollection());

        $paginator->through(fn ($line) => [
            'id'             => $line->id,
            'journal_number' => $line->journal_number,
            'entry_date'     => $line->entry_date,
            'post_date'      => $line->posted_at
                ? Carbon::parse($line->posted_at)->format('Y-m-d')
                : $line->entry_date,
            'account_code'   => $line->account_code,
            'account_name'   => $line->account_name,
            'account_type'   => $line->account_type,
            'entry_type'     => Str::of($line->entry_type)->replace('_', ' ')->title()->value(),
            'source_label'   => $this->resolveSourceLabel($line->entry_type),
            'reference'      => ($line->source_type && $line->source_id)
                ? ($sourceMap[$line->source_type . ':' . $line->source_id] ?? null)
                : null,
            'description'    => $line->description ?: '—',
            'line_type'      => $line->line_type,
            'debit'          => $line->line_type === 'debit'  ? $this->formatCurrency($line->amount) : null,
            'credit'         => $line->line_type === 'credit' ? $this->formatCurrency($line->amount) : null,
            'posted_by'      => $line->posted_by ?: 'System',
        ]);

        $paged = $paginator->toArray();

        return [
            'data'  => $paged['data'],
            'meta'  => [
                'current_page' => $paged['current_page'],
                'from'         => $paged['from'],
                'last_page'    => $paged['last_page'],
                'links'        => $paged['links'],
                'path'         => $paged['path'],
                'per_page'     => $paged['per_page'],
                'to'           => $paged['to'],
                'total'        => $paged['total'],
            ],
            'links' => [
                'first' => $paged['first_page_url'],
                'last'  => $paged['last_page_url'],
                'prev'  => $paged['prev_page_url'],
                'next'  => $paged['next_page_url'],
            ],
            'stats' => [
                'total_debits'  => $this->formatCurrency($totalDebits),
                'total_credits' => $this->formatCurrency($totalCredits),
                'is_balanced'   => abs($totalDebits - $totalCredits) < 0.01,
                'entry_count'   => (int) $statsRow->entry_count,
                'line_count'    => (int) $statsRow->line_count,
            ],
        ];
```

- [ ] **Step 2.2: Update the Inertia render in `generalLedger()` to use new keys**

Find the Inertia return inside `generalLedger()` (around line 587). It currently passes `ledgerLines` and `ledgerStats`. Change those two lines to:

```php
            'ledgerLines'     => $ledgerData['data'],
            'ledgerStats'     => $ledgerData['stats'],
            'ledgerMeta'      => $ledgerData['meta'],
            'ledgerLinks'     => $ledgerData['links'],
```

- [ ] **Step 2.3: Run the tests to confirm they now pass**

```bash
php artisan test --filter=GeneralLedgerPaginationTest
```

Expected: 4 tests pass, 0 failures.

- [ ] **Step 2.4: Commit**

```bash
git add app/Http/Controllers/Modules/AccountingController.php tests/Feature/Accounting/GeneralLedgerPaginationTest.php
git commit -m "feat: paginate general ledger journal lines endpoint"
```

---

## Task 3: Update `GeneralLedger.vue` — Frontend

**Files:**
- Modify: `resources/js/Pages/Modules/Accounting/GeneralLedger.vue`

- [ ] **Step 3.1: Import `Pagination` and add it to `components`**

In the `<script>` block, find the existing imports and add:

```js
import Pagination from "@/Shared/Components/Pagination.vue";
```

In the `export default { ... }` object, add `Pagination` to `components`:

```js
components: { Pagination },
```

- [ ] **Step 3.2: Add new props for initial pagination state**

Inside `props: { ... }`, add two new props after `ledgerStats`:

```js
ledgerMeta:  { type: Object, default: () => ({}) },
ledgerLinks: { type: Object, default: () => ({}) },
```

- [ ] **Step 3.3: Add `llPerPage`, `llMeta`, `llLinks` to `data()`**

Inside `data() { return { ... } }`, add after `llLoading: false`:

```js
llPerPage: 10,
llMeta:    this.ledgerMeta  || {},
llLinks:   this.ledgerLinks || {},
```

- [ ] **Step 3.4: Add `created()` hook for auto-fetch**

Add the `created` lifecycle hook between `data()` and `methods:`:

```js
created() {
    this.fetchLedgerLines();
},
```

- [ ] **Step 3.5: Update `fetchLedgerLines` to accept `pageUrl` and send `per_page`**

Replace the existing `fetchLedgerLines()` method with:

```js
async fetchLedgerLines(pageUrl) {
    this.llLoading = true;
    try {
        const url = (typeof pageUrl === 'string' && pageUrl)
            ? pageUrl
            : '/accounting/general-ledger';
        const params = { option: 'journal_lines', per_page: this.llPerPage };
        if (this.dateFrom)                 params.date_from     = this.dateFrom;
        if (this.dateTo)                   params.date_to       = this.dateTo;
        if (this.llSearch.trim())          params.search        = this.llSearch.trim();
        if (this.llSourceFilter !== 'all') params.source_filter = this.llSourceFilter;
        const { data } = await axios.get(url, { params });
        this.llLines = data.data  || [];
        this.llStats = data.stats || this.llStats;
        this.llMeta  = data.meta  || {};
        this.llLinks = data.links || {};
    } catch {
        // keep existing data on error
    } finally {
        this.llLoading = false;
    }
},
```

- [ ] **Step 3.6: Add the per-page selector to the filter row**

In the template, find the `.ll-filter-row` div. After the existing `ll-source-select` `<select>` element and before the Search button, add:

```html
<select v-model="llPerPage" class="ll-source-select" @change="fetchLedgerLines()">
    <option :value="10">10 / page</option>
    <option :value="25">25 / page</option>
    <option :value="50">50 / page</option>
    <option :value="100">100 / page</option>
</select>
```

- [ ] **Step 3.7: Add `Pagination` component below the table**

In the template, find the closing `</div>` of `class="library-card-body"` (it wraps the stats bar, filter row, and table). Just before that closing `</div>`, add:

```html
<div class="px-3 pb-3">
    <Pagination
        v-if="llMeta && llMeta.last_page > 1"
        :lists="llLines.length"
        :links="llLinks"
        :pagination="llMeta"
        @fetch="fetchLedgerLines"
    />
</div>
```

- [ ] **Step 3.8: Build assets**

```bash
npm run build
```

Expected: Build completes with no errors.

- [ ] **Step 3.9: Commit**

```bash
git add resources/js/Pages/Modules/Accounting/GeneralLedger.vue
git commit -m "feat: add per-page selector and pagination to general ledger"
```

---

## Task 4: Manual Verification

- [ ] **Step 4.1: Start servers**

In two terminals:

```bash
php artisan serve
```

```bash
npm run dev
```

- [ ] **Step 4.2: Verify auto-load on page open**

Navigate to `/accounting/general-ledger`. Confirm the journal lines table populates without clicking "Search".

- [ ] **Step 4.3: Verify per-page selector**

Change the per-page dropdown from 10 to 25. Confirm the table reloads and shows up to 25 rows.

- [ ] **Step 4.4: Verify pagination controls appear only with multiple pages**

If total lines ≤ 10 with default setting, confirm the pagination bar does NOT appear. Switch to `5 / page` — if total > 5, confirm it appears.

- [ ] **Step 4.5: Verify prev/next/first/last navigation**

With multiple pages, click → (next), ← (prev), first, last. Confirm the correct page loads each time and the row count shown at the bottom (from `Pagination` component) is correct.

- [ ] **Step 4.6: Verify stats bar stays accurate across pages**

Navigate to page 2. Confirm "Total Debits" and "Total Credits" in the stats bar still reflect the full period total, not just the current page.

- [ ] **Step 4.7: Run full test suite to confirm no regressions**

```bash
php artisan test
```

Expected: All tests pass.
