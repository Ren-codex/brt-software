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
                <form @submit.prevent="submit">
                    <div class="receipt-search-card">
                        <div class="section-heading">
                            <div>
                                <span class="section-kicker">Step 1</span>
                                <h3>Find the receipt</h3>
                            </div>
                        </div>
                        <div class="form-row align-items-end">
                            <div class="form-group flex-grow-1">
                                <label class="form-label">Search Receipt</label>
                                <div class="input-wrapper">
                                    <i class="ri-search-line input-icon"></i>
                                    <input
                                        v-model="receiptKeyword"
                                        type="text"
                                        class="form-control"
                                        placeholder="Search by receipt number, customer, or sales order"
                                    >
                                </div>
                            </div>
                            <div class="form-group receipt-refresh">
                                <button type="button" class="btn btn-cancel" @click="fetchReceipts" :disabled="loadingReceipts">
                                    <i :class="loadingReceipts ? 'ri-loader-4-line spinner' : 'ri-refresh-line'"></i>
                                    Refresh
                                </button>
                            </div>
                        </div>

                        <div class="form-group mb-0">
                            <label class="form-label">Receipt</label>
                            <select v-model="selectedReceiptId" class="form-control">
                                <option :value="null" disabled>Select receipt</option>
                                <option v-for="receipt in filteredReceipts" :key="receipt.id" :value="receipt.id">
                                    {{ receipt.receipt_number }} - {{ receipt.customer?.name || 'No Customer' }}
                                </option>
                            </select>
                            <small v-if="!loadingReceipts && filteredReceipts.length === 0" class="text-muted">
                                No receipts available for sales return.
                            </small>
                        </div>
                    </div>

                    <div v-if="selectedReceipt" class="receipt-summary-grid">
                        <div class="summary-card">
                            <i class="ri-file-list-3-line summary-icon"></i>
                            <span class="summary-label">Receipt No.</span>
                            <strong class="summary-value">{{ selectedReceipt.receipt_number }}</strong>
                        </div>
                        <div class="summary-card">
                            <i class="ri-user-line summary-icon"></i>
                            <span class="summary-label">Customer</span>
                            <strong class="summary-value">{{ selectedReceipt.customer?.name || '-' }}</strong>
                        </div>
                        <div class="summary-card">
                            <i class="ri-shopping-bag-line summary-icon"></i>
                            <span class="summary-label">Sales Order</span>
                            <strong class="summary-value">{{ selectedReceipt.sales_order?.so_number || '-' }}</strong>
                        </div>
                        <div class="summary-card">
                            <i class="ri-money-dollar-circle-line summary-icon"></i>
                            <span class="summary-label">Amount Paid</span>
                            <strong class="summary-value">{{ formatCurrency(selectedReceipt.amount_paid) }}</strong>
                        </div>
                        <div class="summary-card">
                            <i class="ri-time-line summary-icon"></i>
                            <span class="summary-label">Return Window</span>
                            <strong class="summary-value">
                                {{ getReturnWindowLabel(selectedReceipt) }}
                            </strong>
                        </div>
                    </div>

                    <div v-if="selectedReceipt && !isReceiptEligible" class="error-alert">
                        <i class="ri-error-warning-line"></i>
                        <span>{{ selectedReceipt.return_policy?.reason || 'This receipt is not eligible for return.' }}</span>
                    </div>

                    <div v-if="submitErrorMessage" class="error-alert">
                        <i class="ri-error-warning-line"></i>
                        <span>{{ submitErrorMessage }}</span>
                    </div>

                    <div v-if="selectedReceipt" class="form-row">
                        <div class="form-group w-100">
                            <div class="section-heading section-heading-inline">
                                <div>
                                    <span class="section-kicker">Step 2</span>
                                    <h3>Reason for return</h3>
                                </div>
                            </div>
                            <label class="form-label">Reason</label>
                            <b-form-textarea
                                v-model="form.reason"
                                rows="5"
                                placeholder="Enter the reason for the return"
                                :class="{ 'input-error': form.errors.reason }"
                            />
                            <span v-if="form.errors.reason" class="error-message">{{ form.errors.reason }}</span>
                        </div>
                    </div>

                    <div v-if="selectedReceipt" class="form-row">
                        <div class="form-group w-100">
                            <div class="items-card-header">
                                <div>
                                    <span class="section-kicker">Step 3</span>
                                    <h3>Items to return</h3>
                                </div>
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
                                                    <small>Product return candidate</small>
                                                </div>
                                            </td>
                                            <td><span class="batch-pill">{{ item.batch_code || '-' }}</span></td>
                                            <td class="text-center"><span class="qty-pill">{{ item.quantity }}</span></td>
                                            <td class="text-center">
                                                <input
                                                    type="number"
                                                    class="form-control form-control-sm return-qty-input"
                                                    :value="getReturnQuantity(item.id)"
                                                    :min="isItemSelected(item.id) ? 1 : 0"
                                                    :max="item.quantity"
                                                    :disabled="!isItemSelected(item.id)"
                                                    @input="updateReturnQuantity(item.id, $event.target.value, item.quantity)"
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
                                            <td colspan="7" class="text-center text-muted py-4">No items found for this receipt.</td>
                                        </tr>
                                    </tbody>
                                </table>
                                </div>
                            </div>
                            <span v-if="form.errors.item_ids" class="error-message">{{ form.errors.item_ids }}</span>
                            <span v-if="form.errors.return_quantities" class="error-message">{{ form.errors.return_quantities }}</span>
                            <span v-if="form.errors.return_conditions" class="error-message">{{ form.errors.return_conditions }}</span>
                            <small v-if="selectedItems.length > 0 && selectedReturnCount === 0" class="text-muted">
                                Select at least one item to return.
                            </small>
                        </div>
                    </div>

                    <div v-if="selectedReceipt && isReceiptUnavailable" class="error-alert">
                        <i class="ri-error-warning-line"></i>
                        <span>This receipt's sales order already has a pending or completed sales return.</span>
                    </div>

                    <div v-if="saveSuccess" class="success-alert">
                        <i class="ri-checkbox-circle-fill"></i>
                        <span>Sales return request created successfully!</span>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-cancel" @click="hide">
                            <i class="ri-close-line"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-save" :disabled="isSubmitDisabled">
                            <i class="ri-save-line" v-if="!form.processing"></i>
                            <i class="ri-loader-4-line spinner" v-else></i>
                            {{ form.processing ? 'Saving...' : 'Create Sales Return' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import { useForm } from '@inertiajs/vue3';

export default {
    props: ['dropdowns'],
    data() {
        return {
            showModal: false,
            loadingReceipts: false,
            saveSuccess: false,
            receipts: [],
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

            return this.receipts.filter((receipt) => {
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
            return this.selectedReceipt?.sales_order?.items || [];
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
        selectedReceipt(receipt) {
            this.form.clearErrors();
            this.form.reason = null;
            this.form.id = receipt?.sales_order?.id || null;
            this.form.receipt_id = receipt?.id || null;
            this.form.item_ids = receipt?.sales_order?.items?.map(item => item.id) || [];
            this.form.return_quantities = (receipt?.sales_order?.items || []).reduce((quantities, item) => {
                quantities[item.id] = item.quantity;
                return quantities;
            }, {});
            this.form.return_conditions = (receipt?.sales_order?.items || []).reduce((conditions, item) => {
                conditions[item.id] = 'restockable';
                return conditions;
            }, {});
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
                this.form.return_quantities[item.id] = item.quantity;
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
                quantities[item.id] = this.allItemsSelected ? 0 : item.quantity;
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
.modal-overlay {
    position: fixed;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: rgba(15, 23, 42, 0.52);
    backdrop-filter: blur(7px);
    z-index: 1060;
}

.modal-container {
    width: min(100%, 1120px);
    max-height: 90vh;
    overflow: hidden;
    border-radius: 28px;
    background:
        radial-gradient(circle at top right, rgba(61, 141, 122, 0.12), transparent 26%),
        linear-gradient(180deg, #fbfefd 0%, #f4faf8 100%);
    box-shadow: 0 32px 80px rgba(15, 23, 42, 0.24);
}

.modal-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.2rem 1.35rem 1rem;
    background: linear-gradient(135deg, #d7ece5 0%, #c7e2d9 100%);
    border-bottom: 1px solid #d7e5de;
}

.modal-title-wrap {
    display: flex;
    align-items: center;
    gap: 0.9rem;
}

.modal-title-icon {
    width: 46px;
    height: 46px;
    border-radius: 15px;
    display: grid;
    place-items: center;
    background: rgba(26, 104, 87, 0.14);
    color: #1a6857;
    font-size: 1.35rem;
}

.modal-kicker,
.section-kicker {
    display: inline-block;
    margin-bottom: 0.28rem;
    font-size: 0.74rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #648b74;
}

.modal-header h2 {
    margin: 0;
    font-size: 1.22rem;
    font-weight: 700;
    color: #20413a;
}

.close-btn {
    width: 38px;
    height: 38px;
    border: 0;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.72);
    color: #4b5563;
    display: grid;
    place-items: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.close-btn:hover {
    background: #fff;
    transform: rotate(90deg);
}

.close-btn i {
    font-size: 1.1rem;
}

.modal-body {
    height: 75vh;
    overflow-y: auto;
    padding: 1.2rem 1.35rem 1.35rem;
}

.section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.95rem;
}

.section-heading-inline {
    margin-bottom: 0.7rem;
}

.section-heading h3,
.items-card-header h3 {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: #20413a;
}

.receipt-search-card {
    background: rgba(255, 255, 255, 0.82);
    border: 1px solid #dceae4;
    border-radius: 22px;
    padding: 1rem 1rem 1.05rem;
    margin-bottom: 1rem;
    box-shadow: 0 12px 24px rgba(31, 92, 80, 0.06);
}

.form-row {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
}

.form-group {
    margin-bottom: 1rem;
}

.form-label {
    display: block;
    margin-bottom: 0.45rem;
    font-size: 0.88rem;
    font-weight: 600;
    color: #2c3e50;
}

.input-wrapper {
    position: relative;
}

.input-icon {
    position: absolute;
    top: 50%;
    left: 0.9rem;
    transform: translateY(-50%);
    color: #7f8c8d;
    font-size: 1rem;
}

.form-control,
:deep(textarea.form-control) {
    width: 100%;
    min-height: 48px;
    border: 1px solid #d7e5de;
    border-radius: 14px;
    background: #fff;
    color: #1f2937;
    padding: 0.78rem 1rem;
    transition: all 0.2s ease;
}

.input-wrapper .form-control {
    padding-left: 2.75rem;
}

.form-control:focus,
:deep(textarea.form-control:focus),
:deep(.form-control:focus) {
    outline: none;
    border-color: #3d8d7a;
    box-shadow: 0 0 0 4px rgba(61, 141, 122, 0.12);
}

.input-error,
:deep(.input-error) {
    border-color: #dc3545 !important;
}

.receipt-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.summary-card {
    position: relative;
    background: linear-gradient(180deg, #ffffff 0%, #f7fbfa 100%);
    border: 1px solid #dceae4;
    border-radius: 18px;
    padding: 0.95rem 1rem;
    box-shadow: 0 10px 22px rgba(31, 92, 80, 0.05);
}

.summary-label {
    display: block;
    color: #64748b;
    font-size: 0.75rem;
    margin-bottom: 0.35rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.summary-value {
    color: #20413a;
    font-size: 0.95rem;
}

.summary-icon {
    display: inline-grid;
    place-items: center;
    width: 36px;
    height: 36px;
    margin-bottom: 0.65rem;
    border-radius: 12px;
    background: #eef7f4;
    color: #2f7a68;
    font-size: 1rem;
}

.receipt-refresh {
    flex: 0 0 auto;
}

.error-alert,
.success-alert {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    margin-bottom: 1rem;
    padding: 0.9rem 1rem;
    border-radius: 16px;
}

.error-alert {
    background: linear-gradient(135deg, #fff3f2 0%, #ffe9e7 100%);
    border: 1px solid #f3c3bd;
    color: #a64035;
}

.success-alert {
    background: linear-gradient(135deg, #e6f7ee 0%, #d8f2e3 100%);
    border: 1px solid #b9e6ca;
    color: #155724;
}

.error-alert i,
.success-alert i {
    font-size: 1.1rem;
}

.items-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 0.85rem;
}

.table-action-btn {
    border: 1px solid #d7e5de;
    border-radius: 12px;
    background: #fff;
    color: #355f55;
    padding: 0.6rem 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.82rem;
    font-weight: 700;
    transition: all 0.2s ease;
}

.table-action-btn:hover {
    background: #f4faf8;
    border-color: #b7cec4;
}

.table-shell {
    border: 1px solid #dceae4;
    border-radius: 20px;
    overflow: hidden;
    background: linear-gradient(180deg, #fbfefd 0%, #f4faf8 100%);
}

.return-items-table thead th {
    padding: 0.9rem 0.8rem;
    border: none;
    background: linear-gradient(180deg, #eff7f4 0%, #e6f2ed 100%);
    color: #49655d;
    font-size: 0.75rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.return-items-table tbody td {
    padding: 0.85rem 0.8rem;
    vertical-align: middle;
    border-color: #edf3f1;
}

.product-cell {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.product-cell strong {
    color: #20413a;
}

.product-cell small {
    color: #74867f;
    font-size: 0.78rem;
}

.batch-pill,
.qty-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 44px;
    padding: 0.28rem 0.65rem;
    border-radius: 999px;
    font-weight: 700;
    font-size: 0.8rem;
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
    min-width: 88px;
    text-align: center;
}

.error-message {
    display: block;
    margin-top: 0.45rem;
    color: #dc3545;
    font-size: 0.82rem;
}

.form-actions {
    position: sticky;
    bottom: 0;
    z-index: 5;
    display: flex;
    justify-content: flex-end;
    gap: 0.85rem;
    margin-top: 1.25rem;
    padding-top: 1rem;
    background: linear-gradient(180deg, rgba(244, 250, 248, 0.7) 0%, #f4faf8 24px);
    border-top: 1px solid #dceae4;
    backdrop-filter: blur(6px);
}

.btn {
    min-height: 46px;
    border: none;
    border-radius: 14px;
    padding: 0.72rem 1.1rem;
    font-size: 0.9rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    justify-content: center;
    transition: all 0.2s ease;
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
    box-shadow: 0 12px 24px rgba(61, 141, 122, 0.2);
}

.btn-save:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 16px 28px rgba(61, 141, 122, 0.24);
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
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

@media (max-width: 768px) {
    .modal-body {
        padding: 1rem;
    }

    .items-card-header,
    .form-row {
        flex-direction: column;
        align-items: stretch;
    }

    .form-actions {
        flex-direction: column-reverse;
    }

    .btn,
    .table-action-btn {
        width: 100%;
    }
}
</style>
