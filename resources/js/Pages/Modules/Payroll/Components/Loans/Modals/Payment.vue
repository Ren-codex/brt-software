<template>
  <div v-if="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="hide">
    <div class="modal-container modal-lg" @click.stop>
      <div class="modal-header">
        <div class="header-content">
          <div>
            <h2 class="header-title">Record Loan Payment</h2>
          </div>
        </div>
        <button class="close-btn" @click="hide">
          <i class="ri-close-line"></i>
        </button>
      </div>

      <div class="modal-body">
        <div class="payment-form-card">
          <div class="form-group">
            <div class="field-header">
              <label class="form-label" for="payment-amount">
                <i class="ri-wallet-3-line"></i>
                Amount
              </label>
              <label class="full-payment-toggle">
                <input
                  v-model="form.isFullPayment"
                  type="checkbox"
                  @change="toggleFullPayment"
                >
                <span>Full Amount?</span>
              </label>
            </div>
            <div class="input-group" :class="{ 'input-group-error': errors.amount }">
              <span class="currency-symbol">PHP</span>
              <input
                id="payment-amount"
                v-model="form.amount"
                type="number"
                min="0"
                :max="remainingBalance"
                step="0.01"
                class="form-control amount-input"
                :class="{ 'input-error': errors.amount }"
                placeholder="Enter payment amount"
                @input="enforceAmountLimit"
              >
            </div>
            <p class="field-hint">
              Enter any amount up to {{ numberFormat(remainingBalance) }}.
            </p>
            <transition name="slide-fade">
              <div v-if="errors.amount" class="error-message">
                <i class="ri-error-warning-line"></i>
                <span>{{ errors.amount[0] }}</span>
              </div>
            </transition>
          </div>
        </div>

        <!-- <div class="total-card">
          <div class="total-label">
            <i class="ri-calculator-line"></i>
            <span>Total Payment Amount</span>
          </div>
          <div class="total-amount-wrapper">
            <span class="total-amount">{{ numberFormat(totalAmount) }}</span>
          </div>
        </div> -->

        <div class="form-actions">
          <button type="button" class="btn btn-cancel" @click="hide" :disabled="isSaving">
            <i class="ri-close-line"></i>
            Cancel
          </button>
          <button type="button" @click="submit" class="btn btn-save" :disabled="isSaving || totalAmount <= 0">
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
    termAmount: {
      type: Number,
      default: 0,
    },
    termUnits: {
      type: Number,
      default: 1,
    },
    remainingBalance: {
      type: Number,
      default: 0,
    },
    remainingTermUnits: {
      type: Number,
      default: 0,
    },
  },
  emits: ['saved'],
  data() {
    return {
      showModal: false,
      isSaving: false,
      errors: {},
      form: {
        loan_id: '',
        amount: 0,
        remarks: '',
        isFullPayment: false,
        paid_term: 1,
      },
    };
  },
  computed: {
    suggestedAmount() {
      return this.toNumber(this.termAmount, 0);
    },
    totalAmount() {
      return this.toNumber(this.form.amount, 0);
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
      this.form = {
        loan_id: this.loanId,
        amount: this.suggestedAmount > 0 ? this.suggestedAmount.toFixed(2) : 0,
        remarks: '',
        isFullPayment: false,
        paid_term: Math.max(1, this.toNumber(this.termUnits, 1)),
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
    toggleFullPayment() {
      if (this.form.isFullPayment) {
        const maxAmount = this.toNumber(this.remainingBalance, 0);
        this.form.amount = maxAmount > 0 ? maxAmount.toFixed(2) : 0;
        this.form.paid_term = Math.max(1, this.toNumber(this.remainingTermUnits, 1));
      } else {
        this.form.amount = this.suggestedAmount > 0 ? this.suggestedAmount.toFixed(2) : 0;
        this.form.paid_term = Math.max(1, this.toNumber(this.termUnits, 1));
      }

      if (this.errors.amount) {
        delete this.errors.amount;
      }
    },
    enforceAmountLimit() {
      const amount = this.toNumber(this.form.amount, 0);
      const maxAmount = this.toNumber(this.remainingBalance, 0);

      if (amount > maxAmount) {
        this.form.amount = maxAmount > 0 ? maxAmount.toFixed(2) : 0;
      }

      this.form.isFullPayment = amount > 0 && amount === maxAmount;
      this.form.paid_term = this.form.isFullPayment
        ? Math.max(1, this.toNumber(this.remainingTermUnits, 1))
        : Math.max(1, this.toNumber(this.termUnits, 1));

      if (this.errors.amount) {
        delete this.errors.amount;
      }
    },
    async submit() {
      this.errors = {};

      const amount = this.toNumber(this.form.amount, 0);
      if (amount <= 0) {
        this.errors.amount = ['Please enter a valid payment amount.'];
        return;
      }

      if (amount > this.toNumber(this.remainingBalance, 0)) {
        this.errors.amount = ['Payment amount cannot exceed the remaining balance.'];
        return;
      }

      this.isSaving = true;

      try {
        const response = await axios.post('/loan-payments', {
          loan_id: this.loanId,
          amount,
          paid_term: this.toNumber(this.form.paid_term, 1),
          remarks: this.form.remarks || null,
          type: 'advance',
        });

        Swal.fire({
          icon: 'success',
          title: 'Success!',
          text: response?.data?.message || 'Loan payment saved successfully!',
          showConfirmButton: false,
          timer: 1500,
        });

        this.$emit('saved', {
          payment: response?.data?.data || {},
          amount,
          paidTerms: this.toNumber(response?.data?.data?.paid_term, this.form.paid_term),
          paid_date: response?.data?.data?.paid_date || null,
          remarks: this.form.remarks,
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
            confirmButtonColor: '#3D8D7A',
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
        minimumFractionDigits: 2,
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

.payment-form-card {
  background: var(--gray-50);
  border: 1px solid var(--gray-200);
  border-radius: 20px;
  padding: 24px;
  margin-bottom: 24px;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--gray-800);
  margin-bottom: 20px;
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

.payment-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
  margin-bottom: 20px;
}

.summary-card {
  background: #fff;
  border: 1px solid var(--gray-200);
  border-radius: 16px;
  padding: 18px 20px;
}

.summary-label {
  display: block;
  font-size: 13px;
  color: var(--gray-500);
  margin-bottom: 6px;
}

.summary-value {
  font-size: 24px;
  font-weight: 700;
  color: var(--gray-800);
}

.form-group {
  margin-bottom: 18px;
}

.field-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 10px;
}

.form-label {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 0;
  font-size: 14px;
  font-weight: 600;
  color: var(--gray-700);
}

.form-label i {
  color: var(--theme-primary);
}

.full-payment-toggle {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 0;
  font-size: 14px;
  font-weight: 600;
  color: var(--gray-700);
  cursor: pointer;
  white-space: nowrap;
}

.full-payment-toggle input {
  width: 16px;
  height: 16px;
  accent-color: var(--theme-primary);
  cursor: pointer;
}

.input-group {
  position: relative;
  display: flex;
  align-items: stretch;
  border: 2px solid var(--gray-300);
  border-radius: 16px;
  overflow: hidden;
  background: #fff;
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
  transition: all 0.2s ease;
}

.input-group:focus-within {
  border-color: var(--theme-primary);
  box-shadow: 0 0 0 4px rgba(61, 141, 122, 0.14);
}

.currency-symbol {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 68px;
  padding: 0 16px;
  border-right: 1px solid var(--gray-200);
  background: linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
  font-size: 13px;
  font-weight: 700;
  color: var(--theme-primary);
}

.form-control {
  width: 100%;
  border: none;
  padding: 14px 16px;
  font-size: 15px;
  color: var(--gray-800);
  background: #fff;
  transition: all 0.2s ease;
}

.form-control:focus {
  outline: none;
  box-shadow: none;
}

.input-group.input-group-error {
  border-color: #ef4444;
  box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
}

.amount-input {
  font-size: 18px;
  font-weight: 600;
  letter-spacing: 0.2px;
}

.amount-input::placeholder {
  color: #94a3b8;
  font-weight: 500;
}

.textarea-control {
  resize: vertical;
  min-height: 96px;
}

.field-hint {
  margin: 8px 0 0;
  font-size: 13px;
  color: var(--gray-500);
}

.error-message {
  margin-top: 12px;
  padding: 12px 16px;
  background: #fef2f2;
  border-radius: 12px;
  border-left: 4px solid #ef4444;
  display: flex;
  align-items: center;
  gap: 8px;
  color: #b91c1c;
  font-size: 14px;
}

.error-message i {
  font-size: 18px;
}

.total-card {
  background: var(--gray-800);
  border-radius: 20px;
  padding: 20px 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 28px;
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

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  position: sticky;
  bottom: -28px;
  margin: 0 -28px -28px;
  padding: 16px 28px 20px;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.92) 0%, #ffffff 18px);
  border-top: 1px solid var(--gray-200);
  z-index: 5;
  backdrop-filter: blur(6px);
}

.slide-fade-enter-active,
.slide-fade-leave-active {
  transition: all 0.2s ease;
}

.slide-fade-enter-from,
.slide-fade-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

@media (max-width: 768px) {
  .payment-grid {
    grid-template-columns: 1fr;
  }

  .field-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .form-actions {
    flex-direction: column-reverse;
    margin: 0 -28px -28px;
  }
}
</style>
