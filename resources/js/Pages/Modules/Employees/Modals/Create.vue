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
                <form class="employee-form" @submit.prevent="submit">
                    <!-- Profile Picture - Simple -->
                    <div class="profile-section" :class="{ 'has-error': form.errors.avatar }">
                        <div class="profile-section-header">
                            <label>Profile Photo</label>
                            <span class="error-text" v-if="form.errors.avatar">{{ form.errors.avatar }}</span>
                        </div>
                        <div class="profile-picture" :class="{ 'error-border': form.errors.avatar }">
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
                                <label>Email Address <span v-if="needsAccount" class="required">*</span></label>
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
                    <div class="form-section" v-if="!editable">
                        <h3 class="section-title">
                            <i class="ri-lock-line"></i>
                            Account Credentials
                        </h3>

                        <div class="toggle-wrapper" style="margin-bottom: 16px;">
                            <label class="toggle-switch">
                                <input type="checkbox" v-model="needsAccount">
                                <span class="toggle-slider"></span>
                            </label>
                            <span class="toggle-label">{{ needsAccount ? 'Employee needs account access' : 'No account access required' }}</span>
                        </div>

                        <div class="form-grid" v-if="needsAccount">
                            <div class="form-group">
                                <label>Username</label>
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
                                <label>Select Roles</label>
                                <div class="role-picker" :class="{ 'error': form.errors.role_ids }">
                                    <button type="button" class="role-picker-trigger" @click="roleDropdownOpen = !roleDropdownOpen">
                                        <span>{{ selectedRolesText }}</span>
                                        <i :class="roleDropdownOpen ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line'"></i>
                                    </button>
                                    <div v-if="roleDropdownOpen" class="role-picker-menu">
                                        <label v-for="role in roleOptions" :key="role.value" class="role-picker-option">
                                            <input
                                                type="checkbox"
                                                :checked="isRoleSelected(role.value)"
                                                @change="toggleRoleSelection(role.value, $event.target.checked)"
                                            >
                                            <span>{{ role.name }}</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="role-chips" v-if="selectedRoleNames.length">
                                    <span class="role-chip" v-for="role in selectedRoleNames" :key="role.value">
                                        {{ role.name }}
                                        <button type="button" @click="removeRole(role.value)">
                                            <i class="ri-close-line"></i>
                                        </button>
                                    </span>
                                </div>
                                <span class="error-text" v-if="form.errors.role_ids">{{ form.errors.role_ids }}</span>
                            </div>

                            <div class="form-group">
                                <label>Password</label>
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
                                <label>Confirm Password</label>
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

                    <transition name="fade">
                        <div class="submit-message submit-message-error" v-if="submitError">
                            <div class="submit-message-icon">
                                <i class="ri-error-warning-line"></i>
                            </div>
                            <div class="submit-message-body">
                                <strong>Unable to save employee</strong>
                                <span>{{ submitError }}</span>
                            </div>
                        </div>
                    </transition>

                    <!-- Simple Success Message -->
                    <transition name="fade">
                        <div class="success-message" v-if="saveSuccess">
                            <div class="success-message-icon">
                                <i class="ri-checkbox-circle-line"></i>
                            </div>
                            <div class="success-message-body">
                                <strong>Employee saved</strong>
                                <span>{{ successMessage }}</span>
                            </div>
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
                role_ids: [],
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
            successMessage: 'Employee saved successfully!',
            submitError: '',
            previewImage: null,
            passwordMismatch: false,
            needsAccount: false,
            roleDropdownOpen: false,
        }
    },
    computed: {
        roleOptions() {
            const roles = Array.isArray(this.dropdowns?.roles) ? this.dropdowns.roles : [];
            return roles
                .map((role) => ({
                    value: role?.value ?? role?.id ?? null,
                    name: role?.name ?? role?.title ?? '',
                }))
                .filter((role) => role.value !== null && role.name);
        },
        selectedRoleNames() {
            const ids = Array.isArray(this.form.role_ids) ? this.form.role_ids.map((id) => Number(id)) : [];
            return this.roleOptions.filter((role) => ids.includes(Number(role.value)));
        },
        selectedRolesText() {
            if (!this.selectedRoleNames.length) return 'Select roles';
            if (this.selectedRoleNames.length === 1) return this.selectedRoleNames[0].name;
            return `${this.selectedRoleNames.length} roles selected`;
        }
    },
    watch: {
        'form.password'() {
            this.checkPasswordMatch();
        },
        'form.password_confirmation'() {
            this.checkPasswordMatch();
        },
        needsAccount(newValue) {
            if (!newValue) {
                this.form.username = null;
                this.form.password = null;
                this.form.password_confirmation = null;
                this.form.role_ids = [];
                this.roleDropdownOpen = false;
            }
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
            this.successMessage = 'Employee saved successfully!';
            this.submitError = '';
            this.needsAccount = false;
            this.roleDropdownOpen = false;
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
            this.form.role_ids = this.extractRoleIds(data.user);
            this.form.mobile = data.mobile;
            this.form.birthdate = data.birthdate;
            this.form.sex = data.sex;
            this.form.religion = data.religion;
            this.form.address = data.address;
            this.form.position_id = data.position_id;
            this.form.is_regular = data.is_regular;
            this.form.is_active = data.is_active;
            this.form.is_blacklisted = data.is_blacklisted;
            
            // Handle avatar preview - construct the correct storage path
            if (data.avatar) {
                // Check if avatar already has 'avatars/' prefix or just the filename
                const avatarPath = data.avatar.startsWith('avatars/') || data.avatar.startsWith('storage/') 
                    ? data.avatar 
                    : `avatars/${data.avatar}`;
                this.previewImage = `${this.currentUrl}/storage/${avatarPath}`;
            } else {
                this.previewImage = null;
            }
            
            // Set needsAccount based on whether user exists
            this.needsAccount = !!data.user;
            this.roleDropdownOpen = false;
            
            this.editable = true;
            this.saveSuccess = false;
            this.successMessage = 'Employee updated successfully!';
            this.submitError = '';
            this.showModal = true;
        },
        submit() {
            this.saveSuccess = false;
            this.submitError = '';

            if (!this.needsAccount) {
                this.form.username = null;
                this.form.password = null;
                this.form.password_confirmation = null;
                this.form.role_ids = [];
            } else if (!Array.isArray(this.form.role_ids)) {
                this.form.role_ids = [];
            }

            if (this.editable) {
                this.form.put(`/employees/${this.form.id}`, {
                    preserveScroll: true,
                    onSuccess: (page) => {
                        const flash = page?.props?.flash || {};
                        if (flash.status === false) {
                            this.submitError = flash.info || flash.message || 'Failed to update employee. Please review the form and try again.';
                            return;
                        }

                        this.successMessage = flash.message || 'Employee updated successfully!';
                        this.saveSuccess = true;
                        setTimeout(() => {
                            this.$emit('update', true);
                            this.hide();
                        }, 1500);
                    },
                    onError: (errors) => {
                        this.submitError = this.formatSubmitError(errors, 'Please check the highlighted fields and try again.');
                    },
                });
            } else {
                this.form.post('/employees', {
                    preserveScroll: true,
                    onSuccess: (page) => {
                        const flash = page?.props?.flash || {};
                        if (flash.status === false) {
                            this.submitError = flash.info || flash.message || 'Failed to save employee. Please review the form and try again.';
                            return;
                        }

                        this.successMessage = flash.message || 'Employee saved successfully!';
                        this.saveSuccess = true;
                        setTimeout(() => {
                            this.$emit('add', true);
                            this.hide();
                        }, 1500);
                    },
                    onError: (errors) => {
                        this.submitError = this.formatSubmitError(errors, 'Please check the highlighted fields and try again.');
                    },
                });
            }
        },
        handleInput(field) {
            this.form.errors[field] = false;
            if (this.submitError) {
                this.submitError = null;
            }
        },
        toggleRoleSelection(roleId, checked) {
            const normalizedRoleId = Number(roleId);
            if (Number.isNaN(normalizedRoleId)) return;

            const ids = Array.isArray(this.form.role_ids)
                ? this.form.role_ids.map((id) => Number(id)).filter((id) => !Number.isNaN(id))
                : [];

            if (checked) {
                if (!ids.includes(normalizedRoleId)) ids.push(normalizedRoleId);
                this.form.role_ids = ids;
            } else {
                this.form.role_ids = ids.filter((id) => id !== normalizedRoleId);
            }

            this.handleInput('role_ids');
        },
        isRoleSelected(roleId) {
            const normalizedRoleId = Number(roleId);
            if (Number.isNaN(normalizedRoleId) || !Array.isArray(this.form.role_ids)) return false;
            return this.form.role_ids.map((id) => Number(id)).includes(normalizedRoleId);
        },
        removeRole(roleId) {
            const normalizedRoleId = Number(roleId);
            if (Number.isNaN(normalizedRoleId) || !Array.isArray(this.form.role_ids)) return;
            this.form.role_ids = this.form.role_ids
                .map((id) => Number(id))
                .filter((id) => id !== normalizedRoleId && !Number.isNaN(id));
            this.handleInput('role_ids');
        },
        formatSubmitError(errors, fallbackMessage) {
            const messages = Object.values(errors || {})
                .flat()
                .filter(Boolean);

            if (!messages.length) {
                return fallbackMessage;
            }

            return messages.join(' | ');
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
            this.successMessage = 'Employee saved successfully!';
            this.submitError = '';
            this.passwordMismatch = false;
            this.needsAccount = true;
            this.roleDropdownOpen = false;
            this.showModal = false;
        },
        checkPasswordMatch() {
            this.passwordMismatch = this.form.password && this.form.password_confirmation && 
                                   this.form.password !== this.form.password_confirmation;
        },
        extractRoleIds(user) {
            if (!user) return [];
            if (Array.isArray(user.roles) && user.roles.length) {
                return user.roles.map((role) => role.id).filter(Boolean);
            }
            if (user.role && user.role.id) {
                return [user.role.id];
            }
            if (Array.isArray(user.myroles) && user.myroles.length) {
                const activeRoles = user.myroles
                    .filter((item) => item.is_active === 1 || item.is_active === true)
                    .map((item) => item.role_id)
                    .filter(Boolean);
                if (activeRoles.length) return activeRoles;
            }
            return [];
        }
    }
}
</script>

