<template>
 <b-modal v-model="showModal" size="md" header-class="p-3 bg-light" title="Reset Password" class="v-modal-custom" modal-class="zoomIn" centered no-close-on-backdrop hide-footer>
        <form @submit.prevent="submitForm">
            <BRow>
                <BCol lg="12" class="mt-n1 mb-3">
                    <InputLabel value="New Password" :message="form.errors.password"/>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="ri-lock-line"></i>
                        </span>
                        <TextInput v-model="form.password" :type="togglePassword ? 'text' : 'password'" class="form-control" placeholder="Enter new password" :light="true" @input="validatePassword" :class="{ 'is-invalid': passwordMismatch }" />
                        <span class="input-group-text" style="cursor: pointer;" @click="togglePassword = !togglePassword">
                            <i :class="togglePassword ? 'ri-eye-off-fill' : 'ri-eye-fill'" class="align-middle"></i>
                        </span>
                    </div>
                    <small class="text-muted">
                        <ul class="mb-0">
                            <li :class="{ 'text-danger': !passwordValid.minLength }">At least 8 characters</li>
                            <li :class="{ 'text-danger': !passwordValid.capital }">1 capital letter</li>
                            <li :class="{ 'text-danger': !passwordValid.number }">1 number</li>
                            <li :class="{ 'text-danger': !passwordValid.special }">1 special character</li>
                        </ul>
                    </small>
                </BCol>
                <BCol lg="12" class="mt-n1 mb-3">
                    <InputLabel value="Confirm Password" :message="form.errors.confirm_password"/>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="ri-lock-line"></i>
                        </span>
                        <TextInput v-model="form.confirm_password" :type="toggleConfirm ? 'text' : 'password'" class="form-control" placeholder="Confirm new password" :light="true" @input="validatePassword" :class="{ 'is-invalid': passwordMismatch }" />
                        <span class="input-group-text" style="cursor: pointer;" @click="toggleConfirm = !toggleConfirm">
                            <i :class="toggleConfirm ? 'ri-eye-off-fill' : 'ri-eye-fill'" class="align-middle"></i>
                        </span>
                    </div>
                    <small v-if="passwordMismatch" class="text-danger">Passwords do not match.</small>
                </BCol>
            </BRow>
              <div class="d-flex justify-content-end gap-2 mt-3">
                    <BButton variant="light" @click="closeModal">Cancels</BButton>
                    <BButton variant="primary" @click="submit()" :disabled="processing || passwordMismatch">Reset Password</BButton>
                </div>
        </form>
      
    </b-modal>
</template>
<script>
import TextInput from '@/Shared/Components/Forms/TextInput.vue';
import InputLabel from '@/Shared/Components/Forms/InputLabel.vue';
import { useForm } from '@inertiajs/vue3';

export default {
    components: { TextInput, InputLabel },
    data() {
        return {
            showModal: false,
            user: null,
            form: useForm({
                id: null,
                password: '',
                confirm_password: '',
                option: 'reset_password'
            }),
            processing: false,
            togglePassword: false,
            toggleConfirm: false,
            passwordMismatch: false,
            passwordValid: {
                minLength: false,
                capital: false,
                number: false,
                special: false
            }
        }
    },
    methods: {
        show(data) {
            console.log(data, 99988);
            this.form.id = data.id;
            this.user = data;
            this.showModal = true;
            this.form.password = '';
            this.form.confirm_password = '';
            this.form.errors = {};
        },
        closeModal() {
            this.showModal = false;
        },
        validatePassword() {
            // Check password requirements
            this.passwordValid.minLength = this.form.password.length >= 8;
            this.passwordValid.capital = /[A-Z]/.test(this.form.password);
            this.passwordValid.number = /\d/.test(this.form.password);
            this.passwordValid.special = /[@$!%*?&]/.test(this.form.password);

            // Check mismatch
            if(this.form.password &&
            this.form.confirm_password &&
            this.form.password !== this.form.confirm_password){
                this.passwordMismatch = true;
            } else {
                this.passwordMismatch = false;
            }
        },
        submit() {
            this.processing = true;
            this.form.option = 'reset_password';
            this.form.put('/users/' + this.form.id,{
                preserveScroll: true,
                onSuccess: (response) => {
                    this.$emit('update', true);
                    this.form.reset();
                    this.closeModal();
                },
                OnError: () => {
                    this.processing = false;
                }
            });
        }
    }
}
</script>
