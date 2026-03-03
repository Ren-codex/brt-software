<template>
  <div v-if="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="hide">
    <div class="modal-container modal-lg" @click.stop>
      <!-- Modal Header with Theme Color -->
      <div class="modal-header">
        <div class="header-content">
          <div class="header-icon">
            <i class="ri-bank-card-line"></i>
          </div>
          <div>
            <h2 class="header-title">Record Loan Payment</h2>
            <p class="header-subtitle">Process monthly installment payment</p>
          </div>
        </div>
        <button class="close-btn" @click="hide">
          <i class="ri-close-line"></i>
        </button>
      </div>

      <div class="modal-body">
        <!-- Schedule Selection Section -->
        <div class="schedule-section">
          <div class="section-header">
            <div class="section-title">
              <i class="ri-calendar-check-line"></i>
              <h3>Select Payment Months</h3>
            </div>
            <div class="selection-actions" v-if="scheduleRows.length">
              <button 
                type="button" 
                class="btn-select-all" 
                @click="selectAllMonths"
                :disabled="selectedMonths.length === scheduleRows.length"
              >
                <i class="ri-checkbox-multiple-line"></i>
                Select All
              </button>
              <button 
                type="button" 
                class="btn-clear-all" 
                @click="clearSelection"
                :disabled="!selectedMonths.length"
              >
                <i class="ri-close-circle-line"></i>
                Clear
              </button>
            </div>
          </div>

          <div class="table-container" :class="{ 'has-selection': selectedMonths.length }">
            <div class="table-responsive">
              <table class="schedule-table">
                <thead>
                  <tr>
                    <th width="60" class="text-center">
                      <div class="checkbox-header">
                        <input
                          type="checkbox"
                          class="checkbox-input"
                          :checked="isAllSelected"
                          :indeterminate="isIndeterminate"
                          @change="toggleSelectAll"
                          :disabled="!scheduleRows.length"
                        >
                      </div>
                    </th>
                    <th>Payment Month</th>
                    <th class="text-right">Amount</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!scheduleRows.length" class="empty-row">
                    <td colspan="3">
                      <div class="empty-state">
                        <i class="ri-calendar-todo-line"></i>
                        <p>No remaining monthly terms available</p>
                      </div>
                    </td>
                  </tr>
                  <tr 
                    v-for="row in scheduleRows" 
                    :key="row.index"
                    class="schedule-row"
                    :class="{ 'selected': isSelected(row.index) }"
                    @click="toggleRow(row.index)"
                  >
                    <td class="text-center" @click.stop>
                      <div class="checkbox-cell">
                        <input
                          type="checkbox"
                          class="checkbox-input"
                          :value="row.index"
                          v-model="selectedMonths"
                          @change="handleRowChange"
                        >
                      </div>
                    </td>
                    <td>
                      <div class="month-info">
                        <span class="month-name">{{ row.label }}</span>
                        <!-- <span class="month-badge">Term {{ row.index }}</span> -->
                      </div>
                    </td>
                    <td class="text-right">
                      <span class="month-amount">{{ numberFormat(monthlyAmount) }}</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Error Message -->
          <transition name="slide-fade">
            <div v-if="errors.amount" class="error-message">
              <i class="ri-error-warning-line"></i>
              <span>{{ errors.amount[0] }}</span>
            </div>
          </transition>
        </div>

        <!-- Total Amount Card -->
        <div class="total-card">
          <div class="total-label">
            <i class="ri-calculator-line"></i>
            <span>Total Payment Amount</span>
          </div>
          <div class="total-amount-wrapper">
            <span class="total-amount">{{ numberFormat(totalAmount) }}</span>
            <span class="total-months" v-if="selectedMonths.length">
              ({{ selectedMonths.length }} month{{ selectedMonths.length > 1 ? 's' : '' }})
            </span>
          </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
          <button type="button" class="btn btn-cancel" @click="hide" :disabled="isSaving">
            <i class="ri-close-line"></i>
            Cancel
          </button>
          <button type="button" @click="submit"  class="btn btn-save" :disabled="isSaving || !selectedMonths.length">
              <i class="ri-save-line" v-if="!isSaving"></i>
              <i class="ri-loader-4-line spinner" v-else></i>
              {{ isSaving ? 'Saving...' : 'Save Payment' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import Swal from 'sweetalert2';

export default {
  name: 'LoanPaymentModal',
  props: {
    loanId: {
      type: [Number, String],
      required: true,
    },
    monthlyAmount: {
      type: Number,
      default: 0,
    },
    remainingMonths: {
      type: Number,
      default: 0,
    },
    startDate: {
      type: String,
      default: '',
    },
    startMonthOffset: {
      type: Number,
      default: 0,
    },
  },
  emits: ['saved'],
  data() {
    return {
      showModal: false,
      isSaving: false,
      selectedMonths: [],
      errors: {},
      form: {
        loan_id: '',
      },
    };
  },
  computed: {
    scheduleRows() {
      const rows = [];
      const months = Math.max(0, this.toNumber(this.remainingMonths, 0));
      const start = this.startDate ? new Date(this.startDate) : new Date();
      const offset = Math.max(0, this.toNumber(this.startMonthOffset, 0));

      if (Number.isNaN(start.getTime())) {
        start.setTime(Date.now());
      }

      start.setDate(1);
      start.setMonth(start.getMonth() + offset);

      for (let i = 0; i < months; i += 1) {
        const d = new Date(start);
        d.setMonth(start.getMonth() + i);
        rows.push({
          index: i + 1,
          label: d.toLocaleDateString('en-PH', { month: 'long', year: 'numeric' }),
        });
      }

      return rows;
    },
    totalAmount() {
      const count = this.selectedMonths.length;
      if (!count) {
        return 0;
      }
      return Number((this.monthlyAmount * count).toFixed(2));
    },
    paidTermLabel() {
      if (!this.selectedMonths.length) {
        return '';
      }
      return this.scheduleRows
        .filter(row => this.selectedMonths.includes(row.index))
        .map(row => row.label)
        .join(' • ');
    },
    isAllSelected() {
      return this.scheduleRows.length > 0 && 
             this.selectedMonths.length === this.scheduleRows.length;
    },
    isIndeterminate() {
      return this.selectedMonths.length > 0 && 
             this.selectedMonths.length < this.scheduleRows.length;
    },
  },
  methods: {
    toNumber(value, fallback = 0) {
      if (value === null || value === undefined || value === '') {
        return fallback;
      }
      const numeric = Number(value);
      return Number.isFinite(numeric) ? numeric : fallback;
    },
    reset() {
      this.errors = {};
      this.selectedMonths = [];
      this.form = {
        loan_id: this.loanId,
      };
    },
    show() {
      this.reset();
      this.showModal = true;
    },
    hide() {
      this.showModal = false;
      this.reset();
    },
    isSelected(index) {
      return this.selectedMonths.includes(index);
    },
    /**
     * Check if a term can be selected based on sequential selection rule.
     * A term can only be selected if all previous terms are already selected.
     */
    canSelect(index) {
      // If already selected, it can be deselected
      if (this.isSelected(index)) {
        return true;
      }
      // Check if all previous terms are selected
      for (let i = 1; i < index; i += 1) {
        if (!this.isSelected(i)) {
          return false;
        }
      }
      return true;
    },
    /**
     * Get the message for why a term cannot be selected
     */
    getSelectionErrorMessage(index) {
      // Find the first unselected previous term
      for (let i = 1; i < index; i += 1) {
        if (!this.isSelected(i)) {
          const row = this.scheduleRows.find(r => r.index === i);
          return row ? `Please select "${row.label}" first` : `Please select term ${i} first`;
        }
      }
      return '';
    },
    toggleRow(index) {
      // Check if selection is allowed based on sequential rule
      if (!this.canSelect(index)) {
        const errorMsg = this.getSelectionErrorMessage(index);
        Swal.fire({
          icon: 'warning',
          title: 'Sequential Selection Required',
          text: errorMsg || `Please select all previous terms before selecting term ${index}.`,
          confirmButtonColor: '#3D8D7A'
        });
        return;
      }

      const idx = this.selectedMonths.indexOf(index);
      if (idx === -1) {
        this.selectedMonths.push(index);
      } else {
        this.selectedMonths.splice(idx, 1);
      }
      this.handleRowChange();
    },
    toggleSelectAll(event) {
      if (event.target.checked) {
        this.selectAllMonths();
      } else {
        this.clearSelection();
      }
    },
    selectAllMonths() {
      this.selectedMonths = this.scheduleRows.map(row => row.index);
    },
    clearSelection() {
      this.selectedMonths = [];
    },
    handleRowChange() {
      // Clear amount error when user selects months
      if (this.errors.amount) {
        this.$delete(this.errors, 'amount');
      }
    },
    async submit() {
      this.errors = {};

      const amount = this.toNumber(this.totalAmount, 0);
      if (amount <= 0) {
        this.errors.amount = ['Please select at least one month to pay.'];
        return;
      }
      
      this.isSaving = true;

      try {
        const response = await axios.post('/loan-payments', {
          loan_id: this.loanId,
          amount,
          paid_date: this.paidTermLabel,
          paid_term: this.selectedMonths.length,
        });

        // Show success message
        Swal.fire({
          icon: 'success',
          title: 'Success!',
          text: response?.data?.message || 'Loan payment saved successfully!',
          showConfirmButton: false,
          timer: 1500
        });

        this.$emit('saved', {
          payment: response?.data?.data || {},
          amount,
          paidTermMonths: this.selectedMonths.length,
          message: response?.data?.message || 'Loan payment saved successfully!',
        });

        this.hide();
      } catch (error) {
        if (error?.response?.status === 422 && error?.response?.data?.errors) {
          this.errors = error.response.data.errors;
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Failed to save loan payment.',
            confirmButtonColor: '#3D8D7A'
          });
        }
      } finally {
        this.isSaving = false;
      }
    },
    numberFormat(value) {
      return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2
      }).format(this.toNumber(value, 0));
    },
  },
};
</script>

