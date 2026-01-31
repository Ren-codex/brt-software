<template>
    <div class="emp-profile-container">
        <div>
            <div class="d-flex gap-2 justify-content-end">
                <button class="emp-create-btn" @click="backToList">
                    <i class="ri-arrow-left-line"></i>
                    Back to List
                </button>
                <button @click="openEdit(selectedEmployee, selectedRow)" variant="info" v-b-tooltip.hover title="Edit"
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
                                    <img v-if="employee.avatar" :src="'/storage/' + employee.avatar" alt="Profile"
                                        class="emp-avatar-image">
                                    <div v-else class="emp-avatar-placeholder">
                                        <i class="ri-user-line"></i>
                                    </div>
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
                                <div class="emp-stat-label">Total Amount Earned This Month</div>
                                <div class="emp-stat-value">₱1,250</div>
                                <div class="emp-stat-change emp-positive">+5%</div>
                            </div>
                            <div class="emp-incentives-stat">
                                <div class="emp-stat-label">Total Rice Sold</div>
                                <div class="emp-stat-value">1250 kg</div>
                                <div class="emp-stat-change emp-positive">+15%</div>
                            </div>
                            <div class="emp-incentives-stat">
                                <div class="emp-stat-label">Total Points Earned</div>
                                <div class="emp-stat-value">50</div>
                                <div class="emp-stat-change emp-positive">+10%</div>
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
                                <div class="emp-loan-period">As of January 2024</div>
                            </div>
                            <button @click="toggleLoanCollapse" class="emp-create-btn">
                                <i :class="loanCollapsed ? 'ri-arrow-down-s-line' : 'ri-arrow-up-s-line'"></i>
                            </button>
                        </div>

                        <div class="emp-loan-main-stats">
                            <div class="emp-primary-stat">
                                <div class="emp-stat-number">₱5,200</div>
                                <div class="emp-stat-label">Total Balance</div>
                            </div>

                            <div class="emp-progress-section">
                                <div class="emp-progress-header">
                                    <span>Payment Progress</span>
                                    <span class="emp-progress-percentage">62% Complete</span>
                                </div>
                                <div class="emp-progress-bar">
                                    <div class="emp-progress-fill" style="width: 62%"></div>
                                </div>
                                <div class="emp-progress-details">
                                    <span class="emp-progress-detail">Paid: <strong>₱3,200</strong></span>
                                    <span class="emp-progress-detail">Remaining: <strong>₱2,000</strong></span>
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
                                    <div class="emp-detail-main-value">12 Months</div>
                                    <div class="emp-detail-sub-value">4 Remaining</div>
                                </div>
                            </div>

                            <div class="emp-detail-card">
                                <div class="emp-detail-header">
                                    <i class="ri-time-line"></i>
                                    <span class="emp-detail-title">Unpaid Months</span>
                                </div>
                                <div class="emp-detail-content">
                                    <div class="emp-detail-main-value emp-text-danger">4</div>
                                    <div class="emp-detail-sub-value">₱1,600 Due</div>
                                </div>
                            </div>

                            <div class="emp-detail-card">
                                <div class="emp-detail-header">
                                    <i class="ri-checkbox-circle-line"></i>
                                    <span class="emp-detail-title">Paid Months</span>
                                </div>
                                <div class="emp-detail-content">
                                    <div class="emp-detail-main-value emp-text-success">8</div>
                                    <div class="emp-detail-sub-value">₱3,200 Paid</div>
                                </div>
                            </div>

                            <div class="emp-detail-card">
                                <div class="emp-detail-header">
                                    <i class="ri-calendar-event-line"></i>
                                    <span class="emp-detail-title">Next Due</span>
                                </div>
                                <div class="emp-detail-content">
                                    <div class="emp-detail-main-value">Feb 15</div>
                                    <div class="emp-detail-sub-value">Next Month</div>
                                </div>
                            </div>
                        </div>

                        <div class="emp-loan-footer">
                            <div class="emp-footer-details">
                                <div class="emp-footer-detail">
                                    <span class="emp-footer-label">Last Payment</span>
                                    <span class="emp-footer-value">Dec 15, 2023</span>
                                </div>
                                <div class="emp-footer-detail">
                                    <span class="emp-footer-label">Monthly Payment</span>
                                    <span class="emp-footer-value">₱400</span>
                                </div>
                                <div class="emp-footer-detail">
                                    <span class="emp-footer-label">Start Date</span>
                                    <span class="emp-footer-value">Apr 15, 2023</span>
                                </div>
                            </div>

                            <button class="emp-btn-view-details">
                                <i class="ri-eye-line"></i> View Details
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    props: ['employee', 'backToList', 'openEdit', 'selectedEmployee', 'selectedRow'],
    name: 'EmployeeDetails',
    data() {
        return {
            loanCollapsed: true,
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
            ]
        };
    },
    methods: {
        formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
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
        }
    }
}
</script>

<style scoped></style>
