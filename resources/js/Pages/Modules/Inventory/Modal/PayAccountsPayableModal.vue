<template>
  <div v-if="showModal" class="payable-modal-overlay" @click.self="hide">
    <div class="payable-modal">
      <div class="payable-modal-header">
        <div>
          <p class="modal-kicker mb-1">Accounts Payable</p>
          <h4 class="modal-title mb-0">Record Supplier Payment</h4>
        </div>
        <button type="button" class="close-btn" @click="hide">
          <i class="ri-close-line"></i>
        </button>
      </div>

      <div class="payable-modal-body">
        <div class="summary-grid">
          <div class="summary-card">
            <span class="summary-label">Payable No.</span>
            <strong>{{ record?.received_no || '-' }}</strong>
          </div>
          <div class="summary-card">
            <span class="summary-label">Supplier</span>
            <strong>{{ record?.supplier?.name || '-' }}</strong>
          </div>
          <div class="summary-card">
            <span class="summary-label">Remaining Balance</span>
            <strong>{{ formatCurrency(remainingBalance) }}</strong>
          </div>
        </div>

        <div class="form-grid">
          <div>
            <label class="form-label">Payment Method</label>
            <div class="method-options">
              <button
                v-for="option in paymentOptions"
                :key="option"
                type="button"
                class="method-option"
                :class="{ active: form.payment_mode === option }"
                @click="selectPaymentMode(option)"
              >
                {{ option }}
              </button>
            </div>
            <small v-if="errors.payment_mode" class="field-error">{{ errors.payment_mode }}</small>
          </div>

          <div>
            <label class="form-label" for="payment_amount">Payment Amount</label>
            <input
              id="payment_amount"
              v-model="form.payment_amount"
              type="number"
              min="0"
              step="0.01"
              class="form-input"
              :max="remainingBalance"
            >
            <small class="form-help">Maximum payable amount: {{ formatCurrency(remainingBalance) }}</small>
            <small v-if="errors.payment_amount" class="field-error">{{ errors.payment_amount }}</small>
          </div>

          <template v-if="form.payment_mode === 'Bank Transfer'">
            <div>
              <label class="form-label" for="bank_name">Bank Name</label>
              <input
                id="bank_name"
                v-model.trim="form.bank_name"
                type="text"
                class="form-input"
                placeholder="Enter bank name"
              >
              <small v-if="errors.bank_name" class="field-error">{{ errors.bank_name }}</small>
            </div>

            <div>
              <label class="form-label" for="reference_number">Reference Number</label>
              <input
                id="reference_number"
                v-model.trim="form.reference_number"
                type="text"
                class="form-input"
                placeholder="Enter reference number"
              >
              <small v-if="errors.reference_number" class="field-error">{{ errors.reference_number }}</small>
            </div>
          </template>
        </div>
      </div>

      <div class="payable-modal-footer">
        <button type="button" class="footer-btn secondary" @click="hide" :disabled="isSubmitting">Cancel</button>
        <button type="button" class="footer-btn primary" @click="submit" :disabled="isSubmitting">
          <i v-if="isSubmitting" class="ri-loader-4-line rotating-icon me-1"></i>
          <span>{{ isSubmitting ? 'Processing...' : 'Pay' }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PayAccountsPayableModal',
  emits: ['paid', 'toast'],
  data() {
    return {
      showModal: false,
      isSubmitting: false,
      record: null,
      form: {
        payment_mode: 'Cash',
        payment_amount: '',
        bank_name: '',
        reference_number: '',
      },
      errors: {},
      paymentOptions: ['Cash', 'Bank Transfer'],
    };
  },
  computed: {
    remainingBalance() {
      return Number(this.record?.remaining_balance || 0);
    },
  },
  methods: {
    show(record) {
      this.record = record;
      this.showModal = true;
      this.errors = {};
      this.form.payment_mode = 'Cash';
      this.form.payment_amount = this.remainingBalance > 0 ? this.remainingBalance.toFixed(2) : '';
      this.form.bank_name = record?.bank_name || '';
      this.form.reference_number = record?.reference_number || '';
    },
    hide(force = false) {
      if (this.isSubmitting && !force) return;

      this.showModal = false;
      this.record = null;
      this.errors = {};
      this.form = {
        payment_mode: 'Cash',
        payment_amount: '',
        bank_name: '',
        reference_number: '',
      };
    },
    selectPaymentMode(mode) {
      this.form.payment_mode = mode;
      this.errors.payment_mode = null;

      if (mode !== 'Bank Transfer') {
        this.form.bank_name = '';
        this.form.reference_number = '';
        this.errors.bank_name = null;
        this.errors.reference_number = null;
      }
    },
    formatCurrency(value) {
      return '₱' + Number(value || 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
    },
    validate() {
      const paymentAmount = Number(this.form.payment_amount || 0);
      const errors = {};

      if (!this.form.payment_mode) {
        errors.payment_mode = 'Payment method is required.';
      }

      if (!paymentAmount || paymentAmount <= 0) {
        errors.payment_amount = 'Payment amount must be greater than zero.';
      } else if (paymentAmount > this.remainingBalance) {
        errors.payment_amount = 'Payment amount cannot exceed the remaining balance.';
      }

      if (this.form.payment_mode === 'Bank Transfer') {
        if (!this.form.bank_name) {
          errors.bank_name = 'Bank name is required.';
        }

        if (!this.form.reference_number) {
          errors.reference_number = 'Reference number is required.';
        }
      }

      this.errors = errors;
      return Object.keys(errors).length === 0;
    },
    async submit() {
      if (!this.record?.id || !this.validate()) {
        return;
      }

      this.isSubmitting = true;

      try {
        const response = await axios.post(`/received-stocks/${this.record.id}/pay`, {
          payment_mode: this.form.payment_mode,
          payment_amount: Number(this.form.payment_amount),
          bank_name: this.form.payment_mode === 'Bank Transfer' ? this.form.bank_name : null,
          reference_number: this.form.payment_mode === 'Bank Transfer' ? this.form.reference_number : null,
        });

        this.$emit('toast', response.data?.message || 'Accounts payable payment recorded successfully.');
        this.$emit('paid', response.data?.data || null);
        this.hide(true);
      } catch (error) {
        const validationErrors = error.response?.data?.errors || {};
        if (Object.keys(validationErrors).length > 0) {
          this.errors = Object.fromEntries(
            Object.entries(validationErrors).map(([key, messages]) => [key, Array.isArray(messages) ? messages[0] : messages])
          );
        } else {
          this.$emit('toast', 'Failed to record payable payment.');
        }
      } finally {
        this.isSubmitting = false;
      }
    },
  },
};
</script>

<style scoped>
.payable-modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(4px);
}

