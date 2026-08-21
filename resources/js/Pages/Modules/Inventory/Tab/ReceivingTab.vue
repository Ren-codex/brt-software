<template>
  <div class="receiving-tab">
    <div class="receiving-card">
      <div class="receiving-card-header">
        <div class="receiving-header-copy">
          <div class="receiving-header-icon">
            <i class="ri-inbox-unarchive-line"></i>
          </div>
          <div>
            <h4 class="receiving-title">Received Stocks</h4>
            <p class="receiving-subtitle">All stock received, paid or not</p>
          </div>
        </div>
      </div>

      <div class="receiving-card-body">
        <div class="receiving-toolbar">
          <div class="receiving-search">
            <i class="ri-search-line"></i>
            <input
              v-model.trim="localKeyword"
              type="text"
              class="receiving-search-input"
              placeholder="Search PR number, PO number, supplier, bank, or reference..."
            />
          </div>

          <div class="filter-segment">
            <button
              v-for="option in paymentFilters"
              :key="option.value"
              type="button"
              class="filter-segment-btn"
              :class="{ active: selectedPaymentFilter === option.value }"
              @click="selectedPaymentFilter = option.value"
            >
              <i :class="option.icon"></i>
              <span>{{ option.label }}</span>
            </button>
          </div>
        </div>

        <div class="table-responsive receiving-table-wrap">
          <table class="table align-middle mb-0 receiving-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Received No.</th>
                <th>PO Number</th>
                <th>Supplier</th>
                <th>Received Date</th>
                <th>Payment Method</th>
                <th>Amount Paid</th>
                <th>Payment</th>
                <th>Bank Details</th>
                <th>Remarks</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <TableLoadingRow v-if="loading" :colspan="13" message="Loading received stocks..." />
              <template v-else>
                <tr v-for="(record, index) in filteredRecords" :key="record.id" :class="{ 'voided-row': record.is_voided }">
                  <td>{{ index + 1 }}</td>
                  <td><b>{{ record.received_no || `RCV-${record.id}` }}</b></td>
                  <td>{{ record.purchase_order?.po_number || 'N/A' }}</td>
                  <td>
                    <div class="supplier-cell">
                      <strong>{{ record.supplier?.name || 'Unknown Supplier' }}</strong>
                      <small v-if="record.supplier?.contact_person">{{ record.supplier.contact_person }}</small>
                    </div>
                  </td>
                  <td>{{ formatDate(record.received_date) }}</td>
                  <td>
                    <span class="payment-badge" :class="paymentModeClass(record.payment_mode)">
                      {{ record.payment_mode || 'N/A' }}
                    </span>
                  </td>
                  <td>{{ formatCurrency(record.amount_paid) }}</td>
                  <td>
                    <span class="payment-status" :class="paymentStatus(record).key">
                      {{ paymentStatus(record).label }}
                    </span>
                    <small v-if="paymentStatus(record).key !== 'paid' && !record.is_voided" class="balance-note">
                      {{ formatCurrency(record.remaining_balance) }} outstanding
                    </small>
                  </td>
                  <td>
                    <span v-if="record.payment_mode === 'Bank Transfer'" class="bank-details">
                      {{ formatBankDetails(record) }}
                    </span>
                    <span v-else-if="record.payment_mode === 'Check'" class="bank-details">
                      {{ formatCheckDetails(record) }}
                    </span>
                    <span v-else class="bank-details muted">Cash payment</span>
                  </td>
                  <td>
                    <span v-if="record.remarks" class="remarks-cell">{{ record.remarks }}</span>
                    <span v-else class="bank-details muted">—</span>
                  </td>
                  <td>
                    <span v-if="record.is_voided" class="status-badge status-voided" :title="record.void_reason || ''">
                      Voided
                    </span>
                    <span v-else class="status-badge status-active">Active</span>
                  </td>
                  <td>
                    <div class="action-buttons">
                      <button
                        type="button"
                        class="action-btn action-btn-view"
                        title="View Details"
                        @click="openDetailsModal(record)"
                      >
                        <i class="ri-eye-line"></i>
                      </button>
                      <button
                        v-if="!record.is_voided"
                        type="button"
                        class="action-btn action-btn-void"
                        title="Void this received stock"
                        @click="openVoidModal(record)"
                      >
                        <i class="ri-close-circle-line"></i>
                      </button>
                    </div>
                  </td>
                </tr>
                <tr v-if="filteredRecords.length === 0">
                  <td colspan="13" class="empty-state">
                    <i class="ri-inbox-line"></i>
                    <p>No received stock found</p>
                    <small>Receipts appear here as soon as stock is received, paid or not.</small>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <VoidReceivedStockModal
      ref="voidModal"
      @voided="$emit('refresh')"
      @toast="$emit('toast', $event)"
    />
    <ViewReceivedStockModal ref="detailsModal" />
  </div>
</template>

<script>
import TableLoadingRow from '@/Shared/Components/TableLoadingRow.vue';
import VoidReceivedStockModal from '../Modal/VoidReceivedStockModal.vue';
import ViewReceivedStockModal from '../Modal/ViewReceivedStockModal.vue';

