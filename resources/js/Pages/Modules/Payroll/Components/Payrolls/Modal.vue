<template>
  <div>
    <div
      v-if="showModal"
      class="modal-overlay"
      :class="{ active: showModal }"
    >
      <div class="modal-container modal-xl" @click.stop>
        <div class="modal-header">
        <h2>{{ isEdit ? 'Edit' : 'Create' }} Payroll</h2>
        <button class="close-btn" @click="hide">
          <i class="ri-close-line"></i>
        </button>
      </div>
          <div class="modal-body">
          <div class="success-alert" v-if="saveSuccess">
          <i class="ri-checkbox-circle-fill"></i>
          <span>Your information has been saved successfully!</span>
        </div>
          <div class="error-alert" v-if="form.errors.duplicate">
          <i class="ri-close-circle-fill"></i>
          <span>{{ form.errors.duplicate }}</span>
        </div>
          <form @submit.prevent="savePayroll" class="payroll-form">
          <!-- Payroll Period Section -->
          <div class="form-section">
            <div class="row">
              <div class="col-md-6">
                <div class="row">
                  <div class="form-group col-md-6">
                    <label class="form-label">Period Start *</label>
                    <div class="input-wrapper">
                      <i class="ri-calendar-line input-icon"></i>
                      <input 
                        type="date" 
                        v-model="form.pay_period_start" 
                        class="form-control" 
                        :class="{ 'input-error': form.errors.pay_period_start }" 
                        @input="handleInput('pay_period_start')" 
                        required
                        :disabled="isEdit && form.status == 'approval'">
                    </div>
                    <span class="error-message" v-if="form.errors.pay_period_start">{{ form.errors.pay_period_start }}</span>
                  </div>
                  <div class="form-group col-md-6">
                    <label class="form-label">Period End *</label>
                    <div class="input-wrapper">
                      <i class="ri-calendar-line input-icon"></i>
                      <input 
                        type="date" 
                        v-model="form.pay_period_end" 
                        class="form-control" 
                        :class="{ 'input-error': form.errors.pay_period_end }" 
                        @input="handleInput('pay_period_end')" 
                        required
                        :disabled="isEdit && form.status == 'approval'">
                    </div>
                    <span class="error-message" v-if="form.errors.pay_period_end">{{ form.errors.pay_period_end }}</span>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label class="form-label">Payroll Group *</label>
                  <div class="input-wrapper">
                    <i class="ri-layout-grid-line input-icon"></i>
                    <select v-model="form.payroll_template"
                      class="form-control"
                      :class="{ 'input-error': form.errors.payroll_template }"
                      @change="handleInput('payroll_template'); fetchEmployees()"
                      required
                      :disabled="isEdit">
                      <option value="">Select Payroll Group</option>
                      <option v-for="template in payrollTemplates" :key="template.id" :value="template">{{ template.name }}</option>
                    </select>
                  </div>
                  <span class="error-message" v-if="form.errors.payroll_template">{{ form.errors.payroll_template }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Employees Section -->
          <div class="form-section">
            <div class="employees-container">
              <table class="table table-sm">
                <thead>
                  <tr>
                    <th>Employee</th>
                    <th>Daily Salary</th>
                    <th>Working Days</th>
                    <th>Earning</th>
                    <th>Deductions</th>
                    <th>Net Salary</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(employee, index) in selectedEmployees" :key="employee.value">
                    <td class="employee-name">{{ employee.fullname }}</td>
                    <td class="salary-cell">₱ {{ formatCurrency(employee.basic_salary) }}</td>
                    <td class="salary-cell">{{ employee.total_days }}</td>
                    <td class="salary-cell">₱ {{ formatCurrency(employee.total_earnings) }}</td>
                    <td class="salary-cell">₱ {{ formatCurrency(employee.total_deductions) }}</td>
                    <td class="net-salary-cell">
                      <strong>₱ {{ formatCurrency(employee.net_salary) }}</strong>
                    </td>
                    <td>
                      <button type="button" class="btn btn-sm btn-outline-success ms-2" @click="openIndividualModal(employee)" title="Edit employee payroll">
                        <i class="ri-arrow-right-line"></i>
                      </button>
                    </td>
                  </tr>
                  <tr class="total-row" v-if="form.payroll_template && selectedEmployees.length">
                    <td colspan="6" class="total-label">Grand Total</td>
                    <td class="total-amount">
                      <strong>₱ {{ formatCurrency(calculateTotalSalary()) }}</strong>
                    </td>
                  </tr>
                </tbody>
              </table>

              <!-- Formula Section -->
              <!-- <div class="formula-section">
                <button type="button" class="btn btn-sm btn-link formula-toggle" @click="openComputationModal">
                  <i :class="showComputationModal ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line'"></i>
                  {{ showComputationModal ? 'Hide' : 'Show' }} Calculation Formula
                </button>
                <transition name="fade">
                  <div v-if="showComputationModal" class="computation-box">
                    <div class="formula-item">
                      <label>Overtime Rate:</label>
                      <code>((Daily Salary / {{ hours_per_day }}) × {{ overtime_rate }}) × OT Hours</code>
                    </div> -->
                    <!-- <div class="formula-item">
                      <label>Incentive Rate:</label>
                      <code>((Product Packaging (kg) × Sold) / 25) × {{ incentive_rate }}</code>
                    </div> -->
                  <!-- </div>
                </transition>
              </div> -->
            </div>
          </div>
        </form>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-cancel" @click="hide">
          <i class="ri-close-line"></i>
          Cancel
        </button>
        <button type="button" class="btn btn-draft" :disabled="loading" @click="saveDraft">
          <i class="ri-draft-line" v-if="!loading"></i>
          <i class="ri-loader-4-line spinner" v-else></i>
          {{ loading ? 'Saving...' : 'Save as Draft' }}
        </button>
        <button type="submit" class="btn btn-save" :disabled="loading" @click="savePayroll" v-if="form.status == 'draft'">
          <i class="ri-save-line" v-if="!loading"></i>
          <i class="ri-loader-4-line spinner" v-else></i>
          {{ loading ? 'Saving...' : 'Submit for Approval' }}
        </button>
      </div>
    </div>
  </div>

  <!-- Individual Employee Payroll Modal -->
  <Individual
    :show="showIndividualModal"
    :employee="currentIndividualEmployee"
    :dropdowns="dropdowns"
    @close="() => showIndividualModal = false"
    @save="onSaveIndividualPayroll"
  />
  </div>
</template>

<script>
import axios from 'axios'
import Individual from './Individual.vue';
import Swal from 'sweetalert2';

export default {
  components: {
    Individual
  },

  props: {
    payroll: Object,
    isEdit: Boolean,
    dropdowns: Object,
  },

  data() {
    return {
      form: {
        pay_period_start: '',
        pay_period_end: '',
        payroll_template: '',
        errors: {},
        status: 'draft',
      },
      selectedEmployees: [],
      selectedEmployeeIds: [],
      employeeDetails: {},
      showEmployeeSelector: false,
      loading: false,
      showModal: true,
      saveSuccess: false,
      hours_per_day: 0,
      overtime_rate: 0,
      incentive_rate: 0,
      showIncentiveModal: false,
      currentIncentiveEmployee: null,
      incentiveInput: 0,
      showDeductionModal: false,
      currentDeductionEmployee: null,
      deductionInput: 0,
      showComputationModal: false,
      showLoanDeductionModal: false,
      currentLoanDeductionEmployee: null,
      loanDeductionInput: {
        amount: '',
        note: ''
      },
      showIndividualModal: false,
      currentIndividualEmployee: null,
      payrollTemplates: [],
      makeStateDraft: false,

    }
  },

  watch: {
    'form.pay_period_start': function(newVal) {
      if (newVal) {
        const startDate = new Date(newVal);
        const endDate = new Date(startDate);
        endDate.setDate(startDate.getDate() + 14);
        this.form.pay_period_end = endDate.toISOString().slice(0, 10);
      }
    }
  },

  mounted() {
    // this.fetchEmployees();
    this.fetchPayrollTemplates();

    const today = new Date()
    this.form.pay_period_start = today.toISOString().slice(0, 10);
    const endDate = new Date(today);
    endDate.setDate(today.getDate() + 14);
    this.form.pay_period_end = endDate.toISOString().slice(0, 10);

    const settings = this.dropdowns.payroll_settings;
    if (!settings) return 0;

    const hours_per_day_setting = settings.find(s => s.slug === 'hours-per-day');
    const overtime_rate_setting = settings.find(s => s.slug === 'overtime-rate');
    const incentive_rate_setting = settings.find(s => s.slug === 'incentive-rate');

    if (!hours_per_day_setting || !overtime_rate_setting || !incentive_rate_setting) return 0;

    this.hours_per_day = Math.round(Number(hours_per_day_setting.value));
    this.overtime_rate = Number(overtime_rate_setting.value);
    this.incentive_rate = Number(incentive_rate_setting.value);
  },

  methods: {
    hide() {
      this.showModal = false
      this.$emit('close');
    },

    openIndividualModal(employee) {
      this.currentIndividualEmployee = employee;
      this.showIndividualModal = true;
    },

    onSaveIndividualPayroll(data) {
      // Match by a stable employee identifier and avoid undefined-to-undefined matches.
      const targetId = data.value ?? data.id;
      const index = this.selectedEmployees.findIndex((e) => {
        const employeeId = e.value ?? e.id;
        return targetId !== undefined && employeeId === targetId;
      });
      if (index !== -1) {
        const nextLoans = Array.isArray(data.loans)
          ? data.loans
          : (Array.isArray(data.selected_loans) ? data.selected_loans : this.selectedEmployees[index].loans || []);

        this.selectedEmployees[index] = {
          ...this.selectedEmployees[index],
          basic_salary: data.basic_salary,
          total_days: data.total_days,
          earnings: data.earnings,
          deductions: data.deductions,
          total_earnings: data.total_earnings,
          total_deductions: data.total_deductions,
          net_salary: data.net_salary,
          loans: nextLoans,
        };
      }
      this.showIndividualModal = false;
      this.currentIndividualEmployee = null;
    },

    handleInput(field) {
      this.form.errors[field] = false
    },

    async fetchPayrollTemplates() {
      axios.get('/payroll-templates')
        .then(response => {
          if (response) {
            this.payrollTemplates = response.data.data;
            if(this.isEdit && this.payroll){
              this.form.payroll_template = this.payrollTemplates.find(t => t.id === this.payroll.payroll_template_id) || '';
              this.form.pay_period_start = this.payroll.pay_period_start.slice(0, 10);
              this.form.pay_period_end = this.payroll.pay_period_end.slice(0, 10);
              this.form.status = this.payroll.status.slug;
              this.selectedEmployees = this.payroll.payroll_items.map(item => {
                return {
                  value: item.employee_id,
                  fullname: item.employee_name,
                  basic_salary: item.basic_salary,
                  total_days: item.total_days,
                  earnings: item.earnings,
                  deductions: item.deductions,
                  total_earnings: item.total_earnings,
                  total_deductions: item.total_deductions,
                  net_salary: item.net_salary,
                  loans: item.loans || [],
                }
              })
            }
          }
        })
        .catch(err => console.log(err));
    },

    async fetchEmployees() {
      if (!this.form.payroll_template) return;
      this.selectedEmployees = this.form.payroll_template.employees.map(emp => ({
        ...emp,
        total_days: 0,
        basic_salary: emp.basic_salary || 0,
        total_days: emp.total_days || 0,
        earnings: Array.isArray(emp.earnings) ? emp.earnings : [],
        deductions: Array.isArray(emp.deductions) ? emp.deductions : [],
        total_earnings: emp.total_earnings || 0,
        total_deductions: emp.total_deductions || 0,
        tmp: this.calculateLoanDeduction(emp.loans),
        loans: Array.isArray(emp.loans) ? emp.loans : [],
      }));

      this.form.payroll_template.employees.forEach(emp => {
        this.employeeDetails[emp.value] = {
          total_days: emp.total_days || 0,
          basic_salary: emp.basic_salary || 0,
          earnings: Array.isArray(emp.earnings) ? emp.earnings : [],
          deductions: Array.isArray(emp.deductions) ? emp.deductions : [],
          total_earnings: emp.total_earnings || 0,
          total_deductions: emp.total_deductions || 0,
          tmp: this.calculateLoanDeduction(emp.loans),
          loans: Array.isArray(emp.loans) ? emp.loans : [],
        }
      });
    },

    openComputationModal() {
      this.showComputationModal = !this.showComputationModal
    },

    closeComputationModal() {
      this.showComputationModal = false
    },

    calculateTotalSalary() {
      return this.selectedEmployees.reduce((total, employee) => {
        const netSalary = Number(employee.net_salary);
        return total + (Number.isFinite(netSalary) ? netSalary : 0);
      }, 0);
    },

    formatCurrency(value) {
      // Handle undefined, null, empty string, or non-numeric values
      if (value === undefined || value === null || value === '' || isNaN(value)) {
        return '0.00';
      }
      return parseFloat(value).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    },

    async savePayroll() {
      if (!this.selectedEmployees.length) {
        alert('Select at least one employee')
        return
      }

      this.loading = true;
      const normalizeLoans = (employee) => {
        if (Array.isArray(employee.loans)) return employee.loans;
        if (Array.isArray(employee.selected_loans)) return employee.selected_loans;
        return [];
      };

      const payload = {
        pay_period_start: this.form.pay_period_start,
        pay_period_end: this.form.pay_period_end,
        payroll_template_id: this.form.payroll_template.id,
        items: this.selectedEmployees.map(e => {
          return {
            employee_id: e.value || e.id,
            basic_salary: e.basic_salary,
            total_days: e.total_days,
            earnings: e.earnings,
            deductions: e.deductions,
            total_earnings: e.total_earnings,
            total_deductions: e.total_deductions,
            net_salary: e.net_salary,
            loans: normalizeLoans(e),
            // Backward-compatible key to support legacy backend paths.
            selected_loans: normalizeLoans(e),
          };
        }),
        total_amount: parseFloat(this.calculateTotalSalary().toFixed(2)),
        status: this.makeStateDraft == true ? "draft" : "approval",
      }

      try {
        const response = this.isEdit
          ? await axios.put(`/payrolls/${this.payroll.id}`, payload)
          : await axios.post('/payrolls', payload)

        // Check if response has the alert structure
        if (response.data && response.data.info && response.data.message && response.data.status === 'error') {
          Swal.fire({
            title: response.data.message,
            text: response.data.info,
            icon: 'error',
            confirmButtonText: 'OK'
          });
        } else {
          Swal.fire({
            title: response.data.message,
            text: response.data.info,
            icon: 'success',
          });
          this.saveSuccess = true
          this.form.errors = {}
          this.$emit('saved');
          this.$emit('close');
          this.showModal = false;
        }
      } catch (error) {
        if (error.response && error.response.data.errors) {
          this.form.errors = error.response.data.errors
        }
      } finally {
        this.loading = false
      }
    },

    async saveDraft() {
      this.makeStateDraft = true;
      await this.savePayroll();
    },

    getPayrollPeriodDivisor() {
      if (!this.form.pay_period_start || !this.form.pay_period_end) return 2;

      const startDate = new Date(this.form.pay_period_start);
      const endDate = new Date(this.form.pay_period_end);
      if (isNaN(startDate) || isNaN(endDate) || endDate < startDate) return 2;

      const lastDayOfMonth = new Date(startDate.getFullYear(), startDate.getMonth() + 1, 0).getDate();
      const isWholeMonth =
        startDate.getDate() === 1 &&
        endDate.getDate() === lastDayOfMonth &&
        startDate.getMonth() === endDate.getMonth() &&
        startDate.getFullYear() === endDate.getFullYear();

      if (isWholeMonth) return 1;

      const msPerDay = 24 * 60 * 60 * 1000;
      const periodDays = Math.floor((endDate - startDate) / msPerDay) + 1;
      if (periodDays === 15) return 2;

      return 2;
    },

    calculateLoanDeduction(loans) {
      if (!loans || !loans.length) return 0;
      const divisor = this.getPayrollPeriodDivisor();
      
      const approvedLoans = loans.filter(loan => loan.status === 'approved');
      const total = approvedLoans.reduce((total, loan) => {
        // const interest = (loan.remaining_balance * (loan.interest_rate / 100)) / divisor;
        // const deduction = (loan.remaining_balance / loan.remaining_term_to_pay) + interest;
        const deduction = loan.remaining_balance / loan.remaining_term_to_pay;
        this.selectedEmployees.forEach(employee => {
          const employeeLoan = employee.loans.find(eLoan => eLoan.id === loan.id);
          if (employeeLoan) {
            employeeLoan.id = loan.id;
            employeeLoan.loan_no = loan.loan_no;
            employeeLoan.remaining_balance = loan.remaining_balance;
            employeeLoan.term_months = loan.term_months;
            employeeLoan.interest_rate = loan.interest_rate;
            employeeLoan.payroll_deduction = parseFloat(deduction.toFixed(2));
            employeeLoan.divisor = divisor;
          }
        });
        return total + (isNaN(deduction) ? 0 : deduction);
      }, 0);
      return parseFloat(total.toFixed(2));
    },
  }
}
</script>

<style scoped>
/* Modal Body */
.modal-body {
  max-height: calc(100vh - 240px);
  overflow-y: auto;
  overflow-x: hidden;
  padding-right: 0.5rem;
}

.payroll-form {
  display: contents;
}

.modal-body::-webkit-scrollbar {
  width: 6px;
}

.modal-body::-webkit-scrollbar-track {
  background: transparent;
}

.modal-body::-webkit-scrollbar-thumb {
  background: #d0d0d0;
  border-radius: 3px;
}

.modal-body::-webkit-scrollbar-thumb:hover {
  background: #a0a0a0;
}

/* Modal Actions - Fixed at Bottom */
.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  padding: 1.5rem;
  border-top: 1px solid #f0f0f0;
  background: white;
  flex-shrink: 0;
}

