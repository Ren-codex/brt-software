<template>
  <Teleport to="body">
    <div v-if="open" class="modal-overlay active" @click.self="close">
      <div class="modal-container modal-lg" @click.stop>
        <div class="modal-header">
          <div class="modal-header-icon"><i :class="icon"></i></div>
          <div>
            <p class="modal-kicker">{{ kicker }}</p>
            <h2>{{ data?.context?.label || 'Details' }}</h2>
            <p v-if="sublabel" class="modal-subtitle">{{ sublabel }}</p>
          </div>
          <button class="close-btn" @click="close"><i class="ri-close-line"></i></button>
        </div>

        <div class="modal-body p-4">
          <div v-if="loading" class="dd-state">
            <i class="ri-loader-4-line spinner"></i>
            <p>Loading details…</p>
          </div>

          <div v-else-if="error" class="dd-state dd-state-error">
            <i class="ri-error-warning-line"></i>
            <p>{{ error }}</p>
          </div>

          <template v-else>
            <!-- Record meta (single order / receipt) -->
            <div v-if="metaItems.length" class="dd-meta">
              <div v-for="m in metaItems" :key="m.label" class="dd-meta-item">
                <span class="dd-meta-label">{{ m.label }}</span>
                <span class="dd-meta-value">{{ m.value }}</span>
              </div>
            </div>

            <!-- Totals -->
            <div class="dd-totals">
              <div v-if="totals.orders !== null && totals.orders !== undefined" class="dd-total">
                <span class="dd-total-label">Orders</span>
                <strong class="dd-total-value">{{ totals.orders }}</strong>
              </div>
              <div v-if="totals.quantity !== null && totals.quantity !== undefined" class="dd-total">
                <span class="dd-total-label">Quantity</span>
                <strong class="dd-total-value">{{ formatNumber(totals.quantity) }}</strong>
              </div>
              <div class="dd-total dd-total-accent">
                <span class="dd-total-label">{{ isRecord ? 'Amount' : 'Sales' }}</span>
                <strong class="dd-total-value">{{ formatCurrency(totals.sales) }}</strong>
              </div>
            </div>

            <div v-if="!rows.length" class="dd-state">
              <i class="ri-inbox-line"></i>
              <p>Nothing to show for this selection.</p>
            </div>

            <div v-else class="dd-table">
              <table>
                <thead>
                  <tr v-if="isRecord">
                    <th>Product</th>
                    <th>Batch</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Price</th>
                    <th class="text-right">Amount</th>
                  </tr>
                  <tr v-else>
                    <th>Date</th>
                    <th>SO #</th>
                    <th>Customer</th>
                    <th>Payment</th>
                    <th v-if="showQtyColumn" class="text-right">Qty</th>
                    <th class="text-right">Amount</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, i) in rows" :key="row.id ?? i">
                    <template v-if="isRecord">
                      <td>{{ row.product_name }}</td>
                      <td class="dd-muted">{{ row.batch_code || '—' }}</td>
                      <td class="text-right">{{ formatNumber(row.quantity) }}</td>
                      <td class="text-right">{{ formatCurrency(row.price) }}</td>
                      <td class="text-right amount">{{ formatCurrency(row.amount) }}</td>
                    </template>
                    <template v-else>
                      <td>{{ formatDate(row.order_date) }}</td>
                      <td class="dd-mono">{{ row.so_number }}</td>
                      <td>{{ row.customer_name }}</td>
                      <td class="dd-muted">{{ row.payment_mode || '—' }}</td>
                      <td v-if="showQtyColumn" class="text-right">{{ formatNumber(row.quantity) }}</td>
                      <td class="text-right amount">{{ formatCurrency(row.amount) }}</td>
                    </template>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-cancel" @click="close">
            <i class="ri-close-line"></i> Close
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script>
import axios from 'axios';

const ICONS = {
  customer: 'ri-user-3-line',
  product: 'ri-box-3-line',
  sales_rep: 'ri-team-line',
  order: 'ri-file-list-3-line',
  receipt: 'ri-receipt-line',
};

const KICKERS = {
  customer: 'Customer',
  product: 'Product',
  sales_rep: 'Sales Rep',
  order: 'Sales Order',
  receipt: 'Receipt',
};

