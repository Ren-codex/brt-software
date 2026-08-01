<template>
  <Teleport to="body">
  <div v-if="showModal" class="modal-overlay active" @click.self="hide">
    <div class="modal-container settings-modal-container" @click.stop>

      <div class="modal-header">
        <div class="d-flex align-items-center gap-2">
          <div class="modal-header-icon"><i class="ri-settings-3-line"></i></div>
          <div>
            <h2 class="mb-0">Stock Settings</h2>
            <p class="modal-header-subtitle mb-0">{{ stock?.batch_code }}</p>
          </div>
        </div>
        <button class="close-btn" @click="hide"><i class="ri-close-line"></i></button>
      </div>

      <div class="modal-body">
        <div class="success-alert" v-if="saveSuccess">
          <i class="ri-checkbox-circle-fill"></i>
          <span>Settings saved successfully!</span>
        </div>

        <form @submit.prevent="save">

          <!-- Batch Info section -->
          <p class="settings-section-label">Batch Info</p>

          <div class="form-group">
            <label class="form-label">Batch Code</label>
            <div class="input-wrapper">
              <i class="ri-barcode-line input-icon"></i>
              <input
                type="text"
                v-model="form.batch_code"
                class="form-control"
                :class="{ 'input-error': form.errors.batch_code }"
                placeholder="e.g. B2026000001"
              />
            </div>
            <span class="error-message" v-if="form.errors.batch_code">{{ form.errors.batch_code }}</span>
          </div>

          <div class="form-group">
            <label class="form-label">Expiration Date</label>
            <div class="input-wrapper">
              <i class="ri-calendar-event-line input-icon"></i>
              <input
                type="date"
                v-model="form.expiration_date"
                class="form-control"
                :class="{ 'input-error': form.errors.expiration_date }"
              />
            </div>
            <span class="error-message" v-if="form.errors.expiration_date">{{ form.errors.expiration_date }}</span>
          </div>

          <div class="form-group">
            <label class="form-label">Unit Cost</label>
            <div class="input-wrapper">
              <i class="ri-money-peso-circle-line input-icon"></i>
              <input
                type="number"
                v-model="form.unit_cost"
                class="form-control"
                min="0"
                step="0.0001"
                :class="{ 'input-error': form.errors.unit_cost }"
                placeholder="0.00"
              />
            </div>
            <span class="error-message" v-if="form.errors.unit_cost">{{ form.errors.unit_cost }}</span>
          </div>

          <div class="form-group">
            <label class="form-label">Batch Notes</label>
            <div class="input-wrapper">
              <textarea
                v-model="form.notes"
                class="form-control"
                rows="3"
                placeholder="Optional notes about this batch (e.g. damaged pallet, supplier credit pending)"
              ></textarea>
            </div>
          </div>

          <!-- Status section -->
          <p class="settings-section-label mt-3">Status</p>

          <div class="settings-toggle-row">
            <div class="settings-toggle-info">
              <span class="settings-toggle-title">Archive Batch</span>
              <span class="settings-toggle-desc">Hide from active stock lists without deleting</span>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" v-model="form.is_archived" />
              <span class="toggle-slider"></span>
            </label>
          </div>

          <div class="settings-toggle-row">
            <div class="settings-toggle-info">
              <span class="settings-toggle-title">Mark as Expired</span>
              <span class="settings-toggle-desc">Flag this batch as expired stock</span>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" v-model="form.is_expired" />
              <span class="toggle-slider"></span>
            </label>
          </div>

        </form>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-cancel" @click="hide">
          <i class="ri-close-line"></i> Cancel
        </button>
        <button type="button" class="btn btn-save" :disabled="form.processing" @click="save">
          <i class="ri-save-line"></i> Save Settings
        </button>
      </div>

    </div>
  </div>
  </Teleport>
</template>

<script>
import { useForm } from '@inertiajs/vue3';

export default {
  name: 'StockSettingsModal',
  props: {
    stock: Object,
  },
  data() {
    return {
      form: useForm({
        batch_code:      '',
        expiration_date: '',
        unit_cost:       '',
        notes:           '',
        is_archived:     false,
        is_expired:      false,
      }),
      showModal:   false,
      saveSuccess: false,
    };
  },
  mounted() {
    document.addEventListener('keydown', this._onEscape);
  },
  beforeUnmount() {
    document.removeEventListener('keydown', this._onEscape);
  },
  methods: {
    _onEscape(e) {
      if (e.key === 'Escape' && this.showModal) this.hide();
    },
    show() {
      this.saveSuccess = false;
      this.form.reset();
      this.form.batch_code      = this.stock?.batch_code      ?? '';
      this.form.expiration_date = this.stock?.expiration_date ?? '';
      this.form.unit_cost       = this.stock?.unit_cost       ?? '';
      this.form.notes           = this.stock?.notes           ?? '';
      this.form.is_archived     = this.stock?.is_archived     ?? false;
      this.form.is_expired      = this.stock?.is_expired      ?? false;
      this.showModal = true;
    },
    hide() {
      this.form.reset();
      this.form.clearErrors();
      this.saveSuccess = false;
      this.showModal   = false;
    },
    save() {
      this.form.post(`/inventory-stocks/${this.stock?.id}/settings`, {
        preserveScroll: true,
        onSuccess: () => {
          this.saveSuccess = true;
          setTimeout(() => {
            this.$emit('saved');
            this.hide();
          }, 900);
        },
      });
    },
  },
};
</script>

<style scoped>
.settings-modal-container { max-width: 520px; }

.modal-header-subtitle { font-size: 0.78rem; color: #6b8c85; }

.settings-section-label {
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: #6b8c85;
  margin-bottom: 0.75rem;
}

.settings-toggle-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.65rem 0;
  border-bottom: 1px solid #f0f5f3;
}
.settings-toggle-row:last-child { border-bottom: none; }

.settings-toggle-info { display: flex; flex-direction: column; gap: 2px; }
.settings-toggle-title { font-size: 0.85rem; font-weight: 600; color: #16322e; }
.settings-toggle-desc  { font-size: 0.75rem; color: #6b8c85; }

/* Toggle switch */
.toggle-switch { position: relative; display: inline-block; width: 40px; height: 22px; flex-shrink: 0; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider {
  position: absolute; inset: 0;
  background: #d1d5db;
  border-radius: 22px;
  cursor: pointer;
  transition: background 0.2s;
}
.toggle-slider::before {
  content: '';
  position: absolute;
  width: 16px; height: 16px;
  left: 3px; top: 3px;
  background: #fff;
  border-radius: 50%;
  transition: transform 0.2s;
}
.toggle-switch input:checked + .toggle-slider { background: #3d8d7a; }
.toggle-switch input:checked + .toggle-slider::before { transform: translateX(18px); }

.success-alert {
  display: flex; align-items: center; gap: 8px;
  background: #f0fdf4; border: 1px solid #86efac;
  color: #15803d; border-radius: 8px;
  padding: 0.6rem 1rem; margin-bottom: 1rem;
  font-size: 0.85rem;
}
</style>