<style scoped>
.header-content {
  display: flex;
  align-items: center;
  gap: 16px;
}

.header-icon {
  width: 48px;
  height: 48px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  color: var(--white);
}

.header-title {
  font-size: 20px;
  font-weight: 600;
  color: var(--white);
  margin: 0 0 4px 0;
}

.header-subtitle {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.9);
  margin: 0;
}

.close-btn {
  width: 40px;
  height: 40px;
  border: none;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 12px;
  color: var(--white);
  font-size: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
}

.close-btn:hover {
  background: rgba(255, 255, 255, 0.3);
  transform: rotate(90deg);
}

.modal-body {
  padding: 28px;
  max-height: calc(90vh - 120px);
  overflow-y: auto;
}

/* Loan Summary Card */
.loan-summary-card {
  background: var(--gray-50);
  border-radius: 20px;
  padding: 20px 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 28px;
  border: 1px solid var(--gray-200);
}

.summary-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.summary-label {
  font-size: 13px;
  color: var(--gray-500);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.summary-value {
  font-size: 24px;
  font-weight: 600;
  color: var(--gray-800);
}

.summary-value.highlight {
  color: var(--theme-primary);
  font-size: 28px;
}

.summary-divider {
  width: 2px;
  height: 40px;
  background: linear-gradient(to bottom, transparent, var(--gray-300), transparent);
}

/* Schedule Section */
.schedule-section {
  margin-bottom: 24px;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--gray-800);
}

