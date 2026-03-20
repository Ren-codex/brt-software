<template>
  <BRow>
    <div class="col-lg-12">
      <div class="report-container">
        <!-- Header -->
        <div class="report-header">
          <div class="header-content">
            <i class="ri-bar-chart-2-line header-icon"></i>
            <div>
              <h2>Sales Report</h2>
              <p>Track your sales performance</p>
            </div>
          </div>
        </div>

        <!-- Filters -->
        <div class="filters-section">
          <div class="filters-grid">
            <div class="filter-item">
              <label>From</label>
              <input v-model="form.from" type="date" class="filter-input" @change="fetchReports" />
            </div>
            <div class="filter-item">
              <label>To</label>
              <input v-model="form.to" type="date" class="filter-input" @change="fetchReports" />
            </div>
            <div class="filter-item">
              <label>Location</label>
              <select v-model="form.location_id" class="filter-input" @change="fetchReports">
                <option :value="null">All Locations</option>
                <option v-for="location in locations" :key="location.value" :value="location.value">
                  {{ location.name }}
                </option>
              </select>
            </div>
            <div class="filter-item">
              <label>Payment</label>
              <select v-model="form.payment_mode" class="filter-input" @change="fetchReports">
                <option value="all">All</option>
                <option value="cash">Cash</option>
                <option value="credit">Credit</option>
              </select>
            </div>
            <div class="filter-item">
              <label>Limit</label>
              <input v-model.number="form.limit" min="1" max="50" type="number" class="filter-input" @input="debouncedFetchReports" />
            </div>
            <div class="filter-actions">
              <div class="download-dropdown" ref="downloadDropdown">
                <button :disabled="loading || downloading" class="btn-download" @click="toggleDownloadMenu">
                  <i class="ri-download-2-line"></i>
                  <span>{{ downloading ? 'Downloading...' : 'Export' }}</span>
                  <i class="ri-arrow-down-s-line"></i>
                </button>
                <div v-if="showDownloadMenu && !downloading" class="download-menu">
                  <button class="download-menu-item" @click="downloadReport('excel')">
                    <i class="ri-file-excel-2-line"></i> Excel
                  </button>
                  <button class="download-menu-item" @click="downloadReport('pdf')">
                    <i class="ri-file-pdf-2-line"></i> PDF
                  </button>
                </div>
              </div>
              <button :disabled="loading" class="btn-reset" @click="resetFilters">
                <i class="ri-refresh-line"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="loading-state">
          <div class="spinner"></div>
          <p>Loading report data...</p>
        </div>

        <!-- Report Content -->
        <div v-else class="report-content">
          <!-- Summary Cards -->
          <div class="summary-cards">
            <div class="summary-card">
              <div class="summary-icon cash">
                <i class="ri-money-dollar-circle-line"></i>
              </div>
              <div class="summary-details">
                <span class="summary-label">Cash Sales</span>
                <span class="summary-value">{{ formatCurrency(report?.payment_summary?.cash?.total_sales || 0) }}</span>
              </div>
            </div>
            <div class="summary-card">
              <div class="summary-icon credit">
                <i class="ri-bank-card-line"></i>
              </div>
              <div class="summary-details">
                <span class="summary-label">Credit Sales</span>
                <span class="summary-value">{{ formatCurrency(report?.payment_summary?.credit?.total_sales || 0) }}</span>
              </div>
            </div>
            <div class="summary-card">
              <div class="summary-icon other">
                <i class="ri-exchange-line"></i>
              </div>
              <div class="summary-details">
                <span class="summary-label">Other Sales</span>
                <span class="summary-value">{{ formatCurrency(report?.payment_summary?.other?.total_sales || 0) }}</span>
              </div>
            </div>
            <div class="summary-card total">
              <div class="summary-icon">
                <i class="ri-funds-line"></i>
              </div>
              <div class="summary-details">
                <span class="summary-label">Total Sales</span>
                <span class="summary-value">{{ formatCurrency(report?.payment_summary?.grand_total_sales || 0) }}</span>
              </div>
            </div>
          </div>

          <!-- Top Customers & Products -->
          <div class="tables-grid">
            <!-- Top Customers -->
            <div class="data-table">
              <div class="table-title">
                <i class="ri-star-line"></i>
                <h3>Top Customers</h3>
              </div>
              <table>
                <thead>
                  <tr>
                    <th>Customer</th>
                    <th class="text-right">Orders</th>
                    <th class="text-right">Sales</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in report?.top_customers || []" :key="index">
                    <td>
                      <div class="customer-cell">
                        <span class="rank">{{ index + 1 }}</span>
                        {{ item.customer_name }}
                      </div>
                    </td>
                    <td class="text-right">{{ item.total_orders }}</td>
                    <td class="text-right amount">{{ formatCurrency(item.total_sales) }}</td>
                  </tr>
                  <tr v-if="!report?.top_customers?.length">
                    <td colspan="3" class="empty-message">No customers found</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Top Products -->
            <div class="data-table">
              <div class="table-title">
                <i class="ri-product-hunt-line"></i>
                <h3>Top Products</h3>
              </div>
              <table>
                <thead>
                  <tr>
                    <th>Product</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Sales</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in report?.top_products || []" :key="index">
                    <td>
                      <div class="product-cell">
                        <span class="rank">{{ index + 1 }}</span>
                        {{ item.product_name }}
                      </div>
                    </td>
                    <td class="text-right">{{ item.total_quantity }}</td>
                    <td class="text-right amount">{{ formatCurrency(item.total_sales) }}</td>
                  </tr>
                  <tr v-if="!report?.top_products?.length">
                    <td colspan="3" class="empty-message">No products found</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="tables-grid reports-grid">
            <div class="data-table">
              <div class="table-title">
                <i class="ri-box-3-line"></i>
                <h3>Report Per Product</h3>
              </div>
              <table>
                <thead>
                  <tr>
                    <th>Product</th>
                    <th class="text-right">Orders</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Sales</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in report?.product_sales_report || []" :key="`product-report-${index}`">
                    <td>{{ item.product_name }}</td>
                    <td class="text-right">{{ item.total_orders }}</td>
                    <td class="text-right">{{ item.total_quantity }}</td>
                    <td class="text-right amount">{{ formatCurrency(item.total_sales) }}</td>
                  </tr>
                  <tr v-if="!report?.product_sales_report?.length">
                    <td colspan="4" class="empty-message">No product sales found</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="data-table">
              <div class="table-title">
                <i class="ri-user-star-line"></i>
                <h3>Sales Per Customer</h3>
              </div>
              <table>
                <thead>
                  <tr>
                    <th>Customer</th>
                    <th class="text-right">Orders</th>
                    <th class="text-right">Avg Order</th>
                    <th class="text-right">Sales</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in report?.customer_sales_report || []" :key="`customer-report-${index}`">
                    <td>{{ item.customer_name }}</td>
                    <td class="text-right">{{ item.total_orders }}</td>
                    <td class="text-right">{{ formatCurrency(item.average_order_value) }}</td>
                    <td class="text-right amount">{{ formatCurrency(item.total_sales) }}</td>
                  </tr>
                  <tr v-if="!report?.customer_sales_report?.length">
                    <td colspan="4" class="empty-message">No customer sales found</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="data-table">
            <div class="table-title">
              <i class="ri-team-line"></i>
              <h3>Sales Per Sales Rep</h3>
            </div>
            <table>
              <thead>
                <tr>
                  <th>Sales Rep</th>
                  <th class="text-right">Orders</th>
                  <th class="text-right">Avg Order</th>
                  <th class="text-right">Sales</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, index) in report?.sales_rep_report || []" :key="`rep-report-${index}`">
                  <td>{{ item.sales_rep_name }}</td>
                  <td class="text-right">{{ item.total_orders }}</td>
                  <td class="text-right">{{ formatCurrency(item.average_order_value) }}</td>
                  <td class="text-right amount">{{ formatCurrency(item.total_sales) }}</td>
                </tr>
                <tr v-if="!report?.sales_rep_report?.length">
                  <td colspan="4" class="empty-message">No sales rep data found</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Daily Sales -->
          <div class="daily-sales">
            <div class="table-title">
              <i class="ri-calendar-line"></i>
              <h3>Sales for {{ form.day || new Date().toLocaleDateString() }}</h3>
            </div>
            <table>
              <thead>
                <tr>
                  <th>SO #</th>
                  <th>Date</th>
                  <th>Customer</th>
                  <th>Products</th>
                  <th>Payment</th>
                  <th class="text-right">Amount</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in report?.daily_sales_orders || []" :key="item.id">
                  <td><span class="so-number">{{ item.so_number }}</span></td>
                  <td>{{ formatDate(item.order_date) }}</td>
                  <td>{{ item.customer_name }}</td>
                  <td>{{ item.sold_products || '-' }}</td>
                  <td>
                    <span class="payment-badge" :class="item.payment_mode?.toLowerCase()">
                      {{ item.payment_mode || 'Cash' }}
                    </span>
                  </td>
                  <td class="text-right amount">{{ formatCurrency(item.total_amount) }}</td>
                </tr>
                <tr v-if="!report?.daily_sales_orders?.length">
                  <td colspan="6" class="empty-message">No sales found for this day</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </BRow>
