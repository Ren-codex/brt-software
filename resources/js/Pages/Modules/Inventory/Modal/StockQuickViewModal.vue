<template>
  <Teleport to="body">
  <div v-if="showModal" class="modal-overlay active" @click.self="hide">
    <div class="modal-container sqv-container" @click.stop>

      <div class="modal-header">
        <div class="d-flex align-items-center gap-2">
          <div class="modal-header-icon"><i class="ri-archive-line"></i></div>
          <div>
            <h2 class="mb-0">Stock Details</h2>
            <p class="sqv-subtitle mb-0" v-if="stock">{{ stock.batch_code }}</p>
          </div>
        </div>
        <button class="close-btn" @click="hide"><i class="ri-close-line"></i></button>
      </div>

      <div class="modal-body">
        <!-- Loading -->
        <div v-if="loading" class="sqv-loading">
          <i class="ri-loader-4-line sqv-spin"></i> Loading...
        </div>

        <template v-else-if="stock">
          <!-- Product info -->
          <div class="sqv-product-banner">
            <div class="sqv-product-icon"><i class="ri-box-3-line"></i></div>
            <div>
              <p class="sqv-product-name mb-0">{{ productName }}</p>
              <span class="sqv-batch-code">{{ stock.batch_code }}</span>
            </div>
            <span class="sqv-qty-pill">{{ stock.quantity }} pcs</span>
          </div>

          <!-- Conversion origin -->
          <div class="sqv-origin-bar" v-if="stock.conversion">
            <i class="ri-recycle-line"></i>
            Converted from
            <strong>{{ stock.conversion.source_batch }}</strong>
            on {{ formatDate(stock.conversion.conversion_date) }}
            <span v-if="stock.conversion.converted_by"> · {{ stock.conversion.converted_by }}</span>
          </div>

          <!-- Key fields grid -->
          <div class="sqv-grid">
            <div class="sqv-field">
              <span class="sqv-label">Unit Cost</span>
              <span class="sqv-value">{{ formatCurrency(stock.unit_cost) }}</span>
            </div>
            <div class="sqv-field">
              <span class="sqv-label">Retail Price</span>
              <span class="sqv-value">{{ formatCurrency(stock.retail_price) }}</span>
            </div>
            <div class="sqv-field">
              <span class="sqv-label">Wholesale Price</span>
              <span class="sqv-value">{{ formatCurrency(stock.wholesale_price) }}</span>
            </div>
            <div class="sqv-field">
              <span class="sqv-label">Expiration Date</span>
              <span class="sqv-value" :class="{ 'text-danger': isExpired }">
                {{ stock.expiration_date ? formatDate(stock.expiration_date) : '—' }}
                <span v-if="isExpired" class="sqv-exp-badge">Expired</span>
              </span>
            </div>
            <div class="sqv-field" v-if="stock.notes">
              <span class="sqv-label">Notes</span>
              <span class="sqv-value">{{ stock.notes }}</span>
            </div>
            <div class="sqv-field" v-if="stock.is_archived || stock.is_expired">
              <span class="sqv-label">Status</span>
              <span class="sqv-value">
                <span v-if="stock.is_archived" class="sqv-status-badge archived">Archived</span>
                <span v-if="stock.is_expired"  class="sqv-status-badge expired">Expired</span>
              </span>
            </div>
          </div>
        </template>

        <div v-else class="sqv-loading text-muted">Stock not found.</div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-cancel" @click="hide">
          <i class="ri-close-line"></i> Close
        </button>
        <a v-if="stock" :href="`/inventory-stocks/${stock.id}`" class="btn btn-save">
          <i class="ri-external-link-line"></i> Full Details
        </a>
      </div>

    </div>
  </div>
  </Teleport>
</template>

<script>
import axios from 'axios';

export default {
  name: 'StockQuickViewModal',
  data() {
    return {
      showModal: false,
      loading:   false,
      stock:     null,
    };
  },
  computed: {
    productName() {
      return this.stock?.received_item?.product?.name
          ?? this.stock?.product?.name
          ?? '—';
    },
    isExpired() {
      if (!this.stock?.expiration_date) return false;
      return new Date(this.stock.expiration_date) < new Date();
    },
  },
  mounted() {
    document.addEventListener('keydown', this._onEscape);
  },
  beforeUnmount() {
    document.removeEventListener('keydown', this._onEscape);
  },
  methods: {
    _onEscape(e) {
      if (e.key === 'Escape' && this.showModal) this.hide();
    },
    show(stockId) {
      this.stock     = null;
      this.loading   = true;
      this.showModal = true;
      axios.get(`/inventory-stocks?option=detail&id=${stockId}`)
        .then(res => { this.stock = res.data.data; })
        .finally(() => { this.loading = false; });
    },
    hide() {
      this.showModal = false;
      this.stock     = null;
    },
    formatDate(date) {
      if (!date) return '—';
      return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: '2-digit' });
    },
    formatCurrency(val) {
      if (val === null || val === undefined) return '—';
      return '₱' + parseFloat(val).toLocaleString('en-PH', { minimumFractionDigits: 2 });
    },
  },
};
</script>

<style scoped>
.sqv-container { max-width: 480px; }
.sqv-subtitle  { font-size: 0.78rem; color: #6b8c85; font-family: 'Courier New', monospace; }

.sqv-loading {
  display: flex; align-items: center; justify-content: center;
  gap: 8px; padding: 2rem 0; color: #6b8c85; font-size: 0.9rem;
}
.sqv-spin { animation: spin 0.9s linear infinite; font-size: 1.2rem; }
@keyframes spin { to { transform: rotate(360deg); } }

.sqv-product-banner {
  display: flex; align-items: center; gap: 12px;
  background: #f0f9f6; border: 1px solid #c4d9d2;
  border-radius: 10px; padding: 0.75rem 1rem; margin-bottom: 0.75rem;
}
.sqv-product-icon {
  width: 36px; height: 36px; border-radius: 8px;
  background: rgba(61,141,122,0.12); color: #3d8d7a;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.1rem; flex-shrink: 0;
}
.sqv-product-name { font-size: 0.9rem; font-weight: 700; color: #16322e; }
.sqv-batch-code   { font-size: 0.72rem; color: #6b8c85; font-family: 'Courier New', monospace; }
.sqv-qty-pill {
  margin-left: auto; background: #3d8d7a; color: #fff;
  border-radius: 20px; padding: 0.25rem 0.75rem;
  font-size: 0.78rem; font-weight: 700; white-space: nowrap;
}

.sqv-origin-bar {
  display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
  background: #fffbeb; border: 1px solid #fde68a;
  border-radius: 8px; padding: 0.5rem 0.75rem;
  font-size: 0.78rem; color: #78350f; margin-bottom: 0.75rem;
}
.sqv-origin-bar i { color: #d97706; }

.sqv-grid {
  display: grid; grid-template-columns: 1fr 1fr;
  gap: 0.6rem 1rem;
}
.sqv-field { display: flex; flex-direction: column; gap: 2px; }
.sqv-label { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #6b8c85; }
.sqv-value { font-size: 0.85rem; font-weight: 600; color: #16322e; }

.sqv-exp-badge {
  display: inline-block; font-size: 0.65rem; font-weight: 700;
  background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5;
  border-radius: 4px; padding: 0 5px; margin-left: 4px;
}
.sqv-status-badge {
  display: inline-block; font-size: 0.68rem; font-weight: 700;
  border-radius: 20px; padding: 0.1rem 0.5rem;
}
.sqv-status-badge.archived { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
.sqv-status-badge.expired  { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
</style>
