<template>
    <div
        v-if="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
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
                        <label for="brand_id" class="form-label">Brand</label>
                        <div class="input-wrapper">
                            <i class="ri-price-tag-3-line input-icon"></i>
                            <select
                                v-model="form.brand_id"
                                class="form-control"
                                :class="{ 'input-error': form.errors.brand_id }"
                                @change="handleInput('brand_id')"
                            >
                                <option value="" disabled>Select Brand</option>
                                <option v-for="unit in dropdowns.brands" :value="unit.value" :key="unit.value">{{ unit.name }}</option>
                            </select>
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
                pack_size: null,
                unit_id: null,
                brand_id: null,
            }),
            showModal: false,
            editable: false,
            saveSuccess: false,
        }
    },
    methods: {
        show() {
            this.form.reset();
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = true;
        },
        edit(data) {
            this.form.id = data.id;
            this.form.pack_size = data.pack_size;
            this.form.unit_id = data.unit.id;
            this.form.brand_id = data.brand.id;
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
                            this.$emit('add', true);
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
