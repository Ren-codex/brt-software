<template>
  <div v-if="show" class="modal-overlay active" @click.self="close">
    <div class="modal-container modal-xl" @click.stop>
      
      <!-- Header -->
      <!-- <div class="modal-header">
        <h3>Edit Employee Payroll</h3>
        <button class="close-btn" @click="close">
          <i class="ri-close-line"></i>
        </button>
      </div> -->

      <div class="modal-body payroll-body">
        
        <!-- Employee Name -->
        <div class="employee-title">
          {{ employee?.fullname }} ({{ employee.position?.title }})
        </div>

        <div class="payroll-grid">

          <!-- LEFT SIDE -->
          <div class="left-panel">

            <!-- Earnings -->
            <div class="panel-box">
              <div class="panel-header">
                <span>Earning</span>
                <button class="btn-mini" @click="addEarning">Add</button>
              </div>

              <table class="payroll-table">
                <thead>
                  <tr>
                    <th>Account Description</th>
                    <th width="120">Amount</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                <tr v-for="(item, i) in form.earnings" :key="i">
                    <td>{{ item.description }}</td>
                    <td class="text-right">₱ {{ formatNumber(item.amount) }}</td>
                    <td class="text-center">
                      <button class="btn-action btn-edit" @click="editEarning(i)">
                        <i class="ri-edit-line"></i>
                      </button>
                      <button class="btn-action btn-delete" @click="removeEarning(i)">
                        <i class="ri-delete-bin-line"></i>
                      </button>
                    </td>
                  </tr>
                  <tr v-if="!form.earnings.length">
                    <td colspan="3" class="empty-row">No earnings added</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Deduction -->
            <div class="panel-box">
              <div class="panel-header">
                <span>Deduction</span>
                <button class="btn-mini" @click="addDeduction">Add</button>
              </div>

              <table class="payroll-table">
                <thead>
                  <tr>
                    <th>Account Description</th>
                    <th width="120">Amount</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, i) in form.deductions" :key="i">
                    <td>{{ item.description }}</td>
                    <td class="text-right text-danger">
                      ₱ {{ formatNumber(item.amount) }}
                    </td>
                    <td class="text-center">
                      <!-- <button v-if="!isLoanDeduction(item)" class="btn-action btn-edit" @click="editDeduction(i)">
                        <i class="ri-edit-line"></i>
                      </button> -->
                      <button class="btn-action btn-delete" @click="removeDeduction(i)">
                        <i class="ri-delete-bin-line"></i>
                      </button>
                    </td>
                  </tr>
                  <tr v-if="!form.deductions.length">
                    <td colspan="3" class="empty-row">No deductions added</td>
                  </tr>
                </tbody>
              </table>
            </div>

          </div>

          <!-- RIGHT SIDE -->
          <div class="right-panel">

            <!-- Basic Pay -->
            <div class="summary-box">
              <div class="summary-title">Basic Pay</div>

              <div class="summary-row">
                <span>Rate per day</span>
                <input type="number" v-model="form.basic_salary" class="summary-input" style="width: 163px;"/>
              </div>

              <div class="summary-row">
                <span>Working days</span>
                <div class="stepper-input">
                  <button type="button" class="stepper-btn" @click="decrementDays">-</button>
                  <input type="number" v-model.number="form.total_days" class="summary-input" />
                  <button type="button" class="stepper-btn" @click="incrementDays">+</button>
                </div>
              </div>

              <div class="summary-row total">
                <span>Total Pay</span>
                <span>₱ {{ formatNumber(totalPay) }}</span>
              </div>
            </div>

            <!-- Net Pay -->
            <div class="summary-box">
              <div class="summary-title">Summary</div>

              <div class="summary-row">
                <span>Total Earning</span>
                <span>₱ {{ formatNumber(totalEarnings) }}</span>
              </div>

              <div class="summary-row">
                <span>Total Deduction</span>
                <span>₱ {{ formatNumber(totalDeductions) }}</span>
              </div>

              <div class="summary-row total highlight">
                <span>Net Pay</span>
                <span style="font-size: 1.5rem">₱ {{ formatNumber(netPay) }}</span>
              </div>
            </div>

          </div>

        </div>

        <!-- Footer Buttons -->
        <div class="form-actions">
          <button class="btn btn-cancel" @click="close">
            Cancel
          </button>
          <button class="btn btn-save" @click="save">
            Save
          </button>
        </div>

      </div>
    </div>

    <!-- Earning Modal -->
    <EarningModal
      :show="showEarningModal"
      :employee="employee"
      :isEdit="isEarningEdit"
      :editIndex="editingEarningIndex"
      :description="editingEarning ? editingEarning.description : ''"
      :amount="editingEarning ? editingEarning.amount : 0"
      :existingEarnings="form.earnings"
      @close="showEarningModal = false"
      @save="handleEarningSave"
    />

    <!-- Deduction Modal -->
    <DeductionModal
      :show="showDeductionModal"
      :employee="employee"
      :isEdit="isDeductionEdit"
      :editIndex="editingDeductionIndex"
      :description="editingDeduction ? editingDeduction.description : ''"
      :amount="editingDeduction ? editingDeduction.amount : 0"
      :existingDeductions="form.deductions"
      @close="showDeductionModal = false"
      @save="handleDeductionSave"
    />
  </div>
