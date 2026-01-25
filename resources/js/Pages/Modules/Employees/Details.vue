<template>
    <div class="client-profile-container">
        <div>
            <div class="d-flex gap-2 justify-content-end">
                <button class="create-btn" @click="backToList">
                    <i class="ri-arrow-left-line"></i>
                    Back to List
                </button>
                <button @click="openEdit(selectedEmployee, selectedRow)" variant="info" v-b-tooltip.hover title="Edit"
                    class="create-btn">
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
                <div class="info-card">
                    <div class="profile-header">
                        <div class="profile-avatar-section">
                            <div class="avatar-container">
                                <div class="avatar-preview">
                                    <img v-if="employee.avatar" :src="'/storage/' + employee.avatar" alt="Profile"
                                        class="avatar-image">
                                    <div v-else class="avatar-placeholder">
                                        <i class="ri-user-line"></i>
                                    </div>
                                </div>
                                <button class="btn-change-photo">
                                    <i class="ri-camera-line"></i>
                                </button>
                            </div>


                        </div>

                    </div>
                    <div class="profile-title">
                        <h1>{{ employee.fullname || 'Employee Name' }}</h1>
                        <div class="profile-badges">
                            <span class="badge badge-primary">
                                {{ employee.position ? employee.position.title : '-' }}
                            </span>
                            <span class="badge" :class="employee.is_regular ? 'badge-primary' : 'badge-secondary'">
                                {{ employee.is_regular === 1 ? 'Regular' : 'Contractual' }}
                            </span>
                            <span class="badge" :class="employee.is_active === 1 ? 'badge-success' : 'badge-danger'">
                                {{ employee.is_active === 1 ? 'Active' : 'Inactive' }}
                            </span>
                            <span v-if="employee.is_blacklisted === 1" class="badge badge-dark">
                                Blacklisted
                            </span>
                        </div>
                    </div>
                    
                    <div class="info-card-body">
                        <div class="info-row">
                            <div class="info-label">Email</div>
                            <div class="info-value">
                                <i class="ri-mail-line"></i>
                                {{ employee.email || 'No email' }}
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Gender</div>
                            <div class="info-value">
                                <i class="ri-genderless-line"></i>
                                {{ employee.sex || '-' }}
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Phone</div>
                            <div class="info-value">
                                <i class="ri-phone-line"></i>
                                {{ employee.mobile || 'No phone number' }}
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Address</div>
                            <div class="info-value">
                                <i class="ri-map-pin-line"></i>
                                {{ employee.address || 'No address' }}
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Religion</div>
                            <div class="info-value">{{ employee.religion || '-' }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Birthdate</div>
                            <div class="info-value">{{ employee.birthdate || '-' }}</div>
                        </div>
                    </div>
                    <hr>
                    <div class="info-card-header">
                        <i class="ri-briefcase-line"></i>
                        <h3>Employment Details</h3>
                    </div>
                    <div class="info-card-body">
                        <div class="info-row">
                            <div class="info-label">Position</div>
                            <div class="info-value highlight">{{ employee.position ? employee.position.title : '-' }}
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Status</div>
                            <div class="status-indicator" :class="getStatusClass()">
                                {{ getStatusText() }}
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Created At</div>
                            <div class="info-value">{{ formatDate(employee.created_at) }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Employee ID</div>
                            <div class="info-value">{{ employee.id || 'N/A' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Statistics & Records -->
            <div class="col-md-8">
                <!-- Incentives Earning Per Payroll -->
                <div class="incentives-section">
                    <div class="incentives-card">
                        <div class="incentives-header">
                            <h3>Incentives Earnings</h3>
                            <!-- <div class="incentives-period">This Month</div> -->
                            <select v-model="selectedMonth" class="create-btn month-filter">
                                <option v-for="month in months" :key="month.value" :value="month.value">
                                    {{ month.label }}
                                </option>
                            </select>
                        </div>
                        <div class="incentives-stats">
                            <div class="incentives-stat">
                                <div class="stat-label">Incentives per Rice Sale</div>
                                <div class="stat-value">₱25</div>
                                <div class="stat-change positive">+5%</div>
                            </div>
                            <div class="incentives-stat">
                                <div class="stat-label">Amount of Incentives per Payroll</div>
                                <div class="stat-value">₱1,250</div>
                                <div class="stat-change positive">+15%</div>
                            </div>
                            <div class="incentives-stat">
                                <div class="stat-label">Total Rice Sales</div>
                                <div class="stat-value">50 sacks</div>
                                <div class="stat-change positive">+10%</div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Employee Loans -->
                <div class="stats-section mt-2">
                    <div class="section-header">
                        <h2 class="section-title">Employee Loans</h2>
                    </div>

                    <div class="loan-summary-card">
                        <div class="loan-main-header">
                            <div class="loan-icon">
                                <i class="ri-bank-card-line"></i>
                            </div>
                            <div class="loan-title-section">
                                <h3 class="loan-title">Total Loan Summary</h3>
                                <div class="loan-period">As of January 2024</div>
                            </div>
                            <button @click="toggleLoanCollapse" class="create-btn">
                                <i :class="loanCollapsed ? 'ri-arrow-down-s-line' : 'ri-arrow-up-s-line'"></i>
                            </button>
                        </div>

                        <div class="loan-main-stats">
                            <div class="primary-stat">
                                <div class="stat-number">₱5,200</div>
                                <div class="stat-label">Total Balance</div>
                            </div>

                            <div class="progress-section">
                                <div class="progress-header">
                                    <span>Payment Progress</span>
                                    <span class="progress-percentage">62% Complete</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 62%"></div>
                                </div>
                                <div class="progress-details">
                                    <span class="progress-detail">Paid: <strong>₱3,200</strong></span>
                                    <span class="progress-detail">Remaining: <strong>₱2,000</strong></span>
                                </div>
                            </div>
                        </div>

                        <div v-show="!loanCollapsed" class="loan-details-grid">
                            <div class="detail-card">
                                <div class="detail-header">
                                    <i class="ri-calendar-todo-line"></i>
                                    <span class="detail-title">Payment Terms</span>
                                </div>
                                <div class="detail-content">
                                    <div class="detail-main-value">12 Months</div>
                                    <div class="detail-sub-value">4 Remaining</div>
                                </div>
                            </div>

                            <div class="detail-card">
                                <div class="detail-header">
                                    <i class="ri-time-line"></i>
                                    <span class="detail-title">Unpaid Months</span>
                                </div>
                                <div class="detail-content">
                                    <div class="detail-main-value text-danger">4</div>
                                    <div class="detail-sub-value">₱1,600 Due</div>
                                </div>
                            </div>

                            <div class="detail-card">
                                <div class="detail-header">
                                    <i class="ri-checkbox-circle-line"></i>
                                    <span class="detail-title">Paid Months</span>
                                </div>
                                <div class="detail-content">
                                    <div class="detail-main-value text-success">8</div>
                                    <div class="detail-sub-value">₱3,200 Paid</div>
                                </div>
                            </div>

                            <div class="detail-card">
                                <div class="detail-header">
                                    <i class="ri-calendar-event-line"></i>
                                    <span class="detail-title">Next Due</span>
                                </div>
                                <div class="detail-content">
                                    <div class="detail-main-value">Feb 15</div>
                                    <div class="detail-sub-value">Next Month</div>
                                </div>
                            </div>
                        </div>

                        <div class="loan-footer">
                            <div class="footer-details">
                                <div class="footer-detail">
                                    <span class="footer-label">Last Payment</span>
                                    <span class="footer-value">Dec 15, 2023</span>
                                </div>
                                <div class="footer-detail">
                                    <span class="footer-label">Monthly Payment</span>
                                    <span class="footer-value">₱400</span>
                                </div>
                                <div class="footer-detail">
                                    <span class="footer-label">Start Date</span>
                                    <span class="footer-value">Apr 15, 2023</span>
                                </div>
                            </div>

                            <button class="btn-view-details">
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
            selectedMonth: new Date().getMonth() + 1, // Current month (1-12)
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
            if (this.employee.is_blacklisted === 1) return 'status-danger';
            if (this.employee.is_active === 1) return 'status-success';
            return 'status-warning';
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

<style scoped>
.create-btn {
    background: #3D8D7A;
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.8125rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    cursor: pointer;
    transition: all 0.3s ease;

    &:hover {
        background: rgba(255, 255, 255, 0.3);
        color: rgba(12, 78, 57, 0.5);
        border-color: rgba(12, 78, 57, 0.5);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    i {
        font-size: 0.95rem;
    }
}

.client-profile-container {
    background: #f8f9fa;
    min-height: 100vh;
    padding: 24px;
}

/* Profile Header */
.profile-header {
    padding: 24px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    background-color: #edf5f3;
}

.profile-avatar-section {
    display: flex;
    align-items: center;
    gap: 24px;
}

.avatar-container {
    position: relative;
}

.avatar-preview {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid #e0e0e0;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
}

.avatar-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-placeholder {
    color: white;
    font-size: 48px;
    opacity: 0.9;
}

.btn-change-photo {
    position: absolute;
    bottom: 0;
    right: 0;
    background: #007bff;
    color: white;
    border: 2px solid white;
    border-radius: 50%;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 16px;
}

.profile-title h1 {
    font-size: 28px;
    font-weight: 700;
    color: #2c3e50;
    margin: 0 0 12px 0;
}

.profile-title {
    text-align: center;
}

.profile-badges {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: center;
}

.badge {
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.3px;
    text-transform: uppercase;
}

.badge-primary {
    background: #e3f2fd;
    color: #1976d2;
    border: 1px solid #bbdefb;
}

.badge-secondary {
    background: #f3e5f5;
    color: #7b1fa2;
    border: 1px solid #e1bee7;
}

.badge-success {
    background: #e8f5e9;
    color: #388e3c;
    border: 1px solid #c8e6c9;
}

.badge-danger {
    background: #ffebee;
    color: #d32f2f;
    border: 1px solid #ffcdd2;
}

.badge-dark {
    background: #424242;
    color: white;
    border: 1px solid #616161;
}





.info-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.info-card-header {
    padding: 9px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.info-card-header i {
    font-size: 18px;
    color: #6c757d;
}

.info-card-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
}

.info-card-body {
    padding: 24px;
}

.info-row {
    margin-bottom: 20px;
}

.info-row:last-child {
    margin-bottom: 0;
}

.info-label {
    font-size: 12px;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
    font-weight: 500;
}

.info-value {
    font-size: 16px;
    color: #2c3e50;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-value i {
    color: #adb5bd;
}

.info-value.highlight {
    color: #667eea;
    font-weight: 600;
}

.status-indicator {
    display: inline-block;
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.status-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.status-warning {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}





.section-title {
    font-size: 20px;
    font-weight: 600;
    color: #2c3e50;
    margin: 0 0 20px 0;
}

/* Statistics Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin-bottom: 8px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border: 1px solid #e9ecef;
}



/* Activities Section */
.activities-grid {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.activity-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border: 1px solid #e9ecef;
    transition: transform 0.3s;
}

.activity-card:hover {
    transform: translateX(4px);
    border-color: #667eea;
}

.activity-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.activity-content {
    flex: 1;
}

.activity-title {
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 4px;
}

.activity-time {
    font-size: 14px;
    color: #6c757d;
    margin-bottom: 8px;
}

.activity-status {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.completed {
    background: #d4edda;
    color: #155724;
}

.in-progress {
    background: #e3f2fd;
    color: #1976d2;
}

.pending {
    background: #fff3cd;
    color: #856404;
}

.activity-amount {
    font-size: 20px;
    font-weight: 700;
    color: #2c3e50;
}

/* Incentives Earning Per Payroll */
.incentives-section {
    margin-bottom: 24px;
}

.incentives-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border: 1px solid #e9ecef;
}

.incentives-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.incentives-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #2c3e50;
}

.incentives-period {
    font-size: 14px;
    color: #6c757d;
    background: #f8f9fa;
    padding: 6px 16px;
    border-radius: 20px;
    font-weight: 500;
}

.incentives-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
}

.incentives-stat {
    text-align: center;
}

.stat-label {
    font-size: 14px;
    color: #6c757d;
    margin-bottom: 8px;
    font-weight: 500;
}

.stat-value {
    font-size: 24px;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 4px;
}

.stat-change {
    font-size: 13px;
    font-weight: 600;
    padding: 2px 10px;
    border-radius: 12px;
    display: inline-block;
}

.stat-change.positive {
    background: #d4edda;
    color: #155724;
}



.progress-bar {
    width: 100%;
    height: 10px;
    background: #f8f9fa;
    border-radius: 5px;
    overflow: hidden;
    border: 1px solid #e9ecef;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(135deg, #3D8D7A 0%, #2E6B5C 100%);
    border-radius: 5px;
    transition: width 0.4s ease;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .client-profile-container {
        padding: 16px;
    }

    .profile-header {
        flex-direction: column;
        gap: 24px;
        align-items: flex-start;
    }

    .profile-avatar-section {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }

    .avatar-preview {
        width: 80px;
        height: 80px;
    }

    .avatar-placeholder {
        font-size: 36px;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .performance-stats {
        grid-template-columns: 1fr;
        gap: 16px;
    }

    .activity-card {
        flex-direction: column;
        text-align: center;
        gap: 12px;
    }

    .activity-content {
        text-align: center;
    }
}

/* Employee Loans Section */
.stats-section {

    margin-bottom: 24px;

}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}

.section-title {
    font-size: 24px;
    font-weight: 700;
    color: #2c3e50;
    margin: 0;
}

.section-actions {
    display: flex;
    gap: 12px;
    align-items: center;
}

/* Loan Summary Card */
.loan-summary-card {
    background: white;
    border-radius: 12px;
    border: 1px solid #e9ecef;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.loan-main-header {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
    background: linear-gradient(135deg, rgba(61, 141, 122, 0.1) 0%, rgba(46, 107, 92, 0.1) 100%);
    border-bottom: 1px solid #e9ecef;
}

.loan-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, #3D8D7A 0%, #2E6B5C 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.loan-title-section {
    flex: 1;
}

.loan-title {
    font-size: 20px;
    font-weight: 600;
    color: #2c3e50;
    margin: 0 0 4px 0;
}

.loan-period {
    font-size: 14px;
    color: #6c757d;
    font-weight: 500;
}

.loan-trend {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
}

.loan-trend.positive {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.loan-trend.negative {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Main Stats */
.loan-main-stats {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 24px;
    padding: 24px;
    border-bottom: 1px solid #e9ecef;
}

@media (max-width: 768px) {
    .loan-main-stats {
        grid-template-columns: 1fr;
        gap: 20px;
    }
}

.primary-stat {
    text-align: center;
    padding: 16px;
    background: #f8f9fa;
    border-radius: 12px;
    border: 1px solid #e9ecef;
}

.stat-number {
    font-size: 40px;
    font-weight: 700;
    color: #2c3e50;
    line-height: 1.2;
    margin-bottom: 8px;
    font-family: 'Inter', sans-serif;
}

.stat-label {
    font-size: 14px;
    color: #6c757d;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.progress-section {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.progress-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 14px;
    color: #6c757d;
    font-weight: 500;
}

.progress-percentage {
    font-weight: 600;
    color: #3D8D7A;
    background: rgba(61, 141, 122, 0.1);
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 13px;
}

.progress-bar {
    width: 100%;
    height: 10px;
    background: #f8f9fa;
    border-radius: 5px;
    overflow: hidden;
    border: 1px solid #e9ecef;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(135deg, #3D8D7A 0%, #2E6B5C 100%);
    border-radius: 5px;
    transition: width 0.4s ease;
}

.progress-details {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    color: #6c757d;
}

.progress-detail strong {
    color: #2c3e50;
    font-weight: 600;
}

/* Loan Details Grid */
.loan-details-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    padding: 24px;
    border-bottom: 1px solid #e9ecef;
}

@media (max-width: 992px) {
    .loan-details-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 576px) {
    .loan-details-grid {
        grid-template-columns: 1fr;
    }
}

.detail-card {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 16px;
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
}

.detail-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    border-color: #3D8D7A;
}

.detail-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}

.detail-header i {
    font-size: 20px;
    color: #3D8D7A;
}

.detail-title {
    font-size: 13px;
    color: #6c757d;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.detail-content {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.detail-main-value {
    font-size: 24px;
    font-weight: 700;
    color: #2c3e50;
    line-height: 1.2;
}

.detail-main-value.text-danger {
    color: #d32f2f;
}

.detail-main-value.text-success {
    color: #388e3c;
}

.detail-sub-value {
    font-size: 13px;
    color: #6c757d;
    font-weight: 500;
}

/* Loan Footer */
.loan-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    flex-wrap: wrap;
    gap: 16px;
}

.footer-details {
    display: flex;
    gap: 24px;
    flex-wrap: wrap;
}

.footer-detail {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.footer-label {
    font-size: 12px;
    color: #6c757d;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.footer-value {
    font-size: 14px;
    color: #2c3e50;
    font-weight: 600;
}

.btn-view-details {
    background: #3D8D7A;
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-view-details:hover {
    background: rgba(255, 255, 255, 0.3);
    color: rgba(12, 78, 57, 0.5);
    border-color: rgba(12, 78, 57, 0.5);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn-collapse {
    background: transparent;
    border: none;
    color: #6c757d;
    cursor: pointer;
    font-size: 18px;
    padding: 8px;
    border-radius: 4px;
    transition: all 0.3s ease;
}

.btn-collapse:hover {
    background: rgba(0, 0, 0, 0.05);
    color: #3D8D7A;
}

/* Enhanced Incentives Section */
.incentives-section {
    margin-bottom: 24px;
}

.incentives-card {
    background: white;
    border-radius: 16px;
    padding: 0;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid #e9ecef;
    overflow: hidden;
    transition: all 0.3s ease;
}

.incentives-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
    border-color: #3D8D7A;
}

.incentives-header {
    padding: 24px;
    background: linear-gradient(135deg, rgba(61, 141, 122, 0.1) 0%, rgba(46, 107, 92, 0.05) 100%);
    border-bottom: 1px solid rgba(61, 141, 122, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.incentives-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 12px;
}

.incentives-header h3::before {
    content: '';
    display: block;
    width: 4px;
    height: 24px;
    background: linear-gradient(135deg, #3D8D7A 0%, #2E6B5C 100%);
    border-radius: 2px;
}

.incentives-period {
    font-size: 14px;
    color: #3D8D7A;
    background: rgba(61, 141, 122, 0.1);
    padding: 8px 20px;
    border-radius: 20px;
    font-weight: 600;
    border: 1px solid rgba(61, 141, 122, 0.2);
}

.incentives-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    padding: 24px;
}

@media (max-width: 992px) {
    .incentives-stats {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .incentives-stats {
        grid-template-columns: 1fr;
        gap: 16px;
    }
}

.incentives-stat {
    padding: 20px;
    background: #f8f9fa;
    border-radius: 12px;
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.incentives-stat:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
    border-color: #3D8D7A;
    background: white;
}

.incentives-stat::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(135deg, #3D8D7A 0%, #2E6B5C 100%);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.incentives-stat:hover::before {
    opacity: 1;
}

.incentives-stat .stat-label {
    font-size: 13px;
    color: #6c757d;
    margin-bottom: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    line-height: 1.4;
}

.incentives-stat .stat-value {
    font-size: 32px;
    font-weight: 800;
    color: #2c3e50;
    margin-bottom: 8px;
    line-height: 1.2;
    font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
}

.incentives-stat:nth-child(1) .stat-value {
    color: #3D8D7A;
}

.incentives-stat:nth-child(2) .stat-value {
    color: #2c3e50;
}

.incentives-stat:nth-child(3) .stat-value {
    color: #495057;
}

.incentives-stat .stat-change {
    font-size: 12px;
    font-weight: 700;
    padding: 6px 14px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    letter-spacing: 0.3px;
}

.incentives-stat .stat-change.positive {
    background: linear-gradient(135deg, rgba(61, 141, 122, 0.15) 0%, rgba(46, 107, 92, 0.1) 100%);
    color: #155724;
    border: 1px solid rgba(61, 141, 122, 0.2);
}

.incentives-stat .stat-change.positive::before {
    content: '↑';
    font-weight: 800;
}

/* Optional: Add icons to each stat */
.incentives-stat:nth-child(1)::after {
    content: '💰';
    position: absolute;
    top: 20px;
    right: 20px;
    font-size: 24px;
    opacity: 0.1;
}

.incentives-stat:nth-child(2)::after {
    content: '📊';
    position: absolute;
    top: 20px;
    right: 20px;
    font-size: 24px;
    opacity: 0.1;
}

.incentives-stat:nth-child(3)::after {
    content: '🌾';
    position: absolute;
    top: 20px;
    right: 20px;
    font-size: 24px;
    opacity: 0.1;
}

/* Mobile responsiveness for incentives section */
@media (max-width: 768px) {
    .incentives-header {
        padding: 20px;
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .incentives-header h3 {
        font-size: 18px;
    }
    
    .incentives-stat {
        padding: 16px;
    }
    
    .incentives-stat .stat-value {
        font-size: 28px;
    }
}

@media (max-width: 480px) {
    .incentives-stats {
        padding: 20px 16px;
    }
    
    .incentives-stat .stat-value {
        font-size: 24px;
    }
    
    .incentives-stat .stat-label {
        font-size: 12px;
    }
}
</style>