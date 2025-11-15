<script setup>
import { Link, Head, useForm } from '@inertiajs/vue3';
import InputError from '@/Shared/Components/Forms/InputError.vue';
import InputLabel from '@/Shared/Components/Forms/InputLabel.vue';
import TextInput from '@/Shared/Components/Forms/TextInput.vue';

const props = defineProps({
    email: String,
    token: String,
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<script>
export default {
    layout: null,
    data() {
        return {
            togglePassword: false,
            togglePassword_conf: false,
        }
    }
}
</script>

<template>
    <Head title="Reset Password" />

    <div class="auth-page-wrapper pt-5 d-flex justify-content-center align-items-center min-vh-100">
        <div class="auth-page-content">
            <BContainer>
                <BRow class="justify-content-center">
                    <BCol md="8" lg="6" xl="5">
                        <BCard no-body class="mt-4">

                            <BCardBody class="p-4">
                                <div class="text-center mt-2">
                                    <h5 class="text-primary">Create new password</h5>
                                    <p class="text-muted">Your new password must be different from previous used
                                        password.</p>
                                </div>

                                <div class="p-2">
                                    <form @submit.prevent="submit" class="customform">
                                        <div class="mb-3">
                                            <InputLabel for="email" value="Email" />
                                            <TextInput id="email" v-model="form.email" type="email" required autofocus autocomplete="email" :class="{ 'is-invalid' : form.errors.email}" />
                                            <InputError :message="form.errors.email" />
                                        </div>
                                        <div class="mb-3">
                                            <InputLabel for="password-input" value="Password" />
                                            <div class="position-relative auth-pass-inputgroup">
                                                <input :type="togglePassword ? 'text' : 'password'" class="form-control pe-5 password-input" placeholder="Enter password" id="password-input" v-model="form.password" required :class="{ 'is-invalid' : form.errors.password}">
                                                <BButton variant="link" class="position-absolute end-0 top-0 text-decoration-none text-muted password-addon" type="button" id="password-addon" @click="togglePassword = !togglePassword"><i class="ri-eye-fill align-middle"></i>
                                                </BButton>
                                                <InputError :message="form.errors.password" />
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <InputLabel for="password_confirmation" value="Confirm Password" />
                                            <div class="position-relative auth-pass-inputgroup mb-3">
                                                <input :type="togglePassword_conf ? 'text' : 'password'" class="form-control pe-5 password-input" placeholder="Confirm password" id="confirm-password-input" v-model="form.password_confirmation" required :class="{ 'is-invalid' : form.errors.password_confirmation}">
                                                <BButton variant="link" class="position-absolute end-0 top-0 text-decoration-none text-muted password-addon" type="button" id="confirm-password-input" @click="togglePassword_conf = !togglePassword_conf"><i class="ri-eye-fill align-middle"></i>
                                                </BButton>
                                                <InputError :message="form.errors.password_confirmation" />
                                            </div>
                                        </div>

                                        <div class="mt-4">
                                            <BButton variant="primary" class="w-100" :class="{ 'opacity-25': form.processing }" :disabled="form.processing" type="submit">Reset Password</BButton>
                                        </div>

                                    </form>
                                </div>
                            </BCardBody>
                        </BCard>

                        <div class="mt-4 text-center">
                            <p class="mb-0">Wait, I remember my password...
                                <Link href="/login" class="fw-semibold text-primary text-decoration-underline"> Click
                                here </Link>
                            </p>
                        </div>
                    </BCol>
                </BRow>
            </BContainer>
        </div>

       
    </div>
</template>
