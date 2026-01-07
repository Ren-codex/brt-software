<template>
<Head title="Users"/>
    <PageHeader title="Products Management" pageTitle="List" />
    <BRow>
        <div class="col-md-12">
            <div class="library-card">
                <div class="library-card-header">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="header-icon">
                                <i class="ri-shield-user-line"></i>
                            </div>
                            <div>
                                <h4 class="header-title mb-1">Product Management</h4>
                                <p class="header-subtitle mb-0">Manage products</p>
                            </div>
                        </div>
                        <button class="create-btn" @click="openCreate">
                            <i class="ri-add-line"></i>
                            <span>Add Product</span>
                        </button>
                    </div>
                </div>

                <div class="library-card-body">
                    <div class="search-section">
                        <div class="search-wrapper">
                            <i class="ri-search-line search-icon"></i>
                            <input 
                                type="text" 
                                v-model="filter.keyword" 
                                placeholder="Search products..." 
                                class="search-input"
                            >
                        </div>
                    </div>

                    <div class="table-section">
                        <div class="table-responsive">
                        <table class="table align-middle table-centered mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Pack Size</th>
                                    <th>Unit</th>
                                    <th>Price</th>
                                    <th>Active</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr v-for="(list,index) in lists" v-bind:key="index" @click="selectRow(index)" :class="{
                                    'bg-info-subtle': index === selectedRow,
                                    'bg-danger-subtle': list.is_active === 0 && index !== selectedRow
                                }">
                                    <td>{{ index + 1}}</td>
                                    <td>{{ list.brand?.name }}</td>
                                    <td>{{ list.pack_size }}</td>
                                    <td>{{ list.unit.name }}</td>
                                    <td>{{ formatCurrency(list.price) }}</td>
                                    <td>
                                    <b-form-checkbox
                                        :checked="list.is_active === 1"
                                        @change="toggleActive(list)"
                                        switch
                                        size="md"
                                    />
                                    </td>
                                    <td>
                                    <div class="action-buttons">
                                        <button @click="openEdit(list, index)" class="action-btn action-btn-edit" v-b-tooltip.hover title="Edit">
                                        <i class="ri-pencil-line"></i>
                                        </button>
                                        <!-- <button @click="onDelete(list.id)" class="action-btn action-btn-delete" v-b-tooltip.hover title="Delete">
                                        <i class="ri-delete-bin-line"></i>
                                        </button> -->
                                    </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        </div>
                    </div>

                    <div class="pagination-section">
                        <Pagination v-if="meta" @fetch="fetch" :lists="lists.length" :links="links" :pagination="meta" />
                    </div>
                </div>
            </div>
        </div>
    </BRow>
    <CreateProduct @add="fetch()" ref="createProduct" :dropdowns="dropdowns"/>
    <Delete ref="delete" @delete="handleDeleteSuccess"/>
</template>
<script>
import _ from 'lodash';
import PageHeader from '@/Shared/Components/PageHeader.vue';
import Pagination from "@/Shared/Components/Pagination.vue";
import CreateProduct from '../../Inventory/Modal/CreateProductModal.vue';
import Delete from "@/Shared/Components/Modals/Delete.vue";

export default {
    components: { PageHeader, Pagination, CreateProduct ,Delete },
    props: {
        dropdowns: Object,
    },
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
            units: []
        }
    },
    watch: {
        "filter.keyword"(newVal){
            this.checkSearchStr(newVal);
        }
    },
    created(){
       this.fetch();
    },
    methods: {
        checkSearchStr: _.debounce(function(string) {
            this.fetch();
        }, 300),
        fetch(page_url){
            page_url = page_url || '/libraries/products';
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
            this.$refs.createProduct.show();
        },

        openEdit(data,index){
            this.selectedRow = index;
            this.$refs.createProduct.edit(data , index);
        },

        selectRow(index) {
            this.selectedRow = this.selectedRow === index ? null : index;
        },

        toggleActive(product) {
            const updatedStatus = product.is_active === 1 ? 0 : 1;
            axios.patch(`/libraries/products/${product.id}/toggle-active`, {
                is_active: updatedStatus,
            })
            .then(response => {
                if (response.data.status) {
                product.is_active = updatedStatus;
                this.$emit('toast', 'Status updated successfully');
                } else {
                console.error('Failed to update product active status:', response.data.message);
                }
            })
            .catch(error => {
                console.error('Error updating product active status:', error);
            });
        },

        formatCurrency(value) {
            if (typeof value !== 'number') {
                return value;
            }
            return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(value);
        },
    }
}
</script>