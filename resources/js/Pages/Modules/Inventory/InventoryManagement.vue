<template>
  <div>
    <Head title="Inventory" />
    <PageHeader title="Inventory Management" pageTitle="List" />
    <BRow no-gutters>
      <BCol md="2" class="border-end" style="min-height: 80vh;">
        <ul class="nav flex-column nav-pills">
          <li class="nav-item">
            <a
              href="#"
              class="nav-link"
              :class="{ active: activeTab === 'products' }"
              @click.prevent="changeTab('products')"
              >Product List</a
            >
          </li>
          <li class="nav-item">
            <a
              href="#"
              class="nav-link"
              :class="{ active: activeTab === 'purchaseOrders' }"
              @click.prevent="changeTab('purchaseOrders')"
              >Purchase Orders</a
            >
          </li>
          <li class="nav-item">
            <a
              href="#"
              class="nav-link"
              :class="{ active: activeTab === 'receivingStocks' }"
              @click.prevent="changeTab('receivingStocks')"
              >Receiving of Stocks</a
            >
          </li>
          <li class="nav-item">
            <a
              href="#"
              class="nav-link"
              :class="{ active: activeTab === 'adjustmentStocks' }"
              @click.prevent="changeTab('adjustmentStocks')"
              >Adjustment of Stocks</a
            >
          </li>
        </ul>
      </BCol>
      <BCol md="10">
        <div v-if="activeTab === 'products'" class="card shadow-sm">
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
                              <h5 class="mb-0 fs-14"><span class="text-body">List of Products</span></h5>
                              <p class="text-muted text-truncate-two-lines fs-12">A comprehensive list of products</p>
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
                                  <input type="text" v-model="filter.keyword" placeholder="Search Product" class="form-control" style="width: 20%;">
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
                                      <th style="width: 15%;" class="text-center">Name</th>
                                      <th style="width: 15%;" class="text-center">Unit</th>
                                      <th style="width: 6%;"></th>
                                  </tr>
                              </thead>

                              <tbody class="table-white fs-12">
                                  <tr v-for="(list,index) in listProducts" v-bind:key="index" @click="selectRow(index)" :class="{
                                      'bg-info-subtle': index === selectedRow,
                                      'bg-danger-subtle': list.is_active === 0 && index !== selectedRow
                                  }">
                                      <td class="text-center"> 
                                        {{ index + 1}} 
                                      </td>

                                      <td class="text-center">{{ list.name }}</td>
                                      <td class="text-center">{{ list.unit.name }}</td>

                                      <td class="text-end">
                                          <b-button  @click="openEdit(list,index)"  variant="info" class="me-1" v-b-tooltip.hover title="View" size="sm">
                                              <i class="ri-pencil-fill align-bottom"></i>
                                          </b-button>
                                          <b-button  @click="onDelete(list.id)"  variant="danger" class="me-1" v-b-tooltip.hover title="Delete" size="sm">
                                              <i class="ri-delete-bin-line align-bottom"></i>
                                          </b-button>
                                      </td>
                                  </tr>
                              </tbody>
                          </table>
                      </div>
                  </div>
                  <div class="card-footer">
                      <Pagination class="ms-2 me-2 mt-n1" v-if="meta" @fetch="fetch" :lists="listProducts.length" :links="links" :pagination="meta" />
                  </div>
              </div>
            </div>
          </BRow>
        </div>

        <div v-if="activeTab === 'purchaseOrders'" class="card shadow-sm p-3">
          <h5>Purchase Orders</h5>
          <p>Purchase orders table or content goes here.</p>
        </div>

        <div v-if="activeTab === 'receivingStocks'" class="card shadow-sm p-3">
          <h5>Receiving of Stocks</h5>
          <p>Receiving stocks table or content goes here.</p>
        </div>

        <div v-if="activeTab === 'adjustmentStocks'" class="card shadow-sm p-3">
          <h5>Adjustment of Stocks</h5>
          <p>Adjustment stocks table or content goes here.</p>
        </div>
      </BCol>
    </BRow>
  </div>
    <CreateProduct @add="fetchProducts()" ref="createProduct" :dropdowns="dropdowns"/>
    <Delete @delete="fetchProducts()" ref="delete"/>
</template>

<script>
import _ from 'lodash';
import PageHeader from '@/Shared/Components/PageHeader.vue';
import Pagination from '@/Shared/Components/Pagination.vue';
import CreateProduct from '../Libraries/Products/Modals/Create.vue';
import Delete from "@/Shared/Components/Modals/Delete.vue";

export default {
  name: "InventoryManagement",
  components: { PageHeader, Pagination, CreateProduct, Delete },
  props: ['dropdowns'],
  data() {
    return {
      activeTab: 'products',
      filter: {
        keyword: '',
      },
      listProducts: [],
      meta: null,
      links: null,
      selectedProductRow: null,
    };
  },
  watch: {
    'activeTab'(newVal) {
      this.fetchProducts();
    }
  },
  created() {
    this.fetchProducts();
  },
  methods: {
    changeTab(tab) {
      this.activeTab = tab;
      this.selectedRow = null;
      this.filter.keyword = '';
    },
    fetchProducts(page_url) {
      if (this.activeTab === 'products') {
        page_url = page_url || '/libraries/products';
        axios
          .get(page_url, {
            params: {
              keyword: this.filter.keyword,
              count: 10,
              option: 'lists',
            },
          })
          .then((response) => {
            if (response) {
              this.listProducts = response.data.data;
              this.meta = response.data.meta;
              this.links = response.data.links;
            }
          })
          .catch((err) => console.error(err));
      } else {
        this.listProducts = [];
        this.meta = null;
        this.links = null;
      }
    },
    selectRow(index) {
      this.selectedRow = this.selectedRow === index ? null : index;
    },
    openCreate(){
        this.$refs.createProduct.show();
    },
    openEdit(data,index){
        this.selectedProductRow = index;
        this.$refs.createProduct.edit(data , index);
    },
    onDelete(id){
        let title = "Product";
        this.$refs.delete.show(id , title, '/libraries/products');
    },
  },
};
</script>
