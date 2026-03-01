<template>
  <div v-if="showModal" class="modal-overlay active" @click.self="hide">
    <div class="modal-container modal-lg">
      <div class="modal-header">
        <h2>Record Loan Payment</h2>
        <button class="close-btn" @click="hide">&times;</button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Monthly Loan Schedule</label>
            <div class="table-responsive table-card" style="max-height: 280px; overflow: auto;">
              <table class="table align-middle table-striped mb-0">
                <thead class="table-light">
                  <tr>
                    <th style="width: 70px;">Pay</th>
                    <th>Month</th>
                    <th style="width: 180px;">Amount</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!scheduleRows.length">
                    <td colspan="3" class="text-center text-muted py-3">No remaining monthly terms.</td>
                  </tr>
                  <tr v-for="row in scheduleRows" :key="row.index">
                    <td>
                      <input
                        type="checkbox"
                        class="form-check-input"
                        :value="row.index"
                        v-model="selectedMonths"
                      >
                    </td>
                    <td>{{ row.label }}</td>
                    <td>{{ numberFormat(monthlyAmount) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-if="errors.amount" class="invalid-feedback d-block mt-2">{{ errors.amount[0] }}</div>
          </div>

          <div class="col-md-6">
            <label class="form-label">Total Amount</label>
            <input :value="numberFormat(totalAmount)" type="text" class="form-control" readonly>
          </div>
        </div>

        <div class="form-actions mt-3">
          <button class="btn btn-cancel" @click="hide" :disabled="isSaving">Cancel</button>
          <button class="btn btn-save" @click="submit" :disabled="isSaving">
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
      const start = this.form.created_at ? new Date(this.form.created_at) : new Date();
      start.setDate(1);

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
        .join(', ');
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
          paid_term: this.paidTermLabel,
          paid_term_months: this.selectedMonths.length,
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
          Swal.fire(
            'Error!',
            'Failed to save loan payment.',
            'error'
          );
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
