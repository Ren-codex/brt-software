<template>
  <div v-if="show" class="modal-overlay" :class="{ active: show }" @click.self="close">
    <div class="modal-container modal-sm" @click.stop>
      <div class="modal-header">
        <h3>{{ isEdit ? 'Edit Deduction' : 'Add Loan Deduction' }}</h3>
        <button class="close-btn" @click="close">
          <i class="ri-close-line"></i>
        </button>
      </div>
      <div class="modal-body">
        <div class="existing-loans" v-if="!isEdit && employee && availableLoans && availableLoans.length">
          <label class="form-label">Existing Loan Deductions</label>
          <div class="loan-list">
            <div v-for="loan in availableLoans" :key="loan.id" class="loan-item">
              <div class="loan-checkbox">
                <input 
                  type="checkbox" 
                  :id="'loan-' + loan.id" 
                  v-model="selectedLoans"
                  :value="loan.id"
                  class="loan-checkbox-input"
                />
              </div>
              <div class="loan-info">
                <label :for="'loan-' + loan.id" class="loan-label">
                  <span class="loan-id">Loan #{{ loan.id }}</span>
                  <span class="loan-balance">Balance: ₱ {{ parseFloat(loan.remaining_balance).toFixed(2) }}</span>
                </label>
              </div>
              <div class="loan-deduction">
                ₱ {{ (loan.payroll_deduction || 0).toFixed(2) }}
              </div>
            </div>
          </div>
          <div class="total-existing">
            <span>Total Auto-computed:</span>
            <span class="total-amount">₱ {{ totalExistingDeductions.toFixed(2) }}</span>
          </div>
        </div>

        <div class="form-group" v-if="!isEdit">
          <label class="form-label">Additional Manual Deduction</label>
          <input 
            type="text" 
            v-model="localDeductionLabel" 
            class="form-control" 
            :class="{ 'is-invalid': existingManualDeduction }"
            placeholder="Enter description (e.g. SSS, PhilHealth, etc.)">
            <div v-if="existingManualDeduction" class="invalid-feedback">
              Already exists
            </div>
            <br>
          <div class="input-wrapper mb-2">
            <i class="ri-cash-line input-icon"></i>
            <input 
              type="number" 
              v-model.number="localDeduction" 
              class="form-control" 
              min="0" 
              step="0.01"
              placeholder="0.00"
              @keyup.enter="save">
          </div>
        </div>

        <div class="form-summary" v-if="!isEdit && employee && availableLoans && availableLoans.length">
          <div class="summary-row">
            <span>Auto-computed Deductions:</span>
            <span>₱ {{ totalExistingDeductions.toFixed(2) }}</span>
          </div>
          <div class="summary-row">
            <span>Manual Deduction:</span>
            <span>₱ {{ (localDeduction || 0).toFixed(2) }}</span>
          </div>
          <div class="summary-row total">
            <span>Total Deductions:</span>
            <span>₱ {{ totalDeductions.toFixed(2) }}</span>
          </div>
        </div>

        <div class="form-actions">
          <button type="button" class="btn btn-cancel" @click="close">Cancel</button>
          <button type="button" class="btn btn-save" @click="save" :disabled="existingManualDeduction">Save</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
props: {
    show: { type: Boolean, default: false },
    employee: { type: Object, default: null },
    isEdit: { type: Boolean, default: false },
    editIndex: { type: Number, default: -1 },
    description: { type: String, default: '' },
    amount: { type: [Number, String], default: 0 },
    deduction: { type: [Number, String], default: 0 },
    existingDeductions: { type: Array, default: () => [] },
  },
data() {
    return {
      localDeduction: Number(this.deduction || 0),
      localDeductionLabel: '',
      selectedLoans: [],
    }
  },
