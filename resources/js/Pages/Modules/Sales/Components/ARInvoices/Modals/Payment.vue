<template>
    <div
        v-show="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"
    >
        <div class="modal-container modal-lg" @click.stop>
            <div class="modal-header bg-primary text-white">
                <h4 class="mb-0 text-white">
                    <i class="ri-money-dollar-circle-line me-2"></i>
                    Record Invoice Payment
                </h4>
                <button class="close-btn text-white" @click="hide">
                    <i class="ri-close-line fs-20"></i>
                </button>
            </div>
            <div class="modal-body p-4">
                <!-- Invoice Summary Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0 text-primary">
                            <i class="ri-file-list-line me-2"></i>
                            Invoice Summary
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="text-center">
                                    <i class="ri-file-text-line fs-24 text-muted mb-2"></i>
                                    <p class="text-muted small mb-1">Invoice #</p>
                                    <h6 class="fw-bold">{{ invoice?.invoice_number || 'N/A' }}</h6>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <i class="ri-user-line fs-24 text-muted mb-2"></i>
                                    <p class="text-muted small mb-1">Customer</p>
                                    <h6 class="fw-bold">{{ invoice?.sales_order?.customer?.name || 'N/A' }}</h6>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <i class="ri-calendar-line fs-24 text-muted mb-2"></i>
                                    <p class="text-muted small mb-1">Due Date</p>
                                    <h6 class="fw-bold">{{ invoice?.invoice_date || 'N/A' }}</h6>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="text-center">
                            <h4 class="text-success mb-0">
                                Outstanding Balance: <p class="fw-bold text-dark">{{ numberFormat(form.balance_due?.toFixed(2)) }}</p>
                            </h4>
                        </div>
                    </div>
                </div>

                <!-- Payment Details -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light">
                        <h6 class="mb-0 text-primary">
                            <i class="ri-edit-line me-2"></i>
                            Payment Details
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="payment_amount" class="form-label fw-semibold">
                                    <i class="ri-money-dollar-circle-line me-2 text-success"></i>
                                    Payment Amount <span class="text-danger">*</span>
                                </label>
                                <Amount
                                    v-model="form.amount_paid"
                                    ref="amount_paid"
                                    id="payment_amount"
                                    @input="handleInput('amount_paid')"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.amount_paid }"
                                />
                                <div class="invalid-feedback" v-if="form.errors.amount_paid">{{ form.errors.amount_paid }}</div>
                            </div>
                            <div class="col-md-6">
                                <label for="payment_date" class="form-label fw-semibold">
                                    <i class="ri-calendar-line me-2 text-info"></i>
                                    Payment Date <span class="text-danger">*</span>
                                </label>
                                <input
                                    type="date"
                                    class="form-control"
                                    id="payment_date"
                                    v-model="form.payment_date"
                                    @input="handleInput('payment_date')"
                                    :class="{ 'is-invalid': form.errors.payment_date }"
                                />
                                <div class="invalid-feedback" v-if="form.errors.payment_date">{{ form.errors.payment_date }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 p-4">
                <button class="btn btn-outline-secondary me-3" @click="hide">
                    <i class="ri-close-line me-2"></i>
                    Cancel
                </button>
                <button class="btn btn-primary" @click="submit">
                    <i class="ri-check-line me-2"></i>
                    Record Payment & Generate Receipt
                </button>
            </div>
        </div>
    </div>
</template>
<script>

import { useForm } from '@inertiajs/vue3';
import Amount from '@/Shared/Components/Forms/Amount.vue';


export default {
    components: { Amount },
    props: [ ],
    data(){
        return {
            currentUrl: window.location.origin,
            form: useForm({
                id: null,
                action: 'payment',
                payment_mode: null,
                balance_due: 0.00,
                amount_paid: 0.00,
                payment_date: new Date().toISOString().slice(0, 10),  // current date,
                billing_account: null,
                option: 'payment',
            }),

            payment_modes: ['Cash', 'Credit Card', 'Debit Card','Bank Transfer'],
            title: null,
            table: null,
            showModal: false,
            invoice: null,
        }
    },

    methods: { 
        show(data, title, route){
            this.showModal = true;
            this.invoice = data;
            this.form.id = data.id;
            this.form.balance_due = data.balance_due;
            this.$refs.amount_paid.emitValue(this.form.balance_due?.toFixed(2));
            this.title = title;
            this.route = route;
        },

        submit(){
            this.form.amount_paid = this.cleanAmount(this.form.amount_paid);
            this.form.put(`${this.route}/${this.form.id}`,{
                preserveScroll: true,
                onSuccess: (response) => {
                    this.$emit('approve', true);
                    this.form.amount_paid = 0.00;
                    this.form.reset();
                    this.hide();
                },
            });

        },

        cleanAmount(value) {
            // Remove currency symbol and commas, then parse to float
            return parseFloat(value.replace(/₱|,/g, '')) || 0;
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

        numberFormat(value) {
            return new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP',
                minimumFractionDigits: 2
            }).format(value);
        }


   
    }
}
</script>
<style scoped>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1050;
    transition: opacity 0.3s ease;
}

.modal-overlay.active {
    opacity: 1;
}

.modal-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    max-width: 700px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    animation: modalFadeIn 0.3s ease-out;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid #dee2e6;
    border-radius: 12px 12px 0 0;
}

.close-btn {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    position: absolute;
    top: 1rem;
    right: 1rem;
}

.payment-mode-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 1rem;
}

.payment-mode-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1.5rem 1rem;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    background: white;
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: center;
}

.payment-mode-card:hover {
    border-color: #007bff;
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.15);
    transform: translateY(-2px);
}

.selected-payment-mode {
    border-color: #007bff;
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
}

.payment-icon {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.payment-label {
    font-weight: 600;
    font-size: 0.9rem;
}

.error-message {
    color: #dc3545;
    font-size: 0.875rem;
    margin-top: 0.5rem;
}

.card {
    border-radius: 8px;
}

.card-header {
    border-radius: 8px 8px 0 0 !important;
}

.btn {
    border-radius: 6px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}
</style>
