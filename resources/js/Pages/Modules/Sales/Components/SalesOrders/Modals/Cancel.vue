<template>
    <b-modal v-model="showModal" size="md" header-class="p-3  text-white" :title="'Cancel '+ title" class="v-modal-custom" modal-class="zoomIn" centered no-close-on-backdrop>
        <div class="text-center p-5">
             <h1> <i class="ri-alert-fill align-bottom text-warning" style="font-size: 60px;"></i></h1>
            <h5>Are you sure you want to cancel this {{title}}? </h5>
        </div>
    </b-modal>
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