<template>
    <div class="emp-profile-container">
        <div>
            <div class="d-flex gap-2 justify-content-end">
                <button class="emp-create-btn" @click="backToList">
                    <i class="ri-arrow-left-line"></i>
                    Back to List
                </button>
                <button @click="openEdit(supplier)" variant="info" v-b-tooltip.hover title="Edit"
                    class="emp-create-btn">
                    <i class="ri-pencil-fill"></i>
                    Edit
                </button>
            </div>
        </div>

        <div class="row mt-4">
            <!-- Left Column - Basic Information -->
            <div class="col-md-4">
                <!-- Contact Information Card -->
                <div class="emp-info-card">
                    <div class="emp-info-card-header">
                        <i class="ri-user-line"></i>
                        <h3>Supplier Information</h3>
                    </div>
                    <div class="emp-info-card-body">
                    <div class="emp-profile-title">
                        <h1>{{ supplier.name || 'Supplier Name' }}</h1>
                        <div class="emp-profile-badges">
                            <span class="emp-badge"
                                :class="supplier.is_active === 1 ? 'emp-badge-success' : 'emp-badge-danger'">
                                {{ supplier.is_active === 1 ? 'Active' : 'Inactive' }}
                            </span>
                            <span v-if="supplier.is_blacklisted === 1" class="emp-badge emp-badge-dark">
                                Blacklisted
                            </span>
                        </div>
                    </div>
                    </div>
                    <div class="emp-info-card-body">
                        <div class="emp-info-row">
                            <div class="emp-info-label">Email</div>
                            <div class="emp-info-value">
                                <i class="ri-mail-line"></i>
                                {{ supplier.email || 'No email' }}
                            </div>
                        </div>
                        <div class="emp-info-row">
                            <div class="emp-info-label">Contact Person</div>
                            <div class="emp-info-value">
                                <i class="ri-user-line"></i>
                                {{ supplier.contact_person || '-' }}
                            </div>
                        </div>
                        <div class="emp-info-row">
                            <div class="emp-info-label">Contact Number</div>
                            <div class="emp-info-value">
                                <i class="ri-phone-line"></i>
                                {{ supplier.contact_number || 'No contact number' }}
                            </div>
                        </div>
                        <div class="emp-info-row">
                            <div class="emp-info-label">Address</div>
                            <div class="emp-info-value">
                                <i class="ri-map-pin-line"></i>
                                {{ supplier.address || 'No address' }}
                            </div>
                        </div>
                        <div class="emp-info-row">
                            <div class="emp-info-label">TIN</div>
                            <div class="emp-info-value">
                                <i class="ri-file-list-3-line"></i>
                                {{ supplier.tin || '-' }}
                            </div>
                        </div>
                        <div class="emp-info-row">
                            <div class="emp-info-label">Created At</div>
                            <div class="emp-info-value">{{ formatDate(supplier.created_at) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Statistics & Records -->
            <div class="col-md-8">
                <!-- Purchase Orders Section -->
                <div class="emp-incentives-section">
                    <div class="emp-incentives-card">
                        <div class="emp-incentives-header">
                            <h3>
                                <i class="ri-shopping-cart-line"></i>
                                Purchase Orders
                            </h3>
                        </div>
                        <div class="emp-incentives-stats">
                            <div class="emp-incentives-stat">
                                <div class="emp-stat-label">Total Orders</div>
                                <div class="emp-stat-value">{{ purchaseOrderSummary.total_orders }}</div>
                            </div>
                            <div class="emp-incentives-stat">
                                <div class="emp-stat-label">Pending</div>
                                <div class="emp-stat-value">{{ purchaseOrderSummary.pending_orders }}</div>
                            </div>
                            <div class="emp-incentives-stat">
                                <div class="emp-stat-label">Completed</div>
                                <div class="emp-stat-value">{{ purchaseOrderSummary.completed_orders }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="emp-incentives-section mt-2">
                    <div class="stock-return-card">
                        <div class="emp-incentives-header">
                            <h3>
                                <i class="ri-shopping-cart-line"></i>
                                Stock Return Summary
                            </h3>
                        </div>

                        <!-- Stats Cards -->
                        <div class="stats-grid">
                            <div class="stat-card returned">
                            <div class="stat-icon">
                                <i class="ri-arrow-left-circle-line"></i>
                            </div>
                            <div class="stat-content">
                                <span class="stat-label">Returned</span>
                                <span class="stat-value">{{ stockReturnSummary.total_returned }}</span>
                            </div>
                            </div>
                            
                            <div class="stat-card replaced">
                            <div class="stat-icon">
                                <i class="ri-refresh-line"></i>
                            </div>
                            <div class="stat-content">
                                <span class="stat-label">Replaced</span>
                                <span class="stat-value">{{ stockReturnSummary.total_replaced }}</span>
                            </div>
                            </div>
                            
                            <div class="stat-card loss">
                            <div class="stat-icon">
                                <i class="ri-delete-back-line"></i>
                            </div>
                            <div class="stat-content">
                                <span class="stat-label">Loss</span>
                                <span class="stat-value">{{ stockReturnSummary.total_loss }}</span>
                            </div>
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
                                    <th style="width: 15%">PO #</th>
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
                                    <td colspan="7">
                                        <div class="empty-state">
                                        <i class="ri-inbox-line"></i>
                                        <p>No stock return records found</p>
                                        </div>
                                    </td>
                                    </tr>
                                    
                                    <!-- Loading State -->
                                    <tr v-if="stockReturnsLoading">
                                    <td colspan="7">
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
                </div>


                <!-- Recent Activity Section -->
                <div class="emp-stats-section mt-2">
                    <div class="emp-section-header">
                        <h2 class="emp-section-title">Recent Activity</h2>
                    </div>

                    <div class="emp-loan-summary-card">
                        <div class="emp-loan-main-header">
                            <div class="emp-loan-icon">
                                <i class="ri-history-line"></i>
                            </div>
                            <div class="emp-loan-title-section">
                                <h3 class="emp-loan-title">Supplier History</h3>
                                <div class="emp-loan-period">Record Timeline</div>
                            </div>
                        </div>

                        <div class="emp-loan-main-stats">
                            <div class="emp-primary-stat">
                                <div class="emp-stat-number">{{ supplier.id || 'N/A' }}</div>
                                <div class="emp-stat-label">Supplier ID</div>
                            </div>

                            <div class="emp-progress-section">
                                <div class="emp-progress-header">
                                    <span>Status</span>
                                    <span class="emp-progress-percentage" :class="supplier.is_active === 1 ? 'text-success' : 'text-danger'">
                                        {{ supplier.is_active === 1 ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                                <div class="emp-progress-bar">
                                    <div class="emp-progress-fill" :style="{ width: supplier.is_active === 1 ? '100%' : '0%' }"></div>
                                </div>
                            </div>
                        </div>

                        <div class="emp-loan-footer">
                            <div class="emp-footer-details">
                                <div class="emp-footer-detail">
                                    <span class="emp-footer-label">Last Updated</span>
                                    <span class="emp-footer-value">{{ formatDate(supplier.updated_at) }}</span>
                                </div>
                                <div class="emp-footer-detail">
                                    <span class="emp-footer-label">Created</span>
                                    <span class="emp-footer-value">{{ formatDate(supplier.created_at) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <Create @add="fetch()" @update="fetch()" :dropdowns="dropdowns" ref="create" />
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
                this.fetchPurchaseOrderSummary();
                this.fetchStockReturnSummary();
                this.fetchStockReturns();
            },
            immediate: true,
        },
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
            if (!this.supplier?.id) return;

            axios.get(`/suppliers/${this.supplier.id}/purchase-order-summary`)
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
            if (!this.supplier?.id) return;

            axios.get(`/suppliers/${this.supplier.id}/stock-return-summary`)
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
            if (!this.supplier?.id) {
                this.stockReturns = [];
                this.stockReturnsMeta = null;
                this.stockReturnsLinks = null;
                return;
            }

            page_url = page_url || `/suppliers/${this.supplier.id}/stock-returns`;
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
.text-success {
    color: #28a745 !important;
}

.text-danger {
    color: #dc3545 !important;
}
.stock-return-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

/* Header */
.stock-return-header {
  background: #c4dad2;
  border-bottom: 3px solid #c4dad2;
}

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  padding: 1.5rem 2rem;
  background: #f8faf9;
  border-bottom: 2px solid #eef2f6;
}

.stat-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.25rem;
  background: white;
  border-radius: 18px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
  transition: all 0.3s ease;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(61, 141, 122, 0.12);
}

.stat-card.returned .stat-icon {
  background: #c4dad2;
  color: #3d8d7a;
}

.stat-card.replaced .stat-icon {
  background: #fff3e0;
  color: #f39c12;
}

.stat-card.loss .stat-icon {
  background: #fee9e7;
  color: #e74c3c;
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
}

.stat-content {
  flex: 1;
}

.stat-label {
  display: block;
  color: #6c757d;
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  margin-bottom: 0.25rem;
}

.stat-value {
  display: block;
  font-size: 1.5rem;
  font-weight: 700;
  color: #2c3e50;
  line-height: 1.2;
}

/* Records Section */
.records-section {
  padding: 1.5rem 2rem;
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.5rem;
}

.section-header h4 {
  margin: 0;
  color: #2c3e50;
  font-weight: 600;
  font-size: 1.1rem;
}

.records-count {
  background: #c4dad2;
  color: #2c6b5c;
  padding: 0.35rem 1rem;
  border-radius: 50px;
  font-size: 0.85rem;
  font-weight: 500;
}

/* Table Wrapper */
.table-wrapper {
  background: white;
  border-radius: 18px;
  border: 2px solid #eef2f6;
  overflow: hidden;
  margin-bottom: 1.5rem;
}

/* Modern Table */
.modern-table {
  width: 100%;
  border-collapse: collapse;
}

.modern-table thead tr {
  background: #f8faf9;
  border-bottom: 2px solid #c4dad2;
}

.modern-table thead th {
  padding: 1rem 1.5rem;
  font-size: 0.85rem;
  font-weight: 600;
  color: #3d8d7a;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  white-space: nowrap;
}

.modern-table tbody td {
  padding: 1rem 1.5rem;
  border-bottom: 1px solid #eef2f6;
  color: #2c3e50;
  font-size: 0.95rem;
}

.modern-table tbody tr:last-child td {
  border-bottom: none;
}

.table-row {
  transition: background 0.2s ease;
}

.table-row:hover {
  background: #f8faf9;
}

/* Table Elements */
.return-number {
  font-weight: 600;
  color: #3d8d7a;
  background: #c4dad2;
  padding: 0.35rem 1rem;
  border-radius: 50px;
  font-size: 0.9rem;
  display: inline-block;
}

.po-number {
  font-weight: 500;
  color: #2c3e50;
}

.status-badge {
  display: inline-block;
  padding: 0.35rem 1rem;
  border-radius: 50px;
  font-size: 0.85rem;
  font-weight: 500;
}

.status-badge.completed,
.status-badge.approved {
  background: #c4dad2;
  color: #2c6b5c;
}

.status-badge.pending {
  background: #fff3e0;
  color: #f39c12;
}

.status-badge.warning {
  background: #fff3e0;
  color: #f39c12;
}

.status-badge.rejected,
.status-badge.cancelled,
.status-badge.danger {
  background: #fee9e7;
  color: #e74c3c;
}

.amount {
  font-weight: 600;
}

.amount.returned {
  color: #3d8d7a;
}

.amount.replaced {
  color: #f39c12;
}

.amount.loss {
  color: #e74c3c;
}

.date-cell {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #6c757d;
  font-size: 0.9rem;
}

.date-cell i {
  color: #3d8d7a;
  font-size: 1rem;
}

/* Empty & Loading States */
.empty-state,
.loading-state {
  text-align: center;
  padding: 3rem;
  color: #adb5bd;
}

.empty-state i,
.loading-state i {
  font-size: 3rem;
  margin-bottom: 1rem;
  display: block;
}

.empty-state i {
  color: #c4dad2;
}

.loading-state i {
  color: #3d8d7a;
}

.empty-state p,
.loading-state p {
  margin: 0;
  font-size: 1rem;
}

/* Pagination Wrapper */
.pagination-wrapper {
  display: flex;
  justify-content: flex-end;
}

/* Responsive */
@media (max-width: 768px) {
  .stock-return-header {
    padding: 1.25rem;
  }
  
  .stats-grid {
    grid-template-columns: 1fr;
    padding: 1.25rem;
  }
  
  .records-section {
    padding: 1.25rem;
  }
  
  .modern-table thead th,
  .modern-table tbody td {
    padding: 0.75rem 1rem;
  }
  
  .return-number {
    padding: 0.25rem 0.75rem;
    font-size: 0.85rem;
  }
}
</style>
