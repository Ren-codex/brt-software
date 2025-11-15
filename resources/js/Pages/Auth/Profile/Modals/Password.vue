<template>
    <b-modal v-model="showModal" title="Update Password" header-class="p-3 bg-light" class="v-modal-custom" modal-class="zoomIn" centered no-close-on-backdrop>    
        <div class="p-2">
            <form class="customform" @submit.prevent="submit">
                <div class="row g-3">
                    <div class="col-md-12 text-muted fs-12 mb-2">
                        Ensure your account is using a long, random password to stay secure.
                    </div>
                    <div class="col-md-12">
                        <div class="form-floating mb-n4">
                            <TextInput id="current_password" v-model="form.current_password" :type="showPassword ? 'text' : 'password'" class="form-control" autofocus placeholder="Please current_password" autocomplete="current_password" required :class="{ 'is-invalid': form.errors.current_password}" />
                            <InputLabel for="current_password" value="Current Password"/>
                            <InputError :message="form.errors.current_password" />
                        </div>
                    </div>
                    <div class="col-md-12">
                        <hr class="text-muted"/>
                    </div>
                    <div class="col-md-12 mt-n1">
                        <div class="form-floating mb-n3">
                            <TextInput id="password" v-model="form.password" :type="showPassword ? 'text' : 'password'" class="form-control" autofocus placeholder="Please enter password" autocomplete="password" required :class="{ 'is-invalid': form.errors.password }" />
                            <InputLabel for="password" value="New Password"/>
                        </div>
                    </div>
                    <div class="col-md-12 mb-n4">
                        <div class="form-floating">
                            <TextInput id="password_confirmation" v-model="form.password_confirmation" :type="showPassword ? 'text' : 'password'" class="form-control" autofocus placeholder="Please enter password_confirmation" autocomplete="password_confirmation" required :class="{ 'is-invalid': form.errors.password }" />
                            <InputLabel for="password_confirmation" value="Re-type new Password"/>
                            <InputError :message="form.errors.password"/>
                        </div>
                    </div>
                    <div class="col-md-5 mb-n5 mt-5">
                        <div class="form-check form-switch ">
                            <input class="form-check-input" type="checkbox" role="switch" id="flexSwitchCheckChecked" v-model="showPassword">
                            <label class="form-check-label" for="flexSwitchCheckChecked">Show Password</label>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <template v-slot:footer>
            <b-button @click="hide()" variant="light" block>Cancel</b-button>
            <b-button @click="create()" variant="primary" :disabled="form.processing"><i class="ri-save-3-line align-bottom me-1"></i> Save</b-button>
        </template>
    </b-modal>
</template>
<script>
import { useForm } from '@inertiajs/vue3'
import InputError from '@/Shared/Components/Forms/InputError.vue';
import InputLabel from '@/Shared/Components/Forms/InputLabel.vue';
import TextInput from '@/Shared/Components/Forms/TextInput.vue';
export default {
    components : { InputError, InputLabel, TextInput },
    data(){
        return {
            showModal: false,
            showPassword: false,
            form: useForm({
                current_password: '',
                password: '',
                password_confirmation: ''
            }),
        }
    },
    methods : {
        show() {
            this.showModal = true;
        },
        create(){
            this.form.put('/password',{
                errorBag: 'updatePassword',
                preserveScroll: true,
                onSuccess: (response) => {
                    this.hide();
                },
                onError: () => {
                    if (this.form.errors.password) {
                        this.form.reset('password', 'password_confirmation');
                    }
                    if (this.form.errors.current_password) {
                        this.form.reset('current_password');
                    }
                },
            });
        },
        hide(){
            this.form.reset();
            this.showModal = false;
        },
    }
}
</script>
