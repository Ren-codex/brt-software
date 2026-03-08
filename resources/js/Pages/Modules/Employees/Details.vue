<template>
    <div class="employee-details-page">
        <div class="details-topbar">
            <div class="details-title-block">
                <h1>Employee Profile</h1>
                <p>View and manage employee records, account access, and payroll-related metrics.</p>
            </div>
            <div class="details-actions">
                <button class="details-btn details-btn-outline" @click="backToList">
                    <i class="ri-arrow-left-line"></i>
                    <span>Back to List</span>
                </button>
                <button @click="openEdit(employee)" variant="info" v-b-tooltip.hover title="Edit"
                    class="details-btn details-btn-primary">
                    <i class="ri-pencil-fill"></i>
                    <span>Edit Employee</span>
                </button>
            </div>
        </div>

        <div class="details-grid">
            <aside class="details-sidebar">
                <div class="profile-card">
                    <div class="profile-avatar-wrap">
                        <div class="profile-avatar">
                            <img v-if="employee.avatar" :src="'/storage/' + employee.avatar" alt="Profile"
                                class="profile-avatar-image">
                            <div v-else class="profile-avatar-placeholder">
                                <i class="ri-user-line"></i>
                            </div>
                        </div>
                    
                    </div>

                    <div class="profile-heading">
                        <h2>{{ employee.fullname || 'Employee Name' }}</h2>
                        <div class="profile-badges">
                            <span class="profile-badge profile-badge-primary">{{ employee.position ?
                                employee.position.title : '-' }}</span>
                            <span class="profile-badge"
                                :class="employee.is_regular ? 'profile-badge-primary' : 'profile-badge-neutral'">
                                {{ employee.is_regular === 1 ? 'Regular' : 'Contractual' }}
                            </span>
                            <span class="profile-badge"
                                :class="employee.is_active === 1 ? 'profile-badge-success' : 'profile-badge-danger'">
                                {{ employee.is_active === 1 ? 'Active' : 'Inactive' }}
                            </span>
                            <span v-if="employee.is_blacklisted === 1" class="profile-badge profile-badge-dark">
                                Blacklisted
                            </span>
                        </div>
                    </div>

                    <div class="profile-info-list">
                        <div class="profile-info-item">
                            <div class="profile-label">Email</div>
                            <div class="profile-value">
                                <i class="ri-mail-line"></i>
                                {{ employee.email || 'No email' }}
                            </div>
                        </div>
                        <div class="profile-info-item">
                            <div class="profile-label">Gender</div>
                            <div class="profile-value">
                                <i class="ri-genderless-line"></i>
                                {{ employee.sex || '-' }}
                            </div>
                        </div>
                        <div class="profile-info-item">
                            <div class="profile-label">Phone</div>
                            <div class="profile-value">
                                <i class="ri-phone-line"></i>
                                {{ employee.mobile || 'No phone number' }}
                            </div>
                        </div>
                        <div class="profile-info-item">
                            <div class="profile-label">Address</div>
                            <div class="profile-value">
                                <i class="ri-map-pin-line"></i>
                                {{ employee.address || 'No address' }}
                            </div>
                        </div>
                        <div class="profile-info-item">
                            <div class="profile-label">Religion</div>
                            <div class="profile-value">{{ employee.religion || '-' }}</div>
                        </div>
                        <div class="profile-info-item">
                            <div class="profile-label">Birthdate</div>
                            <div class="profile-value">{{ employee.birthdate || '-' }}</div>
                        </div>
                    </div>

                    <div class="profile-subcard">
                        <div class="profile-subcard-header">
                            <i class="ri-briefcase-line"></i>
                            <h3>Employment Details</h3>
                        </div>
                        <div class="profile-subcard-body">
                            <div class="profile-info-item">
                                <div class="profile-label">Position</div>
                                <div class="profile-value profile-highlight">{{ employee.position ?
                                    employee.position.title :
                                    '-' }}</div>
                            </div>
                            <div class="profile-info-item">
                                <div class="profile-label">Status</div>
                                <div class="status-chip" :class="getStatusClass()">
                                    {{ getStatusText() }}
                                </div>
                            </div>
                            <div class="profile-info-item">
                                <div class="profile-label">Created At</div>
                                <div class="profile-value">{{ formatDate(employee.created_at) }}</div>
                            </div>
                            <div class="profile-info-item">
                                <div class="profile-label">Employee ID</div>
                                <div class="profile-value">{{ employee.id || 'N/A' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            <section class="details-main">
                <div v-if="employee.user" class="details-card">
                    <div class="details-card-header details-card-header-between">
                        <h3>
                            <i class="ri-account-circle-line"></i>
                            Account Details
                        </h3>
                        <div class="details-header-actions">
                            <button class="details-btn details-btn-outline details-btn-sm"
                                @click="openAccountModal(employee)">
                                <i class="ri-user-settings-line"></i>
                                <span>Edit Account</span>
                            </button>
                            <button class="details-btn details-btn-outline details-btn-sm"
                                @click="openResetPasswordModal">
                                <i class="ri-lock-password-line"></i>
                                <span>Reset Password</span>
                            </button>
                        </div>
                    </div>
                    <div class="details-stat-grid">
                        <div class="details-stat">
                            <div class="details-stat-label">Username</div>
                            <div class="details-stat-value">
                                <i class="ri-user-line"></i>
                                {{ employee.user.username || '-' }}
                            </div>
                        </div>
                        <div class="details-stat">
                            <div class="details-stat-label">Roles</div>
                            <div class="details-stat-value">
                                <i class="ri-shield-line"></i>
                                <div class="role-badges">
                                    <span v-for="(role, index) in getAccountRoleBadges(employee.user)"
                                        :key="`${role.name}-${index}`" class="role-badge"
                                        :class="roleBadgeClass(index, role.name)">
                                        {{ role.name }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="details-stat">
                            <div class="details-stat-label">Account Status</div>
                            <div class="details-stat-value">
                                <span class="status-chip"
                                    :class="employee.user.is_active ? 'emp-status-success' : 'emp-status-danger'">
                                    <i
                                        :class="employee.user.is_active ? 'ri-check-circle-line' : 'ri-close-circle-line'"></i>
                                    {{ employee.user.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="details-card">
                    <div class="details-card-header details-card-header-between">
                        <h3>Incentives Earnings</h3>
                        <div class="details-filters">
                            <select v-model="selectedMonth" class="details-select">
                                <option v-for="month in months" :key="month.value" :value="month.value">
                                    {{ month.label }}
                                </option>
                            </select>
                            <select v-model="selectedYear" class="details-select">
                                <option v-for="year in years" :key="year" :value="year">
                                    {{ year }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="details-stat-grid">
                        <div class="details-stat">
                            <div class="details-stat-label">Total Amount Earned This Month</div>
                            <div class="details-stat-value">PHP 1,250</div>
                            <div class="details-stat-trend details-stat-trend-up">+5%</div>
                        </div>
                        <div class="details-stat">
                            <div class="details-stat-label">Total Rice Sold</div>
                            <div class="details-stat-value">1250 kg</div>
                            <div class="details-stat-trend details-stat-trend-up">+15%</div>
                        </div>
                        <div class="details-stat">
                            <div class="details-stat-label">Total Points Earned</div>
                            <div class="details-stat-value">50</div>
                            <div class="details-stat-trend details-stat-trend-up">+10%</div>
                        </div>
                    </div>
                </div>

                <div v-if="hasOpenLoan" class="details-card">
                    <div class="details-card-header details-card-header-between">
                        <h3>Employee Loans</h3>
                        <button @click="toggleLoanCollapse" class="details-btn details-btn-outline details-btn-sm">
                            <i :class="loanCollapsed ? 'ri-arrow-down-s-line' : 'ri-arrow-up-s-line'"></i>
                            <span>{{ loanCollapsed ? 'Expand' : 'Collapse' }}</span>
                        </button>
                    </div>

                    <div>
                        <div class="loan-overview">
                            <div class="loan-balance">
                                <div class="loan-balance-title">Total Balance</div>
                                <div class="loan-balance-value">{{ formatCurrency(remainingBalanceValue) }}</div>
                            </div>

                            <div class="loan-progress">
                                <div class="loan-progress-head">
                                    <span>Payment Progress</span>
                                    <span>{{ loanProgressPercent }}% Complete</span>
                                </div>
                                <div class="loan-progress-bar">
                                    <div class="loan-progress-fill" :style="{ width: `${loanProgressPercent}%` }"></div>
                                </div>
                                <div class="loan-progress-meta">
                                    <span>Paid: <strong>{{ formatCurrency(paidAmountValue) }}</strong></span>
                                    <span>Remaining: <strong>{{ formatCurrency(remainingBalanceValue) }}</strong></span>
                                </div>
                            </div>
                        </div>

                        <div v-show="!loanCollapsed" class="loan-details-grid">
                            <div class="loan-detail-card">
                                <div class="loan-detail-label">
                                    <i class="ri-calendar-todo-line"></i>
                                    Payment Terms
                                </div>
                                <div class="loan-detail-value">{{ loanTermMonths > 0 ? `${loanTermMonths} Months` : '-'
                                    }}</div>
                                <div class="loan-detail-sub">{{ unpaidMonths }} Remaining</div>
                            </div>
                            <div class="loan-detail-card">
                                <div class="loan-detail-label">
                                    <i class="ri-time-line"></i>
                                    Unpaid Months
                                </div>
                                <div class="loan-detail-value details-danger">{{ unpaidMonths }}</div>
                                <div class="loan-detail-sub">{{ formatCurrency(remainingBalanceValue) }} Due</div>
                            </div>
                            <div class="loan-detail-card">
                                <div class="loan-detail-label">
                                    <i class="ri-checkbox-circle-line"></i>
                                    Months Paid
                                </div>
                                <div class="loan-detail-value details-success">{{ paidMonths }}</div>
                                <div class="loan-detail-sub">{{ formatCurrency(paidAmountValue) }} Paid</div>
                            </div>
                            <div v-if="employee.user" class="loan-detail-card">
                                <div class="loan-detail-label">
                                    <i class="ri-calendar-event-line"></i>
                                    Next Due
                                </div>
                                <div class="loan-detail-value">{{ formatDate(nextDueDate) }}</div>
                                <div class="loan-detail-sub">{{ nextDueDate ? 'Upcoming' : '-' }}</div>
                            </div>
                        </div>

                        <div class="loan-footer">
                            <div v-if="employee.user" class="loan-footer-item">
                                <span>Last Payment</span>
                                <strong>{{ formatDate(latestPaymentDate) }}</strong>
                            </div>
                            <div class="loan-footer-item">
                                <span>Monthly Payment</span>
                                <strong>{{ formatCurrency(monthlyPaymentValue) }}</strong>
                            </div>
                            <div v-if="employee.user" class="loan-footer-item">
                                <span>Start Date</span>
                                <strong>{{ formatDate(loanStartDate) }}</strong>
                            </div>
                            <button class="details-btn details-btn-primary details-btn-sm"
                                @click="openLoanDetailsModal">
                                <i class="ri-eye-line"></i>
                                <span>View Details</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="completedLoanHistory.length" class="details-card">
                    <div class="loan-history-section loan-history-section-standalone">
                        <div class="loan-history-header">
                            <h4>
                                <i class="ri-history-line"></i>
                                Completed Loan History
                            </h4>
                            <span class="loan-history-count">{{ completedLoanHistory.length }} loan(s)</span>
                        </div>

                        <div class="loan-history-summary-grid">
                            <div v-for="loan in completedLoanHistory" :key="`loan-summary-${loan.id}`"
                                class="loan-history-summary-card">
                                <div class="loan-history-summary-top">
                                    <div>
                                        <div class="loan-history-loan-no">{{ loan.loan_no || `Loan #${loan.id}` }}</div>
                                        <div class="loan-history-loan-type">{{ loan.loan_type || '-' }}</div>
                                    </div>
                                    <span class="loan-status-chip" :class="loanStatusClass(loan.status)">
                                        {{ loan.status || 'unknown' }}
                                    </span>
                                </div>

                                <div class="loan-history-summary-body">
                                    <div>
                                        <span>Amount</span>
                                        <strong>{{ formatCurrency(loan.total_amount) }}</strong>
                                    </div>
                                    <div>
                                        <span>Paid</span>
                                        <strong>{{ formatCurrency(loan.paid_amount) }}</strong>
                                    </div>
                                    <div>
                                        <span>Remaining</span>
                                        <strong>{{ formatCurrency(loan.remaining_amount) }}</strong>
                                    </div>
                                    <div>
                                        <span>Last Payment</span>
                                        <strong>{{ formatDate(loan.latest_payment_date) }}</strong>
                                    </div>
                                </div>

                                <div class="loan-history-summary-footer">
                                    <span>{{ loan.payment_count }} payment(s)</span>
                                    <button class="details-btn details-btn-outline details-btn-sm"
                                        @click="openLoanDetailsModal(loan)">
                                        <i class="ri-eye-line"></i>
                                        <span>View</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                   
                    </div>
                </div>
            </section>
        </div>
    </div>

    <Create @add="$emit('update')" @update="$emit('update')" :dropdowns="dropdowns" ref="create" />
    <AccountEdit @updated="$emit('update')" :dropdowns="dropdowns" ref="accountEditModal" />
    <LoanDetails ref="loanDetailsModal" />
    <ResetPassword @updated="$emit('update')" ref="resetPasswordModal" />
</template>

<script>
import Create from './Modals/Create.vue';
import AccountEdit from './Modals/AccountEdit.vue';
import LoanDetails from './Modals/LoanDetails.vue';
import ResetPassword from './Modals/ResetPassword.vue';
export default {
    components: { Create, AccountEdit, LoanDetails, ResetPassword },
    props: ['employee', 'backToList', 'dropdowns'],
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
        employeeLoans() {
            if (!Array.isArray(this.employee?.loans)) {
                return [];
            }

            return [...this.employee.loans]
                .filter((loan) => loan && loan.status !== 'disapproved')
                .sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0));
        },
        hasEmployeeLoan() {
            return this.employeeLoans.length > 0;
        },
        currentLoan() {
            return this.employeeLoans.find((loan) => {
                const status = String(loan?.status || '').toLowerCase();
                if (status === 'completed' || status === 'disapproved' || status === 'rejected') {
                    return false;
                }

                const remainingBalance = this.toNumber(loan?.remaining_balance, null);
                if (remainingBalance !== null) {
                    return remainingBalance > 0;
                }

                return true;
            }) || null;
        },
        hasOpenLoan() {
            return !!this.currentLoan;
        },
        employeeLoanHistory() {
            return this.employeeLoans.map((loan) => {
                const payments = Array.isArray(loan?.payments) ? loan.payments : [];
                const latestPayment = payments
                    .filter((payment) => this.paymentDateValue(payment))
                    .sort((a, b) => new Date(this.paymentDateValue(b)).getTime() - new Date(this.paymentDateValue(a)).getTime())[0] || null;
                const totalAmount = this.toNumber(loan?.amount, 0);
                const paidAmount = this.toNumber(loan?.amtpaid, 0);
                const remainingAmount = this.toNumber(loan?.remaining_balance, Math.max(totalAmount - paidAmount, 0));

                return {
                    ...loan,
                    total_amount: totalAmount,
                    paid_amount: paidAmount,
                    remaining_amount: remainingAmount,
                    payment_count: payments.length,
                    latest_payment_date: this.paymentDateValue(latestPayment),
                };
            });
        },
        completedLoanHistory() {
            return this.employeeLoanHistory.filter((loan) => {
                const status = String(loan?.status || '').toLowerCase();
                if (status === 'completed') {
                    return true;
                }

                return this.toNumber(loan?.remaining_amount, 0) <= 0;
            });
        },
        employeePaymentHistory() {
            const rows = [];

            this.employeeLoans.forEach((loan) => {
                const payments = Array.isArray(loan?.payments) ? loan.payments : [];
                payments.forEach((payment, index) => {
                    rows.push({
                        loan_id: loan.id,
                        payment_id: payment?.id || `${loan.id}-payment-${index}`,
                        loan_no: loan.loan_no,
                        loan_type: loan.loan_type,
                        paid_date: payment?.paid_date,
                        amount: this.toNumber(payment?.amount, 0),
                        payment_date: this.paymentDateValue(payment) || this.parseSinglePaymentDate(payment?.created_at),
                    });
                });
            });

            return rows.sort((a, b) => {
                const aDate = a.payment_date ? new Date(a.payment_date).getTime() : 0;
                const bDate = b.payment_date ? new Date(b.payment_date).getTime() : 0;
                return bDate - aDate;
            });
        },
        totalAmountValue() {
            return this.toNumber(this.currentLoan?.amount, 0);
        },
        paidAmountValue() {
            return this.toNumber(this.currentLoan?.amtpaid, 0);
        },
        remainingBalanceValue() {
            const remaining = this.toNumber(this.currentLoan?.remaining_balance, null);
            if (remaining !== null) {
                return remaining;
            }

            return Math.max(this.totalAmountValue - this.paidAmountValue, 0);
        },
        loanProgressPercent() {
            if (this.totalAmountValue <= 0) {
                return 0;
            }

            const progress = (this.paidAmountValue / this.totalAmountValue) * 100;
            return Math.min(100, Math.max(0, Math.round(progress)));
        },
        loanTermMonths() {
            return this.toNumber(this.currentLoan?.term_months, 0);
        },
        remainingTermMonths() {
            const remaining = this.toNumber(this.currentLoan?.remaining_term_to_pay, null);
            if (remaining !== null) {
                return remaining;
            }

            return Math.max(this.loanTermMonths - this.paymentHistory.length, 0);
        },
        paidMonths() {
            if (this.termUnitFactor <= 0) {
                return 0;
            }

            return Math.max(this.paidTermUnits / this.termUnitFactor, 0);
        },
        unpaidMonths() {
            return Math.max(this.remainingTermMonths / 2, 0);
        },
        monthlyPaymentValue() {
            if (this.unpaidMonths <= 0) {
                return this.remainingBalanceValue;
            }

            return this.remainingBalanceValue / this.unpaidMonths;
        },
        paymentHistory() {
            return Array.isArray(this.currentLoan?.payments) ? this.currentLoan.payments : [];
        },
        paidPaymentHistory() {
            return this.paymentHistory.filter((payment) => !!payment?.paid_date);
        },
        termUnitFactor() {
            const remainingUnits = this.toNumber(this.currentLoan?.remaining_term_to_pay, 0);
            if (remainingUnits > this.loanTermMonths) {
                return 2; // semi-monthly units
            }
            return 1; // monthly units
        },
        totalTermUnits() {
            return this.loanTermMonths * this.termUnitFactor;
        },
        paidTermUnits() {
            const remainingUnits = this.toNumber(this.currentLoan?.remaining_term_to_pay, null);
            if (remainingUnits !== null) {
                return Math.max(this.totalTermUnits - remainingUnits, 0);
            }

            const fromPayments = this.paymentHistory.reduce((sum, payment) => {
                return sum + this.toNumber(payment?.paid_term, 1);
            }, 0);
            return Math.max(fromPayments, 0);
        },
        unpaidPaymentSchedules() {
            return this.paymentHistory
                .filter((payment) => !payment?.paid_date && (payment?.payment_date || payment?.due_date))
                .sort((a, b) => {
                    const aDate = new Date(a.payment_date || a.due_date).getTime();
                    const bDate = new Date(b.payment_date || b.due_date).getTime();
                    return aDate - bDate;
                });
        },
        latestPayment() {
            const payments = this.paidPaymentHistory.filter((payment) => this.paymentDateValue(payment));
            if (!payments.length) {
                return null;
            }

            return [...payments].sort(
                (a, b) => new Date(this.paymentDateValue(b)).getTime() - new Date(this.paymentDateValue(a)).getTime()
            )[0];
        },
        latestPaymentDate() {
            return this.paymentDateValue(this.latestPayment);
        },
        nextDueDate() {
            if (this.unpaidPaymentSchedules.length) {
                return this.unpaidPaymentSchedules[0].payment_date || this.unpaidPaymentSchedules[0].due_date;
            }

            const startDate = this.loanStartDate ? new Date(this.loanStartDate) : null;
            if (!startDate || Number.isNaN(startDate.getTime())) {
                return null;
            }

            // Unpaid-only estimate when schedules are not stored in DB.
            // `remaining_term_to_pay` is tracked in term-units; for semi-monthly loans use 15-day steps.
            if (this.termUnitFactor === 2) {
                const due = new Date(startDate);
                due.setDate(due.getDate() + (this.paidTermUnits * 15));
                return due;
            }

            const due = new Date(startDate);
            due.setMonth(due.getMonth() + this.paidTermUnits);
            return due;
        },
        loanStartDate() {
            return this.currentLoan?.approved_at || this.currentLoan?.created_at || null;
        }
    },
    methods: {
        toNumber(value, fallback = 0) {
            if (value === null || value === undefined || value === '') {
                return fallback;
            }

            const numeric = Number(value);
            return Number.isFinite(numeric) ? numeric : fallback;
        },
        formatCurrency(amount) {
            if (amount === null || amount === undefined || amount === '') {
                return '-';
            }

            const value = Number(amount);
            if (!Number.isFinite(value)) {
                return '-';
            }

            return new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP'
            }).format(value);
        },
        formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            if (Number.isNaN(date.getTime())) return dateString;

            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        },
        extractPaymentDateCandidates(value) {
            if (!value) return [];

            const text = String(value).trim();
            if (!text) return [];

            const monthToken = '(?:Jan(?:uary)?\\.?|Feb(?:ruary)?\\.?|Mar(?:ch)?\\.?|Apr(?:il)?\\.?|May|Jun(?:e)?\\.?|Jul(?:y)?\\.?|Aug(?:ust)?\\.?|Sep(?:t(?:ember)?)?\\.?|Oct(?:ober)?\\.?|Nov(?:ember)?\\.?|Dec(?:ember)?\\.?)';
            const sameMonthRangeRegex = new RegExp(`\\b(${monthToken})\\s+(\\d{1,2})\\s*-\\s*(\\d{1,2}),\\s*(\\d{4})\\b`, 'gi');
            const crossMonthRangeRegex = new RegExp(`\\b(${monthToken})\\s+(\\d{1,2})\\s*-\\s*(${monthToken})\\s+(\\d{1,2}),\\s*(\\d{4})\\b`, 'gi');

            const rangeEndDates = [];
            for (const match of text.matchAll(crossMonthRangeRegex)) {
                const endMonth = match[3];
                const endDay = match[4];
                const year = match[5];
                rangeEndDates.push(`${endMonth} ${endDay}, ${year}`);
            }
            for (const match of text.matchAll(sameMonthRangeRegex)) {
                const month = match[1];
                const endDay = match[3];
                const year = match[4];
                rangeEndDates.push(`${month} ${endDay}, ${year}`);
            }

            const isoMatches = text.match(/\b\d{4}-\d{2}-\d{2}\b/g) || [];
            const slashMatches = text.match(/\b\d{1,2}\/\d{1,2}\/\d{2,4}\b/g) || [];
            const dashMatches = text.match(/\b\d{1,2}-\d{1,2}-\d{2,4}\b/g) || [];
            const monthNameMatches = text.match(
                /\b(?:Jan(?:uary)?\.?|Feb(?:ruary)?\.?|Mar(?:ch)?\.?|Apr(?:il)?\.?|May|Jun(?:e)?\.?|Jul(?:y)?\.?|Aug(?:ust)?\.?|Sep(?:t(?:ember)?)?\.?|Oct(?:ober)?\.?|Nov(?:ember)?\.?|Dec(?:ember)?\.?)\s+\d{1,2}(?:,\s*|\s+)\d{4}\b/gi
            ) || [];

            const candidates = [...new Set([...rangeEndDates, ...isoMatches, ...slashMatches, ...dashMatches, ...monthNameMatches])];
            return candidates.length ? candidates : [text];
        },
        parseSinglePaymentDate(value) {
            if (!value) return null;

            const candidates = this.extractPaymentDateCandidates(value);
            const parsedDates = candidates
                .map((candidate) => new Date(candidate))
                .filter((date) => !Number.isNaN(date.getTime()))
                .sort((a, b) => b.getTime() - a.getTime());

            return parsedDates.length ? parsedDates[0] : null;
        },
        paymentDateValue(payment) {
            if (!payment) return null;

            return (
                this.parseSinglePaymentDate(payment.paid_date) ||
                this.parseSinglePaymentDate(payment.payment_date) ||
                this.parseSinglePaymentDate(payment.created_at) ||
                null
            );
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
        openAccountModal(data) {
            this.$refs.accountEditModal.open(data);
        },
        openResetPasswordModal() {
            if (!this.employee?.user) return;
            this.$refs.resetPasswordModal.open(this.employee);
        },
        openLoanDetailsModal(loan = null) {
            const selectedLoan = loan || this.currentLoan;
            if (!selectedLoan) return;
            this.$refs.loanDetailsModal.open(selectedLoan);
        },
        loanStatusClass(status) {
            const normalized = String(status || '').toLowerCase();
            if (normalized === 'completed' || normalized === 'active') {
                return 'loan-status-success';
            }
            if (normalized === 'pending') {
                return 'loan-status-warning';
            }
            if (normalized === 'overdue' || normalized === 'rejected' || normalized === 'disapproved') {
                return 'loan-status-danger';
            }
            return 'loan-status-neutral';
        },
        getAccountRoleBadges(user) {
            if (!user) return [];

            if (Array.isArray(user.roles) && user.roles.length) {
                return user.roles
                    .map((role) => ({
                        name: role?.title || role?.name || '',
                    }))
                    .filter((role) => role.name);
            }

            if (user.role) {
                const name = user.role.title || user.role.name || '';
                return name ? [{ name }] : [];
            }

            if (Array.isArray(user.myroles) && user.myroles.length) {
                return user.myroles
                    .filter((item) => item?.is_active === 1 || item?.is_active === true)
                    .map((item) => ({
                        name: item?.role?.title || item?.role?.name || '',
                    }))
                    .filter((role) => role.name);
            }

            return [];
        },
        roleBadgeClass(index, roleName) {
            const palette = [
                'role-badge-cyan',
                'role-badge-emerald',
                'role-badge-orange',
                'role-badge-violet',
                'role-badge-rose',
                'role-badge-sky',
            ];
            const seed = `${roleName}-${index}`
                .split('')
                .reduce((sum, ch) => sum + ch.charCodeAt(0), 0);
            return palette[seed % palette.length];
        },
        getAccountRoles(user) {
            if (!user) return '-';

            if (Array.isArray(user.roles) && user.roles.length) {
                const names = user.roles
                    .map((role) => role?.title || role?.name || '')
                    .filter(Boolean);
                if (names.length) return names.join(', ');
            }

            if (user.role) {
                return user.role.title || user.role.name || '-';
            }

            if (Array.isArray(user.myroles) && user.myroles.length) {
                const activeRoles = user.myroles
                    .filter((item) => item?.is_active === 1 || item?.is_active === true)
                    .map((item) => item?.role?.title || item?.role?.name)
                    .filter(Boolean);
                if (activeRoles.length) return activeRoles.join(', ');
            }

            return '-';
        },
    }
}
</script>

<style scoped>
.employee-details-page {
    zoom: 1.1;
    --ink-900: #102723;
    --ink-700: #35524d;
    --ink-500: #5c7974;
    --line-200: #d2e4df;
    --mint-700: #1a7e67;
    --mint-500: #2fa485;
    --surface: #ffffff;
    --surface-soft: #f7fcfa;
    --danger-600: #c44f47;
    padding: 20px;
    max-width: 1360px;
    margin: 0 auto;
}

.details-topbar {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: 16px;
}

.details-title-block h1 {
    margin: 0;
    font-size: 1.5rem;
    color: var(--ink-900);
}

.details-title-block p {
    margin: 6px 0 0;
    color: var(--ink-500);
    font-size: 0.88rem;
}

.details-actions {
    display: flex;
    gap: 12px;
    align-items: center;
}

.details-header-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.details-btn {
    min-height: 40px;
    padding: 0 16px;
    border-radius: 10px;
    border: 1px solid transparent;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 0.86rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
}

.details-btn-primary {
    color: #fff;
    background: linear-gradient(125deg, var(--mint-500) 0%, var(--mint-700) 100%);
    box-shadow: 0 10px 20px rgba(28, 120, 99, 0.28);
}

.details-btn-primary:hover {
    transform: translateY(-1px);
}

.details-btn-outline {
    color: var(--ink-700);
    background: #fff;
    border-color: #ceded9;
}

.details-btn-outline:hover {
    background: #f8fdfb;
}

.details-btn-sm {
    min-height: 34px;
    padding: 0 10px;
    font-size: 0.78rem;
}

.details-grid {
    display: grid;
    grid-template-columns: minmax(310px, 390px) minmax(0, 1fr);
    gap: 18px;
    align-items: start;
}

.details-sidebar {
    position: sticky;
    top: 12px;
}

.profile-card {
    border: 1px solid var(--line-200);
    border-radius: 22px;
    padding: 20px;
    background: linear-gradient(160deg, #f9fffd 0%, #eff9f6 100%);
    box-shadow: 0 12px 32px rgba(28, 64, 56, 0.08);
}

.profile-avatar-wrap {
    display: flex;
    justify-content: center;
    margin-bottom: 12px;
    position: relative;
}

.profile-avatar {
    width: 136px;
    height: 136px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid #fff;
    box-shadow: 0 10px 28px rgba(14, 47, 41, 0.2);
}

.profile-avatar-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-avatar-placeholder {
    width: 100%;
    height: 100%;
    display: grid;
    place-items: center;
    color: white;
    font-size: 42px;
    background: linear-gradient(145deg, #2d947c 0%, #1d6454 100%);
}

.profile-avatar-btn {
    position: absolute;
    right: calc(50% - 70px);
    bottom: -6px;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    border: 0;
    color: #fff;
    background: #1f826b;
    display: grid;
    place-items: center;
}

.profile-heading {
    text-align: center;
    margin-bottom: 14px;
}

.profile-heading h2 {
    margin: 0;
    font-size: 1.22rem;
    color: var(--ink-900);
}

.profile-badges {
    margin-top: 10px;
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 6px;
}

.profile-badge {
    padding: 4px 9px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
}

.profile-badge-primary {
    background: #d9f0e8;
    color: #1b6f5a;
}

.profile-badge-neutral {
    background: #edf0f4;
    color: #4f6072;
}

.profile-badge-success,
.emp-status-success {
    background: #dcf6eb;
    color: #157856;
}

.profile-badge-danger,
.emp-status-danger {
    background: #ffe4e0;
    color: #b04740;
}

.profile-badge-dark {
    background: #2e3a39;
    color: #fff;
}

.profile-info-list {
    border-top: 1px solid #d8e9e4;
    padding-top: 12px;
}

.profile-info-item {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    padding: 7px 0;
}

.profile-label {
    color: var(--ink-500);
    font-size: 0.8rem;
    font-weight: 600;
}

.profile-value {
    color: var(--ink-900);
    font-size: 0.82rem;
    font-weight: 600;
    text-align: right;
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    gap: 6px;
}

.profile-highlight {
    color: #176954;
}

.profile-subcard {
    margin-top: 12px;
    border-top: 1px solid #d8e9e4;
    padding-top: 12px;
}

.profile-subcard-header {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-bottom: 6px;
    color: #1a6e5a;
}

.profile-subcard-header h3 {
    margin: 0;
    font-size: 0.9rem;
}

.profile-subcard-body .profile-info-item:last-child {
    padding-bottom: 0;
}

.details-main {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.details-card {
    border: 1px solid #dbe9e5;
    border-radius: 20px;
    padding: 18px;
    background: var(--surface);
    box-shadow: 0 8px 28px rgba(22, 58, 50, 0.06);
}

.details-card-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
    color: var(--ink-900);
}

.details-card-header h3 {
    margin: 0;
    font-size: 1.04rem;
}

.details-card-header-between {
    justify-content: space-between;
}

.details-filters {
    display: flex;
    gap: 8px;
}

.details-select {
    min-height: 36px;
    padding: 0 10px;
    border: 1px solid #ccded8;
    border-radius: 9px;
    background: #f8fdfb;
    color: var(--ink-700);
    font-size: 0.8rem;
}

.details-stat-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.details-stat {
    border: 1px solid #e2eeeb;
    border-radius: 15px;
    padding: 13px;
    background: var(--surface-soft);
}

.details-stat-label {
    color: var(--ink-500);
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.details-stat-value {
    margin-top: 8px;
    font-size: 1.18rem;
    font-weight: 700;
    color: var(--ink-900);
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.role-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.role-badge {
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 700;
    border: 1px solid transparent;
    white-space: nowrap;
    line-height: 1.5;
}

.role-badge-cyan {
    background: #e6f9ff;
    color: #06627f;
    border-color: #bdefff;
}

.role-badge-emerald {
    background: #e8f9ef;
    color: #116a44;
    border-color: #c6efd7;
}

.role-badge-orange {
    background: #fff4e7;
    color: #945200;
    border-color: #ffe1bf;
}

.role-badge-violet {
    background: #f3ecff;
    color: #5a2b9b;
    border-color: #dfd0ff;
}

.role-badge-rose {
    background: #ffedf3;
    color: #9c2f55;
    border-color: #ffd3e0;
}

.role-badge-sky {
    background: #ebf4ff;
    color: #1f4d93;
    border-color: #cfe2ff;
}

.details-stat-trend {
    margin-top: 5px;
    font-size: 0.78rem;
    font-weight: 700;
}

.details-stat-trend-up,
.details-success {
    color: #1b8c64;
}

.details-danger {
    color: var(--danger-600);
}

.status-chip,
.emp-status-warning {
    padding: 5px 10px;
    border-radius: 999px;
    font-size: 0.74rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.emp-status-warning {
    color: #9a6b19;
    background: #fff1d8;
}

.loan-overview {
    display: grid;
    grid-template-columns: 250px minmax(0, 1fr);
    gap: 14px;
}

.loan-balance {
    border: 1px solid #d7e7e2;
    border-radius: 14px;
    padding: 12px;
    background: linear-gradient(135deg, #ecfaf5 0%, #e3f5ef 100%);
}

.loan-balance-title {
    font-size: 0.78rem;
    color: var(--ink-500);
    font-weight: 700;
}

.loan-balance-value {
    margin-top: 8px;
    font-size: 1.6rem;
    color: #1c755d;
    font-weight: 800;
}

.loan-progress {
    border: 1px solid #e0ece8;
    border-radius: 14px;
    padding: 12px;
}

.loan-progress-head {
    display: flex;
    justify-content: space-between;
    font-size: 0.8rem;
    color: var(--ink-700);
    font-weight: 700;
}

.loan-progress-bar {
    margin-top: 9px;
    height: 10px;
    border-radius: 10px;
    background: #e3eeeb;
    overflow: hidden;
}

.loan-progress-fill {
    height: 100%;
    background: linear-gradient(120deg, #30a789 0%, #1a7c64 100%);
}

.loan-progress-meta {
    margin-top: 9px;
    display: flex;
    justify-content: space-between;
    gap: 8px;
    font-size: 0.8rem;
    color: var(--ink-700);
}

.loan-details-grid {
    margin-top: 12px;
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
}

.loan-detail-card {
    border: 1px solid #e1ece8;
    border-radius: 13px;
    padding: 10px;
    background: #fbfffd;
}

.loan-detail-label {
    font-size: 0.76rem;
    color: var(--ink-500);
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.loan-detail-value {
    margin-top: 6px;
    font-size: 1.14rem;
    color: var(--ink-900);
    font-weight: 800;
}

.loan-detail-sub {
    margin-top: 2px;
    font-size: 0.76rem;
    color: var(--ink-500);
}

.loan-footer {
    margin-top: 12px;
    border-top: 1px dashed #d5e4df;
    padding-top: 10px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
}

.loan-footer-item {
    min-width: 140px;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.loan-footer-item span {
    font-size: 0.72rem;
    color: var(--ink-500);
}

.loan-footer-item strong {
    font-size: 0.83rem;
    color: var(--ink-900);
}

.loan-history-section {
    margin-top: 14px;
    border-top: 1px dashed #d5e4df;
    padding-top: 12px;
}

.loan-history-section-standalone {
    margin-top: 0;
    border-top: 0;
    padding-top: 0;
}

.loan-history-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
}

.loan-history-header h4 {
    margin: 0;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--ink-900);
    font-size: 0.95rem;
}

.loan-history-count {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--ink-700);
    background: #edf7f4;
    border: 1px solid #d6e8e2;
    border-radius: 999px;
    padding: 4px 9px;
}

.loan-history-summary-grid {
    margin-top: 10px;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.loan-history-summary-card {
    border: 1px solid #deebe7;
    border-radius: 14px;
    background: #fbfffd;
    padding: 10px;
}

.loan-history-summary-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 8px;
}

.loan-history-loan-no {
    font-size: 0.84rem;
    font-weight: 800;
    color: var(--ink-900);
}

.loan-history-loan-type {
    margin-top: 2px;
    font-size: 0.75rem;
    color: var(--ink-500);
}

.loan-status-chip {
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 3px 8px;
    text-transform: capitalize;
}

.loan-status-success {
    background: #dcf6eb;
    color: #157856;
}

.loan-status-warning {
    background: #fff1d8;
    color: #9a6b19;
}

.loan-status-danger {
    background: #ffe4e0;
    color: #b04740;
}

.loan-status-neutral {
    background: #edf0f4;
    color: #4f6072;
}

.loan-history-summary-body {
    margin-top: 9px;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

.loan-history-summary-body span {
    display: block;
    font-size: 0.7rem;
    color: var(--ink-500);
    text-transform: uppercase;
    font-weight: 700;
}

.loan-history-summary-body strong {
    margin-top: 2px;
    display: block;
    font-size: 0.8rem;
    color: var(--ink-900);
}

.loan-history-summary-footer {
    margin-top: 9px;
    border-top: 1px dashed #d9e8e3;
    padding-top: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    font-size: 0.74rem;
    color: var(--ink-500);
    font-weight: 700;
}

.loan-history-table-wrap {
    margin-top: 10px;
    border: 1px solid #deebe7;
    border-radius: 12px;
    overflow: auto;
}

.loan-history-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 700px;
}

.loan-history-table th,
.loan-history-table td {
    padding: 9px 10px;
    font-size: 0.78rem;
    border-bottom: 1px solid #e6f0ed;
}

.loan-history-table th {
    background: #f6fcfa;
    color: var(--ink-700);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.loan-history-table tbody tr:hover {
    background: #f9fdfb;
}

.loan-history-loan-cell {
    display: flex;
    flex-direction: column;
    gap: 1px;
}

.loan-history-loan-cell strong {
    color: var(--ink-900);
}

.loan-history-loan-cell span {
    color: var(--ink-500);
}

.loan-history-empty {
    margin-top: 10px;
    border: 1px dashed #d2e4df;
    border-radius: 12px;
    padding: 14px;
    color: var(--ink-500);
    font-size: 0.82rem;
    text-align: center;
    background: #f8fdfb;
}

@media (max-width: 1140px) {
    .details-grid {
        grid-template-columns: 1fr;
    }

    .details-sidebar {
        position: static;
    }

    .details-stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .loan-overview {
        grid-template-columns: 1fr;
    }

    .loan-details-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .loan-history-summary-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 700px) {
    .employee-details-page {
        padding: 12px;
    }

    .details-topbar {
        flex-direction: column;
    }

    .details-actions {
        width: 100%;
    }

    .details-btn {
        width: 100%;
        justify-content: center;
    }

    .details-card-header-between {
        flex-direction: column;
        align-items: flex-start;
        gap: 9px;
    }

    .details-filters {
        width: 100%;
    }

    .details-select {
        width: 100%;
    }

    .details-stat-grid {
        grid-template-columns: 1fr;
    }

    .loan-details-grid {
        grid-template-columns: 1fr;
    }

    .loan-progress-meta {
        flex-direction: column;
    }

}
</style>
