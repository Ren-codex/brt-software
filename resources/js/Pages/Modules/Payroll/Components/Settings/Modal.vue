<template>
    <div v-if="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="hide">
        <div class="modal-container modal-md">
            <div class="modal-header">
                <h2>{{ isEdit ? 'Edit' : 'Create' }} Payroll Setting</h2>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body">
                <form @submit.prevent="saveSetting" class="payroll-setting-form">
                    <!-- Field Name -->
                    <div class="form-group">
                        <label class="form-label">Field Name</label>
                        <div class="input-wrapper">
                            <i class="ri-file-text-line input-icon"></i>
                            <input type="text" v-model="form.field_name" class="form-control" readonly>
                        </div>
                    </div>

                    <!-- Formula -->
                    <div class="form-group" v-if="form.formula">
                        <label class="form-label">Formula</label>
                        <div class="input-wrapper">
                            <i class="ri-function-line input-icon"></i>
                            <input type="text" v-model="form.formula" class="form-control" readonly>
                        </div>
                    </div>

                    <!-- Value -->
                    <div class="form-group">
                        <label class="form-label">Value (x)</label>
                        <div class="input-wrapper">
                            <i class="ri-money-dollar-circle-line input-icon"></i>
                            <input type="number" v-model="form.value" class="form-control" step="0.01" required>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="button" class="btn btn-cancel" @click="hide">
                            <i class="ri-close-line"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-save" :disabled="loading">
                            <i class="ri-save-line" v-if="!loading"></i>
                            <i class="ri-loader-4-line spinner" v-else></i>
                            {{ loading ? 'Processing...' : (isEdit ? 'Update Setting' : 'Create Setting') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios'

export default {
  props: {
    setting: Object,
    isEdit: Boolean
  },

  data() {
    return {
      form: {
        field_name: '',
        formula: '',
        value: 0,
        is_active: true
      },
      loading: false,
      showModal: false
    }
  },

  mounted() {
    if (this.isEdit && this.setting) {
      this.form = { ...this.setting }
    }
    this.showModal = true
  },

  methods: {
    async saveSetting() {
      this.loading = true

      try {
        await axios.put(`/payroll-settings/${this.setting.id}`, this.form);

        this.$emit('saved')
        this.hide()
      } finally {
        this.loading = false
      }
    },

    hide() {
      this.showModal = false
      this.$emit('close')
    }
  }
}
</script>

<style scoped>
/* Modal Overlay */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1050;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    padding: 15px;
}

.modal-overlay.active {
    opacity: 1;
    visibility: visible;
}

/* Modal Container */
.modal-container {
    background: white;
    border-radius: 16px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    width: 100%;
    max-width: 500px;
    overflow: hidden;
    transform: translateY(25px) scale(0.95);
    transition: all 0.3s ease;
}

.modal-container.modal-md {
    max-width: 600px;
}

.modal-overlay.active .modal-container {
    transform: translateY(0) scale(1);
}

/* Modal Header */
.modal-header {
    background: #3a8674;
    color: white;
    padding: 0.875rem 1.25rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    font-weight: 700;
    font-size: 1.1rem;
    margin: 0;
}

.close-btn {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1.1rem;
}

.close-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg);
}

/* Modal Body */
.modal-body {
    padding: 1.5rem;
    max-height: 75vh;
    overflow-y: auto;
}

/* Form Group */
.form-group {
    margin-bottom: 1.25rem;
}

.form-label {
    display: block;
    margin-bottom: 0.375rem;
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.9rem;
}

/* Input Wrapper */
.input-wrapper {
    position: relative;
}

.input-icon {
    position: absolute;
    left: 0.875rem;
    top: 50%;
    transform: translateY(-50%);
    color: #7f8c8d;
    font-size: 1.1rem;
    z-index: 1;
}

.form-control {
    width: 100%;
    padding: 0.7rem 0.875rem 0.7rem 2.75rem;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    background-color: white;
}

.form-control:focus {
    outline: none;
    border-color: #2e8b57;
    box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.1);
}

.form-control[readonly] {
    background-color: #f8f9fa;
    cursor: not-allowed;
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    margin-top: 1.5rem;
}

.btn {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.8125rem;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.btn-cancel {
    background-color: transparent;
    color: #7f8c8d;
    border: 1px solid #e9ecef;
}

.btn-cancel:hover {
    background-color: #f8f9fa;
    border-color: #7f8c8d;
}

.btn-save {
    background: #3a8674;
    color: white;
    box-shadow: 0 4px 12px rgba(46, 139, 87, 0.3);
}

.btn-save:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(46, 139, 87, 0.4);
}

.btn-save:disabled {
    background: #95a5a6;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* Spinner Animation */
.spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .modal-header {
        padding: 0.75rem 1rem;
    }

    .modal-header h2 {
        font-size: 1rem;
    }

    .modal-body {
        padding: 1.25rem;
    }

    .form-actions {
        flex-direction: column-reverse;
        gap: 0.625rem;
    }

    .btn {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .modal-overlay {
        padding: 10px;
    }

    .modal-header {
        padding: 0.625rem 0.875rem;
    }

    .modal-header h2 {
        font-size: 0.95rem;
    }

    .modal-body {
        padding: 1rem;
    }

    .form-control {
        font-size: 0.9rem;
        padding: 0.625rem 0.75rem 0.625rem 2.5rem;
    }

    .btn {
        padding: 0.5rem 0.875rem;
        font-size: 0.75rem;
    }
}
</style>
