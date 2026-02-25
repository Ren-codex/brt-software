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
                            <label for="username" class="form-label">Username</label>
                            <div class="input-wrapper">
                                <i class="ri-user-line input-icon"></i>
                                <input
                                    type="text"
                                    id="username"
                                    v-model="form.username"
                                    class="form-control"
                                    :class="{ 'input-error': form.errors.username }"
                                    placeholder="Enter username"
                                    @input="handleInput('username')"
                                >
                            </div>
                            <span class="error-message" v-if="form.errors.username">{{ form.errors.username }}</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-wrapper">
                                <i class="ri-lock-line input-icon"></i>
                                <input
                                    :type="togglePassword ? 'text' : 'password'"
                                    id="password"
                                    v-model="form.password"
                                    class="form-control"
                                    :class="{ 'input-error': form.errors.password }"
                                    placeholder="Enter password"
                                    @input="handleInput('password')"
                                >
                                <button type="button" @click="togglePassword = !togglePassword" class="password-toggle">
                                    <i :class="togglePassword ? 'ri-eye-off-line' : 'ri-eye-line'"></i>
                                </button>
                            </div>
                            <span class="error-message" v-if="form.errors.password">{{ form.errors.password }}</span>
                        </div>

                        <div class="form-group form-group-half">
                            <label for="password_confirmation" class="form-label">Confirm Password</label>
                            <div class="input-wrapper">
                                <i class="ri-lock-line input-icon"></i>
                                <input
                                    :type="toggleConfirm ? 'text' : 'password'"
                                    id="password_confirmation"
                                    v-model="form.password_confirmation"
                                    class="form-control"
                                    :class="{ 'input-error': form.errors.password_confirmation }"
                                    placeholder="Confirm password"
                                    @input="handleInput('password_confirmation')"
                                >
                                <button type="button" @click="toggleConfirm = !toggleConfirm" class="password-toggle">
                                    <i :class="toggleConfirm ? 'ri-eye-off-line' : 'ri-eye-line'"></i>
                                </button>
                            </div>
                            <span class="error-message" v-if="form.errors.password_confirmation">{{ form.errors.password_confirmation }}</span>
                            <span class="error-message" v-if="passwordMismatch && !form.errors.password_confirmation">Passwords do not match</span>
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
                username: null,
                password: null,
                password_confirmation: null,
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
            showModal: false,
            editable: false,
            saveSuccess: false,
            previewImage: null,
            passwordMismatch: false,
        }
    },
    computed: {
        passwordsMatch() {
            if (!this.form.password || !this.form.password_confirmation) return true;
            return this.form.password === this.form.password_confirmation;
        }
    },
    watch: {
        'form.password'() {
            this.checkPasswordMatch();
        },
        'form.password_confirmation'() {
            this.checkPasswordMatch();
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
            this.form.username = data.user ? data.user.username : null;
            this.form.password = null; // Don't prefill password
            this.form.password_confirmation = null;
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
                            this.$emit('update', true);
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
            this.passwordMismatch = false;
            this.showModal = false;
        },
        checkPasswordMatch() {
            this.passwordMismatch = this.form.password && this.form.password_confirmation && this.form.password !== this.form.password_confirmation;
        }
    }
}
</script>

<style scoped>
/* Modal Styles */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1050;
    transition: opacity 0.3s ease;
}

.modal-overlay.active {
    opacity: 1;
}

.modal-container {
    background: white;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
    max-width: 95%;
    width: 100%;
    max-height: 90vh;
    overflow: hidden;
    position: relative;
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px 32px;
    border-bottom: 1px solid #e2e8f0;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 16px 16px 0 0;
}

.modal-header h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
}

.close-btn {
    background: none;
    border: none;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 8px;
    border-radius: 8px;
    transition: background-color 0.2s ease;
}

.close-btn:hover {
    background: rgba(255, 255, 255, 0.1);
}

