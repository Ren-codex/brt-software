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

          <div class="library-card-body emp-loan-summary-card">
            <div class="emp-loan-main-stats">
              <div class="emp-primary-stat">
                <div class="emp-stat-number">{{ formatCurrency(remainingBalanceValue) }}</div>
                <div class="emp-stat-label">Total Balance</div>
              </div>

              <div class="emp-progress-section">
                <div class="emp-progress-header">
                  <span>Payment Progress</span>
                  <span class="emp-progress-percentage">{{ loanProgressPercent }}% Complete</span>
                </div>
                <div class="emp-progress-bar">
                  <div class="emp-progress-fill" :style="{ width: `${loanProgressPercent}%` }"></div>
                </div>
                <div class="emp-progress-details">
                  <span class="emp-progress-detail">Paid: <strong>{{ formatCurrency(paidAmountValue) }}</strong></span>
                  <span class="emp-progress-detail">Remaining: <strong>{{ formatCurrency(remainingBalanceValue) }}</strong></span>
                </div>
              </div>
            </div>

            <div class="emp-loan-details-grid" style="grid-template-columns: repeat(3, 1fr);">
              <div class="emp-detail-card">
                <div class="emp-detail-header">
                  <i class="ri-money-dollar-circle-line"></i>
                  <span class="emp-detail-title">Loan Amount</span>
                </div>
                <div class="emp-detail-content">
                  <div class="emp-detail-main-value emp-text-success">{{ formatCurrency(totalAmountValue) }}</div>
                  <div class="emp-detail-sub-value">{{ loan.interest_rate ? `${loan.interest_rate}% Interest` : '-' }}</div>
                </div>
              </div>

              <div class="emp-detail-card">
                <div class="emp-detail-header">
                  <i class="ri-calendar-todo-line"></i>
                  <span class="emp-detail-title">Payment Terms</span>
                </div>
                  <div class="emp-detail-content">
                    <div class="emp-detail-main-value">{{ loanTermMonths > 0 ? `${loanTermMonths} Terms` : '-' }}</div>
                    <div class="emp-detail-sub-value">{{ remainingTermMonths }} Remaining</div>
                  </div>
              </div>

              <div class="emp-detail-card">
                <div class="emp-detail-header">
                  <i class="ri-user-line"></i>
                  <span class="emp-detail-title">Employee</span>
                </div>
                <div class="emp-detail-content">
                  <div class="emp-detail-main-value" style="font-size: 18px">{{ loan.employee?.fullname || '-' }}</div>
                  <div class="emp-detail-sub-value">Daily Rate: {{ formatCurrency(loan.employee?.monthly_salary ?? loan.employee?.salary ?? loan.employee?.basic_salary) }}</div>
                </div>
              </div>
            </div>

            <div class="emp-loan-footer">
              <div class="emp-footer-details">
                <div class="emp-footer-detail">
                  <span class="emp-footer-label">Date Created</span>
                  <span class="emp-footer-value">{{ formatDate(loan.created_at) }}</span>
                </div>
                <div class="emp-footer-detail">
                  <span class="emp-footer-label">Date Approved</span>
                  <span class="emp-footer-value">{{ formatDate(loan.approved_at) }}</span>
                </div>
                <div class="emp-footer-detail">
                  <span class="emp-footer-label">Approved by</span>
                  <span class="emp-footer-value">{{ loan.approved_by || '-' }}</span>
                </div>
                <!-- <div class="emp-footer-detail">
                  <span class="emp-footer-label">Monthly Payment</span>
                  <span class="emp-footer-value">{{ monthlyPaymentDisplay }}</span>
                </div> -->
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="library-card mb-4" style="height: 230px">
          <div class="library-card-header">
            <div class="d-flex align-items-center gap-3">
              <div class="header-icon">
                <i class="ri-wallet-3-line"></i>
              </div>
              <div style="margin-left: -15px" class="d-flex align-items-center gap-2">
                <h4 class="header-title mb-1">Payments</h4>
                <button v-if="loan.status == 'approved'" type="button" @click="addPayment" class="create-btn" style="right: 25px; position: absolute">
                  Pay Now
                </button>
              </div>
            </div>
          </div>
          <div class="card-body m-2 p-3">
            <div class="table-responsive table-card" style="overflow: auto;">
              <table class="table align-middle table-centered mb-0">
                <thead class="table-light thead-fixed">
                  <tr class="fs-11">
                    <th style="width: 5%;">#</th>
                    <th style="width: 30%;">Payment Date</th>
                    <th style="width: 15%;">Amount</th>
                    <th style="width: 30%;">Date Paid</th>
                  </tr>
                </thead>
                <tbody class="table-white fs-12">
                  <tr v-if="!paymentHistory.length">
                    <td colspan="3" class="text-center text-muted py-3">No loan payments yet.</td>
                  </tr>
                  <tr v-for="(payment, index) in displayedPayments" :key="payment.id || index">
                    <td>{{ index + 1 }}</td>
                    <td>{{ payment.paid_date }}</td>
                    <td>{{ formatCurrency(payment.amount) }}</td>
                    <td>{{ payment.created_at}}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-if="paymentHistory.length" class="timeline-footer">
              <button class="btn-load-more" @click="showPaymentHistoryModal = true">
                <i class="ri-arrow-down-line"></i>
                Show More
              </button>
            </div>
          </div>
        </div>
        <TransactionLogs :logs="loan.logs" :compact="true" :initial-visible="2" :logs-per-page="2" />
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
    <div v-if="showPaymentHistoryModal" class="modal-overlay active" @click.self="showPaymentHistoryModal = false">
      <div class="modal-container modal-lg">
        <div class="modal-header">
          <h2>Payment History</h2>
          <button class="close-btn" @click="showPaymentHistoryModal = false">&times;</button>
        </div>
        <div class="modal-body">
          <div class="table-responsive">
            <table class="table align-middle table-striped table-centered mb-0">
              <thead class="table-light">
                <tr class="fs-11">
                  <th style="width: 5%;">#</th>
                  <th style="width: 20%;">Payment Date</th>
                  <th style="width: 15%;">Amount</th>
                  <th style="width: 30%;">Date Paid</th>
                  <th style="width: 30%;">Collected By</th>
                  <th style="width: 30%;">Remarks</th>
                </tr>
              </thead>
              <tbody class="table-white fs-12">
                <tr v-for="(payment, index) in paymentHistory" :key="payment.id || `history-${index}`">
                  <td>{{ index + 1 }}</td>
                  <td>{{ payment.paid_date }}</td>
                  <td>{{ formatCurrency(payment.amount) }}</td>
                  <td>{{ payment.created_at }}</td>
                  <td>{{ payment.added_by }}</td>
                  <td>{{ payment.remarks }}</td>
                </tr>
              </tbody>
            </table>
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
  <LoanPaymentModal
    ref="paymentModal"
    :loan-id="loan.id"
    :term-amount="suggestedPaymentAmount"
    :remaining-terms="remainingTermsToPay"
    :start-date="loan.approved_at"
    :start-term-offset="paidTermsCount"
    @saved="onPaymentSaved"
  />
