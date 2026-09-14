<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="modal-overlay modal-overlay-stacked active"
            @click.self="cancel"
        >
            <div class="modal-container" style="max-width: 440px" @click.stop>
                <div class="modal-header">
                    <div class="modal-header-icon"><i class="ri-shield-user-line"></i></div>
                    <div>
                        <h5 class="modal-title">Authorization required</h5>
                        <p class="modal-subtitle">Covers this one action only.</p>
                    </div>
                    <button type="button" class="close-btn ms-auto" @click="cancel">
                        <i class="ri-close-line"></i>
                    </button>
                </div>

                <div class="modal-body">
                    <p class="gate-lead">{{ prompt }}</p>

                    <label class="form-label" :for="usernameId">Username or email</label>
                    <input
                        :id="usernameId"
                        ref="usernameField"
                        v-model.trim="username"
                        type="text"
                        class="form-control mb-2"
                        autocomplete="off"
                        :disabled="checking"
                        @keyup.enter="authorize"
                    />

                    <label class="form-label" :for="passwordId">Password</label>
                    <input
                        :id="passwordId"
                        v-model="password"
                        type="password"
                        class="form-control"
                        autocomplete="off"
                        :disabled="checking"
                        @keyup.enter="authorize"
                    />

                    <p v-if="error" class="gate-error">{{ error }}</p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" :disabled="checking" @click="cancel">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-primary" :disabled="!canSubmit" @click="authorize">
                        <i v-if="checking" class="ri-loader-4-line spin me-1"></i>
                        {{ checking ? 'Checking...' : 'Authorize' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script>
import axios from 'axios';

/**
 * Someone authorised for a sensitive action approves it with their own
 * credentials, in a popup over whatever asked for it.
 *
 * The password goes only to /supervisor-authorization and is never held after
 * the exchange: what comes back is a single-use token for one action, which the
 * caller submits with its request. Nothing here decides whether the credentials
 * are good -- the server does, and the action is refused without a token it
 * issued.
 *
 * The caller opens this instead of performing the action, and performs it on
 * @authorized. Closing it must therefore leave the action undone: a cancel that
 * fell through to the action would hand out exactly the override the gate
 * exists to withhold.
 */
let gateSeq = 0;

export default {
    name: 'SupervisorGate',
    props: {
        /** Which action to authorise, e.g. 'sales.credit_sale'. */
        action: { type: String, required: true },
        prompt: {
            type: String,
            default: 'Someone authorized for this must approve it.',
        },
    },
    emits: ['authorized', 'cancelled'],
    data() {
        gateSeq += 1;

        return {
            open: false,
            username: '',
            password: '',
            error: '',
            checking: false,
            uid: gateSeq,
        };
    },
    computed: {
        canSubmit() {
            return !!this.username && !!this.password && !this.checking;
        },
        usernameId() {
            return `supervisor-gate-username-${this.uid}`;
        },
        passwordId() {
            return `supervisor-gate-password-${this.uid}`;
        },
    },
    watch: {
        open(isOpen) {
            if (!isOpen) return;
            this.$nextTick(() => this.$refs.usernameField?.focus());
        },
    },
    mounted() {
        document.addEventListener('keydown', this.onKeydown);
    },
    beforeUnmount() {
        document.removeEventListener('keydown', this.onKeydown);
    },
    methods: {
        /** Ask for credentials. The caller acts on @authorized, not on this call. */
        show() {
            this.reset();
            this.open = true;
        },
        cancel() {
            if (this.checking) return;
            this.open = false;
            this.reset();
            this.$emit('cancelled');
        },
        onKeydown(event) {
            if (event.key === 'Escape' && this.open) this.cancel();
        },
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
                    this.open = false;
                    const token = data.token;
                    this.reset();
                    this.$emit('authorized', token);
                })
                .catch((err) => {
                    const errors = err.response?.data?.errors;
                    this.error = errors
                        ? Object.values(errors).flat()[0]
                        : 'Could not authorize. Try again.';
                })
                .finally(() => {
                    // Never keep the password around, whatever the outcome.
                    this.password = '';
                    this.checking = false;
                });
        },
        reset() {
            this.username = '';
            this.password = '';
            this.error = '';
            this.checking = false;
        },
    },
};
</script>

<style scoped>
/* Only content inside the body -- the modal chrome itself comes from
   _library-modal.scss, which owns it for every modal in the app. */
.gate-lead {
    margin: 0 0 0.9rem;
    font-size: 0.84rem;
    line-height: 1.5;
    color: #4a6963;
}
.gate-error {
    margin: 0.6rem 0 0;
    font-size: 0.79rem;
    color: #96231f;
}
.spin { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
