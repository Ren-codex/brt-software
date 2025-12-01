<template>
  <div class="card shadow-sm">
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
                <h5 class="mb-0 fs-14"><span class="text-body">List of Purchase Orders</span></h5>
                <p class="text-muted text-truncate-two-lines fs-12">A comprehensive list of purchase orders</p>
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
                  <input type="text" v-model="localKeyword" @input="$emit('update-keyword', localKeyword)" placeholder="Search Purchase Order" class="form-control" style="width: 20%;">
                  <b-button type="button" variant="primary" @click="openCreatePurchaseOrder">
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
                    <BLink class="nav-link py-3 active" data-bs-toggle="tab" role="tab" aria-selected="true">
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
                    <th style="width: 15%;" class="text-center">PO Number</th>
                    <th style="width: 15%;" class="text-center">Date</th>
                    <th style="width: 15%;" class="text-center">Supplier</th>
                    <th style="width: 15%;" class="text-center">Total Amount</th>
                    <th style="width: 10%;">Status</th>
                    <th style="width: 6%;"></th>
                  </tr>
                </thead>

                <tbody class="table-white fs-12">
                  <tr v-for="(list,index) in listPurchaseOrders" v-bind:key="index" @click="selectRow(index)" :class="{
                    'bg-info-subtle': index === selectedRow
                  }">
                    <td class="text-center">
                      {{ index + 1}}
                    </td>

                    <td class="text-center">{{ list.po_number }}</td>
                    <td class="text-center">{{ list.po_date }}</td>
                    <td class="text-center">{{ list.supplier ? list.supplier.name : '' }}</td>
                    <td class="text-center">{{ list.total_amount }}</td>
                    <td class="text-center">{{ list.status ? list.status.name : '' }}</td>

                    <td class="text-end">
                      <b-button @click="openView(list.id)" variant="success" class="me-1" v-b-tooltip.hover title="View" size="sm">
                        <i class="ri-eye-fill align-bottom"></i>
                      </b-button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="card-footer">
            <Pagination class="ms-2 me-2 mt-n1" v-if="meta" @fetch="$emit('fetch')" :lists="listPurchaseOrders.length" :links="links" :pagination="meta" />
          </div>
        </div>
  </div>
  <CreatePurchaseOrderModal ref="createModal" :dropdowns="dropdowns" @add="$emit('fetch')" />
  <Delete ref="delete" @delete="handleDeleteSuccess" />
</template>

<script>
import Pagination from '@/Shared/Components/Pagination.vue';
import Delete from "@/Shared/Components/Modals/Delete.vue";
import CreatePurchaseOrderModal from '../Modal/CreatePurchaseOrderModal.vue';

export default {
  name: "PurchaseOrdersTab",
  components: { Pagination, Delete, CreatePurchaseOrderModal },
  props: {
    listPurchaseOrders: Array,
    meta: Object,
    links: Object,
    filter: Object,
    dropdowns: Object,
  },
  emits: ['fetch', 'open-create', 'open-edit', 'on-delete', 'update-keyword', 'toast'],
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
    openCreatePurchaseOrder() {
      this.$refs.createModal.show();
    },
    openView(id) {
      this.$inertia.visit(`/purchase-orders/${id}`);
    },
    openEdit(data, index) {
      this.$refs.createModal.edit(data, index);
    },
    onDelete(id) {
      let title = "Purchase Order";
      this.$refs.delete.show(id, title, '/purchase-orders');
    },
    handleDeleteSuccess() {
      this.$emit('toast', 'Purchase Order deleted successfully');
      this.$emit('fetch');
    },
  },
};
</script>
