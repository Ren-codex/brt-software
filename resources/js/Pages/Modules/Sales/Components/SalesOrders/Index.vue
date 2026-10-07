<template>
    <div>
    <DeliveryBoard v-if="showBoard" @back="onBoardClosed" />

    <template v-else>
        <div class="library-card">
            <div class="library-card-header library-card-header--tools">
                    <div class="d-flex align-items-center gap-3">
                        <div class="header-icon">
                            <i class="ri-shopping-cart-line"></i>
                        </div>
                        <h4 class="header-title mb-0">Sales Orders</h4>
                    </div>
                    <!-- The filters live up here with the buttons, so the page opens
                         on orders instead of on four stacked bands of chrome. -->
                    <div class="header-tools search-section">
                        <div class="search-wrapper">
                            <i class="ri-search-line search-icon"></i>
                            <input type="text" v-model="filter.keyword"
                                placeholder="Search sales order..." class="search-input">
                        </div>
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
                        <span v-if="lastUpdatedAt" class="poll-indicator" :title="'This list refreshes automatically'">
                            <i class="ri-refresh-line"></i> {{ lastUpdatedLabel() }}
                        </span>
                        <button class="acct-btn-secondary" @click="showBoard = true">
                            <i class="ri-truck-line me-1"></i>Deliveries
                        </button>
                        <button v-if="can('sales', 'sales_orders', 'encoder')" class="acct-btn-primary" @click="openCreate">
                            <i class="ri-add-line me-1"></i>Create Order
                        </button>
                    </div>
            </div>
            <div class="library-card-body">
                   
                    <div class="status-tab-bar">
                        <button
                            v-for="tab in statusTabs"
                            :key="tab.key"
                            class="status-tab-btn"
                            :class="{ active: activeTab === tab.key }"
                            @click="selectTab(tab)"
                        >
                            {{ tab.label }}
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table sales-table mb-0">
                            <thead>
                                <tr>
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
                                <TableLoadingRow v-if="loading" :colspan="8" message="Loading sales orders..." />
                                <template v-else>
                                <template v-for="(list, index) in lists" :key="list.id">
                                    <tr @click="openOrder(list)"
                                        :class="{

                                            'row-due-soon': isDueSoon(list),
                                            'cursor-pointer': true
                                        }" 
                                        class="main-table-row transition-all"
                                        style="transition: all 0.3s ease;">
                                        <td class="order-number-cell fw-semibold">
                                            <div class="expand-icon" title="Open this order">
                                                <i class="ri-arrow-right-s-line"></i>
                                            </div>
                                            {{ list.so_number }}
                                        </td>
                                        <td class="text-center">{{ list.customer?.name || '-' }}</td>
                                        <td class="text-center">{{ list.created_at }}</td>
                                        <!-- Status and payment are one fact about the order — "For Payment,
                                             COD" — so they share a cell instead of two columns of pills. -->
                                        <td class="text-center">
                                            <span class="state-stack">
                                                <span class="status-badge" :class="statusTone(list.status)">
                                                    <i v-if="list.status?.icon" :class="list.status.icon" class="me-1"></i>
                                                    {{ list.status ? list.status.name : '' }}
                                                </span>
                                                <span class="payment-pill" :class="paymentTone(list.payment_mode)">
                                                    {{ paymentLabel(list.payment_mode) }}
                                                </span>
                                            </span>
                                        </td>
                                        <td class="text-end fw-semibold">{{ formatCurrency(list.total_amount) }}</td>
                                        <td class="text-center">
                                            <span class="badge-stack">
                                                {{ list.due_date }}
                                                <span v-if="isDueSoon(list)" class="badge bg-danger">Due Soon</span>
                                                <!-- On a closed order the badge restates the status, so it
                                                     only shows where it is news: goods out, money not in. -->
                                                <span v-if="list.delivered_at && list.status?.slug !== 'closed'"
                                                    class="badge bg-success-subtle text-success-emphasis"
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
                                                <!-- Marking delivered belongs to the Deliveries tab, which is
                                                     built around that job and shows how long each one has
                                                     been waiting. Offering it here as well meant two places
                                                     to do the same thing and neither one the obvious place. -->
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
                                    <td colspan="8">
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
    </template>

    <Create @add="fetch()" :dropdowns="dropdowns" :user="user" ref="create"/>
    <Cancel @cancel="fetch()" ref="cancel"/>
    <Adjustment @update="fetch()" :dropdowns="dropdowns" ref="adjustment"/>

    

    <ViewOrder :order="viewingOrder" :dropdowns="dropdowns" @close="viewingOrder = null" />
    </div>
</template>
<script>
import { statusTone } from '@/Shared/utils/statusTone.js';
import { paymentTone, paymentLabel } from '@/Shared/utils/paymentType.js';
import _ from 'lodash';
import Multiselect from "@vueform/multiselect";
import Pagination from "@/Shared/Components/Pagination.vue";
import Cancel from './Modals/Cancel.vue';
import DeliveryBoard from './DeliveryBoard.vue';
import Create from './Modals/Create.vue';
import ViewOrder from './Modals/ViewOrder.vue';
import Adjustment from './Modals/Adjustment.vue';
import TableLoadingRow from '@/Shared/Components/TableLoadingRow.vue';
import { pollingMixin } from '@/Shared/polling.js';
import { recordLockMixin } from '@/Shared/recordLock.js';


