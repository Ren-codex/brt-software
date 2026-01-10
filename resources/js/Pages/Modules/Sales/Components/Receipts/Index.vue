<template>
    <BRow>
        <div class="col-md-9">
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
                <div class="card-body ">
                    <div class="search-section">
                        <div class="search-wrapper">
                            <i class="ri-search-line search-icon"></i>
                            <input type="text" v-model="localKeyword" @input="updateKeyword($event.target.value)"
                                placeholder="Search purchase request..." class="search-input">
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
                                    <th style="width: 12%;" class="text-center border-none">Amount Balance</th>
                                    <th style="width: 12%;" class="text-center border-none">Amount Paid</th>
                                    <th style="width: 12%;" class="text-center border-none">Amount Balance</th>
                                    <th style="width: 12%;" class="text-center border-none">Payment Mode</th>
               
                                    <th style="width: 6%;" class="text-center border-none">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="fs-12">
                                <template v-for="(list,index) in lists" :key="index">
                                    <tr @click="toggleRowExpansion(index)" class="cursor-pointer transition-all" style="transition: all 0.3s ease;">
                                        <td class="text-center">
                                            <i v-if="expandedRows.includes(index)" class="ri-arrow-down-s-line text-primary"></i>
                                            <i v-else class="ri-arrow-right-s-line text-muted"></i>
                                            {{ index + 1}}
                                        </td>
                                        <td class="text-center fw-semibold">{{ list.receipt_number }}</td>
                                        <td class="text-center">{{ list.customer?.name || '-' }}</td>
                                        <td class="text-center">{{ list.receipt_date }}</td>
                                        <td class="text-center">{{ list.balance_due }}</td>
                                        <td class="text-center">₱{{ list.amount_paid }}</td>
                                        <td class="text-center">{{ list.payment_mode }}</td>
                                        <td class="text-center">
                                            <span class="badge" :class="list.status?.slug === 'paid' ? 'bg-success' : 'bg-warning'">
                                                {{ list.status?.name || 'Unknown' }}
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
                                        <td colspan="8" class="p-0">
                                            <div class="p-4">
                                                <h6 class="text-primary mb-3">
                                                    <i class="ri-file-list-line me-2"></i>Order Details
                                                </h6>
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <div class="card border-0 shadow-sm ">
                                                            <div class="card-body">
                                                                <h6 class="card-title text-muted small mb-2">Receipt Information</h6>
                                                                <p class="mb-1"><strong>Payment Date:</strong> {{ list.payment_date }}</p>
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
                    <Pagination class="ms-2 me-2 mt-n1" v-if="meta" @fetch="fetch()" :lists="lists.length" :links="links" :pagination="meta" />
                </div>
            </div>
        </div>
        <div class="col-md-3  bg-light">
            <div class="card shadow-lg border-0 bg-light" >
                <div class="card-header border-0  " >
                    <h4 >
                        <i class="ri-dashboard-line "></i> Quick Stats
                        <hr class="mb-0">
                    </h4>
                </div>
     
                <div class="card-body">
                    <div class="metric-card mb-3 p-3 e bg-opacity-10 rounded" style="backdrop-filter: blur(10px);">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title  bg-opacity-25 rounded">
                                    <i class="ri-shopping-cart-line  fs-18"></i>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class=" fw-semibold fs-12 mb-1">Total Receipts</p>
                                <h4 class="mb-0 ">{{ metrics.total_receipts }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="metric-card mb-3 p-3 bg-opacity-10 rounded" style="backdrop-filter: blur(10px);">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title  bg-opacity-25 rounded">
                                    <i class="ri-money-dollar-circle-line  fs-18"></i>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class=" fw-semibold fs-12 mb-1">Total Amount Collected</p>
                                <h4 class="mb-0 ">₱{{ metrics.total_amount_collected }}</h4>
                            </div>
                        </div>
                    </div>
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
    props: ['dropdowns'],
    data(){
        return {

            lists: [],
            meta: {},
            links: {},
            filter: {
                keyword: null
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
                    count: 10,
                    option: 'lists'
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



         getCustomer(customer_id){
            const product = this.dropdowns.customers.find(u => u.value === customer_id);
            return customer ? customer : [];
        },
    }
}
</script>
