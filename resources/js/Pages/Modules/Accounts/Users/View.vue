<template>
    <Head title="Employee Profile" />
    <div class="chat-wrapper d-lg-flex gap-1 mx-n4 mt-n4 p-1">
        <div class="w-100 p-4 pb-0" ref="box">
            <BRow>
                <BCol lg="12">
                    <BCard no-body class="mt-n4 mx-n4">
                        <div class="bg-info-subtle">
                            <BCardBody class="pb-0 px-4">
                                <BRow class="mb-3">
                                    <BCol md>
                                        <BRow class="align-items-center g-3">
                                            <BCol md="auto">
                                                <div style="height: 3.5rem; width: 3.5rem;">
                                                    <div class="avatar-title bg-white rounded-circle">
                                                        <img :src="user.avatar" alt="" class="avatar-sm rounded-circle">
                                                    </div>
                                                </div>
                                            </BCol>
                                            <BCol md>
                                                <div class="ms-2">
                                                    <h4 class="fw-bold text-uppercase">{{ user.fullname }}</h4>
                                                    <div class="hstack gap-3 flex-wrap">
                                                        <div><i class="ri-qr-code-fill align-bottom me-1"></i> {{user.username}}</div>
                                                        <div class="vr" style="width: 1px;"></div>
                                                        <div><span class="text-muted">Username :</span> <span class="fw-medium">{{user.username}}</span></div>
                                                        <div class="vr" style="width: 1px;"></div>
                                                        <div><span class="text-muted">Email :</span> <span class="fw-medium">{{user.email}}</span></div>
                                                        <div class="vr" style="width: 1px;"></div>
                                                        <div><span class="text-muted">Mobile :</span> <span class="fw-medium">{{user.mobile}}</span></div>
                                                    </div>
                                                </div>
                                            </BCol>
                                        </BRow>
                                    </BCol>
                                    <BCol md="auto">
                                        <div class="hstack gap-1 flex-wrap">
                                            <button @click="back()" type="button" class="btn py-0 fs-16 mt-3 text-body">
                                                <i class="ri-share-line"></i>
                                            </button>
                                        </div>
                                    </BCol>
                                </BRow>
                            </BCardBody>
                        </div>
                    </BCard>
                </BCol>
                <BCol lg="3">
                    <Sidebar :user="user_data.data"/>
                </BCol>
                <BCol lg="9">
                    <Main :dropdowns="dropdowns" :user="user_data.data"/>
                </BCol>
            </BRow>
        </div>
    </div>
</template>
<script>
import Main from './Components/Main.vue';
import Sidebar from './Components/Sidebar.vue';
export default {
    components: { Main, Sidebar },
    props:['user_data','dropdowns'],
    data(){
        return {
            user: this.user_data.data
        }
    },
    methods: {
        back(){
            this.$inertia.visit('/users');
        }
    }
}
</script>