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
                <template v-if="loan.status === 'pending'">
                  <button v-if="canApprove" class="create-btn"
                          @click="approveLoan">
                    <i class="ri-check-line"></i>
                    <span>Approval</span>
                  </button>
                  <button @click="openEdit(loan)" class="create-btn" v-b-tooltip.hover title="Edit" style="margin-right: -8px">
                    <i class="ri-pencil-fill"></i>
                  </button>
                  <button @click="deleteLoan" class="create-btn" v-b-tooltip.hover title="Delete" style="margin-right: -8px">
                    <i class="ri-delete-bin-line"></i>
                  </button>
                </template>
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
        <TransactionLogs :logs="loan.logs" :compact="true" :initial-visible="4" :logs-per-page="4" />
      </div>
    </div>
    <div v-if="showModal" class="modal-overlay active" @click.self="onCancel">
      <div class="modal-container modal-lg">
        <div class="modal-header">
          <h2>Approve Loan</h2>
          <button class="close-btn" @click="onCancel">&times;</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label" for="remarks">Remarks</label>
            <textarea
              id="remarks"
              v-model="remarks"
              class="form-control textarea-control"
              rows="4"
              placeholder="Enter your remarks here..."
            ></textarea>
          </div>
          <div class="form-actions">
          <button class="btn btn-cancel" @click="onCancel">Cancel</button>
          <button class="btn btn-cancel" @click="updateStatus('disapproved')">Disapprove</button>
          <button class="btn btn-save" @click="updateStatus('approved')">Approve</button>
        </div>
        </div>
        
      </div>
    </div>
    <!-- Toast Notification -->
    <div v-if="isToastVisible" class="toast-notification">
      <div class="toast-content">
        <i class="ri-check-line text-white me-2"></i>
        {{ toastMessage }}
      </div>
    </div>
  </div>

  <Create @update="refresh" :dropdowns="dropdowns" ref="create" />
</template>

<script>
import Swal from 'sweetalert2';
import Create from './Create.vue';
import TransactionLogs from '@/Shared/Components/TransactionLogsCard.vue';

export default {
  name: 'LoanView',
  components: { Create, TransactionLogs },
  props: {
    dropdowns: Object,
    loan: {
      type: Object,
      required: true,
    }
  },
  emits: ['back', 'edit', 'view'],
  data() {
    return {
      showModal: false,
      remarks: '',
      isToastVisible: false,
      toastMessage: '',
    };
  },
  computed: {
    canApprove() {
      const roles = this.$page.props.roles;
      const userRoles = roles ? Object.values(roles) : [];
      return userRoles.some(role => ['Top Management', 'Administrator'].includes(role));
    }
  },
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
      this.$emit('view', data);
    },

    approveLoan() {
      this.showModal = true;
    },

    updateStatus(status) {
      this.$inertia.put(`/loans/${this.loan.id}/status`, {
        status: status,
        id: this.loan.id,
        remarks: this.remarks
      }, {
        onSuccess: () => {
          Swal.fire(
              'Success',
              'Loan updated successfully!',
              'success'
          );
          this.showModal = false;
          this.remarks = '';
          this.$emit('back');
        },
        onError: () => {
          Swal.fire(
              'Error',
              'Failed to update loan!',
              'error'
          );
        }
      });
    },

    onCancel() {
      this.showModal = false;
      this.remarks = '';
    },

    showToast(message) {
      this.toastMessage = message;
      this.isToastVisible = true;
      setTimeout(() => {
        this.isToastVisible = false;
      }, 3000);
    },

    deleteLoan() {
      this.confirmDelete(this.loan).then(() => {
        this.showToast('Loan deleted successfully!')  
        this.$emit('toast', 'Delete functionality would open the delete modal');
        this.$emit('back');
      });
    },

    async confirmDelete(template) {
      const result = await Swal.fire({
          title: 'Are you sure?',
          text: 'You want to delete this loan?',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Yes, delete it!'
      });
      if (result.isConfirmed) {
          axios.delete(`/loans/${template.id}`)
              .then(response => {
                  if (response.data && response.data.info && response.data.message && response.data.status === false) {
                    Swal.fire({
                      title: response.data.message,
                      text: response.data.info,
                      icon: 'info',
                      confirmButtonText: 'OK'
                    });
                  } else {
                    Swal.fire({
                      title: response.data.message,
                      text: response.data.info,
                      icon: 'success',
                    });
                    this.selectedLoan = null;
                  }
              })
              .catch(error => {
                  console.error(error);
                  Swal.fire(
                      'Error!',
                      'Failed to delete loan.',
                      'error'
                  );
              });
      }
    },
  }
};
</script>
