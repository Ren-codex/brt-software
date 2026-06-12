<template>
    <div>
        <PageHeader :title="'Expense Management'" :pageTitle="'List'" />
        <BRow>
            <div class="col-md-12">
                <div class="library-card">

                    <!-- Tab bar -->
                    <div class="exp-tab-bar">
                        <button class="exp-tab-btn" :class="{ active: activeTab === 'expenses' }" @click="activeTab = 'expenses'">
                            <i class="ri-receipt-line me-1"></i>Expenses
                        </button>
                        <button class="exp-tab-btn" :class="{ active: activeTab === 'replenishments' }" @click="switchToReplenishments">
                            <i class="ri-refresh-line me-1"></i>Replenishment Requests
                            <span v-if="pendingCount > 0" class="rep-badge">{{ pendingCount }}</span>
                        </button>
                    </div>

                    <!-- ── EXPENSES TAB ──────────────────────────────────── -->
                    <template v-if="activeTab === 'expenses'">
                        <div class="library-card-header">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="header-icon"><i class="ri-receipt-line fs-24"></i></div>
                                    <div>
                                        <h4 class="header-title mb-1">Expense Records</h4>
                                        <p class="header-subtitle mb-0">Record daily purchases made from the petty cash fund</p>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-outline-secondary btn-sm" @click="$refs.printReport.show()">
                                        <i class="ri-printer-line me-1"></i>Print Report
                                    </button>
                                    <button class="create-btn" @click="openCreate">
                                        <i class="ri-add-line"></i><span>Record Expense</span>
                                    </button>
                                </div>
                        </div>

                        <div class="card-body m-2 p-3">
                            <div class="d-flex gap-2 flex-wrap mb-3">
                                <div class="search-wrapper flex-grow-1">
                                    <i class="ri-search-line search-icon"></i>
                                    <input type="text" v-model="filter.keyword" @input="checkSearchStr(filter.keyword)"
                                        placeholder="Search expenses..." class="search-input">
                                </div>
                                <select v-model="filter.fund_id" class="form-select form-select-sm" style="width:200px" @change="fetch()">
                                    <option value="">All Funds</option>
                                    <option v-for="f in dropdowns.funds" :key="f.id" :value="f.id">{{ f.name }}</option>
                                </select>
                                <select v-model="filter.status" class="form-select form-select-sm" style="width:160px" @change="fetch()">
                                    <option value="">All Status</option>
                                    <option value="recorded">Recorded</option>
                                    <option value="submitted">Submitted</option>
                                    <option value="reimbursed">Reimbursed</option>
                                    <option value="released">Released</option>
                                </select>
                            </div>

                            <div class="table-responsive table-card">
                                <table class="table align-middle table-striped table-centered mb-0">
                                    <thead class="table-light thead-fixed">
                                        <tr class="fs-11">
                                            <th style="width:3%">#</th>
                                            <th style="width:4%" class="text-center">View</th>
                                            <th style="width:12%">Type</th>
                                            <th style="width:10%">Amount</th>
                                            <th style="width:10%">Date</th>
                                            <th style="width:15%">Fund</th>
                                            <th style="width:20%">Description</th>
                                            <th style="width:8%">Status</th>
                                            <th style="width:8%">By</th>
                                            <th style="width:10%" class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="table-white fs-12">
                                        <template v-for="(list, index) in lists" :key="list.id">
                                            <tr @click="toggleExpanded(index)"
                                                :class="{ 'bg-info-subtle': selectedRow === index, 'expanded-row': isExpanded(index), 'main-table-row': true }">
                                                <td class="text-center">{{ index + 1 }}</td>
                                                <td class="text-center">
                                                    <button class="collapse-btn" @click.stop="toggleExpanded(index)">
                                                        <div class="expand-icon" :class="{ rotated: isExpanded(index) }">
                                                            <i class="ri-arrow-right-s-line"></i>
                                                        </div>
                                                    </button>
                                                </td>
                                                <td class="text-capitalize">{{ list.expense_type || '-' }}</td>
                                                <td>₱{{ Number(list.amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</td>
                                                <td>{{ list.expense_date || '-' }}</td>
                                                <td class="text-muted">{{ list.fund?.name || '-' }}</td>
                                                <td>
                                                    <span v-if="list.description">
                                                        {{ list.description }}
                                                        <a v-if="list.receipt_path" :href="`/storage/${list.receipt_path}`" target="_blank" class="receipt-icon-link ms-1" @click.stop>
                                                            <i class="ri-attachment-2"></i>
                                                        </a>
                                                    </span>
                                                    <span v-else class="text-muted">—</span>
                                                </td>
                                                <td>
                                                    <span class="exp-status-badge" :class="list.status" :style="getStatusStyle(list.status_info, list.status)">
                                                        {{ list.status || '-' }}
                                                    </span>
                                                </td>
                                                <td class="text-muted">{{ list.added_by?.name || '-' }}</td>
                                                <td class="text-center">
                                                    <div class="d-flex justify-content-center gap-1">
                                                        <!-- Approve -->
                                                        <button
                                                            v-if="list.status === 'recorded' && canApprove"
                                                            @click.stop="approveExpense(list)"
                                                            class="action-icon-btn approve"
                                                            title="Approve"
                                                        >
                                                            <i class="ri-check-double-line"></i>
                                                        </button>
                                                        <!-- Release -->
                                                        <button
                                                            v-if="list.status === 'approved' && canRelease"
                                                            @click.stop="releaseExpense(list)"
                                                            class="action-icon-btn release"
                                                            title="Release"
                                                        >
                                                            <i class="ri-send-plane-line"></i>
                                                        </button>
                                                        <!-- Void -->
                                                        <button
                                                            v-if="['recorded', 'approved', 'submitted'].includes(list.status) && canVoid"
                                                            @click.stop="voidExpense(list)"
                                                            class="action-icon-btn void"
                                                            title="Void"
                                                        >
                                                            <i class="ri-close-circle-line"></i>
                                                        </button>
                                                        <button class="action-icon-btn edit" @click.stop="openEdit(list, index)" title="Edit"
                                                            v-if="['recorded'].includes(list.status)">
                                                            <i class="ri-pencil-line"></i>
                                                        </button>
                                                        <button class="action-icon-btn delete" @click.stop="onDelete(list.id)" title="Delete"
                                                            v-if="['recorded'].includes(list.status)">
                                                            <i class="ri-delete-bin-line"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr v-if="isExpanded(index)" class="details-row">
                                                <td colspan="10">
                                                    <div class="details-container">
                                                        <div class="details-content">
                                                            <div class="row g-3">
                                                                <div class="col-md-6">
                                                                    <div class="info-card">
                                                                        <div class="info-card-header">
                                                                            <i class="ri-file-list-3-line"></i>
                                                                            <h6>Expense Details</h6>
                                                                        </div>
                                                                        <div class="info-card-body">
                                                                            <div class="info-item">
                                                                                <span class="info-label">Fund</span>
                                                                                <span class="info-value">{{ list.fund?.name || '-' }}</span>
                                                                            </div>
                                                                            <div class="info-item">
                                                                                <span class="info-label">Type</span>
                                                                                <span class="info-value text-capitalize">{{ list.expense_type || '-' }}</span>
                                                                            </div>
                                                                            <div class="info-item">
                                                                                <span class="info-label">Amount</span>
                                                                                <span class="info-value">₱{{ Number(list.amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</span>
                                                                            </div>
                                                                            <div class="info-item">
                                                                                <span class="info-label">Date</span>
                                                                                <span class="info-value">{{ list.expense_date || '-' }}</span>
                                                                            </div>
                                                                            <div class="info-item info-item-description">
                                                                                <span class="info-label">Description</span>
                                                                                <span class="info-value">{{ list.description || '-' }}</span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="info-card">
                                                                        <div class="info-card-header">
                                                                            <i class="ri-user-settings-line"></i>
                                                                            <h6>Status & Receipt</h6>
                                                                        </div>
                                                                        <div class="info-card-body">
                                                                            <div class="info-item">
                                                                                <span class="info-label">Status</span>
                                                                                <span class="exp-status-badge" :class="list.status" :style="getStatusStyle(list.status_info, list.status)">{{ list.status || '-' }}</span>
                                                                            </div>
                                                                            <div class="info-item">
                                                                                <span class="info-label">Recorded By</span>
                                                                                <span class="info-value">{{ list.added_by?.name || '-' }}</span>
                                                                            </div>
                                                                            <div class="info-item" v-if="list.receipt_path">
                                                                                <span class="info-label">Receipt</span>
                                                                                <a :href="`/storage/${list.receipt_path}`" target="_blank" class="receipt-link">
                                                                                    <i class="ri-file-line me-1"></i>View Receipt
                                                                                </a>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div v-if="(list.status === 'recorded' && canApprove) || (list.status === 'approved' && canRelease) || (['recorded', 'approved', 'submitted'].includes(list.status) && canVoid)" class="d-flex justify-content-end gap-2 mt-3">
                                                        <button
                                                            v-if="list.status === 'recorded' && canApprove"
                                                            @click="approveExpense(list)"
                                                            class="system-action-btn system-action-success"
                                                        >
                                                            <i class="ri-check-double-line me-1"></i>
                                                            Approve Expense
                                                        </button>
                                                        <button
                                                            v-if="list.status === 'approved' && canRelease"
                                                            @click="releaseExpense(list)"
                                                            class="system-action-btn system-action-release"
                                                        >
                                                            <i class="ri-send-plane-line me-1"></i>
                                                            Release Expense
                                                        </button>
                                                        <button
                                                            v-if="['recorded', 'approved', 'submitted'].includes(list.status) && canVoid"
                                                            @click="voidExpense(list)"
                                                            class="btn-danger-action"
                                                        >
                                                            <i class="ri-close-circle-line me-1"></i>
                                                            Void Expense
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        </template>
                                        <tr v-if="lists.length === 0">
                                            <td colspan="10" class="text-center text-muted py-4">No expenses found.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="card-footer">
                            <Pagination class="ms-2 me-2 mt-n1" v-if="meta" @fetch="fetch()" :lists="lists.length" :links="links" :pagination="meta" />
                        </div>
                    </template>

                    <!-- ── REPLENISHMENT REQUESTS TAB ───────────────────── -->
                    <template v-if="activeTab === 'replenishments'">
                        <div class="library-card-header">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="header-icon"><i class="ri-refresh-line fs-24"></i></div>
                                    <div>
                                        <h4 class="header-title mb-1">Replenishment Requests</h4>
                                        <p class="header-subtitle mb-0">Bundle recorded expenses and submit for Finance approval</p>
                                    </div>
                                </div>
                                <button class="create-btn" @click="openCreateReplenishment">
                                    <i class="ri-add-line"></i><span>New Request</span>
                                </button>
                        </div>

                        <div class="card-body m-2 p-3">
                            <div class="d-flex gap-2 flex-wrap mb-3">
                                <select v-model="repFilter.fund_id" class="form-select form-select-sm" style="width:200px" @change="fetchReplenishments()">
                                    <option value="">All Funds</option>
                                    <option v-for="f in dropdowns.funds" :key="f.id" :value="f.id">{{ f.name }}</option>
                                </select>
                                <select v-model="repFilter.status" class="form-select form-select-sm" style="width:160px" @change="fetchReplenishments()">
                                    <option value="">All Status</option>
                                    <option value="draft">Draft</option>
                                    <option value="submitted">Submitted</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>

                            <div class="table-responsive table-card">
                                <table class="table align-middle table-striped table-centered mb-0">
                                    <thead class="table-light thead-fixed">
                                        <tr class="fs-11">
                                            <th style="width:3%">#</th>
                                            <th style="width:14%">Reference</th>
                                            <th style="width:15%">Fund</th>
                                            <th style="width:12%">Total Amount</th>
                                            <th style="width:8%"># Expenses</th>
                                            <th style="width:15%">Period</th>
                                            <th style="width:10%">Status</th>
                                            <th style="width:10%">Date</th>
                                            <th style="width:13%" class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="table-white fs-12">
                                        <tr v-for="(r, index) in replenishments" :key="r.id">
                                            <td class="text-center">{{ index + 1 }}</td>
                                            <td>
                                                <span class="fw-600">{{ r.reference_no }}</span>
                                                <span v-if="r.over_weekly_budget" class="over-budget-chip ms-1" title="Exceeds weekly budget">
                                                    <i class="ri-alert-line"></i>
                                                </span>
                                            </td>
                                            <td class="text-muted">{{ r.fund_name }}</td>
                                            <td class="fw-600">{{ r.total_formatted }}</td>
                                            <td class="text-center">{{ r.expense_count }}</td>
                                            <td class="text-muted small">{{ r.period_label || '—' }}</td>
                                            <td>
                                                <span class="exp-status-badge" :class="r.status" :style="getStatusStyle(null, r.status)">{{ r.status }}</span>
                                            </td>
                                            <td class="text-muted small">{{ r.submitted_at || r.created_at?.split(' ')[0] }}</td>
                                            <td class="text-center">
                                                <div class="d-flex justify-content-center gap-1">
                                                    <button class="action-icon-btn view" @click="openViewReplenishment(r)" title="View details">
                                                        <i class="ri-eye-line"></i>
                                                    </button>
                                                    <button v-if="r.status === 'draft'" class="action-icon-btn submit" @click="submitReplenishment(r)" title="Submit for review">
                                                        <i class="ri-send-plane-line"></i>
                                                    </button>
                                                    <button v-if="r.status === 'submitted'" class="action-icon-btn approve" @click="openReview(r, 'approve')" title="Approve">
                                                        <i class="ri-check-double-line"></i>
                                                    </button>
                                                    <button v-if="r.status === 'submitted'" class="action-icon-btn delete" @click="openReview(r, 'reject')" title="Reject">
                                                        <i class="ri-close-circle-line"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="replenishments.length === 0">
                                            <td colspan="9" class="text-center text-muted py-4">No replenishment requests found.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>

                </div>
            </div>
        </BRow>

        <!-- ── Create Replenishment Modal ───────────────────────── -->
        <div v-if="repModal.open" class="modal-overlay active" @click.self="repModal.open = false">
            <div class="modal-container" style="max-width:480px" @click.stop>
                <div class="modal-header">
                    <h2>New Replenishment Request</h2>
                    <button class="close-btn" @click="repModal.open = false"><i class="ri-close-line"></i></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Petty Cash Fund <span class="text-danger">*</span></label>
                        <select v-model="repForm.fund_id" class="form-control">
                            <option value="">Select fund</option>
                            <option v-for="f in dropdowns.funds" :key="f.id" :value="f.id">{{ f.name }}</option>
                        </select>
                        <span class="error-message" v-if="repErrors.fund_id">{{ repErrors.fund_id }}</span>
                    </div>

                    <!-- Pending expenses for selected fund -->
                    <div v-if="repForm.fund_id" class="rep-pending-box mt-3">
                        <div class="rep-pending-header">
                            <span>Expenses to be included</span>
                            <span v-if="repPendingLoading" class="rep-pending-loading"><i class="ri-loader-4-line spinner"></i></span>
                            <span v-else class="rep-pending-count">{{ repPending.length }} item{{ repPending.length !== 1 ? 's' : '' }}</span>
                        </div>
                        <div v-if="!repPendingLoading && repPending.length === 0" class="rep-pending-empty">
                            <i class="ri-inbox-2-line"></i> No recorded expenses for this fund.
                        </div>
                        <div v-else class="rep-pending-list">
                            <div v-for="e in repPending" :key="e.id" class="rep-pending-item">
                                <div class="rep-pending-left">
                                    <span class="rep-pending-type">{{ e.expense_type }}</span>
                                    <span class="rep-pending-desc">{{ e.description || '—' }}</span>
                                </div>
                                <div class="rep-pending-right">
                                    <span class="rep-pending-date">{{ e.expense_date }}</span>
                                    <span class="rep-pending-amount">₱{{ Number(e.amount).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</span>
                                </div>
                            </div>
                            <div v-if="repPending.length > 0" class="rep-pending-total">
                                <span>Total</span>
                                <span>₱{{ repPending.reduce((s, e) => s + Number(e.amount), 0).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mt-3">
                        <label class="form-label">Period Label <span class="text-muted small">(optional)</span></label>
                        <input v-model="repForm.period_label" type="text" class="form-control" placeholder="e.g. Week of May 26–June 1, 2026" />
                    </div>
                    <div v-if="repErrors.expenses" class="error-alert mt-2">
                        <i class="ri-close-circle-fill"></i> {{ repErrors.expenses }}
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-cancel" @click="repModal.open = false"><i class="ri-close-line"></i> Cancel</button>
                    <button class="btn btn-save" :disabled="repSaving || repPending.length === 0" @click="submitCreateReplenishment">
                        <i class="ri-refresh-line" v-if="!repSaving"></i>
                        <i class="ri-loader-4-line spinner" v-else></i>
                        {{ repSaving ? 'Creating...' : 'Create Request' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ── View Replenishment Modal ─────────────────────────── -->
        <div v-if="viewModal.open" class="modal-overlay active" @click.self="viewModal.open = false">
            <div class="modal-container modal-lg" @click.stop>
                <div class="modal-header">
                    <h2>{{ viewModal.data?.reference_no }} — {{ viewModal.data?.fund_name }}</h2>
                    <button class="close-btn" @click="viewModal.open = false"><i class="ri-close-line"></i></button>
                </div>
                <div class="modal-body" v-if="viewModal.data">
                    <!-- Summary row -->
                    <div class="rep-view-summary mb-3">
                        <div class="rep-view-stat">
                            <span class="rep-view-stat-label">Total Amount</span>
                            <span class="rep-view-stat-value">{{ viewModal.data.total_formatted }}</span>
                        </div>
                        <div class="rep-view-stat">
                            <span class="rep-view-stat-label">Expenses</span>
                            <span class="rep-view-stat-value">{{ viewModal.data.expense_count }}</span>
                        </div>
                        <div class="rep-view-stat">
                            <span class="rep-view-stat-label">Status</span>
                            <span class="exp-status-badge" :class="viewModal.data.status" :style="getStatusStyle(null, viewModal.data.status)">{{ viewModal.data.status }}</span>
                        </div>
                        <div class="rep-view-stat">
                            <span class="rep-view-stat-label">Created By</span>
                            <span class="rep-view-stat-value">{{ viewModal.data.created_by }}</span>
                        </div>
                    </div>

                    <!-- Over-budget alert -->
                    <div v-if="viewModal.data.over_weekly_budget" class="over-budget-alert mb-3">
                        <i class="ri-alert-fill"></i>
                        <span>This request exceeds the weekly budget by ₱{{ Number(viewModal.data.over_by).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}.</span>
                    </div>

                    <!-- Period & notes -->
                    <div v-if="viewModal.data.period_label" class="text-muted small mb-3">
                        <i class="ri-calendar-line me-1"></i>{{ viewModal.data.period_label }}
                    </div>
                    <div v-if="viewModal.data.review_notes" class="rep-review-notes mb-3">
                        <i class="ri-sticky-note-line me-1"></i><strong>Review Notes:</strong> {{ viewModal.data.review_notes }}
                    </div>

                    <!-- Expenses table -->
                    <table class="table table-sm table-bordered mb-0 fs-12">
                        <thead class="table-light">
                            <tr>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="e in viewModal.data.expenses" :key="e.id">
                                <td class="text-capitalize">{{ e.expense_type }}</td>
                                <td>{{ e.description || '—' }}</td>
                                <td>{{ e.expense_date }}</td>
                                <td>{{ e.amount_fmt }}</td>
                                <td>{{ e.recorded_by }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-cancel" @click="viewModal.open = false"><i class="ri-close-line"></i> Close</button>
                    <button v-if="viewModal.data?.status === 'draft'" class="btn btn-save" @click="submitReplenishment(viewModal.data); viewModal.open = false">
                        <i class="ri-send-plane-line"></i> Submit for Review
                    </button>
                    <template v-if="viewModal.data?.status === 'submitted'">
                        <button class="btn btn-save" @click="openReview(viewModal.data, 'approve'); viewModal.open = false">
                            <i class="ri-check-double-line"></i> Approve
                        </button>
                        <button class="btn btn-danger-action" @click="openReview(viewModal.data, 'reject'); viewModal.open = false">
                            <i class="ri-close-circle-line"></i> Reject
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <!-- ── Finance Review Modal ─────────────────────────────── -->
        <div v-if="reviewModal.open" class="modal-overlay active" @click.self="reviewModal.open = false">
            <div class="modal-container" style="max-width:460px" @click.stop>
                <div class="modal-header">
                    <h2>{{ reviewModal.action === 'approve' ? 'Approve Replenishment' : 'Reject Replenishment' }}</h2>
                    <button class="close-btn" @click="reviewModal.open = false"><i class="ri-close-line"></i></button>
                </div>
                <div class="modal-body">
                    <div v-if="reviewModal.data?.over_weekly_budget" class="over-budget-alert mb-3">
                        <i class="ri-alert-fill"></i>
                        <span>This request exceeds the weekly budget by ₱{{ Number(reviewModal.data.over_by).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}.</span>
                    </div>
                    <p class="text-muted fs-13 mb-3">
                        <template v-if="reviewModal.action === 'approve'">
                            Approving will post the journal entry and replenish <strong>{{ reviewModal.data?.fund_name }}</strong> by <strong>{{ reviewModal.data?.total_formatted }}</strong>.
                        </template>
                        <template v-else>
                            Rejecting will return all expenses to the PIC for correction.
                        </template>
                    </p>
                    <div class="form-group">
                        <label class="form-label">Notes <span class="text-muted small">(optional)</span></label>
                        <textarea v-model="reviewNotes" class="form-control" rows="3" placeholder="Add a note for the PIC..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-cancel" @click="reviewModal.open = false"><i class="ri-close-line"></i> Cancel</button>
                    <button class="btn" :class="reviewModal.action === 'approve' ? 'btn-save' : 'btn-danger-action'" :disabled="reviewSaving" @click="submitReview">
                        <i class="ri-loader-4-line spinner" v-if="reviewSaving"></i>
                        <i class="ri-check-double-line" v-else-if="reviewModal.action === 'approve'"></i>
                        <i class="ri-close-circle-line" v-else></i>
                        {{ reviewSaving ? 'Processing...' : (reviewModal.action === 'approve' ? 'Approve & Post JE' : 'Reject') }}
                    </button>
                </div>
            </div>
        </div>

        <Create @add="fetch()" @update="fetch()" @close="selectedRow = null" :dropdowns="dropdowns" ref="create" />
        <PrintReport ref="printReport" :funds="dropdowns.funds" />
        <DeleteModal ref="deleteModal" />

    </div>

    <!-- Fund Balances floating toggle -->
    <button class="fund-bal-floating-btn" @click="showFundBalances = !showFundBalances"
        :title="showFundBalances ? 'Hide Fund Balances' : 'Show Fund Balances'">
        <span>Fund Balances</span>
    </button>

    <!-- Fund Balances drawer -->
    <transition name="fbd-slide">
        <div v-if="showFundBalances" class="fund-bal-drawer">
            <div class="fbd-header">
                <div class="fbd-title">
                    <i class="ri-wallet-3-line"></i>
                    Fund Balances
                </div>
                <button class="fbd-close" @click="showFundBalances = false"><i class="ri-close-line"></i></button>
            </div>
            <div class="fbd-body">
                <div v-for="f in dropdowns.funds" :key="f.id" class="fund-card">
                    <!-- Card header: icon + fund name + status dot -->
                    <div class="fund-card-header">
                        <div class="fund-card-icon"><i class="ri-safe-2-line"></i></div>
                        <span class="fund-card-name">{{ f.name }}</span>
                        <span class="fund-card-dot" :class="f.balance > 0 ? 'dot-ok' : 'dot-empty'"></span>
                    </div>

                    <!-- Balance -->
                    <div class="fund-card-balance">
                        <span class="fund-card-balance-value">₱{{ Number(f.balance || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</span>
                        <span class="fund-card-balance-label">Available Balance</span>
                    </div>

                    <!-- Weekly budget section (only if set) -->
                    <template v-if="f.weekly_budget > 0">
                        <div class="fund-card-divider"></div>
                        <div class="fund-card-budget-header">
                            <span class="fund-card-budget-title"><i class="ri-calendar-check-line"></i> Weekly Budget</span>
                            <span class="fund-card-budget-total">₱{{ Number(f.weekly_budget).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</span>
                        </div>
                        <div class="fund-card-progress-track">
                            <div class="fund-card-progress-fill" :style="fundBarStyle(f)"></div>
                        </div>
                        <div class="fund-card-metrics">
                            <div class="fund-card-metric">
                                <span class="fund-card-metric-label">Spent</span>
                                <span class="fund-card-metric-value metric-spent">₱{{ Number(f.weekly_spent || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</span>
                            </div>
                            <div class="fund-card-metric-divider"></div>
                            <div class="fund-card-metric">
                                <span class="fund-card-metric-label">Remaining</span>
                                <span class="fund-card-metric-value"
                                    :class="f.weekly_remaining <= 0 ? 'metric-over' : f.weekly_remaining < f.weekly_budget * 0.25 ? 'metric-low' : 'metric-ok'">
                                    ₱{{ Number(f.weekly_remaining ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
                                </span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </transition>
</template>

<script>
import _ from 'lodash';
import PageHeader from '@/Shared/Components/PageHeader.vue';
import Pagination from '@/Shared/Components/Pagination.vue';
import DeleteModal from '@/Shared/Components/Modals/DeleteModal.vue';
import Create from './Modals/Create.vue';
import PrintReport from './Modals/PrintReport.vue';

export default {
    components: { PageHeader, Pagination, Create, DeleteModal, PrintReport },
    props: ['dropdowns'],
    computed: {
        pendingCount() {
            return this.replenishments.filter(r => r.status === 'submitted').length;
        },
        canApprove() {
            const roles = this.$page?.props?.roles || [];
            return ['Administrator', 'Top Management', 'Area Business Manager', 'Super Admin']
                .some(r => roles.includes(r));
        },
        canVoid() {
            const roles = this.$page?.props?.roles || [];
            return ['Administrator', 'Top Management'].some(r => roles.includes(r));
        },
        canRelease() {
            const roles = this.$page?.props?.roles || [];
            return roles.includes('Administrator');
        },
    },
    data() {
        return {
            activeTab: 'expenses',
            showFundBalances: false,
            // Expense list
            lists: [],
            meta:  {},
            links: {},
            filter: { keyword: '', fund_id: '', status: '' },
            selectedRow:  null,
            expandedRow:  null,
            // Replenishment list
            replenishments: [],
            repFilter: { status: '', fund_id: '' },
            // Create replenishment modal
            repModal:          { open: false },
            repForm:           { fund_id: '', period_label: '' },
            repErrors:         {},
            repSaving:         false,
            repPending:        [],
            repPendingLoading: false,
            // View replenishment modal
            viewModal:  { open: false, data: null },
            // Finance review modal
            reviewModal:  { open: false, data: null, action: 'approve' },
            reviewNotes:  '',
            reviewSaving: false,
        };
    },
    watch: {
        'filter.keyword'(val) { this.checkSearchStr(val); },
        'repForm.fund_id'(val) {
            this.repPending = [];
            if (!val) return;
            this.repPendingLoading = true;
            axios.get('/expenses', { params: { option: 'pending', fund_id: val } })
                .then(res => { this.repPending = res.data; })
                .catch(console.error)
                .finally(() => { this.repPendingLoading = false; });
        },
    },
    created() {
        this.fetch();
    },
    methods: {
        // ── Expense list ──────────────────────────────────────────
        checkSearchStr: _.debounce(function () { this.fetch(); }, 300),
        fetch(page_url) {
            axios.get(page_url || '/expenses', {
                params: { keyword: this.filter.keyword, fund_id: this.filter.fund_id, status: this.filter.status, count: 15, option: 'lists' }
            }).then(res => {
                this.lists = res.data.data;
                this.meta  = res.data.meta;
                this.links = res.data.links;
            }).catch(console.error);
        },
        openCreate() { this.$refs.create.show(); },
        openEdit(data, index) { this.selectedRow = index; this.$refs.create.edit(data); },
        async onDelete(id) {
            const confirmed = await this.$refs.deleteModal.show();
            if (!confirmed) return;
            axios.delete(`/expenses/${id}`)
                .then(res => { this.fetch(); this.$toast?.success(res.data.message); })
                .catch(err => this.$toast?.error(err.response?.data?.message || 'Failed to delete'));
        },
        toggleExpanded(i) { this.expandedRow = this.expandedRow === i ? null : i; },
        isExpanded(i) { return this.expandedRow === i; },
        approveExpense(expense) {
            axios.patch(`/expenses/${expense.id}/approve`)
                .then(res => {
                    if (res.data.status === 'error') {
                        this.$swal?.fire?.('Notice', res.data.message, 'warning');
                        return;
                    }
                    const idx = this.lists.findIndex(e => e.id === expense.id);
                    if (idx !== -1) this.lists[idx].status = 'approved';
                })
                .catch(() => {
                    this.$swal?.fire?.('Error', 'Failed to approve expense.', 'error');
                });
        },
        releaseExpense(expense) {
            this.$swal?.fire?.({
                title: 'Release expense?',
                text: 'This will post the expense to accounting. This cannot be undone.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, release it',
                confirmButtonColor: '#3d8d7a',
            }).then(result => {
                if (!result.isConfirmed) return;
                axios.patch(`/expenses/${expense.id}/release`)
                    .then(res => {
                        if (res.data.status === 'error') {
                            this.$swal?.fire?.('Notice', res.data.message, 'warning');
                            return;
                        }
                        const idx = this.lists.findIndex(e => e.id === expense.id);
                        if (idx !== -1) this.lists[idx].status = 'released';
                    })
                    .catch(err => {
                        const msg = err.response?.data?.info || 'Failed to release expense.';
                        this.$swal?.fire?.('Error', msg, 'error');
                    });
            });
        },
        voidExpense(expense) {
            this.$swal?.fire?.({
                title: 'Void expense?',
                text: 'This will cancel the expense and restore the fund balance. This cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, void it',
                confirmButtonColor: '#dc3545',
            }).then(result => {
                if (!result.isConfirmed) return;
                axios.patch(`/expenses/${expense.id}/void`)
                    .then(res => {
                        if (res.data.status === 'error') {
                            this.$swal?.fire?.('Notice', res.data.message, 'warning');
                            return;
                        }
                        const idx = this.lists.findIndex(e => e.id === expense.id);
                        if (idx !== -1) this.lists[idx].status = 'voided';
                    })
                    .catch(() => {
                        this.$swal?.fire?.('Error', 'Failed to void expense.', 'error');
                    });
            });
        },

        // ── Replenishment list ────────────────────────────────────
        switchToReplenishments() {
            this.activeTab = 'replenishments';
            if (this.replenishments.length === 0) this.fetchReplenishments();
        },
        fetchReplenishments() {
            axios.get('/replenishments', {
                params: { status: this.repFilter.status, fund_id: this.repFilter.fund_id, count: 30 }
            }).then(res => { this.replenishments = res.data.data; }).catch(console.error);
        },
        openCreateReplenishment() {
            this.repForm    = { fund_id: '', period_label: '' };
            this.repErrors  = {};
            this.repPending = [];
            this.repModal.open = true;
        },
        async submitCreateReplenishment() {
            this.repSaving = true;
            this.repErrors = {};
            try {
                const res = await axios.post('/replenishments', this.repForm);
                this.repModal.open = false;
                this.replenishments.unshift(res.data.data);
                this.$toast?.success(res.data.message);
            } catch (e) {
                if (e.response?.status === 422) {
                    const errs = e.response.data.errors || {};
                    this.repErrors = Object.fromEntries(Object.entries(errs).map(([k, v]) => [k, v[0]]));
                } else {
                    this.$toast?.error(e.response?.data?.info || 'Failed to create request');
                }
            } finally {
                this.repSaving = false;
            }
        },
        async submitReplenishment(r) {
            try {
                const res = await axios.patch(`/replenishments/${r.id}/submit`);
                this.$toast?.success(res.data.message);
                this.fetchReplenishments();
            } catch (e) {
                this.$toast?.error(e.response?.data?.message || 'Failed to submit');
            }
        },
        async openViewReplenishment(r) {
            try {
                const res = await axios.get(`/replenishments/${r.id}`);
                this.viewModal.data = res.data;
                this.viewModal.open = true;
            } catch (e) { console.error(e); }
        },
        openReview(r, action) {
            this.reviewModal = { open: true, data: r, action };
            this.reviewNotes = '';
        },
        async submitReview() {
            this.reviewSaving = true;
            const id     = this.reviewModal.data.id;
            const action = this.reviewModal.action;
            try {
                const res = await axios.patch(`/replenishments/${id}/${action}`, { review_notes: this.reviewNotes });
                this.$toast?.success(res.data.message);
                this.reviewModal.open = false;
                this.fetchReplenishments();
            } catch (e) {
                this.$toast?.error(e.response?.data?.message || 'Failed');
            } finally {
                this.reviewSaving = false;
            }
        },

        // ── Helpers ───────────────────────────────────────────────
        fundBarStyle(f) {
            if (!f.weekly_budget) return {};
            const pct = Math.min((f.weekly_spent / f.weekly_budget) * 100, 100);
            const color = pct >= 100 ? '#ef4444' : pct >= 75 ? '#f59e0b' : '#22c55e';
            return { width: pct + '%', background: color };
        },
        getStatusStyle(status, fallbackSlug) {
            const fallbacks = {
                recorded:    { bg: '#e0f2fe', text: '#0369a1' },
                submitted:   { bg: '#fef9c3', text: '#854d0e' },
                reimbursed:  { bg: '#dcfce7', text: '#15803d' },
                approved:    { bg: '#fef08a', text: '#713f12' },
                released:    { bg: '#dbeafe', text: '#1d4ed8' },
                draft:       { bg: '#f3f4f6', text: '#374151' },
                rejected:    { bg: '#fee2e2', text: '#991b1b' },
            };
            const bg   = status?.bg_color   || fallbacks[fallbackSlug]?.bg   || '#e2e3e5';
            const text = status?.text_color  || fallbacks[fallbackSlug]?.text || '#6c757d';
            return { color: text, backgroundColor: bg, border: `1px solid ${bg}`, boxShadow: `0 2px 4px ${bg}40` };
        },
    },
}
</script>

<style scoped>
/* ── Tab bar ─────────────────────────────────────────────── */
.exp-tab-bar {
    display: flex;
    gap: 0;
    border-bottom: 2px solid #e2e8f0;
    padding: 0 1.25rem;
    background: #fff;
}
.exp-tab-btn {
    padding: 0.85rem 1.25rem;
    border: none;
    background: transparent;
    font-size: 0.85rem;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.exp-tab-btn:hover { color: #3d8d7a; }
.exp-tab-btn.active { color: #3d8d7a; border-bottom-color: #3d8d7a; }
.rep-badge {
    background: #ef4444;
    color: #fff;
    border-radius: 999px;
    padding: 0.05rem 0.45rem;
    font-size: 0.68rem;
    font-weight: 700;
}

/* ── Shared table row styles ─────────────────────────────── */
.main-table-row { cursor: pointer; transition: all 0.2s ease; border-left: 3px solid transparent; }
.main-table-row:hover { background-color: rgba(61,141,122,0.05) !important; border-left-color: #3D8D7A; }
.main-table-row.expanded-row { background: linear-gradient(90deg,rgba(61,141,122,0.08) 0%,rgba(61,141,122,0.02) 100%); border-left-color: #3D8D7A; }
.collapse-btn { width:30px;height:30px;border:none;border-radius:50%;background:transparent;color:#2e8b57;display:inline-flex;align-items:center;justify-content:center;transition:all 0.2s; }
.collapse-btn:hover { background:rgba(61,141,122,0.08); }
.expand-icon { display:inline-block;transition:transform 0.3s;color:#6c757d; }
.expand-icon i { font-size:18px;vertical-align:middle; }
.expand-icon.rotated { transform:rotate(90deg);color:#3D8D7A; }
.details-row { background-color:#f8fafd;border-bottom:2px solid #e9ecef; }
.details-row td { padding:0 !important;border-top:none !important; }
.details-container { animation:slideDown 0.3s ease-out; }
.details-content { padding:1.25rem 1.5rem; }
@keyframes slideDown { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }

/* ── Status badge ────────────────────────────────────────── */
.exp-status-badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.3px;
    text-transform: capitalize;
}
.exp-status-badge.recorded { border: 1px solid #bae6fd; }

/* ── Action icon buttons ─────────────────────────────────── */
.action-icon-btn { width:28px;height:28px;border:none;border-radius:7px;display:inline-flex;align-items:center;justify-content:center;font-size:0.85rem;cursor:pointer;transition:all 0.15s; }
.action-icon-btn.edit    { background:#e0f2fe;color:#0369a1; }
.action-icon-btn.edit:hover { background:#bae6fd; }
.action-icon-btn.delete  { background:#fee2e2;color:#991b1b; }
.action-icon-btn.delete:hover { background:#fecaca; }
.action-icon-btn.view    { background:#e0f2fe;color:#0369a1; }
.action-icon-btn.view:hover { background:#bae6fd; }
.action-icon-btn.submit  { background:#fef9c3;color:#854d0e; }
.action-icon-btn.submit:hover { background:#fef08a; }
.action-icon-btn.approve { background:#dcfce7;color:#15803d; }
.action-icon-btn.approve:hover { background:#bbf7d0; }
.action-icon-btn.release { background:#d1fae5;color:#065f46; }
.action-icon-btn.release:hover { background:#a7f3d0; }
.action-icon-btn.void    { background:#fee2e2;color:#dc3545;border:1px solid #fca5a5; }
.action-icon-btn.void:hover { background:#fecaca; }

/* ── Info cards (expand row) ─────────────────────────────── */
.info-card { background:white;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.08);border:1px solid #e9ecef;height:100%;overflow:hidden; }
.info-card-header { display:flex;align-items:center;gap:0.75rem;padding:0.85rem 1.25rem;border-bottom:1px solid #e9ecef;background:#f9fafb; }
.info-card-header i { font-size:1.1rem;color:#3D8D7A;background:rgba(61,141,122,0.1);padding:0.4rem;border-radius:8px;width:36px;height:36px;display:flex;align-items:center;justify-content:center; }
.info-card-header h6 { margin:0;font-size:0.85rem;font-weight:600;color:#267A4C;text-transform:uppercase;letter-spacing:0.4px; }
.info-card-body { display:flex;flex-direction:column;padding:0.25rem 1.25rem 1rem; }
.info-item { display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:0.6rem 0;border-bottom:1px dashed #e9ecef; }
.info-item:last-child { border-bottom:none; }
.info-item-description { align-items:flex-start; }
.info-label { color:#6c757d;font-size:0.8rem;font-weight:500; }
.info-value { color:#1e293b;font-size:0.85rem;font-weight:600;text-align:right; }

/* ── Receipt link ────────────────────────────────────────── */
.receipt-link { color:#3d8d7a;font-weight:600;text-decoration:none;font-size:0.85rem; }
.receipt-link:hover { text-decoration:underline; }
.receipt-icon-link { color:#3d8d7a;text-decoration:none; }

/* ── Over-budget alert ───────────────────────────────────── */
.over-budget-alert {
    display:flex;align-items:flex-start;gap:0.5rem;
    background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;
    padding:0.65rem 0.9rem;font-size:0.82rem;color:#9a3412;
}
.over-budget-alert i { font-size:1rem;flex-shrink:0;margin-top:1px;color:#ea580c; }
.over-budget-chip { display:inline-flex;align-items:center;background:#fff7ed;color:#ea580c;border-radius:999px;padding:0.05rem 0.35rem;font-size:0.7rem;border:1px solid #fed7aa; }

/* ── Replenishment view modal ────────────────────────────── */
.rep-view-summary { display:flex;flex-wrap:wrap;gap:1rem;background:#f4faf8;border:1px solid #c4dfd5;border-radius:10px;padding:0.85rem 1rem; }
.rep-view-stat { display:flex;flex-direction:column;gap:0.15rem;flex:1;min-width:100px; }
.rep-view-stat-label { font-size:0.7rem;text-transform:uppercase;letter-spacing:0.05em;color:#6b8c85;font-weight:600; }
.rep-view-stat-value { font-size:0.95rem;font-weight:700;color:#1a4d3d; }
.rep-review-notes { background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:0.65rem 0.9rem;font-size:0.82rem;color:#374151; }

/* ── Create replenishment — pending list ─────────────────── */
.rep-pending-box { border:1px solid #c4dfd5;border-radius:10px;overflow:hidden;background:#f4faf8; }
.rep-pending-header { display:flex;justify-content:space-between;align-items:center;padding:0.5rem 0.9rem;background:#e6f4ef;border-bottom:1px solid #c4dfd5;font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#2a6b54; }
.rep-pending-count { background:#3d8d7a;color:#fff;border-radius:999px;padding:0.1rem 0.5rem;font-size:0.7rem;font-weight:700; }
.rep-pending-loading { color:#6b8c85;font-size:0.82rem; }
.rep-pending-empty { padding:0.85rem;font-size:0.82rem;color:#6b8c85;text-align:center;display:flex;align-items:center;justify-content:center;gap:0.4rem; }
.rep-pending-list { max-height:200px;overflow-y:auto; }
.rep-pending-item { display:flex;justify-content:space-between;align-items:center;gap:0.75rem;padding:0.45rem 0.9rem;border-bottom:1px solid #dceee8;font-size:0.8rem; }
.rep-pending-item:last-of-type { border-bottom:none; }
.rep-pending-left { display:flex;flex-direction:column;gap:0.05rem;min-width:0; }
.rep-pending-type { font-weight:600;color:#1a4d3d;text-transform:capitalize; }
.rep-pending-desc { color:#6b8c85;font-size:0.74rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px; }
.rep-pending-right { display:flex;flex-direction:column;align-items:flex-end;gap:0.05rem;flex-shrink:0; }
.rep-pending-date { font-size:0.72rem;color:#8aada4; }
.rep-pending-amount { font-weight:700;color:#1a4d3d; }
.rep-pending-total { display:flex;justify-content:space-between;padding:0.45rem 0.9rem;background:#e6f4ef;border-top:1px solid #c4dfd5;font-size:0.8rem;font-weight:700;color:#1a4d3d; }

/* ── Misc ────────────────────────────────────────────────── */
.btn-danger-action { background:linear-gradient(135deg,#ef4444 0%,#b91c1c 100%);color:#fff;border:none;border-radius:8px;padding:0.5rem 1.2rem;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:0.35rem; }
.fw-600 { font-weight: 600; }
.fs-13 { font-size: 0.82rem; }
.error-alert { display:flex;align-items:center;gap:0.5rem;background:#fee2e2;border:1px solid #fecaca;border-radius:8px;padding:0.6rem 0.9rem;font-size:0.82rem;color:#991b1b; }

/* ── Fund balance floating button ────────────────────────── */
.fund-bal-floating-btn {
    position: fixed;
    top: 50%;
    right: 0;
    transform: translateY(-50%);
    background: #c4dad2;
    border: 1px solid rgba(108,117,125,0.3);
    border-right: none;
    border-radius: 8px 0 0 8px;
    color: #16423c;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    padding: 30px 4px;
    transition: all 0.3s ease;
    z-index: 1000;
    box-shadow: -2px 0 10px rgba(0,0,0,0.1);
}
.fund-bal-floating-btn:hover { background: #3d8d7a; color: #fff; }
.fund-bal-floating-btn span { writing-mode:vertical-rl;text-orientation:mixed;transform:rotate(180deg);letter-spacing:1px;white-space:nowrap; }

/* ── Fund balance drawer ─────────────────────────────────── */
.fund-bal-drawer {
    position: fixed;
    top: 0; right: 0;
    height: 100vh;
    width: 300px;
    background: #fff;
    border-left: 1px solid #e2e8f0;
    box-shadow: -4px 0 24px rgba(0,0,0,0.10);
    z-index: 1050;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.fbd-header { display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;background:linear-gradient(to right,#cfe0d9 0%,#edf6f2 100%);border-bottom:1px solid #c4d9d2;flex-shrink:0; }
.fbd-title { display:flex;align-items:center;gap:0.5rem;font-size:0.9rem;font-weight:700;color:#16322e; }
.fbd-title i { color:#3d8d7a;font-size:1.1rem; }
.fbd-close { width:28px;height:28px;border:1px solid #c4d9d2;border-radius:8px;background:#fff;color:#16322e;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1rem;transition:background 0.15s; }
.fbd-close:hover { background:#f0f7f4; }
.fbd-body { flex:1;overflow-y:auto;padding:1rem;display:flex;flex-direction:column;background:#f4faf8; }
.fbd-slide-enter-active,.fbd-slide-leave-active { transition:transform 0.25s ease; }
.fbd-slide-enter-from,.fbd-slide-leave-to { transform:translateX(100%); }

/* ── Fund all-in-one card ─────────────────────────────────── */
.fund-card {
    background: #fff;
    border: 1.5px solid #d4e8df;
    border-radius: 14px;
    padding: 1rem 1.1rem 0.9rem;
    display: flex;
    flex-direction: column;
    gap: 0;
    margin-bottom: 0.75rem;
    box-shadow: 0 1px 4px rgba(30,90,70,0.06);
    transition: box-shadow 0.2s, border-color 0.2s;
}
.fund-card:hover { box-shadow: 0 4px 14px rgba(30,90,70,0.10); border-color: #a8d5c5; }

/* Header row */
.fund-card-header { display:flex;align-items:center;gap:0.6rem;margin-bottom:0.7rem; }
.fund-card-icon {
    width: 34px; height: 34px;
    border-radius: 10px;
    background: linear-gradient(135deg,#d4f1e7,#b8e8d6);
    color: #1e6b52;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; flex-shrink: 0;
}
.fund-card-name { flex:1;font-size:0.82rem;font-weight:700;color:#1a4d3d;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.fund-card-dot { width:9px;height:9px;border-radius:50%;flex-shrink:0; }
.dot-ok    { background:#22c55e;box-shadow:0 0 0 2px #dcfce7; }
.dot-empty { background:#d1d5db;box-shadow:0 0 0 2px #f3f4f6; }

/* Balance block */
.fund-card-balance { display:flex;flex-direction:column;gap:0.05rem;margin-bottom:0.1rem; }
.fund-card-balance-value { font-size:1.45rem;font-weight:800;color:#1a4d3d;line-height:1.1; }
.fund-card-balance-label { font-size:0.67rem;font-weight:600;text-transform:uppercase;letter-spacing:0.07em;color:#6b8c85; }

/* Divider */
.fund-card-divider { height:1px;background:#e4f0ea;margin:0.8rem 0 0.65rem; }

/* Budget section */
.fund-card-budget-header { display:flex;justify-content:space-between;align-items:center;margin-bottom:0.4rem; }
.fund-card-budget-title  { font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#4d8c7a;display:flex;align-items:center;gap:0.3rem; }
.fund-card-budget-total  { font-size:0.8rem;font-weight:700;color:#1a4d3d; }

.fund-card-progress-track { height:6px;border-radius:999px;background:#d1e8df;overflow:hidden;margin-bottom:0.6rem; }
.fund-card-progress-fill  { height:100%;border-radius:999px;transition:width 0.3s,background 0.3s; }

/* Spent / Remaining row */
.fund-card-metrics { display:flex;align-items:stretch;gap:0; }
.fund-card-metric  { flex:1;display:flex;flex-direction:column;gap:0.1rem; }
.fund-card-metric:last-child { align-items:flex-end; }
.fund-card-metric-divider { width:1px;background:#e4f0ea;margin:0 0.75rem; }
.fund-card-metric-label { font-size:0.67rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;color:#6b8c85; }
.fund-card-metric-value { font-size:0.92rem;font-weight:700; }
.metric-spent { color:#6b8c85; }
.metric-ok    { color:#15803d; }
.metric-low   { color:#b45309; }
.metric-over  { color:#dc2626; }
</style>
