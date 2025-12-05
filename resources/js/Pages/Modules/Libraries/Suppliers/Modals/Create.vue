<template>
    <div 
        v-if="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"
    >
        <div class="modal-container modal-lg" @click.stop>
            <div class="modal-header">
                <h2>{{ editable ? 'Update Supplier' : 'Supplier Information' }}</h2>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body">
                <form @submit.prevent="submit">
                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label for="name" class="form-label">Supplier Name</label>
                            <div class="input-wrapper">
                                <i class="ri-store-2-line input-icon"></i>
                                <input 
                                    type="text" 
                                    id="name" 
                                    v-model="form.name" 
                                    class="form-control"
                                    :class="{ 'input-error': form.errors.name }"
                                    placeholder="Enter supplier name"
                                    @input="handleInput('name')"
                                >
                            </div>
                            <span class="error-message" v-if="form.errors.name">{{ form.errors.name }}</span>
                        </div>

                        <div class="form-group form-group-half">
                            <label for="address" class="form-label">Address</label>
                            <div class="input-wrapper">
                                <i class="ri-map-pin-line input-icon"></i>
                                <input 
                                    type="text" 
                                    id="address" 
                                    v-model="form.address" 
                                    class="form-control"
                                    :class="{ 'input-error': form.errors.address }"
                                    placeholder="Enter address e.g. Sinunuc, Zamboanga City"
                                    @input="handleInput('address')"
                                >
                            </div>
                            <span class="error-message" v-if="form.errors.address">{{ form.errors.address }}</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label for="contact_person" class="form-label">Contact Person</label>
                            <div class="input-wrapper">
                                <i class="ri-user-line input-icon"></i>
                                <input 
                                    type="text" 
                                    id="contact_person" 
                                    v-model="form.contact_person" 
                                    class="form-control"
                                    :class="{ 'input-error': form.errors.contact_person }"
                                    placeholder="Enter contact person e.g. Mr. Mark Reyes"
                                    @input="handleInput('contact_person')"
                                >
                            </div>
                            <span class="error-message" v-if="form.errors.contact_person">{{ form.errors.contact_person }}</span>
                        </div>

                        <div class="form-group form-group-half">
                            <label for="contact_number" class="form-label">Contact Number</label>
                            <div class="input-wrapper">
                                <i class="ri-phone-line input-icon"></i>
                                <input 
                                    type="text" 
                                    id="contact_number" 
                                    v-model="form.contact_number" 
                                    class="form-control"
                                    :class="{ 'input-error': form.errors.contact_number }"
                                    placeholder="Enter mobile e.g 09xxxxxxxxx"
                                    @input="handleInput('contact_number')"
                                >
                            </div>
                            <span class="error-message" v-if="form.errors.contact_number">{{ form.errors.contact_number }}</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label for="email" class="form-label">Email</label>
                            <div class="input-wrapper">
                                <i class="ri-mail-line input-icon"></i>
                                <input 
                                    type="email" 
                                    id="email" 
                                    v-model="form.email" 
                                    class="form-control"
                                    :class="{ 'input-error': form.errors.email }"
                                    placeholder="Enter email"
                                    @input="handleInput('email')"
                                >
                            </div>
                            <span class="error-message" v-if="form.errors.email">{{ form.errors.email }}</span>
                        </div>

                        <div class="form-group form-group-half">
                            <label for="tin" class="form-label">TIN</label>
                            <div class="input-wrapper">
                                <i class="ri-file-list-3-line input-icon"></i>
                                <input 
                                    type="text" 
                                    id="tin" 
                                    v-model="form.tin" 
                                    class="form-control"
                                    :class="{ 'input-error': form.errors.tin }"
                                    placeholder="Enter TIN"
                                    @input="handleInput('tin')"
                                >
                            </div>
                            <span class="error-message" v-if="form.errors.tin">{{ form.errors.tin }}</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label class="form-label">Is Active?</label>
                            <div class="checkbox-wrapper">
                                <label class="switch">
                                    <input type="checkbox" v-model="form.is_active" :true-value="1" :false-value="0">
                                    <span class="slider"></span>
                                </label>
                                <span class="checkbox-label">{{ form.is_active ? 'Active' : 'Inactive' }}</span>
                            </div>
                        </div>

                        <div class="form-group form-group-half">
                            <label class="form-label">Is Blacklisted?</label>
                            <div class="checkbox-wrapper">
                                <label class="switch">
                                    <input type="checkbox" v-model="form.is_blacklisted" :true-value="1" :false-value="0">
                                    <span class="slider"></span>
                                </label>
                                <span class="checkbox-label">{{ form.is_blacklisted ? 'Yes' : 'No' }}</span>
                            </div>
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
import InputLabel from '@/Shared/Components/Forms/InputLabel.vue';
import TextInput from '@/Shared/Components/Forms/TextInput.vue';
import Swal from 'sweetalert2';

export default {
    components: { InputLabel, TextInput, Multiselect },
    props: [],
    data() {
        return {
            currentUrl: window.location.origin,
            form: useForm({
                id: null,
                name: null,
                address: null,
                contact_person: null,
                contact_number: null,
                birthdate: null,
                email: null,
                tin: null,
                is_active: 1,
                is_blacklisted: 0,  
                option: 'lists'
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
            this.form.defaults({
                id: null,
                name: null,
                address: null,
                contact_person: null,
                contact_number: null,
                birthdate: null,
                email: null,
                tin: null,
                option: '',
                }).reset();
            this.form.is_active = 1;
            this.form.is_blacklisted = 0;
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = true;
        },
        edit(data, index) {
            this.form.id = data.id;
            this.form.name = data.name;
            this.form.address = data.address;
            this.form.contact_person = data.contact_person;
            this.form.contact_number = data.contact_number;
            this.form.email = data.email;
            this.form.tin = data.tin;
            this.form.is_active = data.is_active;
            this.form.is_blacklisted = data.is_blacklisted;
            this.editable = true;
            this.saveSuccess = false;
            this.showModal = true;
        },
        
        submit() {
            if (this.editable) {
                this.form.put(`/libraries/suppliers/${this.form.id}`, {
                    preserveScroll: true,
                    onSuccess: (response) => {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: 'Supplier updated successfully!',
                            timer: 1500,
                            showConfirmButton: false
                        });
                        this.$emit('add', true);
                        this.hide();
                    },
                });
            } else {
                this.form.post('/libraries/suppliers', {
                    preserveScroll: true,
                    onSuccess: (response) => {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: 'Supplier created successfully!',
                            timer: 1500,
                            showConfirmButton: false
                        });
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
