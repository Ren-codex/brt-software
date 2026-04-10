<template>
  <div v-if="showModal" class="ap-details-overlay" @click.self="hide">
    <div class="ap-details-modal">
      <div class="ap-details-header">
        <div>
          <p class="ap-details-kicker mb-1">Accounts Payable</p>
          <h4 class="ap-details-title mb-0">Credit Payable Details</h4>
        </div>
        <div class="ap-details-header-actions">
          <button
            v-if="record"
            type="button"
            class="ap-details-pay-btn"
            @click="handlePay"
          >
            <i class="ri-money-dollar-circle-line"></i>
            <span>Pay Balance</span>
          </button>
          <button type="button" class="ap-details-close-btn" @click="hide">
            <i class="ri-close-line"></i>
          </button>
        </div>
      </div>

      <div class="ap-details-body">
        <div class="ap-detail-info-panel">
          <div class="ap-detail-info-section">
            <div class="ap-detail-section-heading">
              <i class="ri-file-list-3-line"></i>
              <h6>Credit Information</h6>
            </div>

            <div class="ap-detail-info-list">
              <div class="ap-detail-info-row">
                <span class="ap-detail-info-label">Payable No.</span>
                <div class="ap-detail-info-value">
                  {{ record?.received_no || '-' }}
                </div>
              </div>
              <div class="ap-detail-info-row">
                <span class="ap-detail-info-label">Supplier</span>
                <div class="ap-detail-info-value">
                  {{ record?.supplier?.name || '-' }}
                </div>
              </div>
              <div class="ap-detail-info-row">
                <span class="ap-detail-info-label">Purchase Ref.</span>
                <div class="ap-detail-info-value ap-detail-info-value-soft">
                  {{ record?.purchase_order?.po_number || 'No PO' }} • {{ record?.purchase_order?.pr_number || 'No PR' }}
                </div>
              </div>
              <div class="ap-detail-info-row">
                <span class="ap-detail-info-label">Received Date</span>
                <div class="ap-detail-info-value ap-detail-info-value-soft">
                  {{ formatDate(record?.received_date) }}
                </div>
              </div>
              <div class="ap-detail-info-row">
                <span class="ap-detail-info-label">Partial Payments</span>
                <div class="ap-detail-info-value ap-detail-info-value-soft">
                  {{ partialPayments.length }} {{ partialPayments.length === 1 ? 'entry' : 'entries' }}
                </div>
              </div>
              <div class="ap-detail-info-row">
                <span class="ap-detail-info-label">Balance Due</span>
                <div class="ap-detail-info-value ap-detail-info-value-amount">
                  {{ formatCurrency(record?.remaining_balance) }}
                </div>
              </div>
            </div>
          </div>

          <div class="ap-detail-section-divider"></div>

          <div class="ap-detail-info-section">
            <div class="ap-detail-section-heading">
              <i class="ri-wallet-3-line"></i>
              <h6>Settlement Details</h6>
            </div>

            <div class="ap-detail-info-list">
              <div class="ap-detail-info-row">
                <span class="ap-detail-info-label">Total Receipt</span>
                <div class="ap-detail-info-value">
                  {{ formatCurrency(record?.received_total) }}
                </div>
              </div>
              <div class="ap-detail-info-row">
                <span class="ap-detail-info-label">Total Paid</span>
                <div class="ap-detail-info-value">
                  {{ formatCurrency(record?.amount_paid) }}
                </div>
              </div>
              <div class="ap-detail-info-row">
                <span class="ap-detail-info-label">Status</span>
                <div class="ap-detail-info-value">
                  <span class="ap-detail-status-chip" :class="payableStatusClass(record)">
                    {{ payableStatusLabel(record) }}
                  </span>
                </div>
              </div>
              <div class="ap-detail-info-row">
                <span class="ap-detail-info-label">Received By</span>
                <div class="ap-detail-info-value">
                  {{ record?.received_by?.fullname || 'System User' }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="ap-payments-panel">
          <div class="ap-payments-header">
            <div>
              <h6 class="ap-payments-title mb-1">Partial Payment List</h6>
              <p class="ap-payments-subtitle mb-0">Every payment already applied to this credit is listed below.</p>
            </div>
            <div class="ap-payments-count-chip">
              {{ partialPayments.length }} {{ partialPayments.length === 1 ? 'entry' : 'entries' }}
            </div>
          </div>

          <div v-if="partialPayments.length > 0" class="table-responsive ap-payments-table-wrap">
            <table class="table ap-payments-table mb-0">
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
                <tr v-for="(payment, paymentIndex) in partialPayments" :key="payment.id || `payment-${paymentIndex}`">
                  <td>{{ paymentIndex + 1 }}</td>
                  <td>{{ formatDate(payment.payment_date) }}</td>
                  <td>
                    <div class="ap-payment-method-stack">
                      <span class="ap-payment-method-chip" :class="paymentModeClass(payment.payment_mode)">
                        {{ payment.payment_mode || 'Payment' }}
                      </span>
                      <small v-if="payment.is_legacy">Legacy payment</small>
                    </div>
                  </td>
                  <td class="ap-payment-amount">{{ formatCurrency(payment.amount_paid) }}</td>
                  <td>{{ paymentReferenceLabel(payment) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-else class="ap-payments-empty-state">
            <i class="ri-inbox-line"></i>
            <p class="mb-1">No partial payments yet</p>
            <small>The full payable balance is still outstanding.</small>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ViewAccountsPayableModal',
  emits: ['pay'],
  data() {
    return {
      showModal: false,
      record: null,
    };
  },
  computed: {
    partialPayments() {
      return (this.record?.payments || [])
        .slice()
        .sort((left, right) => {
          const leftDate = `${left?.payment_date || ''} ${left?.created_at || ''}`;
          const rightDate = `${right?.payment_date || ''} ${right?.created_at || ''}`;
          return rightDate.localeCompare(leftDate);
        });
    },
  },
  methods: {
    show(record) {
      this.record = record;
      this.showModal = true;
    },
    hide() {
      this.showModal = false;
      this.record = null;
    },
    handlePay() {
      if (!this.record) {
        return;
      }

      const record = this.record;
      this.hide();
      this.$emit('pay', record);
    },
    formatCurrency(value) {
      return '₱' + Number(value || 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
    },
    formatDate(value) {
      if (!value) return 'N/A';

      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return value;

      return date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
      });
    },
    payableStatusLabel(record) {
      return Number(record?.amount_paid || 0) > 0 ? 'Partially Paid' : 'Unpaid';
    },
    payableStatusClass(record) {
      return Number(record?.amount_paid || 0) > 0 ? 'status-partial' : 'status-unpaid';
    },
    paymentModeClass(mode) {
      return String(mode || 'payment')
        .toLowerCase()
        .replace(/\s+/g, '-');
    },
    paymentReferenceLabel(payment) {
      if (String(payment?.payment_mode || '').toLowerCase() === 'bank transfer') {
        const bank = payment?.bank_name || 'Bank not provided';
        const reference = payment?.reference_number || 'Reference missing';
        return `${bank} • ${reference}`;
      }

      return 'Cash payment';
    },
  },
};
</script>

<style scoped>
.ap-details-overlay {
  position: fixed;
  inset: 0;
  z-index: 2100;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(4px);
}

.ap-details-modal {
  width: min(100%, 1120px);
  max-height: calc(100vh - 2rem);
  border-radius: 24px;
  overflow: hidden;
  background: #fff;
  box-shadow: 0 24px 70px rgba(15, 23, 42, 0.25);
  display: flex;
  flex-direction: column;
}

.ap-details-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.2rem 1.4rem;
  background: linear-gradient(135deg, #3d8d7a 0%, #4f9e8c 100%);
  color: #fff;
}

.ap-details-kicker {
  opacity: 0.85;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.ap-details-title {
  color: #fff;
  font-weight: 700;
}

.ap-details-header-actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.ap-details-pay-btn {
  border: none;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.18);
  color: #fff;
  padding: 0.7rem 1rem;
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  font-weight: 700;
}

.ap-details-close-btn {
  width: 40px;
  height: 40px;
  border: none;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.18);
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
}

