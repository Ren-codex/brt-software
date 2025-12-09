<template>
  <BRow>
    <div class="col-md-12">
      <div class="library-card">
        <div class="library-card-header">
          <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
              <div class="header-icon">
                <i class="ri-shopping-cart-line"></i>
              </div>
              <div>
                <h4 class="header-title mb-1">Purchase Order Management</h4>
                <p class="header-subtitle mb-0">Manage and organize your purchase order catalog</p>
              </div>
            </div>
            <button class="create-btn" @click="openCreatePurchaseOrder">
              <i class="ri-add-line"></i>
              <span>Add Purchase Order</span>
            </button>
          </div>
        </div>

        <div class="library-card-body">
          <div class="search-section">
            <div class="search-wrapper">
              <i class="ri-search-line search-icon"></i>
              <input
                type="text"
                v-model="localKeyword"
                @input="updateKeyword(localKeyword)"
                placeholder="Search purchase orders..."
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
                    <th>PO Number</th>
                    <th>Date</th>
                    <th>Supplier</th>
                    <th>Total Amount</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>

                <tbody>
                  <tr v-for="(list,index) in listPurchaseOrders" v-bind:key="index" @click="openView(list.id)" style="cursor: pointer;">
                    <td>{{ index + 1}}</td>
                    <td>{{ list.po_number }}</td>
                    <td>{{ list.po_date }}</td>
                    <td>{{ list.supplier ? list.supplier.name : '' }}</td>
                    <td>{{ list.total_amount }}</td>
                    <td>{{ list.status ? list.status.name : '' }}</td>
                    <td>
                      <!-- <div class="action-buttons">
                        <button @click.stop="openView(list.id)" class="action-btn action-btn-view" v-b-tooltip.hover title="View">
                          <i class="ri-eye-fill"></i>
                        </button>
                      </div> -->
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="pagination-section">
            <Pagination v-if="meta" @fetch="$emit('fetch')" :lists="listPurchaseOrders.length" :links="links" :pagination="meta" />
          </div>
        </div>
      </div>
    </div>
  </BRow>
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
    updateKeyword(keyword) {
      this.localKeyword = keyword;
      this.$emit('update-keyword', keyword);
    },
    handleDeleteSuccess() {
      this.$emit('toast', 'Purchase Order deleted successfully');
      this.$emit('fetch');
    },
  },
};
</script>
