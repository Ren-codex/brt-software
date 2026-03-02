<template>
    <div v-if="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="hide">
        <div class="modal-container modal-lg" @click.stop>
            <div class="modal-header">
                <h2>{{ editable ? 'Update Expense' : 'Expense Information' }}</h2>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body">
                <form @submit.prevent="submit" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label for="expense_type" class="form-label">Expense Type <span class="text-danger">*</span></label>
                            <div class="input-wrapper">
                                <i class="ri-file-text-line input-icon"></i>
                                <select v-model="form.expense_type" class="form-control" :class="{ 'input-error': form.errors.expense_type }">
                                    <option value="">Select Expense Type</option>
                                    <option value="operational">Operational</option>
                                    <option value="utilities">Utilities</option>
                                    <option value="supplies">Supplies</option>
                                    <option value="transportation">Transportation</option>
                                    <option value="maintenance">Maintenance</option>
                                    <option value="others">Others</option>
                                </select>
                            </div>
                            <span class="error-message" v-if="form.errors.expense_type">{{ form.errors.expense_type }}</span>
                        </div>
                        <div class="form-group form-group-half">
                            <label for="amount" class="form-label">Amount <span class="text-danger">*</span></label>
                            <div class="input-wrapper">
                                <i class="ri-money-dollar-circle-line input-icon"></i>
                                <input type="number" v-model="form.amount" class="form-control" :class="{ 'input-error': form.errors.amount }" placeholder="Enter amount">
                            </div>
                            <span class="error-message" v-if="form.errors.amount">{{ form.errors.amount }}</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label for="expense_date" class="form-label">Expense Date <span class="text-danger">*</span></label>
                            <div class="input-wrapper">
                                <i class="ri-calendar-line input-icon"></i>
                                <input type="date" v-model="form.expense_date" class="form-control" :class="{ 'input-error': form.errors.expense_date }">
                            </div>
                            <span class="error-message" v-if="form.errors.expense_date">{{ form.errors.expense_date }}</span>
                        </div>
                      
                    </div>

                    <div class="form-group">
                        <label for="description" class="form-label">Description</label>
                        <div class="input-wrapper">
                            <i class="ri-edit-line input-icon"></i>
                            <textarea v-model="form.description" class="form-control textarea-control" placeholder="Enter expense description"></textarea>
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

export default {
    props: ['dropdowns'],
    data() {
        return {
            currentUrl: window.location.origin,
            form: useForm({
                id: null,
                expense_type: '',
                amount: '',
                expense_date: '',
                description: '',
                status: 'pending'
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
            this.form.expense_date = new Date().toISOString().split('T')[0]; // Set today's date
            this.saveSuccess = false;
            this.editable = false;
            this.showModal = true;
        },
        edit(data, index) {
            this.selectedRow = index;
            this.form.clearErrors();
            this.form.id = data.id;
            this.form.expense_type = data.expense_type;
            this.form.amount = data.amount;
            this.form.expense_date = data.expense_date;
            this.form.description = data.description;
            this.form.status = data.status;
            this.editable = true;
            this.saveSuccess = false;
            this.showModal = true;
        },
        submit() {
            if (this.editable) {
                this.form.put(`/expenses/${this.form.id}`, {
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
                this.form.post('/expenses', {
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
