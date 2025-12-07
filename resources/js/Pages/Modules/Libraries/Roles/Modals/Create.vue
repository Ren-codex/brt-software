<template>
    <div 
        v-if="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"
    >
        <div class="modal-container" @click.stop>
            <div class="modal-header">
                <h2>{{ editable ? 'Update Role' : 'Role Information' }}</h2>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body">
                <form @submit.prevent="submit">
                    <div class="form-group">
                        <label for="name" class="form-label">Role Name</label>
                        <div class="input-wrapper">
                            <i class="ri-user-settings-line input-icon"></i>
                            <input 
                                type="text" 
                                id="name" 
                                v-model="form.name" 
                                class="form-control"
                                :class="{ 'input-error': form.errors.name }"
                                placeholder="Enter role name"
                                @input="handleInput('name')"
                            >
                        </div>
                        <span class="error-message" v-if="form.errors.name">{{ form.errors.name }}</span>
                    </div>

                    <div class="form-group">
                        <label for="type" class="form-label">Type</label>
                        <div class="input-wrapper">
                            <i class="ri-shield-user-line input-icon"></i>
                            <input 
                                type="text" 
                                id="type" 
                                v-model="form.type" 
                                class="form-control"
                                :class="{ 'input-error': form.errors.type }"
                                placeholder="Enter type e.g Staff"
                                @input="handleInput('type')"
                            >
                        </div>
                        <span class="error-message" v-if="form.errors.type">{{ form.errors.type }}</span>
                    </div>

                    <div class="form-group">
                        <label for="definition" class="form-label">Definition</label>
                        <div class="input-wrapper">
                            <i class="ri-file-text-line input-icon"></i>
                            <input 
                                type="text" 
                                id="definition" 
                                v-model="form.definition" 
                                class="form-control"
                                :class="{ 'input-error': form.errors.definition }"
                                placeholder="Enter definition"
                                @input="handleInput('definition')"
                            >
                        </div>
                        <span class="error-message" v-if="form.errors.definition">{{ form.errors.definition }}</span>
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
import Swal from 'sweetalert2';

export default {
    components: { InputLabel, TextInput, ColorInput, Multiselect },
    props: ['dropdowns'],
    data() {
        return {
            currentUrl: window.location.origin,
            form: useForm({
                id: null,
                name: null,
                type: null,
                definition: null,
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
                type: null,
                definition: null,
                }).reset();
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = true;
        },
        edit(data, index) {
            this.form.id = data.id;
            this.form.name = data.name;
            this.form.type = data.type;
            this.form.definition = data.definition;
            this.editable = true;
            this.saveSuccess = false;
            this.showModal = true;
        },
         submit() {
            if (this.editable) {
                this.form.put(`/libraries/roles/${this.form.id}`, {
                    preserveScroll: true,
                    onSuccess: (response) => {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: 'Role updated successfully!',
                            timer: 1500,
                            showConfirmButton: false
                        });
                        this.$emit('add', true);
                        this.hide();
                    },
                });
            } else {
                this.form.post('/libraries/roles', {
                    preserveScroll: true,
                    onSuccess: (response) => {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: 'Role created successfully!',
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
