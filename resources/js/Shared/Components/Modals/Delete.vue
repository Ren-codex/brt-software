<template>
    <div
        v-if="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"
    >
        <div class="modal-container" @click.stop>
            <div class="modal-header">
                <h2>Delete {{ title }}</h2>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center p-4">
                    <h1><i class="ri-alert-fill align-bottom text-warning" style="font-size: 60px;"></i></h1>
                    <h5>Are you sure you want to delete this {{ title }}?</h5>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-cancel" @click="hide">
                        <i class="ri-close-line"></i>
                        Cancel
                    </button>
                    <button type="button" class="btn btn-danger" @click="submit()">
                        <i class="ri-delete-bin-line"></i>
                        Yes
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
<script>

import { useForm } from '@inertiajs/vue3';

export default {
    components: { },
    props: [],
    data(){
        return {
            currentUrl: window.location.origin,
            form: useForm({
                id: null,
            }),
            title: null,
            table: null,
            showModal: false,
        }
    },
    methods: { 
        show(id, title, route){
            this.showModal = true;
            this.form.id = id;
            this.title = title;
            this.route = route;
        },

        submit(){
            this.form.delete(`${this.route}/${this.form.id}`,{
                preserveScroll: true,
                onSuccess: (response) => {
                    this.$emit('delete', true);
                    this.form.reset();
                    this.hide();
                },
            });

        },
        handleInput(field) {
            this.form.errors[field] = false;
        },
        hide(){
            this.editable = false;
            this.showModal = false;
        },

   
    }
}
</script>