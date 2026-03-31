<template>
    <div v-if="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="hide">
        <div class="modal-container modal-fullscreen" @click.stop>
            <div class="modal-header">
                <div class="header-title">
                    <i :class="editable ? 'ri-edit-box-line' : 'ri-shopping-cart-2-line'"></i>
                    <h2>{{ editable ? 'Update Sales Order' : 'Add Sales Order' }}</h2>
                </div>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body modal-body-lg">
                <form @submit.prevent="submit">
                    <div class="form-layout">
                        <section class="form-panel form-panel-main">
                            <div class="panel-head">
                                <div>
                                    <p class="panel-kicker">Sales Setup</p>
                                    <h3 class="panel-title">Sales Order Details</h3>
                                </div>
                            </div>

                            <div class="detail-grid detail-grid-primary">
                                <div class="form-group">
                                    <label for="order_date" class="form-label">Order Date<span class="text-danger">*</span></label>
                                    <div class="input-wrapper">
                                        <i class="ri-calendar-line input-icon"></i>
                                        <text-input type="date" id="name" v-model="form.order_date"
                                            class="form-control"
                                            :class="{ 'input-error': form.errors.order_date }"
                                            @input="handleInput('order_date')" />
                                    </div>
                                    <span class="error-message" v-if="form.errors.order_date">{{ form.errors.order_date }}</span>
                                </div>

                                <div class="form-group">
                                    <label for="customer_id" class="form-label">Customer<span class="text-danger">*</span></label>
                                    <div class="input-wrapper">
                                        <i class="ri-user-line input-icon"></i>
                                        <b-form-select v-model="form.customer_id" :options="dropdowns.customers"
                                            text-field="name" value-field="value"
                                            :class="{ 'input-error': form.errors.customer_id }"
                                            class="form-control">
                                            <template #first>
                                                <b-form-select-option :value="null" disabled>Select Customer</b-form-select-option>
                                            </template>
                                        </b-form-select>
                                    </div>
                                    <span class="error-message" v-if="form.errors.customer_id">{{ form.errors.customer_id }}</span>
                                </div>

                                <div class="form-group form-group-inline-action">
                                    <label class="form-label">New Customer</label>
                                    <button @click="addCustomer()" type="button" class="btn-outline-action">
                                        <i class="ri-add-line"></i>
                                        Add Customer
                                    </button>
                                </div>
                            </div>

                            <div class="detail-grid detail-grid-secondary">
                                <div class="form-group">
                                    <label for="sales_rep_id" class="form-label">Assigned To (Sales Rep)<span class="text-danger">*</span></label>
                                    <div class="input-wrapper">
                                        <i class="ri-user-line input-icon"></i>
                                        <b-form-select v-model="form.sales_rep_id" :options="salesRepOptions"
                                            text-field="name" value-field="value"
                                            :class="{ 'input-error': form.errors.sales_rep_id }"
                                            class="form-control">
                                            <template #first>
                                                <b-form-select-option :value="null" disabled>Select Sales Rep</b-form-select-option>
                                            </template>
                                        </b-form-select>
                                    </div>
                                    <span class="error-message" v-if="form.errors.sales_rep_id">{{ form.errors.sales_rep_id }}</span>
                                </div>

                                <div class="form-group">
                                    <label for="driver_id" class="form-label">Assigned To (Driver)<span class="text-danger">*</span></label>
                                    <div class="input-wrapper">
                                        <i class="ri-user-line input-icon"></i>
                                        <b-form-select v-model="form.driver_id" :options="dropdowns.drivers"
                                            text-field="name" value-field="value"
                                            :class="{ 'input-error': form.errors.driver_id }"
                                            class="form-control">
                                            <template #first>
                                                <b-form-select-option :value="null" disabled>Select Driver</b-form-select-option>
                                            </template>
                                        </b-form-select>
                                    </div>
                                    <span class="error-message" v-if="form.errors.driver_id">{{ form.errors.driver_id }}</span>
                                </div>

                                <div class="form-group">
                                    <label for="location_id" class="form-label">Location<span class="text-danger">*</span></label>
                                    <div class="input-wrapper">
                                        <i class="ri-map-pin-line input-icon"></i>
                                        <b-form-select v-model="form.location_id" :options="dropdowns.locations"
                                            text-field="name" value-field="value"
                                            :class="{ 'input-error': form.errors.location_id }"
                                            class="form-control">
                                            <template #first>
                                                <b-form-select-option :value="0">Select Location</b-form-select-option>
                                            </template>
                                        </b-form-select>
                                    </div>
                                    <span class="error-message" v-if="form.errors.location_id">{{ form.errors.location_id }}</span>
                                </div>
                            </div>

                            <div class="section-divider">
                                <div>
                                    <p class="panel-kicker mb-1">Sales Items</p>
                                    <h4 class="section-title mb-0">Order Line Items</h4>
                                </div>
                                <button type="button" class="btn-add-item" :disabled="!canAddItem" @click="addItem()">
                                    <i class="ri-add-line"></i>
                                    Add Item
                                </button>
                            </div>

                            <div class="items-table-wrap">
                                <table class="table align-middle mb-0 pretty-table">
                                    <thead class="pretty-header">
                                        <tr class="fs-11">
                                            <th style="width: 4%;">#</th>
                                            <th style="width: 17%;">Product</th>
                                            <th style="width: 12%;" class="text-center">Batch Code</th>
                                            <th style="width: 8%;" class="text-center">Quantity</th>
                                            <th style="width: 13%;" class="text-center">Type / Price</th>
                                            <th style="width: 13%;" class="text-center">Total Cost</th>
                                            <th style="width: 11%;" class="text-center">Discount</th>
                                            <th style="width: 14%;" class="text-center">Total Discount</th>
                                            <th style="width: 8%;" class="text-center">Actions</th>
                                        </tr>
                                    </thead>

                                    <tbody class="table-white fs-12">
                                        <tr v-if="form.items.length === 0">
                                            <td colspan="9" class="empty-table">
                                                <div class="empty-table-inner">
                                                    <i class="ri-shopping-cart-line"></i>
                                                    <h4>No items added yet</h4>
                                                    <p>Click "Add Item" to start building this sales order.</p>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-for="(list, index) in form.items" :key="index"
                                            @click="selectRow(index)"
                                            :class="{ 'row-selected': index === selectedRow }">
                                            <td>{{ index + 1 }}</td>
                                            <td>
                                                <div class="product-cell">
                                                    <div class="product-name">{{ getProduct(list.product_id).name || '-' }}</div>
                                                    <small class="product-availability">Available: {{ getRowBatchAvailable(list) }}</small>
                                                </div>
                                            </td>
                                            <td class="text-center">{{ list.batch_code || '-' }}</td>
                                            <td class="text-center"><span class="metric-pill">{{ list.quantity }}</span></td>
                                            <td class="text-center">
                                                <div class="price-type-wrap">
                                                    <span :class="list.price_type === 'retail' ? 'type-badge retail' : 'type-badge wholesale'">
                                                        {{ list.price_type === 'retail' ? 'Retail' : 'Wholesale' }}
                                                    </span>
                                                    <small class="text-muted">{{ formatCurrency(list.price) }}</small>
                                                </div>
                                            </td>
                                            <td class="text-center">{{ formatCurrency(calculateItemTotal(list)) }}</td>
                                            <td class="text-center">{{ formatCurrency(list.discount_per_unit) }}</td>
                                            <td class="text-center">{{ formatCurrency(calculateDiscountedTotal(list)) }}</td>
                                            <td class="text-center">
                                                <div class="action-buttons">
                                                    <button type="button" class="table-action-btn" @click.stop="editItem(list, index)">
                                                        <i class="ri-edit-line"></i>
                                                    </button>
                                                    <button type="button" class="table-action-btn danger" @click.stop="removeItem(list.id)">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>

                                    <tfoot>
                                        <tr>
                                            <td colspan="5" class="text-end footer-label">Totals</td>
                                            <td class="text-center footer-value">{{ formatCurrency(subtotal) }}</td>
                                            <td></td>
                                            <td class="text-center footer-value">{{ formatCurrency(totalDiscount) }}</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </section>

                        <aside class="form-panel form-panel-side">
                            <div class="panel-head">
                                <div>
                                    <p class="panel-kicker">Live Summary</p>
                                    <h3 class="panel-title">Order Overview</h3>
                                </div>
                            </div>

                            <div class="summary-metrics">
                                <div class="summary-metric-card">
                                    <span class="summary-metric-label">Items</span>
                                    <strong class="summary-metric-value">{{ form.items.length }}</strong>
                                </div>
                                <div class="summary-metric-card">
                                    <span class="summary-metric-label">Grand Total</span>
                                    <strong class="summary-metric-value">{{ formatCurrency(grandTotal) }}</strong>
                                </div>
                            </div>

                            <div class="summary-card">
                                <div class="summary-card-header">
                                    <h4>Customer Information</h4>
                                </div>
                                <div v-if="selectedCustomer" class="info-list">
                                    <div class="info-row">
                                        <span>Name</span>
                                        <strong>{{ selectedCustomer.name || '-' }}</strong>
                                    </div>
                                    <div class="info-row">
                                        <span>Address</span>
                                        <strong>{{ selectedCustomer.address || '-' }}</strong>
                                    </div>
                                    <div class="info-row">
                                        <span>Contact</span>
                                        <strong>{{ selectedCustomer.contact_number || '-' }}</strong>
                                    </div>
                                    <div class="info-row">
                                        <span>Email</span>
                                        <strong>{{ selectedCustomer.email || '-' }}</strong>
                                    </div>
                                </div>
                                <div v-else class="empty-side-note">
                                    Select a customer to view account details.
                                </div>
                            </div>

                            <div class="summary-card">
                                <div class="summary-card-header">
                                    <h4>Payment Mode</h4>
                                </div>
                                <div class="payment-mode-grid">
                                    <div v-for="mode in payment_modes" :key="mode"
                                        :class="{ 'selected-payment-mode': form.payment_mode === mode }"
                                        class="payment-mode-card" @click="selectPaymentMode(mode)">
                                        <i :class="getPaymentModeIcon(mode)" class="payment-icon"></i>
                                        <span class="payment-label">{{ mode }}</span>
                                    </div>
                                </div>
                                <span class="error-message" v-if="form.errors.payment_mode">{{ form.errors.payment_mode }}</span>
                            </div>

                            <div v-if="form.payment_mode === 'Credit'" class="summary-card">
                                <div class="summary-card-header">
                                    <h4>Credit Schedule</h4>
                                </div>
                                <div class="form-group mb-0">
                                    <label for="due_date" class="form-label">Due Date<span class="text-danger">*</span></label>
                                    <div class="input-wrapper">
                                        <i class="ri-calendar-line input-icon"></i>
                                        <text-input type="date" id="due_date" v-model="form.due_date"
                                            class="form-control" :class="{ 'input-error': form.errors.due_date }"
                                            @input="handleInput('due_date')" />
                                    </div>
                                    <span class="error-message" v-if="form.errors.due_date">{{ form.errors.due_date }}</span>
                                </div>
                            </div>

                            <div class="summary-section">
                                <div class="summary-header">
                                    <h3>Order Summary</h3>
                                </div>
                                <div class="summary-content">
                                    <div class="summary-row">
                                        <span>Subtotal</span>
                                        <span class="summary-value">{{ formatCurrency(subtotal) }}</span>
                                    </div>
                                    <div class="summary-row">
                                        <span>Item Discounts</span>
                                        <span class="summary-value">{{ formatCurrency(totalDiscount) }}</span>
                                    </div>
                                    <div class="summary-row total-row">
                                        <span>Calculated Total</span>
                                        <span class="summary-value total-amount">{{ formatCurrency(grandTotal) }}</span>
                                    </div>
                                </div>
                            </div>
                        </aside>
                    </div>

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

    <PaymentPromptModal
        :show="showPaymentPrompt"
        :processing="processingPayment"
        :error-message="paymentPromptError"
        :sales-order="pendingSalesOrder"
        :invoice="pendingInvoice"
        :dropdowns="dropdowns"
        @cancel="cancelPaymentPrompt"
        @proceed="proceedPayment"
    />

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
                        <p class="mb-0 text-muted">Payment recorded successfully. Do you want to print the receipt now?
                        </p>
                        <small v-if="!pendingReceiptId" class="text-danger d-block mt-2">
                            Receipt reference is not yet available. Please refresh and open it from Receipts list.
                        </small>
                        <div class="receipt-preview mt-3" v-if="pendingReceiptId">
                            <iframe :src="`/receipts/${pendingReceiptId}?option=print&type=receipt`"
                                class="receipt-preview-frame" title="Receipt Preview"></iframe>
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

    <Item :dropdowns="dropdowns" :items="form.items" @items="storeItem" @update="updateItem"
        :formatCurrency="formatCurrency" ref="item" />
    <Customer :dropdowns="dropdowns" ref="createCustomer" />
