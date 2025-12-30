<template>
    <div 
        v-show="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"
        
    >
        <div class="modal-container modal-xl" @click.stop>
                <div class="modal-header">
                    <h2>{{ 'Cancel Order' }}</h2>
                    <button class="close-btn" @click="hide">
                        <i class="ri-close-line"></i>
                    </button>
                </div>
            <div class="text-center p-5">
                <h1> <i class="ri-alert-fill align-bottom text-warning" style="font-size: 60px;"></i></h1>
                <h5>Are you sure you want to cancel this {{title}}? </h5>
            </div>
            <div class="modal-footer m-3">
                <button class="btn btn-secondary me-2" @click="hide">Close</button>
                <button class="btn btn-danger" @click="submit">Yes, Cancel</button>
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
                    this.$emit('cancel', true);
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