<template>
  <div>
    <b-modal
      id="createReceivedStockModal"
      ref="createReceivedStockModal"
      size="lg"
      title="Create Received Stock"
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
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, index) in form.items" :key="item.id">
                <td style="width: 30%">{{ item.product ? item.product.name : 'N/A' }}</td>
                <td>{{ item.quantity }}</td>
                <td>{{ item.received_quantity}}</td>
                <td>
                  <input
                    type="number"
                    v-model="item.to_received_quantity"
                    :class="['form-control', { 'is-invalid': item.to_received_quantity > item.quantity - item.received_quantity }]"
                    min="0"
                    :max="item.quantity - item.received_quantity"
                    step="1"
                    :disabled="item.status !== 'pending'"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </form>
      <template v-slot:footer>
          <b-button @click="hide()" variant="light" block>Cancel</b-button>
          <b-button @click="handleSubmit()" variant="primary" :disabled="form.processing" block>Submit</b-button>
      </template>
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
      form: {
        po_id: '',
        supplier_id: '',
        items: [],
      },
      errorMessage: '',
      purchaseOrder: [],
    };
  },
  methods: {
    async show(data) {
      this.purchaseOrder = data;
      this.form.po_id = this.purchaseOrder.id;
      this.form.supplier_id = this.purchaseOrder.supplier.id;
      this.form.items = this.purchaseOrder.items
      .filter(item => item.status === 'pending')
      .map(item => ({
        ...item,
        po_item_id: item.id,
        product_id: item.product.id,
        product_name: item.product?.name,
        to_received_quantity: 0,
      }));


      this.$refs.createReceivedStockModal.show();
    },
    resetForm() {
      this.form = {
        po_id: '',
        supplier_id: '',
        items: [],
      };
      this.errorMessage = '';
    },
    async handleSubmit() {
      // Validate to_received_quantity does not exceed quantity - received_quantity
      for (const item of this.form.items) {
        const maxAllowed = item.quantity - item.received_quantity;
        if (item.to_received_quantity > maxAllowed) {
          this.errorMessage = `To receive quantity for ${item.product ? item.product.brand.name : 'N/A'}, must not exceed ${maxAllowed}.`;
          return; // Prevent submission
        }
      }

      this.errorMessage = ''; // Clear any previous error

      try {
        await axios.post('/received-stocks', this.form)
        .then(response => {
          this.$refs.createReceivedStockModal.hide();
          this.$emit('add', 'success');
          this.$inertia.reload();
          this.resetForm();
        });
      } catch (error) {
        console.error('Error submitting received stock:', error);
        this.errorMessage = 'An error occurred while submitting. Please try again.';
      }
    },
    hide() {
      this.$refs.createReceivedStockModal.hide();
      this.resetForm();
    }
  },
};
</script>
