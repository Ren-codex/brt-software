<template>
    <div v-if="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="hide">
        <div class="modal-container" @click.stop>
            <!-- Header Section -->
            <div class="modal-header">
                <div class="header-left">
                    <div class="header-text">
                        <h2>{{ editable ? 'Update Loan Application' : 'New Loan Application' }}</h2>
                    </div>
                </div>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>

            <!-- Body Section -->
            <div class="modal-body">
                <form @submit.prevent="submit">
                    <!-- Progress Steps -->
                    <!-- <div class="progress-steps">
                        <div class="step" :class="{ active: currentStep >= 1, completed: currentStep > 1 }">
                            <div class="step-number">1</div>
                            <div class="step-label">Borrower</div>
                        </div>
                        <div class="step-line" :class="{ active: currentStep > 1 }"></div>
                        <div class="step" :class="{ active: currentStep >= 2, completed: currentStep > 2 }">
                            <div class="step-number">2</div>
                            <div class="step-label">Loan Details</div>
                        </div>
                        <div class="step-line" :class="{ active: currentStep > 2 }"></div>
                        <div class="step" :class="{ active: currentStep >= 3, completed: currentStep > 3 }">
                            <div class="step-number">3</div>
                            <div class="step-label">Terms</div>
                        </div>
                    </div> -->

                    <!-- Step 1: Borrower Information -->
                    <div v-show="currentStep === 1" class="step-content">
                        <div class="section-title">
                            <i class="ri-user-settings-line"></i>
                            <h3>Borrower Information</h3>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="ri-user-line"></i>
                                Select Employee
                                <span class="required">*</span>
                            </label>
                            <Multiselect
                                v-model="form.employee_id"
                                :options="dropdowns.employees || []"
                                :searchable="true"
                                :close-on-select="true"
                                :allow-empty="false"
                                placeholder="Search employee by name or ID..."
                                label="name"
                                value-prop="value"
                                track-by="name"
                                :class="{ 'multiselect-error': form.errors.employee_id }"
                            />
                            <span class="error-message" v-if="form.errors.employee_id">
                                <i class="ri-alert-line"></i>
                                {{ form.errors.employee_id }}
                            </span>
                        </div>

                        <div class="info-card" v-if="selectedEmployee">
                            <div class="info-icon">
                                <i class="ri-bank-card-line"></i>
                            </div>
                            <div class="info-details">
                                <div class="info-row">
                                    <span class="info-label">Total Loan:</span>
                                    <span class="info-value">{{ selectedEmployee.total_loan_count || 0 }}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Total Unpaid Loan:</span>
                                    <span class="info-value">{{ formatCurrency(selectedEmployee.total_unpaid_loan || 0) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Loan Details -->
                    <div v-show="currentStep === 2" class="step-content">
                        <div class="section-title">
                            <i class="ri-file-list-line"></i>
                            <h3>Loan Details</h3>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <i class="ri-file-text-line"></i>
                                Loan Type
                                <span class="required">*</span>
                            </label>
                            <div class="loan-type-grid">
                                <div 
                                    v-for="type in loanTypes" 
                                    :key="type.value"
                                    class="loan-type-card"
                                    :class="{ active: form.loan_type === type.value }"
                                    @click="form.loan_type = type.value"
                                >
                                    <i :class="type.icon"></i>
                                    <span>{{ type.label }}</span>
                                </div>
                            </div>
                            <span class="error-message" v-if="form.errors.loan_type">
                                <i class="ri-alert-line"></i>
                                {{ form.errors.loan_type }}
                            </span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <i class="ri-money-dollar-circle-line"></i>
                                Loan Amount
                                <span class="required">*</span>
                            </label>
                            <div class="input-group">
                                <span class="currency-symbol">₱</span>
                                <input 
                                    type="number" 
                                    v-model="form.amount" 
                                    class="form-control" 
                                    :class="{ 'input-error': form.errors.amount }"
                                    placeholder="0.00"
                                >
                            </div>
                            <span class="error-message" v-if="form.errors.amount">
                                <i class="ri-alert-line"></i>
                                {{ form.errors.amount }}
                            </span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <i class="ri-edit-line"></i>
                                Purpose of Loan
                            </label>
                            <textarea 
                                v-model="form.purpose" 
                                class="form-control textarea-control" 
                                placeholder="Describe the purpose of this loan..."
                                rows="3"
                            ></textarea>
                        </div>
                    </div>

                    <!-- Step 3: Terms & Conditions -->
                    <div v-show="currentStep === 3" class="step-content">
                        <div class="section-title">
                            <i class="ri-calendar-check-line"></i>
                            <h3>Loan Terms</h3>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label class="form-label">
                                    <i class="ri-percent-line"></i>
                                    Interest Rate
                                    <span class="required">*</span>
                                </label>
                                <div class="input-group">
                                    <input 
                                        type="number" 
                                        step="0.01" 
                                        v-model="form.interest_rate" 
                                        class="form-control" 
                                        :class="{ 'input-error': form.errors.interest_rate }"
                                        placeholder="Enter rate"
                                    >
                                    <span class="input-suffix">%</span>
                                </div>
                                <span class="error-message" v-if="form.errors.interest_rate">
                                    <i class="ri-alert-line"></i>
                                    {{ form.errors.interest_rate }}
                                </span>
                            </div>
                            <!-- Loan Summary Card -->
                            <div class="summary-card col-md-6" v-if="form.amount && form.term_months">
                                <div class="summary-header">
                                    <i class="ri-calculator-line"></i>
                                    <h4>Loan Summary</h4>
                                </div>
                                <div class="summary-content">
                                    <div class="summary-item">
                                        <span>Monthly Payment:</span>
                                        <strong>{{ formatCurrency(monthlyPayment) }}</strong>
                                    </div>
                                    <div class="summary-item">
                                        <span>Total Interest:</span>
                                        <strong>{{ formatCurrency(totalInterest) }}</strong>
                                    </div>
                                    <div class="summary-item total">
                                        <span>Total Payment:</span>
                                        <strong>{{ formatCurrency(totalPayment) }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group term-slider-group">
                                <label class="form-label">
                                    <i class="ri-calendar-line"></i>
                                    Term (Months)
                                    <span class="required">*</span>
                                </label>
                                <div class="term-slider-card" :class="{ 'slider-error': form.errors.term_months }">
                                    <div class="term-slider-scale">
                                        <span
                                            v-for="month in 24"
                                            :key="month"
                                            class="scale-label"
                                        >
                                            {{ month }}
                                        </span>
                                    </div>
                                    <div class="term-slider-wrap">
                                        <input
                                            type="range"
                                            v-model="form.term_months"
                                            class="term-slider"
                                            min="1"
                                            max="24"
                                            step="1"
                                        >
                                    </div>
                                    <div class="term-slider-value">
                                        <strong>{{ form.term_months || 0 }}</strong>
                                        <span>months</span>
                                    </div>
                                </div>
                                <span class="error-message" v-if="form.errors.term_months">
                                    <i class="ri-alert-line"></i>
                                    {{ form.errors.term_months }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Success Alert -->
                    <transition name="slide-fade">
                        <div class="success-alert" v-if="saveSuccess">
                            <i class="ri-checkbox-circle-fill"></i>
                            <div class="alert-content">
                                <strong>Success!</strong>
                                <span>Loan information has been saved successfully.</span>
                            </div>
                        </div>
                    </transition>

                    <!-- Form Actions -->
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" @click="previousStep" v-if="currentStep > 1">
                            <i class="ri-arrow-left-line"></i>
                            Previous
                        </button>
                        <button type="button" class="btn btn-primary" @click="nextStep" v-if="currentStep < 3">
                            Next
                            <i class="ri-arrow-right-line"></i>
                        </button>
                        <button type="submit" class="btn btn-success" v-if="currentStep === 3" :disabled="form.processing">
                            <i class="ri-save-line" v-if="!form.processing"></i>
                            <i class="ri-loader-4-line spinner" v-else></i>
                            {{ form.processing ? 'Processing...' : (editable ? 'Update Loan' : 'Submit Application') }}
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

export default {
    components: { Multiselect },
    props: ['dropdowns'],
    data() {
        return {
            currentStep: 1,
            loanTypes: [
                { value: 'personal', label: 'Personal Loan', icon: 'ri-user-line' },
                { value: 'salary', label: 'Salary Loan', icon: 'ri-money-dollar-circle-line' },
                { value: 'emergency', label: 'Emergency Loan', icon: 'ri-flashlight-line' },
                { value: 'housing', label: 'Housing Loan', icon: 'ri-home-line' }
            ],
            form: useForm({
                id: null,
                employee_id: null,
                loan_type: '',
                amount: '',
                interest_rate: 0,
                term_months: '',
                status: 'pending',
                purpose: ''
            }),
            showModal: false,
            editable: false,
            saveSuccess: false,
        }
    },
    computed: {
        selectedEmployee() {
            if (!this.form.employee_id || !this.dropdowns.employees) return null;
            return this.dropdowns.employees.find(emp => emp.value === this.form.employee_id);
        },
        principalAmount() {
            const value = parseFloat(this.form.amount);
            return Number.isFinite(value) ? value : 0;
        },
        interestRateValue() {
            const value = parseFloat(this.form.interest_rate);
            return Number.isFinite(value) ? value : 0;
        },
        termMonthsValue() {
            const value = parseInt(this.form.term_months, 10);
            return Number.isFinite(value) ? value : 0;
        },
        monthlyPayment() {
            if (this.principalAmount <= 0 || this.termMonthsValue <= 0) return 0;
            return this.totalPayment / this.termMonthsValue;
        },
        totalPayment() {
            return this.principalAmount + this.totalInterest;
        },

        totalInterest() {
            return this.principalAmount * (this.interestRateValue / 100);
        }
    },
    methods: {
        formatCurrency(value) {
            return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(value);
        },
        nextStep() {
            if (this.currentStep === 1 && !this.form.employee_id) {
                this.form.errors.employee_id = 'Please select an employee';
                return;
            }
            if (this.currentStep === 2) {
                if (!this.form.loan_type) {
                    this.form.errors.loan_type = 'Please select a loan type';
                    return;
                }
                if (!this.form.amount || this.form.amount <= 0) {
                    this.form.errors.amount = 'Please enter a valid loan amount';
                    return;
                }
            }
            if (this.currentStep === 3) {
                if (!this.form.interest_rate || this.form.interest_rate < 0) {
                    this.form.errors.interest_rate = 'Please enter a valid interest rate';
                    return;
                }
                if (!this.form.term_months || this.form.term_months <= 0) {
                    this.form.errors.term_months = 'Please enter a valid term';
                    return;
                }
            }
            this.currentStep++;
        },
        previousStep() {
            this.currentStep--;
        },
        show() {
            this.form.reset();
            this.form.status = 'pending';
            this.saveSuccess = false;
            this.editable = false;
            this.currentStep = 1;
            this.showModal = true;
        },
        edit(data, index) {
            this.selectedRow = index;
            this.form.clearErrors();
            this.form.id = data.id;
            this.form.employee_id = data.employee_id;
            this.form.loan_type = data.loan_type;
            this.form.amount = data.amount;
            this.form.interest_rate = data.interest_rate;
            this.form.term_months = data.term_months;
            this.form.status = data.status;
            this.form.purpose = data.purpose;
            this.editable = true;
            this.saveSuccess = false;
            this.currentStep = 1;
            this.showModal = true;
        },
        submit() {
            if (this.currentStep === 3) {
                if (this.editable) {
                    this.form.put(`/loans/${this.form.id}`, {
                        preserveScroll: true,
                        onSuccess: (response) => {
                            const updatedLoan = response?.props?.flash?.data?.data || this.$page?.props?.flash?.data?.data;
                            this.saveSuccess = true;
                            this.form.reset();
                            setTimeout(() => {
                                this.$emit('update', updatedLoan);
                                this.hide();
                            }, 1500);
                        },
                        onError: (error) => {
                            console.log(error);
                        }
                    });
                } else {
                    this.form.post('/loans', {
                        preserveScroll: true,
                        onSuccess: (response) => {
                            this.saveSuccess = true;
                            this.form.reset();
                            setTimeout(() => {
                                this.$emit('add', true);
                                this.hide();
                            }, 1500);
                        },
                    });
                }
            }
        },
        hide() {
            this.form.reset();
            this.form.clearErrors();
            this.saveSuccess = false;
            this.editable = false;
            this.currentStep = 1;
            this.showModal = false;
        }
    }
}
</script>

<style scoped>
/* Modal Overlay */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10050;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.modal-overlay.active {
    opacity: 1;
    visibility: visible;
}

/* Modal Container */
.modal-container {
    background: white;
    border-radius: 24px;
    width: 90%;
    max-width: 750px;
    max-height: 90vh;
    overflow-y: hidden;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    animation: modalSlideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes modalSlideUp {
    from {
        opacity: 0;
        transform: translateY(40px) scale(0.96);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.header-left {
    display: flex;
    align-items: center;
    gap: 1rem;
    position: relative;
    z-index: 1;
}

.icon-circle {
    width: 48px;
    height: 48px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(10px);
}

.icon-circle i {
    font-size: 28px;
    color: white;
}

.header-text h2 {
    margin: 0 0 4px 0;
    font-size: 1.5rem;
    font-weight: 600;
    color: white;
    letter-spacing: -0.3px;
}

.header-text p {
    margin: 0;
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.9);
}

.close-btn {
    background: rgba(255, 255, 255, 0.15);
    border: none;
    width: 36px;
    height: 36px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    color: white;
    position: relative;
    z-index: 1;
}

.close-btn:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: rotate(90deg);
}

.close-btn i {
    font-size: 20px;
}

/* Body */
.modal-body {
    padding: 2rem;
    background: white;
}

/* Progress Steps */
.progress-steps {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 2rem;
    padding: 0 1rem;
}

.step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}

.step-number {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #f3f4f6;
    color: #6b7280;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
    border: 2px solid #e5e7eb;
}

.step.active .step-number {
    background: #3d8d7a;
    color: white;
    border-color: #3d8d7a;
    box-shadow: 0 0 0 4px rgba(61, 141, 122, 0.2);
}

.step.completed .step-number {
    background: #c4dad2;
    color: #2c6b5c;
    border-color: #3d8d7a;
}

.step-label {
    font-size: 0.75rem;
    font-weight: 500;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.step.active .step-label {
    color: #3d8d7a;
    font-weight: 600;
}

.step-line {
    flex: 1;
    height: 2px;
    background: #e5e7eb;
    margin: 0 0.5rem;
    transition: all 0.3s ease;
}

.step-line.active {
    background: #3d8d7a;
}

/* Section Title */
.section-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid #c4dad2;
}

.section-title i {
    font-size: 24px;
    color: #3d8d7a;
}

.section-title h3 {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
    color: #1f2937;
}

/* Form Groups */
.form-group {
    margin-bottom: 1.5rem;
}

.term-slider-group {
    width: 100%;
    max-width: 100%;
    flex: 1 1 100%;
}

.form-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.625rem;
    font-weight: 500;
    color: #374151;
    font-size: 0.875rem;
}

.form-label i {
    color: #3d8d7a;
    font-size: 16px;
}

.required {
    color: #ef4444;
    margin-left: 2px;
}

/* Loan Type Grid */
.loan-type-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.loan-type-card {
    padding: 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    transition: all 0.2s ease;
    background: white;
}

.loan-type-card:hover {
    border-color: #c4dad2;
    background: #f9fafb;
    transform: translateY(-2px);
}

.loan-type-card.active {
    border-color: #3d8d7a;
    background: linear-gradient(135deg, #f0f9f6 0%, #e8f3ef 100%);
}

.loan-type-card i {
    font-size: 24px;
    color: #3d8d7a;
}

.loan-type-card span {
    font-weight: 500;
    color: #1f2937;
}

/* Input Groups */
.input-group {
    position: relative;
    display: flex;
    align-items: center;
}

.currency-symbol {
    position: absolute;
    left: 12px;
    font-weight: 600;
    color: #3d8d7a;
    font-size: 1rem;
}

.input-suffix {
    position: absolute;
    right: 12px;
    color: #6b7280;
    font-size: 0.875rem;
}

.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1.5px solid #e5e7eb;
    border-radius: 12px;
    font-size: 0.875rem;
    transition: all 0.2s ease;
    background: white;
}

.form-control:focus {
    outline: none;
    border-color: #3d8d7a;
    box-shadow: 0 0 0 3px rgba(61, 141, 122, 0.1);
}

.form-control.input-error {
    border-color: #ef4444;
}

.textarea-control {
    resize: vertical;
    font-family: inherit;
}

.input-group .form-control {
    padding-left: 2rem;
    padding-right: 2rem;
}

.term-slider-card {
    border: 1.5px solid #e5e7eb;
    border-radius: 16px;
    background: linear-gradient(180deg, #ffffff 0%, #f8faf9 100%);
    padding: 1rem 1rem 0.875rem;
    transition: all 0.2s ease;
}

.term-slider-card:focus-within {
    border-color: #3d8d7a;
    box-shadow: 0 0 0 3px rgba(61, 141, 122, 0.1);
}

.term-slider-card.slider-error {
    border-color: #ef4444;
}

.term-slider-scale {
    display: grid;
    grid-template-columns: repeat(24, minmax(0, 1fr));
    gap: 0.125rem;
    margin-bottom: 0.75rem;
    color: #64748b;
    font-size: 0.625rem;
    font-weight: 600;
}

.scale-label {
    text-align: center;
}

.term-slider-wrap {
    position: relative;
    padding-bottom: 1.25rem;
}

.term-slider-wrap::after {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    top: calc(50% - 1px);
    height: 6px;
    border-radius: 999px;
    background:
        repeating-linear-gradient(
            to right,
            transparent 0,
            transparent calc(10% - 1px),
            #dbe4e0 calc(10% - 1px),
            #dbe4e0 10%
        );
    z-index: 0;
    pointer-events: none;
}

.term-slider {
    position: relative;
    z-index: 1;
    width: 100%;
    margin: 0;
    appearance: none;
    background: transparent;
    cursor: pointer;
}

.term-slider:focus {
    outline: none;
}

.term-slider::-webkit-slider-runnable-track {
    height: 6px;
    border-radius: 999px;
    background: linear-gradient(90deg, #7eb7a7 0%, #3d8d7a 100%);
}

.term-slider::-webkit-slider-thumb {
    appearance: none;
    width: 16px;
    height: 16px;
    margin-top: -5px;
    border-radius: 50%;
    border: 3px solid #3d8d7a;
    background: #ffffff;
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.18);
}

.term-slider::-moz-range-track {
    height: 6px;
    border-radius: 999px;
    background: linear-gradient(90deg, #7eb7a7 0%, #3d8d7a 100%);
}

.term-slider::-moz-range-thumb {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 3px solid #3d8d7a;
    background: #ffffff;
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.18);
}

.term-slider-value {
    display: inline-flex;
    align-items: baseline;
    gap: 0.375rem;
    margin-top: 0.125rem;
    color: #475569;
    font-size: 0.875rem;
}

.term-slider-value strong {
    color: #1f2937;
    font-size: 1rem;
}

/* Info Card */
.info-card {
    background: linear-gradient(135deg, #f0f9f6 0%, #e8f3ef 100%);
    border-radius: 12px;
    padding: 1rem;
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
    border-left: 3px solid #3d8d7a;
}

.info-icon i {
    font-size: 32px;
    color: #3d8d7a;
}

.info-details {
    flex: 1;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 0.25rem 0;
}

.info-label {
    font-size: 0.75rem;
    color: #6b7280;
    font-weight: 500;
}

.info-value {
    font-size: 0.875rem;
    color: #1f2937;
    font-weight: 500;
}

/* Summary Card */
.summary-card {
    background: #f9fafb;
    border-radius: 12px;
    padding: 1rem;
    margin-top: 1rem;
    border: 1px solid #e5e7eb;
}

.summary-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #e5e7eb;
}

.summary-header i {
    font-size: 20px;
    color: #3d8d7a;
}

.summary-header h4 {
    margin: 0;
    font-size: 0.875rem;
    font-weight: 600;
    color: #1f2937;
}

.summary-content {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.875rem;
}

.summary-item.total {
    margin-top: 0.5rem;
    padding-top: 0.5rem;
    border-top: 1px solid #e5e7eb;
    font-weight: 600;
    color: #3d8d7a;
}

.summary-item.total strong {
    font-size: 1rem;
    color: #3d8d7a;
}

/* Success Alert */
.success-alert {
    background: linear-gradient(135deg, #c4dad2 0%, #b3c9c0 100%);
    border-radius: 12px;
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
    border-left: 3px solid #3d8d7a;
}

.success-alert i {
    font-size: 24px;
    color: #3d8d7a;
}

.alert-content strong {
    display: block;
    color: #1f2937;
    font-size: 0.875rem;
    margin-bottom: 0.25rem;
}

.alert-content span {
    font-size: 0.75rem;
    color: #374151;
}

/* Modal Actions */
.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;  
    padding: 1rem 0 0;
    border-top: 1px solid #e5e7eb;
    position: sticky;
    bottom: 15px;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.92) 0%, #ffffff 18px);
    z-index: 5;
    backdrop-filter: blur(6px);
}

.btn {
    padding: 0.625rem 1.25rem;
    border-radius: 12px;
    font-weight: 500;
    font-size: 0.875rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    font-family: inherit;
}

.btn-secondary {
    background: #f3f4f6;
    color: #4b5563;
}

.btn-secondary:hover {
    background: #e5e7eb;
    transform: translateX(-2px);
}

.btn-primary {
    background: #3d8d7a;
    color: white;
}

.btn-primary:hover {
    background: #2c6b5c;
    transform: translateX(2px);
}

.btn-success {
    background: linear-gradient(135deg, #3d8d7a 0%, #2c6b5c 100%);
    color: white;
    box-shadow: 0 2px 4px rgba(61, 141, 122, 0.2);
}

.btn-success:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(61, 141, 122, 0.3);
}

.btn-outline {
    background: transparent;
    border: 1.5px solid #e5e7eb;
    color: #6b7280;
}

.btn-outline:hover {
    background: #f9fafb;
    border-color: #c4dad2;
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
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

.slide-fade-enter-active {
    transition: all 0.3s ease;
}

.slide-fade-leave-active {
    transition: all 0.2s ease;
}

.slide-fade-enter-from,
.slide-fade-leave-to {
    transform: translateX(-20px);
    opacity: 0;
}

/* Step Content Transition */
.step-content {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateX(20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Scrollbar */
.modal-container::-webkit-scrollbar {
    width: 8px;
}

.modal-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.modal-container::-webkit-scrollbar-thumb {
    background: #c4dad2;
    border-radius: 10px;
}

.modal-container::-webkit-scrollbar-thumb:hover {
    background: #3d8d7a;
}

/* Responsive */
@media (max-width: 640px) {
    .modal-container {
        width: 95%;
        margin: 1rem;
    }
    
    .modal-header {
        padding: 1.25rem 1.5rem;
    }
    
    .modal-body {
        padding: 1.5rem;
    }
    
    .loan-type-grid {
        grid-template-columns: 1fr;
    }
    
    .progress-steps {
        padding: 0;
    }

    .term-slider-scale {
        font-size: 0.5rem;
    }
    
    .step-label {
        display: none;
    }
    
    .modal-actions {
        flex-direction: column-reverse;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>
