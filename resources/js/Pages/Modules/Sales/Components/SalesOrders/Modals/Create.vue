 <template>
    <div v-if="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="hide">
        <div class="modal-container modal-fullscreen " @click.stop>
            <div class="modal-header ">
                <h2>{{ editable ? 'Update Sales Order' : 'Add Sales Order' }}</h2>
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
                                    <label for="order_date" class="form-label">Order Date<span
                                            class="text-danger">*</span></label>
                                    <div class="input-wrapper">
                                        <i class="ri-calendar-line input-icon"></i>
                                        <text-input type="date" id="name" v-model="form.order_date" class="form-control"
                                            :class="{ 'input-error': form.errors.order_date }"
                                            @input="handleInput('order_date')" />
                                    </div>
                                    <span class="error-message" v-if="form.errors.order_date">{{ form.errors.order_date
                                        }}</span>
                                </div>

                                <div class="form-group form-group-half">
                                    <label for="customer_id" class="form-label">Customer<span
                                            class="text-danger">*</span></label>
                                    <div class="input-wrapper">
                                        <i class="ri-user-line input-icon"></i>
                                        <b-form-select v-model="form.customer_id" :options="dropdowns.customers"
                                            text-field="name" value-field="value"
                                            :class="{ 'input-error': form.errors.customer_id }" class="form-control">
                                            <template #first>
                                                <b-form-select-option :value="null" disabled>Select
                                                    Customer</b-form-select-option>
                                            </template>
                                        </b-form-select>

                                    </div>

                                    <span class="error-message" v-if="form.errors.customer_id">{{
                                        form.errors.customer_id }}</span>

                                </div>



                                <div class="form-group">
                                    <div class="input-wrapper ">
                                        <label for="customer_id" class="form-label text-center">New</label>
                                        <button @click="addCustomer()" type="button"
                                            class="btn btn-cancel form-control">
                                            <i class="ri-add-line"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                             <div class="form-row">
                                <div class="form-group form-group-half">
                                    <label for="customer_id" class="form-label">Assigned To(Sales Rep)<span
                                        class="text-danger">*</span></label>
                                    <div class="input-wrapper">
                                        <i class="ri-user-line input-icon"></i>
                                        <b-form-select v-model="form.sales_rep_id" :options="dropdowns.sales_reps"
                                            text-field="name" value-field="value"
                                            :class="{ 'input-error': form.errors.sales_rep_id }" class="form-control">
                                            <template #first>
                                                <b-form-select-option :value="null" disabled>Select
                                                    Sales Rep</b-form-select-option>
                                            </template>
                                        </b-form-select>

                                    </div>

                                    <span class="error-message" v-if="form.errors.sales_rep_id">{{
                                        form.errors.sales_rep_id }}</span>

                                </div>

                                <div class="form-group form-group-half">
                                    <label for="customer_id" class="form-label">Assigned To(Driver)<span
                                        class="text-danger">*</span></label>
                                    <div class="input-wrapper">
                                        <i class="ri-user-line input-icon"></i>
                                        <b-form-select v-model="form.driver_id" :options="dropdowns.drivers"
                                            text-field="name" value-field="value"
                                            :class="{ 'input-error': form.errors.driver_id }" class="form-control">
                                            <template #first>
                                                <b-form-select-option :value="null" disabled>Select
                                                    Driver</b-form-select-option>
                                            </template>
                                        </b-form-select>

                                    </div>

                                    <span class="error-message" v-if="form.errors.driver_id">{{
                                        form.errors.driver_id }}</span>

                                </div>

                             </div>

                             <div class="form-row">
                                <div class="form-group form-group-half">
                                    <label for="location_id" class="form-label">Location<span
                                        class="text-danger">*</span></label>
                                    <div class="input-wrapper">
                                        <i class="ri-map-pin-line input-icon"></i>
                                        <b-form-select v-model="form.location_id" :options="dropdowns.locations"
                                            text-field="name" value-field="value"
                                            :class="{ 'input-error': form.errors.location_id }" class="form-control">
                                            <template #first>
                                                <b-form-select-option :value="null" disabled>Select
                                                    Location</b-form-select-option>
                                            </template>
                                        </b-form-select>

                                    </div>

                                    <span class="error-message" v-if="form.errors.location_id">{{
                                        form.errors.location_id }}</span>

                                </div>

                             </div>
                            <div class="mb-2">
                                <b-button :disabled="!form.customer_id || !form.order_date" @click="addItem()"
                                    size="sm" variant="primary">
                                    <i class="ri-add-line" v-if="!form.processing"></i>
                                    Add Item
                                </b-button>
                            </div>
                            <div class="form-row">
                                <div style="height: calc(100vh - 400px);  overflow: auto; width: 100%;">

                                    <table class="table align-middle table-striped table-centered mb-0 pretty-table">
                                        <thead class="table-light thead-fixed pretty-header">
                                            <tr class="fs-11">
                                                <th style="width: 3%;">#</th>
                                                <th style="width: 15%; text-align: center;change the calculation not per percentage but per unit">Product</th>
                                                <th style="width: 10%; text-align: center;">Batch Code</th>
                                                <th style="width: 1%;" class="text-center">Quantity</th>
                                                <th style="width: 12%;" class="text-center">Type/Price</th>
                                                <th style="width: 15%;" class="text-center">Total Cost</th>
                                              <th style="width: 10%;" class="text-center">Discount</th>
                                              <th style="width: 15%;" class="text-center">Total Discount </th>
                                              <th style="width: 10%;" class="text-center">Actions</th>
                                          </tr>
                                      </thead>

                                      <tbody class="table-white fs-12">
                                          <tr v-if="form.items.length === 0">
                                              <td colspan="11" class="text-center text-muted py-4">
                                                  <i class="ri-shopping-cart-line fs-1 text-muted mb-2"></i>
                                                  <div>No items added yet.</div>
                                                  <small>Click "Add Item" to start adding products to the order.</small>
                                              </td>
                                          </tr>
                                          <tr v-for="(list,index) in form.items" v-bind:key="index" @click="selectRow(index)" :class="{
                                              'bg-info-subtle': index === selectedRow
                                          }">
                                            <td >
                                                {{ index + 1}}
                                            </td>
                                            <td class="text-center">
                                                <div class="fw-bold text-primary">{{ getProduct(list.product_id).name || '-' }}</div>
                                                                                                    <small class="text-muted">Available: <span class="badge bg-light text-dark">{{ (getProduct(list.product_id).batch_available ?? getProduct(list.product_id).available) || 0 }}</span></small>
                                            </td>
                                            <td class="text-center">
                                               <span >
                                                    {{ getProduct(list.product_id).batch_code || '-' }}
                                               </span>
                                            </td>
                                    
                                      
                                            <td class="text-center">
                                                <span class="fw-bold">{{ list.quantity }}</span>
                                            </td>
                                      
                                            <td class="text-center">
                                                <div class="d-flex flex-column align-items-center">
                                                    <span :class="list.price_type === 'retail' ? 'badge bg-success mb-1' : 'badge bg-warning mb-1'">
                                                        {{ list.price_type === 'retail' ? 'Retail' : 'Wholesale' }}
                                                    </span>
                                                    <small class="text-muted">{{ formatCurrency(list.price) }}</small>
                                                </div>
                                            </td>
                                            <td class="text-center">{{ formatCurrency(calculateItemTotal(list)) }}</td>
                                            <td class="text-center">
                                               {{ formatCurrency(list.discount_per_unit) }}
                                            </td>
                                            <td class="text-center">{{ formatCurrency(calculateDiscountedTotal(list)) }}</td>
                                              <td class="text-center">
                                                  <div class="d-flex justify-content-center gap-1">
                                                      <b-button @click="editItem(list, index)" variant="primary" v-b-tooltip.hover title="Edit" size="sm" class="btn-icon">
                                                          <i class="ri-edit-line"></i>
                                                      </b-button>
                                                      <b-button @click="removeItem(list.id)" variant="danger" v-b-tooltip.hover title="Delete" size="sm" class="btn-icon">
                                                          <i class="ri-delete-bin-line"></i>
                                                      </b-button>
                                                  </div>
                                              </td>
                                          </tr>
                                      </tbody>
                                      <tfoot class="table-light">
                                          <tr>
                                              <td colspan="6" class="text-end fw-bold">Total:</td>
                                              <td class="text-center fw-bold">{{ formatCurrency(form.items.reduce((total, item) => total + calculateItemTotal(item), 0)) }}</td>
                                              <td class="text-center fw-bold">{{ formatCurrency(form.items.reduce((total, item) => total + calculateDiscountedTotal(item), 0)) }}</td>
                                              <td colspan="2"></td>
                                          </tr>
                                      </tfoot>
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

                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-light">
                                <h6 class="mb-0 text-primary">
                                    <i class="ri-bank-card-line me-2"></i>
                                    Select Payment Mode
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="payment-mode-grid">
                                    <div
                                        v-for="mode in payment_modes"
                                        :key="mode"
                                        :class="{ 'selected-payment-mode': form.payment_mode === mode }"
                                        class="payment-mode-card"
                                        @click="selectPaymentMode(mode)"
                                    >
                                        <i :class="getPaymentModeIcon(mode)" class="payment-icon"></i>
                                        <span class="payment-label">{{ mode }}</span>
                                    </div>
                                </div>
                                <span class="error-message" v-if="form.errors.payment_mode">{{ form.errors.payment_mode }}</span>

                  
                            </div>
                        </div>

                        <div v-if="form.payment_mode === 'Credit'" class="card border-0 shadow-sm">
                            <div class="card-header bg-light">

                                 <label for="due_date" class="form-label">Due Date<span
                                        class="text-danger">*</span></label>
                                <div class="input-wrapper">
                                    <i class="ri-calendar-line input-icon"></i>
                                    <text-input type="date" id="due_date" v-model="form.due_date" class="form-control"
                                        :class="{ 'input-error': form.errors.due_date }"
                                        @input="handleInput('due_date')" />
                                </div>
                                <span class="error-message" v-if="form.errors.due_date">{{ form.errors.due_date
                                    }}</span>
                            </div>
                            <div class="card-body">

                                <span class="error-message" v-if="form.errors.due_date">{{ form.errors.due_date }}</span>
                            </div>
                        </div>
                   
                         <b-card class=" bg-light mt-4 p-3 rounded">
                                <div class="form-group form-group-half mt-0" >
                                    <label class="form-label text-primary">Order Summary</label>
                                    <div class="input-wrapper">
                                        <div class="d-flex justify-content-between">
                                            <span>Subtotal:</span>
                                            <span>{{formatCurrency(form.items.reduce((total, item) => total +
                                                calculateItemTotal(item), 0))
                                                }}</span>
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
                    <div class="error-alert" v-if="form.errors.stock">
                        <i class="ri-error-warning-line"></i>
                        <span>{{ form.errors.stock }}</span>
                    </div>
                    <div class="form-actions d-flex justify-content-end">
                        <button type="button" class="btn btn-cancel me-2" @click="hide">
                            <i class="ri-close-line"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-save"
                            :disabled="form.processing || !form.customer_id || !form.order_date">
                            <i class="ri-save-line" v-if="!form.processing"></i>
                            <i class="ri-loader-4-line spinner" v-else></i>
                            {{ form.processing ? 'Saving...' : 'Save Order' }}
                        </button>
                    </div>
                </form>


            </div>
        </div>
    </div>

    <div v-if="showPaymentPrompt" class="modal-overlay active payment-review-modal" @click.self="cancelPaymentPrompt">
        <div class="modal-container modal-lg" @click.stop>
            <div class="modal-header bg-primary text-white">
                <h4 class="mb-0 text-white">
                    <i class="ri-money-dollar-circle-line me-2"></i>
                    Proceed Payment
                </h4>
                <button class="close-btn text-white" @click="cancelPaymentPrompt">
                    <i class="ri-close-line fs-20"></i>
                </button>
            </div>
            <div class="modal-body p-4" style="height: 75vh; overflow-y: auto;">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light">
                        <h6 class="mb-0 text-primary">
                            <i class="ri-file-list-line me-2"></i>
                            Invoice Summary
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Sales Order #:</strong> {{ pendingSalesOrder?.so_number || '-' }}</p>
                                <p class="mb-1"><strong>Order Date:</strong> {{ pendingSalesOrder?.order_date || '-' }}</p>
                                <p class="mb-1"><strong>Customer:</strong> {{ pendingSalesOrder?.customer?.name || '-' }}</p>
                                <p class="mb-1"><strong>Location:</strong> {{ getLocationName(pendingSalesOrder?.location_id) }}</p>
                                <p class="mb-0"><strong>Payment Mode:</strong> {{ pendingSalesOrder?.payment_mode || '-' }}</p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Sales Rep:</strong> {{ getSalesRepName(pendingSalesOrder?.sales_rep_id) }}</p>
                                <p class="mb-1"><strong>Driver:</strong> {{ getDriverName(pendingSalesOrder?.driver_id) }}</p>
                                <p class="mb-1"><strong>Invoice #:</strong> {{ pendingInvoice?.invoice_number || '-' }}</p>
                                <p class="mb-1"><strong>Invoice Date:</strong> {{ pendingInvoice?.invoice_date || '-' }}</p>
                                <p class="mb-0"><strong>Amount Due:</strong> {{ formatCurrency(pendingInvoice?.balance_due || 0) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card border-0 shadow-sm mt-3" v-if="pendingSalesOrder?.items?.length">
                    <div class="card-header bg-light">
                        <h6 class="mb-0 text-primary">
                            <i class="ri-shopping-bag-line me-2"></i>
                            Order Items
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-2">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-center">Batch</th>
                                        <th class="text-center">Qty</th>
                                        <th class="text-end">Price</th>
                                        <th class="text-end">Discount</th>
                                        <th class="text-end">Line Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item, idx) in pendingSalesOrder.items" :key="idx">
                                        <td>{{ getPromptItemName(item) }}</td>
                                        <td class="text-center">{{ item.batch_code || '-' }}</td>
                                        <td class="text-center">{{ item.quantity || 0 }}</td>
                                        <td class="text-end">{{ formatCurrency(item.price || 0) }}</td>
                                        <td class="text-end">{{ formatCurrency((item.discount_per_unit || 0) * (item.quantity || 0)) }}</td>
                                        <td class="text-end">{{ formatCurrency(calculateItemTotal(item) - calculateDiscountedTotal(item)) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-end">
                            <div style="min-width: 260px;">
                                <div class="d-flex justify-content-between">
                                    <span>Subtotal:</span>
                                    <strong>{{ formatCurrency(getPromptSubtotal()) }}</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Total Discount:</span>
                                    <strong>{{ formatCurrency(getPromptDiscount()) }}</strong>
                                </div>
                                <div class="d-flex justify-content-between border-top pt-1 mt-1">
                                    <span>Net Total:</span>
                                    <strong>{{ formatCurrency(getPromptNetTotal()) }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <p class="text-muted mt-3 mb-0">
                    This order is <strong>Cash</strong>. Do you want to record payment now?
                </p>
                <span class="error-message" v-if="paymentPromptError">{{ paymentPromptError }}</span>
            </div>
            <div class="modal-footer bg-light border-0 p-4">
                <button type="button" class="btn btn-outline-secondary me-3" @click="cancelPaymentPrompt" :disabled="processingPayment">
                    <i class="ri-close-line me-2"></i>
                    Cancel
                </button>
                <button type="button" class="btn btn-primary" @click="proceedPayment" :disabled="processingPayment || !pendingInvoice?.id">
                    <i class="ri-loader-4-line spinner" v-if="processingPayment"></i>
                    <i class="ri-check-line me-2" v-else></i>
                    {{ processingPayment ? 'Processing...' : 'Record Payment & Continue' }}
                </button>
            </div>
        </div>
    </div>

    <div v-if="showPrintPrompt" class="modal-overlay active print-review-modal" @click.self="skipReceiptPrint">
        <div class="modal-container modal-md" @click.stop>
            <div class="modal-header bg-primary text-white">
                <h4 class="mb-0 text-white">
                    <i class="ri-printer-line me-2"></i>
                    Print Receipt
                </h4>
                <button class="close-btn text-white" @click="skipReceiptPrint">
                    <i class="ri-close-line fs-20"></i>
                </button>
            </div>
            <div class="modal-body p-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light">
                        <h6 class="mb-0 text-primary">
                            <i class="ri-file-list-line me-2"></i>
                            Receipt Summary
                        </h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-0 text-muted">Payment recorded successfully. Do you want to print the receipt now?</p>
                        <small v-if="!pendingReceiptId" class="text-danger d-block mt-2">
                            Receipt reference is not yet available. Please refresh and open it from Receipts list.
                        </small>
                        <div class="receipt-preview mt-3" v-if="pendingReceiptId">
                            <iframe
                                :src="`/receipts/${pendingReceiptId}?option=print&type=receipt`"
                                class="receipt-preview-frame"
                                title="Receipt Preview"
                            ></iframe>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 p-4">
                <button type="button" class="btn btn-outline-secondary me-3" @click="skipReceiptPrint">
                    <i class="ri-close-line me-2"></i>
                    Not Now
                </button>
                <button type="button" class="btn btn-primary" @click="printReceiptNow" :disabled="!pendingReceiptId">
                    <i class="ri-printer-line me-2"></i>
                    Print Receipt
                </button>
            </div>
        </div>
    </div>

    <Item @add="fetch()" :dropdowns="dropdowns" :items="form.items" @items="storeItem" @update="updateItem" :formatCurrency="formatCurrency"
        ref="item" />
    <Customer :dropdowns="dropdowns" ref="createCustomer" />
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
    components: { InputLabel, TextInput, Multiselect, Amount, Item, Customer },
    props: ['dropdowns', 'user'],
    data() {
        return {
            currentUrl: window.location.origin,
            form: useForm({
                id: null,
                order_date: new Date().toISOString().slice(0, 10),  // current date
                due_date: null,
                customer_id: null,
                sales_rep_id: null,
                driver_id: null,
                location_id: null,
                status_id: null,
                billing_account: null,
                payment_mode: null,
                items: [],
                option: 'lists',
                action: null
            }),
            paymentForm: useForm({
                id: null,
                option: 'payment',
                action: 'payment',
                balance_due: 0,
                amount_paid: 0,
                payment_date: new Date().toISOString().slice(0, 10),
            }),
            showModal: false,
            editable: false,
            saveSuccess: false,
            selectedRow: null,
            showPaymentPrompt: false,
            processingPayment: false,
            paymentPromptError: null,
            showPrintPrompt: false,
            pendingReceiptId: null,
            pendingInvoice: null,
            pendingSalesOrder: null,
            payment_modes: [
                'Cash',
                'Credit',
                'Debit Card',
                'Bank Transfer',
            ],
        }
    },
    computed: {
        availableProducts() {
            return this.dropdowns.products.filter(product => product.available > 0);
        },
        priceTypeOptions() {
            return [
                { value: 'retail', text: 'Retail Price' },
                { value: 'wholesale', text: 'Wholesale Price' }
            ];
        },

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

        addItem() {
            this.$refs.item.show();
        },

        addCustomer() {
            this.$refs.createCustomer.show();
        },

        addRowItem() {
            this.form.items.push({
                id: Date.now(),
                product_id: null,
                batch_code: null,
                quantity: 1,
                price: 0,
                discount_per_unit: 0,
                price_type: 'retail', // default to retail per item
            });
        },

        editItem(item, index) {
            this.$refs.item.edit(item, index);
        },

        removeItem(id) {
            this.form.items = this.form.items.filter(item => item.id !== id);
            this.updateDiscountAmount();
        },

        show() {
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = true;
            // Set default due date to 3 days ahead
            const dueDate = new Date();
            dueDate.setDate(dueDate.getDate() + 3);
            this.form.due_date = dueDate.toISOString().slice(0, 10);
            // Set default sales rep to current user if they are a sales rep
            const userEmployeeId = this.$page.props.user.data.id;

            if (userEmployeeId) {
                const isSalesRep = this.dropdowns.sales_reps.some(rep => rep.value === userEmployeeId);
                if (isSalesRep) {
                    this.form.sales_rep_id = this.$page.props.user.data.id;
                }
            }

        },
        edit(data, index) {
            this.form.id = data.id;
            this.form.order_date = new Date().toISOString().slice(0, 10);  // set to current date
            this.form.customer_id = data.customer?.id;
            this.form.sales_rep_id = data.sales_rep_id;
            this.form.driver_id = data.driver_id;
            this.form.location_id = data.location_id;
            this.form.status_id = data.status_id;
            this.form.payment_mode = data.payment_mode;
            this.form.due_date = data.due_date;
            this.form.items = data.items.map(item => ({
                id: item.id || Date.now(), // ensure each item has a unique ID
                product_id: item.product_id,
                batch_code: item.batch_code,
                quantity: item.quantity,
                price: item.price,
                discount_per_unit: item.discount_per_unit || 0,
                price_type: item.price_type || 'retail', // default to retail if not set
            }));
            this.editable = true;
            this.saveSuccess = false;
            this.showModal = true;
            this.$nextTick(() => {
                this.form.items.forEach((item, itemIndex) => {
                    if (this.$refs['discountComponent' + itemIndex]) {
                        this.$refs['discountComponent' + itemIndex].emitValue(item.discount_per_unit || 0);
                    }
                });
            });
        },
        submit() {
            if (this.editable) {
                this.form.action = 'update';
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
                this.form.action = null;
                this.form.post('/sales-orders', {
                    preserveScroll: true,
                    onSuccess: (response) => {
                        const createdOrder = response?.props?.flash?.data?.data || this.$page?.props?.flash?.data?.data;
                        const isCash = ['cash', 'cash sales'].includes((this.form.payment_mode || '').toLowerCase());
                        const invoice = createdOrder?.invoices?.[0] || null;

                        this.form.reset();
                        this.hide();

                        if (isCash && invoice) {
                            this.pendingSalesOrder = createdOrder;
                            this.pendingInvoice = invoice;
                            this.paymentPromptError = null;
                            this.paymentForm.id = invoice.id;
                            this.paymentForm.balance_due = invoice.balance_due || 0;
                            this.paymentForm.amount_paid = invoice.balance_due || 0;
                            this.paymentForm.payment_date = new Date().toISOString().slice(0, 10);
                            this.showPaymentPrompt = true;
                            return;
                        }

                        this.saveSuccess = true;
                        setTimeout(() => {
                            this.$emit('add', true);
                        }, 300);
                    },
                });
            }
        },
        proceedPayment() {
            if (!this.pendingInvoice?.id) return;

            this.processingPayment = true;
            this.paymentPromptError = null;
            this.paymentForm.id = this.pendingInvoice.id;
            this.paymentForm.balance_due = this.pendingInvoice.balance_due || 0;
            this.paymentForm.amount_paid = this.pendingInvoice.balance_due || 0;
            this.paymentForm.payment_date = new Date().toISOString().slice(0, 10);

            this.paymentForm.put(`/ar-invoices/${this.pendingInvoice.id}`, {
                preserveScroll: true,
                onSuccess: (response) => {
                    const receiptId = response?.props?.flash?.receipt_id || this.$page?.props?.flash?.receipt_id || null;
                    this.showPaymentPrompt = false;
                    this.pendingReceiptId = receiptId;
                    this.showPrintPrompt = true;
                    this.pendingInvoice = null;
                    this.pendingSalesOrder = null;
                    this.paymentForm.reset();
                    this.$emit('add', true);
                },
                onError: (errors) => {
                    this.paymentPromptError = errors?.amount_paid || errors?.payment_date || 'Failed to process payment.';
                },
                onFinish: () => {
                    this.processingPayment = false;
                }
            });
        },
        cancelPaymentPrompt() {
            this.showPaymentPrompt = false;
            this.pendingInvoice = null;
            this.pendingSalesOrder = null;
            this.paymentPromptError = null;
            this.paymentForm.reset();
            this.$emit('add', true);
        },
        printReceiptNow() {
            if (this.pendingReceiptId) {
                window.open(`/receipts/${this.pendingReceiptId}?option=print&type=receipt`, '_blank');
            }
            this.showPrintPrompt = false;
            this.pendingReceiptId = null;
        },
        skipReceiptPrint() {
            this.showPrintPrompt = false;
            this.pendingReceiptId = null;
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


        getBrand(brand_id) {
            const brand = this.dropdowns.brands.find(b => b.value === brand_id);
            return brand ? brand.name : '';
        },

        getProduct(product_id) {
            const product = this.dropdowns.products.find(u => u.value === product_id);
            return product ? product : [];
        },


        getCustomer(customer_id) {
            const customer = this.dropdowns.customers.find(u => u.value === customer_id);
            return customer;
        },
        getSalesRepName(employeeId) {
            if (!employeeId) return '-';
            const rep = this.dropdowns.sales_reps.find(u => u.value === employeeId);
            return rep ? rep.name : '-';
        },
        getDriverName(employeeId) {
            if (!employeeId) return '-';
            const driver = this.dropdowns.drivers.find(u => u.value === employeeId);
            return driver ? driver.name : '-';
        },
        getLocationName(locationId) {
            if (!locationId) return '-';
            const location = this.dropdowns.locations.find(u => u.value === locationId);
            return location ? location.name : '-';
        },
        getPromptItemName(item) {
            if (!item?.product_id) return '-';
            const product = this.getProduct(item.product_id);
            return product?.name || ('Product #' + item.product_id);
        },
        getPromptSubtotal() {
            if (!this.pendingSalesOrder?.items) return 0;
            return this.pendingSalesOrder.items.reduce((total, item) => total + this.calculateItemTotal(item), 0);
        },
        getPromptDiscount() {
            if (!this.pendingSalesOrder?.items) return 0;
            return this.pendingSalesOrder.items.reduce((total, item) => total + this.calculateDiscountedTotal(item), 0);
        },
        getPromptNetTotal() {
            const backendTotal = parseFloat(this.pendingSalesOrder?.total_amount);
            if (!isNaN(backendTotal)) return backendTotal;
            return this.getPromptSubtotal() - this.getPromptDiscount();
        },

        formatCurrency(value) {
            if (!value) return '₱0.00';
            const num = Number(value);
            if (isNaN(num)) return '₱0.00';
            return '₱' + num.toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        },

        calculateItemTotal(item) {
            const price = parseFloat(item.price) || 0;
            const quantity = parseFloat(item.quantity) || 0;
            return price * quantity;
        },

        calculateDiscountedTotal(item) {
            const discount = parseFloat(item.discount_per_unit) || 0;
            const quantity = parseFloat(item.quantity) || 0;
            return discount * quantity;
        },



        updateDiscount(item, index, value) {
            if (typeof value === 'string') {
                const cleanValue = value.replace(/[₱,]/g, '');
                const num = parseFloat(cleanValue);
                item.discount_per_unit = isNaN(num) ? 0 : num;
            } else {
                item.discount_per_unit = value || 0;
            }
        },

        updateDiscountAmount() {
            // Recalculate totals when items are added or removed
            // This method is called to update any discount-related calculations
            // Currently, it ensures reactivity for the order summary
        },

        onProductChange(index) {
            const product_id = this.form.items[index].product_id;
            const product = this.dropdowns.products.find(p => p.value === product_id);
            if (product) {
                this.form.items[index].batch_code = product.batch_code || null;
                const price = this.form.items[index].price_type === 'wholesale' ? product.wholesale_price : product.retail_price;
                this.form.items[index].price = parseFloat(price) || 0;
            }
        },

        onPriceTypeChange(index) {
            this.onProductChange(index);
        },


        selectPaymentMode(mode) {
            this.form.payment_mode = mode;
        },

        getPaymentModeIcon(mode) {
            const icons = {
                'Cash Sales': 'ri-money-dollar-circle-line',
                'Credit Sales': 'ri-bank-card-line',
            };
            return icons[mode] || 'ri-money-dollar-circle-line';
        },




    }
}
</script>

<style scoped>
.payment-review-modal .modal-container {
    max-width: 900px;
    width: 95%;
}

.payment-review-modal .modal-header {
    border-radius: 20px 20px 0 0;
    background: #C4DAD2 !important;
    border-bottom: 1px solid #e9ecef;
}

.payment-review-modal .modal-header h4 {
    color: #16423C !important;
    font-weight: 700;
}

.payment-review-modal .close-btn {
    background: rgba(255, 255, 255, 0.25);
    color: #16423C !important;
}

.payment-review-modal .close-btn:hover {
    background: rgba(255, 255, 255, 0.35);
}

.payment-review-modal .card {
    border-radius: 12px;
}

.payment-review-modal .card-header {
    border-radius: 12px 12px 0 0 !important;
}

.print-review-modal .modal-container {
    max-width: 700px;
    width: 92%;
}

.print-review-modal .modal-header {
    border-radius: 20px 20px 0 0;
    background: #C4DAD2 !important;
    border-bottom: 1px solid #e9ecef;
}

.print-review-modal .modal-header h4 {
    color: #16423C !important;
    font-weight: 700;
}

.print-review-modal .close-btn {
    background: rgba(255, 255, 255, 0.25);
    color: #16423C !important;
}

.print-review-modal .close-btn:hover {
    background: rgba(255, 255, 255, 0.35);
}

.print-review-modal .card {
    border-radius: 12px;
}

.print-review-modal .card-header {
    border-radius: 12px 12px 0 0 !important;
}

.receipt-preview {
    border: 1px solid #dee2e6;
    border-radius: 10px;
    overflow: hidden;
    background: #fff;
}

.receipt-preview-frame {
    width: 100%;
    height: 460px;
    border: none;
    background: #fff;
}

.modal-container {
    max-width: 100%;
    width: 100%;
    position: relative;
}

.modal-body {
    max-height: 85vh;
    overflow-y: auto;
    /* Space for fixed buttons */
}

.form-actions {

    background: white;
    padding: 1rem;
    border-top: 1px solid #ddd;

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
    border-color: #007bff;
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
}

.payment-mode-buttons .btn:hover {
    border-color: darkgreen !important;
    border: 2px solid darkgreen;
    background-color: transparent !important;
    color: darkgreen;
}

.btn:focus,
.btn:active {
    border-color: darkgreen !important;
    border: 2px solid darkgreen;
    background-color: transparent !important;
    color: darkgreen;
}

/* Payment mode grid styles */
.payment-mode-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 0.75rem;
    margin-top: 0.5rem;
}

.payment-mode-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1rem 0.5rem;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    background: white;
    cursor: pointer;
    transition: all 0.3s ease;
    min-height: 80px;
}

.payment-mode-card:hover {
    border-color: #007bff;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.15);
}

.payment-mode-card.selected-payment-mode {
    border-color: #007bff;
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
}

.payment-icon {
    font-size: 1.5rem;
    margin-bottom: 0.25rem;
}

.payment-label {
    font-size: 0.875rem;
    font-weight: 500;
    text-align: center;
}

/* Pretty table styles */
.pretty-table {
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    border: 1px solid #e9ecef;
}

.pretty-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.pretty-header th {
    border: none;
    padding: 12px 8px;
    vertical-align: middle;
}

.pretty-header th i {
    margin-right: 5px;
    opacity: 0.8;
}

.pretty-table tbody tr {
    transition: all 0.3s ease;
    border-bottom: 1px solid #f8f9fa;
}

.pretty-table tbody tr:hover {
    background: linear-gradient(135deg, #f8f9ff 0%, #e8f2ff 100%);
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.pretty-table tbody tr:nth-child(even) {
    background-color: #f8f9fa;
}

.pretty-table tbody tr:nth-child(even):hover {
    background: linear-gradient(135deg, #f0f2ff 0%, #e0e8ff 100%);
}

.pretty-table td {
    padding: 10px 8px;
    vertical-align: middle;
    border: none;
}

.pretty-table .badge {
    font-size: 0.75rem;
    padding: 4px 8px;
    border-radius: 12px;
}

.pretty-table .btn-icon {
    border-radius: 50%;
    width: 32px;
    height: 32px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.pretty-table .btn-icon:hover {
    transform: scale(1.1);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.pretty-table .text-primary {
    color: #667eea !important;
    font-weight: 600;
}

.pretty-table input[type="number"] {
    border: 1px solid #ced4da;
    border-radius: 4px;
    padding: 4px 8px;
    text-align: center;
    transition: border-color 0.3s ease;
}

.pretty-table input[type="number"]:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}
</style>
