<template>
  <Teleport to="body">
  <div
    v-if="showModal"
    class="modal-overlay active"
    @click.self="hide"
  >
    <div class="modal-container" @click.stop>
      <div class="modal-header">
        <h2>Update Prices</h2>
        <button class="close-btn" @click="hide">
          <i class="ri-close-line"></i>
        </button>
      </div>
      <div class="modal-body">
        <div class="success-alert" v-if="saveSuccess">
          <i class="ri-checkbox-circle-fill"></i>
          <span>Your information has been saved successfully!</span>
        </div>
        <form @submit.prevent="savePrices">
          <div class="form-group">
            <label for="wholesale_price" class="form-label">Wholesale Price</label>
            <div class="input-wrapper">
              <i class="ri-stack-line input-icon"></i>
              <input
                type="number"
                id="wholesale_price"
                v-model="form.wholesale_price"
                class="form-control"
                min="0"
                step="0.01"
                placeholder="Enter wholesale price"
                :class="{ 'input-error': wholesalePriceError }"
              />
            </div>
            <span class="error-message" v-if="wholesalePriceError">{{ wholesalePriceError }}</span>
          </div>

          <div class="form-group">
            <label for="retail_price" class="form-label">Retail Price</label>
            <div class="input-wrapper">
              <i class="ri-price-tag-line input-icon"></i>
              <input
                type="number"
                id="retail_price"
                v-model="form.retail_price"
                class="form-control"
                min="0"
                step="0.01"
                placeholder="Enter retail price"
                :class="{ 'input-error': retailPriceError }"
              />
            </div>
            <span class="error-message" v-if="retailPriceError">{{ retailPriceError }}</span>
          </div>

          <div class="form-group">
            <label for="reason" class="form-label">Remarks</label>
            <div class="input-wrapper">
              <textarea
                id="reason"
                v-model="form.reason"
                class="form-control"
                rows="4"
                placeholder="Enter a reason or reason for the price change"
                :class="{ 'input-error': form.errors.reason }"
                @change="handleInput('reason')"
              ></textarea>
            </div>
            <span class="error-message" v-if="form.errors.reason">{{ form.errors.reason }}</span>
          </div>

        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-cancel" @click="hide">
          <i class="ri-close-line"></i>
          Cancel
        </button>
        <button type="button" class="btn btn-save" :disabled="isUnchanged || wholesalePriceError || retailPriceError" @click="savePrices">
          <i class="ri-save-line"></i>
          Save
        </button>
      </div>
    </div>
  </div>
  </Teleport>
</template>

<script>
import { useForm } from '@inertiajs/vue3';

export default {
  name: 'UpdatePriceModal',
  props: {
    inventoryStock: Object,
  },
  data() {
    return {
      form: useForm({
        inventory_stocks_id: '',
        retail_price: null,
        wholesale_price: null,
        previous_retail_price: null,
        previous_wholesale_price: null,
        reason: '',
        previous_reason: '',
      }),
      showModal: false,
      saveSuccess: false,
    };
  },
  computed: {
    unitCost() {
      return parseFloat(this.inventoryStock?.unit_cost || this.inventoryStock?.received_item?.unit_cost || 0);
    },
    wholesalePriceError() {
      const wholesale = parseFloat(this.form.wholesale_price);
      const retail    = parseFloat(this.form.retail_price);
      if (isNaN(wholesale) || wholesale < 0) return 'Must be 0 or greater';
      if (this.unitCost > 0 && wholesale <= this.unitCost)
        return `Must be greater than unit cost (₱${this.unitCost.toLocaleString('en-PH', { minimumFractionDigits: 2 })})`;
      if (!isNaN(retail) && retail > 0 && wholesale >= retail)
        return `Must be less than retail price (₱${retail.toLocaleString('en-PH', { minimumFractionDigits: 2 })})`;
      return null;
    },
    retailPriceError() {
      const retail    = parseFloat(this.form.retail_price);
      const wholesale = parseFloat(this.form.wholesale_price);
      if (isNaN(retail) || retail < 0) return 'Must be 0 or greater';
      if (this.unitCost > 0 && retail <= this.unitCost)
        return `Must be greater than unit cost (₱${this.unitCost.toLocaleString('en-PH', { minimumFractionDigits: 2 })})`;
      if (!isNaN(wholesale) && wholesale > 0 && retail <= wholesale)
        return `Must be greater than wholesale price (₱${wholesale.toLocaleString('en-PH', { minimumFractionDigits: 2 })})`;
      return null;
    },
    isUnchanged() {
      const pricesUnchanged = (
        parseFloat(this.form.retail_price) === parseFloat(this.form.previous_retail_price) &&
        parseFloat(this.form.wholesale_price) === parseFloat(this.form.previous_wholesale_price)
      );
      const reasonUnchanged = (this.form.reason === '' || this.form.reason === this.form.previous_reason);
      return pricesUnchanged && reasonUnchanged;
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
    show() {
      this.form.reset();
      this.saveSuccess = false;
      this.showModal = true;
      this.form.inventory_stocks_id = this.inventoryStock ? this.inventoryStock.id : null;
      this.form.retail_price = this.inventoryStock && this.inventoryStock.retail_price !== null ? this.inventoryStock.retail_price : 0;
      this.form.wholesale_price = this.inventoryStock && this.inventoryStock.wholesale_price !== null ? this.inventoryStock.wholesale_price : 0;
      this.form.previous_retail_price = this.form.retail_price;
      this.form.previous_wholesale_price = this.form.wholesale_price;
      this.form.reason = this.inventoryStock && this.inventoryStock.reason ? this.inventoryStock.reason : '';
      this.form.previous_reason = this.form.reason;
    },
    savePrices() {
      if (this.wholesalePriceError || this.retailPriceError) return;
      this.form.patch(`/inventory-stocks/${this.form.inventory_stocks_id}`, {
        preserveScroll: true,
        onSuccess: (response) => {
          this.saveSuccess = true;
          const payload = {
            inventory_stocks_id: this.form.inventory_stocks_id,
            retail_price: parseFloat(this.form.retail_price) || 0,
            wholesale_price: parseFloat(this.form.wholesale_price) || 0,
            reason: this.form.reason || '',
          };
          this.form.reset();
          setTimeout(() => {
            this.$emit('saved', payload);
            this.hide();
          }, 1000);
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
  },
};
</script>
