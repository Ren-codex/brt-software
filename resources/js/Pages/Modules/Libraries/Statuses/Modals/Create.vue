<template>
    <div 
        v-if="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"
    >
        <div class="modal-container" @click.stop>
            <div class="modal-header">
                <h2>{{ editable ? 'Update Status' : 'Status Information' }}</h2>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body">
                <form @submit.prevent="submit">
                    <div class="form-group">
                        <label for="name" class="form-label">Status Name</label>
                        <div class="input-wrapper">
                            <i class="ri-bookmark-line input-icon"></i>
                            <input 
                                type="text" 
                                id="name" 
                                v-model="form.name" 
                                class="form-control"
                                :class="{ 'input-error': form.errors.name }"
                                placeholder="Enter status name"
                                @input="handleInput('name')"
                            >
                        </div>
                        <span class="error-message" v-if="form.errors.name">{{ form.errors.name }}</span>
                    </div>

                    <div class="form-group">
                        <label for="description" class="form-label">Description</label>
                        <div class="input-wrapper">
                            <i class="ri-file-text-line input-icon textarea-icon"></i>
                            <textarea 
                                id="description" 
                                v-model="form.description" 
                                class="form-control textarea-control"
                                :class="{ 'input-error': form.errors.description }"
                                placeholder="Enter description"
                                rows="3"
                                @input="handleInput('description')"
                            ></textarea>
                        </div>
                        <span class="error-message" v-if="form.errors.description">{{ form.errors.description }}</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label for="text_color" class="form-label">Text Color</label>
                            <div class="input-wrapper">
                                <i class="ri-palette-line input-icon"></i>
                                <ColorInput 
                                    v-model="form.text_color" 
                                    class="form-control color-input"
                                    :class="{ 'input-error': form.errors.text_color }"
                                    @input="handleInput('text_color')" 
                                />
                            </div>
                            <span class="error-message" v-if="form.errors.text_color">{{ form.errors.text_color }}</span>
                        </div>

                        <div class="form-group form-group-half">
                            <label for="bg_color" class="form-label">Background Color</label>
                            <div class="input-wrapper">
                                <i class="ri-paint-fill input-icon"></i>
                                <ColorInput 
                                    v-model="form.bg_color" 
                                    class="form-control color-input"
                                    :class="{ 'input-error': form.errors.bg_color }"
                                    @input="handleInput('bg_color')" 
                                />
                            </div>
                            <span class="error-message" v-if="form.errors.bg_color">{{ form.errors.bg_color }}</span>
                        </div>
                    </div>
                    
                    <div class="success-alert" v-if="saveSuccess">
                        <i class="ri-checkbox-circle-fill"></i>
                        <span>Your information has been saved successfully!</span>
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
import ColorInput from '@/Shared/Components/Forms/ColorInput.vue';

export default {
    components: { InputLabel, TextInput, ColorInput, Multiselect },
    props: ['dropdowns'],
    data() {
        return {
            currentUrl: window.location.origin,
            form: useForm({
                id: null,
                name: null,
                description: null,
                text_color: null,
                bg_color: null,
                option: 'lists'
            }),
            togglePassword: false,
            toggleConfirm: false,
            passwordMismatch: false,
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
        edit(data, index) {
            console.log(data);
            this.form.id = data.id;
            this.form.name = data.name;
            this.form.description = data.description;
            this.form.text_color = data.text_color;
            this.form.bg_color = data.bg_color;
            this.editable = true;
            this.saveSuccess = false;
            this.showModal = true;
        },
        submit() {
            if (this.editable) {
                this.form.put(`/libraries/statuses/${this.form.id}`, {
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
                console.log(this.form);
                this.form.post('/libraries/statuses', {
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
        }
    }
}
</script>
