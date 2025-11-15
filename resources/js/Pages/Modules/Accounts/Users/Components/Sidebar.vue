<template>
    <div class="card bg-light-subtle shadow-none border">
        <div class="card-header bg-light-subtle">
            <div class="d-flex mb-n3">
                <div class="flex-shrink-0 me-3">
                    <div style="height:2.5rem;width:2.5rem;">
                        <span class="avatar-title bg-primary-subtle rounded p-2 mt-n1">
                            <i class="ri-information-fill text-primary fs-24"></i>
                        </span>
                    </div>
                </div>
                <div class="flex-grow-1">
                    <h5 class="mb-0 fs-14"><span class="text-body">User Details</span></h5>
                    <p class="text-muted text-truncate-two-lines fs-12">Basic Information of the user</p>
                </div>
            </div>
        </div>
        <div class="card-body bg-white rounded-bottom border-bottom">
            <p class="mb-0 text-primary fs-12 fw-semibold">Active Role Assignments</p>
        </div>
        <div class="card bg-white rounded-bottom shadow-none mb-0" style="height: calc(100vh - 398px); overflow: auto;">
            <ul class="mt-3 mb-1">
                <li class="" v-for="(list,index) in user.roles" v-bind:key="index">
                   <span class="badge bg-success">{{list.name}}</span>
                </li> 
            </ul>
            <hr class="text-muted"/>
                <p class="ms-3 mb-0 text-primary fs-12 fw-semibold">Account Security Settings</p>
            <hr class="text-muted mb-2"/>
            <ul class="list-group list-group-flush border-dashed mb-n4 mt-n3 p-3"  style="cursor: pointer;" @click="openSalary()">
                <li class="list-group-item px-0">
                    <div class="d-flex">
                        <div class="flex-shrink-0 avatar-xs">
                            <span class="avatar-title bg-light p-1 rounded-circle">
                                <i v-if="user.email_verified_at" class="ri-checkbox-circle-fill fs-18 text-success"></i>
                                <i v-else class="ri-close-circle-fill fs-18 text-danger"></i>
                            </span>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="mb-0 fs-11">Email Verified</h6>
                            <p class="fs-10 mb-0 text-muted">Indicates that the user's email address has been confirmed and verified.</p>
                        </div>
                    </div>
                </li>
                <li class="list-group-item px-0">
                    <div class="d-flex">
                        <div class="flex-shrink-0 avatar-xs">
                            <span class="avatar-title bg-light p-1 rounded-circle">
                                <i v-if="user.two_factor_confirmed_at" class="ri-checkbox-circle-fill fs-18 text-success"></i>
                                <i v-else class="ri-close-circle-fill fs-18 text-danger"></i>
                            </span>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="mb-0 fs-11">Two-Factor Authentication</h6>
                            <p class="fs-10 mb-0 text-muted">Adds an extra layer of security by requiring a verification code during login.</p>
                        </div>
                    </div>
                </li>
            </ul>
            <hr class="text-muted"/>
                <p class="ms-3 mb-0 text-primary fs-12 fw-semibold">Password Last Changed</p>
            <hr class="text-muted mb-1"/>
            <ul class="list-group list-group-flush border-dashed mb-n4 mt-n3 p-3"  style="cursor: pointer;" @click="openSalary()">
                <li class="list-group-item px-0">
                    <div class="d-flex">
                        <div class="flex-shrink-0 avatar-xs">
                            <span class="avatar-title bg-light p-1 rounded-circle">
                                <i v-if="user.password_changed_at" class="ri-time-fill fs-18 text-secondary"></i>
                                <i v-else class="ri-time-fill fs-18 text-warning"></i>
                            </span>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="mb-0 fs-11">{{ user.password_changed_at }}</h6>
                            <p class="fs-10 mb-0 text-muted">Password was last changed <span class="fw-semibold text-warning">{{ timeAgo(user.password_changed_at) }}</span></p>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>
<script>
import dayjs from "dayjs";
import relativeTime from "dayjs/plugin/relativeTime";
dayjs.extend(relativeTime);
export default {
    props: ['user'],
    data(){
        return {
            now: dayjs()
        }
    },
    mounted() {
        if (this.user?.roles) {
            this.user.roles.sort((a, b) => {
                if (a.is_active !== b.is_active) return b.is_active - a.is_active;

                if (a.is_active === 1) {
                    if (a.name === 'Employee' && b.name !== 'Employee') return 1;
                    if (a.name !== 'Employee' && b.name === 'Employee') return -1;
                }
                return 0;
            });
        }

        this.interval = setInterval(() => {
            this.now = dayjs(); // re-triggers computed values
        }, 60000);
    },
    beforeUnmount() {
        clearInterval(this.interval);
    },
    methods: { 
        timeAgo(date) {
            return dayjs(date).from(this.now);
        }
    }
}
</script>