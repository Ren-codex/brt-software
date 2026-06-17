<template>
    <div>

        <!-- Header + New button -->
        <div class="library-card mb-3">
            <div class="library-card-header">
                <div class="d-flex align-items-center gap-3">
                    <div class="header-icon"><i class="ri-bill-line fs-24"></i></div>
                    <div>
                        <h4 class="header-title mb-1">Operating Expenses</h4>
                        <p class="header-subtitle mb-0">Record, approve, and release direct business expenses with GL posting</p>
                    </div>
                </div>
                <button class="acct-btn-primary" @click="openCreate">
                    <i class="ri-add-line"></i> New Expense
                </button>
            </div>
        </div>

        <!-- Summary chips -->
        <div class="ge-summary-bar mb-3">
            <div class="ge-chip" :class="{ active: filter.status === '' }" @click="filter.status = ''">
                All <span class="ge-chip-count">{{ localExpenses.length }}</span>
            </div>
            <div class="ge-chip" :class="{ active: filter.status === 'draft' }" @click="filter.status = 'draft'">
                Draft <span class="ge-chip-count">{{ countByStatus('draft') }}</span>
            </div>
            <div class="ge-chip" :class="{ active: filter.status === 'approved' }" @click="filter.status = 'approved'">
                Approved <span class="ge-chip-count">{{ countByStatus('approved') }}</span>
            </div>
            <div class="ge-chip" :class="{ active: filter.status === 'voided' }" @click="filter.status = 'voided'">
                Voided <span class="ge-chip-count">{{ countByStatus('voided') }}</span>
            </div>
        </div>

        <!-- Table card -->
        <div class="library-card">
            <div class="library-card-body p-0">

                <!-- Filter bar -->
                <div class="px-3 py-2 border-bottom d-flex align-items-center gap-2 flex-wrap">
                    <input v-model="filter.search" type="text" class="form-control form-control-sm" style="max-width:260px" placeholder="Search payee, ref, account…" />
                    <select v-model="filter.payment_method" class="form-select form-select-sm" style="width:160px">
                        <option value="">All Methods</option>
                        <option value="cash">Cash</option>
                        <option value="check">Check</option>
                        <option value="bank_transfer">Bank Transfer</option>
                    </select>
                    <div class="ms-auto text-muted" style="font-size:0.82rem">
                        {{ filteredExpenses.length }} record(s) &nbsp;·&nbsp;
                        Total: <strong>₱{{ totalFiltered }}</strong>
                    </div>
                </div>

                <!-- Table -->
                <div class="table-responsive">
                    <table class="table ge-table mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Payee</th>
                                <th>Account</th>
                                <th>Method</th>
                                <th>Reference</th>
                                <th class="text-end">Amount</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Receipt</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="filteredExpenses.length === 0">
                                <td colspan="9" class="text-center text-muted py-5">No expenses found.</td>
                            </tr>
                            <tr v-for="e in filteredExpenses" :key="e.id">
                                <td class="text-nowrap">{{ e.expense_date }}</td>
                                <td class="fw-semibold">{{ e.payee }}</td>
                                <td>
                                    <span class="font-monospace text-muted me-1" style="font-size:0.78rem">{{ e.gl_account_code }}</span>
                                    {{ e.gl_account_name }}
                                </td>
                                <td>{{ methodLabel(e.payment_method) }}</td>
                                <td class="text-muted font-monospace" style="font-size:0.82rem">{{ e.reference_no || '—' }}</td>
                                <td class="text-end fw-semibold">{{ e.amount_fmt }}</td>
                                <td class="text-center">
                                    <span class="ge-status-chip" :class="e.status">{{ statusLabel(e.status) }}</span>
                                </td>
                                <td class="text-center">
                                    <a v-if="e.receipt_path" :href="e.receipt_path" target="_blank" class="ge-receipt-link" title="View receipt">
                                        <i class="ri-file-line"></i>
                                    </a>
                                    <span v-else class="text-muted">—</span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        <button v-if="e.status === 'draft'" class="ge-action-btn edit" @click="openEdit(e)" title="Edit">
                                            <i class="ri-pencil-line"></i>
                                        </button>
                                        <button v-if="e.status === 'draft'" class="ge-action-btn approve" @click="doApprove(e)" title="Approve & post GL">
                                            <i class="ri-check-double-line"></i>
                                        </button>
                                        <button v-if="e.status === 'draft'" class="ge-action-btn delete" @click="doDelete(e)" title="Delete">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                        <button v-if="e.status === 'approved'" class="ge-action-btn void" @click="doVoid(e)" title="Void">
                                            <i class="ri-close-circle-line"></i>
                                        </button>
                                        <span v-if="e.status === 'voided'" class="text-muted" style="font-size:0.78rem">—</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ── New / Edit Expense Modal ─────────────────────────────────── -->
        <div v-if="modal.show" class="modal-overlay active" @click.self="closeModal">
                <div class="modal-container" style="max-width:580px">
                    <div class="modal-header">
                        <div class="d-flex align-items-center gap-2">
                            <div class="modal-header-icon"><i class="ri-bill-line"></i></div>
                            <div>
                                <h5 class="modal-title mb-0">{{ modal.editId ? 'Edit Expense' : 'New Expense' }}</h5>
                                <p class="modal-kicker mb-0">{{ modal.editId ? 'Update the expense details' : 'Record a business expense for approval' }}</p>
                            </div>
                        </div>
                        <button class="close-btn" @click="closeModal"><i class="ri-close-line"></i></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">

                            <div class="col-6">
                                <label class="form-label">Expense Date <span class="text-danger">*</span></label>
                                <input type="date" v-model="form.expense_date" class="form-control form-control-sm"
                                    :class="{ 'is-invalid': errors.expense_date }" />
                                <div class="invalid-feedback">{{ errors.expense_date }}</div>
                            </div>

                            <div class="col-6">
                                <label class="form-label">Payee / Vendor <span class="text-danger">*</span></label>
                                <input type="text" v-model="form.payee" class="form-control form-control-sm"
                                    :class="{ 'is-invalid': errors.payee }" placeholder="e.g. Meralco" />
                                <div class="invalid-feedback">{{ errors.payee }}</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Expense Account (GL) <span class="text-danger">*</span></label>
                                <select v-model="form.gl_account_id" class="form-select form-select-sm"
                                    :class="{ 'is-invalid': errors.gl_account_id }">
                                    <option value="">— Select account —</option>
                                    <optgroup v-for="group in groupedAccounts" :key="group.label" :label="group.label">
                                        <option v-for="a in group.accounts" :key="a.id" :value="a.id">
                                            {{ a.code }} — {{ a.name }}
                                        </option>
                                    </optgroup>
                                </select>
                                <div class="invalid-feedback">{{ errors.gl_account_id }}</div>
                            </div>

                            <div class="col-6">
                                <label class="form-label">Amount <span class="text-danger">*</span></label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" v-model="form.amount" min="0.01" step="0.01"
                                        class="form-control" :class="{ 'is-invalid': errors.amount }" />
                                </div>
                                <div class="invalid-feedback">{{ errors.amount }}</div>
                            </div>

                            <div class="col-6">
                                <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                                <select v-model="form.payment_method" class="form-select form-select-sm"
                                    :class="{ 'is-invalid': errors.payment_method }">
                                    <option value="">— Select —</option>
                                    <option v-for="m in paymentMethods" :key="m.value" :value="m.value">{{ m.label }}</option>
                                </select>
                                <div class="invalid-feedback">{{ errors.payment_method }}</div>
                            </div>

                            <template v-if="form.payment_method === 'bank_transfer' || form.payment_method === 'check'">
                                <div class="col-6">
                                    <label class="form-label">Bank Account</label>
                                    <select v-model="form.bank_account_id" class="form-select form-select-sm">
                                        <option value="">— None —</option>
                                        <option v-for="b in bankAccounts" :key="b.id" :value="b.id">
                                            {{ b.bank_name }} — {{ b.account_name }}
                                        </option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">{{ form.payment_method === 'check' ? 'Check No.' : 'Reference No.' }}</label>
                                    <input type="text" v-model="form.reference_no" class="form-control form-control-sm"
                                        :placeholder="form.payment_method === 'check' ? 'e.g. CHK-00123' : 'e.g. TRF-98765'" />
                                </div>
                            </template>

                            <div class="col-12">
                                <label class="form-label">Description / Purpose</label>
                                <textarea v-model="form.description" rows="2" class="form-control form-control-sm"
                                    placeholder="Brief description of what this expense is for…"></textarea>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Receipt / Supporting Document</label>
                                <input type="file" ref="receiptFile" class="form-control form-control-sm"
                                    accept=".jpg,.jpeg,.png,.pdf" @change="onFileChange" />
                                <small class="text-muted">JPG, PNG or PDF · max 5 MB</small>
                            </div>

                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-light btn-sm" @click="closeModal">Cancel</button>
                        <button class="acct-btn-primary" :disabled="saving" @click="saveExpense">
                            <span v-if="saving"><i class="ri-loader-4-line me-1"></i>Saving…</span>
                            <span v-else><i class="ri-save-line me-1"></i>{{ modal.editId ? 'Update' : 'Save' }}</span>
                        </button>
                    </div>
                </div>
        </div>

    </div>
