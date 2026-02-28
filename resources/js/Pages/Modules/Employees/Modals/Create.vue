<template>
    <div 
        v-if="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"
    >
        <div class="modal-container" @click.stop>
            <!-- Simple Header -->
            <div class="modal-header">
                <div class="header-title">
                    <i :class="editable ? 'ri-edit-box-line' : 'ri-user-add-line'"></i>
                    <h2>{{ editable ? 'Update Employee' : 'Add New Employee' }}</h2>
                </div>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>

            <!-- Simple Form -->
            <div class="modal-body">
                <form @submit.prevent="submit">
                    <!-- Profile Picture - Simple -->
                    <div class="profile-section">
                        <div class="profile-picture">
                            <img v-if="previewImage" :src="previewImage" alt="Profile">
                            <div v-else class="profile-placeholder">
                                <i class="ri-user-line"></i>
                            </div>
                        </div>
                        <div class="profile-actions">
                            <input
                                type="file"
                                ref="avatarInput"
                                @change="handleAvatarChange"
                                accept="image/*"
                                hidden
                            >
                            <button type="button" @click="$refs.avatarInput.click()" class="btn-outline">
                                <i class="ri-camera-line"></i>
                                {{ form.avatar ? 'Change' : 'Upload Photo' }}
                            </button>
                            <button type="button" v-if="form.avatar" @click="removeAvatar" class="btn-outline btn-outline-danger">
                                <i class="ri-delete-bin-line"></i>
                                Remove
                            </button>
                        </div>
                    </div>

                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="ri-user-info-line"></i>
                            Personal Information
                        </h3>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>First Name <span class="required">*</span></label>
                                <input
                                    type="text"
                                    v-model="form.firstname"
                                    :class="{ 'error': form.errors.firstname }"
                                    placeholder="Enter first name"
                                    @input="handleInput('firstname')"
                                >
                                <span class="error-text" v-if="form.errors.firstname">{{ form.errors.firstname }}</span>
                            </div>

                            <div class="form-group">
                                <label>Middle Name</label>
                                <input
                                    type="text"
                                    v-model="form.middlename"
                                    :class="{ 'error': form.errors.middlename }"
                                    placeholder="Enter middle name"
                                    @input="handleInput('middlename')"
                                >
                                <span class="error-text" v-if="form.errors.middlename">{{ form.errors.middlename }}</span>
                            </div>

                            <div class="form-group">
                                <label>Last Name <span class="required">*</span></label>
                                <input
                                    type="text"
                                    v-model="form.lastname"
                                    :class="{ 'error': form.errors.lastname }"
                                    placeholder="Enter last name"
                                    @input="handleInput('lastname')"
                                >
                                <span class="error-text" v-if="form.errors.lastname">{{ form.errors.lastname }}</span>
                            </div>

                            <div class="form-group">
                                <label>Suffix</label>
                                <input
                                    type="text"
                                    v-model="form.suffix"
                                    :class="{ 'error': form.errors.suffix }"
                                    placeholder="e.g., Jr., Sr., III"
                                    @input="handleInput('suffix')"
                                >
                                <span class="error-text" v-if="form.errors.suffix">{{ form.errors.suffix }}</span>
                            </div>

                            <div class="form-group">
                                <label>Birthdate <span class="required">*</span></label>
                                <input
                                    type="date"
                                    v-model="form.birthdate"
                                    :class="{ 'error': form.errors.birthdate }"
                                    @input="handleInput('birthdate')"
                                >
                                <span class="error-text" v-if="form.errors.birthdate">{{ form.errors.birthdate }}</span>
                            </div>

                            <div class="form-group">
                                <label>Sex <span class="required">*</span></label>
                                <select
                                    v-model="form.sex"
                                    :class="{ 'error': form.errors.sex }"
                                >
                                    <option value="" disabled selected>Select sex</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                                <span class="error-text" v-if="form.errors.sex">{{ form.errors.sex }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="ri-contacts-line"></i>
                            Contact Information
                        </h3>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Contact Number <span class="required">*</span></label>
                                <input
                                    type="tel"
                                    v-model="form.mobile"
                                    :class="{ 'error': form.errors.mobile }"
                                    placeholder="09XXXXXXXXX"
                                    maxlength="11"
                                    @input="handleInput('mobile')"
                                >
                                <span class="error-text" v-if="form.errors.mobile">{{ form.errors.mobile }}</span>
                            </div>

                            <div class="form-group">
                                <label>Email Address</label>
                                <input
                                    type="email"
                                    v-model="form.email"
                                    :class="{ 'error': form.errors.email }"
                                    placeholder="Enter email address"
                                    @input="handleInput('email')"
                                >
                                <span class="error-text" v-if="form.errors.email">{{ form.errors.email }}</span>
                            </div>

                            <div class="form-group full-width">
                                <label>Complete Address</label>
                                <input
                                    type="text"
                                    v-model="form.address"
                                    :class="{ 'error': form.errors.address }"
                                    placeholder="Enter complete address"
                                    @input="handleInput('address')"
                                >
                                <span class="error-text" v-if="form.errors.address">{{ form.errors.address }}</span>
                            </div>

                            <div class="form-group">
                                <label>Religion</label>
                                <input
                                    type="text"
                                    v-model="form.religion"
                                    :class="{ 'error': form.errors.religion }"
                                    placeholder="Enter religion"
                                    @input="handleInput('religion')"
                                >
                                <span class="error-text" v-if="form.errors.religion">{{ form.errors.religion }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Employment Details Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="ri-briefcase-line"></i>
                            Employment Details
                        </h3>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Position <span class="required">*</span></label>
                                <select
                                    v-model="form.position_id"
                                    :class="{ 'error': form.errors.position_id }"
                                >
                                    <option :value="null" disabled selected>Select position</option>
                                    <option 
                                        v-for="position in dropdowns.positions" 
                                        :key="position.value" 
                                        :value="position.value"
                                    >
                                        {{ position.title }}
                                    </option>
                                </select>
                                <span class="error-text" v-if="form.errors.position_id">{{ form.errors.position_id }}</span>
                            </div>

                            <div class="form-group">
                                <label>Status</label>
                                <div class="toggle-wrapper">
                                    <label class="toggle-switch">
                                        <input type="checkbox" v-model="form.is_regular" :true-value="1" :false-value="0">
                                        <span class="toggle-slider"></span>
                                    </label>
                                    <span class="toggle-label">{{ form.is_regular ? 'Regular' : 'Probationary' }}</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Account Status</label>
                                <div class="toggle-wrapper">
                                    <label class="toggle-switch">
                                        <input type="checkbox" v-model="form.is_active" :true-value="1" :false-value="0">
                                        <span class="toggle-slider"></span>
                                    </label>
                                    <span class="toggle-label" :class="{ 'text-success': form.is_active, 'text-danger': !form.is_active }">
                                        {{ form.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Account Credentials Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="ri-lock-line"></i>
                            Account Credentials
                        </h3>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Username <span class="required">*</span></label>
                                <input
                                    type="text"
                                    v-model="form.username"
                                    :class="{ 'error': form.errors.username }"
                                    placeholder="Choose a username"
                                    @input="handleInput('username')"
                                >
                                <span class="error-text" v-if="form.errors.username">{{ form.errors.username }}</span>
                            </div>

                            <div class="form-group">
                                <label>Password <span class="required">*</span></label>
                                <div class="password-input">
                                    <input
                                        :type="togglePassword ? 'text' : 'password'"
                                        v-model="form.password"
                                        :class="{ 'error': form.errors.password }"
                                        placeholder="Enter password"
                                        @input="handleInput('password')"
                                    >
                                    <button type="button" @click="togglePassword = !togglePassword" class="password-toggle">
                                        <i :class="togglePassword ? 'ri-eye-off-line' : 'ri-eye-line'"></i>
                                    </button>
                                </div>
                                <span class="error-text" v-if="form.errors.password">{{ form.errors.password }}</span>
                            </div>

                            <div class="form-group">
                                <label>Confirm Password <span class="required">*</span></label>
                                <div class="password-input">
                                    <input
                                        :type="toggleConfirm ? 'text' : 'password'"
                                        v-model="form.password_confirmation"
                                        :class="{ 'error': form.errors.password_confirmation || passwordMismatch }"
                                        placeholder="Confirm password"
                                        @input="handleInput('password_confirmation')"
                                    >
                                    <button type="button" @click="toggleConfirm = !toggleConfirm" class="password-toggle">
                                        <i :class="toggleConfirm ? 'ri-eye-off-line' : 'ri-eye-line'"></i>
                                    </button>
                                </div>
                                <span class="error-text" v-if="form.errors.password_confirmation">{{ form.errors.password_confirmation }}</span>
                                <span class="error-text" v-if="passwordMismatch && !form.errors.password_confirmation">Passwords do not match</span>
                            </div>
                        </div>
                    </div>

                    <!-- Simple Success Message -->
                    <transition name="fade">
                        <div class="success-message" v-if="saveSuccess">
                            <i class="ri-checkbox-circle-line"></i>
                            <span>Employee saved successfully!</span>
                        </div>
                    </transition>

                    <!-- Simple Form Actions -->
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" @click="hide">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary" :disabled="form.processing">
                            <i class="ri-save-line" v-if="!form.processing"></i>
                            <i class="ri-loader-4-line spinner" v-else></i>
                            {{ form.processing ? 'Saving...' : (editable ? 'Update Employee' : 'Save Employee') }}
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
            togglePassword: false,
            toggleConfirm: false,
            showModal: false,
            editable: false,
            saveSuccess: false,
            previewImage: null,
            passwordMismatch: false,
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
            this.previewImage = null;
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = true;
        },
        edit(data) {
            this.form.id = data.id;
            this.form.firstname = data.firstname;
            this.form.middlename = data.middlename;
            this.form.lastname = data.lastname;
            this.form.suffix = data.suffix;
            this.form.email = data.email;
            this.form.username = data.user ? data.user.username : null;
            this.form.mobile = data.mobile;
            this.form.birthdate = data.birthdate;
            this.form.sex = data.sex;
            this.form.religion = data.religion;
            this.form.address = data.address;
            this.form.position_id = data.position_id;
            this.form.is_regular = data.is_regular;
            this.form.is_active = data.is_active;
            this.previewImage = data.avatar ? `${this.currentUrl}/storage/${data.avatar}` : null;
            this.editable = true;
            this.saveSuccess = false;
            this.showModal = true;
        },
        submit() {
            if (this.editable) {
                this.form.put(`/employees/${this.form.id}`, {
                    preserveScroll: true,
                    onSuccess: () => {
                        this.saveSuccess = true;
                        setTimeout(() => {
                            this.$emit('update', true);
                            this.hide();
                        }, 1500);
                    },
                });
            } else {
                this.form.post('/employees', {
                    preserveScroll: true,
                    onSuccess: () => {
                        this.saveSuccess = true;
                        setTimeout(() => {
                            this.$emit('add', true);
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
            this.passwordMismatch = this.form.password && this.form.password_confirmation && 
                                   this.form.password !== this.form.password_confirmation;
        }
    }
}
</script>

<style scoped>
/* Simple Modal Styles */
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
    opacity: 0;
    transition: opacity 0.2s ease;
}

.modal-overlay.active {
    opacity: 1;
}

.modal-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    width: 90%;
    max-width: 800px;
    max-height: 90vh;
    overflow: hidden;
    transform: translateY(20px);
    opacity: 0;
    transition: all 0.2s ease;
}

.modal-overlay.active .modal-container {
    transform: translateY(0);
    opacity: 1;
}

/* Simple Header */
.modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: white;
}

.header-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.header-title i {
    font-size: 24px;
    color: #0ab39c;
}

.header-title h2 {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1f2937;
    margin: 0;
}

.close-btn {
    background: none;
    border: none;
    color: #9ca3af;
    font-size: 20px;
    cursor: pointer;
    padding: 4px;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.close-btn:hover {
    background: #f3f4f6;
    color: #4b5563;
}

/* Modal Body */
.modal-body {
    padding: 24px;
    max-height: calc(90vh - 80px);
    overflow-y: auto;
}

/* Simple Profile Section */
.profile-section {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 16px;
    background: #f9fafb;
    border-radius: 8px;
    margin-bottom: 24px;
}

.profile-picture {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    overflow: hidden;
    border: 2px solid white;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.profile-picture img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #0ab39c 0%, #3577f1 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 32px;
}

.profile-actions {
    display: flex;
    gap: 8px;
}

/* Form Sections */
.form-section {
    margin-bottom: 32px;
}

.form-section:last-child {
    margin-bottom: 0;
}

.section-title {
    font-size: 1rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-title i {
    color: #0ab39c;
}

/* Form Grid */
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.form-group.full-width {
    grid-column: span 2;
}

.form-group label {
    font-size: 0.875rem;
    font-weight: 500;
    color: #4b5563;
}

.required {
    color: #ef4444;
    margin-left: 2px;
}

.form-group input,
.form-group select {
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.875rem;
    transition: all 0.2s ease;
    background: white;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #0ab39c;
    box-shadow: 0 0 0 3px rgba(10, 179, 156, 0.1);
}

.form-group input.error,
.form-group select.error {
    border-color: #ef4444;
    background: #fef2f2;
}

.error-text {
    color: #ef4444;
    font-size: 0.75rem;
}

/* Password Input */
.password-input {
    position: relative;
    display: flex;
    align-items: center;
}

.password-input input {
    width: 100%;
    padding-right: 40px;
}

.password-toggle {
    position: absolute;
    right: 8px;
    background: none;
    border: none;
    color: #9ca3af;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.password-toggle:hover {
    color: #0ab39c;
}

/* Toggle Switch */
.toggle-wrapper {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 6px 0;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #d1d5db;
    transition: 0.2s;
    border-radius: 24px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.2s;
    border-radius: 50%;
}

input:checked + .toggle-slider {
    background: linear-gradient(135deg, #0ab39c 0%, #3577f1 100%);
}

input:checked + .toggle-slider:before {
    transform: translateX(20px);
}

.toggle-label {
    font-size: 0.875rem;
    color: #4b5563;
}

.text-success {
    color: #10b981;
}

.text-danger {
    color: #ef4444;
}

/* Buttons */
.btn-outline {
    padding: 6px 12px;
    background: white;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.875rem;
    color: #4b5563;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.btn-outline:hover {
    background: #f9fafb;
    border-color: #9ca3af;
}

.btn-outline-danger {
    color: #ef4444;
    border-color: #fecaca;
}

.btn-outline-danger:hover {
    background: #fee2e2;
    border-color: #ef4444;
    color: #dc2626;
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 32px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
}

.btn {
    padding: 10px 20px;
    border-radius: 6px;
    font-weight: 500;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-primary {
    background: linear-gradient(135deg, #0ab39c 0%, #3577f1 100%);
    color: white;
}

.btn-primary:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(10, 179, 156, 0.3);
}

.btn-secondary {
    background: white;
    border: 1px solid #d1d5db;
    color: #4b5563;
}

.btn-secondary:hover {
    background: #f9fafb;
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Success Message */
.success-message {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
    background: linear-gradient(135deg, #0ab39c 0%, #3577f1 100%);
    border-radius: 8px;
    color: white;
    margin: 16px 0;
}

.success-message i {
    font-size: 1.25rem;
    color: white;
}

/* Animations */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 640px) {
    .modal-container {
        width: 95%;
    }

    .modal-body {
        padding: 16px;
    }

    .profile-section {
        flex-direction: column;
        text-align: center;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .form-group.full-width {
        grid-column: span 1;
    }

    .form-actions {
        flex-direction: column-reverse;
    }

    .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>
