<template>
    <b-modal v-model="showModal" title="Browser Sessions" style="--vz-modal-width: 650px;" header-class="p-3 bg-light" class="v-modal-custom" modal-class="zoomIn" centered no-close-on-backdrop>    
        <div class="p-2">
            <form class="customform" @submit.prevent="submit">
                <div class="row g-3">
                    <div class="col-md-12 text-muted fs-12">
                        <h5 class="mb-3 fs-14 text-primary">Manage and log out your active sessions on other browsers and devices.</h5>
                        <p class="text-muted fs-12">If necessary, you may log out of all of your other browser sessions across all of your devices. Some of your recent sessions are listed below; however, this list may not be exhaustive. If you feel your account has been compromised, you should also update your password. </p>
                        <hr class="text-muted mt-0"/>
                    </div>
                    <div v-if="sessions.length > 0" class="mt-1" style="min-height: 100px; max-height: 150px; overflow: auto;">
                        <div v-for="(session, i) in sessions" :key="i" class="d-flex align-items-center mb-2">
                            <div>
                                <svg v-if="session.agent.is_desktop" width="30" class="text-muted" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" />
                                </svg>
                                <svg v-else width="30" class="text-muted" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
                                </svg>
                            </div>
                            <div class="ms-3">
                                <div>
                                    {{ session.agent.platform ? session.agent.platform : 'Unknown' }} - {{ session.agent.browser ? session.agent.browser : 'Unknown' }}
                                </div>
                                <div>
                                    <div class="text-xs">{{ session.ip_address }},
                                        <span v-if="session.is_current_device" class="text-success fw-semibold">This device</span>
                                        <span v-else>Last active {{ session.last_active }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <template v-slot:footer>
            <b-button @click="hide()" variant="light" block>Cancel</b-button>
            <b-button @click="confirmLogout" variant="danger" :disabled="form.processing" block><i class="ri-save-3-line align-bottom me-1"></i>Logout from other browsers</b-button> 
        </template>
    </b-modal>
    <b-modal v-model="confirmingLogout" title="Confirm Password" header-class="p-3 bg-light" class="v-modal-custom" modal-class="zoomIn" centered no-close-on-backdrop>    
        <div class="p-2">
            <form class="customform">
                <div class="row g-3">
                    <div class="col-md-12 text-muted fs-12 mb-n2">
                        For your security, please confirm your <b class="text-danger">password</b> to continue.
                    </div>
                    <div class="col-md-12">
                        <div class="mb-n2">                            
                            <TextInput id="password" ref="passwordInput" v-model="form.password" type="password" class="form-control" autofocus placeholder="Please enter password" autocomplete="password" required :class="{ 'is-invalid': form.errors.password}" />
                            <InputError :message="form.errors.password" />
                        </div>
                    </div>
                </div>
            </form>
        </div>
            <template v-slot:footer>
            <b-button @click="closeModal" variant="light" block>Cancel</b-button>
            <b-button @click="logoutOtherBrowserSessions" variant="primary" :disabled="form.processing" block><i class="ri-save-3-line align-bottom me-1"></i>Confirm</b-button>
        </template>
    </b-modal>
</template>
<script>
import InputError from '@/Shared/Components/Forms/InputError.vue';
import TextInput from '@/Shared/Components/Forms/TextInput.vue';
import { useForm } from '@inertiajs/vue3'
export default {
    components: { InputError, TextInput },
    data(){
        return {
            showModal: false,
            confirmingLogout: false,
            form: useForm({
                password: ''
            }),
            sessions: []
        }
    },
    methods : {
        show() {
            this.fetch();
            this.showModal = true;
        },
        fetch(){
            axios.get('/profile',{
                params: {
                    option: 'sessions'
                }
            })
            .then(response => {
                this.sessions = response.data;     
            });
        },
        confirmLogout(){
            this.confirmingLogout = true;
        },
        logoutOtherBrowserSessions(){
            this.form.delete('/profile/{destroy}', {
                preserveScroll: true,
                onSuccess: () => this.confirmingLogout = false,
                onFinish: () => this.form.reset(),
            });
        },
        hide(){
            this.form.reset();
            this.showModal = false;
        },
    }
}
</script>
