<template>
  <BRow>
    <div class="col-lg-12 mb-4">
      <div class="library-card">
        <div class="library-card-header">
          <div class="d-flex align-items-center gap-3">
            <div class="header-icon">
              <i class="ri-survey-line fs-24"></i>
            </div>
            <div>
              <h4 class="header-title mb-1">Product Inventory</h4>
              <p class="header-subtitle mb-0">Select a product to view all inventory stocks</p>
            </div>
          </div>
        </div>

        <div class="card-body m-2 p-3">
          <div class="row g-3">
            <div class="col-lg-4 col-xl-3">
              <div class="search-wrapper mb-3">
                <i class="ri-search-line search-icon"></i>
                <input
                  v-model="productKeyword"
                  type="text"
                  placeholder="Search products..."
                  class="search-input"
                >
              </div>

              <div class="products-list-section">
                <div
                  v-for="product in filteredProducts"
                  :key="product.id"
                  class="group-card-wrapper"
                  :class="{ 'selected-group': selectedProductId === product.id }"
                >
                  <b-card
                    :class="[
                      'group-card',
                      {
                        'active-group': selectedProductId === product.id,
                        'low-stock-group': totalQuantityByProductId(product.id) < 11,
                      },
                    ]"
                    @click="selectProduct(product)"
                  >
                    <div class="d-flex justify-content-between align-items-center">
                      <div>
                        <h6 class="group-name mb-1">{{ product.brand?.name || 'Unnamed Product' }} {{ product.pack_size || '-' }} {{ product.unit?.name || '' }}</h6>
                        <small class="text-muted">
                          {{ stocksCountByProductId(product.id) }} stock records
                        </small>
                      </div>
                      <span class="qty-badge">
                        <span class="qty-value">{{ totalQuantityByProductId(product.id) }}</span>
                      </span>
                    </div>
                  </b-card>
                </div>
              </div>
            </div>
            
            <div class="col-lg-8 col-xl-9">
              <div v-if="selectedProduct" class="stocks-section">
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <div>
                    <h5 class="mb-1">{{ selectedProduct.brand?.name || 'Selected Product' }} {{ selectedProduct.pack_size || '-' }} {{ selectedProduct.unit?.name || '' }}</h5>
                    <p class="text-muted mb-0">Inventory Stocks</p>
                  </div>
                  <div class="search-wrapper stocks-search">
                    <i class="ri-search-line search-icon"></i>
                    <input
                      v-model="batchKeyword"
                      type="text"
                      placeholder="Search batch..."
                      class="search-input"
                    >
                  </div>
                </div>

                <div class="table-responsive">
                  <table class="table align-middle table-hover mb-0">
                    <thead class="table-light">
                      <tr>
                        <th style="width: 5%">#</th>
                        <th>Batch Code</th>
                        <th style="width: 20%">Received Date</th>
                        <th>Quantity</th>
                        <th>Unit Cost</th>
                        <th>Retail Price</th>
                        <th>Wholesale Price</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="(stock, index) in paginatedProductStocks"
                        :key="stock.id"
                        :class="['stock-row', { 'expiring-soon-row': isWithinThreeMonths(stock.expiration_date) }]"
                        @click="$emit('view-details', stock)"
                      >
                        <td>{{ stockRowStart + index + 1 }}</td>
                        <td>{{ stock.batch_code || '-' }}</td>
                        <td>{{ formatDate(stock.received_item?.received_stock?.received_date) }}
                          <span 
                            class="status-badge"
                            v-if="stock.expiration_date"
                          >
                            EXP: {{ formatDate(stock.expiration_date) }}
                          </span>
                        </td>
                        <td>{{ stock.quantity }}</td>
                        <td>{{ formatCurrency(stock.received_item?.unit_cost) }}</td>
                        <td>{{ formatCurrency(stock.retail_price) }}</td>
                        <td>{{ formatCurrency(stock.wholesale_price) }}</td>
                      </tr>
                      <tr v-if="filteredProductStocks.length === 0">
                        <td colspan="7" class="text-center py-4 text-muted">
                          No inventory stocks found for this product.
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <div v-if="totalFilteredStockRows > 0" class="stocks-pagination d-flex justify-content-between align-items-center mt-3">
                  <small class="text-muted">
                    Showing {{ stockRowStart + 1 }} - {{ Math.min(stockRowStart + stocksPerPage, totalFilteredStockRows) }}
                    of {{ totalFilteredStockRows }}
                  </small>
                  <div class="d-flex align-items-center gap-2">
                    <button
                      type="button"
                      class="btn btn-sm btn-outline-primary"
                      :disabled="stockPage === 1"
                      @click="stockPage = Math.max(1, stockPage - 1)"
                    >
                      Prev
                    </button>
                    <span class="text-muted small">Page {{ stockPage }} / {{ totalStockPages }}</span>
                    <button
                      type="button"
                      class="btn btn-sm btn-outline-primary"
                      :disabled="stockPage === totalStockPages"
                      @click="stockPage = Math.min(totalStockPages, stockPage + 1)"
                    >
                      Next
                    </button>
                  </div>
                </div>
              </div>

              <div v-else class="text-center py-5">
                <i class="ri-layout-grid-line text-muted" style="font-size: 56px;"></i>
                <h5 class="text-muted mt-2 mb-1">Select a Product</h5>
                <p class="text-muted mb-0">Choose a product on the left to view inventory stocks.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </BRow>
