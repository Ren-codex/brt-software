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
                <form @submit.prevent="submit">
                    <div class="form-group">
                        <label for="name" class="form-label">Product Name</label>
                        <div class="input-wrapper">
                            <i class="ri-archive-line input-icon"></i>
                            <input
                                type="text"
                                id="name"
                                v-model="form.name"
                                class="form-control"
                                :class="{ 'input-error': form.errors.name }"
                                placeholder="Enter product name"
                                @input="handleInput('name')"
                            >
                        </div>
                        <span class="error-message" v-if="form.errors.name">{{ form.errors.name }}</span>
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
                name: null,
                unit_id: null,
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
            this.form.name = data.name;
            this.form.unit_id = data.unit_id;
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
