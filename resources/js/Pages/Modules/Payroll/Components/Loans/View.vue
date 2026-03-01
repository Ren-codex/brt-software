<template>
  <div class="loan-details" v-if="loan">
    <div class="row">
      <div class="col-md-8">
        <div class="library-card">
          <div class="library-card-header">
            <div class="d-flex align-items-center justify-content-between">
              <div class="d-flex align-items-center gap-3">
                <div class="header-icon">
                  <i class="ri-bank-card-line"></i>
                </div>
                <div>
                  <h4 class="header-title mb-1">{{ loan.loan_no }}</h4>
                  <p class="header-subtitle mb-0">View loan details</p>
                </div>
              </div>
              <div class="d-flex gap-2">
                <button @click="openEdit(loan)" class="create-btn" v-b-tooltip.hover title="Edit" style="margin-right: -8px">
                  <i class="ri-pencil-fill"></i>
                </button>
                <button @click="$emit('back')" class="create-btn" v-b-tooltip.hover title="Back">
                  <i class="ri-arrow-left-line"></i>
                </button>
              </div>
            </div>
          </div>

              <div class="library-card-body">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Employee</label>
                    <p class="text-muted mb-0">{{ loan.employee?.fullname || '-' }}</p>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Loan Type</label>
                    <p class="text-muted mb-0">{{ loan.loan_type || '-' }}</p>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Amount</label>
                    <p class="text-muted mb-0">{{ formatCurrency(loan.amount) }}</p>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Interest Rate</label>
                    <p class="text-muted mb-0">{{ loan.interest_rate ? `${loan.interest_rate}%` : '-' }}</p>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Term (Months)</label>
                    <p class="text-muted mb-0">{{ loan.term_months || '-' }}</p>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Remaining Term</label>
                    <p class="text-muted mb-0">{{ loan.remaining_term_to_pay || '-' }}</p>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Amount Paid</label>
                    <p class="text-muted mb-0">{{ formatCurrency(loan.amtpaid) }}</p>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Remaining Balance</label>
                    <p class="text-muted mb-0">{{ formatCurrency(loan.remaining_balance) }}</p>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <p class="mb-0">
                      <span :class="getStatusClass(loan.status)">
                        {{ loan.status || '-' }}
                      </span>
                    </p>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Created</label>
                    <p class="text-muted mb-0">{{ formatDate(loan.created_at) }}</p>
                  </div>
                  <div class="col-12">
                    <label class="form-label">Purpose</label>
                    <p class="text-muted mb-0">{{ loan.purpose || '-' }}</p>
                  </div>
                </div>
              </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="library-card">
            <div class="library-card-header">
              <div class="d-flex align-items-center gap-3">
                <div class="header-icon">
                  <i class="ri-history-line"></i>
                </div>
                <div>
                  <h4 class="header-title mb-1">Transaction Logs</h4>
                  <p class="header-subtitle mb-0">Activity history and remarks</p>
                </div>
              </div>
            </div>
            <div class="library-card-body">
              <div class="table-responsive" v-if="loan.logs && loan.logs.length">
                <table class="table align-middle table-centered mb-0">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>User</th>
                      <th>Action</th>
                      <th>Remarks</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(log, index) in loan.logs" :key="log.id || index">
                      <td>{{ log.created_at || '-' }}</td>
                      <td>{{ log.actioned_by || 'N/A' }}</td>
                      <td>{{ log.action || '-' }}</td>
                      <td>{{ log.remarks || '-' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <p v-else class="mb-0">No transaction logs available.</p>
            </div>
            </div>
      </div>
    </div>
  </div>

  <Create @update="refresh" :dropdowns="dropdowns" ref="create" />
</template>

<script>
import Create from './Create.vue';

export default {
  name: 'LoanView',
  components: { Create },
  props: {
    dropdowns: Object,
    loan: {
      type: Object,
      required: true
    }
  },
  emits: ['back', 'edit', 'view'],
  methods: {
    formatDate(value) {
      if (!value) {
        return '-';
      }

      const date = new Date(value);
      if (Number.isNaN(date.getTime())) {
        return value;
      }

      return date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    },
    formatCurrency(amount) {
      if (amount === null || amount === undefined || amount === '') {
        return '-';
      }

      return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP'
      }).format(Number(amount));
    },
    getStatusClass(status) {
      switch (status) {
        case 'active':
          return 'badge bg-success';
        case 'pending':
          return 'badge bg-warning';
        case 'completed':
          return 'badge bg-info';
        case 'rejected':
        case 'overdue':
          return 'badge bg-danger';
        default:
          return 'badge bg-secondary';
      }
    },

    openEdit(data, index) {
      this.$refs.create.edit(data, index);
    },

    async onDelete(id) {
      const confirmed = await this.$refs.deleteModal.show(
        'Delete Loan',
        'Are you sure you want to delete this loan? This action cannot be undone.'
      );

      if (!confirmed) {
        return;
      }

      axios.delete(`/loans/${id}`)
      .then(response => {
        this.fetch();
        this.$toast.success(response.data.message || 'Loan deleted successfully');
      })
      .catch(err => {
        console.log(err);
        this.$toast.error('Failed to delete loan');
      });
    },

    refresh(data) {
      console.log(data);
      
      this.$emit('view', data);
    }
  }
};
</script>
