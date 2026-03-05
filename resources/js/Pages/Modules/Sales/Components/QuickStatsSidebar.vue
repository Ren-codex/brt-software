<template>
  <div class="quick-stats-sidebar">
    <!-- Card Header -->
    <div class="card-header-modern">
      <h4 class="mb-0">
        <i class="ri-dashboard-line me-2"></i> Quick Stats
      </h4>
    </div>

    <div class="card-body-custom">
      <!-- Sales Orders Tab Stats -->
      <template v-if="activeTab === 'sales_orders'">
        <div class="stat-card mb-3">
          <div class="stat-icon stat-icon-primary">
            <i class="ri-shopping-cart-line"></i>
          </div>
          <div class="stat-info">
            <span class="stat-label">Total Sales Orders</span>
            <div class="stat-value-wrapper">
              <span class="stat-value">{{ metrics.total_sales_orders }}</span>
              <span class="stat-trend trend-up">
                <i class="ri-arrow-up-line"></i> Total
              </span>
            </div>
          </div>
        </div>

        <div class="stat-card mb-3">
          <div class="stat-icon stat-icon-info">
            <i class="ri-calendar-line"></i>
          </div>
          <div class="stat-info">
            <span class="stat-label">Today's Orders</span>
            <div class="stat-value-wrapper">
              <span class="stat-value">{{ metrics.today_orders }}</span>
              <span class="stat-trend trend-up">
                <i class="ri-arrow-up-line"></i> Today
              </span>
            </div>
          </div>
        </div>

        <div class="stat-card mb-3">
          <div class="stat-icon stat-icon-warning">
            <i class="ri-time-line"></i>
          </div>
          <div class="stat-info">
            <span class="stat-label">Pending Orders</span>
            <div class="stat-value-wrapper">
              <span class="stat-value">{{ metrics.pending_orders }}</span>
              <span class="stat-trend trend-neutral">
                <i class="ri-more-line"></i> Waiting
              </span>
            </div>
          </div>
        </div>

        <div class="stat-card mb-3">
          <div class="stat-icon stat-icon-danger">
            <i class="ri-close-line"></i>
          </div>
          <div class="stat-info">
            <span class="stat-label">Cancelled Orders</span>
            <div class="stat-value-wrapper">
              <span class="stat-value">{{ metrics.cancelled_orders }}</span>
              <span class="stat-trend trend-down">
                <i class="ri-arrow-down-line"></i> Cancel
              </span>
            </div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon stat-icon-success">
            <i class="ri-money-dollar-circle-line"></i>
          </div>
          <div class="stat-info">
            <span class="stat-label">Total Revenue</span>
            <div class="stat-value-wrapper">
              <span class="stat-value">{{ formatCurrency(metrics.total_revenue) }}</span>
              <span class="stat-trend trend-up">
                <i class="ri-arrow-up-line"></i> Total
              </span>
            </div>
          </div>
        </div>
      </template>

      <!-- Sales Returns Tab Stats -->
      <template v-else-if="activeTab === 'sales_returns'">
        <div class="stat-card mb-3">
          <div class="stat-icon stat-icon-primary">
            <i class="ri-shopping-cart-line"></i>
          </div>
          <div class="stat-info">
            <span class="stat-label">Total Returns</span>
            <div class="stat-value-wrapper">
              <span class="stat-value">{{ metrics.total_sales_orders }}</span>
              <span class="stat-trend trend-up">
                <i class="ri-arrow-up-line"></i> Total
              </span>
            </div>
          </div>
        </div>

        <div class="stat-card mb-3">
          <div class="stat-icon stat-icon-info">
            <i class="ri-calendar-line"></i>
          </div>
          <div class="stat-info">
            <span class="stat-label">Today's Returns</span>
            <div class="stat-value-wrapper">
              <span class="stat-value">{{ metrics.today_orders }}</span>
              <span class="stat-trend trend-up">
                <i class="ri-arrow-up-line"></i> Today
              </span>
            </div>
          </div>
        </div>

        <div class="stat-card mb-3">
          <div class="stat-icon stat-icon-warning">
            <i class="ri-time-line"></i>
          </div>
          <div class="stat-info">
            <span class="stat-label">Pending Returns</span>
            <div class="stat-value-wrapper">
              <span class="stat-value">{{ metrics.pending_orders }}</span>
              <span class="stat-trend trend-neutral">
                <i class="ri-more-line"></i> Waiting
              </span>
            </div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon stat-icon-success">
            <i class="ri-money-dollar-circle-line"></i>
          </div>
          <div class="stat-info">
            <span class="stat-label">Total Refunded</span>
            <div class="stat-value-wrapper">
              <span class="stat-value">{{ formatCurrency(metrics.total_revenue) }}</span>
              <span class="stat-trend trend-up">
                <i class="ri-arrow-up-line"></i> Total
              </span>
            </div>
          </div>
        </div>
      </template>

      <!-- AR Invoices Tab Stats -->
      <template v-else-if="activeTab === 'ar_invoices'">
        <div class="stat-card mb-3">
          <div class="stat-icon stat-icon-primary">
            <i class="ri-file-list-line"></i>
          </div>
          <div class="stat-info">
            <span class="stat-label">Total Invoices</span>
            <div class="stat-value-wrapper">
              <span class="stat-value">{{ arMetrics.total_invoices }}</span>
              <span class="stat-trend trend-up">
                <i class="ri-arrow-up-line"></i> Total
              </span>
            </div>
          </div>
          </div>

          <div class="stat-card mb-3">
            <div class="stat-icon stat-icon-info">
              <i class="ri-calendar-line"></i>
            </div>
            <div class="stat-info">
              <span class="stat-label">Today's Invoices</span>
              <div class="stat-value-wrapper">
                <span class="stat-value">{{ arMetrics.today_invoices }}</span>
                <span class="stat-trend trend-up">
                  <i class="ri-arrow-up-line"></i> Today
                </span>
              </div>
            </div>
            </div>

            <div class="stat-card mb-3">
              <div class="stat-icon stat-icon-success">
                <i class="ri-money-dollar-circle-line"></i>
              </div>
              <div class="stat-info">
                <span class="stat-label">Outstanding Balance</span>
                <div class="stat-value-wrapper">
                  <span class="stat-value">{{ formatCurrency(arMetrics.outstanding_balance) }}</span>
                  <span class="stat-trend trend-up">
                    <i class="ri-arrow-up-line"></i> Total
                  </span>
                </div>
              </div>
              </div>

              <div class="stat-card mb-3">
                <div class="stat-icon stat-icon-warning">
                  <i class="ri-time-line"></i>
                </div>
                <div class="stat-info">
                  <span class="stat-label">Pending Invoices</span>
                  <div class="stat-value-wrapper">
                    <span class="stat-value">{{ arMetrics.pending_invoices }}</span>
                    <span class="stat-trend trend-neutral">
                      <i class="ri-more-line"></i> Waiting
                    </span>
                  </div>
                </div>
                </div>

                <div class="stat-card">
                  <div class="stat-icon stat-icon-success">
                    <i class="ri-check-circle-line"></i>
                  </div>
                  <div class="stat-info">
                    <span class="stat-label">Paid Invoices</span>
                    <div class="stat-value-wrapper">
                      <span class="stat-value">{{ arMetrics.paid_invoices }}</span>
                      <span class="stat-trend trend-up">
                        <i class="ri-arrow-up-line"></i> Complete
                      </span>
                    </div>
                  </div>
                  </div>
      </template>

      <!-- Receipts Tab Stats -->
      <template v-else-if="activeTab === 'receipts'">
        <div class="stat-card mb-3">
          <div class="stat-icon stat-icon-primary">
            <i class="ri-receipt-line"></i>
          </div>
          <div class="stat-info">
            <span class="stat-label">Total Receipts</span>
            <div class="stat-value-wrapper">
              <span class="stat-value">{{ listReceipts.length }}</span>
              <span class="stat-trend trend-up">
                <i class="ri-arrow-up-line"></i> Total
              </span>
            </div>
          </div>
          </div>

          <div class="stat-card mb-3">
            <div class="stat-icon stat-icon-success">
              <i class="ri-money-dollar-circle-line"></i>
            </div>
            <div class="stat-info">
              <span class="stat-label">Total Collected</span>
              <div class="stat-value-wrapper">
                <span class="stat-value">{{ formatCurrency(totalCollected) }}</span>
                <span class="stat-trend trend-up">
                  <i class="ri-arrow-up-line"></i> Total
                </span>
              </div>
            </div>
            </div>
      </template>

      <!-- Remittance Tab Stats -->
      <template v-else-if="activeTab === 'remittance'">
        <div class="stat-card mb-3">
          <div class="stat-icon stat-icon-primary">
            <i class="ri-file-list-line"></i>
          </div>
          <div class="stat-info">
            <span class="stat-label">Total Remittances</span>
            <div class="stat-value-wrapper">
              <span class="stat-value">{{ remittanceMetrics.total_remittances }}</span>
              <span class="stat-trend trend-up">
                <i class="ri-arrow-up-line"></i> Total
              </span>
            </div>
          </div>
        </div>

        <div class="stat-card mb-3">
          <div class="stat-icon stat-icon-info">
            <i class="ri-calendar-line"></i>
          </div>
          <div class="stat-info">
            <span class="stat-label">Today's Remittances</span>
            <div class="stat-value-wrapper">
              <span class="stat-value">{{ remittanceMetrics.today_remittances }}</span>
              <span class="stat-trend trend-up">
                <i class="ri-arrow-up-line"></i> Today
              </span>
            </div>
          </div>
        </div>

        <div class="stat-card mb-3">
          <div class="stat-icon stat-icon-warning">
            <i class="ri-time-line"></i>
          </div>
          <div class="stat-info">
            <span class="stat-label">Open Remittances</span>
            <div class="stat-value-wrapper">
              <span class="stat-value">{{ remittanceMetrics.open_remittances }}</span>
              <span class="stat-trend trend-neutral">
                <i class="ri-more-line"></i> Waiting
              </span>
            </div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon stat-icon-success">
            <i class="ri-money-dollar-circle-line"></i>
          </div>
          <div class="stat-info">
            <span class="stat-label">Total Amount Remitted</span>
            <div class="stat-value-wrapper">
              <span class="stat-value">{{ formatCurrency(remittanceMetrics.total_amount_remitted) }}</span>
              <span class="stat-trend trend-up">
                <i class="ri-arrow-up-line"></i> Total
              </span>
            </div>
          </div>
        </div>
      </template>

      <!-- Default Stats (when no specific tab matches) -->
      <template v-else>
        <div class="stat-card mb-3">
          <div class="stat-icon stat-icon-primary">
            <i class="ri-shopping-cart-line"></i>
          </div>
          <div class="stat-info">
            <span class="stat-label">Total Sales Orders</span>
            <div class="stat-value-wrapper">
              <span class="stat-value">{{ metrics.total_sales_orders }}</span>
              <span class="stat-trend trend-up">
                <i class="ri-arrow-up-line"></i> Total
              </span>
            </div>
          </div>
          </div>

          <div class="stat-card mb-3">
            <div class="stat-icon stat-icon-info">
              <i class="ri-calendar-line"></i>
            </div>
            <div class="stat-info">
              <span class="stat-label">Today's Orders</span>
              <div class="stat-value-wrapper">
                <span class="stat-value">{{ metrics.today_orders }}</span>
                <span class="stat-trend trend-up">
                  <i class="ri-arrow-up-line"></i> Today
                </span>
              </div>
            </div>
            </div>

            <div class="stat-card mb-3">
              <div class="stat-icon stat-icon-warning">
                <i class="ri-time-line"></i>
              </div>
              <div class="stat-info">
                <span class="stat-label">Pending Orders</span>
                <div class="stat-value-wrapper">
                  <span class="stat-value">{{ metrics.pending_orders }}</span>
                  <span class="stat-trend trend-neutral">
                    <i class="ri-more-line"></i> Waiting
                  </span>
                </div>
              </div>
              </div>

              <div class="stat-card">
                <div class="stat-icon stat-icon-success">
                  <i class="ri-money-dollar-circle-line"></i>
                </div>
                <div class="stat-info">
                  <span class="stat-label">Total Revenue</span>
                  <div class="stat-value-wrapper">
                    <span class="stat-value">{{ formatCurrency(metrics.total_revenue) }}</span>
                    <span class="stat-trend trend-up">
                      <i class="ri-arrow-up-line"></i> Total
                    </span>
                  </div>
                </div>
                </div>
      </template>
    </div>
  </div>
