<template>
    <div 
        v-show="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"
        
    >
        <div class="modal-container modal-xl" @click.stop>
            <div class="modal-header">
                <h2>{{ editable ? 'Update Item' : 'Add Item' }}</h2>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body">
                <form @submit.prevent="submit">
                  <div class="form-row">
                        <div class="form-group form-group-half">
                            <label for="product_id" class="form-label">Product</label>
                            <div class="input-wrapper">
                                <i class="ri-bar-chart-2-line input-icon"></i>
                                <b-form-select
                                class="form-control"
                                v-model="form.product_id"
                                :options="availableProducts"
                                :class="{ 'input-error': form.errors.product_id }"
                                text-field="name"
                                value-field="value"
                                @change="onProductChange"
                                >
                                 <template #first>
                                    <b-form-select-option :value="null" disabled  >Select Product</b-form-select-option>
                                </template>
                                </b-form-select>
                            </div>
                            <span class="error-message" v-if="form.errors.product_id">{{ form.errors.product_id }}</span>
                        </div>

                        <div class="form-group form-group-half">
                            <label for="price_type" class="form-label">Price Type</label>
                            <div class="input-wrapper">
                                <i class="ri-price-tag-3-line input-icon"></i>
                                <b-form-select
                                class="form-control"
                                v-model="form.price_type"
                                :options="priceTypeOptions"
                                :class="{ 'input-error': form.errors.price_type }"
                                text-field="text"
                                value-field="value"
                                @change="onPriceTypeChange"
                                >
                                 <template #first>
                                    <b-form-select-option :value="null" disabled  >Select Price Type</b-form-select-option>
                                </template>
                                </b-form-select>
                            </div>
                            <span class="error-message" v-if="form.errors.price_type">{{ form.errors.price_type }}</span>
                        </div>

                    </div>
                    <div class="form-row">
                         <div class="form-group form-group-half">
                            <label for="quantity" class="form-label">Quantity</label>
                            <div class="input-wrapper">
                                <i class="ri-stack-line input-icon"></i>
                                <TextInput
                                    type="number"
                                    id="quantity"
                                    v-model.number="form.quantity"
                                    class="form-control"
                                    :class="{ 'input-error': form.errors.quantity }"
                                    placeholder="Enter Quantity"
                                    :max="editable ? selectedProductStock : totalAllocatableStock"
                                    @input="validateQuantity"
                                />

                            </div>
                            <div v-if="selectedProductStock !== null" class="stock-info">
                                <small class="text-muted">
                                    <i class="ri-information-line"></i>
                                    Available Stock (Selected Batch): <strong>{{ selectedProductStock }}</strong>
                                </small>
                                <small class="text-muted d-block">
                                    <i class="ri-stack-line"></i>
                                    Total Available From This Batch Onward: <strong>{{ totalAllocatableStock }}</strong>
                                </small>
                                <small
                                    v-if="shouldSplitIntoMultipleItems"
                                    class="text-muted d-block"
                                >
                                    <i class="ri-git-merge-line"></i>
                                    This quantity will be split into multiple items by batch.
                                </small>
                             
                                <div v-if="selectedProductBatches.length > 0" class="batch-stock-list">
                                    <small class="text-muted d-block mb-1">All Batch Stocks:</small>
                                    <div class="batch-stock-item" v-for="batch in selectedProductBatches" :key="batch.batch_code">
                                        <span>{{ batch.batch_code }}</span>
                                        <strong>{{ batch.quantity }}</strong>
                                    </div>
                                </div>
                            </div>
                            <span class="error-message" v-if="form.errors.quantity">{{ form.errors.quantity }}</span>
                        </div>
                         <div class="form-group form-group-half">
                            <label for="price" class="form-label">Price</label>
                            <div v-if="!shouldSplitIntoMultipleItems" class="input-wrapper">
                                <i class="ri-cash-line input-icon"></i>
                                <input
                                    type="text"
                                    :value="formatCurrency(form.price)"
                                    class="form-control"
                                    :class="{ 'input-error': form.errors.price }"
                                    readonly
                                />
                            </div>
                            <div v-else class="split-preview-list">
                                <div
                                    v-for="allocation in batchAllocationsPreview"
                                    :key="`${allocation.batch_code}-price`"
                                    class="split-preview-item"
                                >
                                    <div class="split-preview-header">
                                        <span>{{ allocation.batch_code }}</span>
                                        <strong>Qty: {{ allocation.quantity }}</strong>
                                    </div>
                                    <input
                                        type="text"
                                        :value="formatCurrency(allocation.price)"
                                        class="form-control"
                                        readonly
                                    />
                                </div>
                            </div>
                            <span class="error-message" v-if="form.errors.price">{{ form.errors.price }}</span>
                        </div>
                    </div>


                  

                    <div class="form-row">
        

                        <div class="form-group form-group-half">
                            <label for="discount_per_unit" class="form-label">Discount per Unit (Amount)</label>
                            <div class="input-wrapper">
                                <i class="ri-cash-line input-icon"></i>
                                <Amount
                                    @amount="updateDiscountPerUnit($event)"
                                    :class="{ 'input-error': form.errors.discount_per_unit }"
                                    class="form-control"
                                    ref="discountComponent"
                                />
                            </div>
                            <span class="error-message" v-if="form.errors.discount_per_unit">{{ form.errors.discount_per_unit }}</span>
                        </div>
                    </div>

  
                    <div class="success-alert" v-if="saveSuccess">
                        <i class="ri-checkbox-circle-fill"></i>
                        <span>Item has been saved successfully!</span>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-cancel" @click="hide">
                            <i class="ri-close-line"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-save" :disabled="form.processing || ( form.quantity == 0 || parseFloat(form.price) === 0 || !form.product_id || !form.price_type  || !form.batch_code)">
                            <i class="ri-save-line" v-if="!form.processing"></i>
                            <i class="ri-loader-4-line spinner" v-else></i>
                            {{ form.processing ? 'Saving...' : 'Add Item' }}
                        </button>
                    </div>  
                    

                </form>
            </div>
        </div>
    </div>
