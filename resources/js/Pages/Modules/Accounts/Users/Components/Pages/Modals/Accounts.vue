<template>
    <b-modal v-model="showModal" style="--vz-modal-width: 800px;" header-class="p-3 bg-light" title="Update User Background" class="v-modal-custom" modal-class="zoomIn" centered no-close-on-backdrop>
        <form class="customform">
            <BRow class="mt-2">
                <div class="col-md-6" v-for="(list, index) in form.accounts" v-bind:key="index">
                    <InputLabel :value="list.name" :message="form.errors.school"/>
                    <TextInput v-model="list.number" type="text" class="form-control" placeholder="Please enter school" @input="handleInput('school')" :light="true" />
                </div>
            </BRow>
        </form>
        <template v-slot:footer>
            <b-button @click="hide()" variant="light" block>Cancel</b-button>
            <b-button @click="submit('ok')" variant="primary" :disabled="form.processing" block>Submit</b-button>
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
            currentUrl: window.location.origin,
            form: useForm({
                id: null,
                accounts: [],
                option: 'account'
            }),
            showModal: false
        }
    },
    methods: { 
        show(id,data){
            console.log(data);
            this.form.reset();
            this.form.id = id;
            this.form.accounts = data;
            this.showModal = true;
        },
        submit(){
            this.form.put('/employees/update',{
                preserveScroll: true,
                onSuccess: (response) => {
                    this.form.reset();
                    this.hide();
                },
            });
        },
        handleInput(field) {
            this.form.errors[field] = false;
        },
        hide(){
            this.showModal = false;
        }
    }
}
</script>