</template>

<script>
import EarningModal from './EarningModal.vue'
import DeductionModal from './DeductionModal.vue'

export default {
  components: {
    EarningModal,
    DeductionModal
  },
  props: {
    show: Boolean,
    employee: Object
  },
  data() {
    return {
      form: {
        basic_salary: 0,
        earnings: [],
        deductions: [],
        total_days: 0, 
        selected_loans: [],
      },
      showEarningModal: false,
      isEarningEdit: false,
      editingEarningIndex: -1,
      editingEarning: null,
      showDeductionModal: false,
      isDeductionEdit: false,
      editingDeductionIndex: -1,
      editingDeduction: null,
    }
  },
  mounted() {
    this.setFormFromEmployee();
  },
  watch: {
    employee: {
      handler() {
        this.setFormFromEmployee();
      },
      immediate: true,
      deep: true
    }
  },
  computed: {
    totalPay() {
      return this.form.basic_salary * this.form.total_days
    },
    totalEarnings() {
      return this.totalPay + this.form.earnings.reduce((t, e) => t + e.amount, 0)
    },
    totalDeductions() {
      return this.form.deductions.reduce((t, d) => t + d.amount, 0)
    },
    netPay() {
      return this.totalEarnings - this.totalDeductions
    }
  },
  methods: {
    getEmptyForm() {
      return {
        basic_salary: 0,
        earnings: [],
        deductions: [],
        total_days: 0,
        selected_loans: [],
      }
    },
    resetForm() {
      this.form = this.getEmptyForm()
      this.showEarningModal = false
      this.isEarningEdit = false
      this.editingEarningIndex = -1
      this.editingEarning = null
      this.showDeductionModal = false
      this.isDeductionEdit = false
      this.editingDeductionIndex = -1
      this.editingDeduction = null
    },
    setFormFromEmployee() {
      if (this.employee) {
        this.form.basic_salary = this.employee.basic_salary || 0;
        this.form.total_days = this.employee.total_days || 11;
        this.form.earnings = (this.employee.earnings || []).map(item => ({ ...item }));
        this.form.deductions = (this.employee.deductions || []).map(item => ({ ...item }));
        this.form.selected_loans = (this.employee.selected_loans || []).map(loan => ({ ...loan }));
      } else {
        this.resetForm()
      }
    },
    close() {
      this.$emit('close')
    },
    addEarning() {
      this.isEarningEdit = false
      this.editingEarningIndex = -1
      this.editingEarning = null
      this.showEarningModal = true
    },
    editEarning(index) {
      this.isEarningEdit = true
      this.editingEarningIndex = index
      this.editingEarning = this.form.earnings[index]
      this.showEarningModal = true
    },
    removeEarning(index) {
      this.form.earnings.splice(index, 1)
    },
    handleEarningSave(earning) {
      if (earning.isEdit) {
        this.form.earnings[earning.index] = {
          description: earning.description,
          amount: earning.amount
        }
      } else {
        this.form.earnings.push({
          description: earning.description,
          amount: earning.amount
        })
      }
      this.showEarningModal = false
      this.isEarningEdit = false
      this.editingEarningIndex = -1
      this.editingEarning = null
    },
    addDeduction() {
      this.isDeductionEdit = false
      this.editingDeductionIndex = -1
      this.editingDeduction = null
      this.showDeductionModal = true
    },
    editDeduction(index) {
      this.isDeductionEdit = true
      this.editingDeductionIndex = index
      this.editingDeduction = this.form.deductions[index]
      this.showDeductionModal = true
    },
    removeDeduction(index) {
      this.form.deductions.splice(index, 1)
    },
    handleDeductionSave(deduction) {
      if (deduction.isEdit) {
        // Update existing deduction (edit mode)
        this.form.deductions[deduction.index] = {
          description: deduction.description,
          amount: deduction.amount
        }
      } else {
        // Add each selected loan as an individual deduction item
        if (deduction.selectedLoans && deduction.selectedLoans.length) {
          this.form.selected_loans = deduction.selectedLoans;
          deduction.selectedLoans.forEach(loan => {
            this.form.deductions.push({
              description: `${loan.loan_no}`,
              amount: loan.deduction
            })
          })
        }
        // Add manual deduction if any
        if (deduction.deduction && deduction.deduction > 0) {
          this.form.deductions.push({
            description: deduction.deductionLabel || 'Manual Deduction',
            amount: deduction.deduction
          })
        }
      }
      this.showDeductionModal = false
      this.isDeductionEdit = false
      this.editingDeductionIndex = -1
      this.editingDeduction = null
    },
    incrementDays() {
      this.form.total_days++
    },
    decrementDays() {
      if (this.form.total_days > 0) {
        this.form.total_days--
      }
    },
    otherBenefit() {
      this.$emit('other-benefit')
    },
    isLoanDeduction(item) {
      // Check if the deduction is a loan deduction (starts with "LN")
      return item.description && item.description.startsWith('LN')
    },
    formatNumber(value) {
      return value.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')
    },
    save() {
      // Emit the save event with updated employee data
      const payload = {
        ...this.employee,
        basic_salary: this.form.basic_salary,
        total_days: this.form.total_days,
        earnings: this.form.earnings.map(item => ({ ...item })),
        deductions: this.form.deductions.map(item => ({ ...item })),
        total_earnings: this.totalEarnings,
        total_deductions: this.totalDeductions,
        net_salary: this.netPay,
        selected_loans: this.form.selected_loans.map(loan => ({ ...loan })),
      }

      this.$emit('save', payload)
      this.resetForm()

      // Close after form is reset so the next employee starts fresh
      this.close()
    }
  }
}
</script>

