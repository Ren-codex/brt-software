<template>
    <div v-if="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="hide">
        <div class="modal-container modal-lg" @click.stop>
            <div class="modal-header">
                <h2>{{ editable ? 'Update Purchase Order' : 'Create Purchase Order' }}</h2>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="success-alert" v-if="saveSuccess">
                    <i class="ri-checkbox-circle-fill"></i>
                    <span>Purchase order has been {{ editable ? 'updated' : 'created' }} successfully.</span>
                </div>
                <form @submit.prevent="submit" class="purchase-order-form">
                    <!-- Supplier Section (same as before) -->
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

                    <!-- Items Section -->
                    <div class="form-group">
                        <label class="form-label">Order Items</label>
                        <button type="button" @click="addItem" class="btn-add-item">
                            <i class="ri-add-line"></i>
                            Add Item
                        </button>
                    </div>

                    <div class="items-container" v-if="form.items.length > 0">
                        <div v-for="(item, index) in form.items" :key="index" class="item-card">
                            <div class="item-header">
                                <div class="item-number">Item {{ index + 1 }}</div>
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
                                <!-- Product Selection -->
                                <div class="form-group">
                                    <label>Product</label>
                                    <select v-model="item.product_id" class="form-control"
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

                                <!-- Quantity, Unit Cost, Total -->
                                <div class="item-details">
                                    <div class="form-group">
                                        <label>Quantity</label>
                                        <div class="quantity-control">
                                            <button type="button" class="qty-btn"
                                                @click="adjustQuantity(item, 'decrease')"
                                                :disabled="item.quantity <= 0">
                                                <i class="ri-subtract-line"></i>
                                            </button>
                                            <input type="number" v-model="item.quantity" class="form-control"
                                                :class="{ 'input-error': form.errors[`items.${index}.quantity`] }"
                                                @input="calculateTotal(item); handleInput(`items.${index}.quantity`)"
                                                step="1" min="0" required @change="validateQuantity(item)">
                                            <button type="button" class="qty-btn"
                                                @click="adjustQuantity(item, 'increase')">
                                                <i class="ri-add-line"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label>Unit Cost</label>
                                        <!-- FIXED: Use Amount component with proper binding -->
                                        <div class="cost-input">
                                            <Amount 
                                                :initial-value="item.unit_cost"
                                                @amount="updateUnitCost(item, $event)"
                                                :class="{ 'input-error': form.errors[`items.${index}.unit_cost`] }"
                                                class="form-control amount-input"
                                            />
                                        </div>
                                        <span class="error-message" v-if="form.errors[`items.${index}.unit_cost`]">
                                            {{ form.errors[`items.${index}.unit_cost`] }}
                                        </span>
                                    </div>

                                    <div class="form-group">
                                        <label>Total Cost</label>
                                        <div class="total-display">
                                            <span class="currency"></span>
                                            <span class="total-amount">{{ formatCurrency(item.total_cost) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div class="empty-state" v-else>
                        <i class="ri-shopping-cart-line"></i>
                        <h4>No Items Added</h4>
                        <p>Click "Add Item" to start adding products to your purchase order.</p>
                        <button type="button" @click="addItem" class="btn-add-item">
                            <i class="ri-add-line"></i>
                            Add Your First Item
                        </button>
                    </div>

                    <!-- Summary Section (same as before) -->
                    <div class="summary-section">
                        <div class="summary-header">
                            <h3>Order Summary</h3>
                        </div>
                        <div class="summary-content">
                            <div class="summary-row">
                                <span>Number of Items:</span>
                                <span class="summary-value">{{ form.items.length }}</span>
                            </div>
                            <div class="summary-row">
                                <span>Total Quantity:</span>
                                <span class="summary-value">{{ totalQuantity }}</span>
                            </div>
                            <div class="summary-row total-row">
                                <span>Total Amount:</span>
                                <span class="summary-value total-amount">{{ formatCurrency(totalAmount) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="button" class="btn btn-cancel" @click="hide">
                            <i class="ri-close-line"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-save"
                            :disabled="form.processing || form.items.length === 0">
                            <i class="ri-save-line" v-if="!form.processing"></i>
                            <i class="ri-loader-4-line spinner" v-else></i>
                            {{ form.processing ? 'Processing...' : (editable ? 'Update Order' : 'Create Order') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import { useForm } from '@inertiajs/vue3';
import Amount from '@/Shared/Components/Forms/Amount.vue';

export default {
    components: { Amount },
    name: "CreatePurchaseOrderModal",
    props: ['dropdowns'],
    emits: ['add'],
    data() {
        return {
            form: useForm({
                id: null,
                supplier_id: null,
                total_amount: 0,
                items: [],
            }),
            showModal: false,
            editable: false,
            saveSuccess: false,
            amountRefs: new Map(),
        };
    },
    mounted() {
        this.addItem();
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
            this.form.items = [];
            this.addItem();
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = true;
            this.$nextTick(() => {
                this.form.items.forEach((item, index) => {
                    this.setAmountValue(index, 0.00);
                });
            });
        },
        
        edit(data, index) {
            this.form.reset();
            this.form.clearErrors();
            this.form.id = data.id;
            this.form.supplier_id = data.supplier ? data.supplier.id : null;
            this.form.items = data.items ? data.items.map(item => ({
                product_id: item.product_id,
                quantity: Math.round(item.quantity),
                unit_cost: parseFloat(item.unit_cost),
                total_cost: parseFloat(item.total_cost),
            })) : [];
            this.editable = true;
            this.saveSuccess = false;
            this.showModal = true;
            
        
            this.$nextTick(() => {
                this.form.items.forEach((item, index) => {
                    this.setAmountValue(index, item.unit_cost || 0);
                });
            });
        },
        
       
        setAmountValue(index, value) {
          
            const amountComponent = this.$refs[`amount_${index}`];
            if (amountComponent && amountComponent.emitValue) {
                amountComponent.emitValue(value);
            }
        },
        
        submit() {
            this.form.total_amount = this.totalAmount;
    
            const formattedItems = this.form.items.map(item => ({
                product_id: item.product_id,
                quantity: parseInt(item.quantity) || 0,
                unit_cost: parseFloat(item.unit_cost) || 0,
                total_cost: parseFloat(item.total_cost) || 0,
            }));
            
            this.form.items = formattedItems;

            if (this.editable) {
                this.form.put(`/purchase-orders/${this.form.id}`, {
                    preserveScroll: true,
                    onSuccess: (response) => {
                        this.saveSuccess = true;
                        setTimeout(() => {
                            this.$emit('add', true);
                            this.form.reset();
                            this.hide();
                        }, 1500);
                    },
                });
            } else {
                this.form.post('/purchase-orders', {
                    preserveScroll: true,
                    onSuccess: (response) => {
                        this.saveSuccess = true;
                        setTimeout(() => {
                            this.$emit('add', true);
                            this.form.reset();
                            this.hide();
                        }, 1500);
                    },
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
            this.showModal = false;
        },
        
        addItem() {
            this.form.items.push({
                product_id: null,
                quantity: 0,
                unit_cost: 0,
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
                    unit_cost: 0,
                    total_cost: 0,
                };
            }
        },
        
        duplicateItem(index) {
            const itemToDuplicate = { ...this.form.items[index] };
            this.form.items.splice(index + 1, 0, {
                ...itemToDuplicate,
                product_id: null,
            });
        },
        
        calculateTotal(item) {
            const quantity = parseFloat(item.quantity) || 0;
            const unitCost = parseFloat(item.unit_cost) || 0;
            item.total_cost = quantity * unitCost;
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
        },
        
        formatCurrency(value) {
            if (!value && value !== 0) return '0.00';
            return parseFloat(value).toFixed(2);
        },
        
        // FIXED: Update unit cost for specific item
        updateUnitCost(item, value) {
            if (typeof value === 'string') {
                const cleanValue = value.replace(/[₱,]/g, '');
                const num = parseFloat(cleanValue);
                item.unit_cost = isNaN(num) ? 0 : num;
            } else {
                item.unit_cost = value || 0;
            }
            this.calculateTotal(item);
        },
        formatCurrency(value) {
            if (!value) return '₱0.00';
            return '₱' + Number(value).toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        },
    },
};
</script>

<style scoped>
/* Modal Overlay */
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
    padding: 20px;
}

.modal-overlay.active {
    opacity: 1;
    visibility: visible;
}

/* Modal Container */
.modal-container {
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    width: 100%;
    max-width: 500px;
    overflow: hidden;
    transform: translateY(30px) scale(0.95);
    transition: all 0.3s ease;
}

.modal-container.modal-lg {
    max-width: 900px;
}

.modal-overlay.active .modal-container {
    transform: translateY(0) scale(1);
}

/* Modal Header */
.modal-header {
    background: linear-gradient(135deg, #2e8b57 0%, #1f6b41 100%);
    color: white;
    padding: 1rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    font-weight: 700;
    font-size: 1.25rem;
    margin: 0;
}

.close-btn {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1.2rem;
}

.close-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg);
}

/* Modal Body */
.modal-body {
    padding: 2rem;
    max-height: 70vh;
    overflow-y: auto;
}

/* Form Group */
.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.95rem;
}

/* Input Wrapper */
.input-wrapper {
    position: relative;
}

.input-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #7f8c8d;
    font-size: 1.2rem;
    z-index: 1;
}

.form-control {
    width: 100%;
    padding: 0.8rem 1rem 0.8rem 3rem;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background-color: white;
}

.form-control:focus {
    outline: none;
    border-color: #2e8b57;
    box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.1);
}

.form-control.input-error {
    border-color: #e74c3c;
}

.form-control.input-error:focus {
    box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
}

/* Error Message */
.error-message {
    display: block;
    margin-top: 0.5rem;
    font-size: 0.875rem;
    color: #e74c3c;
}

/* Success Alert */
.success-alert {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    color: #155724;
    padding: 1rem 1.5rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    animation: slideIn 0.5s ease-out;
    border: 1px solid #c3e6cb;
}

.success-alert i {
    font-size: 1.5rem;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Items Section */
.btn-add-item {
    background: linear-gradient(135deg, #2e8b57 0%, #1f6b41 100%);
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.875rem;
    transition: all 0.3s ease;
}

.btn-add-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(46, 139, 87, 0.3);
}

/* Item Card */
.item-card {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 15px;
    transition: box-shadow 0.3s ease;
}

.item-card:hover {
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #f3f4f6;
}

.item-number {
    font-weight: 600;
    color: #374151;
    font-size: 1rem;
}

.item-actions {
    display: flex;
    gap: 8px;
}

.btn-action {
    background: #f9fafb;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 8px;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-action:hover {
    background: #f3f4f6;
    color: #374151;
}

.btn-action.btn-danger:hover {
    background: #e74c3c;
    color: white;
    border-color: #e74c3c;
}

/* Item Details */
.item-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.quantity-control {
    display: flex;
    align-items: center;
    gap: 8px;
}

.qty-btn {
    background: #f9fafb;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #6b7280;
    transition: all 0.3s ease;
}

.qty-btn:hover:not(:disabled) {
    background: #2e8b57;
    color: white;
    border-color: #2e8b57;
}

.qty-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.cost-input {
    position: relative;
}

.cost-input .currency {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #7f8c8d;
    font-weight: 500;
    z-index: 1;
}

.cost-input .form-control {
    padding-left: 2.5rem;
}

.total-display {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 8px;
    padding: 10px 15px;
    font-weight: 600;
    color: #059669;
    display: flex;
    align-items: center;
    gap: 5px;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #9ca3af;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 15px;
    opacity: 0.5;
}

.empty-state h4 {
    font-size: 1.2rem;
    margin-bottom: 10px;
    color: #6b7280;
}

.empty-state p {
    margin-bottom: 20px;
    max-width: 300px;
    margin-left: auto;
    margin-right: auto;
}

/* Summary Section */
.summary-section {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
}

.summary-header h3 {
    font-size: 1.2rem;
    font-weight: 600;
    color: #333;
    margin: 0 0 20px 0;
}

.summary-content {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #e9ecef;
}

.summary-row:last-child {
    border-bottom: none;
}

.summary-value {
    font-weight: 600;
    color: #374151;
}

.total-row {
    font-size: 1.1rem;
}

.total-row .total-amount {
    font-size: 1.5rem;
    color: #059669;
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 2rem;
}

.btn {
    padding: 0.5rem 1.25rem;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.btn-cancel {
    background-color: transparent;
    color: #7f8c8d;
    border: 1px solid #e9ecef;
}

.btn-cancel:hover {
    background-color: #f8f9fa;
    border-color: #7f8c8d;
}

.btn-save {
    background: linear-gradient(135deg, #2e8b57 0%, #1f6b41 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(46, 139, 87, 0.3);
}

.btn-save:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(46, 139, 87, 0.4);
}

.btn-save:disabled {
    background: #95a5a6;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* Spinner Animation */
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

/* Responsive Design */
@media (max-width: 768px) {
    .modal-header {
        padding: 1rem 1.25rem;
    }

    .modal-header h2 {
        font-size: 1.1rem;
    }

    .modal-body {
        padding: 1.5rem;
    }

    .item-details {
        grid-template-columns: 1fr;
    }

    .form-actions {
        flex-direction: column-reverse;
        gap: 0.75rem;
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
        padding: 0.875rem 1rem;
    }

    .modal-header h2 {
        font-size: 1rem;
    }

    .modal-body {
        padding: 1.25rem;
    }

    .form-control {
        font-size: 0.95rem;
    }

    .btn {
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
    }
}
</style>
