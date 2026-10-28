<template>
    <div>
        <div class="col-md-12 mb-4">
            <div class="library-card">
                <div class="library-card-header">
                        <div class="d-flex align-items-center gap-3">
                            <div class="header-icon">
                                <i class="ri-shopping-cart-line fs-24"></i>
                            </div>
                            <div>
                                <h4 class="header-title mb-1">Receipts</h4>
                                <p class="header-subtitle mb-0">A comprehensive list of Receipts</p>
                            </div>
                        </div>
                      

                </div>
                <div class="library-card-body">
                    <!-- Search, location, status and sort share one row; they wrap
                         only when the screen is too narrow to hold them. -->
                    <div class="tabs-search-row">
                        <div class="status-tab-bar">
                            <button
                                v-for="tab in statusTabs"
                                :key="tab.slug || 'all'"
                                class="status-tab-btn"
                                :class="{ active: filter.status === tab.slug }"
                                @click="filter.status = tab.slug; fetch()"
                            >
                                {{ tab.label }}
                            </button>
                        </div>

                        <div class="search-section">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="search-wrapper">
                                        <i class="ri-search-line search-icon"></i>
                                        <input type="text" v-model="filter.keyword"
                                            placeholder="Search receipt..." class="search-input">
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
                            </div>

                            <SortSelect v-model="sortOption" :fields="[{ field: 'receipt_number', label: 'Receipt no.' }, { field: 'receipt_date', label: 'Receipt date', type: 'date' }, { field: 'amount_paid', label: 'Amount', type: 'number' }]" @change="fetch()" />
                        </div>
                    </div>



                    <div class="table-responsive table-card">
                        <table class="table sales-table mb-0">
                            <thead>
                                <tr>
                                    <th style="width:3%">#</th>
                                    <th class="text-center" style="width:12%">OR Number</th>
                                    <th class="text-center" style="width:12%">Customer</th>
                                    <th class="text-center" style="width:12%">Sales Rep</th>
                                    <th class="text-center" style="width:12%">Payment Date</th>
                                    <th class="text-center" style="width:10%">Sale</th>
                                    <th class="text-center" style="width:10%">Type</th>
                                    <th class="text-center" style="width:12%">Amount Balance</th>
                                    <th class="text-end" style="width:12%">Amount Paid</th>
                                    <th class="text-center" style="width:12%">Payment Mode</th>
                                    <th class="text-center" style="width:12%">Status</th>
                                    <th class="text-center" style="width:6%">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="fs-12">
                                <TableLoadingRow v-if="loading" :colspan="12" message="Loading receipts..." />
                                <template v-else>
                                <tr v-if="lists.length === 0">
                                    <td colspan="12">
                                        <div class="sales-empty-state">
                                            <i class="ri-shopping-cart-line"></i>
                                            <p>No receipts found.</p>
                                            <small>Receipts will appear here once they are created.</small>
                                        </div>
                                    </td>
                                </tr>
                                <template v-for="(list,index) in lists" :key="index">
                                    <tr @click="openReceipt(list)"
                                        :class="{
                                            'main-table-row': true,
                                            'unremitted-row': list.is_unremitted_past_day
                                        }"
                                        class="cursor-pointer transition-all" style="transition: all 0.3s ease;">
                                        <td class="text-center">{{ index + 1 }}</td>
                                        <td class="text-center fw-semibold">{{ list.receipt_number }}</td>
                                        <td class="text-center">{{ list.customer?.name || '-' }}</td>
                                        <td class="text-center">{{ list.sales_rep?.fullname || list.sales_order?.sales_rep?.fullname || '-' }}</td>
                                        <td class="text-center">{{ list.receipt_date }}</td>
                                        <td class="text-center">
                                            <span class="payment-pill" :class="paymentTone(list.sales_order?.payment_mode)">
                                                {{ paymentLabel(list.sales_order?.payment_mode) }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge" :class="getReceiptTypeClass(list.receipt_type)">
                                                {{ getReceiptTypeLabel(list.receipt_type) }}
                                            </span>
                                        </td>
                                        <td class="text-center">₱{{ list.balance_due }}</td>
                                        <td class="text-center">₱{{ list.amount_paid }}</td>
                                        <td class="text-center">{{ list.payment_mode }}</td>
                                        <td class="text-center">
                                            <span class="badge-stack">
                                                <span class="status-badge" :class="statusTone(list.status)">
                                                    {{ list.status?.name || 'Unknown' }}
                                                </span>
                                                <span v-if="list.is_unremitted_past_day" class="unremitted-badge">
                                                    Unremitted
                                                </span>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-1">
                                                <button @click.stop="onPrint(list.id)" class="action-btn info" v-b-tooltip.hover title="Print">
                                                    <i class="ri-printer-line"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="px-3 pb-3">
                    <Pagination class="ms-2 me-2 mt-n1" v-if="meta" @fetch="fetch" :lists="lists.length" :links="links" :pagination="meta" />
                </div>
            </div>
        </div>
    </div>

    <!-- One receipt in full. Was a panel unfolding inside the table, which
         pushed every row below it down the page. Chrome from
         _library-modal.scss, as everywhere else. -->
    <div v-if="openedReceipt" class="modal-overlay active" @click.self="closeReceipt">
        <div class="modal-container" style="max-width: 760px" @click.stop>
            <div class="modal-header">
                <div class="modal-header-icon"><i class="ri-receipt-line"></i></div>
                <div>
                    <p class="modal-subtitle mb-0">
                        {{ openedReceipt.customer?.name || 'Walk-in customer' }}
                        <span v-if="openedReceipt.receipt_date">&middot; {{ openedReceipt.receipt_date }}</span>
                    </p>
                    <h5 class="modal-title mb-0">{{ openedReceipt.receipt_number }}</h5>
                </div>
                <button class="close-btn ms-auto" @click="closeReceipt" aria-label="Close">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body">
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <div class="card border-0 shadow-sm ">
                                                            <div class="card-body">
                                                                <h6 class="card-title text-muted small mb-2">Receipt Information</h6>
                                                                <p class="mb-1"><strong>Receipt Date:</strong> {{ openedReceipt.receipt_date }}</p>
                                                                <p class="mb-1"><strong>Sales Rep:</strong> {{ openedReceipt.sales_rep?.fullname || openedReceipt.sales_order?.sales_rep?.fullname || '-' }}</p>
                                                                <p class="mb-1"><strong>Receipt Type:</strong> {{ getReceiptTypeLabel(openedReceipt.receipt_type) }}</p>
                                                                <p v-if="openedReceipt.source_receipt?.receipt_number" class="mb-1"><strong>Source Receipt:</strong> {{ openedReceipt.source_receipt.receipt_number }}</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="card border-0 shadow-sm ">
                                                          
                                                            <div class="card-body">
                                                                <h6 class="card-title text-muted small mb-2">Customer Information</h6>
                                                                <p class="mb-1"><strong>Customer:</strong> {{ openedReceipt.customer?.name || 'N/A' }}</p>
                                                                <p class="mb-1"><strong>Contact:</strong> {{ openedReceipt.customer?.contact_number || 'N/A' }}</p>
                                                                <p class="mb-1"><strong>Email:</strong> {{ openedReceipt.customer?.email || 'N/A' }}</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
            </div>
            <div class="modal-footer">
                <button class="acct-btn-secondary" @click="closeReceipt">Close</button>
                <button class="acct-btn-secondary" @click="onPrint(openedReceipt.id)">
                    <i class="ri-printer-line"></i>
                    Print Receipt
                </button>
            </div>
        </div>
    </div>

</template>
<script>
import { statusTone } from '@/Shared/utils/statusTone.js';
import { listSortMixin } from '@/Shared/listSort.js';
import { paymentTone, paymentLabel } from '@/Shared/utils/paymentType.js';
import _ from 'lodash';
import PageHeader from '@/Shared/Components/PageHeader.vue';
import Pagination from "@/Shared/Components/Pagination.vue";
import TableLoadingRow from '@/Shared/Components/TableLoadingRow.vue';
import { pollingMixin } from '@/Shared/polling.js';



import { openOrPrint } from '@/Shared/utils/printDocument';
export default {
    components: { PageHeader, Pagination, TableLoadingRow },
    mixins: [listSortMixin, pollingMixin],
    props: ['dropdowns', 'isExternal'],
    data(){
        return {

            loading: false,
            lists: [],
            meta: {},
            links: {},
            filter: {
                keyword: null,
                location_id: null,
                status: null
            },

            metrics: {
                total_receipts: 0,
                total_amount_collected: 0
            },

            // The receipt whose modal is open, or null.
            openedReceipt: null,
            currentPageUrl: null
        }
    },

    computed: {
        statusTabs() {
            const relevant = ['pending', 'liquidated', 'voided'];
            const bySlug = Object.fromEntries((this.dropdowns.sales_statuses || []).map(s => [s.slug, s]));

            return [
                { slug: null, label: 'All' },
                ...relevant.filter(slug => bySlug[slug]).map(slug => ({ slug, label: bySlug[slug].name })),
            ];
        },
    },
    watch: {
        "filter.keyword"(newVal){
            this.checkSearchStr(newVal);
        }
    },
    created(){
       this.fetch();
       this.fetchMetrics();
    },
    mounted() {
        this.startPolling(async () => {
            await this.fetch(this.currentPageUrl, { quiet: true });
            this.fetchMetrics();
        });
    },
    methods: {
        paymentTone,
        paymentLabel,
        // Tone comes from what the status means — see Shared/utils/statusTone.js
        statusTone,

        checkSearchStr: _.debounce(function(string) {
            this.fetch();
        }, 300),
        fetch(page_url, { quiet = false } = {}){
            page_url = page_url || '/receipts';
            // Remembered so a background refresh keeps the user's page.
            this.currentPageUrl = page_url;
            if (!quiet) {
                this.loading = true;
            }
            return axios.get(page_url,{
                params : {
                    keyword: this.filter.keyword,
                    location_id: this.filter.location_id,
                    status: this.filter.status,
                    count: 10,
                    ...this.sortParams, option: 'lists',
                    is_external: this.isExternal ? 1 : 0
                }
            })
            .then(response => {
                if(response){
                    this.lists = response.data.data;
                    this.meta = response.data.meta;
                    this.links = response.data.links;
                }
            })
            .catch(err => console.log(err))
            .finally(() => { if (!quiet) this.loading = false; });
        },


        openReceipt(receipt) {
            this.openedReceipt = receipt;
        },
        closeReceipt() {
            this.openedReceipt = null;
        },

        fetchMetrics(){
            axios.get('/receipts',{
                params : {
                    option: 'dashboard'
                }
            })
            .then(response => {
                if(response){
                    this.metrics = response.data;
                }
            })
            .catch(err => console.log(err));
        },

        onPrint(id) {
            openOrPrint(`/receipts/${id}?option=print&type=receipt`);
        },
        getReceiptTypeLabel(type) {
            if (type === 'updated') return 'Adjusted Payment';
            if (type === 'refund') return 'Return Refund';
            return 'Payment';
        },
        getReceiptTypeClass(type) {
            if (type === 'updated') return 'bg-info text-dark';
            if (type === 'refund') return 'bg-warning text-dark';
            return 'bg-primary';
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
    border: 1px solid var(--brt-border);
    background: #fff;
    color: var(--brt-muted);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.status-tab-btn:hover { background: var(--brt-tint-2); color: var(--brt-ink); }
.status-tab-btn.active { background: var(--brt-primary); border-color: var(--brt-primary); color: #fff; }

.main-table-row {
    transition: all 0.2s ease;
    border-left: 3px solid transparent;
}

.main-table-row.unremitted-row {
    background: rgba(239, 68, 68, 0.12);
    border-left-color: #dc2626;
}

.main-table-row.unremitted-row:hover {
    background: rgba(239, 68, 68, 0.18) !important;
    border-left-color: #b91c1c;
}

.unremitted-badge {
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
</style>
