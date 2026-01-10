<template>
    <Head title="Log in" />
    <div class="auth-page-wrapper pt-5 d-flex justify-content-center align-items-center min-vh-100">
        <!-- Background Animation -->
        <div class="background-animation">
            <div class="floating-grain"></div>
            <div class="floating-grain"></div>
            <div class="floating-grain"></div>
            <div class="floating-grain"></div>
            <div class="floating-grain"></div>
        </div>
        
        <div class="auth-page-content">
            <BContainer>
                <BRow class="justify-content-center">
                    <BCol md="8" lg="6" xl="5">
                        <BCard no-body class="mt-4 login-card">
                            <!-- Card Header Accent -->
                            <div class="card-header-bg"></div>
                            
                            <BCardBody class="p-4">
                                <!-- Logo Section -->
                                <div class="logo-section text-center mb-4">
                                    <div class="logo-container mb-3">
                                         <img src="@assets/images/logo-sm.png" alt=""  style=" max-width: 100%; height: auto;">
                                    </div>
                                    <h3 class="brand-name">BRT Accounting Software</h3>
                                    <p class="brand-tagline">Business Information Management System</p>
                                </div>

                                <!-- Status Alert -->
                                <div v-if="status" class="alert alert-success text-success">
                                    {{ status }}
                                </div>

                                <!-- Error Alert -->
                                <div v-if="form.errors.email || form.errors.password" class="alert alert-danger">
                                    Incorrect email or password.
                                </div>

                                <!-- Login Form -->
                                <div class="p-2 mt-3">
                                    <form class="customform" @submit.prevent="submit">
                                        <!-- Email Field -->
                                        <div class="form-group mb-3">
                                            <InputLabel for="email" value="Email" class="form-label" />
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="ri-mail-line"></i>
                                                </span>
                                                <TextInput 
                                                    id="email" 
                                                    v-model="form.email" 
                                                    type="email" 
                                                    class="form-control" 
                                                    autofocus 
                                                    placeholder="Please enter email" 
                                                    autocomplete="email" 
                                                    required 
                                                    :class="{ 'is-invalid': form.errors.email }" 
                                                />
                                            </div>
                                            <InputError :message="form.errors.email" />
                                        </div>

                                        <!-- Password Field -->
                                        <div class="form-group mb-3">
                                            <InputLabel for="password" value="Password" class="form-label" />
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="ri-lock-line"></i>
                                                </span>
                                                <input 
                                                    :type="togglePassword ? 'text' : 'password'" 
                                                    class="form-control" 
                                                    placeholder="Enter password" 
                                                    id="password-input" 
                                                    v-model="form.password" 
                                                    autocomplete="password" 
                                                    required 
                                                    :class="{ 'is-invalid': form.errors.password }"
                                                />
                                                <BButton 
                                                    variant="link" 
                                                    class="password-toggle" 
                                                    type="button" 
                                                    @click="togglePassword = !togglePassword"
                                                    style="width: auto!important;"
                                                >
                                                    <i :class="togglePassword ? 'ri-eye-off-line' : 'ri-eye-line'"></i>
                                                </BButton>
                                            </div>
                                            <InputError :message="form.errors.password" />
                                        </div>

                                        <!-- Remember Me & Forgot Password -->
                                        <div class="form-options d-flex justify-content-between align-items-center mb-4">
                                            <div class="form-check">
                                                <Checkbox 
                                                    v-model:checked="form.remember" 
                                                    name="remember" 
                                                    class="form-check-input" 
                                                    id="auth-remember-check" 
                                                />
                                                <label class="form-check-label" for="auth-remember-check">
                                                    Remember me
                                                </label>
                                            </div>
                                            <!-- <div>
                                                <Link href="/forgot-password" class="forgot-password-link">
                                                    Forgot Password?
                                                </Link>
                                            </div> -->
                                        </div>

                                        <!-- Submit Button -->
                                        <div class="mb-4">
                                            <BButton 
                                                variant="primary" 
                                                class="w-100 login-btn" 
                                                type="submit" 
                                                :class="{ 'opacity-25': form.processing }" 
                                                :disabled="form.processing"
                                                style="justify-content: center!important;"
                                            >
                                                <span v-if="!form.processing">Sign In</span>
                                                <span v-else>
                                                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                                    Signing In...
                                                </span>
                                            </BButton>
                                        </div>
                                    </form>
                                </div>
                            </BCardBody>
                        </BCard>
                    </BCol>
                </BRow>
            </BContainer>
        </div>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import Checkbox from '@/Shared/Components/Forms/Checkbox.vue';
