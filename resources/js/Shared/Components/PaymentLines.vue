<template>
  <div class="payment-lines">
    <div v-for="(line, index) in lines" :key="index" class="payment-line">
      <div class="payment-line-main">
        <div class="payment-line-field payment-line-source">
          <label class="payment-line-label">Paid from</label>
          <select
            v-model="line.source"
            class="form-control modern-input"
            @change="onSourceChange(line)"
          >
            <option value="cash">Cash on Hand</option>
            <option v-for="ba in bankAccounts" :key="ba.id" :value="'bank:' + ba.id">
              {{ ba.bank_name }} — {{ ba.account_name }}
            </option>
            <option value="check">Check</option>
          </select>
          <small class="payment-line-hint">{{ availableLabel(line) }}</small>
        </div>

        <div class="payment-line-field payment-line-amount">
          <label class="payment-line-label">Amount</label>
          <div class="amount-input-wrapper">
            <span class="amount-prefix">₱</span>
            <input
              type="number"
              step="0.01"
              min="0"
              v-model.number="line.payment_amount"
              class="form-control modern-input amount-input"
              :class="{ error: lineError(line, index) }"
              placeholder="0.00"
              @input="emitChange"
            />
          </div>
        </div>

        <button
          type="button"
          class="payment-line-remove"
          :disabled="lines.length === 1"
          :title="lines.length === 1 ? 'At least one payment line is required' : 'Remove this line'"
          @click="removeLine(index)"
        >
          <i class="ri-close-line"></i>
        </button>
      </div>

      <!-- Only the sources that need a reference ask for one. -->
      <div v-if="needsReference(line)" class="payment-line-reference">
        <label class="payment-line-label">
          {{ line.source === 'check' ? 'Check number' : 'Reference number' }}
          <span class="text-danger">*</span>
        </label>
        <input
          type="text"
          v-model.trim="line.reference_number"
          class="form-control modern-input"
          :class="{ error: !line.reference_number }"
          :placeholder="line.source === 'check' ? 'e.g. 000123' : 'e.g. TRN-8891'"
          @input="emitChange"
        />
      </div>

      <p v-if="lineError(line, index)" class="payment-line-error">{{ lineError(line, index) }}</p>
    </div>

    <button type="button" class="payment-line-add" @click="addLine">
      <i class="ri-add-line"></i> Add payment line
    </button>

    <div class="payment-lines-summary">
      <div class="summary-row">
        <span>Paying now</span>
        <strong>{{ formatCurrency(totalEntered) }}</strong>
      </div>
      <div class="summary-row" :class="{ 'summary-remaining': remaining > 0 }">
        <span>{{ remaining > 0 ? 'Remaining payable' : 'Fully settled' }}</span>
        <strong>{{ formatCurrency(Math.max(remaining, 0)) }}</strong>
      </div>
    </div>

    <p v-if="overallError" class="payment-lines-error">
      <i class="ri-error-warning-line"></i> {{ overallError }}
    </p>
  </div>
</template>

<script>
/**
 * Collects one or more payment lines for a single transaction, so a payable can
 * be settled with several methods at once. Each line names its own funding
 * source; only bank transfers and checks ask for a reference number.
 *
 * Shared deliberately: the customer-receipt flow reuses this rather than
 * growing a second copy.
 */
const emptyLine = () => ({
  source: 'cash',
  payment_amount: null,
  reference_number: '',
});

