<template>
    <div 
        v-show="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"
        
    >
        <div class="modal-container modal-xl" @click.stop>
                <div class="modal-header">
                    <h2>{{ ' Sales Order Payment' }}</h2>
                    <button class="close-btn" @click="hide">
                        <i class="ri-close-line"></i>
                    </button>
                </div>
            <div class=" p-5">
                <h5>TOTAL AMOUNT: ₱0.00</h5>
                <div class="mt-3 mb-3">
                     <label for="billing_account" class="form-label">Billing Account<span class="text-danger">*</span></label>
                    <div class="payment-mode-buttons">
                  
                        <b-button
                            v-for="mode in payment_modes"
                            :key="mode"
                            :class="{ 'selected-payment-mode': form.payment_mode === mode }"
                            variant="outline-primary"
                            @click="selectPaymentMode(mode)"
                            class="me-2 mb-2 text-center"
                            size="sm"
                        >
                            <i :class="getPaymentModeIcon(mode)" class="me-1"></i>
                            {{ mode }}
                        </b-button>
                    </div>
                   
                </div>
                <div  class="mb-3">
                     <span class="error-message" v-if="form.errors.payment_mode">{{ form.errors.payment_mode }}</span>
                    <div class="form-group form-group-half" v-if="form.payment_mode !== 'Cash'">
                        <label for="billing_account" class="form-label">Billing Account<span class="text-danger">*</span></label>
                        <div class="input-wrapper">
                            <i class="ri-bank-card-line input-icon"></i>
                            <input
                                type="text"
                                id="billing_account"
                                v-model="form.billing_account"
                                class="form-control"
                                :class="{ 'input-error': form.errors.billing_account }"
                                @input="handleInput('billing_account')"
                                placeholder="Enter Billing Account"
                            />
                        </div>
                        <span class="error-message" v-if="form.errors.billing_account">{{ form.errors.billing_account }}</span>
                    </div>
                </div>

                

                <div class="form-row">
                    <div class="form-group mb-3 form-group-half">
                        <label for="payment_amount">Payment Amount<span class="text-danger">*</span></label>
                        <Amount v-model="form.payment_amount" id="payment_amount" @input="handleInput('payment_amount')" />
                        <span class="error-message" v-if="form.errors.payment_amount">{{ form.errors.payment_amount }}</span>
                    </div>

                    <div class="form-group mb-3 form-group-half">
                        <label for="payment_date">Payment Date<span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="payment_date" v-model="form.payment_date" @input="handleInput('payment_date')" />
                        <span class="error-message" v-if="form.errors.payment_date">{{ form.errors.payment_date }}</span>
                    </div>
                </div>

            </div>
            <div class="modal-footer m-3">
                <button class="btn btn-secondary me-2" @click="hide">Close</button>
                <button class="btn btn-primary" @click="submit">Record Payment & Generate Receipt</button>
            </div>
        </div>
    </div>
</template>
<script>

import { useForm } from '@inertiajs/vue3';
import Amount from '@/Shared/Components/Forms/Amount.vue';


export default {
    components: { Amount },
    props: [],
    data(){
        return {
            currentUrl: window.location.origin,
            form: useForm({
                id: null,
                action: 'approve',
                payment_mode: null,
                payment_amount: null,
                payment_date: new Date().toISOString().slice(0, 10),  // current date,
                billing_account_number: null,
            }),

            payment_modes: ['Cash', 'Credit Card', 'Debit Card','Bank Transfer'],
            title: null,
            table: null,
            showModal: false,
        }
    },
    methods: { 
        show(id, title, route){
            this.showModal = true;
            this.form.id = id;
            this.title = title;
            this.route = route;
        },

        submit(){
            this.form.put(`${this.route}/${this.form.id}`,{
                preserveScroll: true,
                onSuccess: (response) => {
                    this.$emit('approve', true);
                    this.form.reset();
                    this.hide();
                },
            });

        },
        handleInput(field) {
            this.form.errors[field] = false;
        },
        hide(){
            this.editable = false;
            this.showModal = false;
        },

        getPaymentModeIcon(mode) {
            const icons = {
                'Cash': 'ri-money-dollar-circle-line',
                'Credit Card': 'ri-bank-card-line',
                'Debit Card': 'ri-bank-card-2-line',
                'Bank Transfer': 'ri-exchange-dollar-line'
            };
            return icons[mode] || 'ri-money-dollar-circle-line';
        },

        selectPaymentMode(mode) {
            this.form.payment_mode = mode;
            this.handleInput('payment_mode');
        },


   
    }
}
</script>
<style scoped>

.modal-container {
    max-width: 50%;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
}

.modal-body {
    padding-bottom: 80px; /* Space for fixed buttons */
}
    .payment-mode-buttons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
}

.payment-mode-buttons .btn {
    min-width: 70px;
    min-height: 70px;
    justify-content: flex-start;
    border: 1px solid lightgreen;
}

.selected-payment-mode {
    border-width: 2px !important;
    background-color: transparent !important;
    border: 3px solid darkgreen;
}

.payment-mode-buttons .btn:hover {
    border-color:darkgreen !important;
    border: 2px solid darkgreen;
    background-color: transparent !important;
    color:darkgreen;
}

.btn:focus, .btn:active {
    border-color:darkgreen !important;
    border: 2px solid darkgreen;
    background-color: transparent !important;
    color:darkgreen;
}

</style>