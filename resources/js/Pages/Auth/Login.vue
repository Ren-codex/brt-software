<template>
    <Head title="Log in" />
    <div class="login-page">
        <div class="deco deco-1"></div>
        <div class="deco deco-2"></div>
        <div class="deco deco-3"></div>

        <div class="login-card">
            <!-- Branding -->
            <div class="brand">
                <div class="logo-wrap">
                    <img src="@assets/images/logo-sm.png" alt="BRT Logo">
                </div>
                <div class="brand-name">BRT Accounting Software</div>
                <div class="brand-tag">Business Information Management System</div>
            </div>

            <!-- Status / Error alerts -->
            <div v-if="status" class="login-alert login-alert-success">
                <i class="ri-checkbox-circle-line"></i> {{ status }}
            </div>
            <div v-if="form.errors.email || form.errors.password" class="login-alert login-alert-error">
                <i class="ri-error-warning-line"></i> Incorrect email or password.
            </div>

            <form @submit.prevent="submit">
                <!-- Username / Email -->
                <label class="field-label" for="email">Username or Email</label>
                <div class="input-wrap" :class="{ 'input-error': form.errors.email }">
                    <i class="ri-user-line input-icon"></i>
                    <input
                        id="email"
                        v-model="form.email"
                        type="text"
                        class="glass-input"
                        placeholder="Enter username or email address"
                        autocomplete="username"
                        autofocus
                        required
                    >
                </div>

                <!-- Password -->
                <label class="field-label" for="password">Password</label>
                <div class="input-wrap" :class="{ 'input-error': form.errors.password }">
                    <i class="ri-lock-line input-icon"></i>
                    <input
                        id="password"
                        v-model="form.password"
                        :type="togglePassword ? 'text' : 'password'"
                        class="glass-input"
                        placeholder="Enter password"
                        autocomplete="current-password"
                        required
                    >
                    <button type="button" class="input-eye" @click="togglePassword = !togglePassword">
                        <i :class="togglePassword ? 'ri-eye-off-line' : 'ri-eye-line'"></i>
                    </button>
                </div>

                <!-- Remember me -->
                <div class="remember-row">
                    <Checkbox v-model:checked="form.remember" name="remember" id="auth-remember-check" class="remember-check" />
                    <label class="remember-label" for="auth-remember-check">Remember me</label>
                </div>

                <!-- Submit -->
                <button
                    type="submit"
                    class="signin-btn"
                    :disabled="form.processing"
                    :class="{ 'signin-btn-loading': form.processing }"
                >
                    <span v-if="!form.processing">Sign In</span>
                    <span v-else>
                        <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                        Signing In...
                    </span>
                </button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import Checkbox from '@/Shared/Components/Forms/Checkbox.vue';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.transform(data => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<script>
export default {
    layout: null,
    data() {
        return {
            togglePassword: false,
        };
    },
};
</script>

<style scoped>
.login-page {
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
.deco-1 { width: 300px; height: 300px; top: -90px;  left: -90px; }
.deco-2 { width: 220px; height: 220px; bottom: -70px; right: -70px; }
.deco-3 { width: 150px; height: 150px; top: 48%;    left: 80%; }

.login-card {
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
    margin-bottom: 28px;
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

/* ── Alerts ── */
.login-alert {
    display: flex;
    align-items: center;
    gap: 8px;
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 0.8rem;
    margin-bottom: 18px;
}

.login-alert-error {
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #fca5a5;
}

.login-alert-success {
    background: rgba(61, 191, 152, 0.15);
    border: 1px solid rgba(61, 191, 152, 0.3);
    color: #6ee7c7;
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

/* ── Remember me ── */
.remember-row {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-bottom: 22px;
}

.remember-check {
    accent-color: #3dbf98;
    width: 15px;
    height: 15px;
    cursor: pointer;
}

.remember-label {
    font-size: 0.82rem;
    color: rgba(255, 255, 255, 0.5);
    cursor: pointer;
}

/* ── Sign In button ── */
.signin-btn {
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
    transition: transform 0.15s, box-shadow 0.15s, opacity 0.2s;
}

.signin-btn:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 10px 26px rgba(0, 0, 0, 0.35);
}

.signin-btn:disabled,
.signin-btn-loading {
    opacity: 0.65;
    cursor: not-allowed;
}

@media (max-width: 480px) {
    .login-card {
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
