<template>
    <div 
        v-if="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"
    >
        <div class="modal-container modal-xl" @click.stop>
            <div class="modal-header">
                <h2>{{ editable ? 'Update Employee' : 'Employee Information' }}</h2>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body">
                <form @submit.prevent="submit" enctype="multipart/form-data">
                    <!-- Profile Picture Section -->
                    <div class="form-row">
                        <div class="form-group form-group-full">
                            <label class="form-label">Profile Picture</label>
                            <div class="profile-picture-container">
                                <div class="profile-picture-preview">
                                    <img v-if="previewImage" :src="previewImage" alt="Profile Preview" class="profile-image">
                                    <div v-else class="profile-placeholder">
                                        <i class="ri-user-line"></i>
                                    </div>
                                </div>
                                <div class="profile-upload-controls">
                                    <input
                                        type="file"
                                        ref="avatarInput"
                                        @change="handleAvatarChange"
                                        accept="image/*"
                                        style="display: none"
                                    >
                                    <button type="button" @click="$refs.avatarInput.click()" class="btn btn-outline-primary btn-sm">
                                        <i class="ri-camera-line"></i> Choose Image
                                    </button>
                                    <button type="button" v-if="form.avatar" @click="removeAvatar" class="btn btn-outline-danger btn-sm ms-2">
                                        <i class="ri-delete-bin-line"></i> Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label for="firstname" class="form-label">First Name</label>
                            <div class="input-wrapper">
                                <i class="ri-user-line input-icon"></i>
                                <input
                                    type="text"
                                    id="firstname"
                                    v-model="form.firstname"
                                    class="form-control"
                                    :class="{ 'input-error': form.errors.firstname }"
                                    placeholder="Enter firstname"
                                    @input="handleInput('firstname')"
                                >
                            </div>
                            <span class="error-message" v-if="form.errors.firstname">{{ form.errors.firstname }}</span>
                        </div>

                        <div class="form-group form-group-half">
                            <label for="middlename" class="form-label">Middle Name</label>
                            <div class="input-wrapper">
                                <i class="ri-user-line input-icon"></i>
                                <input
                                    type="text"
                                    id="middlename"
                                    v-model="form.middlename"
                                    class="form-control"
                                    :class="{ 'input-error': form.errors.middlename }"
                                    placeholder="Enter middlename"
                                    @input="handleInput('middlename')"
                                >
                            </div>
                            <span class="error-message" v-if="form.errors.middlename">{{ form.errors.middlename }}</span>
                        </div>


                        <div class="form-group form-group-half">
                            <label for="lastname" class="form-label">Last Name</label>
                            <div class="input-wrapper">
                                <i class="ri-user-line input-icon"></i>
                                <input
                                    type="text"
                                    id="lastname"
                                    v-model="form.lastname"
                                    class="form-control"
                                    :class="{ 'input-error': form.errors.lastname }"
                                    placeholder="Enter lastname"
                                    @input="handleInput('lastname')"
                                >
                            </div>
                            <span class="error-message" v-if="form.errors.lastname">{{ form.errors.lastname }}</span>
                        </div>

                         <div class="form-group form-group-half">
                            <label for="suffix" class="form-label">Suffix</label>
                            <div class="input-wrapper">
                                <i class="ri-user-line input-icon"></i>
                                <input
                                    type="text"
                                    id="suffix"
                                    v-model="form.suffix"
                                    class="form-control"
                                    :class="{ 'input-error': form.errors.suffix }"
                                    placeholder="Enter suffix"
                                    @input="handleInput('suffix')"
                                >
                            </div>
                            <span class="error-message" v-if="form.errors.suffix">{{ form.errors.suffix }}</span>
                        </div>

                    
                    </div>

                    <div class="form-row">
                         <div class="form-group form-group-half">
                            <label for="email" class="form-label">Email</label>
                            <div class="input-wrapper">
                                <i class="ri-user-line input-icon"></i>
                                <input
                                    type="email"
                                    id="email"
                                    v-model="form.email"
                                    class="form-control"
                                    :class="{ 'input-error': form.errors.email }"
                                    placeholder="Enter Email"
                                    @input="handleInput('email')"
                                >
                            </div>
                            <span class="error-message" v-if="form.errors.email">{{ form.errors.email }}</span>
                        </div>

                        <div class="form-group form-group-half">
                            <label for="mobile" class="form-label">Contact Number</label>
                            <div class="input-wrapper">
                                <i class="ri-phone-line input-icon"></i>
                                <input
                                    type="text"
                                    id="mobile"
                                    v-model="form.mobile"
                                    class="form-control"
                                    :class="{ 'input-error': form.errors.mobile }"
                                    placeholder="09XXXXXXXXX"
                                    maxlength="11"
                                    @input="handleInput('mobile')"
                                >
                            </div>
                            <span class="error-message" v-if="form.errors.mobile">{{ form.errors.mobile }}</span>
                        </div>


                         <div class="form-group form-group-half">
                            <label for="birthdate" class="form-label">Birthdate</label>
                            <div class="input-wrapper">
                                <i class="ri-user-line input-icon"></i>
                                <input
                                    type="date"
                                    id="birthdate"
                                    v-model="form.birthdate"
                                    class="form-control"
                                    :class="{ 'input-error': form.errors.birthdate }"
                                    @input="handleInput('birthdate')"
                                >
                            </div>
                            <span class="error-message" v-if="form.errors.birthdate">{{ form.errors.birthdate }}</span>
                        </div>

                         <div class="form-group form-group-half">
                            <label for="sex" class="form-label">Sex</label>
                            <div class="input-wrapper">
                                <i class="ri-user-line input-icon"></i>
                                <b-form-select
                                    class="form-control"
                                    v-model="form.sex"
                                    :options="sexOptions"
                                    :class="{ 'input-error': form.errors.sex }"
                                    text-field="text"
                                    value-field="value"
                                >
                                 <template #first>
                                    <b-form-select-option :value="null" disabled>Select Sex</b-form-select-option>
                                </template>
                                </b-form-select>
                            </div>
                            <span class="error-message" v-if="form.errors.sex">{{ form.errors.sex }}</span>
                        </div>

                     </div>

                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label for="religion" class="form-label">Religion</label>
                            <div class="input-wrapper">
                                <i class="ri-user-line input-icon"></i>
                                <input
                                    type="text"
                                    id="religion"
                                    v-model="form.religion"
                                    class="form-control"
                                    :class="{ 'input-error': form.errors.religion }"
                                    placeholder="Enter religion"
                                    @input="handleInput('religion')"
                                >
                            </div>
                            <span class="error-message" v-if="form.errors.religion">{{ form.errors.religion }}</span>
                        </div>

                         <div class="form-group form-group-half">
                            <label for="address" class="form-label">Address</label>
                            <div class="input-wrapper">
                                <i class="ri-user-line input-icon"></i>
                                <input
                                    type="text"
                                    id="address"
                                    v-model="form.address"
                                    class="form-control"
                                    :class="{ 'input-error': form.errors.address }"
                                    placeholder="Enter address"
                                    @input="handleInput('address')"
                                >
                            </div>
                            <span class="error-message" v-if="form.errors.address">{{ form.errors.address }}</span>
                        </div>

                          <div class="form-group form-group-half">
                            <label for="position_id" class="form-label">Position</label>
                            <div class="input-wrapper">
                                <i class="ri-bar-chart-2-line input-icon"></i>
                                <b-form-select
                                class="form-control"
                                v-model="form.position_id"
                                :options="dropdowns.positions"
                                :class="{ 'input-error': form.errors.position_id }"
                                text-field="title"
                                value-field="value"
                                >
                                 <template #first>
                                    <b-form-select-option :value="null" disabled  >Select Position</b-form-select-option>
                                </template>
                                </b-form-select>
                            </div>
                            <span class="error-message" v-if="form.errors.position_id">{{ form.errors.position_id }}</span>
                        </div>
                    </div>


                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label class="form-label">Is Regular?</label>
                            <div class="checkbox-wrapper">
                                <label class="switch">
                                    <input type="checkbox" v-model="form.is_regular" :true-value="1" :false-value="0">
                                    <span class="slider"></span>
                                </label>
                                <span class="checkbox-label">{{ form.is_regular ? 'Yes' : 'No' }}</span>
                            </div>
                        </div>

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

