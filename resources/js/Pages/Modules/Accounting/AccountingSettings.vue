<template>
    <div>

        <!-- Inner tab bar -->
        <div class="settings-tab-bar mb-3">
            <button
                class="settings-tab-btn"
                :class="{ active: activeSettingsTab === 'chart_of_accounts' }"
                @click="activeSettingsTab = 'chart_of_accounts'"
            >
                <i class="ri-node-tree me-1"></i>Chart of Accounts
            </button>
            <button
                class="settings-tab-btn"
                :class="{ active: activeSettingsTab === 'bank_accounts' }"
                @click="activeSettingsTab = 'bank_accounts'"
            >
                <i class="ri-bank-line me-1"></i>Bank Accounts
            </button>
        </div>

        <!-- ── Chart of Accounts ──────────────────────────────────────── -->
        <template v-if="activeSettingsTab === 'chart_of_accounts'">

            <div class="row g-3 mb-3">
                <div v-for="card in summaryCards" :key="card.title" class="col-sm-6 col-xl-3">
                    <div class="acct-stat-card">
                        <div class="acct-stat-icon"><i :class="card.icon"></i></div>
                        <div>
                            <p class="acct-stat-label">{{ card.title }}</p>
                            <h4 class="acct-stat-value">{{ card.value }}</h4>
                            <p class="acct-stat-note">{{ card.description }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="!dataReady" class="acct-empty-notice">
                <i class="ri-information-line"></i>
                Accounting tables are not ready yet. Run the accounting migrations to populate the chart of accounts.
            </div>

            <div v-else class="library-card">
                <div class="library-card-header">
                        <div class="d-flex align-items-center gap-3">
                            <div class="header-icon"><i class="ri-node-tree"></i></div>
                            <div>
                                <h4 class="header-title mb-0">Account Master List</h4>
                                <p class="header-subtitle mb-0">Configured accounts available for posting and reporting.</p>
                            </div>
                        </div>
                        <button type="button" class="acct-btn-primary" @click="openCreateAccount">
                            <i class="ri-add-line"></i> New Account
                        </button>
                </div>
                <div class="library-card-body p-0">
                    <div class="table-responsive">
                        <table class="table coa-table mb-0">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Account</th>
                                    <th>Type</th>
                                    <th>Subtype</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Credit</th>
                                    <th class="text-end">Balance</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in accounts" :key="row.id" :class="{ 'row-inactive': !row.is_active }">
                                    <td class="font-monospace text-muted">{{ row.code }}</td>
                                    <td class="fw-semibold">{{ row.name }}</td>
                                    <td>
                                        <span class="type-chip" :class="row.type">{{ formatLabel(row.type) }}</span>
                                    </td>
                                    <td class="text-muted">{{ formatLabel(row.subtype || 'general') }}</td>
                                    <td class="text-end">{{ row.debit_total_formatted }}</td>
                                    <td class="text-end">{{ row.credit_total_formatted }}</td>
                                    <td class="text-end fw-semibold">{{ row.balance_formatted }}</td>
                                    <td class="text-center">
                                        <span class="status-chip" :class="row.is_active ? 'active' : 'inactive'">
                                            {{ row.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="action-group">
                                            <button type="button" class="action-btn edit" @click="openEditAccount(row)" title="Edit">
                                                <i class="ri-pencil-line"></i>
                                            </button>
                                            <button
                                                type="button"
                                                class="action-btn"
                                                :class="row.is_active ? 'deactivate' : 'activate'"
                                                @click="toggleAccount(row)"
                                                :title="row.is_active ? 'Deactivate' : 'Activate'"
                                            >
                                                <i :class="row.is_active ? 'ri-toggle-fill' : 'ri-toggle-line'"></i>
                                            </button>
                                            <button type="button" class="action-btn delete" @click="deleteAccount(row)" title="Delete">
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

            <!-- Chart of Accounts Modal -->
            <div v-show="coa.showModal" class="modal-overlay" :class="{ active: coa.showModal }" @click.self="closeAccountModal">
                <div class="modal-container modal-md" @click.stop>
                    <div class="modal-header">
                        <div class="d-flex align-items-center gap-2">
                            <div class="modal-header-icon">
                                <i class="ri-node-tree"></i>
                            </div>
                            <div>
                                <p class="modal-kicker mb-0">Chart of Accounts</p>
                                <h5 class="mb-0">{{ coa.editingId ? 'Edit Account' : 'New Account' }}</h5>
                            </div>
                        </div>
                        <button class="close-btn" @click="closeAccountModal">
                            <i class="ri-close-line fs-20"></i>
                        </button>
                    </div>

                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <!-- Type first so hint is available before Code is filled -->
                            <div class="col-6">
                                <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                                <select v-model="coa.form.type" class="form-select" :class="{ 'is-invalid': coa.errors.type }">
                                    <option value="">-- Select type --</option>
                                    <option v-for="t in accountTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                                </select>
                                <div v-if="coa.errors.type" class="invalid-feedback">{{ coa.errors.type[0] }}</div>
                                <small v-if="selectedTypeInfo" class="coa-type-hint">
                                    <i class="ri-information-line"></i>
                                    {{ selectedTypeInfo.hint }}
                                </small>
                            </div>
                            <div class="col-3">
                                <label class="form-label fw-semibold">Code <span class="text-danger">*</span></label>
                                <input
                                    v-model="coa.form.code"
                                    type="text"
                                    class="form-control font-monospace"
                                    :class="{ 'is-invalid': coa.errors.code }"
                                    :placeholder="selectedTypeInfo ? selectedTypeInfo.example : 'e.g. 1100'"
                                    maxlength="20"
                                />
                                <div v-if="coa.errors.code" class="invalid-feedback">{{ coa.errors.code[0] }}</div>
                                <small v-if="selectedTypeInfo" class="coa-code-range">
                                    <i class="ri-key-2-line"></i> {{ selectedTypeInfo.range }}
                                </small>
                            </div>
                            <div class="col-9">
                                <label class="form-label fw-semibold">Account Name <span class="text-danger">*</span></label>
                                <input
                                    v-model="coa.form.name"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': coa.errors.name }"
                                    placeholder="e.g. Cash on Hand"
                                    maxlength="120"
                                />
                                <div v-if="coa.errors.name" class="invalid-feedback">{{ coa.errors.name[0] }}</div>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold">Subtype</label>
                                <input
                                    v-model="coa.form.subtype"
                                    type="text"
                                    class="form-control"
                                    placeholder="e.g. cost_of_sales"
                                    maxlength="60"
                                />
                                <small class="text-muted">Optional — used for P&L grouping</small>
                            </div>
                            <div v-if="!coa.editingId" class="col-12 d-flex align-items-center gap-2">
                                <div class="form-check form-switch mb-0">
                                    <input v-model="coa.form.is_active" class="form-check-input" type="checkbox" role="switch" id="coa-is-active-toggle" />
                                    <label class="form-check-label" for="coa-is-active-toggle">Active (available for posting)</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="closeAccountModal">Cancel</button>
                        <button type="button" class="btn btn-save" :disabled="coa.saving" @click="saveAccount">
                            <span v-if="coa.saving"><i class="ri-loader-4-line spin me-1"></i>Saving…</span>
                            <span v-else><i class="ri-save-line me-1"></i>{{ coa.editingId ? 'Update' : 'Create' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- ── Bank Accounts ─────────────────────────────────────────── -->
        <template v-if="activeSettingsTab === 'bank_accounts'">

            <div class="row g-3 mb-3">
                <div v-for="card in bankSummaryCards" :key="card.title" class="col-sm-6 col-xl-3">
                    <div class="acct-stat-card">
                        <div class="acct-stat-icon"><i :class="card.icon"></i></div>
                        <div>
                            <p class="acct-stat-label">{{ card.title }}</p>
                            <h4 class="acct-stat-value">{{ card.value }}</h4>
                            <p class="acct-stat-note">{{ card.description }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="library-card">
                <div class="library-card-header">
                        <div class="d-flex align-items-center gap-3">
                            <div class="header-icon"><i class="ri-bank-line"></i></div>
                            <div>
                                <h4 class="header-title mb-0">Bank Accounts</h4>
                                <p class="header-subtitle mb-0">Configure bank accounts used for payment recording and GL mapping.</p>
                            </div>
                        </div>
                        <button class="acct-btn-primary" @click="openCreateBank">
                            <i class="ri-add-line"></i> Add Bank Account
                        </button>
                </div>
                <div class="library-card-body p-0">
                    <div v-if="bankAccounts.length === 0" class="empty-state">
                        <i class="ri-bank-line"></i>
                        <p class="mb-1">No bank accounts configured</p>
                        <small>Add a bank account to enable per-bank GL tracking for payments.</small>
                    </div>
                    <div v-else class="table-responsive">
                        <table class="table ba-table mb-0">
                            <thead>
                                <tr>
                                    <th>Bank</th>
                                    <th>Account Name</th>
                                    <th>Account Number</th>
                                    <th>GL Code</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="acct in bankAccounts" :key="acct.id" :class="{ 'row-inactive': !acct.is_active }">
                                    <td class="fw-semibold">{{ acct.bank_name }}</td>
                                    <td>{{ acct.account_name }}</td>
                                    <td class="font-monospace text-muted">{{ acct.account_number || '—' }}</td>
                                    <td><span class="gl-chip">{{ acct.gl_code }}</span></td>
                                    <td class="text-center">
                                        <span class="status-chip" :class="acct.is_active ? 'active' : 'inactive'">
                                            {{ acct.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="action-group">
                                            <button class="action-btn edit" @click="openEditBank(acct)" title="Edit">
                                                <i class="ri-edit-line"></i>
                                            </button>
                                            <button
                                                class="action-btn"
                                                :class="acct.is_active ? 'deactivate' : 'activate'"
                                                @click="toggleBankAccount(acct)"
                                                :title="acct.is_active ? 'Deactivate' : 'Activate'"
                                            >
                                                <i :class="acct.is_active ? 'ri-forbid-line' : 'ri-checkbox-circle-line'"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Bank Accounts Modal -->
            <div v-if="bank.modal.open" class="modal-overlay active" @click.self="closeBankModal">
                <div class="modal-container" style="max-width:520px">
                    <div class="modal-header">
                        <div class="modal-header-icon">
                            <i class="ri-bank-line"></i>
                        </div>
                        <div>
                            <h5 class="modal-title">{{ bank.modal.isEdit ? 'Edit Bank Account' : 'Add Bank Account' }}</h5>
                            <p class="modal-subtitle">{{ bank.modal.isEdit ? 'Update bank account details.' : 'Register a new bank account for payment GL mapping.' }}</p>
                        </div>
                        <button class="close-btn ms-auto" @click="closeBankModal"><i class="ri-close-line"></i></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Bank Name <span class="text-danger">*</span></label>
                                <input v-model="bank.form.bank_name" type="text" class="form-control" placeholder="e.g. BDO, BPI, Metrobank" />
                                <div v-if="bank.errors.bank_name" class="error-message">{{ bank.errors.bank_name }}</div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label">GL Code <span class="text-danger">*</span></label>
                                <input v-model="bank.form.gl_code" type="text" class="form-control font-monospace" placeholder="e.g. 1011" :disabled="bank.modal.isEdit" />
                                <div class="form-text">Must be unique. Cannot be changed after creation.</div>
                                <div v-if="bank.errors.gl_code" class="error-message">{{ bank.errors.gl_code }}</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Account Name <span class="text-danger">*</span></label>
                                <input v-model="bank.form.account_name" type="text" class="form-control" placeholder="e.g. BDO Checking — Operations" />
                                <div v-if="bank.errors.account_name" class="error-message">{{ bank.errors.account_name }}</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Account Number <span class="text-muted">(optional)</span></label>
                                <input v-model="bank.form.account_number" type="text" class="form-control font-monospace" placeholder="e.g. 1234-5678-90" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="acct-btn-secondary" @click="closeBankModal">Cancel</button>
                        <button class="acct-btn-primary" :disabled="bank.saving" @click="submitBank">
                            <span v-if="bank.saving"><i class="ri-loader-4-line"></i> Saving...</span>
                            <span v-else>{{ bank.modal.isEdit ? 'Save Changes' : 'Add Account' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </template>

    </div>
</template>

<script>
import axios from "axios";
import { router } from "@inertiajs/vue3";
import Swal from "sweetalert2";
import MainLayout from "@/Shared/Layouts/Main.vue";
import AccountingLayout from "@/Pages/Modules/Accounting/AccountingLayout.vue";

const emptyBankForm = () => ({ bank_name: '', account_name: '', account_number: '', gl_code: '' });
const emptyCoaForm  = () => ({ code: '', name: '', type: '', subtype: '', is_active: true });

export default {
    layout: [MainLayout, AccountingLayout],
    props: {
        stats:           { type: Object, default: () => ({}) },
        dataReady:       { type: Boolean, default: false },
        summaryCards:    { type: Array,  default: () => [] },
        accounts:        { type: Array,  default: () => [] },
        bankAccounts:    { type: Array,  default: () => [] },
        bankSummaryCards:{ type: Array,  default: () => [] },
    },
    data() {
        return {
            activeSettingsTab: 'chart_of_accounts',

            // Chart of Accounts state
            coa: {
                showModal: false,
                editingId: null,
                saving: false,
                errors: {},
                form: emptyCoaForm(),
            },
            accountTypes: [
                { value: 'asset',     label: 'Asset',     range: '1000–1999', example: '1100', hint: 'Cash, receivables, inventory, fixed assets' },
                { value: 'liability', label: 'Liability', range: '2000–2999', example: '2100', hint: 'Payables, loans, accrued liabilities' },
                { value: 'equity',    label: 'Equity',    range: '3000–3999', example: '3100', hint: "Owner's equity, retained earnings, capital" },
                { value: 'revenue',   label: 'Revenue',   range: '4000–4999', example: '4100', hint: 'Sales, service income, other revenues' },
                { value: 'expense',   label: 'Expense',   range: '5000–5999', example: '5100', hint: 'Operating expenses, cost of sales, payroll' },
            ],

            // Bank Accounts state
            bank: {
                modal:  { open: false, isEdit: false, id: null },
                form:   emptyBankForm(),
                errors: {},
                saving: false,
            },
        };
    },
    computed: {
        selectedTypeInfo() {
            if (!this.coa.form.type) return null;
            return this.accountTypes.find(t => t.value === this.coa.form.type) || null;
        },
    },
    methods: {
        // ── Chart of Accounts ──────────────────────────────────────────
        openCreateAccount() {
            this.coa.editingId = null;
            this.coa.errors = {};
            this.coa.form = emptyCoaForm();
            this.coa.showModal = true;
        },
        openEditAccount(row) {
            this.coa.editingId = row.id;
            this.coa.errors = {};
            this.coa.form = { code: row.code, name: row.name, type: row.type, subtype: row.subtype || '', is_active: row.is_active };
            this.coa.showModal = true;
        },
        closeAccountModal() {
            this.coa.showModal = false;
        },
        async saveAccount() {
            this.coa.saving = true;
            this.coa.errors = {};
            try {
                if (this.coa.editingId) {
                    await axios.put(`/accounting/accounts/${this.coa.editingId}`, this.coa.form);
                } else {
                    await axios.post('/accounting/accounts', this.coa.form);
                }
                this.closeAccountModal();
                router.reload({ only: ['accounts', 'summaryCards'] });
            } catch (err) {
                if (err.response?.status === 422) {
                    this.coa.errors = err.response.data.errors || {};
                }
            } finally {
                this.coa.saving = false;
            }
        },
        async toggleAccount(row) {
            await axios.patch(`/accounting/accounts/${row.id}/toggle`);
            router.reload({ only: ['accounts', 'summaryCards'] });
        },
        async deleteAccount(row) {
            const result = await Swal.fire({
                title: 'Delete Account?',
                text: `"${row.name}" will be permanently removed.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!',
            });
            if (!result.isConfirmed) return;
            try {
                await axios.delete(`/accounting/accounts/${row.id}`);
                router.reload({ only: ['accounts', 'summaryCards'] });
                Swal.fire('Deleted!', `"${row.name}" has been removed.`, 'success');
            } catch {
                Swal.fire('Error', 'Failed to delete the account.', 'error');
            }
        },
        formatLabel(value) {
            return String(value || '-').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        },

        // ── Bank Accounts ──────────────────────────────────────────────
        openCreateBank() {
            this.bank.form   = emptyBankForm();
            this.bank.errors = {};
            this.bank.modal  = { open: true, isEdit: false, id: null };
        },
        openEditBank(acct) {
            this.bank.form = {
                bank_name:      acct.bank_name,
                account_name:   acct.account_name,
                account_number: acct.account_number || '',
                gl_code:        acct.gl_code,
            };
            this.bank.errors = {};
            this.bank.modal  = { open: true, isEdit: true, id: acct.id };
        },
        closeBankModal() {
            this.bank.modal.open = false;
        },
        async submitBank() {
            this.bank.saving = true;
            this.bank.errors = {};
            try {
                if (this.bank.modal.isEdit) {
                    await axios.put(`/accounting/bank-accounts/${this.bank.modal.id}`, this.bank.form);
                } else {
                    await axios.post('/accounting/bank-accounts', this.bank.form);
                }
                this.closeBankModal();
                router.reload({ preserveScroll: true });
            } catch (e) {
                if (e.response?.status === 422) {
                    this.bank.errors = e.response.data.errors || {};
                }
            } finally {
                this.bank.saving = false;
            }
        },
        toggleBankAccount(acct) {
            axios.patch(`/accounting/bank-accounts/${acct.id}/toggle`)
                .then(() => router.reload({ preserveScroll: true }));
        },
    },
};
</script>

<style scoped>
/* ── Settings tab bar ────────────────────────────────────────── */
.settings-tab-bar {
    display: flex;
    gap: 0.5rem;
    border-bottom: 2px solid #e2ece9;
    padding-bottom: 0;
}
.settings-tab-btn {
    padding: 0.5rem 1.1rem;
    border: none;
    border-bottom: 2px solid transparent;
    background: transparent;
    color: #6b8c85;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    margin-bottom: -2px;
    border-radius: 6px 6px 0 0;
    transition: color 0.15s, border-color 0.15s;
}
.settings-tab-btn:hover  { color: #3d8d7a; }
.settings-tab-btn.active { color: #3d8d7a; border-bottom-color: #3d8d7a; background: #f4faf8; }

/* ── Chart of Accounts table ─────────────────────────────────── */
.coa-table thead th {
    background: #edf5f2;
    color: #527267;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    white-space: nowrap;
}
.coa-table tbody td { vertical-align: middle; font-size: 0.85rem; }

.type-chip {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: 700;
}
.type-chip.asset     { color: #1e4d8c; background: #dbeafe; }
.type-chip.liability { color: #7c2d12; background: #fee2e2; }
.type-chip.equity    { color: #5b21b6; background: #ede9fe; }
.type-chip.revenue   { color: #166534; background: #dcfce7; }
.type-chip.expense   { color: #92400e; background: #fef3c7; }

.coa-type-hint {
    display: block;
    margin-top: 0.3rem;
    font-size: 0.74rem;
    color: #6b8c85;
}
.coa-code-range {
    display: block;
    margin-top: 0.3rem;
    font-size: 0.74rem;
    font-weight: 700;
    color: #3d8d7a;
    letter-spacing: 0.02em;
}

/* ── Bank Accounts table ─────────────────────────────────────── */
.ba-table thead th {
    background: #edf5f2;
    color: #527267;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    white-space: nowrap;
}
.ba-table tbody td { font-size: 0.85rem; vertical-align: middle; }

.gl-chip {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 6px;
    background: #f0f5f3;
    color: #335c52;
    font-family: monospace;
    font-size: 0.8rem;
    font-weight: 700;
    border: 1px solid #c8e3da;
}

/* ── Shared ──────────────────────────────────────────────────── */
.row-inactive { opacity: 0.55; }

.status-chip {
    display: inline-flex;
    align-items: center;
    padding: 2px 10px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
}
.status-chip.active   { background: #e7f7f2; color: #277660; }
.status-chip.inactive { background: #fff1f1; color: #b15050; }

.action-group { display: flex; align-items: center; justify-content: center; gap: 0.3rem; }
.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 8px;
    border: 1px solid transparent;
    cursor: pointer;
    font-size: 0.95rem;
    background: transparent;
    transition: all 0.15s;
}
.action-btn.edit           { color: #3d6b5f; border-color: #c8e3da; }
.action-btn.edit:hover     { background: #e7f4ef; }
.action-btn.deactivate     { color: #b15050; border-color: #ffd5d5; }
.action-btn.deactivate:hover { background: #fff1f1; }
.action-btn.activate       { color: #277660; border-color: #c8e3da; }
.action-btn.activate:hover { background: #e7f4ef; }

.empty-state {
    padding: 2rem;
    text-align: center;
    color: #648b74;
}
.empty-state i {
    font-size: 2.5rem;
    color: #3d8d7a;
    display: block;
    margin-bottom: 0.75rem;
}

.modal-kicker {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #6b8c85;
}
.modal-subtitle { font-size: 0.82rem; color: #6b8c85; margin: 0; }
.error-message  { font-size: 0.78rem; color: #dc2626; margin-top: 3px; }

.btn-save { background: #3d8d7a; color: #fff; border: none; font-weight: 700; }
.btn-save:hover:not(:disabled) { background: #347a6a; color: #fff; }
.btn-save:disabled { opacity: 0.6; cursor: not-allowed; }

.spin { display: inline-block; animation: spin 0.8s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
