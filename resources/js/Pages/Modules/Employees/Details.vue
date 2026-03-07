<template>
    <div class="emp-profile-container">
        <div>
            <div class="d-flex gap-2 justify-content-end">
                <button class="emp-create-btn" @click="backToList">
                    <i class="ri-arrow-left-line"></i>
                    Back to List
                </button>
                <button @click="openEdit(employee)" variant="info" v-b-tooltip.hover title="Edit"
                    class="emp-create-btn">
                    <i class="ri-pencil-fill"></i>
                    Edit
                </button>
            </div>
        </div>
        <!-- Main Profile Header -->


        <div class="row mt-4">
            <!-- Left Column - Basic Information -->
            <div class="col-md-4">
                <!-- Contact Information Card -->
                <div class="emp-info-card">
                    <div class="emp-profile-header">
                        <div class="emp-profile-avatar-section">
                            <div class="emp-avatar-container">
                                <div class="emp-avatar-preview">
                                    <img
                                        :src="getAvatarSrc(employee)"
                                        @error="handleAvatarError"
                                        alt="Profile"
                                        class="emp-avatar-image"
                                    >
                                </div>
                                <button class="emp-btn-change-photo">
                                    <i class="ri-camera-line"></i>
                                </button>
                            </div>


                        </div>

                    </div>
                    <div class="emp-profile-title">
                        <h1>{{ employee.fullname || 'Employee Name' }}</h1>
                        <div class="emp-profile-badges">
                            <span class="emp-badge emp-badge-primary">
                                {{ employee.position ? employee.position.title : '-' }}
                            </span>
                            <span class="emp-badge"
                                :class="employee.is_regular ? 'emp-badge-primary' : 'emp-badge-secondary'">
                                {{ employee.is_regular === 1 ? 'Regular' : 'Contractual' }}
                            </span>
                            <span class="emp-badge"
                                :class="employee.is_active === 1 ? 'emp-badge-success' : 'emp-badge-danger'">
                                {{ employee.is_active === 1 ? 'Active' : 'Inactive' }}
                            </span>
                            <span v-if="employee.is_blacklisted === 1" class="emp-badge emp-badge-dark">
                                Blacklisted
                            </span>
                        </div>
                    </div>

                    <div class="emp-info-card-body">
                        <div class="emp-info-row">
                            <div class="emp-info-label">Email</div>
                            <div class="emp-info-value">
                                <i class="ri-mail-line"></i>
                                {{ employee.email || 'No email' }}
                            </div>
                        </div>
                        <div class="emp-info-row">
                            <div class="emp-info-label">Gender</div>
                            <div class="emp-info-value">
                                <i class="ri-genderless-line"></i>
                                {{ employee.sex || '-' }}
                            </div>
                        </div>
                        <div class="emp-info-row">
                            <div class="emp-info-label">Phone</div>
                            <div class="emp-info-value">
                                <i class="ri-phone-line"></i>
                                {{ employee.mobile || 'No phone number' }}
                            </div>
                        </div>
                        <div class="emp-info-row">
                            <div class="emp-info-label">Address</div>
                            <div class="emp-info-value">
                                <i class="ri-map-pin-line"></i>
                                {{ employee.address || 'No address' }}
                            </div>
                        </div>
                        <div class="emp-info-row">
                            <div class="emp-info-label">Religion</div>
                            <div class="emp-info-value">{{ employee.religion || '-' }}</div>
                        </div>
                        <div class="emp-info-row">
                            <div class="emp-info-label">Birthdate</div>
                            <div class="emp-info-value">{{ employee.birthdate || '-' }}</div>
                        </div>
                    </div>
                    <hr>
                    <div class="emp-info-card-header">
                        <i class="ri-briefcase-line"></i>
                        <h3>Employment Details</h3>
                    </div>
                    <div class="emp-info-card-body">
                        <div class="emp-info-row">
                            <div class="emp-info-label">Position</div>
                            <div class="emp-info-value emp-highlight">{{ employee.position ? employee.position.title :
                                '-' }}
                            </div>
                        </div>
                        <div class="emp-info-row">
                            <div class="emp-info-label">Status</div>
                            <div class="emp-status-indicator" :class="getStatusClass()">
                                {{ getStatusText() }}
                            </div>
                        </div>
                        <div class="emp-info-row">
                            <div class="emp-info-label">Created At</div>
                            <div class="emp-info-value">{{ formatDate(employee.created_at) }}</div>
                        </div>
                        <div class="emp-info-row">
                            <div class="emp-info-label">Employee ID</div>
                            <div class="emp-info-value">{{ employee.id || 'N/A' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Statistics & Records -->
            <div class="col-md-8">
                <!-- Account Details Section - Only show if employee has user account -->
                <div v-if="employee.user" class="emp-incentives-section">
                    <div class="emp-incentives-card">
                        <div class="emp-incentives-header">
                            <h3>
                                <i class="ri-account-circle-line"></i>
                                Account Details
                            </h3>
                        </div>
                        <div class="emp-incentives-stats">
                            <div class="emp-incentives-stat">
                                <div class="emp-stat-label">Username</div>
                                <div class="emp-stat-value" style="font-size: 20px;">
                                    <i class="ri-user-line" style="font-size: 16px; color: #6c757d;"></i>
                                    {{ employee.user.username || '-' }}
                                </div>
                            </div>
                            <div class="emp-incentives-stat">
                                <div class="emp-stat-label">Role</div>
                                <div class="emp-stat-value" style="font-size: 20px;">
                                    <i class="ri-shield-line" style="font-size: 16px; color: #6c757d;"></i>
                                    {{ employee.user.role ? employee.user.role.title : '-' }}
                                </div>
                                
                            </div>
                            <div class="emp-incentives-stat">
                                <div class="emp-stat-label">Account Status</div>
                                <div class="emp-stat-value">
                                    <span class="emp-status-indicator" :class="employee.user.is_active ? 'emp-status-success' : 'emp-status-danger'">
                                        <i :class="employee.user.is_active ? 'ri-check-circle-line' : 'ri-close-circle-line'"></i>
                                        {{ employee.user.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Incentives Earning Per Payroll -->
                <div class="emp-incentives-section">
                    <div class="emp-incentives-card">
                        <div class="emp-incentives-header">
                            <h3>Incentives Earnings</h3>
                            <!-- <div class="emp-incentives-period">This Month</div> -->
                            <div class="d-flex gap-2 justify-content-end">
                                <select v-model="selectedMonth" class="emp-create-btn emp-month-filter">
                                    <option v-for="month in months" :key="month.value" :value="month.value">
                                        {{ month.label }}
                                    </option>
                                </select><select v-model="selectedYear" class="emp-create-btn emp-month-filter">
                                    <option v-for="year in years" :key="year" :value="year">
                                        {{ year }}
                                    </option>
                                </select>
                            </div>

                        </div>
                        <div class="emp-incentives-stats">
                            <div class="emp-incentives-stat">
                                <div class="emp-stat-label">Total Points Earned</div>
                                <div class="emp-stat-value">{{ formatIncentiveNumber((incentiveSummary.total_rice_sold_kg * incentiveSummary.total_points_earned)/25, 2)  }}</div>
                                <div class="emp-stat-change" :class="getChangeClass(incentiveSummary.amount_change_percent)">
                                    {{ formatChangePercent(incentiveSummary.amount_change_percent) }}
                                </div>
                            </div>
                            <div class="emp-incentives-stat">
                                <div class="emp-stat-label">Total Rice Sold</div>
                                <div class="emp-stat-value">{{ formatIncentiveNumber(incentiveSummary.total_rice_sold_kg, 2) }} kg</div>
                                <div class="emp-stat-change" :class="getChangeClass(incentiveSummary.rice_change_percent)">
                                    {{ formatChangePercent(incentiveSummary.rice_change_percent) }}
                                </div>
                            </div>
                            <div class="emp-incentives-stat">
                                <div class="emp-stat-label">Total Rice Quantity</div>
                                <div class="emp-stat-value">{{ formatIncentiveNumber(incentiveSummary.total_points_earned) }}</div>
                                <div class="emp-stat-change" :class="getChangeClass(incentiveSummary.points_change_percent)">
                                    {{ formatChangePercent(incentiveSummary.points_change_percent) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Employee Loans -->
                <div class="emp-stats-section mt-2 ">
                    <div class="emp-section-header">
                        <h2 class="emp-section-title">Employee Loans</h2>
                    </div>

                    <div class="emp-loan-summary-card">
                        <div class="emp-loan-main-header">
                            <div class="emp-loan-icon">
                                <i class="ri-bank-card-line"></i>
                            </div>
                            <div class="emp-loan-title-section">
                                <h3 class="emp-loan-title">Total Loan Summary</h3>
                                <div class="emp-loan-period">As of {{ loanSummary.asOfLabel }}</div>
                            </div>
                            <button @click="toggleLoanCollapse" class="emp-create-btn">
                                <i :class="loanCollapsed ? 'ri-arrow-down-s-line' : 'ri-arrow-up-s-line'"></i>
                            </button>
                        </div>

                        <div class="emp-loan-main-stats">
                            <div class="emp-primary-stat">
                                <div class="emp-stat-number">{{ formatCurrency(loanSummary.totalRemaining) }}</div>
                                <div class="emp-stat-label">Total Balance</div>
                            </div>

                            <div class="emp-progress-section">
                                <div class="emp-progress-header">
                                    <span>Payment Progress</span>
                                    <span class="emp-progress-percentage">{{ loanSummary.progressPercent }}% Complete</span>
                                </div>
                                <div class="emp-progress-bar">
                                    <div class="emp-progress-fill" :style="{ width: `${loanSummary.progressPercent}%` }"></div>
                                </div>
                                <div class="emp-progress-details">
                                    <span class="emp-progress-detail">Paid: <strong>{{ formatCurrency(loanSummary.totalPaid) }}</strong></span>
                                    <span class="emp-progress-detail">Remaining: <strong>{{ formatCurrency(loanSummary.totalRemaining) }}</strong></span>
                                </div>
                            </div>
                        </div>

                        <div v-show="!loanCollapsed" class="emp-loan-details-grid">
                            <div class="emp-detail-card">
                                <div class="emp-detail-header">
                                    <i class="ri-calendar-todo-line"></i>
                                    <span class="emp-detail-title">Payment Terms</span>
                                </div>
                                <div class="emp-detail-content">
                                    <div class="emp-detail-main-value">{{ loanSummary.totalTerms }} Terms</div>
                                    <div class="emp-detail-sub-value">{{ loanSummary.remainingTerms/2 }} Remaining</div>
                                </div>
                            </div>

                            <div class="emp-detail-card">
                                <div class="emp-detail-header">
                                    <i class="ri-time-line"></i>
                                    <span class="emp-detail-title">Unpaid Months</span>
                                </div>
                                <div class="emp-detail-content">
                                    <div class="emp-detail-main-value emp-text-danger">{{ loanSummary.remainingTerms/2 }}</div>
                                    <div class="emp-detail-sub-value">{{ formatCurrency(loanSummary.totalRemaining) }} Due</div>
                                </div>
                            </div>

                            <div class="emp-detail-card">
                                <div class="emp-detail-header">
                                    <i class="ri-checkbox-circle-line"></i>
                                    <span class="emp-detail-title">Paid Months</span>
                                </div>
                                <div class="emp-detail-content">
                                    <div class="emp-detail-main-value emp-text-success">{{ loanSummary.paidTerms }}</div>
                                    <div class="emp-detail-sub-value">{{ formatCurrency(loanSummary.totalPaid) }} Paid</div>
                                </div>
                            </div>

                            <div class="emp-detail-card">
                                <div class="emp-detail-header">
                                    <i class="ri-calendar-event-line"></i>
                                    <span class="emp-detail-title">Next Due</span>
                                </div>
                                <div class="emp-detail-content">
                                    <div class="emp-detail-main-value">{{ loanSummary.nextDueDate }}</div>
                                    <div class="emp-detail-sub-value">{{ loanSummary.nextDueSubLabel }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="emp-loan-footer">
                            <div class="emp-footer-details">
                                <div class="emp-footer-detail">
                                    <span class="emp-footer-label">Last Payment</span>
                                    <span class="emp-footer-value">{{ loanSummary.lastPaymentDate }}</span>
                                </div>
                                <div class="emp-footer-detail">
                                    <span class="emp-footer-label">Monthly Payment</span>
                                    <span class="emp-footer-value">{{ formatCurrency(loanSummary.monthlyPayment) }}</span>
                                </div>
                                <div class="emp-footer-detail">
                                    <span class="emp-footer-label">Start Date</span>
                                    <span class="emp-footer-value">{{ loanSummary.startDate }}</span>
                                </div>
                            </div>

                            <button class="emp-btn-view-details" @click="openLoanHistoryModal">
                                <i class="ri-eye-line"></i> View Details
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div v-if="showLoanHistoryModal" class="emp-loan-modal-overlay" @click.self="closeLoanHistoryModal">
        <div class="emp-loan-modal">
            <div class="emp-loan-modal-header">
                <div>
                    <h4 class="emp-loan-modal-title mb-0">Loan History</h4>
                    <small class="text-muted">{{ employee.fullname || 'Employee' }}</small>
                </div>
                <button class="emp-loan-modal-close" @click="closeLoanHistoryModal">
                    <i class="ri-close-line"></i>
                </button>
            </div>

            <div class="emp-loan-modal-body">
                <div v-if="loanHistoryRows.length" class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Loan No.</th>
                                <th>Type</th>
                                <th class="text-end">Amount</th>
                                <th class="text-end">Paid</th>
                                <th class="text-end">Remaining</th>
                                <th class="text-center">Term</th>
                                <th class="text-center">Remaining Term</th>
                                <th>Status</th>
                                <th>Start Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(loan, index) in loanHistoryRows" :key="loan.id || loan.loan_no || index">
                                <td>{{ index + 1 }}</td>
                                <td>{{ loan.loan_no || '-' }}</td>
                                <td>{{ loan.loan_type || '-' }}</td>
                                <td class="text-end">{{ formatCurrency(loan.amount) }}</td>
                                <td class="text-end">{{ formatCurrency(loan.amtpaid) }}</td>
                                <td class="text-end">{{ formatCurrency(getLoanRemaining(loan)) }}</td>
                                <td class="text-center">{{ loan.term_months || '-' }}</td>
                                <td class="text-center">{{ loan.remaining_term_to_pay/2 || '-' }}</td>
                                <td>
                                    <span class="badge text-uppercase" :class="getLoanStatusBadgeClass(loan.status)">
                                        {{ loan.status || '-' }}
                                    </span>
                                </td>
                                <td>{{ formatDate(loan.approved_at || loan.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="text-center text-muted py-4">
                    No loan history found for this employee.
                </div>
            </div>
        </div>
    </div>
      <Create @add="fetch()" @update="fetch()"  :dropdowns="dropdowns" ref="create" />
</template>

<script>

import Create from './Modals/Create.vue';
export default {
    components: {Create },
    props: ['employee', 'backToList',  ],
    name: 'EmployeeDetails',
    data() {
        return {
            loanCollapsed: false,
            showLoanHistoryModal: false,
            loans: [],
            selectedYear: new Date().getFullYear(), // Current year
            selectedMonth: new Date().getMonth() + 1, // Current month (1-12)
            years: Array.from({ length: 10 }, (_, i) => new Date().getFullYear() - i), // Last 10 years
            months: [
                { value: 1, label: 'January' },
                { value: 2, label: 'February' },
                { value: 3, label: 'March' },
                { value: 4, label: 'April' },
                { value: 5, label: 'May' },
                { value: 6, label: 'June' },
                { value: 7, label: 'July' },
                { value: 8, label: 'August' },
                { value: 9, label: 'September' },
                { value: 10, label: 'October' },
                { value: 11, label: 'November' },
                { value: 12, label: 'December' }
            ],
            incentiveSummary: {
                total_amount: 0,
                total_rice_sold_kg: 0,
                total_points_earned: 0,
                amount_change_percent: 0,
                rice_change_percent: 0,
                points_change_percent: 0
            },
            defaultAvatar: '/images/default-avatar.png'
        };
    },
    computed: {
        trackedLoans() {
            if (!Array.isArray(this.loans)) {
                return [];
            }

            return this.loans.filter((loan) => {
                const status = String(loan?.status || '').toLowerCase();
                return ['approved', 'active', 'completed', 'overdue'].includes(status);
            });
        },
        loanSummary() {
            const defaults = {
                asOfLabel: this.formatMonthYear(new Date()),
                totalRemaining: 0,
                totalPaid: 0,
                progressPercent: 0,
                totalTerms: 0,
                remainingTerms: 0,
                paidTerms: 0,
                nextDueDate: 'N/A',
                nextDueSubLabel: 'No schedule',
                lastPaymentDate: 'N/A',
                monthlyPayment: 0,
                startDate: 'N/A'
            };

            if (!this.trackedLoans.length) {
                return defaults;
            }

            const totalAmount = this.trackedLoans.reduce((sum, loan) => sum + this.toNumber(loan?.amount), 0);
            const totalPaid = this.trackedLoans.reduce((sum, loan) => sum + this.toNumber(loan?.amtpaid), 0);
            const totalRemaining = this.trackedLoans.reduce((sum, loan) => {
                const remainingBalance = this.toNumber(loan?.remaining_balance, null);
                if (remainingBalance !== null) {
                    return sum + Math.max(remainingBalance, 0);
                }

                return sum + Math.max(this.toNumber(loan?.amount) - this.toNumber(loan?.amtpaid), 0);
            }, 0);

            const totalTerms = this.trackedLoans.reduce((sum, loan) => sum + this.toNumber(loan?.term_months), 0);
            const remainingTerms = this.trackedLoans.reduce((sum, loan) => sum + this.toNumber(loan?.remaining_term_to_pay), 0);
            const paidTerms = Math.max(totalTerms - remainingTerms, 0);
            const progressPercent = totalAmount > 0 ? Math.min(100, Math.max(0, Math.round((totalPaid / totalAmount) * 100))) : 0;
            const monthlyPayment = remainingTerms > 0 ? totalRemaining / remainingTerms : 0;

            const sortedByStart = [...this.trackedLoans].sort((a, b) => this.toDateSafe(a?.approved_at || a?.created_at) - this.toDateSafe(b?.approved_at || b?.created_at));
            const sortedByLatest = [...this.trackedLoans].sort((a, b) => this.toDateSafe(b?.updated_at || b?.created_at) - this.toDateSafe(a?.updated_at || a?.created_at));
            const startLoan = sortedByStart[0];
            const latestLoan = sortedByLatest[0];

            const latestLoanDate = latestLoan?.updated_at || latestLoan?.created_at;
            const lastPaymentDateValue = this.getLastPaymentDate(this.trackedLoans);
            const nextDueDateValue = this.getNextDueDate(lastPaymentDateValue);

            return {
                asOfLabel: this.formatMonthYear(latestLoanDate),
                totalRemaining,
                totalPaid,
                progressPercent,
                totalTerms,
                remainingTerms,
                paidTerms,
                nextDueDate: nextDueDateValue ? this.formatDate(nextDueDateValue) : 'N/A',
                nextDueSubLabel: nextDueDateValue ? 'Estimated' : 'No schedule',
                lastPaymentDate: lastPaymentDateValue ? this.formatDate(lastPaymentDateValue) : 'N/A',
                monthlyPayment,
                startDate: startLoan ? this.formatDate(startLoan?.approved_at || startLoan?.created_at) : 'N/A'
            };
        },
        loanHistoryRows() {
            return [...this.trackedLoans].sort((a, b) => {
                const dateA = this.toDateSafe(a?.approved_at || a?.created_at);
                const dateB = this.toDateSafe(b?.approved_at || b?.created_at);
                return dateB - dateA;
            });
        }
    },
    watch: {
        'employee.id': {
            immediate: true,
            handler() {
                this.fetchEmployeeLoans();
                this.fetchIncentiveSummary();
            }
        },
        selectedYear() {
            this.fetchIncentiveSummary();
        },
        selectedMonth() {
            this.fetchIncentiveSummary();
        }
    },
    methods: {
        fetch() {
            this.fetchEmployeeLoans();
            this.fetchIncentiveSummary();
            this.$emit('update');
        },
        fetchEmployeeLoans() {
            if (!this.employee?.id) {
                this.loans = [];
                return;
            }

            axios.get('/loans', {
                params: {
                    option: 'lists',
                    count: 100,
                    employee_id: this.employee.id
                }
            })
            .then((response) => {
                this.loans = response?.data?.data || [];
            })
            .catch((err) => {
                console.log(err);
                this.loans = [];
            });
        },
        fetchIncentiveSummary() {
            if (!this.employee?.id || !this.selectedYear || !this.selectedMonth) {
                this.incentiveSummary = {
                    total_amount: 0,
                    total_rice_sold_kg: 0,
                    total_points_earned: 0,
                    amount_change_percent: 0,
                    rice_change_percent: 0,
                    points_change_percent: 0
                };
                return;
            }

            axios.get(`/employees/${this.employee.id}/incentives-summary`, {
                params: {
                    year: this.selectedYear,
                    month: this.selectedMonth
                }
            })
            .then((response) => {
                const totals = response?.data?.totals || {};
                const changes = response?.data?.changes || {};

                this.incentiveSummary = {
                    total_amount: this.toNumber(totals.total_amount),
                    total_rice_sold_kg: this.toNumber(totals.total_rice_sold_kg),
                    total_points_earned: this.toNumber(totals.total_points_earned),
                    amount_change_percent: this.toNumber(changes.amount_change_percent),
                    rice_change_percent: this.toNumber(changes.rice_change_percent),
                    points_change_percent: this.toNumber(changes.points_change_percent)
                };
            })
            .catch((err) => {
                console.log(err);
                this.incentiveSummary = {
                    total_amount: 0,
                    total_rice_sold_kg: 0,
                    total_points_earned: 0,
                    amount_change_percent: 0,
                    rice_change_percent: 0,
                    points_change_percent: 0
                };
            });
        },
        toNumber(value, fallback = 0) {
            const parsed = Number(value);
            if (Number.isFinite(parsed)) {
                return parsed;
            }
            return fallback;
        },
        formatIncentiveAmount(value) {
            return new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(this.toNumber(value));
        },
        formatIncentiveNumber(value, maxFractionDigits = 0) {
            return this.toNumber(value).toLocaleString('en-US', {
                maximumFractionDigits: maxFractionDigits,
                minimumFractionDigits: 0
            });
        },
        formatChangePercent(value) {
            const numeric = this.toNumber(value);
            const sign = numeric > 0 ? '+' : '';
            return `${sign}${this.formatIncentiveNumber(numeric, 2)}%`;
        },
        getChangeClass(value) {
            const numeric = this.toNumber(value);
            if (numeric > 0) return 'emp-positive';
            if (numeric < 0) return 'emp-negative';
            return '';
        },
        toDateSafe(value) {
            const date = new Date(value);
            return Number.isNaN(date.getTime()) ? new Date(0) : date;
        },
        formatCurrency(value) {
            return `PHP ${this.toNumber(value).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            })}`;
        },
        formatMonthYear(value) {
            const date = this.toDateSafe(value);
            return date.toLocaleDateString('en-US', {
                month: 'long',
                year: 'numeric'
            });
        },
        getLastPaymentDate(loans) {
            let latestPaymentDate = null;

            loans.forEach((loan) => {
                const payments = Array.isArray(loan?.payments) ? loan.payments : [];
                payments.forEach((payment) => {
                    const candidate = payment?.paid_date || payment?.created_at;
                    const date = this.toDateSafe(candidate);
                    if (!latestPaymentDate || date > latestPaymentDate) {
                        latestPaymentDate = date;
                    }
                });
            });

            return latestPaymentDate;
        },
        getNextDueDate(lastPaymentDate) {
            if (!lastPaymentDate) {
                return null;
            }

            const nextDate = new Date(lastPaymentDate);
            nextDate.setMonth(nextDate.getMonth() + 1);
            return nextDate;
        },
        getAvatarSrc(employee) {
            if (!employee?.avatar) {
                return this.defaultAvatar;
            }
            return `/storage/${employee.avatar}`;
        },
        handleAvatarError(event) {
            if (event?.target) {
                event.target.onerror = null;
                event.target.src = this.defaultAvatar;
            }
        },
        formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = this.toDateSafe(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        },
        getStatusClass() {
            if (this.employee.is_blacklisted === 1) return 'emp-status-danger';
            if (this.employee.is_active === 1) return 'emp-status-success';
            return 'emp-status-warning';
        },
        getStatusText() {
            if (this.employee.is_blacklisted === 1) return 'Blacklisted';
            if (this.employee.is_active === 1) return 'Active';
            return 'Inactive';
        },
        toggleLoanCollapse() {
            this.loanCollapsed = !this.loanCollapsed;
        },
        getLoanRemaining(loan) {
            const remainingBalance = this.toNumber(loan?.remaining_balance, null);
            if (remainingBalance !== null) {
                return Math.max(remainingBalance, 0);
            }
            return Math.max(this.toNumber(loan?.amount) - this.toNumber(loan?.amtpaid), 0);
        },
        getLoanStatusBadgeClass(status) {
            const normalized = String(status || '').toLowerCase();
            if (normalized === 'approved' || normalized === 'active' || normalized === 'completed') {
                return 'bg-success';
            }
            if (normalized === 'pending') {
                return 'bg-warning';
            }
            if (normalized === 'rejected' || normalized === 'overdue') {
                return 'bg-danger';
            }
            return 'bg-secondary';
        },
        openLoanHistoryModal() {
            this.showLoanHistoryModal = true;
        },
        closeLoanHistoryModal() {
            this.showLoanHistoryModal = false;
        },
        openEdit(data) {
            this.$refs.create.edit(data);
        },
    }
}
</script>

<style scoped>
.emp-loan-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2000;
    padding: 1rem;
}

.emp-loan-modal {
    width: min(1100px, 96vw);
    max-height: 90vh;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.emp-loan-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #eef1f4;
}

.emp-loan-modal-title {
    font-size: 1.1rem;
    font-weight: 700;
}

.emp-loan-modal-close {
    border: none;
    background: transparent;
    font-size: 1.5rem;
    color: #6c757d;
    line-height: 1;
}

.emp-loan-modal-body {
    padding: 1rem 1.25rem 1.25rem;
    overflow: auto;
}

.emp-stat-change.emp-negative {
    background: rgba(220, 53, 69, 0.15);
    color: #842029;
    border: 1px solid rgba(220, 53, 69, 0.3);
}

.emp-stat-change.emp-negative::before {
    content: '\2193';
    font-weight: 800;
}
</style>