.modal-body {
    padding: 32px;
    max-height: calc(90vh - 140px);
    overflow-y: auto;
}

/* Form Styles */
.form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.form-group {
    flex: 1;
    min-width: 200px;
}

.form-group-full {
    flex: 1 1 100%;
}

.form-label {
    display: block;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 8px;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.password-toggle {
    position: absolute;
    right: 12px;
    background: none;
    border: none;
    color: #a0aec0;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    transition: color 0.2s ease;
}

.password-toggle:hover {
    color: #667eea;
}

.input-icon {
    position: absolute;
    left: 12px;
    color: #a0aec0;
    font-size: 1rem;
    z-index: 1;
}

.form-control {
    width: 100%;
    padding: 12px 16px 12px 40px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.875rem;
    transition: all 0.2s ease;
    background: #f8f9fa;
}

.form-control:focus {
    outline: none;
    border-color: #667eea;
    background: white;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.input-error {
    border-color: #f56565 !important;
    background: #fed7d7 !important;
}

.error-message {
    color: #f56565;
    font-size: 0.75rem;
    margin-top: 4px;
    display: block;
}

/* Select Styles */
.form-control option {
    padding: 8px;
}

/* Profile Picture Styles */
.profile-picture-container {
    display: flex;
    align-items: center;
    gap: 24px;
    margin-bottom: 16px;
    padding: 24px;
    background: #f8f9fa;
    border-radius: 12px;
    border: 2px dashed #e2e8f0;
}

.profile-picture-preview {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    border: 4px solid #fff;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: white;
    position: relative;
}

.profile-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-placeholder {
    color: #a0aec0;
    font-size: 3rem;
}

.profile-upload-controls {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.profile-upload-controls button {
    font-size: 0.875rem;
    padding: 10px 16px;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.2s ease;
    cursor: pointer;
}

.btn-outline-primary {
    background: white;
    border: 2px solid #667eea;
    color: #667eea;
}

.btn-outline-primary:hover {
    background: #667eea;
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.btn-outline-danger {
    background: white;
    border: 2px solid #f56565;
    color: #f56565;
}

.btn-outline-danger:hover {
    background: #f56565;
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(245, 101, 101, 0.3);
}

/* Switch Styles */
.checkbox-wrapper {
    display: flex;
    align-items: center;
    gap: 12px;
}

.switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: 0.3s;
    border-radius: 24px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}

input:checked + .slider {
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
}

input:checked + .slider:before {
    transform: translateX(26px);
}

.checkbox-label {
    font-weight: 500;
    color: #4a5568;
}

/* Success Alert */
.success-alert {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
    color: white;
    border-radius: 8px;
    margin-bottom: 24px;
    animation: successSlideIn 0.3s ease-out;
}

@keyframes successSlideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.success-alert i {
    font-size: 1.25rem;
}

/* Form Actions */
.form-actions {
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

.btn {
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-cancel {
    background: #e2e8f0;
    color: #4a5568;
}

.btn-cancel:hover {
    background: #cbd5e0;
    transform: translateY(-1px);
}

.btn-save {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-save:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.btn-save:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive Design */
@media (max-width: 768px) {
    .modal-container {
        max-width: 98%;
        max-height: 95vh;
    }

    .modal-header {
        padding: 20px 24px;
    }

    .modal-header h2 {
        font-size: 1.25rem;
    }

    .modal-body {
        padding: 24px 20px;
    }

    .form-row {
        gap: 16px;
    }

    .form-group {
        min-width: 100%;
    }

    .profile-picture-container {
        flex-direction: column;
        text-align: center;
        gap: 16px;
    }

    .form-actions {
        padding: 20px 24px;
        flex-direction: column;
    }

    .btn {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .modal-header {
        padding: 16px 20px;
    }

    .modal-body {
        padding: 20px 16px;
    }

    .form-actions {
        padding: 16px 20px;
    }
}
</style>