</template>


<script>
import axios from 'axios';
import MainLayout from '@/Shared/Layouts/Main.vue';
import AccountingLayout from '@/Pages/Modules/Accounting/AccountingLayout.vue';

export default {
    layout: [MainLayout, AccountingLayout],

    props: {
        expenses:        { type: Array, default: () => [] },
        expenseAccounts: { type: Array, default: () => [] },
        bankAccounts:    { type: Array, default: () => [] },
        paymentMethods:  { type: Array, default: () => [] },
    },

    data() {
        return {
            localExpenses: [...this.expenses],
            filter: { search: '', status: '', payment_method: '' },
            modal:  { show: false, editId: null },
            form:   this.blankForm(),
            errors: {},
            saving: false,
            receiptFileObj: null,
        };
    },

    computed: {
        filteredExpenses() {
            let list = [...this.localExpenses];
            if (this.filter.status)
                list = list.filter(e => e.status === this.filter.status);
            if (this.filter.payment_method)
                list = list.filter(e => e.payment_method === this.filter.payment_method);
            if (this.filter.search) {
                const s = this.filter.search.toLowerCase();
                list = list.filter(e =>
                    (e.payee || '').toLowerCase().includes(s) ||
                    (e.reference_no || '').toLowerCase().includes(s) ||
                    (e.gl_account_name || '').toLowerCase().includes(s) ||
                    (e.description || '').toLowerCase().includes(s)
                );
            }
            return list;
        },

        totalFiltered() {
            const sum = this.filteredExpenses.reduce((a, e) => a + (e.amount || 0), 0);
            return sum.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        groupedAccounts() {
            const labels = {
                cost_of_sales:     'Cost of Sales',
                operating_expense: 'Operating Expenses',
                payroll:           'Payroll',
                other:             'Other',
            };
            const groups = {};
            for (const a of this.expenseAccounts) {
                const key = a.subtype || 'other';
                if (!groups[key]) groups[key] = { label: labels[key] || key, accounts: [] };
                groups[key].accounts.push(a);
            }
            return Object.values(groups);
        },
    },

    methods: {
        blankForm() {
            return {
                expense_date:    new Date().toISOString().slice(0, 10),
                payee:           '',
                gl_account_id:   '',
                payment_method:  '',
                bank_account_id: '',
                amount:          '',
                reference_no:    '',
                description:     '',
            };
        },

        countByStatus(s) {
            return this.localExpenses.filter(e => e.status === s).length;
        },

        statusLabel(s) {
            return { draft: 'Draft', approved: 'Approved', voided: 'Voided' }[s] || s;
        },

        methodLabel(m) {
            return { cash: 'Cash', check: 'Check', bank_transfer: 'Bank Transfer' }[m] || '—';
        },

        openCreate() {
            this.form   = this.blankForm();
            this.errors = {};
            this.modal  = { show: true, editId: null };
            this.receiptFileObj = null;
        },

        openEdit(e) {
            this.form = {
                expense_date:    e.expense_date,
                payee:           e.payee,
                gl_account_id:   e.gl_account_id,
                payment_method:  e.payment_method,
                bank_account_id: e.bank_account_id || '',
                amount:          e.amount,
                reference_no:    e.reference_no || '',
                description:     e.description || '',
            };
            this.errors = {};
            this.modal  = { show: true, editId: e.id };
            this.receiptFileObj = null;
        },

        closeModal() { this.modal.show = false; },

        onFileChange(e) { this.receiptFileObj = e.target.files[0] || null; },

        saveExpense() {
            this.errors = {};
            this.saving = true;

            const fd = new FormData();
            Object.entries(this.form).forEach(([k, v]) => {
                if (v !== '' && v !== null && v !== undefined) fd.append(k, v);
            });
            if (this.receiptFileObj) fd.append('receipt', this.receiptFileObj);

            const isEdit = !!this.modal.editId;
            if (isEdit) fd.append('_method', 'PUT');

            const url = isEdit ? `/accounting/expenses/${this.modal.editId}` : '/accounting/expenses';

            axios.post(url, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
                .then(res => {
                    if (isEdit) {
                        const idx = this.localExpenses.findIndex(e => e.id === this.modal.editId);
                        if (idx !== -1) this.localExpenses.splice(idx, 1, res.data.data);
                    } else {
                        this.localExpenses.unshift(res.data.data);
                    }
                    this.closeModal();
                })
                .catch(err => {
                    if (err.response?.status === 422) this.errors = err.response.data.errors || {};
                    else alert(err.response?.data?.message || 'An error occurred.');
                })
                .finally(() => { this.saving = false; });
        },

        doApprove(e) {
            if (!confirm(`Approve "${e.payee}" (${e.amount_fmt})?\n\nThis will post a GL journal entry.`)) return;
            axios.patch(`/accounting/expenses/${e.id}/approve`)
                .then(res => this.replaceLocal(res.data.data))
                .catch(err => alert(err.response?.data?.message || 'Error'));
        },

        doVoid(e) {
            if (!confirm(`Void "${e.payee}" (${e.amount_fmt})?\n\nA reversal entry will be posted.`)) return;
            axios.patch(`/accounting/expenses/${e.id}/void`)
                .then(res => this.replaceLocal(res.data.data))
                .catch(err => alert(err.response?.data?.message || 'Error'));
        },

        doDelete(e) {
            if (!confirm(`Permanently delete "${e.payee}" (${e.amount_fmt})?`)) return;
            axios.delete(`/accounting/expenses/${e.id}`)
                .then(() => { this.localExpenses = this.localExpenses.filter(x => x.id !== e.id); })
                .catch(err => alert(err.response?.data?.message || 'Error'));
        },

        replaceLocal(updated) {
            const idx = this.localExpenses.findIndex(e => e.id === updated.id);
            if (idx !== -1) this.localExpenses.splice(idx, 1, updated);
        },
    },
};
</script>

<style scoped>
.ge-summary-bar { display: flex; gap: 8px; flex-wrap: wrap; }

.ge-chip {
    padding: 5px 14px;
    border-radius: 20px;
    font-size: 0.82rem;
    font-weight: 600;
    background: #f1f5f9;
    color: #64748b;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.15s;
    display: flex;
    align-items: center;
    gap: 6px;
}
.ge-chip:hover { background: #e2e8f0; }
.ge-chip.active { background: #e8f5f1; color: #16322e; border-color: #c4d9d2; }
.ge-chip-count { background: rgba(0,0,0,0.1); border-radius: 10px; padding: 0 7px; font-size: 0.75rem; }

.ge-table th {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
    background: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
    padding: 10px 12px;
}
.ge-table td { font-size: 0.865rem; padding: 10px 12px; vertical-align: middle; }
.ge-table tr:hover td { background: #f8fffe; }

.ge-status-chip {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 700;
}
.ge-status-chip.draft    { background: #f1f5f9; color: #64748b; }
.ge-status-chip.approved { background: #dcfce7; color: #15803d; }
.ge-status-chip.voided   { background: #fee2e2; color: #b91c1c; }

.ge-receipt-link { color: #3d8d7a; font-size: 1rem; }

.ge-action-btn {
    width: 28px; height: 28px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    background: #fff;
    font-size: 0.85rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
}
.ge-action-btn.edit:hover    { background: #e8f5f1; border-color: #3d8d7a; color: #3d8d7a; }
.ge-action-btn.approve:hover { background: #dcfce7; border-color: #15803d; color: #15803d; }
.ge-action-btn.void:hover    { background: #fee2e2; border-color: #b91c1c; color: #b91c1c; }
.ge-action-btn.delete:hover  { background: #fee2e2; border-color: #ef4444; color: #ef4444; }

.acct-btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 16px;
    background: #3d8d7a;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
}
.acct-btn-primary:hover { background: #2d6d5e; }
.acct-btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
</style>
