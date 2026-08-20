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
          <div v-if="isLoading" class="product-summary-preloader">
            <div class="table-loading-spinner"></div>
            <p class="mt-2 mb-0 text-muted">Loading product inventory...</p>
          </div>

          <div v-else class="row g-3">
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

              <BTabs v-model="activeProductTab">
                <!-- Available Products -->
                <BTab title="Available">
                  <div class="products-list-section mt-2">
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
                            'low-stock-group': isLowStock(product.id),
                          },
                        ]"
                        @click="selectProduct(product, false)"
                      >
                        <div class="d-flex justify-content-between align-items-center">
                          <div>
                            <h6 class="group-name mb-1">{{ product.brand?.name || 'Unnamed Product' }} {{ product.weight || '-' }} {{ product.unit?.name || '' }}</h6>
                            <small class="text-muted">
                              {{ stocksCountByProductId(product.id) }} stock records
                            </small>
                            <small v-if="isLowStock(product.id)" class="low-stock-text d-block">
                              <i class="ri-alarm-warning-line"></i>
                              Low stock
                            </small>
                          </div>
                          <span
                            :class="[
                              'qty-badge',
                              {
                                'qty-badge-active': selectedProductId === product.id,
                                'qty-badge-low': isLowStock(product.id),
                              },
                            ]"
                          >
                            <span class="qty-label">Available</span>
                            <span class="qty-value">{{ totalQuantityByProductId(product.id) }} <span class="qty-unit-label">pcs</span></span>
                            <span class="qty-kg">{{ totalKgByProduct(product) }} kg</span>
                          </span>
                        </div>
                      </b-card>
                    </div>
                    <div v-if="filteredProducts.length === 0" class="text-center text-muted py-4" style="font-size:0.85rem">
                      No products found.
                    </div>
                  </div>
                </BTab>

                <!-- Converted Products -->
                <BTab>
                  <template #title>
                    Converted
                    <span v-if="filteredConvertedProducts.length > 0" class="conv-tab-badge">{{ filteredConvertedProducts.length }}</span>
                  </template>
                  <div class="products-list-section mt-2">
                    <div
                      v-for="product in filteredConvertedProducts"
                      :key="product.id"
                      class="group-card-wrapper"
                      :class="{ 'selected-group': selectedProductId === product.id }"
                    >
                      <b-card
                        :class="[
                          'group-card',
                          { 'active-group': selectedProductId === product.id },
                        ]"
                        @click="selectProduct(product, true)"
                      >
                        <div class="d-flex justify-content-between align-items-center">
                          <div>
                            <h6 class="group-name mb-1">{{ product.brand?.name || 'Unnamed Product' }} {{ product.weight || '-' }} {{ product.unit?.name || '' }}</h6>
                            <small class="text-muted">
                              {{ convertedStocksCountByProductId(product.id) }} converted record{{ convertedStocksCountByProductId(product.id) !== 1 ? 's' : '' }}
                            </small>
                          </div>
                          <span
                            :class="[
                              'qty-badge',
                              { 'qty-badge-active': selectedProductId === product.id },
                            ]"
                            style="border-color:#3d8d7a; background:#e6f4f1; color:#16322e;"
                          >
                            <span class="qty-label">Converted</span>
                            <span class="qty-value">{{ convertedQuantityByProductId(product.id) }} <span class="qty-unit-label">pcs</span></span>
                            <span class="qty-kg">{{ convertedKgByProduct(product) }} kg</span>
                          </span>
                        </div>
                      </b-card>
                    </div>
                    <div v-if="filteredConvertedProducts.length === 0" class="text-center text-muted py-4" style="font-size:0.85rem">
                      No converted products found.
                    </div>
                  </div>
                </BTab>
              </BTabs>
            </div>
            
            <div class="col-lg-8 col-xl-9">
              <div v-if="selectedProduct" class="stocks-section">
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <div>
                    <h5 class="mb-1">{{ selectedProduct.brand?.name || 'Selected Product' }} {{ selectedProduct.weight || '-' }} {{ selectedProduct.unit?.name || '' }}</h5>
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
                  <table class="table inv-stock-table mb-0">
                    <thead>
                      <tr>
                        <th style="width: 5%">#</th>
                        <th>Batch Code</th>
                        <th style="width: 18%">Received Date</th>
                        <th style="width: 21%">Quantity</th>
                        <th class="text-end">Unit Cost</th>
                        <th class="text-end">Retail Price</th>
                        <th class="text-end">Wholesale Price</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="(stock, index) in paginatedProductStocks"
                        :key="stock.id"
                        :class="['stock-row', { 'expiring-soon-row': isWithinThreeMonths(stock.expiration_date) }]"
                        @click="$emit('view-details', stock)"
                      >
                        <td class="text-muted">{{ stockRowStart + index + 1 }}</td>
                        <td>
                          <span class="batch-code">{{ stock.batch_code || '-' }}</span>
                          <span class="inv-chip-row">
                            <span class="inv-chip converted" v-if="stock.conversion_id">Converted</span>
                            <span class="inv-chip expiring" v-if="stock.expiration_date">EXP {{ formatDate(stock.expiration_date) }}</span>
                          </span>
                        </td>
                        <td class="text-muted">{{ stock.received_item ? formatDate(stock.received_item.received_stock?.received_date) : formatDate(stock.created_at) }}</td>
                        <td>
                          <span class="qty-primary">{{ stock.quantity }} <span class="qty-unit">pcs</span></span>
                          <span class="qty-secondary">{{ stockKg(stock, selectedProduct) }} kg</span>
                          <span v-if="stock.total_affected_sacks > 0" class="inv-chip-row mt-1">
                            <span class="inv-chip normal">{{ stock.quantity - stock.total_affected_sacks }} normal</span>
                            <span class="inv-chip short">{{ stock.total_affected_sacks }} short</span>
                          </span>
                        </td>
                        <td class="text-end">{{ formatCurrency(stock.received_item?.unit_cost) }}</td>
                        <td class="text-end fw-semibold">{{ formatCurrency(stock.retail_price) }}</td>
                        <td class="text-end fw-semibold">{{ formatCurrency(stock.wholesale_price) }}</td>
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
                    <button type="button" class="btn btn-sm btn-outline-primary" :disabled="stockPage === 1" @click="stockPage = Math.max(1, stockPage - 1)">Prev</button>
                    <span class="text-muted small">Page {{ stockPage }} / {{ totalStockPages }}</span>
                    <button type="button" class="btn btn-sm btn-outline-primary" :disabled="stockPage === totalStockPages" @click="stockPage = Math.min(totalStockPages, stockPage + 1)">Next</button>
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

