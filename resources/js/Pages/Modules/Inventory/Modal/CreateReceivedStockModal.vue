<template>
  <div v-if="showModal" class="modal-overlay active" @click.self="onCancel">
    <div class="modal-container modal-xl">
      <div class="modal-header">
        <h2>Received Stock</h2>
        <button class="close-btn" @click="onCancel">&times;</button>
      </div>
      <div class="modal-body">
        <form @submit.prevent="handleSubmit">
          <div v-if="errorMessage" class="alert alert-danger" role="alert">
            {{ errorMessage }}
          </div>

          <div class="mb-3">
            <h6 class="mb-3 fw-semibold text-muted">Purchase Order Items</h6>
            <div class="table-responsive">
              <table class="table purchase-order-table">
                <thead>
                  <tr>
                    <th>Product Details</th>
                    <th>Ordered</th>
                    <th>Received</th>
                    <th>To Receive</th>
                    <th>Unit Cost</th>
                    <th>Retail Price</th>
                    <th>Wholesale Price</th>
                    <th>Expiry</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in form.items" :key="item.id"
                    :class="{ 'disabled-row': item.status !== 'pending' }">

                    <!-- Product Name -->
                    <td class="product-cell">
                      <div class="product-info">
                        <span class="product-name">{{ item.product ? item.product.name : 'N/A' }}</span>
                        <span v-if="item.status !== 'pending'" class="status-badge" :class="item.status">
                          {{ item.status }}
                        </span>
                      </div>
                    </td>

                    <!-- Ordered Quantity -->
                    <td class="text-center quantity-cell">
                      <span class="quantity-badge ordered">{{ item.quantity }}</span>
                    </td>

                    <!-- Received Quantity -->
                    <td class="text-center quantity-cell">
                      <span class="quantity-badge received">{{ item.received_quantity || 0 }}</span>
                    </td>

                    <!-- To Receive Quantity -->
                    <td class="input-cell">
                      <div class="input-wrapper">
                        <input type="number" v-model="item.to_received_quantity" class="form-control modern-input"
                          :class="{ 'error': item.to_received_quantity > (item.quantity - item.received_quantity) }"
                          :min="0" :max="item.quantity - item.received_quantity" step="1"
                          :disabled="item.status !== 'pending'" placeholder="0" />
                        <small class="max-hint" v-if="item.status === 'pending'">
                          Max: {{ item.quantity - item.received_quantity }}
                        </small>
                        <div v-if="item.to_received_quantity > (item.quantity - item.received_quantity)"
                          class="error-message">
                          Exceeds maximum
                        </div>
                      </div>
                    </td>

                    <!-- Unit Cost -->
                    <td class="text-center">
                      <span class="currency-value">{{ formatCurrency(item.unit_cost) }}</span>
                    </td>

                    <!-- Retail Price -->
                    <td class="input-cell">
                      <div class="input-wrapper">
                        <span class="currency-symbol">₱</span>
                        <input type="number" v-model="item.retail_price" class="form-control modern-input with-currency"
                          :class="{ 'error': shouldShowRetailPriceError(item) }"
                          @input="markFieldTouched(item, 'retail_price')" @blur="markFieldTouched(item, 'retail_price')"
                          :min="item.unit_cost || 0" step="0.01" :disabled="item.status !== 'pending'"
                          placeholder="0.00" />
                        <div v-if="shouldShowRetailPriceError(item)" class="field-error">
                          {{ getRetailPriceError(item) }}
                        </div>
                      </div>
                    </td>

                    <!-- Wholesale Price -->
                    <td class="input-cell">
                      <div class="input-wrapper">
                        <span class="currency-symbol">₱</span>
                        <input type="number" v-model="item.wholesale_price"
                          :class="{ 'error': shouldShowWholesalePriceError(item) }"
                          @input="markFieldTouched(item, 'wholesale_price')" @blur="markFieldTouched(item, 'wholesale_price')"
                          class="form-control modern-input with-currency" :min="item.unit_cost || 0"
                          :max="item.retail_price || undefined" step="0.01"
                          :disabled="item.status !== 'pending'" placeholder="0.00" />
                        <div v-if="shouldShowWholesalePriceError(item)" class="field-error">
                          {{ getWholesalePriceError(item) }}
                        </div>
                      </div>
                    </td>

                    <!-- Expiry -->
                    <td class="expiry-cell">
                      <div class="expiry-control">
                        <label class="checkbox-container">
                          <input type="checkbox" v-model="item.has_expiry" :disabled="item.status !== 'pending'" />
                          <span class="checkmark"></span>
                          <span class="checkbox-label">Has Expiry</span>
                        </label>

                        <transition name="fade">
                          <div v-if="item.has_expiry" class="date-input-wrapper">
                            <i class="ri-calendar-line"></i>
                            <input type="date" v-model="item.expiration_date"
                              class="form-control modern-input date-input" :disabled="item.status !== 'pending'" />
                          </div>
                        </transition>
                      </div>
                    </td>
                  </tr>

                  <!-- Empty State -->
                  <tr v-if="!form.items || form.items.length === 0">
                    <td colspan="8" class="empty-state">
                      <i class="ri-inbox-line"></i>
                      <p>No items available</p>
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
          <button class="btn btn-cancel" @click="onCancel">Cancel</button>
          <button class="btn btn-save" @click="handleSubmit()" :disabled="form.processing">Submit</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: "CreateReceivedStockModal",
  props: {
    dropdowns: {
      type: Object,
      required: true,
    },
  },
  emits: ['add'],
  data() {
    return {
      showModal: false,
      form: {
        po_id: '',
        supplier_id: '',
        items: [],
      },
      errorMessage: '',
      purchaseOrder: [],
      submitAttempted: false,
    };
  },
  methods: {
    toNumber(value) {
      if (value === null || value === undefined || value === '') return null;
      const parsed = Number(value);
      return Number.isFinite(parsed) ? parsed : null;
    },
    getProductName(item) {
      return item?.product?.name || item?.product_name || 'N/A';
    },
    markFieldTouched(item, field) {
      if (!item._touched) item._touched = {};
      item._touched[field] = true;
    },
    shouldShowRetailPriceError(item) {
      const touched = !!item?._touched?.retail_price;
      return !!this.getRetailPriceError(item) && (touched || this.submitAttempted);
    },
    shouldShowWholesalePriceError(item) {
      const touched = !!item?._touched?.wholesale_price;
      return !!this.getWholesalePriceError(item) && (touched || this.submitAttempted);
    },
    getRetailPriceError(item) {
      if (item.status !== 'pending') return '';

      const toReceive = this.toNumber(item.to_received_quantity) ?? 0;
      const retailPrice = this.toNumber(item.retail_price);
      const unitCost = this.toNumber(item.unit_cost) ?? 0;

      if (toReceive > 0 && retailPrice === null) return 'Required when To Receive > 0';
      if (retailPrice !== null && retailPrice < 0) return 'Must be 0 or greater';
      if (toReceive > 0 && retailPrice !== null && retailPrice < unitCost) {
        return `Must be at least ${this.formatCurrency(unitCost)}`;
      }

      return '';
    },
    getWholesalePriceError(item) {
      if (item.status !== 'pending') return '';

      const toReceive = this.toNumber(item.to_received_quantity) ?? 0;
      const wholesalePrice = this.toNumber(item.wholesale_price);
      const unitCost = this.toNumber(item.unit_cost) ?? 0;
      const retailPrice = this.toNumber(item.retail_price);

      if (toReceive > 0 && wholesalePrice === null) return 'Required when To Receive > 0';
      if (wholesalePrice !== null && wholesalePrice < 0) return 'Must be 0 or greater';
      if (toReceive > 0 && wholesalePrice !== null && wholesalePrice < unitCost) {
        return `Must be at least ${this.formatCurrency(unitCost)}`;
      }
      if (toReceive > 0 && retailPrice !== null && wholesalePrice !== null && wholesalePrice > retailPrice) {
        return `Must be at most ${this.formatCurrency(retailPrice)}`;
      }

      return '';
    },
    async show(data) {
      this.purchaseOrder = data;
      this.form.po_id = this.purchaseOrder.id;
      this.form.supplier_id = this.purchaseOrder.supplier.id;
      this.form.items = this.purchaseOrder.items
        .filter(item => item.status === 'pending')
        .map(item => ({
          ...item,
          po_item_id: item.id,
          product_id: item.product.id,
          product_name: item.product?.name,
          to_received_quantity: item.quantity - item.received_quantity,
          retail_price: 0,
          wholesale_price: 0,
          expiration_date: null,
          has_expiry: false,
          _touched: {
            retail_price: false,
            wholesale_price: false,
          },
        }));


      this.showModal = true;
    },
    resetForm() {
      this.form = {
        po_id: '',
        supplier_id: '',
        items: [],
      };
      this.errorMessage = '';
      this.submitAttempted = false;
    },
    async handleSubmit() {
      this.submitAttempted = true;
      // Validate to_received_quantity does not exceed quantity - received_quantity
      for (const item of this.form.items) {
        const maxAllowed = item.quantity - item.received_quantity;
        const toReceive = this.toNumber(item.to_received_quantity) ?? 0;
        const unitCost = this.toNumber(item.unit_cost) ?? 0;
        const retailPrice = this.toNumber(item.retail_price);
        const wholesalePrice = this.toNumber(item.wholesale_price);
        const productName = this.getProductName(item);

        if (toReceive > maxAllowed) {
          this.errorMessage = `To receive quantity for ${productName} must not exceed ${maxAllowed}.`;
          return; // Prevent submission
        }

        if (toReceive < 0) {
          this.errorMessage = `To receive quantity for ${productName} must be 0 or greater.`;
          return; // Prevent submission
        }

        if (toReceive > 0) {
          if (retailPrice === null) {
            this.errorMessage = `Retail price is required for ${productName} when "To Receive" has a value.`;
            return;
          }

          if (wholesalePrice === null) {
            this.errorMessage = `Wholesale price is required for ${productName} when "To Receive" has a value.`;
            return;
          }

          if (retailPrice < unitCost) {
            this.errorMessage = `Retail price for ${productName} must not be less than unit cost (${this.formatCurrency(unitCost)}).`;
            return;
          }

          if (wholesalePrice < unitCost) {
            this.errorMessage = `Wholesale price for ${productName} must be at least unit cost (${this.formatCurrency(unitCost)}).`;
            return;
          }

          if (wholesalePrice > retailPrice) {
            this.errorMessage = `Wholesale price for ${productName} must be at most retail price (${this.formatCurrency(retailPrice)}).`;
            return;
          }
        }

        if (retailPrice !== null && retailPrice < 0) {
          this.errorMessage = `Retail price for ${productName} must be 0 or greater.`;
          return;
        }

        if (wholesalePrice !== null && wholesalePrice < 0) {
          this.errorMessage = `Wholesale price for ${productName} must be 0 or greater.`;
          return; // Prevent submission
        }
      }

      this.errorMessage = ''; // Clear any previous error

      try {
        await axios.post('/received-stocks', this.form)
          .then(response => {
            this.hide();
            this.$emit('add', 'success');
            this.$inertia.reload();
            this.resetForm();
          });
      } catch (error) {
        console.error('Error submitting received stock:', error);
        this.errorMessage = 'An error occurred while submitting. Please try again.';
      }
    },
    hide() {
      this.showModal = false;
      this.resetForm();
    },
    onCancel() {
      this.hide();
    },
    formatCurrency(value) {
      if (!value && value !== 0) return '₱0.00';
      return '₱' + Number(value).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    }
  },
};
</script>
<style scoped>
.bg-faded-gray {
  background-color: #f8f8f8;
}

