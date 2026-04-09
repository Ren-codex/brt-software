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
                                        <b-form-select v-model="customerSelection" :options="customerOptions"
                                            text-field="name" value-field="value"
                                            :class="{ 'input-error': form.errors.customer_id }"
                                            class="form-control"
                                            @change="handleCustomerSelectionChange">
                                            <template #first>
                                                <b-form-select-option :value="null" disabled>Select Customer Type</b-form-select-option>
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
                            <span class="error-message" v-if="form.errors.items">{{ form.errors.items }}</span>
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
                                    <div class="customer-balance-alert" :class="{ 'has-balance': hasCustomerOutstandingBalance }">
                                        <span class="customer-balance-label">Existing Credit Balance</span>
                                        <strong class="customer-balance-value">{{ formatCurrency(customerOutstandingBalance) }}</strong>
                                    </div>
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

                            <div v-if="editable" class="summary-card">
                                <div class="summary-card-header">
                                    <h4>Payment Mode</h4>
                                </div>
                                <div class="payment-mode-grid">
                                    <div v-for="type in payment_types" :key="type"
                                        :class="{ 'selected-payment-mode': selectedEditablePaymentType === type }"
                                        class="payment-mode-card" @click="selectPaymentType(type)">
                                        <i :class="getPaymentModeIcon(type)" class="payment-icon"></i>
                                        <span class="payment-label">{{ type }}</span>
                                    </div>
                                </div>
                                <div v-if="selectedEditablePaymentType === 'Cash'" class="payment-subtype-section">
                                    <div class="payment-subtype-label">Cash Options</div>
                                    <div class="payment-mode-grid payment-subtype-grid">
                                        <div v-for="mode in cash_payment_modes" :key="mode"
                                            :class="{ 'selected-payment-mode': form.payment_mode === mode }"
                                            class="payment-mode-card payment-subtype-card" @click="selectPaymentMode(mode)">
                                            <i :class="getPaymentModeIcon(mode)" class="payment-icon"></i>
                                            <span class="payment-label">{{ mode }}</span>
                                        </div>
                                    </div>
                                </div>
                                <span class="error-message" v-if="form.errors.payment_mode">{{ form.errors.payment_mode }}</span>
                            </div>

                            <div v-if="editable && selectedEditablePaymentType === 'Credit'" class="summary-card">
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
                            :disabled="form.processing || !hasCustomerSelection || !form.order_date">
                            <i class="ri-save-line" v-if="!form.processing"></i>
                            <i class="ri-loader-4-line spinner" v-else></i>
                            {{ form.processing ? 'Saving...' : (editable ? 'Save Order' : 'Review Order') }}
                        </button>
                    </div>
                </form>


            </div>
        </div>
    </div>

    <div v-if="showOrderReview" class="modal-overlay active order-review-modal" @click.self="closeOrderReview">
        <div class="modal-container modal-lg" @click.stop>
            <div class="modal-header bg-primary text-white">
                <h4 class="mb-0 text-white">
                    <i class="ri-file-list-3-line me-2"></i>
                    Review Sales Order
                </h4>
                <button class="close-btn text-white" @click="closeOrderReview">
                    <i class="ri-close-line fs-20"></i>
                </button>
            </div>
            <div class="modal-body p-4">
                <div class="review-overview-card">
                    <div class="review-overview-copy">
                        <h5>Check the order details before creating this sales order.</h5>
                    </div>
                    <div class="review-overview-metrics">
                        <div class="review-overview-metric">
                            <span>Total Items</span>
                            <strong>{{ form.items.length }}</strong>
                        </div>
                        <div class="review-overview-metric review-overview-metric-total">
                            <span>Grand Total</span>
                            <strong>{{ formatCurrency(grandTotal) }}</strong>
                        </div>
                    </div>
                </div>

                <div class="review-grid mt-3">
                    <div class="review-section-card">
                        <div class="review-section-head">
                            <div>
                                <span class="review-section-kicker">Order Snapshot</span>
                                <h6>Customer and fulfillment details</h6>
                            </div>
                        </div>
                        <div class="review-info-grid">
                            <div class="review-balance-banner" :class="{ 'has-balance': hasCustomerOutstandingBalance }">
                                <span>Existing Credit Balance</span>
                                <strong>{{ formatCurrency(customerOutstandingBalance) }}</strong>
                            </div>
                            <div class="review-info-item">
                                <span>Order Date</span>
                                <strong>{{ form.order_date || '-' }}</strong>
                            </div>
                            <div class="review-info-item">
                                <span>Customer</span>
                                <strong>{{ selectedCustomer?.name || '-' }}</strong>
                            </div>
                            <div class="review-info-item">
                                <span>Sales Rep</span>
                                <strong>{{ getSalesRepName(form.sales_rep_id) }}</strong>
                            </div>
                            <div class="review-info-item">
                                <span>Driver</span>
                                <strong>{{ getDriverName(form.driver_id) }}</strong>
                            </div>
                            <div class="review-info-item">
                                <span>Location</span>
                                <strong>{{ getLocationName(form.location_id) }}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="review-section-card review-totals-card">
                        <div class="review-section-head">
                            <div>
                                <h6>Charges for this order</h6>
                            </div>
                        </div>
                        <div class="review-total-list">
                            <div class="review-total-row">
                                <span>Subtotal</span>
                                <strong>{{ formatCurrency(subtotal) }}</strong>
                            </div>
                            <div class="review-total-row">
                                <span>Total Discount</span>
                                <strong>{{ formatCurrency(totalDiscount) }}</strong>
                            </div>
                            <div class="review-total-row review-total-row-grand">
                                <span>Grand Total</span>
                                <strong>{{ formatCurrency(grandTotal) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="review-section-card mt-3">
                    <div class="review-section-head review-section-head-items">
                        <div>
                            <h6>Order line items</h6>
                        </div>
                        <div class="review-items-badge">
                            <i class="ri-shopping-bag-line"></i>
                            <span>{{ form.items.length }} item{{ form.items.length === 1 ? '' : 's' }}</span>
                        </div>
                    </div>

                    <div v-if="form.items.length" class="review-items-table-wrap">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0 review-items-table">
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
                                    <tr v-for="(item, idx) in form.items" :key="item.id || idx">
                                        <td>
                                            <div class="review-product-cell">
                                                <strong>{{ getProduct(item.product_id).name || '-' }}</strong>
                                                <small>Price type: {{ item.price_type || 'retail' }}</small>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="review-batch-pill">{{ item.batch_code || '-' }}</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="review-qty-pill">{{ item.quantity || 0 }}</span>
                                        </td>
                                        <td class="text-end">{{ formatCurrency(item.price || 0) }}</td>
                                        <td class="text-end">{{ formatCurrency(calculateDiscountedTotal(item)) }}</td>
                                        <td class="text-end">{{ formatCurrency(calculateItemTotal(item) - calculateDiscountedTotal(item)) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div v-else class="review-empty-state">
                        <i class="ri-inbox-archive-line"></i>
                        <h6>No items added yet</h6>
                        <p>Add products to the order before moving to payment confirmation.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 p-4">
                <button type="button" class="btn btn-outline-secondary me-3" @click="closeOrderReview" :disabled="form.processing">
                    <i class="ri-arrow-left-line me-2"></i>
                    Back to Edit
                </button>
                <button type="button" class="btn btn-primary" @click="openPaymentTypeModal" :disabled="form.processing">
                    <i class="ri-loader-4-line spinner" v-if="form.processing"></i>
                    <i class="ri-check-line me-2" v-else></i>
                    {{ form.processing ? 'Processing...' : 'Confirm Order' }}
                </button>
            </div>
        </div>
    </div>

    <div v-if="showPaymentTypeModal" class="modal-overlay active order-review-modal" @click.self="closePaymentTypeModal">
        <div class="modal-container modal-md" @click.stop>
            <div class="modal-header bg-primary text-white">
                <h4 class="mb-0 text-white">
                    <i class="ri-bank-card-line me-2"></i>
                    Select Payment Type
                </h4>
                <button class="close-btn text-white" @click="closePaymentTypeModal">
                    <i class="ri-close-line fs-20"></i>
                </button>
            </div>
            <div class="modal-body p-4 payment-type-modal-body">
                <div class="payment-section-heading">
                    <span class="payment-section-kicker">Step 1</span>
                    <h5>Choose Sales Type</h5>
                    <p>Select how this order will be settled.</p>
                </div>

                <div class="payment-type-grid">
                    <div v-for="type in review_payment_types" :key="type"
                        :class="{
                            'selected-payment-mode': selectedPaymentType === type,
                            'payment-choice-disabled': type === 'Credit Sales' && isWalkInCustomer
                        }"
                        class="payment-mode-card payment-choice-card" @click="selectPaymentType(type)">
                        <div class="payment-choice-icon-wrap">
                            <i :class="getPaymentModeIcon(type)" class="payment-icon"></i>
                        </div>
                        <span class="payment-label">{{ type }}</span>
                        <small class="payment-choice-copy">
                            {{ type === 'Cash Sales' ? 'Collect payment right away' : (isWalkInCustomer ? 'Unavailable for walk-in customers' : 'Settle on a later due date') }}
                        </small>
                    </div>
                </div>
                <span class="error-message" v-if="form.errors.payment_mode">{{ form.errors.payment_mode }}</span>

                <div v-if="selectedPaymentType === 'Cash Sales'" class="payment-subtype-section payment-detail-card">
                    <div class="payment-section-heading payment-section-heading-sm">
                        <span class="payment-section-kicker">Step 2</span>
                        <h5>Cash Payment Method</h5>
                        <p>Choose how the cashier will receive the payment.</p>
                    </div>
                    <div class="payment-mode-grid payment-subtype-grid">
                        <div v-for="mode in cash_payment_modes" :key="mode"
                            :class="{ 'selected-payment-mode': form.payment_mode === mode }"
                            class="payment-mode-card payment-subtype-card payment-choice-card" @click="selectPaymentMode(mode)">
                            <div class="payment-choice-icon-wrap">
                                <i :class="getPaymentModeIcon(mode)" class="payment-icon"></i>
                            </div>
                            <span class="payment-label">{{ mode }}</span>
                            <small class="payment-choice-copy">
                                {{ mode === 'Cash' ? 'Cashier accepts cash and gives change' : 'Paid through bank transfer' }}
                            </small>
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer bg-light border-0 p-4">
                <button type="button" class="btn btn-outline-secondary payment-modal-btn me-3" @click="closePaymentTypeModal" :disabled="form.processing">
                    <i class="ri-arrow-left-line me-2"></i>
                    Back
                </button>
                <button type="button" class="btn btn-primary payment-modal-btn" @click="confirmCreateOrder" :disabled="isPaymentTypeActionDisabled">
                    <i class="ri-loader-4-line spinner" v-if="form.processing"></i>
                    <i class="ri-check-line me-2" v-else></i>
                    {{ form.processing ? 'Processing...' : (requiresCashReceivedModal ? 'Next' : 'Create Order') }}
                </button>
            </div>
        </div>
    </div>

    <div v-if="showCashReceivedModal" class="modal-overlay active order-review-modal" @click.self="closeCashReceivedModal">
        <div class="modal-container modal-md" @click.stop>
            <div class="modal-header bg-primary text-white">
                <h4 class="mb-0 text-white">
                    <i class="ri-money-dollar-circle-line me-2"></i>
                    Amount Received
                </h4>
                <button class="close-btn text-white" @click="closeCashReceivedModal">
                    <i class="ri-close-line fs-20"></i>
                </button>
            </div>
            <div class="modal-body p-4 payment-type-modal-body">
                <div class="cash-change-display mt-0">
                    <span>Total Amount to Pay</span>
                    <strong>{{ formatCurrency(grandTotal) }}</strong>
                </div>

                <div class="payment-detail-card form-group mb-0 mt-3">
                    <div class="payment-section-heading payment-section-heading-sm">
                        <span class="payment-section-kicker">Final Step</span>
                        <h5>Cashier Input</h5>
                        <p>Enter the amount received from the customer.</p>
                    </div>
                    <label for="cash_received_modal" class="form-label">Amount Received<span class="text-danger">*</span></label>
                    <div class="input-wrapper">
                        <i class="ri-money-dollar-circle-line input-icon"></i>
                        <input
                            id="cash_received_modal"
                            v-model.number="cashReceivedAmount"
                            type="number"
                            min="0"
                            step="0.01"
                            class="form-control"
                            :class="{ 'input-error': cashChargeError }"
                            placeholder="Enter amount received"
                            @input="validateCashReceivedAmount"
                        />
                    </div>
                    <span class="error-message" v-if="cashChargeError">{{ cashChargeError }}</span>
                    <div v-else-if="cashReceivedAmount" class="cash-change-note">
                        <span>Change</span>
                        <strong>{{ formatCurrency(cashChangeAmount > 0 ? cashChangeAmount : 0) }}</strong>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 p-4">
                <button type="button" class="btn btn-outline-secondary payment-modal-btn me-3" @click="closeCashReceivedModal" :disabled="form.processing">
                    <i class="ri-arrow-left-line me-2"></i>
                    Back
                </button>
                <button type="button" class="btn btn-primary payment-modal-btn" @click="submitCashCharge" :disabled="form.processing || isCashChargeInvalid">
                    <i class="ri-loader-4-line spinner" v-if="form.processing"></i>
                    <i class="ri-check-line me-2" v-else></i>
                    {{ form.processing ? 'Processing...' : 'Charge' }}
                </button>
            </div>
        </div>
    </div>

    <div v-if="showCreditVerificationModal" class="modal-overlay active order-review-modal" @click.self="closeCreditVerificationModal">
        <div class="modal-container modal-md" @click.stop>
            <div class="modal-header bg-primary text-white">
                <h4 class="mb-0 text-white">
                    <i class="ri-shield-check-line me-2"></i>
                    Credit Sales Verification
                </h4>
                <button class="close-btn text-white" @click="closeCreditVerificationModal">
                    <i class="ri-close-line fs-20"></i>
                </button>
            </div>
            <div class="modal-body p-4 payment-type-modal-body">
                <div class="payment-success-card text-start">
                    <div class="payment-section-heading payment-section-heading-sm">
                        <span class="payment-section-kicker">Final Step</span>
                        <h5>Confirm this credit sale</h5>
                        <p>Verify this transaction before setting the payment schedule.</p>
                    </div>

                    <div class="credit-verification-summary">
                        <div class="credit-balance-callout" :class="{ 'has-balance': hasCustomerOutstandingBalance }">
                            <span class="credit-balance-callout-label">Existing Credit Balance</span>
                            <strong class="credit-balance-callout-value">{{ formatCurrency(customerOutstandingBalance) }}</strong>
                        </div>
                        <div class="payment-success-row">
                            <span>Customer</span>
                            <strong>{{ selectedCustomer?.name || 'Walk-in Customer' }}</strong>
                        </div>
                        <div class="payment-success-row">
                            <span>Amount Due</span>
                            <strong>{{ formatCurrency(grandTotal) }}</strong>
                        </div>
                        <div v-if="isCreditVerificationMatched" class="payment-success-row">
                            <span>Due Date</span>
                            <strong>{{ form.due_date || 'Not set' }}</strong>
                        </div>
                    </div>

                    <label for="credit_verification_input" class="form-label mt-3">Type <strong>CREDIT</strong> to continue</label>
                    <div class="input-wrapper">
                        <i class="ri-edit-2-line input-icon"></i>
                        <input
                            id="credit_verification_input"
                            v-model.trim="creditVerificationText"
                            type="text"
                            class="form-control"
                            :class="{ 'input-error': creditVerificationError }"
                            placeholder="Type CREDIT"
                            @input="creditVerificationError = null"
                        />
                    </div>
                    <span class="error-message" v-if="creditVerificationError">{{ creditVerificationError }}</span>

                    <div v-if="isCreditVerificationMatched" class="payment-detail-card form-group mb-0 mt-3">
                        <div class="payment-section-heading payment-section-heading-sm">
                            <span class="payment-section-kicker">Schedule</span>
                            <h5>Set due date</h5>
                            <p>Choose when this credit sale should be settled.</p>
                        </div>
                        <label for="credit_verification_due_date" class="form-label">Due Date<span class="text-danger">*</span></label>
                        <div class="input-wrapper">
                            <i class="ri-calendar-line input-icon"></i>
                            <text-input
                                type="date"
                                id="credit_verification_due_date"
                                v-model="form.due_date"
                                class="form-control"
                                :class="{ 'input-error': form.errors.due_date }"
                                @input="handleInput('due_date')"
                            />
                        </div>
                        <span class="error-message" v-if="form.errors.due_date">{{ form.errors.due_date }}</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 p-4">
                <button type="button" class="btn btn-outline-secondary payment-modal-btn me-3" @click="closeCreditVerificationModal" :disabled="form.processing">
                    <i class="ri-arrow-left-line me-2"></i>
                    Back
                </button>
                <button type="button" class="btn btn-primary payment-modal-btn" @click="submitCreditSale" :disabled="form.processing || !canSubmitCreditSale">
                    <i class="ri-loader-4-line spinner" v-if="form.processing"></i>
                    <i class="ri-check-line me-2" v-else></i>
                    {{ form.processing ? 'Processing...' : 'Confirm Credit Sale' }}
                </button>
            </div>
        </div>
    </div>

    <div v-if="showBankTransferModal" class="modal-overlay active order-review-modal" @click.self="closeBankTransferModal">
        <div class="modal-container modal-md" @click.stop>
            <div class="modal-header bg-primary text-white">
                <h4 class="mb-0 text-white">
                    <i class="ri-exchange-funds-line me-2"></i>
                    Bank Transfer Details
                </h4>
                <button class="close-btn text-white" @click="closeBankTransferModal">
                    <i class="ri-close-line fs-20"></i>
                </button>
            </div>
            <div class="modal-body p-4 payment-type-modal-body">
                <div class="cash-change-display mt-0">
                    <span>Total Amount to Pay</span>
                    <strong>{{ formatCurrency(grandTotal) }}</strong>
                </div>

                <div class="payment-detail-card form-group mb-0 mt-3">
                    <div class="payment-section-heading payment-section-heading-sm">
                        <span class="payment-section-kicker">Final Step</span>
                        <h5>Transfer Information</h5>
                        <p>Enter the bank transfer details before we create the order.</p>
                    </div>

                    <div class="form-group">
                        <label for="bank_transfer_name" class="form-label">Bank Name<span class="text-danger">*</span></label>
                        <div class="input-wrapper">
                            <i class="ri-bank-line input-icon"></i>
                            <input
                                id="bank_transfer_name"
                                v-model.trim="bankTransferDetails.bank_name"
                                type="text"
                                class="form-control"
                                placeholder="Enter bank name"
                            />
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="bank_transfer_reference" class="form-label">Transfer Reference Number<span class="text-danger">*</span></label>
                        <div class="input-wrapper">
                            <i class="ri-file-list-3-line input-icon"></i>
                            <input
                                id="bank_transfer_reference"
                                v-model.trim="bankTransferDetails.reference_number"
                                type="text"
                                class="form-control"
                                placeholder="Enter transfer reference number"
                            />
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label for="bank_transfer_amount" class="form-label">Amount Paid<span class="text-danger">*</span></label>
                        <div class="input-wrapper">
                            <i class="ri-money-dollar-circle-line input-icon"></i>
                            <input
                                id="bank_transfer_amount"
                                v-model.number="bankTransferDetails.amount_paid"
                                type="number"
                                min="0"
                                step="0.01"
                                class="form-control"
                                :class="{ 'input-error': bankTransferError }"
                                placeholder="Enter amount paid"
                                @input="validateBankTransferAmount"
                            />
                        </div>
                    </div>

                    <span class="error-message" v-if="bankTransferError">{{ bankTransferError }}</span>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 p-4">
                <button type="button" class="btn btn-outline-secondary payment-modal-btn me-3" @click="closeBankTransferModal" :disabled="form.processing">
                    <i class="ri-arrow-left-line me-2"></i>
                    Back
                </button>
                <button type="button" class="btn btn-primary payment-modal-btn" @click="submitBankTransfer" :disabled="form.processing || isBankTransferInvalid">
                    <i class="ri-loader-4-line spinner" v-if="form.processing"></i>
                    <i class="ri-check-line me-2" v-else></i>
                    {{ form.processing ? 'Processing...' : 'Create Order' }}
                </button>
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

    <div v-if="showChargeSuccessModal" class="modal-overlay active order-review-modal" @click.self="closeChargeSuccessModal">
        <div class="modal-container modal-md" @click.stop>
            <div class="modal-header bg-primary text-white">
                <h4 class="mb-0 text-white">
                    <i class="ri-checkbox-circle-line me-2"></i>
                    Payment Successful
                </h4>
                <button class="close-btn text-white" @click="closeChargeSuccessModal">
                    <i class="ri-close-line fs-20"></i>
                </button>
            </div>
            <div class="modal-body p-4 payment-type-modal-body">
                <div class="payment-success-card">
                    <div class="payment-success-icon">
                        <i class="ri-checkbox-circle-fill"></i>
                    </div>
                    <h5>Charge completed successfully</h5>
                    <p>The cash payment for this order has been recorded.</p>
                    <div class="payment-success-breakdown">
                        <div class="payment-success-row">
                            <span>Amount Paid</span>
                            <strong>{{ formatCurrency(pendingCashPaid) }}</strong>
                        </div>
                        <div class="payment-success-row">
                            <span>Cash Received</span>
                            <strong>{{ formatCurrency(pendingCashReceived) }}</strong>
                        </div>
                        <div class="payment-success-row payment-success-row-highlight">
                            <span>Change</span>
                            <strong>{{ formatCurrency(pendingCashChange) }}</strong>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 p-4">
                <button type="button" class="btn btn-outline-secondary payment-modal-btn me-3" @click="closeChargeSuccessModal">
                    <i class="ri-close-line me-2"></i>
                    Close
                </button>
                <button type="button" class="btn btn-primary payment-modal-btn" @click="openPrintPromptAfterCharge">
                    <i class="ri-printer-line me-2"></i>
                    Print Sales Invoice
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
                        <p class="mb-0 text-muted">Payment recorded successfully. Do you want to print the receipt now?
                        </p>
                        <div v-if="pendingCashChange > 0" class="cash-change-display mt-3">
                            <span>Cash Change</span>
                            <strong>{{ formatCurrency(pendingCashChange) }}</strong>
                        </div>
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
            showOrderReview: false,
            showPaymentTypeModal: false,
            showCashReceivedModal: false,
            showCreditVerificationModal: false,
            showBankTransferModal: false,
            showChargeSuccessModal: false,
            showPrintPrompt: false,
            cashReceivedAmount: null,
            cashChargeError: null,
            creditVerificationText: '',
            creditVerificationError: null,
            bankTransferError: null,
            selectedReviewPaymentType: null,
            bankTransferDetails: {
                bank_name: '',
                reference_number: '',
                amount_paid: null,
            },
            pendingCashChange: 0,
            pendingCashPaid: 0,
            pendingCashReceived: 0,
            pendingReceiptId: null,
            pendingInvoice: null,
            pendingSalesOrder: null,
            customerSelection: null,
            payment_types: [
                'Cash',
                'Credit',
            ],
            review_payment_types: [
                'Cash Sales',
                'Credit Sales',
            ],
            cash_payment_modes: [
                'Cash',
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
        customerOptions() {
            const customers = Array.isArray(this.dropdowns?.customers) ? this.dropdowns.customers : [];
            return [
                {
                    value: '__walk_in__',
                    name: 'Walk-in Customer',
                    address: '-',
                    contact_number: '-',
                    email: '-',
                },
                ...customers,
            ];
        },
        isWalkInCustomer() {
            return this.customerSelection === '__walk_in__';
        },
        hasCustomerSelection() {
            return this.isWalkInCustomer || !!this.customerSelection;
        },
        canAddItem() {
            const hasCustomer = this.hasCustomerSelection;
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
            if (this.isWalkInCustomer) {
                return {
                    name: 'Walk-in Customer',
                    address: '-',
                    contact_number: '-',
                    email: '-',
                };
            }
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
        selectedPaymentType() {
            if (this.selectedReviewPaymentType) return this.selectedReviewPaymentType;
            const paymentMode = String(this.form.payment_mode || '').trim().toLowerCase();
            if (['credit', 'credit sales'].includes(paymentMode)) return 'Credit Sales';
            if (paymentMode) return 'Cash Sales';
            return null;
        },
        selectedEditablePaymentType() {
            const paymentMode = String(this.form.payment_mode || '').trim().toLowerCase();
            if (['credit', 'credit sales'].includes(paymentMode)) return 'Credit';
            if (paymentMode) return 'Cash';
            return null;
        },
        isCashChargeMode() {
            return this.selectedPaymentType === 'Cash Sales' && this.form.payment_mode === 'Cash';
        },
        requiresCashReceivedModal() {
            return this.selectedPaymentType === 'Cash Sales' && this.form.payment_mode === 'Cash';
        },
        cashChangeAmount() {
            const received = Number(this.cashReceivedAmount) || 0;
            return received - this.grandTotal;
        },
        isCashChargeInvalid() {
            if (!this.showCashReceivedModal && !this.isCashChargeMode) return false;
            const received = Number(this.cashReceivedAmount);
            return !Number.isFinite(received) || received < this.grandTotal;
        },
        isCreditVerificationMatched() {
            return this.creditVerificationText.trim().toUpperCase() === 'CREDIT';
        },
        customerOutstandingBalance() {
            return Number(this.selectedCustomer?.outstanding_balance || 0);
        },
        hasCustomerOutstandingBalance() {
            return this.customerOutstandingBalance > 0;
        },
        canSubmitCreditSale() {
            return this.isCreditVerificationMatched && !!this.form.due_date;
        },
        isPaymentTypeActionDisabled() {
            if (this.form.processing || !this.selectedPaymentType) return true;
            if (this.selectedPaymentType === 'Cash Sales' && !this.cash_payment_modes.includes(this.form.payment_mode)) {
                return true;
            }
            return false;
        },
        isBankTransferInvalid() {
            if (!this.showBankTransferModal) return false;
            const amountPaid = Number(this.bankTransferDetails.amount_paid);
            return !this.bankTransferDetails.bank_name
                || !this.bankTransferDetails.reference_number
                || !Number.isFinite(amountPaid)
                || amountPaid < this.grandTotal;
        },

    },
    methods: {
        resetBankTransferDetails() {
            this.bankTransferDetails = {
                bank_name: '',
                reference_number: '',
                amount_paid: null,
            };
            this.bankTransferError = null;
        },
        handleCustomerSelectionChange() {
            this.form.customer_id = this.isWalkInCustomer ? null : this.customerSelection;
            this.handleInput('customer_id');
        },
        syncCustomerSelectionToForm() {
            this.form.customer_id = this.isWalkInCustomer ? null : this.customerSelection;
        },
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
            this.form.errors.items = false;
            this.updateDiscountAmount();
        },

        updateItem(updatedItem) {
            const index = this.form.items.findIndex(item => item.id === updatedItem.id);
            if (index !== -1) {
                this.form.items.splice(index, 1, updatedItem);
            }
            this.form.errors.items = false;
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
            this.form.errors.items = false;
            this.updateDiscountAmount();
        },

        show() {
            this.form.reset();
            this.form.clearErrors();
            this.editable = false;
            this.saveSuccess = false;
            this.showOrderReview = false;
            this.showPaymentTypeModal = false;
            this.showCashReceivedModal = false;
            this.showCreditVerificationModal = false;
            this.showBankTransferModal = false;
            this.showChargeSuccessModal = false;
            this.cashReceivedAmount = null;
            this.cashChargeError = null;
            this.creditVerificationText = '';
            this.creditVerificationError = null;
            this.resetBankTransferDetails();
            this.selectedReviewPaymentType = null;
            this.customerSelection = null;
            this.pendingCashChange = 0;
            this.pendingCashPaid = 0;
            this.pendingCashReceived = 0;
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
            this.customerSelection = data.customer?.id ?? '__walk_in__';
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
            this.showPaymentTypeModal = false;
            this.showCashReceivedModal = false;
            this.showCreditVerificationModal = false;
            this.showBankTransferModal = false;
            this.showChargeSuccessModal = false;
            this.cashReceivedAmount = null;
            this.cashChargeError = null;
            this.creditVerificationText = '';
            this.creditVerificationError = null;
            this.resetBankTransferDetails();
            this.selectedReviewPaymentType = null;
            this.pendingCashChange = 0;
            this.pendingCashPaid = 0;
            this.pendingCashReceived = 0;
            this.showModal = true;
        },
        submit() {
            this.syncCustomerSelectionToForm();
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
                if (!this.validateBeforeReview()) {
                    return;
                }
                this.showOrderReview = true;
            }
        },
        validateBeforeReview() {
            this.form.clearErrors();
            const errors = {};
            const locationId = Number(this.form.location_id);

            if (!this.form.order_date) {
                errors.order_date = 'Order date is required.';
            }
            if (!this.hasCustomerSelection) {
                errors.customer_id = 'Customer is required.';
            }
            if (!this.form.sales_rep_id) {
                errors.sales_rep_id = 'Sales rep is required.';
            }
            if (!Number.isFinite(locationId) || locationId <= 0) {
                errors.location_id = 'Location is required.';
            }
            if (!this.form.items.length) {
                errors.items = 'Add at least one item before reviewing the order.';
            } else {
                const hasIncompleteItem = this.form.items.some((item) => {
                    const quantity = Number(item.quantity);
                    const price = Number(item.price);

                    return !item.product_id
                        || !item.batch_code
                        || !Number.isFinite(quantity)
                        || quantity <= 0
                        || !Number.isFinite(price)
                        || price < 0
                        || !item.price_type;
                });

                if (hasIncompleteItem) {
                    errors.items = 'Complete all item details before reviewing the order.';
                }
            }

            Object.entries(errors).forEach(([field, message]) => {
                this.form.errors[field] = message;
            });

            return Object.keys(errors).length === 0;
        },
        openPaymentTypeModal() {
            this.showOrderReview = false;
            this.showPaymentTypeModal = true;
            this.cashChargeError = null;
            this.creditVerificationError = null;
            this.bankTransferError = null;
            this.selectedReviewPaymentType = this.selectedPaymentType;
        },
        confirmCreateOrder() {
            if (this.selectedPaymentType === 'Credit Sales') {
                this.form.payment_mode = 'Credit Sales';
                this.showPaymentTypeModal = false;
                this.showCreditVerificationModal = true;
                this.creditVerificationText = '';
                this.creditVerificationError = null;
                return;
            } else if (!this.cash_payment_modes.includes(this.form.payment_mode)) {
                return;
            }

            if (this.requiresCashReceivedModal) {
                this.showPaymentTypeModal = false;
                this.showCashReceivedModal = true;
                this.cashChargeError = null;
                return;
            }

            if (this.form.payment_mode === 'Bank Transfer') {
                this.showPaymentTypeModal = false;
                this.showBankTransferModal = true;
                this.bankTransferError = null;
                if (!this.bankTransferDetails.amount_paid) {
                    this.bankTransferDetails.amount_paid = this.grandTotal;
                }
                return;
            }

            this.submitOrderCreation();
        },
        submitCashCharge() {
            if (!this.validateCashReceivedAmount()) {
                return;
            }
            this.submitOrderCreation();
        },
        validateCashReceivedAmount() {
            const received = Number(this.cashReceivedAmount);

            if (!Number.isFinite(received)) {
                this.cashChargeError = 'Please enter the amount received.';
                return false;
            }

            if (received < this.grandTotal) {
                this.cashChargeError = `Amount received cannot be less than ${this.formatCurrency(this.grandTotal)}.`;
                return false;
            }

            this.cashChargeError = null;
            return true;
        },
        submitBankTransfer() {
            if (!this.bankTransferDetails.bank_name || !this.bankTransferDetails.reference_number) {
                this.bankTransferError = 'Please complete all bank transfer details.';
                return;
            }

            if (!this.validateBankTransferAmount()) {
                return;
            }

            this.bankTransferError = null;
            this.submitOrderCreation();
        },
        validateBankTransferAmount() {
            const amountPaid = Number(this.bankTransferDetails.amount_paid);

            if (!Number.isFinite(amountPaid)) {
                this.bankTransferError = 'Please enter the amount paid.';
                return false;
            }

            if (amountPaid < this.grandTotal) {
                this.bankTransferError = `Amount paid cannot be less than ${this.formatCurrency(this.grandTotal)}.`;
                return false;
            }

            this.bankTransferError = null;
            return true;
        },
        submitOrderCreation() {
            this.form.action = null;
            this.form.post('/sales-orders', {
                preserveScroll: true,
                onSuccess: (response) => {
                    const selectedOrderDate = this.form.order_date;
                    const flashData = response?.props?.flash?.data ?? this.$page?.props?.flash?.data ?? null;
                    const createdOrder = flashData?.data || flashData;
                    const isCash = ['cash', 'cash sales'].includes((this.form.payment_mode || '').toLowerCase());
                    const isCashCharge = this.form.payment_mode === 'Cash';
                    const invoice = createdOrder?.invoices?.[0] || null;

                    this.showOrderReview = false;
                    this.showPaymentTypeModal = false;
                    this.showCashReceivedModal = false;
                    this.showBankTransferModal = false;
                    const changeAmount = this.isCashChargeMode ? Math.max(this.cashChangeAmount, 0) : 0;
                    const cashReceivedAmount = this.isCashChargeMode ? (Number(this.cashReceivedAmount) || 0) : 0;
                    this.form.reset();
                    this.hide();
                    this.pendingCashChange = changeAmount;
                    this.pendingCashReceived = cashReceivedAmount;
                    this.pendingCashPaid = 0;

                    if (isCashCharge && invoice) {
                        this.pendingSalesOrder = createdOrder;
                        this.pendingInvoice = invoice;
                        this.paymentForm.id = invoice.id;
                        this.paymentForm.balance_due = invoice.balance_due || 0;
                        this.paymentForm.amount_paid = invoice.balance_due || 0;
                        this.pendingCashPaid = invoice.balance_due || 0;
                        this.paymentForm.payment_date = selectedOrderDate || new Date().toISOString().slice(0, 10);
                        this.processCashPayment();
                        return;
                    }

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
                onError: () => {
                    this.showCashReceivedModal = false;
                    this.showBankTransferModal = false;
                    this.showPaymentTypeModal = false;
                    this.showModal = true;
                },
            });
        },
        closeOrderReview() {
            this.showOrderReview = false;
        },
        closePaymentTypeModal() {
            this.showPaymentTypeModal = false;
            this.cashChargeError = null;
            this.showOrderReview = true;
        },
        closeCashReceivedModal() {
            this.showCashReceivedModal = false;
            this.cashChargeError = null;
            this.showPaymentTypeModal = true;
        },
        closeCreditVerificationModal() {
            this.showCreditVerificationModal = false;
            this.creditVerificationText = '';
            this.creditVerificationError = null;
            this.showPaymentTypeModal = true;
        },
        closeBankTransferModal() {
            this.showBankTransferModal = false;
            this.bankTransferError = null;
            this.showPaymentTypeModal = true;
        },
        submitCreditSale() {
            if (!this.isCreditVerificationMatched) {
                this.creditVerificationError = 'Please type CREDIT to verify this credit sale.';
                return;
            }

            if (!this.form.due_date) {
                this.form.errors.due_date = 'Due date is required for credit sales.';
                return;
            }

            this.creditVerificationError = null;
            this.showCreditVerificationModal = false;
            this.submitOrderCreation();
        },
        closeChargeSuccessModal() {
            this.showChargeSuccessModal = false;
            this.pendingCashChange = 0;
            this.pendingCashPaid = 0;
            this.pendingCashReceived = 0;
            this.pendingReceiptId = null;
        },
        openPrintPromptAfterCharge() {
            this.showChargeSuccessModal = false;
            this.showPrintPrompt = true;
        },
        processCashPayment() {
            if (!this.pendingInvoice?.id) return;

            this.processingPayment = true;
            this.paymentPromptError = null;

            this.paymentForm.put(`/ar-invoices/${this.pendingInvoice.id}`, {
                preserveScroll: true,
                onSuccess: (response) => {
                    const receiptId = response?.props?.flash?.receipt_id || this.$page?.props?.flash?.receipt_id || null;
                    this.pendingReceiptId = receiptId;
                    this.playSuccessBeep();
                    this.showChargeSuccessModal = true;
                    this.pendingInvoice = null;
                    this.pendingSalesOrder = null;
                    this.paymentForm.reset();
                    this.$emit('add', true);
                },
                onError: (errors) => {
                    this.paymentPromptError = errors?.amount_paid || errors?.payment_date || 'Failed to process payment.';
                    this.showPaymentPrompt = true;
                },
                onFinish: () => {
                    this.processingPayment = false;
                }
            });
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
            this.pendingCashChange = 0;
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
        playSuccessBeep() {
            if (typeof window === 'undefined') return;

            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (!AudioContextClass) return;

            const audioContext = new AudioContextClass();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            const now = audioContext.currentTime;

            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(880, now);
            oscillator.frequency.exponentialRampToValueAtTime(1174.66, now + 0.12);

            gainNode.gain.setValueAtTime(0.0001, now);
            gainNode.gain.exponentialRampToValueAtTime(0.12, now + 0.02);
            gainNode.gain.exponentialRampToValueAtTime(0.0001, now + 0.22);

            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);

            oscillator.start(now);
            oscillator.stop(now + 0.24);

            oscillator.onended = () => {
                gainNode.disconnect();
                oscillator.disconnect();
                if (audioContext.state !== 'closed') {
                    audioContext.close().catch(() => {});
                }
            };
        },
        skipReceiptPrint() {
            this.showPrintPrompt = false;
            this.pendingReceiptId = null;
            this.pendingCashChange = 0;
            this.pendingCashPaid = 0;
            this.pendingCashReceived = 0;
        },
        handleInput(field) {
            this.form.errors[field] = false;
        },
        hide() {
            this.form.reset();
            this.form.clearErrors();
            this.editable = false;
            this.saveSuccess = false;
            this.showOrderReview = false;
            this.showPaymentTypeModal = false;
            this.showCashReceivedModal = false;
            this.showCreditVerificationModal = false;
            this.showBankTransferModal = false;
            this.showChargeSuccessModal = false;
            this.cashReceivedAmount = null;
            this.cashChargeError = null;
            this.creditVerificationText = '';
            this.creditVerificationError = null;
            this.resetBankTransferDetails();
            this.selectedReviewPaymentType = null;
            this.customerSelection = null;
            this.pendingCashPaid = 0;
            this.pendingCashReceived = 0;
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
        getSalesRepName(employeeId) {
            if (!employeeId) return '-';
            const rep = this.salesRepOptions.find(employee => Number(employee.value) === Number(employeeId));
            return rep ? rep.name : '-';
        },
        getDriverName(employeeId) {
            if (!employeeId) return '-';
            const driver = this.dropdowns?.drivers?.find(employee => Number(employee.value) === Number(employeeId));
            return driver ? driver.name : '-';
        },
        getLocationName(locationId) {
            if (!locationId) return '-';
            const location = this.dropdowns?.locations?.find(entry => Number(entry.value) === Number(locationId));
            return location ? location.name : '-';
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


        selectPaymentType(type) {
            if (type === 'Credit' || type === 'Credit Sales') {
                if (this.isWalkInCustomer) {
                    this.form.errors.payment_mode = 'Credit sales are not allowed for walk-in customers.';
                    return;
                }
                this.selectedReviewPaymentType = 'Credit Sales';
                this.form.payment_mode = 'Credit Sales';
                this.cashReceivedAmount = null;
                this.cashChargeError = null;
                this.handleInput('payment_mode');
                return;
            }

            this.selectedReviewPaymentType = 'Cash Sales';
            const currentMode = this.form.payment_mode;
            this.form.payment_mode = this.cash_payment_modes.includes(currentMode) ? currentMode : null;
            this.cashReceivedAmount = null;
            this.cashChargeError = null;
            this.handleInput('payment_mode');
        },

        selectPaymentMode(mode) {
            this.form.payment_mode = mode;
            if (mode !== 'Cash') {
                this.cashReceivedAmount = null;
                this.cashChargeError = null;
            }
            if (mode === 'Cash') {
                this.showPaymentTypeModal = false;
                this.showCashReceivedModal = true;
                this.cashChargeError = null;
            } else if (mode === 'Bank Transfer') {
                this.showPaymentTypeModal = false;
                this.showBankTransferModal = true;
                this.bankTransferError = null;
                if (!this.bankTransferDetails.amount_paid) {
                    this.bankTransferDetails.amount_paid = this.grandTotal;
                }
            } else {
                this.showCashReceivedModal = false;
                this.showBankTransferModal = false;
                this.bankTransferError = null;
            }
            this.handleInput('payment_mode');
        },

        getPaymentModeIcon(mode) {
            const icons = {
                'Cash': 'ri-money-dollar-circle-line',
                'Credit': 'ri-bank-card-line',
                'Cash Sales': 'ri-money-dollar-circle-line',
                'Credit Sales': 'ri-bank-card-line',
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

.order-review-modal .modal-container {
    max-width: 960px;
    width: 94%;
}

.order-review-modal .modal-header {
    border-radius: 20px 20px 0 0;
    background: #C4DAD2 !important;
    border-bottom: 1px solid #e9ecef;
}

.order-review-modal .modal-header h4 {
    color: #16423C !important;
    font-weight: 700;
}

.order-review-modal .close-btn {
    background: rgba(255, 255, 255, 0.25);
    color: #16423C !important;
}

.order-review-modal .close-btn:hover {
    background: rgba(255, 255, 255, 0.35);
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
    max-height: 75vh;
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

.payment-type-modal-body {
    background:
        radial-gradient(circle at top right, rgba(61, 141, 122, 0.12), transparent 28%),
        linear-gradient(180deg, #f8fcfb 0%, #f1f8f5 100%);
}

.payment-section-heading {
    margin: 0 0 0.75rem;
}

.payment-section-heading-sm {
    margin: 0 0 0.9rem;
}

.payment-section-kicker {
    display: inline-block;
    margin-bottom: 0.35rem;
    font-size: 0.75rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #648b74;
}

.payment-section-heading h5 {
    margin: 0;
    font-size: 1.02rem;
    font-weight: 700;
    color: #20413a;
}

.payment-section-heading p {
    margin: 0.2rem 0 0;
    font-size: 0.9rem;
    color: #6b7f78;
}

.review-overview-card {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1.25rem;
    padding: 1.35rem 1.4rem;
    border-radius: 24px;
    background:
        radial-gradient(circle at top right, rgba(61, 141, 122, 0.16), transparent 28%),
        linear-gradient(135deg, #f7fcfa 0%, #edf7f3 100%);
    border: 1px solid #dbe9e3;
    box-shadow: 0 14px 28px rgba(31, 92, 80, 0.08);
}

.review-overview-copy {
    max-width: 560px;
}

.review-overview-kicker,
.review-section-kicker {
    display: inline-block;
    margin-bottom: 0.35rem;
    font-size: 0.74rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #648b74;
}

.review-overview-copy h5 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 700;
    color: #20413a;
}

.review-overview-copy p {
    margin: 0.45rem 0 0;
    font-size: 0.92rem;
    line-height: 1.6;
    color: #6b7f78;
}

.review-overview-metrics {
    display: grid;
    grid-template-columns: repeat(2, minmax(135px, 1fr));
    gap: 0.8rem;
    width: min(100%, 320px);
}

.review-overview-metric {
    padding: 1rem 1.05rem;
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.82);
    border: 1px solid #dbe9e3;
}

.review-overview-metric span {
    display: block;
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: #6c877d;
}

.review-overview-metric strong {
    display: block;
    margin-top: 0.45rem;
    font-size: 1.18rem;
    font-weight: 700;
    color: #20413a;
    line-height: 1.35;
}

.review-overview-metric-total {
    background: linear-gradient(135deg, #2f7a68 0%, #235e50 100%);
    border-color: transparent;
}

.review-overview-metric-total span,
.review-overview-metric-total strong {
    color: #fff;
}

.review-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.6fr) minmax(280px, 0.95fr);
    gap: 1rem;
}

.review-section-card {
    padding: 1.2rem;
    border-radius: 22px;
    background: rgba(255, 255, 255, 0.86);
    border: 1px solid #dbe9e3;
    box-shadow: 0 12px 26px rgba(31, 92, 80, 0.06);
}

.review-section-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1rem;
}

.review-section-head h6 {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: #20413a;
}

.review-info-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.85rem;
}

.review-balance-banner {
    grid-column: 1 / -1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.95rem 1rem;
    border-radius: 18px;
    background: linear-gradient(135deg, #f4f7f6 0%, #edf3f1 100%);
    border: 1px solid #dbe5e1;
}

.review-balance-banner.has-balance {
    background: linear-gradient(135deg, #fff0e6 0%, #ffe0cc 100%);
    border-color: #f4b183;
    box-shadow: 0 12px 24px rgba(217, 119, 6, 0.14);
}

.review-balance-banner span {
    color: #7a695d;
    font-size: 0.8rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.review-balance-banner strong {
    color: #20413a;
    font-size: 1.2rem;
    font-weight: 800;
}

.review-balance-banner.has-balance strong {
    color: #b45309;
}

.review-info-item {
    padding: 0.9rem 0.95rem;
    border-radius: 16px;
    background: linear-gradient(180deg, #f8fcfb 0%, #f1f8f5 100%);
    border: 1px solid #e2eeea;
}

.review-info-item span {
    display: block;
    margin-bottom: 0.35rem;
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: #6c877d;
}

.review-info-item strong {
    display: block;
    font-size: 0.96rem;
    color: #20413a;
    line-height: 1.4;
}

.review-info-item small {
    display: block;
    margin-top: 0.35rem;
    font-size: 0.78rem;
    line-height: 1.45;
    color: #7a8d86;
}

.review-totals-card {
    background: linear-gradient(180deg, #f7fcfa 0%, #eef7f3 100%);
}

.review-total-list {
    display: grid;
    gap: 0.75rem;
}

.review-total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    padding: 0.9rem 1rem;
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.82);
    border: 1px solid #e2eeea;
}

.review-total-row span {
    color: #5f7a70;
    font-size: 0.9rem;
    font-weight: 600;
}

.review-total-row strong {
    color: #20413a;
    font-size: 0.98rem;
    font-weight: 700;
}

.review-total-row-grand {
    background: linear-gradient(135deg, #2f7a68 0%, #235e50 100%);
    border-color: transparent;
}

.review-total-row-grand span,
.review-total-row-grand strong {
    color: #fff;
}

.review-total-row-grand strong {
    font-size: 1.15rem;
}

.review-total-note {
    margin: 0.9rem 0 0;
    font-size: 0.83rem;
    line-height: 1.55;
    color: #6b7f78;
}

.review-section-head-items {
    align-items: center;
}

.review-items-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.5rem 0.8rem;
    border-radius: 999px;
    background: #eef7f4;
    color: #2f7666;
    font-size: 0.82rem;
    font-weight: 700;
}

.review-items-table-wrap {
    border: 1px solid #dceae4;
    border-radius: 18px;
    overflow: hidden;
    background: linear-gradient(180deg, #fbfefd 0%, #f5faf8 100%);
}

.review-items-table thead th {
    padding: 0.95rem 0.85rem;
    border: none;
    background: linear-gradient(180deg, #eff7f4 0%, #e6f2ed 100%);
    color: #49655d;
    font-size: 0.75rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.review-items-table tbody td {
    padding: 0.9rem 0.85rem;
    vertical-align: middle;
    border-color: #edf3f1;
}

.review-items-table tbody tr:last-child td {
    border-bottom: none;
}

.review-product-cell {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.review-product-cell strong {
    color: #20413a;
    font-size: 0.95rem;
}

.review-product-cell small {
    color: #74867f;
    font-size: 0.78rem;
    text-transform: capitalize;
}

.review-batch-pill,
.review-qty-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 44px;
    padding: 0.28rem 0.65rem;
    border-radius: 999px;
    font-weight: 700;
    font-size: 0.8rem;
}

.review-batch-pill {
    background: #eef7f4;
    color: #2f7666;
}

.review-qty-pill {
    background: #edf4ff;
    color: #315d9a;
}

.review-empty-state {
    padding: 2rem 1rem;
    text-align: center;
    color: #74867f;
}

.review-empty-state i {
    display: inline-grid;
    place-items: center;
    width: 58px;
    height: 58px;
    margin-bottom: 0.8rem;
    border-radius: 18px;
    background: #eef7f4;
    color: #2f7a68;
    font-size: 1.7rem;
}

.review-empty-state h6 {
    margin: 0;
    color: #355f55;
    font-size: 1rem;
    font-weight: 700;
}

.review-empty-state p {
    margin: 0.4rem 0 0;
    font-size: 0.9rem;
}

.review-helper-copy {
    color: #70827b;
    font-size: 0.9rem;
    line-height: 1.55;
}

.payment-type-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.9rem;
}

.payment-detail-card {
    margin-top: 1rem;
    padding: 1rem 1rem 1.05rem;
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.75);
    border: 1px solid #dbe9e3;
    box-shadow: 0 12px 25px rgba(61, 141, 122, 0.08);
}

.payment-subtype-section {
    margin-top: 1rem;
    padding-top: 0.9rem;
    border-top: 1px solid #edf3f1;
}

.payment-subtype-label {
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #648b74;
}

.payment-subtype-grid {
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
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

.payment-subtype-card {
    min-height: 72px;
}

.payment-choice-card {
    align-items: flex-start;
    text-align: left;
    padding: 1rem;
    min-height: 132px;
    border-radius: 18px;
    border-color: #dbe9e3;
    box-shadow: 0 10px 24px rgba(31, 92, 80, 0.06);
}

.payment-choice-icon-wrap {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    background: #eef7f4;
    display: grid;
    place-items: center;
    margin-bottom: 0.7rem;
    color: #2f7a68;
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

.payment-mode-card.selected-payment-mode .payment-choice-icon-wrap {
    background: rgba(255, 255, 255, 0.18);
    color: #fff;
}

.payment-icon {
    font-size: 1.5rem;
    margin-bottom: 0.25rem;
}

.payment-label {
    font-size: 0.875rem;
    font-weight: 700;
    text-align: left;
}

.payment-choice-copy {
    margin-top: 0.35rem;
    font-size: 0.78rem;
    line-height: 1.45;
    color: #6b7f78;
}

.payment-mode-card.selected-payment-mode .payment-choice-copy {
    color: rgba(255, 255, 255, 0.82);
}

.payment-choice-disabled {
    opacity: 0.55;
    cursor: not-allowed;
    filter: grayscale(0.15);
}

.payment-choice-disabled:hover {
    transform: none;
    border-color: #dbe9e3;
    box-shadow: 0 10px 24px rgba(31, 92, 80, 0.06);
}

.payment-success-card {
    padding: 1.25rem 1rem;
    text-align: center;
}

.payment-success-icon {
    width: 72px;
    height: 72px;
    margin: 0 auto 1rem;
    border-radius: 22px;
    display: grid;
    place-items: center;
    background: linear-gradient(135deg, #d8f2e3 0%, #e8f8ef 100%);
    color: #1f7a4d;
    font-size: 2rem;
}

.payment-success-card h5 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 700;
    color: #20413a;
}

.payment-success-card p {
    margin: 0.45rem 0 0;
    color: #6b7f78;
    font-size: 0.92rem;
}

.payment-success-breakdown {
    display: grid;
    gap: 0.75rem;
    margin-top: 1rem;
    text-align: left;
}

.payment-success-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.9rem 1rem;
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.82);
    border: 1px solid #dbe9e3;
}

.payment-success-row span {
    color: #5f7a70;
    font-size: 0.86rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.payment-success-row strong {
    color: #20413a;
    font-size: 1rem;
    font-weight: 700;
}

.payment-success-row-highlight {
    background: linear-gradient(135deg, #e6f7ee 0%, #d8f2e3 100%);
    border-color: #b9e6ca;
}

.credit-verification-summary {
    display: grid;
    gap: 0.75rem;
    margin-top: 0.35rem;
}

.credit-balance-callout {
    padding: 1rem 1.05rem;
    border-radius: 18px;
    background: linear-gradient(135deg, #f4f7f6 0%, #edf3f1 100%);
    border: 1px solid #dbe5e1;
}

.credit-balance-callout.has-balance {
    background: linear-gradient(135deg, #fff0e6 0%, #ffd9bf 100%);
    border-color: #f4b183;
    box-shadow: 0 14px 28px rgba(217, 119, 6, 0.18);
}

.credit-balance-callout-label {
    display: block;
    color: #7a695d;
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.credit-balance-callout-value {
    display: block;
    margin-top: 0.35rem;
    color: #20413a;
    font-size: 1.4rem;
    font-weight: 800;
}

.credit-balance-callout.has-balance .credit-balance-callout-value {
    color: #b45309;
}

.cash-change-note,
.cash-change-display {
    margin-top: 0.85rem;
    padding: 0.85rem 1rem;
    border-radius: 14px;
    background: linear-gradient(135deg, #e6f7ee 0%, #d8f2e3 100%);
    border: 1px solid #b9e6ca;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    color: #155724;
}

.cash-change-note span,
.cash-change-display span {
    font-size: 0.85rem;
    font-weight: 600;
}

.cash-change-note strong,
.cash-change-display strong {
    font-size: 1rem;
    font-weight: 700;
}

.cash-change-display strong {
    font-size: 1.5rem;
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

.customer-balance-alert {
    padding: 0.95rem 1rem;
    border-radius: 18px;
    background: linear-gradient(135deg, #f4f7f6 0%, #edf3f1 100%);
    border: 1px solid #dbe5e1;
}

.customer-balance-alert.has-balance {
    background: linear-gradient(135deg, #fff0e6 0%, #ffe0cc 100%);
    border-color: #f4b183;
    box-shadow: 0 12px 24px rgba(217, 119, 6, 0.14);
}

.customer-balance-label {
    display: block;
    color: #7a695d;
    font-size: 0.76rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.customer-balance-value {
    display: block;
    margin-top: 0.35rem;
    color: #20413a;
    font-size: 1.25rem;
    font-weight: 800;
}

.customer-balance-alert.has-balance .customer-balance-value {
    color: #b45309;
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

.payment-modal-btn {
    padding: 0.65rem 0.95rem;
    font-size: 0.8rem;
    border-radius: 12px;
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

    .review-overview-card,
    .review-grid {
        grid-template-columns: 1fr;
    }

    .review-overview-card {
        flex-direction: column;
    }

    .review-overview-metrics {
        width: 100%;
    }
}

@media (max-width: 768px) {
    .modal-body {
        padding: 1.25rem;
    }

    .payment-type-grid {
        grid-template-columns: 1fr;
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

    .review-info-grid,
    .review-overview-metrics {
        grid-template-columns: 1fr;
    }

    .review-section-head {
        flex-direction: column;
        align-items: flex-start;
    }

    .review-total-row {
        padding: 0.85rem 0.9rem;
    }
}
</style>
