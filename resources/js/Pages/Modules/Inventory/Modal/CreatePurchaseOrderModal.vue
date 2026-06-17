<template>
  <Teleport to="body">
    <div v-if="showModal" class="modal-overlay active" @click.self="hide">
        <div class="modal-container modal-fullscreen" @click.stop>
            <div class="modal-header">
                <div class="header-title">
                    <i :class="editable ? 'ri-edit-box-line' : 'ri-shopping-bag-3-line'"></i>
                    <h2>{{ editable ? 'Update Purchase Request' : 'Create Purchase Request' }}</h2>
                </div>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body modal-body-lg">
                <form @submit.prevent="submit" class="purchase-order-form">
                    <div class="form-layout">
                        <section class="form-panel form-panel-main">
                            <div class="panel-head">
                                <div>
                                    <p class="panel-kicker">Supplier Setup</p>
                                    <h3 class="panel-title">Purchase Request Details</h3>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Select Supplier</label>
                                <div class="input-wrapper">
                                    <i class="ri-store-line input-icon"></i>
                                    <select v-model="form.supplier_id" class="form-control"
                                        :class="{ 'input-error': form.errors.supplier_id }"
                                        @change="handleInput('supplier_id')">
                                        <option value="" disabled>Choose a supplier...</option>
                                        <option v-for="supplier in dropdowns.suppliers" :value="supplier.value"
                                            :key="supplier.value">
                                            {{ supplier.name }}
                                        </option>
                                    </select>
                                </div>
                                <span class="error-message" v-if="form.errors.supplier_id">{{ form.errors.supplier_id }}</span>
                            </div>

                            <div class="section-divider">
                                <div>
                                    <p class="panel-kicker mb-1">Line Items</p>
                                    <h4 class="section-title mb-0">Order Items</h4>
                                </div>
                                <button type="button" @click="addItem" class="btn-add-item">
                                    <i class="ri-add-line"></i>
                                    Add Item
                                </button>
                            </div>

                            <div class="items-table-wrap">
                                <table class="table align-middle mb-0 pretty-table">
                                    <thead class="pretty-header">
                                        <tr class="fs-11">
                                            <th style="width: 4%;">#</th>
                                            <th style="width: 30%;">Product</th>
                                            <th style="width: 16%;" class="text-center">Quantity</th>
                                            <th style="width: 18%;" class="text-center">Unit Cost</th>
                                            <th style="width: 18%;" class="text-center">Total Cost</th>
                                            <th style="width: 14%;" class="text-center">Actions</th>
                                        </tr>
                                    </thead>

                                    <tbody class="table-white fs-12">
                                        <tr v-if="form.items.length === 0">
                                            <td colspan="6" class="empty-table">
                                                <div class="empty-table-inner">
                                                    <i class="ri-shopping-cart-line"></i>
                                                    <h4>No Items Added Yet</h4>
                                                    <p>Start this request by adding your first product line item.</p>
                                                    <button type="button" @click="addItem" class="btn-add-item">
                                                        <i class="ri-add-line"></i>
                                                        Add Your First Item
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-for="(item, index) in form.items" :key="index">
                                            <td>{{ index + 1 }}</td>
                                            <td>
                                                <select v-model="item.product_id" class="form-control form-control-plain table-select"
                                                    :class="{ 'input-error': form.errors[`items.${index}.product_id`], 'input-warning': isDuplicateProduct(index) }"
                                                    @change="handleInput(`items.${index}.product_id`)">
                                                    <option value="" disabled>Select a product...</option>
                                                    <option v-for="product in dropdowns.products" :value="product.value"
                                                        :key="product.value">
                                                        {{ product.name }}
                                                    </option>
                                                </select>
                                                <span class="error-message" v-if="form.errors[`items.${index}.product_id`]">
                                                    {{ form.errors[`items.${index}.product_id`] }}
                                                </span>
                                                <span class="warning-message" v-else-if="isDuplicateProduct(index)">
                                                    <i class="ri-error-warning-line"></i> Already in list
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="quantity-control">
                                                    <button type="button" class="qty-btn"
                                                        @click="adjustQuantity(item, 'decrease')"
                                                        :disabled="item.quantity <= 0">
                                                        <i class="ri-subtract-line"></i>
                                                    </button>
                                                    <input type="number" v-model="item.quantity" class="form-control form-control-plain table-qty-input"
                                                        :class="{ 'input-error': form.errors[`items.${index}.quantity`] }"
                                                        @input="calculateTotal(item); handleInput(`items.${index}.quantity`)"
                                                        step="1" min="0" :max="MAX_QUANTITY" required @change="validateQuantity(item)">
                                                    <button type="button" class="qty-btn"
                                                        @click="adjustQuantity(item, 'increase')">
                                                        <i class="ri-add-line"></i>
                                                    </button>
                                                </div>
                                                <span class="error-message" v-if="form.errors[`items.${index}.quantity`]">
                                                    {{ form.errors[`items.${index}.quantity`] }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="money-input">
                                                    <span class="money-prefix">PHP</span>
                                                    <input type="number" v-model="item.unit_cost" class="form-control form-control-plain no-spinner"
                                                        :class="{ 'input-error': form.errors[`items.${index}.unit_cost`] }"
                                                        @input="calculateTotal(item); handleInput(`items.${index}.unit_cost`)"
                                                        step="any" min="0" :max="MAX_UNIT_COST" required>
                                                </div>
                                                <span class="error-message" v-if="form.errors[`items.${index}.unit_cost`]">
                                                    {{ form.errors[`items.${index}.unit_cost`] }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <strong class="cell-total-amount">{{ formatCurrency(item.total_cost) }}</strong>
                                            </td>
                                            <td class="text-center">
                                                <div class="action-buttons">
                                                    <button type="button" class="table-action-btn" @click="duplicateItem(index)"
                                                        title="Duplicate">
                                                        <i class="ri-file-copy-line"></i>
                                                    </button>
                                                    <button type="button" class="table-action-btn danger" @click="removeItem(index)"
                                                        title="Remove">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>

                                    <tfoot v-if="form.items.length > 0">
                                        <tr>
                                            <td colspan="4" class="text-end footer-label">Totals</td>
                                            <td class="text-center footer-value">{{ formatCurrency(totalAmount) }}</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </section>

                        <aside class="form-panel form-panel-side">
                            <div class="panel-head">
                                <div>
                                    <p class="panel-kicker">Live Summary</p>
                                    <h3 class="panel-title">Request Overview</h3>
                                </div>
                            </div>

                            <div class="summary-metrics">
                                <div class="summary-metric-card">
                                    <span class="summary-metric-label">Line Items</span>
                                    <strong class="summary-metric-value">{{ form.items.length }}</strong>
                                </div>
                                <div class="summary-metric-card">
                                    <span class="summary-metric-label">Total Quantity</span>
                                    <strong class="summary-metric-value">{{ totalQuantity }}</strong>
                                </div>
                            </div>

                            <div class="summary-section">
                                <div class="summary-header">
                                    <h3>Order Summary</h3>
                                </div>
                                <div class="summary-content">
                                    <div class="summary-row">
                                        <span>Number of Items</span>
                                        <span class="summary-value">{{ form.items.length }}</span>
                                    </div>
                                    <div class="summary-row">
                                        <span>Total Quantity</span>
                                        <span class="summary-value">{{ totalQuantity }}</span>
                                    </div>
                                    <div class="summary-row total-row">
                                        <span>Total Amount</span>
                                        <span class="summary-value total-amount">{{ formatCurrency(totalAmount) }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="hint-card">
                                <div class="hint-icon">
                                    <i class="ri-information-line"></i>
                                </div>
                                <div>
                                    <h4 class="hint-title">Validation Guide</h4>
                                    <p class="hint-text mb-0">
                                        Each item needs a product, valid quantity, and unit cost before submission.
                                    </p>
                                </div>
                            </div>
                        </aside>
                    </div>

                    <div class="feedback-stack">
                        <div class="error-banner" v-if="submitError">
                            <i class="ri-error-warning-line"></i>
                            <span>{{ submitError }}</span>
                        </div>
                        <div class="success-alert" v-if="saveSuccess">
                            <i class="ri-checkbox-circle-fill"></i>
                            <span>Purchase request has been {{ editable ? 'updated' : 'created' }} successfully.</span>
                        </div>
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" @click="hide">
                    <i class="ri-close-line"></i>
                    Cancel
                </button>
                <button type="button" class="btn btn-save"
                    :disabled="form.processing || form.items.length === 0"
                    @click="submit">
                    <i class="ri-save-line" v-if="!form.processing"></i>
                    <i class="ri-loader-4-line spinner" v-else></i>
                    {{ form.processing ? 'Processing...' : (editable ? 'Update Request' : 'Create Request') }}
                </button>
            </div>
        </div>
    </div>
  </Teleport>
</template>

<script>
import { useForm } from '@inertiajs/vue3';

export default {
    name: "CreatePurchaseOrderModal",
    props: ['dropdowns'],
    emits: ['add'],
    data() {
        return {
            MAX_ITEM_TOTAL: 9999999999999.99,
            MAX_UNIT_COST: 99999999.99,
            MAX_QUANTITY: 2147483647,
            form: useForm({
                id: null,
                supplier_id: null,
                total_amount: 0,
                items: [],
            }),
            showModal: false,
            editable: false,
            saveSuccess: false,
            submitError: '',
        };
    },
    computed: {
        totalAmount() {
            return this.form.items.reduce((sum, item) => sum + (parseFloat(item.total_cost) || 0), 0);
        },
        totalQuantity() {
            return this.form.items.reduce((sum, item) => sum + (parseInt(item.quantity) || 0), 0);
        },
    },




    mounted() {
        document.addEventListener('keydown', this._onEscape);
    },
    beforeUnmount() {
        document.removeEventListener('keydown', this._onEscape);
    },
    methods: {
        _onEscape(e) {
            if (e.key === 'Escape' && this.showModal) this.hide();
        },
        isDuplicateProduct(index) {
            const productId = this.form.items[index]?.product_id;
            if (!productId) return false;
            return this.form.items.some((other, i) => i !== index && other.product_id === productId);
        },
        show() {
            this.form.reset();
            this.form.clearErrors();
            this.submitError = '';

            this.form.items = [
                {
                    product_id: null,
                    quantity: 0,
                    unit_cost: '',
                    total_cost: 0,
                }
            ];
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = true;
        },

        edit(data) {
            this.form.reset();
            this.form.clearErrors();
            this.form.id = data.id;
            this.form.supplier_id = data.supplier ? data.supplier.id : null;
            this.submitError = '';
            this.form.items = data.items ? data.items.map(item => ({
                product_id: item.product.id,
                quantity: item.quantity,
                unit_cost: item.unit_cost != null ? String(item.unit_cost) : '',
                total_cost: item.total_cost || 0,
            })) : [];

            this.editable = true;
            this.saveSuccess = false;
            this.showModal = true;
        },

        submit() {
            this.form.total_amount = this.totalAmount;
            this.submitError = '';

            if (!this.form.supplier_id) {
                this.submitError = 'Please select a supplier.';
                return;
            }

            const formattedItems = this.form.items.map(item => ({
                product_id: item.product_id,
                quantity: parseInt(item.quantity) || 0,
                unit_cost: parseFloat(item.unit_cost) || 0,
                total_cost: this.roundTo2((parseInt(item.quantity) || 0) * (parseFloat(item.unit_cost) || 0)),
            }));

            const hasInvalidItem = formattedItems.some((item) => {
                return !item.product_id
                    || item.quantity <= 0
                    || item.quantity > this.MAX_QUANTITY
                    || item.unit_cost < 0
                    || item.unit_cost > this.MAX_UNIT_COST
                    || item.total_cost > this.MAX_ITEM_TOTAL;
            });

            if (hasInvalidItem) {
                this.submitError = `Each item must have a product, quantity 1-${this.MAX_QUANTITY}, unit cost 0-${this.MAX_UNIT_COST.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}, and total not above ${this.MAX_ITEM_TOTAL.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}.`;
                return;
            }

            this.form.items = formattedItems;

            if (this.editable) {
                this.form.put(`/purchase-orders/${this.form.id}`, {
                    preserveScroll: true,
                    onSuccess: () => {
                        const flashStatus = this.$page?.props?.flash?.status;
                        const flashMessage = this.$page?.props?.flash?.message;
                        const flashInfo = this.$page?.props?.flash?.info;

                        if (flashStatus === false) {
                            this.saveSuccess = false;
                            this.submitError = flashInfo || flashMessage || 'Purchase request was not saved. Please try again.';
                            return;
                        }

                        this.saveSuccess = true;
                        this.submitError = '';
                        setTimeout(() => {
                            this.$emit('add', { action: 'updated' });
                            this.form.reset();
                            this.hide();
                        }, 1500);
                    },
                    onError: () => {
                        this.saveSuccess = false;
                        const firstError = Object.values(this.form.errors || {}).flat()[0];
                        this.submitError = firstError || 'Failed to update purchase request. Please check the form and try again.';
                    }
                });
            } else {
                this.form.post('/purchase-orders', {
                    preserveScroll: true,
                    onSuccess: () => {
                        const flashStatus = this.$page?.props?.flash?.status;
                        const flashMessage = this.$page?.props?.flash?.message;
                        const flashInfo = this.$page?.props?.flash?.info;

                        if (flashStatus === false) {
                            this.saveSuccess = false;
                            this.submitError = flashInfo || flashMessage || 'Purchase request was not saved. Please try again.';
                            return;
                        }

                        this.saveSuccess = true;
                        this.submitError = '';
                        setTimeout(() => {
                            this.$emit('add', { action: 'created' });
                            this.form.reset();
                            this.hide();
                        }, 1500);
                    },
                    onError: () => {
                        this.saveSuccess = false;
                        const firstError = Object.values(this.form.errors || {}).flat()[0];
                        this.submitError = firstError || 'Failed to create purchase request. Please check the form and try again.';
                    }
                });
            }
        },

        handleInput(field) {
            this.form.errors[field] = false;
        },

        hide() {
            this.form.reset();
            this.form.clearErrors();
            this.editable = false;
            this.saveSuccess = false;
            this.submitError = '';
            this.showModal = false;
        },

        addItem() {
            this.form.items.push({
                product_id: null,
                quantity: 0,
                unit_cost: '',
                total_cost: 0,
            });
        },

        removeItem(index) {
            if (this.form.items.length > 1) {
                this.form.items.splice(index, 1);
            } else {
                this.form.items[0] = {
                    product_id: null,
                    quantity: 0,
                    unit_cost: '',
                    total_cost: 0,
                };
            }
        },

        duplicateItem(index) {
            const itemToDuplicate = { ...this.form.items[index] };
            this.form.items.splice(index + 1, 0, {
                quantity: itemToDuplicate.quantity,
                unit_cost: itemToDuplicate.unit_cost ?? '',
                total_cost: itemToDuplicate.total_cost,
                product_id: itemToDuplicate.product_id,
            });
        },

        calculateTotal(item) {
            const quantity = parseInt(item.quantity) || 0;
            const unitCost = parseFloat(item.unit_cost) || 0;
            item.total_cost = this.roundTo2(quantity * unitCost);
        },

        adjustQuantity(item, action) {
            let currentQuantity = parseFloat(item.quantity) || 0;

            if (action === 'increase') {
                item.quantity = currentQuantity + 1;
            } else if (action === 'decrease' && currentQuantity > 0) {
                item.quantity = currentQuantity - 1;
            }

            this.calculateTotal(item);
        },

        validateQuantity(item) {
            if (item.quantity < 0) {
                item.quantity = 0;
                this.calculateTotal(item);
            }
            if ((parseInt(item.quantity) || 0) > this.MAX_QUANTITY) {
                item.quantity = this.MAX_QUANTITY;
                this.calculateTotal(item);
            }
        },

        roundTo2(value) {
            return Math.round(((Number(value) || 0) + Number.EPSILON) * 100) / 100;
        },

        formatCurrency(amount) {
            return new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP'
            }).format(amount);
        },
    },
};
</script>



<style scoped>
.modal-container {
    max-width: 100%;
    width: 100%;
    max-height: calc(100vh - 2rem);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.modal-header {
    padding: 0.65rem 1rem;
}

.header-title {
    display: flex;
    align-items: center;
    gap: 10px;
}

.header-title i {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    display: grid;
    place-items: center;
    background: rgba(61, 141, 122, 0.12);
    border: 1px solid rgba(61, 141, 122, 0.16);
    color: #3d8d7a;
    font-size: 14px;
    flex-shrink: 0;
}

.header-title h2 {
    margin: 0;
    font-size: 0.9rem;
    color: #16322e;
    font-weight: 700;
}

.modal-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    padding: 0.9rem 1.1rem 1.1rem;
}

.modal-footer {
    flex-shrink: 0;
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    padding: 0.65rem 1rem;
    border-top: 1px solid #e2e8f0;
}

.form-layout {
    display: grid;
    grid-template-columns: minmax(0, 2.35fr) minmax(260px, 0.85fr);
    gap: 0.85rem;
    align-items: start;
}

.form-panel {
    background: #ffffff;
    border: 1px solid rgba(61, 141, 122, 0.12);
    border-radius: 16px;
    box-shadow: 0 10px 22px rgba(39, 84, 72, 0.07);
}

.form-panel-main {
    padding: 0.75rem;
}

.form-panel-side {
    padding: 0.7rem;
    position: sticky;
    top: 0;
}

.panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.7rem;
}

