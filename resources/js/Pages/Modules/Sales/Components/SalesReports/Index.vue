<template>
  <BRow>
    <div class="col-lg-12 mb-4">
        <div class="library-card">
            <div class="library-card-header">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <div class="header-icon">
                            <i class="ri-file-chart-line fs-24"></i>
                        </div>
                        <div>
                            <h4 class="header-title mb-1">Sales Report</h4>
                            <p class="header-subtitle mb-0">A comprehensive dashboard of Sales</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body m-2 p-3">
              <!-- Filter Card -->
              <div class="card filter-card">
                <div class="card-body">
                  <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                      <label class="filter-label">From</label>
                      <input v-model="form.from" type="date" class="filter-input" />
                    </div>
                    <div class="col-md-2">
                      <label class="filter-label">To</label>
                      <input v-model="form.to" type="date" class="filter-input" />
                    </div>
                    <!-- <div class="col-md-2">
                      <label class="filter-label">Day</label>
                      <input v-model="form.day" type="date" class="filter-input" />
                    </div> -->
                    <div class="col-md-2">
                      <label class="filter-label">Payment Type</label>
                      <select v-model="form.payment_mode" class="filter-select">
                        <option value="all">All</option>
                        <option value="cash">Cash Sales</option>
                        <option value="credit">Credit Sales</option>
                      </select>
                    </div>
                    <div class="col-md-2">
                      <label class="filter-label">Top Limit</label>
                      <input v-model.number="form.limit" min="1" max="50" type="number" class="filter-input" />
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                      <button :disabled="loading" class="btn-apply" @click="fetchReports">
                        <i v-if="loading" class="ri-loader-4-line ri-spin me-1"></i>
                        <span>{{ loading ? 'Loading...' : 'Apply' }}</span>
                      </button>
                      <button :disabled="loading" class="btn-reset" @click="resetFilters">
                        <i class="ri-refresh-line"></i>
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Dashboard Grid -->
              <div class="row mt-1 gap-4">
                <!-- Payment Summary Card -->
                <div class="col-lg-3 table-card">
                  <div class="table-card-inner">
                    <div class="table-header">
                      <i class="ri-money-dollar-circle-line"></i>
                      <h6>Sales Summary</h6>
                    </div>
                    <div class="summary-body">
                      <div class="summary-item">
                        <span>Cash Sales</span>
                        <strong class="text-cash">{{ formatCurrency(report?.payment_summary?.cash?.total_sales || 0) }}</strong>
                      </div>
                      <div class="summary-item">
                        <span>Credit Sales</span>
                        <strong class="text-credit">{{ formatCurrency(report?.payment_summary?.credit?.total_sales || 0) }}</strong>
                      </div>
                      <div class="summary-item">
                        <span>Other</span>
                        <strong>{{ formatCurrency(report?.payment_summary?.other?.total_sales || 0) }}</strong>
                      </div>
                      <div class="summary-divider"></div>
                      <div class="summary-item total">
                        <span>Total Sales</span>
                        <strong class="text-primary">{{ formatCurrency(report?.payment_summary?.grand_total_sales || 0) }}</strong>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Top Customers Card -->
                <div class="col-lg-5 table-card">
                  <div class="table-card-inner">
                    <div class="table-header">
                      <i class="ri-star-line"></i>
                      <h6>Top Customers</h6>
                    </div>
                    <div class="table-responsive" style="overflow: hidden;">
                      <table class="modern-table">
                        <thead>
                          <tr>
                            <th>Customer</th>
                            <th class="text-end">Orders</th>
                            <th class="text-end">Sales</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="(item, index) in report?.top_customers || []" :key="`${item.customer_id}-${item.customer_name}`" class="table-row">
                            <td>
                              <div class="customer-info">
                                <span class="rank-badge" :style="{ backgroundColor: getRankColor(index) }">{{ index + 1 }}</span>
                                {{ item.customer_name }}
                              </div>
                            </td>
                            <td class="text-end">{{ item.total_orders }}</td>
                            <td class="text-end amount">{{ formatCurrency(item.total_sales) }}</td>
                          </tr>
                          <tr v-if="!loading && !report?.top_customers?.length">
                            <td colspan="3" class="empty-state">
                              <i class="ri-group-line"></i>
                              <p>No customer data found</p>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>

                <!-- Top Products Card -->
                <div class="col-lg-4 table-card">
                  <div class="table-card-inner">
                    <div class="table-header">
                      <i class="ri-product-hunt-line"></i>
                      <h6>Top Selling Products</h6>
                    </div>
                    <div class="table-responsive">
                      <table class="modern-table">
                        <thead>
                          <tr>
                            <th>Product</th>
                            <th class="text-end">Sold</th>
                            <th class="text-end">Sales</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="(item, index) in report?.top_products || []" :key="item.product_id" class="table-row">
                            <td>
                              <div class="product-info">
                                <span class="rank-badge" :style="{ backgroundColor: getRankColor(index) }">{{ index + 1 }}</span>
                                {{ item.product_name }}
                              </div>
                            </td>
                            <td class="text-end"><span class="quantity">{{ item.total_quantity }}</span></td>
                            <td class="text-end amount">{{ formatCurrency(item.total_sales) }}</td>
                          </tr>
                          <tr v-if="!loading && !report?.top_products?.length">
                            <td colspan="3" class="empty-state">
                              <i class="ri-box-3-line"></i>
                              <p>No product data found</p>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>

                <!-- Daily Sales Card -->
                <div class="col-lg-12 table-card daily-sales mt-2">
                  <div class="table-card-inner">
                    <div class="table-header">
                      <div class="header-left">
                        <i class="ri-calendar-check-line"></i>
                        <h6>Sales for the Day</h6>
                      </div>
                      <span class="badge-day">{{ form.day || new Date().toISOString().split('T')[0] }}</span>
                    </div>
                    <div class="table-responsive">
                      <table class="modern-table">
                        <thead>
                          <tr>
                            <th>SO #</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Sold Products</th>
                            <th>Payment</th>
                            <th class="text-end">Amount</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="item in report?.daily_sales_orders || []" :key="item.id" class="table-row">
                            <td><span class="so-number">{{ item.so_number }}</span></td>
                            <td>{{ formatDate(item.order_date) }}</td>
                            <td>{{ item.customer_name }}</td>
                            <td class="sold-products">{{ item.sold_products || '-' }}</td>
                            <td>
                              <span class="payment-badge" :class="item.payment_mode?.toLowerCase() || 'cash'">
                                {{ item.payment_mode || 'Cash' }}
                              </span>
                            </td>
                            <td class="text-end amount">{{ formatCurrency(item.total_amount) }}</td>
                          </tr>
                          <tr v-if="!loading && !report?.daily_sales_orders?.length">
                            <td colspan="6" class="empty-state">
                              <i class="ri-inbox-line"></i>
                              <p>No sales orders found for selected day</p>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>
        </div>
    </div>
  </BRow>
