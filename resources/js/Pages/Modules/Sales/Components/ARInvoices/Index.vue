<template>
    <BRow>
        <div class="col-lg-9 mb-4">
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
                        <!-- <button class="create-btn" @click="openCreate">
                            <i class="ri-add-line"></i>
                            <span>Create Invoice</span>
                        </button> -->
                    </div>

                </div>

      
                <div class="card-body bg-white m-2 p-3">
                    <div class="search-section">
                        <div class="search-wrapper">
                            <i class="ri-search-line search-icon"></i>
                            <input type="text" v-model="filter.keyword" @input="debouncedSearch"
                                placeholder="Search purchase request..." class="search-input">
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
                                    <th style="width: 6%;" class="text-center border-none">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="fs-12">
                                <template v-for="(list, index) in lists" :key="index">
                                    <tr @click="toggleRowExpansion(index)" :class="{
                                        'bg-primary bg-opacity-10': index === selectedRow,
                                        'cursor-pointer': true
                                    }" class="transition-all" style="transition: all 0.3s ease;">
                                        <td class="text-center">
                                            <i v-if="expandedRows.includes(index)"
                                                class="ri-arrow-down-s-line text-primary"></i>
                                            <i v-else class="ri-arrow-right-s-line text-muted"></i>
                                            {{ index + 1 }}
                                        </td>
                                        <td class="text-center fw-semibold">{{ list.invoice_number }}</td>
                                        <td class="text-center">{{ list.sales_order?.so_number || '-' }}</td>
                                        <td class="text-center">{{ list.sales_order?.customer?.name || '-' }}</td>
                                        <td class="text-center">{{ list.invoice_date }}</td>
                                        <td class="text-center">
                                            <b-badge
                                                :style="{ 'background-color': list.status?.bg_color, color: '#fff' }"
                                                class="px-3 py-2 rounded-pill">
                                                {{ list.status?.name }}
                                            </b-badge>
                                        </td>
                                        <td class="text-center">₱{{ list.balance_due?.toFixed(2) }}</td>
                                        <td class="text-center">₱{{ list.amount_paid?.toFixed(2) }}</td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-1">
                                                <b-button @click.stop="onPrint(list.id)" variant="outline-info" v-b-tooltip.hover title="Print" size="sm" class="btn-icon rounded-circle">
                                                    <i class="ri-printer-line"></i>
                                                </b-button>
                                                <b-button v-if="list.status?.slug == 'unpaid' || list.status?.slug == 'partially_paid' || list.balance_due > 0" @click.stop="onPayment(list)" variant="outline-primary" v-b-tooltip.hover title="Payment" size="sm" class="btn-icon rounded-circle">
                                                    <i class="ri-money-dollar-circle-fill"></i>
                                                </b-button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="expandedRows.includes(index)" class="bg-light">
                                        <td colspan="8" class="p-0">
                                            <div class="p-4">
                                                <h6 class="text-primary mb-3">
                                                    <i class="ri-file-list-line me-2"></i>Invoice Details
                                                </h6>
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <div class="card border-0 shadow-sm bg-white">
                                                            <div class="card-body">
                                                                <h6 class="card-title text-muted small mb-2">Invoice Information</h6>
                                                                <p class="mb-1"><strong>Invoice Date:</strong> {{ list.invoice_date }}</p>
                                                                <p class="mb-1"><strong>Amount Balance:</strong> ₱{{ list.balance_due?.toFixed(2) }}</p>
                                                                <p class="mb-1"><strong>Amount Paid:</strong> ₱{{ list.amount_paid?.toFixed(2) }}</p>
                                                                <p class="mb-0"><strong>Balance Due:</strong> ₱{{ list.balance_due?.toFixed(2) }}</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="card border-0 shadow-sm bg-white">
                                                            <div class="card-body">
                                                                <h6 class="card-title text-muted small mb-2">Sales Order
                                                                    Details</h6>
                                                                <p class="mb-1"><strong>Sales Order:</strong> {{
                                                                    list.sales_order?.so_number || '-' }}</p>
                                                                <p class="mb-1"><strong>Customer:</strong> {{
                                                                    list.sales_order?.customer?.name || '-' }}</p>
                                                                <p class="mb-0"><strong>Order Date:</strong> {{
                                                                    list.sales_order?.order_date || '-' }}</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light border-0 mt-3">
                    <Pagination class="ms-2 me-2 mt-n1" v-if="meta" @fetch="fetch()" :lists="lists.length" :links="links" :pagination="meta" />
                </div>
            </div>
        </div>
        <div class="col-lg-3 ">
            <div class="card shadow-lg border-0 bg-primary">
                <div class="card-header border-0  bg-primary">
                    <h4 class="text-white">
                        <i class="ri-dashboard-line "></i> Quick Stats
                        <hr class="mb-0">
                    </h4>
                </div>

                <div class="card-body">
                    <div class="metric-card mb-3 p-3 bg-light rounded">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-primary text-white rounded">
                                    <i class="ri-file-list-line fs-18"></i>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="fw-semibold fs-12 mb-1">Total Invoices</p>
                                <h4 class="mb-0">{{ metrics.total_invoices }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="metric-card mb-3 p-3 bg-light rounded">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-info text-white rounded">
                                    <i class="ri-calendar-line fs-18"></i>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="fw-semibold fs-12 mb-1">Today's Invoices</p>
                                <h4 class="mb-0">{{ metrics.today_invoices }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="metric-card mb-3 p-3 bg-light rounded">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-success text-white rounded">
                                    <i class="ri-money-dollar-circle-line fs-18"></i>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="fw-semibold fs-12 mb-1">Outstanding Balance</p>
                                <h4 class="mb-0">₱{{ metrics.outstanding_balance?.toFixed(2) }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="metric-card mb-3 p-3 bg-light rounded">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-warning text-white rounded">
                                    <i class="ri-time-line fs-18"></i>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="fw-semibold fs-12 mb-1">Pending Invoices</p>
                                <h4 class="mb-0">{{ metrics.pending_invoices }}</h4>
                            </div>
                        </div>
                    </div>

                    <div class="metric-card p-3 bg-light rounded">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-success text-white rounded">
                                    <i class="ri-check-circle-line fs-18"></i>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="fw-semibold fs-12 mb-1">Paid Invoices</p>
                                <h4 class="mb-0">{{ metrics.paid_invoices }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </BRow>
    <Payment @approve="fetch()"  ref="payment"/>
</template>
<script>
import _ from 'lodash';
import Multiselect from "@vueform/multiselect";
import PageHeader from '@/Shared/Components/PageHeader.vue';
import Pagination from "@/Shared/Components/Pagination.vue";
import Payment from '../ARInvoices/Modals/Payment.vue';
export default {
    components: { PageHeader, Pagination, Multiselect, Payment },
    props: ['dropdowns'],
    data() {
        return {
            currentUrl: window.location.origin,
            lists: [],
            meta: {},
            links: {},
            filter: {
                keyword: null
            },
            index: null,
            selectedRow: null,
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
            showStock: false,
            expandedRows: []
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
            axios.get(page_url,{
                params : {
                    option: 'lists',
                    keyword: this.filter.keyword,
                    count: 10
                }
            })
                .then(response => {
                    if (response) {
                        this.lists = response.data.data;
                        this.meta = response.data.meta;
                        this.links = response.data.links;
                    }
                })
                .catch(err => console.log(err));
        },
        onPayment(data){
            let title = "Record Payment";
            this.$refs.payment.show(data, title, '/ar-invoices');
        },

        onPrint(id){
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
            if (this.expandedRows.includes(index)) {
                this.expandedRows = this.expandedRows.filter(i => i !== index);
            } else {
                this.expandedRows.push(index);
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
            // Calculate percentage based on total stock - this is a simple implementation
            // You might want to adjust this based on your business logic
            const maxStock = Math.max(...this.stock.products.map(p => p.total_quantity));
            return Math.min((quantity / maxStock) * 100, 100);
        }
    }
}
</script>
