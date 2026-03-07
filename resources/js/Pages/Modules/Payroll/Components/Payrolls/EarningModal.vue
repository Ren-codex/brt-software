<template>
  <div v-if="show" class="modal-overlay" :class="{ active: show }" @click.self="close">
    <div class="modal-container modal-sm" @click.stop>
      <div class="modal-header">
        <h3>{{ isEdit ? 'Edit Earning' : 'Add Earning' }}</h3>
        <button class="close-btn" @click="close">
          <i class="ri-close-line"></i>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Earning Description</label>
          <select
            v-model="localDescription" 
            class="form-control" 
            :class="{ 'is-invalid': existingEarning }"
          >
            <option value="">Select earning</option>
            <option
              v-for="item in earningOptions"
              :key="item"
              :value="item"
            >
              {{ item }}
            </option>
          </select>
          <div v-if="existingEarning" class="invalid-feedback">
            Already exists
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Amount</label>
          <div class="input-wrapper">
            <i class="ri-cash-line input-icon"></i>
            <input 
              type="number" 
              v-model.number="localAmount" 
              class="form-control" 
              min="0" 
              step="0.01"
              placeholder="0.00"
              @keyup.enter="save">
          </div>
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-cancel" @click="close">Cancel</button>
          <button type="button" class="btn btn-save" @click="save" :disabled="existingEarning">Save</button>
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
    amount: { type: [Number, String] },
    existingEarnings: { type: Array, default: () => [] },
    earningDropdown: { type: Array, default: () => [] },
    earningsDropdown: { type: Array, default: () => [] },
  },
  data() {
    return {
      localDescription: this.description,
      localAmount: Number(this.amount),
    }
  },
  computed: {
    earningOptions() {
      const source = this.earningDropdown.length ? this.earningDropdown : this.earningsDropdown
      if (!Array.isArray(source) || !source.length) {
        return []
      }

      return [...new Set(source
        .map(item => item?.description || item?.name || '')
        .filter(Boolean))]
    },
    existingEarning() {
      if (!this.localDescription || !this.existingEarnings || !this.existingEarnings.length) {
        return null
      }
      // In edit mode, ignore the current row when checking duplicates.
      return this.existingEarnings.find((earning, index) => {
        if (this.isEdit && index === this.editIndex) {
          return false
        }
        return earning.description === this.localDescription
      })
    }
  },
  watch: {
    show(val) {
      if (val) {
        this.localDescription = this.description
        this.localAmount = Number(this.amount)
      }
    },
    description(val) {
      this.localDescription = val || ''
    },
    amount(val) {
      this.localAmount = Number(val || 0)
    },
    notes(val) {
      this.localNotes = val || ''
    }
  },
  methods: {
    close() {
      this.$emit('close')
    },
    save() {
      this.$emit('save', {
        isEdit: this.isEdit,
        index: this.editIndex,
        description: this.localDescription,
        amount: Number(this.localAmount || 0)
      })
    },
    formatNumber(value) {
      return value.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')
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
  font-weight: 600;
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

.form-control.is-invalid {
  border-color: #dc3545;
}

.invalid-feedback {
  color: #dc3545;
  font-size: 0.8rem;
  margin-top: 0.25rem;
}
</style>
