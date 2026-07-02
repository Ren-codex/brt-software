# Petty Cash UI Consolidation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove the duplicate fund-administration UI (create/top-up/rename a petty cash fund) from the Cash Management page and the Petty Cash vouchers page, leaving the Petty Cash Funds page (`/accounting/funds`) as the single place to do it.

**Architecture:** Pure removal + one added navigation link per page. No new backend logic — `CashManagementService::createFund()`/`addTransaction()`/`FundClass` are untouched and keep working exactly as today; only the two now-unused HTTP entry points into them (`CashManagementController::storeFund/updateFund`, `PettyCashController::topUpFund`) and their frontend triggers are deleted.

**Tech Stack:** Laravel 11 (routes/web.php, Controllers), Vue 3 + Inertia (Options API, `<script>` blocks, axios).

## Global Constraints

- Do not touch `/accounting/funds` (`FundController`/`FundClass`), the vouchers/replenishment sub-tabs of `PettyCash.vue`, or any `JournalEntryService`/`CashManagementService` method bodies — spec says these are unchanged.
- Every removed frontend action must have its route/controller method removed too — no orphaned dead backend code.
- Existing test suites (`FundManagementTest`, `LowBalanceFundClassTest`, `LowBalanceCashManagementTest`) must still pass unchanged after the backend removals.

---

### Task 1: Remove dead backend routes and controller methods

**Files:**
- Modify: `app/Http/Controllers/Modules/CashManagementController.php:150-177`
- Modify: `app/Http/Controllers/Modules/PettyCashController.php:20-24` (constructor), `:142-167` (method)
- Modify: `routes/web.php:137,152,153`
- Test: `php artisan test tests/Feature/Libraries/FundManagementTest.php tests/Feature/Notifications/`

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing new — this task only deletes. Confirms for later tasks that these routes/methods no longer exist, so Tasks 2 and 3 must not reference them.

- [ ] **Step 1: Confirm nothing else calls the three routes being removed**

Run:
```bash
grep -rn "petty-cash/funds'" /Users/radzmilalvarez/Desktop/laravel_projects/brt-software/resources/js
grep -rn "petty-cash/funds/\${" /Users/radzmilalvarez/Desktop/laravel_projects/brt-software/resources/js
grep -rn "petty-cash/funds/.*top-up" /Users/radzmilalvarez/Desktop/laravel_projects/brt-software/resources/js
```
Expected: only `CashManagement.vue` (for the first two patterns, POST/PUT `/accounting/petty-cash/funds`) and only `PettyCash.vue` (for the top-up pattern). If any other file shows up, STOP — do not proceed until Tasks 2/3 have removed those calls too.

- [ ] **Step 2: Remove `storeFund` and `updateFund` from `CashManagementController.php`**

In `app/Http/Controllers/Modules/CashManagementController.php`, delete lines 150-177 (the two full methods, including their surrounding blank lines so exactly one blank line separates `destroyFundTransfer` from `storePettyCashTransaction`):

```php
    public function storeFund(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:100',
            'gl_code'         => 'required|string|max:20|unique:petty_cash_funds,gl_code',
            'initial_balance' => 'nullable|numeric|min:0.01',
            'bank_account_id' => 'nullable|integer|exists:bank_accounts,id',
        ]);

        $fund = $this->service->createFund($data);

        return response()->json(['message' => 'Petty cash fund created.', 'data' => $fund]);
    }

    public function updateFund(Request $request, int $id)
    {
        $fund = \App\Models\PettyCashFund::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $fund->update([
            'name' => $data['name'],
        ]);

        return response()->json(['message' => 'Fund updated.', 'data' => $fund->fresh()]);
    }

```

- [ ] **Step 3: Remove `topUpFund` from `PettyCashController.php`**

