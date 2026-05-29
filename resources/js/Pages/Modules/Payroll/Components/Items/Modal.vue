<template>
  <div v-if="show" class="modal-overlay" :class="{ active: show }" @click.self="close">
    <div class="modal-container">
      <div class="modal-header">
        <h2 class="mb-0">{{ modalTitle }}</h2>
        <button class="close-btn" @click="close">
          <i class="ri-close-line"></i>
        </button>
      </div>
      <div class="modal-body">
        <form @submit.prevent="submit">
          <div class="form-group">
            <label class="form-label">Name</label>
            <input v-model="localForm.name" type="text" class="form-control" placeholder="Enter name" required>
            <small v-if="formErrors.name" class="text-danger">{{ formErrors.name[0] }}</small>
          </div>

          <div class="form-group">
            <label class="form-label">Description</label>
            <textarea
              v-model="localForm.description"
              class="form-control"
              rows="3"
              placeholder="Enter description (optional)"
            ></textarea>
            <small v-if="formErrors.description" class="text-danger">{{ formErrors.description[0] }}</small>
          </div>

          <div class="form-actions">
            <button type="button" class="btn btn-cancel" @click="close">Cancel</button>
            <button type="submit" class="btn btn-save" :disabled="isSaving">
              {{ isSaving ? 'Saving...' : (isEdit ? 'Update Item' : 'Save Item') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  emits: ['save', 'close'],
  props: {
    show: Boolean,
    form: {
      type: Object,
      default: () => ({}),
    },
    formErrors: {
      type: Object,
      default: () => ({}),
    },
    isSaving: Boolean,
    isEdit: Boolean,
  },
  data() {
    return {
      localForm: {
        name: '',
        description: '',
        type: 'earning',
      },
    };
  },
  computed: {
    modalTitle() {
      const label = this.localForm.type === 'earning' ? 'Earning' : 'Deduction';
      return this.isEdit ? `Edit ${label}` : `Add ${label}`;
    },
  },
  watch: {
    show(newVal) {
      if (newVal) {
        this.syncForm();
      }
    },
    form: {
      deep: true,
      handler() {
        if (this.show) {
          this.syncForm();
        }
      },
    },
  },
  methods: {
    syncForm() {
      this.localForm = {
        name: this.form?.name || '',
        description: this.form?.description || '',
        type: this.form?.type || 'earning',
      };
    },
    submit() {
      this.$emit('save', { ...this.localForm });
    },
    close() {
      this.$emit('close');
    },
  },
};
</script>

<style scoped>
.modal-overlay {
  z-index: 1060;
}

.modal-container {
  max-width: 520px;
}

.modal-body {
  padding: 16px;
}

.form-group {
  margin-bottom: 12px;
}

.form-label {
  display: block;
  font-weight: 600;
  margin-bottom: 6px;
}

.checkbox-wrap {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.btn-cancel,
.btn-save {
  border: none;
  border-radius: 6px;
  padding: 8px 14px;
  font-weight: 600;
}

.btn-cancel {
  background: #e9ecef;
  color: #495057;
}

.btn-save {
  background: #198754;
  color: #fff;
}
</style>
