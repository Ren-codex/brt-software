<template>
  <div v-if="show" class="modal-overlay" :class="{ active: show }" @click.self="close">
    <div class="modal-container modal-sm" @click.stop>
      <div class="modal-header">
        <h3>Add Incentive</h3>
        <button class="close-btn" @click="close">
          <i class="ri-close-line"></i>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Employee</label>
          <div>{{ employee ? employee.name : '' }}</div>
        </div>
        <div class="form-group">
          <label class="form-label">Incentive</label>
          <input type="number" v-model.number="localIncentive" class="form-control" min="0" step="0.01">
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-cancel" @click="close">Cancel</button>
          <button type="button" class="btn btn-save" @click="save">Save</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    show: { type: Boolean, default: false },
    employee: { type: Object, default: null },
    incentive: { type: [Number, String], default: 0 }
  },
  data() {
    return { localIncentive: Number(this.incentive || 0) }
  },
  watch: {
    show(val) {
      if (val) this.localIncentive = Number(this.incentive || 0)
    },
    incentive(val) {
      this.localIncentive = Number(val || 0)
    }
  },
  methods: {
    close() {
      this.$emit('close')
    },
    save() {
      this.$emit('save', Number(this.localIncentive || 0))
    }
  }
}
</script>
