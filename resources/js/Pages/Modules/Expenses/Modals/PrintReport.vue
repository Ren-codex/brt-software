<template>
  <div v-if="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="hide">
    <div class="modal-container modal-md" @click.stop>
      <div class="modal-header">
        <div class="modal-header-icon"><i class="ri-printer-line"></i></div>
        <div>
          <h2>Print Expense Report</h2>
          <p class="modal-header-kicker">Export filtered expenses as PDF</p>
        </div>
        <button class="close-btn" @click="hide"><i class="ri-close-line"></i></button>
      </div>
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group form-group-half">
            <label class="form-label">Date From</label>
            <input type="date" v-model="form.date_from" class="form-control" />
          </div>
          <div class="form-group form-group-half">
            <label class="form-label">Date To</label>
            <input type="date" v-model="form.date_to" class="form-control" />
          </div>
        </div>
        <div class="form-row">
          <div class="form-group form-group-half">
            <label class="form-label">Status</label>
            <select v-model="form.status" class="form-control">
              <option value="">All Statuses</option>
              <option value="recorded">Recorded</option>
              <option value="submitted">Submitted</option>
              <option value="reimbursed">Reimbursed</option>
              <option value="released">Released</option>
              <option value="voided">Voided</option>
            </select>
          </div>
          <div class="form-group form-group-half">
            <label class="form-label">Fund</label>
            <select v-model="form.fund_id" class="form-control">
              <option value="">All Funds</option>
              <option v-for="f in funds" :key="f.id" :value="f.id">{{ f.name }}</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group form-group-full">
            <label class="form-label">Expense Type</label>
            <select v-model="form.expense_type" class="form-control">
              <option value="">All Types</option>
              <option value="operational">Operational</option>
              <option value="utilities">Utilities</option>
              <option value="supplies">Supplies</option>
              <option value="transportation">Transportation</option>
              <option value="maintenance">Maintenance</option>
              <option value="others">Others</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" @click="hide">Cancel</button>
        <button type="button" class="btn btn-primary" @click="print">
          <i class="ri-printer-line me-1"></i>Generate PDF
        </button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
    props: {
        funds: { type: Array, default: () => [] },
    },
    data() {
        return {
            showModal: false,
            form: {
                date_from: this.defaultDateFrom(),
                date_to:   this.defaultDateTo(),
                status:       '',
                fund_id:      '',
                expense_type: '',
            },
        };
    },
    methods: {
        show() { this.showModal = true; },
        hide() { this.showModal = false; },
        defaultDateFrom() {
            const d = new Date();
            return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10);
        },
        defaultDateTo() {
            const d = new Date();
            return new Date(d.getFullYear(), d.getMonth() + 1, 0).toISOString().slice(0, 10);
        },
        print() {
            const params = new URLSearchParams();
            if (this.form.date_from)    params.append('date_from',    this.form.date_from);
            if (this.form.date_to)      params.append('date_to',      this.form.date_to);
            if (this.form.status)       params.append('status',       this.form.status);
            if (this.form.fund_id)      params.append('fund_id',      this.form.fund_id);
            if (this.form.expense_type) params.append('expense_type', this.form.expense_type);
            window.open('/expenses/print?' + params.toString(), '_blank');
            this.hide();
        },
    },
};
</script>
