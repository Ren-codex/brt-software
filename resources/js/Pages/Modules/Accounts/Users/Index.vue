<template>
<Head title="Users"/>
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
                <div class="library-card-body">
                    <div class="search-section">
                        <div class="input-group mb-1">
                            <span class="input-group-text"> <i class="ri-search-line search-icon"></i></span>
                            <input type="text" v-model="filter.keyword" placeholder="Search User" class="form-control" style="width: 20%;">
                            <Multiselect class="white" style="width: 13%;" :options="dropdowns.statuses" v-model="filter.status" label="name" :searchable="true" placeholder="Select Status" />
                            <span @click="refresh()" class="input-group-text" v-b-tooltip.hover title="Refresh" style="cursor: pointer;">
                                <i class="bx bx-refresh search-icon"></i>
                            </span>
                        </div>
                    </div>
<<<<<<< HEAD
                    <div class="table-section">
                        <div class="table-responsive">
                            <table class="table align-middle table-centered mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 3%;"></th>
                                        <th>Name</th>
                                        <th style="width: 10%;" class="text-center">Username</th>
                                        <th style="width: 10%;" class="text-center">Email</th>
                                        <th style="width: 10%;" class="text-center">Mobile</th>
                                        <th style="width: 10%;" class="text-center">Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(list,index) in lists" v-bind:key="index" @click="selectRow(index)" :class="{
                                        'bg-info-subtle': index === selectedRow,
                                        'bg-danger-subtle': list.is_active === 0 && index !== selectedRow
                                    }">
                                        <td class="text-center">
                                            <div class="avatar-xs chat-user-img online">
                                                <img :src="list.avatar" alt="" class="avatar-xs rounded-circle">
=======
                </div>
                <div class="card-body bg-white rounded-bottom">
                    <div class="table-responsive table-card" style="margin-top: -39px; height: calc(100vh - 465px); overflow: auto;">
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
                                <tr v-for="(list,index) in lists" v-bind:key="index" @click="selectRow(index)" :class="{
                                    'bg-info-subtle': index === selectedRow,
                                    'bg-danger-subtle': list.is_active === 0 && index !== selectedRow
                                }">
                                    <td class="text-center"> 
                                        <div class="avatar-xs chat-user-img online">
                                            <img :src="list.avatar" alt="" class="avatar-xs rounded-circle">
                                        </div>
                                    </td>
                                    <td>
                                        <h5 class="fs-13 mb-0 fw-semibold text-primary text-uppercase">{{list.name}}</h5>
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
                                    <td class="text-end">
                                        <div class="d-flex gap-3 justify-content-center"> 
    
                                            <div class="dropdown" @click.stop> 
                                                <button class="btn btn-light btn-icon btn-sm dropdown material-shadow-none" type="button" data-bs-toggle="dropdown" aria-expanded="false"> <i class="ri-more-fill align-bottom"></i> </button>
                                                <ul class="dropdown-menu dropdownmenu-primary dropdown-menu-end">
                                                    <!-- <li>
                                                        <Link :href="`/users/${list.code}`" class="dropdown-item d-flex align-items-center" role="button">
                                                            <i class="ri-eye-fill me-2"></i> View
                                                        </Link>
                                                    </li> -->
                                                    <li>
                                                        <a @click="openUpdate(list,index)" class="dropdown-item d-flex align-items-center" role="button">
                                                            <i class="ri-edit-2-fill me-2"></i> Update
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a @click="openRole(list,index)" class="dropdown-item d-flex align-items-center" role="button">
                                                            <i class="ri-group-2-line me-2"></i> Set Roles
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a @click="openActivation('activation',list,index)" class="dropdown-item d-flex align-items-center" :class="(list.is_active) ? 'text-danger' : 'text-success'" href="#removeFileItemModal" data-id="1" data-bs-toggle="modal" role="button">
                                                            <span v-if="list.is_active"><i class="ri-lock-2-fill me-2"></i> Deactivate User</span>
                                                            <span v-else><i class="ri-lock-unlock-line me-2"></i> Activate User</span>
                                                        </a>
                                                    </li>
                                                </ul>

>>>>>>> 7eef488250f0a2454fe56a9292a76ee49682d36d
                                            </div>
                                        </td>
                                        <td>
                                            <h5 class="fs-13 mb-0 fw-semibold text-primary text-uppercase">{{list.name}}</h5>
                                            <p class="fs-12 text-muted mb-0">
                                                <span class="badge bg-primary-subtle text-info me-1" v-for="role in list.roles" v-bind:key="role.id">{{ role.name }}</span>
                                            </p>
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
                                                <button @click="viewUser(list)" class="action-btn action-btn-view" v-b-tooltip.hover title="View">
                                                    <i class="ri-eye-fill"></i>
                                                </button>
                                                <button @click="openUpdate(list,index)" class="action-btn action-btn-edit" v-b-tooltip.hover title="Update">
                                                    <i class="ri-edit-2-fill"></i>
                                                </button>
                                                <button @click="openRole(list,index)" class="action-btn action-btn-role" v-b-tooltip.hover title="Set Roles">
                                                    <i class="ri-group-2-line"></i>
                                                </button>
                                                <button @click="openActivation('activation',list,index)" :class="['action-btn', list.is_active ? 'action-btn-deactivate' : 'action-btn-activate']" v-b-tooltip.hover :title="list.is_active ? 'Deactivate' : 'Activate'">
                                                    <i :class="list.is_active ? 'ri-lock-2-fill' : 'ri-lock-unlock-line'"></i>
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
    <Role :roles="dropdowns.roles" ref="role"/>
    <Update @update="updateData" ref="update"/>
    <Create :dropdowns="dropdowns" @add="fetch()" ref="create"/>
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
    components: { PageHeader, Pagination, Multiselect, Update, Role , Create },
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
            page_url = page_url || '/users';
            axios.get(page_url,{
                params : {
                    keyword: this.filter.keyword,
                    count: 10, 
                    option: 'list'
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
        openUpdate(data,index){
            this.index = index;
            this.selectedRow = index;
            this.$refs.update.show(data);
        },
        openRole(data,index){
            this.index = index;
            this.selectedRow = index;
            this.$refs.role.show(data);
        },
        openView(data,index){
            this.index = index;
            this.selectedRow = index;
            this.$refs.view.show(data);
        },
        updateData(data){
            this.lists[this.index] = data;
        },
        selectRow(index) {
            if (this.selectedRow === index) {
                this.selectedRow = null;
            } else {
                this.selectedRow = index;
            }
        },
        viewUser(list){
            window.location.href = `/users/${list.code}`;
        },
        refresh(){
            this.fetch();
        }
    }
}
</script>