</template>

<script>
import PageHeader from '@/Shared/Components/PageHeader.vue';

export default {
  components: { PageHeader },
  props: {
    filters: {
      type: Object,
      default: () => null,
    },
    reportData: {
      type: Object,
      default: () => null,
    },
  },
  data() {
    const today = new Date().toISOString().split('T')[0];
    const monthStart = new Date();
    monthStart.setDate(1);
    const monthStartText = monthStart.toISOString().split('T')[0];

    const defaultReport = {
      top_customers: [],
      top_products: [],
      daily_sales_orders: [],
      payment_summary: {
        cash: null,
        credit: null,
        other: null,
        grand_total_sales: 0,
        grand_total_orders: 0,
      },
    };

    const initialFilters = this.filters || {
      from: monthStartText,
      to: today,
      day: today,
      payment_mode: 'all',
      limit: 10,
    };

    return {
      loading: false,
      form: { ...initialFilters },
      report: this.reportData || defaultReport,
    };
  },
  mounted() {
    if (!this.reportData) {
      this.fetchReports();
    }
  },
  methods: {
    fetchReports() {
      this.loading = true;
      axios
        .get('/reports', {
          params: {
            option: 'summary',
            ...this.form,
          },
        })
        .then((response) => {
          this.report = response.data;
        })
        .catch((error) => {
          console.error('Failed to fetch reports', error);
        })
        .finally(() => {
          this.loading = false;
        });
    },
    resetFilters() {
      const today = new Date().toISOString().split('T')[0];
      const monthStart = new Date();
      monthStart.setDate(1);
      const monthStartText = monthStart.toISOString().split('T')[0];

      this.form = {
        from: this.filters?.from || monthStartText,
        to: this.filters?.to || today,
        day: this.filters?.day || today,
        payment_mode: 'all',
        limit: 10,
      };
      this.fetchReports();
    },
    formatCurrency(value) {
      const amount = Number(value || 0);
      return `₱${amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    },
    formatDate(date) {
      if (!date) return 'N/A';
      return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    },
    getRankColor(index) {
      const colors = ['#3d8d7a', '#5fa892', '#8fc0b0', '#b8d5cb', '#c4dad2'];
      return colors[index] || colors[colors.length - 1];
    },
  },
};
</script>

<style scoped>
/* Filter Card Styles */
.filter-card {
  background: white;
  border: none;
  border-radius: 20px;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.05);
  margin-bottom: 1.5rem;
}

.filter-card .card-body {
  padding: 1.5rem;
}

.filter-label {
  display: block;
  color: #2c3e50;
  font-size: 0.8rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  margin-bottom: 0.5rem;
}

.filter-input,
.filter-select {
  width: 100%;
  padding: 0.6rem 1rem;
  border: 2px solid #eef2f6;
  border-radius: 12px;
  font-size: 0.95rem;
  transition: all 0.2s ease;
  background: white;
}

.filter-input:focus,
.filter-select:focus {
  outline: none;
  border-color: #3d8d7a;
  box-shadow: 0 0 0 3px rgba(61, 141, 122, 0.1);
}

.btn-apply {
  flex: 1;
  background: #3d8d7a;
  color: white;
  border: none;
  border-radius: 12px;
  padding: 0.6rem 1rem;
  font-weight: 500;
  font-size: 0.95rem;
  cursor: pointer;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.btn-apply:hover:not(:disabled) {
  background: #2c6b5c;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(61, 141, 122, 0.2);
}

.btn-apply:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-reset {
  width: 42px;
  background: #f8f9fa;
  color: #6c757d;
  border: 2px solid #eef2f6;
  border-radius: 12px;
  padding: 0.6rem;
  cursor: pointer;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.btn-reset:hover:not(:disabled) {
  background: #eef2f6;
  color: #3d8d7a;
  border-color: #3d8d7a;
}

/* Dashboard Grid */
.dashboard-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1.5rem;
}

.daily-sales {
  grid-column: span 2;
}

/* Summary Card */
.summary-card {
  background: white;
  border-radius: 20px;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.summary-header {
  background: linear-gradient(135deg, #3d8d7a 0%, #2c6b5c 100%);
  padding: 1rem 1.5rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.summary-header i {
  color: white;
  font-size: 1.25rem;
  background: rgba(255, 255, 255, 0.2);
  padding: 0.5rem;
  border-radius: 10px;
}

.summary-header h6 {
  color: white;
  margin: 0;
  font-weight: 600;
  font-size: 1rem;
}

.summary-body {
  padding: 1.5rem;
}

.summary-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  color: #2c3e50;
}

.summary-item.total {
  margin-top: 1rem;
  font-size: 1.1rem;
}

.summary-divider {
  height: 2px;
  background: linear-gradient(90deg, #c4dad2, #3d8d7a, #c4dad2);
  margin: 1rem 0;
  opacity: 0.3;
}

.text-cash {
  color: #3d8d7a;
}

.text-credit {
  color: #e67e22;
}

.text-primary {
  color: #3d8d7a;
}

/* Table Cards */
.table-card {
  height: fit-content;
}

.table-card-inner {
  background: white;
  border-radius: 20px;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.table-header {
  padding: 1rem 1.5rem;
  background: #f8faf9;
  border-bottom: 2px solid #c4dad2;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.table-header i {
  color: #3d8d7a;
  font-size: 1.25rem;
  background: rgba(61, 141, 122, 0.1);
  padding: 0.5rem;
  border-radius: 10px;
}

.table-header h6 {
  color: #2c3e50;
  margin: 0;
  font-weight: 600;
  font-size: 1rem;
}

.badge-day {
  background: #c4dad2;
  color: #2c6b5c;
  padding: 0.35rem 1rem;
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: 500;
}

/* Modern Table */
.modern-table {
  width: 100%;
  border-collapse: collapse;
}

.modern-table thead th {
  background: #f8faf9;
  padding: 1rem 1.5rem;
  font-size: 0.85rem;
  font-weight: 600;
  color: #6c757d;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  border-bottom: 2px solid #c4dad2;
}

.modern-table tbody td {
  padding: 1rem 1.5rem;
  border-bottom: 1px solid #eef2f6;
  color: #2c3e50;
  font-size: 0.95rem;
}

.table-row:hover {
  background: #f8faf9;
  transition: background 0.2s ease;
}

.so-number {
  font-weight: 600;
  color: #3d8d7a;
}

.payment-badge {
  display: inline-block;
  padding: 0.25rem 1rem;
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: 500;
}

.payment-badge.cash,
.payment-badge.cash_sales {
  background: #c4dad2;
  color: #2c6b5c;
}

.payment-badge.credit,
.payment-badge.credit_sales {
  background: #fff3e0;
  color: #e67e22;
}

.amount {
  font-weight: 600;
  color: #3d8d7a;
}

.quantity {
  font-weight: 600;
  color: #e67e22;
}

.customer-info,
.product-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.sold-products {
  min-width: 260px;
  white-space: normal;
}

.rank-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  background: #3d8d7a;
  color: white;
  border-radius: 8px;
  font-size: 0.75rem;
  font-weight: 600;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 3rem !important;
  color: #adb5bd;
}

.empty-state i {
  font-size: 2.5rem;
  margin-bottom: 0.5rem;
  display: block;
}

.empty-state p {
  margin: 0;
  font-size: 0.95rem;
}

/* Responsive */
@media (max-width: 1200px) {
  .dashboard-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .daily-sales {
    grid-column: span 2;
  }
}

@media (max-width: 768px) {
  .dashboard-grid {
    grid-template-columns: 1fr;
  }
  
  .daily-sales {
    grid-column: span 1;
  }
  
  .filter-card .card-body {
    padding: 1rem;
  }
  
  .modern-table thead th,
  .modern-table tbody td {
    padding: 0.75rem 1rem;
  }
}
</style>
