<template>
    <Head title="Activation" />

    <div class="auth-page-wrapper pt-5 d-flex justify-content-center align-items-center min-vh-100">
        <div class="auth-page-content">
            <BContainer>
                <BRow class="justify-content-center">
                    <BCol md="8" lg="6" xl="5">
                        <BCard no-body class="mt-4 login-card">
                            <div class="card-header-bg"></div>

                            <BCardBody class="p-4">
                                <div class="logo-section text-center mb-4">
                                    <div class="logo-container mb-3">
                                        <img src="@assets/images/logo-sm.png" alt="" style="max-width: 100%; height: auto;">
                                    </div>
                                    <h3 class="brand-name">BRT Accounting Software</h3>
                                    <p class="brand-tagline">Set up your account to continue</p>
                                </div>

                                <div class="text-center mb-4">
                                    <h5 class="welcome-name mb-1">{{ $page.props.user.data.name }}</h5>
                                    <p class="welcome-meta mb-0">{{ $page.props.user.data.username }} | {{ $page.props.user.data.email }}</p>
                                </div>

                                <div class="p-2">
                                    <form class="customform" @submit.prevent="create">
                                        <div class="form-group mb-3">
                                            <InputLabel for="username" value="Username" class="form-label" />
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="ri-user-line"></i>
                                                </span>
                                                <TextInput id="username" v-model="form.username" type="text" class="form-control" placeholder="Enter username" @input="handleInput('username')" :class="{ 'is-invalid': form.errors.username }" />
                                            </div>
                                            <InputError :message="form.errors.username" />
                                        </div>

                                        <div class="form-group mb-3">
                                            <InputLabel for="password" value="New Password" class="form-label" />
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="ri-lock-line"></i>
                                                </span>
                                                <input :type="togglePassword ? 'text' : 'password'" id="password" v-model="form.password" class="form-control" placeholder="Enter new password" @input="handleInput('password')" :class="{ 'is-invalid': form.errors.password }" />
                                                <BButton variant="link" class="password-toggle" type="button" @click="togglePassword = !togglePassword" style="width: auto!important;">
                                                    <i :class="togglePassword ? 'ri-eye-off-line' : 'ri-eye-line'"></i>
                                                </BButton>
                                            </div>
                                        </div>

                                        <div class="form-group mb-4">
                                            <InputLabel for="password_confirmation" value="Re-type New Password" class="form-label" />
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="ri-shield-keyhole-line"></i>
                                                </span>
                                                <input :type="togglePassword ? 'text' : 'password'" id="password_confirmation" v-model="form.password_confirmation" class="form-control" placeholder="Confirm new password" @input="handleInput('password')" :class="{ 'is-invalid': form.errors.password }" />
                                            </div>
                                            <InputError :message="form.errors.password" />
                                        </div>

                                        <div class="mb-4">
                                            <BButton variant="primary" class="w-100 login-btn" type="submit" :class="{ 'opacity-25': form.processing }" :disabled="form.processing" style="justify-content: center!important;">
                                                <span v-if="!form.processing">Submit</span>
                                                <span v-else>
                                                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                                    Submitting...
                                                </span>
                                            </BButton>
                                        </div>

                                        <div class="text-center">
                                            <p class="mb-0">Aren't ready yet?
                                                <a style="cursor: pointer;" @click.prevent="logout" class="fw-semibold text-danger">Logout</a>
                                            </p>
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
<script>
import { useForm } from '@inertiajs/vue3'
import InputError from '@/Shared/Components/Forms/InputError.vue';
import InputLabel from '@/Shared/Components/Forms/InputLabel.vue';
import TextInput from '@/Shared/Components/Forms/TextInput.vue';
export default {
    layout: null,
    components : { InputError, InputLabel, TextInput },
    data(){
        return {
            form: useForm({
                username: this.$page.props.user.data.username,
                password: '',
                password_confirmation: '',
                is_active: 1,
                option: 'activation'
            }),
            togglePassword: false
        }
    },
    methods: {
        create(){
            this.form.put('/activate',{
                errorBag: 'updatePassword',
                preserveScroll: true,
            });
        },
        handleInput(field) {
            this.form.errors[field] = false;
        }
    }
}
</script>
<script setup>
import { router } from '@inertiajs/vue3';
    const logout = () => {
        router.post('/logout');
    };
</script>
<style scoped>
.auth-page-wrapper {
    background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
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

.brand-name {
    color: #2c3e50;
    font-weight: 700;
    margin-bottom: 5px;
}

.brand-tagline {
    color: #7f8c8d;
    font-size: 0.9rem;
}

.welcome-name {
    color: #2c3e50;
    font-weight: 600;
}

.welcome-meta {
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

.form-control::placeholder {
    font-size: 1rem;
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

@media (max-width: 576px) {
    .login-card {
        border-radius: 15px;
    }

    .brand-name {
        font-size: 1.5rem;
    }
}
</style>
