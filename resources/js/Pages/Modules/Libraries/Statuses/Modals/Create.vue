<template>
    <b-modal v-model="showModal" size="md" header-class="p-3 bg-light" :title="(editable) ? 'Update Status' : 'Add Status'" class="v-modal-custom" modal-class="zoomIn" centered no-close-on-backdrop>
        <form class="customform">
            <BRow>
                <BCol lg="12">
                    <BRow class="g-3 mt-n1">

                        <BCol lg="12" class="mt-n1 mb-3">
                            <InputLabel value="Name" :message="form.errors.name"/>
                            <TextInput v-model="form.name" type="text" class="form-control" placeholder="Please enter status name" @input="handleInput('name')" />
                        </BCol>

                        <BCol lg="12" class="mt-n1 mb-3">
                            <InputLabel value="Description" :message="form.errors.description"/>
                            <textarea v-model="form.description" type="text" class="form-control" placeholder="Please enter description" @input="handleInput('description')" />
                        </BCol>

                        <BCol lg="12" class="mt-n1 mb-3">
                            <InputLabel value="Text Color" :message="form.errors.text_color"/>
                            <TextInput v-model="form.text_color" type="text" class="form-control" placeholder="Please enter text color" @input="handleInput('text_color')" />
                        </BCol>

                        <BCol lg="12" class="mt-n1 mb-3">
                            <InputLabel value="Background Color" :message="form.errors.bg_color"/>
                            <TextInput v-model="form.bg_color" type="text" class="form-control" placeholder="Please enter bg color" @input="handleInput('bg_color')" />
                        </BCol>

                    </BRow>
                </BCol>
            </BRow>
        </form>
        <template v-slot:footer>
            <b-button @click="hide()" variant="light" block>Cancel</b-button>
            <b-button
                @click="submit('ok')"
                variant="primary"
                :disabled="form.processing || passwordMismatch"
                block
            >
                Submit
            </b-button>
        </template>
    </b-modal>
</template>
<script>
import { useForm } from '@inertiajs/vue3';
import Multiselect from "@vueform/multiselect";
import InputLabel from '@/Shared/Components/Forms/InputLabel.vue';
import TextInput from '@/Shared/Components/Forms/TextInput.vue';
export default {
    components: {InputLabel, TextInput, Multiselect },
    props: ['dropdowns'],
    data(){
        return {
            currentUrl: window.location.origin,
            form: useForm({
                id: null,
                name: null,
                description: null,
                text_color: null,
                bg_color: null,
                option: 'lists'
            }),
            togglePassword: false,
            toggleConfirm: false,
            passwordMismatch: false,
            showModal: false,
            editable: false,

        }
    },
    methods: { 
        show(){
            this.form.reset();
            this.editable = false;
            this.showModal = true;
        },
        edit(data, index){
            console.log(data);
            this.form.id = data.id;
            this.form.name = data.name;
            this.form.description = data.description;
            this.form.text_color = data.text_color;
            this.form.bg_color = data.bg_color;
            this.editable = true;
            this.showModal = true;
        },


        submit(){
            if(this.editable){
                this.form.put(`/libraries/statuses/${this.form.id}`,{
                    preserveScroll: true,
                    onSuccess: (response) => {
                        this.$emit('add', true);
                        this.form.reset();
                        this.hide();
                    },
                });
            }else{
                console.log(this.form);
                this.form.post('/libraries/statuses',{
                    preserveScroll: true,
                    onSuccess: (response) => {
                        this.$emit('add', true);
                        this.form.reset();
                        this.hide();
                    },
                });
            }
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