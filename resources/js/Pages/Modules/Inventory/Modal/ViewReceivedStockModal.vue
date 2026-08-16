<template>
  <Teleport to="body">
  <div v-if="showModal" class="modal-overlay active" @click.self="hide">
    <div class="modal-container modal-xl">
      <div class="modal-header">
        <div>
          <p class="rs-details-kicker mb-1">Received Stocks</p>
          <h4 class="rs-details-title mb-0">{{ record?.received_no || `RCV-${record?.id}` }}</h4>
        </div>
        <button type="button" class="close-btn" @click="hide">
          <i class="ri-close-line"></i>
        </button>
      </div>

      <div class="modal-body">
        <div class="rs-detail-info-panel">
          <div class="rs-detail-info-section">
            <div class="rs-detail-section-heading">
              <i class="ri-file-list-3-line"></i>
              <h6>Receipt Information</h6>
            </div>

            <div class="rs-detail-info-list">
              <div class="rs-detail-info-row">
                <span class="rs-detail-info-label">Received No.</span>
                <div class="rs-detail-info-value">
                  {{ record?.received_no || `RCV-${record?.id}` }}
                </div>
              </div>
              <div class="rs-detail-info-row">
                <span class="rs-detail-info-label">Supplier</span>
                <div class="rs-detail-info-value">
                  {{ record?.supplier?.name || '-' }}
                </div>
              </div>
              <div class="rs-detail-info-row">
                <span class="rs-detail-info-label">Purchase Ref.</span>
                <div class="rs-detail-info-value rs-detail-info-value-soft">
                  {{ record?.purchase_order?.po_number || 'No PO' }} • {{ record?.purchase_order?.pr_number || 'No PR' }}
                </div>
              </div>
              <div class="rs-detail-info-row">
                <span class="rs-detail-info-label">Received Date</span>
                <div class="rs-detail-info-value rs-detail-info-value-soft">
                  {{ formatDateTime(record?.received_date) }}
                </div>
              </div>
              <div class="rs-detail-info-row">
                <span class="rs-detail-info-label">Received By</span>
                <div class="rs-detail-info-value rs-detail-info-value-soft">
                  {{ record?.received_by?.fullname || 'System User' }}
                </div>
              </div>
              <div v-if="record?.remarks" class="rs-detail-info-row">
                <span class="rs-detail-info-label">Remarks</span>
                <div class="rs-detail-info-value rs-detail-info-value-soft">
                  {{ record?.remarks }}
                </div>
              </div>
            </div>
          </div>

          <div class="rs-detail-section-divider"></div>

          <div class="rs-detail-info-section">
            <div class="rs-detail-section-heading">
              <i class="ri-wallet-3-line"></i>
              <h6>Payment Summary</h6>
            </div>

            <div class="rs-detail-info-list">
              <div class="rs-detail-info-row">
                <span class="rs-detail-info-label">Total Receipt</span>
                <div class="rs-detail-info-value">
                  {{ formatCurrency(record?.received_total) }}
                </div>
              </div>
              <div class="rs-detail-info-row">
                <span class="rs-detail-info-label">Total Paid</span>
                <div class="rs-detail-info-value">
                  {{ formatCurrency(record?.amount_paid) }}
                </div>
              </div>
              <div class="rs-detail-info-row">
                <span class="rs-detail-info-label">Payment Method</span>
                <div class="rs-detail-info-value">
                  <span class="rs-payment-method-chip" :class="paymentModeClass(record?.payment_mode)">
                    {{ record?.payment_mode || 'N/A' }}
                  </span>
                </div>
              </div>
              <div class="rs-detail-info-row">
                <span class="rs-detail-info-label">Status</span>
                <div class="rs-detail-info-value">
                  <span class="rs-detail-status-chip" :class="record?.is_voided ? 'status-voided' : 'status-active'">
                    {{ record?.is_voided ? 'Voided' : 'Active' }}
                  </span>
                </div>
              </div>
              <div v-if="record?.is_voided" class="rs-detail-info-row">
                <span class="rs-detail-info-label">Void Reason</span>
                <div class="rs-detail-info-value rs-detail-info-value-soft">
                  {{ record?.void_reason || '-' }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="rs-items-panel">
          <div class="rs-items-header">
            <div>
              <h6 class="rs-items-title mb-1">Received Items</h6>
              <p class="rs-items-subtitle mb-0">Products and quantities recorded on this receipt.</p>
            </div>
            <div class="rs-items-count-chip">
              {{ items.length }} {{ items.length === 1 ? 'item' : 'items' }}
            </div>
          </div>

          <div v-if="items.length > 0" class="table-responsive rs-items-table-wrap">
            <table class="table rs-items-table mb-0">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Product</th>
                  <th>Quantity</th>
                  <th>Unit Cost</th>
                  <th>Total Cost</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, itemIndex) in items" :key="item.id || `item-${itemIndex}`">
                  <td>{{ itemIndex + 1 }}</td>
                  <td>{{ item.product?.name || 'N/A' }}</td>
                  <td>{{ item.quantity }}</td>
                  <td>{{ formatCurrency(item.unit_cost) }}</td>
                  <td class="rs-item-amount">{{ formatCurrency(item.total_cost) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-else class="rs-items-empty-state">
            <i class="ri-inbox-line"></i>
            <p class="mb-1">No items recorded</p>
            <small>This receipt has no line items to display.</small>
          </div>
        </div>

        <div class="rs-payments-panel">
          <div class="rs-payments-header">
            <div>
              <h6 class="rs-payments-title mb-1">Payment History</h6>
              <p class="rs-payments-subtitle mb-0">Every payment applied to this receipt is listed below.</p>
            </div>
            <div class="rs-items-count-chip">
              {{ payments.length }} {{ payments.length === 1 ? 'entry' : 'entries' }}
            </div>
          </div>

          <div v-if="payments.length > 0" class="table-responsive rs-payments-table-wrap">
            <table class="table rs-payments-table mb-0">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Payment Date</th>
                  <th>Method</th>
                  <th>Amount</th>
                  <th>Bank / Reference</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(payment, paymentIndex) in payments" :key="payment.id || `payment-${paymentIndex}`">
                  <td>{{ paymentIndex + 1 }}</td>
                  <td>{{ formatDate(payment.payment_date) }}</td>
                  <td>
                    <div class="rs-payment-method-stack">
                      <span class="rs-payment-method-chip" :class="paymentModeClass(payment.payment_mode)">
                        {{ payment.payment_mode || 'Payment' }}
                      </span>
                      <small v-if="payment.is_legacy">Legacy payment</small>
                    </div>
                  </td>
                  <td class="rs-item-amount">{{ formatCurrency(payment.amount_paid) }}</td>
                  <td>{{ paymentReferenceLabel(payment) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-else class="rs-payments-empty-state">
            <i class="ri-inbox-line"></i>
            <p class="mb-1">No payments recorded</p>
            <small>Payment history for this receipt will appear here.</small>
          </div>
        </div>
      </div>
    </div>
  </div>
  </Teleport>
</template>

<script>
import { formatCurrency, formatDate } from '@/Shared/utils/formatters.js';

export default {
  name: 'ViewReceivedStockModal',
  data() {
    return {
      showModal: false,
      record: null,
    };
  },
  computed: {
    items() {
      return this.record?.items || [];
    },
    payments() {
      return (this.record?.payments || [])
        .slice()
        .sort((left, right) => {
          const leftDate = `${left?.payment_date || ''} ${left?.created_at || ''}`;
          const rightDate = `${right?.payment_date || ''} ${right?.created_at || ''}`;
          return rightDate.localeCompare(leftDate);
        });
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
    show(record) {
      this.record = record;
      this.showModal = true;
    },
    hide() {
      this.showModal = false;
      this.record = null;
    },
    formatCurrency,
    formatDate,
    formatDateTime(value) {
      if (!value) return 'N/A';
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return value;
      return date.toLocaleString('en-PH', {
        year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit',
      });
    },
    paymentModeClass(mode) {
      return String(mode || 'payment')
        .toLowerCase()
        .replace(/\s+/g, '-');
    },
    paymentReferenceLabel(payment) {
      const mode = String(payment?.payment_mode || '').toLowerCase();

      if (mode === 'bank transfer') {
        const bank = payment?.bank_name || 'Bank not provided';
        const reference = payment?.reference_number || 'Reference missing';
        return `${bank} • ${reference}`;
      }

      if (mode === 'check') {
        return payment?.reference_number || 'Reference missing';
      }

      return 'Cash payment';
    },
  },
};
</script>

<style scoped>
.rs-details-kicker {
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #6b8c85;
}

.rs-details-title {
  color: #16322e;
  font-weight: 700;
}

.rs-detail-info-panel {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 1px minmax(0, 1fr);
  align-items: start;
  border: 1px solid #e2ede7;
  border-radius: 14px;
  background: #fcfffd;
  margin-bottom: 0.9rem;
  overflow: hidden;
}

.rs-detail-info-section {
  padding: 0.15rem 0;
}

.rs-detail-section-heading {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.55rem 0.75rem 0.2rem;
  color: #415d54;
}

.rs-detail-section-heading i {
  color: #2f7666;
  font-size: 0.9rem;
}

.rs-detail-section-heading h6 {
  margin: 0;
  color: #4b5563;
  font-size: 0.82rem;
  font-weight: 700;
}

.rs-detail-section-divider {
  align-self: stretch;
  background: #e4efe9;
}

.rs-detail-info-list {
  display: flex;
  flex-direction: column;
}

.rs-detail-info-row {
  display: grid;
  grid-template-columns: minmax(96px, 130px) 1fr;
  align-items: center;
  gap: 0.6rem;
  padding: 0.45rem 0.75rem;
  min-height: 36px;
}

.rs-detail-info-row + .rs-detail-info-row {
  border-top: 1px solid #f1f5f3;
}

.rs-detail-info-label {
  color: #5f7f73;
  font-size: 0.74rem;
  font-weight: 700;
}

.rs-detail-info-value {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.3rem;
  text-align: right;
  color: #1f2f2b;
  font-size: 0.86rem;
  font-weight: 700;
  line-height: 1.2;
}

.rs-detail-info-value-soft {
  color: #556f67;
  font-weight: 600;
}

.rs-detail-status-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.25rem 0.6rem;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 700;
  line-height: 1;
}

.rs-detail-status-chip.status-active {
  color: #166534;
  background: #dcfce7;
  border: 1px solid #bbf7d0;
}

.rs-detail-status-chip.status-voided {
  color: #b91c1c;
  background: #fee2e2;
  border: 1px solid #fecaca;
}

.rs-items-panel,
.rs-payments-panel {
  border-radius: 15px;
  border: 1px solid #dbece4;
  background: #ffffff;
  padding: 0.9rem;
  margin-bottom: 0.9rem;
}

.rs-payments-panel {
  margin-bottom: 0;
}

.rs-items-header,
.rs-payments-header {
  margin-bottom: 0.7rem;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.rs-items-title,
.rs-payments-title {
  color: #214e41;
  font-weight: 700;
  font-size: 1.02rem;
}

.rs-items-subtitle,
.rs-payments-subtitle {
  color: #6b7280;
  font-size: 0.78rem;
}

.rs-items-count-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 88px;
  padding: 0.42rem 0.72rem;
  border-radius: 999px;
  background: #f8fafc;
  color: #48665c;
  font-size: 0.72rem;
  font-weight: 700;
  border: 1px solid #dbe5ee;
}

.rs-items-table-wrap,
.rs-payments-table-wrap {
  border: 1px solid #edf2f7;
  border-radius: 18px;
  overflow: hidden;
  background: #fff;
}

.rs-items-table thead th,
.rs-payments-table thead th {
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  color: #334155;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 0.85rem 0.95rem;
  white-space: nowrap;
}

.rs-items-table tbody td,
.rs-payments-table tbody td {
  padding: 0.85rem 0.95rem;
  vertical-align: middle;
  border-color: #eef2f7;
}

.rs-items-table tbody tr:hover,
.rs-payments-table tbody tr:hover {
  background: rgba(61, 141, 122, 0.05);
}

.rs-item-amount {
  font-weight: 700;
  color: #1f2937;
}

.rs-payment-method-stack {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.rs-payment-method-stack small {
  color: #94a3b8;
  font-size: 0.75rem;
}

.rs-payment-method-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 2px 8px;
  border-radius: 14px;
  font-size: 10px;
  font-weight: 600;
  line-height: 1.2;
  letter-spacing: 0.2px;
}

.rs-payment-method-chip.cash {
  color: #166534;
  background: #dcfce7;
  border: 1px solid #bbf7d0;
}

.rs-payment-method-chip.bank-transfer {
  color: #0c5460;
  background: #d1ecf1;
  border: 1px solid #bee5eb;
}

.rs-payment-method-chip.check {
  color: #92400e;
  background: #fef3c7;
  border: 1px solid #fde68a;
}

.rs-payment-method-chip.payment {
  color: #5b21b6;
  background: #ede9fe;
  border: 1px solid #ddd6fe;
}

.rs-items-empty-state,
.rs-payments-empty-state {
  border: 1px dashed #cbd5e1;
  border-radius: 14px;
  padding: 1rem;
  text-align: center;
  color: #64748b;
}

.rs-items-empty-state i,
.rs-payments-empty-state i {
  font-size: 1.8rem;
  color: #94a3b8;
}

@media (max-width: 900px) {
  .rs-detail-info-panel {
    grid-template-columns: 1fr;
  }

  .rs-detail-section-divider {
    height: 1px;
  }
}

@media (max-width: 767px) {
  .rs-detail-info-row {
    grid-template-columns: 1fr;
    gap: 0.2rem;
  }

  .rs-detail-info-value {
    justify-content: flex-start;
    text-align: left;
  }

  .rs-items-header,
  .rs-payments-header {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>
