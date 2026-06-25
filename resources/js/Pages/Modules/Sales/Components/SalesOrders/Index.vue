<template>
    <div>
        <div class="library-card">
            <div class="library-card-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="header-icon">
                            <i class="ri-shopping-cart-line"></i>
                        </div>
                        <div>
                            <h4 class="header-title mb-0">Sales Orders</h4>
                            <p class="header-subtitle mb-0">Manage and track all sales orders.</p>
                        </div>
                    </div>
                    <button class="acct-btn-primary" @click="openCreate">
                        <i class="ri-add-line me-1"></i>Create Order
                    </button>
            </div>
            <div class="library-card-body">
                   
                    <div class="search-section">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="search-wrapper">
                                    <i class="ri-search-line search-icon"></i>
                                    <input type="text"  v-model="filter.keyword" 
                                        placeholder="Search sales order..." class="search-input">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="search-wrapper">
                                    <i class="ri-map-pin-line search-icon"></i>
                                    <select v-model="filter.location_id" @change="fetch()" class="search-input">
                                        <option :value="null">All Locations</option>
                                        <option v-for="location in dropdowns.locations" :key="location.value" :value="location.value">
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
                                        <option v-for="status in dropdowns.sales_statuses" :key="status.value" :value="status.slug" >
                                            {{ status.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="table-responsive">
                        <table class="table sales-table mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Order Number</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th class="text-end">Total Amount</th>
                                    <th>Due Date</th>
                                    <th class="text-center">Paid %</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="fs-12">
                                <template v-for="(list, index) in lists" :key="list.id">
                                    <tr @click="toggleRowExpansion(index)"
                                        :class="{
                                            'expanded-row': expandedRow === index,
                                            'bg-danger bg-opacity-25': isDueSoon(list),
                                            'cursor-pointer': true
                                        }" 
                                        class="main-table-row transition-all"
                                        style="transition: all 0.3s ease;">
                                        <td class="text-center">
                                            <div class="expand-icon" :class="{ 'rotated': expandedRow === index }">
                                                <i class="ri-arrow-right-s-line"></i>
                                            </div>
                                            {{ index + 1 }}
                                        </td>
                                        <td class="text-center fw-semibold">{{ list.so_number }}</td>
                                        <td class="text-center">{{ list.customer?.name || '-' }}</td>
                                        <td class="text-center">{{ list.created_at }}</td>
                                        <td class="text-center">
                                            <span class="status-badge" :style="getStatusStyle(list.status)">
                                                <i v-if="list.status?.icon" :class="list.status.icon" class="me-1"></i>
                                                {{ list.status ? list.status.name : '' }}
                                            </span>
                                        </td>
                                          <!-- <td class="text-center">
                                            <span
                                                v-if="list.sub_status?.name"
                                                :style="{ backgroundColor: list.sub_status?.bg_color || '#6c757d', color: '#fff', padding: '4px 8px', borderRadius: '12px' }">
                                                {{ list.sub_status?.name  }}
                                            </span>
                                        </td> -->
                                        <td class="text-end fw-semibold">{{ formatCurrency(list.total_amount) }}</td>
                                        <td class="text-center">
                                            {{ list.due_date }}
                                            <span v-if="isDueSoon(list)" class="badge bg-danger ms-1">Due Soon</span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex align-items-center justify-content-center">
                                                <div class="progress" style="width: 60px; height: 8px; margin-right: 8px;">
                                                    <div class="progress-bar bg-success" role="progressbar" 
                                                         :style="{ width: calculatePercentagePaid(list) + '%' }" 
                                                         :aria-valuenow="calculatePercentagePaid(list)" 
                                                         aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                                <small class="text-muted">{{ calculatePercentagePaid(list) }}%</small>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-1">
                                                <button v-if="canApprove && list.status?.slug == 'for-payment'"
                                                    @click.stop="onApproval(list.id)"
                                                    class="action-btn success" title="Approve Order">
                                                    <i class="ri-check-line"></i>
                                                </button>
                                                <button v-if="list.status?.slug == 'for-payment'"
                                                    @click.stop="onSalesAdjustment(list)"
                                                    class="action-btn warn" title="Sales Adjustment">
                                                    <i class="ri-refund-line"></i>
                                                </button>
                                                <button @click.stop="onPrint(list.id)"
                                                    class="action-btn info" title="Print Invoice">
                                                    <i class="ri-printer-line"></i>
                                                </button>
                                                <button v-if="list.status?.slug == 'for-payment'"
                                                    @click.stop="openEdit(list, index)"
                                                    class="action-btn edit" title="Edit">
                                                    <i class="ri-pencil-fill"></i>
                                                </button>
                                                <button v-if="list.status?.slug !== 'cancelled'"
                                                    @click.stop="onCancel(list)"
                                                    class="action-btn delete" title="Cancel Order">
                                                    <i class="ri-close-line"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <!-- Expanded Details Row -->
                                <Transition name="details-row">
                                <tr v-if="expandedRow === index" class="details-row">
                                    <td colspan="9" class="p-0">
                                        <div class="details-container">
                                            <div class="details-content">
                                                <div class="row g-4">
                                                    <div class="col-md-6">
                                                        <div class="card border-0 shadow-sm ">
                                                            <div class="card-body">
                                                                <h6 class="card-title text-muted small mb-2">Order
                                                                    Information</h6>
                                                                <p class="mb-1"><strong>Order Date:</strong> {{
                                                                    list.order_date }}</p>
                                                                <p class="mb-1"><strong>Added By:</strong> {{
                                                                    list.added_by?.fullname || '-' }}</p>
                                                                <p class="mb-0"><strong>Transferred To:</strong> {{
                                                                    list.transferred_to || '-' }}</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="info-card items-card">
                                                            <div class="info-card-header">
                                                                <i class="ri-shopping-bag-line"></i>
                                                                <h6>Items</h6>
                                                            </div>
                                                            <div class="info-card-body">
                                                                <div v-if="list.items && list.items.length > 0">
                                                                    <table class="table table-sm table-borderless mb-0">
                                                                        <thead>
                                                                            <tr>
                                                                                <th class="fw-semibold">Product Name</th>
                                                                                <th class="fw-semibold">Quantity</th>
                                                                                <th class="fw-semibold">Price</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            <tr v-for="item in list.items" :key="item.id">
                                                                                <td>{{ getProduct(item.product_id).name || 'Unknown Product' }}</td>
                                                                                <td>
                                                                                    <span class="badge bg-primary">{{ item.quantity }} {{ item.unit }}</span>
                                                                                </td>
                                                                                <td>₱{{ item.price }}</td>
                                                                            </tr>
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                                <p v-else class="text-muted mb-0">No items found</p>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-12" v-if="list.invoices && list.invoices.some(inv => inv.receipts && inv.receipts.length > 0)">
                                                        <div class="info-card">
                                                            <div class="info-card-header">
                                                                <i class="ri-receipt-line"></i>
                                                                <h6>Payment History</h6>
                                                            </div>
                                                            <div class="info-card-body">
                                                                <table class="table table-sm table-borderless mb-0">
                                                                    <thead>
                                                                        <tr>
                                                                            <th class="fw-semibold">OR Number</th>
                                                                            <th class="fw-semibold">Date</th>
                                                                            <th class="fw-semibold">Amount Paid</th>
                                                                            <th class="fw-semibold">Mode</th>
                                                                            <th class="fw-semibold">Type</th>
                                                                            <th class="fw-semibold">Status</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <template v-for="inv in list.invoices" :key="inv.id">
                                                                            <tr v-for="receipt in inv.receipts" :key="receipt.id">
                                                                                <td class="fw-semibold">{{ receipt.receipt_number }}</td>
                                                                                <td>{{ receipt.receipt_date }}</td>
                                                                                <td>₱{{ receipt.amount_paid }}</td>
                                                                                <td>{{ receipt.payment_mode || '-' }}</td>
                                                                                <td>
                                                                                    <span class="badge" :class="getReceiptTypeBadge(receipt.receipt_type)">
                                                                                        {{ getReceiptTypeLabel(receipt.receipt_type) }}
                                                                                    </span>
                                                                                </td>
                                                                                <td>
                                                                                    <span v-if="receipt.status" class="badge" :style="{ backgroundColor: receipt.status.bg_color, color: receipt.status.text_color }">
                                                                                        {{ receipt.status.name }}
                                                                                    </span>
                                                                                </td>
                                                                            </tr>
                                                                        </template>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                </Transition>
                                </template>
                                <tr v-if="lists.length === 0">
                                    <td colspan="9">
                                        <div class="sales-empty-state">
                                            <i class="ri-shopping-cart-line"></i>
                                            <p class="mb-1">No sales orders found</p>
                                            <small>Try adjusting your search or filter.</small>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="px-3 pb-3">
                    <Pagination v-if="meta" @fetch="fetch" :lists="lists.length"
                        :links="links" :pagination="meta" />
                </div>
            </div>
    </div>
    <Create @add="fetch()" :dropdowns="dropdowns" :user="user" ref="create"/>
    <Cancel @cancel="fetch()" ref="cancel"/>
     <Approval @approve="fetch()" ref="approval"/>
    <Adjustment @update="fetch()" :dropdowns="dropdowns" ref="adjustment"/>

    
</template>
<script>
import _ from 'lodash';
import Multiselect from "@vueform/multiselect";
import PageHeader from '@/Shared/Components/PageHeader.vue';
import Pagination from "@/Shared/Components/Pagination.vue";
import Cancel from './Modals/Cancel.vue';
import Create from './Modals/Create.vue';
import Adjustment from './Modals/Adjustment.vue';
import Approval from './Modals/Approval.vue';


export default {
    components: { PageHeader, Pagination, Multiselect , Create, Cancel, Adjustment, Approval },
    props: ['dropdowns', 'invoices', 'user', 'isExternal'],
    data(){
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
            selectedRow: null,
            units: [],
            metrics: {
                total_sales_orders: 0,
                today_orders: 0,
                total_revenue: 0,
                pending_orders: 0,
                total_cancelled_orders: 0
            },
            expandedRow: null
        }
    },
    computed: {
        canApprove() {
            const roles = this.$page?.props?.roles || [];
            return ['Administrator', 'Area Business Manager', 'Super Admin'].some(r => roles.includes(r));
        },
    },
    watch: {
        "filter.keyword"(newVal) {
            this.checkSearchStr(newVal);
        }
    },
    created() {
        this.fetch();
        this.fetchMetrics();
    },
    methods: {
        getReceiptTypeLabel(type) {
            if (type === 'updated') return 'Adjusted Payment';
            if (type === 'refund') return 'Return Refund';
            return 'Payment';
        },
        getReceiptTypeBadge(type) {
            if (type === 'updated') return 'bg-info text-dark';
            if (type === 'refund') return 'bg-warning text-dark';
            return 'bg-primary';
        },
        checkSearchStr: _.debounce(function (string) {
            this.fetch();
        }, 300),
        fetch(page_url) {
            let baseUrl = this.isExternal ? '/sales-orders-external' : '/sales-orders';
            page_url = page_url || baseUrl;
            axios.get(page_url, {
                params: {
                    keyword: this.filter.keyword,
                    location_id: this.filter.location_id,
                    status: this.filter.status,
                    count: 10,
                    option: 'lists'
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
        openCreate() {
            this.$refs.create.show();
        },

        openEdit(data, index) {
            this.selectedRow = index;
            this.$refs.create.edit(data, index);
        },

        onCancel(list) {
            let title = "Sales Order";
            let url = '/sales-orders';
            const hasPayments = (list.invoices || []).some(inv => inv.amount_paid > 0);
            this.$refs.cancel.show(list.id, title, url, hasPayments);
        },

        onApproval(id) {
            let title = "Sales Order";
            let url = '/sales-orders';
            this.$refs.approval.show(id, title, url);
        },
        onPrint(id) {
            let url =  '/sales-orders';
            window.open(`${url}/${id}?option=print&type=sales_order`);
        },
    

        onSalesAdjustment(data) {
            this.$refs.adjustment.show(data?.id, this.isExternal, data?.items || []);
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
            axios.get('/sales-orders', {
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
        getStatusStyle(status) {
            if (!status) return {};

            return {
                color: status.text_color || '#000000',
                backgroundColor: status.bg_color || '#ffffff',
                border: `1px solid ${status.bg_color ? status.bg_color + '40' : '#cccccc'}`,
                boxShadow: `0 2px 4px ${status.bg_color ? status.bg_color + '20' : 'rgba(0,0,0,0.1)'}`
            };
        },

        formatCurrency(value) {
            if (!value) return '₱0.00';
            return '₱' + Number(value).toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        },


        getProduct(product_id) {
            const product = this.dropdowns.products.find(u => u.value === product_id);
            return product ? product : [];
        },

        calculatePercentagePaid(list) {
            if (!list.total_amount || list.total_amount == 0) return 0;
            // Calculate total paid as total_amount minus the remaining balance_due
            const balanceDue = list.invoices && list.invoices.length > 0 ? list.invoices[0].balance_due || 0 : list.total_amount;
            const totalPaid = list.total_amount - balanceDue;
            return Math.min(Math.round((totalPaid / list.total_amount) * 100), 100);
        },

        isDueSoon(list) {
            if (!list.due_date) return false;
            const balanceDue = list.invoices && list.invoices.length > 0 ? Number(list.invoices[0].balance_due || 0) : Number(list.total_amount || 0);
            if (balanceDue <= 0) return false;
            const dueDate = new Date(list.due_date);
            const today = new Date();
            const diffTime = dueDate - today;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            return diffDays <= 2 && diffDays >= 0;
        },
    }
}
</script>
<style scoped>
    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 0.5px;
        transition: all 0.3s ease;
        cursor: default;
    }

    /* Modern Collapsible Row Styles */
    .main-table-row {
        cursor: pointer;
        transition: all 0.2s ease;
        border-left: 3px solid transparent;
    }

    .main-table-row:hover {
        background-color: rgba(61, 141, 122, 0.05) !important;
        border-left-color: #3D8D7A;
    }

    .main-table-row.expanded-row {
        background: linear-gradient(90deg, rgba(61, 141, 122, 0.08) 0%, rgba(61, 141, 122, 0.02) 100%);
        border-left-color: #3D8D7A;
    }

    .expand-icon {
        display: inline-block;
        margin-right: 8px;
        transition: transform 0.3s ease;
        color: #6c757d;
    }

    .expand-icon i {
        font-size: 18px;
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

    .details-content {
        padding: 1.5rem 2rem;
    }

    /* Expand / collapse transitions */
    .details-row-enter-active,
    .details-row-leave-active {
        transition: opacity 0.25s ease, transform 0.25s ease;
    }

    .details-row-enter-from,
    .details-row-leave-to {
        opacity: 0;
        transform: translateY(-8px);
    }

    .details-row-enter-to,
    .details-row-leave-from {
        opacity: 1;
        transform: translateY(0);
    }

    /* Info Card Styles */
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
    }
</style>
