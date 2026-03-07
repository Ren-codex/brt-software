<template>
  <div
    v-if="showModal"
    class="modal-overlay active"
    @click.self="hide"
  >
    <div class="modal-container modal-xl" @click.stop>
      <div class="modal-header">
        <h2>Return Purchase Order Stock</h2>
        <button class="close-btn" @click="hide">
          <i class="ri-close-line"></i>
        </button>
      </div>
      <div class="modal-body">
        <form @submit.prevent="submit">
          <div class="form-group">
            <label class="form-label">Purchase Order</label>
            <select
              class="form-control form-control-sm"
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
            <label class="form-label">Purchase Order Items</label>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th class="text-center">Select</th>
                    <th>Product</th>
                    <th class="text-center">Received</th>
                    <th style="width:18%">For Return</th>
                    <!-- <th>Status</th> -->
                    <th>Remarks</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in selectedOrderItems" :key="item.id || index">
                    <td class="text-center">
                      <input
                        type="checkbox"
                        v-model="rowForm[item.id].checked"
                        :disabled="(item.received_quantity || 0) <= 0"
                      >
                    </td>
                    <td>{{ getItemProductName(item) }}</td>
                    <td class="text-center">{{ item.received_quantity || 0 }}</td>
                    <td>
                      <div class="qty-control">
                        <button
                          type="button"
                          class="qty-btn"
                          @click="decrementQty(item)"
                          :disabled="!rowForm[item.id].checked || Number(rowForm[item.id].quantity || 0) <= 1"
                        >
                          -
                        </button>
                        <input
                          type="number"
                          class="form-control form-control-sm qty-input"
                          min="1"
                          :max="item.received_quantity || 0"
                          v-model.number="rowForm[item.id].quantity"
                          :disabled="!rowForm[item.id].checked"
                        >
                        <button
                          type="button"
                          class="qty-btn"
                          @click="incrementQty(item)"
                          :disabled="!rowForm[item.id].checked || Number(rowForm[item.id].quantity || 0) >= Number(item.received_quantity || 0)"
                        >
                          +
                        </button>
                      </div>
                      <small class="error-message" v-if="errors[item.id]?.quantity">{{ errors[item.id].quantity }}</small>
                    </td>
                    <!-- <td>
                      <select
                        class="form-control form-control-sm"
                        v-model="rowForm[item.id].status_id"
                        :disabled="!rowForm[item.id].checked"
                      >
                        <option value="">Select status</option>
                        <option
                          v-for="status in statusOptions"
                          :key="getStatusValue(status)"
                          :value="getStatusValue(status)"
                        >
                          {{ getStatusLabel(status) }}
                        </option>
                      </select>
                      <small class="error-message" v-if="errors[item.id]?.status_id">{{ errors[item.id].status_id }}</small>
                    </td> -->
                    <td>
                      <input
                        type="text"
                        class="form-control form-control-sm"
                        v-model="rowForm[item.id].remarks"
                        :disabled="!rowForm[item.id].checked"
                        placeholder="Enter remarks"
                      >
                    </td>
                  </tr>
                  <tr v-if="selectedOrderItems.length === 0">
                    <td colspan="6" class="text-center text-muted py-3">
                      {{ selectedOrder ? 'No items found.' : 'Select a purchase order to view items.' }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <p v-if="formError" class="error-message mb-0">{{ formError }}</p>

          <div class="form-actions">
            <button type="button" class="btn btn-cancel" @click="hide">
              Cancel
            </button>
            <button type="submit" class="btn btn-save" :disabled="submitting">
              {{ submitting ? 'Saving...' : 'Submit' }}
            </button>
          </div>
        </form>
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
    statusOptions() {
      const statuses = this.dropdowns?.statuses || [];
      const allowedStatuses = new Set(['pending', 'replaced', 'loss']);

      return statuses.filter((status) => {
        const slug = String(status?.slug || '').trim().toLowerCase();
        const name = String(status?.name || status?.label || '').trim().toLowerCase();
        return allowedStatuses.has(slug) || allowedStatuses.has(name);
      });
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
        this.rowForm[item.id] = {
          checked: false,
          quantity: item.received_quantity > 0 ? 1 : 0,
          remarks: '',
          status_id: this.getDefaultStatusId(),
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
        const maxQty = Number(item.received_quantity || 0);
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
    getDefaultStatusId() {
      const pending = this.statusOptions.find((status) => {
        const slug = String(status?.slug || '').trim().toLowerCase();
        const name = String(status?.name || status?.label || '').trim().toLowerCase();
        return slug === 'pending' || name === 'pending';
      });
      return pending?.value || pending?.id || '';
    },
    getStatusValue(status) {
      return status?.value ?? status?.id ?? '';
    },
    getStatusLabel(status) {
      return status?.name ?? status?.label ?? '';
    },
    incrementQty(item) {
      const row = this.rowForm[item.id];
      if (!row || !row.checked) return;
      const maxQty = Number(item.received_quantity || 0);
      const current = Number(row.quantity || 0);
      row.quantity = Math.min(maxQty, Math.max(1, current + 1));
    },
    decrementQty(item) {
      const row = this.rowForm[item.id];
      if (!row || !row.checked) return;
      const current = Number(row.quantity || 0);
      row.quantity = Math.max(1, current - 1);
    },
    selectedPayloadItems() {
      return this.selectedOrderItems
        .filter((item) => this.rowForm[item.id]?.checked)
        .map((item) => ({
          po_item_id: item.id,
          quantity: Number(this.rowForm[item.id]?.quantity || 0),
          remarks: (this.rowForm[item.id]?.remarks || '').trim(),
          status_id: Number(this.rowForm[item.id]?.status_id || 0),
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
}

.modal-header {
  padding: 1rem 1.25rem;
  border-bottom: 1px solid #e9ecef;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.modal-header h2 {
  margin: 0;
  font-size: 1.1rem;
}

.close-btn {
  border: none;
  background: transparent;
  color: #6c757d;
  font-size: 1.3rem;
  line-height: 1;
}

.modal-body {
  padding: 1.25rem;
}

.form-group {
  margin-bottom: 1rem;
}

.form-label {
  font-weight: 600;
  margin-bottom: 0.35rem;
  display: inline-block;
}

.form-static-value {
  color: #495057;
  font-weight: 500;
}

.table-responsive {
  border: 1px solid #e9ecef;
  border-radius: 8px;
  max-height: 240px;
  overflow: auto;
}

th {
  position: sticky;
  top: 0;
  background: #f8f9fa;
  z-index: 1;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.6rem;
  margin-top: 1.25rem;
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
  border: none;
}

.btn-save:focus,
.btn-save:active {
  outline: none;
  box-shadow: none;
  border: none;
}

.btn-save:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.error-message {
  color: #dc3545;
  font-size: 0.8rem;
  display: block;
  margin-top: 0.2rem;
}

.qty-control {
  display: flex;
  align-items: center;
  gap: 0.35rem;
}

.qty-input {
  max-width: 84px;
  text-align: center;
}

.qty-btn {
  width: 48px;
  height: 48px;
  border: 1px solid #ced4da;
  border-radius: 4px;
  background: #fff;
  color: #212529;
  line-height: 1;
}

.qty-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
