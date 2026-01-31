<template>
    <div class="emp-profile-container">
        <div>
            <div class="d-flex gap-2 justify-content-end">
                <button class="emp-create-btn" @click="backToList">
                    <i class="ri-arrow-left-line"></i>
                    Back to List
                </button>
                <button @click="openEdit(selectedCustomer, selectedRow)" variant="info" v-b-tooltip.hover title="Edit"
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
                    <div class="emp-info-card-header">
                        <i class="ri-user-line"></i>
                        <h3>Customer Information</h3>
                    </div>
                    <div class="emp-info-card-body">
                        <div class="emp-profile-title mb-4">
                            <h1>{{ customer.name || 'Customer Name' }}</h1>
                            <div class="emp-profile-badges">
                                <span class="emp-badge" :class="customer.is_regular ? 'emp-badge-primary' : 'emp-badge-secondary'">
                                    {{ customer.is_regular === 1 ? 'Regular' : 'Irregular' }}
                                </span>
                                <span class="emp-badge" :class="customer.is_active === 1 ? 'emp-badge-success' : 'emp-badge-danger'">
                                    {{ customer.is_active === 1 ? 'Active' : 'Inactive' }}
                                </span>
                                <span v-if="customer.is_blacklisted === 1" class="emp-badge emp-badge-dark">
                                    Blacklisted
                                </span>
                            </div>
                        </div>

                        <div class="emp-info-row">
                            <div class="emp-info-label">Customer ID</div>
                            <div class="emp-info-value emp-highlight">{{ customer.id || 'N/A' }}</div>
                        </div>
                        <div class="emp-info-row">
                            <div class="emp-info-label">Email</div>
                            <div class="emp-info-value">
                                <i class="ri-mail-line"></i>
                                {{ customer.email || 'No email' }}
                            </div>
                        </div>
                        <div class="emp-info-row">
                            <div class="emp-info-label">Contact Number</div>
                            <div class="emp-info-value">
                                <i class="ri-phone-line"></i>
                                {{ customer.contact_number || 'No contact number' }}
                            </div>
                        </div>
                        <div class="emp-info-row">
                            <div class="emp-info-label">Address</div>
                            <div class="emp-info-value">
                                <i class="ri-map-pin-line"></i>
                                {{ customer.address || 'No address' }}
                            </div>
                        </div>
                        <div class="emp-info-row">
                            <div class="emp-info-label">TIN</div>
                            <div class="emp-info-value">{{ customer.tin || '-' }}</div>
                        </div>
                        <div class="emp-info-row">
                            <div class="emp-info-label">Status</div>
                            <div class="emp-info-value">{{ customer.status ? customer.status.title : '-' }}</div>
                        </div>
                        <div class="emp-info-row">
                            <div class="emp-info-label">Member Since</div>
                            <div class="emp-info-value">{{ formatDate(customer.created_at) }}</div>
                        </div>
                        <div class="emp-info-row">
                            <div class="emp-info-label">Last Order</div>
                            <div class="emp-info-value">{{ customer.last_order_date ? formatDate(customer.last_order_date) : 'No orders yet' }}</div>
                        </div>
                    </div>
                </div>

                <!-- Credit Summary Card -->
                <div class="emp-info-card mt-4">
                    <div class="emp-info-card-header">
                        <i class="ri-bank-card-line"></i>
                        <h3>Credit Summary</h3>
                    </div>
                    <div class="emp-info-card-body">
                        <div class="emp-info-row">
                            <div class="emp-info-label">Credit Limit</div>
                            <div class="emp-info-value emp-highlight">₱{{ customer.credit_limit || 0 }}</div>
                        </div>
                        <div class="emp-info-row">
                            <div class="emp-info-label">Credit Used</div>
                            <div class="emp-info-value" :class="getCreditUsedClass(customer)">
                                ₱{{ customer.credit_used || 0 }}
                            </div>
                        </div>
                        <div class="emp-info-row">
                            <div class="emp-info-label">Available Credit</div>
                            <div class="emp-info-value emp-highlight">
                                ₱{{ (customer.credit_limit || 0) - (customer.credit_used || 0) }}
                            </div>
                        </div>
                        <div class="emp-info-row">
                            <div class="emp-info-label">Credit Score</div>
                            <div class="emp-info-value">
                                <span class="emp-status-indicator" :class="getCreditScoreClass(customer.credit_score)">
                                    {{ customer.credit_score || 'N/A' }}
                                </span>
                            </div>
                        </div>
                        <div class="emp-info-row">
                            <div class="emp-info-label">Payment Terms</div>
                            <div class="emp-info-value">{{ customer.payment_terms || '30 days' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Rice Orders & Payments -->
            <div class="col-md-8">
                <!-- Rice Orders Summary -->
                <div class="emp-incentives-section">
                    <div class="emp-incentives-card">
                        <div class="emp-incentives-header">
                            <h3>Rice Orders Summary</h3>
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
                                <div class="emp-stat-label">Total Orders</div>
                                <div class="emp-stat-value">{{ customer.total_orders || 0 }}</div>
                                <div class="emp-stat-change" :class="getTrendClass(customer.order_trend)">
                                    <i :class="getTrendIcon(customer.order_trend)"></i>
                                    {{ customer.order_trend || '0%' }}
                                </div>
                            </div>
                            <div class="emp-incentives-stat">
                                <div class="emp-stat-label">Total Rice Ordered</div>
                                <div class="emp-stat-value">{{ customer.total_rice_ordered || 0 }} kg</div>
                                <div class="emp-stat-change emp-positive">0%</div>
                            </div>
                            <div class="emp-incentives-stat">
                                <div class="emp-stat-label">Total Amount</div>
                                <div class="emp-stat-value">₱{{ customer.total_amount || 0 }}</div>
                                <div class="emp-stat-change emp-positive">0%</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Due Dates -->
                <div class="emp-stats-section mt-2">
                    <div class="emp-section-header">
                        <h2 class="emp-section-title">Payment Due Dates</h2>
                    </div>

                    <div class="emp-loan-summary-card">
                        <div class="emp-loan-main-header">
                            <div class="emp-loan-icon">
                                <i class="ri-calendar-event-line"></i>
                            </div>
                            <div class="emp-loan-title-section">
                                <h3 class="emp-loan-title">Outstanding Payments</h3>
                                <div class="emp-loan-period">As of {{ currentDate }}</div>
                            </div>
                            <button @click="toggleLoanCollapse" class="emp-create-btn">
                                <i :class="loanCollapsed ? 'ri-arrow-down-s-line' : 'ri-arrow-up-s-line'"></i>
                            </button>
                        </div>

                        <div class="emp-loan-main-stats">
                            <div class="emp-primary-stat">
                                <div class="emp-stat-number">₱{{ customer.total_due || 0 }}</div>
                                <div class="emp-stat-label">Total Due</div>
                            </div>

                            <div class="emp-progress-section">
                                <div class="emp-progress-header">
                                    <span>Payment Progress</span>
                                    <span class="emp-progress-percentage">{{ getPaymentProgress(customer) }}%</span>
                                </div>
                                <div class="emp-progress-bar">
                                    <div class="emp-progress-fill" :style="{ width: getPaymentProgress(customer) + '%' }"></div>
                                </div>
                                <div class="emp-progress-details">
                                    <span class="emp-progress-detail">Paid: <strong>₱{{ customer.paid_amount || 0 }}</strong></span>
                                    <span class="emp-progress-detail">Pending: <strong>₱{{ customer.total_due || 0 }}</strong></span>
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

                        <div v-show="!loanCollapsed" class="emp-loan-details-grid">
                            <div class="emp-detail-card" v-for="due in customer.due_dates || []" :key="due.id">
                                <div class="emp-detail-header">
                                    <i class="ri-calendar-check-line"></i>
                                    <div class="emp-detail-title">Due {{ formatRelativeDate(due.due_date) }}</div>
                                </div>
                                <div class="emp-detail-content">
                                    <div class="emp-detail-main-value" :class="getDueAmountClass(due)">
                                        ₱{{ due.amount }}
                                    </div>
                                    <div class="emp-detail-sub-value">{{ formatDate(due.due_date) }}</div>
                                    <div class="emp-detail-sub-value">Order: {{ due.order_id }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="emp-loan-footer">
                            <div class="emp-footer-details">
                                <div class="emp-footer-detail">
                                    <span class="emp-footer-label">Total Paid Amount</span>
                                    <span class="emp-footer-value">₱{{ customer.paid_amount || 0 }}</span>
                                </div>
                                <div class="emp-footer-detail">
                                    <span class="emp-footer-label">Remaining Amount</span>
                                    <span class="emp-footer-value">₱{{ customer.total_due || 0 }}</span>
                                </div>
                                <div class="emp-footer-detail">
                                    <span class="emp-footer-label">Remaining Days</span>
                                    <span class="emp-footer-value">{{ getRemainingDays(customer) }}</span>
                                </div>
                            </div>
                            <button class="emp-btn-view-details" @click="processPayment">
                                <i class="ri-money-dollar-circle-line"></i>
                                Process Payment
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="emp-info-card mt-4">
                    <div class="emp-info-card-header">
                        <i class="ri-history-line"></i>
                        <h3>Recent Activities</h3>
                    </div>
                    <div class="emp-info-card-body">
                        <div class="emp-activities-grid">
                            <div class="emp-activity-card" v-for="activity in customer.recent_activities || []" :key="activity.id">
                                <div class="emp-activity-icon" :class="getActivityIconClass(activity.type)">
                                    <i :class="getActivityIcon(activity.type)"></i>
                                </div>
                                <div class="emp-activity-content">
                                    <div class="emp-activity-title">{{ activity.title }}</div>
                                    <div class="emp-activity-time">{{ formatDateTime(activity.created_at) }}</div>
                                    <div class="emp-activity-status" :class="getActivityStatusClass(activity.status)">
                                        {{ activity.status }}
                                    </div>
                                </div>
                                <div class="emp-activity-amount" v-if="activity.amount">
                                    ₱{{ activity.amount }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    props: ['customer', 'backToList', 'openEdit', 'selectedCustomer', 'selectedRow'],
    name: 'CustomerDetails',
    data() {
        return {
            currentDate: new Date().toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            }),
            selectedYear: new Date().getFullYear(),
            selectedMonth: new Date().getMonth() + 1,
            years: Array.from({ length: 10 }, (_, i) => new Date().getFullYear() - i),
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
            loanCollapsed: true
        }
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
        
        formatDateTime(dateTimeString) {
            if (!dateTimeString) return 'N/A';
            const date = new Date(dateTimeString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        },
        
        formatRelativeDate(dateString) {
            if (!dateString) return '';
            const dueDate = new Date(dateString);
            const today = new Date();
            const diffTime = dueDate - today;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            if (diffDays === 0) return 'Today';
            if (diffDays === 1) return 'Tomorrow';
            if (diffDays > 1) return `in ${diffDays} days`;
            if (diffDays === -1) return 'Yesterday';
            return `${Math.abs(diffDays)} days ago`;
        },
        
        
        getCreditUsedClass(customer) {
            const creditLimit = customer.credit_limit || 0;
            const creditUsed = customer.credit_used || 0;
            const percentage = creditLimit > 0 ? (creditUsed / creditLimit) * 100 : 0;
            
            if (percentage >= 90) return 'emp-text-danger';
            if (percentage >= 75) return 'emp-text-warning';
            return '';
        },
        
        getCreditScoreClass(score) {
            if (!score) return 'emp-status-warning';
            if (score >= 80) return 'emp-status-success';
            if (score >= 60) return 'emp-status-warning';
            return 'emp-status-danger';
        },
        
        getTrendClass(trend) {
            if (!trend) return '';
            const trendValue = parseFloat(trend) || 0;
            return trendValue > 0 ? 'emp-positive' : trendValue < 0 ? 'emp-negative' : '';
        },
        
        getTrendIcon(trend) {
            if (!trend) return 'ri-line-chart-line';
            const trendValue = parseFloat(trend) || 0;
            return trendValue > 0 ? 'ri-arrow-up-line' : trendValue < 0 ? 'ri-arrow-down-line' : 'ri-line-chart-line';
        },
        
        getStockClass(quantity) {
            if (!quantity) return 'emp-pending';
            if (quantity > 100) return 'emp-completed';
            if (quantity > 50) return 'emp-in-progress';
            return 'emp-pending';
        },
        
        getDueStatusClass(status) {
            if (!status) return '';
            if (status.includes('Overdue')) return 'emp-negative';
            if (status.includes('Due Soon')) return 'emp-warning';
            return 'emp-positive';
        },
        
        getPaymentProgress(customer) {
            const totalAmount = (customer.total_amount || 0) + (customer.total_due || 0);
            const paidAmount = customer.paid_amount || 0;
            return totalAmount > 0 ? Math.round((paidAmount / totalAmount) * 100) : 0;
        },
        
        getDueAmountClass(due) {
            const dueDate = new Date(due.due_date);
            const today = new Date();
            const diffTime = dueDate - today;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            if (diffDays < 0) return 'emp-text-danger';
            if (diffDays <= 3) return 'emp-text-warning';
            return '';
        },
        
        getNextDueDate(customer) {
            if (!customer.due_dates || customer.due_dates.length === 0) return 'No pending dues';

            const now = new Date();
            const upcomingDues = customer.due_dates.filter(due => {
                const dueDate = new Date(due.due_date);
                return dueDate >= now;
            });

            if (upcomingDues.length === 0) return 'No upcoming dues';

            upcomingDues.sort((a, b) => new Date(a.due_date) - new Date(b.due_date));
            return this.formatDate(upcomingDues[0].due_date);
        },

        getRemainingDays(customer) {
            if (!customer.due_dates || customer.due_dates.length === 0) return 'N/A';

            const now = new Date();
            const upcomingDues = customer.due_dates.filter(due => {
                const dueDate = new Date(due.due_date);
                return dueDate >= now;
            });

            if (upcomingDues.length === 0) return 'N/A';

            upcomingDues.sort((a, b) => new Date(a.due_date) - new Date(b.due_date));
            const nextDueDate = new Date(upcomingDues[0].due_date);
            const diffTime = nextDueDate - now;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            return diffDays > 0 ? `${diffDays} days` : 'Overdue';
        },
        
        getActivityIconClass(type) {
            const iconClasses = {
                'order': 'bg-primary',
                'payment': 'bg-success',
                'credit': 'bg-warning',
                'delivery': 'bg-info',
                'return': 'bg-danger'
            };
            return iconClasses[type] || 'bg-secondary';
        },
        
        getActivityIcon(type) {
            const icons = {
                'order': 'ri-shopping-cart-line',
                'payment': 'ri-money-dollar-circle-line',
                'credit': 'ri-bank-card-line',
                'delivery': 'ri-truck-line',
                'return': 'ri-arrow-go-back-line'
            };
            return icons[type] || 'ri-notification-line';
        },
        
        getActivityStatusClass(status) {
            const statusClasses = {
                'completed': 'emp-completed',
                'pending': 'emp-pending',
                'in-progress': 'emp-in-progress',
                'cancelled': 'emp-cancelled'
            };
            return statusClasses[status] || 'emp-pending';
        },
        
        showOrderHistory() {
            // Emit event or show modal for order history
            this.$emit('showOrderHistory', this.customer);
        },
        
        showPaymentSchedule() {
            // Emit event or show modal for payment schedule
            this.$emit('showPaymentSchedule', this.customer);
        },
        
        processPayment() {
            // Emit event or show modal for payment processing
            this.$emit('processPayment', this.customer);
        },
        


        toggleLoanCollapse() {
            this.loanCollapsed = !this.loanCollapsed;
        }
    },
    computed: {
        sampleCustomerData() {
            return {
                id: this.customer.id || 'N/A',
                name: this.customer.name || 'Sample Customer',
                is_regular: this.customer.is_regular || 1,
                is_active: this.customer.is_active || 1,
                is_blacklisted: this.customer.is_blacklisted || 0,
                email: this.customer.email || 'sample@example.com',
                contact_number: this.customer.contact_number || '+63 912 345 6789',
                address: this.customer.address || 'Sample Address, City, Philippines',
                tin: this.customer.tin || '123-456-789-000',
                status: this.customer.status || { title: 'Active' },
                created_at: this.customer.created_at || new Date().toISOString(),
                last_order_date: this.customer.last_order_date || new Date().toISOString(),
                credit_limit: this.customer.credit_limit || 50000,
                credit_used: this.customer.credit_used || 15000,
                credit_score: this.customer.credit_score || 85,
                payment_terms: this.customer.payment_terms || '30 days',
                total_orders: this.customer.total_orders || 25,
                order_trend: this.customer.order_trend || '+15%',
                total_rice_ordered: this.customer.total_rice_ordered || 500,
                total_amount: this.customer.total_amount || 75000,
                avg_order_value: this.customer.avg_order_value || 3000,
                rice_varieties: this.customer.rice_varieties || [
                    { id: 1, name: 'Premium Jasmine', type: 'Long Grain', quantity: 200, total_value: 30000 },
                    { id: 2, name: 'Brown Rice', type: 'Whole Grain', quantity: 150, total_value: 22500 },
                    { id: 3, name: 'Basmati', type: 'Aromatic', quantity: 150, total_value: 22500 }
                ],
                due_dates: this.customer.due_dates || [
                    { id: 1, due_date: '2024-01-20T00:00:00Z', amount: 5000, order_id: 'ORD-001' },
                    { id: 2, due_date: '2024-01-25T00:00:00Z', amount: 3000, order_id: 'ORD-002' },
                    { id: 3, due_date: '2024-02-01T00:00:00Z', amount: 2000, order_id: 'ORD-003' }
                ],
                due_status: this.customer.due_status || 'Due Soon',
                total_due: this.customer.total_due || 10000,
                paid_amount: this.customer.paid_amount || 65000,
                overdue_amount: this.customer.overdue_amount || 0,
                recent_activities: this.customer.recent_activities || [
                    { id: 1, type: 'order', title: 'Placed new order', created_at: '2024-01-10T14:30:00Z', status: 'completed', amount: 5000 },
                    { id: 2, type: 'payment', title: 'Payment received', created_at: '2024-01-08T09:15:00Z', status: 'completed', amount: 3000 },
                    { id: 3, type: 'delivery', title: 'Order delivered', created_at: '2024-01-05T16:45:00Z', status: 'completed' },
                    { id: 4, type: 'credit', title: 'Credit limit updated', created_at: '2024-01-03T11:20:00Z', status: 'completed' }
                ]
            };
        }
    }
}
</script>

