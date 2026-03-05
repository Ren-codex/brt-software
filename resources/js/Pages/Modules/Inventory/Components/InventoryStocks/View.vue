<template>
  <div class="inventory-stock-details">
    <div class="row" v-if="data">
      <div class="col-sm-8">
        <div class="row">
          <div class="col-lg-12 mb-4">
            <div class="library-card">
              <div class="library-card-header">
                <div class="d-flex align-items-center justify-content-between">
                  <div class="d-flex align-items-center gap-3">
                    <div class="header-icon">
                      <i class="ri-archive-line"></i>
                    </div>
                    <div>
                      <h4 class="header-title mb-1">Inventory Stock #{{ data.id }}</h4>
                      <p class="header-subtitle mb-0">View and manage inventory stock details</p>
                    </div>
                  </div>
                  <div class="d-flex gap-2">
                    <button class="create-btn" @click="updatePrice()">
                      <span>Update Price</span>
                    </button>
                    <button v-if="data.quantity > 0" class="create-btn" @click="adjustStock">
                      <i class="ri-edit-line"></i>
                      <span>Adjust Stocks</span>
                    </button>
                    <button @click="$emit('back')" class="create-btn" v-b-tooltip.hover title="Back">
                      <i class="ri-arrow-left-line"></i>
                    </button>
                  </div>
                </div>
              </div>
              <div class="library-card-body">
                <div class="row">
                  <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                      <div class="card-body p-4">
                        
                        <!-- Pricing & Quantity Section -->
                        <div class="mb-4">
                          <h5 class="card-title mb-3 d-flex align-items-center">
                            <span class="badge bg-success me-2" style="width: 4px; height: 20px;"></span>
                            Pricing & Quantity
                          </h5>
                          
                          <div class="row g-4">
                            <div class="col-md-6">
                              <div class="info-item">
                                <label class="text-muted small text-uppercase tracking-wide mb-1">Unit Cost</label>
                                <p class="fw-semibold mb-0 fs-5 text-success">
                                  {{ formatCurrency(data.received_item?.unit_cost) }}
                                </p>
                              </div>
                            </div>

                            <div class="col-md-6">
                              <div class="info-item">
                                <label class="text-muted small text-uppercase tracking-wide mb-1">Quantity</label>
                                <p class="fw-semibold mb-0 fs-5">
                                  <span class="badge bg-info bg-opacity-10 text-info px-3 py-2">
                                    <i class="ri-stack-line me-1"></i>
                                    {{ data.quantity }} units
                                  </span>
                                </p>
                              </div>
                            </div>

                            <div class="col-md-6">
                              <div class="info-item">
                                <label class="text-muted small text-uppercase tracking-wide mb-1">Retail Price</label>
                                <p class="fw-semibold mb-0">
                                  <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2">
                                    <i class="ri-price-tag-3-line me-1"></i>
                                    {{ formatCurrency(data.retail_price) }}
                                  </span>
                                </p>
                              </div>
                            </div>

                            <div class="col-md-6">
                              <div class="info-item">
                                <label class="text-muted small text-uppercase tracking-wide mb-1">Wholesale Price</label>
                                <p class="fw-semibold mb-0">
                                  <span class="badge bg-warning bg-opacity-10 text-warning px-3 py-2">
                                    <i class="ri-price-tag-line me-1"></i>
                                    {{ formatCurrency(data.wholesale_price) }}
                                  </span>
                                </p>
                              </div>
                            </div>
                          </div>
                        </div>
                        
                        <!-- Received Information Section -->
                        <div class="mb-4">
                          <h5 class="card-title mb-3 d-flex align-items-center">
                            <span class="badge bg-primary me-2" style="width: 4px; height: 20px;"></span>
                            Received Information
                          </h5>
                          
                          <div class="row g-4">
                            <div class="col-md-6">
                              <div class="info-item">
                                <label class="text-muted small text-uppercase tracking-wide mb-1">Received Date</label>
                                <p class="fw-semibold mb-0 d-flex align-items-center">
                                  <i class="ri-calendar-line text-primary me-2"></i>
                                  {{ formatDate(data.received_item?.received_stock?.received_date) || 'N/A' }}
                                </p>
                              </div>
                            </div>
                            
                            <div class="col-md-6">
                              <div class="info-item">
                                <label class="text-muted small text-uppercase tracking-wide mb-1">Batch Code</label>
                                <p class="fw-semibold mb-0 d-flex align-items-center">
                                  <i class="ri-barcode-line text-primary me-2"></i>
                                  {{ data.batch_code || 'N/A' }}
                                </p>
                              </div>
                            </div>

                            <div class="col-md-6">
                              <div class="info-item">
                                <label class="text-muted small text-uppercase tracking-wide mb-1">Supplier</label>
                                <p class="fw-semibold mb-0 d-flex align-items-center">
                                  <i class="ri-truck-line text-primary me-2"></i>
                                  {{ data.received_item?.received_stock?.supplier?.name || 'N/A' }}
                                </p>
                              </div>
                            </div>

                            <div class="col-md-6">
                              <div class="info-item">
                                <label class="text-muted small text-uppercase tracking-wide mb-1">Product</label>
                                <p class="fw-semibold mb-0 d-flex align-items-center">
                                  <i class="ri-shopping-bag-line text-primary me-2"></i>
                                  {{ data.received_item?.product?.name || 'N/A' }}
                                </p>
                              </div>
                            </div>
                          </div>
                        </div>

                        <!-- Divider -->
                        <hr class="my-4">

                        <!-- Divider -->
                        <hr class="my-4">

                        <!-- Additional Information Section -->
                        <div class="mb-3">
                          <h5 class="card-title mb-3 d-flex align-items-center">
                            <span class="badge bg-info me-2" style="width: 4px; height: 20px;"></span>
                            Additional Information
                          </h5>
                          
                          <div class="row g-4">
                            <div class="col-md-4">
                              <div class="info-item">
                                <label class="text-muted small text-uppercase tracking-wide mb-1">Expiration Date</label>
                                <p class="fw-semibold mb-0" :class="getExpirationClass(data.expiration_date)">
                                  <i class="ri-calendar-event-line me-2"></i>
                                  {{ formatDate(data.expiration_date) || 'No expiration' }}
                                </p>
                              </div>
                            </div>

                            <div class="col-md-4">
                              <div class="info-item">
                                <label class="text-muted small text-uppercase tracking-wide mb-1">Created At</label>
                                <p class="fw-semibold mb-0">
                                  <i class="ri-time-line me-2 text-muted"></i>
                                  {{ formatDate(data.created_at) }}
                                </p>
                              </div>
                            </div>

                            <div class="col-md-4">
                              <div class="info-item">
                                <label class="text-muted small text-uppercase tracking-wide mb-1">Last Updated</label>
                                <p class="fw-semibold mb-0">
                                  <i class="ri-refresh-line me-2 text-muted"></i>
                                  {{ formatDate(data.updated_at) }}
                                </p>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Side Panel for Quick Actions/Summary -->
                  <div class="col-lg-4">
                    <div class="card border-0 shadow-sm bg-light">
                      <div class="card-body p-4">
                        <h6 class="card-title mb-3">Quick Summary</h6>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                          <span class="text-muted">Total Value</span>
                          <span class="fw-bold fs-5">{{ calculateTotalValue() }}</span>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                          <span class="text-muted">Profit Margin (Retail)</span>
                          <span class="fw-bold text-success">{{ calculateProfitMargin('retail') }}</span>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-4">
                          <span class="text-muted">Profit Margin (Wholesale)</span>
                          <span class="fw-bold text-warning">{{ calculateProfitMargin('wholesale') }}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-sm-4">
        <div class="library-card">
          <div class="library-card-header">
            <div class="d-flex align-items-center gap-3">
              <div class="header-icon">
                <i class="ri-history-line"></i>
              </div>
              <div>
                <h4 class="header-title mb-1">Inventory Adjustment Logs</h4>
                <p class="header-subtitle mb-0">Activity history and adjustments</p>
              </div>
            </div>
          </div>
          <div class="library-card-body">
            <div class="table-section" v-if="data.inventory_adjustments && data.inventory_adjustments.length">
              <div class="table-responsive">
                <table class="table align-middle table-centered mb-0">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>Quantity</th>
                      <th>Type</th>
                      <th>Reason</th>
                      <th>Adjusted By</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(log, index) in data.inventory_adjustments" :key="log.id">
                      <td>{{ formatDate(log.adjustment_date) }}</td>
                      <td>{{ log.previous_quantity }} → {{ log.new_quantity }}</td>
                      <td>{{ log.type }}</td>
                      <td>{{ log.reason }}</td>
                      <td>{{ log.received_by ? log.received_by.fullname : 'N/A' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <p v-else>No adjustment logs available.</p>
          </div>
        </div>
      </div>
    </div>

    <UpdatePriceModal
      :inventoryStock="data"
      @saved="$inertia.reload()"
      ref="updatePriceDialog"
    />

    <AdjustStockModal
      :inventoryStock="data"
      @saved="$inertia.reload()"
      ref="adjustStockDialog"
    />
  </div>
</template>

<script>
import AdjustStockModal from '../../Modal/AdjustStockModal.vue';
import UpdatePriceModal from '../../Modal/UpdatePriceModal.vue';

export default {
  emits: ['back'],
  components: {
    AdjustStockModal,
    UpdatePriceModal,
  },
  props: {
    stock: Object,
    inventory_stock_data: Object,
    dropdowns: Object,
  },
  data() {
    return {
    };
  },
  computed: {
    data() {
      if (this.stock) {
        return this.stock;
      }
      return this.inventory_stock_data?.data || null;
    },
  },
  methods: {
    updatePrice() {
      this.$refs.updatePriceDialog.show();
    },
    adjustStock() {
      this.$refs.adjustStockDialog.show();
    },
    formatDate(date) {
      return new Date(date).toLocaleDateString();
    },
    formatCurrency(amount) {
      return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP'
      }).format(amount);
    },
    getExpirationClass(date) {
      if (!date) return 'text-muted';
      const today = new Date();
      const expDate = new Date(date);
      const daysUntilExp = Math.ceil((expDate - today) / (1000 * 60 * 60 * 24));
      
      if (daysUntilExp < 0) return 'text-danger fw-bold';
      if (daysUntilExp < 30) return 'text-warning fw-bold';
      return 'text-success';
    },
    calculateTotalValue() {
      const quantity = this.data.quantity || 0;
      const unitCost = this.data.received_item?.unit_cost || 0;
      return this.formatCurrency(quantity * unitCost);
    },
    calculateProfitMargin(type) {
      const unitCost = this.data.received_item?.unit_cost || 0;
      const price = type === 'retail' ? this.data.retail_price : this.data.wholesale_price;
      
      if (!unitCost || !price) return 'N/A';
      
      const margin = ((price - unitCost) / price) * 100;
      return margin.toFixed(1) + '%';
    }
  }
};
</script>
<style scoped>
.info-item {
  transition: transform 0.2s ease;
  padding: 0.5rem;
  border-radius: 0.5rem;
}

.info-item:hover {
  transform: translateX(5px);
  background-color: rgba(13, 110, 253, 0.03);
}

.tracking-wide {
  letter-spacing: 0.5px;
}

.card {
  transition: box-shadow 0.3s ease;
}

.card:hover {
  box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08) !important;
}

.badge {
  font-weight: 500;
}

hr {
  opacity: 0.1;
}
</style>
