<template>
    <div 
        v-if="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"
        
    >
        <div class="modal-container">
            <div class="modal-header">
                <h2>{{ editable ? 'Update Supplier' : 'Add Sales Order' }}</h2>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body">
                <form @submit.prevent="submit">
                            <div class="form-row">
                                <div class="form-group form-group-half">
                                    <label for="batch_code" class="form-label">Batch Code</label>
                                    <div class="input-wrapper">
                                        <i class="ri-code-line input-icon"></i>
                                        <!-- <b-form-select
                                            v-model="form.batch_code"
                                            :options="dropdowns.batch_codes"
                                            text-field="code"
                                            value-field="value"
                                            :class="{ 'input-error': form.errors.batch_code }"
                                            class="form-control"

                                        >
                                          <template #first>
                                            <b-form-select-option :value="null" disabled>Select Batch Code</b-form-select-option>
                                        </template> -->
                                        <Multiselect 
                                        v-model="form.batch_code"
                                        :options="dropdowns.batch_codes"
                                        label="code"
                                        mode="tags"
                                        placeholder="Select type" />
                                        <!-- </b-form-select> -->

                                    </div>

                                    <span class="error-message" v-if="form.errors.batch_code">{{ form.errors.batch_code }}</span>
                                </div>


                                <div class="form-group form-group-half">
                                    <label for="order_date" class="form-label">Order Date</label>
                                    <div class="input-wrapper">
                                        <i class="ri-calendar-line input-icon"></i>
                                        <text-input
                                            type="date"
                                            id="name"
                                            v-model="form.order_date"
                                            class="form-control"
                                            :class="{ 'input-error': form.errors.order_date }"
                                            @input="handleInput('order_date')"
                                        />
                                    </div>
                                    <span class="error-message" v-if="form.errors.order_date">{{ form.errors.order_date }}</span>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group form-group-half">
                                    <label for="customer_id" class="form-label">Customer</label>
                                    <div class="input-wrapper">
                                        <i class="ri-user-line input-icon"></i>
                                        <b-form-select
                                            v-model="form.customer_id"
                                            :options="dropdowns.customers"
                                            text-field="name"
                                            value-field="value"
                                            :class="{ 'input-error': form.errors.customer_id }"
                                            class="form-control"

                                        >
                                          <template #first>
                                            <b-form-select-option :value="null" disabled>Select Customer</b-form-select-option>
                                        </template>
                                        </b-form-select>

                                    </div>

                                    <span class="error-message" v-if="form.errors.customer_id">{{ form.errors.customer_id }}</span>
                                </div>

                                 <div   class="form-group form-group-half">
                                    <label for="payment_mode" class="form-label">Payment Mode</label>
                                    <div class="input-wrapper">
                                        <i class="ri-money-dollar-circle-line input-icon"></i>
                                        <b-form-select
                                        class="form-control"
                                        v-model="form.payment_mode"
                                        :options="paymentModes"
                                        :class="{ 'input-error': form.errors.payment_mode }"
                                        label="name"
                                        >
                                         <template #first>
                                            <b-form-select-option :value="null" disabled  >Select Mode</b-form-select-option>
                                        </template>
                                        </b-form-select>
                                    </div>
                                    <span class="error-message" v-if="form.errors.payment_mode">{{ form.errors.payment_mode }}</span>
                                </div>
                            </div>

                            <div class="form-row" v-if="form.payment_mode">

                                <div class="form-group form-group-half" v-if="form.payment_mode !== 'Cash'">
                                    <label for="billing_account" class="form-label">Billing Account</label>
                                    <div class="input-wrapper">
                                        <i class="ri-bank-card-line input-icon"></i>
                                        <text-input
                                            type="text"
                                            id="billing_account"
                                            v-model="form.billing_account"
                                            class="form-control"
                                            :class="{ 'input-error': form.errors.billing_account }"
                                            @input="handleInput('billing_account')"
                                            placeholder="Enter Billing Account"
                                        />
                                    </div>
                                    <span class="error-message" v-if="form.errors.billing_account">{{ form.errors.billing_account }}</span>
                                </div>
                            </div>


                            <div class="text-end">
                                <b-button :disabled="!form.customer_id || !form.payment_mode || !form.order_date" @click="addItem()" size="sm" variant="secondary" class="mb-2">Add Item</b-button>
                            </div>
                            <div class="form-row">
                                <table class="table align-middle table-striped table-centered mb-0">
                                      <thead class="table-light thead-fixed">
                                          <tr class="fs-11">
                                              <th style="width: 3%;">#</th>
                                             <th style="width: 10%;" class="text-center">Brand</th>
                                             <th style="width: 12%;" class="text-center">Quantity/Unit</th>
                                              <th style="width: 10%;" class="text-center">Unit Price</th>
                                              <th style="width: 8%;" class="text-center">Discount (₱)</th>
                                              <th style="width: 10%;" class="text-center">Total Amount</th>
                                              <th style="width: 6%;" class="text-center">Actions</th>
                                          </tr>
                                      </thead>

                                      <tbody class="table-white fs-12">
                                          <tr v-for="(list,index) in form.items" v-bind:key="index" @click="selectRow(index)" :class="{
                                              'bg-info-subtle': index === selectedRow
                                          }">
                                            <td >
                                                {{ index + 1}}
                                            </td>
                                            <td class="text-center">{{ getBrand(list.brand_id) }}</td>
                                            <td class="text-center">{{ list.quantity }} {{ getUnit(list.unit_id) }}</td>
                                            <td class="text-center">{{ formatCurrency(list.unit_cost) }} </td>
                                            <td class="text-center">
                                                <input
                                                    type="number"
                                                    v-model="list.discount_per_unit"
                                                    class="form-control form-control-sm text-center"
                                                    min="0"
                                                    step="0.01"
                                                    placeholder="0.00"
                                                    @input="updateItemTotal(index)"
                                                />
                                            </td>
                                            <td class="text-center">{{ formatCurrency(calculateItemTotal(list)) }}</td>


                                              <td class="text-center">
                                                  <div class="d-flex justify-content-center gap-1">
                                                      <b-button @click="editItem(list,index)" variant="info" v-b-tooltip.hover title="Edit" size="sm" class="btn-icon">
                                                          <i class="ri-pencil-fill"></i>
                                                      </b-button>
                                                      <b-button @click="removeItem(list.id)" variant="danger" v-b-tooltip.hover title="Delete" size="sm" class="btn-icon">
                                                          <i class="ri-delete-bin-line"></i>
                                                      </b-button>
                                                  </div>
                                              </td>
                                          </tr>
                                      </tbody>
                                  </table>
                            </div>

                            <div class="success-alert" v-if="saveSuccess">
                                <i class="ri-checkbox-circle-fill"></i>
                                <span>Your information has been saved successfully!</span>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn btn-cancel" @click="hide">
                                    <i class="ri-close-line"></i>
                                    Cancel
                                </button>
                                <button type="submit" class="btn btn-save" :disabled="form.processing">
                                    <i class="ri-save-line" v-if="!form.processing"></i>
                                    <i class="ri-loader-4-line spinner" v-else></i>
                                    {{ form.processing ? 'Saving...' : 'Save Order' }}
                                </button>
                            </div>

                        </form>
            </div>
        </div>
    </div>

       <Item @add="fetch()" :dropdowns="dropdowns"  @items="storeItem" @update="updateItem" :formatCurrency="formatCurrency()" ref="item"/>
