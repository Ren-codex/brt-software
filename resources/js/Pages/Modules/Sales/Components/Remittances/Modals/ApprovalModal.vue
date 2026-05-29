<template>
    <div
        v-show="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"

    >
        <div class="modal-container modal-lg">
            <div class="modal-header">
                <h2>Remittance Approval</h2>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body px-5">
                <div class="success-alert" v-if="saveSuccess">
                    <i class="ri-checkbox-circle-fill"></i>
                    <span>Approval has been saved successfully!</span>
                </div>

                <form @submit.prevent="submit">
                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <!-- <label for="status" class="form-label">Status</label> -->
                            <div class="input-wrapper mt-2">
                                <div class="d-flex gap-3" style="font-size: 14px;">
                                    <label class="d-flex align-items-center gap-1">
                                        <input type="radio" v-model="form.status" value="Approve"> Approve
                                    </label>
                                    <label class="d-flex align-items-center gap-1">
                                        <input type="radio" v-model="form.status" value="Disapprove"> Disapprove
                                    </label>
                                </div>
                            </div>
                            <span class="error-message" v-if="form.errors.status">{{ form.errors.status }}</span>
                        </div>

                    </div>

                    <div class="form-row">
                      <div class="form-group form-group-half">
                            <label for="remarks" class="form-label">Remarks</label>
                              <div class="input-wrapper">
                                <textarea
                                    id="textarea-remarks"
                                    class="form-control"
                                    placeholder="Enter remarks"
                                    rows="8"
                                    v-model="form.remarks"
                                    :class="{ 'input-error': form.errors.remarks }"
                                ></textarea>
                            </div>
                            <span class="error-message" v-if="form.errors.remarks">{{ form.errors.remarks }}</span>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-cancel" @click="hide">
                            <i class="ri-close-line"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-save" :disabled="form.processing || !form.status">
                            <i class="ri-save-line" v-if="!form.processing"></i>
                            <i class="ri-loader-4-line spinner" v-else></i>
                            {{ form.processing ? 'Saving...' : 'Submit' }}
                        </button>
                    </div>


                </form>
            </div>
        </div>
    </div>
</template>

<script>
import { useForm } from '@inertiajs/vue3';

export default {
    props: ['item'],
    data() {
        return {
            form: useForm({
                status: 'Approve',
                remarks: null,
            }),
            showModal: false,
            saveSuccess: false,
        }
    },
    methods: {
        show() {
            this.form.reset();
            this.showModal = true;
        },

        submit() {
            this.form.post(`remittances/${this.item.id}/approve`, {
                onSuccess: () => {
                    this.saveSuccess = true;
                    setTimeout(() => {
                        this.$emit('reload');
                        this.hide();
                    }, 1500);
                }
            });
        },

        hide() {
            this.saveSuccess = false;
            this.showModal = false;
            this.$emit('hide');
            this.form.reset();
            this.form.clearErrors();
        },
    }
}
</script>
<style scoped>
.modal-overlay{z-index:50;}
.modal-container{overflow:hidden;width:100%;padding: 0px;}
.modal-body{padding:16px}
.form-actions .btn{min-width:140px}
</style>
