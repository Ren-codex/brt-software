<template>
    <PageHeader title="Customer Management" pageTitle="List" />
    <BRow>
        <div class="col-md-12">
            <div class="library-card">
                <div class="library-card-header">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="header-icon">
                                <i class="ri-shopping-cart-line fs-24"></i>
                            </div>
                            <div>
                                <h4 class="header-title mb-1">List of Customers</h4>
                                <p class="header-subtitle mb-0">A comprehensive list of customers</p>
                            </div>
                        </div>
                        <button class="create-btn" @click="openCreate">
                            <i class="ri-add-line"></i>
                            <span>Customer</span>
                        </button>
                    </div>

                </div>
            
                <div class="card-body m-2 p-3">
                    <div class="search-section">
                        <div class="search-wrapper">
                            <i class="ri-search-line search-icon"></i>
                            <input type="text" v-model="filter.keyword" @input="debouncedSearch"
                                placeholder="Search Employee..." class="search-input">
                        </div>

                    </div>
    
                
                    <div>
                        <table class="table align-middle table-striped table-centered mb-0">
                            <thead class="table-light thead-fixed">
                                <tr class="fs-11">
                                    <th style="width: 3%;">#</th>
                                    <th style="width: 12%;">Name</th>
                                    <th style="width: 12%;">Email</th>
                                    <th style="width: 10%;">Contact</th>
                                    <th style="width: 12%;">Address</th>
                                    <th style="width: 6%;" class="text-center">Regular</th>
                                    <th style="width: 6%;">Active</th>
                                    <th style="width: 8%;">Created</th>
                                    <th style="width: 8%;" class="text-center">Actions</th>
                                </tr>
                            </thead>


                            <tbody class="table-white fs-12">
                                <tr v-for="(list, index) in lists" v-bind:key="index" @click="selectRow(index)" :class="{
                                    'bg-info-subtle': index === selectedRow,
                                    'bg-danger-subtle': list.is_active === 0 && index !== selectedRow,
                                    'bg-warning-subtle': list.is_blacklisted === 1
                                }">
                                    <td class="text-center">
                                        {{ index + 1 }}
                                    </td>

                                    <td>{{ list.name }}</td>
                                    <td>{{ list.email || '-' }}</td>
                                    <td>{{ list.contact_number }}</td>
                                    <td>{{ list.address }}</td>


                                    <td class="text-center">
                                        <i v-if="list.is_regular === 1" class="ri-check-line text-success fs-16"></i>
                                        <i v-else class="ri-close-line text-muted fs-16"></i>
                                    </td>

                                    <td>
                                        <b-form-checkbox :checked="list.is_active === 1" @change="toggleActive(list)"
                                            switch size="md" />
                                    </td>

                                    <td>
                                        {{ list.created_at }}
                                    </td>

                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-1">
                                            <b-button @click="openEdit(list, index)" variant="info" v-b-tooltip.hover
                                                title="Edit" size="sm" class="btn-icon">
                                                <i class="ri-pencil-fill"></i>
                                            </b-button>
                                            <b-button @click="onDelete(list.id)" variant="danger" v-b-tooltip.hover
                                                title="Delete" size="sm" class="btn-icon">
                                                <i class="ri-delete-bin-line"></i>
                                            </b-button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <Pagination class="ms-2 me-2 mt-n1" v-if="meta" @fetch="fetch()" :lists="lists.length"
                        :links="links" :pagination="meta" />
                </div>
            </div>
        </div>
    </BRow>
    <Create @add="fetch()" :dropdowns="dropdowns" ref="create" />
    <Delete @delete="fetch()" ref="delete" />
</template>
<script>
import _ from 'lodash';
import Multiselect from "@vueform/multiselect";
import PageHeader from '@/Shared/Components/PageHeader.vue';
import Pagination from "@/Shared/Components/Pagination.vue";
import Delete from "@/Shared/Components/Modals/Delete.vue";
import Create from './Modals/Create.vue';

export default {
    components: { PageHeader, Pagination, Multiselect, Create, Delete },
    props: ['dropdowns'],
    data() {
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
        "filter.keyword"(newVal) {
            this.checkSearchStr(newVal);
        }
    },
    created() {
        this.fetch();
    },
    methods: {
        checkSearchStr: _.debounce(function (string) {
            this.fetch();
        }, 300),
        fetch(page_url) {
            page_url = page_url || '/customers';
            axios.get(page_url, {
                params: {
                    keyword: this.filter.keyword,
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
                .catch(err => console.log(err));
        },
        openCreate() {
            this.$refs.create.show();
        },

        openEdit(data, index) {
            this.selectedRow = index;
            this.$refs.create.edit(data, index);
        },

        onDelete(id) {
            let title = "Customer";
            this.$refs.delete.show(id, title, '/customers');
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
