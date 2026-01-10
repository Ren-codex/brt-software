<template>
  <div class="card shadow-lg border-0 text-primary">
    <div class="card-header border-0 text-primary">
      <h4>
        <i class="ri-dashboard-line"></i> Quick Stats
        <hr class="mb-0">
      </h4>
    </div>

    <div class="card-body">
      <!-- Purchase Requests Tab Stats -->
      <template v-if="activeTab === 'purchaseRequests'">
        <div class="metric-card mb-3 p-3 bg-light rounded">
          <div class="d-flex align-items-center">
            <div class="avatar-sm flex-shrink-0">
              <span class="avatar-title bg-danger text-white rounded">
                <i class="ri-time-line fs-18"></i>
              </span>
            </div>
            <div class="flex-grow-1 ms-3">
              <p class="fw-semibold fs-12 mb-1">Pending Requests</p>
              <h4 class="mb-0">{{ pendingRequests }}</h4>
            </div>
          </div>
        </div>

        <div class="metric-card mb-3 p-3 bg-light rounded">
          <div class="d-flex align-items-center">
            <div class="avatar-sm flex-shrink-0">
              <span class="avatar-title bg-info text-white rounded">
                <i class="ri-shopping-cart-2-line fs-18"></i>
              </span>
            </div>
            <div class="flex-grow-1 ms-3">
              <p class="fw-semibold fs-12 mb-1">Purchase Orders</p>
              <h4 class="mb-0">{{ totalPurchaseOrders }}</h4>
            </div>
          </div>
        </div>

        <div class="metric-card p-3 bg-light rounded">
          <div class="d-flex align-items-center">
            <div class="avatar-sm flex-shrink-0">
              <span class="avatar-title bg-secondary text-white rounded">
                <i class="ri-alert-line fs-18"></i>
              </span>
            </div>
            <div class="flex-grow-1 ms-3">
              <p class="fw-semibold fs-12 mb-1">Low Stock Items</p>
              <h4 class="mb-0">{{ lowStockItems }}</h4>
            </div>
          </div>
        </div>
      </template>

      <!-- Purchase Orders Tab Stats -->
      <template v-else-if="activeTab === 'purchaseOrders'">
        <div class="metric-card mb-3 p-3 bg-light rounded">
          <div class="d-flex align-items-center">
            <div class="avatar-sm flex-shrink-0">
              <span class="avatar-title bg-info text-white rounded">
                <i class="ri-shopping-cart-2-line fs-18"></i>
              </span>
            </div>
            <div class="flex-grow-1 ms-3">
              <p class="fw-semibold fs-12 mb-1">Purchase Orders</p>
              <h4 class="mb-0">{{ totalPurchaseOrders }}</h4>
            </div>
          </div>
        </div>

        <div class="metric-card mb-3 p-3 bg-light rounded">
          <div class="d-flex align-items-center">
            <div class="avatar-sm flex-shrink-0">
              <span class="avatar-title bg-danger text-white rounded">
                <i class="ri-time-line fs-18"></i>
              </span>
            </div>
            <div class="flex-grow-1 ms-3">
              <p class="fw-semibold fs-12 mb-1">Pending Requests</p>
              <h4 class="mb-0">{{ pendingRequests }}</h4>
            </div>
          </div>
        </div>

        <div class="metric-card p-3 bg-light rounded">
          <div class="d-flex align-items-center">
            <div class="avatar-sm flex-shrink-0">
              <span class="avatar-title bg-success text-white rounded">
                <i class="ri-money-dollar-circle-line fs-18"></i>
              </span>
            </div>
            <div class="flex-grow-1 ms-3">
              <p class="fw-semibold fs-12 mb-1">Inventory Value</p>
              <h4 class="mb-0">{{ formatCurrency(totalInventoryValue) }}</h4>
            </div>
          </div>
        </div>
      </template>

      <!-- Products Tab Stats -->
      <template v-else-if="activeTab === 'products'">
        <div class="metric-card mb-3 p-3 bg-light rounded">
          <div class="d-flex align-items-center">
            <div class="avatar-sm flex-shrink-0">
              <span class="avatar-title bg-primary text-white rounded">
                <i class="ri-box-3-line fs-18"></i>
              </span>
            </div>
            <div class="flex-grow-1 ms-3">
              <p class="fw-semibold fs-12 mb-1">Total Products</p>
              <h4 class="mb-0">{{ totalProducts }}</h4>
            </div>
          </div>
        </div>

        <div class="metric-card mb-3 p-3 bg-light rounded">
          <div class="d-flex align-items-center">
            <div class="avatar-sm flex-shrink-0">
              <span class="avatar-title bg-warning text-white rounded">
                <i class="ri-store-3-line fs-18"></i>
              </span>
            </div>
            <div class="flex-grow-1 ms-3">
              <p class="fw-semibold fs-12 mb-1">Stock Items</p>
              <h4 class="mb-0">{{ totalInventoryStocks }}</h4>
            </div>
          </div>
        </div>

        <div class="metric-card p-3 bg-light rounded">
          <div class="d-flex align-items-center">
            <div class="avatar-sm flex-shrink-0">
              <span class="avatar-title bg-success text-white rounded">
                <i class="ri-money-dollar-circle-line fs-18"></i>
              </span>
            </div>
            <div class="flex-grow-1 ms-3">
              <p class="fw-semibold fs-12 mb-1">Inventory Value</p>
              <h4 class="mb-0">{{ formatCurrency(totalInventoryValue) }}</h4>
            </div>
          </div>
        </div>
      </template>

      <!-- Inventory Stocks Tab Stats -->
      <template v-else-if="activeTab === 'inventoryStocks'">
        <div class="metric-card mb-3 p-3 bg-light rounded">
          <div class="d-flex align-items-center">
            <div class="avatar-sm flex-shrink-0">
              <span class="avatar-title bg-warning text-white rounded">
                <i class="ri-store-3-line fs-18"></i>
              </span>
            </div>
            <div class="flex-grow-1 ms-3">
              <p class="fw-semibold fs-12 mb-1">Stock Items</p>
              <h4 class="mb-0">{{ totalInventoryStocks }}</h4>
            </div>
          </div>
        </div>

        <div class="metric-card mb-3 p-3 bg-light rounded">
          <div class="d-flex align-items-center">
            <div class="avatar-sm flex-shrink-0">
              <span class="avatar-title bg-success text-white rounded">
                <i class="ri-money-dollar-circle-line fs-18"></i>
              </span>
            </div>
            <div class="flex-grow-1 ms-3">
              <p class="fw-semibold fs-12 mb-1">Inventory Value</p>
              <h4 class="mb-0">{{ formatCurrency(totalInventoryValue) }}</h4>
            </div>
          </div>
        </div>

        <div class="metric-card p-3 bg-light rounded">
          <div class="d-flex align-items-center">
            <div class="avatar-sm flex-shrink-0">
              <span class="avatar-title bg-secondary text-white rounded">
                <i class="ri-alert-line fs-18"></i>
              </span>
            </div>
            <div class="flex-grow-1 ms-3">
              <p class="fw-semibold fs-12 mb-1">Low Stock Items</p>
              <h4 class="mb-0">{{ lowStockItems }}</h4>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<script>
