<template>
    <div
        v-show="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"
    >
        <div class="modal-container modal-xl pretty-return-modal" @click.stop>
            <div class="modal-header">
                <div>
                    <h2 class="mb-1">Approve Sales Return</h2>
                    <p class="mb-0 header-subtitle">Review items before confirming this return request.</p>
                </div>
                <button type="button" class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>

            <div class="modal-body p-4">
                <div class="summary-grid">
                    <div class="summary-card">
                        <p class="summary-label">Sales Order</p>
                        <p class="summary-value text-danger">{{ so_number || "-" }}</p>
                    </div>
                    <div class="summary-card">
                        <p class="summary-label">Items Selected</p>
                        <p class="summary-value">{{ selectedItems.length }} / {{ items.length }}</p>
                    </div>
                    <div class="summary-card">
                        <p class="summary-label">Selected Amount</p>
                        <p class="summary-value text-success">{{ formatCurrency(selectedTotal) }}</p>
                    </div>
                </div>

                <div class="notice-box">
                    <i class="ri-information-line"></i>
                    <span>Each approved item follows its saved condition: restockable items return to inventory, while damaged items stay out of sellable stock.</span>
                </div>

                <div class="table-wrap mt-3">
                    <div class="table-toolbar">
                        <h6 class="mb-0">Select items to return</h6>
                        <button type="button" class="btn btn-sm btn-outline-secondary" @click="toggleAll">
                            {{ allItemsSelected ? "Clear All" : "Select All" }}
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 pretty-table">
                            <thead>
                                <tr>
                                    <th class="col-check">
                                        <input type="checkbox" :checked="allItemsSelected" @change="toggleAll">
                                    </th>
                                    <th>Product</th>
                                    <th>Returnable Qty</th>
                                    <th>Return Qty</th>
                                    <th>Condition</th>
                                    <th>Unit</th>
                                    <th>Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in items" :key="item.id">
                                    <td>
                                        <input type="checkbox" v-model="selectedItems" :value="item.id">
                                    </td>
                                    <td>{{ getProductName(item.product_id) }}</td>
                                    <td>{{ item.quantity - (item.returned_quantity || 0) }}</td>
                                    <td>{{ getReturnQuantity(item.id, item.quantity - (item.returned_quantity || 0)) }}</td>
                                    <td>{{ formatCondition(getReturnCondition(item.id)) }}</td>
                                    <td>{{ item.unit }}</td>
                                    <td>{{ formatCurrency(item.price) }}</td>
                                    <td class="fw-semibold">{{ formatCurrency(getReturnQuantity(item.id, item.quantity - (item.returned_quantity || 0)) * (item.price - (item.discount_per_unit || 0))) }}</td>
                                </tr>
                            </tbody>
                            <tfoot v-if="items.length > 0">
                                <tr>
                                    <td colspan="7" class="text-end fw-bold">Return Value:</td>
                                    <td class="fw-bold text-danger">{{ formatCurrency(selectedTotal) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Replacement Items -->
                <div class="table-wrap mt-3">
                    <div class="table-toolbar">
                        <h6 class="mb-0">Replacement Items</h6>
                        <button type="button" class="btn btn-sm btn-outline-success" @click="addReplacementRow">
                            <i class="ri-add-line me-1"></i> Add Item
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 pretty-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th style="width:120px">Qty</th>
                                    <th style="width:130px">Unit Price</th>
                                    <th style="width:130px">Total</th>
                                    <th style="width:44px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, i) in replacementRows" :key="i">
                                    <td>
                                        <select v-model="row.product_id" @change="onReplacementProductChange(row)" class="form-select form-select-sm">
                                            <option :value="null">Select product...</option>
                                            <option v-for="p in productsWithStock" :key="p.value" :value="p.value">{{ p.name }}</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" v-model.number="row.quantity" min="1" class="form-control form-control-sm" placeholder="Qty">
                                    </td>
                                    <td>
                                        <input type="number" v-model.number="row.price" min="0" step="0.01" class="form-control form-control-sm" placeholder="Price">
                                    </td>
                                    <td class="fw-semibold">{{ formatCurrency((row.quantity || 0) * (row.price || 0)) }}</td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-danger" @click="removeReplacementRow(i)">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="replacementRows.length === 0">
                                    <td colspan="5" class="text-center text-muted small py-2">No replacement items added yet.</td>
                                </tr>
                            </tbody>
                            <tfoot v-if="replacementRows.length > 0">
                                <tr>
                                    <td colspan="3" class="text-end fw-bold">Replacement Value:</td>
                                    <td class="fw-bold text-success">{{ formatCurrency(replacementTotal) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Under-value warning -->
                    <div v-if="replacementUndervalue" class="alert-undervalue mt-2">
                        <i class="ri-error-warning-line"></i>
                        Replacement value ({{ formatCurrency(replacementTotal) }}) must be equal to or greater than the return value ({{ formatCurrency(selectedTotal) }}). No under-value replacements allowed.
                    </div>

                    <!-- Cash return summary -->
                    <div class="cash-return-summary mt-3">
                        <div class="crs-row">
                            <span>Return Value</span>
                            <span class="text-danger">- {{ formatCurrency(selectedTotal) }}</span>
                        </div>
                        <div class="crs-row">
                            <span>Replacement Value</span>
                            <span class="text-success">+ {{ formatCurrency(replacementTotal) }}</span>
                        </div>
                        <div class="crs-row crs-total">
                            <span>Extra to Collect</span>
                            <span :class="extraAmount > 0 ? 'text-primary fw-bold' : 'text-muted'">
                                {{ extraAmount > 0 ? formatCurrency(extraAmount) : 'None' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="confirm-box mt-4">
                    <label for="confirm" class="form-label fw-semibold">Type <code>CONFIRM</code> to proceed</label>
                    <TextInput
                        type="text"
                        id="confirm"
                        v-model="text_confirm"
                        class="form-control text-center"
                        :class="{ 'input-error': text_confirm && !confirmTextValid }"
                        placeholder="CONFIRM"
                    />
                    <small v-if="text_confirm && !confirmTextValid" class="text-danger">Confirmation text does not match.</small>
                </div>
            </div>

            <div class="modal-footer pretty-footer">
                <button class="btn btn-secondary me-2" @click="hide">Close</button>
                <button class="btn btn-primary" @click="submit" :disabled="!isValid">
                    <i class="ri-loader-4-line spinner me-1" v-if="form.processing"></i>
                    <i class="ri-check-line me-1" v-else></i>
                    {{ form.processing ? "Approving..." : "Approve Return" }}
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { useForm } from '@inertiajs/vue3';
import TextInput from '@/Shared/Components/Forms/TextInput.vue';

export default {
    components: { TextInput },
    props: ['products'],
    data(){
        return {
            form: useForm({
                id: null,
                action: 'approve',
                item_ids: [],
                replacement_items: [],
            }),
            so_number: null,
            paymentMode: '',
            text_confirm: '',
            showModal: false,
            items: [],
            selectedItems: [],
            returnItems: {},
            returnConditions: {},
            replacementRows: [],
        }
    },
    computed: {
        productsWithStock() {
            return (this.products || []).filter(p => (p.available_quantity || 0) > 0);
        },
        isCashSale() {
            const mode = (this.paymentMode || '').trim().toLowerCase();
            return mode !== 'credit' && mode !== 'credit sales';
        },
        allItemsSelected() {
            return this.items.length > 0 && this.selectedItems.length === this.items.length;
        },
        selectedTotal() {
            return this.items
                .filter(item => this.selectedItems.includes(item.id))
                .reduce((sum, item) => sum + (this.getReturnQuantity(item.id, item.quantity - (item.returned_quantity || 0)) * (item.price - (item.discount_per_unit || 0))), 0);
        },
        replacementTotal() {
            return this.replacementRows.reduce((sum, row) => sum + ((row.quantity || 0) * (row.price || 0)), 0);
        },
        extraAmount() {
            return Math.max(0, this.replacementTotal - this.selectedTotal);
        },
        replacementUndervalue() {
            return this.replacementRows.length > 0 && this.replacementTotal < this.selectedTotal;
        },
        confirmTextValid() {
            return (this.text_confirm || '').trim().toUpperCase() === 'CONFIRM';
        },
        isValid() {
            return this.selectedItems.length > 0 && this.confirmTextValid && !this.form.processing && !this.replacementUndervalue;
        }
    },
    methods: {
        show(id, so_number, route, items = [], preselectedItemIds = [], returnItems = {}, returnConditions = {}, paymentMode = ''){
            this.form.reset();
            this.form.clearErrors();
            this.showModal = true;
            this.form.id = id;
            this.so_number = so_number;
            this.route = route;
            this.items = items;
            this.paymentMode = paymentMode;
            this.returnItems = returnItems || {};
            this.returnConditions = returnConditions || {};
            this.replacementRows = [];
            this.selectedItems = preselectedItemIds.length > 0
                ? preselectedItemIds
                : items.map(item => item.id);
            this.text_confirm = '';
        },

        addReplacementRow() {
            this.replacementRows.push({ product_id: null, quantity: 1, price: 0 });
        },
        removeReplacementRow(index) {
            this.replacementRows.splice(index, 1);
        },
        onReplacementProductChange(row) {
            const product = (this.products || []).find(p => p.value === row.product_id);
            if (product) {
                row.price = product.retail_price || product.price || 0;
            }
        },

        submit(){
            if (!this.isValid) return;

            this.form.item_ids = this.selectedItems;
            this.form.replacement_items = this.replacementRows.filter(r => r.product_id && r.quantity > 0);
            this.form.put(`${this.route}/${this.form.id}`,{
                preserveScroll: true,
                onSuccess: (response) => {
                    const receiptId = response?.props?.flash?.receipt_id || this.$page?.props?.flash?.receipt_id || null;
                    this.$emit('approve', true);
                    this.form.reset();
                    this.hide();
                    if (receiptId) {
                        window.open(`/receipts/${receiptId}?option=print&type=receipt`, '_blank');
                    }
                },
            });

        },
        hide(){
            this.form.reset();
            this.form.clearErrors();
            this.showModal = false;
            this.items = [];
            this.selectedItems = [];
            this.returnItems = {};
            this.returnConditions = {};
            this.replacementRows = [];
            this.paymentMode = '';
            this.text_confirm = '';
        },
        toggleAll(){
            if(this.allItemsSelected){
                this.selectedItems = [];
            } else {
                this.selectedItems = this.items.map(item => item.id);
            }
        },
        getProductName(productId) {
            const product = this.products ? this.products.find(p => p.value === productId) : null;
            return product ? product.name : 'Unknown Product';
        },
        getReturnQuantity(itemId, fallbackQuantity = 0) {
            return Number(this.returnItems?.[itemId] ?? fallbackQuantity);
        },
        getReturnCondition(itemId) {
            return this.returnConditions?.[itemId] || 'restockable';
        },
        formatCondition(condition) {
            const labels = {
                restockable: 'Restockable',
                damaged: 'Damaged',
            };
            return labels[condition] || condition;
        },
        formatCurrency(value) {
            const amount = Number(value || 0);
            return `PHP ${amount.toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            })}`;
        },
    }
}
</script>

<style scoped>
.pretty-return-modal {
    border-radius: 24px;
    overflow: hidden;
    max-height: calc(100vh - 2rem);
    display: flex;
    flex-direction: column;
}

.modal-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
}

.header-subtitle {
    color: rgba(255, 255, 255, 0.85);
    font-size: 13px;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.summary-card {
    background: #f8fbfa;
    border: 1px solid #e3ece8;
    border-radius: 12px;
    padding: 12px;
}

.summary-label {
    margin: 0;
    font-size: 12px;
    color: #6a7c74;
}

.summary-value {
    margin: 4px 0 0;
    font-size: 20px;
    font-weight: 700;
    color: #2a3b34;
}

.notice-box {
    margin-top: 14px;
    background: #eef7ff;
    border: 1px solid #d4e8fb;
    color: #30536f;
    border-radius: 10px;
    padding: 10px 12px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
}

.table-wrap {
    border: 1px solid #e2e8e5;
    border-radius: 12px;
    overflow: hidden;
}

.table-toolbar {
    padding: 10px 12px;
    background: #f8fbfa;
    border-bottom: 1px solid #e2e8e5;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.pretty-table thead th {
    background: #f5f8f7;
    color: #4d6058;
    font-weight: 700;
    font-size: 12px;
    border-bottom: 1px solid #dfe8e4;
}

.pretty-table td,
.pretty-table th {
    padding: 10px 12px;
}

.pretty-table tbody tr:hover {
    background: #f8fcfb;
}

.col-check {
    width: 44px;
}

.confirm-box code {
    background: #f2f5f3;
    color: #2f4a3f;
    border-radius: 6px;
    padding: 2px 6px;
}

.alert-undervalue {
    background: #fff5f5;
    border: 1px solid #ffc5c5;
    color: #c0392b;
    border-radius: 8px;
    padding: 10px 12px;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.cash-return-summary {
    background: #f8fbfa;
    border: 1px solid #e2e8e5;
    border-radius: 10px;
    padding: 12px 16px;
}
.crs-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 5px 0;
    font-size: 13px;
    border-bottom: 1px dashed #e2e8e5;
}
.crs-row:last-child { border-bottom: none; }
.crs-total {
    font-size: 14px;
    padding-top: 8px;
    margin-top: 4px;
}

.pretty-footer {
    flex-shrink: 0;
    border-top: 1px solid #e1e9e5;
    background: #fbfdfc;
    padding: 14px 18px;
}

@media (max-width: 991px) {
    .summary-grid {
        grid-template-columns: 1fr;
    }
}
</style>
