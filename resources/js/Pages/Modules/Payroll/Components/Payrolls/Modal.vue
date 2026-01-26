<template>
  <!-- Payroll Modal -->
  <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5)">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">

        <div class="modal-header">
          <h5 class="modal-title">{{ isEdit ? 'Edit' : 'Create' }} Payroll</h5>
          <button class="btn-close" @click="$emit('close')" />
        </div>

        <form @submit.prevent="savePayroll">
          <div class="modal-body">
            <div class="row">
              <!-- Pay Period -->
              <div class="col-md-3 mb-3">
                <label class="form-label">Pay Period Start *</label>
                <input type="date" v-model="form.pay_period_start" class="form-control" required>
              </div>

              <div class="col-md-3 mb-3">
                <label class="form-label">Pay Period End *</label>
                <input type="date" v-model="form.pay_period_end" class="form-control" required>
              </div>

              <div class="col-md-3 mb-3">
                <label class="form-label">Pay Period End *</label>
                <input type="date" v-model="form.pay_period_end" class="form-control" required>
              </div>

              <!-- Selected Employees -->
              <div class="col-12">
                <label class="form-label">Employees *</label>
                <div class="border rounded p-3">
                <table class="table table-sm">
                  <thead>
                    <tr>
                      <th>Employee</th>
                      <th>Daily Salary Rate</th>
                      <th>Total Days</th>
                      <th>Deductions</th>
                      <th>OT Hrs</th>
                      <th>OT Rate</th>
                      <th>Incentives</th>
                      <th>Net Salary</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(employee, index) in selectedEmployees" :key="employee.value">
                      <td>{{ employee.name }}</td>
                      <td>
                        <input
                          type="number"
                          v-model="employeeDetails[employee.value].basic_salary"
                          @input="updateOvertimeRate(employee.value)"
                          class="form-control form-control-sm"
                          min="0"
                          step="0.01"
                        >
                      </td>
                      <td>
                        <input
                          type="number"
                          v-model="employeeDetails[employee.value].total_days"
                          class="form-control form-control-sm"
                          min="0"
                          step="1"
                        >
                      </td>
                      <td>
                        <input
                          type="number"
                          v-model="employeeDetails[employee.value].deductions"
                          class="form-control form-control-sm"
                          min="0"
                          step="0.01"
                        >
                      </td>
                      <td>
                        <input
                          type="number"
                          v-model="employeeDetails[employee.value].overtime_hours"
                          class="form-control form-control-sm"
                          min="0"
                          step="0.5"
                        >
                      </td>
                      <td>
                        <input
                          type="number"
                          v-model="employeeDetails[employee.value].overtime_rate"
                          class="form-control form-control-sm"
                          min="0"
                          step="0.01"
                          readonly
                        >
                      </td>
                      <td>
                        <input
                          type="number"
                          v-model="employeeDetails[employee.value].incentives"
                          class="form-control form-control-sm"
                          min="0"
                          step="0.01"
                        >
                      </td>
                      <td>{{ calculateEmployeeNet(employee) }}</td>
                      <td>
                        <button type="button" class="btn btn-sm btn-danger" @click="removeEmployee(index)">
                          <i class="ri-close-line"></i>
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
                </div>

                <button
                  type="button"
                  class="btn btn-sm btn-outline-primary mt-2"
                  @click="showEmployeeSelector = true"
                >
                  + Add Employee
                </button>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="$emit('close')">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="loading">
              {{ isEdit ? 'Update' : 'Create' }} Payroll
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
</template>

<script>
import axios from 'axios'
import EmployeeSelector from './EmployeeSelector.vue'

export default {
  components: {
    EmployeeSelector
  },

  props: {
    payroll: Object,
    isEdit: Boolean,
    employees: Array
  },

  data() {
    return {
      form: {
        pay_period_start: '',
        pay_period_end: ''
      },
      selectedEmployees: [],
      selectedEmployeeIds: [],
      employeeDetails: {},
      showEmployeeSelector: false,
      loading: false
    }
  },

  mounted() {
    this.fetchEmployees()

    const today = new Date()
    this.form.pay_period_start = today.toISOString().slice(0, 10)
    this.form.pay_period_end = today.toISOString().slice(0, 10)
  },

  methods: {
    async fetchEmployees() {
      console.log(this.employees);
      
      this.employees.forEach(emp => {
        this.employeeDetails[emp.value] = {
          total_days: 0,
          basic_salary: emp.basic_salary || 0,
          overtime_hours: 0,
          overtime_rate: (emp.basic_salary || 0) / 8,
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

    removeEmployee(index) {
      this.selectedEmployees.splice(index, 1)
    },

    updateOvertimeRate(id) {
      this.employeeDetails[id].overtime_rate = this.employeeDetails[id].basic_salary / 8
    },

    calculateEmployeeNet(employee) {
      const details = this.employeeDetails[employee.value];
      const ot = details.overtime_hours * details.overtime_rate;
      return (details.basic_salary * details.total_days) + ot + details.incentives - details.deductions;
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
        items: this.selectedEmployees.map(e => {
          const details = this.employeeDetails[e.value];
          return {
            employee_id: e.id,
            basic_salary: details.basic_salary,
            overtime_hours: details.overtime_hours,
            overtime_rate: details.overtime_rate,
            total_days: details.total_days,
            deductions: details.deductions,
            net_salary: this.calculateEmployeeNet(e)
          };
        })
      }

      try {
        this.isEdit
          ? await axios.put(`/api/payrolls/${this.payroll.id}`, payload)
          : await axios.post('/api/payrolls', payload)

        this.$emit('saved')
      } finally {
        this.loading = false
      }
    }
  }
}
</script>