.panel-kicker {
    margin: 0;
    font-size: 0.64rem;
    font-weight: 700;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: #648b74;
}

.panel-title {
    margin: 0.15rem 0 0;
    color: #20413a;
    font-size: 0.92rem;
    font-weight: 700;
}

.form-group {
    margin-bottom: 0.75rem;
}

.form-label {
    display: block;
    margin-bottom: 0.3rem;
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.78rem;
}

.input-wrapper {
    position: relative;
}

.input-icon {
    position: absolute;
    left: 0.7rem;
    top: 50%;
    transform: translateY(-50%);
    color: #7f8c8d;
    font-size: 0.92rem;
    z-index: 1;
}

.form-control {
    width: 100%;
    padding: 0.5rem 0.75rem 0.5rem 2.3rem;
    border: 1px solid #d7e5de;
    border-radius: 10px;
    font-size: 0.82rem;
    transition: all 0.3s ease;
    background-color: #fdfefe;
}

.form-control:focus {
    outline: none;
    border-color: #3d8d7a;
    box-shadow: 0 0 0 4px rgba(61, 141, 122, 0.12);
}

.form-control.input-error {
    border-color: #e74c3c;
}

.form-control.input-error:focus {
    box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
}

.form-control.input-warning {
    border-color: #d97706;
    background-color: #fffbeb;
}

