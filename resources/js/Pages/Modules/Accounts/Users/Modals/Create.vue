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
                    <div v-if="!editable" class="form-row">
                        <div class="form-group">
                            <label class="form-label">Link to Employee <span style="font-weight:400;color:#94a3b8">(optional)</span></label>
                            <Multiselect
                                :options="dropdowns.unlinked_employees || []"
                                :searchable="true"
                                label="name"
                                v-model="form.employee_id"
                                placeholder="Search employee..."
                                class="modern-multiselect"
                                @select="onEmployeeSelect"
                                @clear="onEmployeeClear"
                            />
                        </div>
                    </div>
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
                                    :disabled="!editable && !!form.employee_id"
                                    :placeholder="!editable && form.employee_id ? 'Generating…' : 'Enter username'"
                                    @input="handleInput('username')"
                                >
                            </div>
                            <span v-if="!editable && form.employee_id" class="hint-message">Generated from the employee's initials and birth date{{ form.username ? '' : ' — will be finalized on save' }}.</span>
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

                    <div v-if="saveSuccess" class="success-alert">
                        <div class="success-alert-icon"><i class="ri-checkbox-circle-fill"></i></div>
                        <div class="success-alert-body">
                            <strong>Account created</strong>
                            <div class="credentials-list">
                                <div class="credentials-row">
                                    <span class="credentials-label">Username</span>
                                    <span class="credentials-value">{{ createdUsername }}</span>
                                </div>
                                <div v-if="createdEmail" class="credentials-row">
                                    <span class="credentials-label">Email</span>
                                    <span class="credentials-value">{{ createdEmail }}</span>
                                </div>
                                <div class="credentials-row">
                                    <span class="credentials-label">Default Password</span>
                                    <span class="credentials-value">{{ createdPassword }}</span>
                                </div>
                            </div>
                            <p class="credentials-note">The user will be required to set a new password on first login.</p>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-cancel" @click="hide">
                            <i class="ri-close-line"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-save" :disabled="form.processing">
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
                username: null,
                role_ids: null,
                employee_id: null,
                option: 'users'
            }),
            showModal: false,
            editable: false,
            saveSuccess: false,
            createdUsername: '',
            createdEmail: '',
            createdPassword: ''
        }
    },
    mounted() {
        document.addEventListener('keydown', this._onEscape);
    },
    beforeUnmount() {
        document.removeEventListener('keydown', this._onEscape);
    },
    methods: {
        _onEscape(e) {
            if (e.key === 'Escape' && this.showModal && !this.form.processing) this.hide();
        },
        onEmployeeSelect(value, option) {
            if (option?.email) {
                this.form.email = option.email;
            }
            this.form.username = option?.username || null;
            this.form.errors.username = false;
        },
        onEmployeeClear() {
            this.form.email = null;
            this.form.username = null;
        },
        show(data = null){
            this.form.defaults({
                id: null,
                email: null,
                username: null,
                role_ids: null,
                employee_id: null,
                option: 'users'
            }).reset();
            this.form.clearErrors();
            this.saveSuccess = false;
            this.createdUsername = '';
            this.createdEmail = '';
            this.createdPassword = '';
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
            this.form.role_ids = data.roles ? data.roles.map(r => r.role.id) : [];
            this.editable = true;
            this.saveSuccess = false;
            this.showModal = true;
        },

        submit(){
            this.saveSuccess = false;

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
                    onSuccess: (page) => {
                        const flash = page?.props?.flash || {};
                        const created = flash.data?.data || flash.data || {};
                        this.createdUsername = created.username || '';
                        this.createdEmail = created.email || '';
                        this.createdPassword = flash.password || '';
                        this.saveSuccess = true;
                        this.$emit('add', true);
                        this.$emit('success', true);
                        setTimeout(() => {
                            this.form.reset();
                            this.hide();
                        }, 4000);
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
            this.form.employee_id = null;
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = false;
        }
    }
}
</script><style scoped>
.modal-lg {
    width: min(100%, 920px);
    max-height: calc(100vh - 3rem);
    overflow-y: auto;
}

.modal-header h2 {
    font-size: 1.1rem;
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
    padding: 1.1rem 1.25rem;
    border-radius: 14px;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}

.success-alert-icon {
    font-size: 1.25rem;
    line-height: 1.4;
}

.success-alert-body {
    flex: 1;
}

.credentials-list {
    margin-top: 0.6rem;
    background: rgba(255, 255, 255, 0.55);
    border-radius: 10px;
    padding: 0.6rem 0.9rem;
}

.credentials-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    padding: 0.3rem 0;
}

.credentials-row + .credentials-row {
    border-top: 1px solid rgba(110, 231, 183, 0.5);
}

.credentials-label {
    font-size: 0.8rem;
    color: #0d6e4f;
    font-weight: 500;
}

.credentials-value {
    font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
    font-weight: 600;
    color: #065f46;
}

.credentials-note {
    margin: 0.6rem 0 0;
    font-size: 0.8rem;
    color: #0d6e4f;
}

.error-message {
    color: #dc3545;
    font-size: 0.85rem;
    margin-top: 0.5rem;
}

.hint-message {
    color: #6b8c85;
    font-size: 0.8rem;
    margin-top: 0.5rem;
}

.form-control:disabled {
    background: #f1f3f5;
    color: #6c757d;
    cursor: not-allowed;
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
