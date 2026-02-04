<template>
  <b-modal v-model="showModal" title="Payroll Setting Logs" size="lg" @hide="$emit('close')">
    <div v-if="selectedSetting && selectedSetting.logs && selectedSetting.logs.length">
      <div class="table-responsive">
        <table class="table table-striped">
          <thead>
            <tr>
              <th>#</th>
              <th>Changed Data</th>
              <th>Updated By</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(log, index) in selectedSetting.logs" :key="log.id">
              <td>{{ index + 1 }}</td>
              <td>{{ log.changed_data }}</td>
              <td>{{ log.updated_by ? log.updated_by.name : 'Unknown' }}</td>
              <td>{{ new Date(log.created_at).toLocaleString() }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    <div v-else>
      <p>No logs available for this setting.</p>
    </div>
    <template #modal-footer>
      <b-button variant="secondary" @click="$emit('close')">Close</b-button>
    </template>
  </b-modal>
</template>

<script>
export default {
  props: {
    modelValue: Boolean,
    selectedSetting: Object
  },
  emits: ['update:modelValue', 'close'],
  computed: {
    showModal: {
      get() {
        return this.modelValue;
      },
      set(value) {
        this.$emit('update:modelValue', value);
      }
    }
  }
}
</script>
