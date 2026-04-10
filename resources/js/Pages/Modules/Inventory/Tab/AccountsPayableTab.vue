<template>
  <BRow>
    <div class="col-lg-12 mb-4">
      <div class="library-card">
        <div class="library-card-header">
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
              <div class="header-icon">
                <i class="ri-wallet-3-line fs-24"></i>
              </div>
              <div>
                <h4 class="header-title mb-1">Accounts Payable</h4>
                <p class="header-subtitle mb-0">All open supplier credit balances are listed here. Open any credit to review the partial payments already posted to it.</p>
              </div>
            </div>
            <div class="credit-pill">
              <i class="ri-file-list-3-line"></i>
              <span>{{ filteredPayables.length }} Credit {{ filteredPayables.length === 1 ? 'Record' : 'Records' }}</span>
            </div>
          </div>
        </div>

        <div class="card-body bg-white m-2 p-3">
          <div v-if="isLoading" class="state-panel loading-panel">
            <i class="ri-loader-4-line rotating-icon"></i>
            <span>Loading accounts payable records...</span>
          </div>

          <template v-else>
            <div v-if="!dataReady" class="state-panel info-panel">
              <i class="ri-information-line"></i>
              <span>Accounting sync is not ready yet. Showing live open supplier balances from receiving records.</span>
            </div>

            <div class="search-section">
              <div class="row">
                <div class="col-md-5">
                  <div class="search-wrapper">
                    <i class="ri-search-line search-icon"></i>
                    <input
                      v-model.trim="localKeyword"
                      type="text"
                      placeholder="Search payable no., supplier, PR, PO, or payment reference..."
                      class="search-input"
                    >
                  </div>
                </div>
              </div>
            </div>

            <div class="table-responsive table-card">
              <table class="table align-middle table-hover mb-0">
                <thead class="table-head">
                  <tr class="fs-12 fw-bold text-muted">
                    <th style="width: 5%;">#</th>
                    <th class="text-center">Payable No.</th>
                    <th class="text-center">Purchase Order</th>
                    <th class="text-center">Supplier</th>
                    <th class="text-center">Received Date</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Total Payable</th>
                    <th class="text-center">Total Paid</th>
                    <th class="text-center">Balance Due</th>
                    <th class="text-center">Actions</th>
                  </tr>
                </thead>
                <tbody class="fs-12">
                  <template v-for="(record, index) in filteredPayables" :key="record.id">
                    <tr :class="['main-table-row', rowStateClass(record)]">
                      <td class="text-center">
                        {{ index + 1 }}
                      </td>
                      <td class="text-center fw-semibold">{{ record.received_no || `RCV-${record.id}` }}</td>
                      <td class="text-center">
                        <div class="table-stack text-center">
                          <span>{{ record.purchase_order?.po_number || '-' }}</span>
                          <small class="text-muted">{{ record.purchase_order?.pr_number || 'No PR' }}</small>
                        </div>
                      </td>
                      <td class="text-center">
                        <div class="table-stack text-center">
                          <span>{{ record.supplier?.name || '-' }}</span>
                          <small class="text-muted">{{ supplierSubline(record) }}</small>
                        </div>
                      </td>
                      <td class="text-center">{{ formatDate(record.received_date) }}</td>
                      <td class="text-center">
                        <div class="status-cell">
                          <span class="status-badge" :class="payableStatusClass(record)">
                            {{ payableStatusLabel(record) }}
                          </span>
                          <span class="credit-chip">Credit</span>
                        </div>
                      </td>
                      <td class="text-center amount-value">{{ formatCurrency(record.received_total) }}</td>
                      <td class="text-center amount-value">{{ formatCurrency(record.amount_paid) }}</td>
                      <td class="text-center amount-value balance-due">{{ formatCurrency(record.remaining_balance) }}</td>
                      <td class="text-center">
                        <div class="action-buttons-row">
                          <button
                            type="button"
                            class="table-action-btn"
                            @click.stop="openDetailsModal(record)"
                          >
                            <i class="ri-eye-line"></i>
                            <span>Details</span>
                          </button>
                          <button
                            type="button"
                            class="table-action-btn pay-btn"
                            @click.stop="openPayModal(record)"
                          >
                            <i class="ri-money-dollar-circle-line"></i>
                            <span>Pay</span>
                          </button>
                        </div>
                      </td>
                    </tr>
                  </template>

                  <tr v-if="filteredPayables.length === 0">
                    <td colspan="10" class="text-center py-4">
                      <i class="ri-inbox-line text-muted" style="font-size: 3rem;"></i>
                      <p class="mt-2 mb-0">No open accounts payable credit found</p>
                      <small class="text-muted">Supplier balances with remaining unpaid credit will appear here automatically.</small>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>
        </div>
      </div>
    </div>
    <PayAccountsPayableModal
      ref="payModal"
      @paid="$emit('refresh')"
      @toast="$emit('toast', $event)"
    />
    <ViewAccountsPayableModal
      ref="detailsModal"
      @pay="openPayFromDetails"
    />
  </BRow>
</template>

<script>
import PayAccountsPayableModal from '../Modal/PayAccountsPayableModal.vue';
import ViewAccountsPayableModal from '../Modal/ViewAccountsPayableModal.vue';