export default {
  name: 'SalesReportDrillDown',
  props: {
    // The report's current filter state, so the drill-down matches the row.
    filters: { type: Object, default: () => ({}) },
    locations: { type: Array, default: () => [] },
  },
  data() {
    return {
      open: false,
      loading: false,
      error: '',
      type: null,
      data: null,
    };
  },
  computed: {
    isRecord() {
      return this.data?.mode === 'record';
    },
    rows() {
      return this.data?.rows || [];
    },
    totals() {
      return this.data?.totals || {};
    },
    metaItems() {
      return this.data?.context?.meta || [];
    },
    showQtyColumn() {
      return this.type === 'product';
    },
    icon() {
      return ICONS[this.type] || 'ri-search-line';
    },
    kicker() {
      return KICKERS[this.type] || 'Details';
    },
    sublabel() {
      if (this.isRecord) return '';
      const { from, to, location_id: locationId } = this.filters || {};
      const location = this.locations.find((l) => String(l.value) === String(locationId));
      const range = from && to ? `${this.formatDate(from)} – ${this.formatDate(to)}` : '';
      return [range, location ? location.name : 'All Locations'].filter(Boolean).join(' · ');
    },
  },
  methods: {
    show(type, id) {
      this.type = type;
      this.data = null;
      this.error = '';
      this.open = true;
      this.loading = true;

      axios
        .get('/reports', {
          params: { ...this.filters, option: 'drilldown', type, id: id ?? null },
        })
        .then(({ data }) => { this.data = data; })
        .catch(() => { this.error = 'Could not load these details. Please try again.'; })
        .finally(() => { this.loading = false; });
    },
    close() {
      this.open = false;
    },
    formatCurrency(value) {
      return new Intl.NumberFormat('en-PH', {
        style: 'currency', currency: 'PHP', minimumFractionDigits: 2,
      }).format(Number(value) || 0);
    },
    formatNumber(value) {
      return new Intl.NumberFormat('en-PH').format(Number(value) || 0);
    },
    formatDate(value) {
      if (!value) return '—';
      const date = new Date(String(value).replace(' ', 'T'));
      if (Number.isNaN(date.getTime())) return value;
      return date.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
    },
  },
};
</script>

<style scoped>
/* Content-only styles: modal chrome lives in _library-modal.scss. */
.dd-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  padding: 2.5rem 1rem;
  color: #7f8c8d;
}

.dd-state i {
  font-size: 1.75rem;
  color: #b9c7c2;
}

.dd-state-error i {
  color: #e74c3c;
}

.spinner {
  animation: dd-spin 0.9s linear infinite;
}

@keyframes dd-spin {
  to { transform: rotate(360deg); }
}

.dd-meta {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 0.6rem;
  margin-bottom: 1rem;
}

.dd-meta-item {
  background: #f8fbfa;
  border: 1px solid #e4efeb;
  border-radius: 9px;
  padding: 0.5rem 0.7rem;
}

.dd-meta-label {
  display: block;
  font-size: 0.68rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #7f9a92;
  margin-bottom: 0.15rem;
}

.dd-meta-value {
  font-size: 0.85rem;
  font-weight: 600;
  color: #1e3530;
}

.dd-totals {
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
  margin-bottom: 1rem;
}

.dd-total {
  flex: 1;
  min-width: 120px;
  background: #f4faf8;
  border: 1px solid #d9ebe4;
  border-radius: 10px;
  padding: 0.6rem 0.8rem;
}

.dd-total-accent {
  background: linear-gradient(135deg, #eaf5f1 0%, #f6fbf9 100%);
}

.dd-total-label {
  display: block;
  font-size: 0.68rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #6b8c85;
}

.dd-total-value {
  font-size: 1.05rem;
  color: #16322e;
}

.dd-table {
  max-height: 45vh;
  overflow: auto;
  border: 1px solid #e4efeb;
  border-radius: 10px;
}

.dd-table table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.82rem;
}

.dd-table thead th {
  position: sticky;
  top: 0;
  background: #f4faf8;
  color: #4a6b63;
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 0.55rem 0.7rem;
  text-align: left;
  border-bottom: 1px solid #e4efeb;
}

.dd-table tbody td {
  padding: 0.5rem 0.7rem;
  border-bottom: 1px solid #f0f6f4;
  color: #22403a;
}

.dd-table tbody tr:last-child td {
  border-bottom: none;
}

.text-right {
  text-align: right;
}

.amount {
  font-weight: 600;
  color: #0f766e;
}

.dd-muted {
  color: #7f9a92;
  text-transform: capitalize;
}

.dd-mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 0.78rem;
}
</style>