</template>

<script>
import { useForm } from '@inertiajs/vue3';
import TextInput from '@/Shared/Components/Forms/TextInput.vue';
import Item from '@/Pages/Modules/Sales/Components/SalesOrders/Modals/AddItem.vue';
import Customer from '@/Pages/Modules/Customers/Modals/Create.vue'
import PaymentPromptModal from '@/Pages/Modules/Sales/Components/SalesOrders/Modals/PaymentPromptModal.vue';

export default {
    components: { TextInput, Item, Customer, PaymentPromptModal },
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
                location_id: 0,
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
        currentUserData() {
            return this.user?.data || this.$page?.props?.user?.data || null;
        },
        currentEmployeeId() {
            const id = this.currentUserData?.employee_id;
            return id === null || id === undefined ? null : Number(id);
        },
        salesRepOptions() {
            const options = Array.isArray(this.dropdowns?.sales_reps) ? [...this.dropdowns.sales_reps] : [];
            if (!this.currentEmployeeId) {
                return options;
            }

            const exists = options.some((rep) => Number(rep?.value) === this.currentEmployeeId);
            if (!exists) {
                options.unshift({
                    value: this.currentEmployeeId,
                    name: this.currentUserData?.name || 'Current Employee',
                });
            }

            return options;
        },
        canAddItem() {
            const hasCustomer = !!this.form.customer_id;
            const hasOrderDate = !!this.form.order_date;
            const locationId = Number(this.form.location_id);
            const hasValidLocation = Number.isFinite(locationId) && locationId > 0;

            return hasCustomer && hasOrderDate && hasValidLocation;
        },
        availableProducts() {
            return this.dropdowns.products.filter(product => product.available > 0);
        },
        priceTypeOptions() {
            return [
                { value: 'retail', text: 'Retail Price' },
                { value: 'wholesale', text: 'Wholesale Price' }
            ];
        },
        selectedCustomer() {
            return this.getCustomer(this.form.customer_id) || null;
        },
        subtotal() {
            return this.form.items.reduce((total, item) => total + this.calculateItemTotal(item), 0);
        },
        totalDiscount() {
            return this.form.items.reduce((total, item) => total + this.calculateDiscountedTotal(item), 0);
        },
        grandTotal() {
            return this.subtotal - this.totalDiscount;
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
            const itemsToStore = Array.isArray(item) ? item : [item];

            itemsToStore.forEach((entry, index) => {
                const newItem = {
                    ...entry,
                    id: entry.id || (Date.now() + index), // temporary unique ID
                    discount_per_unit: entry.discount_per_unit || 0
                };
                this.form.items.push(newItem);
            });
            this.updateDiscountAmount();
        },

        updateItem(updatedItem) {
            const index = this.form.items.findIndex(item => item.id === updatedItem.id);
            if (index !== -1) {
                this.form.items.splice(index, 1, updatedItem);
            }
        },

        addItem() {
            if (!this.canAddItem) {
                return;
            }
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
            this.form.reset();
            this.form.clearErrors();
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = true;
            this.form.location_id = 0;
            this.form.order_date = new Date().toISOString().slice(0, 10);
            // Set default due date to 3 days ahead
            const dueDate = new Date();
            dueDate.setDate(dueDate.getDate() + 3);
            this.form.due_date = dueDate.toISOString().slice(0, 10);
            // Default sales rep should be the logged-in employee (not user id).
            if (this.currentEmployeeId) {
                this.form.sales_rep_id = this.currentEmployeeId;
            }

        },
        getDefaultLocationId() {
            if (!Array.isArray(this.dropdowns?.locations)) return null;
            const zamboanga = this.dropdowns.locations.find(location =>
                (location?.name || '').toLowerCase().trim() === 'zamboanga city'
            );
            return zamboanga ? zamboanga.value : null;
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
                        const selectedOrderDate = this.form.order_date;
                        const flashData = response?.props?.flash?.data ?? this.$page?.props?.flash?.data ?? null;
                        const createdOrder = flashData?.data || flashData;
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
                            this.paymentForm.payment_date = selectedOrderDate || new Date().toISOString().slice(0, 10);
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
            this.form.reset();
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

        getBatchDetails(productId, batchCode) {
            if (!productId || !batchCode) return null;
            const product = this.getProduct(productId);
            if (!product?.batch_stocks) return null;
            return product.batch_stocks.find(batch => batch.batch_code === batchCode) || null;
        },

        getReservedBatchQuantity(productId, batchCode, excludeItemId = null) {
            return this.form.items.reduce((sum, item) => {
                if (!item?.product_id || !item?.batch_code) return sum;
                if (excludeItemId && item.id === excludeItemId) return sum;
                if (item.product_id !== productId || item.batch_code !== batchCode) return sum;
                return sum + (parseFloat(item.quantity) || 0);
            }, 0);
        },

        getRowBatchAvailable(item) {
            const batch = this.getBatchDetails(item?.product_id, item?.batch_code);
            if (!batch) return 0;
            const batchQuantity = parseFloat(batch.quantity) || 0;
            const reserved = this.getReservedBatchQuantity(item.product_id, item.batch_code, item.id);
            return Math.max(batchQuantity - reserved, 0);
        },


        getCustomer(customer_id) {
            const customer = this.dropdowns.customers.find(u => u.value === customer_id);
            return customer;
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
                'Cash': 'ri-money-dollar-circle-line',
                'Credit': 'ri-bank-card-line',
                'Debit Card': 'ri-bank-card-2-line',
                'Bank Transfer': 'ri-exchange-funds-line',
            };
            return icons[mode] || 'ri-money-dollar-circle-line';
        },




    }
}
</script>