import { pollingMixin } from '@/Shared/polling.js';

export default {
  name: 'ProductSummary',
  mixins: [pollingMixin],
  emits: ['view-details'],
  data() {
    return {
      products: [],
      inventoryStocks: [],
      selectedProductId: null,
      viewingConverted: false,
      activeProductTab: 0,
      productKeyword: '',
      batchKeyword: '',
      stockPage: 1,
      stocksPerPage: 10,
      loadingProducts: true,
      loadingStocks: true,
    };
  },
  computed: {
    isLoading() {
      return this.loadingProducts || this.loadingStocks;
    },
    activeProducts() {
      return this.products.filter((product) => this.isProductActive(product));
    },
    filteredProducts() {
      // Exclude only products that have conversion stock but NO received stock
      // (pure conversion-output products belong in the Converted tab only)
      const base = this.activeProducts.filter(p =>
        this.receivedProductIds.has(p.id) || !this.convertedProductIds.has(p.id)
      );
      const keyword = this.productKeyword.trim().toLowerCase();

      if (!keyword) {
        return [...base].sort((a, b) => Number(this.isLowStock(a.id)) - Number(this.isLowStock(b.id)));
      }

      return base.filter((product) => {
        const haystack = [
          product.name,
          product.brand?.name,
          product.code,
          product.weight,
          product.unit?.name,
        ]
          .map((value) => String(value || '').toLowerCase())
          .join(' ');
        return haystack.includes(keyword);
      }).sort((a, b) => Number(this.isLowStock(a.id)) - Number(this.isLowStock(b.id)));
    },
    selectedProduct() {
      return this.products.find((product) => product.id === this.selectedProductId) || null;
    },
    convertedProductIds() {
      const ids = new Set();
      this.inventoryStocks.forEach(s => {
        if (s.conversion_id && s.product?.id) ids.add(s.product.id);
      });
      return ids;
    },
    receivedProductIds() {
      // Products that have at least one stock batch from a PO receipt (not a conversion)
      const ids = new Set();
      this.inventoryStocks.forEach(s => {
        if (!s.conversion_id && s.received_item?.product?.id) ids.add(s.received_item.product.id);
      });
      return ids;
    },
    filteredConvertedProducts() {
      const keyword = this.productKeyword.trim().toLowerCase();
      const list = this.activeProducts.filter(p => this.convertedProductIds.has(p.id));
      if (!keyword) return list;
      return list.filter(product => {
        const haystack = [product.name, product.brand?.name, product.code, product.weight, product.unit?.name]
          .map(v => String(v || '').toLowerCase()).join(' ');
        return haystack.includes(keyword);
      });
    },
    productStocks() {
      if (!this.selectedProductId) return [];
      return this.inventoryStocks.filter((stock) => {
        const matchesProduct =
          stock.received_item?.product?.id === this.selectedProductId ||
          stock.product?.id === this.selectedProductId;
        if (!matchesProduct) return false;
        return this.viewingConverted ? !!stock.conversion_id : !stock.conversion_id;
      });
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
    activeProductTab(tab) {
      if (tab === 0) {
        this.viewingConverted = false;
        this.selectedProductId = this.filteredProducts[0]?.id ?? null;
      } else {
        this.viewingConverted = true;
        this.selectedProductId = this.filteredConvertedProducts[0]?.id ?? null;
      }
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
  mounted() {
    // Only the stock levels need refreshing; the product list barely changes
    // and refetching 1000 rows on a timer would be wasteful.
    this.startPolling(() => this.fetchInventoryStocks({ quiet: true }));
  },
  methods: {
    isProductActive(product) {
      const value = product?.is_active;
      if (value === true || value === 1 || value === '1') return true;
      if (value === false || value === 0 || value === '0') return false;
      return Boolean(value);
    },
    async fetchProducts() {
      this.loadingProducts = true;
      try {
        const response = await axios.get('/libraries/products', {
          params: {
            option: 'lists',
            count: 1000,
          },
        });

        this.products = response?.data?.data || [];
        const savedId = Number(sessionStorage.getItem('inv_selected_product_id'));
        const savedExists = savedId && this.activeProducts.some(p => p.id === savedId);
        if (savedExists) {
          this.selectedProductId = savedId;
        } else if (this.activeProducts.length > 0) {
          this.selectedProductId = this.activeProducts[0].id;
        }
      } catch (error) {
        console.error(error);
        this.products = [];
      } finally {
        this.loadingProducts = false;
      }
    },
    async fetchInventoryStocks({ quiet = false } = {}) {
      // `quiet` is the background refresh: no spinner, so the poll is invisible.
      if (!quiet) {
        this.loadingStocks = true;
      }
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
      } finally {
        if (!quiet) this.loadingStocks = false;
      }
    },
    selectProduct(product, isConverted = false) {
      this.selectedProductId = product.id;
      this.viewingConverted = isConverted;
      sessionStorage.setItem('inv_selected_product_id', product.id);
    },
    convertedStocksCountByProductId(productId) {
      return this.inventoryStocks.filter(s =>
        s.conversion_id &&
        (s.received_item?.product?.id === productId || s.product?.id === productId)
      ).length;
    },
    convertedQuantityByProductId(productId) {
      return this.inventoryStocks
        .filter(s => s.conversion_id && (s.received_item?.product?.id === productId || s.product?.id === productId))
        .reduce((total, s) => total + Number(s.quantity || 0), 0);
    },
    convertedKgByProduct(product) {
      const packSize = parseFloat(product.weight) || 0;
      return this.inventoryStocks
        .filter(s => s.conversion_id && (s.received_item?.product?.id === product.id || s.product?.id === product.id))
        .reduce((sum, s) => sum + Number(s.quantity || 0) * packSize, 0)
        .toLocaleString('en-PH');
    },
    stocksCountByProductId(productId) {
      return this.inventoryStocks.filter((stock) =>
        stock.received_item?.product?.id === productId ||
        stock.product?.id === productId
      ).length;
    },
    totalQuantityByProductId(productId) {
      return this.inventoryStocks
        .filter((stock) =>
          stock.received_item?.product?.id === productId ||
          stock.product?.id === productId
        )
        .reduce((total, stock) => total + Number(stock.quantity || 0), 0);
    },
    isLowStock(productId) {
      if (this.loadingStocks) return false;
      return this.totalQuantityByProductId(productId) < 11;
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
    totalKgByProduct(product) {
      const packSize   = parseFloat(product.weight) || 0;
      const stocks     = this.inventoryStocks.filter(s =>
        s.received_item?.product?.id === product.id ||
        s.product?.id === product.id
      );
      const grossKg    = stocks.reduce((sum, s) => sum + Number(s.quantity || 0) * packSize, 0);
      const lossKg     = stocks.reduce((sum, s) => sum + Number(s.total_weight_loss || 0), 0);
      return Math.max(0, grossKg - lossKg).toLocaleString('en-PH');
    },
    stockKg(stock, product) {
      const packSize = parseFloat(product?.weight) || 0;
      const gross    = Number(stock.quantity || 0) * packSize;
      const loss     = Number(stock.total_weight_loss || 0);
      return Math.max(0, gross - loss).toLocaleString('en-PH');
    },
    isWithinThreeMonths(date) {
      if (!date) return false;
      const expirationDate = new Date(date);
      if (Number.isNaN(expirationDate.getTime())) return false;

      const today = new Date();
      today.setHours(0, 0, 0, 0);

      expirationDate.setHours(0, 0, 0, 0);

      const threeMonthsLater = new Date(today);
      threeMonthsLater.setMonth(threeMonthsLater.getMonth() + 3);
      threeMonthsLater.setHours(23, 59, 59, 999);

      return expirationDate >= today && expirationDate <= threeMonthsLater;
    },
  },
};
</script>

<style scoped>
.product-summary-preloader {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 0;
}

.table-loading-spinner {
  width: 36px;
  height: 36px;
  border: 3px solid #d7e5de;
  border-top-color: #3d8d7a;
  border-radius: 50%;
  animation: table-loading-spin 0.8s linear infinite;
}

@keyframes table-loading-spin {
  to {
    transform: rotate(360deg);
  }
}

.products-list-section {
  overflow-y: auto;
  overflow-x: hidden;
  max-height: 580px;
  padding: 2px;
}

.group-card :deep(.card-body) {
  padding: 0.5rem 0.75rem;
}

.group-card-wrapper {
  margin-bottom: 6px;
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
  font-size: 0.82rem;
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

.inv-stock-table {
  table-layout: fixed;
  width: 100%;
}

.inv-stock-table th,
.inv-stock-table td {
  white-space: normal;
  word-break: break-word;
}

.inv-stock-table thead th {
  background: #edf5f2;
  color: #527267;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.02em;
  padding: 0.6rem 0.8rem;
  border-bottom: 1px solid #c4d9d2;
}

.inv-stock-table tbody td {
  padding: 0.6rem 0.8rem;
  font-size: 0.85rem;
  vertical-align: middle;
  color: #1f2937;
}

.stock-row {
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.stock-row:hover {
  background-color: #f9fafb;
}

.expiring-soon-row {
  background-color: #fef9c3;
}

.expiring-soon-row:hover {
  background-color: #fef3a3;
}

.batch-code {
  font-weight: 600;
  color: #16322e;
}

.inv-chip-row {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: 4px;
}

.inv-chip {
  display: inline-flex;
  align-items: center;
  padding: 2px 10px;
  border-radius: 999px;
  font-size: 0.68rem;
  font-weight: 700;
  white-space: nowrap;
}

.inv-chip.converted { background: #e6f4f1; color: #16322e; }
.inv-chip.expiring  { background: #fef9c3; color: #854d0e; }
.inv-chip.normal    { background: #dcfce7; color: #166534; }
.inv-chip.short     { background: #fee2e2; color: #991b1b; }

.qty-primary {
  font-weight: 600;
  color: #1f2937;
}

.qty-unit {
  font-weight: 500;
  color: #6b7280;
  font-size: 0.78rem;
}

.qty-secondary {
  display: block;
  font-size: 0.75rem;
  color: #6b8c85;
}

.qty-badge {
  min-width: 60px;
  padding: 4px 8px;
  border: 1px solid #bfe7cf;
  border-radius: 8px;
  background: #e8f7ef;
  color: #146c43;
  text-align: center;
  line-height: 1.1;
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  gap: 1px;
}

.qty-label {
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.4px;
  text-transform: uppercase;
  opacity: 0.9;
}

.qty-value {
  font-size: 13px;
  font-weight: 800;
}

.qty-unit-label {
  font-size: 9px;
  font-weight: 600;
  opacity: 0.7;
}

.qty-kg {
  font-size: 10px;
  font-weight: 700;
  opacity: 0.85;
  letter-spacing: 0.2px;
}

.qty-badge-active {
  border-color: #0f5132;
  box-shadow: 0 0 0 2px rgba(20, 108, 67, 0.12);
}

.qty-badge-low {
  border-color: #f3c2c7;
  background: #fdecef;
  color: #9f1239;
}

.low-stock-text {
  color: #b42318;
  font-size: 10px;
  font-weight: 700;
  margin-top: 1px;
}

.conv-tab-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  margin-left: 5px;
  background: #3d8d7a;
  color: #fff;
  border-radius: 20px;
  font-size: 0.65rem;
  font-weight: 700;
  line-height: 1;
  vertical-align: middle;
}
</style>