export default {
    components: { InputLabel, TextInput, Multiselect },
    props: ['dropdowns'],
    data() {
        return {
            currentUrl: window.location.origin,
            form: useForm({
                id: null,
                firstname: null,
                middlename: null,
                lastname: null,
                suffix: null,
                email: null,
                mobile: null,
                birthdate: null,
                sex: null,
                religion: null,
                address: null,
                position_id: null,
                avatar: null,
                is_regular: 0,
                is_active: 1,
                is_blacklisted: 0,
                option: 'lists'
            }),
            sexOptions: [
                { value: 'Male', text: 'Male' },
                { value: 'Female', text: 'Female' }
            ],
            togglePassword: false,
            toggleConfirm: false,
            passwordMismatch: false,
            showModal: false,
            editable: false,
            saveSuccess: false,
            previewImage: null,
        }
    },
    methods: { 
        show() {
            this.form.reset();
            this.form.is_active = 1;
            this.form.is_regular = 0;
            this.form.is_blacklisted = 0;
            this.previewImage = null;
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = true;
        },
        edit(data, index) {
            this.form.id = data.id;
            this.form.firstname = data.firstname;
            this.form.middlename = data.middlename;
            this.form.lastname = data.lastname;
            this.form.suffix = data.suffix;
            this.form.email = data.email;
            this.form.mobile = data.mobile;
            this.form.birthdate = data.birthdate;
            this.form.sex = data.sex;
            this.form.religion = data.religion;
            this.form.address = data.address;
            this.form.position_id = data.position_id;
            this.form.avatar = null; // Don't set to filename, only set when new file selected
            this.form.is_regular = data.is_regular;
            this.form.is_active = data.is_active;
            this.form.is_blacklisted = data.is_blacklisted;
            this.previewImage = data.avatar ? `${this.currentUrl}/storage/${data.avatar}` : null;
            this.editable = true;
            this.saveSuccess = false;
            this.showModal = true;
        },
        submit() {
            if (this.editable) {
                this.form.put(`/employees/${this.form.id}`, {
                    preserveScroll: true,
                    onSuccess: (response) => {
                        this.saveSuccess = true;
                          setTimeout(() => {
                            this.$emit('add', true);
                            this.form.reset();
                            this.hide();
                            }, 1500);
                       
                    },
                });
            } else {
                this.form.post('/employees', {
                    preserveScroll: true,
                    onSuccess: (response) => {
                        this.saveSuccess = true;
                        setTimeout(() => {
                            this.$emit('add', true);
                            this.form.reset();
                            this.hide();
                         }, 1500);
                    },
                });
            }
        },
        handleInput(field) {
            this.form.errors[field] = false;
        },
        handleAvatarChange(event) {
            const file = event.target.files[0];
            if (file) {
                this.form.avatar = file;
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.previewImage = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        },
        removeAvatar() {
            this.form.avatar = null;
            this.previewImage = null;
            if (this.$refs.avatarInput) {
                this.$refs.avatarInput.value = '';
            }
        },
        hide() {
            this.form.reset();
            this.form.clearErrors();
            this.previewImage = null;
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = false;
        }
    }
}
</script>

<style scoped>
.modal-container {
    max-width: 95%;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
}

.modal-body {
    padding-bottom: 80px; /* Space for fixed buttons */
}

.form-actions {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: white;
    padding: 1rem;
    border-top: 1px solid #ddd;
    z-index: 10;
}

/* Profile Picture Styles */
.profile-picture-container {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.profile-picture-preview {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    border: 2px dashed #ddd;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background-color: #f8f9fa;
}

.profile-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-placeholder {
    color: #6c757d;
    font-size: 2rem;
}

.profile-upload-controls {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.profile-upload-controls button {
    font-size: 0.875rem;
    padding: 0.375rem 0.75rem;
}
</style>
