<template>
    <div
        v-if="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"
    >
        <div class="modal-container modal-lg" @click.stop>
            <div class="modal-header">
                <h2>{{ editable ? 'Update Account' : 'Account Information' }}</h2>
                <button class="close-btn" type="button" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>

            <div class="modal-body">
                <form @submit.prevent="submit">
                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label for="username" class="form-label">Username</label>
                            <div class="input-wrapper">
                                <i class="ri-user-line input-icon"></i>
                                <input
                                    id="username"
                                    v-model="form.username"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'input-error': form.errors.username }"
                                    placeholder="Enter username"
                                    @input="handleInput('username')"
                                >
                            </div>
                            <span v-if="form.errors.username" class="error-message">{{ form.errors.username }}</span>
                        </div>

                        <div class="form-group form-group-half">
                            <label for="email" class="form-label">Email</label>
                            <div class="input-wrapper">
                                <i class="ri-mail-line input-icon"></i>
                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    class="form-control"
                                    :class="{ 'input-error': form.errors.email }"
                                    placeholder="Enter email address"
                                    @input="handleInput('email')"
                                >
                            </div>
                            <span v-if="form.errors.email" class="error-message">{{ form.errors.email }}</span>
                        </div>
                    </div>

                    <div v-if="!editable" class="form-row">
                        <div class="form-group form-group-half">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-wrapper has-action">
                                <i class="ri-lock-password-line input-icon"></i>
                                <input
                                    id="password"
                                    v-model="form.password"
                                    :type="togglePassword ? 'text' : 'password'"
                                    class="form-control"
                                    :class="{ 'input-error': form.errors.password || passwordMismatch }"
                                    placeholder="Enter password"
                                    @input="validatePassword"
                                >
                                <button class="input-action" type="button" @click="togglePassword = !togglePassword">
                                    <i :class="togglePassword ? 'ri-eye-off-line' : 'ri-eye-line'"></i>
                                </button>
                            </div>
                            <span v-if="form.errors.password" class="error-message">{{ form.errors.password }}</span>
                        </div>

                        <div class="form-group form-group-half">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <div class="input-wrapper has-action">
                                <i class="ri-shield-keyhole-line input-icon"></i>
                                <input
                                    id="confirm_password"
                                    v-model="form.confirm_password"
                                    :type="toggleConfirm ? 'text' : 'password'"
                                    class="form-control"
                                    :class="{ 'input-error': passwordMismatch }"
                                    placeholder="Confirm password"
                                    @input="validatePassword"
                                >
                                <button class="input-action" type="button" @click="toggleConfirm = !toggleConfirm">
                                    <i :class="toggleConfirm ? 'ri-eye-off-line' : 'ri-eye-line'"></i>
                                </button>
                            </div>
                            <span v-if="passwordMismatch" class="error-message">Passwords do not match.</span>
                        </div>
                    </div>

                    <div v-if="!editable" class="form-row">
                        <div class="form-group">
                            <label class="form-label">Roles</label>
                            <Multiselect
                                :options="dropdowns.roles"
                                :searchable="true"
                                label="name"
                                v-model="form.role_ids"
                                placeholder="Select roles"
                                @input="handleInput('role_ids')"
                                mode="tags"
                                class="modern-multiselect"
                            />
                            <span v-if="form.errors.role_ids" class="error-message">{{ form.errors.role_ids }}</span>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-cancel" @click="hide">
                            <i class="ri-close-line"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-save" :disabled="form.processing || passwordMismatch">
                            <i class="ri-save-line" v-if="!form.processing"></i>
                            <i class="ri-loader-4-line spinner" v-else></i>
                            {{ form.processing ? 'Saving...' : editable ? 'Update Account' : 'Save Information' }}
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
    data(){
        return {
            currentUrl: window.location.origin,
            form: useForm({
                id: null,
                email: null,
                password: null,
                confirm_password: null,
                username: null,
                role_ids: null,
                option: 'users'
            }),
            togglePassword: false,
            toggleConfirm: false,
            passwordMismatch: false,
            showModal: false,
            editable: false,
            saveSuccess: false
        }
    },
    methods: { 
        show(data = null){
            this.form.defaults({
                id: null,
                email: null,
                password: null,
                confirm_password: null,
                username: null,
                role_ids: null,
                option: 'users'
            }).reset();
            this.form.clearErrors();
            this.passwordMismatch = false;
            this.togglePassword = false;
            this.toggleConfirm = false;
            this.saveSuccess = false;
            if (data) {
                this.form.id = data.id;
                this.form.username = data.username;
                this.form.email = data.email;
                this.form.role_ids = data.roles ? data.roles.map(r => r.role.id) : [];
                this.editable = true;
            } else {
                this.editable = false;
            }
            this.showModal = true;
        },
        edit(data){
            this.form.clearErrors();
            this.form.id = data.id;
            this.form.username = data.username;
            this.form.email = data.email;
            this.form.password = null;
            this.form.confirm_password = null;
            this.form.role_ids = data.roles ? data.roles.map(r => r.role.id) : [];
            this.passwordMismatch = false;
            this.togglePassword = false;
            this.toggleConfirm = false;
            this.editable = true;
            this.saveSuccess = false;
            this.showModal = true;
        },
        validatePassword() {
            this.passwordMismatch =
                this.form.password &&
                this.form.confirm_password &&
                this.form.password !== this.form.confirm_password;
        },

        submit(){
    
            if(this.editable){
                this.form.put('/users/' + this.form.id,{
                    preserveScroll: true,
                    onSuccess: (response) => {
                        this.$emit('add', true);
                        this.form.reset();
                        this.hide();
                    },
                });
            }else{
                this.form.post('/users',{
                    preserveScroll: true,
                    onSuccess: (response) => {
                        this.$emit('add', true);
                        this.form.reset();
                        this.hide();
                        this.$emit('success',true);
                    },
                });
            }
        },
        handleInput(field) {
            this.form.errors[field] = false;
        },
        hide(){
            this.form.reset();
            this.form.clearErrors();
            this.passwordMismatch = false;
            this.togglePassword = false;
            this.toggleConfirm = false;
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = false;
        }
    }
}
</script><style scoped>
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(6px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
    z-index: 1055;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.25s ease;
}

