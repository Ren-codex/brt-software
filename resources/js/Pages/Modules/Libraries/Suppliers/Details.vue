<template>
    <div class="employee-details-page">
        <div class="details-topbar">
            <div class="details-title-block">
                <h1>Supplier Profile</h1>
                <p>View supplier records, purchase activity, and stock-return performance.</p>
            </div>
            <div class="details-actions">
                <button class="details-btn details-btn-outline" @click="backToList">
                    <i class="ri-arrow-left-line"></i>
                    <span>Back to List</span>
                </button>
                <button @click="openEdit(supplierData)" variant="info" v-b-tooltip.hover title="Edit"
                    class="details-btn details-btn-primary">
                    <i class="ri-pencil-fill"></i>
                    <span>Edit Supplier</span>
                </button>
            </div>
        </div>

        <div class="details-grid">
            <!-- Left Column - Basic Information -->
            <aside class="details-sidebar">
                <!-- Contact Information Card -->
                <div class="profile-card">
                    <div class="profile-avatar-wrap">
                        <div class="profile-avatar">
                            <div class="profile-avatar-placeholder">
                                <i class="ri-store-line"></i>
                            </div>
                        </div>
                    </div>

                    <div class="profile-heading">
                        <h2>{{ supplierData.name || 'Supplier Name' }}</h2>
                        <div class="profile-badges">
                            <span class="profile-badge" :class="supplierData.is_active === 1 ? 'profile-badge-success' : 'profile-badge-danger'">
                                {{ supplierData.is_active === 1 ? 'Active' : 'Inactive' }}
                            </span>
                            <span v-if="supplierData.is_blacklisted === 1" class="profile-badge profile-badge-dark">
                                Blacklisted
                            </span>
                        </div>
                    </div>

                    <div class="profile-info-list">
                        <div class="profile-info-item">
                            <div class="profile-label">Supplier ID</div>
                            <div class="profile-value profile-highlight">{{ supplierData.id || 'N/A' }}</div>
                        </div>
                        <div class="profile-info-item">
                            <div class="profile-label">Email</div>
                            <div class="profile-value">
                                <i class="ri-mail-line"></i>
                                {{ supplierData.email || 'No email' }}
                            </div>
                        </div>
                        <div class="profile-info-item">
                            <div class="profile-label">Contact Person</div>
                            <div class="profile-value">
                                <i class="ri-user-line"></i>
                                {{ supplierData.contact_person || '-' }}
                            </div>
                        </div>
                        <div class="profile-info-item">
                            <div class="profile-label">Contact Number</div>
                            <div class="profile-value">
                                <i class="ri-phone-line"></i>
                                {{ supplierData.contact_number || 'No contact number' }}
                            </div>
                        </div>
                        <div class="profile-info-item">
                            <div class="profile-label">Address</div>
                            <div class="profile-value">
                                <i class="ri-map-pin-line"></i>
                                {{ supplierData.address || 'No address' }}
                            </div>
                        </div>
                        <div class="profile-info-item">
                            <div class="profile-label">TIN</div>
                            <div class="profile-value">
                                <i class="ri-file-list-3-line"></i>
                                {{ supplierData.tin || '-' }}
                            </div>
                        </div>
                        <div class="profile-info-item">
                            <div class="profile-label">Created At</div>
                            <div class="profile-value">{{ formatDate(supplierData.created_at) }}</div>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Right Column - Statistics & Records -->
            <section class="details-main">
                <!-- Purchase Orders Section -->
                <div class="details-card">
                    <div class="details-card-header details-card-header-between">
                        <h3>
                            <i class="ri-shopping-cart-line"></i>
                            Purchase Orders
                        </h3>
                    </div>
                    <div class="details-stat-grid">
                        <div class="details-stat">
                            <div class="details-stat-label">Total Orders</div>
                            <div class="details-stat-value">{{ purchaseOrderSummary.total_orders }}</div>
                        </div>
                        <div class="details-stat">
                            <div class="details-stat-label">Pending</div>
                            <div class="details-stat-value">{{ purchaseOrderSummary.pending_orders }}</div>
                        </div>
                        <div class="details-stat">
                            <div class="details-stat-label">Completed</div>
                            <div class="details-stat-value">{{ purchaseOrderSummary.completed_orders }}</div>
                        </div>
                    </div>
                </div>

                <!-- Stock Return Summary -->
                <div class="details-card">
                    <div class="details-card-header details-card-header-between">
                        <h3>
                            <i class="ri-arrow-left-circle-line"></i>
                            Stock Return Summary
                        </h3>
                    </div>

                    <!-- Stats Cards -->
                    <div class="details-stat-grid">
                        <div class="details-stat">
                            <div class="details-stat-label">Returned</div>
                            <div class="details-stat-value details-danger">{{ stockReturnSummary.total_returned }}</div>
                        </div>
                        <div class="details-stat">
                            <div class="details-stat-label">Replaced</div>
                            <div class="details-stat-value details-success">{{ stockReturnSummary.total_replaced }}</div>
                        </div>
                        <div class="details-stat">
                            <div class="details-stat-label">Loss</div>
                            <div class="details-stat-value">{{ stockReturnSummary.total_loss }}</div>
                        </div>
                    </div>

                    <!-- Records Section -->
                    <div class="records-section">
                        <div class="section-header">
                            <h4>Stock Return Records</h4>
                            <span class="records-count" v-if="stockReturns.length">
                                {{ stockReturns.length }} records
                            </span>
                        </div>

                        <div class="table-wrapper">
                            <div class="table-responsive">
                                <table class="modern-table">
                                    <thead>
                                        <tr>
                                            <th>Stock Return #</th>
                                            <th>PO #</th>
                                            <th class="text-end">Returned</th>
                                            <th class="text-end">Replaced</th>
                                            <th class="text-end">Loss</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="item in stockReturns" :key="item.id" class="table-row">
                                            <td>
                                                {{ item.stock_return_no || `SR-${item.id}` }}
                                            </td>
                                            <td>
                                                <span class="po-number">{{ item.po_number || '-' }}</span>
                                                <span class="status-badge" :class="getStatusClass(item.status_name)">
                                                    {{ item.status_name || '-' }}
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <span class="amount returned">{{ item.total_returned }}</span>
                                            </td>
                                            <td class="text-end">
                                                <span class="amount replaced">{{ item.total_replaced }}</span>
                                            </td>
                                            <td class="text-end">
                                                <span class="amount loss">{{ item.total_loss }}</span>
                                            </td>
                                            <td>
                                                <span class="date-cell">
                                                    <i class="ri-calendar-line"></i>
                                                    {{ formatDate(item.created_at) }}
                                                </span>
                                            </td>
                                        </tr>
                                        
                                        <!-- Empty State -->
                                        <tr v-if="!stockReturnsLoading && stockReturns.length === 0">
                                            <td colspan="6">
                                                <div class="empty-state">
                                                    <i class="ri-inbox-line"></i>
                                                    <p>No stock return records found</p>
                                                </div>
                                            </td>
                                        </tr>
                                        
                                        <!-- Loading State -->
                                        <tr v-if="stockReturnsLoading">
                                            <td colspan="6">
                                                <div class="loading-state">
                                                    <i class="ri-loader-4-line ri-spin"></i>
                                                    <p>Loading stock return records...</p>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div class="pagination-wrapper" v-if="stockReturnsMeta && stockReturnsLinks && stockReturns.length">
                            <Pagination
                                :lists="stockReturns.length"
                                :links="stockReturnsLinks"
                                :pagination="stockReturnsMeta"
                                @fetch="fetchStockReturns"
                            />
                        </div>
                    </div>
                </div>


                <!-- Recent Activity Section -->
                <div class="details-card">
                    <div class="details-card-header">
                        <h3>
                            <i class="ri-history-line"></i>
                            Recent Activity
                        </h3>
                    </div>

                    <div class="loan-overview">
                        <div class="loan-balance">
                            <div class="loan-balance-title">Supplier ID</div>
                            <div class="loan-balance-value">{{ supplierData.id || 'N/A' }}</div>
                        </div>

                        <div class="loan-progress">
                            <div class="loan-progress-head">
                                <span>Status</span>
                                <span :class="supplierData.is_active === 1 ? 'details-success' : 'details-danger'">
                                    {{ supplierData.is_active === 1 ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            <div class="loan-progress-bar">
                                <div class="loan-progress-fill" :style="{ width: supplierData.is_active === 1 ? '100%' : '0%' }"></div>
                            </div>
                            <div class="loan-progress-meta">
                                <span>Created: <strong>{{ formatDate(supplierData.created_at) }}</strong></span>
                                <span>Updated: <strong>{{ formatDate(supplierData.updated_at) }}</strong></span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
    <Create @add="fetchData" @update="fetchData" :dropdowns="dropdowns" ref="create" />
</template>

<script>
import Create from './Modals/Create.vue';
import Pagination from '@/Shared/Components/Pagination.vue';

export default {
    components: { Create, Pagination },
    props: ['supplier', 'backToList', 'dropdowns'],
    name: 'SupplierDetails',
    data() {
        return {
            supplierDetails: {},
            purchaseOrderSummary: {
                total_orders: 0,
                pending_orders: 0,
                completed_orders: 0,
            },
            stockReturnSummary: {
                total_returned: 0,
                total_replaced: 0,
                total_loss: 0,
                total_stock_returns: 0,
            },
            stockReturns: [],
            stockReturnsLoading: false,
            stockReturnsMeta: null,
            stockReturnsLinks: null,
        };
    },
    watch: {
        'supplier.id': {
            handler() {
                this.fetchData();
            },
            immediate: true,
        },
    },
    computed: {
        supplierData() {
            return this.supplierDetails?.id ? this.supplierDetails : (this.supplier || {});
        },
    },
    methods: {
        getSupplierId() {
            return this.supplier?.id || null;
        },
        fetchData() {
            this.fetchSupplierDetails();
            this.fetchPurchaseOrderSummary();
            this.fetchStockReturnSummary();
            this.fetchStockReturns();
        },
        fetchSupplierDetails() {
            const id = this.getSupplierId();
            if (!id) {
                this.supplierDetails = {};
                return;
            }

            axios.get(`/suppliers/${id}`)
                .then((response) => {
                    const payload = response.data?.data ?? response.data;
                    this.supplierDetails = payload || {};
                })
                .catch(() => {
                    this.supplierDetails = {};
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
        getStatusClass(status) {
            if (!status) return '';
            const normalized = String(status).toLowerCase().replace(/\s+/g, '-');
            if (normalized === 'disapproved') return 'danger';
            if (normalized === 'approved') return 'warning';
            return normalized;
        },
        openEdit(data) {
            this.$refs.create.edit(data);
        },
        fetchPurchaseOrderSummary() {
            const id = this.getSupplierId();
            if (!id) return;

            axios.get(`/suppliers/${id}/purchase-order-summary`)
                .then((response) => {
                    this.purchaseOrderSummary = {
                        ...this.purchaseOrderSummary,
                        ...(response.data?.data || {}),
                    };
                })
                .catch(() => {
                    this.purchaseOrderSummary = {
                        total_orders: 0,
                        pending_orders: 0,
                        completed_orders: 0,
                    };
                });
        },
        fetchStockReturnSummary() {
            const id = this.getSupplierId();
            if (!id) return;

            axios.get(`/suppliers/${id}/stock-return-summary`)
                .then((response) => {
                    this.stockReturnSummary = {
                        ...this.stockReturnSummary,
                        ...(response.data?.data || {}),
                    };
                })
                .catch(() => {
                    this.stockReturnSummary = {
                        total_returned: 0,
                        total_replaced: 0,
                        total_loss: 0,
                        total_stock_returns: 0,
                    };
                });
        },
        fetchStockReturns(page_url) {
            const id = this.getSupplierId();
            if (!id) {
                this.stockReturns = [];
                this.stockReturnsMeta = null;
                this.stockReturnsLinks = null;
                return;
            }

            page_url = page_url || `/suppliers/${id}/stock-returns`;
            this.stockReturnsLoading = true;
            axios.get(page_url, {
                params: {
                    count: 10,
                },
            })
                .then((response) => {
                    const rows = response.data?.data;
                    this.stockReturns = Array.isArray(rows) ? rows : [];
                    this.stockReturnsMeta = response.data?.meta || null;
                    this.stockReturnsLinks = response.data?.links || null;
                })
                .catch(() => {
                    this.stockReturns = [];
                    this.stockReturnsMeta = null;
                    this.stockReturnsLinks = null;
                })
                .finally(() => {
                    this.stockReturnsLoading = false;
                });
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
    --warn-600: #9a6b19;
    --ok-600: #157856;
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
    width: 100px;
    height: 100px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid #fff;
    box-shadow: 0 10px 28px rgba(14, 47, 41, 0.2);
}

.profile-avatar-placeholder {
    width: 100%;
    height: 100%;
    display: grid;
    place-items: center;
    color: white;
    font-size: 36px;
    background: linear-gradient(145deg, #2d947c 0%, #1d6454 100%);
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

.details-success {
    color: #1b8c64;
}

.details-danger {
    color: var(--danger-600);
}

/* Table Styles */
.records-section {
    margin-top: 16px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.section-header h4 {
    margin: 0;
    color: var(--ink-900);
    font-weight: 700;
    font-size: 0.95rem;
}

.records-count {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--ink-700);
    background: #edf7f4;
    border: 1px solid #d6e8e2;
    border-radius: 999px;
    padding: 4px 9px;
}

.table-wrapper {
    background: #fff;
    border-radius: 12px;
    border: 1px solid #deebe7;
    overflow: hidden;
    margin-bottom: 1rem;
}

.table-responsive {
    overflow-x: auto;
}

.modern-table {
    width: 100%;
    border-collapse: collapse;
}

.modern-table thead tr {
    background: #f6fcfa;
    border-bottom: 1px solid #e6f0ed;
}

.modern-table thead th {
    padding: 9px 10px;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--ink-700);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    white-space: nowrap;
}

.modern-table tbody td {
    padding: 9px 10px;
    border-bottom: 1px solid #e6f0ed;
    color: var(--ink-900);
    font-size: 0.8rem;
}

.modern-table tbody tr:last-child td {
    border-bottom: none;
}

.table-row {
    transition: background 0.2s ease;
}

.table-row:hover {
    background: #f9fdfb;
}

.status-badge {
    display: inline-block;
    padding: 0.25rem 0.6rem;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: capitalize;
}

.status-badge.completed,
.status-badge.approved {
    background: #dcf6eb;
    color: #157856;
}

.status-badge.pending,
.status-badge.warning {
    background: #fff1d8;
    color: #9a6b19;
}

.status-badge.rejected,
.status-badge.cancelled,
.status-badge.danger,
.status-badge.disapproved {
    background: #ffe4e0;
    color: #b04740;
}

.amount {
    font-weight: 700;
}

.amount.returned {
    color: var(--danger-600);
}

.amount.replaced {
    color: #1b8c64;
}

.amount.loss {
    color: var(--warn-600);
}

.po-number {
    display: block;
    font-weight: 600;
}

.date-cell {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    color: var(--ink-700);
    font-size: 0.76rem;
}

.date-cell i {
    color: #1f826b;
}

.empty-state,
.loading-state {
    text-align: center;
    padding: 1.5rem;
    color: var(--ink-500);
}

.empty-state i,
.loading-state i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    display: block;
}

.pagination-wrapper {
    display: flex;
    justify-content: flex-end;
}

/* Loan Overview Styles */
.loan-overview {
    display: grid;
    grid-template-columns: 200px minmax(0, 1fr);
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

/* Responsive */
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

    .details-stat-grid {
        grid-template-columns: 1fr;
    }
}
</style>