<style>
.modal-header h3 {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 600;
  color: white;
}

.modal-lg {
  width: 95%;
  max-width: 1000px;
}

.payroll-header {
  background: linear-gradient(90deg, #3D8D7A, #2d6d5e);
  color: white;
}

.employee-title {
  font-weight: 700;
  font-size: 1.1rem;
  margin-bottom: 1rem;
  color: #2c3e50;
}

.period-row {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1rem;
}

.payroll-grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 1rem;
}

.panel-box {
  border: 1px solid #d1d5db;
  border-radius: 8px;
  margin-bottom: 1rem;
  overflow: hidden;
}

.panel-header {
  display: flex;
  justify-content: space-between;
  padding: 0.6rem 1rem;
  background: #f8f9fa;
  font-weight: 600;
}

.payroll-table {
  width: 100%;
  border-collapse: collapse;
}

.payroll-table th,
.payroll-table td {
  padding: 0.6rem;
  border-top: 1px solid #eee;
  font-size: 0.85rem;
}

.text-right {
  text-align: right;
}

.text-danger {
  color: #dc3545;
}

.empty-row {
  text-align: center;
  color: #999;
  padding: 1rem;
}

.summary-box {
  border: 1px solid #3D8D7A;
  border-radius: 8px;
  padding: 1rem;
  margin-bottom: 1rem;
  background: #f4fbf9;
}

.summary-title {
  font-weight: 700;
  margin-bottom: 0.75rem;
  color: #3D8D7A;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 0.5rem;
  font-size: 0.9rem;
}

.summary-row.total {
  border-top: 1px solid #3D8D7A;
  padding-top: 0.5rem;
  font-weight: 600;
}

.summary-row.highlight {
  font-size: 1rem;
  font-weight: 700;
  color: #2d6d5e;
}

.summary-input {
  width: 100px;
  padding: 0.3rem;
  border: 1px solid #ccc;
  border-radius: 4px;
  text-align: right;
}

.stepper-input {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.stepper-btn {
  width: 28px;
  height: 28px;
  padding: 0;
  border: 1px solid #ccc;
  border-radius: 4px;
  background: #f8f9fa;
  color: #3D8D7A;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.stepper-btn:hover {
  background: #3D8D7A;
  color: white;
  border-color: #3D8D7A;
}

.stepper-btn:active {
  background: #2d6d5e;
  border-color: #2d6d5e;
}

.btn-mini {
  font-size: 0.75rem;
  padding: 0.3rem 0.6rem;
  border-radius: 4px;
  background: #3D8D7A;
  color: white;
  border: none;
  cursor: pointer;
}

.btn-mini:hover {
  background: #2d6d5e;
}

.btn-action {
  background: none;
  border: none;
  cursor: pointer;
  padding: 0.25rem 0.5rem;
  font-size: 1rem;
  transition: all 0.2s ease;
}

.btn-edit {
  color: #3D8D7A;
}

.btn-edit:hover {
  color: #2d6d5e;
}

.btn-delete {
  color: #dc3545;
}

.btn-delete:hover {
  color: #c82333;
}

.text-center {
  text-align: center;
}
</style>
