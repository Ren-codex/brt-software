<template>
  <Teleport to="body">
  <div class="modal-overlay active" v-if="visible" @click.self="hide">
    <div class="modal-container" style="max-width: 560px;" @click.stop>
      <div class="modal-header">
        <div class="d-flex align-items-center gap-3">
          <div class="modal-header-icon">
            <i class="ri-scales-3-line"></i>
          </div>
          <div>
            <h5 class="modal-title mb-0">Record Weight Loss</h5>
            <p class="modal-kicker mb-0">{{ inventoryStock?.batch_code }}</p>
          </div>
        </div>
        <button class="close-btn" @click="hide"><i class="ri-close-line"></i></button>
      </div>

      <div class="modal-body">

        <!-- Line items -->
        <div class="mb-3">
          <label class="form-label">Short Sack Groups <span class="text-danger">*</span></label>
          <div class="line-items-table">
            <div class="line-items-header">
              <span>Short Sacks</span>
              <span></span>
              <span>Loss / Sack (kg)</span>
              <span></span>
              <span>Subtotal</span>
              <span></span>
            </div>
            <div class="line-item-row" v-for="(row, i) in form.items" :key="i">
              <input
                type="number"
                class="form-control form-control-sm"
                :class="{ 'is-invalid': rowError(i, 'affected_sacks') }"
                v-model.number="row.affected_sacks"
                min="1"
                step="1"
                placeholder="0"
              />
              <span class="line-op">×</span>
              <input
                type="number"
                class="form-control form-control-sm"
                :class="{ 'is-invalid': rowError(i, 'loss_per_sack') }"
                v-model.number="row.loss_per_sack"
                min="0.01"
                step="0.01"
                placeholder="0.00"
              />
              <span class="line-op">=</span>
              <span class="line-subtotal">
                {{ rowSubtotal(row) > 0 ? rowSubtotal(row).toFixed(2) + ' kg' : '—' }}
              </span>
              <button
                class="line-remove-btn"
                @click="removeRow(i)"
                :disabled="form.items.length === 1"
                title="Remove row"
              >
                <i class="ri-close-line"></i>
              </button>
            </div>
          </div>

          <button class="add-row-btn mt-2" @click="addRow">
            <i class="ri-add-line"></i> Add Row
          </button>

          <div class="text-danger small mt-1" v-if="form.errors.items">{{ form.errors.items }}</div>
        </div>

        <!-- Total summary -->
        <div class="total-loss-banner mb-3" :class="{ 'has-value': grandTotal > 0 }">
          <div class="d-flex justify-content-between align-items-center">
            <span class="total-loss-label">Total Weight Loss</span>
            <span class="total-loss-value" v-if="grandTotal > 0">
              <strong>{{ totalSacks }} sacks</strong> — <strong class="text-danger">{{ grandTotal.toFixed(2) }} kg</strong>
            </span>
            <span class="total-loss-value text-muted" v-else>Fill in the rows above</span>
          </div>
        </div>

        <!-- Shared fields -->
        <div class="mb-3">
          <label class="form-label">Reason <span class="text-danger">*</span></label>
          <select class="form-select" :class="{ 'is-invalid': form.errors.reason }" v-model="form.reason">
            <option value="">Select reason...</option>
            <option value="Damaged sack">Damaged sack</option>
            <option value="Moisture loss">Moisture loss</option>
            <option value="Spillage">Spillage</option>
            <option value="Measurement shortage">Measurement shortage</option>
            <option value="Other">Other</option>
          </select>
          <div class="invalid-feedback" v-if="form.errors.reason">{{ form.errors.reason }}</div>
        </div>

        <div class="mb-3">
          <label class="form-label">Notes</label>
          <textarea
            class="form-control"
            v-model="form.notes"
            rows="2"
            placeholder="Optional notes..."
            maxlength="500"
          ></textarea>
        </div>

        <div class="mb-0">
          <label class="form-label">Date <span class="text-danger">*</span></label>
          <input
            type="date"
            class="form-control"
            :class="{ 'is-invalid': form.errors.recorded_at }"
            v-model="form.recorded_at"
          />
          <div class="invalid-feedback" v-if="form.errors.recorded_at">{{ form.errors.recorded_at }}</div>
        </div>

      </div>

      <div class="modal-footer">
        <button class="btn btn-outline-secondary btn-sm" @click="hide" :disabled="form.processing">Cancel</button>
        <button class="btn btn-primary btn-sm" @click="submit" :disabled="form.processing || grandTotal <= 0">
          <span v-if="form.processing"><i class="ri-loader-4-line spin"></i> Saving...</span>
          <span v-else><i class="ri-save-line"></i> Record Loss</span>
        </button>
      </div>
    </div>
  </div>
  </Teleport>
