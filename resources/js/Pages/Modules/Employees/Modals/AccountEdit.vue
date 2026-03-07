<template>
    <div v-if="showModal" class="account-modal-overlay" @click.self="close">
        <div class="account-modal-card">
            <div class="account-modal-header">
                <h3>
                    <i class="ri-user-settings-line"></i>
                    Edit Account Details
                </h3>
                <button type="button" class="account-modal-close" @click="close">
                    <i class="ri-close-line"></i>
                </button>
            </div>

            <form class="account-modal-body" @submit.prevent="submit">
                <div class="account-form-group">
                    <label>Username</label>
                    <input
                        type="text"
                        v-model="accountForm.username"
                        :class="{ 'error': accountForm.errors.username }"
                        placeholder="Enter username"
                    >
                    <span class="account-error" v-if="accountForm.errors.username">{{ accountForm.errors.username }}</span>
                </div>

                <div class="account-form-group">
                    <label>Select Roles</label>
                    <div class="account-role-grid">
                        <label v-for="role in accountRoleOptions" :key="role.value" class="account-role-item">
                            <input
                                type="checkbox"
                                :value="role.value"
                                :checked="isRoleSelected(role.value)"
                                @change="toggleRole(role.value, $event.target.checked)"
                            >
                            <span>{{ role.name }}</span>
                        </label>
                    </div>
                    <div class="account-role-chips" v-if="selectedRoleNames.length">
                        <span class="account-role-chip" v-for="role in selectedRoleNames" :key="role.value">
                            {{ role.name }}
                            <button type="button" @click="toggleRole(role.value, false)">
                                <i class="ri-close-line"></i>
                            </button>
                        </span>
                    </div>
                    <span class="account-error" v-if="accountForm.errors.role_ids">{{ accountForm.errors.role_ids }}</span>
                </div>

                <div class="account-modal-actions">
                    <button type="button" class="details-btn details-btn-outline" @click="close">Cancel</button>
                    <button type="submit" class="details-btn details-btn-primary" :disabled="accountForm.processing">
                        <i :class="accountForm.processing ? 'ri-loader-4-line spinning' : 'ri-save-line'"></i>
                        <span>{{ accountForm.processing ? 'Saving...' : 'Save Account' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script>
import { useForm } from '@inertiajs/vue3';

export default {
    props: {
        dropdowns: {
            type: Object,
            default: () => ({
                roles: [],
            }),
        },
    },
    emits: ['updated'],
    data() {
        return {
            showModal: false,
            accountForm: useForm({
                id: null,
                username: '',
                email: '',
                role_ids: [],
                option: 'users',
            }),
        };
    },
    computed: {
        accountRoleOptions() {
            const roles = Array.isArray(this.dropdowns?.roles) ? this.dropdowns.roles : [];
            return roles
                .map((role) => ({
                    value: role?.value ?? role?.id ?? null,
                    name: role?.name ?? role?.title ?? '',
                }))
                .filter((role) => role.value !== null && role.name);
        },
        selectedRoleNames() {
            const selectedIds = Array.isArray(this.accountForm.role_ids)
                ? this.accountForm.role_ids.map((id) => Number(id))
                : [];
            return this.accountRoleOptions.filter((role) => selectedIds.includes(Number(role.value)));
        },
    },
    methods: {
        open(employeeData) {
            const user = employeeData?.user;
            if (!user) return;

            this.accountForm.reset();
            this.accountForm.clearErrors();
            this.accountForm.id = user.id;
            this.accountForm.username = user.username || '';
            this.accountForm.email = user.email || employeeData?.email || '';
            this.accountForm.role_ids = this.extractRoleIds(user);
            this.accountForm.option = 'users';
            this.showModal = true;
        },
        close() {
            this.showModal = false;
            this.accountForm.clearErrors();
        },
        isRoleSelected(roleId) {
            const normalizedRoleId = Number(roleId);
            if (Number.isNaN(normalizedRoleId) || !Array.isArray(this.accountForm.role_ids)) return false;
            return this.accountForm.role_ids.map((id) => Number(id)).includes(normalizedRoleId);
        },
        toggleRole(roleId, checked) {
            const normalizedRoleId = Number(roleId);
            if (Number.isNaN(normalizedRoleId)) return;

            const ids = Array.isArray(this.accountForm.role_ids)
                ? this.accountForm.role_ids.map((id) => Number(id)).filter((id) => !Number.isNaN(id))
                : [];

            if (checked) {
                if (!ids.includes(normalizedRoleId)) ids.push(normalizedRoleId);
                this.accountForm.role_ids = ids;
            } else {
                this.accountForm.role_ids = ids.filter((id) => id !== normalizedRoleId);
            }
        },
        submit() {
            this.accountForm.put(`/users/${this.accountForm.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    this.close();
                    this.$emit('updated');
                },
            });
        },
        extractRoleIds(user) {
            if (!user) return [];
            if (Array.isArray(user.roles) && user.roles.length) {
                return user.roles.map((role) => role.id).filter(Boolean);
            }
            if (user.role && user.role.id) {
                return [user.role.id];
            }
            if (Array.isArray(user.myroles) && user.myroles.length) {
                const activeRoles = user.myroles
                    .filter((item) => item.is_active === 1 || item.is_active === true)
                    .map((item) => item.role_id)
                    .filter(Boolean);
                if (activeRoles.length) return activeRoles;
            }
            return [];
        },
    },
};
</script>

<style scoped>
.account-modal-overlay {
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

.account-modal-card {
    width: min(560px, 100%);
    border-radius: 16px;
    border: 1px solid #d4e4df;
    background: #fff;
    box-shadow: 0 20px 50px rgba(10, 35, 30, 0.22);
    overflow: hidden;
}

.account-modal-header {
    padding: 14px 16px;
    border-bottom: 1px solid #e1ece9;
    background: #f4fbf8;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.account-modal-header h3 {
    margin: 0;
    font-size: 0.98rem;
    color: #102723;
    display: inline-flex;
    align-items: center;
    gap: 7px;
}

.account-modal-close {
    border: 0;
    background: transparent;
    width: 30px;
    height: 30px;
    border-radius: 8px;
    color: #35524d;
    cursor: pointer;
}

.account-modal-close:hover {
    background: #e9f3ef;
}

.account-modal-body {
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.account-form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.account-form-group label {
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #35524d;
}

.account-form-group input[type="text"] {
    min-height: 40px;
    border: 1px solid #cfded9;
    border-radius: 10px;
    background: #fbfefd;
    padding: 0 11px;
    font-size: 0.88rem;
}

.account-form-group input[type="text"].error {
    border-color: #e39b92;
    background: #fff4f2;
}

.account-role-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 7px;
    max-height: 180px;
    overflow-y: auto;
    padding: 3px 1px;
}

.account-role-item {
    border: 1px solid #dce8e4;
    border-radius: 10px;
    min-height: 38px;
    padding: 0 10px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #fbfefd;
}

.account-role-item:hover {
    background: #f2fbf7;
}

.account-role-chips {
    margin-top: 6px;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.account-role-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 999px;
    background: #e7f7f1;
    color: #1a6c56;
    font-size: 0.74rem;
    font-weight: 600;
}

.account-role-chip button {
    border: 0;
    background: transparent;
    color: inherit;
    padding: 0;
    display: grid;
    place-items: center;
    cursor: pointer;
}

.account-error {
    font-size: 0.74rem;
    color: #c44f47;
    font-weight: 600;
}

.account-modal-actions {
    margin-top: 4px;
    padding-top: 10px;
    border-top: 1px solid #e1ece9;
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.details-btn {
    min-height: 38px;
    padding: 0 14px;
    border-radius: 10px;
    border: 1px solid transparent;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 0.84rem;
    font-weight: 700;
    cursor: pointer;
}

.details-btn-primary {
    color: #fff;
    background: linear-gradient(125deg, #2fa485 0%, #1a7e67 100%);
    box-shadow: 0 10px 20px rgba(28, 120, 99, 0.28);
}

.details-btn-outline {
    color: #35524d;
    background: #fff;
    border-color: #ceded9;
}

.spinning {
    animation: details-spin 0.9s linear infinite;
}

@keyframes details-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

@media (max-width: 700px) {
    .account-role-grid {
        grid-template-columns: 1fr;
    }
}
</style>