</template>

<script>
export default {
  name: 'SalesQuickStatsSidebar',
  props: {
    activeTab: {
      type: String,
      default: ''
    },
    metrics: {
      type: Object,
      default: () => ({
        total_sales_orders: 0,
        today_orders: 0,
        total_revenue: 0,
        pending_orders: 0,
        cancelled_orders: 0
      })
    },
    arMetrics: {
      type: Object,
      default: () => ({
        total_invoices: 0,
        today_invoices: 0,
        outstanding_balance: 0,
        pending_invoices: 0,
        paid_invoices: 0
      })
    },
    listInvoices: {
      type: Array,
      default: () => []
    },
    listReceipts: {
      type: Array,
      default: () => []
    },
    listRemittances: {
      type: Array,
      default: () => []
    },
    remittanceMetrics: {
      type: Object,
      default: () => ({
        total_remittances: 0,
        today_remittances: 0,
        open_remittances: 0,
        total_amount_remitted: 0
      })
    },
    isRightSidebarCollapsed: {
      type: Boolean,
      default: false
    }
  },
  emits: ['toggle'],
  computed: {
    pendingInvoices() {
      return this.listInvoices.filter(inv => inv.balance_due > 0).length;
    },
    totalReceivable() {
      return this.listInvoices.reduce((total, inv) => total + (inv.balance_due || 0), 0);
    },
    totalCollected() {
      return this.listReceipts.reduce((total, receipt) => total + (receipt.amount || 0), 0);
    },
    totalRemitted() {
      return this.listRemittances.reduce((total, rem) => total + (rem.amount || 0), 0);
    }
  },
  methods: {
    formatCurrency(value) {
      if (!value) return '₱0.00';
      return '₱' + Number(value).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    }
  }
}
</script>

