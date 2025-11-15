<script setup>
import { ref, reactive, nextTick } from 'vue';
import { usePage } from '@inertiajs/vue3';
import InputError from '@/Shared/Components/Forms/InputError.vue';
import TextInput from '@/Shared/Components/Forms/TextInput.vue';

const emit = defineEmits(['confirmed']);

const confirmingPassword = ref(false);

const form = reactive({
    password: '',
    error: '',
    processing: false,
});

const passwordInput = ref(null);

const startConfirmingPassword = () => {
    if(usePage().props.user.data.password_confirmed_at){    
        emit('confirmed');
    }else{
        confirmingPassword.value = true;
        setTimeout(() => passwordInput.value.focus(), 250);
    }
};

const confirmPassword = () => {
    form.processing = true;

    axios.post('/confirm-password', {
        password: form.password,
    }).then(() => {
        form.processing = false;

        closeModal();
        nextTick().then(() => emit('confirmed'));

    }).catch(error => {
        form.processing = false;
        form.error = error.response.data.errors.password[0];
        passwordInput.value.focus();
    });
};

const closeModal = () => {
    confirmingPassword.value = false;
    form.password = '';
    form.error = '';
};
</script>

<template>
    <span>
        <span @click="startConfirmingPassword">
            <slot />
        </span>

        <b-modal v-model="confirmingPassword" title="Confirm Password" header-class="p-3 bg-light" class="v-modal-custom" modal-class="zoomIn" centered no-close-on-backdrop>    
            <div class="p-2">
                <form class="customform" @submit.prevent="submit">
                    <div class="row g-3">
                        <div class="col-md-12 text-muted fs-12 mb-n2">
                            For your security, please confirm your <b class="text-danger">password</b> to continue.
                        </div>
                        <div class="col-md-12">
                            <div class="mb-n2">                            
                                <TextInput id="password" ref="passwordInput" v-model="form.password" type="password" class="form-control" autofocus placeholder="Please enter password" autocomplete="password" required :class="{ 'is-invalid': form.error}" />
                                <InputError :message="form.error" />
                            </div>
                        </div>
                    </div>
                </form>
            </div>
             <template v-slot:footer>
                <b-button @click="closeModal" variant="light" block>Cancel</b-button>
                <b-button @click="confirmPassword" variant="primary" :disabled="form.processing" block><i class="ri-save-3-line align-bottom me-1"></i>Confirm</b-button>
            </template>
        </b-modal>
    </span>
</template>