.section-title i {
  font-size: 20px;
  color: var(--theme-primary);
}

.section-title h3 {
  font-size: 16px;
  font-weight: 600;
  margin: 0;
}

.selection-actions {
  display: flex;
  gap: 8px;
}

.btn-select-all,
.btn-clear-all {
  padding: 8px 14px;
  border: none;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-select-all {
  background: var(--theme-primary-very-soft);
  color: var(--theme-primary);
}

.btn-select-all:hover:not(:disabled) {
  background: var(--theme-primary-soft);
  transform: translateY(-1px);
}

.btn-clear-all {
  background: var(--gray-100);
  color: var(--gray-600);
}

.btn-clear-all:hover:not(:disabled) {
  background: var(--gray-200);
  transform: translateY(-1px);
}

.btn-select-all:disabled,
.btn-clear-all:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Table Styles */
.table-container {
  border: 2px solid var(--gray-200);
  border-radius: 16px;
  overflow: hidden;
  transition: all 0.3s ease;
}

.table-container.has-selection {
  border-color: var(--theme-primary);
  box-shadow: 0 0 0 3px rgba(61, 141, 122, 0.1);
}

.schedule-table {
  width: 100%;
  border-collapse: collapse;
}

.schedule-table th {
  background: var(--gray-50);
  padding: 16px 12px;
  font-size: 13px;
  font-weight: 600;
  color: var(--gray-600);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid var(--gray-200);
}

.schedule-table td {
  padding: 16px 12px;
  border-bottom: 1px solid var(--gray-100);
}

.checkbox-header {
  display: flex;
  justify-content: center;
}

.checkbox-input {
  width: 20px;
  height: 20px;
  border: 2px solid var(--gray-300);
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s ease;
  accent-color: var(--theme-primary);
}

.checkbox-input:hover {
  border-color: var(--theme-primary);
}

.checkbox-cell {
  display: flex;
  justify-content: center;
}

.schedule-row {
  cursor: pointer;
  transition: all 0.2s ease;
}

.schedule-row:hover {
  background: var(--gray-50);
}

.schedule-row.selected {
  background: var(--theme-primary-very-soft);
}

.schedule-row.selected:hover {
  background: var(--theme-primary-soft);
}

.month-info {
  display: flex;
  align-items: center;
  gap: 10px;
}

.month-name {
  font-weight: 500;
  color: var(--gray-800);
}

.month-badge {
  padding: 4px 8px;
  background: var(--gray-200);
  border-radius: 6px;
  font-size: 11px;
  font-weight: 500;
  color: var(--gray-600);
}

.month-amount {
  font-weight: 600;
  color: var(--theme-primary);
  font-size: 15px;
}

.text-right {
  text-align: right;
}

/* Empty State */
.empty-row td {
  padding: 40px 0;
}

.empty-state {
  text-align: center;
  color: var(--gray-400);
}

.empty-state i {
  font-size: 48px;
  margin-bottom: 12px;
  opacity: 0.5;
}

.empty-state p {
  margin: 0;
  font-size: 15px;
}

/* Selection Summary */
.selection-summary {
  padding: 16px;
  background: var(--gray-50);
  border-top: 2px solid var(--gray-200);
  display: flex;
  align-items: center;
  gap: 20px;
}

.summary-badge {
  padding: 8px 16px;
  background: var(--theme-primary);
  border-radius: 40px;
  color: var(--white);
  font-size: 14px;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 8px;
}

.selected-months-preview {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
}

.preview-label {
  color: var(--gray-500);
  font-weight: 500;
}

.preview-text {
  color: var(--gray-800);
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Error Message */
.error-message {
  margin-top: 12px;
  padding: 12px 16px;
  background: #FEF2F2;
  border-radius: 12px;
  border-left: 4px solid #EF4444;
  display: flex;
  align-items: center;
  gap: 8px;
  color: #B91C1C;
  font-size: 14px;
  animation: slideDown 0.3s ease;
}

.error-message i {
  font-size: 18px;
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Total Card */
.total-card {
  background: var(--gray-800);
  border-radius: 20px;
  padding: 20px 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 28px;
  margin-top: -10px;
}

.total-label {
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--gray-300);
  font-size: 16px;
  font-weight: 500;
}

.total-label i {
  font-size: 20px;
  color: var(--gray-400);
}

.total-amount-wrapper {
  text-align: right;
}

.total-amount {
  font-size: 32px;
  font-weight: 700;
  color: var(--white);
  letter-spacing: 1px;
}

.total-months {
  display: block;
  font-size: 13px;
  color: var(--gray-400);
  margin-top: 4px;
}

/* Form Actions */
.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}
</style>
