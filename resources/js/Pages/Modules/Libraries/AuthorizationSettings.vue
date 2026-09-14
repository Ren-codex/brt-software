<template>
    <div class="library-card">
        <div class="library-card-header">
            <div class="d-flex align-items-center gap-3">
                <div class="header-icon"><i class="ri-shield-user-line"></i></div>
                <div>
                    <h4 class="header-title mb-0">Who Can Authorize</h4>
                    <p class="header-subtitle mb-0">
                        These actions ask for a username and password before they go through. Choose whose.
                    </p>
                </div>
            </div>
        </div>

        <div class="library-card-body">
            <div class="auth-actions">
                <div v-for="action in rows" :key="action.key" class="auth-action">
                    <div class="auth-action-head">
                        <div>
                            <h5>{{ action.label }}</h5>
                            <p>{{ action.description }}</p>
                        </div>
                        <span v-if="savedKey === action.key" class="auth-saved">
                            <i class="ri-check-line"></i> Saved
                        </span>
                    </div>

                    <div class="auth-roles">
                        <label v-for="role in roles" :key="role.id" class="auth-role">
                            <input
                                type="checkbox"
                                :value="role.id"
                                :checked="action.role_ids.includes(role.id)"
                                :disabled="!canEdit || saving === action.key"
                                @change="toggle(action, role.id, $event.target.checked)"
                            />
                            <span>{{ role.name }}</span>
                        </label>
                    </div>

                    <p v-if="action.role_ids.length === 0" class="auth-fallback">
                        <i class="ri-information-line"></i>
                        Nobody selected, so this falls back to Administrator. An action can never be left
                        with nobody able to authorize it.
                    </p>

                    <p v-if="errors[action.key]" class="auth-error">{{ errors[action.key] }}</p>
                </div>
            </div>

            <p class="auth-note">
                <i class="ri-shield-check-line"></i>
                A Super Admin can always authorize, whatever is selected here, so a mistake on this screen
                cannot lock anyone out of an action.
            </p>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import MainLayout from '@/Shared/Layouts/Main.vue';

export default {
    layout: MainLayout,
    props: {
        actions: { type: Array, default: () => [] },
        roles: { type: Array, default: () => [] },
    },
    data() {
        return {
            rows: this.actions.map((a) => ({ ...a, role_ids: [...a.role_ids] })),
            saving: null,
            savedKey: null,
            errors: {},
        };
    },
    computed: {
        canEdit() {
            return this.can('libraries', 'roles', 'admin');
        },
    },
    methods: {
        toggle(action, roleId, checked) {
            action.role_ids = checked
                ? [...action.role_ids, roleId]
                : action.role_ids.filter((id) => id !== roleId);

            this.save(action);
        },
        save(action) {
            this.saving = action.key;
            this.errors = { ...this.errors, [action.key]: '' };

            axios.put('/libraries/authorization-settings', {
                action: action.key,
                role_ids: action.role_ids,
            })
                .then(() => {
                    this.savedKey = action.key;
                    setTimeout(() => { if (this.savedKey === action.key) this.savedKey = null; }, 2000);
                })
                .catch((err) => {
                    this.errors = { ...this.errors, [action.key]: err.response?.data?.message || 'Could not save.' };
                })
                .finally(() => { this.saving = null; });
        },
    },
};
</script>

<style scoped>
.auth-actions { display: flex; flex-direction: column; gap: 1rem; }

.auth-action {
    border: 1px solid #d8e6e1;
    border-radius: 11px;
    padding: 1rem 1.1rem;
    background: #fbfdfc;
}

.auth-action-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 0.8rem;
}
.auth-action-head h5 { margin: 0 0 0.15rem; font-size: 0.95rem; color: #16322e; }
.auth-action-head p { margin: 0; font-size: 0.8rem; color: #6b8c85; max-width: 62ch; }

.auth-saved { color: #1b6b4a; font-size: 0.78rem; font-weight: 600; white-space: nowrap; }

.auth-roles { display: flex; flex-wrap: wrap; gap: 0.45rem; }
.auth-role {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border: 1px solid #d8e6e1;
    border-radius: 20px;
    background: #fff;
    font-size: 0.78rem;
    cursor: pointer;
}
.auth-role:has(input:checked) { background: #e9f5f0; border-color: #a8dcc8; color: #1b6b4a; font-weight: 600; }
.auth-role:has(input:disabled) { opacity: 0.6; cursor: not-allowed; }
.auth-role input { accent-color: #3d8d7a; }

.auth-fallback, .auth-note {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    font-size: 0.76rem;
    color: #33564f;
    margin: 0.7rem 0 0;
}
.auth-fallback i, .auth-note i { color: #3d8d7a; margin-top: 1px; }
.auth-note { margin-top: 1.1rem; }

.auth-error { margin: 0.5rem 0 0; font-size: 0.78rem; color: #96231f; }
</style>
