<script setup>
import { nextTick, ref } from 'vue';
import { Link, Head, useForm, router } from '@inertiajs/vue3';
import InputError from '@/Shared/Components/Forms/InputError.vue';
import InputLabel from '@/Shared/Components/Forms/InputLabel.vue';
import TextInput from '@/Shared/Components/Forms/TextInput.vue';

const recovery = ref(false);

const form = useForm({
    code: '',
    recovery_code: '',
});

const recoveryCodeInput = ref(null);
const codeInput = ref(null);

const toggleRecovery = async () => {
    recovery.value ^= true;

    await nextTick();

    if (recovery.value) {
        recoveryCodeInput.value.focus();
        form.code = '';
    } else {
        codeInput.value.focus();
        form.recovery_code = '';
    }
};

const submit = () => {
    form.post('/two-factor-challenge');
};

const logout = () => {
  router.post('/logout');
};
</script>
<script>
export default {
    layout: null
}
</script>
<template>
    <Head title="Two Factor Authentication" />

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
                                            <i class="ri-rotate-lock-line"></i>
                                        </div>
                                    </div>
                                </div>

                                <div class="p-2 mt-4">
                                    <div class="text-muted text-center mb-4 mx-lg-3">
                                        <h4 class="fs-15">Two Factor Authentication</h4>
                                        <div class="text-sm fs-12 text-muted">
                                            <template v-if="! recovery">
                                                Please confirm access to your account by entering the authentication code provided by your authenticator application.
                                            </template>
                                
                                            <template v-else>
                                                Please confirm access to your account by entering one of your emergency recovery codes.
                                            </template>
                                        </div>
                                    </div>

                                    <form class="customform" @submit.prevent="submit">
                                        <div v-if="! recovery">
                                            <!-- <InputLabel for="code" value="Code" /> -->
                                            <TextInput
                                                id="code"
                                                ref="codeInput"
                                                v-model="form.code"
                                                type="text"
                                                inputmode="numeric"
                                                autofocus
                                                placeholder="Enter the 6 digit code"
                                                autocomplete="one-time-code"
                                                class="text-center"
                                                :class="{ 'is-invalid' : form.errors.code }"
                                            />
                                            <InputError class="mt-2" :message="form.errors.code" />
                                        </div>
                            
                                        <div v-else>
                                            <!-- <InputLabel for="recovery_code" value="Recovery Code" /> -->
                                            <TextInput
                                                id="recovery_code"
                                                ref="recoveryCodeInput"
                                                v-model="form.recovery_code"
                                                type="text"
                                                placeholder="Enter recovery code"
                                                autocomplete="one-time-code"
                                                class="text-center"
                                                :class="{ 'is-invalid' : form.errors.recovery_code }"
                                            />
                                            <InputError class="mt-2" :message="form.errors.recovery_code" />
                                        </div>

                                        <div class="mt-4">
                                            <BButton variant="primary" class="w-100" type="submit" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">Log In</BButton>
                                        </div>

                                         <div class="mt-4 text-center">
                                            <p class="mb-0" v-if="!recovery"> Use a recovery code? 
                                                <a style="cursor: pointer;" @click.prevent="toggleRecovery" class="fw-semibold text-primary text-decoration-underline"> Click here</a> | <a style="cursor: pointer;" @click.prevent="logout" class="fw-semibold text-danger">Logout</a>
                                            </p>
                                            <p class="mb-0" v-else> Use an authentication code? 
                                                <a style="cursor: pointer;" @click.prevent="toggleRecovery" class="fw-semibold text-primary text-decoration-underline"> Click here</a> | <a style="cursor: pointer;" @click.prevent="logout" class="fw-semibold text-danger">Logout</a>
                                            </p>
                                        </div>
                            
                                        <!-- <div class="d-flex align-items-center justify-content-end mt-4">
                                            <button type="button" class="btn btn-primary me-1" @click.prevent="toggleRecovery">
                                                <template v-if="! recovery">
                                                    Use a recovery code
                                                </template>
                            
                                                <template v-else>
                                                    Use an authentication code
                                                </template>
                                            </button>
                            
                                            <BButton variant="success" type="submit" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">Log in</BButton>
                                        </div> -->
                                        
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
<style>
.auth-page-wrapper {
    background-color: hsl(201, 80%, 82%);
}
</style>
