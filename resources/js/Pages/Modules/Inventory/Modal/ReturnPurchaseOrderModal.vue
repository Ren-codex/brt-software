<template>
  <div v-if="showModal" class="modal-overlay active" @click.self="hide">
    <div class="modal-container modal-xl">
      <div class="library-card-header modal-header">
        <div class="d-flex align-items-center justify-content-between w-100">
          <div class="d-flex align-items-center gap-3">
            <div class="header-icon">
              <i class="ri-arrow-go-back-line"></i>
            </div>
            <div>
              <h4 class="header-title mb-1">Return Purchase Order Stock</h4>
              <p class="header-subtitle mb-0">Select items and quantity to return</p>
            </div>
          </div>
          <button class="close-btn" @click="hide">&times;</button>
        </div>
      </div>
      <div class="modal-body">
        <form @submit.prevent="submit">
          <div v-if="formError" class="alert alert-danger" role="alert">
            {{ formError }}
          </div>

          <div class="form-group">
            <label class="form-label">Purchase Order</label>
            <select
              class="form-control form-control-sm modern-input"
              v-model="selectedPoId"
              @change="onPurchaseOrderChange"
              :disabled="purchaseOrderOptions.length === 0"
            >
              <option value="">Select purchase order</option>
              <option
                v-for="order in purchaseOrderOptions"
                :key="order.id"
                :value="order.id"
              >
                {{ order.po_number || `PO #${order.id}` }} - {{ order.supplier?.name || 'N/A' }}
              </option>
            </select>
          </div>

          <div class="form-group">
            <h6 class="mb-3 fw-semibold text-muted">Purchase Order Items</h6>
            <div class="table-responsive">
              <table class="table purchase-order-table">
                <thead>
                  <tr>
                    <th class="text-center">Select</th>
                    <th>Product</th>
                    <th class="text-center">Available Stock</th>
                    <th>For Return</th>
                    <th>Remarks</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in selectedOrderItems" :key="item.id || index">
                    <td class="text-center checkbox-cell">
                      <label class="checkbox-container">
                        <input
                          type="checkbox"
                          v-model="rowForm[item.id].checked"
                          @change="validateItemQuantity(item)"
                          :disabled="getReturnableQuantity(item) <= 0"
                        >
                        <span class="checkmark"></span>
                      </label>
                    </td>
                    <td class="product-cell">
                      <div class="product-info">
                        <span class="product-name">{{ getItemProductName(item) }}</span>
                      </div>
                    </td>
                    <td class="text-center quantity-cell">
                      <span
                        :class="[
                          'quantity-badge received',
                          {
                            'received-badge-low': getReturnableQuantity(item) <= 0,
                          },
                        ]"
                      >
                        {{ getReturnableQuantity(item) }}
                      </span>
                    </td>
                    <td class="input-cell">
                      <div class="quantity-control">
                        <button
                          type="button"
                          class="qty-btn"
                          @click="decrementQty(item)"
                          :disabled="!rowForm[item.id].checked || Number(rowForm[item.id].quantity || 0) <= 1"
                        >
                          <i class="ri-subtract-line"></i>
                        </button>
                        <input
                          type="number"
                          class="form-control form-control-sm quantity-input"
                          min="1"
                          :max="getReturnableQuantity(item)"
                          v-model.number="rowForm[item.id].quantity"
                          @input="validateItemQuantity(item)"
                          :disabled="!rowForm[item.id].checked"
                        >
                        <button
                          type="button"
                          class="qty-btn"
                          @click="incrementQty(item)"
                          :disabled="!rowForm[item.id].checked || Number(rowForm[item.id].quantity || 0) >= getReturnableQuantity(item)"
                        >
                          <i class="ri-add-line"></i>
                        </button>
                      </div>
                      <small class="error-message" v-if="errors[item.id]?.quantity">{{ errors[item.id].quantity }}</small>
                    </td>
                    <td class="remarks-cell">
                      <input
                        type="text"
                        class="form-control form-control-sm modern-input"
                        v-model="rowForm[item.id].remarks"
                        :disabled="!rowForm[item.id].checked"
                        placeholder="Enter remarks"
                      >
                    </td>
                  </tr>
                  <tr v-if="selectedOrderItems.length === 0">
                    <td colspan="5" class="empty-state">
                      <i class="ri-inbox-line"></i>
                      <p>{{ selectedOrder ? 'No items found.' : 'Select a purchase order to view items.' }}</p>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <div class="form-actions">
          <button type="button" class="create-btn create-btn-cancel" @click="hide">
            Cancel
          </button>
          <button type="submit" class="create-btn" :disabled="submitting" @click="submit">
            {{ submitting ? 'Saving...' : 'Submit' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ReturnPurchaseOrderModal',
  props: {
    dropdowns: {
      type: Object,
      default: () => ({})
    },
    purchaseOrders: {
      type: Array,
      default: () => []
    }
  },
  emits: ['success', 'toast'],
  data() {
    return {
      showModal: false,
      submitting: false,
      selectedOrder: null,
      selectedPoId: '',
      rowForm: {},
      errors: {},
      formError: '',
    };
  },
  computed: {
    purchaseOrderOptions() {
      if (this.purchaseOrders.length > 0) return this.purchaseOrders;
      return this.selectedOrder ? [this.selectedOrder] : [];
    },
    selectedOrderItems() {
      return this.selectedOrder?.items || [];
    },
  },
  methods: {
    show(order = null) {
      this.selectedOrder = order || null;
      this.selectedPoId = order?.id || '';
      this.initializeRowForm();
      this.errors = {};
      this.formError = '';
      this.submitting = false;
      this.showModal = true;
    },
    hide() {
      this.showModal = false;
      this.submitting = false;
      this.selectedOrder = null;
      this.selectedPoId = '';
      this.rowForm = {};
      this.errors = {};
      this.formError = '';
    },
    onPurchaseOrderChange() {
      const selectedId = Number(this.selectedPoId || 0);
      this.selectedOrder = this.purchaseOrderOptions.find((order) => Number(order.id) === selectedId) || null;
      this.initializeRowForm();
      this.errors = {};
      this.formError = '';
    },
    initializeRowForm() {
      this.rowForm = {};
      this.selectedOrderItems.forEach((item) => {
        const maxQty = this.getReturnableQuantity(item);
        this.rowForm[item.id] = {
          checked: false,
          quantity: maxQty > 0 ? 1 : 0,
          remarks: '',
        };
      });
    },
    validate() {
      this.errors = {};
      this.formError = '';

      if (!this.selectedOrder) {
        this.formError = 'Please select a purchase order.';
        return false;
      }

      const checkedItems = this.selectedOrderItems.filter((item) => this.rowForm[item.id]?.checked);
      if (checkedItems.length === 0) {
        this.formError = 'Select at least one item for return.';
        return false;
      }

      checkedItems.forEach((item) => {
        const row = this.rowForm[item.id] || {};
        const rowErrors = {};
        const maxQty = this.getReturnableQuantity(item);
        const qty = Number(row.quantity || 0);

        if (!qty || qty < 1) {
          rowErrors.quantity = 'For Return must be at least 1.';
        } else if (qty > maxQty) {
          rowErrors.quantity = `For Return cannot exceed ${maxQty}.`;
        }

        if (Object.keys(rowErrors).length > 0) {
          this.errors[item.id] = rowErrors;
        }
      });

      return Object.keys(this.errors).length === 0;
    },
    getItemProductName(item) {
      return item?.product?.name || item?.product?.brand?.name || 'N/A';
    },
    getReturnableQuantity(item) {
      return Number(item?.available_inventory_quantity || 0);
    },
    incrementQty(item) {
      const row = this.rowForm[item.id];
      if (!row || !row.checked) return;
      const maxQty = this.getReturnableQuantity(item);
      const current = Number(row.quantity || 0);
      row.quantity = Math.min(maxQty, Math.max(1, current + 1));
      this.validateItemQuantity(item);
    },
    decrementQty(item) {
      const row = this.rowForm[item.id];
      if (!row || !row.checked) return;
      const current = Number(row.quantity || 0);
      row.quantity = Math.max(1, current - 1);
      this.validateItemQuantity(item);
    },
    validateItemQuantity(item) {
      if (!item?.id) return;
      const row = this.rowForm[item.id] || {};
      const maxQty = this.getReturnableQuantity(item);
      const qty = Number(row.quantity || 0);

      if (!row.checked) {
        if (this.errors[item.id]?.quantity) {
          const next = { ...(this.errors[item.id] || {}) };
          delete next.quantity;
          if (Object.keys(next).length) {
            this.errors[item.id] = next;
          } else {
            delete this.errors[item.id];
          }
        }
        return;
      }

      if (!qty || qty < 1) {
        this.errors[item.id] = {
          ...(this.errors[item.id] || {}),
          quantity: 'For Return must be at least 1.',
        };
        return;
      }

      if (qty > maxQty) {
        this.errors[item.id] = {
          ...(this.errors[item.id] || {}),
          quantity: `For Return cannot exceed ${maxQty}.`,
        };
        return;
      }

      if (this.errors[item.id]?.quantity) {
        const next = { ...(this.errors[item.id] || {}) };
        delete next.quantity;
        if (Object.keys(next).length) {
          this.errors[item.id] = next;
        } else {
          delete this.errors[item.id];
        }
      }
    },
    selectedPayloadItems() {
      return this.selectedOrderItems
        .filter((item) => this.rowForm[item.id]?.checked)
        .map((item) => ({
          po_item_id: item.id,
          quantity: Number(this.rowForm[item.id]?.quantity || 0),
          remarks: (this.rowForm[item.id]?.remarks || '').trim(),
        }));
    },
    async submit() {
      if (!this.selectedOrder) return;
      if (!this.validate()) return;

      this.submitting = true;
      try {
        await axios.post(`/stock-returns`, {
          items: this.selectedPayloadItems(),
        });
        this.hide();
        this.$emit('success');
      } catch (error) {
        const errorMessage = error?.response?.data?.message || 'Unable to process return';
        const responseErrors = error?.response?.data?.errors || {};
        if (Object.keys(responseErrors).length > 0) this.formError = errorMessage;
        // this.$emit('toast', { message: errorMessage, type: 'error' });
      } finally {
        this.submitting = false;
      }
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
  border-bottom: 1px solid #e2e8f0;
}

.modal-header .header-title {
  color: #1e293b;
}

.close-btn {
  border: none;
  background: transparent;
  color: #6c757d;
  font-size: 1.5rem;
  line-height: 1;
  cursor: pointer;
  padding: 0;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  transition: all 0.2s ease;
}

.close-btn:hover {
  background: #e2e8f0;
  color: #1e293b;
}

.modal-body {
  padding: 1.25rem;
  flex: 1 1 auto;
  min-height: 0;
  overflow-y: auto;
}

.modal-footer {
  flex-shrink: 0;
  padding: 0.875rem 1.5rem;
  border-top: 1px solid #e2e8f0;
  background: #fff;
}

.form-group {
  margin-bottom: 1rem;
}

.form-label {
  font-weight: 600;
  margin-bottom: 0.35rem;
  display: inline-block;
  color: #1e293b;
}

.form-static-value {
  color: #495057;
  font-weight: 500;
}

.alert-danger {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #dc2626;
  padding: 0.75rem 1rem;
  border-radius: 8px;
  font-size: 0.875rem;
  margin-bottom: 1rem;
}

.table-responsive {
  border: 1px solid #edf2f7;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
}

.purchase-order-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
  background: white;
}

.purchase-order-table thead tr {
  background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
  border-bottom: 2px solid #e2e8f0;
}

.purchase-order-table th {
  padding: 1rem 1rem;
  font-weight: 600;
  color: #1e293b;
  text-transform: uppercase;
  font-size: 0.7rem;
  letter-spacing: 0.5px;
  white-space: nowrap;
  border: none;
  position: sticky;
  top: 0;
  z-index: 1;
}

.purchase-order-table td {
  padding: 1rem;
  border-bottom: 1px solid #edf2f7;
  vertical-align: middle;
}

/* Checkbox Cell */
.checkbox-cell {
  text-align: center;
  width: 60px;
}

/* Custom Checkbox */
.checkbox-container {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  position: relative;
  padding-left: 1.5rem;
  user-select: none;
}

.checkbox-container input {
  position: absolute;
  opacity: 0;
  cursor: pointer;
  height: 0;
  width: 0;
}

.checkmark {
  position: absolute;
  left: 0;
  height: 18px;
  width: 18px;
  background-color: #fff;
  border: 2px solid #cbd5e1;
  border-radius: 4px;
  transition: all 0.2s ease;
}

.checkbox-container:hover input ~ .checkmark {
  border-color: #2e8b57;
}

.checkbox-container input:checked ~ .checkmark {
  background-color: #2e8b57;
  border-color: #2e8b57;
}

.checkmark:after {
  content: "";
  position: absolute;
  display: none;
}

.checkbox-container input:checked ~ .checkmark:after {
  display: block;
}

.checkbox-container .checkmark:after {
  left: 5px;
  top: 1px;
  width: 4px;
  height: 8px;
  border: solid white;
  border-width: 0 2px 2px 0;
  transform: rotate(45deg);
}

/* Product Cell */
.product-cell {
  min-width: 180px;
}

.product-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.product-name {
  font-weight: 500;
  color: #0f172a;
}

/* Quantity Cell */
.quantity-cell {
  text-align: center;
  width: 100px;
}

.quantity-badge {
  display: inline-block;
  padding: 0.3rem 0.8rem;
  border-radius: 20px;
  font-weight: 600;
  font-size: 0.85rem;
  min-width: 50px;
}

.quantity-badge.received {
  background: #d4edda;
  color: #155724;
}

.received-badge-low {
  background: #fdecef;
  color: #9f1239;
  border: 1px solid #f3c2c7;
}

/* Input Cell */
.input-cell {
  min-width: 150px;
}

.remarks-cell {
  min-width: 180px;
}

/* Modern Input */
.modern-input {
  width: 100%;
  height: 40px;
  padding: 0.5rem 0.75rem;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.9rem;
  transition: all 0.2s ease;
  background: white;
}

.modern-input:focus {
  border-color: #2e8b57;
  box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.12);
  outline: none;
}

.modern-input:disabled {
  background: #f8fafc;
  border-color: #e2e8f0;
  color: #94a3b8;
  cursor: not-allowed;
}

/* Quantity Control */
.quantity-control {
  display: flex;
  align-items: center;
  gap: 6px;
}

.quantity-input {
  width: 70px;
  text-align: center;
  padding-left: 0.5rem;
  padding-right: 0.5rem;
  height: 40px;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.9rem;
  transition: all 0.2s ease;
}

.quantity-input:focus {
  border-color: #2e8b57;
  box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.12);
  outline: none;
}

