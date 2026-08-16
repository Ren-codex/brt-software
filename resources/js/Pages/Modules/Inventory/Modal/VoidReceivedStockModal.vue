<template>
  <Teleport to="body">
  <div v-if="showModal" class="modal-overlay active" @click.self="hide">
    <div class="modal-container">
      <div class="modal-header">
        <h2>Void Received Stock</h2>
        <button class="close-btn" @click="hide"><i class="ri-close-line"></i></button>
      </div>
      <div class="modal-body">
        <p class="void-warning">
          Voiding <strong>{{ record?.received_no }}</strong> reverses its accounting entries and removes the stock
          it added to inventory. The record is kept for audit — it cannot be undone from here.
        </p>
        <div class="form-group">
          <label class="form-label" for="void-reason">Reason <span class="text-danger">*</span></label>
          <textarea
            id="void-reason"
            v-model="reason"
            class="form-control"
            rows="3"
            placeholder="Why is this received stock being voided?"
          ></textarea>
          <span v-if="error" class="error-message">{{ error }}</span>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-cancel" @click="hide" :disabled="voiding">Cancel</button>
        <button class="btn btn-save" @click="submit" :disabled="voiding">
          {{ voiding ? 'Voiding...' : 'Void Received Stock' }}
        </button>
      </div>
    </div>
  </div>
  </Teleport>
</template>

<script>
export default {
  name: 'VoidReceivedStockModal',
  emits: ['voided', 'toast'],
  data() {
    return {
      showModal: false,
      record: null,
      reason: '',
      error: '',
      voiding: false,
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
    show(record) {
      this.record = record;
      this.reason = '';
      this.error = '';
      this.showModal = true;
    },
    hide(force = false) {
      if (this.voiding && !force) return;
      this.showModal = false;
      this.record = null;
    },
    async submit() {
      if (!String(this.reason || '').trim()) {
        this.error = 'Please enter a reason.';
        return;
      }

      this.voiding = true;
      this.error = '';
      try {
        await axios.delete(`/received-stocks/${this.record.id}`, {
          data: { reason: this.reason },
        });
        this.$emit('toast', 'Received stock voided successfully');
        this.$emit('voided');
        this.hide(true);
      } catch (err) {
        this.error = err?.response?.data?.errors?.received_stock?.[0]
          || err?.response?.data?.errors?.reason?.[0]
          || err?.response?.data?.message
          || 'Unable to void this received stock.';
      } finally {
        this.voiding = false;
      }
    },
  },
};
</script>

<style scoped>
.void-warning {
  font-size: 0.85rem;
  color: #6b5b0f;
  background: #fff8e6;
  border: 1px solid #f2e3ab;
  border-radius: 10px;
  padding: 0.75rem 1rem;
  margin-bottom: 1rem;
}

.error-message {
  color: #e74c3c;
  font-size: 0.8rem;
  display: block;
  margin-top: 0.3rem;
}
</style>
