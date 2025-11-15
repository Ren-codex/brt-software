<template>
    <b-modal v-model="showModal" header-class="p-3 bg-light" title="Delete File" class="v-modal-custom" modal-class="zoomIn" centered no-close-on-backdrop>
        <div class="p-1" v-if="selected">
            <div class="alert alert-danger alert-dismissible alert-additional fade show mb-xl-0 material-shadow" role="alert">
                <div class="alert-body">
                    <div class="d-flex mt-n1 mb-n2">
                        <div class="flex-shrink-0 me-2">
                            <i class="ri-alert-line fs-14 align-middle"></i>
                        </div>
                        <div class="flex-grow-1 mt-1">
                            <h5 class="fs-11 alert-heading">Are you sure you want to remove <b class="text-uppercase text-decoration-line-through">{{ selected.name }}</b> role?</h5>
                        </div>
                    </div>
                </div>
                <div class="alert-content">
                    <p class="mb-0 fs-10">Removing this role will revoke the user’s access and permissions associated with the <strong class="text-decoration-underline">{{ selected.name }}</strong> module.</p>
                </div>
            </div>
        </div>
        <template v-slot:footer>
            <b-button @click="hide()" variant="light" block>Cancel</b-button>
            <b-button @click="submit('ok')" variant="danger" :disabled="form.processing" block>Remove</b-button>
        </template>
    </b-modal>
</template>
<script>
import { useForm } from '@inertiajs/vue3';
import InputLabel from '@/Shared/Components/Forms/InputLabel.vue';
import TextInput from '@/Shared/Components/Forms/TextInput.vue';
export default {
    components: {InputLabel, TextInput },
    data(){
        return {
            form: useForm({
                code: null,
                type: 'remove',
                option: 'role'
            }),
            selected: null,
            showModal: false
        }
    },
    methods: { 
        show(selected){
            this.form.reset();
            this.form.code = selected.code;
            this.selected = selected;
            this.showModal = true;
        },
        submit(){
            this.form.put('/users/update',{
                preserveScroll: true,
                onSuccess: (response) => {
                    this.$emit('update',this.$page.props.flash.data.data);
                    this.form.clearErrors();
                    this.form.reset();
                    this.hide();
                },
            });
        },
        hide(){
            this.showModal = false;
        }
    }
}
</script>