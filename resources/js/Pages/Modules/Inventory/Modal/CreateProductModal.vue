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
                        <label for="code" class="form-label">
                            Product Code
                            <span class="code-auto-badge" v-if="!editable">auto-generated</span>
                        </label>
                        <div class="input-wrapper">
                            <i class="ri-barcode-line input-icon"></i>
                            <input
                                type="text"
                                id="code"
                                v-model="form.code"
                                class="form-control"
                                :class="{ 'input-error': form.errors.code }"
                                placeholder="Fill Brand, Packaging & Weight to generate"
                                @input="form.code = form.code.toUpperCase(); codeManuallyEdited = true; handleInput('code')"
                            >
                            <button
                                v-if="!editable && getCodePrefix()"
                                type="button"
                                class="code-regen-btn"
                                @click="fetchNextCode()"
                                title="Re-generate code"
                            ><i class="ri-refresh-line"></i></button>
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
                        <label for="packaging_id" class="form-label">Packaging</label>
                        <div class="input-wrapper">
                            <i class="ri-archive-2-line input-icon"></i>
                            <select
                                v-model="form.packaging_id"
                                class="form-control"
                                :class="{ 'input-error': form.errors.packaging_id }"
                                @change="handleInput('packaging_id')"
                            >
                                <option :value="null" disabled>Select Packaging</option>
                                <option v-for="p in dropdowns.packagings" :value="p.value" :key="p.value">{{ p.name }}</option>
                            </select>
                        </div>
                        <span class="error-message" v-if="form.errors.packaging_id">{{ form.errors.packaging_id }}</span>
                    </div>

                    <div class="form-group">
                        <label for="weight" class="form-label">Weight</label>
                        <div class="input-wrapper">
                            <i class="ri-inbox-unarchive-line input-icon"></i>
                            <input
                                type="number"
                                id="weight"
                                v-model="form.weight"
                                class="form-control"
                                :class="{ 'input-error': form.errors.weight }"
                                placeholder="Enter product weight"
                                @input="handleInput('weight')"
                            >
                        </div>
                        <span class="error-message" v-if="form.errors.weight">{{ form.errors.weight }}</span>
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
                id:           null,
                code:         '',
                weight:    null,
                unit_id:      null,
                brand_id:     null,
                packaging_id: null,
                minimum_stock: 0,
            }),
            showModal:           false,
            editable:            false,
            saveSuccess:         false,
            codeManuallyEdited:  false,
        }
    },
    watch: {
        'form.brand_id'()     { this.tryAutoGenerateCode(); },
        'form.packaging_id'() { this.tryAutoGenerateCode(); },
        'form.weight'()       { this.tryAutoGenerateCode(); },
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
        getCodePrefix() {
            const brand     = this.dropdowns?.brands?.find(b => b.value === this.form.brand_id);
            const packaging = this.dropdowns?.packagings?.find(p => p.value === this.form.packaging_id);
            const weight    = this.form.weight;
            if (!brand || !packaging || !weight) return null;
            const initials = str => str.trim().split(/\s+/).map(w => w[0].toUpperCase()).join('');
            return initials(brand.name) + initials(packaging.name) + weight;
        },
        tryAutoGenerateCode() {
            if (this.editable || this.codeManuallyEdited) return;
            const prefix = this.getCodePrefix();
            if (!prefix) return;
            this.fetchNextCode(prefix);
        },
        fetchNextCode(prefix) {
            prefix = prefix ?? this.getCodePrefix();
            if (!prefix) return;
            axios.get('/libraries/products', {
                params: { option: 'next-code', prefix },
            }).then(r => {
                this.form.code          = r.data.code;
                this.codeManuallyEdited = false;
            });
        },
        show() {
            this.form.reset();
            this.form.unit_id          = this.dropdowns?.units?.find(u => u.name.toLowerCase() === 'kg')?.value ?? null;
            this.codeManuallyEdited    = false;
            this.editable              = false;
            this.saveSuccess           = false;
            this.showModal             = true;
        },
        edit(data) {
            this.form.id           = data.id;
            this.form.code         = data.code;
            this.form.weight    = data.weight;
            this.form.unit_id      = data.unit.id;
            this.form.brand_id     = data.brand.id;
            this.form.packaging_id = data.packaging?.id ?? null;
            this.form.minimum_stock = data.minimum_stock ?? 0;
            this.editable = true;
            this.saveSuccess = false;
            this.showModal = true;
        },
        validate() {
            this.form.clearErrors();
            const errors = {};

            if (!this.form.code?.trim())
                errors.code = 'Product code is required.';
            else if (!/^[A-Z0-9]+$/.test(this.form.code))
                errors.code = 'Product code must contain only uppercase letters and numbers.';

            if (!this.form.brand_id)
                errors.brand_id = 'Brand is required.';

            if (!this.form.packaging_id)
                errors.packaging_id = 'Packaging is required.';

            if (!this.form.weight && this.form.weight !== 0)
                errors.weight = 'Weight is required.';
            else if (this.form.weight <= 0)
                errors.weight = 'Weight must be greater than 0.';

            if (!this.form.unit_id)
                errors.unit_id = 'Unit is required.';

            if (this.form.minimum_stock !== null && this.form.minimum_stock !== '' && this.form.minimum_stock < 0)
                errors.minimum_stock = 'Minimum stock cannot be negative.';

            if (Object.keys(errors).length) {
                this.form.setError(errors);
                return false;
            }
            return true;
        },
        submit() {
            if (!this.validate()) return;

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
            this.codeManuallyEdited = false;
            this.editable           = false;
            this.saveSuccess        = false;
            this.showModal          = false;
        }
    }
}
</script>

<style scoped>
.code-auto-badge {
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #3d8d7a;
    background: rgba(61,141,122,0.10);
    border-radius: 4px;
    padding: 1px 6px;
    margin-left: 6px;
    vertical-align: middle;
}
.code-regen-btn {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #3d8d7a;
    font-size: 1rem;
    cursor: pointer;
    padding: 2px 4px;
    line-height: 1;
}
.code-regen-btn:hover { color: #16322e; }
</style>
