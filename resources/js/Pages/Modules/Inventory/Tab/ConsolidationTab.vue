<template>
  <div>
    <div class="library-card">
      <div class="library-card-header">
        <div class="d-flex align-items-center gap-3">
          <div class="header-icon">
            <i class="ri-stack-line"></i>
          </div>
          <div>
            <h4 class="header-title mb-0">Inventory Consolidation</h4>
            <p class="header-subtitle mb-0">Every batch of a product added together into one line</p>
          </div>
        </div>
        <button class="acct-btn-secondary" :disabled="loading" @click="fetch">
          <i class="ri-refresh-line me-1"></i>{{ loading ? 'Loading...' : 'Refresh' }}
        </button>
      </div>

      <div class="library-card-body">
        <!-- Cost and retail are kept apart: one is what the stock cost, the
             other what it would fetch if all of it sold. -->
        <div class="consol-stats">
          <div class="consol-stat">
            <span class="consol-stat-label">Products in stock</span>
            <strong class="consol-stat-value">{{ number(totals.products) }}</strong>
            <small>{{ number(totals.batches) }} batches</small>
          </div>
          <div class="consol-stat">
            <span class="consol-stat-label">Total on hand</span>
            <strong class="consol-stat-value">{{ number(totals.quantity) }}</strong>
            <small>{{ number(totals.weight) }} kg</small>
          </div>
          <div class="consol-stat">
            <span class="consol-stat-label">Stock value (at cost)</span>
            <strong class="consol-stat-value">{{ peso(totals.stock_value) }}</strong>
            <small>unit cost × quantity</small>
          </div>
          <div class="consol-stat">
            <span class="consol-stat-label">Retail value</span>
            <strong class="consol-stat-value">{{ peso(totals.retail_value) }}</strong>
            <small>retail price × quantity</small>
          </div>
        </div>

        <div class="search-section">
          <div class="search-wrapper">
            <i class="ri-search-line search-icon"></i>
            <input
              v-model.trim="keyword"
              type="text"
              class="search-input"
              placeholder="Search products..."
              title="Search by product or batch code"
            >
          </div>
          <select v-model="stockFilter" class="consol-filter" aria-label="Filter by stock level">
            <option value="">All stock levels</option>
            <option value="low">Low stock only</option>
            <option value="multi">More than one batch</option>
          </select>
          <SortSelect v-model="sortOption" :added="false" :fields="[
            { field: 'product_name', label: 'Product' },
            { field: 'quantity', label: 'On hand', type: 'number' },
            { field: 'batch_count', label: 'Batches', type: 'number' },
            { field: 'stock_value', label: 'Stock value', type: 'number' },
          ]" />
        </div>

        <div class="table-responsive">
          <table class="table align-middle mb-0 consol-table">
            <thead>
              <tr>
                <th style="width: 34px"></th>
                <th>Product</th>
                <th class="text-end">Batches</th>
                <th class="text-end">On hand</th>
                <th class="text-end">Weight (kg)</th>
                <th class="text-end">Avg. unit cost</th>
                <th class="text-end">Stock value</th>
                <th class="text-end">Retail value</th>
                <th>Oldest batch</th>
              </tr>
            </thead>
            <tbody>
              <TableLoadingRow v-if="loading" :colspan="9" message="Adding up the stock..." />
              <template v-else>
                <template v-for="row in pagedRows" :key="row.product_id">
                  <tr class="consol-row" @click="toggle(row.product_id)">
                    <td>
                      <i :class="expanded[row.product_id] ? 'ri-arrow-down-s-line' : 'ri-arrow-right-s-line'"></i>
                    </td>
                    <td class="fw-semibold">
                      {{ row.product_name }}
                      <span v-if="row.is_low" class="consol-low">Low stock</span>
                    </td>
                    <td class="text-end">{{ number(row.batch_count) }}</td>
                    <td class="text-end fw-semibold">{{ number(row.quantity) }}</td>
                    <td class="text-end">{{ number(row.weight) }}</td>
                    <td class="text-end">{{ peso(row.average_cost) }}</td>
                    <td class="text-end fw-semibold">{{ peso(row.stock_value) }}</td>
                    <td class="text-end">{{ peso(row.retail_value) }}</td>
                    <td>{{ row.oldest_received || '—' }}</td>
                  </tr>
                  <tr v-if="expanded[row.product_id]" class="consol-batches">
                    <td></td>
                    <td colspan="8">
                      <table class="table table-sm mb-0 consol-batch-table">
                        <thead>
                          <tr>
                            <th>Batch code</th>
                            <th>Received</th>
                            <th>Expires</th>
                            <th class="text-end">Quantity</th>
                            <th class="text-end">Unit cost</th>
                            <th class="text-end">Retail</th>
                            <th class="text-end">Wholesale</th>
                            <th class="text-end">Value</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="batch in row.batches" :key="batch.id">
                            <td>{{ batch.batch_code || '—' }}</td>
                            <td>{{ batch.received_date || '—' }}</td>
                            <td>{{ batch.expiration_date || '—' }}</td>
                            <td class="text-end">{{ number(batch.quantity) }}</td>
                            <td class="text-end">{{ peso(batch.unit_cost) }}</td>
                            <td class="text-end">{{ peso(batch.retail_price) }}</td>
                            <td class="text-end">{{ peso(batch.wholesale_price) }}</td>
                            <td class="text-end">{{ peso(batch.stock_value) }}</td>
                          </tr>
                        </tbody>
                      </table>
                    </td>
                  </tr>
                </template>
                <tr v-if="!filteredRows.length">
                  <td colspan="9" class="consol-empty">
                    {{ rows.length ? 'No products match this search.' : 'There is no stock on hand yet.' }}
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <div v-if="filteredRows.length > pageSize" class="consol-pager">
          <span>Showing {{ (currentPage - 1) * pageSize + 1 }}–{{ Math.min(currentPage * pageSize, filteredRows.length) }} of {{ filteredRows.length }}</span>
          <div class="d-flex gap-2">
            <button class="acct-btn-secondary" :disabled="currentPage <= 1" @click="currentPage--">Previous</button>
            <button class="acct-btn-secondary" :disabled="currentPage >= totalPages" @click="currentPage++">Next</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'ConsolidationTab',
  emits: ['toast'],
  data() {
    return {
      loading: false,
      rows: [],
      totals: { products: 0, batches: 0, quantity: 0, weight: 0, stock_value: 0, retail_value: 0 },
      keyword: '',
      stockFilter: '',
      // "field:direction"; empty is the default, A to Z.
      sortOption: '',
      expanded: {},
      currentPage: 1,
      pageSize: 15,
    };
  },
  computed: {
    filteredRows() {
      const keyword = this.keyword.toLowerCase();
      const [field, direction] = (this.sortOption || 'product_name:asc').split(':');
      const sign = direction === 'desc' ? -1 : 1;
      const value = (row) => (field === 'product_name' ? String(row.product_name || '').toLowerCase() : Number(row[field]) || 0);

      return this.rows
        .filter((row) => {
          if (this.stockFilter === 'low' && !row.is_low) return false;
          if (this.stockFilter === 'multi' && row.batch_count < 2) return false;
          if (!keyword) return true;
          // A batch code finds the product it belongs to.
          return String(row.product_name || '').toLowerCase().includes(keyword)
            || row.batches.some((batch) => String(batch.batch_code || '').toLowerCase().includes(keyword));
        })
        .sort((a, b) => {
          const av = value(a);
          const bv = value(b);
          if (av < bv) return -1 * sign;
          if (av > bv) return 1 * sign;
          return String(a.product_name).localeCompare(String(b.product_name));
        });
    },
    totalPages() {
      return Math.max(1, Math.ceil(this.filteredRows.length / this.pageSize));
    },
    pagedRows() {
      const start = (this.currentPage - 1) * this.pageSize;
      return this.filteredRows.slice(start, start + this.pageSize);
    },
  },
  watch: {
    keyword() { this.currentPage = 1; },
    stockFilter() { this.currentPage = 1; },
    sortOption() { this.currentPage = 1; },
  },
  mounted() {
    this.fetch();
  },
  methods: {
    fetch() {
      this.loading = true;
      axios.get('/inventory-stocks', { params: { option: 'consolidation' } })
        .then((res) => {
          this.rows = Array.isArray(res.data?.data) ? res.data.data : [];
          this.totals = { ...this.totals, ...(res.data?.totals || {}) };
        })
        .catch(() => {
          this.rows = [];
          this.$emit('toast', { type: 'error', message: 'Could not load the consolidated stock.' });
        })
        .finally(() => { this.loading = false; });
    },
    toggle(productId) {
      this.expanded = { ...this.expanded, [productId]: !this.expanded[productId] };
    },
    number(value) {
      return Number(value || 0).toLocaleString('en-US', { maximumFractionDigits: 2 });
    },
    peso(value) {
      return `₱${Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    },
  },
};
</script>

<style scoped>
.consol-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: 0.75rem;
  margin-bottom: 1.1rem;
}

.consol-stat {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 0.8rem 1rem;
  border: 1px solid var(--brt-border);
  border-radius: 10px;
  background: var(--brt-tint-2);
}

.consol-stat-label {
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: var(--brt-muted);
}

.consol-stat-value {
  font-size: 1.15rem;
  color: var(--brt-ink);
}

.consol-stat small {
  font-size: 0.72rem;
  color: var(--brt-muted);
}

.consol-filter {
  padding: 0.55rem 2rem 0.55rem 0.75rem;
  border: 2px solid #e5e7eb;
  border-radius: 8px;
  background-color: #f9fafb;
  font-size: 0.8125rem;
  color: #374151;
  cursor: pointer;
}

.consol-table thead th {
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: var(--brt-muted);
  white-space: nowrap;
}

.consol-row {
  cursor: pointer;
}

.consol-row:hover {
  background: var(--brt-tint-2);
}

.consol-low {
  margin-left: 0.4rem;
  padding: 1px 8px;
  border-radius: 999px;
  background: #fdeaea;
  font-size: 0.68rem;
  font-weight: 600;
  color: #96231f;
}

.consol-batches > td {
  background: #fafbfb;
  padding-top: 0.4rem;
  padding-bottom: 0.7rem;
}

.consol-batch-table th {
  font-size: 0.68rem;
  text-transform: uppercase;
  color: var(--brt-muted);
}

.consol-batch-table td {
  font-size: 0.78rem;
}

.consol-empty {
  padding: 2rem 0;
  text-align: center;
  color: var(--brt-muted);
}

.consol-pager {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 0.9rem;
  font-size: 0.8rem;
  color: var(--brt-muted);
}
</style>
