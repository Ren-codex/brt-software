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
                    <div class="d-flex align-items-center gap-2">
                        <span v-if="lastUpdatedAt" class="poll-indicator" :title="'This list refreshes automatically'">
                            <i class="ri-refresh-line"></i> {{ lastUpdatedLabel() }}
                        </span>
                        <button v-if="can('sales', 'sales_orders', 'encoder')" class="acct-btn-primary" @click="openCreate">
                            <i class="ri-add-line me-1"></i>Create Order
                        </button>
                    </div>
            </div>
            <div class="library-card-body">
                   
                    <div class="status-tab-bar">
                        <button
                            v-for="tab in statusTabs"
                            :key="tab.slug || 'all'"
                            class="status-tab-btn"
                            :class="{ active: filter.status === tab.slug }"
                            @click="filter.status = tab.slug"
                        >
                            {{ tab.label }}
                        </button>
                    </div>

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
                                <div class="search-wrapper filter-multiselect-wrapper">
                                    <i class="ri-map-pin-line search-icon"></i>
                                    <Multiselect
                                        v-model="filter.location_id"
                                        :options="dropdowns.locations"
                                        label="name"
                                        value-prop="value"
                                        track-by="name"
                                        :searchable="true"
                                        :can-clear="true"
                                        placeholder="All Locations"
                                        class="search-input filter-multiselect"
                                    />
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
                                    <th>Payment</th>
                                    <th class="text-end">Total Amount</th>
                                    <th>Due Date</th>
                                    <th class="text-center">Paid %</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="fs-12">
                                <TableLoadingRow v-if="loading" :colspan="10" message="Loading sales orders..." />
                                <template v-else>
                                <template v-for="(list, index) in lists" :key="list.id">
                                    <tr @click="openOrder(list)"
                                        :class="{

                                            'bg-danger bg-opacity-25': isDueSoon(list),
                                            'cursor-pointer': true
                                        }" 
                                        class="main-table-row transition-all"
                                        style="transition: all 0.3s ease;">
                                        <td class="text-center">
                                            <div class="expand-icon" title="Open this order">
                                                <i class="ri-arrow-right-s-line"></i>
                                            </div>
                                            {{ index + 1 }}
                                        </td>
                                        <td class="text-center fw-semibold">{{ list.so_number }}</td>
                                        <td class="text-center">{{ list.customer?.name || '-' }}</td>
                                        <td class="text-center">{{ list.created_at }}</td>
                                        <td class="text-center">
                                            <span class="badge-stack">
                                                <span class="status-badge" :class="statusTone(list.status)">
                                                    <i v-if="list.status?.icon" :class="list.status.icon" class="me-1"></i>
                                                    {{ list.status ? list.status.name : '' }}
                                                </span>
                                            </span>
                                        </td>
                                          <!-- <td class="text-center">
                                            <span
                                                v-if="list.sub_status?.name"
                                                class="status-badge" :class="statusTone(list.sub_status)">
                                                {{ list.sub_status?.name  }}
                                            </span>
                                        </td> -->
                                        <td class="text-center">
                                            <span class="payment-pill" :class="paymentTone(list.payment_mode)">
                                                {{ paymentLabel(list.payment_mode) }}
                                            </span>
                                        </td>
                                        <td class="text-end fw-semibold">{{ formatCurrency(list.total_amount) }}</td>
                                        <td class="text-center">
                                            <span class="badge-stack">
                                                {{ list.due_date }}
                                                <span v-if="isDueSoon(list)" class="badge bg-danger">Due Soon</span>
                                                <span v-if="list.delivered_at" class="badge bg-success-subtle text-success-emphasis"
                                                    v-b-tooltip.hover :title="`Recorded by ${list.delivered_by || 'staff'}`">
                                                    Delivered {{ list.delivered_at }}
                                                </span>
                                            </span>
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
                                                <button v-if="list.status?.slug == 'for-payment' && can('sales', 'sales_orders', 'encoder')"
                                                    @click.stop="onSalesAdjustment(list)"
                                                    class="action-btn warn" v-b-tooltip.hover title="Sales Adjustment">
                                                    <i class="ri-refund-line"></i>
                                                </button>
                                                <button v-if="canMarkDelivered(list)"
                                                    @click.stop="onMarkDelivered(list)"
                                                    class="action-btn success" v-b-tooltip.hover title="Mark Delivered">
                                                    <i class="ri-truck-line"></i>
                                                </button>
                                                <button @click.stop="onPrint(list.id)"
                                                    class="action-btn info" v-b-tooltip.hover title="Print Invoice">
                                                    <i class="ri-printer-line"></i>
                                                </button>
                                                <button v-if="isEditableOrder(list) && can('sales', 'sales_orders', 'encoder')"
                                                    @click.stop="openEdit(list, index)"
                                                    class="action-btn edit" v-b-tooltip.hover title="Edit">
                                                    <i class="ri-pencil-fill"></i>
                                                </button>
                                                <button v-if="isCancellable(list) && (can('sales', 'sales_orders', 'void') || can('sales', 'sales_orders', 'approver'))"
                                                    @click.stop="onCancel(list)"
                                                    class="action-btn delete" v-b-tooltip.hover title="Cancel Order">
                                                    <i class="ri-close-line"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                                <tr v-if="lists.length === 0">
                                    <td colspan="10">
                                        <div class="sales-empty-state">
                                            <i class="ri-shopping-cart-line"></i>
                                            <p class="mb-1">No sales orders found</p>
                                            <small>Try adjusting your search or filter.</small>
                                        </div>
                                    </td>
                                </tr>
                                </template>
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
    <MarkDelivered @delivered="fetch()" ref="markDelivered"/>
    <Adjustment @update="fetch()" :dropdowns="dropdowns" ref="adjustment"/>

    

    <ViewOrder :order="viewingOrder" :dropdowns="dropdowns" @close="viewingOrder = null" />
