no<template>
    <PageHeader title="Employee Management" pageTitle="List" />
    <BRow>
             <div class="col-md-12">
              <div class="card bg-light-subtle shadow-none border">
                  <div class="card-header bg-light-subtle">
                      <div class="d-flex mb-n3">
                          <div class="flex-shrink-0 me-3">
                              <div style="height:2.5rem;width:2.5rem;">
                                  <span class="avatar-title bg-primary-subtle rounded p-2 mt-n1">
                                      <i class="ri-shopping-cart-line text-primary fs-24"></i>
                                  </span>
                              </div>
                          </div>
                          <div class="flex-grow-1">
                              <h5 class="mb-0 fs-14"><span class="text-body">List of Employees</span></h5>
                              <p class="text-muted text-truncate-two-lines fs-12">A comprehensive list of employees</p>
                          </div>
                          <div class="flex-shrink-0" style="width: 45%;">
                            
                          </div>
                      </div>
                  </div>
                        <div class="card-body bg-white border-bottom shadow-none">
                      <b-row class="mb-2 ms-1 me-1" style="margin-top: 12px;">
                          <b-col lg>
                              <div class="input-group mb-1">
                                  <span class="input-group-text"> <i class="ri-search-line search-icon"></i></span>
                                  <input type="text" v-model="filter.keyword" @input="debouncedSearch" placeholder="Search Employee" class="form-control" style="width: 20%;">
                                  <b-button type="button" variant="primary" @click="openCreate">
                                      <i class="ri-add-circle-fill align-bottom me-1"></i> Create
                                  </b-button>
                              </div>
                          </b-col>
                      </b-row>
                  </div>

                  <div class="card bg-white border-bottom shadow-none" no-body>
                      <div class="d-flex">
                          <div class="flex-grow-1">
                              <ul class="nav nav-tabs nav-tabs-custom nav-primary fs-12" role="tablist">
                                  <li class="nav-item">
                                      <BLink @click="viewStatus(null,null)" class="nav-link py-3 active" data-bs-toggle="tab" role="tab" aria-selected="true">
                                      <i class="ri-apps-2-line me-1 align-bottom"></i> All
                                      </BLink>
                                  </li>
                              </ul>
                          </div>
                          <div class="flex-shrink-0">
                              <div class="d-flex flex-wrap gap-2 mt-3">
                                
                              </div>
                          </div>
                      </div>
                  </div>
                  <div class="card-body bg-white rounded-bottom">
                      <div class="table-responsive table-card" style="margin-top: -39px; overflow: auto;">
                          <table class="table align-middle table-striped table-centered mb-0">
                              <thead class="table-light thead-fixed">
                                  <tr class="fs-11">
                                      <th style="width: 3%;">#</th>
                                      <th style="width: 12%;" >Name</th>
                                      <th style="width: 8%;" >Position</th>
                                      <th style="width: 8%;" >Email</th>
                                      <th style="width: 7%;" >Contact</th>
                                      <th style="width: 7%;" >Birthdate</th>
                                      <th style="width: 5%;" >Sex</th>
                                      <th style="width: 5%;" >Religion</th>
                                      <th style="width: 8%;" >Address</th>
                                      <th style="width: 4%;"  class="text-center">Regular</th>
                                      <th style="width: 4%;"  class="text-center">Blacklisted</th>
                                      <th style="width: 4%;" >Active</th>
                                
                                      <th style="width: 6%;" >Created</th>
                                      <th style="width: 8%;" class="text-center">Actions</th>
                                  </tr>
                              </thead>


                              <tbody class="table-white fs-12">
                                  <tr v-for="(list,index) in lists" v-bind:key="index" @click="selectRow(index)" :class="{
                                      'bg-info-subtle': index === selectedRow,
                                      'bg-danger-subtle': list.is_active === 0 && index !== selectedRow,
                                      'bg-warning-subtle': list.is_blacklisted === 1
                                  }">
                                      <td class="text-center">
                                        {{ index + 1}}
                                      </td>

                                      <td>
                                          <div class="d-flex align-items-center">
                                              <div class="avatar-xs me-2">
                                                  <img v-if="list.avatar" :src="'/storage/' + list.avatar" alt="Avatar" class="rounded-circle avatar-xs">
                                                  <div v-else class="avatar-xs rounded-circle bg-light d-flex align-items-center justify-content-center">
                                                      <i class="ri-user-line text-muted"></i>
                                                  </div>
                                              </div>
                                              <span>{{ list.fullname }}</span>
                                          </div>
                                      </td>
                                      <td>{{ list.position ? list.position.title : '-' }}</td>
                                      <td>{{ list.email || '-' }}</td>
                                      <td>{{ list.mobile || '-' }}</td>
                                      <td>{{ list.birthdate || '-' }}</td>
                                      <td>{{ list.sex || '-' }}</td>
                                      <td>{{ list.religion || '-' }}</td>
                                      <td>{{ list.address || '-' }}</td>

                                      <td class="text-center">
                                        <i v-if="list.is_regular === 1" class="ri-check-line text-success fs-16"></i>
                                        <i v-else class="ri-close-line text-muted fs-16"></i>
                                      </td>

                                      <td class="text-center">
                                        <i v-if="list.is_blacklisted === 1" class="ri-close-line text-danger fs-16"></i>
                                        <i v-else class="ri-check-line text-success fs-16"></i>
                                      </td>

                                      <td>
                                        <b-form-checkbox
                                          :checked="list.is_active === 1"
                                          @change="toggleActive(list)"
                                          switch
                                          size="md"
                                        />
                                      </td>

                                   

                                      <td>
                                        {{ list.created_at }}
                                      </td>

                                      <td class="text-center">
                                          <div class="d-flex justify-content-center gap-1">
                                              <b-button @click="openEdit(list,index)" variant="info" v-b-tooltip.hover title="Edit" size="sm" class="btn-icon">
                                                  <i class="ri-pencil-fill"></i>
                                              </b-button>
                                              <!-- <b-button @click="onDelete(list.id)" variant="danger" v-b-tooltip.hover title="Delete" size="sm" class="btn-icon">
                                                  <i class="ri-delete-bin-line"></i>
                                              </b-button> -->
                                          </div>
                                      </td>
                                  </tr>
                              </tbody>
                          </table>
                      </div>
                  </div>
                  <div class="card-footer">
                      <Pagination class="ms-2 me-2 mt-n1" v-if="meta" @fetch="fetch()" :lists="lists.length" :links="links" :pagination="meta" />
                  </div>
              </div>
            </div>
    </BRow>
    <Create  @add="fetch()" :dropdowns="dropdowns" ref="create"/>
    <DeleteModal ref="deleteModal" :title="deleteModalTitle" :message="deleteModalMessage" />
