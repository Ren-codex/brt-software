<template>
<Head title="Users"/>
    <PageHeader title="Supplier Management" pageTitle="List" />
    <BRow>
        <div class="col-md-12">
            <div class="library-card">
                <div class="library-card-header">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="header-icon">
                                <i class="ri-truck-line"></i>
                            </div>
                            <div>
                                <h4 class="header-title mb-1">Supplier Management</h4>
                                <p class="header-subtitle mb-0">Manage suppliers and vendor information</p>
                            </div>
                        </div>
                        <button class="create-btn" @click="openCreate">
                            <i class="ri-add-line"></i>
                            <span>Add Supplier</span>
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
                                placeholder="Search suppliers..." 
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
                                    <th>Address</th>
                                    <th>Contact Person/Number</th>
                                    <th>Email</th>
                                    <th>TIN</th>
                                    <th>Active</th>
                                    <th>Blacklisted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr v-for="(list,index) in lists" v-bind:key="index" @click="selectRow(index)" :class="{
                                    'bg-info-subtle': index === selectedRow,
                                    'bg-danger-subtle': list.is_active === 0 && index !== selectedRow
                                }">
                                    <td>{{ index + 1}}</td>
                                    <td>{{ list.name }}</td>
                                    <td>{{ list.address }}</td>
                                    <td>
                                        <span>{{ list.contact_person }}</span>
                                        <p class="text-muted mb-0">{{ list.contact_number }}</p>
                                    </td>
                                    <td>{{ list.email}}</td>
                                    <td>{{ list.tin}}</td>
                                    <td>
                                        <b-form-checkbox
                                            switch
                                            size="sm"
                                            :checked="list.is_active"
                                        ></b-form-checkbox>
                                    </td>
                                    <td>
                                        <b-form-checkbox
                                            switch
                                            size="sm"
                                            :checked="list.is_blacklisted"
                                        ></b-form-checkbox>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button @click="openEdit(list,index)" class="action-btn action-btn-edit" v-b-tooltip.hover title="Edit">
                                                <i class="ri-pencil-line"></i>
                                            </button>
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
    <Create @add="fetch()" :dropdowns="dropdowns" ref="create"/>
</template>
<script>
import _ from 'lodash';
import Multiselect from "@vueform/multiselect";
import PageHeader from '@/Shared/Components/PageHeader.vue';
import Pagination from "@/Shared/Components/Pagination.vue";
import Create from './Modals/Create.vue';

export default {
    components: { PageHeader, Pagination, Multiselect , Create },
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
            page_url = page_url || '/libraries/suppliers';
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


        selectRow(index) {
            if (this.selectedRow === index) {
                this.selectedRow = null;
            } else {
                this.selectedRow = index;
            }
        }
    }
}
</script>