import InputError from '@/Shared/Components/Forms/InputError.vue';
import InputLabel from '@/Shared/Components/Forms/InputLabel.vue';
import TextInput from '@/Shared/Components/Forms/TextInput.vue';

defineProps({
    canResetPassword: Boolean,
    status: String
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
            togglePassword: false
        }
    },
    methods: {
        openRegister(){
            this.$refs.register.show();
        }
    }
}
</script>

<style scoped>
.auth-page-wrapper {
    background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
    position: relative;
    overflow: hidden;
}

.auth-page-content {
    position: relative;
    z-index: 2;
}

.login-card {
    border: none;
    border-radius: 20px;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.card-header-bg {
    height: 6px;
    background: linear-gradient(90deg, #2e8b57 0%, #1f6b41 100%);
}

.logo-container {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 100px;
    height: 100px;
    border-radius: 50%;
    box-shadow: 0 8px 20px rgba(46, 139, 87, 0.3);
}

.logo-icon {
    font-size: 32px;
    color: white;
}

.brand-name {
    color: #2c3e50;
    font-weight: 700;
    margin-bottom: 5px;
}

.brand-tagline {
    color: #7f8c8d;
    font-size: 0.9rem;
}

.form-label {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 8px;
}

.input-group {
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid #e9ecef;
    transition: all 0.3s;
}

.input-group:focus-within {
    border-color: #2e8b57;
    box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.2);
}

.input-group-text {
    background-color: #f8f9fa;
    border: none;
    color: #7f8c8d;
}

.form-control {
    border: none;
    padding: 12px 15px;
}

.form-control:focus {
    box-shadow: none;
    outline: none;
}

.password-toggle {
    background: none;
    border: none;
    color: #7f8c8d;
    transition: color 0.3s;
}

.password-toggle:hover {
    color: #2e8b57;
}

.login-btn {
    background: linear-gradient(135deg, #2e8b57 0%, #1f6b41 100%);
    border: none;
    padding: 12px;
    font-weight: 600;
    border-radius: 10px;
    transition: all 0.3s;
    box-shadow: 0 6px 15px rgba(46, 139, 87, 0.3);
}

.login-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(46, 139, 87, 0.4);
}

.forgot-password-link {
    color: #2e8b57;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.3s;
}

.forgot-password-link:hover {
    color: #1f6b41;
}

.form-check-input:checked {
    background-color: #2e8b57;
    border-color: #2e8b57;
}

/* Background Animation */
.background-animation {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    overflow: hidden;
    z-index: 1;
}

.floating-grain {
    position: absolute;
    background: rgba(46, 139, 87, 0.1);
    border-radius: 50%;
    animation: float 15s infinite linear;
}

.floating-grain:nth-child(1) {
    width: 80px;
    height: 80px;
    top: 10%;
    left: 5%;
    animation-delay: 0s;
    animation-duration: 20s;
}

.floating-grain:nth-child(2) {
    width: 60px;
    height: 60px;
    top: 70%;
    left: 10%;
    animation-delay: 2s;
    animation-duration: 18s;
}

.floating-grain:nth-child(3) {
    width: 100px;
    height: 100px;
    top: 40%;
    left: 80%;
    animation-delay: 4s;
    animation-duration: 22s;
}

.floating-grain:nth-child(4) {
    width: 50px;
    height: 50px;
    top: 20%;
    left: 70%;
    animation-delay: 1s;
    animation-duration: 16s;
}

.floating-grain:nth-child(5) {
    width: 70px;
    height: 70px;
    top: 60%;
    left: 85%;
    animation-delay: 3s;
    animation-duration: 19s;
}

@keyframes float {
    0% {
        transform: translateY(0) rotate(0deg);
        opacity: 0.7;
    }
    50% {
        transform: translateY(-20px) rotate(180deg);
        opacity: 0.9;
    }
    100% {
        transform: translateY(0) rotate(360deg);
        opacity: 0.7;
    }
}

/* Responsive Design */
@media (max-width: 576px) {
    .login-card {
        border-radius: 15px;
    }
    
    .brand-name {
        font-size: 1.5rem;
    }
    
    .form-options {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
}
</style>