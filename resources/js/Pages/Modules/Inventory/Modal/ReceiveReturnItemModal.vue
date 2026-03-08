<template>
  <div v-if="show" class="modal-overlay active" @click.self="$emit('close')">
    <div class="modal-container modal-lg">
      <div class="library-card-header modal-header">
        <div class="d-flex align-items-center justify-content-between w-100">
          <div class="d-flex align-items-center gap-3">
            <div class="header-icon">
              <i class="ri-inbox-archive-line"></i>
            </div>
            <div>
              <h4 class="header-title mb-1">Receive Return Item</h4>
              <p class="header-subtitle mb-0">Process replacement quantity</p>
            </div>
          </div>
          <button class="close-btn" @click="$emit('close')">&times;</button>
        </div>
      </div>
      <div class="modal-body">
        <div class="form-group mb-3">
          <label class="form-label">Product</label>
          <input type="text" class="form-control" :value="selectedReturnItemName" disabled>
        </div>

        <div class="form-group mb-3">
          <label class="form-label" for="replaced-qty">Replacement Quantity</label>
          <input
            id="replaced-qty"
            :value="Number(receiveForm.replaced_quantity || 0)"
            type="number"
            min="0"
            step="1"
            :max="Number(selectedReturnItem?.quantity || 0)"
            class="form-control"
            placeholder="Enter replacement quantity"
            @input="updateField('replaced_quantity', $event.target.value)"
          >
          <small v-if="replacementQtyError" class="error-message">{{ replacementQtyError }}</small>
          <div class="returned-qty-highlight">
            <span class="returned-qty-label">Remaining Returned Qty</span>
            <span class="returned-qty-value">{{ remainingReturnedQty }}</span>
          </div>
        
        </div>

        <div class="form-group">
          <label class="form-label" for="receive-remarks">Remarks</label>
          <textarea
            id="receive-remarks"
            :value="receiveForm.remarks || ''"
            class="form-control textarea-control"
            rows="3"
            placeholder="Enter remarks"
            @input="updateField('remarks', $event.target.value)"
          ></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <div class="form-actions">
          <button class="btn btn-cancel" @click="$emit('close')" :disabled="receiving">Cancel</button>
          <button class="btn btn-save" @click="$emit('save')" :disabled="receiving || Boolean(replacementQtyError)">
            {{ receiving ? 'Saving...' : 'Save' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ReceiveReturnItemModal',
  props: {
    show: {
      type: Boolean,
      default: false,
    },
    selectedReturnItem: {
      type: Object,
      default: null,
    },
    receiveForm: {
      type: Object,
      default: () => ({
        replaced_quantity: 0,
        remarks: '',
      }),
    },
    receiving: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['close', 'save', 'update-receive-form'],
  computed: {
    selectedReturnItemName() {
      return this.selectedReturnItem?.purchase_order_item?.product?.name || 'N/A';
    },
    totalReturnedQty() {
      return Number(this.selectedReturnItem?.quantity || 0);
    },
    replacementQtyValue() {
      return Number(this.receiveForm?.replaced_quantity || 0);
    },
    replacementQtyError() {
      if (Number.isNaN(this.replacementQtyValue) || this.replacementQtyValue < 0) {
        return 'Replacement quantity must be 0 or greater.';
      }
      if (!Number.isInteger(this.replacementQtyValue)) {
        return 'Replacement quantity must be a whole number.';
      }
      if (this.replacementQtyValue > this.totalReturnedQty) {
        return `Replacement quantity cannot be greater than ${this.totalReturnedQty}.`;
      }
      return '';
    },
    remainingReturnedQty() {
      return Math.max(this.totalReturnedQty - this.replacementQtyValue, 0);
    },
  },
  methods: {
    updateField(field, value) {
      const isNumericField = field === 'replaced_quantity';
      this.$emit('update-receive-form', {
        [field]: isNumericField ? Math.max(0, Math.trunc(Number(value || 0))) : value,
      });
    },
  },
};
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1050;
}

.modal-container {
  background: #ffffff;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
  display: flex;
  flex-direction: column;
  max-height: 85vh;
}

.modal-header {
  padding: 0.875rem 1.25rem;
  border-bottom: 1px solid #e9ecef;
}

.modal-body {
  padding: 1.25rem;
  flex: 1 1 auto;
  min-height: 0;
  overflow-y: auto;
}

.modal-footer {
  flex-shrink: 0;
  padding: 0.875rem 1.25rem;
  border-top: 1px solid #e9ecef;
  background: #fff;
}

.close-btn {
  border: none;
  background: transparent;
  color: #6c757d;
  font-size: 1.3rem;
  line-height: 1;
}

.textarea-control {
  resize: vertical;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.6rem;
  margin-top: 0;
}

.btn {
  border: none;
  border-radius: 6px;
  padding: 0.5rem 0.85rem;
  font-weight: 500;
}

.btn-cancel {
  background: #e9ecef;
  color: #495057;
}

.btn-save {
  background: #2e8b57;
  color: #fff;
}

.btn:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.error-message {
  color: #ef4444;
  font-size: 0.75rem;
  display: block;
  margin-top: 0.25rem;
}

.returned-qty-highlight {
  margin-top: 0.5rem;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.3rem 0.65rem;
  border-radius: 999px;
  background: #ecfdf3;
  border: 1px solid #b7ebcd;
}

.returned-qty-label {
  font-size: 0.75rem;
  color: #166534;
  font-weight: 600;
}

.returned-qty-value {
  font-size: 0.9rem;
  color: #065f46;
  font-weight: 800;
}
</style>
