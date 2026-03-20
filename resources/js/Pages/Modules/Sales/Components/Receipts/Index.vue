<template>
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
                                <h4 class="header-title mb-1">Receipts</h4>
                                <p class="header-subtitle mb-0">A comprehensive list of Receipts</p>
                            </div>
                        </div>
                      
                    </div>

                </div>
                <div class="card-body m-2 p-3">
                    <div class="search-section">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="search-wrapper">
                                    <i class="ri-search-line search-icon"></i>
                                    <input type="text" v-model="filter.keyword" @input="debouncedSearch"
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
                            <div class="col-md-3">
                                <div class="search-wrapper">
                                    <i class="ri-flag-line search-icon"></i>
                                    <select v-model="filter.status" @change="fetch()" class="search-input">
                                        <option :value="null">All Status</option>
                                        <option v-for="status in dropdowns.sales_statuses" :key="status.value" :value="status.slug">
                                            {{ status.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>

                    </div>



                    <div class="table-responsive table-card">
                        <table class="table align-middle table-hover mb-0" style="border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                            <thead style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                                <tr class="fs-12 fw-bold text-muted">
                                    <th style="width: 3%; border: none;">#</th>
                                    <th style="width: 12%;" class="text-center border-none">OR Number</th>
                                    <th style="width: 12%;" class="text-center border-none">Customer</th>
                                    <th style="width: 12%;" class="text-center border-none">Payment Date</th>
                                    <th style="width: 10%;" class="text-center border-none">Type</th>
                                    <th style="width: 12%;" class="text-center border-none">Amount Balance</th>
                                    <th style="width: 12%;" class="text-center border-none">Amount Paid</th>
                                    <th style="width: 12%;" class="text-center border-none">Payment Mode</th>
                                    <th style="width: 12%;" class="text-center border-none">Status</th>
                                    <th style="width: 6%;" class="text-center border-none">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="fs-12">
                                <tr v-if="lists.length === 0">
                                    <td colspan="10" class="text-center text-muted py-4">
                                        <i class="ri-shopping-cart-line fs-1 text-muted mb-2"></i>
                                        <div>No receipts found.</div>
                                        <small>Receipts will appear here once they are created.</small>
                                    </td>
                                </tr>
                                <template v-for="(list,index) in lists" :key="index">
                                    <tr @click="toggleRowExpansion(index)"
                                        :class="{
                                            'main-table-row': true,
                                            'unremitted-row': list.is_unremitted_past_day
                                        }"
                                        class="cursor-pointer transition-all" style="transition: all 0.3s ease;">
                                        <td class="text-center">
                                            <i v-if="expandedRows.includes(index)" class="ri-arrow-down-s-line text-primary"></i>
                                            <i v-else class="ri-arrow-right-s-line text-muted"></i>
                                            {{ index + 1}}
                                        </td>
                                        <td class="text-center fw-semibold">{{ list.receipt_number }}</td>
                                        <td class="text-center">{{ list.customer?.name || '-' }}</td>
                                        <td class="text-center">{{ list.receipt_date }}</td>
                                        <td class="text-center">
                                            <span class="badge" :class="getReceiptTypeClass(list.receipt_type)">
                                                {{ getReceiptTypeLabel(list.receipt_type) }}
                                            </span>
                                        </td>
                                        <td class="text-center">{{ list.balance_due }}</td>
                                        <td class="text-center">₱{{ list.amount_paid }}</td>
                                        <td class="text-center">{{ list.payment_mode }}</td>
                                        <td class="text-center">
                                            <span
                                                :style="{ backgroundColor: list.status?.bg_color || '#6c757d', color: '#fff', padding: '4px 8px', borderRadius: '12px' }">
                                                {{ list.status?.name || 'Unknown' }}
                                            </span>
                                            <span v-if="list.is_unremitted_past_day" class="unremitted-badge ms-1">
                                                Unremitted
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-1">
                                                <b-button @click.stop="onPrint(list.id)" variant="outline-info" v-b-tooltip.hover title="Print" size="sm" class="btn-icon rounded-circle">
                                                    <i class="ri-printer-line"></i>
                                                </b-button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="expandedRows.includes(index)" class="bg-light">
                                        <td colspan="10" class="p-0">
                                            <div class="p-4">
                                                <h6 class="text-primary mb-3">
                                                    <i class="ri-file-list-line me-2"></i>Order Details
                                                </h6>
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <div class="card border-0 shadow-sm ">
                                                            <div class="card-body">
                                                <h6 class="card-title text-muted small mb-2">Receipt Information</h6>
                                                                <p class="mb-1"><strong>Receipt Date:</strong> {{ list.receipt_date }}</p>
                                                                <p class="mb-1"><strong>Receipt Type:</strong> {{ getReceiptTypeLabel(list.receipt_type) }}</p>
                                                                <p v-if="list.source_receipt?.receipt_number" class="mb-1"><strong>Source Receipt:</strong> {{ list.source_receipt.receipt_number }}</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="card border-0 shadow-sm ">
                                                          
                                                            <div class="card-body">
                                                                <h6 class="card-title text-muted small mb-2">Customer Information</h6>
                                                                <p class="mb-1"><strong>Customer:</strong> {{ list.customer?.name || 'N/A' }}</p>
                                                                <p class="mb-1"><strong>Contact:</strong> {{ list.customer?.contact_number || 'N/A' }}</p>
                                                                <p class="mb-1"><strong>Email:</strong> {{ list.customer?.email || 'N/A' }}</p>
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
                <div class="card-footer bg-light border-0">
                    <Pagination class="ms-2 me-2 mt-n1" v-if="meta" @fetch="fetch" :lists="lists.length" :links="links" :pagination="meta" />
                </div>
            </div>
        </div>
    </BRow>

    
</template>
<script>
import _ from 'lodash';
import PageHeader from '@/Shared/Components/PageHeader.vue';
import Pagination from "@/Shared/Components/Pagination.vue";



export default {
    components: { PageHeader, Pagination },
    props: ['dropdowns', 'isExternal'],
    data(){
        return {

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

            expandedRows: []
        }
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
    methods: {
        checkSearchStr: _.debounce(function(string) {
            this.fetch();
        }, 300),
        fetch(page_url){
            page_url = page_url || '/receipts';
            axios.get(page_url,{
                params : {
                    keyword: this.filter.keyword,
                    location_id: this.filter.location_id,
                    status: this.filter.status,
                    count: 10,
                    option: 'lists',
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
            .catch(err => console.log(err));
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
            window.open(`/receipts/${id}?option=print&type=receipt`);
        },
        getReceiptTypeLabel(type) {
            if (type === 'updated') return 'Updated Receipt';
            if (type === 'refund') return 'Refund';
            return 'Payment';
        },
        getReceiptTypeClass(type) {
            if (type === 'updated') return 'bg-info text-dark';
            if (type === 'refund') return 'bg-warning text-dark';
            return 'bg-primary';
        },

         getCustomer(customer_id){
            const customer = this.dropdowns.customers.find(u => u.value === customer_id);
            return customer ? customer : [];
        },
    }
}
</script>
<style scoped>
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
