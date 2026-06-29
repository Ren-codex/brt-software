<template>
    <div v-if="showModal" class="modal-overlay active" @click.self="hide">
        <div class="modal-container modal-xl" @click.stop>
            <div class="modal-header">
                <div class="modal-title-wrap">
                    <div class="modal-title-icon">
                        <i class="ri-arrow-go-back-line"></i>
                    </div>
                    <div>
                        <span class="modal-kicker">Returns Workflow</span>
                        <h2>Create Sales Return From Receipt</h2>
                    </div>
                </div>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>

            <div class="modal-body">
                <form class="return-form" @submit.prevent="submit">

                    <!-- Step 1: Find the receipt -->
                    <div class="rfw-step">
                        <div class="rfw-step-rail">
                            <div class="rfw-dot" :class="selectedReceiptId ? 'done' : 'active'">
                                <i class="ri-check-line" v-if="selectedReceiptId"></i>
                                <span v-else>1</span>
                            </div>
                            <div class="rfw-line"></div>
                        </div>
                        <div class="rfw-content">
                            <p class="rfw-title">Find the receipt</p>
                            <div class="search-compound">
                                <i class="ri-search-line search-lead-icon"></i>
                                <input
                                    v-model="receiptKeyword"
                                    type="text"
                                    class="search-compound-input"
                                    placeholder="Search by receipt number, customer, or sales order"
                                >
                                <button type="button" class="search-refresh" @click="fetchReceipts" :disabled="loadingReceipts">
                                    <i :class="loadingReceipts ? 'ri-loader-4-line spinner' : 'ri-refresh-line'"></i>
                                    Refresh
                                </button>
                            </div>
                            <div class="select-wrapper">
                                <select v-model="selectedReceiptId" class="form-control receipt-select">
                                    <option :value="null" disabled>Choose a receipt...</option>
                                    <option v-for="receipt in filteredReceipts" :key="receipt.id" :value="receipt.id">
                                        {{ receipt.receipt_number }} — {{ receipt.customer?.name || 'No Customer' }}
                                    </option>
                                </select>
                                <i class="ri-arrow-down-s-line select-chevron"></i>
                            </div>
                            <small v-if="!loadingReceipts && filteredReceipts.length === 0" class="help-text">
                                No receipts available for sales return.
                            </small>
                            <div v-if="selectedReceipt" class="receipt-meta">
                                <div class="meta-chip">
                                    <span class="meta-chip-label">Receipt</span>
                                    <strong>{{ selectedReceipt.receipt_number }}</strong>
                                </div>
                                <div class="meta-chip">
                                    <span class="meta-chip-label">Customer</span>
                                    <strong>{{ selectedReceipt.customer?.name || '—' }}</strong>
                                </div>
                                <div class="meta-chip">
                                    <span class="meta-chip-label">Sales Order</span>
                                    <strong>{{ selectedReceipt.sales_order?.so_number || '—' }}</strong>
                                </div>
                                <div class="meta-chip">
                                    <span class="meta-chip-label">Amount Paid</span>
                                    <strong>{{ formatCurrency(selectedReceipt.amount_paid) }}</strong>
                                </div>
                                <div class="meta-chip" :class="{ 'meta-chip--warn': !isReceiptEligible }">
                                    <span class="meta-chip-label">Return Window</span>
                                    <strong>{{ getReturnWindowLabel(selectedReceipt) }}</strong>
                                </div>
                            </div>
                            <div v-if="selectedReceipt && !isReceiptEligible" class="inline-alert inline-alert--error">
                                <i class="ri-error-warning-line"></i>
                                <span>{{ selectedReceipt.return_policy?.reason || 'This receipt is not eligible for return.' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Reason for return -->
                    <div v-if="selectedReceipt" class="rfw-step">
                        <div class="rfw-step-rail">
                            <div class="rfw-dot" :class="form.reason ? 'done' : 'active'">
                                <i class="ri-check-line" v-if="form.reason"></i>
                                <span v-else>2</span>
                            </div>
                            <div class="rfw-line"></div>
                        </div>
                        <div class="rfw-content">
                            <p class="rfw-title">Reason for return</p>
                            <textarea
                                class="form-control"
                                v-model="form.reason"
                                rows="3"
                                placeholder="Enter the reason for the return"
                                :class="{ 'input-error': form.errors.reason }"
                            ></textarea>
                            <span v-if="form.errors.reason" class="error-message">{{ form.errors.reason }}</span>
                        </div>
                    </div>

                    <!-- Step 3: Items to return -->
                    <div v-if="selectedReceipt" class="rfw-step rfw-step--last">
                        <div class="rfw-step-rail">
                            <div class="rfw-dot active">3</div>
                        </div>
                        <div class="rfw-content">
                            <div class="rfw-title-row">
                                <p class="rfw-title">Items to return</p>
                                <button type="button" class="table-action-btn" @click="toggleAllItems">
                                    <i :class="allItemsSelected ? 'ri-checkbox-multiple-blank-line' : 'ri-checkbox-multiple-line'"></i>
                                    {{ allItemsSelected ? 'Clear All' : 'Select All' }}
                                </button>
                            </div>
                            <div class="table-shell">
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0 return-items-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 44px;">
                                                    <input type="checkbox" :checked="allItemsSelected" @change="toggleAllItems">
                                                </th>
                                                <th>Product</th>
                                                <th>Batch</th>
                                                <th class="text-center">Sold Qty</th>
                                                <th class="text-center">Return Qty</th>
                                                <th class="text-center">Condition</th>
                                                <th class="text-end">Price</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="item in selectedItems" :key="item.id">
                                                <td>
                                                    <input
                                                        type="checkbox"
                                                        :checked="isItemSelected(item.id)"
                                                        @change="toggleItemSelection(item)"
                                                    >
                                                </td>
                                                <td>
                                                    <div class="product-cell">
                                                        <strong>{{ item.product_name || getProductName(item.product_id) }}</strong>
                                                        <small>Return candidate</small>
                                                    </div>
                                                </td>
                                                <td><span class="batch-pill">{{ item.batch_code || '-' }}</span></td>
                                                <td class="text-center"><span class="qty-pill">{{ returnableQty(item) }}</span></td>
                                                <td class="text-center">
                                                    <input
                                                        type="number"
                                                        class="form-control form-control-sm return-qty-input"
                                                        :value="getReturnQuantity(item.id)"
                                                        :min="isItemSelected(item.id) ? 1 : 0"
                                                        :max="returnableQty(item)"
                                                        :disabled="!isItemSelected(item.id)"
                                                        @input="updateReturnQuantity(item.id, $event.target.value, returnableQty(item))"
                                                    >
                                                </td>
                                                <td class="text-center">
                                                    <select
                                                        class="form-control form-control-sm"
                                                        :value="getReturnCondition(item.id)"
                                                        :disabled="!isItemSelected(item.id)"
                                                        @change="updateReturnCondition(item.id, $event.target.value)"
                                                    >
                                                        <option value="restockable">Restockable</option>
                                                        <option value="damaged">Damaged</option>
                                                    </select>
                                                </td>
                                                <td class="text-end">{{ formatCurrency(item.price) }}</td>
                                            </tr>
                                            <tr v-if="selectedItems.length === 0">
                                                <td colspan="7" class="text-center text-muted py-3">No items found for this receipt.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <span v-if="form.errors.item_ids" class="error-message">{{ form.errors.item_ids }}</span>
                            <span v-if="form.errors.return_quantities" class="error-message">{{ form.errors.return_quantities }}</span>
                            <span v-if="form.errors.return_conditions" class="error-message">{{ form.errors.return_conditions }}</span>
                            <small v-if="selectedItems.length > 0 && selectedReturnCount === 0" class="help-text">
                                Select at least one item to return.
                            </small>
                        </div>
                    </div>

                    <div v-if="submitErrorMessage" class="inline-alert inline-alert--error">
                        <i class="ri-error-warning-line"></i>
                        <span>{{ submitErrorMessage }}</span>
                    </div>

                    <div v-if="selectedReceipt && isReceiptUnavailable" class="inline-alert inline-alert--error">
                        <i class="ri-error-warning-line"></i>
                        <span>This receipt's sales order already has a pending or completed sales return.</span>
                    </div>

                    <div v-if="saveSuccess" class="inline-alert inline-alert--success">
                        <i class="ri-checkbox-circle-fill"></i>
                        <span>Sales return request created successfully!</span>
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" @click="hide">
                    <i class="ri-close-line"></i>
                    Cancel
                </button>
                <button type="button" class="btn btn-save" :disabled="isSubmitDisabled" @click="submit">
                    <i class="ri-save-line" v-if="!form.processing"></i>
                    <i class="ri-loader-4-line spinner" v-else></i>
                    {{ form.processing ? 'Saving...' : 'Create Sales Return' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import { useForm } from '@inertiajs/vue3';

export default {
    props: ['dropdowns', 'returnGracePeriod'],
    data() {
        return {
            showModal: false,
            loadingReceipts: false,
            loadingDetail: false,
            saveSuccess: false,
            receipts: [],
            receiptDetail: null,
            receiptKeyword: '',
            selectedReceiptId: null,
            form: useForm({
                id: null,
                type: 'Sales Return',
                reason: null,
                action: 'adjustment',
                receipt_id: null,
                item_ids: [],
                return_quantities: {},
                return_conditions: {},
            }),
        };
    },
    computed: {
        filteredReceipts() {
            const keyword = (this.receiptKeyword || '').trim().toLowerCase();
            const windowDays = this.returnGracePeriod || 7;
            const cutoff = new Date();
            cutoff.setDate(cutoff.getDate() - windowDays);
            cutoff.setHours(0, 0, 0, 0);

            return this.receipts.filter((receipt) => {
                // Only payment-type receipts that aren't cancelled
                if (receipt.receipt_type !== 'payment') return false;
                if (receipt.status?.slug === 'cancelled') return false;

                // Within return grace period window
                const receiptDate = receipt.receipt_date ? new Date(receipt.receipt_date) : null;
                if (!receiptDate || receiptDate < cutoff) return false;

                // Sales order must exist and not be in a blocked status
                if (!receipt?.sales_order || this.isBlockedStatus(receipt.sales_order.status?.slug)) {
                    return false;
                }

                if (!keyword) return true;

                const haystack = [
                    receipt.receipt_number,
                    receipt.customer?.name,
                    receipt.sales_order?.so_number,
                ]
                    .filter(Boolean)
                    .join(' ')
                    .toLowerCase();

                return haystack.includes(keyword);
            });
        },
        selectedReceipt() {
            return this.receipts.find(receipt => Number(receipt.id) === Number(this.selectedReceiptId)) || null;
        },
        selectedItems() {
            return this.receiptDetail?.sales_order?.items || [];
        },
        allItemsSelected() {
            return this.selectedItems.length > 0 && this.selectedItems.every(item => this.isItemSelected(item.id));
        },
        selectedReturnCount() {
            return this.selectedItems.filter(item => this.isItemSelected(item.id)).length;
        },
        isReceiptUnavailable() {
            return this.isBlockedStatus(this.selectedReceipt?.sales_order?.status?.slug);
        },
        isReceiptEligible() {
            return !!this.selectedReceipt?.return_policy?.is_eligible;
        },
        isSubmitDisabled() {
            return this.form.processing
                || !this.selectedReceipt
                || !this.form.id
                || !this.form.receipt_id
                || !this.form.reason
                || this.selectedReturnCount === 0
                || !this.isReceiptEligible
                || this.isReceiptUnavailable;
        },
        submitErrorMessage() {
            const prioritizedFields = [
                'receipt_id',
                'reason',
                'item_ids',
                'return_quantities',
                'return_conditions',
                'stock',
            ];

            for (const field of prioritizedFields) {
                const error = this.form.errors?.[field];
                if (Array.isArray(error) && error.length > 0) return error[0];
                if (typeof error === 'string' && error.trim()) return error;
            }

            const firstError = Object.values(this.form.errors || {}).find((error) => {
                if (Array.isArray(error)) return error.length > 0;
                return typeof error === 'string' && error.trim();
            });

            if (Array.isArray(firstError) && firstError.length > 0) return firstError[0];
            return typeof firstError === 'string' ? firstError : '';
        }
    },
    watch: {
        async selectedReceipt(receipt) {
            this.form.clearErrors();
            this.form.reason = null;
            this.receiptDetail = null;

            if (!receipt) {
                this.form.id = null;
                this.form.receipt_id = null;
                this.form.item_ids = [];
                this.form.return_quantities = {};
                this.form.return_conditions = {};
                return;
            }

            this.form.receipt_id = receipt.id;
            this.loadingDetail = true;
            try {
                const res = await axios.get(`/receipts/${receipt.id}`, { params: { option: 'detail' } });
                this.receiptDetail = res.data?.data ?? res.data;
                const allItems = this.receiptDetail?.sales_order?.items || [];
                const items = allItems.filter(item => (item.quantity - (item.returned_quantity || 0)) > 0);
                this.form.id = this.receiptDetail?.sales_order?.id || null;
                this.form.item_ids = items.map(item => item.id);
                this.form.return_quantities = items.reduce((q, item) => { q[item.id] = item.quantity - (item.returned_quantity || 0); return q; }, {});
                this.form.return_conditions = items.reduce((c, item) => { c[item.id] = 'restockable'; return c; }, {});
            } catch (e) {
                console.error('Failed to load receipt detail', e);
            } finally {
                this.loadingDetail = false;
            }
        }
    },
    methods: {
        isBlockedStatus(status) {
            return ['sales-returned', 'sales-return-approval', 'cancelled'].includes(status);
        },
        async fetchReceipts() {
            this.loadingReceipts = true;
            try {
                const response = await axios.get('/receipts', {
                    params: {
                        option: 'lists',
                        count: 100,
                    }
                });

                this.receipts = response?.data?.data || [];
            } catch (error) {
                console.error('Failed to load receipts for sales return creation.', error);
                this.receipts = [];
            } finally {
                this.loadingReceipts = false;
            }
        },
        async show() {
            this.resetState();
            this.showModal = true;
            await this.fetchReceipts();
        },
        hide() {
            this.showModal = false;
            this.resetState();
        },
        resetState() {
            this.saveSuccess = false;
            this.receiptKeyword = '';
            this.selectedReceiptId = null;
            this.receipts = [];
            this.receiptDetail = null;
            this.form.reset();
            this.form.type = 'Sales Return';
            this.form.action = 'adjustment';
            this.form.clearErrors();
        },
        getReturnWindowLabel(receipt) {
            const policy = receipt?.return_policy;
            if (!policy) return '-';
            if (!policy.is_within_window) return 'Expired';
            if (typeof policy.days_remaining === 'number') {
                return `${policy.days_remaining} day(s) left`;
            }
            return `${policy.window_days} day window`;
        },
        returnableQty(item) {
            return Math.max(0, (item.quantity || 0) - (item.returned_quantity || 0));
        },
        isItemSelected(itemId) {
            return Number(this.form.return_quantities?.[itemId] || 0) > 0;
        },
        getReturnQuantity(itemId) {
            return Number(this.form.return_quantities?.[itemId] || 0);
        },
        getReturnCondition(itemId) {
            return this.form.return_conditions?.[itemId] || 'restockable';
        },
        toggleItemSelection(item) {
            if (this.isItemSelected(item.id)) {
                this.form.return_quantities[item.id] = 0;
            } else {
                this.form.return_quantities[item.id] = item.quantity - (item.returned_quantity || 0);
                if (!this.form.return_conditions[item.id]) {
                    this.form.return_conditions[item.id] = 'restockable';
                }
            }

            this.syncSelectedItems();
        },
        updateReturnQuantity(itemId, value, maxQuantity) {
            const parsed = Number(value);

            if (!Number.isFinite(parsed) || parsed <= 0) {
                this.form.return_quantities[itemId] = 0;
            } else {
                this.form.return_quantities[itemId] = Math.min(Math.floor(parsed), Number(maxQuantity));
            }

            this.syncSelectedItems();
        },
        updateReturnCondition(itemId, condition) {
            this.form.return_conditions[itemId] = condition;
        },
        syncSelectedItems() {
            this.form.item_ids = this.selectedItems
                .filter(item => Number(this.form.return_quantities?.[item.id] || 0) > 0)
                .map(item => item.id);
        },
        toggleAllItems() {
            this.form.return_quantities = this.selectedItems.reduce((quantities, item) => {
                quantities[item.id] = this.allItemsSelected ? 0 : item.quantity - (item.returned_quantity || 0);
                return quantities;
            }, {});
            this.syncSelectedItems();
        },
        submit() {
            if (this.isSubmitDisabled) return;
            this.syncSelectedItems();
            this.form.clearErrors();

            this.form.put(`/sales-orders/${this.form.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    this.saveSuccess = true;
                    this.$emit('add', true);
                    setTimeout(() => {
                        this.hide();
                    }, 1200);
                },
                onError: () => {
                    this.saveSuccess = false;
                }
            });
        },
        getProductName(productId) {
            const product = this.dropdowns?.products?.find(item => item.value === productId);
            return product?.name || `Product #${productId}`;
        },
        formatCurrency(value) {
            const amount = Number(value || 0);
            return `PHP ${amount.toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            })}`;
        },
    }
};
</script>

<style scoped>
/* ── Modal shell ── */
.modal-container {
    width: min(100%, 1100px);
    max-height: 90vh;
}

.modal-title-wrap {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.modal-title-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    background: rgba(61, 141, 122, 0.12);
    border: 1px solid rgba(61, 141, 122, 0.16);
    color: #3d8d7a;
    font-size: 17px;
    flex-shrink: 0;
}

.modal-kicker {
    display: inline-block;
    margin-bottom: 0.15rem;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #6b8c85;
}

.modal-header h2 {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
}

.close-btn i {
    font-size: 1.1rem;
}

.modal-body {
    padding: 1.1rem 1.25rem 1.25rem;
}

/* ── Vertical stepper ── */
.return-form {
    display: flex;
    flex-direction: column;
}

.rfw-step {
    display: flex;
    gap: 1rem;
}

.rfw-step-rail {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex-shrink: 0;
    width: 28px;
}

.rfw-dot {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 0.78rem;
    font-weight: 800;
    flex-shrink: 0;
    background: #e6f2ec;
    border: 1.5px solid #c0ddd2;
    color: #4a7a6e;
    transition: background 0.2s ease, box-shadow 0.2s ease;
}

.rfw-dot.active {
    background: #3d8d7a;
    border-color: #3d8d7a;
    color: #fff;
    box-shadow: 0 2px 8px rgba(61, 141, 122, 0.28);
}

.rfw-dot.done {
    background: #2a6657;
    border-color: #2a6657;
    color: #fff;
}

.rfw-line {
    flex: 1;
    width: 2px;
    background: linear-gradient(to bottom, #c0ddd2, #deeee8);
    min-height: 20px;
    margin-top: 4px;
}

.rfw-content {
    flex: 1;
    padding-bottom: 1.4rem;
    min-width: 0;
}

.rfw-step--last .rfw-content {
    padding-bottom: 0.25rem;
}

.rfw-title {
    font-size: 0.88rem;
    font-weight: 700;
    color: #16322e;
    margin: 0 0 0.7rem;
    line-height: 28px;
}

.rfw-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 0.7rem;
}

.rfw-title-row .rfw-title {
    margin-bottom: 0;
}

/* ── Compound search bar ── */
.search-compound {
    display: flex;
    align-items: center;
    border: 1.5px solid #cfe5dc;
    border-radius: 12px;
    background: #fff;
    overflow: hidden;
    transition: border-color 0.18s ease, box-shadow 0.18s ease;
    margin-bottom: 0.65rem;
}

.search-compound:focus-within {
    border-color: #3d8d7a;
    box-shadow: 0 0 0 3px rgba(61, 141, 122, 0.12);
}

.search-lead-icon {
    padding: 0 0.55rem 0 0.85rem;
    color: #8aab9f;
    font-size: 1rem;
    flex-shrink: 0;
    line-height: 1;
}

.search-compound-input {
    flex: 1;
    border: none !important;
    outline: none !important;
    box-shadow: none !important;
    background: transparent !important;
    padding: 0.68rem 0.4rem !important;
    font-size: 0.88rem;
    color: #1f2937;
    min-height: 0 !important;
    border-radius: 0 !important;
}

.search-compound-input::placeholder {
    color: #a4bab3;
}

.search-refresh {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.4rem 0.75rem;
    margin: 0.25rem 0.25rem 0.25rem 0;
    border: 1px solid #cfe5dc;
    border-radius: 9px;
    background: #f2f9f6;
    color: #4a7a6e;
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.18s ease;
    flex-shrink: 0;
}

.search-refresh:hover:not(:disabled) {
    background: #e4f2ec;
    border-color: #b8d9cc;
    color: #3d8d7a;
}

.search-refresh:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* ── Form controls ── */
.form-control,
:deep(textarea.form-control) {
    width: 100%;
    min-height: 44px;
    border: 1.5px solid #cfe5dc;
    border-radius: 12px;
    background: #fff;
    color: #1f2937;
    padding: 0.68rem 1rem;
    font-size: 0.88rem;
    transition: border-color 0.18s ease, box-shadow 0.18s ease;
}

.form-control:focus,
:deep(textarea.form-control:focus),
:deep(.form-control:focus) {
    outline: none;
    border-color: #3d8d7a;
    box-shadow: 0 0 0 3px rgba(61, 141, 122, 0.12);
}

.input-error,
:deep(.input-error) {
    border-color: #dc3545 !important;
}

/* ── Receipt select ── */
.select-wrapper {
    position: relative;
}

.receipt-select {
    -webkit-appearance: none;
    appearance: none;
    padding-right: 2.5rem;
    cursor: pointer;
}

.select-chevron {
    position: absolute;
    right: 0.85rem;
    top: 50%;
    transform: translateY(-50%);
    color: #6b8c85;
    font-size: 1.1rem;
    pointer-events: none;
    line-height: 1;
}

/* ── Receipt meta chips (inline below select) ── */
.receipt-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: 0.65rem;
}

.meta-chip {
    display: flex;
    flex-direction: column;
    background: #f2f9f6;
    border: 1px solid #cce8de;
    border-radius: 10px;
    padding: 0.35rem 0.6rem;
}

.meta-chip-label {
    font-size: 0.63rem;
    font-weight: 700;
    letter-spacing: 0.055em;
    text-transform: uppercase;
    color: #8aab9f;
    line-height: 1;
    margin-bottom: 0.18rem;
}

.meta-chip strong {
    font-size: 0.82rem;
    font-weight: 700;
    color: #16322e;
    white-space: nowrap;
}

.meta-chip--warn {
    background: #fff8f1;
    border-color: #f5d5b0;
}

.meta-chip--warn strong {
    color: #a85e20;
}

/* ── Inline alerts ── */
.inline-alert {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.65rem 0.9rem;
    border-radius: 10px;
    font-size: 0.86rem;
    margin-top: 0.65rem;
}

.inline-alert--error {
    background: #fff3f2;
    border: 1px solid #f3c3bd;
    color: #a64035;
}

.inline-alert--success {
    background: #e6f7ee;
    border: 1px solid #b9e6ca;
    color: #155724;
}

.inline-alert i {
    font-size: 1rem;
    flex-shrink: 0;
}

.error-message {
    display: block;
    margin-top: 0.4rem;
    color: #dc3545;
    font-size: 0.8rem;
}

.help-text {
    display: block;
    margin-top: 0.38rem;
    font-size: 0.78rem;
    color: #8aab9f;
}

/* ── Items table ── */
.table-shell {
    border: 1.5px solid #cfe5dc;
    border-radius: 14px;
    overflow: hidden;
}

.return-items-table thead th {
    padding: 0.6rem 0.8rem;
    border: none;
    background: linear-gradient(180deg, #eef6f2 0%, #e7f1ec 100%);
    color: #49655d;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.return-items-table tbody td {
    padding: 0.58rem 0.8rem;
    vertical-align: middle;
    border-color: #ecf2ef;
}

.table-action-btn {
    border: 1.5px solid #cfe5dc;
    border-radius: 10px;
    background: #fff;
    color: #355f55;
    padding: 0.42rem 0.7rem;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.79rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.18s ease;
    white-space: nowrap;
}

.table-action-btn:hover {
    background: #f2f9f6;
    border-color: #b8d4c8;
}

.product-cell {
    display: flex;
    flex-direction: column;
    gap: 0.12rem;
}

.product-cell strong {
    color: #20413a;
    font-size: 0.87rem;
}

.product-cell small {
    color: #74867f;
    font-size: 0.74rem;
}

.batch-pill,
.qty-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    font-weight: 700;
    font-size: 0.77rem;
}

.batch-pill {
    background: #eef7f4;
    color: #2f7666;
}

.qty-pill {
    background: #edf4ff;
    color: #315d9a;
}

.return-qty-input {
    min-width: 76px;
    text-align: center;
}

/* ── Footer buttons ── */
.btn {
    min-height: 42px;
    border: none;
    border-radius: 11px;
    padding: 0.6rem 1rem;
    font-size: 0.88rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 0.42rem;
    justify-content: center;
    transition: all 0.18s ease;
    cursor: pointer;
}

.btn-cancel {
    background: #eef2f4;
    color: #58646f;
}

.btn-cancel:hover {
    background: #e2e8ec;
}

.btn-save {
    background: linear-gradient(135deg, #3d8d7a 0%, #2f7464 100%);
    color: #fff;
    box-shadow: 0 6px 18px rgba(61, 141, 122, 0.22);
}

.btn-save:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 10px 22px rgba(61, 141, 122, 0.28);
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    box-shadow: none;
}

/* ── Spinner ── */
.spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to   { transform: rotate(360deg); }
}

/* ── Responsive ── */
@media (max-width: 768px) {
    .modal-body {
        padding: 0.85rem;
    }

    .rfw-step {
        gap: 0.7rem;
    }

    .receipt-meta {
        flex-direction: column;
    }

    .rfw-title-row {
        flex-direction: column;
        align-items: flex-start;
    }

    .btn {
        width: 100%;
    }
}

</style>