import printDocument from '@/Shared/utils/printDocument';
export default {
    components: { ViewOrder, Pagination, Multiselect , Create, Cancel, DeliveryBoard, Adjustment, TableLoadingRow },
    mixins: [pollingMixin, recordLockMixin],
    props: ['dropdowns', 'invoices', 'user', 'isExternal'],
    data(){
        return {
            currentUrl: window.location.origin,
            showBoard: false,
            currentPageUrl: null,
            loading: false,
            lists: [],
            meta: {},
            links: {},
            filter: {
                keyword: null,
                location_id: null,
                status: null,
                delivery: null
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
            // For Release sits between paid and finished: money in, goods not gone.
            const relevant = ['for-payment', 'partially-paid', 'for-release', 'closed', 'cancelled'];
            const bySlug = Object.fromEntries((this.dropdowns.sales_statuses || []).map(s => [s.slug, s]));

            return [
                { key: 'all', label: 'All', slug: null, delivery: null },
                ...relevant.filter(slug => bySlug[slug]).map(slug => ({
                    key: slug, label: bySlug[slug].name, slug, delivery: null,
                })),
                // Goods outstanding, not money: a paid order can still be sitting
                // in the warehouse, so this tab cuts across every status.
                { key: 'undelivered', label: 'Undelivered', slug: null, delivery: 'undelivered' },
                // The mirror image: goods delivered, money not in. Oldest
                // delivery first, because that is who to ask about first.
                { key: 'to-collect', label: 'To Collect', slug: null, delivery: 'to-collect' },
            ];
        },
        activeTab() {
            if (this.filter.delivery) return this.filter.delivery;
            return this.filter.status || 'all';
        },
    },
    watch: {
        "filter.keyword"(newVal) {
            this.checkSearchStr(newVal);
        },
        "filter.location_id"() {
            this.fetch();
        },
        // One watcher for the tab bar: a tab sets both the status and the
        // delivery filter, and two watchers would fetch the list twice.
        activeTab() {
            this.fetch();
        },
    },
    created() {
        this.fetch();
    },
    mounted() {
        this.startPolling(() => this.fetch(this.currentPageUrl, { quiet: true }));
        this.fetchMetrics();
        // An order minimised earlier, resumed from the floating bar.
        this.resumeHandler = () => this.$refs.create?.resumeDraft();
        window.addEventListener('resume-sales-order', this.resumeHandler);
    },
    beforeUnmount() {
        window.removeEventListener('resume-sales-order', this.resumeHandler);
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
                    delivery: this.filter.delivery,
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
        paymentTone,
        paymentLabel,
        onBoardClosed() {
            this.showBoard = false;
            this.fetch();
        },
        selectTab(tab) {
            this.filter.status = tab.slug;
            this.filter.delivery = tab.delivery;
        },
        onPrint(id) {
            let url =  '/sales-orders';
            printDocument(`${url}/${id}?option=print&type=sales_order`);
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
/* The header holds the title, the filters and the buttons on one line. Four
   separate bands used to stack above the table, and 116px of the page was the
   gaps between them — more than two rows of orders spent on air. */
.library-card-header--tools {
    flex-wrap: wrap;
    gap: 0.6rem 1rem;
}

.header-tools {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex: 1 1 auto;
    justify-content: flex-end;
    min-width: 0;
    /* .search-section carries a 1.5rem bottom margin for its old position
       below the header; in here that would reopen the gap it just closed. */
    margin-bottom: 0;
}

/* The shared rule caps these at 350px for a full-width row. Up here they are
   sized to leave the title its space. */
.header-tools .search-wrapper {
    max-width: none;
    flex: 0 1 215px;
}

/* Wide enough that "All Locations" stays on one line once the icon's 2.5rem
   of left padding and the clear/caret buttons have taken their share. */
.header-tools .search-wrapper.filter-multiselect-wrapper {
    flex: 0 1 200px;
}

/* The fields shrink before they wrap, so they stay beside the title down to
   laptop width. Below that they take their own line rather than squeezing the
   search box down to nothing. */
@media (max-width: 1199px) {
    .header-tools {
        flex: 1 1 100%;
        justify-content: flex-start;
    }
}

@media (max-width: 575px) {
    .header-tools .search-wrapper,
    .header-tools .search-wrapper.filter-multiselect-wrapper {
        flex: 1 1 100%;
    }
}

/* The pills are the first thing under the header now, so the body no longer
   needs a full 1.5rem of air above them. */
.library-card-body {
    padding-top: 0.9rem;
}

/* The chevron leads the order number now that the row counter is gone. That
   counter numbered rows within the current page, so the same order was "1" on
   page one and "11" on page two — it identified nothing. */
/* Kept a plain table cell on purpose: display:flex here would take the td out
   of table layout, and its border would then be drawn to the content instead
   of across the row. nowrap is all it needs to hold the chevron and the
   number on one line. */
.order-number-cell {
    white-space: nowrap;
    text-align: left;
}

/* Status and payment read as one phrase and stay on one line, so every row is
   the same height. Letting them wrap made a Credit row twice as tall as the
   COD row above it. */
.state-stack {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-wrap: nowrap;
    gap: 0.3rem;
    white-space: nowrap;
}

/* Due soon used to wash the whole row in red, which made the order that most
   needs reading the hardest to read. The edge says the same thing and leaves
   the text alone. */
.row-due-soon > td:first-child {
    box-shadow: inset 3px 0 0 #c0392b;
}

.row-due-soon {
    background: rgba(192, 57, 43, 0.045);
}

.status-tab-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 0.85rem;
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

</style>
