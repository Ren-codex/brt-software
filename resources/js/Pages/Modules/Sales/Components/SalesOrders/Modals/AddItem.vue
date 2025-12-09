<template>
    <div 
        v-show="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"
        
    >
        <div class="modal-container modal-lg">
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
                                    @input="handleInput('quantity')"
                                />
  
                            </div>
                            <span class="error-message" v-if="form.errors.quantity">{{ form.errors.quantity }}</span>
                        </div>
                         <div class="form-group form-group-half">
                            <label for="unit_cost" class="form-label">Unit Cost</label>
                            <div class="input-wrapper">
                                <i class="ri-cash-line input-icon"></i>
                                <Amount
                                    @amount="updateUnitCost($event)"
                                    :class="{ 'input-error': form.errors.unit_cost }"
                                    class="form-control"
                                    ref="amountComponent"
                                />
                            </div>
                            <span class="error-message" v-if="form.errors.unit_cost">{{ form.errors.unit_cost }}</span>
                        </div>
                    </div>

                    {{  dropdowns.products }}
                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label for="product_id" class="form-label">Product</label>
                            <div class="input-wrapper">
                                <i class="ri-bar-chart-2-line input-icon"></i>
                                <b-form-select
                                class="form-control"
                                v-model="form.product_id"
                                :options="dropdowns.products"
                                :class="{ 'input-error': form.errors.product_id }"
                                text-field="name"
                                value-field="value"
                                >
                                 <template #first>
                                    <b-form-select-option :value="null" disabled  >Select Product</b-form-select-option>
                                </template>
                                </b-form-select>    
                            </div>
                            <span class="error-message" v-if="form.errors.product_id">{{ form.errors.product_id }}</span>
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
                        <button type="submit" class="btn btn-save" :disabled="form.processing || (!form.brand_id || form.quantity == 0 || form.unit_cost == 0 || !form.product_id)">
                            <i class="ri-save-line" v-if="!form.processing"></i>
                            <i class="ri-loader-4-line spinner" v-else></i>
                            {{ form.processing ? 'Saving...' : 'Save Order' }}
                        </button>
                    </div>  


                </form>
            </div>
        </div>
    </div>
</template>

<script>
import { useForm } from '@inertiajs/vue3';
import { nextTick } from 'vue';
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
                unit_cost: 0.00,
                product_id: null,
            }),

            togglePassword: false,
            toggleConfirm: false,
            passwordMismatch: false,
            showModal: false,
            editable: false,
            saveSuccess: false,
        }
    },
    methods: { 
        show() {
            this.form.reset();
            this.$refs.amountComponent.emitValue(0.00);
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = true;
        },
        edit(data, index) {
            this.form.id = data.id;
            this.form.brand_id = data.brand_id;
            this.form.quantity = data.quantity;
            this.$refs.amountComponent.emitValue(data.unit_cost);
            this.form.product_id = data.product_id;
            this.form.total_amount = data.total_amount;
            this.editable = true;
            this.saveSuccess = false;
            this.showModal = true;
            console.log(data , 33);
        },
        submit() {
            const itemData = {
                id: this.form.id,
                brand_id: this.form.brand_id,
                quantity: this.form.quantity,
                unit_cost: Number(this.form.unit_cost).toFixed(2), // 2 decimals
                product_id: this.form.product_id,
                total_amount: this.form.amount || 0,
            };

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
            this.form.unit_cost = null;
            this.form.reset();
            this.form.clearErrors();
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = false;
        },

        updateUnitCost(value) {
            if (typeof value === 'string') {
                const cleanValue = value.replace(/[₱,]/g, '');
                const num = parseFloat(cleanValue);
                this.form.unit_cost = isNaN(num) ? 0 : num;
            } else {
                this.form.unit_cost = value || 0;
            }
        },



        formatCurrency(value) {
            if (!value) return '₱0.00';
            return '₱' + Number(value).toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        
    }
}
</script>


