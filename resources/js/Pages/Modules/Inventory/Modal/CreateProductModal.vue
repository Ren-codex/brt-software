<template>
  <Teleport to="body">
    <div
        v-if="showModal"
        class="modal-overlay active"
        @click.self="hide"
    >
        <div class="modal-container" @click.stop>
            <div class="modal-header">
                <h2>{{ editable ? 'Update Product' : 'Product Information' }}</h2>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="success-alert" v-if="saveSuccess">
                    <i class="ri-checkbox-circle-fill"></i>
                    <span>Your information has been saved successfully!</span>
                </div>
                <div class="error-alert" v-if="form.errors.duplicate">
                    <i class="ri-close-circle-fill"></i>
                    <span>{{ form.errors.duplicate }}</span>
                </div>
                <form @submit.prevent="submit">
                    <div class="form-group">
                        <label for="code" class="form-label">Product Code</label>
                        <div class="input-wrapper">
                            <i class="ri-barcode-line input-icon"></i>
                            <input
                                type="text"
                                id="code"
                                v-model="form.code"
                                class="form-control"
                                :class="{ 'input-error': form.errors.code }"
                                placeholder="Enter product code"
                                @input="form.code = form.code.toUpperCase(); handleInput('code')"
                            >
                        </div>
                        <span class="error-message" v-if="form.errors.code">{{ form.errors.code }}</span>
                    </div>

                    <div class="form-group">
                        <label for="brand_id" class="form-label">Brand</label>
                        <div class="input-wrapper">
                            <i class="ri-price-tag-3-line input-icon"></i>
                            <!-- <select
                                v-model="form.brand_id"
                                class="form-control"
                                :class="{ 'input-error': form.errors.brand_id }"
                                @change="handleInput('brand_id')"
                            >
                                <option value="" disabled>Select Brand</option>
                                <option v-for="unit in dropdowns.brands" :value="unit.value" :key="unit.value">{{ unit.name }}</option>

                            </select> -->


                              <b-form-select
                                class="form-control"
                                v-model="form.brand_id"
                                :options="dropdowns.brands"
                                :class="{ 'input-error': form.errors.brand_id }"
                                text-field="name"
                                value-field="value"
                                >
                                 <template #first>
                                    <b-form-select-option :value="null" disabled  >Select Brand</b-form-select-option>
                                </template>
                                </b-form-select>    
                     
                        </div>
                        <span class="error-message" v-if="form.errors.brand_id">{{ form.errors.brand_id }}</span>
                    </div>

                    <div class="form-group">
                        <label for="pack_size" class="form-label">Pack Size</label>
                        <div class="input-wrapper">
                            <i class="ri-inbox-unarchive-line input-icon"></i>
                            <input
                                type="number"
                                id="pack_size"
                                v-model="form.pack_size"
                                class="form-control"
                                :class="{ 'input-error': form.errors.pack_size }"
                                placeholder="Enter product pack size"
                                @input="handleInput('pack_size')"
                            >
                        </div>
                        <span class="error-message" v-if="form.errors.pack_size">{{ form.errors.pack_size }}</span>
                    </div>

                    <div class="form-group">
                        <label for="minimum_stock" class="form-label">Minimum Stock <span class="text-muted small">(low-stock alert threshold)</span></label>
                        <div class="input-wrapper">
                            <i class="ri-alarm-warning-line input-icon"></i>
                            <input
                                type="number"
                                id="minimum_stock"
                                v-model="form.minimum_stock"
                                class="form-control"
                                :class="{ 'input-error': form.errors.minimum_stock }"
                                placeholder="0"
                                min="0"
                                @input="handleInput('minimum_stock')"
                            >
                        </div>
                        <span class="error-message" v-if="form.errors.minimum_stock">{{ form.errors.minimum_stock }}</span>
                    </div>

                    <div class="form-group">
                        <label for="unit_id" class="form-label">Unit</label>
                        <div class="input-wrapper">
                            <i class="ri-ruler-line input-icon"></i>
                            <select
                                v-model="form.unit_id"
                                class="form-control"
                                :class="{ 'input-error': form.errors.unit_id }"
                                @change="handleInput('unit_id')"
                            >
                                <option value="" disabled>Select Unit</option>
                                <option v-for="unit in dropdowns.units" :value="unit.value" :key="unit.value">{{ unit.name }}</option>
                            </select>
                        </div>
                        <span class="error-message" v-if="form.errors.unit_id">{{ form.errors.unit_id }}</span>
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" @click="hide">
                    <i class="ri-close-line"></i>
                    Cancel
                </button>
                <button type="button" class="btn btn-save" :disabled="form.processing" @click="submit">
                    <i class="ri-save-line" v-if="!form.processing"></i>
                    <i class="ri-loader-4-line spinner" v-else></i>
                    {{ form.processing ? 'Saving...' : 'Save' }}
                </button>
            </div>
        </div>
    </div>
  </Teleport>
</template>

<script>
import { useForm } from '@inertiajs/vue3';
import Multiselect from "@vueform/multiselect";
import InputLabel from '@/Shared/Components/Forms/InputLabel.vue';
import TextInput from '@/Shared/Components/Forms/TextInput.vue';

export default {
    components: { InputLabel, TextInput, Multiselect },
    props: ['dropdowns'],
    data() {
        return {
            form: useForm({
                id: null,
                code: '',
                pack_size: null,
                unit_id: null,
                brand_id: null,
                minimum_stock: 0,
            }),
            showModal: false,
            editable: false,
            saveSuccess: false,
        }
    },
    mounted() {
        document.addEventListener('keydown', this._onEscape);
    },
    beforeUnmount() {
        document.removeEventListener('keydown', this._onEscape);
    },
    methods: {
        _onEscape(e) {
            if (e.key === 'Escape' && this.showModal) this.hide();
        },
        show() {
            this.form.reset();
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = true;
        },
        edit(data) {
            this.form.id = data.id;
            this.form.code = data.code;
            this.form.pack_size = data.pack_size;
            this.form.unit_id = data.unit.id;
            this.form.brand_id = data.brand.id;
            this.form.minimum_stock = data.minimum_stock ?? 0;
            this.editable = true;
            this.saveSuccess = false;
            this.showModal = true;
        },
        submit() {
            if (this.editable) {
                this.form.put(`/libraries/products/${this.form.id}`, {
                    preserveScroll: true,
                    onSuccess: (response) => {
                        this.saveSuccess = true;
                        this.form.reset();
                        setTimeout(() => {
                            this.$emit('add');
                            this.hide();
                        }, 1500);
                    },
                });
            } else {
                this.form.post('/libraries/products', {
                    preserveScroll: true,
                    onSuccess: (response) => {
                        this.saveSuccess = true;
                        this.form.reset();
                        setTimeout(() => {
                            this.$emit('add', true);
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
        }
    }
}
</script>
