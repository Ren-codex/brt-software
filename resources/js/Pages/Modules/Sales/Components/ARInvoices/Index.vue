<template>
    <BRow>
        <div class="col-lg-12 mb-4">
            <div class="library-card">
                <div class="library-card-header">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="header-icon">
                                <i class="ri-shopping-cart-line fs-24"></i>
                            </div>
                            <div>
                                <h4 class="header-title mb-1">Account Receivable Invoices</h4>
                                <p class="header-subtitle mb-0">A comprehensive Account Receivable Invoices</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body bg-white m-2 p-3">
                    <div class="search-section">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="search-wrapper">
                                    <i class="ri-search-line search-icon"></i>
                                    <input type="text" v-model="filter.keyword" @input="debouncedSearch"
                                        placeholder="Search AR invoice..." class="search-input">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="search-wrapper">
                                    <i class="ri-map-pin-line search-icon"></i>
                                    <select v-model="filter.location_id" @change="fetch()" class="search-input">
                                        <option :value="null">All Locations</option>
                                        <option v-for="location in dropdowns.locations" :key="location.value"
                                            :value="location.value">
                                            {{ location.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="search-wrapper">
                                    <i class="ri-flag-line search-icon"></i>
                                    <select v-model="filter.status" @change="fetch()" class="search-input">
                                        <option :value="null">All Status</option>
                                        <option v-for="status in dropdowns.sales_statuses" :key="status.value"
                                            :value="status.slug">
                                            {{ status.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive table-card">
                        <table class="table align-middle table-hover mb-0"
                            style="border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                            <thead style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                                <tr class="fs-12 fw-bold text-muted">
                                    <th style="width: 3%; border: none;">#</th>
                                    <th style="width: 12%;" class="text-center border-none">Invoice Number</th>
                                    <th style="width: 12%;" class="text-center border-none">Sales Order</th>
                                    <th style="width: 12%;" class="text-center border-none">Customer</th>
                                    <th style="width: 12%;" class="text-center border-none">Invoice Date</th>
                                    <th style="width: 12%;" class="text-center border-none">Status</th>
                                    <th style="width: 12%;" class="text-center border-none">Balance Due</th>
                                    <th style="width: 12%;" class="text-center border-none">Amount Paid</th>
                                    <th style="width: 12%;" class="text-center border-none">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="fs-12">
                                <template v-for="(list, index) in lists" :key="index">
                                    <tr @click="toggleRowExpansion(index)" :class="{
                                        'expanded-row': expandedRow === index,
                                        'overdue-row': isOverdue(list),
                                        'due-soon-row': isDueSoon(list),
                                        'main-table-row': true
                                    }" class="transition-all" style="transition: all 0.3s ease;">
                                        <td class="text-center">
                                            <button type="button" class="collapse-btn"
                                                @click.stop="toggleRowExpansion(index)">
                                                <div class="expand-icon" :class="{ rotated: expandedRow === index }">
                                                    <i class="ri-arrow-right-s-line"></i>
                                                </div>
                                            </button>
                                            {{ index + 1 }}
                                        </td>
                                        <td class="text-center fw-semibold">{{ list.invoice_number }}</td>
                                        <td class="text-center">{{ list.sales_order?.so_number || '-' }}</td>
                                        <td class="text-center">{{ list.sales_order?.customer?.name || '-' }}</td>
                                        <td class="text-center">{{ list.invoice_date }}</td>
                                        <td class="text-center">
                                            <span class="status-badge" :style="getStatusStyle(list.status)">
                                                {{ list.status?.name || '-' }}
                                            </span>
                                            <span v-if="isOverdue(list)" class="overdue-badge ms-1">
                                                Past Due
                                            </span>
                                            <span v-if="isDueSoon(list)" class="due-soon-badge ms-1">
                                                Due Soon
                                            </span>
                                        </td>
                                        <td class="text-center">₱{{ list.balance_due?.toFixed(2) }}</td>
                                        <td class="text-center">₱{{ list.amount_paid?.toFixed(2) }}</td>
                                        <td class="text-center">
                                            <div class="action-buttons-row">
                                                <b-button
                                                    @click.stop="onViewReceipts(list)"
                                                    variant="success"
                                                    v-b-tooltip.hover
                                                    title="View Receipts"
                                                    size="sm"
                                                    class="btn-icon"
                                                >
                                                    <i class="ri-file-3-line"></i>
                                                </b-button>
                                                <b-button @click.stop="onPrint(list.id)" variant="info"
                                                    v-b-tooltip.hover title="Print" size="sm"
                                                    class="btn-icon">
                                                    <i class="ri-printer-line"></i>
                                                </b-button>
                                                <b-button
                                                    v-if="(list.status?.slug == 'unpaid' || list.status?.slug == 'partially_paid' || list.balance_due > 0) && (list.sales_order?.status?.slug != 'cancelled' && list.sales_order?.status?.slug != 'sales-returned')"
                                                    @click.stop="onPayment(list)" variant="primary"
                                                    v-b-tooltip.hover title="Payment" size="sm"
                                                    class="btn-icon">
                                                    <i class="ri-money-dollar-circle-fill"></i>
                                                </b-button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="expandedRow === index" class="details-row">
                                        <td colspan="12">
                                            <div class="details-container">
                                                <div class="details-content">
                                                    <div class="collapse-actions">
                                                        <b-button
                                                            @click.stop="onViewReceipts(list)"
                                                            variant="success"
                                                            size="sm"
                                                            class="system-action-btn system-action-success"
                                                        >
                                                            <i class="ri-file-3-line me-1"></i>
                                                            View Receipts
                                                        </b-button>
                                                        <b-button
                                                            @click.stop="onPrint(list.id)"
                                                            variant="info"
                                                            size="sm"
                                                            class="system-action-btn system-action-info"
                                                        >
                                                            <i class="ri-printer-line me-1"></i>
                                                            Print Invoice
                                                        </b-button>
                                                        <b-button
                                                            v-if="(list.status?.slug == 'unpaid' || list.status?.slug == 'partially_paid' || list.balance_due > 0) && (list.sales_order?.status?.slug != 'cancelled' && list.sales_order?.status?.slug != 'sales-returned')"
                                                            @click.stop="onPayment(list)"
                                                            variant="primary"
                                                            size="sm"
                                                            class="system-action-btn system-action-primary"
                                                        >
                                                            <i class="ri-money-dollar-circle-fill me-1"></i>
                                                            Record Payment
                                                        </b-button>
                                                    </div>
                                                <h6 class="text-primary mb-3">
                                                    <i class="ri-file-list-line me-2"></i>Invoice Details
                                                </h6>
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <div class="info-card invoice-card">
                                                            <div class="info-card-header">
                                                                <i class="ri-file-list-line"></i>
                                                                <h6>Invoice Information</h6>
                                                            </div>
                                                            <div class="info-card-body">
                                                                <div class="info-item">
                                                                    <span class="info-label">Invoice Date:</span>
                                                                    <span class="info-value">{{ list.invoice_date
                                                                        }}</span>
                                                                </div>
                                                                <div class="info-item">
                                                                    <span class="info-label">Amount Balance:</span>
                                                                    <span class="info-value amount">₱{{
                                                                        list.balance_due?.toFixed(2) }}</span>
                                                                </div>
                                                                <div class="info-item">
                                                                    <span class="info-label">Amount Paid:</span>
                                                                    <span class="info-value amount paid">₱{{
                                                                        list.amount_paid?.toFixed(2) }}</span>
                                                                </div>
                                                                <div class="info-item highlight">
                                                                    <span class="info-label">Balance Due:</span>
                                                                    <span class="info-value amount due">₱{{
                                                                        list.balance_due?.toFixed(2) }}</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="info-card order-card">
                                                            <div class="info-card-header">
                                                                <i class="ri-shopping-bag-line"></i>
                                                                <h6>Sales Order Details</h6>
                                                            </div>
                                                            <div class="info-card-body">
                                                                <div class="info-item">
                                                                    <span class="info-label">Sales Order:</span>
                                                                    <span class="info-value">{{
                                                                        list.sales_order?.so_number || '-' }}</span>
                                                                </div>
                                                                <div class="info-item">
                                                                    <span class="info-label">Customer:</span>
                                                                    <span class="info-value">{{
                                                                        list.sales_order?.customer?.name || '-'
                                                                        }}</span>
                                                                </div>
                                                                <div class="info-item">
                                                                    <span class="info-label">Order Date:</span>
                                                                    <span class="info-value">{{
                                                                        list.sales_order?.order_date || '-' }}</span>
                                                                </div>
                                                                <div v-if="list.sales_order?.status" class="info-item">
                                                                    <span class="info-label">Order Status:</span>
                                                                    <span class="info-value">
                                                                        <span class="status-badge"
                                                                            :style="getStatusStyle(list.sales_order.status)">
                                                                            {{ list.sales_order.status?.name }}
                                                                        </span>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                                <tr v-if="lists.length === 0">
                                    <td colspan="9" class="text-center py-4">
                                        <i class="ri-inbox-line text-muted" style="font-size: 3rem;"></i>
                                        <p class="mt-2 mb-0">No invoice found</p>
                                        <small class="text-muted">Try changing your search or filter criteria</small>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light border-0 mt-3">
                    <Pagination class="ms-2 me-2 mt-n1" v-if="meta" @fetch="fetch()" :lists="lists.length"
                        :links="links" :pagination="meta" />
                </div>
            </div>
        </div>

    </BRow>
    <Payment @approve="fetch()" ref="payment" />
    <ReceiptsList ref="receiptsList" />
</template>

<script>
import _ from 'lodash';
import Multiselect from "@vueform/multiselect";
import PageHeader from '@/Shared/Components/PageHeader.vue';
import Pagination from "@/Shared/Components/Pagination.vue";
import Payment from '../ARInvoices/Modals/Payment.vue';
import ReceiptsList from '../ARInvoices/Modals/ReceiptsList.vue';

export default {
    components: { PageHeader, Pagination, Multiselect, Payment, ReceiptsList },
    props: ['dropdowns', 'isExternal'],
    data() {
        return {
            currentUrl: window.location.origin,
            lists: [],
            meta: {},
            links: {},
            filter: {
                keyword: null,
                location_id: null,
                status: null
            },
            index: null,
            expandedRow: null, // Changed from expandedRows array to single value
            units: [],
            metrics: {
                total_invoices: 0,
                outstanding_balance: 0,
                paid_invoices: 0,
                pending_invoices: 0,
                today_invoices: 0
            },
            stock: {
                products: []
            },
            showStock: false
        }
    },
    computed: {
        groupedProducts() {
            if (!this.stock.products || this.stock.products.length === 0) {
                return [];
            }

            const grouped = {};
            this.stock.products.forEach(product => {
                const brand = product.brand_name || 'No Brand';
                if (!grouped[brand]) {
                    grouped[brand] = {
                        brand: brand,
                        products: []
                    };
                }
                grouped[brand].products.push(product);
            });

            return Object.values(grouped);
        }
    },
    watch: {
        "filter.keyword"(newVal) {
            this.checkSearchStr(newVal);
        }
    },
    created() {
        this.fetch();
        this.fetchMetrics();
        this.fetchStock();
    },
    methods: {
        checkSearchStr: _.debounce(function (string) {
            this.fetch();
        }, 300),

        fetch(page_url) {
            page_url = page_url || '/ar-invoices';
            axios.get(page_url, {
                params: {
                    option: 'lists',
                    keyword: this.filter.keyword,
                    location_id: this.filter.location_id,
                    status: this.filter.status,
                    count: 10,
                    is_external: this.isExternal ? 1 : 0
                }
            })
                .then(response => {
                    if (response) {
                        this.lists = response.data.data;
                        this.meta = response.data.meta;
                        this.links = response.data.links;
                        this.expandedRow = null; // Reset expanded row when data changes
                    }
                })
                .catch(err => console.log(err));
        },

        onPayment(data) {
            let title = "Record Payment";
            this.$refs.payment.show(data, title, '/ar-invoices');
        },
        onViewReceipts(invoice){
            this.$refs.receiptsList.show(invoice);
        },

        onPrint(id) {
            window.open(`/ar-invoices/${id}?option=print&type=ar_invoice`);
        },

        selectRow(index) {
            if (this.selectedRow === index) {
                this.selectedRow = null;
            } else {
                this.selectedRow = index;
            }
        },

        toggleRowExpansion(index) {
            // Toggle between opening and closing, only one row open at a time
            if (this.expandedRow === index) {
                this.expandedRow = null; // Close if clicking the same row
            } else {
                this.expandedRow = index; // Open new row, closing any previously opened one
            }
        },

        fetchMetrics() {
            axios.get('/ar-invoices', {
                params: {
                    option: 'dashboard'
                }
            })
                .then(response => {
                    if (response) {
                        this.metrics = response.data;
                    }
                })
                .catch(err => console.log(err));
        },

        fetchStock() {
            axios.get('/ar-invoices', {
                params: {
                    option: 'stock'
                }
            })
                .then(response => {
                    if (response) {
                        this.stock = response.data;
                    }
                })
                .catch(err => console.log(err));
        },

        getStockPercentage(quantity) {
            const maxStock = Math.max(...this.stock.products.map(p => p.total_quantity));
            return Math.min((quantity / maxStock) * 100, 100);
        },

        isDueSoon(list) {
            const dueDateValue = list?.sales_order?.due_date;
            if (!dueDateValue || Number(list?.balance_due || 0) <= 0) return false;

            const dueDate = new Date(dueDateValue);
            if (Number.isNaN(dueDate.getTime())) return false;

            const today = new Date();
            today.setHours(0, 0, 0, 0);
            dueDate.setHours(0, 0, 0, 0);

            const diffTime = dueDate.getTime() - today.getTime();
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            return diffDays <= 2 && diffDays >= 0;
        },

        isOverdue(list) {
            const dueDateValue = list?.sales_order?.due_date;
            if (!dueDateValue || Number(list?.balance_due || 0) <= 0) return false;

            const dueDate = new Date(dueDateValue);
            if (Number.isNaN(dueDate.getTime())) return false;

            const today = new Date();
            today.setHours(0, 0, 0, 0);
            dueDate.setHours(0, 0, 0, 0);

            return dueDate.getTime() < today.getTime();
        },

        getStatusStyle(status) {
            if (!status) {
                return {
                    color: '#6c757d',
                    backgroundColor: '#e2e3e5',
                    border: '1px solid #cccccc'
                };
            }

            return {
                color: status.text_color || '#000000',
                backgroundColor: status.bg_color || '#ffffff',
                border: `1px solid ${status.bg_color ? status.bg_color + '40' : '#cccccc'}`,
                boxShadow: `0 2px 4px ${status.bg_color ? status.bg_color + '20' : 'rgba(0,0,0,0.1)'}`
            };
        }
    }
}
</script>

<style scoped>
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 14px;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.2px;
    line-height: 1.2;
    transition: all 0.3s ease;
    cursor: default;
}

/* Modern Collapsible Row Styles */
.main-table-row {
    cursor: pointer;
    transition: all 0.2s ease;
    border-left: 3px solid transparent;
}

.main-table-row td {
    padding-top: 0.55rem;
    padding-bottom: 0.55rem;
    vertical-align: middle;
}

.main-table-row:hover {
    background-color: rgba(61, 141, 122, 0.05) !important;
    border-left-color: #3D8D7A;
}

.main-table-row.expanded-row {
    background: linear-gradient(90deg, rgba(61, 141, 122, 0.08) 0%, rgba(61, 141, 122, 0.02) 100%);
    border-left-color: #3D8D7A;
}

.main-table-row.overdue-row {
    background: rgba(239, 68, 68, 0.14);
    border-left-color: #dc2626;
}

.main-table-row.overdue-row:hover {
    background: rgba(239, 68, 68, 0.2) !important;
    border-left-color: #b91c1c;
}

.main-table-row.due-soon-row {
    background: rgba(244, 114, 182, 0.12);
    border-left-color: #ec4899;
}

.main-table-row.due-soon-row:hover {
    background: rgba(244, 114, 182, 0.18) !important;
    border-left-color: #db2777;
}

.collapse-btn {
    width: 24px;
    height: 24px;
    border: none;
    border-radius: 50%;
    background: transparent;
    color: #2e8b57;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.collapse-btn:hover {
    background: rgba(61, 141, 122, 0.08);
}

.expand-icon {
    display: inline-block;
    transition: transform 0.3s ease;
    color: #6c757d;
}

.expand-icon i {
    font-size: 15px;
    vertical-align: middle;
}

.expand-icon.rotated {
    transform: rotate(90deg);
    color: #3D8D7A;
}

/* Details Row Styles */
.details-row {
    background-color: #f8fafd;
    border-bottom: 2px solid #e9ecef;
}

.details-row td {
    padding: 0 !important;
    border-top: none !important;
}

.details-container {
    animation: slideDown 0.3s ease-out;
}

.details-content {
    padding: 1.5rem 2rem;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Payment Modal Design - Matching Styles */
.info-card {
    background: white;
    border-radius: 12px;
    padding: 0;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
    height: 100%;
    overflow: hidden;
}

.info-card:hover {
    box-shadow: 0 8px 25px rgba(61, 141, 122, 0.15);
    transform: translateY(-2px);
    border-color: #3D8D7A;
}

.info-card-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #e9ecef;
    background: #f9fafb;
}

.info-card-header i {
    font-size: 1.25rem;
    color: #3D8D7A;
    background: rgba(61, 141, 122, 0.1);
    padding: 0.5rem;
    border-radius: 8px;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.info-card-header h6 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 600;
    color: #267A4C;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-card-body {
    display: flex;
    flex-direction: column;
    gap: 0;
    padding: 0.5rem 1.25rem 1.25rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px dashed #e9ecef;
}

.info-item:last-child {
    border-bottom: none;
}

.info-item.highlight {
    background: linear-gradient(135deg, rgba(61, 141, 122, 0.08) 0%, rgba(38, 122, 76, 0.05) 100%);
    padding: 1rem;
    border-radius: 10px;
    margin-top: 0.5rem;
    border: none;
    box-shadow: 0 2px 8px rgba(61, 141, 122, 0.1);
}

.info-label {
    color: #6c757d;
    font-size: 0.85rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-label::before {
    content: '';
    width: 6px;
    height: 6px;
    background: #C4DAD2;
    border-radius: 50%;
}

.info-value {
    color: #2b3459;
    font-weight: 600;
    font-size: 0.9rem;
}

.info-value.amount {
    font-family: 'Courier New', monospace;
    font-size: 0.95rem;
    font-weight: 700;
}

.info-value.amount.paid {
    color: #2e8b57;
}

.info-value.amount.due {
    color: #e74c3c;
    font-size: 1rem;
}

/* Custom badge styles */
.badge {
    font-weight: 500;
    letter-spacing: 0.3px;
}

.btn-icon {
    width: 24px;
    height: 24px;
    min-width: 24px;
    min-height: 24px;
    padding: 0;
    border-radius: 7px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 3px 8px rgba(15, 23, 42, 0.08);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.btn-icon i {
    font-size: 12px;
    line-height: 1;
}

.btn-icon:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 12px rgba(15, 23, 42, 0.12);
}

.action-buttons-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    flex-wrap: nowrap;
    white-space: nowrap;
}

.collapse-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e2e8f0;
    flex-wrap: wrap;
}

.system-action-btn {
    border: none;
    border-radius: 10px;
    padding: 0.65rem 1rem;
    font-size: 0.85rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08);
    transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
}

.system-action-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 20px rgba(15, 23, 42, 0.12);
}

.system-action-success {
    background: linear-gradient(135deg, #2e8b57 0%, #1f6b41 100%);
    color: #ffffff;
}

.system-action-primary {
    background: linear-gradient(135deg, #3d8d7a 0%, #267a4c 100%);
    color: #ffffff;
}

.system-action-info {
    background: linear-gradient(135deg, #0ea5e9 0%, #0369a1 100%);
    color: #ffffff;
}

.due-soon-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 7px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    color: #9d174d;
    background: rgba(244, 114, 182, 0.16);
    border: 1px solid rgba(236, 72, 153, 0.28);
    line-height: 1.2;
    white-space: nowrap;
}

.overdue-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 7px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    color: #991b1b;
    background: rgba(239, 68, 68, 0.16);
    border: 1px solid rgba(220, 38, 38, 0.28);
    line-height: 1.2;
    white-space: nowrap;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .details-content {
        padding: 1rem;
    }

    .info-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }

    .info-value {
        width: 100%;
    }

    .collapse-actions {
        justify-content: stretch;
    }

    .system-action-btn {
        width: 100%;
    }
}
</style>
