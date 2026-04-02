<template>
    <div>
        <template v-if="currentView === 'list'">
            <BRow>
                <div class="col-md-12 mb-4">
                    <div class="library-card">
                        <div class="library-card-header">
                            <div class="d-flex align-items-center justify-content-between">
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
                                    <div class="cash-on-hand-card">
                                        <span class="cash-on-hand-label">Total Cash on Hand</span>
                                        <strong class="cash-on-hand-value">{{ formatCurrency(metrics.total_amount_remitted) }}</strong>
                                    </div>
                                    <button class="create-btn" @click="openCreate">
                                        <i class="ri-add-line"></i>
                                        <span>Prepare Remittance</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="card-body bg-white m-2 p-3">
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

                            <div class="table-responsive table-card mt-2">
                                <b-tabs v-model="tabIndex">
                                    <b-tab title="Open">
                                        <table class="table align-middle table-hover mb-0" style="border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                                            <thead style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                                                <tr class="fs-12 fw-bold text-muted">
                                                    <th style="width: 3%; border: none;">#</th>
                                                    <th style="width: 15%;" class="text-center border-none">Remittance No.</th>
                                                    <th style="width: 15%;" class="text-center border-none">Date</th>
                                                    <th style="width: 15%;" class="text-center border-none">Amount</th>
                                                    <th style="width: 15%;" class="text-center border-none">Status</th>
                                                    <th style="width: 20%;" class="text-center border-none">Collector Name</th>
                                                    <th style="width: 7%;" class="text-center border-none">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody class="fs-12">
                                                <tr
                                                    v-for="(item, index) in openRemittance"
                                                    :key="item.id || index"
                                                    class="cursor-pointer"
                                                    @click="openView(item)"
                                                >
                                                    <td class="text-center">{{ index + 1 }}</td>
                                                    <td class="text-center">{{ item.remittance_no || '-' }}</td>
                                                    <td class="text-center">{{ item.date || item.remittance_date }}</td>
                                                    <td class="text-center">{{ formatCurrency(item.total_amount) }}</td>
                                                    <td class="text-center">
                                                        <span :style="{ 'background-color': item.status.name === 'disapproved' ? '#ff0000' : '#6c757d', color: '#fff' }" class="px-3 py-2 rounded-pill badge">
                                                            {{ item.status.name }}
                                                        </span>
                                                    </td>
                                                    <td class="text-center">{{ item.created_by?.fullname || '-' }}</td>
                                                    <td class="text-center">
                                                        <b-button @click.stop="openView(item)" variant="outline-default" size="sm" class="btn-icon rounded-circle">
                                                            <i class="ri-eye-line"></i>
                                                        </b-button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <div v-if="openRemittance.length === 0" class="text-center py-5">
                                            <i class="ri-inbox-line fs-48 text-muted"></i>
                                            <p class="text-muted mt-2">No remittance found</p>
                                        </div>
                                    </b-tab>

                                    <b-tab title="Liquidated">
                                        <table class="table align-middle table-hover mb-0" style="border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                                            <thead style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                                                <tr class="fs-12 fw-bold text-muted">
                                                    <th style="width: 3%; border: none;">#</th>
                                                    <th style="width: 15%;" class="text-center border-none">Remittance No.</th>
                                                    <th style="width: 15%;" class="text-center border-none">Date</th>
                                                    <th style="width: 15%;" class="text-center border-none">Amount</th>
                                                    <th style="width: 20%;" class="text-center border-none">Collector Name</th>
                                                    <th style="width: 7%;" class="text-center border-none">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody class="fs-12">
                                                <tr
                                                    v-for="(item, index) in liquidatedRemittance"
                                                    :key="item.id || index"
                                                    class="cursor-pointer"
                                                    @click="openView(item)"
                                                >
                                                    <td class="text-center">{{ index + 1 }}</td>
                                                    <td class="text-center">{{ item.remittance_no || '-' }}</td>
                                                    <td class="text-center">{{ item.date || item.remittance_date }}</td>
                                                    <td class="text-center">{{ formatCurrency(item.total_amount) }}</td>
                                                    <td class="text-center">{{ item.created_by?.fullname || '-' }}</td>
                                                    <td class="text-center">
                                                        <b-button @click.stop="openView(item)" variant="outline-default" size="sm" class="btn-icon rounded-circle">
                                                            <i class="ri-eye-line"></i>
                                                        </b-button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <div v-if="liquidatedRemittance.length === 0" class="text-center py-5">
                                            <i class="ri-inbox-line fs-48 text-muted"></i>
                                            <p class="text-muted mt-2">No remittance found</p>
                                        </div>
                                    </b-tab>

                                    <b-tab title="Disapproved">
                                        <table class="table align-middle table-hover mb-0" style="border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                                            <thead style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                                                <tr class="fs-12 fw-bold text-muted">
                                                    <th style="width: 3%; border: none;">#</th>
                                                    <th style="width: 15%;" class="text-center border-none">Remittance No.</th>
                                                    <th style="width: 15%;" class="text-center border-none">Date</th>
                                                    <th style="width: 15%;" class="text-center border-none">Amount</th>
                                                    <th style="width: 20%;" class="text-center border-none">Collector Name</th>
                                                    <th style="width: 7%;" class="text-center border-none">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody class="fs-12">
                                                <tr
                                                    v-for="(item, index) in disapprovedRemittance"
                                                    :key="item.id || index"
                                                    class="cursor-pointer"
                                                    @click="openView(item)"
                                                >
                                                    <td class="text-center">{{ index + 1 }}</td>
                                                    <td class="text-center">{{ item.remittance_no || '-' }}</td>
                                                    <td class="text-center">{{ item.date || item.remittance_date }}</td>
                                                    <td class="text-center">{{ formatCurrency(item.total_amount) }}</td>
                                                    <td class="text-center">{{ item.created_by?.fullname || '-' }}</td>
                                                    <td class="text-center">
                                                        <b-button @click.stop="openView(item)" variant="outline-default" size="sm" class="btn-icon rounded-circle">
                                                            <i class="ri-eye-line"></i>
                                                        </b-button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <div v-if="disapprovedRemittance.length === 0" class="text-center py-5">
                                            <i class="ri-inbox-line fs-48 text-muted"></i>
                                            <p class="text-muted mt-2">No remittance found</p>
                                        </div>
                                    </b-tab>
                                </b-tabs>
                            </div>
                        </div>
                        <div class="card-footer bg-light border-0">
                            <Pagination class="ms-2 me-2 mt-n1" v-if="meta" @fetch="fetch" :lists="lists.length" :links="links" :pagination="meta" />
                        </div>
                    </div>
                </div>
            </BRow>
            <Create @add="fetch" ref="create" />
        </template>

        <View
            v-else-if="selectedRemittance"
            :item="selectedRemittance"
            :dropdowns="dropdowns"
            @back="closeView"
            @reload="handleViewReload"
        />
    </div>
