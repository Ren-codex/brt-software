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

          <BTabs>
            <BTab title="Available Stocks">
              <div class="table-section">
                <div class="table-responsive" style="overflow: visible; max-height: none;">
                  <table class="table align-middle table-centered mb-0">
                    <thead>
                      <tr>
                        <th>#</th>
                        <th>Received Date</th>
                        <th>Batch Code</th>
                        <th>Supplier</th>
                        <th>Product</th>
                        <th>Unit Cost</th>
                        <th>Quantity</th>
                        <th>Expiration Date</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr 
                        v-for="(list,index) in availableStocks" 
                        v-bind:key="list.id" 
                        @click="openView(list.id)" 
                        style="cursor: pointer;"
                        :class="{'bg-info-subtle': list.id === selectedRow}"
                      >
                        <td>{{ index + 1 }}</td>
                        <td>{{ formatDate(list.received_item.received_stock.received_date) }}</td>
                        <td>{{ list.received_item.received_stock.batch_code }}</td>
                        <td>{{ list.received_item.received_stock.supplier ? list.received_item.received_stock.supplier.name : 'N/A' }}</td>
                        <td>
                          <div class="product-info">
                            <strong>{{ list.received_item.product.name }}</strong>
                            <small class="text-muted d-block">{{ list.received_item.product.code || 'No Code' }}</small>
                          </div>
                        </td>
                        <td><b>{{ formatCurrency(list.received_item.unit_cost) }}</b>
                          <br>Retail Price: {{ formatCurrency(list.retail_price) }}
                          <br>Wholesale Price: {{ formatCurrency(list.wholesale_price) }}
                        </td>
                        <td>
                          <span class="quantity-badge" :class="getQuantityClass(list.quantity)">
                            {{ list.quantity }}
                          </span>
                        </td>
                        <td>{{ list.expiration_date ? formatDate(list.expiration_date) : '--' }}</td>
                        <td>
                          <div class="action-buttons" @click.stop>
                            <button @click.stop="openView(list.id)" class="action-btn action-btn-view" v-b-tooltip.hover title="View">
                              <i class="ri-eye-fill"></i>
                            </button>
                            <button @click.stop="updatePrice(list)" class="action-btn action-btn-price" v-b-tooltip.hover title="Update Price">
                              <i class="ri-price-tag-line"></i>
                            </button>
                            <button v-if="list.quantity > 0" @click.stop="adjustStock(list)" class="action-btn action-btn-edit" v-b-tooltip.hover title="Adjust Stock">
                              <i class="ri-edit-box-line"></i>
                            </button>
                          </div>
                        </td>
                      </tr>
                      <tr v-if="availableStocks.length === 0">
                        <td colspan="9" class="text-center py-4">
                          <div class="empty-state">
                            <i class="ri-inbox-line empty-icon"></i>
                            <p class="mb-0">No available stocks found</p>
                          </div>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </BTab>
            <BTab title="Consumed Stocks">
              <div class="table-section">
                <div class="table-responsive" style="overflow: visible; max-height: none;">
                  <table class="table align-middle table-centered mb-0">
                    <thead>
                      <tr>
                        <th>#</th>
                        <th>Received Date</th>
                        <th>Batch Code</th>
                        <th>Supplier</th>
                        <th>Product</th>
                        <th>Unit Cost</th>
                        <th>Quantity</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr 
                        v-for="(list,index) in consumedStocks" 
                        v-bind:key="list.id" 
                        @click="openView(list.id)" 
                        style="cursor: pointer;"
                        :class="{'bg-info-subtle': list.id === selectedRow}"
                      >
                        <td>{{ index + 1 }}</td>
                        <td>{{ formatDate(list.received_item.received_stock.received_date) }}</td>
                        <td>{{ list.received_item.received_stock.batch_code }}</td>
                        <td>{{ list.received_item.received_stock.supplier ? list.received_item.received_stock.supplier.name : 'N/A' }}</td>
                        <td>
                          <div class="product-info">
                            <strong>{{ list.received_item.product.name }}</strong>
                            <small class="text-muted d-block">{{ list.received_item.product.code || 'No Code' }}</small>
                          </div>
                        </td>
                        <td>{{ formatCurrency(list.received_item.unit_cost) }}</td>
                        <td>
                          <span class="quantity-badge text-danger">
                            {{ list.quantity }}
                          </span>
                        </td>
                        <td>
                          <div class="action-buttons" @click.stop>
                            <button @click.stop="openView(list.id)" class="action-btn action-btn-view" v-b-tooltip.hover title="View">
                              <i class="ri-eye-fill"></i>
                            </button>
                          </div>
                        </td>
                      </tr>
                      <tr v-if="consumedStocks.length === 0">
                        <td colspan="8" class="text-center py-4">
                          <div class="empty-state">
                            <i class="ri-check-double-line empty-icon"></i>
                            <p class="mb-0">No consumed stocks found</p>
                          </div>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </BTab>
          </BTabs>

          <div class="pagination-section">
            <Pagination v-if="meta" @fetch="$emit('fetch')" :lists="listInventoryStocks.length" :links="links" :pagination="meta" />
          </div>
        </div>
      </div>
    </div>

    <UpdatePriceModal
      :inventoryStock="selectedStock"
      @saved="handlePriceUpdate"
      ref="updatePriceModal"
    />
  </BRow>