<style scoped>
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

.modal-overlay {
    position: fixed;
    inset: 0;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1050;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    padding: 15px;
}

.modal-overlay.active {
    opacity: 1;
    visibility: visible;
}

.modal-container {
    max-width: 100%;
    width: 100%;
    position: relative;
    background: #f7fbfa;
    border-radius: 28px;
    box-shadow: 0 28px 70px rgba(17, 24, 39, 0.25);
    overflow: hidden;
    transform: translateY(25px) scale(0.95);
    transition: all 0.3s ease;
}

.modal-overlay.active .modal-container {
    transform: translateY(0) scale(1);
}

.modal-header {
    padding: 16px 22px;
    border-bottom: 1px solid #d7e5de;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(140deg, #d7ece5 0%, #c7e2d9 100%);
}

.header-title {
    display: flex;
    align-items: center;
    gap: 10px;
}

.header-title i {
    width: 38px;
    height: 38px;
    border-radius: 11px;
    display: grid;
    place-items: center;
    background: rgba(26, 104, 87, 0.15);
    color: #1a6857;
    font-size: 21px;
}

.header-title h2 {
    margin: 0;
    font-size: 1.16rem;
    color: #1f2937;
    font-weight: 700;
}

.close-btn {
    width: 34px;
    height: 34px;
    border: 0;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.75);
    color: #4b5563;
    font-size: 19px;
    display: grid;
    place-items: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.close-btn:hover {
    background: #fff;
    transform: rotate(90deg);
}