<style scoped>
.modal-overlay {
    --ink-900: #102b26;
    --ink-700: #2f5550;
    --ink-500: #5d7d79;
    --line-200: #d7e4e1;
    --line-100: #e9f1ef;
    --mint-700: #1e7e67;
    --mint-500: #2da487;
    --mint-100: #e8f8f3;
    --danger-500: #d65b4e;
    --danger-100: #fff2f0;
    position: fixed;
    inset: 0;
    background: radial-gradient(circle at 15% 18%, rgba(45, 164, 135, 0.22), transparent 40%), rgba(10, 25, 22, 0.74);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 18px;
    z-index: 1050;
    opacity: 0;
    transition: opacity 0.25s ease;
}

.modal-overlay.active {
    opacity: 1;
}

.modal-container {
    max-width: min(1320px, 100%);
    max-height: calc(100vh - 36px);
    overflow: hidden;
    border-radius: 24px;
    border: 1px solid rgba(255, 255, 255, 0.35);
    background: linear-gradient(145deg, #f6fcfa 0%, #f9fffd 55%, #f2f8f7 100%);
    box-shadow: 0 28px 70px rgba(3, 18, 15, 0.3);
    transform: translateY(18px) scale(0.97);
    transition: transform 0.25s ease;
    font-family: "Poppins", "Segoe UI", sans-serif;
}

.modal-overlay.active .modal-container {
    transform: translateY(0) scale(1);
}

.modal-header {
    padding: 16px 22px;
    border-bottom: 1px solid var(--line-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(140deg, #d7ece5 0%, #c7e2d9 100%);
}

.header-title {
    display: flex;
    align-items: center;
    gap: 10px;
}

.header-title i {
    width: 38px;
    height: 38px;
    border-radius: 11px;
    display: grid;
    place-items: center;
    background: rgba(26, 104, 87, 0.15);
    color: #1a6857;
    font-size: 21px;
}

.header-title h2 {
    margin: 0;
    font-size: 1.16rem;
    color: var(--ink-900);
    font-weight: 700;
}

.close-btn {
    width: 34px;
    height: 34px;
    border: 0;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.75);
    color: var(--ink-700);
    font-size: 19px;
    display: grid;
    place-items: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.close-btn:hover {
    background: #fff;
    transform: rotate(90deg);
}

.modal-body {
    padding: 18px;
    max-height: calc(100vh - 126px);
    overflow-y: auto;
}

.employee-form {
    display: grid;
    grid-template-columns: 320px minmax(0, 1fr);
    gap: 14px;
}

.profile-section {
    grid-column: 1;
    grid-row: 1 / span 4;
    border: 1px solid var(--line-200);
    border-radius: 18px;
    background: linear-gradient(165deg, #f8fffc 0%, #f1faf7 100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
    padding: 18px;
    position: sticky;
    top: 0;
}

.profile-section.has-error {
    border-color: #efb3ab;
}

.profile-section-header {
    width: 100%;
    text-align: center;
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.profile-section-header label {
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.06em;
    color: var(--ink-500);
    font-weight: 700;
}

.profile-picture {
    width: 130px;
    height: 130px;
    border-radius: 999px;
    overflow: hidden;
    border: 3px solid #fff;
    box-shadow: 0 14px 26px rgba(14, 48, 41, 0.18);
}

.profile-picture.error-border {
    border-color: #efb3ab;
}

.profile-picture img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(145deg, #2f967d 0%, #1f6656 100%);
    display: grid;
    place-items: center;
    color: #fff;
    font-size: 40px;
}

.profile-actions {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-section {
    grid-column: 2;
    border: 1px solid var(--line-100);
    border-radius: 16px;
    background: #fff;
    padding: 16px;
    box-shadow: 0 8px 24px rgba(21, 57, 49, 0.06);
}

.section-title {
    margin: 0 0 12px;
    padding-bottom: 10px;
    border-bottom: 1px dashed #d6e4e1;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    color: #1f5149;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 700;
}

.section-title i {
    width: 24px;
    height: 24px;
    border-radius: 7px;
    display: grid;
    place-items: center;
    background: var(--mint-100);
    color: var(--mint-700);
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group label {
    font-size: 0.76rem;
    color: var(--ink-700);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-weight: 700;
}

.required {
    color: var(--danger-500);
}

.form-group input,
.form-group select {
    height: 40px;
    border: 1px solid var(--line-200);
    border-radius: 10px;
    background: #fbfefd;
    color: #1a302c;
    padding: 0 11px;
    font-size: 0.86rem;
}

.form-group input:focus,
.form-group select:focus,
.role-picker-trigger:focus {
    outline: none;
    border-color: var(--mint-500);
    box-shadow: 0 0 0 3px rgba(45, 164, 135, 0.14);
}

.form-group input.error,
.form-group select.error {
    border-color: #e59c92;
    background: var(--danger-100);
}

.role-picker {
    position: relative;
}

.role-picker-trigger {
    width: 100%;
    min-height: 40px;
    border: 1px solid var(--line-200);
    border-radius: 10px;
    background: #fbfefd;
    color: #1a302c;
    padding: 0 11px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    font-size: 0.86rem;
}

.role-picker-menu {
    position: absolute;
    z-index: 20;
    top: calc(100% + 6px);
    left: 0;
    right: 0;
    max-height: 190px;
    overflow-y: auto;
    border: 1px solid var(--line-200);
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.14);
    padding: 6px;
}

.role-picker-option {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 7px 8px;
    border-radius: 8px;
    cursor: pointer;
}

.role-picker-option:hover {
    background: #f2fbf7;
}

.role-picker.error .role-picker-trigger {
    border-color: #e59c92;
    background: var(--danger-100);
}

.role-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 6px;
}

.role-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 999px;
    background: #e6f7ef;
    color: #1e6d57;
    font-size: 0.74rem;
    font-weight: 600;
}

.role-chip button {
    border: 0;
    background: transparent;
    padding: 0;
    color: inherit;
    cursor: pointer;
    display: grid;
    place-items: center;
}

.error-text {
    font-size: 0.73rem;
    color: var(--danger-500);
}

.password-input {
    position: relative;
    display: flex;
    align-items: center;
}

.password-input input {
    width: 100%;
    padding-right: 36px;
}

.password-toggle {
    position: absolute;
    right: 8px;
    border: 0;
    background: transparent;
    color: #6f8b87;
    font-size: 1rem;
    cursor: pointer;
}

.password-toggle:hover {
    color: var(--mint-700);
}

.toggle-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 4px 0;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    inset: 0;
    cursor: pointer;
    border-radius: 26px;
    background-color: #cedad7;
    transition: 0.25s;
}

.toggle-slider:before {
    content: "";
    position: absolute;
    width: 20px;
    height: 20px;
    left: 3px;
    bottom: 3px;
    border-radius: 50%;
    background: #fff;
    transition: 0.25s;
}

input:checked + .toggle-slider {
    background: linear-gradient(120deg, #2ca386 0%, #1b7c65 100%);
}

input:checked + .toggle-slider:before {
    transform: translateX(24px);
}

.toggle-label {
    font-size: 0.84rem;
    color: var(--ink-700);
    font-weight: 600;
}

.text-success {
    color: #1a8a61;
}

.text-danger {
    color: var(--danger-500);
}

.btn-outline {
    width: 100%;
    min-height: 38px;
    border: 1px solid #cfe2de;
    border-radius: 10px;
    background: #fff;
    color: var(--ink-700);
    font-size: 0.83rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    cursor: pointer;
}

.btn-outline:hover {
    background: #f6fcfa;
}

.btn-outline-danger {
    border-color: #efc7c1;
    color: var(--danger-500);
}

.btn-outline-danger:hover {
    background: #fff5f4;
}

.success-message {
    grid-column: 2;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 14px 16px;
    border-radius: 14px;
    border: 1px solid #9fdcc4;
    background: linear-gradient(120deg, #dbf9ed 0%, #effdf7 100%);
    color: #1f6b4f;
    box-shadow: 0 8px 20px rgba(31, 107, 79, 0.08);
}

.success-message-icon {
    width: 34px;
    height: 34px;
    min-width: 34px;
    border-radius: 10px;
    background: rgba(31, 107, 79, 0.12);
    display: grid;
    place-items: center;
    font-size: 1rem;
    color: #1f6b4f;
}

.success-message-body {
    display: flex;
    flex-direction: column;
    gap: 3px;
    line-height: 1.45;
}

.success-message-body strong {
    font-size: 0.85rem;
    font-weight: 700;
    color: #16533b;
}

.success-message-body span {
    font-size: 0.8rem;
    font-weight: 500;
    color: #1f6b4f;
}

.submit-message {
    grid-column: 2;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 14px 16px;
    border-radius: 14px;
    font-size: 0.84rem;
    font-weight: 600;
}

.submit-message-error {
    border: 1px solid #efb8b0;
    background: linear-gradient(120deg, #fff1ee 0%, #fff9f8 100%);
    color: #982f26;
    box-shadow: 0 8px 20px rgba(163, 63, 53, 0.08);
}

.submit-message-icon {
    width: 34px;
    height: 34px;
    min-width: 34px;
    border-radius: 10px;
    background: rgba(214, 91, 78, 0.12);
    display: grid;
    place-items: center;
    font-size: 1rem;
}

.submit-message-body {
    display: flex;
    flex-direction: column;
    gap: 3px;
    line-height: 1.45;
}

.submit-message-body strong {
    font-size: 0.85rem;
    color: #7f2119;
}

.submit-message-body span {
    font-size: 0.8rem;
    font-weight: 500;
    color: #a33f35;
    white-space: normal;
    word-break: break-word;
}

.form-actions {
    grid-column: 2;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 2px;
    padding-top: 14px;
    border-top: 1px solid #dbe8e4;
}

.btn {
    min-height: 40px;
    padding: 0 17px;
    border-radius: 10px;
    border: 0;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.84rem;
    font-weight: 700;
    cursor: pointer;
}

.btn-primary {
    background: linear-gradient(120deg, #2ca588 0%, #1c7f67 100%);
    color: #fff;
    box-shadow: 0 10px 18px rgba(28, 122, 99, 0.28);
}

.btn-primary:hover:not(:disabled) {
    transform: translateY(-1px);
}

.btn-secondary {
    background: #fff;
    border: 1px solid #d2dfdc;
    color: var(--ink-700);
}

.btn-secondary:hover {
    background: #f7fcfa;
}

.btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

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
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

@media (max-width: 1100px) {
    .employee-form {
        grid-template-columns: 1fr;
    }

    .profile-section {
        grid-column: 1;
        grid-row: auto;
        position: static;
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }

    .profile-section-header {
        text-align: left;
    }

    .profile-actions {
        width: auto;
        min-width: 190px;
    }

    .form-section {
        grid-column: 1;
    }

    .submit-message,
    .success-message,
    .form-actions {
        grid-column: 1;
    }
}

@media (max-width: 700px) {
    .modal-container {
        width: 95%;
        border-radius: 18px;
    }

    .modal-body {
        padding: 14px;
    }

    .profile-section {
        flex-direction: column;
        text-align: center;
    }

    .profile-section-header {
        text-align: center;
    }

    .profile-actions {
        width: 100%;
        min-width: 0;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .form-group.full-width {
        grid-column: 1;
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