</template>

<script>
import Pagination from '@/Shared/Components/Pagination.vue';
import UpdatePriceModal from '../Modal/UpdatePriceModal.vue';

export default {
  name: "InventoryStocksTab",
  components: { Pagination, UpdatePriceModal },
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
      selectedStock: null,
    };
  },
  computed: {
    availableStocks() {
      return this.listInventoryStocks.filter(stock => stock.quantity > 0);
    },
    consumedStocks() {
      return this.listInventoryStocks.filter(stock => stock.quantity <= 0);
    }
  },
  watch: {
    'filter.keyword'(newVal) {
      this.localKeyword = newVal;
    }
  },
  methods: {
    selectRow(id) {
      this.selectedRow = this.selectedRow === id ? null : id;
    },
    
    openView(id) {
      this.$inertia.visit(`/inventory-stocks/${id}`);
    },
    
    adjustStock(stock) {
      // You can implement adjust stock functionality here
      // For example: this.$emit('adjust-stock', stock);
      console.log('Adjust stock:', stock);
    },
    
    updateKeyword(keyword) {
      this.localKeyword = keyword;
      this.$emit('update-keyword', keyword);
    },
    
    handleDeleteSuccess() {
      this.$emit('toast', 'Inventory stock deleted successfully');
      this.$emit('fetch');
    },
    
    getQuantityClass(quantity) {
      if (quantity <= 0) return 'text-danger';
      if (quantity < 10) return 'text-warning';
      return 'text-success';
    },
    
    formatDate(date) {
      return new Date(date).toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: '2-digit' 
      });
    },
    
    formatCurrency(amount) {
      if (!amount && amount !== 0) return '₱0.00';
      return '₱' + Number(amount).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    },

    updatePrice(stock) {
      this.selectedStock = stock;
      this.$refs.updatePriceModal.show();
    },

    handlePriceUpdate() {
      this.$emit('toast', 'Price updated successfully');
      this.$emit('fetch');
      this.selectedStock = null;
    },
  },
};
</script>

<style scoped>
.product-info {
  max-width: 200px;
}

.quantity-badge {
  font-weight: 600;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
}

.text-success {
  color: #28a745;
}

.text-warning {
  color: #ffc107;
}

.text-danger {
  color: #dc3545;
}

.action-buttons {
  display: flex;
  gap: 6px;
}

.action-btn {
  width: 32px;
  height: 32px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  transition: all 0.3s ease;
  cursor: pointer;
}

.action-btn-view {
  background: #e7f3ff;
  color: #0d6efd;
}

.action-btn-view:hover {
  background: #d0e7ff;
}

.action-btn-price {
  background: #e8f5e8;
  color: #2e7d32;
}

.action-btn-price:hover {
  background: #c8e6c9;
}

.action-btn-edit {
  background: #fff3cd;
  color: #ffc107;
}

.action-btn-edit:hover {
  background: #ffeaa7;
}

.empty-state {
  color: #6c757d;
  text-align: center;
  padding: 20px;
}

.empty-icon {
  font-size: 48px;
  margin-bottom: 10px;
  opacity: 0.3;
}

/* Make table rows clickable */
.table tbody tr {
  cursor: pointer;
  transition: background-color 0.2s ease;
}

.table tbody tr:hover {
  background-color: #f8f9fa !important;
}

.table tbody tr.bg-info-subtle:hover {
  background-color: #e7f3ff !important;
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .action-buttons {
    flex-direction: column;
  }
  
  .action-btn {
    width: 28px;
    height: 28px;
  }
}
</style>