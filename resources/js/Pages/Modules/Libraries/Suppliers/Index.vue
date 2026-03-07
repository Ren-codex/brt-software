<template>
    <PageHeader :title="currentView === 'list' ? 'Supplier Management' : 'Supplier Details'" :pageTitle="currentView === 'list' ? 'List' : 'Details'" />
    <BRow>
        <div class="col-md-12">
            <div class="library-card">
                <div class="library-card-header" v-if="currentView === 'list'">
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

                <div class="library-card-body" v-if="currentView === 'list'">
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
                        <div class="table-responsive" style="overflow: visible; max-height: none;">
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
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>

                                <tbody class="fs-12">
                                    <tr 
                                        v-for="(list,index) in lists" 
                                        v-bind:key="list.id" 
                                        @click="openView(list)"
                                        style="cursor: pointer;"
                                        :style="getRowStyle(list)"
                                    >
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
                                                @change="toggleActive(list)"
                                            ></b-form-checkbox>
                                        </td>
                                        <td>
                                            <b-form-checkbox
                                                switch
                                                size="sm"
                                                :checked="list.is_blacklisted"
                                                @change="toggleBlacklist(list)"
                                            ></b-form-checkbox>
                                        </td>
                                        <td>
                                            <div class="action-buttons" @click.stop>
                                                <button @click="openEdit(list,index)" class="action-btn action-btn-edit" v-b-tooltip.hover title="Edit">
                                                    <i class="ri-pencil-line"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="lists.length === 0">
                                        <td colspan="9" class="text-center py-4">
                                            <i class="ri-truck-line text-muted" style="font-size: 3rem;"></i>
                                            <p class="mt-2 mb-0">No suppliers found</p>
                                            <small class="text-muted">Try changing your search or filter criteria</small>
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

                <div v-if="currentView === 'details'">
                    <Details @update="fetch()" :supplier="selectedSupplier" :backToList="backToList" ref="details" />
                </div>
            </div>
        </div>
    </BRow>
    <Create @add="fetch()" @update="fetch()" :dropdowns="dropdowns" ref="create"/>
</template>
<script>
import _ from 'lodash';
import Multiselect from "@vueform/multiselect";
import PageHeader from '@/Shared/Components/PageHeader.vue';
import Pagination from "@/Shared/Components/Pagination.vue";
import Create from './Modals/Create.vue';
import Details from './Details.vue';

export default {
    components: { PageHeader, Pagination, Multiselect, Create, Details },
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
            selectedSupplier: {},
            currentView: 'list',
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
            page_url = page_url || '/suppliers';
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
        },

        openView(data) {
            this.selectedSupplier = data;
            this.currentView = 'details';
        },

        backToList() {
            this.currentView = 'list';
            this.selectedSupplier = {};
            this.selectedRow = null;
        },

        getRowStyle(list) {
            let hoverColor = 'rgba(46, 139, 87, 0.05)';
            
            if (list.is_active === 0) {
                hoverColor = 'rgba(220, 53, 69, 0.05)';
            } else if (list.is_blacklisted === 1) {
                hoverColor = 'rgba(255, 193, 7, 0.1)';
            }
            
            return {
                '--hover-color': hoverColor
            };
        },

        toggleActive(data) {
            axios.patch(`/suppliers/${data.id}/toggle-active`, {
                is_active: data.is_active === 1 ? 0 : 1
            })
            .then(response => {
                this.fetch();
                this.$toast.success(response.data.message);
            })
            .catch(err => {
                console.log(err);
                this.$toast.error('Failed to update supplier status');
            });
        },

        toggleBlacklist(data) {
            axios.patch(`/suppliers/${data.id}/toggle-blacklist`, {
                is_blacklisted: data.is_blacklisted === 1 ? 0 : 1
            })
            .then(response => {
                this.fetch();
                this.$toast.success(response.data.message);
            })
            .catch(err => {
                console.log(err);
                this.$toast.error('Failed to update supplier blacklist status');
            });
        }
    }
}
</script>

<style scoped>
/* Table Row Hover Effects */
tbody tr {
    transition: background-color 0.3s ease;
}

tbody tr:hover {
    background-color: var(--hover-color, rgba(46, 139, 87, 0.05)) !important;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 8px;
    justify-content: flex-start;
    min-width: 80px;
}

/* Pagination Section */
.pagination-section {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #e9ecef;
}

/* Empty State */
.text-center {
    color: #6c757d;
}

.text-center i {
    opacity: 0.5;
}
</style>