export default {
  name: 'PaymentLines',
  props: {
    modelValue: { type: Array, default: () => [] },
    bankAccounts: { type: Array, default: () => [] },
    cashOnHand: { type: Number, default: 0 },
    totalDue: { type: Number, default: 0 },
  },
  emits: ['update:modelValue', 'validity'],
  data() {
    return {
      lines: this.modelValue.length ? [...this.modelValue] : [emptyLine()],
    };
  },
  computed: {
    totalEntered() {
      return this.lines.reduce((sum, l) => sum + (Number(l.payment_amount) || 0), 0);
    },
    remaining() {
      return Number((this.totalDue - this.totalEntered).toFixed(2));
    },
    /** Per-source totals, so two lines on one source are judged together. */
    perSourceTotals() {
      return this.lines.reduce((acc, l) => {
        if (l.source === 'check') return acc;
        acc[l.source] = (acc[l.source] || 0) + (Number(l.payment_amount) || 0);
        return acc;
      }, {});
    },
    overallError() {
      if (this.totalEntered <= 0) return 'Enter at least one payment amount.';
      if (this.totalEntered > this.totalDue) {
        return `Payments total ${this.formatCurrency(this.totalEntered)}, more than the ${this.formatCurrency(this.totalDue)} due.`;
      }
      for (const [source, amount] of Object.entries(this.perSourceTotals)) {
        const available = this.availableFor(source);
        if (available !== null && amount > available) {
          return `${this.sourceLabel(source)} payments total ${this.formatCurrency(amount)}, more than the ${this.formatCurrency(available)} available.`;
        }
      }
      if (this.lines.some((l) => this.needsReference(l) && !l.reference_number)) {
        return 'A reference number is required for bank transfers and checks.';
      }
      return '';
    },
    isValid() {
      return this.overallError === '';
    },
  },
  watch: {
    isValid: {
      immediate: true,
      handler(valid) {
        this.$emit('validity', valid);
      },
    },
  },
  methods: {
    needsReference(line) {
      return line.source === 'check' || String(line.source).startsWith('bank:');
    },
    sourceLabel(source) {
      if (source === 'cash') return 'Cash on Hand';
      if (source === 'check') return 'Check';
      const bank = this.bankFor(source);
      return bank ? `${bank.bank_name} — ${bank.account_name}` : 'Bank';
    },
    bankFor(source) {
      const id = Number(String(source).split(':')[1]);
      return this.bankAccounts.find((b) => Number(b.id) === id) || null;
    },
    /** Null means "no balance to check against", as for a check. */
    availableFor(source) {
      if (source === 'cash') return Number(this.cashOnHand) || 0;
      if (String(source).startsWith('bank:')) {
        const bank = this.bankFor(source);
        return bank ? Number(bank.balance) || 0 : 0;
      }
      return null;
    },
    availableLabel(line) {
      const available = this.availableFor(line.source);
      if (available === null) return 'No balance check for checks';
      return `Available ${this.formatCurrency(available)}`;
    },
    lineError(line) {
      const amount = Number(line.payment_amount) || 0;
      if (line.payment_amount !== null && line.payment_amount !== '' && amount <= 0) {
        return 'Amount must be more than zero.';
      }
      return '';
    },
    onSourceChange(line) {
      if (!this.needsReference(line)) line.reference_number = '';
      this.emitChange();
    },
    addLine() {
      this.lines.push(emptyLine());
      this.emitChange();
    },
    removeLine(index) {
      if (this.lines.length === 1) return;
      this.lines.splice(index, 1);
      this.emitChange();
    },
    /** Emit in the shape the API expects, translating the source back out. */
    emitChange() {
      const payload = this.lines
        .filter((l) => (Number(l.payment_amount) || 0) > 0)
        .map((l) => {
          if (l.source === 'cash') {
            return { payment_mode: 'Cash on Hand', payment_amount: Number(l.payment_amount) };
          }
          if (l.source === 'check') {
            return {
              payment_mode: 'Check',
              payment_amount: Number(l.payment_amount),
              reference_number: l.reference_number,
            };
          }
          const bank = this.bankFor(l.source);
          return {
            payment_mode: 'Bank Transfer',
            payment_amount: Number(l.payment_amount),
            bank_account_id: bank ? bank.id : null,
            bank_name: bank ? bank.bank_name : '',
            reference_number: l.reference_number,
          };
        });

      this.$emit('update:modelValue', payload);
    },
    formatCurrency(value) {
      return new Intl.NumberFormat('en-PH', {
        style: 'currency', currency: 'PHP', minimumFractionDigits: 2,
      }).format(Number(value) || 0);
    },
  },
};
</script>

<style scoped>
.payment-lines {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.payment-line {
  border: 1px solid #e4efeb;
  border-radius: 12px;
  padding: 0.75rem;
  background: #fbfefd;
}

.payment-line-main {
  display: flex;
  gap: 0.6rem;
  align-items: flex-end;
}

.payment-line-field {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.payment-line-source {
  flex: 1 1 55%;
}

.payment-line-amount {
  flex: 1 1 45%;
}

.payment-line-label {
  font-size: 0.7rem;
  font-weight: 600;
  color: #4a6b63;
  margin-bottom: 0.25rem;
}

.payment-line-hint {
  font-size: 0.68rem;
  color: #7f9a92;
  margin-top: 0.2rem;
}

.amount-input-wrapper {
  position: relative;
}

.amount-prefix {
  position: absolute;
  left: 0.7rem;
  top: 50%;
  transform: translateY(-50%);
  color: #648b74;
  font-weight: 600;
  font-size: 0.85rem;
}

.amount-input {
  padding-left: 1.9rem !important;
  text-align: right;
}

.payment-line-remove {
  flex: 0 0 auto;
  width: 34px;
  height: 34px;
  border-radius: 9px;
  border: 1px solid #e4d3d3;
  background: #fff;
  color: #b45309;
  cursor: pointer;
  margin-bottom: 1.05rem;
}

.payment-line-remove:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

.payment-line-reference {
  display: flex;
  flex-direction: column;
  margin-top: 0.6rem;
}

.payment-line-error,
.payment-lines-error {
  color: #b91c1c;
  font-size: 0.75rem;
  margin: 0.4rem 0 0;
}

.payment-line-add {
  align-self: flex-start;
  background: none;
  border: 1px dashed #b9d3ca;
  color: #3d8d7a;
  border-radius: 10px;
  padding: 0.45rem 0.8rem;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
}

.payment-line-add:hover {
  background: #f2faf7;
}

.payment-lines-summary {
  border-top: 1px solid #e4efeb;
  padding-top: 0.65rem;
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  font-size: 0.85rem;
  color: #22403a;
}

.summary-remaining strong {
  color: #b45309;
}

.form-control.error {
  border-color: #e74c3c;
}
</style>