</template>

<script>
import axios from 'axios';

export default {
  name: 'ProductSummary',
  emits: ['view-details'],
  data() {
    return {
      products: [],
      inventoryStocks: [],
      selectedProductId: null,
      productKeyword: '',
      batchKeyword: '',
      stockPage: 1,
      stocksPerPage: 10,
    };
  },
  computed: {
    activeProducts() {
      return this.products.filter((product) => this.isProductActive(product));
    },
    filteredProducts() {
      const keyword = this.productKeyword.trim().toLowerCase();
      if (!keyword) return this.activeProducts;

      return this.activeProducts.filter((product) => {
        const haystack = [
          product.name,
          product.brand?.name,
          product.code,
          product.pack_size,
          product.unit?.name,
        ]
          .map((value) => String(value || '').toLowerCase())
          .join(' ');

        return haystack.includes(keyword);
      });
    },
    selectedProduct() {
      return this.products.find((product) => product.id === this.selectedProductId) || null;
    },
    productStocks() {
      if (!this.selectedProductId) return [];
      return this.inventoryStocks.filter((stock) => stock.received_item?.product?.id === this.selectedProductId);
    },
    filteredProductStocks() {
      const keyword = this.batchKeyword.trim().toLowerCase();
      if (!keyword) return this.productStocks;

      return this.productStocks.filter((stock) =>
        String(stock.batch_code || '').toLowerCase().includes(keyword)
      );
    },
    totalFilteredStockRows() {
      return this.filteredProductStocks.length;
    },
    totalStockPages() {
      return Math.max(1, Math.ceil(this.totalFilteredStockRows / this.stocksPerPage));
    },
    stockRowStart() {
      return (this.stockPage - 1) * this.stocksPerPage;
    },
    paginatedProductStocks() {
      return this.filteredProductStocks.slice(this.stockRowStart, this.stockRowStart + this.stocksPerPage);
    },
  },
  watch: {
    selectedProductId() {
      this.stockPage = 1;
    },
    batchKeyword() {
      this.stockPage = 1;
    },
    totalStockPages(newVal) {
      if (this.stockPage > newVal) {
        this.stockPage = newVal;
      }
    },
  },
  created() {
    this.fetchProducts();
    this.fetchInventoryStocks();
  },
  methods: {
    isProductActive(product) {
      const value = product?.is_active;
      if (value === true || value === 1 || value === '1') return true;
      if (value === false || value === 0 || value === '0') return false;
      return Boolean(value);
    },
    async fetchProducts() {
      try {
        const response = await axios.get('/libraries/products', {
          params: {
            option: 'lists',
            count: 1000,
          },
        });

        this.products = response?.data?.data || [];
        if (!this.selectedProductId && this.products.length > 0) {
          this.selectedProductId = this.products[0].id;
        }
      } catch (error) {
        console.error(error);
        this.products = [];
      }
    },
    async fetchInventoryStocks() {
      try {
        const response = await axios.get('/inventory-stocks', {
          params: {
            option: 'lists',
            count: 1000,
          },
        });

        this.inventoryStocks = response?.data?.data || [];
      } catch (error) {
        console.error(error);
        this.inventoryStocks = [];
      }
    },
    selectProduct(product) {
      this.selectedProductId = product.id;
    },
    stocksCountByProductId(productId) {
      return this.inventoryStocks.filter((stock) => stock.received_item?.product?.id === productId).length;
    },
    totalQuantityByProductId(productId) {
      return this.inventoryStocks
        .filter((stock) => stock.received_item?.product?.id === productId)
        .reduce((total, stock) => total + Number(stock.quantity || 0), 0);
    },
    formatDate(date) {
      if (!date) return '-';
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
      });
    },
    formatCurrency(amount) {
      if (amount === null || amount === undefined || amount === '') return 'PHP 0.00';
      return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
      }).format(Number(amount));
    },
    isWithinThreeMonths(date) {
      if (!date) return false;
      const today = new Date();
      const expirationDate = new Date(date);
      if (Number.isNaN(expirationDate.getTime())) return false;

      const threeMonthsLater = new Date(today);
      threeMonthsLater.setMonth(threeMonthsLater.getMonth() + 3);

      return expirationDate >= today && expirationDate <= threeMonthsLater;
    },
  },
};
</script>