In `app/Http/Controllers/Modules/PettyCashController.php`, delete lines 142-167 (the full method plus its trailing blank line, so `storeVoucher`'s closing brace is followed directly by `voidVoucher`):

```php
    public function topUpFund(Request $request, int $id)
    {
        $data = $request->validate([
            'amount'         => 'required|numeric|min:0.01',
            'top_up_date'    => 'required|date',
            'bank_account_id'=> 'nullable|integer|exists:bank_accounts,id',
            'notes'          => 'nullable|string|max:300',
        ]);

        $fund = PettyCashFund::findOrFail($id);
        $amount = round((float) $data['amount'], 2);

        $this->cashManagement->addTransaction($fund, [
            'type' => 'replenishment',
            'amount' => $amount,
            'transaction_date' => $data['top_up_date'],
            'bank_account_id' => $data['bank_account_id'] ?? null,
            'source_type' => !empty($data['bank_account_id']) ? 'bank' : null,
            'description' => $data['notes'] ?? null,
        ]);

        return response()->json([
            'message' => '₱' . number_format($amount, 2) . ' added to ' . $fund->name . '.',
            'fund'    => $this->formatFund($fund->fresh(['custodian'])),
        ]);
    }

```

- [ ] **Step 4: Remove the now-unused `CashManagementService $cashManagement` constructor dependency from `PettyCashController`**

Run to confirm it's truly unused after Step 3:
```bash
grep -n "cashManagement" /Users/radzmilalvarez/Desktop/laravel_projects/brt-software/app/Http/Controllers/Modules/PettyCashController.php
```
Expected: only the constructor property declaration line remains (no `$this->cashManagement->` usages).

Change the constructor from:
```php
    public function __construct(
        protected SeriesService $series,
        protected CashManagementService $cashManagement,
        protected JournalEntryService $journal,
        protected NotificationService $notificationService,
    ) {}
```
to:
```php
    public function __construct(
        protected SeriesService $series,
        protected JournalEntryService $journal,
        protected NotificationService $notificationService,
    ) {}
```

Also remove the now-unused import — check first:
```bash
grep -n "^use App\\\\Services\\\\Accounting\\\\CashManagementService;" /Users/radzmilalvarez/Desktop/laravel_projects/brt-software/app/Http/Controllers/Modules/PettyCashController.php
```
Remove that `use` line.

- [ ] **Step 5: Remove the three dead routes in `routes/web.php`**

Delete line 137:
```php
        Route::post('/accounting/petty-cash/funds/{id}/top-up', [App\Http\Controllers\Modules\PettyCashController::class, 'topUpFund']);
```

Delete lines 152-153:
```php
        Route::post('/accounting/petty-cash/funds', [App\Http\Controllers\Modules\CashManagementController::class, 'storeFund']);
        Route::put('/accounting/petty-cash/funds/{id}', [App\Http\Controllers\Modules\CashManagementController::class, 'updateFund']);
```

- [ ] **Step 6: Lint the three PHP files**

Run:
```bash
php -l /Users/radzmilalvarez/Desktop/laravel_projects/brt-software/app/Http/Controllers/Modules/CashManagementController.php
php -l /Users/radzmilalvarez/Desktop/laravel_projects/brt-software/app/Http/Controllers/Modules/PettyCashController.php
php -l /Users/radzmilalvarez/Desktop/laravel_projects/brt-software/routes/web.php
```
Expected: `No syntax errors detected` for all three.

- [ ] **Step 7: Confirm the routes are actually gone**

Run:
```bash
cd /Users/radzmilalvarez/Desktop/laravel_projects/brt-software && php artisan route:list --path=petty-cash
```
Expected: no row for `POST petty-cash/funds`, no row for `PUT petty-cash/funds/{id}`, no row for `POST petty-cash/funds/{id}/top-up`. The `GET petty-cash`, `POST petty-cash/vouchers`, `DELETE petty-cash/vouchers/{id}` rows must still be present.

- [ ] **Step 8: Run the existing test suites to confirm no regressions**

Run:
```bash
cd /Users/radzmilalvarez/Desktop/laravel_projects/brt-software && php artisan test tests/Feature/Libraries/FundManagementTest.php tests/Feature/Notifications/LowBalanceFundClassTest.php tests/Feature/Notifications/LowBalanceCashManagementTest.php
```
Expected: all tests pass (these exercise `FundClass`/`CashManagementService` directly, not the removed controller methods, so they must be unaffected).

- [ ] **Step 9: Commit**

```bash
cd /Users/radzmilalvarez/Desktop/laravel_projects/brt-software
git add app/Http/Controllers/Modules/CashManagementController.php app/Http/Controllers/Modules/PettyCashController.php routes/web.php
git commit -m "$(cat <<'EOF'
refactor: remove dead petty-cash fund admin routes/controllers

storeFund, updateFund, and topUpFund had no callers left once the
duplicate fund-admin UI is removed from Cash Management and the
Petty Cash vouchers page in favor of the Petty Cash Funds page.
EOF
)"
```

---

### Task 2: Remove duplicate fund-admin UI from `CashManagement.vue`

**Files:**
- Modify: `resources/js/Pages/Modules/Accounting/CashManagement.vue`

**Interfaces:**
- Consumes: nothing from Task 1 directly (frontend calls being removed match the backend routes removed in Task 1).
- Produces: a "Manage Funds" link to `/accounting/funds` that Task 3 does NOT depend on (each page gets its own link).

- [ ] **Step 1: Remove the "New Fund" / "Replenish Fund" buttons and add a "Manage Funds" link**

Find this block (around line 405-421):
```html
                <!-- Fund selector + new fund button -->
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <label class="form-label mb-0 fw-semibold">Fund:</label>
                        <select v-model="selectedFundId" class="form-select form-select-sm" style="min-width:220px">
                            <option v-for="f in funds" :key="f.id" :value="f.id">{{ f.name }} ({{ f.balance_formatted }})</option>
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="acct-btn-secondary" @click="openCreateFund">
                            <i class="ri-add-line"></i> New Fund
                        </button>
                        <button class="acct-btn-primary" @click="openAddTransaction('replenishment')" :disabled="!selectedFund">
                            <i class="ri-arrow-down-circle-line"></i> Replenish Fund
                        </button>
                    </div>
                </div>
```

Replace with:
```html
                <!-- Fund selector + manage-funds link -->
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <label class="form-label mb-0 fw-semibold">Fund:</label>
                        <select v-model="selectedFundId" class="form-select form-select-sm" style="min-width:220px">
                            <option v-for="f in funds" :key="f.id" :value="f.id">{{ f.name }} ({{ f.balance_formatted }})</option>
                        </select>
                    </div>
                    <a href="/accounting/funds" class="acct-btn-secondary">
                        <i class="ri-external-link-line"></i> Manage Funds
                    </a>
                </div>
```

- [ ] **Step 2: Remove the rename-pencil button from the fund balance card**

Find (around line 429-436):
```html
                            <div>
                                <p class="fund-name mb-0">{{ selectedFund.name }}</p>
                                <p class="fund-gl mb-0">
                                    GL: {{ selectedFund.gl_code }}
                                    <button class="fund-edit-btn ms-2" @click="openEditFund(selectedFund)" title="Rename fund">
                                        <i class="ri-pencil-line"></i>
                                    </button>
                                </p>
                            </div>
```

Replace with:
```html
                            <div>
                                <p class="fund-name mb-0">{{ selectedFund.name }}</p>
                                <p class="fund-gl mb-0">GL: {{ selectedFund.gl_code }}</p>
                            </div>
```

- [ ] **Step 3: Remove the "No funds yet" empty-state button**

Find (around line 396-401):
```html
            <!-- No funds yet -->
            <div v-if="funds.length === 0 && !pcFundModal.open" class="cm-empty-state mb-3">
                <i class="ri-wallet-3-line"></i>
                <p class="mb-2">No petty cash funds set up</p>
                <button class="acct-btn-primary" @click="openCreateFund">
                    <i class="ri-add-line"></i> Set Up Petty Cash Fund
                </button>
            </div>
```

Replace with:
```html
            <!-- No funds yet -->
            <div v-if="funds.length === 0" class="cm-empty-state mb-3">
                <i class="ri-wallet-3-line"></i>
                <p class="mb-2">No petty cash funds set up</p>
                <a href="/accounting/funds" class="acct-btn-primary">
                    <i class="ri-external-link-line"></i> Set Up in Petty Cash Funds
                </a>
            </div>
```

- [ ] **Step 4: Delete the Create Fund modal, Edit Fund modal, and Add Transaction modal**

Delete the entire block from the `<!-- Create Fund Modal -->` comment through the end of the `<!-- Add Transaction Modal -->` block (this spans from the line containing `<!-- Create Fund Modal -->` down to the closing `</div>` right before `</template>` — three modals back to back with no other content between them). Confirm before deleting:
```bash
grep -n "Create Fund Modal\|Edit Fund Modal\|Add Transaction Modal\|</template>" /Users/radzmilalvarez/Desktop/laravel_projects/brt-software/resources/js/Pages/Modules/Accounting/CashManagement.vue
```
Delete everything from `<!-- Create Fund Modal -->` up to (but not including) the `</template>` line — that removes all three `v-if="pcFundModal.open"`, `v-if="fundEditModal.open"`, and `v-if="pcTxnModal.open"` blocks in one contiguous deletion.

- [ ] **Step 5: Remove the `emptyPcTxnForm` helper function**

Find and delete (in the `<script>` section, above `export default`):
```js
const emptyPcTxnForm = (type = 'replenishment') => ({
    type,
    transaction_date: new Date().toISOString().slice(0, 10),
    amount: '',
    category: '',
    description: '',
    reference_number: '',
    source_type: 'cash',
    bank_account_id: '',
    receipt: null,
});

```

- [ ] **Step 6: Remove the dead `data()` state**

In `data()`, delete these blocks:
```js
            // Petty cash fund creation
            pcFundModal:  { open: false },
            pcFundForm:   { name: '', gl_code: '', initial_balance: '', source_type: 'cash', bank_account_id: '' },
            pcFundErrors: {},
            pcFundSaving: false,

            // Petty cash transaction
            pcTxnModal:  { open: false },
            pcTxnForm:   emptyPcTxnForm(),
            pcTxnErrors: {},
            pcTxnSaving: false,

```
and:
```js
            // Fund edit modal
            fundEditModal:  { open: false },
            fundEditForm:   { name: '' },
            fundEditSaving: false,

            // Approved replenishment requests for the replenish modal
            repRequests:        [],
            repRequestsLoading: false,
            selectedRepRequest: '',
```
(the second block's removal means `data()`'s `return { ... }` now ends right after the `bdSaving: false,` line from the Bank Deposits section — leave that section untouched.)

- [ ] **Step 7: Remove the dead methods**

Delete these method definitions from the `methods: { ... }` block:
```js
        openCreateFund() {
            this.pcFundForm   = { name: '', gl_code: '', initial_balance: '', source_type: 'cash', bank_account_id: '' };
            this.pcFundErrors = {};
            this.pcFundModal.open = true;
        },
        async submitFund() {
            this.pcFundSaving = true;
            this.pcFundErrors = {};
            try {
                const payload = {
                    name: this.pcFundForm.name,
                    gl_code: this.pcFundForm.gl_code,
                    initial_balance: this.pcFundForm.initial_balance,
                    bank_account_id: this.pcFundForm.source_type === 'bank' ? this.pcFundForm.bank_account_id : null,
                };
                const res = await axios.post('/accounting/petty-cash/funds', payload);
                this.pcFundModal.open = false;
                router.reload({ preserveScroll: true });
            } catch (e) {
                if (e.response?.status === 422) this.pcFundErrors = e.response.data.errors || {};
            } finally {
                this.pcFundSaving = false;
            }
        },
        async openAddTransaction(type) {
            this.pcTxnForm        = emptyPcTxnForm(type);
            this.pcTxnErrors      = {};
            this.selectedRepRequest = '';
            this.repRequests      = [];
            this.pcTxnModal.open  = true;
            this.$nextTick(() => {
                if (this.$refs.pcReceiptInput) this.$refs.pcReceiptInput.value = '';
            });

            if (type === 'replenishment' && this.selectedFundId) {
                this.repRequestsLoading = true;
                try {
                    const { data } = await axios.get('/replenishments', {
                        params: { fund_id: this.selectedFundId, status: 'approved', count: 100 },
                    });
                    this.repRequests = data.data ?? [];
                } catch (e) {
                    this.repRequests = [];
                } finally {
                    this.repRequestsLoading = false;
                }
            }
        },
        selectRepRequest() {
            const req = this.repRequests.find(r => r.id == this.selectedRepRequest);
            if (!req) return;
            this.pcTxnForm.amount      = req.total_amount;
            this.pcTxnForm.description = `Replenishment for ${req.reference_no}`;
        },
        onPcReceiptChange(e) {
            this.pcTxnForm.receipt = e.target.files[0] || null;
        },
        async submitTransaction() {
            this.pcTxnSaving = true;
            this.pcTxnErrors = {};
            try {
                const base = {
                    ...this.pcTxnForm,
                    fund_id: this.selectedFundId,
                    replenishment_request_id: this.selectedRepRequest || null,
                };
                const payload = this.pcTxnForm.receipt
                    ? (() => {
                        const fd = new FormData();
                        Object.entries(base).forEach(([k, v]) => {
                            if (k === 'receipt' && v instanceof File) fd.append('receipt', v);
                            else if (v !== null && v !== undefined) fd.append(k, v);
                        });
                        return fd;
                    })()
                    : base;

                const headers = this.pcTxnForm.receipt ? { 'Content-Type': 'multipart/form-data' } : {};
                await axios.post('/accounting/petty-cash/transactions', payload, { headers });
                this.pcTxnModal.open = false;
                router.reload({ preserveScroll: true });
            } catch (e) {
                if (e.response?.status === 422) this.pcTxnErrors = e.response.data.errors || {};
            } finally {
                this.pcTxnSaving = false;
            }
        },
```
and, in the `// ── Fund Edit ─────` section:
```js
        openEditFund(fund) {
            this.fundEditForm = { name: fund.name };
            this.fundEditModal.open = true;
        },
        async submitEditFund() {
            this.fundEditSaving = true;
            try {
                await axios.put(`/accounting/petty-cash/funds/${this.selectedFundId}`, this.fundEditForm);
                this.fundEditModal.open = false;
                router.reload({ preserveScroll: true });
            } catch (e) {
                // validation errors not expected here; reload will re-sync
            } finally {
                this.fundEditSaving = false;
            }
        },
```
Keep `confirmDeleteTransaction` (it's the ledger's delete/reverse action, which stays per spec).

- [ ] **Step 8: Grep-check for orphaned references**

Run:
```bash
grep -n "pcFundModal\|pcFundForm\|pcFundErrors\|pcFundSaving\|pcTxnModal\|pcTxnForm\|pcTxnErrors\|pcTxnSaving\|fundEditModal\|fundEditForm\|fundEditSaving\|repRequests\|repRequestsLoading\|selectedRepRequest\|openCreateFund\|submitFund\|openAddTransaction\|selectRepRequest\|onPcReceiptChange\|submitTransaction\|openEditFund\|submitEditFund\|emptyPcTxnForm" /Users/radzmilalvarez/Desktop/laravel_projects/brt-software/resources/js/Pages/Modules/Accounting/CashManagement.vue
```
Expected: no output (everything removed cleanly, both template and script references).

- [ ] **Step 9: Build and smoke-test**

Run:
```bash
cd /Users/radzmilalvarez/Desktop/laravel_projects/brt-software && npm run build
```
Expected: build succeeds with no Vue compile errors.

- [ ] **Step 10: Commit**

```bash
cd /Users/radzmilalvarez/Desktop/laravel_projects/brt-software
git add resources/js/Pages/Modules/Accounting/CashManagement.vue
git commit -m "$(cat <<'EOF'
refactor: remove duplicate fund admin UI from Cash Management page

Create/Top-up/Rename Fund now only live on the Petty Cash Funds page.
The Petty Cash tab keeps its fund selector and transaction ledger as
a read-only view, with a "Manage Funds" link to /accounting/funds.
EOF
)"
```

---

### Task 3: Remove duplicate top-up UI from `PettyCash.vue`

**Files:**
- Modify: `resources/js/Pages/Modules/Accounting/PettyCash.vue`

**Interfaces:**
- Consumes: nothing from Tasks 1/2.
- Produces: nothing consumed elsewhere.

- [ ] **Step 1: Remove the "Top Up" button, add a "Manage Funds" link**

Find (around line 27-52):
```html
            <!-- ── Tab: Fund ───────────────────────────────────────── -->
            <template v-if="tab === 'fund'">

                <div v-if="funds.length === 0" class="acct-empty-notice">
                    <i class="ri-wallet-3-line"></i>
                    No petty cash fund set up yet.
                    Go to <strong>Accounting → Petty Cash Funds</strong> to create one.
                </div>

                <div v-else v-for="fund in funds" :key="fund.id" class="library-card mb-3">
                    <div class="library-card-header">
                        <div class="d-flex align-items-center gap-3">
                            <div class="header-icon"><i class="ri-wallet-3-line"></i></div>
                            <div>
                                <h4 class="header-title mb-0">{{ fund.name }}</h4>
                                <p class="header-subtitle mb-0">GL: {{ fund.gl_code }} &nbsp;·&nbsp; Custodian: {{ fund.custodian_name || 'Unassigned' }}</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span v-if="fund.low_balance" class="pc-low-badge">
                                <i class="ri-error-warning-line"></i> Low Balance
                            </span>
                            <button class="acct-btn-primary" @click="openTopUp(fund)">
                                <i class="ri-add-circle-line"></i> Top Up
                            </button>
                        </div>
                    </div>
```

Replace with:
```html
            <!-- ── Tab: Fund ───────────────────────────────────────── -->
            <template v-if="tab === 'fund'">

                <div v-if="funds.length === 0" class="acct-empty-notice">
                    <i class="ri-wallet-3-line"></i>
                    No petty cash fund set up yet.
                    Go to <strong>Accounting → Petty Cash Funds</strong> to create one.
                </div>

                <div v-if="funds.length > 0" class="d-flex justify-content-end mb-2">
                    <a href="/accounting/funds" class="acct-btn-secondary">
                        <i class="ri-external-link-line"></i> Manage Funds
                    </a>
                </div>

                <div v-for="fund in funds" :key="fund.id" class="library-card mb-3">
                    <div class="library-card-header">
                        <div class="d-flex align-items-center gap-3">
                            <div class="header-icon"><i class="ri-wallet-3-line"></i></div>
                            <div>
                                <h4 class="header-title mb-0">{{ fund.name }}</h4>
                                <p class="header-subtitle mb-0">GL: {{ fund.gl_code }} &nbsp;·&nbsp; Custodian: {{ fund.custodian_name || 'Unassigned' }}</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span v-if="fund.low_balance" class="pc-low-badge">
                                <i class="ri-error-warning-line"></i> Low Balance
                            </span>
                        </div>
                    </div>
```

Note: the original `v-for` div used `v-else` (paired with the empty-state `v-if`). That pairing is broken here since a new element (`v-if="funds.length > 0"` link) sits between them. The `v-for` div above intentionally has no `v-if`/`v-else` at all — with 0 funds it simply renders nothing, which matches the old behavior.

- [ ] **Step 2: Delete the Top Up Fund modal**

Delete this entire block (the `<!-- ── Top Up Fund Modal ── -->` comment through its closing `</div>`, right before the final `</div></template>` of the file):
```html
        <!-- ── Top Up Fund Modal ──────────────────────────────────── -->
        <div v-if="topUpModal.open" class="modal-overlay active" @click.self="topUpModal.open = false">
            <div class="modal-container modal-sm" @click.stop>
                <div class="modal-header">
                    <div class="modal-header-icon"><i class="ri-add-circle-line"></i></div>
                    <div>
                        <h4 class="mb-0">Top Up Fund</h4>
                        <p class="header-subtitle mb-0">{{ topUpForm.fund?.name }}</p>
                    </div>
                    <button class="close-btn ms-auto" @click="topUpModal.open = false"><i class="ri-close-line fs-20"></i></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                            <input v-model="topUpForm.amount" type="number" step="0.01" min="0.01" class="form-control" placeholder="0.00" />
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                            <input v-model="topUpForm.top_up_date" type="date" class="form-control" />
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Source Bank Account <span class="text-muted fw-normal">(optional)</span></label>
                            <select v-model="topUpForm.bank_account_id" class="form-select">
                                <option value="">Cash on Hand</option>
                                <option v-for="b in bankAccounts" :key="b.id" :value="b.id">{{ b.label }}</option>
                            </select>
                            <small class="text-muted">If blank, the source will be recorded as Cash in Bank (general).</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <input v-model="topUpForm.notes" type="text" class="form-control" placeholder="e.g. Monthly replenishment" maxlength="300" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="topUpModal.open = false">Cancel</button>
                    <button class="btn btn-save" @click="doTopUp" :disabled="topUpModal.saving">
                        <span v-if="topUpModal.saving"><i class="ri-loader-4-line spin me-1"></i>Processing…</span>
                        <span v-else><i class="ri-add-circle-line me-1"></i>Top Up Fund</span>
                    </button>
                </div>
            </div>
        </div>

```

- [ ] **Step 3: Remove the `topUpModal`/`topUpForm` state**

In `data()`, delete:
```js
            // Top Up modal
            topUpModal: { open: false, saving: false },
            topUpForm:  { fund: null, amount: '', top_up_date: new Date().toISOString().slice(0, 10), bank_account_id: '', notes: '' },

```

- [ ] **Step 4: Remove the `openTopUp`/`doTopUp` methods**

In `methods: { ... }`, delete:
```js
        openTopUp(fund) {
            this.topUpForm = { fund, amount: '', top_up_date: new Date().toISOString().slice(0, 10), bank_account_id: '', notes: '' };
            this.topUpModal = { open: true, saving: false };
        },
        async doTopUp() {
            if (!this.topUpForm.amount || this.topUpForm.amount <= 0) { alert('Enter a valid amount.'); return; }
            if (!this.topUpForm.top_up_date) { alert('Date is required.'); return; }
            this.topUpModal.saving = true;
            try {
                const res = await axios.post(`/accounting/petty-cash/funds/${this.topUpForm.fund.id}/top-up`, {
                    amount:          this.topUpForm.amount,
                    top_up_date:     this.topUpForm.top_up_date,
                    bank_account_id: this.topUpForm.bank_account_id || null,
                    notes:           this.topUpForm.notes || null,
                });
                const idx = this.localFunds.findIndex(f => f.id === this.topUpForm.fund.id);
                if (idx !== -1) this.localFunds[idx] = res.data.fund;
                this.topUpModal.open = false;
            } catch (err) {
                alert(err.response?.data?.message || 'Top-up failed.');
            } finally {
                this.topUpModal.saving = false;
            }
        },
```
(this is the last two methods before the closing `},` of `methods: { ... }` — after deletion, `submitApproval`'s closing `},` is followed directly by the `methods` block's closing `},`.)

- [ ] **Step 5: Grep-check for orphaned references**

Run:
```bash
grep -n "topUpModal\|topUpForm\|openTopUp\|doTopUp" /Users/radzmilalvarez/Desktop/laravel_projects/brt-software/resources/js/Pages/Modules/Accounting/PettyCash.vue
```
Expected: no output.

- [ ] **Step 6: Build and smoke-test**

Run:
```bash
cd /Users/radzmilalvarez/Desktop/laravel_projects/brt-software && npm run build
```
Expected: build succeeds with no Vue compile errors.

- [ ] **Step 7: Commit**

```bash
cd /Users/radzmilalvarez/Desktop/laravel_projects/brt-software
git add resources/js/Pages/Modules/Accounting/PettyCash.vue
git commit -m "$(cat <<'EOF'
refactor: remove duplicate top-up UI from Petty Cash vouchers page

Top-up now only lives on the Petty Cash Funds page. The Fund tab
here becomes a read-only reconciliation view with a "Manage Funds"
link to /accounting/funds.
EOF
)"
```

---

### Task 4: Final verification

**Files:** none (verification only)

- [ ] **Step 1: Run the full relevant backend test suite**

```bash
cd /Users/radzmilalvarez/Desktop/laravel_projects/brt-software
php artisan test tests/Feature/Libraries/FundManagementTest.php tests/Feature/Notifications/ tests/Feature/Expenses/
```
Expected: same pass/fail counts as the pre-existing baseline (the 5 `Expenses/` failures already present before this session's work are unrelated route/auth issues — confirm no *new* failures appear).

- [ ] **Step 2: Full asset build**

```bash
cd /Users/radzmilalvarez/Desktop/laravel_projects/brt-software && npm run build
```
Expected: `✓ built in` with no errors.

- [ ] **Step 3: Manual browser smoke test**

With `php artisan serve` and `npm run dev` running:
1. Visit `/accounting/cash-management` → Petty Cash tab. Confirm: no "New Fund"/"Replenish Fund" buttons, no rename-pencil icon, ledger table still shows transactions with working delete/reverse, "Manage Funds" link navigates to `/accounting/funds`.
2. Visit `/accounting/petty-cash` → Fund tab. Confirm: no "Top Up" button, reconciliation figures (Fixed Amount / Unreimbursed Vouchers / Cash on Hand) still render, "Manage Funds" link navigates to `/accounting/funds`. Switch to Vouchers and Replenishments sub-tabs and confirm they still work (record a voucher, view a replenishment).
3. Visit `/accounting/funds`. Confirm: Add Fund, Top-up, Adjust Balance, Edit, Toggle Active all still work exactly as before (this page was not modified).

- [ ] **Step 4: Update the design spec status**

In `docs/superpowers/specs/2026-07-03-petty-cash-ui-consolidation-design.md`, no change needed — status is already "Approved" and the spec was written to match what was actually built.
