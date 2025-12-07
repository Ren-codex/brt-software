<template>
  <div
    v-if="showModal"
    class="modal-overlay"
    :class="{ active: showModal }"
    @click.self="hide"
  >
    <div class="modal-container" @click.stop>
      <div class="modal-header">
        <h2>Adjust Stock Quantity</h2>
        <button class="close-btn" @click="hide">
          <i class="ri-close-line"></i>
        </button>
      </div>
      <div class="modal-body">
        <div class="success-alert" v-if="saveSuccess">
          <i class="ri-checkbox-circle-fill"></i>
          <span>Your information has been saved successfully!</span>
        </div>
        <form @submit.prevent="saveQuantity">
          <div class="form-group">
            <label for="new_quantity" class="form-label">New Quantity</label>
            <div class="input-wrapper">
              <i class="ri-inbox-unarchive-line input-icon"></i>
              <input
                type="number"
                id="new_quantity"
                v-model="form.new_quantity"
                class="form-control"
                min="0"
                placeholder="Enter new quantity"
                :class="{ 'input-error': form.errors.new_quantity }"
                @change="handleInput('new_quantity')"
              >
            </div>
            <span class="error-message" v-if="form.errors.new_quantity">{{ form.errors.new_quantity }}</span>
          </div>
          <div class="form-group">
            <label for="reason" class="form-label">Reason for Adjustment</label>
            <div class="input-wrapper">
              <textarea
                id="reason"
                v-model="form.reason"
                class="form-control"
                placeholder="Enter reason for stock adjustment"
                rows="5"
              ></textarea>
            </div>
          </div>
          <div class="form-actions">
            <button type="button" class="btn btn-cancel" @click="hide">
              <i class="ri-close-line"></i>
              Cancel
            </button>
            <button type="submit" class="btn btn-save" :disabled="form.new_quantity == form.previous_quantity">
              <i class="ri-save-line"></i>
              Save
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { useForm } from '@inertiajs/vue3';

export default {
  name: 'AdjustStockModal',
  props: {
    inventoryStock: Object,
  },
  data() {
    return {
      form: useForm({
        inventory_stocks_id: '',
        new_quantity: '',
        previous_quantity: '',
        reason: '',
      }),
      showModal: false,
      saveSuccess: false,
    };
  },
  methods: {
    show() {
        this.form.reset();
        this.saveSuccess = false;
        this.showModal = true;
        this.form.inventory_stocks_id = this.inventoryStock ? this.inventoryStock.id : null;
        this.form.new_quantity = this.inventoryStock ? this.inventoryStock.quantity : 0;
        this.form.previous_quantity = this.inventoryStock ? this.inventoryStock.quantity : 0;
    },
    saveQuantity() {
      if (this.new_quantity < 0) {
        alert('Quantity cannot be negative.');
        return;
      }
      this.form.post(`/inventory-stocks/adjustment/${this.form.inventory_stocks_id}`, {
          preserveScroll: true,
          onSuccess: (response) => {
              this.saveSuccess = true;
              this.form.reset();
              setTimeout(() => {
                  this.$emit('saved', true);
                  this.hide();
              }, 1500);
          },
      });
    },
    hide() {
        this.form.reset();
        this.form.clearErrors();
        this.saveSuccess = false;
        this.showModal = false;
    },
    handleInput(field) {
        this.form.errors[field] = false;
    },
  }
};
</script>
