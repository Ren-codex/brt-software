<template>
    <b-modal v-model="showModal" header-class="p-3 bg-light" title="Update Salary" class="v-modal-custom" modal-class="zoomIn" centered no-close-on-backdrop>
        <form class="customform">
            <BRow class="mt-2">
                <div class="col-md-6" >
                    <InputLabel value="Old Rate" :message="form.errors.school"/>
                    <TextInput :value="old" type="text" class="form-control" placeholder="Please enter school" @input="handleInput('school')" :light="true" readonly/>
                </div>
                <div class="col-md-6" >
                    <InputLabel value="New Rate" :message="form.errors.school"/>
                    <Amount @amount="amount" ref="testing" :readonly="false" @input="handleInput('amount')"/>
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
import Amount from '@/Shared/Components/Forms/Amount.vue';
export default {
    components: {InputLabel, TextInput, Amount },
    data(){
        return {
            currentUrl: window.location.origin,
            form: useForm({
                id: null,
                salary: null,
                option: 'salary'
            }),
            old: null,
            showModal: false
        }
    },
    methods: { 
        amount(val){
            this.form.salary = val;
        },
        show(id,data){
            this.form.reset();
            this.form.id = id;
            this.old = data;
            this.form.salary = data;
            this.showModal = true;
        },
        submit(){
            this.form.put('/employees/update',{
                preserveScroll: true,
                onSuccess: (response) => {
                    this.$emit('update',this.$page.props.flash.data.salary);
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