<style scoped>
/* Modern Quick Stats Sidebar */
.quick-stats-sidebar {
  background: white;
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
  border: 1px solid #e2e8f0;
}

.card-header-modern {
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
}

.card-header-modern h4 {
  font-size: 1rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
  display: flex;
  align-items: center;
}

.card-header-modern h4 i {
  font-size: 1.25rem;
  color: #2e8b57;
}

.card-body-custom {
  padding: 1rem;
}

/* Modern Stat Card */
.stat-card {
  background: #f8fafc;
  padding: 1rem 1.25rem;
  border-radius: 16px;
  display: flex;
  align-items: center;
  gap: 1rem;
  border: 1px solid #e2e8f0;
  transition: all 0.3s ease;
}

.stat-card:hover {
  background: white;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  border-color: #cbd5e1;
}

/* Stat Icon */
.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  flex-shrink: 0;
}

.stat-icon-primary {
  background: linear-gradient(135deg, #E5F0FF 0%, #DBEAFE 100%);
  color: #3b82f6;
}

.stat-icon-success {
  background: linear-gradient(135deg, #E6F9ED 0%, #D1FAE5 100%);
  color: #10b981;
}

.stat-icon-danger {
  background: linear-gradient(135deg, #FFE5E5 0%, #FEE2E2 100%);
  color: #ef4444;
}

.stat-icon-warning {
  background: linear-gradient(135deg, #FFF0E5 0%, #FFEDD5 100%);
  color: #f97316;
}

.stat-icon-info {
  background: linear-gradient(135deg, #E5F0FF 0%, #DBEAFE 100%);
  color: #3b82f6;
}

.stat-icon-secondary {
  background: linear-gradient(135deg, #F3E8FF 0%, #EDE9FE 100%);
  color: #8b5cf6;
}

/* Stat Info */
.stat-info {
  flex: 1;
  min-width: 0;
}

.stat-label {
  color: #64748b;
  font-size: 0.8rem;
  display: block;
  margin-bottom: 0.25rem;
  font-weight: 500;
}

.stat-value-wrapper {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.5rem;
  min-width: 0;
  flex-wrap: wrap;
}

.stat-value {
  font-size: 1.25rem;
  font-weight: 700;
  color: #1e293b;
  line-height: 1.2;
  word-break: break-word;
  flex: 1 1 auto;
}

/* Trend Indicators */
.stat-trend {
  font-size: 0.7rem;
  padding: 0.2rem 0.5rem;
  border-radius: 20px;
  display: inline-flex;
  align-items: center;
  gap: 0.15rem;
  white-space: nowrap;
  font-weight: 500;
}

.stat-trend i {
  font-size: 0.75rem;
}

.trend-up {
  color: #10b981;
  background: #e6f9ed;
}

.trend-down {
  color: #ef4444;
  background: #fee2e2;
}

.trend-neutral {
  color: #6b7280;
  background: #f3f4f6;
}

/* Responsive */
@media (max-width: 768px) {
  .stat-card {
    padding: 0.875rem 1rem;
    gap: 0.75rem;
  }

  .stat-icon {
    width: 40px;
    height: 40px;
    font-size: 1rem;
  }

  .stat-value {
    font-size: 1.1rem;
  }

  .stat-label {
    font-size: 0.75rem;
  }
}

@media (max-width: 576px) {
  .stat-card {
    padding: 0.75rem;
    gap: 0.5rem;
  }

  .stat-icon {
    width: 36px;
    height: 36px;
    font-size: 0.9rem;
  }

  .stat-value {
    font-size: 1rem;
  }

  .stat-value-wrapper {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.25rem;
  }

  .stat-trend {
    font-size: 0.65rem;
    padding: 0.15rem 0.4rem;
  }

  .stat-label {
    font-size: 0.7rem;
  }
}

@media (max-width: 400px) {
  .stat-card {
    padding: 0.625rem;
    gap: 0.5rem;
  }

  .stat-icon {
    width: 32px;
    height: 32px;
    font-size: 0.8rem;
  }

  .stat-value {
    font-size: 0.9rem;
  }

  .stat-label {
    font-size: 0.65rem;
  }

  .stat-trend {
    font-size: 0.6rem;
    padding: 0.1rem 0.3rem;
  }
}
</style>
