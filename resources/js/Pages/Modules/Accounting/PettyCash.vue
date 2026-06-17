<template>
    <div>

        <!-- Not ready -->
        <div v-if="!dataReady" class="acct-empty-notice">
            <i class="ri-information-line"></i>
            Petty cash tables are not ready. Run the accounting migrations first.
        </div>

        <template v-else>

            <!-- Tab bar -->
            <div class="pc-tab-bar mb-3">
                <button class="pc-tab-btn" :class="{ active: tab === 'fund' }"          @click="tab = 'fund'">
                    <i class="ri-wallet-3-line me-1"></i> Fund
                </button>
                <button class="pc-tab-btn" :class="{ active: tab === 'vouchers' }"      @click="tab = 'vouchers'">
                    <i class="ri-receipt-line me-1"></i> Vouchers
                    <span v-if="pendingVoucherCount" class="pc-badge">{{ pendingVoucherCount }}</span>
                </button>
                <button class="pc-tab-btn" :class="{ active: tab === 'replenishments' }" @click="tab = 'replenishments'">
                    <i class="ri-refresh-line me-1"></i> Replenishments
                </button>
            </div>

            <!-- ── Tab: Fund ───────────────────────────────────────── -->
            <template v-if="tab === 'fund'">

                <div v-if="funds.length === 0" class="acct-empty-notice">
                    <i class="ri-wallet-3-line"></i>
                    No petty cash fund set up yet.
                    Go to <strong>Libraries → Funds</strong> to create one.
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
                    <div class="library-card-body">
                        <!-- Imprest reconciliation -->
                        <div class="pc-reconciliation">
                            <div class="pc-recon-item">
                                <span class="pc-recon-label">Fixed Fund Amount</span>
                                <span class="pc-recon-value">{{ fund.fixed_formatted }}</span>
                            </div>
                            <div class="pc-recon-sep">–</div>
                            <div class="pc-recon-item">
                                <span class="pc-recon-label">Unreimbursed Vouchers</span>
                                <span class="pc-recon-value text-warning">₱{{ formatNum(fund.unsubmitted_vouchers) }}</span>
                            </div>
                            <div class="pc-recon-sep">=</div>
                            <div class="pc-recon-item">
                                <span class="pc-recon-label">Cash on Hand</span>
                                <span class="pc-recon-value" :class="fund.low_balance ? 'text-danger' : 'text-success'">
                                    {{ fund.balance_formatted }}
                                </span>
                            </div>
                        </div>
                        <p class="pc-recon-note">
                            <i class="ri-information-line"></i>
                            Cash on Hand + Unreimbursed Vouchers must always equal the Fixed Fund Amount (₱{{ formatNum(fund.fixed_amount) }}).
                        </p>
                    </div>
                </div>

            </template>

            <!-- ── Tab: Vouchers (PCVs) ───────────────────────────── -->
            <template v-if="tab === 'vouchers'">
                <div class="library-card">
                    <div class="library-card-header">
                        <div class="d-flex align-items-center gap-3">
                            <div class="header-icon"><i class="ri-receipt-line"></i></div>
                            <div>
                                <h4 class="header-title mb-0">Petty Cash Vouchers</h4>
                                <p class="header-subtitle mb-0">Record every disbursement from the fund. One voucher per expense.</p>
                            </div>
                        </div>
                        <button class="acct-btn-primary" @click="openVoucherModal">
                            <i class="ri-add-line"></i> New Voucher
                        </button>
                    </div>
                    <div class="library-card-body p-0">

                        <!-- Filter bar -->
                        <div class="px-3 py-2 border-bottom d-flex align-items-center gap-2 flex-wrap">
                            <select v-model="vFilter.fund_id" class="form-select form-select-sm" style="width:180px">
                                <option value="">All Funds</option>
                                <option v-for="f in funds" :key="f.id" :value="f.id">{{ f.name }}</option>
                            </select>
                            <select v-model="vFilter.status" class="form-select form-select-sm" style="width:150px">
                                <option value="">All Status</option>
                                <option value="recorded">Pending</option>
                                <option value="submitted">Submitted</option>
                                <option value="reimbursed">Reimbursed</option>
                            </select>
                            <input v-model="vFilter.search" type="text" class="form-control form-control-sm" style="width:200px" placeholder="Search payee, description…" />
                        </div>

                        <div class="table-responsive">
                            <table class="table pc-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Voucher #</th>
                                        <th>Date</th>
                                        <th>Fund</th>
                                        <th>Payee</th>
                                        <th>Category</th>
                                        <th>Description</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Receipt</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="filteredVouchers.length === 0">
                                        <td colspan="10" class="text-center text-muted py-4">No vouchers found.</td>
                                    </tr>
                                    <tr v-for="v in filteredVouchers" :key="v.id">
                                        <td class="font-monospace text-muted">{{ v.voucher_no || '—' }}</td>
                                        <td>{{ v.expense_date }}</td>
                                        <td>{{ v.fund_name }}</td>
                                        <td class="fw-semibold">{{ v.payee }}</td>
                                        <td>{{ formatType(v.expense_type) }}</td>
                                        <td class="text-muted" style="max-width:200px;white-space:normal">{{ v.description || '—' }}</td>
                                        <td class="text-end fw-semibold">{{ v.amount_fmt }}</td>
                                        <td class="text-center">
                                            <span class="pc-status-chip" :class="v.status">{{ statusLabel(v.status) }}</span>
                                        </td>
                                        <td class="text-center">
                                            <a v-if="v.receipt_path" :href="v.receipt_path" target="_blank" class="pc-receipt-link">
                                                <i class="ri-file-line"></i>
                                            </a>
                                            <span v-else class="text-muted">—</span>
                                        </td>
                                        <td class="text-center">
                                            <button
                                                v-if="v.status === 'recorded'"
                                                class="pc-void-btn"
                                                @click="confirmVoid(v)"
                                                title="Void voucher"
                                            ><i class="ri-delete-bin-line"></i></button>
                                            <span v-else class="text-muted">—</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </template>

            <!-- ── Tab: Replenishments ────────────────────────────── -->
            <template v-if="tab === 'replenishments'">
                <div class="library-card">
                    <div class="library-card-header">
                        <div class="d-flex align-items-center gap-3">
                            <div class="header-icon"><i class="ri-refresh-line"></i></div>
                            <div>
                                <h4 class="header-title mb-0">Replenishment Requests</h4>
                                <p class="header-subtitle mb-0">Bundle pending vouchers and submit to Finance for reimbursement.</p>
                            </div>
                        </div>
                        <button class="acct-btn-primary" @click="createReplenishment" :disabled="replenishing">
                            <i class="ri-file-add-line"></i> New Request
                        </button>
                    </div>
                    <div class="library-card-body p-0">
                        <div class="table-responsive">
                            <table class="table pc-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Reference</th>
                                        <th>Fund</th>
                                        <th>Period</th>
                                        <th class="text-center">Vouchers</th>
                                        <th class="text-end">Total</th>
                                        <th class="text-center">Status</th>
                                        <th>Submitted</th>
                                        <th>Reviewed By</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="replenishments.length === 0">
                                        <td colspan="9" class="text-center text-muted py-4">No replenishment requests yet.</td>
                                    </tr>
                                    <tr v-for="r in replenishments" :key="r.id">
                                        <td class="font-monospace">{{ r.reference_no }}</td>
                                        <td>{{ r.fund_name }}</td>
                                        <td class="text-muted">{{ r.period_label || '—' }}</td>
                                        <td class="text-center">{{ r.expense_count }}</td>
                                        <td class="text-end fw-semibold">{{ r.total_formatted }}</td>
                                        <td class="text-center">
                                            <span class="pc-status-chip" :class="r.status">{{ replenishLabel(r.status) }}</span>
                                        </td>
                                        <td class="text-muted">{{ r.submitted_at || '—' }}</td>
                                        <td class="text-muted">{{ r.reviewed_by || '—' }}</td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-1">
                                                <button class="pc-action-btn view" @click="viewReplenishment(r)" title="View vouchers">
                                                    <i class="ri-eye-line"></i>
                                                </button>
                                                <button v-if="r.status === 'draft'" class="pc-action-btn submit" @click="submitReplenishment(r)" title="Submit for approval">
                                                    <i class="ri-send-plane-line"></i>
                                                </button>
                                                <button v-if="r.status === 'submitted'" class="pc-action-btn approve" @click="openApproval(r, 'approve')" title="Approve">
                                                    <i class="ri-check-line"></i>
                                                </button>
                                                <button v-if="r.status === 'submitted'" class="pc-action-btn reject" @click="openApproval(r, 'reject')" title="Reject">
                                                    <i class="ri-close-line"></i>
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

        </template>

        <!-- ── New Voucher Modal ──────────────────────────────────── -->
        <div v-if="voucherModal.open" class="modal-overlay active" @click.self="voucherModal.open = false">
            <div class="modal-container modal-md" @click.stop>
                <div class="modal-header">
                    <div class="modal-header-icon"><i class="ri-receipt-line"></i></div>
                    <div>
                        <h4 class="mb-0">New Petty Cash Voucher</h4>
                        <p class="header-subtitle mb-0">Record a disbursement from the petty cash fund.</p>
                    </div>
                    <button class="close-btn ms-auto" @click="voucherModal.open = false"><i class="ri-close-line fs-20"></i></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Fund <span class="text-danger">*</span></label>
                            <select v-model="voucherForm.fund_id" class="form-select" :class="{ 'is-invalid': voucherErrors.fund_id }">
                                <option value="">-- Select fund --</option>
                                <option v-for="f in activeFunds" :key="f.id" :value="f.id">
                                    {{ f.name }} ({{ f.balance_formatted }})
                                </option>
                            </select>
                            <div v-if="voucherErrors.fund_id" class="invalid-feedback">{{ voucherErrors.fund_id }}</div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                            <input v-model="voucherForm.expense_date" type="date" class="form-control" :class="{ 'is-invalid': voucherErrors.expense_date }" />
                            <div v-if="voucherErrors.expense_date" class="invalid-feedback">{{ voucherErrors.expense_date }}</div>
                        </div>
                        <div class="col-7">
                            <label class="form-label fw-semibold">Payee <span class="text-danger">*</span></label>
                            <input v-model="voucherForm.payee" type="text" class="form-control" :class="{ 'is-invalid': voucherErrors.payee }" placeholder="e.g. Mercury Drug, Grab" maxlength="120" />
                            <div v-if="voucherErrors.payee" class="invalid-feedback">{{ voucherErrors.payee }}</div>
                        </div>
                        <div class="col-5">
                            <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                            <input v-model="voucherForm.amount" type="number" step="0.01" min="0.01" class="form-control" :class="{ 'is-invalid': voucherErrors.amount }" placeholder="0.00" />
                            <div v-if="voucherErrors.amount" class="invalid-feedback">{{ voucherErrors.amount }}</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                            <div class="d-flex flex-wrap gap-2">
                                <button
                                    v-for="t in expenseTypes"
                                    :key="t.value"
                                    type="button"
                                    class="pc-type-btn"
                                    :class="{ active: voucherForm.expense_type === t.value }"
                                    @click="voucherForm.expense_type = t.value"
                                >{{ t.label }}</button>
                            </div>
                            <div v-if="voucherErrors.expense_type" class="text-danger mt-1" style="font-size:0.8rem">{{ voucherErrors.expense_type }}</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Purpose / Description</label>
                            <textarea v-model="voucherForm.description" class="form-control" rows="2" maxlength="300" placeholder="What was purchased and why…"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Receipt</label>
                            <input type="file" class="form-control" accept=".jpg,.jpeg,.png,.pdf" @change="voucherForm.receipt = $event.target.files[0]" />
                            <small class="text-muted">JPG, PNG or PDF · Max 5 MB</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="voucherModal.open = false">Cancel</button>
                    <button class="btn btn-save" @click="saveVoucher" :disabled="voucherModal.saving">
                        <span v-if="voucherModal.saving"><i class="ri-loader-4-line spin me-1"></i>Saving…</span>
                        <span v-else><i class="ri-save-line me-1"></i>Record Voucher</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ── View Replenishment Modal ───────────────────────────── -->
        <div v-if="viewModal.open" class="modal-overlay active" @click.self="viewModal.open = false">
            <div class="modal-container modal-lg" @click.stop>
                <div class="modal-header">
                    <div class="modal-header-icon"><i class="ri-file-list-3-line"></i></div>
                    <div>
                        <h4 class="mb-0">{{ viewModal.data?.reference_no }}</h4>
                        <p class="header-subtitle mb-0">{{ viewModal.data?.fund_name }} · {{ viewModal.data?.total_formatted }}</p>
                    </div>
                    <button class="close-btn ms-auto" @click="viewModal.open = false"><i class="ri-close-line fs-20"></i></button>
                </div>
                <div class="modal-body p-4">
                    <table class="table pc-table mb-0">
                        <thead>
                            <tr>
                                <th>Voucher #</th>
                                <th>Date</th>
                                <th>Payee</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="v in viewModal.data?.vouchers" :key="v.voucher_no">
                                <td class="font-monospace">{{ v.voucher_no }}</td>
                                <td>{{ v.expense_date }}</td>
                                <td>{{ v.payee }}</td>
                                <td>{{ formatType(v.expense_type) }}</td>
                                <td class="text-muted">{{ v.description || '—' }}</td>
                                <td class="text-end fw-semibold">{{ v.amount_fmt }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="5" class="text-end">Total</th>
                                <th class="text-end">{{ viewModal.data?.total_formatted }}</th>
                            </tr>
                        </tfoot>
                    </table>
                    <div v-if="viewModal.data?.review_notes" class="pc-review-notes mt-3">
                        <strong>Finance Notes:</strong> {{ viewModal.data.review_notes }}
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="viewModal.open = false">Close</button>
                </div>
            </div>
        </div>

        <!-- ── Approval Modal ─────────────────────────────────────── -->
        <div v-if="approvalModal.open" class="modal-overlay active" @click.self="approvalModal.open = false">
            <div class="modal-container modal-sm" @click.stop>
                <div class="modal-header">
                    <div class="modal-header-icon" :class="approvalModal.action === 'approve' ? 'approve-icon' : 'reject-icon'">
                        <i :class="approvalModal.action === 'approve' ? 'ri-check-line' : 'ri-close-line'"></i>
                    </div>
                    <div>
                        <h4 class="mb-0">{{ approvalModal.action === 'approve' ? 'Approve Request' : 'Reject Request' }}</h4>
                        <p class="header-subtitle mb-0">{{ approvalModal.data?.reference_no }}</p>
                    </div>
                    <button class="close-btn ms-auto" @click="approvalModal.open = false"><i class="ri-close-line fs-20"></i></button>
                </div>
                <div class="modal-body p-4">
                    <p v-if="approvalModal.action === 'approve'" class="text-muted mb-3">
                        Approving will post the journal entry and restore the fund balance by
                        <strong>{{ approvalModal.data?.total_formatted }}</strong>.
                    </p>
                    <p v-else class="text-muted mb-3">
                        Rejecting will return all vouchers to the custodian for correction.
                    </p>
                    <label class="form-label fw-semibold">Notes <span v-if="approvalModal.action === 'reject'" class="text-danger">*</span></label>
                    <textarea v-model="approvalModal.notes" class="form-control" rows="3" placeholder="Optional notes…"></textarea>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="approvalModal.open = false">Cancel</button>
                    <button
                        class="btn"
                        :class="approvalModal.action === 'approve' ? 'btn-save' : 'btn-danger'"
                        @click="submitApproval"
                        :disabled="approvalModal.saving"
                    >
                        <span v-if="approvalModal.saving"><i class="ri-loader-4-line spin me-1"></i>Processing…</span>
                        <span v-else>{{ approvalModal.action === 'approve' ? 'Approve & Post' : 'Reject' }}</span>
                    </button>
                </div>
            </div>
        </div>

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

    </div>
</template>

<script>
import { router } from '@inertiajs/vue3';
import MainLayout from '@/Shared/Layouts/Main.vue';
import AccountingLayout from '@/Pages/Modules/Accounting/AccountingLayout.vue';
import axios from 'axios';

export default {
    layout: [MainLayout, AccountingLayout],
    props: {
        dataReady:      { type: Boolean, default: false },
        funds:          { type: Array,   default: () => [] },
        vouchers:       { type: Array,   default: () => [] },
        replenishments: { type: Array,   default: () => [] },
        users:          { type: Array,   default: () => [] },
        bankAccounts:   { type: Array,   default: () => [] },
        expenseTypes:   { type: Array,   default: () => [] },
    },
    data() {
        return {
            tab: 'fund',
            replenishing: false,

            // Voucher filters
            vFilter: { fund_id: '', status: '', search: '' },

            // New Voucher modal
            voucherModal: { open: false, saving: false },
            voucherForm:  { fund_id: '', expense_date: new Date().toISOString().slice(0, 10), payee: '', expense_type: '', amount: '', description: '', receipt: null },
            voucherErrors: {},

            // View replenishment modal
            viewModal: { open: false, data: null },

            // Approval modal
            approvalModal: { open: false, action: 'approve', data: null, notes: '', saving: false },

            // Top Up modal
            topUpModal: { open: false, saving: false },
            topUpForm:  { fund: null, amount: '', top_up_date: new Date().toISOString().slice(0, 10), bank_account_id: '', notes: '' },

            // Local copies (updated after mutations)
            localFunds:          this.funds,
            localVouchers:       this.vouchers,
            localReplenishments: this.replenishments,
        };
    },
    computed: {
        activeFunds() {
            return this.localFunds.filter(f => f.is_active);
        },
        pendingVoucherCount() {
            return this.localVouchers.filter(v => v.status === 'recorded').length;
        },
        filteredVouchers() {
            return this.localVouchers.filter(v => {
                if (this.vFilter.fund_id && v.fund_id !== this.vFilter.fund_id) return false;
                if (this.vFilter.status  && v.status  !== this.vFilter.status)  return false;
                if (this.vFilter.search) {
                    const q = this.vFilter.search.toLowerCase();
                    if (!(v.payee || '').toLowerCase().includes(q) &&
                        !(v.description || '').toLowerCase().includes(q) &&
                        !(v.voucher_no || '').toLowerCase().includes(q)) return false;
                }
                return true;
            });
        },
    },
    methods: {
        formatNum(n) { return Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 }); },
        formatType(t) {
            const map = { operational: 'Operational', utilities: 'Utilities', supplies: 'Office Supplies', transportation: 'Transportation', maintenance: 'Maintenance', others: 'Others' };
            return map[t] || t;
        },
        statusLabel(s) {
            return { recorded: 'Pending', submitted: 'Submitted', reimbursed: 'Reimbursed', voided: 'Voided' }[s] || s;
        },
        replenishLabel(s) {
            return { draft: 'Draft', submitted: 'For Review', approved: 'Approved', rejected: 'Rejected' }[s] || s;
        },

        openVoucherModal() {
            this.voucherForm   = { fund_id: this.activeFunds[0]?.id || '', expense_date: new Date().toISOString().slice(0, 10), payee: '', expense_type: '', amount: '', description: '', receipt: null };
            this.voucherErrors = {};
            this.voucherModal.open = true;
        },
        async saveVoucher() {
            this.voucherErrors = {};
            if (!this.voucherForm.fund_id)      { this.voucherErrors.fund_id = 'Please select a fund.'; return; }
            if (!this.voucherForm.expense_date) { this.voucherErrors.expense_date = 'Date is required.'; return; }
            if (!this.voucherForm.payee)        { this.voucherErrors.payee = 'Payee is required.'; return; }
            if (!this.voucherForm.expense_type) { this.voucherErrors.expense_type = 'Please select a category.'; return; }
            if (!this.voucherForm.amount || this.voucherForm.amount <= 0) { this.voucherErrors.amount = 'Enter a valid amount.'; return; }

            const formData = new FormData();
            Object.entries(this.voucherForm).forEach(([k, v]) => { if (v !== null && v !== '') formData.append(k, v); });

            this.voucherModal.saving = true;
            try {
                const res = await axios.post('/accounting/petty-cash/vouchers', formData, { headers: { 'Content-Type': 'multipart/form-data' } });
                this.localVouchers.unshift(res.data.data);
                const idx = this.localFunds.findIndex(f => f.id === res.data.fund.id);
                if (idx !== -1) this.localFunds[idx] = res.data.fund;
                this.voucherModal.open = false;
            } catch (err) {
                const msg = err.response?.data?.message || 'Failed to save voucher.';
                alert(msg);
            } finally {
                this.voucherModal.saving = false;
            }
        },

        confirmVoid(v) {
            if (!confirm(`Void voucher ${v.voucher_no || '#' + v.id}? This will restore the fund balance.`)) return;
            axios.delete(`/accounting/petty-cash/vouchers/${v.id}`)
                .then(() => {
                    const idx = this.localVouchers.findIndex(x => x.id === v.id);
                    if (idx !== -1) this.localVouchers.splice(idx, 1);
                    router.reload({ only: ['funds'] });
                })
                .catch(err => alert(err.response?.data?.message || 'Failed to void voucher.'));
        },

        async createReplenishment() {
            if (this.activeFunds.length === 0) { alert('No active fund found.'); return; }
            const fundId = this.activeFunds[0].id;
            const pending = this.localVouchers.filter(v => v.fund_id === fundId && v.status === 'recorded');
            if (pending.length === 0) { alert('No pending vouchers to bundle. Record expenses first.'); return; }
            if (!confirm(`Bundle ${pending.length} pending voucher(s) into a replenishment request?`)) return;

            this.replenishing = true;
            try {
                const res = await axios.post('/replenishments', { fund_id: fundId });
                this.localReplenishments.unshift(res.data.data);
                pending.forEach(v => { const idx = this.localVouchers.findIndex(x => x.id === v.id); if (idx !== -1) this.localVouchers[idx].status = 'submitted'; });
                this.tab = 'replenishments';
            } catch (err) {
                alert(err.response?.data?.errors?.expenses?.[0] || err.response?.data?.message || 'Failed to create replenishment.');
            } finally {
                this.replenishing = false;
            }
        },

        viewReplenishment(r) {
            this.viewModal = { open: true, data: r };
        },

        async submitReplenishment(r) {
            if (!confirm(`Submit ${r.reference_no} for Finance approval?`)) return;
            try {
                const res = await axios.patch(`/replenishments/${r.id}/submit`);
                const idx = this.localReplenishments.findIndex(x => x.id === r.id);
                if (idx !== -1) this.localReplenishments[idx] = res.data.data;
            } catch (err) {
                alert(err.response?.data?.message || 'Failed to submit.');
            }
        },

        openApproval(r, action) {
            this.approvalModal = { open: true, action, data: r, notes: '', saving: false };
        },
        async submitApproval() {
            this.approvalModal.saving = true;
            const { action, data, notes } = this.approvalModal;
            try {
                const res = await axios.patch(`/replenishments/${data.id}/${action}`, { review_notes: notes });
                const idx = this.localReplenishments.findIndex(x => x.id === data.id);
                if (idx !== -1) this.localReplenishments[idx] = res.data.data;
                if (action === 'approve') {
                    router.reload({ only: ['funds'] });
                } else {
                    this.localVouchers.forEach(v => { if (v.status === 'submitted' && v.fund_id === data.fund_id) v.status = 'recorded'; });
                }
                this.approvalModal.open = false;
            } catch (err) {
                alert(err.response?.data?.message || `Failed to ${action}.`);
            } finally {
                this.approvalModal.saving = false;
            }
        },

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
    },
};
</script>

