<template>
  <Teleport to="body">
  <div v-if="showModal" class="modal-overlay active" @click.self="hide">
    <div class="modal-container">
      <div class="modal-header">
        <h2>{{ isApproved ? 'Void Stock Return' : 'Delete Stock Return' }}</h2>
        <button class="close-btn" @click="hide"><i class="ri-close-line"></i></button>
      </div>
      <div class="modal-body">
        <p class="void-warning" v-if="isApproved">
          Voiding <strong>{{ record?.stock_return_no || `SR-${record?.id}` }}</strong> reverses its accounting entries
          and restores the inventory it deducted. The record is kept for audit — it cannot be undone from here.
        </p>
        <p class="void-warning" v-else>
          This stock return hasn't been approved yet, so nothing has affected inventory or the books.
          Deleting <strong>{{ record?.stock_return_no || `SR-${record?.id}` }}</strong> removes it entirely and cannot be undone.
        </p>
        <div class="form-group">
          <label class="form-label" for="void-reason">Reason <span class="text-danger">*</span></label>
          <textarea
            id="void-reason"
            v-model="reason"
            class="form-control"
            rows="3"
            :placeholder="isApproved ? 'Why is this stock return being voided?' : 'Why is this stock return being deleted?'"
          ></textarea>
          <span v-if="error" class="error-message">{{ error }}</span>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-cancel" @click="hide" :disabled="voiding">Cancel</button>
        <button class="btn btn-save" @click="submit" :disabled="voiding">
          {{ voiding ? 'Saving...' : (isApproved ? 'Void Stock Return' : 'Delete Stock Return') }}
        </button>
      </div>
    </div>
  </div>
  </Teleport>
</template>

<script>
export default {
  name: 'VoidStockReturnModal',
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
  computed: {
    isApproved() {
      return String(this.record?.status?.slug || '').toLowerCase() === 'approved';
    },
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
        const recordId = this.record.id;
        const response = await axios.patch(`/stock-returns/${recordId}/void`, {
          reason: this.reason,
        });
        this.$emit('toast', response?.data?.message || 'Stock return updated successfully');
        this.$emit('voided', { id: recordId, deleted: Boolean(response?.data?.deleted) });
        this.hide(true);
      } catch (err) {
        this.error = err?.response?.data?.errors?.items?.[0]
          || err?.response?.data?.errors?.reason?.[0]
          || err?.response?.data?.message
          || 'Unable to process this stock return.';
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
