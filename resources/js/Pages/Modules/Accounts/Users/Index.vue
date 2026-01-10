<template>

    <Head title="Users" />
    <PageHeader title="User Management" pageTitle="List" />
    <BRow>
        <div class="col-md-12">
            <div class="library-card">
                <div class="library-card-header">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="header-icon">
                                <i class="ri-team-fill"></i>
                            </div>
                            <div>
                                <h4 class="header-title mb-1">User Management</h4>
                                <p class="header-subtitle mb-0">Manage user accounts and permissions</p>
                            </div>
                        </div>
                        <button class="create-btn" @click="openCreate">
                            <i class="ri-add-line"></i>
                            <span>Create User</span>
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

                    <div style="overflow: auto;">
                        <table class="table align-middle table-striped table-centered mb-0">
                            <thead class="table-light thead-fixed">
                                <tr class="fs-11">
                                    <th style="width: 3%;"></th>
                                    <th>Name</th>
                                    <th style="width: 10%;" class="text-center">Username</th>
                                    <th style="width: 10%;" class="text-center">Email</th>
                                    <th style="width: 10%;" class="text-center">Mobile</th>
                                    <th style="width: 10%;" class="text-center">Status</th>
                                    <th style="width: 6%;"></th>
                                </tr>
                            </thead>
                            <tbody class="table-white fs-12">
                                <tr v-for="(list, index) in lists" v-bind:key="index" @click="selectRow(index)" :class="{
                                    'bg-info-subtle': index === selectedRow,
                                    'bg-danger-subtle': list.is_active === 0 && index !== selectedRow
                                }">
                                    <td class="text-center">
                                        <div class="avatar-xs chat-user-img online">
                                            <img :src="list.avatar" alt="" class="avatar-xs rounded-circle">
                                        </div>
                                    </td>
                                    <td>
                                        <h5 class="fs-13 mb-0 fw-semibold text-primary text-uppercase">{{ list.name }}
                                        </h5>
                                        <!-- <p class="fs-12 text-muted mb-0">
                                            <span class="badge bg-primary-subtle text-info me-1" v-for="role in list.roles" v-bind:key="role.id">{{ role.name }}</span>
                                        </p> -->
                                    </td>
                                    <td class="text-center">{{ list.username }}</td>
                                    <td class="text-center">{{ list.email }}</td>
                                    <td class="text-center">{{ list.mobile }}</td>
                                    <td class="text-center">
                                        <span v-if="list.is_active" class="badge bg-success">Active</span>
                                        <span v-else class="badge bg-danger">Inactive</span>
                                    </td>
                                   

                                   
                                    <td>
                                        <div class="action-buttons">
                                            <button @click="viewUser(list)" class="action-btn action-btn-view"
                                                v-b-tooltip.hover title="View">
                                                <i class="ri-eye-fill"></i>
                                            </button>
                                            <button @click="openUpdate(list, index)" class="action-btn action-btn-edit"
                                                v-b-tooltip.hover title="Update">
                                                <i class="ri-edit-2-fill"></i>
                                            </button>
                                            <button @click="openRole(list, index)" class="action-btn action-btn-role"
                                                v-b-tooltip.hover title="Set Roles">
                                                <i class="ri-group-2-line"></i>
                                            </button>
                                            <button @click="openActivation('activation', list, index)"
                                                :class="['action-btn', list.is_active ? 'action-btn-deactivate' : 'action-btn-activate']"
                                                v-b-tooltip.hover :title="list.is_active ? 'Deactivate' : 'Activate'">
                                                <i
                                                    :class="list.is_active ? 'ri-lock-2-fill' : 'ri-lock-unlock-line'"></i>
                                            </button>
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
    <Role :roles="dropdowns.roles" ref="role" />
    <Update @update="updateData" ref="update" />
    <Create :dropdowns="dropdowns" @add="fetch()" ref="create" />
</template>
<script>
import _ from 'lodash';
import Role from './Modals/Role.vue';
import Update from './Modals/Update.vue';
import Multiselect from "@vueform/multiselect";
import PageHeader from '@/Shared/Components/PageHeader.vue';
import Pagination from "@/Shared/Components/Pagination.vue";
import Create from './Modals/Create.vue';

export default {
    components: { PageHeader, Pagination, Multiselect, Update, Role, Create },
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
            page_url = page_url || '/users';
            axios.get(page_url, {
                params: {
                    keyword: this.filter.keyword,
                    count: 10,
                    option: 'list'
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
        openUpdate(data, index) {
            this.index = index;
            this.selectedRow = index;
            this.$refs.update.show(data);
        },
        openRole(data, index) {
            this.index = index;
            this.selectedRow = index;
            this.$refs.role.show(data);
        },
        openView(data, index) {
            this.index = index;
            this.selectedRow = index;
            this.$refs.view.show(data);
        },
        updateData(data) {
            this.lists[this.index] = data;
        },
        selectRow(index) {
            if (this.selectedRow === index) {
                this.selectedRow = null;
            } else {
                this.selectedRow = index;
            }
        },
        viewUser(list) {
            window.location.href = `/users/${list.code}`;
        },
        refresh() {
            this.fetch();
        }
    }
}
</script>