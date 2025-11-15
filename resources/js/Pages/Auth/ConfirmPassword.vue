<template>
    <Head title="Secure Area"/>
    <div class="auth-page-wrapper pt-5 d-flex justify-content-center align-items-center min-vh-100">
        <div class="auth-page-content">
            <BContainer>
                <BRow class="justify-content-center">
                    <BCol md="8" lg="6" xl="5">
                        <BCard no-body class="mt-4">

                            <BCardBody class="p-4">
                                <div class="text-center mt-2">
                                    <h5 class="text-primary fs-15 ">Lock Screen</h5>
                                    <p class="text-muted fs-12"> This is a secure area of the application. Please confirm your password before continuing.</p>
                                </div>
                                <div class="user-thumb text-center">
                                    <img class="rounded-circle img-thumbnail avatar-md" :src="$page.props.user.data.avatar" :alt="$page.props.user.name">
                                    <h5 class="fs-14 mt-3">{{ $page.props.user.name }}</h5>
                                </div>
                                <div class="p-2 mt-4">
                                    <form class="customform" @submit.prevent="submit">
                                        <TextInput id="username" type="text" class="" style="display: none;" autocomplete="username"  />
                                        <div class="mb-3">
                                            <InputLabel for="password" value="Password" />
                                            <TextInput id="password" ref="passwordInput" v-model="form.password" type="password" class="" required placeholder="Enter your password" autocomplete="current-password" autofocus :class="{ 'is-invalid' : form.errors.password }"/>
                                            <InputError :message="form.errors.password" />
                                        </div>
                                        <div class="mb-2 mt-1">
                                            <BButton variant="primary" class="w-100" type="submit" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">Unlock</BButton>
                                        </div>

                                        <div class="mt-5 text-center">
                                            <p class="mb-0">Not you ? return
                                                <Link href="/login" class="fw-semibold text-primary text-decoration-underline"> Signin </Link>
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
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import InputLabel from '@/Shared/Components/Forms/InputLabel.vue';
import TextInput from '@/Shared/Components/Forms/TextInput.vue';
import InputError from '@/Shared/Components/Forms/InputError.vue';
const form = useForm({
    password: '',
});
const passwordInput = ref(null);
const submit = () => {
    form.post('/confirm-password', {
        onFinish: () => {
            form.reset();
        },
    });
};
</script>
<script>
export default {
    layout: null,
    data(){
        return {
            currentUrl: window.location.origin,
        }
    }
}
</script>
<style>
.auth-page-wrapper {
    background-color: #299cdb;
}
</style>