.modal-body {
    padding: 1.5rem 1.75rem 1.75rem;
    max-height: 88vh;
    overflow-y: auto;
}

.form-layout {
    display: grid;
    grid-template-columns: minmax(0, 2.1fr) minmax(300px, 0.95fr);
    gap: 1.25rem;
    align-items: start;
}

.form-panel {
    background: #ffffff;
    border: 1px solid rgba(61, 141, 122, 0.12);
    border-radius: 24px;
    box-shadow: 0 16px 35px rgba(39, 84, 72, 0.08);
}

.form-panel-main {
    padding: 1.35rem;
}

.form-panel-side {
    padding: 1.25rem;
    position: sticky;
    top: 0;
}

.panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.25rem;
}

.panel-kicker {
    margin: 0;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #648b74;
}

.panel-title {
    margin: 0.2rem 0 0;
    color: #20413a;
    font-size: 1.1rem;
    font-weight: 700;
}

.detail-grid {
    display: grid;
    gap: 1rem;
}

.detail-grid-primary {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.2fr) 170px;
}

.detail-grid-secondary {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    margin-top: 1rem;
}

.form-group {
    margin-bottom: 1rem;
}

.form-label {
    display: block;
    margin-bottom: 0.375rem;
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.9rem;
}

.input-wrapper {
    position: relative;
}