export default {
  name: 'ReceivingTab',
  components: { TableLoadingRow, VoidReceivedStockModal, ViewReceivedStockModal },
  emits: ['refresh', 'toast'],
  props: {
    listReceivedStocks: {
      type: Array,
      default: () => [],
    },
    loading: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      localKeyword: '',
      selectedPaymentFilter: 'all',
      paymentFilters: [
        { value: 'all',           label: 'All',           icon: 'ri-list-check-2' },
        { value: 'unpaid',        label: 'Unpaid',        icon: 'ri-time-line' },
        { value: 'paid',          label: 'Fully Paid',    icon: 'ri-check-double-line' },
        { value: 'Cash',          label: 'Cash',          icon: 'ri-cash-line' },
        { value: 'Bank Transfer', label: 'Bank Transfer', icon: 'ri-bank-line' },
        { value: 'Check',         label: 'Check',         icon: 'ri-file-text-line' },
      ],
    };
  },
  computed: {
    /**
     * Every receipt, settled or not. This list used to keep only fully paid
     * records, which meant a tab called Received Stocks showed nothing at all
     * while the goods sat in Accounts Payable — and once receiving was split
     * from paying, a warehouse manager's receipts could never qualify.
     */
    paidReceivingRecords() {
      return this.listReceivedStocks || [];
    },
    filteredRecords() {
      const keyword = this.localKeyword.toLowerCase();

      return this.paidReceivingRecords
        .filter((record) => {
          const filter = this.selectedPaymentFilter;
          if (filter === 'all') return true;
          if (filter === 'paid') return record?.is_fully_paid === true;
          if (filter === 'unpaid') return record?.is_fully_paid !== true;
          // Anything else names a payment method.
          return record?.payment_mode === filter;
        })
        .filter((record) => {
          if (!keyword) return true;

          const haystack = [
            record?.purchase_order?.pr_number,
            record?.purchase_order?.po_number,
            record?.received_no,
            record?.supplier?.name,
            record?.payment_mode,
            record?.bank_name,
            record?.reference_number,
          ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

          return haystack.includes(keyword);
        })
        .slice()
        .sort((left, right) => {
          return new Date(right?.received_date || 0) - new Date(left?.received_date || 0);
        });
    },
  },
  methods: {
    openVoidModal(record) {
      this.$refs.voidModal.show(record);
    },
    openDetailsModal(record) {
      this.$refs.detailsModal.show(record);
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
    /**
     * Where a receipt stands: settled, part-paid, or still owed. Voided ones say
     * so rather than reporting a balance nobody has to pay.
     */
    paymentStatus(record) {
      if (record?.is_voided) return { key: 'voided', label: 'Voided' };
      if (record?.is_fully_paid) return { key: 'paid', label: 'Fully Paid' };
      if (Number(record?.amount_paid) > 0) return { key: 'partial', label: 'Partially Paid' };
      return { key: 'unpaid', label: 'Unpaid' };
    },
    paymentModeClass(mode) {
      return String(mode || '')
        .toLowerCase()
        .replace(/\s+/g, '-');
    },
    formatBankDetails(record) {
      const latestBankPayment = (record?.payments || [])
        .find(p => String(p.payment_mode).toLowerCase() === 'bank transfer');
      const bankName = latestBankPayment?.bank_name || record?.bank_name || 'No bank';
      const referenceNumber = latestBankPayment?.reference_number || record?.reference_number || 'No reference';

      return `${bankName} • ${referenceNumber}`;
    },
    formatCheckDetails(record) {
      const latestCheckPayment = (record?.payments || [])
        .find(p => String(p.payment_mode).toLowerCase() === 'check');
      const referenceNumber = latestCheckPayment?.reference_number || record?.reference_number || 'No reference';

      return `Ref#: ${referenceNumber}`;
    },
  },
};
</script>

<style scoped>
.payment-status {
  display: inline-block;
  padding: 2px 9px;
  border-radius: 10px;
  font-size: 0.68rem;
  font-weight: 700;
  white-space: nowrap;
}

.payment-status.paid    { background: #dcfce7; color: #166534; }
.payment-status.partial { background: #fef3c7; color: #92400e; }
.payment-status.unpaid  { background: #fee2e2; color: #7c2d12; }
.payment-status.voided  { background: #e5e7eb; color: #4b5563; }

.balance-note {
  display: block;
  font-size: 0.66rem;
  color: #94a3b8;
  margin-top: 2px;
}

.receiving-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 24px;
  box-shadow: 0 8px 30px rgba(15, 23, 42, 0.04);
  overflow: hidden;
}

.receiving-card-header {
  padding: 0.75rem 1.1rem;
  border-bottom: 1px solid #c4d9d2;
  background: linear-gradient(to right, #cfe0d9 0%, #edf6f2 100%);
}

.receiving-header-copy {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.receiving-header-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  border-radius: 10px;
  background: rgba(61, 141, 122, 0.12);
  border: 1px solid rgba(61, 141, 122, 0.16);
  color: #3d8d7a;
  font-size: 18px;
  flex-shrink: 0;
}

.receiving-title {
  margin: 0;
  color: #16322e;
  font-size: 0.95rem;
  font-weight: 700;
}

.receiving-subtitle {
  margin: 0;
  color: #6b8c85;
  font-size: 0.76rem;
}

.receiving-card-body {
  padding: 1.5rem;
}

.receiving-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.25rem;
  flex-wrap: wrap;
}

.receiving-search {
  position: relative;
  flex: 1 1 340px;
}

.receiving-search i {
  position: absolute;
  left: 1rem;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
}

.receiving-search-input {
  width: 100%;
  min-height: 48px;
  padding: 0.85rem 1rem 0.85rem 2.75rem;
  border: 1px solid #dbe3ee;
  border-radius: 16px;
  background: #fff;
  color: #0f172a;
}

.receiving-search-input:focus {
  outline: none;
  border-color: #4c9a85;
  box-shadow: 0 0 0 4px rgba(76, 154, 133, 0.12);
}

.filter-segment {
  display: inline-flex;
  background: #eaf2f0;
  border-radius: 10px;
  padding: 3px;
  gap: 2px;
}

.filter-segment-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: none;
  background: transparent;
  color: #4a7a70;
  font-weight: 600;
  font-size: 0.8rem;
  padding: 0.38rem 0.85rem;
  border-radius: 8px;
  transition: all 0.18s ease;
  white-space: nowrap;
}

.filter-segment-btn i {
  font-size: 0.95rem;
}

.filter-segment-btn:hover:not(.active) {
  background: rgba(61, 141, 122, 0.1);
  color: #3d8d7a;
}

.filter-segment-btn.active {
  background: #3d8d7a;
  color: #fff;
  box-shadow: 0 2px 8px rgba(61, 141, 122, 0.28);
}

.receiving-table-wrap {
  border: 1px solid #edf2f7;
  border-radius: 20px;
  /* overflow:hidden clipped the row instead of scrolling it, so the last
     columns were simply unreachable once the Payment column was added. */
  overflow: auto;
  max-height: 70vh;
}

.receiving-table {
  margin: 0;
  /* Let the columns keep their natural width and scroll, rather than
     squeezing thirteen of them into the viewport. */
  min-width: 1200px;
}

/* Keep the headings in view while scrolling a long list. */
.receiving-table thead th {
  position: sticky;
  top: 0;
  z-index: 2;
}

.receiving-table thead th {
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  color: #334155;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  white-space: nowrap;
}

.receiving-table td {
  vertical-align: middle;
  border-color: #eef2f7;
}

.supplier-cell {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.supplier-cell small {
  color: #64748b;
}

.payment-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.45rem 0.75rem;
  border-radius: 999px;
  font-size: 0.78rem;
  font-weight: 700;
}

.payment-badge.cash {
  color: #166534;
  background: #dcfce7;
}

.payment-badge.bank-transfer {
  color: #1d4ed8;
  background: #dbeafe;
}

.payment-badge.check {
  color: #92400e;
  background: #fef3c7;
}

.bank-details {
  color: #334155;
  font-size: 0.92rem;
}

.bank-details.muted {
  color: #94a3b8;
}

.remarks-cell {
  color: #334155;
  font-size: 0.85rem;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  max-width: 220px;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.3rem 0.7rem;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 700;
}

.status-badge.status-active {
  color: #166534;
  background: #dcfce7;
}

.status-badge.status-voided {
  color: #b91c1c;
  background: #fee2e2;
}

.action-buttons {
  display: flex;
  gap: 8px;
  justify-content: flex-start;
}

.action-btn {
  width: 32px;
  height: 32px;
  border-radius: 6px;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s ease;
}

.action-btn-view {
  background-color: #e3f2fd;
  color: #1976d2;
}

.action-btn-view:hover {
  background-color: #bbdefb;
  transform: translateY(-2px);
}

.action-btn-void {
  background-color: #ffebee;
  color: #d32f2f;
}

.action-btn-void:hover {
  background-color: #ffcdd2;
  transform: translateY(-2px);
}

.voided-row {
  opacity: 0.6;
}

.empty-state {
  padding: 3rem 1rem !important;
  text-align: center;
  color: #94a3b8;
}

.empty-state i {
  display: block;
  margin-bottom: 0.75rem;
  font-size: 2.5rem;
}

.empty-state p {
  margin: 0 0 0.2rem;
  color: #475569;
  font-weight: 600;
}

@media (max-width: 992px) {
  .receiving-card-header {
    padding: 1.25rem;
  }
}

@media (max-width: 768px) {
  .receiving-card-header,
  .receiving-card-body {
    padding: 1rem;
  }

  .receiving-header-copy {
    align-items: flex-start;
  }

  .receiving-header-icon {
    width: 48px;
    height: 48px;
    font-size: 1.2rem;
  }

  .receiving-table-wrap {
    overflow-x: auto;
  }

  .receiving-table {
    min-width: 980px;
  }
}
</style>
