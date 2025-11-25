<template>
    <b-modal v-model="showModal" size="lg" header-class="p-3 bg-light" :title="(editable) ? 'Update Supplier' : 'Add Supplier'" class="v-modal-custom" modal-class="zoomIn" centered no-close-on-backdrop>
        <form class="customform">
            <BRow>
                <BCol lg="12">
                    <BRow class="g-3 mt-n1">

                        <BCol lg="6" class="mt-n1 mb-3">
                            <InputLabel value="Name" :message="form.errors.name"/>
                            <TextInput v-model="form.name" type="text" class="form-control" placeholder="Please enter supplier name" @input="handleInput('name')" :light="true" />
                        </BCol>

                        <BCol lg="6" class="mt-n1 mb-3">
                            <InputLabel value="Address" :message="form.errors.address"/>
                            <TextInput v-model="form.address" type="text" class="form-control" placeholder="Please enter address e.g. Sinunuc, Zamboanga City" @input="handleInput('address')" :light="true" />
                        </BCol>

                        <BCol lg="6" class="mt-n1 mb-3">
                            <InputLabel value="Contact Person" :message="form.errors.contact_person"/>
                            <TextInput v-model="form.contact_person" type="text" class="form-control" placeholder="Please enter contact person e.g. Mr. Mark Reyes" @input="handleInput('contact_person')" :light="true" />
                        </BCol>

                        <BCol lg="6" class="mt-n1 mb-3">
                            <InputLabel value="Contact Number" :message="form.errors.contact_number"/>
                            <TextInput v-model="form.contact_number" type="text" class="form-control" placeholder="Please enter mobile e.g 09xxxxxxxxx" @input="handleInput('contact_number')" :light="true" />
                        </BCol>

                        <BCol lg="6" class="mt-n1 mb-3">
                            <InputLabel value="Email" :message="form.errors.email"/>
                            <TextInput v-model="form.email" type="email" class="form-control" placeholder="Please enter email" @input="handleInput('email')" :light="true" />
                        </BCol>

                        <BCol lg="6" class="mt-n1 mb-3">
                            <InputLabel value="TIN" :message="form.errors.tin"/>
                            <TextInput v-model="form.tin" type="username" class="form-control" placeholder="Please enter tin" @input="handleInput('tin')" :light="true" />
                        </BCol>

                        <BCol lg="6" class="mt-n1 mb-3">
                            <InputLabel value="Is Active?" />
                            <b-form-checkbox
                                switch
                                size="sm"
                                v-model="form.is_active"
                                :value="1"
                                :unchecked-value="0"
                            >
                            </b-form-checkbox>
                        </BCol>


                        <BCol lg="6" class="mt-n1 mb-3">
                            <InputLabel value="Is Blacklisted" />
                            <b-form-checkbox
                                    switch
                                    size="sm"
                                    v-model="form.is_blacklisted"
                                    :value="1"
                                    :unchecked-value="0"
                                >
                                </b-form-checkbox>
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
    props: [],
    data(){
        return {
            currentUrl: window.location.origin,
            form: useForm({
                id: null,
                name: null,
                address: null,
                contact_person: null,
                contact_number: null,
                birthdate: null,
                email: null,
                tin: null,
                is_active: true,
                is_blacklisted: false,  
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
            this.form.id = data.id;
            this.form.name = data.name;
            this.form.address = data.address;
            this.form.contact_person = data.contact_person;
            this.form.contact_number = data.contact_number;
            this.form.email = data.email;
            this.form.tin = data.tin;
            this.form.is_active = data.is_active;
            this.form.is_blacklisted = data.is_blacklisted;
            this.editable = true;
            this.showModal = true;
        },


        submit(){
            if(this.editable){
                this.form.put(`/libraries/suppliers/${this.form.id}`,{
                    preserveScroll: true,
                    onSuccess: (response) => {
                        this.$emit('add', true);
                        this.form.reset();
                        this.hide();
                    },
                });
            }else{
                console.log(this.form);
                this.form.post('/libraries/suppliers',{
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