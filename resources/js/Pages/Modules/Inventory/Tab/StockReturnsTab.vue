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
          
          <div class="tabs-section">
            <div class="tabs-wrapper">
              <button
                :class="['tab-btn', { active: activeTab === 'all' }]"
                @click="setActiveTab('all')"
              >
                All
              </button>
              <button
                :class="['tab-btn', { active: activeTab === 'pending' }]"
                @click="setActiveTab('pending')"
              >
                Pending
              </button>
              <button
                :class="['tab-btn', { active: activeTab === 'approved' }]"
                @click="setActiveTab('approved')"
              >
                Approved
              </button>
              <button
                :class="['tab-btn', { active: activeTab === 'disapproved' }]"
                @click="setActiveTab('disapproved')"
              >
                Disapproved
              </button>
              <button
                :class="['tab-btn', { active: activeTab === 'completed' }]"
                @click="setActiveTab('completed')"
              >
                Completed
              </button>
            </div>
          </div>

          <div class="table-section">
            <div class="table-responsive" style="overflow: visible; max-height: none;">
              <table class="table align-middle table-centered mb-0">
                <thead>
                  <tr>
                    <th>Stock Return No</th>
                    <th>PO Number</th>
                    <th>Supplier</th>
                    <th>Items</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Date Created</th>
                    <th>Received Items</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="row in filteredStockReturns"
                    :key="row.id"
                    @click="openView(row)"
                    :style="[{ cursor: 'pointer' }]"
                  >
                    <td>{{ row.stock_return_no || `SR-${row.id}` }}</td>
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
                      <span :style="getStatusStyle(row.status)" class="status-badge">
                        {{ row.status?.name || 'Pending' }}
                      </span>
                    </td>
                    <td>{{ row.created_by?.fullname || 'N/A' }}</td>
                    <td>{{ formatDate(row.created_at) }}</td>
                    <td>
                      <div class="return-progress-wrap">
                        <div class="return-progress-bar">
                          <div
                            class="return-progress-fill"
                            :style="{ width: `${calculateReturnProgress(row).percent}%` }"
                          ></div>
                        </div>
                        <small class="return-progress-text">
                          {{ calculateReturnProgress(row).returned }} / {{ calculateReturnProgress(row).total }}
                          ({{ calculateReturnProgress(row).percent }}%)
                        </small>
                      </div>
                    </td>
                    <td>
                      <button class="btn btn-sm btn-outline-primary" @click.stop="openView(row)">
                        <i class="ri-eye-line"></i>
                      </button>
                    </td>
                  </tr>
                  <tr v-if="filteredStockReturns.length === 0">
                    <td colspan="9" class="text-center py-4">
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
  emits: ['fetch', 'update-keyword', 'toast', 'view-details'],
  data() {
    return {
      localKeyword: this.filter.keyword || '',
      activeTab: 'all',
      loadingOrders: false,
      returnableOrders: [],
    };
  },
  computed: {
    filteredStockReturns() {
      const targetStatus = this.activeTab;
      if (!targetStatus || targetStatus === 'all') return this.listStockReturns;

      return this.listStockReturns.filter((row) => {
        const statusName = String(row?.status?.name || '').trim().toLowerCase();
        return statusName === targetStatus;
      });
    },
  },
  watch: {
    'filter.keyword'(newVal) {
      this.localKeyword = newVal || '';
    },
  },
  methods: {
    setActiveTab(tab) {
      this.activeTab = tab;
    },
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
    calculateReturnProgress(stockReturn) {
      const items = stockReturn?.items || [];
      const total = items.reduce((sum, item) => sum + Number(item.quantity || 0), 0);
      const returned = items.reduce((sum, item) => sum + Number(item.returned_quantity || 0), 0);

      if (total <= 0) {
        return { returned: 0, total: 0, percent: 0 };
      }

      const percent = Math.min(100, Math.round((returned / total) * 100));
      return { returned, total, percent };
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
    openView(stockReturn) {
      this.$emit('view-details', stockReturn);
    },
    getStatusStyle(status) {
      if (!status) return {};
      
      return {
        color: status.text_color || '#000000',
        backgroundColor: status.bg_color || '#ffffff',
        border: `1px solid ${status.bg_color ? status.bg_color + '40' : '#cccccc'}`,
        boxShadow: `0 2px 4px ${status.bg_color ? status.bg_color + '20' : 'rgba(0,0,0,0.1)'}`
      };
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

.return-progress-wrap {
  min-width: 140px;
}

.return-progress-bar {
  width: 100%;
  height: 8px;
  border-radius: 999px;
  background: #e5e7eb;
  overflow: hidden;
}

.return-progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #2e8b57 0%, #3dbb77 100%);
  transition: width 0.25s ease;
}

.return-progress-text {
  display: inline-block;
  margin-top: 4px;
  color: #475569;
  font-size: 11px;
  font-weight: 500;
}

/* Tabs Section */
.tabs-section {
  margin-bottom: 1rem;
}

.tabs-wrapper {
  display: flex;
  gap: 8px;
  border-bottom: 1px solid #e9ecef;
}

.tab-btn {
  padding: 8px 16px;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  color: #6c757d;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
}

.tab-btn:hover {
  color: #2e8b57;
}

.tab-btn.active {
  color: #2e8b57;
  border-bottom-color: #2e8b57;
}

tbody tr {
  transition: background-color 0.3s ease;
}

tbody tr:hover {
  background-color: var(--hover-color, rgba(46, 139, 87, 0.05)) !important;
}
</style>