.modal-container.modal-xl {
  display: flex;
  flex-direction: column;
  max-height: 85vh;
}

.modal-body {
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

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  margin: 0;
}

.table-responsive {
  border-radius: 12px;
  border: 1px solid #edf2f7;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
}

.purchase-order-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
  background: white;
}

/* Table Header */
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
}

/* Table Body */
.purchase-order-table td {
  padding: 1rem;
  border-bottom: 1px solid #edf2f7;
  vertical-align: middle;
}

/* Product Cell */
.product-cell {
  min-width: 200px;
}

.product-info {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.product-name {
  font-weight: 500;
  color: #0f172a;
}

.status-badge {
  font-size: 0.6rem;
  padding: 0.2rem 0.5rem;
  border-radius: 20px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.status-badge.pending {
  background: #fff3cd;
  color: #856404;
}

.status-badge.completed {
  background: #d4edda;
  color: #155724;
}

.status-badge.cancelled {
  background: #f8d7da;
  color: #721c24;
}

/* Quantity Cells */
.quantity-cell {
  text-align: center;
}

.quantity-badge {
  display: inline-block;
  padding: 0.3rem 0.8rem;
  border-radius: 20px;
  font-weight: 600;
  font-size: 0.85rem;
  min-width: 50px;
}

.quantity-badge.ordered {
  background: #e9ecef;
  color: #495057;
}

.quantity-badge.received {
  background: #d4edda;
  color: #155724;
}

/* Input Cells */
.input-cell {
  min-width: 120px;
}

.input-wrapper {
  position: relative;
  width: 100%;
}

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
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
  outline: none;
}

.modern-input.error {
  border-color: #ef4444;
  background: #fef2f2;
}

.modern-input:disabled {
  background: #f8fafc;
  border-color: #e2e8f0;
  color: #94a3b8;
  cursor: not-allowed;
}

.modern-input.with-currency {
  padding-left: 1.5rem;
}

.currency-symbol {
  position: absolute;
  left: 0.5rem;
  top: 50%;
  transform: translateY(-50%);
  color: #64748b;
  font-weight: 500;
  font-size: 0.9rem;
}

.currency-value {
  font-weight: 600;
  color: #0f172a;
}

/* Max Hint */
.max-hint {
  display: block;
  font-size: 0.6rem;
  color: #64748b;
  margin-top: 0.25rem;
  text-align: right;
}

/* Error Message */
.error-message {
  position: absolute;
  bottom: -1.5rem;
  left: 0;
  font-size: 0.6rem;
  color: #ef4444;
  white-space: nowrap;
}

.field-error {
  margin-top: 0.25rem;
  font-size: 0.65rem;
  color: #ef4444;
  line-height: 1.2;
}

/* Expiry Cell */
.expiry-cell {
  min-width: 150px;
}

.expiry-control {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

/* Custom Checkbox */
.checkbox-container {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  font-size: 0.85rem;
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

.checkbox-container:hover input~.checkmark {
  border-color: #667eea;
}

.checkbox-container input:checked~.checkmark {
  background-color: #667eea;
  border-color: #667eea;
}

.checkmark:after {
  content: "";
  position: absolute;
  display: none;
}

.checkbox-container input:checked~.checkmark:after {
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

.checkbox-label {
  color: #334155;
}

/* Date Input */
.date-input-wrapper {
  position: relative;
  margin-top: 0.25rem;
}

.date-input-wrapper i {
  position: absolute;
  left: 0.5rem;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
  font-size: 1rem;
  pointer-events: none;
}

.date-input {
  padding-left: 2rem !important;
  font-size: 0.85rem;
}

/* Disabled Row */
.disabled-row {
  opacity: 0.7;
  background: #f8fafc;
}

.disabled-row td {
  background: #f8fafc;
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

/* Transitions */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* Responsive */
@media (max-width: 1200px) {
  .purchase-order-table {
    font-size: 0.85rem;
  }

  .input-cell {
    min-width: 100px;
  }
}

@media (max-width: 992px) {
  .table-responsive {
    overflow-x: auto;
  }

  .purchase-order-table {
    min-width: 1000px;
  }
}
</style>
