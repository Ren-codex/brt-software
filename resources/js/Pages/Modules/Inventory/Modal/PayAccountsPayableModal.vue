<template>
  <Teleport to="body">
  <div v-if="showModal" class="modal-overlay active" @click.self="hide">
    <div class="modal-container modal-md">
      <div class="modal-header">
        <div>
          <p class="modal-kicker mb-1">Accounts Payable</p>
          <h4 class="modal-title mb-0">Record Supplier Payment</h4>
        </div>
        <button type="button" class="close-btn" @click="hide">
          <i class="ri-close-line"></i>
        </button>
      </div>

      <div class="modal-body">
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
              <label class="form-label" for="bank_account_id">Bank Account</label>
              <select
                id="bank_account_id"
                v-model="form.bank_account_id"
                class="form-input"
                @change="onBankAccountChange"
              >
                <option value="">— Select bank account —</option>
                <option v-for="ba in bankAccounts" :key="ba.id" :value="ba.id">
                  {{ ba.bank_name }} — {{ ba.account_name }}
                </option>
              </select>
              <small v-if="bankAccounts.length === 0" class="form-help text-muted">
                No bank accounts configured. <a href="/accounting/bank-accounts" target="_blank">Add one here.</a>
              </small>
              <small v-if="errors.bank_account_id" class="field-error">{{ errors.bank_account_id }}</small>
            </div>

            <div>
              <label class="form-label" for="reference_number">Reference Number</label>
              <input
                id="reference_number"
                v-model.trim="form.reference_number"
                type="text"
                class="form-input"
                placeholder="Enter transfer reference number"
              >
              <small v-if="errors.reference_number" class="field-error">{{ errors.reference_number }}</small>
            </div>
          </template>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="footer-btn secondary" @click="hide" :disabled="isSubmitting">Cancel</button>
        <button type="button" class="footer-btn primary" @click="submit" :disabled="isSubmitting">
          <i v-if="isSubmitting" class="ri-loader-4-line rotating-icon me-1"></i>
          <span>{{ isSubmitting ? 'Processing...' : 'Pay' }}</span>
        </button>
      </div>
    </div>
  </div>
  </Teleport>
</template>

<script>
import { formatCurrency } from '@/Shared/utils/formatters.js';

export default {
  name: 'PayAccountsPayableModal',
  emits: ['paid', 'toast'],
  data() {
    return {
      showModal: false,
      isSubmitting: false,
      record: null,
      bankAccounts: [],
      form: {
        payment_mode: 'Cash',
        payment_amount: '',
        bank_account_id: '',
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
      this.errors = {};
      this.form.payment_mode = 'Cash';
      this.form.payment_amount = '';
      this.form.bank_account_id = '';
      this.form.bank_name = '';
      this.form.reference_number = '';
      this.loadBankAccounts();
    },
    hide(force = false) {
      if (this.isSubmitting && !force) return;

      this.showModal = false;
      this.record = null;
      this.errors = {};
      this.form = {
        payment_mode: 'Cash',
        payment_amount: '',
        bank_account_id: '',
        bank_name: '',
        reference_number: '',
      };
    },
    async loadBankAccounts() {
      try {
        const res = await axios.get('/accounting/bank-accounts/list');
        this.bankAccounts = res.data || [];
      } catch {
        this.bankAccounts = [];
      }
    },
    onBankAccountChange() {
      const selected = this.bankAccounts.find(b => Number(b.id) === Number(this.form.bank_account_id));
      this.form.bank_name = selected ? selected.bank_name : '';
    },
    selectPaymentMode(mode) {
      this.form.payment_mode = mode;
      this.errors.payment_mode = null;

      if (mode !== 'Bank Transfer') {
        this.form.bank_account_id = '';
        this.form.bank_name = '';
        this.form.reference_number = '';
        this.errors.bank_account_id = null;
        this.errors.reference_number = null;
      }
    },
    formatCurrency,
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
        if (!this.form.bank_account_id) {
          errors.bank_account_id = 'Please select a bank account.';
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
          bank_account_id: this.form.payment_mode === 'Bank Transfer' ? (this.form.bank_account_id || null) : null,
          bank_name: this.form.payment_mode === 'Bank Transfer' ? this.form.bank_name : null,
          reference_number: this.form.payment_mode === 'Bank Transfer' ? this.form.reference_number : null,
        });

        const isFullySettled = Number(response.data?.data?.remaining_balance || 0) <= 0;
        const message = isFullySettled
          ? 'Supplier payable fully settled. Record moved to Receiving.'
          : response.data?.message || 'Payment recorded successfully.';
        this.$emit('toast', message);
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
.modal-kicker {
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #6b8c85;
}

.modal-title {
  color: #16322e;
  font-weight: 700;
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

  .footer-btn {
    width: 100%;
  }
}
</style>
