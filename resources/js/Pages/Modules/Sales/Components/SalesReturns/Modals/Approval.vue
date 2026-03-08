<template>
    <div
        v-show="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"
    >
        <div class="modal-container modal-xl pretty-return-modal" @click.stop>
            <div class="modal-header pretty-header">
                <div>
                    <h2 class="mb-1">Approve Sales Return</h2>
                    <p class="mb-0 header-subtitle">Review items before confirming this return request.</p>
                </div>
                <button class="close-btn" @click="hide">
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
                    <span>Selected items will be restored to stock. Unselected items remain treated as loss or damage.</span>
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
                                        <input
                                            type="checkbox"
                                            :checked="allItemsSelected"
                                            @change="toggleAll"
                                        >
                                    </th>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Unit</th>
                                    <th>Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in items" :key="item.id">
                                    <td>
                                        <input
                                            type="checkbox"
                                            v-model="selectedItems"
                                            :value="item.id"
                                        >
                                    </td>
                                    <td>{{ getProductName(item.product_id) }}</td>
                                    <td>{{ item.quantity }}</td>
                                    <td>{{ item.unit }}</td>
                                    <td>{{ formatCurrency(item.price) }}</td>
                                    <td class="fw-semibold">{{ formatCurrency(item.quantity * item.price) }}</td>
                                </tr>
                            </tbody>
                            <tfoot v-if="items.length > 0">
                                <tr>
                                    <td colspan="5" class="text-end fw-bold">Selected Total:</td>
                                    <td class="fw-bold text-success">{{ formatCurrency(selectedTotal) }}</td>
                                </tr>
                            </tfoot>
                        </table>
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
                    <i class="ri-check-line me-1"></i>
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
            }),
            so_number: null,
            text_confirm: '',
            showModal: false,
            items: [],
            selectedItems: [],
        }
    },
    computed: {
        allItemsSelected() {
            return this.items.length > 0 && this.selectedItems.length === this.items.length;
        },
        selectedTotal() {
            return this.items
                .filter(item => this.selectedItems.includes(item.id))
                .reduce((sum, item) => sum + (item.quantity * item.price), 0);
        },
        confirmTextValid() {
            return (this.text_confirm || '').trim().toUpperCase() === 'CONFIRM';
        },
        isValid() {
            return this.selectedItems.length > 0 && this.confirmTextValid && !this.form.processing;
        }
    },
    methods: {
        show(id, so_number, route, items = [], preselectedItemIds = []){
            this.form.reset();
            this.form.clearErrors();
            this.showModal = true;
            this.form.id = id;
            this.so_number = so_number;
            this.route = route;
            this.items = items;
            this.selectedItems = preselectedItemIds.length > 0
                ? preselectedItemIds
                : items.map(item => item.id);
            this.text_confirm = '';
        },

        submit(){
            if (!this.isValid) return;

            this.form.item_ids = this.selectedItems;
            this.form.put(`${this.route}/${this.form.id}`,{
                preserveScroll: true,
                onSuccess: () => {
                    this.$emit('approve', true);
                    this.form.reset();
                    this.hide();
                },
            });

        },
        hide(){
            this.form.reset();
            this.form.clearErrors();
            this.showModal = false;
            this.items = [];
            this.selectedItems = [];
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
    border-radius: 16px;
    overflow: hidden;
}

.pretty-header {
    background: linear-gradient(135deg, #f7faf9 0%, #eef6f3 100%);
    border-bottom: 1px solid #dde9e3;
}

.header-subtitle {
    color: #5f7169;
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

.pretty-footer {
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
