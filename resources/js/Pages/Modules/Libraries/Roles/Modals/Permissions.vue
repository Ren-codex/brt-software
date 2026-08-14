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
          <table v-else class="table permissions-table">
            <thead>
              <tr>
                <th>Module / Submodule</th>
                <th class="text-center">Encoder</th>
                <th class="text-center">Approver</th>
                <th class="text-center">View</th>
                <th class="text-center">Admin</th>
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
                    <label class="perm-checkbox" :class="{ checked: sub.levels.includes(lvl) }">
                      <input
                        type="checkbox"
                        :checked="sub.levels.includes(lvl)"
                        @change="toggle(mod.id, sub.id, lvl, $event.target.checked)"
                      >
                      <span class="perm-checkbox-box"><i class="ri-check-line"></i></span>
                    </label>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
          <div class="success-alert" v-if="saveSuccess">
            <i class="ri-checkbox-circle-fill"></i>
            <span>Permissions saved successfully!</span>
          </div>
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
      saveSuccess: false,
      role: null,
      modules: [],
      levels: ['encoder', 'approver', 'view', 'admin'],
    };
  },
  methods: {
    show(role) {
      this.role = role;
      this.showModal = true;
      this.saveSuccess = false;
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
      } finally {
        this.loading = false;
      }
    },
    toggle(moduleId, submoduleId, level, checked) {
      const target = submoduleId
        ? this.modules.find((m) => m.id === moduleId).submodules.find((s) => s.id === submoduleId)
        : this.modules.find((m) => m.id === moduleId);

      if (checked) {
        if (!target.levels.includes(level)) target.levels.push(level);
      } else {
        target.levels = target.levels.filter((l) => l !== level);
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
        await axios.post(`/libraries/roles/${this.role.id}/permissions`, { grants });
        this.saveSuccess = true;
        this.$emit('saved');
        setTimeout(() => {
          this.hide();
        }, 900);
      } finally {
        this.saving = false;
      }
    },
  },
};
</script>

<style scoped>
.permissions-table { width: 100%; }
.permissions-table td, .permissions-table th { vertical-align: middle; }
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
</style>
