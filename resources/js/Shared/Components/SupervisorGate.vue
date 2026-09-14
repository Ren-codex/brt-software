<template>
    <div class="supervisor-gate" :class="{ authorized: !!token }">
        <template v-if="!token">
            <p class="supervisor-gate-lead">
                <i class="ri-shield-user-line"></i>
                {{ prompt }}
            </p>

            <div class="supervisor-gate-fields">
                <input
                    v-model.trim="username"
                    type="text"
                    class="form-control"
                    placeholder="Username"
                    autocomplete="off"
                    :disabled="checking"
                    @keyup.enter="authorize"
                />
                <input
                    v-model="password"
                    type="password"
                    class="form-control"
                    placeholder="Password"
                    autocomplete="off"
                    :disabled="checking"
                    @keyup.enter="authorize"
                />
                <button type="button" class="btn btn-primary" :disabled="!canSubmit" @click="authorize">
                    <i v-if="checking" class="ri-loader-4-line spin"></i>
                    <span v-else>Authorize</span>
                </button>
            </div>

            <small v-if="error" class="supervisor-gate-error">{{ error }}</small>
        </template>

        <p v-else class="supervisor-gate-done">
            <i class="ri-shield-check-line"></i>
            Authorized by <strong>{{ authorizedAs }}</strong>. This approval covers this one action.
        </p>
    </div>
</template>

<script>
import axios from 'axios';

/**
 * An administrator authorises a sensitive action with their own credentials.
 *
 * The password goes only to /supervisor-authorization and is never held after
 * the exchange: what comes back is a single-use token for one action, which the
 * surrounding form submits. Nothing here decides whether the credentials are
 * good — the server does, and the action is refused without a token it issued.
 */
export default {
    name: 'SupervisorGate',
    props: {
        /** Which action to authorise, e.g. 'sales.cancel_order'. */
        action: { type: String, required: true },
        prompt: {
            type: String,
            default: 'Someone authorized for this must approve it. Ask them to enter their credentials.',
        },
    },
    emits: ['update:token'],
    data() {
        return { username: '', password: '', token: '', authorizedAs: '', error: '', checking: false };
    },
    computed: {
        canSubmit() {
            return this.username && this.password && !this.checking;
        },
    },
    methods: {
        authorize() {
            if (!this.canSubmit) return;

            this.checking = true;
            this.error = '';

            axios.post('/supervisor-authorization', {
                username: this.username,
                password: this.password,
                action: this.action,
            })
                .then(({ data }) => {
                    this.token = data.token;
                    this.authorizedAs = this.username;
                    this.$emit('update:token', this.token);
                })
                .catch((err) => {
                    const errors = err.response?.data?.errors;
                    this.error = errors ? Object.values(errors).flat()[0] : 'Could not authorize. Try again.';
                })
                .finally(() => {
                    // Never keep the password around, whatever the outcome.
                    this.password = '';
                    this.checking = false;
                });
        },
        reset() {
            Object.assign(this, { username: '', password: '', token: '', authorizedAs: '', error: '', checking: false });
            this.$emit('update:token', '');
        },
    },
};
</script>

<style scoped>
.supervisor-gate {
    margin-top: 1rem;
    padding: 0.9rem 1rem;
    border: 1px solid #d8e6e1;
    border-radius: 10px;
    background: #f7fbfa;
}
.supervisor-gate.authorized {
    border-color: #a8dcc8;
    background: #e9f5f0;
}

.supervisor-gate-lead {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    margin: 0 0 0.7rem;
    font-size: 0.82rem;
    color: #33564f;
}
.supervisor-gate-lead i { color: #3d8d7a; margin-top: 1px; }

.supervisor-gate-fields {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: 0.5rem;
}
@media (max-width: 560px) {
    .supervisor-gate-fields { grid-template-columns: 1fr; }
}

.supervisor-gate-error {
    display: block;
    margin-top: 0.45rem;
    color: #96231f;
    font-size: 0.78rem;
}

.supervisor-gate-done {
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 0;
    font-size: 0.82rem;
    color: #1b6b4a;
}

.spin { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