.input-icon {
    position: absolute;
    left: 0.875rem;
    top: 50%;
    transform: translateY(-50%);
    color: #7f8c8d;
    font-size: 1.1rem;
    z-index: 1;
}

.form-control {
    width: 100%;
    padding: 0.85rem 1rem 0.85rem 2.9rem;
    border: 1px solid #d7e5de;
    border-radius: 14px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    background-color: #fdfefe;
}

.form-control:focus {
    outline: none;
    border-color: #3d8d7a;
    box-shadow: 0 0 0 4px rgba(61, 141, 122, 0.12);
}

.input-error {
    border-color: #e74c3c !important;
}

.error-message {
    display: block;
    margin-top: 0.375rem;
    font-size: 0.8125rem;
    color: #e74c3c;
}

.form-group-inline-action {
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
}

.btn-outline-action {
    min-height: 52px;
    border: 1px solid #d7e5de;
    border-radius: 14px;
    background: #fff;
    color: #355f55;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-outline-action:hover {
    background: #f4faf8;
    border-color: #b7cec4;
}

.section-divider {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin: 1.5rem 0 1rem;
    padding-top: 1.25rem;
    border-top: 1px solid #edf3f1;
}

.section-title {
    color: #20413a;
    font-size: 1.02rem;
    font-weight: 700;
}

