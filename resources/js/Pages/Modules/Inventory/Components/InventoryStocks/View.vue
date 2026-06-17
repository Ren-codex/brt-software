<template>
  <div class="inventory-stock-details">
    <div class="row" v-if="data">
      <div class="col-sm-8">
        <div class="row">
          <div class="col-lg-12 mb-4">
            <div class="library-card">
              <div class="library-card-header">
                  <div class="d-flex align-items-center gap-3">
                    <div class="header-icon">
                      <i class="ri-archive-line"></i>
                    </div>
                    <div>
                      <h4 class="header-title mb-1">{{ data.batch_code }}</h4>
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
              <div class="library-card-body">
                <div class="row">
                  <div class="col-lg-8">
                    <div class="row">
                      <div class="col-12 mb-1">
                        <p class="section-label">Pricing &amp; Quantity</p>
                      </div>
                      <div class="col-md-6">
                        <div class="mb-3">
                          <label class="form-label">Unit Cost</label>
                          <p class="text-muted">{{ formatCurrency(data.received_item?.unit_cost) }}</p>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="mb-3">
                          <label class="form-label">Quantity</label>
                          <p class="text-muted">{{ data.quantity }} units</p>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="mb-3">
                          <label class="form-label">Retail Price</label>
                          <p class="text-muted">{{ formatCurrency(data.retail_price) }}</p>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="mb-3">
                          <label class="form-label">Wholesale Price</label>
                          <p class="text-muted">{{ formatCurrency(data.wholesale_price) }}</p>
                        </div>
                      </div>

                      <div class="col-12 mb-1 mt-2">
                        <p class="section-label">Received Information</p>
                      </div>
                      <div class="col-md-6">
                        <div class="mb-3">
                          <label class="form-label">Received Date</label>
                          <p class="text-muted">{{ formatDate(data.received_item?.received_stock?.received_date) || 'N/A' }}</p>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="mb-3">
                          <label class="form-label">Product</label>
                          <p class="text-muted">{{ data.received_item?.product?.name || 'N/A' }}</p>
                        </div>
                      </div>
                      <div class="col-md-12">
                        <div class="mb-3">
                          <label class="form-label">Supplier</label>
                          <p class="text-muted">{{ data.received_item?.received_stock?.supplier?.name || 'N/A' }}</p>
                        </div>
                      </div>

                      <div class="col-12"><hr class="section-divider"></div>

                      <div class="col-12 mb-1">
                        <p class="section-label">Additional Information</p>
                      </div>
                      <div class="col-md-4">
                        <div class="mb-3">
                          <label class="form-label">Expiration Date</label>
                          <p class="text-muted" :class="getExpirationClass(data.expiration_date)">
                            {{ formatDate(data.expiration_date) || 'No expiration' }}
                          </p>
                        </div>
                      </div>
                      <div class="col-md-4">
                        <div class="mb-3">
                          <label class="form-label">Created At</label>
                          <p class="text-muted">{{ formatDate(data.created_at) }}</p>
                        </div>
                      </div>
                      <div class="col-md-4">
                        <div class="mb-3">
                          <label class="form-label">Last Updated</label>
                          <p class="text-muted">{{ formatDate(data.updated_at) }}</p>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="col-lg-4">
                    <div class="summary-panel">
                      <p class="section-label mb-3">Quick Summary</p>
                      <div class="mb-3">
                        <label class="form-label">Total Value</label>
                        <p class="text-muted">{{ calculateTotalValue() }}</p>
                      </div>
                      <div class="mb-3">
                        <label class="form-label">Profit Margin (Retail)</label>
                        <p class="summary-margin text-success">{{ calculateProfitMargin('retail') }}</p>
                      </div>
                      <div class="mb-0">
                        <label class="form-label">Profit Margin (Wholesale)</label>
                        <p class="summary-margin text-warning">{{ calculateProfitMargin('wholesale') }}</p>
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
        <TransactionLogs
          :logs="adjustmentLogs"
          title="Inventory Adjustment Logs"
          subtitle="Activity history and adjustments"
          :compact="true"
        />
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
import TransactionLogs from '@/Shared/Components/TransactionLogsCard.vue';

export default {
  emits: ['back'],
  components: {
    AdjustStockModal,
    UpdatePriceModal,
    TransactionLogs,
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
    adjustmentLogs() {
      return (this.data?.inventory_adjustments || []).map(log => ({
        id: log.id,
        created_at: log.adjustment_date,
        action: `${log.previous_quantity} → ${log.new_quantity}`,
        actioned_by: log.received_by?.fullname || 'System',
        remarks: log.reason || null,
      }));
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
.section-label {
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  color: #6b8c85;
  margin-bottom: 0;
}

.section-divider {
  border-color: #dee6e3;
  opacity: 1;
  margin: 0.5rem 0 1rem;
}

.summary-panel {
  background: #f6fbf9;
  border: 1px solid #dee6e3;
  border-radius: 8px;
  padding: 1rem 1.1rem;
}

.summary-margin {
  font-weight: 700;
  font-size: 1rem;
  margin-bottom: 0;
}
</style>