watch: {
    show(val) {
      if (val) {
        if (this.isEdit) {
          // Edit mode - populate from props
          this.localDeduction = Number(this.amount || 0)
          this.localDeductionLabel = this.description || ''
          // In edit mode, don't select any loans (manual deduction edit)
          this.selectedLoans = []
        } else {
          // Add mode
          this.localDeduction = Number(this.deduction || 0)
          this.localDeductionLabel = ''
          // Initialize selectedLoans with available loan IDs when modal opens
          if (this.availableLoans && this.availableLoans.length) {
            this.selectedLoans = this.availableLoans.map(loan => loan.id)
          } else {
            this.selectedLoans = []
          }
        }
      }
    },
    isEdit(val) {
      if (val && this.show) {
        this.localDeduction = Number(this.amount || 0)
        this.localDeductionLabel = this.description || ''
        this.selectedLoans = []
      }
    },
    deduction(val) {
      if (!this.isEdit) {
        this.localDeduction = Number(val || 0)
      }
    },
    employee: {
      handler() {
        // Update selectedLoans when employee changes (only in add mode)
        if (!this.isEdit && this.availableLoans && this.availableLoans.length) {
          this.selectedLoans = this.availableLoans.map(loan => loan.id)
        } else {
          this.selectedLoans = []
        }
      },
      immediate: true
    },
    existingDeductions: {
      handler() {
        // Update selectedLoans when existingDeductions changes (only in add mode)
        if (!this.isEdit && this.show && this.availableLoans && this.availableLoans.length) {
          this.selectedLoans = this.availableLoans.map(loan => loan.id)
        }
      },
      immediate: true
    }
  },
computed: {
    availableLoans() {
      if (!this.employee || !this.employee.loans || !this.employee.loans.length) {
        return []
      }
      // Filter out loans that are already added
      return this.employee.loans.filter(loan => {
        const loanDescription = `Loan #${loan.id}`
        const isAlreadyAdded = this.existingDeductions.some(deduction => 
          deduction.description === loanDescription
        )
        return !isAlreadyAdded
      })
    },
    existingManualDeduction() {
      if (!this.localDeductionLabel || !this.existingDeductions || !this.existingDeductions.length) {
        return null
      }
      // Check if the current label matches any existing manual deduction (not loan deductions)
      return this.existingDeductions.find(deduction => 
        deduction.description === this.localDeductionLabel && 
        !deduction.description.startsWith('Loan #')
      )
    },
    totalExistingDeductions() {
      if (!this.availableLoans || !this.availableLoans.length) {
        return 0
      }
      return this.availableLoans.reduce((total, loan) => {
        // Only include loans that are selected
        if (this.selectedLoans.includes(loan.id)) {
          return total + (loan.payroll_deduction || 0)
        }
        return total
      }, 0)
    },
    totalDeductions() {
      return this.totalExistingDeductions + (this.localDeduction || 0)
    },
    selectedLoansData() {
      if (!this.employee || !this.employee.loans || !this.employee.loans.length) {
        return []
      }
      return this.employee.loans
        .filter(loan => this.selectedLoans.includes(loan.id))
        .map(loan => {
          const divisor = loan.divisor || 2
          const deduction = Number(loan.payroll_deduction || 0)

          return {
            id: loan.id,
            remaining_balance: loan.remaining_balance,
            term_months: loan.term_months,
            interest_rate: loan.interest_rate,
            payroll_deduction: parseFloat(deduction.toFixed(2)),
            divisor,
            deduction: parseFloat(deduction.toFixed(2))
          }
        })
    }
  },
  methods: {
    close() {
      this.$emit('close')
    },
    save() {
      if (this.isEdit) {
        // Edit mode - return updated deduction
        this.$emit('save', {
          isEdit: true,
          index: this.editIndex,
          description: this.localDeductionLabel || 'Manual Deduction',
          amount: Number(this.localDeduction || 0)
        })
      } else {
        // Add mode
        this.$emit('save', {
          isEdit: false,
          deduction: Number(this.localDeduction || 0),
          deductionLabel: this.localDeductionLabel || 'Manual Deduction',
          total: this.totalDeductions,
          selectedLoans: this.selectedLoansData,
          totalExistingDeductions: this.totalExistingDeductions
        })
      }
    }
  }
}
</script>

<style scoped>
.employee-name-display {
  font-weight: 600;
  color: #2c3e50;
  padding: 0.5rem 0;
}

.existing-loans {
  background: #f8f9fa;
  border: 1px solid #e9ecef;
  border-radius: 8px;
  padding: 1rem;
  margin-bottom: 1rem;
}

.loan-list {
  max-height: 150px;
  overflow-y: auto;
}

