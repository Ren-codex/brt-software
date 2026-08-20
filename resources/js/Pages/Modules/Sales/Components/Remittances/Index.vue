<template>
    <div>
        <template v-if="currentView === 'list'">
            <div>
                <div class="col-md-12 mb-4">
                    <div class="library-card">
                        <div class="library-card-header">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="header-icon">
                                        <i class="ri-shopping-cart-line fs-24"></i>
                                    </div>
                                    <div>
                                        <h4 class="header-title mb-1">Remittances</h4>
                                        <p class="header-subtitle mb-0">Manage remittances</p>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-3 flex-wrap justify-content-end">
                                    <div class="cash-on-hand-card" v-if="isSalesRep">
                                        <span class="cash-on-hand-label">My Cash on Hand</span>
                                        <strong class="cash-on-hand-value">{{ formatCurrency(myHoldings.total_amount) }}</strong>
                                        <span class="cash-on-hand-sub">{{ myHoldings.receipt_count }} unremitted receipt{{ myHoldings.receipt_count !== 1 ? 's' : '' }}</span>
                                    </div>
                                    <div class="cash-on-hand-card" v-else>
                                        <span class="cash-on-hand-label">Total Cash on Hand</span>
                                        <strong class="cash-on-hand-value">{{ formatCurrency(metrics.total_cash_on_hand) }}</strong>
                                    </div>
                                    <div class="cash-on-hand-card undeposited-card" v-if="!isSalesRep" @click="switchTab('undeposited')" title="View undeposited remittances">
                                        <span class="cash-on-hand-label">Undeposited Cash</span>
                                        <strong class="cash-on-hand-value">{{ formatCurrency(undepositedSummary.total_amount) }}</strong>
                                        <span class="cash-on-hand-sub">{{ undepositedSummary.count }} liquidated remittance{{ undepositedSummary.count !== 1 ? 's' : '' }} awaiting deposit</span>
                                    </div>
                                    <button class="acct-btn-secondary" @click="currentView = 'summary'">
                                        <i class="ri-bar-chart-grouped-line"></i>
                                        Pending Collections
                                    </button>
                                    <button v-if="can('sales', 'remittances', 'encoder')" class="acct-btn-primary" @click="openCreate">
                                        <i class="ri-add-line"></i>
                                        Prepare Remittance
                                    </button>
                                </div>
                        </div>

                        <div class="library-card-body">
                            <div class="search-section">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="search-wrapper">
                                            <i class="ri-search-line search-icon"></i>
                                            <input
                                                type="text"
                                                v-model="filter.keyword"
                                                @input="debouncedSearch"
                                                placeholder="Search remittance..."
                                                class="search-input"
                                            >
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="search-wrapper">
                                            <i class="ri-map-pin-line search-icon"></i>
                                            <select v-model.number="filter.location_id" @change="fetch" class="search-input">
                                                <option :value="null">All Locations</option>
                                                <option v-for="location in dropdowns.locations" :key="location.value" :value="location.value">
                                                    {{ location.name }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <div class="filter-segment">
                                    <button :class="['filter-segment-btn', activeTab === 'for-verification' ? 'active' : '']" @click="switchTab('for-verification')">
                                        <i class="ri-shield-check-line"></i>
                                        <span>For Verification</span>
                                        <span v-if="activeTab === 'for-verification'" class="seg-count">{{ meta.total ?? 0 }}</span>
                                    </button>
                                    <button :class="['filter-segment-btn', activeTab === 'liquidated' ? 'active' : '']" @click="switchTab('liquidated')">
                                        <i class="ri-checkbox-circle-line"></i>
                                        <span>Liquidated</span>
                                        <span v-if="activeTab === 'liquidated'" class="seg-count">{{ meta.total ?? 0 }}</span>
                                    </button>
                                    <button :class="['filter-segment-btn', activeTab === 'disapproved' ? 'active' : '']" @click="switchTab('disapproved')">
                                        <i class="ri-close-circle-line"></i>
                                        <span>Disapproved</span>
                                        <span v-if="activeTab === 'disapproved'" class="seg-count">{{ meta.total ?? 0 }}</span>
                                    </button>
                                    <button v-if="!isSalesRep" :class="['filter-segment-btn', activeTab === 'undeposited' ? 'active' : '']" @click="switchTab('undeposited')">
                                        <i class="ri-time-line"></i>
                                        <span>Undeposited</span>
                                        <span v-if="activeTab === 'undeposited'" class="seg-count">{{ meta.total ?? 0 }}</span>
                                    </button>
                                </div>

                                <div v-show="activeTab === 'for-verification'" class="table-responsive">
                                    <table class="table sales-table mb-0">
                                        <thead>
                                            <tr>
                                                <th style="width:3%">#</th>
                                                <th class="text-center" style="width:15%">Remittance No.</th>
                                                <th class="text-center" style="width:15%">Date &amp; Time</th>
                                                <th class="text-end" style="width:15%">Amount</th>
                                                <th class="text-center" style="width:15%">Status</th>
                                                <th class="text-center" style="width:20%">Sales Rep</th>
                                                <th class="text-center" style="width:7%">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <TableLoadingRow v-if="loading" :colspan="7" message="Loading remittances..." />
                                            <template v-else>
                                            <tr v-if="openRemittance.length === 0">
                                                <td colspan="7">
                                                    <div class="sales-empty-state">
                                                        <i class="ri-inbox-line"></i>
                                                        <p>No open remittances found.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr
                                                v-for="(item, index) in openRemittance"
                                                :key="item.id || index"
                                                class="cursor-pointer"
                                                @click="openView(item)"
                                            >
                                                <td>{{ index + 1 }}</td>
                                                <td class="text-center fw-semibold">{{ item.remittance_no || '-' }}</td>
                                                <td class="text-center">{{ formatDateTime(item.created_at) }}</td>
                                                <td class="text-end">{{ formatCurrency(item.total_amount) }}</td>
                                                <td class="text-center">
                                                    <span :style="{ backgroundColor: item.status?.bg_color || '#6c757d', color: '#fff', padding: '3px 10px', borderRadius: '12px', fontSize: '11px', whiteSpace: 'nowrap', display: 'inline-block' }">
                                                        {{ item.status?.name }}
                                                    </span>
                                                </td>
                                                <td class="text-center">{{ item.created_by?.fullname || '-' }}</td>
                                                <td class="text-center">
                                                    <button @click.stop="openView(item)" class="action-btn info" title="View">
                                                        <i class="ri-eye-line"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>


                                <div v-show="activeTab === 'liquidated'" class="table-responsive">
                                    <table class="table sales-table mb-0">
                                        <thead>
                                            <tr>
                                                <th style="width:3%">#</th>
                                                <th class="text-center" style="width:13%">Remittance No.</th>
                                                <th class="text-center" style="width:12%">Date &amp; Time</th>
                                                <th class="text-end" style="width:12%">Amount</th>
                                                <th class="text-center" style="width:18%">Sales Rep</th>
                                                <th class="text-center" style="width:15%">Bank Deposit</th>
                                                <th class="text-center" style="width:7%">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <TableLoadingRow v-if="loading" :colspan="7" message="Loading remittances..." />
                                            <template v-else>
                                            <tr v-if="liquidatedRemittance.length === 0">
                                                <td colspan="7">
                                                    <div class="sales-empty-state">
                                                        <i class="ri-inbox-line"></i>
                                                        <p>No liquidated remittances found.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr
                                                v-for="(item, index) in liquidatedRemittance"
                                                :key="item.id || index"
                                                class="cursor-pointer"
                                                @click="openView(item)"
                                            >
                                                <td>{{ index + 1 }}</td>
                                                <td class="text-center fw-semibold">{{ item.remittance_no || '-' }}</td>
                                                <td class="text-center">{{ formatDateTime(item.created_at) }}</td>
                                                <td class="text-end">{{ formatCurrency(item.total_amount) }}</td>
                                                <td class="text-center">{{ item.created_by?.fullname || '-' }}</td>
                                                <td class="text-center">
                                                    <span v-if="item.is_deposited" class="deposit-status-chip deposited" :title="item.bank_deposit?.bank_name">
                                                        <i class="ri-checkbox-circle-fill"></i> {{ item.bank_deposit?.deposit_no }}
                                                    </span>
                                                    <span v-else class="deposit-status-chip pending">
                                                        <i class="ri-time-line"></i> Not yet deposited
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <button @click.stop="openView(item)" class="action-btn info" title="View">
                                                        <i class="ri-eye-line"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>

                                <div v-show="activeTab === 'disapproved'" class="table-responsive">
                                    <table class="table sales-table mb-0">
                                        <thead>
                                            <tr>
                                                <th style="width:3%">#</th>
                                                <th class="text-center" style="width:15%">Remittance No.</th>
                                                <th class="text-center" style="width:15%">Date &amp; Time</th>
                                                <th class="text-end" style="width:15%">Amount</th>
                                                <th class="text-center" style="width:20%">Sales Rep</th>
                                                <th class="text-center" style="width:7%">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <TableLoadingRow v-if="loading" :colspan="6" message="Loading remittances..." />
                                            <template v-else>
                                            <tr v-if="disapprovedRemittance.length === 0">
                                                <td colspan="6">
                                                    <div class="sales-empty-state">
                                                        <i class="ri-inbox-line"></i>
                                                        <p>No disapproved remittances found.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr
                                                v-for="(item, index) in disapprovedRemittance"
                                                :key="item.id || index"
                                                class="cursor-pointer"
                                                @click="openView(item)"
                                            >
                                                <td>{{ index + 1 }}</td>
                                                <td class="text-center fw-semibold">{{ item.remittance_no || '-' }}</td>
                                                <td class="text-center">{{ formatDateTime(item.created_at) }}</td>
                                                <td class="text-end">{{ formatCurrency(item.total_amount) }}</td>
                                                <td class="text-center">{{ item.created_by?.fullname || '-' }}</td>
                                                <td class="text-center">
                                                    <button @click.stop="openView(item)" class="action-btn info" title="View">
                                                        <i class="ri-eye-line"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>

                                <div v-show="activeTab === 'undeposited'" class="table-responsive">
                                    <table class="table sales-table mb-0">
                                        <thead>
                                            <tr>
                                                <th style="width:3%">#</th>
                                                <th class="text-center" style="width:15%">Remittance No.</th>
                                                <th class="text-center" style="width:15%">Date &amp; Time</th>
                                                <th class="text-end" style="width:15%">Amount</th>
                                                <th class="text-center" style="width:20%">Sales Rep</th>
                                                <th class="text-center" style="width:7%">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <TableLoadingRow v-if="loading" :colspan="6" message="Loading remittances..." />
                                            <template v-else>
                                            <tr v-if="undepositedRemittance.length === 0">
                                                <td colspan="6">
                                                    <div class="sales-empty-state">
                                                        <i class="ri-checkbox-circle-line"></i>
                                                        <p>Nothing awaiting deposit — all liquidated remittances have been banked.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr
                                                v-for="(item, index) in undepositedRemittance"
                                                :key="item.id || index"
                                                class="cursor-pointer"
                                                @click="openView(item)"
                                            >
                                                <td>{{ index + 1 }}</td>
                                                <td class="text-center fw-semibold">{{ item.remittance_no || '-' }}</td>
                                                <td class="text-center">{{ formatDateTime(item.created_at) }}</td>
                                                <td class="text-end">{{ formatCurrency(item.total_amount) }}</td>
                                                <td class="text-center">{{ item.created_by?.fullname || '-' }}</td>
                                                <td class="text-center">
                                                    <button @click.stop="openView(item)" class="action-btn info" title="View">
                                                        <i class="ri-eye-line"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="px-3 pb-3">
                            <Pagination class="ms-2 me-2 mt-n1" v-if="meta" @fetch="fetch" :lists="lists.length" :links="links" :pagination="meta" />
                        </div>
                    </div>
                </div>
            </div>
            <Create @add="fetch" ref="create" />
        </template>

        <View
            v-else-if="currentView === 'view' && selectedRemittance"
            :item="selectedRemittance"
            :dropdowns="dropdowns"
            @back="closeView"
            @reload="handleViewReload"
        />

        <SummaryView
            v-else-if="currentView === 'summary'"
            @back="currentView = 'list'"
        />
    </div>
</template>

<script>
import _ from 'lodash';
import Pagination from "@/Shared/Components/Pagination.vue";
import Create from './Modals/Create.vue';
import View from './View.vue';
import SummaryView from './SummaryView.vue';
import TableLoadingRow from '@/Shared/Components/TableLoadingRow.vue';
import { pollingMixin } from '@/Shared/polling.js';

export default {
    components: { Pagination, Create, View, SummaryView, TableLoadingRow },
    mixins: [pollingMixin],
    props: ['dropdowns'],
    data() {
        return {
            loading: false,
            lists: [],
            meta: {},
            links: {},
            filter: {
                keyword: '',
                location_id: null,
                status: 'for-verification'
            },
            activeTab: 'for-verification',
            currentView: 'list',
            selectedRemittance: null,
            metrics: {
                total_remittances: 0,
                total_amount_remitted: 0,
                total_cash_on_hand: 0,
                today_remittances: 0,
                open_remittances: 0
            },
            myHoldings: {
                total_amount: 0,
                receipt_count: 0,
            },
            undepositedSummary: {
                total_amount: 0,
                count: 0,
            }
        };
    },
    computed: {
        openRemittance() { return this.lists; },
        liquidatedRemittance() { return this.lists; },
        disapprovedRemittance() { return this.lists; },
        undepositedRemittance() { return this.lists; },
        // A user who also holds an admin-level sales role (e.g. Super Admin
        // stacked with Sales Rep, as happens with multi-role test accounts)
        // must still see everything — only a pure Sales Rep is restricted.
        isSalesRep() {
            const roles = this.$page.props.roles ?? [];
            const hasSalesAdmin = (this.$page.props.permissions?.sales?._module ?? []).includes('admin');
            return roles.includes('Sales Rep') && !!this.$page.props.user?.data?.employee_id && !hasSalesAdmin;
        },
    },
    created() {
        this.debouncedSearch = _.debounce(this.fetch, 500);
        this.fetch();
        if (this.isSalesRep) {
            this.fetchMyHoldings();
        } else {
            this.fetchMetrics();
            this.fetchUndepositedSummary();
        }
    },
    mounted() {
        this.startPolling(async () => {
            await this.fetch(null, { quiet: true });
            if (this.isSalesRep) {
                this.fetchMyHoldings();
            } else {
                this.fetchMetrics();
                this.fetchUndepositedSummary();
            }
        });
    },
    methods: {
        switchTab(tab) {
            this.activeTab = tab;
            this.filter.status = tab;
            this.fetch();
        },
        formatCurrency(value) {
            if (!value && value !== 0) return '-';
            return '\u20B1' + Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        formatDateTime(value) {
            if (!value) return '-';

            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return value;

            return date.toLocaleString(undefined, {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
            });
        },
        fetch(page_url, { quiet = false } = {}) {
            if (!quiet) {
                this.loading = true;
            }
            return axios.get('/remittances', {
                params: {
                    keyword: this.filter.keyword,
                    location_id: this.filter.location_id === null || this.filter.location_id === '' ? null : Number(this.filter.location_id),
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
                }
            })
            .catch(err => console.log(err))
            .finally(() => { if (!quiet) this.loading = false; });
        },
        openCreate() {
            this.$refs.create.show();
        },
        openView(item) {
            this.selectedRemittance = item;
            this.currentView = 'view';
        },
        closeView() {
            this.currentView = 'list';
            this.selectedRemittance = null;
        },
        handleViewReload() {
            const selectedId = this.selectedRemittance?.id;
            this.fetch().then(() => {
                if (!selectedId) {
                    this.closeView();
                    return;
                }

                const updated = this.lists.find(remittance => remittance.id === selectedId);
                if (updated) {
                    this.selectedRemittance = updated;
                } else {
                    this.closeView();
                }
            });
        },
        fetchMyHoldings() {
            axios.get('/remittances', { params: { option: 'my_holdings' } })
                .then(res => { this.myHoldings = res.data; })
                .catch(err => console.error(err));
        },
        fetchMetrics() {
            axios.get('/remittances', {
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
        fetchUndepositedSummary() {
            axios.get('/remittances', {
                params: {
                    option: 'undeposited_summary',
                    location_id: this.filter.location_id === null || this.filter.location_id === '' ? null : Number(this.filter.location_id),
                }
            })
                .then(res => { this.undepositedSummary = res.data; })
                .catch(err => console.error(err));
        },
    }
};
</script>

<style scoped>
.deposit-status-chip {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px; border-radius: 999px;
    font-size: 11px; font-weight: 700;
}
.deposit-status-chip.deposited { background: #dcfce7; color: #166534; }
.deposit-status-chip.pending   { background: #fef3c7; color: #92400e; }

.filter-segment {
    display: inline-flex;
    background: #eaf2f0;
    border-radius: 10px;
    padding: 3px;
    gap: 2px;
    margin-bottom: 0.75rem;
}

.filter-segment-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: none;
    background: transparent;
    color: #4a7a70;
    font-weight: 600;
    font-size: 0.8rem;
    padding: 0.38rem 0.85rem;
    border-radius: 8px;
    transition: all 0.18s ease;
    white-space: nowrap;
    cursor: pointer;
}

.filter-segment-btn i { font-size: 0.95rem; }

.filter-segment-btn:hover:not(.active) {
    background: rgba(61, 141, 122, 0.1);
    color: #3d8d7a;
}

.filter-segment-btn.active {
    background: #3d8d7a;
    color: #fff;
    box-shadow: 0 2px 8px rgba(61, 141, 122, 0.28);
}

.seg-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 16px;
    padding: 0 4px;
    border-radius: 8px;
    font-size: 10px;
    font-weight: 700;
    background: rgba(61, 141, 122, 0.15);
    color: #3d8d7a;
}

.filter-segment-btn.active .seg-count {
    background: rgba(255, 255, 255, 0.25);
    color: #fff;
}

.cash-on-hand-card {
    min-width: 220px;
    padding: 0.75rem 1rem;
    border-radius: 14px;
    background: linear-gradient(135deg, #f4fbf8 0%, #e7f6ef 100%);
    border: 1px solid rgba(22, 66, 60, 0.12);
    box-shadow: 0 8px 18px rgba(22, 66, 60, 0.08);
    text-align: left;
}

.undeposited-card {
    cursor: pointer;
    background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
    border-color: rgba(146, 64, 14, 0.15);
    transition: box-shadow 0.15s;
}
.undeposited-card:hover { box-shadow: 0 10px 22px rgba(146, 64, 14, 0.15); }
.undeposited-card .cash-on-hand-label { color: #92400e; }

.cash-on-hand-label {
    display: block;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #5f7f77;
}

.cash-on-hand-value {
    display: block;
    margin-top: 0.3rem;
    font-size: 1.2rem;
    font-weight: 700;
    color: #16423c;
    line-height: 1.3;
}

.cash-on-hand-sub {
    display: block;
    margin-top: 0.15rem;
    font-size: 0.72rem;
    color: #5f7f77;
}
</style>
