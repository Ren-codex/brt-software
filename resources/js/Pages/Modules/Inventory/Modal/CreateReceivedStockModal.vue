<template>
  <div v-if="showModal" class="modal-overlay active" @click.self="onCancel">
    <div class="modal-container modal-xl">
      <div class="modal-header">
        <h2>Create Received Stock</h2>
        <button class="close-btn" @click="onCancel">&times;</button>
      </div>
      <div class="modal-body">
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
                  <th>Retail Price</th>
                  <th>Wholesale Price</th>
                  <th>Expiration Date</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, index) in form.items" :key="item.id">
                  <td style="width: 30%" class="bg-faded-gray">{{ item.product ? item.product.name : 'N/A' }}</td>
                  <td class="bg-faded-gray">{{ item.quantity }}</td>
                  <td class="bg-faded-gray">{{ item.received_quantity}}</td>
                  <td style="width: 15%">
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
                  <td style="width: 15%">
                    <input
                      type="number"
                      v-model="item.retail_price"
                      class="form-control"
                      min="0"
                      step="0.01"
                      :disabled="item.status !== 'pending'"
                    />
                  </td>
                  <td style="width: 15%">
                    <input
                      type="number"
                      v-model="item.wholesale_price"
                      class="form-control"
                      min="0"
                      step="0.01"
                      :disabled="item.status !== 'pending'"
                    />
                  </td>
                  <td>
                    <input
                      type="date"
                      v-model="item.expiration_date"
                      class="form-control"
                      :disabled="item.status !== 'pending'"
                    />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </form>
        <div class="form-actions">
          <button class="btn btn-cancel" @click="onCancel">Cancel</button>
          <button class="btn btn-save" @click="handleSubmit()" :disabled="form.processing">Submit</button>
        </div>
      </div>
    </div>
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
      showModal: false,
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
        to_received_quantity: item.quantity - item.received_quantity,
        retail_price: 0,
        wholesale_price: 0,
        expiration_date: null,
      }));


      this.showModal = true;
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
          this.hide();
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
      this.showModal = false;
      this.resetForm();
    },
    onCancel() {
      this.hide();
    }
  },
};
</script>
<style scoped>
.bg-faded-gray {
  background-color: #f8f8f8;
}
</style>