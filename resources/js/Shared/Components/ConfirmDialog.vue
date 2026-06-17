<template>
  <Teleport to="body">
    <div class="cd-overlay" @click.self="cancel">
      <div class="cd-container" role="alertdialog" aria-modal="true">
        <div class="cd-header">
          <div class="d-flex align-items-center gap-2">
            <div class="cd-icon" :class="iconClass">
              <i :class="iconName"></i>
            </div>
            <div>
              <h5 class="cd-title mb-0">{{ title }}</h5>
              <p v-if="subtitle" class="cd-subtitle mb-0">{{ subtitle }}</p>
            </div>
          </div>
        </div>

        <div class="cd-body">
          <p class="cd-message">{{ message }}</p>
          <p v-if="note" class="cd-note">
            <i class="ri-information-line"></i> {{ note }}
          </p>
        </div>

        <div class="cd-footer">
          <button type="button" class="cd-btn cd-btn-cancel" @click="cancel">
            {{ cancelText }}
          </button>
          <button type="button" class="cd-btn" :class="confirmBtnClass" @click="confirm">
            {{ confirmText }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script>
export default {
  name: 'ConfirmDialog',
  props: {
    title:       { type: String, default: 'Are you sure?' },
    subtitle:    { type: String, default: '' },
    message:     { type: String, default: 'This action cannot be undone.' },
    note:        { type: String, default: '' },
    confirmText: { type: String, default: 'Confirm' },
    cancelText:  { type: String, default: 'Cancel' },
    variant:     { type: String, default: 'danger' }, // danger | warning | info
  },
  emits: ['confirm', 'cancel'],
  computed: {
    iconName() {
      return {
        danger:  'ri-error-warning-line',
        warning: 'ri-alert-line',
        info:    'ri-question-line',
      }[this.variant] || 'ri-error-warning-line';
    },
    iconClass() {
      return `cd-icon--${this.variant}`;
    },
    confirmBtnClass() {
      return `cd-btn-confirm--${this.variant}`;
    },
  },
  mounted() {
    document.addEventListener('keydown', this._onKey);
  },
  beforeUnmount() {
    document.removeEventListener('keydown', this._onKey);
  },
  methods: {
    _onKey(e) {
      if (e.key === 'Escape') this.cancel();
      if (e.key === 'Enter')  this.confirm();
    },
    confirm() { this.$emit('confirm'); },
    cancel()  { this.$emit('cancel'); },
  },
};
</script>

<style scoped>
.cd-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 30, 28, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  animation: cd-fade-in 0.15s ease;
}

@keyframes cd-fade-in {
  from { opacity: 0; }
  to   { opacity: 1; }
}

.cd-container {
  background: #fff;
  border-radius: 16px;
  width: 100%;
  max-width: 420px;
  margin: 1rem;
  box-shadow: 0 20px 60px rgba(15, 30, 28, 0.22);
  animation: cd-slide-in 0.18s ease;
  overflow: hidden;
}

@keyframes cd-slide-in {
  from { transform: translateY(-12px); opacity: 0; }
  to   { transform: translateY(0);     opacity: 1; }
}

/* Header */
.cd-header {
  background: linear-gradient(to right, #cfe0d9 0%, #edf6f2 100%);
  border-bottom: 1px solid #c4d9d2;
  padding: 0.85rem 1.1rem;
}

.cd-title {
  font-size: 0.95rem;
  font-weight: 700;
  color: #16322e;
}

.cd-subtitle {
  font-size: 0.75rem;
  color: #6b8c85;
}

/* Icon */
.cd-icon {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  flex-shrink: 0;
}

.cd-icon--danger {
  background: rgba(220, 53, 69, 0.1);
  border: 1px solid rgba(220, 53, 69, 0.2);
  color: #dc3545;
}

.cd-icon--warning {
  background: rgba(245, 158, 11, 0.1);
  border: 1px solid rgba(245, 158, 11, 0.2);
  color: #f59e0b;
}

.cd-icon--info {
  background: rgba(61, 141, 122, 0.12);
  border: 1px solid rgba(61, 141, 122, 0.16);
  color: #3d8d7a;
}

/* Body */
.cd-body {
  padding: 1.1rem 1.25rem 0.85rem;
}

.cd-message {
  font-size: 0.9rem;
  color: #16322e;
  margin: 0;
  line-height: 1.55;
}

.cd-note {
  margin: 0.65rem 0 0;
  padding: 0.55rem 0.75rem;
  background: #fff8e6;
  border-left: 3px solid #f59e0b;
  border-radius: 6px;
  font-size: 0.8rem;
  color: #7c5b10;
  display: flex;
  align-items: flex-start;
  gap: 0.4rem;
  line-height: 1.45;
}

.cd-note i {
  margin-top: 1px;
  flex-shrink: 0;
}

/* Footer */
.cd-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.6rem;
  padding: 0.75rem 1.25rem;
  border-top: 1px solid #edf2f0;
  background: #f9fdfb;
}

.cd-btn {
  padding: 0.42rem 1rem;
  border-radius: 8px;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all 0.15s ease;
}

.cd-btn-cancel {
  background: #fff;
  color: #5f7f77;
  border: 1px solid #c4d9d2;
}

.cd-btn-cancel:hover {
  background: #f0f7f5;
}

.cd-btn-confirm--danger {
  background: #dc3545;
  color: #fff;
}

.cd-btn-confirm--danger:hover {
  background: #c0323f;
}

.cd-btn-confirm--warning {
  background: #f59e0b;
  color: #fff;
}

.cd-btn-confirm--warning:hover {
  background: #d97706;
}

.cd-btn-confirm--info {
  background: #3d8d7a;
  color: #fff;
}

.cd-btn-confirm--info:hover {
  background: #2f7464;
}
</style>
