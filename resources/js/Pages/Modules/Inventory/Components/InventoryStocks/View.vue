<template>
  <div class="inventory-stock-details">
    <div class="row">
      <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
          <h4 class="mb-sm-0">Inventory Stock Details</h4>
          <div class="page-title-right">
            <ol class="breadcrumb m-0">
              <li class="breadcrumb-item">
                <router-link to="/inventory">Inventory</router-link>
              </li>
              <li class="breadcrumb-item">
                <router-link to="/inventory?tab=inventoryStocks">Inventory Stocks</router-link>
              </li>
              <li class="breadcrumb-item active">View</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

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
                    <button @click="$inertia.visit('/inventory?tab=inventoryStocks')" class="create-btn" v-b-tooltip.hover title="Back">
                      <i class="ri-arrow-left-line"></i>
                    </button>
                  </div>
                </div>
              </div>
              <div class="library-card-body">
                <div class="row">
                  <div class="col-lg-8">
                    <div class="row">
                      <div class="col-md-6">
                        <div class="mb-3">
                          <label class="form-label">Received Date</label>
                          <p class="text-muted">{{ formatDate(data.received_item?.received_stock?.received_date) }}</p>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="mb-3">
                          <label class="form-label">Batch Code</label>
                          <p class="text-muted">{{ data.received_item?.received_stock?.batch_code }}</p>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="mb-3">
                          <label class="form-label">Supplier</label>
                          <p class="text-muted">{{ data.received_item?.received_stock?.supplier?.name || 'N/A' }}</p>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="mb-3">
                          <label class="form-label">Product</label>
                          <p class="text-muted">{{ data.received_item?.product?.name || 'N/A' }}</p>
                        </div>
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
                          <p class="text-muted">{{ data.quantity }}</p>
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
                      <div>
                        <label class="form-label mb-3">Additional Information</label>
                        <div class="mb-3">
                          <label class="form-label">Expiration Date</label>
                          <p class="text-muted">{{ formatDate(data.expiration_date) }}</p>
                        </div>
                        <div class="mb-3">
                          <label class="form-label">Created At</label>
                          <p class="text-muted">{{ formatDate(data.created_at) }}</p>
                        </div>
                        <div class="mb-3">
                          <label class="form-label">Updated At</label>
                          <p class="text-muted">{{ formatDate(data.updated_at) }}</p>
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
                      <td>{{ log.received_by ? log.received_by.name : 'N/A' }}</td>
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
  components: {
    AdjustStockModal,
    UpdatePriceModal,
  },
  props: {
    inventory_stock_data: Object,
    dropdowns: Object,
  },
  data() {
    return {
    };
  },
  computed: {
    data() {
      return this.inventory_stock_data.data;
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
  }
};
</script>

<style scoped>
</style>