</template>

<script>
import Swal from 'sweetalert2';
import Create from './Create.vue';
import LoanPaymentModal from './Modals/Payment.vue';
import TransactionLogs from '@/Shared/Components/TransactionLogsCard.vue';

export default {
  name: 'LoanView',
  components: { Create, LoanPaymentModal, TransactionLogs },
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
      loanCollapsed: true,
      showModal: false,
      showPaymentHistoryModal: false,
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
    },
    totalAmountValue() {
      return this.toNumber(this.loan?.amount, 0);
    },
    paidAmountValue() {
      return this.toNumber(this.loan?.amtpaid, 0);
    },
    remainingBalanceValue() {
      const remaining = this.toNumber(this.loan?.remaining_balance, null);
      if (remaining !== null) {
        return remaining;
      }

      return Math.max(this.totalAmountValue - this.paidAmountValue, 0);
    },
    loanProgressPercent() {
      if (this.totalAmountValue <= 0) {
        return 0;
      }

      const progress = (this.paidAmountValue / this.totalAmountValue) * 100;
      return Math.min(100, Math.max(0, Math.round(progress)));
    },
    loanTermMonths() {
      return this.toNumber(this.loan?.term_months, 0);
    },
    interestRateValue() {
      return this.toNumber(this.loan?.interest_rate, 0);
    },
    totalAmountWithInterest() {
      return this.totalAmountValue + (this.totalAmountValue * (this.interestRateValue / 100));
    },
    remainingTermMonths() {
      const remaining = this.toNumber(this.loan?.remaining_term_to_pay, null);
      if (remaining !== null) {
        return remaining;
      }

      return Math.max(this.loanTermMonths - this.paidMonths, 0);
    },
    paidMonths() {
      if (this.loanTermMonths > 0) {
        return Math.max(this.loanTermMonths - this.remainingTermMonths, 0);
      }

      return Array.isArray(this.loan?.payments) ? this.loan.payments.length : 0;
    },
    unpaidMonths() {
      return this.remainingTermMonths;
    },
    suggestedPaymentAmount() {
      const divisor = this.remainingTermMonths;
      if (divisor <= 0) {
        return this.remainingBalanceValue;
      }

      return this.remainingBalanceValue / divisor;
    },

    remainingTermsToPay() {
      return Math.max(0, Math.ceil(this.remainingTermMonths));
    },
    paidTermsCount() {
      return Math.max(0, this.loanTermMonths - this.remainingTermsToPay);
    },
    todayDate() {
      return new Date().toISOString().slice(0, 10);
    },
    paymentHistory() {
      return Array.isArray(this.loan?.payments) ? this.loan.payments : [];
    },
    displayedPayments() {
      return this.paymentHistory.slice(0, 1);
    },
    monthlyPaymentDisplay() {
      if (this.loanTermMonths <= 0) {
        return '-';
      }

      return this.formatCurrency(this.totalAmountWithInterest / this.loanTermMonths);
    },
    loanPeriodLabel() {
      return new Date().toLocaleDateString('en-PH', {
        month: 'long',
        year: 'numeric'
      });
    },
    latestPayment() {
      const payments = Array.isArray(this.loan?.payments)
        ? this.loan.payments.filter(payment => payment?.payment_date || payment?.created_at)
        : [];

      if (!payments.length) {
        return null;
      }

      return [...payments].sort((a, b) =>
        new Date(b.payment_date || b.created_at).getTime() - new Date(a.payment_date || a.created_at).getTime()
      )[0];
    },
    latestPaymentDate() {
      return this.latestPayment?.payment_date || this.latestPayment?.created_at || null;
    },
    nextDueDate() {
      if (!this.latestPaymentDate) {
        return null;
      }

      const date = new Date(this.latestPaymentDate);
      if (Number.isNaN(date.getTime())) {
        return null;
      }

      date.setMonth(date.getMonth() + 1);
      return date;
    }
  },
  methods: {
    toNumber(value, fallback = 0) {
      if (value === null || value === undefined || value === '') {
        return fallback;
      }

      const numeric = Number(value);
      return Number.isFinite(numeric) ? numeric : fallback;
    },
    toggleLoanCollapse() {
      this.loanCollapsed = !this.loanCollapsed;
    },
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
    paymentDateValue(payment) {
      return payment?.payment_date || payment?.paid_date || payment?.created_at || null;
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
    addPayment() {
      this.$refs.paymentModal.show();
    },
    onPaymentSaved(payload) {
      const savedPayment = payload?.payment || {};
      const amount = this.toNumber(payload?.amount, 0);
      const paidTerms = this.toNumber(payload?.paidTerms, 0);
      const paidAmount = this.toNumber(this.loan.amtpaid, 0) + amount;
      const remainingBalance = Math.max(this.toNumber(this.loan.remaining_balance, 0) - amount, 0);
      const remainingTerms = Math.max(this.toNumber(this.loan.remaining_term_to_pay, 0) - paidTerms, 0);

      this.loan.amtpaid = paidAmount;
      this.loan.remaining_balance = remainingBalance;
      this.loan.remaining_term_to_pay = remainingTerms;
      if (remainingBalance <= 0) {
        this.loan.status = 'completed';
      }

      if (!Array.isArray(this.loan.payments)) {
        this.loan.payments = [];
      }

      this.loan.payments.unshift({
        id: savedPayment.id || Date.now(),
        paid_date: savedPayment.paid_date || payload?.paid_date,
        amount: savedPayment.amount ?? amount,
        remarks: savedPayment.remarks ?? payload?.remarks ?? '-',
        added_by: savedPayment.added_by || '-',
        created_at: savedPayment.created_at || payload?.paid_date,
      });

      this.showToast(payload?.message || 'Loan payment saved successfully!');
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
<style>
.timeline-footer {
  text-align: center;
  border-top: 1px solid #f1f5f9;
  margin-top: 25px;
}

.btn-load-more {
  background: none;
  border: 2px solid #e2e8f0;
  padding: 5px 8px;
  border-radius: 40px;
  font-size: 10px;
  font-weight: 500;
  color: #475569;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-load-more:hover {
  background: #f8fafc;
  border-color: #cbd5e1;
  color: #1e293b;
  transform: translateY(-2px);
}

.btn-load-more i {
  transition: transform 0.2s ease;
}

.btn-load-more:hover i {
  transform: translateY(2px);
}
</style>
