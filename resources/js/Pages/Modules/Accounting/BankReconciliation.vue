<template>
    <div>

        <!-- Active reconciliation view -->
        <template v-if="activeReconciliation">
            <div class="d-flex align-items-center gap-2 mb-3">
                <button class="back-btn" @click="closeReconciliation">
                    <i class="ri-arrow-left-line"></i> All Reconciliations
                </button>
                <span class="text-muted mx-1">/</span>
                <span class="fw-semibold">{{ activeReconciliation.bank_name }} — Up to {{ activeReconciliation.period_end }}</span>
                <span class="recon-status-chip ms-2" :class="activeReconciliation.status">{{ activeReconciliation.status }}</span>
            </div>

            <!-- Summary panel -->
            <div class="recon-summary-bar mb-3" :class="{ reconciled: summary.is_reconciled }">
                <div class="recon-summary-item">
                    <span class="recon-summary-label">Statement Balance</span>
                    <span class="recon-summary-value">{{ fmt(summary.statement_balance) }}</span>
                </div>
                <div class="recon-summary-sep"></div>
                <div class="recon-summary-item">
                    <span class="recon-summary-label">Book Balance</span>
                    <span class="recon-summary-value">{{ fmt(summary.book_balance) }}</span>
                </div>
                <div class="recon-summary-sep"></div>
                <div class="recon-summary-item">
                    <span class="recon-summary-label">Cleared Items</span>
                    <span class="recon-summary-value">{{ summary.cleared_count }} cleared</span>
                </div>
                <div class="recon-summary-sep"></div>
                <div class="recon-summary-item">
                    <span class="recon-summary-label">Uncleared</span>
                    <span class="recon-summary-value">{{ summary.uncleared_count }} pending</span>
                </div>
                <div class="recon-summary-sep"></div>
                <div class="recon-summary-item">
                    <span class="recon-summary-label">Difference</span>
                    <span class="recon-summary-value" :class="summary.is_reconciled ? 'text-success' : 'text-danger'">
                        {{ fmt(summary.difference) }}
                        <i v-if="summary.is_reconciled" class="ri-checkbox-circle-fill text-success ms-1"></i>
                    </span>
                </div>
                <div class="recon-summary-sep"></div>
                <div class="recon-summary-item">
                    <button
                        v-if="activeReconciliation.status === 'open'"
                        class="acct-btn-primary"
                        :disabled="!summary.is_reconciled || finalizing"
                        @click="finalize"
                        title="Only available when difference is 0"
                    >
                        <i v-if="finalizing" class="ri-loader-4-line spin"></i>
                        <i v-else class="ri-check-double-line"></i>
                        Finalize
                    </button>
                    <span v-else class="finalized-badge"><i class="ri-shield-check-line"></i> Finalized</span>
                </div>
            </div>

            <!-- Transactions table -->
            <div class="library-card">
                <div class="library-card-header">
                        <div class="d-flex align-items-center gap-3">
                            <div class="header-icon"><i class="ri-list-check"></i></div>
                            <div>
                                <h4 class="header-title mb-0">Transactions</h4>
                                <p class="header-subtitle mb-0">Check off items that appear on the bank statement.</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button class="filter-chip" :class="{ active: lineFilter === 'all' }" @click="lineFilter = 'all'">All ({{ lines.length }})</button>
                            <button class="filter-chip" :class="{ active: lineFilter === 'uncleared' }" @click="lineFilter = 'uncleared'">Uncleared ({{ unclearedCount }})</button>
                            <button class="filter-chip" :class="{ active: lineFilter === 'cleared' }" @click="lineFilter = 'cleared'">Cleared ({{ clearedCount }})</button>
                        </div>
                </div>
                <div class="library-card-body p-0">
                    <div v-if="loadingLines" class="cm-empty-state">
                        <i class="ri-loader-4-line spin" style="font-size:2rem"></i>
                        <p>Loading transactions...</p>
                    </div>
                    <div v-else-if="filteredLines.length === 0" class="cm-empty-state">
                        <i class="ri-receipt-line"></i>
                        <p>No transactions found for this period.</p>
                    </div>
                    <div v-else class="table-responsive">
                        <table class="table recon-table mb-0">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width:44px">
                                        <input type="checkbox" class="form-check-input" @change="toggleAll($event)" :disabled="activeReconciliation.status === 'finalized'" />
                                    </th>
                                    <th>Journal No</th>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Memo / Description</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Credit</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="line in filteredLines" :key="line.id" :class="{ 'row-cleared': line.is_cleared }">
                                    <td class="text-center">
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            :checked="line.is_cleared"
                                            :disabled="activeReconciliation.status === 'finalized' || toggling === line.id"
                                            @change="toggleLine(line)"
                                        />
                                    </td>
                                    <td class="font-monospace text-muted small">{{ line.journal_number }}</td>
                                    <td class="small">{{ line.entry_date }}</td>
                                    <td>
                                        <span class="entry-type-chip">{{ formatType(line.entry_type) }}</span>
                                    </td>
                                    <td class="small text-muted">{{ line.memo || line.description || '—' }}</td>
                                    <td class="text-end">
                                        <span v-if="line.line_type === 'debit'" class="fw-semibold text-success">{{ line.amount_formatted }}</span>
                                        <span v-else class="text-muted">—</span>
                                    </td>
                                    <td class="text-end">
                                        <span v-if="line.line_type === 'credit'" class="fw-semibold text-danger">{{ line.amount_formatted }}</span>
                                        <span v-else class="text-muted">—</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="cleared-chip" :class="line.is_cleared ? 'cleared' : 'uncleared'">
                                            {{ line.is_cleared ? 'Cleared' : 'Pending' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>

        <!-- Reconciliation list view -->
        <template v-else>
            <div class="library-card">
                <div class="library-card-header">
                        <div class="d-flex align-items-center gap-3">
                            <div class="header-icon"><i class="ri-bank-card-line"></i></div>
                            <div>
                                <h4 class="header-title mb-0">Bank Reconciliation</h4>
                                <p class="header-subtitle mb-0">Match your books against bank statements to find discrepancies.</p>
                            </div>
                        </div>
                        <button class="acct-btn-primary" @click="openStartModal">
                            <i class="ri-add-line"></i> Start Reconciliation
                        </button>
                </div>
                <div class="library-card-body p-0">
                    <div v-if="reconciliations.length === 0" class="cm-empty-state">
                        <i class="ri-bank-card-line"></i>
                        <p class="mb-1">No reconciliations yet</p>
                        <small>Start your first bank reconciliation to check for discrepancies.</small>
                    </div>
                    <div v-else class="table-responsive">
                        <table class="table recon-table mb-0">
                            <thead>
                                <tr>
                                    <th>Bank Account</th>
                                    <th>Period End</th>
                                    <th class="text-end">Statement Balance</th>
                                    <th class="text-center">Status</th>
                                    <th>Notes</th>
                                    <th>Created By</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in reconciliations" :key="r.id">
                                    <td class="fw-semibold">{{ r.bank_name }}</td>
                                    <td>{{ r.period_end }}</td>
                                    <td class="text-end">{{ r.statement_balance_formatted }}</td>
                                    <td class="text-center">
                                        <span class="recon-status-chip" :class="r.status">{{ r.status }}</span>
                                    </td>
                                    <td class="text-muted small">{{ r.notes || '—' }}</td>
                                    <td class="text-muted small">{{ r.created_by || '—' }}</td>
                                    <td class="text-center">
                                        <div class="d-flex align-items-center justify-content-center gap-1">
                                            <button class="action-btn open" @click="openReconciliation(r)" title="Open">
                                                <i class="ri-eye-line"></i>
                                            </button>
                                            <button v-if="r.status === 'open'" class="action-btn delete" @click="confirmDelete(r)" title="Delete">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>

        <!-- Start Reconciliation Modal -->
        <div v-if="startModal.open" class="modal-overlay active" @click.self="startModal.open = false">
            <div class="modal-container" style="max-width:480px">
                <div class="modal-header">
                    <div class="modal-header-icon"><i class="ri-bank-card-line"></i></div>
                    <div>
                        <h5 class="modal-title">Start Reconciliation</h5>
                        <p class="modal-subtitle">Select a bank account, enter your statement's closing balance and period end date.</p>
                    </div>
                    <button class="close-btn ms-auto" @click="startModal.open = false"><i class="ri-close-line"></i></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Bank Account <span class="text-danger">*</span></label>
                            <select v-model="startForm.bank_account_id" class="form-select">
                                <option value="">-- Select bank account --</option>
                                <option v-for="b in bankAccounts" :key="b.id" :value="b.id">{{ b.bank_name }} — {{ b.account_name }}</option>
                            </select>
                            <div v-if="startErrors.bank_account_id" class="error-msg">{{ startErrors.bank_account_id[0] }}</div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Period End Date <span class="text-danger">*</span></label>
                            <input v-model="startForm.period_end" type="date" class="form-control" />
                            <div v-if="startErrors.period_end" class="error-msg">{{ startErrors.period_end[0] }}</div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Statement Closing Balance <span class="text-danger">*</span></label>
                            <input v-model="startForm.statement_balance" type="number" step="0.01" class="form-control" placeholder="0.00" />
                            <div class="form-text">Enter the ending balance from your bank statement.</div>
                            <div v-if="startErrors.statement_balance" class="error-msg">{{ startErrors.statement_balance[0] }}</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes <span class="text-muted">(optional)</span></label>
                            <textarea v-model="startForm.notes" class="form-control" rows="2" placeholder="Month-end reconciliation, etc."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="acct-btn-secondary" @click="startModal.open = false">Cancel</button>
                    <button class="acct-btn-primary" :disabled="starting" @click="startReconciliation">
                        <span v-if="starting"><i class="ri-loader-4-line spin"></i> Starting...</span>
                        <span v-else>Start</span>
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>

<script>
import axios from "axios";
import { router } from "@inertiajs/vue3";
import MainLayout from "@/Shared/Layouts/Main.vue";
import AccountingLayout from "@/Pages/Modules/Accounting/AccountingLayout.vue";

export default {
    layout: [MainLayout, AccountingLayout],
    props: {
        bankAccounts:    { type: Array,  default: () => [] },
        reconciliations: { type: Array,  default: () => [] },
        stats:           { type: Object, default: () => ({}) },
    },
    data() {
        return {
            activeReconciliation: null,
            lines:       [],
            summary:     { statement_balance: 0, book_balance: 0, cleared_net: 0, cleared_count: 0, uncleared_count: 0, difference: 0, is_reconciled: false },
            loadingLines: false,
            lineFilter:  'all',
            toggling:    null,
            finalizing:  false,

            startModal: { open: false },
            startForm:  { bank_account_id: '', period_end: new Date().toISOString().slice(0, 10), statement_balance: '', notes: '' },
            startErrors:{},
            starting:   false,
        };
    },
    computed: {
        filteredLines() {
            if (this.lineFilter === 'cleared')   return this.lines.filter(l => l.is_cleared);
            if (this.lineFilter === 'uncleared') return this.lines.filter(l => !l.is_cleared);
            return this.lines;
        },
        clearedCount()   { return this.lines.filter(l => l.is_cleared).length; },
        unclearedCount() { return this.lines.filter(l => !l.is_cleared).length; },
    },
    methods: {
        fmt(value) {
            return '₱' + Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        formatType(type) {
            return String(type || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        },

        openStartModal() {
            this.startForm   = { bank_account_id: '', period_end: new Date().toISOString().slice(0, 10), statement_balance: '', notes: '' };
            this.startErrors = {};
            this.startModal.open = true;
        },
        async startReconciliation() {
            this.starting = true;
            this.startErrors = {};
            try {
                const res = await axios.post('/accounting/bank-reconciliation', this.startForm);
                this.startModal.open = false;
                router.reload({ preserveScroll: true, only: ['reconciliations'] });
            } catch (e) {
                if (e.response?.status === 422) this.startErrors = e.response.data.errors || {};
            } finally {
                this.starting = false;
            }
        },

        async openReconciliation(r) {
            this.activeReconciliation = r;
            this.lines = [];
            this.lineFilter = 'all';
            this.loadingLines = true;
            try {
                const res = await axios.get(`/accounting/bank-reconciliation/${r.id}`);
                this.lines   = res.data.lines;
                this.summary = res.data.summary;
            } finally {
                this.loadingLines = false;
            }
        },
        closeReconciliation() {
            this.activeReconciliation = null;
            this.lines = [];
        },

        async toggleLine(line) {
            if (this.toggling === line.id) return;
            this.toggling = line.id;
            try {
                await axios.post(`/accounting/bank-reconciliation/${this.activeReconciliation.id}/toggle-clear`, {
                    journal_entry_line_id: line.id,
                });
                line.is_cleared = !line.is_cleared;
                await this.refreshSummary();
            } finally {
                this.toggling = null;
            }
        },
        async toggleAll(event) {
            const cleared = event.target.checked;
            const toToggle = this.filteredLines.filter(l => l.is_cleared !== cleared);
            for (const line of toToggle) {
                await this.toggleLine(line);
            }
        },
        async refreshSummary() {
            const res = await axios.get(`/accounting/bank-reconciliation/${this.activeReconciliation.id}`);
            this.summary = res.data.summary;
        },

        async finalize() {
            const ok = await this.$confirm({
                title:       'Finalize Reconciliation?',
                message:     'Are you sure you want to finalize this reconciliation?',
                note:        'It cannot be edited after finalization.',
                confirmText: 'Yes, Finalize',
                variant:     'warning',
            });
            if (!ok) return;
            this.finalizing = true;
            try {
                await axios.post(`/accounting/bank-reconciliation/${this.activeReconciliation.id}/finalize`);
                this.activeReconciliation.status = 'finalized';
                router.reload({ preserveScroll: true, only: ['reconciliations'] });
            } finally {
                this.finalizing = false;
            }
        },

        async confirmDelete(r) {
            const ok = await this.$confirm({
                title:       'Delete Reconciliation?',
                message:     `Delete reconciliation for ${r.bank_name} (${r.period_end})?`,
                confirmText: 'Yes, Delete',
                variant:     'danger',
            });
            if (!ok) return;
            await axios.delete(`/accounting/bank-reconciliation/${r.id}`);
            router.reload({ preserveScroll: true, only: ['reconciliations'] });
        },
    },
};
</script>

<style scoped>
/* ── Back button ─────────────────────────────────────────────── */
.back-btn {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.35rem 0.85rem; border-radius: 8px; border: 1px solid #c4d9d2;
    background: #fff; color: #335c52; font-size: 0.82rem; font-weight: 600;
    cursor: pointer; transition: background 0.15s;
}
.back-btn:hover { background: #edf5f2; }

/* ── Summary bar ─────────────────────────────────────────────── */
.recon-summary-bar {
    display: flex; align-items: center; flex-wrap: wrap; gap: 0;
    background: #f4faf8; border: 1px solid #c4d9d2; border-radius: 12px;
    padding: 0.85rem 1.25rem; transition: border-color 0.3s, background 0.3s;
}
.recon-summary-bar.reconciled { background: #edfaf4; border-color: #6dd4ab; }
.recon-summary-item { display: flex; flex-direction: column; align-items: flex-start; padding: 0 1.1rem; }
.recon-summary-label { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #7a9e95; }
.recon-summary-value { font-size: 0.95rem; font-weight: 700; color: #1a3830; margin-top: 2px; }
.recon-summary-sep  { width: 1px; height: 36px; background: #c4d9d2; margin: 0; }

/* ── Status chips ────────────────────────────────────────────── */
.recon-status-chip {
    display: inline-flex; align-items: center; padding: 2px 10px;
    border-radius: 999px; font-size: 0.72rem; font-weight: 700;
}
.recon-status-chip.open      { background: #fef9c3; color: #854d0e; }
.recon-status-chip.finalized { background: #dcfce7; color: #166534; }

.cleared-chip {
    display: inline-flex; align-items: center; padding: 2px 8px;
    border-radius: 999px; font-size: 0.7rem; font-weight: 700;
}
.cleared-chip.cleared   { background: #dcfce7; color: #166534; }
.cleared-chip.uncleared { background: #f3f4f6; color: #6b7280; }

.finalized-badge {
    display: inline-flex; align-items: center; gap: 0.3rem;
    font-size: 0.82rem; font-weight: 700; color: #166534;
}

/* ── Table ───────────────────────────────────────────────────── */
.recon-table thead th {
    background: #edf5f2; color: #527267; font-size: 0.75rem;
    font-weight: 700; text-transform: uppercase; white-space: nowrap;
}
.recon-table tbody td { font-size: 0.82rem; vertical-align: middle; }
.row-cleared td { background: #f0fdf4; }

.entry-type-chip {
    display: inline-flex; align-items: center; padding: 2px 7px;
    border-radius: 6px; font-size: 0.68rem; font-weight: 700;
    background: #e2ece9; color: #335c52;
}

/* ── Filter chips ────────────────────────────────────────────── */
.filter-chip {
    padding: 0.3rem 0.85rem; border-radius: 999px;
    border: 1px solid #c4d9d2; background: #fff; color: #527267;
    font-size: 0.78rem; font-weight: 600; cursor: pointer; transition: all 0.15s;
}
.filter-chip.active, .filter-chip:hover { background: #3d8d7a; color: #fff; border-color: #3d8d7a; }

/* ── Actions ─────────────────────────────────────────────────── */
.action-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 30px; height: 30px; border-radius: 8px; border: 1px solid;
    cursor: pointer; font-size: 0.9rem; background: transparent; transition: all 0.15s;
}
.action-btn.open         { border-color: #c4d9d2; color: #335c52; }
.action-btn.open:hover   { background: #edf5f2; }
.action-btn.delete       { border-color: #fca5a5; color: #991b1b; }
.action-btn.delete:hover { background: #fee2e2; }

/* ── Empty state ─────────────────────────────────────────────── */
.cm-empty-state { padding: 2.5rem; text-align: center; color: #648b74; }
.cm-empty-state i { font-size: 2.5rem; color: #3d8d7a; display: block; margin-bottom: 0.75rem; }

/* ── Modal misc ──────────────────────────────────────────────── */
.modal-subtitle { font-size: 0.82rem; color: #6b8c85; margin: 0; }
.error-msg      { font-size: 0.78rem; color: #dc2626; margin-top: 3px; }

.spin { display: inline-block; animation: spin 0.8s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