</template>
<script>
import { statusTone } from '@/Shared/utils/statusTone.js';
import _ from 'lodash';
import Multiselect from "@vueform/multiselect";
import PageHeader from '@/Shared/Components/PageHeader.vue';
import Pagination from "@/Shared/Components/Pagination.vue";
import Cancel from './Modals/Cancel.vue';
import MarkDelivered from './Modals/MarkDelivered.vue';
import Create from './Modals/Create.vue';
import ViewOrder from './Modals/ViewOrder.vue';
import Adjustment from './Modals/Adjustment.vue';
import TableLoadingRow from '@/Shared/Components/TableLoadingRow.vue';
import { pollingMixin } from '@/Shared/polling.js';
import { recordLockMixin } from '@/Shared/recordLock.js';


export default {
    components: { ViewOrder, PageHeader, Pagination, Multiselect , Create, Cancel, MarkDelivered, Adjustment, TableLoadingRow },
    mixins: [pollingMixin, recordLockMixin],
    props: ['dropdowns', 'invoices', 'user', 'isExternal'],
    data(){
        return {
            currentUrl: window.location.origin,
            currentPageUrl: null,
            loading: false,
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
            viewingOrder: null
        }
    },
    computed: {
        statusTabs() {
            const relevant = ['for-payment', 'partially-paid', 'closed', 'cancelled'];
            const bySlug = Object.fromEntries((this.dropdowns.sales_statuses || []).map(s => [s.slug, s]));

            return [
                { slug: null, label: 'All' },
                ...relevant.filter(slug => bySlug[slug]).map(slug => ({ slug, label: bySlug[slug].name })),
            ];
        },
    },
    watch: {
        "filter.keyword"(newVal) {
            this.checkSearchStr(newVal);
        },
        "filter.location_id"() {
            this.fetch();
        },
        "filter.status"() {
            this.fetch();
        },
    },
    created() {
        this.fetch();
    },
    mounted() {
        this.startPolling(() => this.fetch(this.currentPageUrl, { quiet: true }));
        this.fetchMetrics();
    },
    methods: {
        // Tone comes from what the status means — see Shared/utils/statusTone.js
        statusTone,

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
        /**
         * `quiet` is used by the background refresh: it skips the loading row
         * and keeps any expanded row open, so the poll is invisible to whoever
         * is reading the screen.
         */
        fetch(page_url, { quiet = false } = {}) {
            let baseUrl = this.isExternal ? '/sales-orders-external' : '/sales-orders';
            page_url = page_url || baseUrl;
            // Remembered so a background refresh stays on the page the user is
            // actually looking at rather than snapping back to the first one.
            this.currentPageUrl = page_url;

            if (!quiet) {
                this.loading = true;
            }

            return axios.get(page_url, {
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
                        if (!quiet) {
                            this.viewingOrder = null; // Whatever was open is stale once the list reloads
                        }
                    }
                })
                .catch(err => {
                    console.log(err);
                    if (!quiet) this.$toast.error('Unable to load sales orders.');
                })
                .finally(() => { if (!quiet) this.loading = false; });
        },
        openCreate() {
            this.$refs.create.show();
        },

        openEdit(data, index) {
            this.selectedRow = index;
            this.$refs.create.edit(data, index);
        },

        /**
         * Only worth offering while there is something to record: an order the
         * driver could still be out with, and not one already cancelled.
         */
        canMarkDelivered(order) {
            return !order.delivered_at
                && order.status?.slug !== 'cancelled'
                && this.can('sales', 'sales_orders', 'encoder');
        },
        onMarkDelivered(order) {
            this.$refs.markDelivered.show(order);
        },

        onCancel(list) {
            let title = "Sales Order";
            let url = '/sales-orders';
            const hasPayments = (list.invoices || []).some(inv => inv.amount_paid > 0);
            this.$refs.cancel.show(list.id, title, url, hasPayments);
        },

        /**
         * Cancelling is allowed for longer than editing is. An order still in
         * play — for payment, partially paid, even paid — can be pulled back;
         * only a Closed one has finished its run through the ledger.
         */
        isCancellable(list) {
            return !['closed', 'cancelled'].includes(list.status?.slug);
        },
        isEditableOrder(list) {
            // Credit/COD orders stay editable until fully paid, not just at creation.
            return ['for-payment', 'partially-paid'].includes(list.status?.slug);
        },
        /**
         * How the sale settles, not the method used: a cash sale paid by
         * transfer or split is still a cash sale to anyone scanning the list.
         */
        paymentTone(mode) {
            const value = String(mode || 'cash').trim().toLowerCase();
            if (['credit', 'credit sales'].includes(value)) return 'is-credit';
            if (value === 'cod') return 'is-cod';
            return 'is-cash';
        },
        paymentLabel(mode) {
            const value = String(mode || 'cash').trim().toLowerCase();
            if (['credit', 'credit sales'].includes(value)) return 'Credit';
            if (value === 'cod') return 'COD';
            if (value === 'split') return 'Cash · Split';
            if (['bank transfer', 'check', 'cheque', 'gcash'].includes(value)) {
                return `Cash · ${String(mode).trim()}`;
            }
            return 'Cash';
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

        openOrder(order) {
            this.viewingOrder = order;
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
                .catch(err => {
                    console.log(err);
                    this.$toast.error('Unable to load sales order metrics.');
                });
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
            // A cancelled or voided document owes nothing, whatever balance
            // was left on the row when it was cancelled — chasing it as due
            // would be chasing money nobody has to pay.
            const status = (list?.status?.slug || list?.sales_order?.status?.slug || '').toLowerCase();
            if (['cancelled', 'voided'].includes(status)) return false;

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
.status-tab-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.status-tab-btn {
    padding: 6px 16px;
    border-radius: 8px;
    border: 1px solid #c4d9d2;
    background: #fff;
    color: #6b8c85;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.status-tab-btn:hover { background: #edf6f2; color: #16322e; }
.status-tab-btn.active { background: #3D8D7A; border-color: #3D8D7A; color: #fff; }

/* Quiet "this screen keeps itself current" hint. */
.poll-indicator {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.72rem;
    color: #7f9a92;
    white-space: nowrap;
    user-select: none;
}

    .filter-multiselect-wrapper {
        --ms-px: 0.75rem;
        --ms-py: 0.6rem;
        --ms-font-size: 0.8125rem;
        --ms-radius: 8px;
        --ms-bg: #f9fafb;
        --ms-border-color: #e5e7eb;
        --ms-border-width: 2px;
        --ms-border-color-active: #2e8b57;
        --ms-ring-color: rgba(46, 139, 87, 0.1);
        --ms-ring-width: 3px;
        --ms-placeholder-color: #9ca3af;
    }

    .filter-multiselect-wrapper .filter-multiselect {
        width: 100%;
        padding: 0;
        border: none;
        background: none;
    }

    .filter-multiselect-wrapper :deep(.multiselect-single-label),
    .filter-multiselect-wrapper :deep(.multiselect-placeholder),
    .filter-multiselect-wrapper :deep(.multiselect-search) {
        padding-left: 2.5rem;
    }

    /* .status-badge tones come from _library-index.scss. */

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

/* How the sale settles: cash at the counter, cash at the door, or on terms. */
.payment-pill {
    display: inline-block;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    white-space: nowrap;
}

.payment-pill.is-cash {
    background: rgba(61, 141, 122, 0.12);
    color: #2f6f60;
}

.payment-pill.is-cod {
    background: #e8f1ff;
    color: #2456a6;
}

.payment-pill.is-credit {
    background: #fef3c7;
    color: #92400e;
}
</style>