.ap-details-body {
  padding: 1rem 1.25rem 1.2rem;
  overflow: auto;
}

.ap-detail-info-panel {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 1px minmax(0, 1fr);
  align-items: start;
  border: 1px solid #e2ede7;
  border-radius: 14px;
  background: #fcfffd;
  margin-bottom: 0.9rem;
  overflow: hidden;
}

.ap-detail-info-section {
  padding: 0.15rem 0;
}

.ap-detail-section-heading {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.55rem 0.75rem 0.2rem;
  color: #415d54;
}

.ap-detail-section-heading i {
  color: #2f7666;
  font-size: 0.9rem;
}

.ap-detail-section-heading h6 {
  margin: 0;
  color: #4b5563;
  font-size: 0.82rem;
  font-weight: 700;
}

.ap-detail-section-divider {
  align-self: stretch;
  background: #e4efe9;
}

.ap-detail-info-list {
  display: flex;
  flex-direction: column;
}

.ap-detail-info-row {
  display: grid;
  grid-template-columns: minmax(96px, 130px) 1fr;
  align-items: center;
  gap: 0.6rem;
  padding: 0.45rem 0.75rem;
  min-height: 36px;
}

.ap-detail-info-row + .ap-detail-info-row {
  border-top: 1px solid #f1f5f3;
}

