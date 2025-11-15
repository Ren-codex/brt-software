<template>
    <Head title="Forgot Password" />
    <div class="auth-page-wrapper pt-5 d-flex justify-content-center align-items-center min-vh-100">
        <div class="auth-page-content">
            <BContainer>
            
                <BRow class="justify-content-center">
                    <BCol md="8" lg="6" xl="5">
                        <BCard no-body class="mt-4">

                            <BCardBody class="p-5">
                                <div class="text-center mt-n3">
                                    <h5 class="text-primary fs-15">Forgot Password?</h5>
                                    <p class="text-muted fs-11">Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.</p>
                                    <!-- <lottie class="avatar-xl some-class mt-n4" colors="primary:#0ab39c" :options="defaultOptions" :height="120" :width="120" /> -->
                                </div>

                                <BAlert v-if="status" :model-value="true" variant="warning" class="mb-2 mx-2 border-0 text-center">{{ status }}</BAlert>

                                <div class="p-2 mt-4">
                                    <form class="customform" @submit.prevent="submit">
                                        <div class="mb-3">
                                            <InputLabel for="email" value="Email" />
                                            <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required autofocus autocomplete="email" placeholder="Enter your registred email address" :class="{ 'is-invalid': form.errors.email }" />
                                            <InputError :message="form.errors.email" />
                                        </div>

                                        <div class="text-center mt-2">
                                            <b-button variant="primary" class="w-100" type="submit" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">Send Reset Link</b-button>
                                        </div>

                                        <div class="mt-4 text-center">
                                            <p class="mb-0 fs-12">Wait, I remember my password...
                                                <Link href="/login" class="fw-semibold text-primary text-decoration-underline"> Click here</Link>
                                            </p>
                                        </div>

                                    </form>
                                </div>
                            </BCardBody>
                        </BCard>   

                    </BCol>
                </BRow>
            </BContainer>
        </div>
    </div>
</template>
<script setup>
import { Link, Head, useForm } from '@inertiajs/vue3';
import InputError from '@/Shared/Components/Forms/InputError.vue';
import InputLabel from '@/Shared/Components/Forms/InputLabel.vue';
import TextInput from '@/Shared/Components/Forms/TextInput.vue';
defineProps({
    status: String,
});
const form = useForm({
    email: '',
});
const submit = () => {
    form.post('/forgot-password');
};
</script>
<script>
export default {
    layout: null,
    data() {
        return {
         
        };
    }
}
</script>
<style>
.auth-page-wrapper {
    background-color: #299cdb;
}
</style>
