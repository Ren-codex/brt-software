<template>
    <div
        v-if="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"
    >
        <div class="modal-container" @click.stop style="max-width: 800px;">
            <div class="modal-header">
                <h2>{{ editable ? 'Update Purchase Order' : 'Create Purchase Order' }}</h2>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="success-alert" v-if="saveSuccess">
                    <i class="ri-checkbox-circle-fill"></i>
                    <span>Your information has been saved successfully!</span>
                </div>
                <form @submit.prevent="submit">
                    <div class="form-group">
                        <label for="supplier_id" class="form-label">Supplier</label>
                        <div class="input-wrapper">
                            <i class="ri-store-line input-icon"></i>
                            <select
                                v-model="form.supplier_id"
                                class="form-control"
                                :class="{ 'input-error': form.errors.supplier_id }"
                                @change="handleInput('supplier_id')"
                            >
                                <option value="" disabled>Select Supplier</option>
                                <option v-for="supplier in dropdowns.suppliers" :value="supplier.value" :key="supplier.value">{{ supplier.name }}</option>
                            </select>
                        </div>
                        <span class="error-message" v-if="form.errors.supplier_id">{{ form.errors.supplier_id }}</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Items</label>
                        <button type="button" @click="addItem" class="btn btn-sm btn-primary">Add Item</button>
                        <table class="table table-sm mt-2">
                            <thead>
                                <tr>
                                    <th style="width: 50%">Product</th>
                                    <th>Quantity</th>
                                    <th>Unit Cost</th>
                                    <th>Total Cost</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in form.items" :key="index">
                                    <td>
                                        <select
                                            v-model="item.product_id"
                                            class="form-control"
                                            :class="{ 'input-error': form.errors[`items.${index}.product_id`] }"
                                            @change="handleInput(`items.${index}.product_id`)"
                                        >
                                            <option value="" disabled>Select Product</option>
                                            <option v-for="product in dropdowns.products" :value="product.value" :key="product.value">{{ product.name }}</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input
                                            type="number"
                                            v-model="item.quantity"
                                            class="form-control"
                                            :class="{ 'input-error': form.errors[`items.${index}.quantity`] }"
                                            @input="calculateTotal(item); handleInput(`items.${index}.quantity`)"
                                            step="1"
                                            min="0"
                                            required
                                        >
                                    </td>
                                    <td>
                                        <input
                                            type="number"
                                            step="0.01"
                                            v-model="item.unit_cost"
                                            class="form-control"
                                            :class="{ 'input-error': form.errors[`items.${index}.unit_cost`] }"
                                            @input="calculateTotal(item); handleInput(`items.${index}.unit_cost`)"
                                            required
                                        >
                                    </td>
                                    <td>
                                        <input
                                            type="number"
                                            step="0.01"
                                            v-model="item.total_cost"
                                            class="form-control"
                                            readonly
                                        >
                                    </td>
                                    <td><button type="button" @click="removeItem(index)" class="btn btn-danger btn-sm"><i class="ri-close-line"></i></button></td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="mt-3">
                            <p><strong>Total Amount: {{ totalAmount }}</strong></p>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-cancel" @click="hide">
                            <i class="ri-close-line"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-save" :disabled="form.processing">
                            <i class="ri-save-line" v-if="!form.processing"></i>
                            <i class="ri-loader-4-line spinner" v-else></i>
                            {{ form.processing ? 'Saving...' : 'Save Information' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import { useForm } from '@inertiajs/vue3';
import Multiselect from '@/Shared/Components/Forms/Multiselect.vue';

export default {
    name: "CreatePurchaseOrderModal",
    components: {
        Multiselect,
    },
    props: ['dropdowns'],
    emits: ['add'],
    data() {
        return {
            form: useForm({
                id: null,
                supplier_id: null,
                total_amount: 0,
                items: [],
            }),
            showModal: false,
            editable: false,
            saveSuccess: false,
        };
    },
    mounted() {
        this.addItem();
    },
    computed: {
        totalAmount() {
            return this.form.items.reduce((sum, item) => sum + (item.total_cost || 0), 0);
        },
    },
    methods: {
        show() {
            this.form.reset();
            this.form.clearErrors();
            this.form.items = [];
            this.addItem();
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = true;
        },
        edit(data, index) {
            this.form.reset();
            this.form.clearErrors();
            this.form.id = data.id;
            this.form.supplier_id = data.supplier ? data.supplier.id : null;
            this.form.items = data.items ? data.items.map(item => ({
                product_id: item.product_id,
                quantity: Math.round(item.quantity),
                unit_cost: item.unit_cost,
                total_cost: item.total_cost,
            })) : [];
            this.editable = true;
            this.saveSuccess = false;
            this.showModal = true;
        },
        submit() {
            this.form.total_amount = this.totalAmount;

            if (this.editable) {
                this.form.put(`/purchase-orders/${this.form.id}`, {
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
                this.form.post('/purchase-orders', {
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
            }
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
        addItem() {
            this.form.items.push({
                product_id: null,
                quantity: 0,
                unit_cost: 0,
                total_cost: 0,
            });
        },
        removeItem(index) {
            this.form.items.splice(index, 1);
        },
        calculateTotal(item) {
            item.total_cost = item.quantity * item.unit_cost;
        },
    },
};
</script>
