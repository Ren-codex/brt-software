<template>
<Head title="Users"/>
    <PageHeader title="Roles Management" pageTitle="List" />
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
                                <h4 class="header-title mb-1">Role Management</h4>
                                <p class="header-subtitle mb-0">Manage user roles and permissions</p>
                            </div>
                        </div>
                        <button class="create-btn" @click="openCreate">
                            <i class="ri-add-line"></i>
                            <span>Add Role</span>
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
                                placeholder="Search roles..." 
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
                                    <th>Type</th>
                                    <th>Definition</th>
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
                                    <td>{{ list.type }}</td>
                                    <td>{{ list.definition }}</td>
                                    <td>
                                        <div class="action-buttons">
                                            <button @click="openEdit(list,index)" class="action-btn action-btn-edit" v-b-tooltip.hover title="Edit">
                                                <i class="ri-pencil-line"></i>
                                            </button>
                                            <button @click="openDelete(list.id)" class="action-btn action-btn-delete" v-b-tooltip.hover title="Delete">
                                                <i class="ri-delete-bin-line"></i>
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
    <Create @add="fetch()" ref="create"/>
    <Delete @delete="fetch()" ref="delete"/>
</template>
<script>
import _ from 'lodash';
import Multiselect from "@vueform/multiselect";
import PageHeader from '@/Shared/Components/PageHeader.vue';
import Pagination from "@/Shared/Components/Pagination.vue";
import Create from './Modals/Create.vue';
import Delete from "@/Shared/Components/Modals/Delete.vue";

export default {
    components: { PageHeader, Pagination, Multiselect , Create ,Delete },
    props: [],
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
            page_url = page_url || '/libraries/roles';
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

        openDelete(id){
            let title = "Role";
            this.$refs.delete.show(id , title, '/libraries/roles');
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