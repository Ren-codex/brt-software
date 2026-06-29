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
                                        <strong class="cash-on-hand-value">{{ formatCurrency(metrics.total_amount_remitted) }}</strong>
                                    </div>
                                    <button class="acct-btn-secondary" @click="currentView = 'summary'">
                                        <i class="ri-bar-chart-grouped-line"></i>
                                        Daily Summary
                                    </button>
                                    <button class="acct-btn-primary" @click="openCreate">
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
                                    <button :class="['filter-segment-btn', activeTab === 'open' ? 'active' : '']" @click="switchTab('open')">
                                        <i class="ri-folder-open-line"></i>
                                        <span>Open</span>
                                        <span v-if="activeTab === 'open'" class="seg-count">{{ meta.total ?? 0 }}</span>
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
                                </div>

                                <div v-show="activeTab === 'open'" class="table-responsive">
                                    <table class="table sales-table mb-0">
                                        <thead>
                                            <tr>
                                                <th style="width:3%">#</th>
                                                <th class="text-center" style="width:15%">Remittance No.</th>
                                                <th class="text-center" style="width:15%">Date</th>
                                                <th class="text-end" style="width:15%">Amount</th>
                                                <th class="text-center" style="width:15%">Status</th>
                                                <th class="text-center" style="width:20%">Sales Rep</th>
                                                <th class="text-center" style="width:7%">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
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
                                                <td class="text-center">{{ item.date || item.remittance_date }}</td>
                                                <td class="text-end">{{ formatCurrency(item.total_amount) }}</td>
                                                <td class="text-center">
                                                    <span :style="{ backgroundColor: item.status?.bg_color || '#6c757d', color: '#fff', padding: '3px 10px', borderRadius: '12px', fontSize: '11px' }">
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
                                        </tbody>
                                    </table>
                                </div>


                                <div v-show="activeTab === 'liquidated'" class="table-responsive">
                                    <table class="table sales-table mb-0">
                                        <thead>
                                            <tr>
                                                <th style="width:3%">#</th>
                                                <th class="text-center" style="width:15%">Remittance No.</th>
                                                <th class="text-center" style="width:15%">Date</th>
                                                <th class="text-end" style="width:15%">Amount</th>
                                                <th class="text-center" style="width:20%">Sales Rep</th>
                                                <th class="text-center" style="width:7%">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-if="liquidatedRemittance.length === 0">
                                                <td colspan="6">
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
                                                <td class="text-center">{{ item.date || item.remittance_date }}</td>
                                                <td class="text-end">{{ formatCurrency(item.total_amount) }}</td>
                                                <td class="text-center">{{ item.created_by?.fullname || '-' }}</td>
                                                <td class="text-center">
                                                    <button @click.stop="openView(item)" class="action-btn info" title="View">
                                                        <i class="ri-eye-line"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div v-show="activeTab === 'disapproved'" class="table-responsive">
                                    <table class="table sales-table mb-0">
                                        <thead>
                                            <tr>
                                                <th style="width:3%">#</th>
                                                <th class="text-center" style="width:15%">Remittance No.</th>
                                                <th class="text-center" style="width:15%">Date</th>
                                                <th class="text-end" style="width:15%">Amount</th>
                                                <th class="text-center" style="width:20%">Sales Rep</th>
                                                <th class="text-center" style="width:7%">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
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
                                                <td class="text-center">{{ item.date || item.remittance_date }}</td>
                                                <td class="text-end">{{ formatCurrency(item.total_amount) }}</td>
                                                <td class="text-center">{{ item.created_by?.fullname || '-' }}</td>
                                                <td class="text-center">
                                                    <button @click.stop="openView(item)" class="action-btn info" title="View">
                                                        <i class="ri-eye-line"></i>
                                                    </button>
                                                </td>
                                            </tr>
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

export default {
    components: { Pagination, Create, View, SummaryView },
    props: ['dropdowns', 'isExternal'],
    data() {
        return {
            lists: [],
            meta: {},
            links: {},
            filter: {
                keyword: '',
                location_id: null,
                status: 'open'
            },
            activeTab: 'open',
            currentView: 'list',
            selectedRemittance: null,
            metrics: {
                total_remittances: 0,
                total_amount_remitted: 0,
                today_remittances: 0,
                open_remittances: 0
            },
            myHoldings: {
                total_amount: 0,
                receipt_count: 0,
            }
        };
    },
    computed: {
        openRemittance() { return this.lists; },
        liquidatedRemittance() { return this.lists; },
        disapprovedRemittance() { return this.lists; },
        isSalesRep() {
            const roles = this.$page.props.roles ?? [];
            return roles.includes('Sales Rep');
        },
    },
    created() {
        this.debouncedSearch = _.debounce(this.fetch, 500);
        this.fetch();
        if (this.isSalesRep) {
            this.fetchMyHoldings();
        } else {
            this.fetchMetrics();
        }
    },
    methods: {
        switchTab(tab) {
            this.activeTab = tab;
            this.filter.status = tab;
            this.fetch();
        },
        formatCurrency(value) {
            if (!value && value !== 0) return '-';
            return '\u20B1' + Number(value).toFixed(2);
        },
        fetch() {
            return axios.get('/remittances', {
                params: {
                    keyword: this.filter.keyword,
                    location_id: this.filter.location_id === null || this.filter.location_id === '' ? null : Number(this.filter.location_id),
                    status: this.filter.status,
                    count: 10,
                    option: 'lists',
                    is_external: this.isExternal ? 1 : 0
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
        }
    }
};
</script>

<style scoped>
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
