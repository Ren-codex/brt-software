<template>
    <PageHeader title="Profile Information" pageTitle="User" />
    <div class="employee-details-page">
        <div class="details-topbar">
            <div class="details-title-block">
                <h1>My Profile</h1>
                <p>Manage account details, security settings, and recent account activity.</p>
            </div>
        </div>

        <div class="details-grid">
            <aside class="details-sidebar">
                <div class="profile-card">
                    <div class="profile-avatar-wrap">
                        <div class="profile-avatar">
                            <img :src="$page.props.user.data.avatar" class="profile-avatar-image user-profile-image"
                                alt="User profile avatar">
                        </div>
                        <div class="profile-avatar-edit">
                            <input id="profile-img-file-input" type="file" class="profile-img-file-input"
                                @change="previewImage" />
                            <label for="profile-img-file-input" class="profile-avatar-btn" title="Change photo">
                                <i class="ri-camera-fill"></i>
                            </label>
                        </div>
                    </div>

                    <div class="profile-heading">
                        <h2>{{ $page.props.user.data.name || "-" }}</h2>
                        <div class="profile-badges">
                            <span class="profile-badge profile-badge-primary">{{ $page.props.user.data.role || "User" }}</span>
                            <span class="profile-badge profile-badge-neutral">{{ $page.props.user.data.username || "-" }}</span>
                        </div>
                    </div>

                    <div class="profile-info-list">
                        <div class="profile-info-item">
                            <div class="profile-label">Email</div>
                            <div class="profile-value">
                                <i class="ri-mail-line"></i>
                                {{ $page.props.user.data.email || "-" }}
                            </div>
                        </div>
                        <div class="profile-info-item">
                            <div class="profile-label">Phone</div>
                            <div class="profile-value">
                                <i class="ri-phone-line"></i>
                                {{ $page.props.user.data.mobile || "-" }}
                            </div>
                        </div>
                        <div class="profile-info-item">
                            <div class="profile-label">Gender</div>
                            <div class="profile-value">
                                <i class="ri-genderless-line"></i>
                                {{ $page.props.user.data.gender || "-" }}
                            </div>
                        </div>
                    </div>

                    <div class="profile-subcard">
                        <div class="profile-subcard-header">
                            <i class="ri-settings-3-line"></i>
                            <h3>Profile Sections</h3>
                        </div>
                        <div class="profile-tabs">
                            <button type="button" class="profile-tab-btn" :class="{ active: activeTab === 1 }"
                                @click="show(1)">
                                <i class="ri-apps-2-fill"></i>Overview
                            </button>
                            <button type="button" class="profile-tab-btn" :class="{ active: activeTab === 2 }"
                                @click="show(2)">
                                <i class="ri-profile-fill"></i>Personal Information
                            </button>
                            <button type="button" class="profile-tab-btn" :class="{ active: activeTab === 3 }"
                                @click="show(3)">
                                <i class="ri-lock-password-fill"></i>Password & Security
                            </button>
                            <button type="button" class="profile-tab-btn" :class="{ active: activeTab === 4 }"
                                @click="show(4)">
                                <i class="ri-shield-keyhole-fill"></i>Authentication Logs
                            </button>
                            <button type="button" class="profile-tab-btn" :class="{ active: activeTab === 5 }"
                                @click="show(5)">
                                <i class="ri-history-line"></i>Activity Logs
                            </button>
                        </div>
                    </div>
                </div>
            </aside>

            <section class="details-main">
                <div class="details-card profile-content-wrap">
                    <Overview v-if="activeTab === 1" />
                    <Edit v-if="activeTab === 2" />
                    <Security v-if="activeTab === 3" />
                    <AuthenticationLog v-if="activeTab === 4" />
                    <ActivityLog v-if="activeTab === 5" />
                </div>
            </section>
        </div>
    </div>
</template>

<script>
import { useForm } from "@inertiajs/vue3";
import Overview from "./Overview.vue";
import Edit from "./Edit.vue";
import Security from "./Security.vue";
import ActivityLog from "./ActivityLog.vue";
import AuthenticationLog from "./AuthenticationLog.vue";
import PageHeader from "@/Shared/Components/PageHeader.vue";

export default {
    components: { PageHeader, Overview, Edit, AuthenticationLog, ActivityLog, Security },
    data() {
        return {
            activeTab: 1,
            form: useForm({
                image: null,
            }),
        };
    },
    methods: {
        show(tab) {
            this.activeTab = tab;
        },
        previewImage() {
            const fileInput = document.querySelector(".profile-img-file-input");
            const preview = document.querySelector(".user-profile-image");
            const file = fileInput.files[0];
            this.form.image = file;
            const reader = new FileReader();

            reader.addEventListener("load", () => {
                preview.src = reader.result;
                this.form.post("/profile", {
                    errorBag: "updateProfileInformation",
                    preserveScroll: true,
                    onSuccess: () => "",
                });
            }, false);

            if (file) {
                reader.readAsDataURL(file);
            }
        },
    },
};
</script>