</template>

<script>
import { useForm } from '@inertiajs/vue3';
import InputLabel from '@/Shared/Components/Forms/InputLabel.vue';
import TextInput from '@/Shared/Components/Forms/TextInput.vue';
import Multiselect from '@/Shared/Components/Forms/Multiselect.vue';
import Amount from '@/Shared/Components/Forms/Amount.vue';
import Item from '@/Pages/Modules/Sales/Components/SalesOrders/Modals/AddItem.vue';

export default {
    components: { InputLabel, TextInput, Multiselect, Amount , Item},
    props: ['dropdowns'],
    data() {
        return {
            currentUrl: window.location.origin,
            form: useForm({
                id: null,
                batch_code: null,
                payment_mode: null,
                order_date: new Date().toISOString().slice(0, 10),  // current date
                customer_id: null,
                status_id: null,
                or_release: null,
                billing_account: null,
                items: [],
                option: 'lists'
            }),

            paymentModes: ['Cash', 'Credit Card', 'Debit Card','Bank Transfer'],
            togglePassword: false,
            toggleConfirm: false,
            passwordMismatch: false,
            showModal: false,
            editable: false,
            saveSuccess: false,
            selectedRow: null,
        }
    },
    methods: { 
        storeItem(item) {
            const newItem = {
                ...item,
                id: Date.now(), // temporary unique ID
                discount_per_unit: 0
            };
            this.form.items.push(newItem);
        },

        updateItem(updatedItem) {
            const index = this.form.items.findIndex(item => item.id === updatedItem.id);
            if (index !== -1) {
                this.form.items.splice(index, 1, updatedItem);
            }
        },

        addItem(){
            this.$refs.item.show();
        },

        editItem(item, index ) {
            this.$refs.item.edit(item, index);
        },

        removeItem(id) {
            this.form.items = this.form.items.filter(item => item.id !== id);
        },


        show() {
            this.form.reset();
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = true;
        },
        edit(data, index) {
            this.form.id = data.id;
            this.form.batch_code = data.received?.id;
            this.form.payment_mode = data.payment_mode;
            this.form.order_date = data.order_date;
            this.form.customer_id = data.customer?.id;
            this.form.amount = data.amount;
            this.form.status_id = data.status_id;
            this.form.discount_percentage = data.discount_percentage || 0;
            this.form.discount_amount = data.discount_amount || 0;
            this.form.items = data.items.map(item => ({
                id: item.id || Date.now(), // ensure each item has a unique ID
                brand_id: item.brand_id,
                quantity: item.quantity,
                unit_cost: item.unit_cost,
                unit_id: item.unit_id,
                discount_per_unit: item.discount_per_unit || 0,
            }));
            console.log(data.items , 33);
            this.editable = true;
            this.saveSuccess = false;
            this.showModal = true;
        },
        submit() {
            if (this.editable) {
                this.form.put(`/sales-orders/${this.form.id}`, {
                    preserveScroll: true,
                    onSuccess: (response) => {
                        this.saveSuccess = true;
                        setTimeout(() => {
                            this.$emit('add', true);
                            this.form.reset();
                            this.hide();
                        }, 1500);
                    },
                });
            } else {
                this.form.post('/sales-orders', {
                    preserveScroll: true,
                    onSuccess: (response) => {
                        this.saveSuccess = true;
                        setTimeout(() => {
                            this.$emit('add', true);
                            this.form.reset();
                            this.hide();
                        }, 1500);
                    },
                });
            }
        },
        handleInput(field) {
            this.form.errors[field] = false;
        },
        hide() {
            this.form.reset();
            this.form.clearErrors();
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = false;
        },


    


        selectRow(index) {
            this.selectedRow = this.selectedRow === index ? null : index;
        },


        getBrand(brand_id){
            const brand = this.dropdowns.brands.find(b => b.value === brand_id);
            return brand ? brand.name : '';
        },

        getUnit(unit_id){
            const unit = this.dropdowns.units.find(u => u.value === unit_id);
            return unit ? unit.name : '';
        },

        formatCurrency(value) {
            if (!value) return '₱0.00';
            return '₱' + Number(value).toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        },

        calculateItemTotal(item) {
            const discount = parseFloat(item.discount_per_unit) || 0;
            const unitCost = parseFloat(item.unit_cost) || 0;
            const quantity = parseFloat(item.quantity) || 0;
            const discountedUnitCost = Math.max(0, unitCost - discount);
            return discountedUnitCost * quantity;
        },

        updateItemTotal(index) {
            // Force reactivity update for the item total
            this.$set(this.form.items, index, { ...this.form.items[index] });
        }


    }
}
</script>

<style scoped>
.modal-container {
    max-width: 70%;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
}
</style>