.modal-overlay.active {
    opacity: 1;
    pointer-events: auto;
}

.modal-container {
    background: #ffffff;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.22);
    transform: translateY(12px) scale(0.98);
    transition: transform 0.25s ease;
}

.modal-overlay.active .modal-container {
    transform: translateY(0) scale(1);
}

.modal-lg {
    width: min(100%, 920px);
    max-height: calc(100vh - 3rem);
    overflow-y: auto;
}

.modal-header {
    background: linear-gradient(135deg, #2e8b57 0%, #1f6b41 100%);
    color: white;
    padding: 1.5rem 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.modal-header h2 {
    font-weight: 600;
    font-size: 1.5rem;
    margin: 0;
}

.close-btn {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.close-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg);
}

.modal-body {
    padding: 2rem;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
}

.form-row {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    flex: 1;
}

.form-group-half {
    flex: 0 0 calc(50% - 0.75rem);
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #495057;
    font-size: 0.9rem;
}

.input-wrapper {
    position: relative;
}

.input-wrapper.has-action .form-control {
    padding-right: 3.25rem;
}

.input-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 1rem;
    pointer-events: none;
}

.form-control {
    width: 100%;
    padding: 0.875rem 1rem 0.875rem 2.75rem;
    border: 2px solid #e9ecef;
    border-radius: 14px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    background: white;
}

.form-control:focus {
    border-color: #2e8b57;
    box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.12);
    outline: none;
}

.input-error {
    border-color: #dc3545;
}

.input-action {
    position: absolute;
    right: 0.875rem;
    top: 50%;
    transform: translateY(-50%);
    border: none;
    background: transparent;
    color: #64748b;
    padding: 0;
    width: 24px;
    height: 24px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.modern-multiselect {
    --ms-border-color: #e9ecef;
    --ms-border-radius: 14px;
    --ms-py: 0.6rem;
    --ms-px: 0.75rem;
    --ms-font-size: 0.95rem;
    --ms-ring-width: 0px;
    --ms-tag-bg: #e6f7ed;
    --ms-tag-color: #2e8b57;
    --ms-option-bg-selected: #2e8b57;
    --ms-option-color-selected: #ffffff;
}

.modern-multiselect:focus-within {
    --ms-border-color: #2e8b57;
    --ms-shadow: 0 0 0 3px rgba(46, 139, 87, 0.12);
}

.success-alert {
    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
    border: 1px solid #6ee7b7;
    color: #065f46;
    padding: 1rem 1.25rem;
    border-radius: 14px;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}

.error-message {
    color: #dc3545;
    font-size: 0.85rem;
    margin-top: 0.5rem;
}

.btn {
    border-radius: 12px;
    font-weight: 500;
    transition: all 0.3s ease;
    padding: 0.9rem 1.25rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.btn-cancel {
    background: #6c757d;
    color: white;
    border: none;
}

.btn-cancel:hover {
    background: #5a6268;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
}

.btn-save {
    background: linear-gradient(135deg, #2e8b57 0%, #1f6b41 100%);
    border: none;
    color: white;
}

.btn-save:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(46, 139, 87, 0.3);
}

.btn-save:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e9ecef;
}

.spinner {
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .modal-header {
        padding: 1rem 1.5rem;
    }

    .modal-body {
        padding: 1.5rem;
    }

    .form-row {
        flex-direction: column;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .form-group-half {
        flex-basis: auto;
    }

    .form-actions {
        flex-direction: column;
    }

    .btn {
        width: 100%;
    }
}
</style>
