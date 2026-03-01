<template>
    <b-modal v-model="showModal" size="md" header-class="p-3 bg-light" :title="isActive ? 'Deactivate User' : 'Activate User'" class="v-modal-custom" modal-class="zoomIn" centered no-close-on-backdrop hide-footer>
        <form @submit.prevent="submitForm">
            <BRow>
                <BCol lg="12" class="mt-n1 mb-3">
                    <div class="alert alert-warning alert-dismissible alert-additional fade show mb-xl-0 material-shadow" role="alert">
                        <div class="alert-body">
                            <div class="d-flex mt-n1 mb-n2">
                                <div class="flex-shrink-0 me-2">
                                    <i class="ri-alert-line fs-14 align-middle"></i>
                                </div>
                                <div class="flex-grow-1 mt-1">
                                    <h5 class="fs-11 text-primary">Are you sure you want to {{ isActive ? 'deactivate' : 'activate' }} <b class="text-uppercase">{{ user?.name }}</b>?</h5>
                                </div>
                            </div>
                        </div>
                        <div class="bg-warning p-2 rounded alert-content">
                            <p class="mb-0 fs-10">{{ isActive ? 'Deactivating this user will prevent them from logging in.' : 'Activating this user will allow them to log in again.' }}</p>
                        </div>
                    </div>
                </BCol>
            </BRow>
            <div class="d-flex justify-content-end gap-2 mt-3">
                <BButton variant="light" @click="closeModal">Cancel</BButton>
                <BButton variant="primary" @click="submit()" :disabled="processing">
                    <i class="ri-loader-4-line spinner" v-if="processing"></i>
                    {{ processing ? (isActive ? 'Deactivating...' : 'Activating...') : (isActive ? 'Deactivate' : 'Activate') }}
                </BButton>
            </div>
        </form>
    </b-modal>
</template>
<script>
import { useForm } from '@inertiajs/vue3';

export default {
    data() {
        return {
            showModal: false,
            user: null,
            isActive: true,
            form: useForm({
                id: null,
                is_active: null,
                option: 'status'
            }),
            processing: false
        }
    },
    methods: {
        show(data) {
            this.user = data;
            this.isActive = data.is_active;
            this.form.id = data.id;
            this.form.is_active = !data.is_active;
            this.showModal = true;
            this.form.errors = {};
        },
        closeModal() {
            this.showModal = false;
        },
        submit() {
            this.processing = true;
            this.form.option = 'deactivate';
            this.form.put('/users/' + this.form.id,{
                preserveScroll: true,
                onSuccess: (response) => {
                    this.$emit('deactivate', true);
                    this.form.reset();
                    this.closeModal();
                    this.processing = false;
                },
                OnError: () => {
                    this.processing = false;
                }
            });
        }
    }
}
</script>
