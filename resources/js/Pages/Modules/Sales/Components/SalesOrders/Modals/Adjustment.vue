<template>
    <div 
        v-show="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"
        
    >
        <div class="modal-container modal-lg">
            <div class="modal-header">
                <h2>{{ 'Sales Adjustment'}}</h2>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body">
                <form @submit.prevent="submit">
                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label for="type" class="form-label">Type</label>
                            <div class="input-wrapper">
                                <i class="ri-refund-line input-icon"></i>
                                <select
                                    class="form-control"
                                    v-model="form.type"
                                    :class="{ 'input-error': form.errors.type }"
                                >
                                    <option :value="null" disabled>Select Type</option>
                                    <option value="Sales Return">Sales Return</option>
                                    <option value="Sales Allowance">Sales Allowance</option>
                                </select>    
                            </div>
                            <span class="error-message" v-if="form.errors.type">{{ form.errors.type }}</span>
                        </div>

                    </div>
                    
                    <div class="form-row">
                      <div class="form-group w-100">
                            <label for="reason" class="form-label">Reason</label>
                            <textarea
                                id="textarea-rows"
                                class="form-control"
                                placeholder="Enter the reason"
                                rows="8"
                                v-model="form.reason"
                                :class="{ 'input-error': form.errors.reason }"
                            ></textarea>
                            <span class="error-message" v-if="form.errors.reason">{{ form.errors.reason }}</span>
                        </div>
                    </div>

                    <div class="form-row" v-if="isSalesReturn">
                        <div class="form-group w-100">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0">Items To Return</label>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    @click="toggleAllItems"
                                >
                                    {{ allItemsSelected ? 'Clear All' : 'Select All' }}
                                </button>
                            </div>
                            <div class="table-responsive border rounded p-2">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px;">
                                                <input
                                                    type="checkbox"
                                                    :checked="allItemsSelected"
                                                    @change="toggleAllItems"
                                                >
                                            </th>
                                            <th>Item</th>
                                            <th>Sold Qty</th>
                                            <th>Return Qty</th>
                                            <th>Condition</th>
                                            <th class="text-end">Price</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="item in items" :key="item.id">
                                            <td>
                                                <input
                                                    type="checkbox"
                                                    v-model="form.item_ids"
                                                    :value="item.id"
                                                >
                                            </td>
                                            <td>{{ getProductName(item.product_id) }}</td>
                                            <td>{{ item.quantity }}</td>
                                            <td>
                                                <input
                                                    type="number"
                                                    class="form-control form-control-sm"
                                                    v-model.number="form.return_quantities[item.id]"
                                                    :min="1"
                                                    :max="item.quantity"
                                                    :disabled="!form.item_ids.includes(item.id)"
                                                    style="width: 72px;"
                                                >
                                            </td>
                                            <td>
                                                <select
                                                    class="form-control form-control-sm"
                                                    v-model="form.return_conditions[item.id]"
                                                    :disabled="!form.item_ids.includes(item.id)"
                                                    style="width: 120px;"
                                                >
                                                    <option value="restockable">Restockable</option>
                                                    <option value="damaged">Damaged</option>
                                                </select>
                                            </td>
                                            <td class="text-end">{{ formatCurrency(item.price) }}</td>
                                        </tr>
                                        <tr v-if="items.length === 0">
                                            <td colspan="6" class="text-center text-muted">No items found for this order.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <span class="error-message" v-if="form.errors.item_ids">{{ form.errors.item_ids }}</span>
                            <small v-if="form.item_ids.length === 0" class="text-muted">Select at least one item to return.</small>
                        </div>
                    </div>

  
                    <div class="success-alert" v-if="saveSuccess">
                        <i class="ri-checkbox-circle-fill"></i>
                        <span>Adjustment has been saved successfully!</span>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-cancel" @click="hide">
                            <i class="ri-close-line"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-save" :disabled="form.processing || !form.type || !form.reason || (isSalesReturn && form.item_ids.length === 0)">
                            <i class="ri-save-line" v-if="!form.processing"></i>
                            <i class="ri-loader-4-line spinner" v-else></i>
                            {{ form.processing ? 'Saving...' : 'Save Order' }}
                        </button>
                    </div>  


                </form>
            </div>
        </div>
    </div>
</template>

<script>
import { useForm } from '@inertiajs/vue3';
import InputLabel from '@/Shared/Components/Forms/InputLabel.vue';
import TextInput from '@/Shared/Components/Forms/TextInput.vue';
import Multiselect from '@/Shared/Components/Forms/Multiselect.vue';

export default {
    components: { InputLabel, TextInput, Multiselect },
    props: ['dropdowns', 'items' , 'update' ],
    data() {
        return {
            currentUrl: window.location.origin,
            form: useForm({
                id: null,
                type: null,
                reason: null,
                action: 'adjustment',
                item_ids: [],
                return_quantities: {},
                return_conditions: {},
            }),
            showModal: false,
            saveSuccess: false,
            isExternal: false,
            items: [],
        }
    },
    computed: {
        isSalesReturn() {
            return this.form.type === 'Sales Return';
        },
        allItemsSelected() {
            return this.items.length > 0 && this.form.item_ids.length === this.items.length;
        }
    },
    methods: { 
        show(id, isExternal = false, items = []) {
            this.form.reset();
            this.form.clearErrors();
            this.form.id = id;
            this.isExternal = isExternal;
            this.items = items || [];
            this.form.item_ids = this.items.map(item => item.id);
            const quantities = {};
            const conditions = {};
            this.items.forEach(item => {
                quantities[item.id] = item.quantity;
                conditions[item.id] = 'restockable';
            });
            this.form.return_quantities = quantities;
            this.form.return_conditions = conditions;
            this.showModal = true;
        },

        submit() {
            this.form.action = 'adjustment';
            let url = this.isExternal ? `/sales-orders-external/${this.form.id}` : `/sales-orders/${this.form.id}`;
             this.form.put(url, {
                onSuccess: () => {
                    this.saveSuccess = true;
                    this.$emit('update');
                    setTimeout(() => {
                        this.hide();
                    }, 2000);
                },
                onError: () => {
                    this.saveSuccess = false;
                }
            });
        },

        hide() {
            this.form.reset();
            this.form.clearErrors();
            this.saveSuccess = false;
            this.showModal = false;
            this.items = [];
        },
        getReturnQty(itemId) {
            return this.form.return_quantities[itemId] ?? 0;
        },
        toggleAllItems() {
            if (this.allItemsSelected) {
                this.form.item_ids = [];
            } else {
                this.form.item_ids = this.items.map(item => item.id);
            }
        },
        getProductName(productId) {
            const product = this.dropdowns?.products?.find(p => p.value === productId);
            return product ? product.name : `Product #${productId}`;
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
