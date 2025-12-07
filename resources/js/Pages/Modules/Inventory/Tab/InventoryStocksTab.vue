<template>
  <BRow>
    <div class="col-md-12">
      <div class="library-card">
        <div class="library-card-header">
          <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
              <div class="header-icon">
                <i class="ri-archive-line"></i>
              </div>
              <div>
                <h4 class="header-title mb-1">Inventory Stocks Management</h4>
                <p class="header-subtitle mb-0">Manage and organize your inventory stocks</p>
              </div>
            </div>
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
                placeholder="Search inventory stocks..."
                class="search-input"
              >
            </div>
          </div>

          <div class="table-section">
            <div class="table-responsive" style="overflow: visible; max-height: none;">
              <table class="table align-middle table-centered mb-0">
                <thead>
                  <tr>
                    <th>Received Date</th>
                    <th>Batch Code</th>
                    <th>Supplier</th>
                    <th>Product</th>
                    <th>Unit Cost</th>
                    <th>Quanity</th>
                    <th>Actions</th>
                  </tr>
                </thead>

                <tbody>
                  <tr v-for="(list,index) in listInventoryStocks" v-bind:key="index" @click="selectRow(index)" :class="{
                    'bg-info-subtle': index === selectedRow
                  }">
                    <td>{{ formatDate(list.received_item.received_stock.received_date) }}</td>
                    <td>{{ list.received_item.received_stock.batch_code }}</td>
                    <td>{{ list.received_item.received_stock.supplier ? list.received_item.received_stock.supplier.name : 'N/A' }}</td>
                    <td>{{ list.received_item.product.name }}</td>
                    <td>{{ number_format(list.received_item.unit_cost, 2) }}</td>
                    <td>{{ list.quantity }}</td>
                    <td>
                      <div class="action-buttons">
                        <button @click="openView(list.id)" class="action-btn action-btn-view" v-b-tooltip.hover title="View">
                          <i class="ri-eye-fill"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="pagination-section">
            <Pagination v-if="meta" @fetch="$emit('fetch')" :lists="listInventoryStocks.length" :links="links" :pagination="meta" />
          </div>
        </div>
      </div>
    </div>
  </BRow>
</template>

<script>
import Pagination from '@/Shared/Components/Pagination.vue';

export default {
  name: "InventoryStocksTab",
  components: { Pagination },
  props: {
    listInventoryStocks: Array,
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
    openView(id) {
      this.$inertia.visit(`/inventory-stocks/${id}`);
    },
    updateKeyword(keyword) {
      this.localKeyword = keyword;
      this.$emit('update-keyword', keyword);
    },
    handleDeleteSuccess() {
      this.$emit('toast', 'Inventory stock deleted successfully');
      this.$emit('fetch');
    },
    formatDate(date) {
      return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: '2-digit' });
    },
    number_format(number, decimals = 0) {
      return parseFloat(number).toLocaleString('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
      });
    },
  },
};
</script>