.payable-modal {
  width: min(100%, 720px);
  border-radius: 24px;
  overflow: hidden;
  background: #fff;
  box-shadow: 0 24px 70px rgba(15, 23, 42, 0.25);
}

.payable-modal-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.35rem 1.5rem;
  background: linear-gradient(135deg, #3d8d7a 0%, #4f9e8c 100%);
  color: #fff;
}

.modal-kicker {
  opacity: 0.85;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.modal-title {
  color: #fff;
  font-weight: 700;
}

.close-btn {
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

.payable-modal-body {
  padding: 1.5rem;
}

.summary-grid {
  display: grid;
  gap: 0.85rem;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  margin-bottom: 1.25rem;
}

.summary-card {
  padding: 1rem;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  background: #f8fbfa;
}

.summary-label {
  display: block;
  margin-bottom: 0.35rem;
  color: #64748b;
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
}

.info-banner {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.95rem 1rem;
  margin-bottom: 1.25rem;
  border-radius: 14px;
  border: 1px solid #dbeafe;
  background: #eff6ff;
  color: #1d4ed8;
}

.form-grid {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.form-label {
  display: block;
  margin-bottom: 0.45rem;
  color: #334155;
  font-weight: 600;
}

.method-options {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.method-option {
  min-width: 140px;
  padding: 0.85rem 1rem;
  border: 1px solid #d5e3dd;
  border-radius: 14px;
  background: #fff;
  color: #40645d;
  font-weight: 700;
  transition: all 0.2s ease;
}

.method-option.active {
  background: #3d8d7a;
  border-color: #3d8d7a;
  color: #fff;
}

.method-option.disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.form-input {
  width: 100%;
  min-height: 48px;
  padding: 0.8rem 0.95rem;
  border: 1px solid #cbd5e1;
  border-radius: 14px;
  background: #fff;
  color: #0f172a;
}

.form-input:focus {
  outline: none;
  border-color: #3d8d7a;
  box-shadow: 0 0 0 4px rgba(61, 141, 122, 0.12);
}

.form-help {
  display: block;
  margin-top: 0.35rem;
  color: #64748b;
  font-size: 0.8rem;
}

.field-error {
  display: block;
  margin-top: 0.35rem;
  color: #dc2626;
  font-size: 0.8rem;
}

.payable-modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding: 1.1rem 1.5rem 1.5rem;
}

.footer-btn {
  min-width: 132px;
  min-height: 46px;
  border-radius: 14px;
  border: 1px solid transparent;
  font-weight: 700;
}

.footer-btn.secondary {
  background: #fff;
  border-color: #d8dee7;
  color: #64748b;
}

.footer-btn.primary {
  background: #3d8d7a;
  color: #fff;
}

.rotating-icon {
  animation: spin 0.9s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }

  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 768px) {
  .form-grid {
    grid-template-columns: 1fr;
  }

  .payable-modal-footer {
    flex-direction: column-reverse;
  }

  .footer-btn {
    width: 100%;
  }
}
</style>
