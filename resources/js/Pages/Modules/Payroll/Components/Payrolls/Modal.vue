<template>
  <div
    v-if="showModal"
    class="modal-overlay"
    :class="{ active: showModal }"
    @click.self="hide"
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
        <form @submit.prevent="savePayroll">
          <div class="row">
            <div class="col-md-6">
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label class="form-label">Period Start *</label>
                    <div class="input-wrapper">
                      <i class="ri-calendar-line input-icon"></i>
                      <input type="date" v-model="form.pay_period_start" class="form-control" :class="{ 'input-error': form.errors.pay_period_start }" @input="handleInput('pay_period_start')" required>
                    </div>
                    <span class="error-message" v-if="form.errors.pay_period_start">{{ form.errors.pay_period_start }}</span>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label class="form-label">Period End *</label>
                    <div class="input-wrapper">
                      <i class="ri-calendar-line input-icon"></i>
                      <input type="date" v-model="form.pay_period_end" class="form-control" :class="{ 'input-error': form.errors.pay_period_end }" @input="handleInput('pay_period_end')" required>
                    </div>
                    <span class="error-message" v-if="form.errors.pay_period_end">{{ form.errors.pay_period_end }}</span>
                  </div>
                </div>
              </div>              
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Payroll Template *</label>
                <div class="input-wrapper">
                  <select v-model="form.payroll_template" class="form-control" :class="{ 'input-error': form.errors.payroll_template }" @change="handleInput('payroll_template'); fetchEmployees()" required>
                    <option value="">Select Payroll Template</option>
                    <option v-for="template in dropdowns.payroll_templates" :key="template.value" :value="template">{{ template.name }}</option>
                  </select>
                </div>
                <span class="error-message" v-if="form.errors.payroll_template">{{ form.errors.payroll_template }}</span>
              </div>
            </div>
          </div>

          <!-- Selected Employees -->
          <div class="form-group">
            <label class="form-label">Employees *</label>
            <div class="border rounded p-3">
              <table class="table table-sm">
                <thead>
                  <tr>
                    <th>Employee</th>
                    <th>Daily Salary Rate</th>
                    <th>Total Days</th>
                    <th>OT Hrs *</th>
                    <th>Incentives **</th>
                    <th>Deductions</th>
                    <th>Net Salary</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(employee, index) in selectedEmployees" :key="employee.value">
                    <td>{{ employee.name }}</td>
                    <td>{{ employee.basic_salary }}</td>
                    <td>
                      <input
                        type="number"
                        v-model="employee.total_days"
                        class="form-control form-control-sm"
                        min="0"
                        step="1"
                      >
                    </td>
                    <td>
                      <input
                        type="number"
                        v-model="employee.overtime_hours"
                        class="form-control form-control-sm"
                        min="0"
                        step="0.5"
                      >
                    </td>                    
                    <td>
                      <div style="display:flex;align-items:center;gap:6px">
                        <span>{{ employee.incentives }}</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" @click="openIncentiveModal(employee)">+</button>
                      </div>
                    </td>
                    <td>
                      <input
                        type="number"
                        v-model="employee.deductions"
                        class="form-control form-control-sm"
                        min="0"
                        step="0.01"
                      >
                    </td>
                    <td>{{ (calculateEmployeeNet(employee) ?? 0).toFixed(2) }}</td>
                  </tr>
                </tbody>
              </table>
              <br>
              <a class="btn btn-sm btn-link" @click="openComputationModal">{{ showComputationModal ? 'Hide' : 'Show' }} Formula</a>
              <div v-if="showComputationModal" class="computation-box border rounded p-3 mt-2">
                <p>* ((Daily Salary Rate / {{ hours_per_day }}) * {{ overtime_rate }}) * OT Hours</p>
                <p>** ((Product Packaging (kg) * Sold) / 25) * {{ incentive_rate }}</p>
              </div>
            </div>
          </div>

          <div class="form-actions">
            <button type="button" class="btn btn-cancel" @click="hide">
              <i class="ri-close-line"></i>
              Cancel
            </button>
            <button type="submit" class="btn btn-save" :disabled="loading">
              <i class="ri-save-line" v-if="!loading"></i>
              <i class="ri-loader-4-line spinner" v-else></i>
              {{ loading ? 'Saving...' : (isEdit ? 'Update' : 'Create') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <EmployeeSelector
    :show="showEmployeeSelector"
    :employees="employees"
    :selectedEmployeeIds.sync="selectedEmployeeIds"
    :selectedEmployees="selectedEmployees"
    @close="showEmployeeSelector = false"
    @add="handleAdd"
  />
  <IncentiveModal
    :show="showIncentiveModal"
    :employee="currentIncentiveEmployee"
    :incentive="incentiveInput"
    @close="closeIncentiveModal"
    @save="onSaveIncentive"
  />
</template>

<script>
import axios from 'axios'
import EmployeeSelector from './EmployeeSelector.vue'
import IncentiveModal from './IncentiveModal.vue'

export default {
  components: {
    EmployeeSelector,
    IncentiveModal
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
        errors: {}
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
      showComputationModal: false,
    }
  },

  mounted() {
    this.fetchEmployees()

    const today = new Date()
    this.form.pay_period_start = today.toISOString().slice(0, 10);
    this.form.pay_period_end = today.toISOString().slice(0, 10);

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
      this.$emit('close')
    },

    handleInput(field) {
      this.form.errors[field] = false
    },

    async fetchEmployees() {
      if (!this.form.payroll_template) return;
      this.selectedEmployees = this.form.payroll_template.employees.map(emp => ({
        ...emp,
        total_days: 0,
        basic_salary: emp.basic_salary || 0,
        overtime_hours: 0,
        overtime_rate: (emp.basic_salary || 0) / 8,
        deductions: 0,
        incentives: 0,
        has_overtime: false
      }));

      this.form.payroll_template.employees.forEach(emp => {
        this.employeeDetails[emp.value] = {
          total_days: 0,
          basic_salary: emp.basic_salary || 0,
          overtime_hours: 0,
          deductions: 0,
          incentives: 0,
          has_overtime: false
        }
      });
    },

    handleAdd(ids) {
      this.selectedEmployeeIds = ids
      this.addSelectedEmployees()
    },

    addSelectedEmployees() {
      for (const id of this.selectedEmployeeIds) {
        const emp = this.employees.find(e => e.value === id)
        const d = this.employeeDetails[id]

        if (!this.selectedEmployees.find(e => e.value === id)) {
          this.selectedEmployees.push({ ...emp, ...d })
        }
      }

      this.selectedEmployeeIds = []
      this.showEmployeeSelector = false
    },

    removeEmployee(employee) {
      const idx = this.selectedEmployees.findIndex(e => e.value === employee.value)
      if (idx !== -1) this.selectedEmployees.splice(idx, 1)
    },

    openIncentiveModal(employee) {
      this.currentIncentiveEmployee = employee
      this.incentiveInput = Number(employee.incentives || 0)
      this.showIncentiveModal = true
    },

    closeIncentiveModal() {
      this.showIncentiveModal = false
      this.currentIncentiveEmployee = null
      this.incentiveInput = 0
    },

    saveIncentive() {
      if (!this.currentIncentiveEmployee) return
      this.currentIncentiveEmployee.incentives = Number(this.incentiveInput || 0)
      this.closeIncentiveModal()
    },

    onSaveIncentive(value) {
      if (!this.currentIncentiveEmployee) return
      this.currentIncentiveEmployee.incentives = Number(value || 0)
      this.closeIncentiveModal()
    },

    openComputationModal() {
      this.showComputationModal = !this.showComputationModal
    },

    closeComputationModal() {
      this.showComputationModal = false
    },

    calculateEmployeeNet(employee) {
      const details = employee;
      const ot = ((details.basic_salary / this.hours_per_day) * this.overtime_rate) * (details.overtime_hours || 0);
      return (details.basic_salary * (details.total_days || 0)) + ot + (details.incentives || 0) - (details.deductions || 0);
    },

    async savePayroll() {
      if (!this.selectedEmployees.length) {
        alert('Select at least one employee')
        return
      }

      this.loading = true

      const payload = {
        pay_period_start: this.form.pay_period_start,
        pay_period_end: this.form.pay_period_end,
        payroll_template: this.form.payroll_template,
        items: this.selectedEmployees.map(e => {
          return {
            employee_id: e.value || e.id,
            basic_salary: e.basic_salary,
            overtime_hours: e.overtime_hours,
            overtime_rate: e.overtime_rate,
            total_days: e.total_days,
            deductions: e.deductions,
            incentives: e.incentives || 0,
            net_salary: parseFloat(this.calculateEmployeeNet(e).toFixed(2))
          };
        })
      }

      try {
        this.isEdit
          ? await axios.put(`/api/payrolls/${this.payroll.id}`, payload)
          : await axios.post('/api/payrolls', payload)

        this.saveSuccess = true
        this.form.errors = {}
        setTimeout(() => {
          this.hide()
        }, 1500)
        this.$emit('saved')
      } catch (error) {
        if (error.response && error.response.data.errors) {
          this.form.errors = error.response.data.errors
        }
      } finally {
        this.loading = false
      }
    }
  }
}
</script>
