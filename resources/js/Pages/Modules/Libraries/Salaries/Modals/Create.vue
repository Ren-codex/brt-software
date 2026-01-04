<template>
    <div 
        v-if="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"
    >
        <div class="modal-container" @click.stop>
            <div class="modal-header">
                <h2>{{ editable ? 'Update Salary' : 'Salary Information' }}</h2>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body">


                <form @submit.prevent="submit">
                    <div class="form-group">
                        <label for="amount" class="form-label">Amount</label>
                        <div class="input-wrapper">
                            <i class="ri-user-settings-line input-icon"></i>
                            {{  this.form.amount  }} hey
                            <Amount
                                @amount="amount"
                               ref="amountComponent"
                                :class="{ 'input-error': form.errors.amount }"
                                class="form-control"
                               
                            />
                        </div>
                        <span class="error-message" v-if="form.errors.amount">{{ form.errors.amount }}</span>
                    </div>




                    
                    <div class="success-alert" v-if="saveSuccess">
                        <i class="ri-checkbox-circle-fill"></i>
                        <span>Your information has been saved successfully!</span>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-cancel" @click="hide">
                            <i class="ri-close-line"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-save" :disabled="form.processing">
                            <i class="ri-save-line" v-if="!form.processing"></i>
                            <i class="ri-loader-4-line spinner" v-else></i>
                            {{ form.processing ? 'Saving...' : 'Save Information' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import { useForm } from '@inertiajs/vue3';
import Multiselect from "@vueform/multiselect";
import InputLabel from '@/Shared/Components/Forms/InputLabel.vue';
import TextInput from '@/Shared/Components/Forms/TextInput.vue';
import ColorInput from '@/Shared/Components/Forms/ColorInput.vue';
import Amount from "@/Shared/Components/Forms/Amount.vue";


export default {
    components: { InputLabel, TextInput, ColorInput, Multiselect, Amount },
    props: ['dropdowns'],
    data() {
        return {
            currentUrl: window.location.origin,
            form: useForm({
                id: null,
                amount: null,
            }),
            togglePassword: false,
            toggleConfirm: false,
            passwordMismatch: false,
            showModal: false,
            editable: false,
            saveSuccess: false,
        }
    },

    watch: {
        "form.amount"(val){
            if(val){
                this.$refs.amountComponent.emitValue(val);
            }
        }
    },
    methods: { 

        amount(val){

            this.form.amount =  this.cleanCurrency(val);

        },

        cleanCurrency(value) {
            if (!value) return 0;
            // Remove ₱, commas, and spaces
            const cleaned = value.toString().replace(/[^0-9.]/g, "");
            return parseFloat(cleaned);
        },
        show() {
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = true;
        },
        edit(data, index) {
            this.form.id = data.id;
            this.form.amount = data.amount;
            this.editable = true;
            this.saveSuccess = false;
            this.showModal = true;
        },
         submit() {
            if (this.editable) {
                this.form.put(`/libraries/salaries/${this.form.id}`, {
                    preserveScroll: true,
                    onSuccess: (response) => {
                        this.$emit('add', true);
                        this.hide();
                    },
                });
            } else {
                this.form.post('/libraries/salaries', {
                    preserveScroll: true,
                    onSuccess: (response) => {
                        this.$emit('add', true);
                        this.hide();
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
        }
    }
}
</script>
