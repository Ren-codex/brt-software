<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
const props = defineProps({
    status: String,
});
const form = useForm({});
const submit = () => {
    form.post('/email/verification-notification');
};
const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>
<script>
export default {
    layout: null,
}
</script>
<template>
    <Head title="Email Verification" />

    <div class="auth-page-wrapper pt-5 d-flex justify-content-center align-items-center min-vh-100">
        <div class="auth-page-content">
            <BContainer>
                
                <BRow class="justify-content-center">
                    <BCol md="8" lg="6" xl="5">
                        <BCard no-body class="mt-4">

                            <BCardBody class="p-4">
                                <div class="mb-4">
                                    <div class="avatar-lg mx-auto">
                                        <div class="avatar-title bg-light text-primary display-5 rounded-circle">
                                            <i class="ri-mail-line"></i>
                                        </div>
                                    </div>
                                </div>

                                <div class="p-2 mt-4">
                                    <div class="text-muted text-center mb-4 mx-lg-3">
                                        <h4 class="fs-15">Verify Your Email</h4>
                                        <div class="text-sm text-muted fs-11">
                                            Before continuing, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the email, we will gladly send you another.
                                        </div>
                                    </div>

                                    <div v-if="verificationLinkSent" class="mb-4 fs-11 alert alert-success text-sm text-success">
                                        A new verification link has been sent to the email address you provided in your profile settings.
                                    </div>

                                    <form @submit.prevent="submit">
                                        <div class="w-100 mb-3">
                                            <BButton variant="primary" type="submit" class="w-100" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">Send Verification Email</BButton>
                                        </div>

                                        <div class="mt-5 text-center">
                                            <p class="mb-0">Didn't receive a code ?
                                                <Link href="/login" class="fw-semibold text-primary text-decoration-underline">Resend</Link> | <Link href="/logout" method="post" class="fw-semibold text-danger text-decoration-underline">Logout</Link>
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