.btn-add-item {
    background: linear-gradient(135deg, #3d8d7a 0%, #2f7464 100%);
    color: white;
    border: none;
    padding: 0.7rem 1rem;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.85rem;
    transition: all 0.3s ease;
    box-shadow: 0 10px 20px rgba(61, 141, 122, 0.18);
}

.btn-add-item:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 14px 25px rgba(61, 141, 122, 0.24);
}

.btn-add-item:disabled {
    background: #95a5a6;
    box-shadow: none;
    cursor: not-allowed;
}

.items-table-wrap {
    border: 1px solid #d9ebe4;
    border-radius: 20px;
    overflow: auto;
    background: linear-gradient(180deg, #fbfefd 0%, #f4faf8 100%);
    max-height: calc(100vh - 360px);
}

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
    border-color: #3d8d7a;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(61, 141, 122, 0.15);
}

.payment-mode-card.selected-payment-mode {
    border-color: #3d8d7a;
    background: linear-gradient(135deg, #3d8d7a 0%, #2e6f61 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(61, 141, 122, 0.3);
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

.pretty-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.pretty-header {
    background: linear-gradient(140deg, #eff7f4 0%, #e6f2ed 100%);
    color: #20413a;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.pretty-header th {
    border: none;
    padding: 12px 8px;
    vertical-align: middle;
    position: sticky;
    top: 0;
    background: linear-gradient(140deg, #eff7f4 0%, #e6f2ed 100%);
    z-index: 1;
}

.pretty-table tbody tr {
    transition: all 0.3s ease;
    border-bottom: 1px solid #edf3f1;
}

.pretty-table tbody tr:hover {
    background: #f4faf8;
}

.pretty-table td {
    padding: 10px 8px;
    vertical-align: middle;
    border: none;
}

.row-selected {
    background: #eef7f4;
}

.empty-table {
    padding: 0 !important;
}

.empty-table-inner {
    text-align: center;
    padding: 2rem 1rem;
    color: #74867f;
}

.empty-table-inner i {
    font-size: 2.2rem;
    color: #3d8d7a;
    margin-bottom: 0.65rem;
}

.empty-table-inner h4 {
    margin: 0 0 0.35rem;
    color: #355f55;
    font-size: 1rem;
}

.empty-table-inner p {
    margin: 0;
    font-size: 0.9rem;
}

.product-cell {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.product-name {
    font-weight: 700;
    color: #20413a;
}

.product-availability {
    color: #648b74;
}

.metric-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 34px;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    background: #e8f4ef;
    color: #2f7666;
    font-weight: 700;
}

.price-type-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.35rem;
}

.type-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.22rem 0.6rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
}

.type-badge.retail {
    background: #dbf5e7;
    color: #166534;
}

.type-badge.wholesale {
    background: #fef3c7;
    color: #92400e;
}

.action-buttons {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
}

.table-action-btn {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    border: 1px solid #d7e5de;
    background: #fff;
    color: #355f55;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.table-action-btn:hover {
    background: #eff7f4;
}

.table-action-btn.danger:hover {
    background: #e74c3c;
    border-color: #e74c3c;
    color: #fff;
}

tfoot .footer-label,
tfoot .footer-value {
    padding: 0.9rem 0.6rem !important;
    background: #f1f7f4;
    font-weight: 700;
    color: #20413a;
}

.summary-metrics {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.summary-metric-card {
    padding: 0.95rem 1rem;
    border-radius: 18px;
    background: linear-gradient(180deg, #f6fbf9 0%, #eef7f3 100%);
    border: 1px solid #ddebe5;
}

.summary-metric-label {
    display: block;
    color: #6c877d;
    font-size: 0.76rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
}

.summary-metric-value {
    display: block;
    margin-top: 0.35rem;
    color: #20413a;
    font-size: 1rem;
    line-height: 1.35;
}

.summary-card,
.summary-section {
    background: linear-gradient(135deg, #f8fbfa 0%, #f0f7f4 100%);
    border: 1px solid #e0ece7;
    border-radius: 20px;
    padding: 1.15rem;
    margin-bottom: 1rem;
}

.summary-card-header h4,
.summary-header h3 {
    margin: 0 0 1rem;
    font-size: 1rem;
    font-weight: 700;
    color: #20413a;
}

.info-list {
    display: grid;
    gap: 0.75rem;
}

.info-row {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.info-row span {
    color: #648b74;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.info-row strong {
    color: #20413a;
    font-size: 0.92rem;
}

.empty-side-note {
    color: #74867f;
    font-size: 0.9rem;
}

.summary-content {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e4ece8;
}

.summary-row:last-child {
    border-bottom: none;
}

.summary-value {
    font-weight: 600;
    color: #374151;
}

.total-row .total-amount {
    font-size: 1.2rem;
    color: #059669;
}

.success-alert,
.error-alert {
    margin-top: 1rem;
    padding: 0.875rem 1.25rem;
    border-radius: 14px;
    display: flex;
    align-items: center;
    gap: 0.625rem;
}

.success-alert {
    background: linear-gradient(135deg, #e6f7ee 0%, #d8f2e3 100%);
    color: #155724;
    border: 1px solid #b9e6ca;
}

.error-alert {
    background: #fee2e2;
    border: 1px solid #fecaca;
    color: #991b1b;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    margin-top: 1.5rem;
}

.btn {
    padding: 0.8rem 1.15rem;
    border: none;
    border-radius: 14px;
    cursor: pointer;
    font-size: 0.85rem;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.btn-cancel {
    background-color: #ffffff;
    color: #60756d;
    border: 1px solid #d7e5de;
}

.btn-cancel:hover {
    background-color: #f7fbfa;
    border-color: #b7cec4;
}

.btn-save {
    background: linear-gradient(135deg, #3d8d7a 0%, #2e6f61 100%);
    color: white;
    box-shadow: 0 12px 24px rgba(61, 141, 122, 0.28);
}

.btn-save:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 16px 28px rgba(61, 141, 122, 0.34);
}

.btn-save:disabled {
    background: #95a5a6;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}

@media (max-width: 992px) {
    .form-layout {
        grid-template-columns: 1fr;
    }

    .form-panel-side {
        position: static;
    }

    .detail-grid-primary,
    .detail-grid-secondary {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .modal-body {
        padding: 1.25rem;
    }

    .form-actions {
        flex-direction: column-reverse;
    }

    .btn {
        width: 100%;
        justify-content: center;
    }

    .summary-metrics {
        grid-template-columns: 1fr;
    }
}
</style>
