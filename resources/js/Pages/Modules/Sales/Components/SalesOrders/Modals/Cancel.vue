<template>
    <div v-show="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="hide">
        <div class="modal-container" @click.stop>
            <div class="modal-header">
                <div class="modal-title-wrap">
                    <div class="modal-badge">
                        <i class="ri-error-warning-line"></i>
                    </div>
                    <div>
                        <span class="modal-kicker">Verification Required</span>
                        <h2>Cancel {{ title }}</h2>
                    </div>
                </div>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>

            <div class="modal-body">
                <div class="verification-panel">
                    <div v-if="hasPayments" class="warning-card payment-warning-card">
                        <div class="warning-icon payment-warning-icon">
                            <i class="ri-bank-card-line"></i>
                        </div>
                        <div>
                            <h5>Payments are recorded on this order.</h5>
                            <p>You must void or refund all receipts before this Sales Order can be cancelled.</p>
                        </div>
                    </div>
                    <div v-else class="warning-card">
                        <div class="warning-icon">
                            <i class="ri-alert-fill"></i>
                        </div>
                        <div>
                            <h5>You're about to void this record.</h5>
                            <p>This action will cancel the sales order and apply the related cancellation updates tied to it.</p>
                        </div>
                    </div>
                    <div v-if="form.errors.cancel" class="error-alert">
                        <i class="ri-error-warning-line me-1"></i> {{ form.errors.cancel }}
                    </div>

                    <div class="verification-card">
                        <label class="verification-label" for="cancel_verification">
                            Type <strong>CANCEL</strong> to continue
                        </label>
                        <input
                            id="cancel_verification"
                            v-model.trim="confirmationText"
                            type="text"
                            class="verification-input"
                            :class="{ 'verification-input-error': form.errors.confirmation }"
                            placeholder="Type CANCEL"
                            @input="handleInput('confirmation')"
                        />
                        <small v-if="form.errors.confirmation" class="verification-error">
                            {{ form.errors.confirmation }}
                        </small>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" @click="hide">Keep Order</button>
                <button class="btn btn-danger" @click="submit" :disabled="form.processing || !isVerificationMatched || hasPayments">
                    <i class="ri-loader-4-line spinner" v-if="form.processing"></i>
                    <i class="ri-close-circle-line" v-else></i>
                    {{ form.processing ? 'Cancelling...' : 'Confirm Cancellation' }}
                </button>
            </div>
        </div>
    </div>
</template>
<script>

import { useForm } from '@inertiajs/vue3';

export default {
    components: { },
    props: [],
    data(){
        return {
            currentUrl: window.location.origin,
            form: useForm({
                id: null,
                action: 'cancel',
            }),
            title: null,
            table: null,
            showModal: false,
            confirmationText: '',
            hasPayments: false,
        }
    },
    computed: {
        isVerificationMatched() {
            return this.confirmationText.toUpperCase() === 'CANCEL';
        }
    },
    methods: { 
        show(id, title, route, hasPayments = false){
            this.showModal = true;
            this.form.id = id;
            this.title = title;
            this.route = route;
            this.confirmationText = '';
            this.hasPayments = hasPayments;
            this.form.clearErrors();
        },

        submit(){
            if (!this.isVerificationMatched) {
                this.form.errors.confirmation = 'Please type CANCEL to verify this action.';
                return;
            }

            this.form.delete(`${this.route}/${this.form.id}`,{
                preserveScroll: true,
                onSuccess: (response) => {
                    this.$emit('cancel', true);
                    this.form.reset();
                    this.hide();
                },
            });

        },
        handleInput(field) {
            this.form.errors[field] = false;
            if (field === 'confirmation') {
                this.form.errors.confirmation = false;
            }
        },
        hide(){
            this.editable = false;
            this.showModal = false;
            this.confirmationText = '';
            this.form.clearErrors();
        },

   
    }
}
</script>
<style scoped>
.modal-container {
    width: min(100%, 560px);
    max-height: calc(100vh - 2rem);
}

.modal-title-wrap {
    display: flex;
    align-items: center;
    gap: 0.9rem;
}

.modal-badge {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    background: rgba(61, 141, 122, 0.12);
    border: 1px solid rgba(61, 141, 122, 0.16);
    color: #3d8d7a;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.modal-kicker {
    display: inline-block;
    margin-bottom: 0.2rem;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #6b8c85;
}

.modal-header h2 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 700;
    color: #16322e;
}

.modal-body {
    padding: 1.1rem 1.35rem 1.35rem;
}

.verification-panel {
    display: grid;
    gap: 1rem;
}

.warning-card,
.verification-card {
    border-radius: 20px;
    padding: 1rem 1.05rem;
    border: 1px solid rgba(214, 83, 83, 0.12);
    background: rgba(255, 255, 255, 0.82);
}

.payment-warning-card {
    border-color: rgba(220, 53, 69, 0.3);
    background: rgba(220, 53, 69, 0.05);
}

.payment-warning-icon {
    background: linear-gradient(135deg, #ffd7d7 0%, #ffb3b3 100%);
    color: #b91c1c;
}

.error-alert {
    border-radius: 12px;
    padding: 0.65rem 1rem;
    background: rgba(220, 53, 69, 0.1);
    border: 1px solid rgba(220, 53, 69, 0.25);
    color: #b91c1c;
    font-size: 0.85rem;
    font-weight: 600;
}

.warning-card {
    display: flex;
    gap: 0.9rem;
    align-items: flex-start;
}

.warning-icon {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    background: linear-gradient(135deg, #ffe8d6 0%, #ffd7d7 100%);
    color: #d35400;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.warning-card h5 {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: #2d1f1f;
}

.warning-card p {
    margin: 0.35rem 0 0;
    color: #766666;
    font-size: 0.9rem;
    line-height: 1.55;
}

.verification-label {
    display: block;
    margin-bottom: 0.6rem;
    color: #4d4040;
    font-size: 0.9rem;
    font-weight: 600;
}

.verification-input {
    width: 100%;
    height: 50px;
    border-radius: 14px;
    border: 1px solid #ead7d7;
    background: #fff;
    padding: 0 1rem;
    font-size: 0.95rem;
    text-align: center;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    transition: all 0.2s ease;
}

.verification-input:focus {
    outline: none;
    border-color: #d66767;
    box-shadow: 0 0 0 4px rgba(214, 103, 103, 0.12);
}

.verification-input-error {
    border-color: #dc3545;
}

.verification-error {
    display: block;
    margin-top: 0.5rem;
    color: #dc3545;
    font-size: 0.82rem;
}

.modal-footer {
    gap: 0.8rem;
    padding: 0 1.35rem 1.35rem;
}

.btn {
    min-height: 46px;
    padding: 0.7rem 1.1rem;
    border: none;
    border-radius: 14px;
    font-size: 0.9rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    transition: all 0.2s ease;
}

.btn-secondary {
    background: #f1f3f5;
    color: #5f6368;
}

.btn-secondary:hover {
    background: #e4e7eb;
}

.btn-danger {
    background: linear-gradient(135deg, #dc3545 0%, #b91c1c 100%);
    color: #fff;
    box-shadow: 0 10px 24px rgba(185, 28, 28, 0.22);
}

.btn-danger:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 14px 28px rgba(185, 28, 28, 0.28);
}

.btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
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

@media (max-width: 576px) {
    .modal-header,
    .modal-body,
    .modal-footer {
        padding-left: 1rem;
        padding-right: 1rem;
    }

    .modal-footer {
        flex-direction: column-reverse;
    }

    .btn {
        width: 100%;
    }
}
</style>
