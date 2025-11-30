<template>
  <div class="card shadow-sm">
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
                  <input type="text" v-model="localKeyword" @input="updateKeyword(localKeyword)" placeholder="Search Product" class="form-control" style="width: 20%;">
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
                    <th style="width: 10%;">Active</th>
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

                    <td class="text-center">
                      <b-form-checkbox
                        :checked="list.is_active === 1"
                        @change="toggleActive(list)"
                        switch
                        size="md"
                      />
                    </td>

                    <td class="text-end">
                      <b-button @click="openEdit(list, index)" variant="info" class="me-1" v-b-tooltip.hover title="View" size="sm">
                        <i class="ri-pencil-fill align-bottom"></i>
                      </b-button>
                      <b-button @click="onDelete(list.id)" variant="danger" class="me-1" v-b-tooltip.hover title="Delete" size="sm">
                        <i class="ri-delete-bin-line align-bottom"></i>
                      </b-button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="card-footer">
            <Pagination class="ms-2 me-2 mt-n1" v-if="meta" @fetch="$emit('fetch')" :lists="listProducts.length" :links="links" :pagination="meta" />
          </div>
        </div>
      </div>
    </BRow>
  </div>
  <CreateProduct @add="$emit('fetch')" ref="createProduct" :dropdowns="dropdowns"/>
  <Delete ref="delete" @delete="handleDeleteSuccess"/>
</template>

<script>
import Pagination from '@/Shared/Components/Pagination.vue';
import CreateProduct from '../Libraries/Products/Modals/Create.vue';
import Delete from "@/Shared/Components/Modals/Delete.vue";

export default {
  name: "ProductsTab",
  components: { Pagination, CreateProduct, Delete },
  props: {
    listProducts: Array,
    meta: Object,
    links: Object,
    filter: Object,
    dropdowns: Object,
  },
  emits: ['fetch', 'update-keyword', 'toast'],
  data() {
    return {
      selectedRow: null,
      localKeyword: this.filter.keyword,
    };
  },
  watch: {
    'filter.keyword'(newVal) {
      this.localKeyword = newVal;
    }
  },
  methods: {
    selectRow(index) {
      this.selectedRow = this.selectedRow === index ? null : index;
    },
    openCreate() {
      this.$refs.createProduct.show();
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
    viewStatus(a, b) {
      // Placeholder
    },
    openEdit(data, index) {
      this.$refs.createProduct.edit(data);
    },
    onDelete(id) {
      this.$refs.delete.show(id, 'Delete Product', '/libraries/products');
    },
    updateKeyword(keyword) {
      this.localKeyword = keyword;
      this.$emit('update-keyword', keyword);
    },
    handleDeleteSuccess() {
      this.$emit('toast', 'Product deleted successfully');
      this.$emit('fetch');
    },
  },
};
</script>