<style scoped>
.products-list-section {
  overflow-y: auto;
  overflow-x: hidden;
  padding: 2px;
}

.group-card-wrapper {
  margin-bottom: 12px;
  border-radius: 8px;
  transition: all 0.3s ease;
}

.group-card-wrapper.selected-group {
  outline: 2px solid rgb(6, 107.4, 93.6);
  outline-offset: 0;
}

.group-card {
  border: 1px solid #e9ecef;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s ease;
  background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
}

.group-card:hover {
  border-color: rgb(6, 107.4, 93.6);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(74, 108, 247, 0.15);
}

.group-card.active-group {
  border-color: rgb(6, 107.4, 93.6);
  background: linear-gradient(135deg, #ffffff 0%, #e1fffb 100%);
}

.group-card.low-stock-group {
  border-color: #dc3545;
  background: linear-gradient(135deg, #ffffff 0%, #ffe5e8 100%);
}

.group-name {
  color: #343a40;
  font-weight: 600;
  font-size: 1rem;
}

.search-wrapper {
  position: relative;
}

.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #6c757d;
}

.search-input {
  width: 100%;
  padding: 8px 16px 8px 40px;
  border: 1px solid #dee6e3;
  border-radius: 6px;
}

.search-input:focus {
  outline: none;
  border-color: #04ad56;
}

.stocks-search {
  min-width: 220px;
}

.stocks-pagination {
  gap: 12px;
  flex-wrap: wrap;
}

.stocks-section .table {
  table-layout: fixed;
  width: 100%;
}

.stocks-section .table th,
.stocks-section .table td {
  white-space: normal;
  word-break: break-word;
}

.stock-row {
  cursor: pointer;
}

.expiring-soon-row {
  background-color: #fff3cd;
}

.qty-badge {
  min-width: 36px;
  padding: 2px 8px;
  border: 1px solid #dee2e6;
  border-radius: 999px;
  background: #f8f9fa;
  color: #6c757d;
  text-align: center;
  line-height: 1.1;
}

.qty-label {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.4px;
  text-transform: uppercase;
  opacity: 0.9;
}

.qty-value {
  font-size: 12px;
  font-weight: 600;
}

.qty-badge-active {
  min-width: 72px;
  padding: 6px 10px;
  border: 0;
  border-radius: 10px;
  background: #e8f7ef;
  color: #146c43;
}

.qty-badge-active .qty-value {
  font-size: 18px;
  font-weight: 800;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  transition: all 0.3s ease;
  cursor: default;
  background-color: #7a848e;
  color: white;
}
</style>
