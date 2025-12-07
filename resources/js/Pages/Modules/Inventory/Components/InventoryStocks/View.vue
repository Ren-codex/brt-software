<template>
  <div class="container-fluid">
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

    <div class="row">
      <div class="col-lg-12">
        <div class="card">
          <div class="card-header">
            <div class="d-flex align-items-center">
              <h5 class="card-title mb-0 flex-grow-1">Inventory Stock #{{ data.id }}</h5>
              <div class="flex-shrink-0">
                <div class="d-flex gap-2">
                  <b-button
                    v-if="data.quantity > 0"
                    variant="primary"
                    @click="adjustStock"
                  >
                    Adjust Stocks
                  </b-button>
                  <b-button @click="$inertia.visit('/inventory?tab=inventoryStocks')">
                    <i class="ri-arrow-left-line align-bottom"></i>
                  </b-button>
                </div>
              </div>
            </div>
          </div>

          <div class="card-body" v-if="data">
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
                      <label class="form-label">Total Cost</label>
                      <p class="text-muted">{{ formatCurrency((data.received_item?.unit_cost || 0) * data.quantity) }}</p>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-lg-4">
                <label class="form-label mb-3">Additional Information</label>
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

    <AdjustStockModal
      :inventoryStock="data"
      @saved="$inertia.reload()"
      ref="adjustStockDialog"
    />
  </div>
</template>

<script>
import AdjustStockModal from '../../Modal/AdjustStockModal.vue';

export default {
  components: {
    AdjustStockModal,
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
