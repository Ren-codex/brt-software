<template>
    <b-modal v-model="showModal" size="xl" header-class="p-3 bg-light" :title="(editable) ? 'Update Account' : 'Add Account'" class="v-modal-custom" modal-class="zoomIn" centered no-close-on-backdrop>
        <form class="customform">
            <BRow>
                <BCol lg="12">
                    <BRow class="g-3 mt-n1">
                        <BCol lg="6" class="mt-n1 mb-3">
                            <InputLabel value="Username" :message="form.errors.username"/>
                            <TextInput v-model="form.username" type="text" class="form-control" placeholder="Please enter username" @input="handleInput('username')" :light="true" />
                        </BCol>

                        <BCol lg="6" class="mt-n1 mb-3">
                            <InputLabel value="Email" :message="form.errors.email"/>
                            <TextInput v-model="form.email" type="email" class="form-control" placeholder="Please enter email" @input="handleInput('email')" :light="true" />
                        </BCol>

                        <BCol lg="6" class="mt-n1 mb-3" v-if="!editable">
                            <InputLabel value="Password" :message="form.errors.password"/>
                            <div class="position-relative auth-pass-inputgroup">
                                <TextInput
                                    v-model="form.password"
                                    :type="togglePassword ? 'text' : 'password'"
                                    class="form-control pe-5"
                                    placeholder="Please enter password"
                                    @input="validatePassword"
                                    :class="{ 'is-invalid': passwordMismatch }"
                                    :light="true"
                                />
                                <BButton variant="link" class="position-absolute end-0 top-50 text-decoration-none text-muted" type="button" @click="togglePassword = !togglePassword" style="transform: translateY(-50%);">
                                    <i :class="togglePassword ? 'ri-eye-off-fill' : 'ri-eye-fill'" class="align-middle"></i>
                                </BButton>
                            </div>
                        </BCol>

                        <BCol lg="6" class="mt-n1 mb-3" v-if="!editable">
                            <InputLabel value="Confirm Password" />
                            <div class="position-relative auth-pass-inputgroup">
                                <TextInput
                                    v-model="form.confirm_password"
                                    :type="toggleConfirm ? 'text' : 'password'"
                                    class="form-control pe-5"
                                    placeholder="Please confirm password"
                                    @input="validatePassword"
                                    :class="{ 'is-invalid': passwordMismatch }"
                                    :light="true"
                                />
                                <BButton variant="link" class="position-absolute end-0 top-50 text-decoration-none text-muted" type="button" @click="toggleConfirm = !toggleConfirm" style="transform: translateY(-50%);">
                                    <i :class="toggleConfirm ? 'ri-eye-off-fill' : 'ri-eye-fill'" class="align-middle"></i>
                                </BButton>
                            </div>

                            <!-- Password mismatch warning -->
                            <small v-if="passwordMismatch" class="text-danger">
                                Passwords do not match.
                            </small>
                        </BCol>

                        <BCol :lg="editable ? 6 : 12" class="mt-n1 mb-3" v-if="!editable">
                            <InputLabel value="Roles" :message="form.errors.role_ids"/>
                            <Multiselect :options="dropdowns.roles" :searchable="true" label="name" v-model="form.role_ids" placeholder="Select Roles" @input="handleInput('role_ids')" mode="tags"/>
                        </BCol>

                        </BRow>
                </BCol>
            </BRow>
        </form>
        <template v-slot:footer>
            <b-button @click="hide()" variant="light" block>Cancel</b-button>
            <b-button
                @click="submit('ok')"
                variant="primary"
                :disabled="form.processing || passwordMismatch"
                block
            >
                Submit
            </b-button>
        </template>
    </b-modal>
</template>
<script>
import { useForm } from '@inertiajs/vue3';
import Multiselect from "@vueform/multiselect";
import InputLabel from '@/Shared/Components/Forms/InputLabel.vue';
import TextInput from '@/Shared/Components/Forms/TextInput.vue';
export default {
    components: {InputLabel, TextInput, Multiselect },
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
            editable: false
        }
    },
    methods: { 
        show(data = null){
            this.form.reset();
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
            this.form.id = data.id;
            this.form.username = data.username;
            this.form.email = data.email;
            this.form.password = data.password;
            this.form.role_ids = data.roles ? data.roles.map(r => r.role.id) : [];
            this.editable = true;
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
            this.editable = false;
            this.showModal = false;
        }
    }
}
</script><style scoped>
.modal-content {
    border-radius: 16px;
    border: none;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-bottom: none;
    border-radius: 16px 16px 0 0;
    padding: 1.5rem 2rem;
}

.modal-header .modal-title {
    font-weight: 600;
    font-size: 1.5rem;
    display: flex;
    align-items: center;
}

.modal-header .modal-title::before {
    content: '���';
    margin-right: 0.5rem;
    font-size: 1.8rem;
}

.btn-close {
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

.btn-close:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg);
}

.modal-body {
    padding: 2rem;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
}

.form-row {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.form-group {
    flex: 1;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #495057;
    font-size: 0.9rem;
}

.form-control {
    width: 100%;
    padding: 0.75rem;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    background: white;
}

.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    outline: none;
}

.form-control.is-invalid {
    border-color: #dc3545;
}

.multiselect {
    --ms-border-color: #e9ecef;
    --ms-border-radius: 8px;
    --ms-py: 0.75rem;
    --ms-font-size: 0.95rem;
}

.multiselect:focus {
    --ms-border-color: #667eea;
    --ms-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.position-relative {
    position: relative;
}

.btn {
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-light {
    background: #6c757d;
    color: white;
    border: none;
}

.btn-light:hover {
    background: #5a6268;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
}

.btn-primary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.text-danger {
    color: #dc3545 !important;
}

.modal-footer {
    border-top: 1px solid #e9ecef;
    padding: 1.5rem 2rem;
    background: #f8f9fa;
    border-radius: 0 0 16px 16px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .modal-dialog {
        margin: 1rem;
    }

    .modal-header {
        padding: 1rem 1.5rem;
    }

    .modal-body {
        padding: 1.5rem;
    }

    .form-row {
        flex-direction: column;
        gap: 1rem;
    }

    .modal-footer {
        padding: 1rem 1.5rem;
        flex-direction: column;
    }

    .btn {
        width: 100%;
        margin-bottom: 0.5rem;
    }
}
</style>
