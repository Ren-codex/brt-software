<template>
    <div 
        v-if="showModal"
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
                            <label for="brand_id" class="form-label">Brand</label>
                            <div class="input-wrapper">
                                <i class="ri-calendar-line input-icon"></i>
                                <b-form-select
                                class="form-control"
                                v-model="form.brand_id"
                                :options="dropdowns.brands"
                                :class="{ 'input-error': form.errors.brand_id }"
                                text-field="name"
                                value-field="value"
                                >
                                 <template #first>
                                    <b-form-select-option :value="null" disabled  >Select Brand</b-form-select-option>
                                </template>
                                </b-form-select>    
                            </div>
                            <span class="error-message" v-if="form.errors.brand_id">{{ form.errors.brand_id }}</span>
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
                                    v-model="form.quantity" 
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
                                />
                            </div>
                            <span class="error-message" v-if="form.errors.unit_cost">{{ form.errors.unit_cost }}</span>
                        </div>
                    </div>


                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label for="brand_id" class="form-label">Unit</label>
                            <div class="input-wrapper">
                                <i class="ri-bar-chart-2-line input-icon"></i>
                                <b-form-select
                                class="form-control"
                                v-model="form.unit_id"
                                :options="dropdowns.units"
                                :class="{ 'input-error': form.errors.unit_id }"
                                text-field="name"
                                value-field="value"
                                >
                                 <template #first>
                                    <b-form-select-option :value="null" disabled  >Select Unit</b-form-select-option>
                                </template>
                                </b-form-select>    
                            </div>
                            <span class="error-message" v-if="form.errors.unit_id">{{ form.errors.unit_id }}</span>
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
                        <button type="submit" class="btn btn-save" :disabled="form.processing">
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
import InputLabel from '@/Shared/Components/Forms/InputLabel.vue';
import TextInput from '@/Shared/Components/Forms/TextInput.vue';
import Multiselect from '@/Shared/Components/Forms/Multiselect.vue';
import Amount from '@/Shared/Components/Forms/Amount.vue';

export default {
    components: { InputLabel, TextInput, Multiselect, Amount },
    props: ['dropdowns', 'items'],
    data() {
        return {
            currentUrl: window.location.origin,
            form: useForm({
                id: null,
                brand_id: null,
                quantity: null,
                unit_cost: null,
                unit_id: null,
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
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = true;
        },
        edit(data, index) {
            this.form.brand_id = data.brand_id;
            this.form.quantity = data.quantity;
            this.form.unit_cost = data.unit_cost;
            this.form.unit_id = data.unit_id;
            this.form.total_amount = data.total_amount;
            this.editable = true;
            this.saveSuccess = false;
            this.showModal = true;
        },
        submit(index) {
            if (this.editable) {
                this.$emit('items', this.form);
            } else {
                const stored_item = {
                    id: Date.now(),
                    brand_id: this.form.brand_id,
                    quantity: this.form.quantity,
                    unit_cost: this.form.unit_cost,
                    unit_id: this.form.unit_id,
                    total_amount: this.form.amount,
                };

                this.$emit('items', stored_item);
            }
            this.showModal = false;
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

        updateUnitCost(value){
            // Remove ₱ symbol and commas
            const cleanValue = value.replace(/[₱,]/g, '');
            
            // Optional: convert to float
            this.form.unit_cost = parseFloat(cleanValue || 0).toFixed(2);
        }

        
    }
}
</script>


