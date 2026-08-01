<template>
    <Head title="Activation" />

    <div class="activation-page">
        <div class="deco deco-1"></div>
        <div class="deco deco-2"></div>
        <div class="deco deco-3"></div>

        <div class="activation-card">
            <!-- Branding -->
            <div class="brand">
                <div class="logo-wrap">
                    <img src="@assets/images/logo-sm.png" alt="BRT Logo">
                </div>
                <div class="brand-name">BRT Accounting Software</div>
                <div class="brand-tag">Set up your account to continue</div>
            </div>

            <!-- User info row -->
            <div class="user-row">
                <div class="user-avatar">{{ userInitial }}</div>
                <div class="user-text">
                    <div class="user-name">{{ userName }}</div>
                    <div class="user-meta">{{ userMeta }}</div>
                </div>
            </div>

            <form @submit.prevent="create">
                <!-- Username -->
                <label class="field-label" for="username">Username</label>
                <div class="input-wrap" :class="{ 'input-error': form.errors.username }">
                    <i class="ri-user-line input-icon"></i>
                    <input
                        id="username"
                        v-model="form.username"
                        type="text"
                        class="glass-input"
                        placeholder="Enter username"
                        @input="handleInput('username')"
                    >
                </div>
                <div v-if="form.errors.username" class="field-error">{{ form.errors.username }}</div>

                <!-- New Password -->
                <label class="field-label" for="password">New Password</label>
                <div class="input-wrap" :class="{ 'input-error': form.errors.password }">
                    <i class="ri-lock-line input-icon"></i>
                    <input
                        id="password"
                        v-model="form.password"
                        :type="togglePassword ? 'text' : 'password'"
                        class="glass-input"
                        placeholder="Enter new password"
                        @input="handleInput('password')"
                    >
                    <button type="button" class="input-eye" @click="togglePassword = !togglePassword">
                        <i :class="togglePassword ? 'ri-eye-off-line' : 'ri-eye-line'"></i>
                    </button>
                </div>

                <!-- Confirm Password -->
                <label class="field-label" for="password_confirmation">Re-type New Password</label>
                <div class="input-wrap" :class="{ 'input-error': form.errors.password }">
                    <i class="ri-shield-keyhole-line input-icon"></i>
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        :type="togglePassword ? 'text' : 'password'"
                        class="glass-input"
                        placeholder="Confirm new password"
                        @input="handleInput('password')"
                    >
                </div>
                <div v-if="form.errors.password" class="field-error">{{ form.errors.password }}</div>

                <!-- Submit -->
                <button
                    type="submit"
                    class="submit-btn"
                    :disabled="form.processing"
                    :class="{ 'submit-btn-loading': form.processing }"
                >
                    <span v-if="!form.processing">Submit</span>
                    <span v-else>
                        <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                        Submitting...
                    </span>
                </button>

                <div class="logout-row">
                    Aren't ready yet?
                    <a class="logout-link" @click.prevent="logout">Logout</a>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';

const page = usePage();

const userName    = computed(() => page.props.user?.data?.name || '');
const userInitial = computed(() => (userName.value.charAt(0) || 'U').toUpperCase());
const userMeta    = computed(() => {
    const u = page.props.user?.data?.username || '';
    const e = page.props.user?.data?.email    || '';
    return [u, e].filter(Boolean).join(' | ');
});

const form = useForm({
    username:              page.props.user?.data?.username || '',
    password:              '',
    password_confirmation: '',
    is_active:             1,
    option:                'activation',
});

const create      = () => form.put('/activate', { errorBag: 'updatePassword', preserveScroll: true });
const handleInput = (field) => { form.errors[field] = false; };
const logout      = () => router.post('/logout');
</script>

<script>
export default {
    layout: null,
    data() {
        return { togglePassword: false };
    },
};
</script>

<style scoped>
.activation-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(160deg, #0d3b2e 0%, #1a5c48 50%, #0f4a38 100%);
    position: relative;
    overflow: hidden;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.deco {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.04);
    pointer-events: none;
}
.deco-1 { width: 300px; height: 300px; top: -90px;   left: -90px; }
.deco-2 { width: 220px; height: 220px; bottom: -70px; right: -70px; }
.deco-3 { width: 150px; height: 150px; top: 48%;     left: 80%; }