<style scoped>
.employee-details-page {
    --ink-900: #102723;
    --ink-700: #35524d;
    --ink-500: #5c7974;
    --line-200: #d2e4df;
    --mint-700: #1a7e67;
    --mint-500: #2fa485;
    --surface: #ffffff;
    --surface-soft: #f7fcfa;
    padding: 4px 0 0;
    max-width: 1360px;
    margin: 0 auto;
}

.details-topbar {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: 16px;
}

.details-title-block h1 {
    margin: 0;
    font-size: 1.5rem;
    color: var(--ink-900);
}

.details-title-block p {
    margin: 6px 0 0;
    color: var(--ink-500);
    font-size: 0.88rem;
}

.details-grid {
    display: grid;
    grid-template-columns: minmax(310px, 390px) minmax(0, 1fr);
    gap: 18px;
    align-items: start;
}

.details-sidebar {
    position: sticky;
    top: 12px;
}

.profile-card {
    border: 1px solid var(--line-200);
    border-radius: 22px;
    padding: 20px;
    background: linear-gradient(160deg, #f9fffd 0%, #eff9f6 100%);
    box-shadow: 0 12px 32px rgba(28, 64, 56, 0.08);
}

.profile-avatar-wrap {
    display: flex;
    justify-content: center;
    margin-bottom: 12px;
    position: relative;
}

.profile-avatar {
    width: 136px;
    height: 136px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid #fff;
    box-shadow: 0 10px 28px rgba(14, 47, 41, 0.2);
}

.profile-avatar-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-avatar-btn {
    position: absolute;
    right: calc(50% - 70px);
    bottom: -6px;
    width: 34px;
    height: 34px;
    padding: 0;
    border: 0;
    border-radius: 50%;
    display: grid;
    place-items: center;
    color: #fff;
    background: #1f826b;
    cursor: pointer;
    box-shadow: 0 10px 18px rgba(31, 130, 107, 0.28);
}

.profile-img-file-input {
    display: none;
}

.profile-heading {
    text-align: center;
    margin-bottom: 14px;
}

.profile-heading h2 {
    margin: 0;
    font-size: 1.22rem;
    color: var(--ink-900);
}

.profile-badges {
    margin-top: 10px;
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 6px;
}

.profile-badge {
    padding: 4px 9px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
}

.profile-badge-primary {
    background: #d9f0e8;
    color: #1b6f5a;
}

.profile-badge-neutral {
    background: #edf0f4;
    color: #4f6072;
}

.profile-info-list {
    border-top: 1px solid #d8e9e4;
    padding-top: 12px;
}

.profile-info-item {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    padding: 7px 0;
}

.profile-label {
    color: var(--ink-500);
    font-size: 0.8rem;
    font-weight: 600;
}

.profile-value {
    color: var(--ink-900);
    font-size: 0.82rem;
    font-weight: 600;
    text-align: right;
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    gap: 6px;
}

.profile-subcard {
    margin-top: 12px;
    border-top: 1px solid #d8e9e4;
    padding-top: 12px;
}

.profile-subcard-header {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-bottom: 8px;
    color: #1a6e5a;
}

.profile-subcard-header h3 {
    margin: 0;
    font-size: 0.9rem;
}

.profile-tabs {
    display: grid;
    gap: 0.5rem;
}

.profile-tab-btn {
    border: 1px solid #d7e7e2;
    background: var(--surface-soft);
    color: var(--ink-700);
    border-radius: 11px;
    padding: 0.62rem 0.72rem;
    font-size: 0.8rem;
    font-weight: 700;
    text-align: left;
    display: flex;
    align-items: center;
    gap: 0.45rem;
    transition: all 0.2s ease;
}

.profile-tab-btn i {
    font-size: 0.98rem;
}

.profile-tab-btn:hover {
    background: #f3fbf8;
    border-color: #bedbd3;
    transform: translateX(3px);
}

.profile-tab-btn.active {
    border-color: #2a9479;
    color: #fff;
    background: linear-gradient(125deg, var(--mint-500) 0%, var(--mint-700) 100%);
    box-shadow: 0 8px 20px rgba(28, 120, 99, 0.25);
}

.details-main {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.details-card {
    border: 1px solid #dbe9e5;
    border-radius: 20px;
    padding: 18px;
    background: var(--surface);
    box-shadow: 0 8px 28px rgba(22, 58, 50, 0.06);
}

.profile-content-wrap {
    min-height: calc(100vh - 240px);
    animation: fadeInUp 0.22s ease;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(7px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 1140px) {
    .details-grid {
        grid-template-columns: 1fr;
    }

    .details-sidebar {
        position: static;
    }
}

@media (max-width: 700px) {
    .employee-details-page {
        padding-top: 0;
    }

    .details-title-block h1 {
        font-size: 1.28rem;
    }

    .details-title-block p {
        font-size: 0.82rem;
    }

    .profile-card {
        padding: 14px;
    }

    .details-card {
        padding: 12px;
    }

    .profile-content-wrap {
        min-height: auto;
    }
}
</style>