</template>

<script>
import _ from 'lodash';

export default {
  props: {
    locations: { type: Array, default: () => [] },
    filters: { type: Object, default: () => null },
    reportData: { type: Object, default: () => null },
  },
  data() {
    const today = new Date().toISOString().split('T')[0];
    const monthStart = new Date();
    monthStart.setDate(1);
    
    return {
      loading: false,
      downloading: false,
      showDownloadMenu: false,
      form: this.filters || {
        from: monthStart.toISOString().split('T')[0],
        to: today,
        day: today,
        location_id: null,
        payment_mode: 'all',
        limit: 10,
      },
      report: this.reportData || {
        top_customers: [],
        top_products: [],
        product_sales_report: [],
        customer_sales_report: [],
        sales_rep_report: [],
        daily_sales_orders: [],
        payment_summary: { cash: null, credit: null, other: null, grand_total_sales: 0 },
      },
    };
  },
  created() {
    this.debouncedFetchReports = _.debounce(this.fetchReports, 300);
  },
  mounted() {
    document.addEventListener('click', this.handleDocumentClick);
    if (!this.reportData) this.fetchReports();
  },
  beforeUnmount() {
    document.removeEventListener('click', this.handleDocumentClick);
  },
  methods: {
    fetchReports() {
      this.loading = true;
      axios.get('/reports', { params: { option: 'summary', ...this.normalizedForm() } })
        .then(res => this.report = res.data)
        .catch(err => console.error('Failed to fetch reports', err))
        .finally(() => this.loading = false);
    },
    resetFilters() {
      const today = new Date().toISOString().split('T')[0];
      const monthStart = new Date();
      monthStart.setDate(1);
      this.form = {
        from: monthStart.toISOString().split('T')[0],
        to: today,
        day: today,
        location_id: null,
        payment_mode: 'all',
        limit: 10,
      };
      this.fetchReports();
    },
    toggleDownloadMenu(e) {
      e.stopPropagation();
      if (!this.loading && !this.downloading) this.showDownloadMenu = !this.showDownloadMenu;
    },
    handleDocumentClick(e) {
      if (this.showDownloadMenu && !this.$refs.downloadDropdown?.contains(e.target)) {
        this.showDownloadMenu = false;
      }
    },
    downloadReport(format) {
      this.showDownloadMenu = false;
      this.downloading = true;
      axios.get('/reports', { 
        params: { option: format === 'pdf' ? 'pdf' : 'excel', ...this.normalizedForm() },
        responseType: 'blob' 
      })
      .then(response => {
        const blob = new Blob([response.data], { type: response.headers['content-type'] });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `sales-report-${new Date().toISOString().slice(0, 10)}.${format === 'pdf' ? 'pdf' : 'xlsx'}`;
        link.click();
        window.URL.revokeObjectURL(url);
      })
      .catch(err => console.error('Download failed', err))
      .finally(() => this.downloading = false);
    },
    formatCurrency(val) {
      return `₱${Number(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    },
    normalizedForm() {
      return { ...this.form, location_id: this.form.location_id || null };
    },
    formatDate(date) {
      return date ? new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A';
    },
  },
};
</script>

<style scoped>
.report-container {
  background: #f8fafc;
  min-height: 100vh;
  padding: 1.5rem;
}

/* Header */
.report-header {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.header-content {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.header-icon {
  font-size: 2rem;
  color: #059669;
  background: #ecfdf5;
  padding: 0.75rem;
  border-radius: 12px;
}

.header-content h2 {
  font-size: 1.5rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.header-content p {
  color: #64748b;
  margin: 0.25rem 0 0 0;
  font-size: 0.875rem;
}

/* Filters */
.filters-section {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.filters-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 1rem;
  align-items: end;
}

.filter-item {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.filter-item label {
  font-size: 0.75rem;
  font-weight: 500;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.filter-input {
  width: 100%;
  padding: 0.5rem 0.75rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.875rem;
  background: white;
  transition: all 0.15s ease;
}

.filter-input:focus {
  outline: none;
  border-color: #059669;
  box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
}

.filter-actions {
  display: flex;
  gap: 0.5rem;
}

/* Buttons */
.btn-download {
  background: #059669;
  color: white;
  border: none;
  border-radius: 8px;
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 0.375rem;
  cursor: pointer;
  transition: background 0.15s;
}

.btn-download:hover:not(:disabled) {
  background: #047857;
}

.btn-download:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-reset {
  width: 36px;
  height: 36px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: white;
  color: #64748b;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s;
}

.btn-reset:hover:not(:disabled) {
  background: #f8fafc;
  color: #059669;
  border-color: #059669;
}

/* Download Dropdown */
.download-dropdown {
  position: relative;
}

.download-menu {
  position: absolute;
  top: calc(100% + 0.25rem);
  left: 0;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
  min-width: 120px;
  z-index: 10;
}

.download-menu-item {
  width: 100%;
  padding: 0.5rem 1rem;
  text-align: left;
  background: none;
  border: none;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: #1e293b;
  cursor: pointer;
}

.download-menu-item:hover {
  background: #f8fafc;
}

.download-menu-item:first-child {
  border-radius: 8px 8px 0 0;
}

.download-menu-item:last-child {
  border-radius: 0 0 8px 8px;
}

/* Loading State */
.loading-state {
  background: white;
  border-radius: 12px;
  padding: 3rem;
  text-align: center;
  color: #64748b;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #e2e8f0;
  border-top-color: #059669;
  border-radius: 50%;
  margin: 0 auto 1rem;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Summary Cards */
.summary-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.summary-card {
  background: white;
  border-radius: 12px;
  padding: 1.25rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.summary-card.total {
  background: #059669;
}

.summary-card.total .summary-label,
.summary-card.total .summary-value {
  color: white;
}

.summary-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
}

.summary-icon.cash {
  background: #ecfdf5;
  color: #059669;
}

.summary-icon.credit {
  background: #fffbeb;
  color: #d97706;
}

.summary-icon.other {
  background: #eff6ff;
  color: #2563eb;
}

.summary-details {
  flex: 1;
}

.summary-label {
  display: block;
  font-size: 0.75rem;
  color: #64748b;
  margin-bottom: 0.25rem;
}

.summary-value {
  display: block;
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
}

/* Tables */
.tables-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
  margin-bottom: 1.5rem;
}

.reports-grid {
  margin-top: 0;
}

.data-table,
.daily-sales {
  background: white;
  border-radius: 12px;
  padding: 1.25rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.table-title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.table-title i {
  color: #059669;
  font-size: 1.25rem;
}

.table-title h3 {
  font-size: 1rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th {
  text-align: left;
  padding: 0.75rem 0.5rem 0.5rem 0.5rem;
  font-size: 0.75rem;
  font-weight: 500;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  border-bottom: 1px solid #e2e8f0;
}

td {
  padding: 0.75rem 0.5rem;
  font-size: 0.875rem;
  color: #1e293b;
  border-bottom: 1px solid #f1f5f9;
}

tr:last-child td {
  border-bottom: none;
}

.text-right {
  text-align: right;
}

.customer-cell,
.product-cell {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.rank {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  background: #f1f5f9;
  color: #475569;
  border-radius: 6px;
  font-size: 0.7rem;
  font-weight: 600;
}

.amount {
  font-weight: 500;
  color: #059669;
}

.so-number {
  font-weight: 500;
  color: #059669;
}

.payment-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 500;
}

.payment-badge.cash {
  background: #ecfdf5;
  color: #059669;
}

.payment-badge.credit {
  background: #fffbeb;
  color: #d97706;
}

.empty-message {
  text-align: center;
  color: #94a3b8;
  padding: 2rem !important;
}

/* Responsive */
@media (max-width: 768px) {
  .report-container {
    padding: 1rem;
  }
  
  .tables-grid {
    grid-template-columns: 1fr;
    gap: 1rem;
  }
  
  .filters-grid {
    grid-template-columns: 1fr;
  }
  
  .filter-actions {
    grid-column: span 1;
  }
}
</style>
