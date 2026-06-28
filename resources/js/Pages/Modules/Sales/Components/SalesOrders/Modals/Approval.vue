<template>
    <div 
        v-show="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"
        
    >
        <div class="modal-container modal-xl" @click.stop>
                <div class="modal-header">
                    <h2>{{ 'Approve Order' }}</h2>
                    <button class="close-btn" @click="hide">
                        <i class="ri-close-line"></i>
                    </button>
                </div>
            <div class="text-center p-5">
                <h1> <i class="ri-check-fill align-bottom text-success" style="font-size: 60px;"></i></h1>
                <h5>Are you sure you want to approve this {{title}}? </h5>

            </div>
            <div class="modal-footer m-3">
                <button class="btn btn-secondary me-2" @click="hide" :disabled="form.processing">Close</button>
                <button class="btn btn-primary" @click="submit" :disabled="form.processing">
                    <i class="ri-loader-4-line spinner me-1" v-if="form.processing"></i>
                    <i class="ri-check-line me-1" v-else></i>
                    {{ form.processing ? 'Approving...' : 'Yes, Approve' }}
                </button>
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
                action: 'approve',
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
            this.form.put(`${this.route}/${this.form.id}`,{
                preserveScroll: true,
                onSuccess: (response) => {
                    this.$emit('approve', true);
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

<style scoped>
.modal-container {
    max-height: calc(100vh - 2rem);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.modal-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
}

.modal-footer {
    flex-shrink: 0;
    border-top: 1px solid #e9ecef;
}
</style>