</template>
<script>
import _ from 'lodash';
import Multiselect from "@vueform/multiselect";
import PageHeader from '@/Shared/Components/PageHeader.vue';
import Pagination from "@/Shared/Components/Pagination.vue";
import DeleteModal from '@/Shared/Components/Modals/DeleteModal.vue';
import Create from './Modals/Create.vue';

export default {
    components: { PageHeader, Pagination, Multiselect, Create, DeleteModal },
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
            deleteModalTitle: 'Delete Employee',
            deleteModalMessage: 'Are you sure you want to delete this employee? This action cannot be undone.'
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
            page_url = page_url || '/employees';
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

        toggleActive(data) {
            axios.patch(`/employees/toggle-active/${data.id}`, {
                is_active: data.is_active === 1 ? 0 : 1
            })
            .then(response => {
                this.fetch();
                this.$toast.success(response.data.message);
            })
            .catch(err => {
                console.log(err);
                this.$toast.error('Failed to update employee status');
            });
        },

        async onDelete(id) {
            this.deleteModalTitle = 'Delete Employee';
            this.deleteModalMessage = 'Are you sure you want to delete this employee? This action cannot be undone.';
            const confirmed = await this.$refs.deleteModal.show();

            if (confirmed) {
                axios.delete(`/employees/${id}`)
                .then(response => {
                    this.fetch();
                    this.$toast.success(response.data.message);
                })
                .catch(err => {
                    console.log(err);
                    this.$toast.error('Failed to delete employee');
                });
            }
        },

        selectRow(index) {
            if (this.selectedRow === index) {
                this.selectedRow = null;
            } else {
                this.selectedRow = index;
            }
        },

        onEmployeeSaved() {
            this.fetch();
            
            //this.$toast.success('Employee saved successfully!');
        }
    }
}
</script>

