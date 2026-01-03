<template>
  <div>
    <b-modal
      id="createReceivedStockModal"
      ref="createReceivedStockModal"
      size="lg"
      title="Create Received Stock"
      @hidden="resetForm"
      @ok="handleSubmit"
      ok-title="Save"
      cancel-title="Cancel"
      :ok-disabled="form.items.length === 0"
      persistent
    >
      <form @submit.prevent="handleSubmit">
        <div v-if="errorMessage" class="alert alert-danger" role="alert">
          {{ errorMessage }}
        </div>

        <div class="mb-3">
          <h6>Purchase Order Items</h6>
          <table class="table table-bordered">
            <thead>
              <tr>
                <th>Product</th>
                <th>Ordered</th>
                <th>Received</th>
                <th>To Receive</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, index) in editableItems" :key="item.id">
                <td style="width: 30%">{{ item.product ? item.product.brand.name : 'N/A' }}</td>
                <td>
                  <input
                    type="number"
                    v-model="item.quantity"
                    class="form-control"
                    min="0"
                    step="1"
                    :disabled="item.status !== 'pending'"
                  />
                </td>
                <td>
                  <input
                    type="number"
                    v-model="item.editable_quantity"
                    class="form-control"
                    min="0"
                    step="1"
                    :disabled="item.status !== 'pending'"
                  />
                </td>
                <td>
                  <input
                    type="number"
                    v-model="item.editable_quantity"
                    class="form-control"
                    min="0"
                    step="1"
                    :disabled="item.status !== 'pending'"
                  />
                </td>
                <td style="width: 10%">
                  <div v-if="item.status == 'pending'" class="d-flex">
                    <b-button
                      variant="success"
                      size="sm"
                      class="rounded-circle d-flex align-items-center justify-content-center p-0"
                      style="width: 32px; height: 32px;"
                      @click="addToReceived(index)"
                    >
                      <i class="ri-check-line"></i>
                    </b-button>
                    <b-button
                      variant="danger"
                      size="sm"
                      class="rounded-circle d-flex align-items-center justify-content-center p-0"
                      style="width: 32px; height: 32px;"
                      @click="addToRejected(index)"
                    >
                      <i class="ri-close-line"></i>
                    </b-button>
                  </div>
                  <div v-else>
                    <span>{{ item.status }}</span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </form>
    </b-modal>
  </div>
</template>

<script>
export default {
  name: "CreateReceivedStockModal",
  props: {
    dropdowns: {
      type: Object,
      required: true,
    },
  },
  emits: ['add'],
  data() {
    return {
      editableItems: [],
      form: {
        po_id: '',
        supplier_id: '',
        received_date: '',
        batch_code: '',
        items: [],
      },
      errorMessage: '',
      purchaseOrder: [],
    };
  },
  methods: {
    async getNextBatchCode() {
      try {
        const response = await axios.get('/received-stocks/next-batch-code');
        this.form.batch_code = response.data.batch_code;
      } catch (error) {
        console.error('Error fetching batch code:', error);
      }
    },
    async show(data) {
      this.purchaseOrder = data;
      // Fetch next batch code
      await this.getNextBatchCode();

      // Set form fields
      this.form.po_id = this.purchaseOrder.id;
      this.form.supplier_id = this.purchaseOrder.supplier.id;
      this.form.received_date = new Date().toISOString().split('T')[0]; // Today's date

      // Initialize editable items
      this.editableItems = this.purchaseOrder.items
      .filter(item => item.status === 'pending')
      .map(item => ({
        ...item,
        editable_quantity: item.quantity,
        editable_unit_cost: item.unit_cost,
      }));


      this.$refs.createReceivedStockModal.show();
    },
    edit(data, index) {
      // Implement edit logic if needed
    },
    resetForm() {
      this.editableItems = [];
      this.form = {
        po_id: '',
        supplier_id: '',
        received_date: '',
        batch_code: '',
        items: [],
      };
      this.errorMessage = '';
    },
    addToReceived(index) {
      const item = this.editableItems[index];
      const totalCost = item.editable_quantity * item.editable_unit_cost;
      this.form.items.push({
        product_id: item.product.id,
        quantity: item.editable_quantity,
        unit_cost: item.editable_unit_cost,
        total_cost: totalCost,
        po_item_id: item.id,
      });
      item.status = 'received';
    },
    addToRejected(index) {
      const item = this.editableItems[index];
      item.status = 'not included';
    },
    async handleSubmit() {
      if (this.form.items.length === 0) {
        this.errorMessage = 'Please add at least one item to receive.';
        return;
      }

      try {
        await axios.post('/received-stocks', this.form);
        this.$emit('add', 'success');
      } catch (error) {
        console.error('Error submitting received stock:', error);
        // Optionally, set this.errorMessage based on error response
      } finally {
        this.$refs.createReceivedStockModal.hide();
      }
    },
  },
};
</script>
