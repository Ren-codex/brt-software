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

                    <!-- Not ready -->
                    <div v-if="!dataReady" class="acct-empty-notice">
                        <i class="ri-information-line"></i>
                        Accounting tables are not ready yet. Run the accounting migrations to populate the chart of accounts.
                    </div>

                    <!-- Main panel -->
                    <div v-else class="library-card">
                        <div class="library-card-header">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="header-icon"><i class="ri-node-tree"></i></div>
                                    <div>
                                        <h4 class="header-title mb-0">Account Master List</h4>
                                        <p class="header-subtitle mb-0">Configured accounts available for posting and reporting.</p>
                                    </div>
                                </div>
                                <button type="button" class="new-account-btn" @click="openCreate">
                                    <i class="ri-add-line"></i>
                                    New Account
                                </button>
                            </div>
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
                                        <tr v-for="row in rows" :key="row.id" :class="{ 'row-inactive': !row.is_active }">
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
                                                    <button type="button" class="action-btn edit" @click="openEdit(row)" title="Edit">
                                                        <i class="ri-pencil-line"></i>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="action-btn"
                                                        :class="row.is_active ? 'deactivate' : 'activate'"
                                                        @click="toggleStatus(row)"
                                                        :title="row.is_active ? 'Deactivate' : 'Activate'"
                                                    >
                                                        <i :class="row.is_active ? 'ri-toggle-fill' : 'ri-toggle-line'"></i>
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
        <div v-show="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="closeModal">
            <div class="modal-container modal-md" @click.stop>
                <div class="modal-header">
                    <div class="d-flex align-items-center gap-2">
                        <div class="modal-header-icon">
                            <i class="ri-node-tree"></i>
                        </div>
                        <div>
                            <p class="modal-kicker mb-0">Chart of Accounts</p>
                            <h5 class="mb-0">{{ editingId ? 'Edit Account' : 'New Account' }}</h5>
                        </div>
                    </div>
                    <button class="close-btn" @click="closeModal">
                        <i class="ri-close-line fs-20"></i>
                    </button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-4">
                            <label class="form-label fw-semibold">Code <span class="text-danger">*</span></label>
                            <input
                                v-model="form.code"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': errors.code }"
                                placeholder="e.g. 1100"
                                maxlength="20"
                            />
                            <div v-if="errors.code" class="invalid-feedback">{{ errors.code[0] }}</div>
                        </div>
                        <div class="col-8">
                            <label class="form-label fw-semibold">Account Name <span class="text-danger">*</span></label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': errors.name }"
                                placeholder="e.g. Cash on Hand"
                                maxlength="120"
                            />
                            <div v-if="errors.name" class="invalid-feedback">{{ errors.name[0] }}</div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                            <select v-model="form.type" class="form-select" :class="{ 'is-invalid': errors.type }">
                                <option value="">-- Select type --</option>
                                <option v-for="t in accountTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                            <div v-if="errors.type" class="invalid-feedback">{{ errors.type[0] }}</div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Subtype</label>
                            <input
                                v-model="form.subtype"
                                type="text"
                                class="form-control"
                                placeholder="e.g. cost_of_sales"
                                maxlength="60"
                            />
                            <small class="text-muted">Optional — used for P&L grouping (e.g. <code>cost_of_sales</code>)</small>
                        </div>
                        <div v-if="!editingId" class="col-12 d-flex align-items-center gap-2">
                            <div class="form-check form-switch mb-0">
                                <input v-model="form.is_active" class="form-check-input" type="checkbox" role="switch" id="is-active-toggle" />
                                <label class="form-check-label" for="is-active-toggle">Active (available for posting)</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" @click="closeModal">Cancel</button>
                    <button type="button" class="btn btn-save" :disabled="saving" @click="save">
                        <span v-if="saving"><i class="ri-loader-4-line spin me-1"></i>Saving…</span>
                        <span v-else><i class="ri-save-line me-1"></i>{{ editingId ? 'Update' : 'Create' }}</span>
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
    components: {},
    props: {
        stats: Object,
        dataReady: Boolean,
        summaryCards: Array,
        rows: { type: Array, default: () => [] },
    },
    data() {
        return {
            showModal: false,
            editingId: null,
            saving: false,
            errors: {},
            form: {
                code: '',
                name: '',
                type: '',
                subtype: '',
                is_active: true,
            },
            accountTypes: [
                { value: 'asset',     label: 'Asset' },
                { value: 'liability', label: 'Liability' },
                { value: 'equity',    label: 'Equity' },
                { value: 'revenue',   label: 'Revenue' },
                { value: 'expense',   label: 'Expense' },
            ],
        };
    },
    methods: {
        openCreate() {
            this.editingId = null;
            this.errors = {};
            this.form = { code: '', name: '', type: '', subtype: '', is_active: true };
            this.showModal = true;
        },
        openEdit(row) {
            this.editingId = row.id;
            this.errors = {};
            this.form = {
                code: row.code,
                name: row.name,
                type: row.type,
                subtype: row.subtype || '',
                is_active: row.is_active,
            };
            this.showModal = true;
        },
        closeModal() {
            this.showModal = false;
        },
        async save() {
            this.saving = true;
            this.errors = {};
            try {
                if (this.editingId) {
                    await axios.put(`/accounting/accounts/${this.editingId}`, this.form);
                } else {
                    await axios.post('/accounting/accounts', this.form);
                }
                this.closeModal();
                router.reload({ only: ['rows', 'summaryCards'] });
            } catch (err) {
                if (err.response?.status === 422) {
                    this.errors = err.response.data.errors || {};
                }
            } finally {
                this.saving = false;
            }
        },
        async toggleStatus(row) {
            try {
                await axios.patch(`/accounting/accounts/${row.id}/toggle`);
                router.reload({ only: ['rows', 'summaryCards'] });
            } catch {
                // silent
            }
        },
        formatLabel(value) {
            return String(value || '-')
                .replace(/_/g, ' ')
                .replace(/\b\w/g, (c) => c.toUpperCase());
        },
    },
};
</script>

<style scoped>
.new-account-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 1rem;
    border-radius: 10px;
    background: #3d8d7a;
    color: #fff;
    font-weight: 700;
    font-size: 0.85rem;
    border: none;
    cursor: pointer;
    transition: background 0.2s;
}
.new-account-btn:hover { background: #347a6a; }

.coa-table thead th {
    background: #edf5f2;
    color: #527267;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    white-space: nowrap;
}
.coa-table tbody td { vertical-align: middle; font-size: 0.85rem; }
.row-inactive td { opacity: 0.55; }

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

.modal-kicker {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #6b8c85;
}

.btn-save { background: #3d8d7a; color: #fff; border: none; font-weight: 700; }
.btn-save:hover:not(:disabled) { background: #347a6a; color: #fff; }
.btn-save:disabled { opacity: 0.6; cursor: not-allowed; }

.spin { display: inline-block; animation: spin 0.8s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
