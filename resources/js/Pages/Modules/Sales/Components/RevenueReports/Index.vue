<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5>Revenue Reports</h5>
    </div>

    <div class="table-responsive">
      <table class="table table-striped">
        <thead>
          <tr>
            <th>Product</th>
            <th>Quantity Sold</th>
            <th>Unit Cost</th>
            <th>Retail Price</th>
            <th>Wholesale Price</th>
            <th>Income</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="report in reports" :key="report.product_name">
            <td>{{ report.product_name }}</td>
            <td>{{ report.quantity }}</td>
            <td>{{ report.unit_cost }}</td>
            <td>{{ report.retail_price }}</td>
            <td>{{ report.wholesale_price }}</td>
            <td>{{ report.income }}</td>
          </tr>
          <tr v-if="!loading && reports.length === 0">
            <td colspan="6" class="text-center">No revenue reports found.</td>
          </tr>
          <tr v-if="loading">
            <td colspan="6" class="text-center">Loading...</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
export default {
  props: ['dropdowns'],
  data() {
    return {
      reports: [],
      loading: false,
    };
  },
  mounted() {
    this.fetchReports();
  },
  methods: {
    fetchReports() {
      this.loading = true;
      axios
        .get('/api/revenue-reports')
        .then((response) => {
          this.reports = response.data || [];
          this.loading = false;
        })
        .catch((error) => {
          console.error('Error fetching revenue reports:', error);
          // Do not use hardcoded sample data; show empty table instead
          this.reports = [];
          this.loading = false;
        });
    }
  }
};
</script>
