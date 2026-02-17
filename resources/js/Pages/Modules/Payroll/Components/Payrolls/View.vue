<template>
  <div class="payroll-details">
    <div class="row" v-if="payroll">
      <div class="col-sm-8">
        <div class="row">
          <div class="col-lg-12 mb-4">
            <div class="library-card">
              <div class="library-card-header">
                <div class="d-flex align-items-center justify-content-between">
                  <div class="d-flex align-items-center gap-3">
                    <div class="header-icon">
                      <i class="ri-money-dollar-circle-line"></i>
                    </div>
                    <div>
                      <h4 class="header-title mb-1">{{ payroll.payroll_no }}</h4>
                      <p class="header-subtitle mb-0">View and manage payroll details</p>
                    </div>
                  </div>
                  <div class="d-flex gap-2">
                    <button v-if="canApprove && payroll.status.slug === 'pending'" class="create-btn"
                            @click="approvePayroll">
                      <i class="ri-check-line"></i>
                      <span>Approval</span>
                    </button>
                    <template v-if="payroll.status.slug === 'draft' && payroll.created_by_id === $page.props.user.id">
                      <button @click="editPayroll" class="create-btn" v-b-tooltip.hover title="Edit">
                        <i class="ri-pencil-fill"></i>
                      </button>
                      <button @click="deletePayroll" class="create-btn" v-b-tooltip.hover title="Delete">
                        <i class="ri-delete-bin-line"></i>
                      </button>
                    </template>
                    <button @click="printPayroll" class="create-btn" v-b-tooltip.hover title="Print">
                      <i class="ri-printer-line"></i>
                    </button>
                    <button @click="$emit('back')" class="create-btn" v-b-tooltip.hover title="Back" style="margin-left: -8px">
                      <i class="ri-arrow-left-line"></i>
                    </button>
                  </div>
                </div>
              </div>
              <div class="library-card-body">
                <div class="row">
                  <div class="col-lg-8">
                    <div class="row">
                      <div class="col-md-6">
                        <div class="mb-3">
                          <label class="form-label">Pay Period</label>
                          <p class="text-muted">{{ formatDate(payroll.pay_period_start) }} - {{ formatDate(payroll.pay_period_end) }}</p>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="mb-3">
                          <label class="form-label">Payroll Template</label>
                          <p class="text-muted">{{ payroll.payroll_name }}</p>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="mb-3">
                          <label class="form-label">Status</label>
                          <span
                            :style="{ color: payroll.status?.text_color, backgroundColor: payroll.status?.bg_color, padding: '0.25rem 0.5rem', borderRadius: '0.5rem' }">
                            {{ payroll.status.slug }}
                          </span>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="mb-3">
                          <label class="form-label">Total Amount</label>
                          <p class="text-muted">{{ formatCurrency(payroll.total_amount) }}</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-lg-12 mb-4">
            <div class="library-card">
              <div class="library-card-header">
                <div class="d-flex align-items-center gap-3">
                  <div class="header-icon">
                    <i class="ri-list-check"></i>
                  </div>
                  <div>
                    <h4 class="header-title mb-1">Payroll Employees</h4>
                    <p class="header-subtitle mb-0">Payroll items and details</p>
                  </div>
                </div>
              </div>
              <div class="library-card-body">
                <div class="table-section">
                  <div class="table-responsive">
                    <table class="table align-middle table-centered mb-0">
                      <thead>
                        <tr>
                          <th>#</th>
                          <th>Employee</th>
                          <th>Basic Salary</th>
                          <th>Total Days</th>
                          <th>Overtime Hours</th>
                          <th>Deductions</th>
                          <th>Net Salary</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="(item, index) in payroll.payroll_items" :key="item.id">
                          <td>{{ index + 1 }}</td>
                          <td>{{ item.employee_name }}</td>
                          <td>{{ formatCurrency(item.basic_salary) }}</td>
                          <td>{{ formatCurrency(item.total_days) }}</td>
                          <td>{{ formatCurrency(item.overtime_hours) }}</td>
                          <td>{{ formatCurrency(item.deductions) }}
                            <div class="loan-container" v-if="item.loans.length">
                              <i class="ri-git-repository-line loan-icon"></i>
                              <div class="loan-tooltip">
                                <div v-for="loan in item.loans" :key="loan.id" class="loan-detail">
                                  <strong>Loan ID:</strong> {{ loan.id }}<br>
                                  <strong>Balance:</strong> ₱ {{ parseFloat(loan.remaining_balance).toFixed(2) }}<br>
                                  <strong>Term Left:</strong> {{ loan.term_months }} months<br>
                                  <strong>Interest Rate:</strong> {{ Math.round(loan.interest_rate) }}%<br>
                                  <strong>Payroll Deduction:</strong> ₱ {{ loan.payroll_deduction ||((loan.remaining_balance / loan.remaining_term_to_pay) + (loan.remaining_balance * (loan.interest_rate / 100) / 2)).toFixed(2) }}
                                </div>
                              </div>
                            </div>
                          </td>
                          <td>{{ formatCurrency(item.net_salary) }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-sm-4">
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
            <div class="table-section" v-if="payroll.logs && payroll.logs.length">
              <div class="table-responsive">
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
                    <tr v-for="(log, index) in payroll.logs" :key="log.id">
                      <td>{{ formatDate(log.created_at) }}</td>
                      <td>{{ log.user?.name || 'N/A' }}</td>
                      <td>{{ log.action }}</td>
                      <td>{{ log.remarks }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <p v-else>No transaction logs available.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div v-if="showModal" class="modal-overlay active" @click.self="onCancel">
    <div class="modal-container modal-lg">
      <div class="modal-header">
        <h2>Approve Payroll</h2>
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
</template>

<script>
import Swal from 'sweetalert2';

export default {
  name: "PayrollView",
  props: {
    payroll: Object,
    dropdowns: Object,
  },
  emits: ['back', 'toast', 'fetch'],
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
      return userRoles.some(role => ['Inventory Manager', 'Top Management', 'Administrator'].includes(role));
    }
  },
  methods: {
    formatDate(dateString) {
      if (!dateString) return '';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    },
    
    formatCurrency(value) {
      if (!value && value !== 0) return '₱0.00';
      return '₱' + Number(value).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    },
    
    getStatusStyle(status) {
      if (!status) return {};
      return {
        color: status.text_color || '#000000',
        backgroundColor: status.bg_color || '#ffffff',
        border: `1px solid ${status.bg_color ? status.bg_color + '40' : '#cccccc'}`,
      };
    },
    
    approvePayroll() {
      this.showModal = true;
    },
    
    editPayroll() {
      // This would open the edit modal in the parent component
      this.$emit('toast', 'Edit functionality would open the edit modal');
    },
    
    deletePayroll() {
      // This would trigger the delete modal in the parent component
      this.$emit('toast', 'Delete functionality would open the delete modal');
    },
    
    printPayroll() {
      if (this.payroll) {
        window.open(`/payrolls/${this.payroll.id}/print`, '_blank');
      }
    },

    updateStatus(status) {
      this.$inertia.put(`/payrolls/${this.payroll.id}/status`, {
        status: status,
        id: this.payroll.id,
        remarks: this.remarks
      }, {
        onSuccess: () => {
          Swal.fire(
              'Success',
              'Payroll updated successfully!',
              'success'
          );
          this.showModal = false;
          this.remarks = '';
          this.$inertia.visit('/payrolls');
        },
        onError: () => {
          Swal.fire(
              'Error',
              'Failed to update payroll!',
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
  }
};
</script>

<style scoped>
.toast-notification {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 9999;
  background-color: #28a745;
  color: white;
  padding: 12px 16px;
  border-radius: 4px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
  animation: slideIn 0.3s ease-out;
}

.toast-content {
  display: flex;
  align-items: center;
  font-size: 14px;
}

@keyframes slideIn {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

/* Loan Tooltip */
.loan-container {
  position: relative;
  display: inline-block;
}

.loan-icon {
  color: #3D8D7A;
  cursor: pointer;
  font-size: 1rem;
  margin-left: 0.5rem;
}

.loan-tooltip {
  visibility: hidden;
  opacity: 0;
  position: fixed;
  top: 50%;
  left: 71%;
  transform: translateX(-50%);
  background: #fffce3;
  border: 1px solid #e9ecef;
  border-radius: 8px;
  padding: 1rem;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  z-index: 9999;
  min-width: 250px;
  max-width: 350px;
  font-size: 0.85rem;
  transition: opacity 0.3s ease, visibility 0.3s ease;
}

.loan-container:hover .loan-tooltip {
  visibility: visible;
  opacity: 999;
}

.loan-detail {
  margin-bottom: 0.75rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid #f0f0f0;
}

.loan-detail:last-child {
  margin-bottom: 0;
  padding-bottom: 0;
  border-bottom: none;
}

.loan-detail strong {
  color: #3D8D7A;
}
</style>