export default {
  name: 'QuickStatsSidebar',
  props: {
    activeTab: {
      type: String,
      default: ''
    },
    listProducts: {
      type: Array,
      default: () => []
    },
    listPurchaseOrders: {
      type: Array,
      default: () => []
    },
    listPurchaseRequests: {
      type: Array,
      default: () => []
    },
    listInventoryStocks: {
      type: Array,
      default: () => []
    }
  },
  computed: {
    totalProducts() {
      return this.listProducts.length;
    },
    totalPurchaseOrders() {
      return this.listPurchaseOrders.length;
    },
    totalInventoryStocks() {
      return this.listInventoryStocks.length;
    },
    pendingRequests() {
      return this.listPurchaseRequests.length;
    },
    lowStockItems() {
      // Count items with quantity <= 10 as low stock
      return this.listInventoryStocks.filter(stock => stock.quantity <= 10).length;
    },
    totalInventoryValue() {
      return this.listInventoryStocks.reduce((total, stock) => {
        const retailPrice = stock.retail_price || stock.product?.price || 0;
        return total + (retailPrice * stock.quantity);
      }, 0);
    }
  },
  methods: {
    formatCurrency(value) {
      if (!value) return '₱0.00';
      return '₱' + Number(value).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    }
  }
}
</script>

<style scoped>
.quick-stats-sidebar {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
  padding: 20px;
  margin-bottom: 20px;
  border: 1px solid #e9ecef;
}

.quick-stats-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 1px solid #e9ecef;
}

.quick-stats-header i {
  font-size: 20px;
  color: #007bff;
}

.quick-stats-header h5 {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
  color: #495057;
}

.quick-stats-content {
  display: grid;
  grid-template-columns: 1fr;
  gap: 15px;
}

.stat-card {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 15px;
  background: #f8f9fa;
  border-radius: 8px;
  border: 1px solid #e9ecef;
  transition: all 0.3s ease;
}

.stat-card:hover {
  background: #e9ecef;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.stat-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 45px;
  height: 45px;
  background: linear-gradient(135deg, #007bff, #0056b3);
  border-radius: 10px;
  color: white;
  font-size: 18px;
}

.stat-info {
  flex: 1;
}

.stat-value {
  font-size: 20px;
  font-weight: 700;
  color: #212529;
  margin-bottom: 2px;
}

.stat-label {
  font-size: 12px;
  color: #6c757d;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 500;
}

@media (max-width: 768px) {
  .quick-stats-sidebar {
    padding: 15px;
  }

  .stat-card {
    padding: 12px;
    gap: 12px;
  }

  .stat-icon {
    width: 40px;
    height: 40px;
    font-size: 16px;
  }

  .stat-value {
    font-size: 18px;
  }
}
</style>
