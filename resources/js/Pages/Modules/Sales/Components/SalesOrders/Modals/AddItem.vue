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
                                    :max="selectedProductStock"
                                    @input="validateQuantity"
                                />

                            </div>
                            <div v-if="selectedProductStock !== null" class="stock-info">
                                <small class="text-muted">
                                    <i class="ri-information-line"></i>
                                    Available Stock: <strong>{{ selectedProductStock }}</strong>
                                </small>
                            </div>
                            <span class="error-message" v-if="form.errors.quantity">{{ form.errors.quantity }}</span>
                        </div>
                         <div class="form-group form-group-half">
                            <label for="price" class="form-label">Price</label>
                            <div class="input-wrapper">
                                <i class="ri-cash-line input-icon"></i>
                                <input
                                    type="text"
                                    :value="formatCurrency(form.price)"
                                    class="form-control"
                                    :class="{ 'input-error': form.errors.price }"
                                    readonly
                                />
                            </div>
                            <span class="error-message" v-if="form.errors.price">{{ form.errors.price }}</span>
                        </div>
                    </div>


                  

                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label for="batch_code" class="form-label">Batch Code</label>
                            <div class="input-wrapper">
                                <i class="ri-barcode-line input-icon"></i>
                                <TextInput
                                    type="text"
                                    id="batch_code"
                                    v-model="form.batch_code"
                                    class="form-control"
                                    :class="{ 'input-error': form.errors.batch_code }"
                                    placeholder="Batch Code"
                                    readonly
                                    @input="handleInput('batch_code')"
                                />
                            </div>
                            <span class="error-message" v-if="form.errors.batch_code">{{ form.errors.batch_code }}</span>
                        </div>

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
    props: ['dropdowns', 'items' , 'update' ],
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
        selectedProductStock() {
            if (!this.form.product_id) return null;
            const product = this.dropdowns.products.find(p => p.value === this.form.product_id);
            return product ? product.available : null;
        },
        availableProducts() {
            return this.dropdowns.products.filter(product => product.available > 0);
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
            this.onProductChange(); // Recalculate price and batch_code based on current inventory
            this.form.discount_per_unit = data.discount_per_unit || 0;
            this.form.total_amount = data.total_amount;
            this.editable = true;
            this.saveSuccess = false;
            this.showModal = true;
            this.validateQuantity(); // Validate quantity against current stock
        },

        submit() {
            // Re-validate discount before submitting
            this.validateDiscount();

            // // Check for any validation errors before submitting
            // if (Object.keys(this.form.errors).length > 0) {
            //     return; // Prevent submission if there are errors
            // }

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

            console.log('Submitting item:', itemData); // Debug log

            if (this.editable) {
                this.$emit('update', itemData );
            } else {
                itemData.id = Date.now();
                this.$emit('items', itemData);
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
            console.log(this.dropdowns.product, 77);
            console.log(product, 888);
            if (product) {
                this.form.batch_code = product.batch_code || null;
                const price = this.form.price_type === 'wholesale' ? product.wholesale_price : product.retail_price;
                this.form.price = parseFloat(price || 0).toFixed(2);
            } else {
                this.form.batch_code = null;
                this.form.price = '0.00';
            }
        },

        onPriceTypeChange() {
            if (this.form.product_id) {
                this.onProductChange();
            } else {
                this.form.price = '0.00';
            }
        },

        validateQuantity() {
            this.handleInput('quantity');
            if (this.selectedProductStock !== null && this.form.quantity > this.selectedProductStock) {
                this.form.errors.quantity = `Quantity cannot exceed available stock (${this.selectedProductStock})`;
                this.form.quantity = this.selectedProductStock;
            } else {
                this.form.errors.quantity = null;
            }
            this.validateDiscount();
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
</style>




