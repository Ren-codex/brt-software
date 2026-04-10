<template>
    <div v-if="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="hide">
        <div class="modal-container modal-lg" @click.stop>
            <div class="modal-header">
                <div class="header-title">
                    <i :class="editable ? 'ri-edit-box-line' : 'ri-shopping-bag-3-line'"></i>
                    <h2>{{ editable ? 'Update Purchase Request' : 'Create Purchase Request' }}</h2>
                </div>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body">
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

                            <div class="items-container" v-if="form.items.length > 0">
                                <div v-for="(item, index) in form.items" :key="index" class="item-card">
                                    <div class="item-header">
                                        <div>
                                            <div class="item-number">Item {{ index + 1 }}</div>
                                            <div class="item-caption">Purchase line details</div>
                                        </div>
                                        <div class="item-actions">
                                            <button type="button" class="btn-action" @click="duplicateItem(index)"
                                                title="Duplicate">
                                                <i class="ri-file-copy-line"></i>
                                            </button>
                                            <button type="button" class="btn-action btn-danger" @click="removeItem(index)"
                                                title="Remove">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="item-body">
                                        <div class="form-group">
                                            <label>Product</label>
                                            <select v-model="item.product_id" class="form-control form-control-plain"
                                                :class="{ 'input-error': form.errors[`items.${index}.product_id`] }"
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
                                        </div>

                                        <div class="item-details">
                                            <div class="form-group">
                                                <label>Quantity</label>
                                                <div class="quantity-control">
                                                    <button type="button" class="qty-btn"
                                                        @click="adjustQuantity(item, 'decrease')"
                                                        :disabled="item.quantity <= 0">
                                                        <i class="ri-subtract-line"></i>
                                                    </button>
                                                    <input type="number" v-model="item.quantity" class="form-control form-control-plain"
                                                        :class="{ 'input-error': form.errors[`items.${index}.quantity`] }"
                                                        @input="calculateTotal(item); handleInput(`items.${index}.quantity`)"
                                                        step="1" min="0" :max="MAX_QUANTITY" required @change="validateQuantity(item)">
                                                    <button type="button" class="qty-btn"
                                                        @click="adjustQuantity(item, 'increase')">
                                                        <i class="ri-add-line"></i>
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label>Unit Cost</label>
                                                <div class="money-input">
                                                    <span class="money-prefix">PHP</span>
                                                    <input type="number" v-model="item.unit_cost" class="form-control form-control-plain"
                                                        :class="{ 'input-error': form.errors[`items.${index}.unit_cost`] }"
                                                        @input="calculateTotal(item); handleInput(`items.${index}.unit_cost`)"
                                                        step="0.01" min="0" :max="MAX_UNIT_COST" required>
                                                </div>
                                                <span class="error-message" v-if="form.errors[`items.${index}.unit_cost`]">
                                                    {{ form.errors[`items.${index}.unit_cost`] }}
                                                </span>
                                            </div>

                                            <div class="form-group">
                                                <label>Total Cost</label>
                                                <div class="total-display">
                                                    <span class="total-amount">{{ formatCurrency(item.total_cost) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="empty-state" v-else>
                                <div class="empty-state-icon">
                                    <i class="ri-shopping-cart-line"></i>
                                </div>
                                <h4>No Items Added Yet</h4>
                                <p>Start this request by adding your first product line item.</p>
                                <button type="button" @click="addItem" class="btn-add-item">
                                    <i class="ri-add-line"></i>
                                    Add Your First Item
                                </button>
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
                            <span>Purchase order has been {{ editable ? 'updated' : 'created' }} successfully.</span>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-cancel" @click="hide">
                            <i class="ri-close-line"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-save"
                            :disabled="form.processing || form.items.length === 0">
                            <i class="ri-save-line" v-if="!form.processing"></i>
                            <i class="ri-loader-4-line spinner" v-else></i>
                            {{ form.processing ? 'Processing...' : (editable ? 'Update Request' : 'Create Request') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
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




    methods: {
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

        edit(data, index) {
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
                            this.$emit('add', true);
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
                            this.$emit('add', true);
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
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1050;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    padding: 15px;
}

.modal-overlay.active {
    opacity: 1;
    visibility: visible;
}

.modal-container {
    background: #f7fbfa;
    border-radius: 28px;
    box-shadow: 0 28px 70px rgba(17, 24, 39, 0.25);
    width: 100%;
    max-width: 500px;
    overflow: hidden;
    transform: translateY(25px) scale(0.95);
    transition: all 0.3s ease;
}

.modal-container.modal-lg {
    max-width: 1240px;
}

.modal-overlay.active .modal-container {
    transform: translateY(0) scale(1);
}

.modal-header {
    padding: 16px 22px;
    border-bottom: 1px solid #d7e5de;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(140deg, #d7ece5 0%, #c7e2d9 100%);
}

.header-title {
    display: flex;
    align-items: center;
    gap: 10px;
}

.header-title i {
    width: 38px;
    height: 38px;
    border-radius: 11px;
    display: grid;
    place-items: center;
    background: rgba(26, 104, 87, 0.15);
    color: #1a6857;
    font-size: 21px;
}

.header-title h2 {
    margin: 0;
    font-size: 1.16rem;
    color: #1f2937;
    font-weight: 700;
}

.close-btn {
    width: 34px;
    height: 34px;
    border: 0;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.75);
    color: #4b5563;
    font-size: 19px;
    display: grid;
    place-items: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.close-btn:hover {
    background: #fff;
    transform: rotate(90deg);
}

.modal-body {
    padding: 1.5rem 1.75rem 1.75rem;
    max-height: 75vh;
    overflow-y: auto;
}

.form-layout {
    display: grid;
    grid-template-columns: minmax(0, 2.35fr) minmax(300px, 0.85fr);
    gap: 1.5rem;
    align-items: start;
}

.form-panel {
    background: #ffffff;
    border: 1px solid rgba(61, 141, 122, 0.12);
    border-radius: 24px;
    box-shadow: 0 16px 35px rgba(39, 84, 72, 0.08);
}

.form-panel-main {
    padding: 1.35rem;
}

.form-panel-side {
    padding: 1.25rem;
    position: sticky;
    top: 0;
}

.panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.25rem;
}

.panel-kicker {
    margin: 0;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #648b74;
}

.panel-title {
    margin: 0.2rem 0 0;
    color: #20413a;
    font-size: 1.1rem;
    font-weight: 700;
}

.form-group {
    margin-bottom: 1.25rem;
}

.form-label {
    display: block;
    margin-bottom: 0.375rem;
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.9rem;
}

.input-wrapper {
    position: relative;
}

.input-icon {
    position: absolute;
    left: 0.875rem;
    top: 50%;
    transform: translateY(-50%);
    color: #7f8c8d;
    font-size: 1.1rem;
    z-index: 1;
}

.form-control {
    width: 100%;
    padding: 0.85rem 1rem 0.85rem 2.9rem;
    border: 1px solid #d7e5de;
    border-radius: 14px;
    font-size: 0.95rem;
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

.form-control-plain {
    padding-left: 1rem;
}

.money-input {
    position: relative;
}

.money-prefix {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    color: #648b74;
    z-index: 1;
}

.money-input .form-control {
    padding-left: 3.7rem;
}

.error-message {
    display: block;
    margin-top: 0.375rem;
    font-size: 0.8125rem;
    color: #e74c3c;
}

.error-banner {
    background: #fee2e2;
    border: 1px solid #fecaca;
    color: #991b1b;
    border-radius: 8px;
    padding: 0.625rem 0.875rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
}

.success-alert {
    background: linear-gradient(135deg, #e6f7ee 0%, #d8f2e3 100%);
    color: #155724;
    padding: 0.875rem 1.25rem;
    border-radius: 14px;
    display: flex;
    align-items: center;
    gap: 0.625rem;
    animation: slideIn 0.5s ease-out;
    border: 1px solid #b9e6ca;
}

.success-alert i {
    font-size: 1.3rem;
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
    gap: 1rem;
    margin: 1.5rem 0 1rem;
    padding-top: 1.25rem;
    border-top: 1px solid #edf3f1;
}

.section-title {
    color: #20413a;
    font-size: 1.02rem;
    font-weight: 700;
}

.btn-add-item {
    background: linear-gradient(135deg, #3d8d7a 0%, #2f7464 100%);
    color: white;
    border: none;
    padding: 0.7rem 1rem;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.85rem;
    transition: all 0.3s ease;
    box-shadow: 0 10px 20px rgba(61, 141, 122, 0.18);
}

.btn-add-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 25px rgba(61, 141, 122, 0.24);
}

.items-container {
    display: grid;
    gap: 1rem;
}

.item-card {
    background: linear-gradient(180deg, #fbfefd 0%, #f4faf8 100%);
    border: 1px solid #d9ebe4;
    border-radius: 20px;
    padding: 1rem;
    transition: box-shadow 0.3s ease, transform 0.3s ease;
}

.item-card:hover {
    box-shadow: 0 14px 28px rgba(32, 65, 58, 0.08);
    transform: translateY(-1px);
}

.item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.85rem;
    border-bottom: 1px solid #e7f0ed;
}

.item-number {
    font-weight: 700;
    color: #20413a;
    font-size: 1rem;
}

.item-caption {
    color: #6b8d80;
    font-size: 0.82rem;
    margin-top: 0.2rem;
}

.item-actions {
    display: flex;
    gap: 0.45rem;
}

.btn-action {
    background: #ffffff;
    border: 1px solid #dce9e4;
    border-radius: 10px;
    width: 38px;
    height: 38px;
    color: #5f786e;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-action:hover {
    background: #eff7f4;
    color: #20413a;
}

.btn-action.btn-danger:hover {
    background: #e74c3c;
    color: white;
    border-color: #e74c3c;
}

.item-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin-top: 0.75rem;
}

.quantity-control {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.qty-btn {
    background: #ffffff;
    border: 1px solid #d7e5de;
    border-radius: 12px;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #6b7280;
    transition: all 0.3s ease;
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

.total-display {
    min-height: 51px;
    background: linear-gradient(135deg, #effbf6 0%, #e3f5ed 100%);
    border: 1px solid #cbe9d7;
    border-radius: 14px;
    padding: 0.8rem 1rem;
    font-weight: 600;
    color: #059669;
    display: flex;
    align-items: center;
    justify-content: center;
}

.total-display .total-amount {
    font-size: 1rem;
}

.empty-state {
    text-align: center;
    padding: 2rem 1.25rem;
    color: #74867f;
    border: 1px dashed #cfe0da;
    border-radius: 20px;
    background: linear-gradient(180deg, #fbfefd 0%, #f5faf8 100%);
}

.empty-state-icon {
    width: 72px;
    height: 72px;
    margin: 0 auto 1rem;
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #e4f2ed 0%, #f0f7f4 100%);
    color: #3d8d7a;
}

.empty-state i {
    font-size: 2rem;
}

.empty-state h4 {
    font-size: 1.1rem;
    margin-bottom: 8px;
    color: #355f55;
}

.empty-state p {
    margin-bottom: 16px;
    max-width: 280px;
    margin-left: auto;
    margin-right: auto;
    font-size: 0.9rem;
}

.summary-metrics {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.summary-metric-card {
    padding: 0.95rem 1rem;
    border-radius: 18px;
    background: linear-gradient(180deg, #f6fbf9 0%, #eef7f3 100%);
    border: 1px solid #ddebe5;
}

.summary-metric-label {
    display: block;
    color: #6c877d;
    font-size: 0.76rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
}

.summary-metric-value {
    display: block;
    margin-top: 0.35rem;
    color: #20413a;
    font-size: 1.25rem;
}

.summary-section {
    background: linear-gradient(135deg, #f8fbfa 0%, #f0f7f4 100%);
    border: 1px solid #e0ece7;
    border-radius: 20px;
    padding: 1.15rem;
    margin-bottom: 1rem;
}

.summary-header h3 {
    font-size: 1.1rem;
    font-weight: 600;
    color: #20413a;
    margin: 0 0 1rem 0;
}

.summary-content {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e4ece8;
}

.summary-row:last-child {
    border-bottom: none;
}

.summary-value {
    font-weight: 600;
    color: #374151;
}

.total-row {
    font-size: 1rem;
}

.total-row .total-amount {
    font-size: 1.3rem;
    color: #059669;
}

.hint-card {
    display: flex;
    gap: 0.8rem;
    padding: 1rem;
    border-radius: 18px;
    background: #f5faf8;
    border: 1px solid #e0ece7;
    color: #46695d;
}

.hint-icon {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e4f1ed;
    color: #2f7666;
    flex-shrink: 0;
}

.hint-title {
    margin: 0 0 0.25rem;
    font-size: 0.92rem;
    font-weight: 700;
    color: #20413a;
}

.hint-text {
    font-size: 0.85rem;
    line-height: 1.55;
}

.feedback-stack {
    display: grid;
    gap: 0.75rem;
    margin-top: 1.25rem;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    margin-top: 1.5rem;
}

.btn {
    padding: 0.8rem 1.15rem;
    border: none;
    border-radius: 14px;
    cursor: pointer;
    font-size: 0.85rem;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
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

    .item-details {
        grid-template-columns: 1fr;
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

    .item-card {
        padding: 12px;
    }

    .summary-section {
        padding: 16px;
    }

    .summary-metrics {
        grid-template-columns: 1fr;
    }
}
</style>
