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
                                <b-form-select
                                class="form-control"
                                v-model="form.type"
                                :options="['Sales Return' , 'Sales Allowance']"
                                :class="{ 'input-error': form.errors.type }"
                                text-field="name"
                                value-field="value"
                                >
                                 <template #first>
                                    <b-form-select-option :value="null" disabled  >Select Type</b-form-select-option>
                                </template>
                                </b-form-select>    
                            </div>
                            <span class="error-message" v-if="form.errors.brand_id">{{ form.errors.type }}</span>
                        </div>

                    </div>
                    
                    <div class="form-row">
                      <div class="form-group">
                            <label for="reason" class="form-label">Reason</label>
                            <b-form-textarea
                                id="textarea-rows"
                                placeholder="Enter the reason"
                                rows="8"
                                v-model="form.reason"
                                :class="{ 'input-error': form.errors.reason }"
                            ></b-form-textarea>
                            <span class="error-message" v-if="form.errors.reason">{{ form.errors.reason }}</span>
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
                        <button type="submit" class="btn btn-save" :disabled="form.processing || (!form.type || !form.reason )">
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
            }),
            showModal: false,
            saveSuccess: false,
        }
    },
    methods: { 
        show(id) {
            this.form.reset();
            this.form.id = id;
            this.showModal = true;
        },

        submit() {
            this.form.post(`/sales-orders/adjustment/${this.form.id}`, {
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
        },



        
    }
}
</script>