export default {
  name: 'AccountsPayableTab',
  components: {
    PayAccountsPayableModal,
    ViewAccountsPayableModal,
  },
  emits: ['refresh', 'toast'],
  props: {
    dataReady: {
      type: Boolean,
      default: false,
    },
    isLoading: {
      type: Boolean,
      default: false,
    },
    summaryCards: {
      type: Array,
      default: () => [],
    },
    rows: {
      type: Array,
      default: () => [],
    },
    receivedStocks: {
      type: Array,
      default: () => [],
    },
  },
  data() {
    return {
      localKeyword: '',
    };
  },
  computed: {
    creditPayables() {
      return (this.receivedStocks || [])
        .filter((record) => this.isAccountsPayableRecord(record))
        .slice()
        .sort((left, right) => new Date(right?.received_date || 0) - new Date(left?.received_date || 0));
    },
    filteredPayables() {
      const keyword = this.localKeyword.toLowerCase();

      return this.creditPayables.filter((record) => {
        if (!keyword) return true;

        const paymentTerms = this.partialPayments(record)
          .flatMap((payment) => [
            payment?.payment_mode,
            payment?.bank_name,
            payment?.reference_number,
          ])
          .filter(Boolean);

        const haystack = [
          record?.supplier?.name,
          record?.supplier?.contact_person,
          record?.purchase_order?.pr_number,
          record?.purchase_order?.po_number,
          record?.received_no,
          ...paymentTerms,
        ]
          .filter(Boolean)
          .join(' ')
          .toLowerCase();

        return haystack.includes(keyword);
      });
    },
  },
  methods: {
    openDetailsModal(record) {
      this.$refs.detailsModal.show(record);
    },
    openPayModal(record) {
      this.$refs.payModal.show(record);
    },
    openPayFromDetails(record) {
      this.$refs.payModal.show(record);
    },
    partialPayments(record) {
      return (record?.payments || [])
        .slice()
        .sort((left, right) => {
          const leftDate = `${left?.payment_date || ''} ${left?.created_at || ''}`;
          const rightDate = `${right?.payment_date || ''} ${right?.created_at || ''}`;
          return rightDate.localeCompare(leftDate);
        });
    },
    isAccountsPayableRecord(record) {
      return Number(record?.remaining_balance || 0) > 0;
    },
    isUnpaid(record) {
      return Number(record?.amount_paid || 0) <= 0;
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
      return this.isUnpaid(record) ? 'Unpaid' : 'Partially Paid';
    },
    payableStatusClass(record) {
      return this.isUnpaid(record) ? 'status-unpaid' : 'status-partial';
    },
    rowStateClass(record) {
      return this.isUnpaid(record) ? 'unpaid-row' : 'partial-row';
    },
    supplierSubline(record) {
      return record?.supplier?.contact_person || 'Supplier balance pending';
    },
  },
};
</script>

<style scoped>
.search-section {
  margin-bottom: 1.25rem;
}

.search-wrapper {
  position: relative;
}

.search-icon {
  position: absolute;
  left: 1rem;
  top: 50%;
  transform: translateY(-50%);
  color: #6c757d;
  font-size: 1rem;
  z-index: 2;
}

.search-input {
  width: 100%;
  min-height: 46px;
  padding: 0.7rem 1rem 0.7rem 2.7rem;
  border: 1px solid #ced4da;
  border-radius: 10px;
  background: #fff;
  color: #495057;
  transition: all 0.3s ease;
}

.search-input:focus {
  outline: none;
  border-color: #2e8b57;
  box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.1);
}

.credit-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.65rem 0.9rem;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.16);
  color: #fff;
  font-size: 0.8rem;
  font-weight: 700;
}

.table-card {
  border-radius: 10px;
  overflow: hidden;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.08);
}

.table-head {
  background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.main-table-row {
  cursor: default;
  transition: all 0.2s ease;
  border-left: 3px solid transparent;
}

.main-table-row td {
  padding-top: 0.65rem;
  padding-bottom: 0.65rem;
  vertical-align: middle;
}

.main-table-row:hover {
  background-color: rgba(61, 141, 122, 0.05) !important;
  border-left-color: #3d8d7a;
}

.main-table-row.unpaid-row {
  background: rgba(251, 191, 36, 0.08);
  border-left-color: #d97706;
}

.main-table-row.unpaid-row:hover {
  background: rgba(251, 191, 36, 0.14) !important;
}

.main-table-row.partial-row {
  background: rgba(59, 130, 246, 0.07);
  border-left-color: #2563eb;
}

.main-table-row.partial-row:hover {
  background: rgba(59, 130, 246, 0.12) !important;
}

.table-stack {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.status-cell {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.35rem;
}

.status-badge,
.credit-chip {
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

.status-badge.status-unpaid {
  color: #8a5207;
  background: #fff3cd;
  border: 1px solid #ffe08a;
}

.status-badge.status-partial {
  color: #1d4ed8;
  background: #dbeafe;
  border: 1px solid #bfdbfe;
}

.credit-chip {
  color: #7c2d12;
  background: #ffedd5;
  border: 1px solid #fed7aa;
}

.amount-value {
  font-weight: 700;
  color: #1f2937;
}

.balance-due {
  color: #c2410c;
}

.table-action-btn {
  border: 1px solid #d0d7de;
  background: #fff;
  color: #2e8b57;
  border-radius: 8px;
  padding: 0.4rem 0.65rem;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.75rem;
  font-weight: 600;
  transition: all 0.2s ease;
}

.table-action-btn:hover {
  background: #f0f9f4;
  border-color: #9ad0b7;
}

.action-buttons-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  flex-wrap: wrap;
}

.pay-btn {
  background: #3d8d7a;
  border-color: #3d8d7a;
  color: #fff;
}

.pay-btn:hover {
  background: #347868;
  border-color: #347868;
}

@media (max-width: 767px) {
  .action-buttons-row {
    flex-direction: column;
  }
}
</style>
