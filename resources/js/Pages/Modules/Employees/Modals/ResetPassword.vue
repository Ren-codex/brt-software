<template>
    <div v-if="showModal" class="reset-modal-overlay" @click.self="close">
        <div class="reset-modal-card">
            <div class="reset-modal-header">
                <h3>
                    <i class="ri-lock-password-line"></i>
                    Reset Password
                </h3>
                <button type="button" class="reset-modal-close" @click="close">
                    <i class="ri-close-line"></i>
                </button>
            </div>

            <form class="reset-modal-body" @submit.prevent="submit">
                <div class="reset-form-group">
                    <label>New Password</label>
                    <div class="reset-input-wrap">
                        <input
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            :class="{ error: form.errors.password }"
                            placeholder="Enter new password"
                            @input="validatePassword"
                        >
                        <button type="button" class="reset-toggle" @click="showPassword = !showPassword">
                            <i :class="showPassword ? 'ri-eye-off-line' : 'ri-eye-line'"></i>
                        </button>
                    </div>
                    <span class="reset-error" v-if="form.errors.password">{{ form.errors.password }}</span>
                </div>

                <div class="reset-form-group">
                    <label>Confirm Password</label>
                    <div class="reset-input-wrap">
                        <input
                            v-model="form.confirm_password"
                            :type="showConfirmPassword ? 'text' : 'password'"
                            :class="{ error: form.errors.confirm_password || passwordMismatch }"
                            placeholder="Confirm password"
                            @input="validatePassword"
                        >
                        <button type="button" class="reset-toggle" @click="showConfirmPassword = !showConfirmPassword">
                            <i :class="showConfirmPassword ? 'ri-eye-off-line' : 'ri-eye-line'"></i>
                        </button>
                    </div>
                    <span class="reset-error" v-if="form.errors.confirm_password">{{ form.errors.confirm_password }}</span>
                    <span class="reset-error" v-else-if="passwordMismatch">Passwords do not match.</span>
                </div>

                <ul class="password-rules">
                    <li :class="{ invalid: !passwordValid.minLength }">At least 8 characters</li>
                    <li :class="{ invalid: !passwordValid.capital }">At least 1 uppercase letter</li>
                    <li :class="{ invalid: !passwordValid.number }">At least 1 number</li>
                    <li :class="{ invalid: !passwordValid.special }">At least 1 special character (@$!%*?&)</li>
                </ul>

                <div class="reset-modal-actions">
                    <BButton type="button" variant="light" @click="close">Cancel</BButton>
                    <BButton
                        type="submit"
                        variant="primary"
                        :disabled="form.processing || passwordMismatch || !isPasswordValid"
                    >
                        <i :class="form.processing ? 'ri-loader-4-line spinning' : 'ri-lock-unlock-line'"></i>
                        <span>{{ form.processing ? 'Resetting...' : 'Reset Password' }}</span>
                    </BButton>
                </div>
            </form>
        </div>
    </div>
</template>

<script>
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

export default {
    emits: ['updated'],
    data() {
        return {
            showModal: false,
            showPassword: false,
            showConfirmPassword: false,
            passwordMismatch: false,
            passwordValid: {
                minLength: false,
                capital: false,
                number: false,
                special: false,
            },
            form: useForm({
                id: null,
                password: '',
                confirm_password: '',
                option: 'reset_password',
            }),
        };
    },
    computed: {
        isPasswordValid() {
            return Object.values(this.passwordValid).every(Boolean);
        },
    },
    methods: {
        open(employeeData) {
            const user = employeeData?.user || employeeData;
            if (!user?.id) return;

            this.form.reset();
            this.form.clearErrors();
            this.form.id = user.id;
            this.form.option = 'reset_password';
            this.passwordMismatch = false;
            this.passwordValid = {
                minLength: false,
                capital: false,
                number: false,
                special: false,
            };
            this.showPassword = false;
            this.showConfirmPassword = false;
            this.showModal = true;
        },
        close() {
            this.showModal = false;
            this.form.clearErrors();
        },
        validatePassword() {
            const password = this.form.password || '';
            const confirm = this.form.confirm_password || '';

            this.passwordValid.minLength = password.length >= 8;
            this.passwordValid.capital = /[A-Z]/.test(password);
            this.passwordValid.number = /\d/.test(password);
            this.passwordValid.special = /[@$!%*?&]/.test(password);

            this.passwordMismatch = !!(password && confirm && password !== confirm);
        },
        submit() {
            this.validatePassword();
            if (this.passwordMismatch || !this.isPasswordValid || !this.form.id) {
                return;
            }

            this.form.put(`/users/${this.form.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    const message = 'Password has been reset successfully.';
                    if (this.$toast?.success) {
                        this.$toast.success(message);
                    } else {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: message,
                            timer: 1800,
                            showConfirmButton: false,
                        });
                    }
                    this.close();
                    this.$emit('updated');
                },
                onError: (errors) => {
                    const message = errors?.password || errors?.confirm_password || 'Failed to reset password.';
                    if (this.$toast?.error) {
                        this.$toast.error(Array.isArray(message) ? message[0] : message);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: Array.isArray(message) ? message[0] : message,
                        });
                    }
                },
            });
        },
    },
};
</script>

<style scoped>
.reset-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 1200;
    background: rgba(7, 22, 19, 0.55);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 14px;
}

.reset-modal-card {
    width: min(520px, 100%);
    border-radius: 16px;
    border: 1px solid #d4e4df;
    background: #fff;
    box-shadow: 0 20px 50px rgba(10, 35, 30, 0.22);
    overflow: hidden;
}

.reset-modal-header {
    padding: 14px 16px;
    border-bottom: 1px solid #e1ece9;
    background: #f4fbf8;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.reset-modal-header h3 {
    margin: 0;
    font-size: 0.98rem;
    color: #102723;
    display: inline-flex;
    align-items: center;
    gap: 7px;
}

.reset-modal-close {
    border: 0;
    background: transparent;
    width: 30px;
    height: 30px;
    border-radius: 8px;
    color: #35524d;
    cursor: pointer;
}

.reset-modal-close:hover {
    background: #e9f3ef;
}

.reset-modal-body {
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.reset-form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.reset-form-group label {
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #35524d;
}

.reset-input-wrap {
    position: relative;
}

.reset-input-wrap input {
    width: 100%;
    min-height: 40px;
    border: 1px solid #cfded9;
    border-radius: 10px;
    background: #fbfefd;
    padding: 0 40px 0 11px;
    font-size: 0.88rem;
}

.reset-input-wrap input.error {
    border-color: #e39b92;
    background: #fff4f2;
}

.reset-toggle {
    position: absolute;
    top: 50%;
    right: 9px;
    transform: translateY(-50%);
    border: 0;
    background: transparent;
    color: #5c7974;
    width: 26px;
    height: 26px;
    border-radius: 6px;
}

.reset-toggle:hover {
    background: #edf5f2;
}

.reset-error {
    font-size: 0.75rem;
    color: #c44f47;
}

.password-rules {
    margin: 0;
    padding-left: 18px;
    color: #1f7d66;
    font-size: 0.76rem;
    line-height: 1.5;
}

.password-rules .invalid {
    color: #c44f47;
}

.reset-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 4px;
}

.spinning {
    animation: spin 0.85s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>
