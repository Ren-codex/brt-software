<template>
    <div v-if="showModal" class="modal-overlay active" @click.self="hide">
        <div class="modal-container modal-xl" @click.stop>
            <div class="modal-header">
                <h2>Create Sales Return From Receipt</h2>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>

            <div class="modal-body" style="height: 75vh; overflow-y: auto;">
                <form @submit.prevent="submit">
                    <div class="receipt-search-card">
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
                            <span class="summary-label">Receipt No.</span>
                            <strong class="summary-value">{{ selectedReceipt.receipt_number }}</strong>
                        </div>
                        <div class="summary-card">
                            <span class="summary-label">Customer</span>
                            <strong class="summary-value">{{ selectedReceipt.customer?.name || '-' }}</strong>
                        </div>
                        <div class="summary-card">
                            <span class="summary-label">Sales Order</span>
                            <strong class="summary-value">{{ selectedReceipt.sales_order?.so_number || '-' }}</strong>
                        </div>
                        <div class="summary-card">
                            <span class="summary-label">Amount Paid</span>
                            <strong class="summary-value">{{ formatCurrency(selectedReceipt.amount_paid) }}</strong>
                        </div>
                        <div class="summary-card">
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
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0">Items To Return</label>
                                <button type="button" class="btn btn-sm btn-outline-secondary" @click="toggleAllItems">
                                    {{ allItemsSelected ? 'Clear All' : 'Select All' }}
                                </button>
                            </div>

                            <div class="table-responsive border rounded">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light">
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
                                            <td>{{ item.product_name || getProductName(item.product_id) }}</td>
                                            <td>{{ item.batch_code || '-' }}</td>
                                            <td class="text-center">{{ item.quantity }}</td>
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
.receipt-search-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.receipt-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.summary-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 0.875rem 1rem;
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
    color: #0f172a;
    font-size: 0.95rem;
}

.receipt-refresh {
    flex: 0 0 auto;
}

.return-qty-input {
    min-width: 88px;
    text-align: center;
}
</style>