</template>

<script>
import { useForm } from '@inertiajs/vue3';
import InputLabel from '@/Shared/Components/Forms/InputLabel.vue';
import TextInput from '@/Shared/Components/Forms/TextInput.vue';
import Multiselect from '@/Shared/Components/Forms/Multiselect.vue';
import Amount from '@/Shared/Components/Forms/Amount.vue';

export default {
    components: { InputLabel, TextInput, Multiselect, Amount },
    props: {
        dropdowns: { type: Object, required: true },
        items: { type: Array, default: () => [] },
        update: { type: Boolean, default: false },
    },
    data() {
        return {
            currentUrl: window.location.origin,
            form: useForm({
                id: null,
                brand_id: null,
                quantity: 0,
                price: isNaN(0) ? 0 : 0.00,
                product_id: null,
                batch_code: null,
                price_type: 'retail',
                discount_per_unit: 0,
            }),

            togglePassword: false,
            toggleConfirm: false,
            passwordMismatch: false,
            showModal: false,
            editable: false,
            saveSuccess: false,
        }
    },
    computed: {
        priceTypeOptions() {
            return [
                { value: 'retail', text: 'Retail Price' },
                { value: 'wholesale', text: 'Wholesale Price' }
            ];
        },
        reservedStocks() {
            const reserved = {};
            this.items.forEach((item) => {
                if (!item?.product_id || !item?.batch_code) return;
                if (this.editable && this.form.id && item.id === this.form.id) return;
                const key = `${item.product_id}::${item.batch_code}`;
                reserved[key] = (reserved[key] || 0) + (parseFloat(item.quantity) || 0);
            });
            return reserved;
        },
        selectedProductStock() {
            if (!this.form.product_id) return null;
            if (!this.form.batch_code) return null;
            const batch = this.selectedProductBatches.find((b) => b.batch_code === this.form.batch_code);
            return batch ? batch.quantity : 0;
        },
        selectedBatchDetails() {
            if (!this.form.batch_code) return null;
            return this.selectedProductBatches.find((batch) => batch.batch_code === this.form.batch_code) || null;
        },
        selectedProductBatches() {
            if (!this.form.product_id) return [];
            const product = this.dropdowns.products.find(p => p.value === this.form.product_id);
            if (!product?.batch_stocks) return [];

            return product.batch_stocks
                .map((batch) => {
                    const key = `${this.form.product_id}::${batch.batch_code}`;
                    const reserved = this.reservedStocks[key] || 0;
                    return {
                        batch_code: batch.batch_code,
                        quantity: Math.max((parseFloat(batch.quantity) || 0) - reserved, 0),
                        unit_cost: parseFloat(batch.unit_cost) || 0,
                        retail_price: parseFloat(batch.retail_price) || 0,
                        wholesale_price: parseFloat(batch.wholesale_price) || 0,
                    };
                })
                .filter((batch) => batch.quantity > 0);
        },
        allocatableBatches() {
            if (!this.form.batch_code) return this.selectedProductBatches;
            const startIndex = this.selectedProductBatches.findIndex((batch) => batch.batch_code === this.form.batch_code);
            if (startIndex === -1) return [];
            return this.selectedProductBatches.slice(startIndex);
        },
        totalAllocatableStock() {
            return this.allocatableBatches.reduce((sum, batch) => sum + (parseFloat(batch.quantity) || 0), 0);
        },
        shouldSplitIntoMultipleItems() {
            const quantity = parseFloat(this.form.quantity) || 0;
            return !this.editable && quantity > 0 && quantity > (parseFloat(this.selectedProductStock) || 0);
        },
        batchAllocationsPreview() {
            let remainingQuantity = parseFloat(this.form.quantity) || 0;
            const allocations = [];

            this.allocatableBatches.forEach((batch) => {
                if (remainingQuantity <= 0) return;

                const batchQuantity = Math.min(remainingQuantity, parseFloat(batch.quantity) || 0);
                if (batchQuantity <= 0) return;

                allocations.push({
                    batch_code: batch.batch_code,
                    quantity: batchQuantity,
                    price: Number(this.getBatchPrice(batch) || 0),
                });

                remainingQuantity -= batchQuantity;
            });

            return allocations;
        },
        availableProducts() {
            return this.dropdowns.products.filter((product) => {
                if (!product?.value) return false;
                const totalAvailable = (product.batch_stocks || []).reduce((sum, batch) => {
                    const key = `${product.value}::${batch.batch_code}`;
                    const reserved = this.reservedStocks[key] || 0;
                    const remaining = Math.max((parseFloat(batch.quantity) || 0) - reserved, 0);
                    return sum + remaining;
                }, 0);
                return totalAvailable > 0;
            });
        }
    },


    methods: {
        show() {
            this.form.reset();
            this.form.quantity = 0;
            this.form.price = '0.00';
            this.form.discount_per_unit = 0;
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = true;
            this.$nextTick(() => {
            this.$refs.discountComponent.emitValue(0.00);
            });
        },
        edit(data, index) {
            this.form.id = data.id;
            this.form.brand_id = data.brand_id;
            this.form.quantity = data.quantity;
            this.form.product_id = data.product_id;
            this.form.price_type = data.price_type || 'retail';
            this.editable = true;
            this.onProductChange();
            this.form.batch_code = data.batch_code || this.form.batch_code;
            this.form.price = parseFloat(this.getBatchPrice(this.selectedBatchDetails) || data.price || 0).toFixed(2);
            this.form.discount_per_unit = data.discount_per_unit || 0;
            this.form.total_amount = data.total_amount;
            this.saveSuccess = false;
            this.showModal = true;
            this.validateQuantity(); // Validate quantity against current stock
        },

        submit() {
            // Re-validate discount before submitting
            this.validateQuantity();
            this.validateDiscount();

            if (this.form.errors.quantity || this.form.errors.discount_per_unit) {
                return;
            }

            const itemData = {
                id: this.form.id,
                brand_id: this.form.brand_id,
                quantity: this.form.quantity,
                price: Number(this.form.price || 0).toFixed(2), // 2 decimals
                product_id: this.form.product_id,
                batch_code: this.form.batch_code,
                price_type: this.form.price_type,
                discount_per_unit: this.form.discount_per_unit,
                total_amount: this.form.amount || 0,
            };

            if (this.editable) {
                this.$emit('update', itemData );
            } else {
                const splitItems = this.allocateItemQuantities(itemData);
                this.$emit('items', splitItems);
            }

            this.showModal = false;
        },

        handleInput(field) {
            this.form.errors[field] = false;
        },
        hide() {
            this.form.price = null;
            this.form.reset();
            this.form.clearErrors();
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = false;
        },



        updateDiscountPerUnit(value) {
            if (typeof value === 'string') {
                const cleanValue = value.replace(/[₱,]/g, '');
                const num = parseFloat(cleanValue);
                this.form.discount_per_unit = isNaN(num) ? 0 : num;
            } else {
                this.form.discount_per_unit = value || 0;
            }
            this.validateDiscount();
        },

        onProductChange() {
            const product_id = this.form.product_id;
            const product = this.dropdowns.products.find(p => p.value === product_id);
            if (product) {
                const availableBatch = this.selectedProductBatches[0];
                this.form.batch_code = availableBatch ? availableBatch.batch_code : null;
                const price = this.getBatchPrice(availableBatch);
                this.form.price = parseFloat(price || 0).toFixed(2);
            } else {
                this.form.batch_code = null;
                this.form.price = '0.00';
            }
        },

        onPriceTypeChange() {
            if (this.form.product_id && this.selectedBatchDetails) {
                this.form.price = parseFloat(this.getBatchPrice(this.selectedBatchDetails) || 0).toFixed(2);
            } else {
                this.form.price = '0.00';
            }
        },

        validateQuantity() {
            this.handleInput('quantity');
            const quantity = parseFloat(this.form.quantity) || 0;

            if (quantity <= 0) {
                this.form.errors.quantity = null;
            } else if (this.editable && this.selectedProductStock !== null && quantity > this.selectedProductStock) {
                this.form.errors.quantity = `Quantity cannot exceed available stock for batch ${this.form.batch_code} (${this.selectedProductStock})`;
            } else if (!this.editable && this.totalAllocatableStock > 0 && quantity > this.totalAllocatableStock) {
                this.form.errors.quantity = `Quantity cannot exceed total available stock from batch ${this.form.batch_code} onward (${this.totalAllocatableStock})`;
            } else {
                this.form.errors.quantity = null;
            }
            this.validateDiscount();
        },

        allocateItemQuantities(baseItem) {
            return this.batchAllocationsPreview.map((allocation, index) => ({
                    ...baseItem,
                    id: Date.now() + index,
                    quantity: allocation.quantity,
                    batch_code: allocation.batch_code,
                    price: Number(allocation.price || 0).toFixed(2),
                }));
        },

        getBatchPrice(batch) {
            if (!batch) return 0;
            return this.form.price_type === 'wholesale' ? batch.wholesale_price : batch.retail_price;
        },

        validateDiscount() {
            this.handleInput('discount_per_unit');
            const totalCost = parseFloat(this.form.price).toFixed(2) * parseFloat(this.form.quantity);
            const totalDiscount = parseFloat(this.form.discount_per_unit) * parseFloat(this.form.quantity);

            if (totalDiscount > totalCost) {
                this.form.errors.discount_per_unit = 'Total discount cannot exceed total cost';
            } else {
                this.form.errors.discount_per_unit = null;
            }
        },



        formatCurrency(value) {
            if (!value) return '₱0.00';
            const num = Number(value);
            if (isNaN(num)) return '₱0.00';
            return '₱' + num.toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        
    }
}
</script>

<style scoped>
.stock-info {
    margin-top: 0.25rem;
    padding: 0.25rem 0.5rem;
    background-color: #f8f9fa;
    border-radius: 4px;
    border-left: 3px solid #17a2b8;
}

.stock-info small {
    font-size: 0.75rem;
    color: #6c757d;
}

.stock-info strong {
    color: #17a2b8;
}

.batch-stock-list {
    margin-top: 0.35rem;
}

.batch-stock-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.75rem;
    padding: 0.2rem 0.35rem;
    border-radius: 4px;
    background: #ffffff;
    border: 1px solid #e9ecef;
    margin-bottom: 0.2rem;
}

.split-preview-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.split-preview-item {
    padding: 0.5rem;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    background: #f8f9fa;
}

.split-preview-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.35rem;
    font-size: 0.8rem;
    color: #495057;
}
</style>
