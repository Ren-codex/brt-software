<template>
    <b-modal v-model="showModal" header-class="p-3 bg-light" title="Update User Credential" class="v-modal-custom" modal-class="zoomIn" centered no-close-on-backdrop>
        <form class="customform">
            <BRow class="g-3 p-2 mb-n4">
                <BCol lg="12" class="mt-2">
                    <InputLabel for="name" value="Email" :message="form.errors.email"/>
                    <TextInput id="name" v-model="form.email" type="email" class="form-control" placeholder="Please enter email" @input="handleInput('email')" :light="true"/>
                </BCol>
                <BCol lg="12" class="mt-0">
                    <InputLabel for="name" value="Mobile" :message="form.errors.email"/>
                    <TextInput id="name" v-model="form.mobile" type="text" class="form-control" placeholder="Please enter mobile" @input="handleInput('mobile')" :light="true"/>
                </BCol>
                <BCol lg="12">
                    <div class="form-check mt-1">
                        <input 
                            type="checkbox" 
                            class="form-check-input" 
                            id="permanentDelete" 
                            v-model="form.set"
                        />
                        <label class="form-check-label fs-11" for="permanentDelete">
                            Allow the system to generate a temporary password for the user
                        </label>
                    </div>
                </BCol>
                <BCol lg="12" v-if="form.set" class="mt-2 mb-4">
                    <div class="d-flex">
                        <div style="width: 100%;">
                            <InputLabel for="tempPassword" value="Generated Password"/>
                            <TextInput 
                            id="tempPassword" 
                            v-model="form.password" 
                            type="text" 
                            class="form-control me-2" 
                            readonly
                            :light="true"
                        />
                        </div>
                        <div class="flex-shrink-0">
                            <b-button @click="copyPassword" style="margin-top: 20px;" variant="light" class="waves-effect waves-light ms-1"><i class="ri-scissors-line"></i></b-button>
                        </div>
                    </div>
                    <div v-if="showCopiedAlert" class="alert alert-primary alert-top-border alert-dismissible fade show material-shadow mb-0" role="alert">
                        <i class="ri-scissors-line me-3 align-middle fs-12 text-primary"></i><span class="fs-12">Password copied to clipboard!</span> 
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>

                </BCol>
            </BRow>
        </form> 
        <template v-slot:footer>
            <b-button @click="hide()" variant="light" block>Cancel</b-button>
            <b-button @click="submit('ok')" variant="primary" :disabled="form.processing" block>Submit</b-button>
        </template>
    </b-modal>
</template>
<script>
import _ from 'lodash';
import { useForm } from '@inertiajs/vue3';
import Multiselect from "@vueform/multiselect";
import InputLabel from '@/Shared/Components/Forms/InputLabel.vue';
import TextInput from '@/Shared/Components/Forms/TextInput.vue';
import Textarea from '@/Shared/Components/Forms/Textarea.vue';
export default {
    components: { Multiselect, InputLabel, TextInput, Textarea },
    data(){
        return {
            currentUrl: window.location.origin,
            form: useForm({
                code: null,
                email: null,
                mobile: null,
                password: null,
                set: false,
                option: 'credential'
            }),
            showCopiedAlert: false,
            showModal: false
        }
    },
     watch: {
        'form.set'(newValue) {
            if(newValue){
                this.generatePassword();
            } else {
                this.form.password = null;
            }
        }
    },
    methods: { 
        show(data){
            this.form.reset();
            this.form.code = data.code;
            this.form.email = data.email;
            this.form.mobile = data.mobile;
            this.showModal = true;
        },
        submit(){
            this.form.put('/users/update',{
                preserveScroll: true,
                onSuccess: (response) => {
                    this.$emit('update',this.$page.props.flash.data.data);
                    this.form.clearErrors();
                    this.form.reset();
                    this.hide();
                },
            });
        },
        copyPassword() {
            if (!this.form.password) return;
            const passwordInput = document.getElementById('tempPassword');

            if (!passwordInput) {
                alert('Password field not found.');
                return;
            }

            passwordInput.select();
            passwordInput.setSelectionRange(0, 99999); 

            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    this.showCopiedAlert = true;
                    setTimeout(() => {
                        this.showCopiedAlert = false;
                    }, 3000);
                } else {
                    alert('Failed to copy password.');
                }
            } catch (err) {
                alert('Failed to copy password.');
            }
            window.getSelection().removeAllRanges();
        },
        generatePassword(){
            if(this.form.set){
                const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_+[]{}|;:,.<>?";
                let password = "";
                for(let i=0; i<12; i++){
                    password += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                this.form.password = password;
            } else {
                this.form.password = null;
            }
        },
        handleInput(field) {
            this.form.errors[field] = false;
        },
        hide(){
            this.showModal = false;
        }
    }
}
</script>