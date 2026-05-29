<template>
    <div>

        <!-- Summary cards -->
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

        <!-- Accounts table -->
        <div class="library-card">
            <div class="library-card-header">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-3">
                        <div class="header-icon"><i class="ri-bank-line"></i></div>
                        <div>
                            <h4 class="header-title mb-0">Bank Accounts</h4>
                            <p class="header-subtitle mb-0">Configure bank accounts used for payment recording and GL mapping.</p>
                        </div>
                    </div>
                    <button class="acct-btn-primary" @click="openCreate">
                        <i class="ri-add-line"></i> Add Bank Account
                    </button>
                </div>
            </div>
            <div class="library-card-body p-0">
                <div v-if="accounts.length === 0" class="empty-state">
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
                            <tr v-for="acct in accounts" :key="acct.id" :class="{ 'row-inactive': !acct.is_active }">
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
                                    <div class="action-btns">
                                        <button class="action-btn edit" @click="openEdit(acct)" title="Edit">
                                            <i class="ri-edit-line"></i>
                                        </button>
                                        <button class="action-btn" :class="acct.is_active ? 'deactivate' : 'activate'" @click="toggleAccount(acct)" :title="acct.is_active ? 'Deactivate' : 'Activate'">
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

        <!-- Create / Edit Modal -->
        <div v-if="modal.open" class="modal-overlay active" @click.self="closeModal">
            <div class="modal-container" style="max-width:520px">
                <div class="modal-header">
                    <div class="modal-header-icon">
                        <i class="ri-bank-line"></i>
                    </div>
                    <div>
                        <h5 class="modal-title">{{ modal.isEdit ? 'Edit Bank Account' : 'Add Bank Account' }}</h5>
                        <p class="modal-subtitle">{{ modal.isEdit ? 'Update bank account details.' : 'Register a new bank account for payment GL mapping.' }}</p>
                    </div>
                    <button class="close-btn ms-auto" @click="closeModal"><i class="ri-close-line"></i></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Bank Name <span class="text-danger">*</span></label>
                            <input v-model="form.bank_name" type="text" class="form-control" placeholder="e.g. BDO, BPI, Metrobank" />
                            <div v-if="errors.bank_name" class="error-message">{{ errors.bank_name }}</div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label">GL Code <span class="text-danger">*</span></label>
                            <input v-model="form.gl_code" type="text" class="form-control font-monospace" placeholder="e.g. 1011" :disabled="modal.isEdit" />
                            <div class="form-text">Must be unique. Cannot be changed after creation.</div>
                            <div v-if="errors.gl_code" class="error-message">{{ errors.gl_code }}</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Account Name <span class="text-danger">*</span></label>
                            <input v-model="form.account_name" type="text" class="form-control" placeholder="e.g. BDO Checking — Operations" />
                            <div v-if="errors.account_name" class="error-message">{{ errors.account_name }}</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Account Number <span class="text-muted">(optional)</span></label>
                            <input v-model="form.account_number" type="text" class="form-control font-monospace" placeholder="e.g. 1234-5678-90" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="acct-btn-secondary" @click="closeModal">Cancel</button>
                    <button class="acct-btn-primary" :disabled="saving" @click="submit">
                        <span v-if="saving"><i class="ri-loader-4-line"></i> Saving...</span>
                        <span v-else>{{ modal.isEdit ? 'Save Changes' : 'Add Account' }}</span>
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>

<script>
import { router } from "@inertiajs/vue3";
import axios from "axios";
import MainLayout from "@/Shared/Layouts/Main.vue";
import AccountingLayout from "@/Pages/Modules/Accounting/AccountingLayout.vue";

const emptyForm = () => ({ bank_name: '', account_name: '', account_number: '', gl_code: '' });

export default {
    layout: [MainLayout, AccountingLayout],
    components: {},
    props: {
        accounts:     { type: Array,  default: () => [] },
        summaryCards: { type: Array,  default: () => [] },
    },
    data() {
        return {
            modal:  { open: false, isEdit: false, id: null },
            form:   emptyForm(),
            errors: {},
            saving: false,
        };
    },
    methods: {
        openCreate() {
            this.form   = emptyForm();
            this.errors = {};
            this.modal  = { open: true, isEdit: false, id: null };
        },
        openEdit(acct) {
            this.form = {
                bank_name:      acct.bank_name,
                account_name:   acct.account_name,
                account_number: acct.account_number || '',
                gl_code:        acct.gl_code,
            };
            this.errors = {};
            this.modal  = { open: true, isEdit: true, id: acct.id };
        },
        closeModal() {
            this.modal.open = false;
        },
        async submit() {
            this.saving = true;
            this.errors = {};
            try {
                if (this.modal.isEdit) {
                    await axios.put(`/accounting/bank-accounts/${this.modal.id}`, this.form);
                } else {
                    await axios.post('/accounting/bank-accounts', this.form);
                }
                this.closeModal();
                router.reload({ preserveScroll: true });
            } catch (e) {
                if (e.response?.status === 422) {
                    this.errors = e.response.data.errors || {};
                }
            } finally {
                this.saving = false;
            }
        },
        toggleAccount(acct) {
            axios.patch(`/accounting/bank-accounts/${acct.id}/toggle`)
                .then(() => router.reload({ preserveScroll: true }));
        },
    },
};
</script>

<style scoped>
.ba-table thead th {
    background: #edf5f2;
    color: #527267;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    white-space: nowrap;
}
.ba-table tbody td { font-size: 0.85rem; vertical-align: middle; }
.ba-table tbody tr.row-inactive { opacity: 0.55; }

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

.status-chip {
    display: inline-flex;
    align-items: center;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
}
.status-chip.active   { background: #dcfce7; color: #166534; }
.status-chip.inactive { background: #f3f4f6; color: #6b7280; }

.action-btns { display: flex; align-items: center; justify-content: center; gap: 0.4rem; }
.action-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 30px; height: 30px; border-radius: 8px; border: 1px solid;
    cursor: pointer; font-size: 0.9rem; background: transparent;
    transition: all 0.15s;
}
.action-btn.edit       { border-color: #c4d9d2; color: #335c52; }
.action-btn.edit:hover { background: #edf5f2; }
.action-btn.deactivate       { border-color: #fca5a5; color: #991b1b; }
.action-btn.deactivate:hover { background: #fee2e2; }
.action-btn.activate         { border-color: #86efac; color: #166534; }
.action-btn.activate:hover   { background: #dcfce7; }

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

.modal-subtitle { font-size: 0.82rem; color: #6b8c85; margin: 0; }
.error-message  { font-size: 0.78rem; color: #dc2626; margin-top: 3px; }
</style>
