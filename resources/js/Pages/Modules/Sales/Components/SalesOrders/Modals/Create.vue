<template>
    <div 
        v-if="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"
        
    >
        <div class="modal-container modal-xl " @click.stop>
            <div class="modal-header ">
                <h2>{{ editable ? 'Update Supplier' : 'Add Sales Order' }}</h2>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body modal-body-lg">
               
                <form @submit.prevent="submit">
                    <BRow>
                     <div class="col-md-9">
                            <div class="form-row">
                                <div class="form-group form-group-half">
                                        <label for="order_date" class="form-label">Order Date<span class="text-danger">*</span></label>
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
                                    <label for="customer_id" class="form-label">Customer<span class="text-danger">*</span></label>
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

                                 <div class="form-group">
                                    <div class="input-wrapper ">
                                        <label for="customer_id" class="form-label text-center">New</label>
                                        <button @click="addCustomer()" type="button" class="btn btn-cancel form-control">
                                            <i class="ri-add-line"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
           
                            <div class="form-row">
                                <div style="height: calc(100vh - 400px);  overflow: auto; width: 100%;">

                                <table class="table align-middle table-striped table-centered mb-0 ">
                                      <thead class="table-light thead-fixed">
                                          <tr class="fs-11">
                                              <th style="width: 3%;">#</th>
                                              <th style="width: 15%;" >Product</th>
                                              <th style="width: 10%; text-align: center;"  >Batch Code</th>
                                              <th style="width: 12%;" class="text-center">Unit</th>
                                             <th style="width: 5%;" class="text-center">Quantity</th>
                                              <th style="width: 12%;" class="text-center">Price</th>
                                                <th style="width: 15%;" class="text-center">Total Cost</th>
                                              <th style="width: 10%;" class="text-center">Discount</th>
                                              <th style="width: 15%;" class="text-center">Total Discount </th>
                                              <th style="width: 10%;" class="text-center">Actions</th>
                                          </tr>
                                      </thead>

                                      <tbody class="table-white fs-12">
                                          <tr v-for="(list,index) in form.items" v-bind:key="index" @click="selectRow(index)" :class="{
                                              'bg-info-subtle': index === selectedRow
                                          }">
                                            <td >
                                                {{ index + 1}}
                                            </td>
                                            <td >
                                                <b-form-select
                                                    v-model="list.product_id"
                                                    :options="availableProducts"
                                                    value-field="value"
                                                    text-field="name"
                                                    size="sm"
                                                    :class="{ 'input-error': form.errors['items.' + index + '.product_id'] }"
                                                    @change="onProductChange(index)"
                                                >
                                                    <template #first>
                                                        <b-form-select-option :value="null" disabled>
                                                            Select Product
                                                        </b-form-select-option>
                                                    </template>
                                                </b-form-select>
                                            </td>
                                            <td class="text-center">
                                               <span >
                                                    {{ getProduct(list.product_id).batch_code || '-' }}
                                               </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="text-center">
                                                    <div class="fw-bold">{{ getProduct(list.product_id).name || '-' }}</div>
                                                    <small class="text-muted">Available: {{ getProduct(list.product_id).available || 0 }}</small>
                                                </div>
                                            </td>
                                      
                                            <td class="text-center">
                                                <input 
                                                    class="text-center"
                                                    type="number" 
                                                    v-model.number="list.quantity" 
                                                    :class="{ 'input-error': form.errors['items.' + index + '.quantity'] }"
                                                    @input="updateItemTotal(index)"
                                                />
                                            </td>
                                      
                                            <td class="text-center">
                                                {{ formatCurrency(getProduct(list.product_id).price) }}
                                            </td>
                                            <td class="text-center">{{ formatCurrency(calculateItemTotal(list)) }}</td>
                                            <td class="text-center">
                                                <input
                                                    type="number"
                                                    min="0"
                                                    max="100"
                                                    step="10"
                                                    v-model.number="list.discount_per_unit"
                                                    @input="updateItemTotal(index)"
                                                    class="text-end"
                                                    style="width: 80px;"
                                                />
                                            </td>
                                            <td class="text-center">{{ formatCurrency(calculateDiscountedTotal(list)) }}</td>
                                              <td class="text-center">
                                                  <div class="d-flex justify-content-center gap-1">
                                                      
                                                      <b-button @click="removeItem(list.id)" variant="danger" v-b-tooltip.hover title="Delete" size="sm" class="btn-icon">
                                                          <i class="ri-delete-bin-line"></i>
                                                      </b-button>
                                                  </div>
                                              </td>
                                          </tr>
                                      </tbody>
                                  </table>
                                </div>
                            </div>
                    </div>
                    <div class="col-md-3">
                        <b-card class="bg-light p-1 rounded">
                            <h5 class="fw-bolder text-primary"> <i class="ri-user-line"></i> Customer Information</h5>
                            <div v-if="form.customer_id" class="mt-1">
                                <ul class="list-unstyled">
                                    <li><strong>Name:</strong> {{ getCustomer(form.customer_id)?.name || '-' }}</li>
                                    <li><strong>Address:</strong> {{ getCustomer(form.customer_id)?.address || '-' }}</li>
                                    <li><strong>Contact:</strong> {{ getCustomer(form.customer_id)?.contact_number || '-' }}</li>
                                    <li><strong>Email:</strong> {{ getCustomer(form.customer_id)?.email || '-' }}</li>
                                </ul>
                            </div>
                            <div v-else class="mt-3 text-muted">
                                <p class="text-center">Select a customer to view information</p>
                            </div>
                        </b-card>
                   
                         <b-card class=" bg-light mt-4 p-3 rounded">
                                <div class="form-group form-group-half mt-0" >
                                    <label class="form-label text-primary">Order Summary</label>
                                    <div class="input-wrapper">
                                        <div class="d-flex justify-content-between">
                                            <span>Subtotal:</span>
                                            <span>{{ formatCurrency(form.items.reduce((total, item) => total + calculateItemTotal(item), 0)) }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Item Discounts:</span>
                                            <span>{{ formatCurrency(form.items.reduce((total, item) => total + calculateDiscountedTotal(item), 0)) }}</span>
                                        </div>

                                        <hr />
                                        <div class="d-flex justify-content-between fw-bold">
                                            <span>Calculated Total:</span>
                                            <span>{{ formatCurrency(form.items.reduce((total, item) => total + calculateItemTotal(item), 0) - form.items.reduce((total, item) => total + calculateDiscountedTotal(item), 0)) }}</span>
                                        </div>
                                </div>
                            </div>
                        </b-card>
                    </div>
                    </BRow>
                    
                    <div class="success-alert" v-if="saveSuccess">
                        <i class="ri-checkbox-circle-fill"></i>
                        <span>Your information has been saved successfully!</span>
                    </div>
                    <div class="form-actions d-flex justify-content-between">
                        <b-button :disabled="!form.customer_id || !form.order_date" @click="addRowItem()" size="sm" variant="primary">
                            <i class="ri-add-line" v-if="!form.processing"></i>
                            Add Item
                        </b-button>
                        <div>
                            <button type="button" class="btn btn-cancel me-2" @click="hide">
                                <i class="ri-close-line"></i>
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-save" :disabled="form.processing || !form.customer_id || !form.order_date">
                                <i class="ri-save-line" v-if="!form.processing"></i>
                                <i class="ri-loader-4-line spinner" v-else></i>
                                {{ form.processing ? 'Saving...' : 'Save Order' }}
                            </button>
                        </div>
                    </div>
                </form>
              
                
            </div>
        </div>
    </div>

       <Item @add="fetch()" :dropdowns="dropdowns"  @items="storeItem" @update="updateItem" :formatCurrency="formatCurrency" ref="item"/>
       <Customer :dropdowns="dropdowns" ref="createCustomer"/>
</template>

<script>
import { useForm } from '@inertiajs/vue3';
import InputLabel from '@/Shared/Components/Forms/InputLabel.vue';
import TextInput from '@/Shared/Components/Forms/TextInput.vue';
import Multiselect from '@/Shared/Components/Forms/Multiselect.vue';
import Amount from '@/Shared/Components/Forms/Amount.vue';
import Item from '@/Pages/Modules/Sales/Components/SalesOrders/Modals/AddItem.vue';
import Customer from '@/Pages/Modules/Customers/Modals/Create.vue'

export default {
    components: { InputLabel, TextInput, Multiselect, Amount , Item, Customer},
    props: ['dropdowns'],
    data() {
        return {
            currentUrl: window.location.origin,
            form: useForm({
                id: null,
                order_date: new Date().toISOString().slice(0, 10),  // current date
                customer_id: null,
                status_id: null,
                billing_account: null,
                items: [],
                option: 'lists'
            }),
            showModal: false,
            editable: false,
            saveSuccess: false,
            selectedRow: null,
        }
    },
    computed: {
        availableProducts() {
            return this.dropdowns.products.filter(product => product.available > 0);
        }
    },
    methods: {
        cleanUnitCost(value) {
            // Remove currency symbol and commas, then parse to float
            return parseFloat(value.replace(/₱|,/g, '')) || 0;
        },

        cleanDiscount(value) {
            // Remove currency symbol and commas, then parse to float
            return parseFloat(value.replace(/₱|,/g, '')) || 0;
        },

        storeItem(item) {
            const newItem = {
                ...item,
                id: Date.now(), // temporary unique ID
                discount_per_unit: item.discount_per_unit || 0
            };
            this.form.items.push(newItem);
            this.updateDiscountAmount();
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

        addCustomer(){
            this.$refs.createCustomer.show();
        },

        addRowItem() {
            this.form.items.push({
                id: Date.now(),
                product_id: null,
                batch_code: null,
                quantity: 1,
                unit_cost: 0,
                discount_per_unit: 0,
            });
        },

        editItem(item, index ) {
            this.$refs.item.edit(item, index);
        },

        removeItem(id) {
            this.form.items = this.form.items.filter(item => item.id !== id);
            this.updateDiscountAmount();
        },

        show() {
            if (this.form.items.length === 0) {
                this.addRowItem(); // Add a starting row if none
            }
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = true;
        },
        edit(data, index) {
            this.form.id = data.id;
            this.form.order_date = data.order_date;
            this.form.customer_id = data.customer?.id;
            this.form.status_id = data.status_id;
            this.form.items = data.items.map(item => ({
                id: item.id || Date.now(), // ensure each item has a unique ID
                product_id: item.product_id,
                batch_code: item.batch_code,
                quantity: item.quantity,
                unit_cost: item.unit_cost,
                discount_per_unit: item.discount_per_unit || 0,
            }));
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

        getProduct(product_id){
            const product = this.dropdowns.products.find(u => u.value === product_id);
            return product ? product : [];
        },


        getCustomer(customer_id){
            const customer = this.dropdowns.customers.find(u => u.value === customer_id);
            return customer;
        },

        formatCurrency(value) {
            if (!value) return '₱0.00';
            return '₱' + Number(value).toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        },

        calculateItemTotal(item) {
            const unitCost = parseFloat(item.unit_cost) || 0;
            const quantity = parseFloat(item.quantity) || 0;
            return unitCost * quantity;
        },

        calculateDiscountedTotal(item) {
            const discount = parseFloat(item.discount_per_unit) || 0;
            const price = parseFloat(item.unit_cost) || 0;
            const quantity = parseFloat(item.quantity) || 0;
            return (price * discount / 100) * quantity;
        },

        updateItemTotal(index) {
            // In Vue 3, to trigger reactivity update for array elements
            this.form.items.splice(index, 1, { ...this.form.items[index] });
        },

        onProductChange(index) {
            const product_id = this.form.items[index].product_id;
            const product = this.dropdowns.products.find(p => p.value === product_id);
            if (product) {
                this.form.items[index].batch_code = product.batch_code || null;
                this.form.items[index].unit_cost = parseFloat(product.price) || 0;
            }
            this.updateItemTotal(index);
        },

        getPaymentModeIcon(mode) {
            const icons = {
                'Cash': 'ri-money-dollar-circle-line',
                'Credit Card': 'ri-bank-card-line',
                'Debit Card': 'ri-bank-card-2-line',
                'Bank Transfer': 'ri-exchange-dollar-line'
            };
            return icons[mode] || 'ri-money-dollar-circle-line';
        },




    }
}
</script>

<style scoped>
.modal-container {
    max-width: 95%;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
}

.modal-body {
    padding-bottom: 80px; /* Space for fixed buttons */
}

.form-actions {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: white;
    padding: 1rem;
    border-top: 1px solid #ddd;
    z-index: 10;
}

.payment-mode-buttons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
}

.payment-mode-buttons .btn {
    min-width: 70px;
    min-height: 70px;
    justify-content: flex-start;
    border: 1px solid lightgreen;
}

.selected-payment-mode {
    border-width: 2px !important;
    background-color: transparent !important;
    border: 3px solid darkgreen;
}

.payment-mode-buttons .btn:hover {
    border-color:darkgreen !important;
    border: 2px solid darkgreen;
    background-color: transparent !important;
    color:darkgreen;
}

.btn:focus, .btn:active {
    border-color:darkgreen !important;
    border: 2px solid darkgreen;
    background-color: transparent !important;
    color:darkgreen;
}


</style>
