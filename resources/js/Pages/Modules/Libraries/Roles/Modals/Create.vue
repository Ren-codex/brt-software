<template>
    <b-modal v-model="showModal" size="md" header-class="p-3 bg-light" :title="(editable) ? 'Update Role' : 'Add Role'" class="v-modal-custom" modal-class="zoomIn" centered no-close-on-backdrop>
        <form class="customform">
            <BRow>
                <BCol lg="12">
                    <BRow class="g-3 mt-n1">

                        <BCol lg="12" class="mt-n1 mb-3">
                            <InputLabel value="Name" :message="form.errors.name"/>
                            <TextInput v-model="form.name" type="text" class="form-control" placeholder="Please enter role name" @input="handleInput('name')" />
                        </BCol>

                        <BCol lg="12" class="mt-n1 mb-3">
                            <InputLabel value="Type" :message="form.errors.type"/>
                            <TextInput v-model="form.type" type="text" class="form-control" placeholder="Please enter type e.g Staff" @input="handleInput('type')" />
                        </BCol>

                        <BCol lg="12" class="mt-n1 mb-3">
                            <InputLabel value="Definition" :message="form.errors.type"/>
                            <TextInput v-model="form.definition" type="text" class="form-control" placeholder="Please enter definition" @input="handleInput('definition')" />
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
import ColorInput from '@/Shared/Components/Forms/ColorInput.vue';
export default {
    components: {InputLabel, TextInput, ColorInput, Multiselect },
    props: ['dropdowns'],
    data(){
        return {
            currentUrl: window.location.origin,
            form: useForm({
                id: null,
                name: null,
                type: null,
                definition: null,
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
            this.form.id = data.id;
            this.form.name = data.name;
            this.form.type = data.type;
            this.form.definition = data.definition;
            this.editable = true;
            this.showModal = true;
        },


        submit(){
            if(this.editable){
                this.form.put(`/libraries/roles/${this.form.id}`,{
                    preserveScroll: true,
                    onSuccess: (response) => {
                        this.$emit('add', true);
                        this.form.reset();
                        this.hide();
                    },
                });
            }else{
                console.log(this.form);
                this.form.post('/libraries/roles',{
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