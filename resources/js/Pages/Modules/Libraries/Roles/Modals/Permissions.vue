<template>
  <Teleport to="body">
    <div v-if="showModal" class="modal-overlay active" @click.self="hide">
      <div class="modal-container modal-lg" @click.stop>
        <div class="modal-header">
          <div>
            <h2>Manage Permissions</h2>
            <p class="modal-subtitle" v-if="role">{{ role.name }}</p>
          </div>
          <button class="close-btn" @click="hide">
            <i class="ri-close-line"></i>
          </button>
        </div>
        <div class="modal-body">
          <div v-if="loading" class="permissions-loading">
            <i class="ri-loader-4-line spinner"></i> Loading...
          </div>
          <template v-else>
          <p class="permissions-hint">
            <i class="ri-information-line"></i>
            <span>
              Checking a box on a <strong>bold module row</strong> grants that access to <strong>every submodule under it</strong>.
              Those submodule boxes then turn <span class="hint-swatch"></span><strong>light &amp; locked</strong> — they're already
              covered and can't be unchecked on their own. Uncheck the module row first if you need to set submodules individually.
            </span>
          </p>
          <table class="table permissions-table">
            <thead>
              <tr>
                <th>Module / Submodule</th>
                <th class="text-center" v-for="lm in levelMeta" :key="lm.key">
                  <div class="perm-head">
                    <i :class="lm.icon"></i>
                    <span>{{ lm.label }}</span>
                  </div>
                </th>
              </tr>
            </thead>
            <tbody>
              <template v-for="mod in modules" :key="'m' + mod.id">
                <tr class="module-row">
                  <td><strong>{{ mod.name }}</strong></td>
                  <td class="text-center" v-for="lvl in levels" :key="lvl">
                    <label class="perm-checkbox" :class="{ checked: mod.levels.includes(lvl) }">
                      <input
                        type="checkbox"
                        :checked="mod.levels.includes(lvl)"
                        @change="toggle(mod.id, null, lvl, $event.target.checked)"
                      >
                      <span class="perm-checkbox-box"><i class="ri-check-line"></i></span>
                    </label>
                  </td>
                </tr>
                <tr v-for="sub in mod.submodules" :key="'s' + sub.id" class="submodule-row">
                  <td class="submodule-name">{{ sub.name }}</td>
                  <td class="text-center" v-for="lvl in levels" :key="lvl">
                    <label
                      class="perm-checkbox"
                      :class="{ checked: sub.levels.includes(lvl) || mod.levels.includes(lvl), inherited: mod.levels.includes(lvl) }"
                      :title="mod.levels.includes(lvl) ? `Already included via the ${mod.name} module-wide grant` : ''"
                    >
                      <input
                        type="checkbox"
                        :checked="sub.levels.includes(lvl) || mod.levels.includes(lvl)"
                        :disabled="mod.levels.includes(lvl)"
                        @change="toggle(mod.id, sub.id, lvl, $event.target.checked)"
                      >
                      <span class="perm-checkbox-box"><i class="ri-check-line"></i></span>
                    </label>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>

          <section class="auth-section">
            <div class="auth-section-head">
              <h3><i class="ri-shield-user-line"></i> Can Authorize</h3>
              <p>
                These actions stop and ask for a username and password before they go through.
                Tick one to let someone with this role be the person who approves it.
              </p>
            </div>

            <label
              v-for="action in authorizations"
              :key="action.key"
              class="auth-action"
              :class="{ checked: action.assigned }"
            >
              <input type="checkbox" v-model="action.assigned">
              <span class="auth-action-box"><i class="ri-check-line"></i></span>
              <span class="auth-action-text">
                <strong>{{ action.label }}</strong>
                <span>{{ action.description }}</span>
              </span>
            </label>

            <p class="auth-note">
              <i class="ri-shield-check-line"></i>
              <span>
                A <strong>Super Admin</strong> can always authorize, whatever is ticked here, so nothing on
                this screen can leave an action with nobody able to approve it. An action ticked for no role
                at all falls back to <strong>Administrator</strong>.
              </span>
            </p>
          </section>
          </template>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-cancel" @click="hide">
            <i class="ri-close-line"></i>
            Cancel
          </button>
          <button type="button" class="btn btn-save" :disabled="saving || loading" @click="save">
            <i class="ri-save-line" v-if="!saving"></i>
            <i class="ri-loader-4-line spinner" v-else></i>
            {{ saving ? 'Saving...' : 'Save Permissions' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script>
import axios from 'axios';

export default {
  name: 'RolePermissions',
  emits: ['saved'],
  data() {
    return {
      showModal: false,
      loading: false,
      saving: false,
      role: null,
      modules: [],
      authorizations: [],
      levelMeta: [
        { key: 'encoder', label: 'Encoder', icon: 'ri-edit-line' },
        { key: 'approver', label: 'Approver', icon: 'ri-checkbox-circle-line' },
        { key: 'releaser', label: 'Releaser', icon: 'ri-send-plane-line' },
        { key: 'void', label: 'Voider', icon: 'ri-forbid-line' },
        { key: 'view', label: 'Viewer', icon: 'ri-eye-line' },
        { key: 'admin', label: 'Admin', icon: 'ri-shield-star-line' },
      ],
    };
  },
  computed: {
    levels() {
      return this.levelMeta.map((lm) => lm.key);
    },
  },
  methods: {
    show(role) {
      this.role = role;
      this.showModal = true;
      this.fetch();
    },
    hide() {
      this.showModal = false;
    },
    async fetch() {
      this.loading = true;
      try {
        const res = await axios.get(`/libraries/roles/${this.role.id}/permissions`);
        this.modules = res.data.modules;
        this.authorizations = res.data.authorizations ?? [];
      } finally {
        this.loading = false;
      }
    },
    toggle(moduleId, submoduleId, level, checked) {
      const mod = this.modules.find((m) => m.id === moduleId);
      const target = submoduleId ? mod.submodules.find((s) => s.id === submoduleId) : mod;

      if (checked) {
        if (!target.levels.includes(level)) target.levels.push(level);
      } else {
        target.levels = target.levels.filter((l) => l !== level);
      }

      // Checking a module-wide box makes that level redundant on every
      // submodule underneath it — clear it there so the submodule boxes
      // cleanly show as "inherited" instead of carrying a hidden duplicate
      // grant that would resurface confusingly if the module box is later
      // unchecked.
      if (!submoduleId && checked) {
        mod.submodules.forEach((sub) => {
          sub.levels = sub.levels.filter((l) => l !== level);
        });
      }
    },
    async save() {
      this.saving = true;
      const grants = [];
      this.modules.forEach((mod) => {
        mod.levels.forEach((level) => grants.push({ module_id: mod.id, submodule_id: null, access_level: level }));
        mod.submodules.forEach((sub) => {
          sub.levels.forEach((level) => grants.push({ module_id: mod.id, submodule_id: sub.id, access_level: level }));
        });
      });

      try {
        const authorizations = this.authorizations.filter((a) => a.assigned).map((a) => a.key);
        const res = await axios.post(`/libraries/roles/${this.role.id}/permissions`, { grants, authorizations });

        // HandlesTransaction swallows the exception and still answers 200 with
        // status:false, so a resolved promise is not proof the grants were
        // written. Without this check a rolled-back save showed the green
        // success toast and the role kept its old permissions.
        if (res.data && res.data.status === false) {
          this.fail(res.data.info || res.data.message || 'The permissions were not saved.');
          return;
        }

        this.$emit('saved');
        this.hide();
        this.$confirm({
          title: 'Success',
          message: 'Permissions saved successfully!',
          variant: 'success',
          confirmText: 'OK',
          hideCancel: true,
          autoCloseMs: 1800,
        });
      } catch (error) {
        // There is no global axios interceptor, so without this catch a 403 or
        // an expired session left the modal open with no message at all and the
        // grants silently never reached the server.
        this.fail(this.errorMessage(error));
      } finally {
        this.saving = false;
      }
    },
    errorMessage(error) {
      const status = error?.response?.status;
      if (status === 403) {
        return 'You are not allowed to change role permissions. This needs Libraries → Roles admin access.';
      }
      if (status === 419) {
        return 'Your session expired before the permissions were saved. Reload the page and try again.';
      }
      if (status === 422) {
        return 'The server rejected some of the selected permissions.';
      }
      return error?.response?.data?.message || error?.message || 'The permissions could not be saved.';
    },
    fail(message) {
      this.$confirm({
        title: 'Save failed',
        message,
        variant: 'danger',
        confirmText: 'OK',
        hideCancel: true,
      });
    },
  },
};
</script>

<style scoped>
.permissions-table {
  width: 100%;
  /* Bootstrap's default border-collapse: collapse breaks position: sticky on
     <th> in Chrome/Edge -- collapsed cell borders let the row scrolling
     underneath bleed through the sticky header during repaint. */
  border-collapse: separate;
  border-spacing: 0;
}
.permissions-table td, .permissions-table th { vertical-align: middle; }
.permissions-table thead th {
  position: sticky;
  top: -16px;
  z-index: 3;
  background: #fff;
  box-shadow: inset 0 -1px 0 #dcebe6, 0 2px 4px rgba(22, 50, 46, 0.06);
}
.perm-head {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.2rem;
}
.perm-head i { font-size: 1.05rem; color: #3d8d7a; }
.perm-head span { font-size: 0.78rem; }
.module-row td { background: #f7fbf9; font-weight: 600; }
.submodule-row .submodule-name { padding-left: 2rem; color: #5a7a73; }
.permissions-loading { display: flex; align-items: center; gap: .5rem; padding: 2rem; justify-content: center; color: #6b8c85; }
.modal-subtitle { font-size: .8rem; color: #6b8c85; margin: 0; }

.perm-checkbox {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  padding: 0.6rem 0.4rem;
  margin: 0;
  cursor: pointer;
  border-radius: 8px;
  transition: background-color 0.15s ease;
}
.perm-checkbox:hover { background-color: rgba(61, 141, 122, 0.08); }
.perm-checkbox input[type="checkbox"] {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
}
.perm-checkbox-box {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border: 2px solid #c4d9d2;
  border-radius: 7px;
  background: #fff;
  color: transparent;
  font-size: 16px;
  line-height: 1;
  transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease, transform 0.1s ease;
}
.perm-checkbox:hover .perm-checkbox-box { border-color: #3d8d7a; }
.perm-checkbox.checked .perm-checkbox-box {
  background-color: #3d8d7a;
  border-color: #3d8d7a;
  color: #fff;
}
.perm-checkbox input[type="checkbox"]:focus-visible ~ .perm-checkbox-box {
  outline: 2px solid #3d8d7a;
  outline-offset: 2px;
}
.perm-checkbox:active .perm-checkbox-box { transform: scale(0.9); }

.perm-checkbox.inherited {
  cursor: not-allowed;
}
.perm-checkbox.inherited:hover { background-color: transparent; }
.perm-checkbox.inherited:hover .perm-checkbox-box { border-color: #a9cfc3; }
.perm-checkbox.inherited .perm-checkbox-box {
  background-color: #a9cfc3;
  border-color: #a9cfc3;
  color: #fff;
}

.permissions-hint {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  background: #f7fbf9;
  border: 1px solid #dcebe6;
  border-radius: 8px;
  padding: 0.65rem 0.9rem;
  margin-bottom: 0.9rem;
  font-size: 0.82rem;
  color: #4a6963;
  line-height: 1.45;
}
.permissions-hint i { color: #3d8d7a; margin-top: 0.15rem; }
.hint-swatch {
  display: inline-block;
  width: 11px;
  height: 11px;
  border-radius: 3px;
  background-color: #a9cfc3;
  margin: 0 0.15rem -1px;
}

/* Who may approve somebody else's guarded action -- a different question from
   the grants above, so it reads as its own block rather than another table row. */
.auth-section {
  margin-top: 1.4rem;
  padding-top: 1.1rem;
  border-top: 1px solid #e3ece9;
}
.auth-section-head h3 {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  margin: 0 0 0.25rem;
  font-size: 0.95rem;
  font-weight: 600;
  color: #16322e;
}
.auth-section-head h3 i { color: #3d8d7a; }
.auth-section-head p {
  margin: 0 0 0.8rem;
  font-size: 0.82rem;
  color: #6b8c85;
  line-height: 1.45;
  max-width: 64ch;
}
.auth-action {
  display: flex;
  align-items: flex-start;
  gap: 0.65rem;
  padding: 0.6rem 0.75rem;
  margin-bottom: 0.4rem;
  border: 1px solid #e3ece9;
  border-radius: 8px;
  cursor: pointer;
  transition: border-color 0.15s ease, background-color 0.15s ease;
}
.auth-action:hover { border-color: #c4d9d2; background: #fbfdfc; }
.auth-action.checked { border-color: #a9cfc3; background: #f4faf8; }
.auth-action input { position: absolute; opacity: 0; pointer-events: none; }
.auth-action-box {
  flex: 0 0 auto;
  width: 18px;
  height: 18px;
  margin-top: 0.1rem;
  border: 1.5px solid #c4d9d2;
  border-radius: 5px;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background-color 0.15s ease, border-color 0.15s ease;
}
.auth-action-box i { font-size: 13px; color: #fff; opacity: 0; }
.auth-action.checked .auth-action-box { background: #3d8d7a; border-color: #3d8d7a; }
.auth-action.checked .auth-action-box i { opacity: 1; }
.auth-action-text { display: flex; flex-direction: column; gap: 0.1rem; }
.auth-action-text strong { font-size: 0.86rem; font-weight: 600; color: #16322e; }
.auth-action-text span { font-size: 0.79rem; color: #6b8c85; line-height: 1.4; }
.auth-note {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  margin: 0.7rem 0 0;
  font-size: 0.79rem;
  color: #4a6963;
  line-height: 1.45;
}
.auth-note i { color: #3d8d7a; margin-top: 0.1rem; }
</style>
