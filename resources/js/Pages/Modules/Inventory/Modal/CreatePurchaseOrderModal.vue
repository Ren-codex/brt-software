<template>
    <div v-if="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="hide">
        <div class="modal-container modal-lg" @click.stop>
            <div class="modal-header">
                <h2>{{ editable ? 'Update Purchase Request' : 'Create Purchase Request' }}</h2>
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
                                    
                                        <Amount @amount="amount(index , item.unit_cost)"
                                            ref="amountComponent"
                                            :class="{ 'input-error': form.errors[`items.${index}.unit_cost`] }"
                                            class="form-control"
                                        />
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

            this.form.items = [
                {
                    product_id: null,
                    quantity: 0,
                    unit_cost: 0.00,
                    total_cost: 0,
                }
            ];
            this.addItem();
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = true;
        },
        
        edit(data, index) {
            this.form.reset();
            this.form.clearErrors();
            this.form.id = data.id;
            this.form.supplier_id = data.supplier ? data.supplier.id : null;
            this.form.items = data.items ? data.items.map(item => ({
                product_id: item.product.id,
                quantity: Math.round(item.quantity),
                unit_cost: parseFloat(item.unit_cost) || 0,
                total_cost: parseFloat(item.total_cost) || 0,
            })) : [];
            this.editable = true;
            this.saveSuccess = false;
            this.showModal = true;
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
            console.log('Duplicating item:', itemToDuplicate.unit_cost);
            this.form.items.splice(index + 1, 0, {
                quantity: itemToDuplicate.quantity,
                unit_cost: itemToDuplicate.unit_cost,
                total_cost: itemToDuplicate.total_cost,
                product_id: itemToDuplicate.product_id,
            });

            console.log('New item added:', this.form.items[index + 1]);
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
        
        // FIXED: Update unit cost method
        amount(index ,val){
            this.$refs.amountComponent[index].emitValue(val.toFixed(2) ) ;
        },

        cleanCurrency(value) {
            if (!value) return 0;
            // Remove ₱, commas, and spaces
            const cleaned = value.toString().replace(/[^0-9.]/g, "");
            return parseFloat(cleaned);
        },

        // FIXED: Format currency method
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
    .cost-input .form-control {
    padding-left: 1rem !important;
}

.total-display {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 6px;
    padding: 8px 12px;
    font-weight: 600;
    color: #059669;
    display: flex;
    align-items: center;
    justify-content: center;
}

.total-display .total-amount {
    font-size: 1rem;
}

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
    padding: 15px;
}

.modal-overlay.active {
    opacity: 1;
    visibility: visible;
}

/* Modal Container */
.modal-container {
    background: white;
    border-radius: 16px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    width: 100%;
    max-width: 500px;
    overflow: hidden;
    transform: translateY(25px) scale(0.95);
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
    background: #3a8674;
    color: white;
    padding: 0.875rem 1.25rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    font-weight: 700;
    font-size: 1.1rem;
    margin: 0;
}

.close-btn {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1.1rem;
}

.close-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg);
}

/* Modal Body */
.modal-body {
    padding: 1.5rem;
    max-height: 75vh;
    overflow-y: auto;
}

/* Form Group */
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

/* Input Wrapper */
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
    padding: 0.7rem 0.875rem 0.7rem 2.75rem;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    font-size: 0.95rem;
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
    margin-top: 0.375rem;
    font-size: 0.8125rem;
    color: #e74c3c;
}

/* Success Alert */
.success-alert {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    color: #155724;
    padding: 0.875rem 1.25rem;
    border-radius: 8px;
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.625rem;
    animation: slideIn 0.5s ease-out;
    border: 1px solid #c3e6cb;
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

/* Items Section */
.btn-add-item {
    background: #3a8674;
    color: white;
    border: none;
    padding: 0.5rem 0.875rem;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.8125rem;
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
    border-radius: 10px;
    padding: 16px;
    margin-bottom: 12px;
    transition: box-shadow 0.3s ease;
}

.item-card:hover {
    box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
}

.item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f3f4f6;
}

.item-number {
    font-weight: 600;
    color: #374151;
    font-size: 0.95rem;
}

.item-actions {
    display: flex;
    gap: 6px;
}

.btn-action {
    background: #f9fafb;
    border: 1px solid #e9ecef;
    border-radius: 5px;
    padding: 6px;
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
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-top: 12px;
}

.quantity-control {
    display: flex;
    align-items: center;
    gap: 6px;
}

.qty-btn {
    background: #f9fafb;
    border: 1px solid #e9ecef;
    border-radius: 5px;
    width: 32px;
    height: 32px;
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
    left: 0.875rem;
    top: 50%;
    transform: translateY(-50%);
    color: #7f8c8d;
    font-weight: 500;
    z-index: 1;
}

.cost-input .form-control {
    padding-left: 2.25rem;
}

.total-display {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 6px;
    padding: 8px 12px;
    font-weight: 600;
    color: #059669;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 32px 16px;
    color: #9ca3af;
}

.empty-state i {
    font-size: 2.5rem;
    margin-bottom: 12px;
    opacity: 0.5;
}

.empty-state h4 {
    font-size: 1.1rem;
    margin-bottom: 8px;
    color: #6b7280;
}

.empty-state p {
    margin-bottom: 16px;
    max-width: 280px;
    margin-left: auto;
    margin-right: auto;
    font-size: 0.9rem;
}

/* Summary Section */
.summary-section {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
}

.summary-header h3 {
    font-size: 1.1rem;
    font-weight: 600;
    color: #333;
    margin: 0 0 16px 0;
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
    font-size: 1rem;
}

.total-row .total-amount {
    font-size: 1.3rem;
    color: #059669;
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    margin-top: 1.5rem;
}

.btn {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.8125rem;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
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
    background:#3a8674;
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
        padding: 0.75rem 1rem;
    }

    .modal-header h2 {
        font-size: 1rem;
    }

    .modal-body {
        padding: 1.25rem;
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
}
</style>