.form-control.input-warning:focus {
    box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.12);
}

.form-control-plain {
    padding-left: 0.75rem;
}

.no-spinner::-webkit-outer-spin-button,
.no-spinner::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.no-spinner {
    -moz-appearance: textfield;
    appearance: textfield;
}

.money-input {
    position: relative;
}

.money-prefix {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.66rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    color: #648b74;
    z-index: 1;
}

.money-input .form-control {
    padding-left: 3rem;
}

.error-message {
    display: block;
    margin-top: 0.3rem;
    font-size: 0.7rem;
    color: #e74c3c;
}

.warning-message {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    margin-top: 0.3rem;
    font-size: 0.7rem;
    color: #b45309;
    font-weight: 600;
}

.error-banner {
    background: #fee2e2;
    border: 1px solid #fecaca;
    color: #991b1b;
    border-radius: 8px;
    padding: 0.5rem 0.7rem;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.78rem;
    font-weight: 500;
}

.success-alert {
    background: linear-gradient(135deg, #e6f7ee 0%, #d8f2e3 100%);
    color: #155724;
    padding: 0.6rem 0.9rem;
    border-radius: 10px;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    animation: slideIn 0.5s ease-out;
    border: 1px solid #b9e6ca;
    font-size: 0.82rem;
}

.success-alert i {
    font-size: 1.05rem;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-8px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.section-divider {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin: 0.85rem 0 0.6rem;
    padding-top: 0.75rem;
    border-top: 1px solid #edf3f1;
}

.section-title {
    color: #20413a;
    font-size: 0.88rem;
    font-weight: 700;
}

.btn-add-item {
    background: linear-gradient(135deg, #3d8d7a 0%, #2f7464 100%);
    color: white;
    border: none;
    padding: 0.45rem 0.75rem;
    border-radius: 9px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.76rem;
    transition: all 0.3s ease;
    box-shadow: 0 8px 16px rgba(61, 141, 122, 0.18);
}

.btn-add-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 25px rgba(61, 141, 122, 0.24);
}

.items-table-wrap {
    border: 1px solid #d9ebe4;
    border-radius: 14px;
    overflow: hidden;
    background: linear-gradient(180deg, #fbfefd 0%, #f4faf8 100%);
}

.pretty-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.8rem;
}

.pretty-header {
    background: linear-gradient(140deg, #eff7f4 0%, #e6f2ed 100%);
    color: #20413a;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    font-size: 0.68rem;
}

.pretty-header th {
    border: none;
    padding: 7px 6px;
    vertical-align: middle;
    background: linear-gradient(140deg, #eff7f4 0%, #e6f2ed 100%);
}

.pretty-table tbody tr {
    transition: all 0.3s ease;
    border-bottom: 1px solid #edf3f1;
}

.pretty-table tbody tr:hover {
    background: #f4faf8;
}

.pretty-table td {
    padding: 5px 6px;
    vertical-align: middle;
    border: none;
}

.table-select {
    min-width: 160px;
    font-size: 0.8rem;
}

.table-qty-input {
    width: 76px;
    padding-left: 0.4rem;
    padding-right: 0.4rem;
    text-align: right;
}

.cell-total-amount {
    color: #059669;
    font-size: 0.82rem;
}

.action-buttons {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.3rem;
}

.table-action-btn {
    width: 27px;
    height: 27px;
    border-radius: 8px;
    border: 1px solid #d7e5de;
    background: #fff;
    color: #355f55;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    font-size: 0.78rem;
}

.table-action-btn:hover {
    background: #eff7f4;
}

.table-action-btn.danger:hover {
    background: #e74c3c;
    border-color: #e74c3c;
    color: #fff;
}

.empty-table {
    padding: 0 !important;
}

.empty-table-inner {
    text-align: center;
    padding: 1.25rem 1rem;
    color: #74867f;
}

.empty-table-inner i {
    font-size: 1.7rem;
    color: #3d8d7a;
    margin-bottom: 0.5rem;
}

.empty-table-inner h4 {
    margin: 0 0 0.3rem;
    font-size: 0.92rem;
    color: #355f55;
}

.empty-table-inner p {
    margin: 0 0 0.75rem;
    max-width: 280px;
    margin-left: auto;
    margin-right: auto;
    font-size: 0.78rem;
}

tfoot .footer-label,
tfoot .footer-value {
    padding: 0.55rem 0.5rem !important;
    background: #f1f7f4;
    font-weight: 700;
    color: #20413a;
    font-size: 0.8rem;
}

.quantity-control {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.qty-btn {
    background: #ffffff;
    border: 1px solid #d7e5de;
    border-radius: 8px;
    width: 26px;
    height: 26px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #6b7280;
    transition: all 0.3s ease;
    font-size: 0.72rem;
}

.qty-btn:hover:not(:disabled) {
    background: #3d8d7a;
    color: white;
    border-color: #3d8d7a;
}

.qty-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}


.summary-metrics {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}

.summary-metric-card {
    padding: 0.6rem 0.7rem;
    border-radius: 12px;
    background: linear-gradient(180deg, #f6fbf9 0%, #eef7f3 100%);
    border: 1px solid #ddebe5;
}

.summary-metric-label {
    display: block;
    color: #6c877d;
    font-size: 0.66rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.summary-metric-value {
    display: block;
    margin-top: 0.25rem;
    color: #20413a;
    font-size: 1.05rem;
}

.summary-section {
    background: linear-gradient(135deg, #f8fbfa 0%, #f0f7f4 100%);
    border: 1px solid #e0ece7;
    border-radius: 14px;
    padding: 0.8rem;
    margin-bottom: 0.75rem;
}

.summary-header h3 {
    font-size: 0.92rem;
    font-weight: 600;
    color: #20413a;
    margin: 0 0 0.7rem 0;
}

.summary-content {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 5px 0;
    border-bottom: 1px solid #e4ece8;
    font-size: 0.82rem;
}

.summary-row:last-child {
    border-bottom: none;
}

.summary-value {
    font-weight: 600;
    color: #374151;
}

.total-row {
    font-size: 0.85rem;
}

.total-row .total-amount {
    font-size: 1.05rem;
    color: #059669;
}

.hint-card {
    display: flex;
    gap: 0.55rem;
    padding: 0.7rem;
    border-radius: 12px;
    background: #f5faf8;
    border: 1px solid #e0ece7;
    color: #46695d;
}

.hint-icon {
    width: 30px;
    height: 30px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e4f1ed;
    color: #2f7666;
    flex-shrink: 0;
    font-size: 0.85rem;
}

.hint-title {
    margin: 0 0 0.2rem;
    font-size: 0.78rem;
    font-weight: 700;
    color: #20413a;
}

.hint-text {
    font-size: 0.72rem;
    line-height: 1.45;
}

.feedback-stack {
    display: grid;
    gap: 0.5rem;
    margin-top: 0.85rem;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    margin-top: 1rem;
}

.btn {
    padding: 0.55rem 0.9rem;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-size: 0.78rem;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.btn-cancel {
    background-color: #ffffff;
    color: #60756d;
    border: 1px solid #d7e5de;
}

.btn-cancel:hover {
    background-color: #f7fbfa;
    border-color: #b7cec4;
}

.btn-save {
    background: linear-gradient(135deg, #3d8d7a 0%, #2e6f61 100%);
    color: white;
    box-shadow: 0 12px 24px rgba(61, 141, 122, 0.28);
}

.btn-save:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 16px 28px rgba(61, 141, 122, 0.34);
}

.btn-save:disabled {
    background: #95a5a6;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}

@media (max-width: 768px) {
    .modal-header {
        padding: 0.75rem 1rem;
    }

    .modal-header h2 {
        font-size: 1rem;
    }

    .modal-body {
        padding: 1.25rem;
    }

    .form-layout {
        grid-template-columns: 1fr;
    }

    .form-panel-side {
        position: static;
    }

    .form-actions {
        flex-direction: column-reverse;
        gap: 0.625rem;
    }

    .btn {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .modal-overlay {
        padding: 10px;
    }

    .modal-header {
        padding: 0.625rem 0.875rem;
    }

    .modal-header h2 {
        font-size: 0.95rem;
    }

    .modal-body {
        padding: 1rem;
    }

    .form-control {
        font-size: 0.9rem;
        padding: 0.625rem 0.75rem 0.625rem 2.5rem;
    }

    .form-control-plain {
        padding-left: 0.875rem;
    }

    .money-input .form-control {
        padding-left: 3.5rem;
    }

    .btn {
        padding: 0.5rem 0.875rem;
        font-size: 0.75rem;
    }

    .summary-section {
        padding: 16px;
    }

    .summary-metrics {
        grid-template-columns: 1fr;
    }
}
</style>
