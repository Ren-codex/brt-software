<template>
  <tr>
    <td :colspan="colspan" class="text-center py-4">
      <div class="table-loading-spinner"></div>
      <p class="mt-2 mb-0 text-muted">{{ message }}</p>
    </td>
  </tr>
</template>

<script>
export default {
  name: "TableLoadingRow",
  props: {
    colspan: {
      type: Number,
      required: true,
    },
    message: {
      type: String,
      default: "Loading...",
    },
  },
};
</script>

<style scoped>
.table-loading-spinner {
  width: 36px;
  height: 36px;
  border: 3px solid #d7e5de;
  border-top-color: #3d8d7a;
  border-radius: 50%;
  margin: 0 auto 1rem;
  animation: table-loading-spin 0.8s linear infinite;
}

@keyframes table-loading-spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