.activation-card {
    position: relative;
    z-index: 2;
    background: rgba(255, 255, 255, 0.07);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 24px;
    padding: 40px 38px 36px;
    width: 100%;
    max-width: 440px;
    margin: 20px;
}

/* ── Branding ── */
.brand {
    text-align: center;
    margin-bottom: 24px;
}

.logo-wrap {
    width: 90px;
    height: 90px;
    border-radius: 20px;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    overflow: hidden;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.28);
}

.logo-wrap img {
    width: 80px;
    height: 80px;
    object-fit: contain;
}

.brand-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 4px;
}

.brand-tag {
    font-size: 0.76rem;
    color: rgba(255, 255, 255, 0.45);
}

/* ── User info row ── */
.user-row {
    display: flex;
    align-items: center;
    gap: 14px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 14px;
    padding: 14px 18px;
    margin-bottom: 24px;
}

.user-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(145deg, #3dbf98, #2d9478);
    color: #fff;
    font-size: 1.1rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
}

.user-text {
    flex: 1;
    min-width: 0;
}

.user-name {
    font-size: 0.92rem;
    font-weight: 700;
    color: #fff;
}

.user-meta {
    font-size: 0.74rem;
    color: rgba(255, 255, 255, 0.5);
    margin-top: 2px;
}

/* ── Fields ── */
.field-label {
    display: block;
    font-size: 0.78rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.65);
    margin-bottom: 7px;
}

.input-wrap {
    position: relative;
    display: flex;
    align-items: center;
    margin-bottom: 18px;
}

.input-icon {
    position: absolute;
    left: 14px;
    color: rgba(255, 255, 255, 0.35);
    font-size: 1rem;
    pointer-events: none;
}

.glass-input {
    width: 100%;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 12px;
    padding: 13px 44px 13px 42px;
    font-size: 0.9rem;
    color: #fff;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.glass-input::placeholder {
    color: rgba(255, 255, 255, 0.28);
}

.glass-input:focus {
    border-color: rgba(61, 191, 152, 0.6);
    box-shadow: 0 0 0 3px rgba(61, 191, 152, 0.14);
}

.input-wrap.input-error .glass-input {
    border-color: rgba(239, 68, 68, 0.6);
}

.input-eye {
    position: absolute;
    right: 12px;
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.35);
    cursor: pointer;
    font-size: 1rem;
    padding: 4px;
    line-height: 1;
}

.input-eye:hover {
    color: rgba(255, 255, 255, 0.7);
}

.field-error {
    font-size: 0.76rem;
    color: #f87171;
    margin-top: -12px;
    margin-bottom: 14px;
    padding-left: 4px;
}

/* ── Submit button ── */
.submit-btn {
    display: block;
    width: 100%;
    padding: 13px;
    background: linear-gradient(135deg, #3dbf98 0%, #2a9478 100%);
    color: #fff;
    border: none;
    border-radius: 12px;
    font-size: 0.95rem;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 8px 22px rgba(0, 0, 0, 0.28);
    margin-bottom: 20px;
    transition: transform 0.15s, box-shadow 0.15s, opacity 0.2s;
}

.submit-btn:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 10px 26px rgba(0, 0, 0, 0.35);
}

.submit-btn:disabled,
.submit-btn-loading {
    opacity: 0.65;
    cursor: not-allowed;
}

/* ── Logout row ── */
.logout-row {
    text-align: center;
    font-size: 0.82rem;
    color: rgba(255, 255, 255, 0.45);
}

.logout-link {
    color: #f87171;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    margin-left: 4px;
}

.logout-link:hover {
    color: #fca5a5;
}

@media (max-width: 480px) {
    .activation-card {
        padding: 32px 24px 28px;
    }
}
</style>

<style>
.glass-input:-webkit-autofill,
.glass-input:-webkit-autofill:hover,
.glass-input:-webkit-autofill:focus,
.glass-input:-webkit-autofill:active {
    -webkit-box-shadow: 0 0 0 1000px rgba(20, 75, 58, 0.95) inset !important;
    -webkit-text-fill-color: #fff !important;
    caret-color: #fff;
    transition: background-color 5000s ease-in-out 0s;
}
</style>