<style scoped>
/* ── Tab bar ───────────────────────────────────────────────── */
.pc-tab-bar {
    display: flex; gap: 0.4rem;
    border-bottom: 2px solid #e8f0ed;
    padding-bottom: 0;
}
.pc-tab-btn {
    display: inline-flex; align-items: center; gap: 0.3rem;
    padding: 0.5rem 1rem; border: none; background: none;
    border-radius: 8px 8px 0 0; font-size: 0.84rem; font-weight: 600;
    color: #6b8c85; cursor: pointer; position: relative;
    transition: color 0.12s, background 0.12s;
}
.pc-tab-btn:hover { background: #f0f7f4; color: #335c52; }
.pc-tab-btn.active { color: #1e3530; background: #fff; border: 2px solid #e8f0ed; border-bottom: 2px solid #fff; margin-bottom: -2px; }
.pc-badge { background: #ef4444; color: #fff; border-radius: 999px; font-size: 0.7rem; padding: 1px 6px; font-weight: 700; }

/* ── Reconciliation display ────────────────────────────────── */
.pc-reconciliation {
    display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
    padding: 1rem; background: #f7fbfa; border-radius: 12px;
}
.pc-recon-item { display: flex; flex-direction: column; align-items: center; min-width: 140px; }
.pc-recon-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #9ab8af; margin-bottom: 0.2rem; }
.pc-recon-value { font-size: 1.1rem; font-weight: 800; color: #1e3530; }
.pc-recon-sep { font-size: 1.4rem; font-weight: 700; color: #c4d9d2; }
.pc-recon-note { font-size: 0.75rem; color: #9ab8af; margin: 0.75rem 0 0; }

.pc-low-badge {
    display: inline-flex; align-items: center; gap: 0.3rem;
    padding: 0.3rem 0.75rem; border-radius: 999px;
    background: #fef2f2; color: #dc2626; border: 1px solid #fca5a5;
    font-size: 0.78rem; font-weight: 700;
}

/* ── Table ─────────────────────────────────────────────────── */
.pc-table thead th {
    background: #edf5f2; color: #527267;
    font-size: 0.74rem; font-weight: 700; text-transform: uppercase;
    white-space: nowrap; padding: 0.6rem 0.8rem;
}
.pc-table tbody td { font-size: 0.85rem; vertical-align: middle; padding: 0.6rem 0.8rem; }

/* ── Status chips ──────────────────────────────────────────── */
.pc-status-chip {
    display: inline-block; padding: 2px 10px; border-radius: 999px;
    font-size: 0.72rem; font-weight: 700;
}
.pc-status-chip.recorded   { background: #fef9c3; color: #854d0e; }
.pc-status-chip.submitted  { background: #dbeafe; color: #1e40af; }
.pc-status-chip.reimbursed { background: #dcfce7; color: #166534; }
.pc-status-chip.draft      { background: #f3f4f6; color: #6b7280; }
.pc-status-chip.approved   { background: #dcfce7; color: #166534; }
.pc-status-chip.rejected   { background: #fee2e2; color: #991b1b; }

/* ── Type buttons ──────────────────────────────────────────── */
.pc-type-btn {
    padding: 0.3rem 0.8rem; border-radius: 999px;
    border: 1px solid #c4d9d2; background: #f7fbfa;
    color: #335c52; font-size: 0.8rem; font-weight: 600;
    cursor: pointer; transition: all 0.12s;
}
.pc-type-btn:hover { background: #edf5f2; }
.pc-type-btn.active { background: #3d8d7a; border-color: #3d8d7a; color: #fff; }

/* ── Action buttons ────────────────────────────────────────── */
.pc-action-btn {
    width: 28px; height: 28px; border-radius: 7px; border: 1px solid #e0eeea;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.9rem; cursor: pointer; background: #f7fbfa; color: #527267;
    transition: all 0.12s;
}
.pc-action-btn.view    { color: #3d8d7a; }
.pc-action-btn.submit  { color: #2563eb; border-color: #bfdbfe; background: #eff6ff; }
.pc-action-btn.approve { color: #16a34a; border-color: #bbf7d0; background: #f0fdf4; }
.pc-action-btn.reject  { color: #dc2626; border-color: #fca5a5; background: #fef2f2; }
.pc-action-btn:hover   { filter: brightness(0.92); }

.pc-void-btn {
    width: 28px; height: 28px; border-radius: 7px; border: 1px solid #fca5a5;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.88rem; cursor: pointer; background: #fef2f2; color: #dc2626;
    transition: all 0.12s;
}
.pc-void-btn:hover { background: #fee2e2; }

.pc-receipt-link { color: #3d8d7a; font-size: 1rem; }
.pc-review-notes { background: #fef9c3; border: 1px solid #fde68a; border-radius: 8px; padding: 0.75rem 1rem; font-size: 0.84rem; }

.approve-icon { background: rgba(22,163,74,0.12) !important; color: #16a34a !important; }
.reject-icon  { background: rgba(220,38,38,0.12) !important; color: #dc2626 !important; }

.spin { animation: spin 1s linear infinite; display: inline-block; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