/* Form Sections */
.form-section {
  padding: 0;
}

.form-section:last-of-type {
  margin-bottom: 2rem;
}

.section-title {
  font-size: 1rem;
  font-weight: 700;
  color: #2c3e50;
  margin: 0 0 1.5rem 0;
  padding-bottom: 0.75rem;
  border-bottom: 2px solid #f0f0f0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.section-title i {
  color: #3D8D7A;
  font-size: 1.1rem;
}

/* Employees Container */
.employees-container {
  background: #fafbfc;
  border: 1px solid #e9ecef;
  border-radius: 8px;
  padding: 1.5rem;
}

.table {
  margin-bottom: 1.5rem;
}

.table thead th {
  background: white;
  font-weight: 600;
  color: #3D8D7A;
  font-size: 0.85rem;
  padding: 1rem 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  border-bottom: 2px solid #e9ecef;
  border-top: none;
  white-space: nowrap;
}

.table thead th:nth-child(1) {
  width: 20%;
  min-width: 120px;
}

.table thead th:nth-child(2),
.table thead th:nth-child(3),
.table thead th:nth-child(4),
.table thead th:nth-child(5),
.table thead th:nth-child(6),
.table thead th:nth-child(7) {
  width: 18%;
  min-width: 90px;
}

.table tbody tr {
  border-bottom: 1px solid #f0f0f0;
  transition: background-color 0.2s ease;
}

