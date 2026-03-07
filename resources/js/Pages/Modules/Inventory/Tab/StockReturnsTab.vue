<template>
  <BRow>
    <div class="col-md-12">
      <div class="library-card">
        <div class="library-card-header">
          <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
              <div class="header-icon">
                <i class="ri-arrow-go-back-line"></i>
              </div>
              <div>
                <h4 class="header-title mb-1">Stock Returns</h4>
                <p class="header-subtitle mb-0">Track purchase order stock return requests</p>
              </div>
            </div>
            <button class="create-btn" @click="openReturnStockModal">
              <i class="ri-add-line"></i>
              <span>Return Stock</span>
            </button>
          </div>
        </div>

        <div class="library-card-body">
          <div class="search-section">
            <div class="search-wrapper">
              <i class="ri-search-line search-icon"></i>
              <input
                v-model="localKeyword"
                type="text"
                class="search-input"
                placeholder="Search stock returns..."
                @input="updateKeyword(localKeyword)"
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
                    <th>Supplier</th>
                    <th>Items</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Date Created</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, index) in listStockReturns" :key="row.id">
                    <td>{{ index + 1 }}</td>
                    <td>{{ row.purchase_order?.po_number || 'N/A' }}</td>
                    <td>{{ row.purchase_order?.supplier?.name || 'N/A' }}</td>
                    <td>
                      <div class="items-list">
                        <span v-for="(item, itemIndex) in row.items || []" :key="item.id || itemIndex" class="item-chip">
                          {{ item.purchase_order_item?.product?.name || 'Item' }} ({{ item.quantity || 0 }})
                        </span>
                      </div>
                    </td>
                    <td>
                      <span class="status-badge">
                        {{ row.status?.name || 'Pending' }}
                      </span>
                    </td>
                    <td>{{ row.created_by?.fullname || 'N/A' }}</td>
                    <td>{{ formatDate(row.created_at) }}</td>
                  </tr>
                  <tr v-if="listStockReturns.length === 0">
                    <td colspan="7" class="text-center py-4">
                      <i class="ri-inbox-line text-muted" style="font-size: 2rem;"></i>
                      <p class="mt-2 mb-0">No stock returns found</p>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="pagination-section">
            <Pagination
              v-if="meta"
              :lists="listStockReturns.length"
              :links="links"
              :pagination="meta"
              @fetch="$emit('fetch', $event)"
            />
          </div>
        </div>
      </div>
    </div>
  </BRow>

  <ReturnPurchaseOrderModal
    ref="returnPurchaseOrderModal"
    :dropdowns="dropdowns"
    :purchaseOrders="returnableOrders"
    @success="handleReturnSuccess"
    @toast="$emit('toast', $event)"
  />
</template>

<script>
import Pagination from '@/Shared/Components/Pagination.vue';
import ReturnPurchaseOrderModal from '../Modal/ReturnPurchaseOrderModal.vue';

export default {
  name: 'StockReturnsTab',
  components: { Pagination, ReturnPurchaseOrderModal },
  props: {
    listStockReturns: {
      type: Array,
      default: () => [],
    },
    meta: {
      type: Object,
      default: null,
    },
    links: {
      type: Object,
      default: null,
    },
    filter: {
      type: Object,
      default: () => ({}),
    },
    dropdowns: {
      type: Object,
      default: () => ({}),
    },
  },
  emits: ['fetch', 'update-keyword', 'toast'],
  data() {
    return {
      localKeyword: this.filter.keyword || '',
      loadingOrders: false,
      returnableOrders: [],
    };
  },
  watch: {
    'filter.keyword'(newVal) {
      this.localKeyword = newVal || '';
    },
  },
  methods: {
    updateKeyword(keyword) {
      this.$emit('update-keyword', keyword);
    },
    formatDate(dateValue) {
      if (!dateValue) return 'N/A';
      const date = new Date(dateValue);
      if (Number.isNaN(date.getTime())) return 'N/A';
      return date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
      });
    },
    async fetchReturnableOrders() {
      this.loadingOrders = true;
      try {
        const response = await axios.get('/purchase-orders', {
          params: {
            count: 10,
            option: 'list',
          },
        });
        const orders = response?.data?.data || [];
        this.returnableOrders = orders.filter((order) => {
          return (order.items || []).some((item) => Number(item.received_quantity || 0) > 0);
        });
      } catch (error) {
        this.$emit('toast', error?.response?.data?.message || 'Unable to load purchase orders');
        this.returnableOrders = [];
      } finally {
        this.loadingOrders = false;
      }
    },
    async openReturnStockModal() {
      if (this.loadingOrders) return;

      await this.fetchReturnableOrders();

      if (!this.returnableOrders.length) {
        this.$emit('toast', 'No purchase orders available for stock return');
        return;
      }

      this.$refs.returnPurchaseOrderModal.show();
    },
    handleReturnSuccess() {
      this.$emit('toast', 'Purchase order return processed successfully');
      this.$emit('fetch');
      this.returnableOrders = [];
    },
  },
};
</script>

<style scoped>
.items-list {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}

.item-chip {
  display: inline-flex;
  align-items: center;
  border: 1px solid #dbe3e8;
  background: #f8fafc;
  border-radius: 999px;
  padding: 2px 8px;
  font-size: 12px;
  color: #334155;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  background: #e8f5e8;
  color: #1f7a1f;
  font-size: 12px;
  font-weight: 600;
  padding: 4px 10px;
}

.return-stock-btn {
  white-space: nowrap;
}
</style>