.quantity-input:disabled {
  background: #f8fafc;
  border-color: #e2e8f0;
  color: #94a3b8;
  cursor: not-allowed;
}

.qty-btn {
  background: #f9fafb;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #6b7280;
  transition: all 0.3s ease;
  padding: 0;
}

.qty-btn:hover:not(:disabled) {
  background: #2e8b57;
  color: #ffffff;
  border-color: #2e8b57;
}

.qty-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Form Actions */
.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  margin: 0;
}

.create-btn {
  background: #2e8b57;
  border: none;
  border-radius: 8px;
  padding: 0.625rem 1.25rem;
  font-weight: 600;
  font-size: 0.9rem;
  color: #fff;
  transition: all 0.2s ease;
  box-shadow: 0 4px 12px rgba(46, 139, 87, 0.25);
}

.create-btn:hover:not(:disabled) {
  background: #256f46;
  transform: translateY(-1px);
}

.create-btn:focus,
.create-btn:active {
  outline: none;
  box-shadow: none;
  border: none;
}

.create-btn:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.create-btn-cancel {
  background: #f1f5f9;
  color: #475569;
  box-shadow: none;
  border: 1px solid #e2e8f0;
}

.create-btn-cancel:hover:not(:disabled) {
  background: #e2e8f0;
  color: #334155;
  transform: none;
}

/* Error Message */
.error-message {
  color: #ef4444;
  font-size: 0.75rem;
  display: block;
  margin-top: 0.25rem;
  line-height: 1.2;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 3rem 1rem !important;
  color: #94a3b8;
}

.empty-state i {
  font-size: 2.5rem;
  display: block;
  margin-bottom: 0.5rem;
}

.empty-state p {
  margin: 0;
  font-size: 0.9rem;
}

/* Responsive */
@media (max-width: 992px) {
  .table-responsive {
    overflow-x: auto;
  }

  .purchase-order-table {
    min-width: 700px;
  }
}
</style>
