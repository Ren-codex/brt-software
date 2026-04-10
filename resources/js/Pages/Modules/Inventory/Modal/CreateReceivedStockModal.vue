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
          <button type="button" class="btn btn-cancel" @click="onCancel" :disabled="isSubmitting">Cancel</button>
          <button type="button" class="btn btn-save" @click="handleSubmit" :disabled="isSubmitting">Submit</button>
        </div>
      </div>
    </div>
  </div>

  <div v-if="showPaymentMethodModal" class="modal-overlay active payment-method-overlay" @click.self="closePaymentMethodModal">
    <div class="modal-container modal-md payment-method-modal">
      <div class="modal-header">
        <h2>Select Payment Method</h2>
        <button type="button" class="close-btn" @click="closePaymentMethodModal" :disabled="isSubmitting">&times;</button>
      </div>
      <div class="modal-body">
        <div class="payment-summary-card">
          <span class="payment-summary-label">Total Purchase Cost</span>
          <strong class="payment-summary-value">{{ formatCurrency(totalReceiveAmount) }}</strong>
        </div>

        <div class="payment-option-grid">
          <button
            v-for="option in paymentTypes"
            :key="option.value"
            type="button"
            class="payment-option-card"
            :class="{ 'selected': selectedPaymentType === option.value }"
            @click="selectPaymentType(option.value)"
          >
            <span class="payment-option-icon">
              <i :class="option.icon"></i>
            </span>
            <span class="payment-option-title">{{ option.label }}</span>
            <small class="payment-option-copy">{{ option.description }}</small>
          </button>
        </div>

        <div v-if="selectedPaymentType === 'Cash'" class="payment-suboption-panel">
          <div class="payment-panel-heading">
            <span class="payment-panel-kicker">Cash Settlement</span>
            <h5>Choose how you will pay the supplier</h5>
            <p>Select either direct cash payment or bank transfer.</p>
          </div>

          <div class="payment-option-grid suboption-grid">
            <button
              v-for="option in cashPaymentModes"
              :key="option.value"
              type="button"
              class="payment-option-card payment-suboption-card"
              :class="{ 'selected': selectedCashPaymentMode === option.value }"
              @click="selectCashPaymentMode(option.value)"
            >
              <span class="payment-option-icon">
                <i :class="option.icon"></i>
              </span>
              <span class="payment-option-title">{{ option.label }}</span>
              <small class="payment-option-copy">{{ option.description }}</small>
            </button>
          </div>
          <div class="payment-suboption-note">
            Select <strong>Cash</strong> or <strong>Bank Transfer</strong> to open the amount-paid modal.
          </div>
        </div>

        <div v-if="paymentMethodError" class="alert alert-danger mt-3 mb-0" role="alert">
          {{ paymentMethodError }}
        </div>
      </div>
      <div class="modal-footer">
        <div class="form-actions">
          <button type="button" class="btn btn-cancel" @click="closePaymentMethodModal" :disabled="isSubmitting">Back</button>
          <button
            type="button"
            class="btn btn-save"
            @click="confirmPaymentMethod"
            :disabled="isSubmitting || selectedPaymentType === 'Cash'"
          >
            {{
              isSubmitting
                ? 'Submitting...'
                : selectedPaymentType === 'Credit'
                  ? 'Confirm Payment Method'
                  : selectedPaymentType === 'Cash'
                    ? 'Choose Cash or Bank Transfer'
                    : 'Select Payment Method'
            }}
          </button>
        </div>
      </div>
    </div>
  </div>

  <div v-if="showAmountPaidModal" class="modal-overlay active payment-method-overlay" @click.self="backToPaymentMethodModal">
    <div class="modal-container modal-md payment-method-modal amount-paid-modal">
      <div class="modal-header">
        <h2>{{ isBankTransferMode ? 'Bank Transfer Details' : 'Total Amount Paid' }}</h2>
        <button type="button" class="close-btn" @click="backToPaymentMethodModal" :disabled="isSubmitting">&times;</button>
      </div>
      <div class="modal-body">
        <div class="payment-summary-card">
          <span class="payment-summary-label">Total Purchase Cost</span>
          <strong class="payment-summary-value">{{ formatCurrency(totalReceiveAmount) }}</strong>
        </div>

        <div class="payment-suboption-panel payment-amount-dialog">
          <div class="payment-panel-heading">
            <span class="payment-panel-kicker">{{ selectedCashPaymentMode }}</span>
            <h5>{{ isBankTransferMode ? 'Enter the bank transfer details' : 'Enter the total amount already paid' }}</h5>
            <p>
              {{
                isBankTransferMode
                  ? 'Provide the bank information together with the amount already transferred.'
                  : 'Any remaining balance after this payment will stay under accounts payable.'
              }}
            </p>
          </div>

          <div v-if="isBankTransferMode" class="payment-detail-grid">
            <div class="payment-detail-field">
              <label for="received_stock_bank_name" class="payment-detail-label">
                Bank Name
              </label>
              <input
                id="received_stock_bank_name"
                v-model.trim="form.bank_name"
                type="text"
                class="form-control payment-detail-input"
                placeholder="Enter bank name"
                @input="paymentMethodError = ''"
              />
            </div>

            <div class="payment-detail-field">
              <label for="received_stock_reference_number" class="payment-detail-label">
                Reference Number
              </label>
              <input
                id="received_stock_reference_number"
                v-model.trim="form.reference_number"
                type="text"
                class="form-control payment-detail-input"
                placeholder="Enter transfer reference number"
                @input="paymentMethodError = ''"
              />
            </div>
          </div>

          <div class="payment-amount-panel">
            <label for="received_stock_amount_paid" class="payment-amount-label">
              {{ isBankTransferMode ? 'Amount Paid' : 'Total Amount Paid' }}
            </label>
            <div class="payment-amount-input-wrap">
              <span class="payment-amount-symbol">₱</span>
              <input
                id="received_stock_amount_paid"
                v-model.number="form.amount_paid"
                type="number"
                min="0"
                :max="totalReceiveAmount"
                step="0.01"
                class="form-control payment-amount-input"
                :class="{ 'input-error': paymentMethodError && selectedPaymentType === 'Cash' }"
                placeholder="Enter total amount paid"
                @input="paymentMethodError = ''"
              />
            </div>
            <div class="payment-amount-meta">
              <span>Total Due: {{ formatCurrency(totalReceiveAmount) }}</span>
              <strong v-if="remainingPayableAmount > 0">
                Remaining Payable: {{ formatCurrency(remainingPayableAmount) }}
              </strong>
              <strong v-else>
                Fully paid
              </strong>
            </div>
          </div>

          <div v-if="paymentMethodError" class="alert alert-danger mt-3 mb-0" role="alert">
            {{ paymentMethodError }}
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <div class="form-actions">
          <button type="button" class="btn btn-cancel" @click="backToPaymentMethodModal" :disabled="isSubmitting">Back</button>
          <button type="button" class="btn btn-save" @click="confirmPaymentMethod" :disabled="isSubmitting">
            {{ isSubmitting ? 'Submitting...' : `Confirm ${selectedCashPaymentMode} Payment` }}
          </button>
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
        payment_mode: '',
        amount_paid: null,
        bank_name: '',
        reference_number: '',
        items: [],
      },
      errorMessage: '',
      purchaseOrder: [],
      submitAttempted: false,
      showPaymentMethodModal: false,
      showAmountPaidModal: false,
      selectedPaymentType: null,
      selectedCashPaymentMode: null,
      paymentMethodError: '',
      isSubmitting: false,
      paymentTypes: [
        {
          value: 'Cash',
          label: 'Cash',
          description: 'Pay now using cash or bank transfer',
          icon: 'ri-money-dollar-circle-line',
        },
        {
          value: 'Credit',
          label: 'Credit',
          description: 'Record this receipt under accounts payable',
          icon: 'ri-bank-card-line',
        },
      ],
      cashPaymentModes: [
        {
          value: 'Cash',
          label: 'Cash',
          description: 'Immediate physical cash payment',
          icon: 'ri-coins-line',
        },
        {
          value: 'Bank Transfer',
          label: 'Bank Transfer',
          description: 'Immediate payment through bank transfer',
          icon: 'ri-exchange-funds-line',
        },
      ],
    };
  },
  computed: {
    totalReceiveAmount() {
      return (this.form.items || []).reduce((total, item) => {
        const toReceive = this.toNumber(item.to_received_quantity) ?? 0;
        const unitCost = this.toNumber(item.unit_cost) ?? 0;

        return total + (toReceive * unitCost);
      }, 0);
    },
    remainingPayableAmount() {
      const amountPaid = this.toNumber(this.form.amount_paid) ?? 0;
      return Math.max(this.totalReceiveAmount - amountPaid, 0);
    },
    isBankTransferMode() {
      return this.selectedPaymentType === 'Cash' && this.selectedCashPaymentMode === 'Bank Transfer';
    },
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
    clearBankTransferDetails() {
      this.form.bank_name = '';
      this.form.reference_number = '';
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
      this.resetForm();
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
        payment_mode: '',
        amount_paid: null,
        bank_name: '',
        reference_number: '',
        items: [],
      };
      this.errorMessage = '';
      this.submitAttempted = false;
      this.showPaymentMethodModal = false;
      this.showAmountPaidModal = false;
      this.selectedPaymentType = null;
      this.selectedCashPaymentMode = null;
      this.paymentMethodError = '';
      this.isSubmitting = false;
    },
    validateForm() {
      this.submitAttempted = true;
      this.errorMessage = '';

      let hasReceivableItem = false;

      for (const item of this.form.items) {
        const maxAllowed = item.quantity - item.received_quantity;
        const toReceive = this.toNumber(item.to_received_quantity) ?? 0;
        const unitCost = this.toNumber(item.unit_cost) ?? 0;
        const retailPrice = this.toNumber(item.retail_price);
        const wholesalePrice = this.toNumber(item.wholesale_price);
        const productName = this.getProductName(item);

        if (toReceive > maxAllowed) {
          this.errorMessage = `To receive quantity for ${productName} must not exceed ${maxAllowed}.`;
          return false;
        }

        if (toReceive < 0) {
          this.errorMessage = `To receive quantity for ${productName} must be 0 or greater.`;
          return false;
        }

        if (toReceive > 0) {
          hasReceivableItem = true;

          if (retailPrice === null) {
            this.errorMessage = `Retail price is required for ${productName} when "To Receive" has a value.`;
            return false;
          }

          if (wholesalePrice === null) {
            this.errorMessage = `Wholesale price is required for ${productName} when "To Receive" has a value.`;
            return false;
          }

          if (retailPrice < unitCost) {
            this.errorMessage = `Retail price for ${productName} must not be less than unit cost (${this.formatCurrency(unitCost)}).`;
            return false;
          }

          if (wholesalePrice < unitCost) {
            this.errorMessage = `Wholesale price for ${productName} must be at least unit cost (${this.formatCurrency(unitCost)}).`;
            return false;
          }

          if (wholesalePrice > retailPrice) {
            this.errorMessage = `Wholesale price for ${productName} must be at most retail price (${this.formatCurrency(retailPrice)}).`;
            return false;
          }
        }

        if (retailPrice !== null && retailPrice < 0) {
          this.errorMessage = `Retail price for ${productName} must be 0 or greater.`;
          return false;
        }

        if (wholesalePrice !== null && wholesalePrice < 0) {
          this.errorMessage = `Wholesale price for ${productName} must be 0 or greater.`;
          return false;
        }
      }

      if (!hasReceivableItem) {
        this.errorMessage = 'Please enter a quantity greater than 0 for at least one item before submitting.';
        return false;
      }

      return true;
    },
    handleSubmit() {
      if (!this.validateForm()) {
        return;
      }

      this.selectedPaymentType = null;
      this.selectedCashPaymentMode = null;
      this.form.amount_paid = this.totalReceiveAmount > 0 ? this.totalReceiveAmount : null;
      this.paymentMethodError = '';
      this.showAmountPaidModal = false;
      this.showPaymentMethodModal = true;
    },
    selectPaymentType(type) {
      this.selectedPaymentType = type;
      this.paymentMethodError = '';

      if (type === 'Cash' && (this.form.amount_paid === null || this.form.amount_paid === '' || Number(this.form.amount_paid) <= 0)) {
        this.form.amount_paid = this.totalReceiveAmount > 0 ? this.totalReceiveAmount : null;
      }

      if (type === 'Credit') {
        this.form.amount_paid = 0;
        this.showAmountPaidModal = false;
        this.clearBankTransferDetails();
      }
    },
    selectCashPaymentMode(mode) {
      if (this.isSubmitting) return;

      this.selectedPaymentType = 'Cash';
      this.selectedCashPaymentMode = mode;
      this.paymentMethodError = '';

      if (mode !== 'Bank Transfer') {
        this.clearBankTransferDetails();
      }

      if (this.form.amount_paid === null || this.form.amount_paid === '' || Number(this.form.amount_paid) <= 0) {
        this.form.amount_paid = this.totalReceiveAmount > 0 ? this.totalReceiveAmount : null;
      }

      this.showPaymentMethodModal = false;
      this.showAmountPaidModal = true;
    },
    backToPaymentMethodModal() {
      if (this.isSubmitting) return;

      this.showAmountPaidModal = false;
      this.showPaymentMethodModal = true;
      this.paymentMethodError = '';
    },
    closePaymentMethodModal() {
      if (this.isSubmitting) return;

      this.showPaymentMethodModal = false;
      this.showAmountPaidModal = false;
      this.paymentMethodError = '';
      this.selectedPaymentType = null;
      this.selectedCashPaymentMode = null;
      this.form.payment_mode = '';
      this.form.amount_paid = null;
      this.clearBankTransferDetails();
    },
    async confirmPaymentMethod() {
      if (!this.selectedPaymentType) {
        this.paymentMethodError = 'Please choose Cash or Credit before continuing.';
        return;
      }

      if (this.selectedPaymentType === 'Cash' && !this.selectedCashPaymentMode) {
        this.paymentMethodError = 'Please choose Cash or Bank Transfer.';
        return;
      }

      if (this.selectedPaymentType === 'Cash') {
        const amountPaid = this.toNumber(this.form.amount_paid);

        if (amountPaid === null) {
          this.paymentMethodError = 'Please enter the total amount paid.';
          return;
        }

        if (amountPaid <= 0) {
          this.paymentMethodError = 'Total amount paid must be greater than zero.';
          return;
        }

        if (amountPaid > this.totalReceiveAmount) {
          this.paymentMethodError = 'Total amount paid cannot exceed the total purchase cost.';
          return;
        }

        if (this.isBankTransferMode) {
          if (!String(this.form.bank_name || '').trim()) {
            this.paymentMethodError = 'Please enter the bank name for this transfer.';
            return;
          }

          if (!String(this.form.reference_number || '').trim()) {
            this.paymentMethodError = 'Please enter the transfer reference number.';
            return;
          }
        } else {
          this.clearBankTransferDetails();
        }
      }

      this.form.payment_mode = this.selectedPaymentType === 'Credit'
        ? 'Credit'
        : this.selectedCashPaymentMode;

      if (this.selectedPaymentType === 'Credit') {
        this.form.amount_paid = 0;
        this.clearBankTransferDetails();
      }

      await this.submitReceivedStock();
    },
    async submitReceivedStock() {
      this.isSubmitting = true;
      this.errorMessage = '';

      try {
        await axios.post('/received-stocks', this.form);
        this.hide();
        this.$emit('add', 'success');
        this.$inertia.reload();
      } catch (error) {
        console.error('Error submitting received stock:', error);
        this.showPaymentMethodModal = false;
        this.showAmountPaidModal = false;
        this.errorMessage = error?.response?.data?.message || 'An error occurred while submitting. Please try again.';
      } finally {
        this.isSubmitting = false;
      }
    },
    hide() {
      this.showModal = false;
      this.resetForm();
    },
    onCancel() {
      if (this.isSubmitting) return;

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

.payment-method-overlay {
  z-index: 1100;
}

.payment-method-modal {
  max-width: 640px;
}

.amount-paid-modal {
  max-width: 560px;
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

.payment-summary-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem 1.25rem;
  margin-bottom: 1.25rem;
  border: 1px solid rgba(61, 141, 122, 0.18);
  border-radius: 16px;
  background: linear-gradient(135deg, rgba(61, 141, 122, 0.08), rgba(61, 141, 122, 0.02));
}

.payment-summary-label {
  color: #527267;
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.payment-summary-value {
  color: #16423c;
  font-size: 1.2rem;
}

.payment-option-grid {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.payment-option-card {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.6rem;
  padding: 1.1rem;
  border: 1px solid #d7e4df;
  border-radius: 18px;
  background: #fff;
  color: #1f2937;
  text-align: left;
  transition: all 0.2s ease;
}

.payment-option-card:hover {
  border-color: #3d8d7a;
  box-shadow: 0 12px 28px rgba(22, 66, 60, 0.08);
  transform: translateY(-1px);
}

.payment-option-card.selected {
  border-color: #3d8d7a;
  background: linear-gradient(180deg, #f4fbf8 0%, #ffffff 100%);
  box-shadow: 0 14px 30px rgba(22, 66, 60, 0.1);
}

.payment-option-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border-radius: 14px;
  background: #e6f2ef;
  color: #2f7666;
  font-size: 1.15rem;
}

.payment-option-title {
  font-size: 1rem;
  font-weight: 700;
  color: #1e293b;
}

.payment-option-copy {
  color: #64748b;
  line-height: 1.45;
}

.payment-suboption-panel {
  margin-top: 1.25rem;
  padding: 1rem 1.1rem;
  border: 1px solid #dfeae6;
  border-radius: 18px;
  background: #f8fcfb;
}

.payment-amount-dialog {
  margin-top: 0;
}

.payment-suboption-note {
  margin-top: 1rem;
  color: #527267;
  font-size: 0.9rem;
  line-height: 1.5;
}

.payment-detail-grid {
  display: grid;
  gap: 1rem;
  margin-top: 1rem;
}

.payment-detail-field {
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
}

.payment-detail-label {
  color: #16423c;
  font-weight: 700;
}

.payment-detail-input {
  border: 1px solid #cfe0d9;
  border-radius: 14px;
  min-height: 48px;
}

.payment-amount-panel {
  margin-top: 1rem;
}

.payment-amount-label {
  display: block;
  margin-bottom: 0.5rem;
  color: #16423c;
  font-weight: 700;
}

.payment-amount-input-wrap {
  position: relative;
}

.payment-amount-symbol {
  position: absolute;
  left: 0.85rem;
  top: 50%;
  transform: translateY(-50%);
  color: #527267;
  font-weight: 700;
}

.payment-amount-input {
  padding-left: 2rem;
  border: 1px solid #cfe0d9;
  border-radius: 14px;
  min-height: 48px;
}

.payment-amount-input.input-error {
  border-color: #dc3545;
  box-shadow: 0 0 0 0.15rem rgba(220, 53, 69, 0.12);
}

.payment-amount-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-top: 0.65rem;
  color: #527267;
  font-size: 0.9rem;
}

.payment-panel-heading h5 {
  margin-bottom: 0.25rem;
  color: #16423c;
  font-weight: 700;
}

.payment-panel-heading p {
  margin-bottom: 1rem;
  color: #64748b;
}

.payment-panel-kicker {
  display: inline-block;
  margin-bottom: 0.35rem;
  color: #3d8d7a;
  font-size: 0.75rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.suboption-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr));
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

  .payment-option-grid,
  .suboption-grid {
    grid-template-columns: 1fr;
  }

  .payment-amount-meta {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
