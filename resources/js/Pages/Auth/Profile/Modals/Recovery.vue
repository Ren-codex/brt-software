<template>
    <b-modal v-model="showModal" title="Recovery Codes" header-class="p-3 bg-light" class="v-modal-custom" modal-class="zoomIn" centered no-close-on-backdrop>    
        <div class="p-2">
            <form class="customform" @submit.prevent="submit">
                <div class="row g-3">
                    <div class="col-md-12 text-muted fs-12 mb-2">
                        A backup code is automatically generated for you when you turn on two-factor authentication through your Google Authenticator App. 
                         <hr class="text-muted mt-3"/>
                    </div>
                    <div class="col-md-12 mt-n2">
                        <div v-if="recoveryCodes.length > 0">
                            <div class="text-sm text-muted">
                                <p class="fs-10 text-danger fw-semibold">
                                    Store these recovery codes in a secure password manager. They can be used to recover access to your account if your two factor authentication device is lost.
                                </p>
                            </div>

                            <div class="fw-semibold">
                                <pre class="language-markup"><code><div v-for="code in recoveryCodes" :key="code">{{ code }}</div></code></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <template v-slot:footer>
            <b-button @click="hide()" variant="light" block>Cancel</b-button>
            <Confirm @confirmed="regenerateRecoveryCodes" v-if="recoveryCodes.length > 0">
                <b-button variant="primary" :disabled="form.processing" block><i class="ri-save-3-line align-bottom me-1"></i> Regenerate Recovery Codes</b-button>
            </Confirm>
            <Confirm @confirmed="showRecoveryCodes" v-else>
                <b-button variant="primary" :disabled="form.processing" block><i class="ri-save-3-line align-bottom me-1"></i> Show Recovery Codes</b-button>
            </Confirm>
        </template>
    </b-modal>
</template>
<script>
import Confirm from './Confirm.vue';
export default {
    components : {  Confirm },
    data(){
        return {
            showModal: false,
            recoveryCodes: [],
            form: {}
        }
    },
    methods : {
        show() {
            this.showModal = true;
        },
        regenerateRecoveryCodes(){
            axios
            .get('/two-factor/regenerate')
            .then(() => this.showRecoveryCodes());
        },
        showRecoveryCodes(){
            return axios.get('/two-factor/recovery')
            .then(response => {
                this.recoveryCodes = response.data;
            });
        },
        hide(){
            this.showModal = false;
        },
    }
}
</script>
