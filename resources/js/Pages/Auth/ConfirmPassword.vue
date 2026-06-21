<template>
    <Head title="Secure Area"/>
    <div class="lock-page">
        <div class="lock-deco lock-deco-1"></div>
        <div class="lock-deco lock-deco-2"></div>
        <div class="lock-deco lock-deco-3"></div>

        <div class="lock-card">
            <div class="lock-icon-badge">
                <i class="ri-lock-line"></i>
            </div>
            <div class="lock-title">Lock Screen</div>
            <div class="lock-sub">This is a secure area. Please confirm your identity.</div>

            <div class="lock-avatar-row">
                <div class="lock-avatar-circle">
                    {{ ($page.props.user?.name || $page.props.user?.data?.name || 'U').charAt(0).toUpperCase() }}
                </div>
                <div class="lock-user-text">
                    <div class="lock-user-name">{{ $page.props.user?.name || $page.props.user?.data?.name }}</div>
                    <div class="lock-user-role">{{ $page.props.user?.data?.roles?.[0]?.name || $page.props.user?.data?.role?.name || '' }}</div>
                </div>
            </div>

            <form @submit.prevent="submit">
                <input type="text" autocomplete="username" style="display:none;">

                <div class="lock-input-wrap" :class="{ 'has-error': form.errors.password }">
                    <i class="ri-lock-password-line lock-input-icon"></i>
                    <input
                        ref="passwordInput"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        class="lock-input"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        autofocus
                        required
                    >
                    <button type="button" class="lock-eye" @click="showPassword = !showPassword">
                        <i :class="showPassword ? 'ri-eye-off-line' : 'ri-eye-line'"></i>
                    </button>
                </div>
                <div v-if="form.errors.password" class="lock-error">{{ form.errors.password }}</div>

                <button
                    type="submit"
                    class="lock-btn"
                    :disabled="form.processing"
                    :class="{ 'lock-btn-loading': form.processing }"
                >
                    {{ form.processing ? 'Verifying...' : 'Unlock' }}
                </button>
            </form>

            <div class="lock-signin">
                Not you? return <Link href="/login">Signin</Link>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const showPassword = ref(false);
const passwordInput = ref(null);
const form = useForm({ password: '' });

const submit = () => {
    form.post('/confirm-password', {
        onFinish: () => form.reset(),
    });
};
</script>

<script>
export default {
    layout: null,
};
</script>

<style scoped>
.lock-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(160deg, #0d3b2e 0%, #1a5c48 50%, #0f4a38 100%);
    position: relative;
    overflow: hidden;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.lock-deco {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.04);
    pointer-events: none;
}
.lock-deco-1 { width: 260px; height: 260px; top: -80px; left: -80px; }
.lock-deco-2 { width: 200px; height: 200px; bottom: -60px; right: -60px; }
.lock-deco-3 { width: 130px; height: 130px; top: 50%; left: 82%; }

.lock-card {
    position: relative;
    z-index: 2;
    background: rgba(255, 255, 255, 0.07);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 24px;
    padding: 40px 36px 36px;
    width: 100%;
    max-width: 420px;
    margin: 20px;
    text-align: center;
}

.lock-icon-badge {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.14);
    border: 1px solid rgba(255, 255, 255, 0.22);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 18px;
    font-size: 1.7rem;
    color: #a8d5c5;
}

.lock-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 6px;
}

.lock-sub {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    margin-bottom: 26px;
    line-height: 1.5;
}

.lock-avatar-row {
    display: flex;
    align-items: center;
    gap: 14px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 14px;
    padding: 14px 18px;
    margin-bottom: 22px;
    text-align: left;
}

.lock-avatar-circle {
    width: 46px;
    height: 46px;
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

.lock-user-text {
    flex: 1;
    min-width: 0;
}

.lock-user-name {
    font-size: 0.92rem;
    font-weight: 700;
    color: #fff;
}

.lock-user-role {
    font-size: 0.74rem;
    color: rgba(255, 255, 255, 0.5);
    margin-top: 2px;
}

.lock-input-wrap {
    position: relative;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
}

.lock-input-icon {
    position: absolute;
    left: 14px;
    color: rgba(255, 255, 255, 0.4);
    font-size: 1rem;
    pointer-events: none;
}

.lock-input {
    width: 100%;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    padding: 13px 44px 13px 42px;
    font-size: 0.9rem;
    color: #fff;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.lock-input::placeholder {
    color: rgba(255, 255, 255, 0.3);
}

.lock-input:focus {
    border-color: rgba(61, 191, 152, 0.6);
    box-shadow: 0 0 0 3px rgba(61, 191, 152, 0.15);
}

.lock-input-wrap.has-error .lock-input {
    border-color: rgba(239, 100, 100, 0.7);
}

.lock-eye {
    position: absolute;
    right: 12px;
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.4);
    cursor: pointer;
    font-size: 1rem;
    padding: 4px;
    line-height: 1;
}

.lock-eye:hover {
    color: rgba(255, 255, 255, 0.75);
}

.lock-error {
    font-size: 0.78rem;
    color: #f87171;
    margin-top: -10px;
    margin-bottom: 14px;
    text-align: left;
    padding-left: 4px;
}

.lock-btn {
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
    transition: opacity 0.2s, transform 0.15s;
}

.lock-btn:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 10px 26px rgba(0, 0, 0, 0.35);
}

.lock-btn:disabled,
.lock-btn-loading {
    opacity: 0.65;
    cursor: not-allowed;
}

.lock-signin {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.45);
}

.lock-signin a {
    color: #6dd5b5;
    font-weight: 600;
    text-decoration: none;
}

.lock-signin a:hover {
    color: #a8e8d5;
}
</style>
