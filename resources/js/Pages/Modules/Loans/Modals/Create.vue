<template>
    <div v-if="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="hide">
        <div class="modal-container modal-lg" @click.stop>
            <div class="modal-header">
                <h2>{{ editable ? 'Update Loan' : 'Loan Information' }}</h2>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body">
                <form @submit.prevent="submit" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label for="employee_id" class="form-label">Employee <span class="text-danger">*</span></label>
                            <div class="input-wrapper">
                                <i class="ri-user-line input-icon"></i>
                                <Multiselect
                                    v-model="form.employee_id"
                                    :options="dropdowns.employees || []"
                                    :searchable="true"
                                    :close-on-select="true"
                                    :allow-empty="false"
                                    placeholder="Select Employee"
                                    label="name"
                                    value-prop="value"
                                    track-by="name"
                                    :class="{ 'input-error': form.errors.employee_id }"
                                />
                            </div>
                            <span class="error-message" v-if="form.errors.employee_id">{{ form.errors.employee_id }}</span>
                        </div>
                        <div class="form-group form-group-half">
                            <label for="loan_type" class="form-label">Loan Type <span class="text-danger">*</span></label>
                            <div class="input-wrapper">
                                <i class="ri-file-text-line input-icon"></i>
                                <select v-model="form.loan_type" class="form-control" :class="{ 'input-error': form.errors.loan_type }">
                                    <option value="">Select Loan Type</option>
                                    <option value="personal">Personal Loan</option>
                                    <option value="salary">Salary Loan</option>
                                    <option value="emergency">Emergency Loan</option>
                                    <option value="housing">Housing Loan</option>
                                </select>
                            </div>
                            <span class="error-message" v-if="form.errors.loan_type">{{ form.errors.loan_type }}</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label for="amount" class="form-label">Loan Amount <span class="text-danger">*</span></label>
                            <div class="input-wrapper">
                                <i class="ri-money-dollar-circle-line input-icon"></i>
                                <input type="number" v-model="form.amount" class="form-control" :class="{ 'input-error': form.errors.amount }" placeholder="Enter amount">
                            </div>
                            <span class="error-message" v-if="form.errors.amount">{{ form.errors.amount }}</span>
                        </div>
                        <div class="form-group form-group-half">
                            <label for="interest_rate" class="form-label">Interest Rate (%) <span class="text-danger">*</span></label>
                            <div class="input-wrapper">
                                <i class="ri-percent-line input-icon"></i>
                                <input type="number" step="0.01" v-model="form.interest_rate" class="form-control" :class="{ 'input-error': form.errors.interest_rate }" placeholder="Enter interest rate">
                            </div>
                            <span class="error-message" v-if="form.errors.interest_rate">{{ form.errors.interest_rate }}</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label for="term_months" class="form-label">Term (Months) <span class="text-danger">*</span></label>
                            <div class="input-wrapper">
                                <i class="ri-calendar-line input-icon"></i>
                                <input type="number" v-model="form.term_months" class="form-control" :class="{ 'input-error': form.errors.term_months }" placeholder="Enter term in months">
                            </div>
                            <span class="error-message" v-if="form.errors.term_months">{{ form.errors.term_months }}</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="purpose" class="form-label">Purpose</label>
                        <div class="input-wrapper">
                            <i class="ri-edit-line input-icon"></i>
                            <textarea v-model="form.purpose" class="form-control textarea-control" placeholder="Enter loan purpose"></textarea>
                        </div>
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

export default {
    components: { Multiselect },
    props: ['dropdowns'],
    data() {
        return {
            currentUrl: window.location.origin,
            form: useForm({
                id: null,
                employee_id: null,
                loan_type: '',
                amount: '',
                interest_rate: '',
                term_months: '',
                status: 'pending',
                purpose: ''
            }),
            showModal: false,
            editable: false,
            saveSuccess: false,
        }
    },
    methods: {
        show() {
            this.form.reset();
            this.form.status = 'pending';
            this.saveSuccess = false;
            this.editable = false;
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
            this.showModal = true;
        },
        submit() {
            if (this.editable) {
                this.form.put(`/loans/${this.form.id}`, {
                    preserveScroll: true,
                    onSuccess: (response) => {
                        this.saveSuccess = true;
                        this.form.reset();
                        setTimeout(() => {
                            this.$emit('update', true);
                            this.hide();
                        }, 1500);
                    },
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
        },
        hide() {
            this.form.reset();
            this.form.clearErrors();
            this.saveSuccess = false;
            this.editable = false;
            this.showModal = false;
        }
    }
}
</script>
