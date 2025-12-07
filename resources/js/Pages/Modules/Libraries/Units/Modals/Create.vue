<template>
    <div 
        v-if="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"
    >
        <div class="modal-container" @click.stop>
            <div class="modal-header">
                <h2>{{ editable ? 'Update Unit' : 'Unit Information' }}</h2>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body">
                <form @submit.prevent="submit">
                    <div class="form-group">
                        <label for="name" class="form-label">Unit Name</label>
                        <div class="input-wrapper">
                            <i class="ri-ruler-line input-icon"></i>
                            <input 
                                type="text" 
                                id="name" 
                                v-model="form.name" 
                                class="form-control"
                                :class="{ 'input-error': form.errors.name }"
                                placeholder="Enter unit name"
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
                                placeholder="Enter unit description"
                                rows="3"
                                @input="handleInput('description')"
                            ></textarea>
                        </div>
                        <span class="error-message" v-if="form.errors.description">{{ form.errors.description }}</span>
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
import TextArea from '@/Shared/Components/Forms/Textarea.vue';
import Swal from 'sweetalert2';

export default {
    components: { InputLabel, TextInput, TextArea, Multiselect },
    props: ['dropdowns'],
    data() {
        return {
            currentUrl: window.location.origin,
            form: useForm({
                id: null,
                name: null,
                description: null,
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
            this.form.defaults({
                id: null,
                name: null,
                description: null,
                }).reset();
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = true;
        },
        edit(data, index) {
            console.log(data);
            this.form.id = data.id;
            this.form.name = data.name;
            this.form.description = data.description;
            this.editable = true;
            this.saveSuccess = false;
            this.showModal = true;
        },
        submit() {
            if (this.editable) {
                this.form.put(`/libraries/units/${this.form.id}`, {
                    preserveScroll: true,
                    onSuccess: (response) => {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: 'Unit updated successfully!',
                            timer: 1500,
                            showConfirmButton: false
                        });
                        this.$emit('add', true);
                        this.hide();
                    },
                });
            } else {
                this.form.post('/libraries/units', {
                    preserveScroll: true,
                    onSuccess: (response) => {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: 'Unit created successfully!',
                            timer: 1500,
                            showConfirmButton: false
                        });
                        this.$emit('add', true);
                        this.hide();
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
