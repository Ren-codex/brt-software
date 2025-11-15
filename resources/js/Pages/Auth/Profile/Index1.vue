<template>
    <Head title="Profile" />
    <PageHeader title="Profile Information" pageTitle="User" />
    <div class="row">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body" style="height: calc(100vh - 220px); overflow: auto;">
                    <div class="text-center">
                        <div class="profile-user position-relative d-inline-block mx-auto mb-3">
                            <img :src="$page.props.user.data.avatar" class="rounded-circle avatar-xl img-thumbnail user-profile-image material-shadow">
                            <div class="avatar-xs p-0 rounded-circle profile-photo-edit">
                                <input id="profile-img-file-input" type="file" class="profile-img-file-input" @change="previewImage"/>
                                <label for="profile-img-file-input" class="profile-photo-edit avatar-xs">
                                    <span class="avatar-title rounded-circle bg-light text-body">
                                    <i class="ri-camera-fill"></i>
                                    </span>
                                </label>
                            </div>
                        </div>
                        <h5 class="fs-16 mb-1">{{ $page.props.user.data.name }}</h5>
                        <p class="text-muted mb-0">{{ $page.props.user.data.role }}</p>
                    </div>
                    <hr class="text-muted">
                    <b-list-group class="list-group-fill-success mt-4">
                        <BListGroupItem :active="activeTab === 1" href="#" class="list-group-item-action" @click="show(1)">
                            <i class="ri-apps-2-fill align-middle me-2"></i>Overview
                        </BListGroupItem>
                        <BListGroupItem :active="activeTab === 2" href="#" class="list-group-item-action" @click="show(2)">
                            <i class="ri-profile-fill align-middle me-2"></i>Personal Information
                        </BListGroupItem>
                        <BListGroupItem :active="activeTab === 3" href="#" class="list-group-item-action" @click="show(3)">
                            <i class="ri-shield-check-line align-middle me-2"></i>Password & Security
                        </BListGroupItem>
                        <BListGroupItem :active="activeTab === 4" href="#" class="list-group-item-action" @click="show(4)">
                            <i class="ri-shield-keyhole-fill align-middle me-2"></i>Authentication Logs
                        </BListGroupItem>
                        <BListGroupItem :active="activeTab === 5" href="#" class="list-group-item-action" @click="show(5)">
                            <i class="ri-history-line align-middle me-2"></i>Activity Logs
                        </BListGroupItem>
                    </b-list-group>
                </div>
            </div>
        </div>
        <div class="col-md-9" style="margin-top: 6px;">
            <Overview v-if="activeTab === 1"/>
            <Edit v-if="activeTab === 2"/>
            <Security v-if="activeTab === 3"/>
            <AuthenticationLog v-if="activeTab === 4"/>
            <ActivityLog v-if="activeTab === 5"/>
        </div>
    </div>
</template>
<script>
import { useForm } from "@inertiajs/vue3"
import Overview from "./Pages/Overview.vue";
import Edit from "./Pages/Edit.vue";
import Security from './Pages/Security.vue';
import ActivityLog from './Pages/ActivityLog.vue';
import AuthenticationLog from "./Pages/AuthenticationLog.vue";
import PageHeader from '@/Shared/Components/PageHeader.vue';
export default {
    components: { PageHeader, Overview, Edit, AuthenticationLog, ActivityLog, Security },
    data() {
        return {
            currentUrl: window.location.origin,
            activeTab: 1, 
            form: useForm({
                image: null,
            }),
        };
    },
    methods: {
        show(tab){
            this.activeTab = tab;
        },
        previewImage(e) {
            var fileInput = document.querySelector(".profile-img-file-input");
            var preview = document.querySelector(".user-profile-image");
            var file = fileInput.files[0];
            this.form.image = file;
            var reader = new FileReader();

            reader.addEventListener("load", () => { 
                preview.src = reader.result;
                this.form.post('/profile', {
                    errorBag: 'updateProfileInformation',
                    preserveScroll: true,
                    onSuccess: () => '',
                });
            }, false);

            if (file) { 
                reader.readAsDataURL(file); 
            }
        },
    }
}
</script>