.table tbody tr:not(.total-row):hover {
  background: white;
}

.table tbody td {
  padding: 0.9rem 0.75rem;
  color: #2c3e50;
  font-size: 0.9rem;
  vertical-align: middle;
}

.employee-name {
  font-weight: 600;
  color: #2c3e50;
}

.salary-cell {
  color: #3D8D7A;
  font-weight: 500;
  white-space: nowrap;
}

.incentive-cell {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.incentive-value {
  color: #3D8D7A;
  font-weight: 500;
  flex: 1;
}

.net-salary-cell {
  color: #27ae60;
  font-weight: 700;
  white-space: nowrap;
}

.form-control-sm {
  padding: 0.5rem 0.6rem;
  font-size: 0.85rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  transition: all 0.2s ease;
}

.form-control-sm:focus {
  outline: none;
  border-color: #3D8D7A;
  box-shadow: 0 0 0 3px rgba(61, 141, 122, 0.1);
}

/* Formula Section */
.formula-section {
  padding-top: 1.5rem;
  margin-top: 1rem;
}

.formula-toggle {
  color: #3D8D7A;
  font-weight: 600;
  font-size: 0.85rem;
  padding: 0;
  margin: 0;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  cursor: pointer;
  transition: all 0.2s ease;
}

.formula-toggle:hover {
  color: #2d6d5e;
  text-decoration: none;
}

.formula-toggle i {
  font-size: 1rem;
  transition: transform 0.2s ease;
}

.computation-box {
  margin-top: 1rem;
  background: white;
  border: 1px solid #e9ecef;
  border-radius: 8px;
  padding: 1.25rem;
  display: grid;
  gap: 1rem;
}

.formula-item {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.formula-item label {
  font-size: 0.85rem;
  font-weight: 600;
  color: #3D8D7A;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.formula-item code {
  font-family: 'Courier New', monospace;
  font-size: 0.8rem;
  background: #f5f5f5;
  padding: 0.6rem 0.8rem;
  border-radius: 6px;
  color: #2c3e50;
  word-break: break-word;
  border-left: 3px solid #3D8D7A;
}

/* Transitions */
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter, .fade-leave-to {
  opacity: 0;
}

/* Button Styling */
.btn-outline-primary {
  padding: 0.4rem 0.6rem;
  font-size: 0.75rem;
  border: 1px solid #3D8D7A;
  color: #3D8D7A;
  transition: all 0.2s ease;
}

.btn-outline-primary:hover {
  background: #f0f7f6;
  border-color: #2d6d5e;
  color: #2d6d5e;
}

.btn-draft {
  background: #f5f5f5;
  color: #666;
  border: 1px solid #d1d5db;
  padding: 0.6rem 1rem;
  border-radius: 6px;
  font-size: 0.9rem;
  font-weight: 500;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  transition: all 0.2s ease;
}

.btn-draft:hover:not(:disabled) {
  background: #e8e8e8;
  border-color: #aaa;
  color: #333;
}

.btn-draft:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Total Row */
.total-row {
  background: linear-gradient(135deg, #f0f7f6 0%, #e8f4f2 100%);
  font-weight: 700;
  border-top: 2px solid #3D8D7A;
  border-bottom: 2px solid #3D8D7A;
}

.total-row td {
  padding: 1rem 0.75rem !important;
  color: #2d9b8c;
}

.total-label {
  text-align: right;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  font-size: 0.9rem;
}

.total-amount {
  color: #27ae60;
  font-size: 1.1rem;
  white-space: nowrap;
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
  position: absolute;
  bottom: 100%;
  left: 50%;
  transform: translateX(-50%);
  background: #fffce3;
  border: 1px solid #e9ecef;
  border-radius: 8px;
  padding: 1rem;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  z-index: 1000;
  min-width: 250px;
  max-width: 350px;
  font-size: 0.85rem;
  transition: opacity 0.3s ease, visibility 0.3s ease;
}

.loan-container:hover .loan-tooltip {
  visibility: visible;
  opacity: 1;
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

/* Responsive */
@media (max-width: 768px) {
  .employees-container {
    padding: 1rem;
  }

  .table {
    font-size: 0.8rem;
  }

  .table thead th {
    padding: 0.75rem 0.5rem;
  }

  .table tbody td {
    padding: 0.75rem 0.5rem;
  }

  .formula-item {
    gap: 0.25rem;
  }

  .formula-item code {
    font-size: 0.75rem;
  }

  .loan-tooltip {
    min-width: 200px;
    max-width: 280px;
    font-size: 0.8rem;
  }
}
</style>
