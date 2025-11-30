<template>
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
                              <h5 class="mb-0 fs-14"><span class="text-body">List of Sales Orders</span></h5>
                              <p class="text-muted text-truncate-two-lines fs-12">A comprehensive list of Sales Orders</p>
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
                                  <input type="text" v-model="filter.keyword" @input="debouncedSearch" placeholder="Search Sales Order" class="form-control" style="width: 20%;">
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
                                     <th style="width: 12%;" class="text-center">Order Number</th>
                                      <th style="width: 12%;" class="text-center">Customer</th>
                                      <th style="width: 12%;" class="text-center">Date</th>
                                      <th style="width: 10%;" class="text-center">Amount</th>
                                      <th style="width: 12%;" class="text-center">Status</th>
                                      <th style="width: 6%;" class="text-center">Actions</th>
                                  </tr>
                              </thead>


                              <tbody class="table-white fs-12">
                                  <tr v-for="(list,index) in lists" v-bind:key="index" @click="selectRow(index)" :class="{
                                      'bg-info-subtle': index === selectedRow
                                  }">
                                      <td class="text-center">
                                        {{ index + 1}}
                                      </td>

                                      <td class="text-center">{{ list.order_number }}</td>
                                      <td class="text-center">{{ list.customer || '-' }}</td>
                                      <td class="text-center">{{ list.created_at }}</td>
                                          <td class="text-center">{{ list.amount }}</td>
                                      <td class="text-center">{{ list.status }}</td>
                                    

                                      <td class="text-end">
                                          <div class="d-flex justify-content-end gap-1">
                                              <b-button @click="openEdit(list,index)" variant="info" v-b-tooltip.hover title="Edit" size="sm" class="btn-icon">
                                                  <i class="ri-pencil-fill"></i>
                                              </b-button>
                                              <b-button @click="onDelete(list.id)" variant="danger" v-b-tooltip.hover title="Delete" size="sm" class="btn-icon">
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
                      <Pagination class="ms-2 me-2 mt-n1" v-if="meta" @fetch="fetch()" :lists="lists.length" :links="links" :pagination="meta" />
                  </div>
              </div>
            </div>
    </BRow>
    <Create @add="fetch()" :dropdowns="dropdowns" ref="create"/>
    <Delete @delete="fetch()" ref="delete"/>
</template>
<script>
import _ from 'lodash';
import Multiselect from "@vueform/multiselect";
import PageHeader from '@/Shared/Components/PageHeader.vue';
import Pagination from "@/Shared/Components/Pagination.vue";
import Delete from "@/Shared/Components/Modals/Delete.vue";
import Create from './Modals/Create.vue';

export default {
    components: { PageHeader, Pagination, Multiselect , Create, Delete },
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

        onDelete(id){
            let title = "Sales Order";
            this.$refs.delete.show(id , title, '/sales-orders');
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