</template>

<script>
import { useForm } from '@inertiajs/vue3';

export default {
  props: {
    inventoryStock: Object,
  },
  emits: ['saved'],
  data() {
    return {
      visible: false,
      form: useForm({
        inventory_stock_id: null,
        items: [{ affected_sacks: null, loss_per_sack: null }],
        reason: '',
        notes: '',
        recorded_at: new Date().toISOString().slice(0, 10),
      }),
    };
  },
  computed: {
    grandTotal() {
      return Math.round(
        this.form.items.reduce((sum, r) => sum + this.rowSubtotal(r), 0) * 100
      ) / 100;
    },
    totalSacks() {
      return this.form.items.reduce((sum, r) => sum + (parseInt(r.affected_sacks) || 0), 0);
    },
  },
  methods: {
    show() {
      this.form.reset();
      this.form.inventory_stock_id = this.inventoryStock?.id ?? null;
      this.form.items = [{ affected_sacks: null, loss_per_sack: null }];
      this.form.recorded_at = new Date().toISOString().slice(0, 10);
      this.visible = true;
    },
    hide() {
      this.visible = false;
    },
    addRow() {
      this.form.items.push({ affected_sacks: null, loss_per_sack: null });
    },
    removeRow(i) {
      if (this.form.items.length > 1) {
        this.form.items.splice(i, 1);
      }
    },
    rowSubtotal(row) {
      const s = parseFloat(row.affected_sacks) || 0;
      const p = parseFloat(row.loss_per_sack) || 0;
      return Math.round(s * p * 100) / 100;
    },
    rowError(i, field) {
      return !!this.form.errors[`items.${i}.${field}`];
    },
    submit() {
      this.form.post(`/inventory-stocks/${this.inventoryStock.id}/weight-loss`, {
        onSuccess: () => {
          this.hide();
          this.$emit('saved');
        },
      });
    },
  },
};
</script>

<style scoped>
.line-items-table {
  border: 1px solid #dee6e3;
  border-radius: 8px;
  overflow: hidden;
}
.line-items-header {
  display: grid;
  grid-template-columns: 1fr 20px 1fr 20px 90px 28px;
  gap: 6px;
  align-items: center;
  background: #edf5f2;
  padding: 0.4rem 0.75rem;
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #527267;
}
.line-item-row {
  display: grid;
  grid-template-columns: 1fr 20px 1fr 20px 90px 28px;
  gap: 6px;
  align-items: center;
  padding: 0.5rem 0.75rem;
  border-top: 1px solid #f0f7f4;
}
.line-op {
  text-align: center;
  font-weight: 700;
  color: #6b8c85;
  font-size: 0.85rem;
}
.line-subtotal {
  font-size: 0.85rem;
  font-weight: 700;
  color: #b91c1c;
  text-align: right;
}
.line-remove-btn {
  width: 24px;
  height: 24px;
  border: 1px solid #e4eeea;
  border-radius: 6px;
  background: #fff;
  color: #94a3b8;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 0.8rem;
  padding: 0;
  transition: all 0.15s;
}
.line-remove-btn:hover:not(:disabled) {
  background: #fee2e2;
  border-color: #fca5a5;
  color: #b91c1c;
}
.line-remove-btn:disabled { opacity: 0.3; cursor: not-allowed; }
.add-row-btn {
  background: none;
  border: 1px dashed #3d8d7a;
  border-radius: 6px;
  color: #3d8d7a;
  font-size: 0.8rem;
  font-weight: 600;
  padding: 0.25rem 0.75rem;
  cursor: pointer;
  transition: all 0.15s;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.add-row-btn:hover { background: #edf5f2; }
.total-loss-banner {
  background: #f8fafc;
  border: 1px dashed #cbd5e1;
  border-radius: 8px;
  padding: 0.6rem 1rem;
}
.total-loss-banner.has-value { background: #fef2f2; border-color: #fca5a5; }
.total-loss-label {
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #64748b;
}
.total-loss-banner.has-value .total-loss-label { color: #b91c1c; }
.total-loss-value { font-size: 0.88rem; }
.spin { display: inline-block; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
