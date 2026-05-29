<template>
    <div>

                    <!-- Summary cards -->
                    <div class="row g-3 mb-3">
                        <div class="col-sm-4">
                            <div class="acct-stat-card">
                                <div class="acct-stat-icon"><i class="ri-draft-line"></i></div>
                                <div>
                                    <p class="acct-stat-label">Pending Entries</p>
                                    <h4 class="acct-stat-value">{{ stats.pending_entries }}</h4>
                                    <p class="acct-stat-note">Awaiting posting</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="acct-stat-card">
                                <div class="acct-stat-icon"><i class="ri-file-check-line"></i></div>
                                <div>
                                    <p class="acct-stat-label">Active Originals</p>
                                    <h4 class="acct-stat-value">{{ stats.unreconciled_items }}</h4>
                                    <p class="acct-stat-note">Posted originals</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="acct-stat-card">
                                <div class="acct-stat-icon"><i class="ri-arrow-go-back-line"></i></div>
                                <div>
                                    <p class="acct-stat-label">Reversals Logged</p>
                                    <h4 class="acct-stat-value">{{ stats.generated_reports }}</h4>
                                    <p class="acct-stat-note">Reversal journals</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filter bar -->
                    <div class="library-card mb-3">
                        <div class="library-card-body py-2">
                            <div class="journal-filter-bar">
                                <div class="journal-filter-field journal-filter-search">
                                    <i class="ri-search-line"></i>
                                    <input v-model="filter.keyword" type="text" class="jf-input" placeholder="Search journal number, memo…" />
                                </div>
                                <div class="journal-filter-field">
                                    <i class="ri-filter-3-line"></i>
                                    <select v-model="filter.status" class="jf-input">
                                        <option :value="null">All Statuses</option>
                                        <option value="posted">Posted</option>
                                        <option value="reversed">Reversed</option>
                                        <option value="reversal_posted">Reversal Posted</option>
                                    </select>
                                </div>
                                <div class="journal-filter-field">
                                    <i class="ri-file-list-3-line"></i>
                                    <select v-model="filter.entry_type" class="jf-input">
                                        <option :value="null">All Entry Types</option>
                                        <option v-for="type in entryTypes" :key="type" :value="type">
                                            {{ formatLabel(type) }}
                                        </option>
                                    </select>
                                </div>
                                <div class="journal-filter-field">
                                    <i class="ri-calendar-line"></i>
                                    <input v-model="filter.date_from" type="date" class="jf-input" />
                                </div>
                                <div class="journal-filter-field">
                                    <i class="ri-calendar-check-line"></i>
                                    <input v-model="filter.date_to" type="date" class="jf-input" />
                                </div>
                                <button v-if="hasActiveFilters" class="jf-clear-btn" @click="clearFilters">
                                    <i class="ri-close-circle-line"></i>
                                    Clear
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Compatibility banner -->
                    <div v-if="!journalFeatures.reversal_ready" class="compatibility-banner mb-3">
                        <div class="compat-icon"><i class="ri-error-warning-line"></i></div>
                        <div>
                            <p class="compat-title mb-1">Migration Needed For Full Reversal Tracking</p>
                            <p class="compat-text mb-0">{{ journalFeatures.compatibility_message }}</p>
                        </div>
                    </div>

                    <!-- Journal table -->
                    <div class="library-card">
                        <div class="library-card-header">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="header-icon"><i class="ri-book-open-line"></i></div>
                                    <div>
                                        <h4 class="header-title mb-0">Journal Entries &amp; Reversals</h4>
                                        <p class="header-subtitle mb-0">Auto-generated postings, source transactions, and reversal links.</p>
                                    </div>
                                </div>
                                <button type="button" class="create-btn" @click="openNewEntry">
                                    <i class="ri-add-line"></i> New Manual Entry
                                </button>
                            </div>
                        </div>
                        <div class="library-card-body p-0">
                            <div class="table-responsive">
                                <table class="table align-middle journal-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Journal No.</th>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Source</th>
                                            <th>Status</th>
                                            <th>Original / Reversal Link</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="lists.length === 0">
                                            <td colspan="7" class="empty-cell">
                                                <div class="empty-state">
                                                    <i class="ri-book-open-line"></i>
                                                    <p class="mb-0">No journal entries found.</p>
                                                </div>
                                            </td>
                                        </tr>
                                        <template v-for="(entry, index) in lists" :key="entry.id">
                                            <tr class="journal-row" @click="toggleExpanded(entry.id)">
                                                <td>{{ index + 1 }}</td>
                                                <td>
                                                    <div class="journal-primary">
                                                        <strong>{{ entry.journal_number }}</strong>
                                                        <small>{{ entry.memo || 'No memo' }}</small>
                                                    </div>
                                                </td>
                                                <td>{{ entry.entry_date }}</td>
                                                <td>{{ entry.entry_type }}</td>
                                                <td>
                                                    <div class="source-cell">
                                                        <span class="source-type">{{ formatSourceType(entry.source_type) }}</span>
                                                        <span class="source-ref">{{ entry.source_ref || ('#' + entry.source_id) }}</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="status-chip" :class="statusClass(entry.status)">
                                                        {{ formatLabel(entry.status) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="link-stack">
                                                        <span v-if="entry.reversal_of" class="link-chip reversal-origin">
                                                            Reversal of {{ entry.reversal_of.journal_number }}
                                                        </span>
                                                        <span v-else-if="entry.reversals.length" class="link-chip reversal-has">
                                                            {{ entry.reversals.length }} reversal(s)
                                                        </span>
                                                        <span v-else class="link-chip link-neutral">Original only</span>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr v-if="expandedRows.includes(entry.id)" class="details-row">
                                                <td colspan="7">
                                                    <div class="details-grid">
                                                        <div class="detail-card">
                                                            <h6 class="detail-title">Entry Details</h6>
                                                            <p><strong>Memo:</strong> {{ entry.memo || '-' }}</p>
                                                            <p><strong>Source:</strong> {{ entry.source_type }} #{{ entry.source_id || '-' }}</p>
                                                            <p><strong>Reversed At:</strong> {{ entry.reversed_at || '-' }}</p>
                                                            <p><strong>Reason:</strong> {{ entry.reversal_reason || '-' }}</p>
                                                        </div>
                                                        <div class="detail-card">
                                                            <h6 class="detail-title">Linked Journals</h6>
                                                            <p v-if="entry.reversal_of">
                                                                <strong>Original:</strong> {{ entry.reversal_of.journal_number }} on {{ entry.reversal_of.entry_date }}
                                                            </p>
                                                            <div v-if="entry.reversals.length">
                                                                <p class="mb-2"><strong>Reversals:</strong></p>
                                                                <div v-for="reversal in entry.reversals" :key="reversal.id" class="reversal-item">
                                                                    <span>{{ reversal.journal_number }}</span>
                                                                    <small>{{ reversal.entry_date }} • {{ formatLabel(reversal.status) }}</small>
                                                                </div>
                                                            </div>
                                                            <p v-if="!entry.reversal_of && !entry.reversals.length" class="mb-0">No linked reversal journal.</p>
                                                        </div>
                                                    </div>
                                                    <div class="line-table-wrap">
                                                        <table class="table line-table mb-0">
                                                            <thead>
                                                                <tr>
                                                                    <th>Account</th>
                                                                    <th>Code</th>
                                                                    <th class="text-center">Type</th>
                                                                    <th class="text-end">Debit</th>
                                                                    <th class="text-end">Credit</th>
                                                                    <th>Description</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <tr v-for="line in entry.lines" :key="line.id">
                                                                    <td>{{ line.account_name || '-' }}</td>
                                                                    <td>{{ line.account_code || '-' }}</td>
                                                                    <td class="text-center">
                                                                        <span class="line-type-chip" :class="line.line_type">
                                                                            {{ line.line_type === 'debit' ? 'DR' : 'CR' }}
                                                                        </span>
                                                                    </td>
                                                                    <td class="text-end text-debit">{{ line.line_type === 'debit' ? '₱' + line.amount : '' }}</td>
                                                                    <td class="text-end text-credit">{{ line.line_type === 'credit' ? '₱' + line.amount : '' }}</td>
                                                                    <td>{{ line.description || '-' }}</td>
                                                                </tr>
                                                            </tbody>
                                                            <tfoot>
                                                                <tr class="lines-totals-row">
                                                                    <td colspan="3" class="text-end fw-semibold">Totals</td>
                                                                    <td class="text-end text-debit fw-bold">₱{{ lineDebitTotal(entry.lines) }}</td>
                                                                    <td class="text-end text-credit fw-bold">₱{{ lineCreditTotal(entry.lines) }}</td>
                                                                    <td>
                                                                        <span class="balance-chip" :class="isBalanced(entry.lines) ? 'balanced' : 'unbalanced'">
                                                                            {{ isBalanced(entry.lines) ? 'Balanced ✓' : 'Unbalanced !' }}
                                                                        </span>
                                                                    </td>
                                                                </tr>
                                                            </tfoot>
                                                        </table>
                                                    </div>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                            <div class="px-3 pb-3">
                                <Pagination
                                    v-if="meta && meta.links"
                                    :lists="lists.length"
                                    :links="links"
                                    :pagination="meta"
                                    @fetch="fetch"
                                />
                            </div>
                        </div>
                    </div>

        <!-- Manual Journal Entry Modal -->
        <div v-show="showEntryModal" class="modal-overlay" :class="{ active: showEntryModal }" @click.self="closeEntryModal">
            <div class="modal-container modal-lg" @click.stop>
                <div class="modal-header">
                    <div class="d-flex align-items-center gap-2">
                        <div class="modal-header-icon">
                            <i class="ri-book-open-line"></i>
                        </div>
                        <div>
                            <p class="modal-kicker mb-0">Accounting</p>
                            <h5 class="mb-0">New Manual Journal Entry</h5>
                        </div>
                    </div>
                    <button class="close-btn" @click="closeEntryModal">
                        <i class="ri-close-line fs-20"></i>
                    </button>
                </div>

                <div class="modal-body p-4">
                    <!-- Entry header fields -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Entry Date <span class="text-danger">*</span></label>
                            <input v-model="entryForm.entry_date" type="date" class="form-control"
                                :class="{ 'is-invalid': entryErrors.entry_date }" />
                            <div v-if="entryErrors.entry_date" class="invalid-feedback">{{ entryErrors.entry_date[0] }}</div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Memo</label>
                            <input v-model="entryForm.memo" type="text" class="form-control"
                                placeholder="Brief description of this entry…" maxlength="500" />
                        </div>
                    </div>

                    <!-- Balance indicator -->
                    <div class="balance-indicator mb-3" :class="entryIsBalanced ? 'balanced' : 'unbalanced'">
                        <i :class="entryIsBalanced ? 'ri-checkbox-circle-fill' : 'ri-error-warning-fill'"></i>
                        <span>
                            Debits: <strong>{{ formatMoney(entryDebitTotal) }}</strong>
                            &nbsp;|&nbsp;
                            Credits: <strong>{{ formatMoney(entryCreditTotal) }}</strong>
                            &nbsp;—&nbsp;
                            <span v-if="entryIsBalanced">Balanced ✓</span>
                            <span v-else>Difference: {{ formatMoney(Math.abs(entryDebitTotal - entryCreditTotal)) }}</span>
                        </span>
                    </div>

                    <!-- Line items -->
                    <div class="line-items-wrap">
                        <table class="table entry-lines-table mb-2">
                            <thead>
                                <tr>
                                    <th style="width:38%">Account</th>
                                    <th style="width:12%">DR / CR</th>
                                    <th style="width:16%">Amount</th>
                                    <th>Description</th>
                                    <th style="width:44px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(line, i) in entryForm.lines" :key="i">
                                    <td>
                                        <select v-model="line.account_id" class="form-select form-select-sm"
                                            :class="{ 'is-invalid': entryErrors['lines.' + i + '.account_id'] }">
                                            <option :value="null">— Select account —</option>
                                            <optgroup v-for="type in accountTypes" :key="type" :label="capitalize(type)">
                                                <option v-for="acct in accountsByType[type]" :key="acct.id" :value="acct.id">
                                                    {{ acct.code }} — {{ acct.name }}
                                                </option>
                                            </optgroup>
                                        </select>
                                    </td>
                                    <td>
                                        <div class="dr-cr-toggle">
                                            <button type="button" class="dr-btn" :class="{ active: line.line_type === 'debit' }"
                                                @click="line.line_type = 'debit'">DR</button>
                                            <button type="button" class="cr-btn" :class="{ active: line.line_type === 'credit' }"
                                                @click="line.line_type = 'credit'">CR</button>
                                        </div>
                                    </td>
                                    <td>
                                        <input v-model.number="line.amount" type="number" min="0.01" step="0.01"
                                            class="form-control form-control-sm text-end"
                                            :class="{ 'is-invalid': entryErrors['lines.' + i + '.amount'] }"
                                            placeholder="0.00" />
                                    </td>
                                    <td>
                                        <input v-model="line.description" type="text" class="form-control form-control-sm"
                                            placeholder="Optional note…" maxlength="255" />
                                    </td>
                                    <td>
                                        <button type="button" class="remove-line-btn" @click="removeLine(i)"
                                            :disabled="entryForm.lines.length <= 2" title="Remove line">
                                            <i class="ri-delete-bin-6-line"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div v-if="entryErrors.lines" class="text-danger small mb-2">{{ entryErrors.lines[0] }}</div>

                        <button type="button" class="add-line-btn" @click="addLine">
                            <i class="ri-add-line"></i> Add Line
                        </button>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" @click="closeEntryModal">Cancel</button>
                    <button type="button" class="btn btn-post" :disabled="entrySaving" @click="submitEntry">
                        <span v-if="entrySaving"><i class="ri-loader-4-line spin me-1"></i>Posting…</span>
                        <span v-else><i class="ri-send-plane-line me-1"></i>Post Entry</span>
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>

<script>
import axios from "axios";
import _ from "lodash";
import MainLayout from "@/Shared/Layouts/Main.vue";
import AccountingLayout from "@/Pages/Modules/Accounting/AccountingLayout.vue";
import Pagination from "@/Shared/Components/Pagination.vue";

export default {
    layout: [MainLayout, AccountingLayout],
    components: { Pagination },
    props: {
        stats: {
            type: Object,
            default: () => ({
                open_periods: 0,
                pending_entries: 0,
                unreconciled_items: 0,
                generated_reports: 0,
            }),
        },
        accounts: { type: Array, default: () => [] },
        journalFeatures: {
            type: Object,
            default: () => ({
                reversal_ready: true,
                compatibility_message: null,
            }),
        },
    },
    data() {
        return {
            lists: [],
            meta: {},
            links: {},
            expandedRows: [],
            filter: {
                keyword: null,
                status: null,
                entry_type: null,
                date_from: null,
                date_to: null,
            },
            showEntryModal: false,
            entrySaving: false,
            entryErrors: {},
            entryForm: {
                entry_date: '',
                memo: '',
                lines: [
                    { account_id: null, line_type: 'debit',  amount: null, description: '' },
                    { account_id: null, line_type: 'credit', amount: null, description: '' },
                ],
            },
            accountTypes: ['asset', 'liability', 'equity', 'revenue', 'expense'],
            entryTypes: [
                'sales_revenue',
                'inventory_out',
                'receipt_collection',
                'refund_receipt',
                'sales_return_revenue',
                'sales_return_inventory',
                'expense_release',
                'purchase_receipt',
                'purchase_receipt_cash',
                'inventory_adjustment',
                'reversal',
            ],
        };
    },
    computed: {
        hasActiveFilters() {
            return !!(this.filter.keyword || this.filter.status || this.filter.entry_type || this.filter.date_from || this.filter.date_to);
        },
        accountsByType() {
            const grouped = {};
            for (const type of this.accountTypes) {
                grouped[type] = this.accounts.filter(a => a.type === type);
            }
            return grouped;
        },
        entryDebitTotal() {
            return this.entryForm.lines
                .filter(l => l.line_type === 'debit')
                .reduce((s, l) => s + (parseFloat(l.amount) || 0), 0);
        },
        entryCreditTotal() {
            return this.entryForm.lines
                .filter(l => l.line_type === 'credit')
                .reduce((s, l) => s + (parseFloat(l.amount) || 0), 0);
        },
        entryIsBalanced() {
            return this.entryDebitTotal > 0 && Math.abs(this.entryDebitTotal - this.entryCreditTotal) < 0.01;
        },
    },
    watch: {
        'filter.keyword': _.debounce(function () { this.fetch(); }, 300),
        'filter.status'()     { this.fetch(); },
        'filter.entry_type'() { this.fetch(); },
        'filter.date_from'()  { this.fetch(); },
        'filter.date_to'()    { this.fetch(); },
    },
    created() {
        this.fetch();
    },
    methods: {
        fetch(pageUrl) {
            pageUrl = pageUrl || '/accounting/journal-entries';
            axios.get(pageUrl, {
                params: {
                    option: 'lists',
                    keyword: this.filter.keyword,
                    status: this.filter.status,
                    entry_type: this.filter.entry_type,
                    date_from: this.filter.date_from,
                    date_to: this.filter.date_to,
                    count: 15,
                },
            }).then((response) => {
                this.lists = response.data.data || [];
                this.meta  = response.data.meta  || {};
                this.links = response.data.links  || {};
            });
        },
        clearFilters() {
            this.filter = { keyword: null, status: null, entry_type: null, date_from: null, date_to: null };
        },
        openNewEntry() {
            const today = new Date().toISOString().slice(0, 10);
            this.entryForm = {
                entry_date: today,
                memo: '',
                lines: [
                    { account_id: null, line_type: 'debit',  amount: null, description: '' },
                    { account_id: null, line_type: 'credit', amount: null, description: '' },
                ],
            };
            this.entryErrors = {};
            this.showEntryModal = true;
        },
        closeEntryModal() {
            this.showEntryModal = false;
        },
        addLine() {
            this.entryForm.lines.push({ account_id: null, line_type: 'debit', amount: null, description: '' });
        },
        removeLine(index) {
            if (this.entryForm.lines.length > 2) {
                this.entryForm.lines.splice(index, 1);
            }
        },
        async submitEntry() {
            this.entrySaving = true;
            this.entryErrors = {};
            try {
                await axios.post('/accounting/journal-entries', this.entryForm);
                this.closeEntryModal();
                this.fetch();
            } catch (err) {
                if (err.response?.status === 422) {
                    this.entryErrors = err.response.data.errors || {};
                }
            } finally {
                this.entrySaving = false;
            }
        },
        formatMoney(value) {
            return '₱' + Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        capitalize(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        },
        toggleExpanded(id) {
            if (this.expandedRows.includes(id)) {
                this.expandedRows = this.expandedRows.filter(rowId => rowId !== id);
                return;
            }
            this.expandedRows.push(id);
        },
        formatLabel(value) {
            return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        },
        formatSourceType(type) {
            const map = {
                SalesOrder: 'Sales Order', ArInvoice: 'AR Invoice', Receipt: 'Receipt',
                ReceivedStock: 'Stock Receipt', Expense: 'Expense',
                InventoryAdjustment: 'Inv. Adjustment', SalesReturn: 'Sales Return',
                Loan: 'Loan', Payroll: 'Payroll',
            };
            return map[type] || type || '—';
        },
        statusClass(status) {
            return { posted: status === 'posted', reversed: status === 'reversed', reversal: status === 'reversal_posted' };
        },
        parseAmount(str) {
            return parseFloat(String(str || '0').replace(/,/g, '')) || 0;
        },
        lineDebitTotal(lines) {
            const total = (lines || []).filter(l => l.line_type === 'debit').reduce((s, l) => s + this.parseAmount(l.amount), 0);
            return total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        lineCreditTotal(lines) {
            const total = (lines || []).filter(l => l.line_type === 'credit').reduce((s, l) => s + this.parseAmount(l.amount), 0);
            return total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        isBalanced(lines) {
            const dr = (lines || []).filter(l => l.line_type === 'debit').reduce((s, l) => s + this.parseAmount(l.amount), 0);
            const cr = (lines || []).filter(l => l.line_type === 'credit').reduce((s, l) => s + this.parseAmount(l.amount), 0);
            return Math.abs(dr - cr) < 0.01;
        },
    },
};
</script>

<style scoped>
/* Filter bar */
.journal-filter-bar {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.journal-filter-field {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.4rem 0.65rem;
    border: 1px solid #d1e4dc;
    border-radius: 8px;
    background: #f7fbfa;
    flex: 1;
    min-width: 140px;
}
.journal-filter-field.journal-filter-search { flex: 2; min-width: 200px; }
.journal-filter-field > i { color: #648b74; font-size: 0.9rem; flex-shrink: 0; }

.jf-input {
    width: 100%;
    border: 0;
    background: transparent;
    color: #20413a;
    font-size: 0.84rem;
    outline: none;
}

.jf-clear-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.42rem 0.85rem;
    border: 1px solid rgba(214, 100, 100, 0.25);
    border-radius: 8px;
    background: #fff5f5;
    color: #b04040;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
}
.jf-clear-btn:hover { background: #ffe8e8; }

/* Compatibility banner */
.compatibility-banner {
    display: flex;
    align-items: flex-start;
    gap: 0.9rem;
    padding: 1rem 1.1rem;
    border: 1px solid rgba(214, 141, 41, 0.24);
    border-radius: 12px;
    background: linear-gradient(180deg, #fff7e8 0%, #fffaf1 100%);
}
.compat-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: rgba(214, 141, 41, 0.14);
    color: #b06d18;
    font-size: 1.2rem;
    flex-shrink: 0;
}
.compat-title { color: #7d5016; font-weight: 700; }
.compat-text  { color: #916437; line-height: 1.55; }

/* Journal table */
.journal-table thead th {
    background: #edf5f2;
    color: #527267;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.journal-row { cursor: pointer; }
.journal-row:hover { background: #f7fbf9; }

.journal-primary { display: grid; gap: 0.2rem; }
.journal-primary strong { color: #20413a; }
.journal-primary small  { color: #648b74; }

.source-cell { display: grid; gap: 0.15rem; }
.source-type { color: #20413a; font-weight: 600; font-size: 0.88rem; }
.source-ref  { color: #648b74; font-size: 0.8rem; }

.status-chip, .link-chip {
    display: inline-flex;
    align-items: center;
    padding: 0.3rem 0.65rem;
    border-radius: 999px;
    font-size: 0.76rem;
    font-weight: 700;
}
.status-chip.posted   { background: #e7f7f2; color: #277660; }
.status-chip.reversed { background: #fff1f1; color: #b15050; }
.status-chip.reversal { background: #eef1fb; color: #4c5f9d; }

.link-stack { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.reversal-origin { background: #fff1e7; color: #a0641b; }
.reversal-has    { background: #e8effe; color: #4568b0; }
.link-neutral    { background: #edf4f1; color: #537267; }

/* Expanded detail row */
.details-row td { background: #fbfdfc; padding: 1rem; }

.details-grid {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    margin-bottom: 1rem;
}

.detail-card {
    padding: 1rem;
    border: 1px solid rgba(61, 141, 122, 0.12);
    border-radius: 12px;
    background: #ffffff;
}
.detail-card p { margin-bottom: 0.5rem; color: #46655b; }
.detail-title  { color: #20413a; font-weight: 700; margin-bottom: 0.85rem; }

.reversal-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.65rem 0.75rem;
    margin-bottom: 0.5rem;
    border-radius: 10px;
    background: #f5faf8;
}
.reversal-item span  { color: #20413a; font-weight: 600; }
.reversal-item small { color: #648b74; }

.line-table-wrap { overflow: hidden; border: 1px solid rgba(61, 141, 122, 0.12); border-radius: 12px; }
.line-table thead th { background: #edf5f2; color: #527267; }

.line-type-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 22px;
    border-radius: 6px;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.04em;
}
.line-type-chip.debit  { background: #e8f3ff; color: #2563ab; }
.line-type-chip.credit { background: #e8f7f0; color: #216e4c; }

.text-debit  { color: #2563ab; font-size: 0.88rem; }
.text-credit { color: #216e4c; font-size: 0.88rem; }

.lines-totals-row td {
    background: #f4faf8;
    border-top: 2px solid rgba(61, 141, 122, 0.18);
    font-size: 0.88rem;
}

.balance-chip {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.6rem;
    border-radius: 999px;
    font-size: 0.74rem;
    font-weight: 700;
}
.balance-chip.balanced   { background: #e7f7f2; color: #1a6e4e; }
.balance-chip.unbalanced { background: #fff1f1; color: #b13535; }

.empty-cell { padding: 2rem 1rem; }
.empty-state { display: grid; justify-items: center; gap: 0.5rem; color: #648b74; }
.empty-state i { font-size: 2rem; }

@media (max-width: 991.98px) {
    .details-grid { grid-template-columns: 1fr; }
    .journal-filter-bar { flex-direction: column; }
    .journal-filter-field { width: 100%; }
}

/* Manual entry modal content styles */
.modal-kicker {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #6b8c85;
}

.balance-indicator {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.65rem 0.9rem;
    border-radius: 10px;
    font-size: 0.84rem;
}
.balance-indicator i { font-size: 1rem; }
.balance-indicator.balanced   { background: #e7f7f2; color: #1a5c42; border: 1px solid #b8e8d8; }
.balance-indicator.unbalanced { background: #fff7ed; color: #92400e; border: 1px solid #fcd34d; }

.line-items-wrap { overflow: hidden; }

.entry-lines-table thead th {
    background: #edf5f2;
    color: #527267;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    padding: 0.5rem 0.6rem;
}
.entry-lines-table tbody td {
    padding: 0.45rem 0.5rem;
    vertical-align: middle;
}

.dr-cr-toggle { display: flex; border-radius: 8px; overflow: hidden; border: 1px solid #c4d9d2; }
.dr-btn, .cr-btn {
    flex: 1;
    padding: 0.3rem 0.5rem;
    border: none;
    background: #f7fbfa;
    font-size: 0.76rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.15s;
}
.dr-btn.active { background: #dbeafe; color: #1e40af; }
.cr-btn.active { background: #dcfce7; color: #166534; }
.dr-btn:not(.active):hover { background: #eff6ff; }
.cr-btn:not(.active):hover { background: #f0fdf4; }

.remove-line-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border: 1px solid #ffd5d5;
    border-radius: 8px;
    background: transparent;
    color: #b15050;
    cursor: pointer;
    font-size: 0.9rem;
}
.remove-line-btn:disabled { opacity: 0.3; cursor: not-allowed; }
.remove-line-btn:not(:disabled):hover { background: #fff1f1; }

.add-line-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.4rem 0.85rem;
    border: 1px dashed #9dc9be;
    border-radius: 8px;
    background: transparent;
    color: #3d8d7a;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
}
.add-line-btn:hover { background: #edf5f2; }

.btn-post { background: #3d8d7a; color: #fff; border: none; font-weight: 700; }
.btn-post:hover:not(:disabled) { background: #347a6a; color: #fff; }
.btn-post:disabled { opacity: 0.6; cursor: not-allowed; }

.spin { display: inline-block; animation: spin 0.8s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
