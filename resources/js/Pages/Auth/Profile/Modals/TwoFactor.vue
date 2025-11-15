<template>
    <b-modal v-model="showModal" title="Two Factor Authentication" style="--vz-modal-width: 650px;" header-class="p-3 bg-light" class="v-modal-custom" modal-class="zoomIn" centered no-close-on-backdrop>    
        <div class="p-2">
            <form class="customform" @submit.prevent="submit">
                <div class="row g-3">
                    <div class="col-md-12 text-muted fs-12">
                        <h5 class="mb-3 fs-14 text-success" v-if="twoFactorEnabled && !confirming">You have enabled two factor authentication.</h5>
                        <h5 class="mb-3 fs-14 text-warning" v-else-if="twoFactorEnabled && confirming">Finish enabling two factor authentication.</h5>
                        <h5 class="mb-3 fs-14 text-danger" v-else>You have not enabled two factor authentication.</h5>
                        <p class="text-muted fs-12">When two factor authentication is enabled, you will be prompted for a secure, random token during authentication. You may retrieve this token from your phone's Google Authenticator application.</p>
                    </div>
                    <div class="col-md-12 mt-n1" v-if="twoFactorEnabled">
                        <div v-if="qrCode">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mt-2" v-html="qrCode" />
                                </div>
                                <div class="col-md-8">
                                     <p v-if="confirming" class="text-muted mt-3 mb-n2 fs-12">
                                    To finish enabling two factor authentication, scan the following QR code using your phone's authenticator application or enter the setup key and provide the generated OTP code.
                                </p>
                                    <div v-if="setupKey" class="mt-4 text-muted">
                                        <div class="form-floating">
                                            <input ref="input" class="form-control" v-model="setupKey" readonly>
                                            <label class="form-label fw-semibold fs-12">SETUP KEY</label>
                                        </div>
                                        <div class="form-floating">
                                            <input ref="input" class="form-control" v-model="code" :class="{ 'is-invalid': form.errors.code}">
                                            <label class="form-label fw-semibold fs-12">CODE</label>
                                            <div class="invalid-feedback mt-n1">
                                                <span class="fs-10">{{ form.errors.code }}</span>
                                            </div>
                                        </div>
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
            <div v-if="!twoFactorEnabled">
                <b-button @click="enableTwoFactorAuthentication" variant="success" :disabled="form.processing" block><i class="ri-save-3-line align-bottom me-1"></i>Enable</b-button>
            </div>
            <div v-else>
                <Confirm @confirmed="confirmTwoFactorAuthentication">
                    <b-button v-if="confirming" variant="warning" :disabled="form.processing" block><i class="ri-save-3-line align-bottom me-1"></i>Confirm</b-button>
                </Confirm>
                <Confirm @confirmed="disableTwoFactorAuthentication">
                    <b-button v-if="!confirming" variant="danger" :disabled="form.processing" block><i class="ri-save-3-line align-bottom me-1"></i>Disable</b-button>
                </Confirm>
            </div>
        </template>
    </b-modal>
</template>
<script>
import Confirm from './Confirm.vue';
export default {
    components: { Confirm },
    data(){
        return {
            showModal: false,
            showPassword: false,
            enabling: (!this.$page.props.user.data.two_factor_enabled && !this.$page.props.user.data.two_factor_confirmed) ? true : false,
            confirming: (this.$page.props.user.data.two_factor_enabled && !this.$page.props.user.data.two_factor_confirmed) ? true : false,
            disabling: (this.$page.props.user.data.two_factor_enabled && this.$page.props.user.data.two_factor_confirmed) ? true : false,
            qrCode: null,
            setupKey: null,
            confirmed: false,
            twoFactorEnabled: (this.$page.props.user.data.two_factor_enabled) ? true : false,
            recoveryCodes: [],
            form: {
                errors: []
            }
        }
    },
    methods : {
        show() {
            this.showModal = true;
            (this.$page.props.user.data.two_factor_enabled && !this.$page.props.user.data.two_factor_confirmed) ? this.fetchTwoFactor() : '';
        },
        fetchTwoFactor(){
            return axios.post('/two-factor/fetch').then(response => {
                this.qrCode = response.data.url;
                this.setupKey = response.data.key;
            });
        },
        enableTwoFactorAuthentication(){
            this.enabling = true;
            return axios.post('/two-factor/enable').then(response => {
                this.twoFactorEnabled = true;
                this.confirming = true;
                this.qrCode = response.data.url;
                this.setupKey = response.data.key;
            });
        },
        confirmTwoFactorAuthentication(){
            return axios.post('/two-factor/confirm',{
                key: this.setupKey,
                code: this.code
            }).then(response => {
                if(!response.data.status){
                    this.form.errors.code = response.data.message;
                }else{
                    this.confirming = false;
                    this.qrCode = null;
                    this.setupKey = null;
                    this.form.errors.code = '';
                }
            });
        },
        disableTwoFactorAuthentication(){
            return axios.post('/two-factor/disable').then(response => {
                this.twoFactorEnabled = false;
            });
        },
        confirmPassword(){
            // axios.get('/user/confirmed-password-status').then(response => {
            //     if (!response.data.confirmed) {
            //        this.$refs.confirm.show();
            //     }
            // });
            (!$page.props.user.data.password_changed_at) ? this.$refs.confirm.show() : '';
        },
        hide(){
            this.showModal = false;
        },
    }
}
</script>
