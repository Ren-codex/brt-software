<template>
    <div
        v-show="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"

    >
        <div class="modal-container modal-xl" @click.stop>
            <div class="modal-header">
                <div class="modal-header-title-group">
                    <div class="modal-header-icon">
                        <i class="ri-add-box-line"></i>
                    </div>
                    <div>
                        <h2>{{ editable ? 'Update Item' : 'Add Item' }}</h2>
                        <p class="modal-header-subtitle">{{ editable ? 'Update this item on the sales order' : 'Add a new item to the sales order' }}</p>
                    </div>
                </div>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body">
                <form @submit.prevent="submit">
                  <div class="form-row">
                        <div class="form-group">
                            <label for="product_id" class="form-label">Product <span class="required-asterisk">*</span></label>
                            <div class="input-wrapper">
                                <i class="ri-box-3-line input-icon batch-select-icon"></i>
                                <Multiselect
                                    id="product_id"
                                    ref="productSelect"
                                    v-model="form.product_id"
                                    :options="productSelectOptions"
                                    label="name"
                                    value-prop="value"
                                    track-by="name"
                                    :searchable="true"
                                    :can-clear="false"
                                    placeholder="Select Product"
                                    class="form-control batch-multiselect"
                                    :class="{ 'input-error': form.errors.product_id }"
                                    @change="onProductChange"
                                />
                            </div>
                            <span class="error-message" v-if="form.errors.product_id">{{ form.errors.product_id }}</span>
                        </div>
                    </div>

                    <hr class="section-divider" />

                    <div class="quantity-pricing-grid">
                        <div class="quantity-pricing-col">
                            <h3 class="section-title">Quantity</h3>

                            <div class="stat-row">
                                <div class="stat-box">
                                    <span class="stat-label">Available (Selected Batch)</span>
                                    <div class="stat-value">{{ selectedProductStock ?? 0 }}</div>
                                </div>
                                <div class="stat-box">
                                    <span class="stat-label">Total Available Onward</span>
                                    <div class="stat-value stat-value-accent">{{ totalAllocatableStock }}</div>
                                </div>
                                <div class="stat-box stat-box-input">
                                    <label for="quantity" class="stat-label">Quantity to Add <span class="required-asterisk">*</span></label>
                                    <div class="quantity-stepper">
                                        <button
                                            type="button"
                                            class="quantity-stepper-btn"
                                            :disabled="(parseFloat(form.quantity) || 0) <= 0"
                                            @click="stepQuantity(-1)"
                                        >
                                            <i class="ri-subtract-line"></i>
                                        </button>
                                        <input
                                            type="number"
                                            id="quantity"
                                            ref="quantityInput"
                                            class="stat-input-field"
                                            v-model.number="form.quantity"
                                            :class="{ 'input-error': form.errors.quantity }"
                                            :max="editable ? selectedProductStock : totalAllocatableStock"
                                            @input="validateQuantity"
                                        />
                                        <button
                                            type="button"
                                            class="quantity-stepper-btn"
                                            :disabled="(parseFloat(form.quantity) || 0) >= (editable ? selectedProductStock : totalAllocatableStock)"
                                            @click="stepQuantity(1)"
                                        >
                                            <i class="ri-add-line"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <span class="error-message" v-if="form.errors.quantity">{{ form.errors.quantity }}</span>

                            <div v-if="selectedProductBatches.length > 0" class="batch-stock-panel">
                                <p class="batch-stock-panel-title">All Batch Stocks</p>
                                <div
                                    v-for="batch in selectedProductBatches"
                                    :key="batch.batch_code"
                                    class="batch-radio-row"
                                    :class="{ selected: batch.batch_code === form.batch_code }"
                                    @click="selectBatch(batch.batch_code)"
                                >
                                    <span class="batch-radio-dot" :class="{ selected: batch.batch_code === form.batch_code }"></span>
                                    <span class="batch-radio-code">{{ batch.batch_code }}</span>
                                    <span v-if="batch.received_date" class="batch-radio-date">Received {{ formatDate(batch.received_date) }}</span>
                                    <span class="batch-radio-qty">{{ batch.quantity }}</span>
                                    <span v-if="batch.batch_code === recommendedBatchCode" class="batch-radio-oldest-tag">(oldest)</span>
                                </div>
                                <p class="batch-note">
                                    <i class="ri-information-line"></i>
                                    Stocks are displayed from oldest to newest.
                                </p>
                                <small v-if="isBatchOverride" class="batch-override-warning">
                                    <i class="ri-alert-line"></i>
                                    Not the oldest available batch — this order will require approval.
                                </small>
                            </div>

                            <small
                                v-if="shouldSplitIntoMultipleItems"
                                class="text-muted d-block split-note"
                            >
                                <i class="ri-git-merge-line"></i>
                                This quantity will be split into multiple items by batch.
                            </small>
                        </div>

                        <div class="quantity-pricing-col">
                            <h3 class="section-title">Pricing</h3>

                            <div class="checkbox-wrapper price-type-toggle">
                                <label class="switch">
                                    <input type="checkbox" v-model="isWholesale" @change="onPriceTypeChange">
                                    <span class="slider"></span>
                                </label>
                                <span class="checkbox-label">{{ isWholesale ? 'Wholesale Price' : 'Retail Price' }}</span>
                            </div>

                            <div class="price-label-row">
                                <label for="price" class="form-label mb-0">Unit Price</label>
                                <button
                                    v-if="!shouldSplitIntoMultipleItems"
                                    type="button"
                                    class="price-adjust-btn"
                                    :disabled="!selectedInventoryStock"
                                    @click="openPriceAdjustment(selectedInventoryStock)"
                                >
                                    <i class="ri-edit-line"></i>
                                    <span>Adjust Price</span>
                                </button>
                            </div>
                            <div v-if="!shouldSplitIntoMultipleItems" class="input-wrapper">
                                <i class="ri-cash-line input-icon"></i>
                                <input
                                    type="text"
                                    id="price"
                                    :value="formatCurrency(form.price)"
                                    class="form-control"
                                    :class="{ 'input-error': form.errors.price }"
                                    readonly
                                />
                            </div>
                            <div v-else class="split-preview-list">
                                <div
                                    v-for="allocation in batchAllocationsPreview"
                                    :key="`${allocation.batch_code}-price`"
                                    class="split-preview-item"
                                >
                                    <div class="split-preview-header">
                                        <span>{{ allocation.batch_code }}</span>
                                        <div class="split-preview-actions">
                                            <strong>Qty: {{ allocation.quantity }}</strong>
                                            <button
                                                type="button"
                                                class="price-adjust-btn price-adjust-btn-inline"
                                                :disabled="!allocation.inventoryStock"
                                                @click="openPriceAdjustment(allocation.inventoryStock)"
                                            >
                                                <i class="ri-edit-line"></i>
                                                <span>Adjust Price</span>
                                            </button>
                                        </div>
                                    </div>
                                    <input
                                        type="text"
                                        :value="formatCurrency(allocation.price)"
                                        class="form-control"
                                        readonly
                                    />
                                </div>
                            </div>
                            <span class="error-message" v-if="form.errors.price">{{ form.errors.price }}</span>

                            <div class="form-group discount-group">
                                <label for="discount_per_unit" class="form-label">Discount per Unit (Amount)</label>
                                <div class="input-wrapper">
                                    <i class="ri-cash-line input-icon"></i>
                                    <Amount
                                        @amount="updateDiscountPerUnit($event)"
                                        :class="{ 'input-error': form.errors.discount_per_unit }"
                                        class="form-control"
                                        ref="discountComponent"
                                    />
                                </div>
                                <span class="error-message" v-if="form.errors.discount_per_unit">{{ form.errors.discount_per_unit }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="!shouldSplitIntoMultipleItems" class="net-price-panel">
                        <div class="net-price-icon">
                            <i class="ri-price-tag-3-line"></i>
                        </div>
                        <div class="net-price-text">
                            <div class="net-price-label">Net Unit Price</div>
                            <div class="net-price-sub">(Unit Price – Discount)</div>
                        </div>
                        <div class="net-price-value">{{ formatCurrency(netUnitPrice) }}</div>

                        <div class="net-price-divider"></div>

                        <div class="net-price-text">
                            <div class="net-price-label">Total Amount</div>
                            <div class="net-price-sub">Net Unit Price × Qty ({{ form.quantity || 0 }})</div>
                        </div>
                        <div class="net-price-value net-price-value-total">{{ formatCurrency(lineTotal) }}</div>
                    </div>

                    <div class="success-alert" v-if="saveSuccess">
                        <i class="ri-checkbox-circle-fill"></i>
                        <span>Item has been saved successfully!</span>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-cancel" @click="hide">
                            <i class="ri-close-line"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-save" :disabled="form.processing || ( form.quantity == 0 || parseFloat(form.price) === 0 || !form.product_id || !form.price_type  || !form.batch_code)">
                            <i class="ri-save-line" v-if="!form.processing"></i>
                            <i class="ri-loader-4-line spinner" v-else></i>
                            {{ form.processing ? 'Saving...' : 'Add Item' }}
                        </button>
                    </div>


                </form>
            </div>
        </div>
    </div>
    <UpdatePriceModal
        :inventoryStock="priceAdjustmentStock"
        @saved="handlePriceAdjustmentSaved"
        ref="updatePriceModal"
    />
</template>

<script>
import { useForm } from '@inertiajs/vue3';
import InputLabel from '@/Shared/Components/Forms/InputLabel.vue';
import Multiselect from '@vueform/multiselect';
import Amount from '@/Shared/Components/Forms/Amount.vue';
import UpdatePriceModal from '@/Pages/Modules/Inventory/Modal/UpdatePriceModal.vue';

export default {
    components: { InputLabel, Multiselect, Amount, UpdatePriceModal },
    props: {
        dropdowns: { type: Object, required: true },
        items: { type: Array, default: () => [] },
        update: { type: Boolean, default: false },
    },
    data() {
        return {
            currentUrl: window.location.origin,
            form: useForm({
                id: null,
                brand_id: null,
                quantity: 0,
                price: isNaN(0) ? 0 : 0.00,
                product_id: null,
                batch_code: null,
                price_type: 'retail',
                discount_per_unit: 0,
            }),

            togglePassword: false,
            toggleConfirm: false,
            passwordMismatch: false,
            showModal: false,
            editable: false,
            saveSuccess: false,
            priceAdjustmentStock: null,
        }
    },
    computed: {
        isWholesale: {
            get() {
                return this.form.price_type === 'wholesale';
            },
            set(value) {
                this.form.price_type = value ? 'wholesale' : 'retail';
            }
        },
        reservedStocks() {
            const reserved = {};
            this.items.forEach((item) => {
                if (!item?.product_id || !item?.batch_code) return;
                if (this.editable && this.form.id && item.id === this.form.id) return;
                const key = `${item.product_id}::${item.batch_code}`;
                reserved[key] = (reserved[key] || 0) + (parseFloat(item.quantity) || 0);
            });
            return reserved;
        },
        selectedProductStock() {
            if (!this.form.product_id) return null;
            if (!this.form.batch_code) return null;
            const batch = this.selectedProductBatches.find((b) => b.batch_code === this.form.batch_code);
            return batch ? batch.quantity : 0;
        },
        selectedBatchDetails() {
            if (!this.form.batch_code) return null;
            return this.selectedProductBatches.find((batch) => batch.batch_code === this.form.batch_code) || null;
        },
        selectedInventoryStock() {
            if (!this.form.product_id || !this.form.batch_code) return null;
            const product = this.dropdowns.products.find(p => p.value === this.form.product_id);
            return product?.batch_stocks?.find((batch) => batch.batch_code === this.form.batch_code) || null;
        },
        selectedProductBatches() {
            if (!this.form.product_id) return [];
            const product = this.dropdowns.products.find(p => p.value === this.form.product_id);
            if (!product?.batch_stocks) return [];

            return product.batch_stocks
                .map((batch) => {
                    const key = `${this.form.product_id}::${batch.batch_code}`;
                    const reserved = this.reservedStocks[key] || 0;
                    return {
                        id: batch.id,
                        batch_code: batch.batch_code,
                        quantity: Math.max((parseFloat(batch.quantity) || 0) - reserved, 0),
                        unit_cost: parseFloat(batch.unit_cost) || 0,
                        retail_price: parseFloat(batch.retail_price) || 0,
                        wholesale_price: parseFloat(batch.wholesale_price) || 0,
                        received_date: batch.received_date || null,
                    };
                })
                .filter((batch) => batch.quantity > 0);
        },
        allocatableBatches() {
            if (!this.form.batch_code) return this.selectedProductBatches;
            const startIndex = this.selectedProductBatches.findIndex((batch) => batch.batch_code === this.form.batch_code);
            if (startIndex === -1) return [];
            return this.selectedProductBatches.slice(startIndex);
        },
        totalAllocatableStock() {
            return this.allocatableBatches.reduce((sum, batch) => sum + (parseFloat(batch.quantity) || 0), 0);
        },
        shouldSplitIntoMultipleItems() {
            const quantity = parseFloat(this.form.quantity) || 0;
            return !this.editable && quantity > 0 && quantity > (parseFloat(this.selectedProductStock) || 0);
        },
        batchAllocationsPreview() {
            let remainingQuantity = parseFloat(this.form.quantity) || 0;
            const allocations = [];

            this.allocatableBatches.forEach((batch) => {
                if (remainingQuantity <= 0) return;

                const batchQuantity = Math.min(remainingQuantity, parseFloat(batch.quantity) || 0);
                if (batchQuantity <= 0) return;

                allocations.push({
                    batch_code: batch.batch_code,
                    quantity: batchQuantity,
                    price: Number(this.getBatchPrice(batch) || 0),
                    inventoryStock: this.getInventoryStockByBatchCode(batch.batch_code),
                });

                remainingQuantity -= batchQuantity;
            });

            return allocations;
        },
        recommendedBatchCode() {
            return this.selectedProductBatches[0]?.batch_code || null;
        },
        isBatchOverride() {
            return !!(this.form.batch_code && this.recommendedBatchCode && this.form.batch_code !== this.recommendedBatchCode);
        },
        availableProducts() {
            return this.dropdowns.products.filter((product) => {
                if (!product?.value) return false;
                const totalAvailable = (product.batch_stocks || []).reduce((sum, batch) => {
                    const key = `${product.value}::${batch.batch_code}`;
                    const reserved = this.reservedStocks[key] || 0;
                    const remaining = Math.max((parseFloat(batch.quantity) || 0) - reserved, 0);
                    return sum + remaining;
                }, 0);
                return totalAvailable > 0;
            });
        },
        productSelectOptions() {
            return this.availableProducts.map((product) => ({
                value: product.value,
                name: product.name,
            }));
        },
        netUnitPrice() {
            const price = parseFloat(this.form.price) || 0;
            const discount = parseFloat(this.form.discount_per_unit) || 0;
            return Math.max(price - discount, 0);
        },
        lineTotal() {
            const quantity = parseFloat(this.form.quantity) || 0;
            return this.netUnitPrice * quantity;
        }
    },


    methods: {
        show() {
            this.form.reset();
            this.form.quantity = 0;
            this.form.price = '0.00';
            this.form.discount_per_unit = 0;
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = true;
            this.$nextTick(() => {
                this.$refs.discountComponent.emitValue(0.00);
                this.$refs.productSelect?.blur();
            });
        },
        edit(data, index) {
            this.form.id = data.id;
            this.form.brand_id = data.brand_id;
            this.form.quantity = data.quantity;
            this.form.product_id = data.product_id;
            this.form.price_type = data.price_type || 'retail';
            this.editable = true;
            this.onProductChange();
            this.form.batch_code = data.batch_code || this.form.batch_code;
            this.form.price = parseFloat(this.getBatchPrice(this.selectedBatchDetails) || data.price || 0).toFixed(2);
            this.form.discount_per_unit = data.discount_per_unit || 0;
            this.form.total_amount = data.total_amount;
            this.saveSuccess = false;
            this.showModal = true;
            this.validateQuantity(); // Validate quantity against current stock
            this.$nextTick(() => {
                this.$refs.productSelect?.blur();
            });
        },

        submit() {
            // Re-validate discount before submitting
            this.validateQuantity();
            this.validateDiscount();

            if (this.form.errors.quantity || this.form.errors.discount_per_unit) {
                return;
            }

            const itemData = {
                id: this.form.id,
                brand_id: this.form.brand_id,
                quantity: this.form.quantity,
                price: Number(this.form.price || 0).toFixed(2), // 2 decimals
                product_id: this.form.product_id,
                batch_code: this.form.batch_code,
                price_type: this.form.price_type,
                discount_per_unit: this.form.discount_per_unit,
                total_amount: this.form.amount || 0,
                is_batch_override: this.isBatchOverride,
            };

            if (this.editable) {
                this.$emit('update', itemData );
            } else {
                const splitItems = this.allocateItemQuantities(itemData);
                this.$emit('items', splitItems);
            }

            this.showModal = false;
        },

        handleInput(field) {
            this.form.errors[field] = false;
        },
        hide() {
            this.form.price = null;
            this.form.reset();
            this.form.clearErrors();
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = false;
        },



        updateDiscountPerUnit(value) {
            if (typeof value === 'string') {
                const cleanValue = value.replace(/[₱,]/g, '');
                const num = parseFloat(cleanValue);
                this.form.discount_per_unit = isNaN(num) ? 0 : num;
            } else {
                this.form.discount_per_unit = value || 0;
            }
            this.validateDiscount();
        },

        onProductChange(productId) {
            // The Multiselect's @change fires before it updates v-model, so form.product_id
            // may still hold the previous selection here — use the emitted value when given.
            if (productId !== undefined) {
                this.form.product_id = productId;
            }
            const product_id = this.form.product_id;
            const product = this.dropdowns.products.find(p => p.value === product_id);
            if (product) {
                const availableBatch = this.selectedProductBatches[0];
                this.form.batch_code = availableBatch ? availableBatch.batch_code : null;
                const price = this.getBatchPrice(availableBatch);
                this.form.price = parseFloat(price || 0).toFixed(2);
                this.focusQuantity();
            } else {
                this.form.batch_code = null;
                this.form.price = '0.00';
            }
        },

        focusQuantity() {
            this.$nextTick(() => {
                this.$refs.quantityInput?.focus();
                this.$refs.quantityInput?.select();
            });
        },

        selectBatch(batchCode) {
            if (this.form.batch_code === batchCode) return;
            this.form.batch_code = batchCode;
            this.onBatchCodeChange();
        },

        onBatchCodeChange() {
            const batch = this.selectedProductBatches.find((b) => b.batch_code === this.form.batch_code);
            const price = this.getBatchPrice(batch);
            this.form.price = parseFloat(price || 0).toFixed(2);
            this.validateQuantity();
        },

        onPriceTypeChange() {
            if (this.form.product_id && this.selectedBatchDetails) {
                this.form.price = parseFloat(this.getBatchPrice(this.selectedBatchDetails) || 0).toFixed(2);
            } else {
                this.form.price = '0.00';
            }
        },

        getInventoryStockByBatchCode(batchCode) {
            if (!this.form.product_id || !batchCode) return null;
            const product = this.dropdowns.products.find(p => p.value === this.form.product_id);
            return product?.batch_stocks?.find((batch) => batch.batch_code === batchCode) || null;
        },

        openPriceAdjustment(stock = null) {
            if (!stock) return;
            this.priceAdjustmentStock = stock;
            // UpdatePriceModal reads its `inventoryStock` prop inside show(), which needs a
            // render tick to receive the value just assigned above.
            this.$nextTick(() => {
                this.$refs.updatePriceModal.show();
            });
        },

        handlePriceAdjustmentSaved(payload = {}) {
            if (!payload?.inventory_stocks_id) return;

            this.dropdowns.products.forEach((product) => {
                (product.batch_stocks || []).forEach((batch) => {
                    if (batch.id !== payload.inventory_stocks_id) return;
                    batch.retail_price = payload.retail_price;
                    batch.wholesale_price = payload.wholesale_price;
                    if (Object.prototype.hasOwnProperty.call(batch, 'reason')) {
                        batch.reason = payload.reason;
                    }
                });
            });

            this.onPriceTypeChange();
            this.priceAdjustmentStock = null;
        },

        validateQuantity() {
            this.handleInput('quantity');
            const quantity = parseFloat(this.form.quantity) || 0;

            if (quantity <= 0) {
                this.form.errors.quantity = null;
            } else if (this.editable && this.selectedProductStock !== null && quantity > this.selectedProductStock) {
                this.form.errors.quantity = `Quantity cannot exceed available stock for batch ${this.form.batch_code} (${this.selectedProductStock})`;
            } else if (!this.editable && this.totalAllocatableStock > 0 && quantity > this.totalAllocatableStock) {
                this.form.errors.quantity = `Quantity cannot exceed total available stock from batch ${this.form.batch_code} onward (${this.totalAllocatableStock})`;
            } else {
                this.form.errors.quantity = null;
            }
            this.validateDiscount();
        },

        stepQuantity(delta) {
            const current = parseFloat(this.form.quantity) || 0;
            const max = parseFloat(this.editable ? this.selectedProductStock : this.totalAllocatableStock) || 0;
            const next = Math.min(Math.max(current + delta, 0), max);
            this.form.quantity = next;
            this.validateQuantity();
        },

        allocateItemQuantities(baseItem) {
            return this.batchAllocationsPreview.map((allocation, index) => ({
                    ...baseItem,
                    id: Date.now() + index,
                    quantity: allocation.quantity,
                    batch_code: allocation.batch_code,
                    price: Number(allocation.price || 0).toFixed(2),
                }));
        },

        getBatchPrice(batch) {
            if (!batch) return 0;
            return this.form.price_type === 'wholesale' ? batch.wholesale_price : batch.retail_price;
        },

        validateDiscount() {
            this.handleInput('discount_per_unit');
            const totalCost = parseFloat(this.form.price).toFixed(2) * parseFloat(this.form.quantity);
            const totalDiscount = parseFloat(this.form.discount_per_unit) * parseFloat(this.form.quantity);

            if (totalDiscount > totalCost) {
                this.form.errors.discount_per_unit = 'Total discount cannot exceed total cost';
            } else {
                this.form.errors.discount_per_unit = null;
            }
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

        formatDate(value) {
            if (!value) return '';
            const date = new Date(`${value}T00:00:00`);
            if (isNaN(date.getTime())) return '';
            return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        }


    }
}
</script>

<style scoped>
.modal-header-title-group {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.modal-header-subtitle {
    margin: 0;
    font-size: 0.72rem;
    color: #6b8c85;
}

.required-asterisk {
    color: #e74c3c;
}

.form-row > .form-group {
    flex: 1;
}

.batch-select-icon {
    z-index: 2;
}

.batch-multiselect {
    padding-left: 2.3rem !important;
}

.batch-override-warning {
    display: block;
    margin-top: 0.35rem;
    color: #b45309;
    font-size: 0.75rem;
    font-weight: 600;
}

.section-divider {
    border: none;
    border-top: 1px solid #e9ecef;
    margin: 1rem 0;
}

.quantity-pricing-grid {
    display: flex;
    gap: 1.25rem;
    margin-bottom: 0.85rem;
}

.quantity-pricing-col {
    flex: 1;
    min-width: 0;
}

.section-title {
    font-size: 0.9rem;
    font-weight: 700;
    color: #3d8d7a;
    margin: 0 0 0.65rem;
}

.stat-row {
    display: flex;
    gap: 0.6rem;
    margin-bottom: 0.5rem;
}

.stat-box {
    flex: 1;
    min-width: 0;
    background-color: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 9px;
    padding: 0.55rem 0.7rem;
}

.stat-label {
    display: block;
    font-size: 0.68rem;
    color: #7f8c8d;
    margin-bottom: 0.25rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.stat-value {
    font-size: 1.2rem;
    font-weight: 700;
    color: #16322e;
}

.stat-value-accent {
    color: #3d8d7a;
}

.stat-box-input {
    background-color: #fff;
    border: 1.5px solid #3d8d7a;
    box-shadow: 0 1px 3px rgba(61, 141, 122, 0.12);
}

.stat-box-input .stat-label {
    color: #267a4c;
    font-weight: 600;
}

.quantity-stepper {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.stat-box-input .stat-input-field {
    width: 100%;
    min-width: 0;
    border: none;
    background: transparent;
    padding: 0;
    font-size: 1.2rem;
    font-weight: 700;
    color: #16322e;
    text-align: center;
    -moz-appearance: textfield;
}

.stat-box-input .stat-input-field::-webkit-outer-spin-button,
.stat-box-input .stat-input-field::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.stat-box-input .stat-input-field:focus {
    outline: none;
}

.stat-box-input:focus-within {
    border-color: #267a4c;
    box-shadow: 0 0 0 3px rgba(61, 141, 122, 0.18);
}

.quantity-stepper-btn {
    flex-shrink: 0;
    width: 22px;
    height: 22px;
    border-radius: 6px;
    border: 1px solid #c4d9d2;
    background: #eef7f3;
    color: #267a4c;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    padding: 0;
    transition: all 0.15s ease;
}

.quantity-stepper-btn:hover:not(:disabled) {
    background: #3d8d7a;
    color: #fff;
}

.quantity-stepper-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.stat-box-input.input-error,
.stat-input-field.input-error {
    border-color: #e74c3c;
}

.batch-stock-panel {
    margin-top: 0.6rem;
    border: 1px solid #e9ecef;
    border-radius: 9px;
    padding: 0.6rem 0.7rem;
    background: #fff;
}

.batch-stock-panel-title {
    margin: 0 0 0.4rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: #495057;
}

.batch-radio-row {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.4rem 0.45rem;
    border-radius: 7px;
    cursor: pointer;
    transition: background-color 0.15s ease;
}

.batch-radio-row:hover {
    background-color: #f5f9f7;
}

.batch-radio-row.selected {
    background-color: #eef7f3;
}

.batch-radio-dot {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 2px solid #3d8d7a;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.batch-radio-dot.selected::after {
    content: '';
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #3d8d7a;
}

.batch-radio-code {
    font-weight: 600;
    font-size: 0.8rem;
    color: #16322e;
}

.batch-radio-date {
    font-size: 0.7rem;
    color: #94a3b8;
    flex: 1;
}

.batch-radio-qty {
    font-weight: 700;
    font-size: 0.8rem;
    color: #16322e;
    margin-left: auto;
}

.batch-radio-oldest-tag {
    font-size: 0.7rem;
    color: #7f8c8d;
}

.batch-note {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin: 0.5rem 0 0;
    font-size: 0.7rem;
    color: #94a3b8;
}

.split-note {
    margin-top: 0.5rem;
}

.split-preview-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.split-preview-item {
    padding: 0.5rem;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    background: #f8f9fa;
}

.discount-group {
    margin-top: 0.85rem;
    margin-bottom: 0;
}

.price-type-toggle {
    margin-bottom: 0.85rem;
}

.price-label-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
}

.price-adjust-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border: 1px solid #3d8d7a;
    background: #eef7f3;
    color: #267a4c;
    border-radius: 8px;
    padding: 0.45rem 0.75rem;
    font-size: 0.8rem;
    font-weight: 600;
    transition: all 0.2s ease;
}

.price-adjust-btn-inline {
    padding: 0.35rem 0.6rem;
    font-size: 0.75rem;
}

.price-adjust-btn:hover:not(:disabled) {
    background: #3d8d7a;
    color: #ffffff;
}

.price-adjust-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.split-preview-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.35rem;
    font-size: 0.8rem;
    color: #495057;
}

.split-preview-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.net-price-panel {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 9px;
    padding: 0.65rem 0.85rem;
    margin-bottom: 0.85rem;
}

.net-price-icon {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    background: rgba(61, 141, 122, 0.12);
    color: #3d8d7a;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}

.net-price-text {
    flex: 1;
    min-width: 0;
}

.net-price-label {
    font-weight: 700;
    font-size: 0.85rem;
    color: #16322e;
}

.net-price-sub {
    font-size: 0.7rem;
    color: #7f8c8d;
}

.net-price-value {
    font-size: 1.3rem;
    font-weight: 700;
    color: #267a4c;
    white-space: nowrap;
}

.net-price-value-total {
    font-size: 1.5rem;
    color: #16322e;
}

.net-price-divider {
    width: 1px;
    align-self: stretch;
    background: #e9ecef;
    flex-shrink: 0;
}

@media (max-width: 768px) {
    .net-price-panel {
        flex-wrap: wrap;
    }

    .net-price-divider {
        display: none;
    }

    .quantity-pricing-grid {
        flex-direction: column;
        gap: 1rem;
    }

    .stat-row {
        flex-direction: column;
    }
}
</style>
