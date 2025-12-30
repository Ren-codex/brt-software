<template>
    <BRow>
        <div class="col-md-10">
            <div class="card shadow-lg border-0" >
                <div class="card-header bg-primary"  >
                    <div class="d-flex mb-n3">
                        <div class="flex-shrink-0 me-3">
                            <div style="height:2.5rem;width:2.5rem;">
                                <span class="avatar-title rounded p-2 mt-n1">
                                    <i class="ri-shopping-cart-line fs-24"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex-grow-1 text-white">
                            <h5 class=" fs-14"><span class="text-white">Sales Orders</span></h5>
                            <p class=" text-truncate-two-lines fs-12">A comprehensive list of Sales Orders</p>
                        </div>

                    </div>
                </div>
                <div class="card-body ">
                    <b-row class="mb-3 ms-1 me-1">
                        <b-col lg>
                            <div class="input-group">
                                <span class="input-group-text bg-primary text-white border-primary">
                                    <i class="ri-search-line"></i>
                                </span>
                                <input type="text" v-model="filter.keyword" @input="debouncedSearch" placeholder="Search Sales Order" class="form-control border-primary">
                                <b-button type="button" variant="primary" @click="openCreate" class="d-flex align-items-center">
                                    <i class="ri-add-circle-fill me-1"></i> Create Order
                                </b-button>
                            </div>
                        </b-col>
                    </b-row>

                    <div class="mb-4">
                        <b-button @click="showStock = !showStock" variant="outline-primary" size="sm" class="mb-3">
                            <i class="ri-eye-line me-1"></i> Stock Availability
                        </b-button>
                        <b-collapse v-model="showStock">
                            <div class="card border-primary shadow-sm" style="border-radius: 10px;">
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <div class="p-3 bg-light rounded">
                                                <p class="mb-1 text-muted small">Total KG Left</p>
                                                <h5 class="text-primary mb-0">{{ stock.total_kg_left || 0 }} kg</h5>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="p-3 bg-light rounded">
                                                <p class="mb-1 text-muted small">5kg Sacks Left</p>
                                                <h5 class="text-success mb-0">{{ stock.five_kg_sacks_left || 0 }}</h5>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="p-3 bg-light rounded">
                                                <p class="mb-1 text-muted small">10kg Sacks Left</p>
                                                <h5 class="text-info mb-0">{{ stock.ten_kg_sacks_left || 0 }}</h5>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="p-3 bg-light rounded">
                                                <p class="mb-1 text-muted small">25kg Sacks Left</p>
                                                <h5 class="text-warning mb-0">{{ stock.twenty_five_kg_sacks_left || 0 }}</h5>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="stock.products && stock.products.length > 0" class="mt-3">
                                        <h6 class="text-muted">Product Details by Brand:</h6>
                                        <div v-for="(brandGroup, brandIndex) in groupedProducts" :key="brandIndex" class="mb-4">
                                            <h6 class="text-primary mb-2">
                                                <i class="ri-building-line me-2"></i>{{ brandGroup.brand || 'No Brand' }}
                                            </h6>
                                            <div class="row">
                                                <div v-for="product in brandGroup.products" :key="product.product_name" class="col-md-6 mb-2">
                                                    <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
                                                        <span class="small">{{ product.product_name }}</span>
                                                        <span class="badge bg-secondary">{{ product.total_quantity }} x {{ product.pack_size }} {{ product.unit }} ({{ product.total_kg }} kg)</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </b-collapse>
                    </div>

                    <div class="table-responsive table-card">
                        <table class="table align-middle table-hover mb-0" style="border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                            <thead style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                                <tr class="fs-12 fw-bold text-muted">
                                    <th style="width: 3%; border: none;">#</th>
                                    <th style="width: 12%;" class="text-center border-none">Order Number</th>
                                    <th style="width: 12%;" class="text-center border-none">Customer</th>
                                    <th style="width: 12%;" class="text-center border-none">Date</th>
                                    <th style="width: 12%;" class="text-center border-none">Status</th>
                                    <th style="width: 12%;" class="text-center border-none">Payment Mode</th>
                                    <th style="width: 12%;" class="text-center border-none">Payment Term</th>
                                    <th style="width: 6%;" class="text-center border-none">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="fs-12">
                                <template v-for="(list,index) in lists" :key="index">
                                    <tr @click="toggleRowExpansion(index)" :class="{
                                        'bg-primary bg-opacity-10': index === selectedRow,
                                        'cursor-pointer': true
                                    }" class="transition-all" style="transition: all 0.3s ease;">
                                        <td class="text-center">
                                            <i v-if="expandedRows.includes(index)" class="ri-arrow-down-s-line text-primary"></i>
                                            <i v-else class="ri-arrow-right-s-line text-muted"></i>
                                            {{ index + 1}}
                                        </td>
                                        <td class="text-center fw-semibold">{{ list.so_number }}</td>
                                        <td class="text-center">{{ list.customer?.name || '-' }}</td>
                                        <td class="text-center">{{ list.created_at }}</td>
                                        <td class="text-center">
                                            <b-badge :style="{ 'background-color': list.status?.bg_color, color: '#fff' }" class="px-3 py-2 rounded-pill">
                                                {{ list.status?.name }}
                                            </b-badge>
                                        </td>
                                        <td class="text-center">{{ list.payment_mode }}</td>
                                        <td class="text-center">{{ list.payment_term }}</td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-1">
                                                <b-button @click.stop="onSalesAdjustment(list.id)" variant="outline-secondary" v-b-tooltip.hover title="Sales Adjustment" size="sm" class="btn-icon rounded-circle">
                                                    <i class="ri-refund-line"></i>
                                                </b-button>
                                                <b-button @click.stop="onPrint(list.id)" variant="outline-info" v-b-tooltip.hover title="Print" size="sm" class="btn-icon rounded-circle">
                                                    <i class="ri-printer-line"></i>
                                                </b-button>
                                                <b-button @click.stop="openEdit(list,index)" variant="outline-primary" v-b-tooltip.hover title="Edit" size="sm" class="btn-icon rounded-circle">
                                                    <i class="ri-pencil-fill"></i>
                                                </b-button>
                                                <b-button @click.stop="onCancel(list.id)" variant="outline-danger" v-b-tooltip.hover title="Delete" size="sm" class="btn-icon rounded-circle">
                                                    <i class="ri-close-line"></i>
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
                                                                <h6 class="card-title text-muted small mb-2">Order Information</h6>
                                                                <p class="mb-1"><strong>Order Date:</strong> {{ list.order_date }}</p>
                                                                <p class="mb-1"><strong>Added By:</strong> {{ list.added_by?.name || '-' }}</p>
                                                                <p class="mb-0"><strong>Transferred To:</strong> {{ list.transferred_to || '-' }}</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="card border-0 shadow-sm ">
                                                          
                                                            <div class="card-body">
                                                                <h6 class="card-title text-muted small mb-2">Items</h6>
                                                                <div v-if="list.items && list.items.length > 0">
                                                                    <table class="table table-sm table-borderless mb-0">
                                                                       <thead>
                                                                        <tr>
                                                                            <th class="fw-semibold">Product Name</th>
                                                                            <th class=" fw-semibold">Quantity</th>
                                                                            <th class=" fw-semibold">
                                                                                Price
                                                                            </th>
                                                                        </tr>
                                                                       </thead>      
                                                                       <tbody>
                                                                        <tr v-for="item in list.items" :key="item.id">
                                                                            <td>{{ getProduct(item.product_id).name || 'Unknown Product' }}</td>
                                                                            <td >
                                                                                <span class="badge bg-primary">{{ item.quantity }} {{ item.unit }}</span>
                                                                            </td>
                                                                            <td >
                                                                                ₱{{ item.price }}
                                                                            </td>
                                                                        </tr>
                                                                       </tbody>
                                                                    </table>
                                                                    <!-- <div v-for="item in list.items" :key="item.id" class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                                                                        <span>{{ getProduct(item.product_id).name || 'Unknown Product' }}</span>
                                                                        <span class="badge bg-primary">{{ item.quantity }} {{ item.unit }}</span>
                                                                    </div> -->
                                                                </div>
                                                                <p v-else class="text-muted mb-0">No items found</p>
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
        <div class="col-md-2  bg-light">
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
                                <p class=" fw-semibold fs-12 mb-1">Total Sales Orders</p>
                                <h4 class="mb-0 ">{{ metrics.total_sales_orders }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="metric-card mb-3 p-3 bg-opacity-10 rounded" style="backdrop-filter: blur(10px);">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title  bg-opacity-25 rounded">
                                    <i class="ri-calendar-line  fs-18"></i>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class=" fw-semibold fs-12 mb-1">Today's Orders</p>
                                <h4 class="mb-0 ">{{ metrics.today_orders }}</h4>
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
                                <p class=" fw-semibold fs-12 mb-1">Total Revenue</p>
                                <h4 class="mb-0 ">₱{{ metrics.total_revenue?.toFixed(2) }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="metric-card p-3  bg-opacity-10 rounded" style="backdrop-filter: blur(10px);">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title  bg-opacity-25 rounded">
                                    <i class="ri-time-line  fs-18"></i>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class=" fw-semibold fs-12 mb-1">Pending Orders</p>
                                <h4 class="mb-0 ">{{ metrics.pending_orders }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </BRow>
    <Create @add="fetch()" :dropdowns="dropdowns" ref="create"/>
    <Cancel @cancel="fetch()" ref="cancel"/>
    <Adjustment @update="fetch()"  ref="adjustment"/>
</template>
<script>
import _ from 'lodash';
import Multiselect from "@vueform/multiselect";
import PageHeader from '@/Shared/Components/PageHeader.vue';
import Pagination from "@/Shared/Components/Pagination.vue";
import Cancel from './Modals/Cancel.vue';
import Create from './Modals/Create.vue';
import Adjustment from './Modals/Adjustment.vue';

export default {
    components: { PageHeader, Pagination, Multiselect , Create, Cancel, Adjustment },
    props: ['dropdowns'],
    data(){
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
                total_sales_orders: 0,
                today_orders: 0,
                total_revenue: 0,
                pending_orders: 0
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
        "filter.keyword"(newVal){
            this.checkSearchStr(newVal);
        }
    },
    created(){
       this.fetch();
       this.fetchMetrics();
       this.fetchStock();
    },
    methods: {
        checkSearchStr: _.debounce(function(string) {
            this.fetch();
        }, 300),
        fetch(page_url){
            page_url = page_url || '/sales-orders';
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
        openCreate(){
            this.$refs.create.show();
        },

        openEdit(data,index){
            this.selectedRow = index;
            this.$refs.create.edit(data , index);
        },

        onCancel(id){
            let title = "Sales Order";
            this.$refs.cancel.show(id , title, '/sales-orders');
        },

        onPrint(id){
            window.open(`/sales-orders/${id}?option=print&type=sales_order`);
        },

        onSalesAdjustment(id){
            let title = "Sales Order";
            this.$refs.adjustment.show(id);
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
            axios.get('/sales-orders',{
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

        fetchStock(){
            axios.get('/sales-orders',{
                params : {
                    option: 'stock'
                }
            })
            .then(response => {
                if(response){
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
        },

         getProduct(product_id){
            const product = this.dropdowns.products.find(u => u.value === product_id);
            return product ? product : [];
        },
    }
}
</script>