.loan-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 0;
  border-bottom: 1px solid #e9ecef;
}

.loan-item:last-child {
  border-bottom: none;
}

.loan-checkbox {
  display: flex;
  align-items: center;
  margin-right: 0.75rem;
}

.loan-checkbox-input {
  width: 18px;
  height: 18px;
  cursor: pointer;
  accent-color: #3D8D7A;
}

.loan-label {
  cursor: pointer;
  display: flex;
  flex-direction: column;
}

.loan-info {
  display: flex;
  flex-direction: column;
  flex: 1;
}

.loan-id {
  font-weight: 600;
  color: #3D8D7A;
  font-size: 0.85rem;
}

.loan-balance {
  font-size: 0.8rem;
  color: #6c757d;
}

.loan-deduction {
  font-weight: 600;
  color: #dc3545;
}

.total-existing {
  display: flex;
  justify-content: space-between;
  padding-top: 0.75rem;
  margin-top: 0.5rem;
  border-top: 1px solid #dee2e6;
  font-weight: 600;
  font-size: 0.9rem;
}

.total-amount {
  color: #dc3545;
}

.form-hint {
  display: block;
  margin-top: 0.25rem;
  font-size: 0.8rem;
  color: #6c757d;
}

.input-wrapper {
  position: relative;
}

.input-icon {
  position: absolute;
  left: 10px;
  top: 50%;
  transform: translateY(-50%);
  color: #6c757d;
}

.input-wrapper input {
  padding-left: 35px;
}

.form-summary {
  background: #e8f4f2;
  border: 1px solid #3D8D7A;
  border-radius: 8px;
  padding: 1rem;
  margin-top: 1rem;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  padding: 0.25rem 0;
  font-size: 0.9rem;
}

.summary-row.total {
  border-top: 1px solid #3D8D7A;
  margin-top: 0.5rem;
  padding-top: 0.5rem;
  font-weight: 700;
  font-size: 1rem;
  color: #3D8D7A;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 1.5rem;
  padding-top: 1rem;
  border-top: 1px solid #f0f0f0;
}

.btn {
  padding: 0.5rem 1rem;
  border-radius: 6px;
  font-size: 0.9rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-cancel {
  background: #f5f5f5;
  border: 1px solid #d1d5db;
  color: #666;
}

.btn-cancel:hover {
  background: #e8e8e8;
  border-color: #aaa;
}

.btn-save {
  background: #3D8D7A;
  border: 1px solid #3D8D7A;
  color: white;
}

.btn-save:hover {
  background: #2d6d5e;
  border-color: #2d6d5e;
}

/* Modal Styles */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  opacity: 0;
  visibility: hidden;
  transition: all 0.3s ease;
}

.modal-overlay.active {
  opacity: 1;
  visibility: visible;
}

.modal-container {
  background: white;
  border-radius: 12px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.modal-sm {
  width: 90%;
  max-width: 450px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid #f0f0f0;
  background: #f8f9fa;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 600;
  color: #2c3e50;
}

.close-btn {
  background: none;
  border: none;
  font-size: 1.25rem;
  cursor: pointer;
  color: #6c757d;
  padding: 0;
  line-height: 1;
}

.close-btn:hover {
  color: #dc3545;
}

.modal-body {
  padding: 1.5rem;
  overflow-y: auto;
}

.form-group {
  margin-bottom: 1rem;
}

.form-label {
  display: block;
  font-weight: 600;
  color: #2c3e50;
  margin-bottom: 0.5rem;
  font-size: 0.9rem;
}

.form-control {
  width: 100%;
  padding: 0.6rem 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.9rem;
  transition: all 0.2s ease;
}

.form-control:focus {
  outline: none;
  border-color: #3D8D7A;
  box-shadow: 0 0 0 3px rgba(61, 141, 122, 0.1);
}

.existing-deduction-notice {
  background: #fff3cd;
  border: 1px solid #ffc107;
  border-radius: 6px;
  padding: 0.75rem;
  text-align: center;
}

.notice-text {
  color: #856404;
  font-size: 0.9rem;
}

.form-control.is-invalid {
  border-color: #dc3545;
}

.invalid-feedback {
  color: #dc3545;
  font-size: 0.8rem;
  margin-top: 0.25rem;
}
</style>

