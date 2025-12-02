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

                        <div class="form-group form-group-half">
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
                    </div>
                    
                

                    <div class="text-end">
                        <b-button @click="addItem()" size="sm" variant="secondary" class="mb-2">Add Item</b-button>
                    </div>
                    <div class="form-row">
                        <table class="table align-middle table-striped table-centered mb-0">
                              <thead class="table-light thead-fixed">
                                  <tr class="fs-11">
                                      <th style="width: 3%;">#</th>
                                     <th style="width: 10%;" class="text-center">Brand</th>
                                     <th style="width: 12%;" class="text-center">Quantity/Unit Price</th>
                                      <th style="width: 10%;" class="text-center">Unit Price</th>
                                      <th style="width: 10%;" class="text-center">Total Amount</th>
                                      <th style="width: 6%;" class="text-center">Actions</th>
                                  </tr>
                              </thead>

                              <tbody class="table-white fs-12">
                                  <tr v-for="(list,index) in form.items" v-bind:key="index" @click="selectRow(index)" :class="{
                                      'bg-info-subtle': index === selectedRow
                                  }">
                                    <td class="text-center">
                                        {{ index + 1}}
                                    </td>
                                    <td class="text-center">{{ getBrand(list.brand_id) }}</td>
                                    <td class="text-center">{{ list.quantity }} {{ getUnit(list.unit_id) }}</td>
                                    <td class="text-center">{{ formatCurrency(list.unit_cost) }} </td>
                                    <td class="text-center">{{ formatCurrency(list.quantity * list.unit_cost) }}</td>
                                    

                                      <td class="text-end">
                                          <div class="d-flex justify-content-end gap-1">
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

       <Item @add="fetch()" :dropdowns="dropdowns"  @items="storeItem" ref="item"/>
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
                payment_mode: null,
                order_date: new Date().toISOString().slice(0, 10),  // current date
                customer_id: null,
                status_id: null,
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
                id: Date.now() // temporary unique ID
            };
            this.form.items.push(newItem);
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
            this.form.payment_mode = data.payment_mode;
            this.form.order_date = data.order_date;
            this.form.customer_id = data.customer_id;
            this.form.amount = data.amount;
            this.form.status_id = data.status_id;
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


        addItem(){
            this.$refs.item.show();
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
        }


        
    }
}
</script>

<style scoped>
.modal-container {
    max-width: 70%;      /* up to 90% of viewport width */
    width: 100%;         /* full width inside max-width */
    max-height: 90vh;    /* optional: prevent it from overflowing viewport height */
    overflow-y: auto;    /* allow scrolling if content is too tall */    /* adjust inner spacing */
}
</style>