.ap-detail-info-label {
  color: #5f7f73;
  font-size: 0.74rem;
  font-weight: 700;
}

.ap-detail-info-value {
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

.ap-detail-info-value-soft {
  color: #556f67;
  font-weight: 600;
}

.ap-detail-info-value-amount {
  color: #1f6b56;
}

.ap-detail-status-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.25rem 0.6rem;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 700;
  line-height: 1;
}

.ap-detail-status-chip.status-unpaid {
  color: #8a5207;
  background: #fff3cd;
  border: 1px solid #ffe08a;
}

.ap-detail-status-chip.status-partial {
  color: #166534;
  background: #dcfce7;
  border: 1px solid #bbf7d0;
}

.ap-payments-panel {
  border-radius: 15px;
  border: 1px solid #dbece4;
  background: #ffffff;
  padding: 0.9rem;
}

.ap-payments-header {
  margin-bottom: 0.7rem;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.ap-payments-title {
  color: #214e41;
  font-weight: 700;
  font-size: 1.02rem;
}

.ap-payments-subtitle {
  color: #6b7280;
  font-size: 0.78rem;
}

.ap-payments-count-chip {
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

.ap-payments-table-wrap {
  border: 1px solid #edf2f7;
  border-radius: 18px;
  overflow: hidden;
  background: #fff;
}

.ap-payments-table thead th {
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  color: #334155;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 0.85rem 0.95rem;
  white-space: nowrap;
}

.ap-payments-table tbody td {
  padding: 0.85rem 0.95rem;
  vertical-align: middle;
  border-color: #eef2f7;
}

.ap-payments-table tbody tr:hover {
  background: rgba(61, 141, 122, 0.05);
}

.ap-payment-method-stack {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.ap-payment-method-stack small {
  color: #94a3b8;
  font-size: 0.75rem;
}

.ap-payment-method-chip {
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

.ap-payment-method-chip.cash {
  color: #166534;
  background: #dcfce7;
  border: 1px solid #bbf7d0;
}

.ap-payment-method-chip.bank-transfer {
  color: #0c5460;
  background: #d1ecf1;
  border: 1px solid #bee5eb;
}

.ap-payment-method-chip.payment {
  color: #5b21b6;
  background: #ede9fe;
  border: 1px solid #ddd6fe;
}

.ap-payment-amount {
  font-weight: 700;
  color: #1f2937;
}

.ap-payments-empty-state {
  border: 1px dashed #cbd5e1;
  border-radius: 14px;
  padding: 1rem;
  text-align: center;
  color: #64748b;
}

.ap-payments-empty-state i {
  font-size: 1.8rem;
  color: #94a3b8;
}

@media (max-width: 900px) {
  .ap-detail-info-panel {
    grid-template-columns: 1fr;
  }

  .ap-detail-section-divider {
    height: 1px;
  }
}

@media (max-width: 767px) {
  .ap-details-header {
    padding: 1rem;
    flex-direction: column;
  }

  .ap-details-header-actions {
    width: 100%;
    justify-content: space-between;
  }

  .ap-details-body {
    padding: 0.9rem;
  }

  .ap-detail-info-row {
    grid-template-columns: 1fr;
    gap: 0.2rem;
  }

  .ap-detail-info-value {
    justify-content: flex-start;
    text-align: left;
  }

  .ap-payments-header {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>
