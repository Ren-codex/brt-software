<template>
    <div class="employee-details-page">
        <div class="details-topbar">
            <div class="details-title-block">
                <h1>Customer Profile</h1>
                <p>View purchase behavior, credit status, and payment timeline.</p>
            </div>
            <div class="details-actions">
                <button class="details-btn details-btn-outline" @click="backToList">
                    <i class="ri-arrow-left-line"></i>
                    <span>Back to List</span>
                </button>
                <button @click="openEdit(selectedCustomer, selectedRow)" variant="info" v-b-tooltip.hover title="Edit"
                    class="details-btn details-btn-primary">
                    <i class="ri-pencil-fill"></i>
                    <span>Edit Customer</span>
                </button>
            </div>
        </div>

        <div class="details-grid">
            <aside class="details-sidebar">
                <!-- Contact Information Card -->
                <div class="profile-card">
                    <div class="profile-avatar-wrap">
                        <div class="profile-avatar">
                            <div class="profile-avatar-placeholder">
                                <i class="ri-user-line"></i>
                            </div>
                        </div>
                    </div>

                    <div class="profile-heading">
                        <h2>{{ customer.name || 'Customer Name' }}</h2>
                        <div class="profile-badges">
                            <span class="profile-badge" :class="customer.is_regular ? 'profile-badge-primary' : 'profile-badge-neutral'">
                                {{ customer.is_regular === 1 ? 'Regular' : 'Irregular' }}
                            </span>
                            <span class="profile-badge" :class="customer.is_active === 1 ? 'profile-badge-success' : 'profile-badge-danger'">
                                {{ customer.is_active === 1 ? 'Active' : 'Inactive' }}
                            </span>
                            <span v-if="customer.is_blacklisted === 1" class="profile-badge profile-badge-dark">
                                Blacklisted
                            </span>
                            <span v-if="customer.has_late_payment_history" class="profile-badge profile-badge-danger">
                                Late Payment History
                            </span>
                        </div>
                    </div>

                    <div class="profile-info-list">
                        <div class="profile-info-item">
                            <div class="profile-label">Customer ID</div>
                            <div class="profile-value profile-highlight">{{ customer.id || 'N/A' }}</div>
                        </div>
                        <div class="profile-info-item">
                            <div class="profile-label">Email</div>
                            <div class="profile-value">
                                <i class="ri-mail-line"></i>
                                {{ customer.email || 'No email' }}
                            </div>
                        </div>
                        <div class="profile-info-item">
                            <div class="profile-label">Contact Number</div>
                            <div class="profile-value">
                                <i class="ri-phone-line"></i>
                                {{ customer.contact_number || 'No contact number' }}
                            </div>
                        </div>
                        <div class="profile-info-item">
                            <div class="profile-label">Address</div>
                            <div class="profile-value">
                                <i class="ri-map-pin-line"></i>
                                {{ customer.address || 'No address' }}
                            </div>
                        </div>
                        <div class="profile-info-item">
                            <div class="profile-label">TIN</div>
                            <div class="profile-value">{{ customer.tin || '-' }}</div>
                        </div>
                        <div class="profile-info-item">
                            <div class="profile-label">Status</div>
                            <div class="profile-value">{{ customer.status ? customer.status.title : '-' }}</div>
                        </div>
                        <div class="profile-info-item">
                            <div class="profile-label">Member Since</div>
                            <div class="profile-value">{{ formatDate(customer.created_at) }}</div>
                        </div>
                        <div class="profile-info-item">
                            <div class="profile-label">Last Order</div>
                            <div class="profile-value">{{ customer.last_order_date ? formatDate(customer.last_order_date) : 'No orders yet' }}</div>
                        </div>
                    </div>

                    <!-- Credit Summary Card -->
                    <div class="profile-subcard">
                        <div class="profile-subcard-header">
                            <i class="ri-bank-card-line"></i>
                            <h3>Credit Summary</h3>
                        </div>
                        <div class="profile-subcard-body">
                            <div class="profile-info-item">
                                <div class="profile-label">Credit Limit</div>
                                <div class="profile-value profile-highlight">₱{{ formatCurrencyNumeric(customer.credit_limit || 0) }}</div>
                            </div>
                            <div class="profile-info-item">
                                <div class="profile-label">Credit Used</div>
                                <div class="profile-value" :class="getCreditUsedClass(customer)">
                                    ₱{{ formatCurrencyNumeric(customer.credit_used || 0) }}
                                </div>
                            </div>
                            <div class="profile-info-item">
                                <div class="profile-label">Available Credit</div>
                                <div class="profile-value profile-highlight">
                                    ₱{{ formatCurrencyNumeric((customer.credit_limit || 0) - (customer.credit_used || 0)) }}
                                </div>
                            </div>
                            <div class="profile-info-item">
                                <div class="profile-label">Credit Score</div>
                                <div class="profile-value">
                                    <span class="status-chip" :class="getCreditScoreClass(customer.credit_score)">
                                        {{ customer.credit_score || 'N/A' }}
                                    </span>
                                </div>
                            </div>
                            <div class="profile-info-item">
                                <div class="profile-label">Payment Terms</div>
                                <div class="profile-value">{{ customer.payment_terms || '30 days' }}</div>
                            </div>
                            <div class="profile-info-item">
                                <div class="profile-label">Late Payments</div>
                                <div class="profile-value" :class="customer.has_late_payment_history ? 'details-danger' : ''">
                                    {{ customer.late_payment_count || 0 }}
                                </div>
                            </div>
                            <div class="profile-info-item" v-if="customer.has_late_payment_history">
                                <div class="profile-label">Last Late Payment</div>
                                <div class="profile-value details-danger">
                                    {{ formatDate(customer.last_late_payment_date) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            <section class="details-main">
                <!-- Purchase Summary -->
                <div class="details-card">
                    <div class="details-card-header details-card-header-between">
                        <h3>
                            <i class="ri-shopping-bag-line"></i>
                            Purchase Summary
                        </h3>
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
                            <div class="details-stat-label">Total Orders</div>
                            <div class="details-stat-value">{{ orderSummary.total_orders || 0 }}</div>
                            <div class="details-stat-trend" :class="getTrendClass(orderSummary.order_trend)">
                                <i :class="getTrendIcon(orderSummary.order_trend)"></i>
                                {{ orderSummary.order_trend || '0%' }}
                            </div>
                        </div>
                        <div class="details-stat">
                            <div class="details-stat-label">Total Rice Ordered</div>
                            <div class="details-stat-value">{{ formatQuantity(orderSummary.total_rice_ordered) }} kg</div>
                            <div class="details-stat-trend" :class="getTrendClass(orderSummary.rice_trend)">
                                <i :class="getTrendIcon(orderSummary.rice_trend)"></i>
                                {{ orderSummary.rice_trend || '0%' }}
                            </div>
                        </div>
                        <div class="details-stat">
                            <div class="details-stat-label">Total Amount</div>
                            <div class="details-stat-value">₱{{ formatCurrencyNumeric(orderSummary.total_amount) }}</div>
                            <div class="details-stat-trend" :class="getTrendClass(orderSummary.amount_trend)">
                                <i :class="getTrendIcon(orderSummary.amount_trend)"></i>
                                {{ orderSummary.amount_trend || '0%' }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Purchase Records -->
                <div class="details-card">
                    <div class="details-card-header details-card-header-between">
                        <h3>
                            <i class="ri-file-list-3-line"></i>
                            Purchase Records
                        </h3>
                        <span v-if="purchaseHistory.length" class="records-count">
                            {{ purchaseHistory.length }} records
                        </span>
                    </div>

                    <div class="table-wrapper">
                        <div class="table-responsive">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>SO #</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Payment</th>
                                        <th class="text-end">Items</th>
                                        <th class="text-end">KG</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="order in purchaseHistory" :key="order.id" class="table-row">
                                        <td>
                                            {{ order.so_number || '-' }}
                                            <small
                                                v-if="String(order.payment_mode || '').toLowerCase() === 'credit' && order.due_date"
                                                class="status-badge"
                                                style="font-size: 10px; background-color: red; color: white;"
                                            >
                                                Due on {{ formatDate(order.due_date) }}
                                            </small>
                                        </td>
                                        <td>
                                            <span class="date-cell">
                                                <i class="ri-calendar-line"></i>
                                                {{ formatDate(order.order_date) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge" :class="getStatusClass(order.status)">
                                                {{ order.status || '-' }}
                                            </span>
                                        </td>
                                        <td>
                                            <div>{{ order.payment_mode || '-' }}</div>
                                        </td>
                                        <td class="text-end">
                                            <span class="amount">{{ order.total_items || 0 }}</span>
                                        </td>
                                        <td class="text-end">
                                            <span class="amount">{{ formatQuantity(order.total_kg) }}</span>
                                        </td>
                                        <td class="text-end">
                                            <span class="amount returned">&#8369;{{ formatCurrencyNumeric(order.total_amount) }}</span>
                                        </td>
                                    </tr>

                                    <tr v-if="!purchaseHistoryLoading && purchaseHistory.length === 0">
                                        <td colspan="7">
                                            <div class="empty-state">
                                                <i class="ri-inbox-line"></i>
                                                <p>No purchase history found</p>
                                            </div>
                                        </td>
                                    </tr>

                                    <tr v-if="purchaseHistoryLoading">
                                        <td colspan="7">
                                            <div class="loading-state">
                                                <i class="ri-loader-4-line ri-spin"></i>
                                                <p>Loading purchase history...</p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="pagination-wrapper" v-if="purchaseHistoryMeta && purchaseHistoryLinks && purchaseHistory.length">
                        <Pagination
                            :lists="purchaseHistory.length"
                            :links="purchaseHistoryLinks"
                            :pagination="purchaseHistoryMeta"
                            @fetch="fetchPurchaseHistory"
                        />
                    </div>
                </div>

                <!-- Payment Due Dates -->
                <div class="details-card">
                    <div class="details-card-header details-card-header-between">
                        <h3>
                            <i class="ri-calendar-event-line"></i>
                            Payment Due Dates
                        </h3>
                        <button @click="toggleLoanCollapse" class="details-btn details-btn-outline details-btn-sm">
                            <i :class="loanCollapsed ? 'ri-arrow-down-s-line' : 'ri-arrow-up-s-line'"></i>
                            <span>{{ loanCollapsed ? 'Expand' : 'Collapse' }}</span>
                        </button>
                    </div>

                    <div class="loan-overview">
                        <div class="loan-balance">
                            <div class="loan-balance-title">Total Due</div>
                            <div class="loan-balance-value">₱{{ formatCurrencyNumeric(customer.total_due || 0) }}</div>
                        </div>

                        <div class="loan-progress">
                            <div class="loan-progress-head">
                                <span>Payment Progress</span>
                                <span>{{ getPaymentProgress(customer) }}% Complete</span>
                            </div>
                            <div class="loan-progress-bar">
                                <div class="loan-progress-fill" :style="{ width: getPaymentProgress(customer) + '%' }"></div>
                            </div>
                            <div class="loan-progress-meta">
                                <span>Paid: <strong>₱{{ formatCurrencyNumeric(customer.paid_amount || 0) }}</strong></span>
                                <span>Remaining: <strong>₱{{ formatCurrencyNumeric(customer.total_due || 0) }}</strong></span>
                            </div>
                        </div>
                    </div>

                    <div v-show="!loanCollapsed" class="loan-details-grid">
                        <div class="loan-detail-card">
                            <div class="loan-detail-label">
                                <i class="ri-calendar-todo-line"></i>
                                Payment Terms
                            </div>
                            <div class="loan-detail-value">{{ paymentTermsLabel }}</div>
                            <div class="loan-detail-sub">{{ unpaidDueCount }} Remaining</div>
                        </div>
                        <div class="loan-detail-card">
                            <div class="loan-detail-label">
                                <i class="ri-time-line"></i>
                                Unpaid Months
                            </div>
                            <div class="loan-detail-value details-danger">{{ unpaidDueCount }}</div>
                            <div class="loan-detail-sub">₱{{ formatCurrencyNumeric(unpaidDueAmount) }} Due</div>
                        </div>
                        <div class="loan-detail-card">
                            <div class="loan-detail-label">
                                <i class="ri-checkbox-circle-line"></i>
                                Paid Months
                            </div>
                            <div class="loan-detail-value details-success">{{ paidDueCount }}</div>
                            <div class="loan-detail-sub">₱{{ formatCurrencyNumeric(customer.paid_amount || 0) }} Paid</div>
                        </div>
                        <div class="loan-detail-card">
                            <div class="loan-detail-label">
                                <i class="ri-calendar-event-line"></i>
                                Next Due
                            </div>
                            <div class="loan-detail-value">{{ nextDueLabel }}</div>
                            <div class="loan-detail-sub">{{ nextDueRelative }}</div>
                        </div>
                    </div>

                    <div v-show="!loanCollapsed" class="loan-details-grid">
                        <div class="loan-detail-card" v-for="due in sortedDueDates" :key="due.id">
                            <div class="loan-detail-label">
                                <i class="ri-calendar-check-line"></i>
                                Due {{ formatRelativeDate(due.due_date) }}
                            </div>
                            <div class="loan-detail-value" :class="getDueAmountClass(due)">
                                ₱{{ formatCurrencyNumeric(due.amount) }}
                            </div>
                            <div class="loan-detail-sub">{{ formatDate(due.due_date) }}</div>
                            <div class="loan-detail-sub">Order: {{ due.order_id }}</div>
                        </div>
                    </div>

                    <div class="loan-footer">
                        <div class="loan-footer-item">
                            <span>Total Paid Amount</span>
                            <strong>₱{{ formatCurrencyNumeric(customer.paid_amount || 0) }}</strong>
                        </div>
                        <div class="loan-footer-item">
                            <span>Remaining Amount</span>
                            <strong>₱{{ formatCurrencyNumeric(customer.total_due || 0) }}</strong>
                        </div>
                        <div class="loan-footer-item">
                            <span>Remaining Days</span>
                            <strong>{{ getRemainingDays(customer) }}</strong>
                        </div>
                    </div>
                </div>

                <!-- Recent Receipts -->
                <div class="details-card">
                    <div class="details-card-header">
                        <h3>
                            <i class="ri-receipt-line"></i>
                            Recent Receipts
                        </h3>
                    </div>
                    <div class="receipts-grid">
                        <div class="receipt-card" v-for="receipt in customer.recent_receipts || []" :key="receipt.id">
                            <div class="receipt-icon bg-success">
                                <i class="ri-receipt-line"></i>
                            </div>
                            <div class="receipt-content">
                                <div class="receipt-title">
                                    {{ receipt.receipt_number || ('Receipt #' + receipt.id) }}
                                    <small v-if="receipt.so_number">({{ receipt.so_number }})</small>
                                </div>
                                <div class="receipt-time">{{ formatDate(receipt.receipt_date) }}</div>
                                <div class="receipt-status" :class="getReceiptStatusClass(receipt.status)">
                                    {{ receipt.status || 'Pending' }}
                                </div>
                            </div>
                            <div class="receipt-amount">
                                &#8369;{{ formatCurrencyNumeric(receipt.amount_paid) }}
                            </div>
                        </div>
                        <div v-if="(customer.recent_receipts || []).length === 0" class="empty-state text-center">
                            <i class="ri-inbox-line"></i>
                            <p class="mb-0">No recent receipts found</p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>

<script>
import Pagination from '@/Shared/Components/Pagination.vue';

export default {
    components: { Pagination },
    props: ['customer', 'backToList', 'openEdit', 'selectedCustomer', 'selectedRow'],
    name: 'CustomerDetails',
    data() {
        return {
            currentDate: new Date().toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            }),
            orderSummary: {
                total_orders: 0,
                total_rice_ordered: 0,
                total_amount: 0,
                order_trend: '0%',
                rice_trend: '0%',
                amount_trend: '0%',
            },
            purchaseHistory: [],
            purchaseHistoryLoading: false,
            purchaseHistoryMeta: null,
            purchaseHistoryLinks: null,
            latestPurchaseOrder: null,
            selectedYear: null,
            selectedMonth: null,
            loanCollapsed: true
        }
    },
    watch: {
        customer: {
            immediate: true,
            handler() {
                this.initializePeriodFilter();
                this.fetchOrderSummary();
                this.fetchPurchaseHistory();
            }
        },
        years: {
            immediate: true,
            handler(list) {
                if (!Array.isArray(list) || list.length === 0) return;
                if (!list.includes(this.selectedYear)) {
                    this.selectedYear = list[0];
                }
            }
        },
        months: {
            immediate: true,
            handler(list) {
                if (!Array.isArray(list) || list.length === 0) return;
                const monthValues = list.map(month => month.value);
                if (!monthValues.includes(this.selectedMonth)) {
                    this.selectedMonth = list[0].value;
                }
            }
        },
        selectedYear() {
            this.fetchOrderSummary();
            this.fetchPurchaseHistory();
        },
        selectedMonth() {
            this.fetchOrderSummary();
            this.fetchPurchaseHistory();
        }
    },
    methods: {
        isDueMarkedPaid(due) {
            if (!due) return false;
            const status = String(due.status || '').toLowerCase();

            return Boolean(
                due.paid_at ||
                due.is_paid === true ||
                due.is_paid === 1 ||
                due.paid === true ||
                due.paid === 1 ||
                status === 'paid' ||
                status === 'completed'
            );
        },
        initializePeriodFilter() {
            const now = new Date();
            if (!this.selectedYear) {
                this.selectedYear = now.getFullYear();
            }
            if (!this.selectedMonth) {
                this.selectedMonth = now.getMonth() + 1;
            }
        },
        buildDate(value) {
            if (!value) return null;
            const parsed = new Date(value);
            return Number.isNaN(parsed.getTime()) ? null : parsed;
        },
        fetchOrderSummary() {
            if (!this.customer?.id || !this.selectedYear || !this.selectedMonth) return;

            axios.get(`/customers/${this.customer.id}/order-summary`, {
                params: {
                    year: this.selectedYear,
                    month: this.selectedMonth,
                },
            })
                .then((response) => {
                    this.orderSummary = {
                        ...this.orderSummary,
                        ...(response.data?.data || {}),
                    };
                })
                .catch(() => {
                    this.orderSummary = {
                        total_orders: 0,
                        total_rice_ordered: 0,
                        total_amount: 0,
                        order_trend: '0%',
                        rice_trend: '0%',
                        amount_trend: '0%',
                    };
                });
        },
        fetchPurchaseHistory(page_url) {
            if (!this.customer?.id) {
                this.purchaseHistory = [];
                this.purchaseHistoryMeta = null;
                this.purchaseHistoryLinks = null;
                this.latestPurchaseOrder = null;
                return;
            }

            page_url = page_url || `/customers/${this.customer.id}/purchase-history`;
            this.purchaseHistoryLoading = true;
            axios.get(page_url, {
                params: {
                    count: 10,
                    year: this.selectedYear,
                    month: this.selectedMonth,
                },
            })
                .then((response) => {
                    this.purchaseHistory = Array.isArray(response.data?.data) ? response.data.data : [];
                    this.purchaseHistoryMeta = response.data?.meta || null;
                    this.purchaseHistoryLinks = response.data?.links || null;
                    if ((response.data?.meta?.current_page || 1) === 1) {
                        this.latestPurchaseOrder = this.purchaseHistory[0]?.so_number || null;
                    }
                })
                .catch(() => {
                    this.purchaseHistory = [];
                    this.purchaseHistoryMeta = null;
                    this.purchaseHistoryLinks = null;
                    this.latestPurchaseOrder = null;
                })
                .finally(() => {
                    this.purchaseHistoryLoading = false;
                });
        },
        formatQuantity(value) {
            return Number(value || 0).toLocaleString('en-US', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 2
            });
        },
        formatCurrencyNumeric(value) {
            return Number(value || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        },
        formatCurrency(value) {
            return Number(value || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        },
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
            
            if (percentage >= 90) return 'details-danger';
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
            return trendValue > 0 ? 'details-stat-trend-up' : trendValue < 0 ? 'details-danger' : '';
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
            if (status.includes('Overdue')) return 'details-danger';
            if (status.includes('Due Soon')) return 'emp-warning';
            return 'details-stat-trend-up';
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
            
            if (diffDays < 0) return 'details-danger';
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
        
        getReceiptStatusClass(status) {
            const normalized = String(status || '').toLowerCase();
            const statusClasses = {
                'paid': 'emp-status-success',
                'liquidated': 'emp-status-success',
                'completed': 'emp-status-success',
                'pending': 'emp-status-warning',
                'in-progress': 'emp-status-warning',
                'cancelled': 'emp-status-danger'
            };
            return statusClasses[normalized] || 'emp-status-warning';
        },
        getStatusClass(status) {
            if (!status) return '';
            const normalized = String(status).toLowerCase().replace(/\s+/g, '-');
            if (normalized === 'disapproved') return 'danger';
            if (normalized === 'approved') return 'warning';
            return normalized;
        },
        
        showOrderHistory() {
            this.$emit('showOrderHistory', this.customer);
        },
        
        showPaymentSchedule() {
            this.$emit('showPaymentSchedule', this.customer);
        },
        
        processPayment() {
            this.$emit('processPayment', this.customer);
        },
        
        toggleLoanCollapse() {
            this.loanCollapsed = !this.loanCollapsed;
        }
    },
    computed: {
        sortedDueDates() {
            return [...(this.customer?.due_dates || [])]
                .filter(due => due?.due_date)
                .sort((a, b) => new Date(a.due_date) - new Date(b.due_date));
        },
        hasExplicitDuePaymentStatus() {
            return this.sortedDueDates.some(due =>
                due?.paid_at ||
                due?.is_paid === true ||
                due?.is_paid === 1 ||
                due?.paid === true ||
                due?.paid === 1 ||
                ['paid', 'completed'].includes(String(due?.status || '').toLowerCase())
            );
        },
        totalDueDatesAmount() {
            return this.sortedDueDates.reduce((sum, due) => sum + (Number(due?.amount) || 0), 0);
        },
        unpaidDueDates() {
            return this.sortedDueDates.filter(due => !this.isDueMarkedPaid(due));
        },
        paymentTermsLabel() {
            if (this.customer?.payment_terms) return this.customer.payment_terms;
            const terms = this.sortedDueDates.length;
            return terms > 0 ? `${terms} Months` : 'N/A';
        },
        unpaidDueCount() {
            const totalTerms = this.sortedDueDates.length;
            if (!totalTerms) return 0;

            if (this.hasExplicitDuePaymentStatus) {
                return this.unpaidDueDates.length;
            }

            const remainingAmount = Number(this.customer?.total_due) || 0;
            if (remainingAmount > 0 && this.totalDueDatesAmount > 0) {
                return Math.min(
                    totalTerms,
                    Math.max(0, Math.round((remainingAmount / this.totalDueDatesAmount) * totalTerms))
                );
            }

            return totalTerms;
        },
        unpaidDueAmount() {
            const remainingAmount = Number(this.customer?.total_due) || 0;
            if (remainingAmount > 0) return remainingAmount;

            if (this.hasExplicitDuePaymentStatus) {
                return this.unpaidDueDates.reduce((sum, due) => sum + (Number(due?.amount) || 0), 0);
            }

            return this.totalDueDatesAmount;
        },
        paidDueCount() {
            return Math.max(this.sortedDueDates.length - this.unpaidDueCount, 0);
        },
        nextDueItem() {
            const source = this.hasExplicitDuePaymentStatus ? this.unpaidDueDates : this.sortedDueDates;
            if (!source.length) return null;

            const now = new Date();
            const upcoming = source.filter(due => new Date(due.due_date) >= now);
            return upcoming.length ? upcoming[0] : source[0];
        },
        nextDueLabel() {
            return this.nextDueItem ? this.formatDate(this.nextDueItem.due_date) : 'No pending dues';
        },
        nextDueRelative() {
            return this.nextDueItem ? this.formatRelativeDate(this.nextDueItem.due_date) : 'N/A';
        },
        timelineDates() {
            const dates = [];
            const baseFields = [this.customer?.created_at, this.customer?.last_order_date];

            baseFields.forEach(value => {
                const date = this.buildDate(value);
                if (date) dates.push(date);
            });

            (this.customer?.due_dates || []).forEach(item => {
                const date = this.buildDate(item?.due_date);
                if (date) dates.push(date);
            });

            (this.customer?.recent_receipts || []).forEach(item => {
                const date = this.buildDate(item?.receipt_date);
                if (date) dates.push(date);
            });

            return dates;
        },
        years() {
            const currentYear = new Date().getFullYear();
            const uniqueYears = [...new Set([
                ...this.timelineDates.map(date => date.getFullYear()),
                currentYear
            ])]
                .sort((a, b) => b - a);

            return uniqueYears;
        },
        months() {
            const monthValues = Array.from({ length: 12 }, (_, index) => index + 1);

            return monthValues.map(value => ({
                value,
                label: new Date(2000, value - 1, 1).toLocaleString('en-US', { month: 'long' })
            }));
        },
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
                has_late_payment_history: this.customer.has_late_payment_history || false,
                late_payment_count: this.customer.late_payment_count || 0,
                last_late_payment_date: this.customer.last_late_payment_date || null,
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
                recent_receipts: this.customer.recent_receipts || [
                    { id: 1, receipt_number: 'RCP-202601-0001', receipt_date: '2026-01-10', status: 'Paid', amount_paid: 5000, so_number: 'SO-0001' },
                    { id: 2, receipt_number: 'RCP-202601-0002', receipt_date: '2026-01-08', status: 'Pending', amount_paid: 3000, so_number: 'SO-0002' },
                    { id: 3, receipt_number: 'RCP-202601-0003', receipt_date: '2026-01-05', status: 'Liquidated', amount_paid: 2000, so_number: 'SO-0003' }
                ]
            };
        }
    }
}
</script>

<style scoped src="@/Shared/Styles/employee-details-layout.css"></style>