</template>

<script>
import _ from 'lodash';
import Pagination from "@/Shared/Components/Pagination.vue";
import Create from './Modals/Create.vue';
import View from './View.vue';

export default {
    components: { Pagination, Create, View },
    props: ['dropdowns', 'isExternal'],
    data() {
        return {
            lists: [],
            meta: {},
            links: {},
            filter: {
                keyword: '',
                location_id: null,
                status: null
            },
            tabIndex: 0,
            currentView: 'list',
            selectedRemittance: null,
            metrics: {
                total_remittances: 0,
                total_amount_remitted: 0,
                today_remittances: 0,
                open_remittances: 0
            }
        };
    },
    computed: {
        openRemittance() {
            return this.lists.filter(r => r.status.slug === 'open');
        },
        liquidatedRemittance() {
            return this.lists.filter(r => r.status.slug === 'liquidated');
        },
        disapprovedRemittance() {
            return this.lists.filter(r => r.status.slug === 'disapproved');
        }
    },
    created() {
        this.debouncedSearch = _.debounce(this.fetch, 500);
        this.fetch();
        this.fetchMetrics();
    },
    methods: {
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
.badge {
    display: inline-block;
    padding: 4px 8px;
    font-size: 10px;
    font-weight: 600;
    color: white;
    background-color: #0d6efd;
    border-radius: 12px;
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
</style>
