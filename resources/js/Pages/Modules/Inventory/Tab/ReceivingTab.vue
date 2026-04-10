<template>
  <div class="receiving-tab">
    <div class="receiving-card">
      <div class="receiving-card-header">
        <div class="receiving-header-copy">
          <div class="receiving-header-icon">
            <i class="ri-inbox-unarchive-line"></i>
          </div>
          <div>
            <h4 class="receiving-title">Receiving</h4>
            <p class="receiving-subtitle">List of all paid purchase requests</p>
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

          <div class="receiving-filter-group">
            <button
              v-for="option in paymentFilters"
              :key="option.value"
              type="button"
              class="filter-chip"
              :class="{ active: selectedPaymentFilter === option.value }"
              @click="selectedPaymentFilter = option.value"
            >
              {{ option.label }}
            </button>
          </div>
        </div>

        <div class="table-responsive receiving-table-wrap">
          <table class="table align-middle mb-0 receiving-table">
            <thead>
              <tr>
                <th>#</th>
                <th>PR Number</th>
                <th>PO Number</th>
                <th>Received No.</th>
                <th>Supplier</th>
                <th>Received Date</th>
                <th>Payment Method</th>
                <th>Amount Paid</th>
                <th>Bank Details</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(record, index) in filteredRecords" :key="record.id">
                <td>{{ index + 1 }}</td>
                <td>
                  <strong>{{ record.purchase_order?.pr_number || 'N/A' }}</strong>
                </td>
                <td>{{ record.purchase_order?.po_number || 'N/A' }}</td>
                <td>{{ record.received_no || `RCV-${record.id}` }}</td>
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
                  <span v-if="record.payment_mode === 'Bank Transfer'" class="bank-details">
                    {{ formatBankDetails(record) }}
                  </span>
                  <span v-else class="bank-details muted">Cash payment</span>
                </td>
              </tr>
              <tr v-if="filteredRecords.length === 0">
                <td colspan="9" class="empty-state">
                  <i class="ri-inbox-line"></i>
                  <p>No paid purchase requests found</p>
                  <small>Paid receiving records will appear here after stock is fully settled.</small>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ReceivingTab',
  props: {
    listReceivedStocks: {
      type: Array,
      default: () => [],
    },
  },
  data() {
    return {
      localKeyword: '',
      selectedPaymentFilter: 'all',
      paymentFilters: [
        { value: 'all', label: 'All Paid' },
        { value: 'Cash', label: 'Cash' },
        { value: 'Bank Transfer', label: 'Bank Transfer' },
      ],
    };
  },
  computed: {
    paidReceivingRecords() {
      return (this.listReceivedStocks || []).filter((record) => record?.is_fully_paid === true);
    },
    filteredRecords() {
      const keyword = this.localKeyword.toLowerCase();

      return this.paidReceivingRecords
        .filter((record) => {
          if (this.selectedPaymentFilter !== 'all') {
            return record?.payment_mode === this.selectedPaymentFilter;
          }

          return true;
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
    paymentModeClass(mode) {
      return String(mode || '')
        .toLowerCase()
        .replace(/\s+/g, '-');
    },
    formatBankDetails(record) {
      const bankName = record?.bank_name || 'No bank';
      const referenceNumber = record?.reference_number || 'No reference';

      return `${bankName} • ${referenceNumber}`;
    },
  },
};
</script>

<style scoped>
.receiving-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 24px;
  box-shadow: 0 8px 30px rgba(15, 23, 42, 0.04);
  overflow: hidden;
}

.receiving-card-header {
  padding: 1.5rem;
  border-bottom: 1px solid #eef2f7;
  background: linear-gradient(135deg, #f8fffc 0%, #eef8f4 100%);
}

.receiving-header-copy {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.receiving-header-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 56px;
  height: 56px;
  border-radius: 18px;
  background: linear-gradient(135deg, #dff5ed 0%, #cdeee3 100%);
  color: #2f7666;
  font-size: 1.4rem;
}

.receiving-title {
  margin: 0 0 0.2rem;
  color: #0f172a;
  font-size: 1.35rem;
  font-weight: 700;
}

.receiving-subtitle {
  margin: 0;
  color: #64748b;
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

.receiving-filter-group {
  display: flex;
  gap: 0.6rem;
  flex-wrap: wrap;
}

.filter-chip {
  border: 1px solid #d7e6df;
  border-radius: 999px;
  background: #fff;
  color: #41655d;
  font-weight: 600;
  padding: 0.7rem 1rem;
  transition: all 0.2s ease;
}

.filter-chip.active {
  background: #3d8d7a;
  border-color: #3d8d7a;
  color: #fff;
  box-shadow: 0 10px 24px rgba(61, 141, 122, 0.2);
}

.receiving-table-wrap {
  border: 1px solid #edf2f7;
  border-radius: 20px;
  overflow: hidden;
}

.receiving-table {
  margin: 0;
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

.bank-details {
  color: #334155;
  font-size: 0.92rem;
}

.bank-details.muted {
  color: #94a3b8;
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
