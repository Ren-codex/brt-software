<template>
    <div class="card bg-light-subtle shadow-none border">
        <div class="card-header bg-light-subtle">
            <div class="d-flex mb-n3">
                <div class="flex-shrink-0 me-3">
                    <div style="height:2.5rem;width:2.5rem;">
                        <span class="avatar-title bg-primary-subtle rounded p-2 mt-n1">
                            <i class="ri-lock-password-fill text-primary fs-24"></i>
                        </span>
                    </div>
                </div>
                <div class="flex-grow-1">
                    <h5 class="mb-0 fs-14"><span class="text-body">Password & Security</span></h5>
                    <p class="text-muted text-truncate-two-lines fs-12">Ensure your account is using a long, random password to stay secure.</p>
                </div>
                <div class="flex-shrink-0">
                  
                </div>
            </div>
        </div>
        <div class="card-body bg-white rounded-bottom" style="height: calc(100vh - 295px); overflow: auto;">
            <div class="p-3">
                <b-row>
                    <b-col lg="4" md="6" v-for="(item, index) of statistics" :key="index">
                        <b-card no-body class="shadow-none border">
                            <b-card-body>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-light rounded-circle fs-3" :class="item.color">
                                            <i :class="`bx ${item.icon} align-middle`"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-semibold fs-12 text-muted mb-1">
                                            {{ item.name }}
                                        </p>
                                        <h4 class="mb-0">
                                            <span class="counter-value">
                                            {{ item.total }}
                                            </span>
                                        </h4>
                                    </div>
                                </div>
                            </b-card-body>
                        </b-card>
                    </b-col>
                </b-row>
                <hr class="text-muted mt-1 mb-4"/>
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="mb-1 fs-14 text-primary">Login & Recovery</h5>
                        <p class="text-muted fs-12">Manage your passwords, login preferences and recovery methods.</p>
                        <b-list-group>
                            <BListGroupItem @click="openModal('password')" tag="button" class="list-group-item-action"><i class="ri-lock-password-fill me-2"></i>Change Password 
                                <span class="text-muted fs-10 float-end">{{($page.props.user.data.password_changed_at) ? 'Updated '+$page.props.user.data.password_changed_at : 'Never updated'}}</span>
                            </BListGroupItem>
                            <BListGroupItem @click="openModal('twofactor')" tag="button" class="list-group-item-action"><i class="ri-shield-keyhole-fill me-2"></i>Two-factor Aunthentication 
                                <span v-if="$page.props.user.data.two_factor_enabled && $page.props.user.data.two_factor_confirmed" class="badge bg-success-subtle text-success float-end">Enabled</span>
                                <span v-else-if="$page.props.user.data.two_factor_enabled && !$page.props.user.data.two_factor_confirmed" class="badge bg-warning-subtle text-warning float-end">Pending</span>
                                <span v-else class="badge bg-danger-subtle text-danger float-end">Disabled</span>
                            </BListGroupItem>
                            <BListGroupItem @click="openModal('session')" tag="button" class="list-group-item-action"><i class="ri-window-fill me-2"></i>Browser Sessions</BListGroupItem>
                        </b-list-group>
                    </div>
                    <div class="col-md-6">
                        <h5 class="mb-1 fs-14 text-primary">Security Checks</h5>
                        <p class="text-muted fs-12">Review security issues by running checks across apps, devices and emails sent.</p>
                        <b-list-group>
                            <BListGroupItem @click="openModal('recovery')" tag="button" class="list-group-item-action"><i class="ri-folder-shield-2-fill me-2"></i>Recovery Codes</BListGroupItem>
                            <BListGroupItem tag="button" class="list-group-item-action"><i class=" ri-alarm-warning-fill me-2"></i>Login Alerts</BListGroupItem>
                            <BListGroupItem tag="button" class="list-group-item-action"><i class="ri-error-warning-fill me-2"></i>Security Checkup</BListGroupItem>
                        </b-list-group>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <Password ref="password"/>
    <TwoFactor ref="twofactor"/>
    <Recovery ref="recovery"/>
    <Session ref="session"/>
</template>
<script>
import Session from '../Modals/Session.vue';
import Recovery from '../Modals/Recovery.vue';
import Password from '../Modals/Password.vue';
import TwoFactor from '../Modals/TwoFactor.vue';
export default {
    components: { Password, TwoFactor, Recovery, Session },
    data(){
        return {
            statistics: [],
        }
    },
    created(){
        this.getCounts();
    },
    methods: {
        openModal(data){
            switch(data){
                case 'password':
                    this.$refs.password.show();
                break;
                case 'twofactor':
                    this.$refs.twofactor.show();
                break;
                case 'session':
                    this.$refs.session.show();
                break;
                case 'recovery':
                    this.$refs.recovery.show();
                break;
            }
        },
        getCounts(){
            return axios.get('/profile',{
                params : { option: 'statistics' }
            })
            .then(response => {
                this.statistics = response.data;     
            });
        }
    }
}
</script>