<template>
    <b-modal v-model="showModal" size="xl" header-class="p-3 bg-light" :title="(editable) ? 'Update Account' : 'Add Account'" class="v-modal-custom" modal-class="zoomIn" centered no-close-on-backdrop>
        <form class="customform">
            <BRow>
                <BCol lg="12">
                    <BRow class="g-3 mt-n1">
                        <BCol lg="3" class="mt-1">
                            <InputLabel value="Firstname" :message="form.errors.firstname"/>
                            <TextInput v-model="form.firstname" type="text" class="form-control" placeholder="Please enter firstname" @input="handleInput('name')" :light="true" />
                        </BCol>
                        <BCol lg="3" class="mt-1">
                            <InputLabel value="Middlename" :message="form.errors.middlename"/>
                            <TextInput v-model="form.middlename" type="text" class="form-control" placeholder="Please enter middlename" @input="handleInput('name')" :light="true" />
                        </BCol>
                        <BCol lg="3" class="mt-1">
                            <InputLabel value="Lastname" :message="form.errors.lastname"/>
                            <TextInput v-model="form.lastname" type="text" class="form-control" placeholder="Please enter lastname" @input="handleInput('name')" :light="true" />
                        </BCol>
                        
                        <BCol lg="3" class="mt-n1">
                            <InputLabel value="Suffix" :message="form.errors.short"/>
                            <TextInput v-model="form.short" type="text" class="form-control" placeholder="Please enter short" @input="handleInput('short')" :light="true" />
                        </BCol>
                        <BCol lg="6" class="mt-0 mb-2">
                            <InputLabel value="Roles" :message="form.errors.division_id"/>
                            <Multiselect :options="role" :searchable="true" label="name" v-model="form.roles" placeholder="Select Roles" @input="handleInput('roles')" mode="tags"/>
                        </BCol>
                        <BCol lg="6" class="mt-0 mb-2">
                            <InputLabel value="Roles" :message="form.errors.division_id"/>
                            <Multiselect :options="role" :searchable="true" label="name" v-model="form.roles" placeholder="Select Roles" @input="handleInput('roles')"/>
                        </BCol>
                        <BCol lg="12"><hr class="text-muted mt-n1 mb-n4"/></BCol>
                        <BCol lg="12" style="margin-top: 13px; margin-bottom: -10px;">
                            <div class="d-flex position-relative">
                                <div class="flex-shrink-0 fs-12" :class="(form.errors.is_active) ? 'text-danger' : ''">
                                    Is unit active? :
                                </div>
                                <div class="flex-grow-1 ms-2"></div>
                                <div class="flex-shrink-0">
                                    <div class="d-inline-block">
                                        <div class="custom-control custom-radio mb-3 ms-4">
                                            <input type="radio" id="customRadio1" class="custom-control-input me-2" @input="handleInput('is_active')" :value="true" v-model="form.is_active">
                                            <label class="custom-control-label fs-12 fw-normal" for="customRadio1">Yes</label>
                                        </div>
                                    </div>
                                    <div class="d-inline-block">
                                        <div class="custom-control custom-radio mb-3 ms-4">
                                            <input type="radio" id="customRadio1" class="custom-control-input me-2" @input="handleInput('is_active')" :value="false" v-model="form.is_active">
                                            <label class="custom-control-label fs-12 fw-normal" for="customRadio1">No</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </BCol>
                        <BCol lg="12"><hr class="text-muted mt-n1 mb-n4"/></BCol>
                    </BRow>
                </BCol>
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
import Multiselect from "@vueform/multiselect";
import InputLabel from '@/Shared/Components/Forms/InputLabel.vue';
import TextInput from '@/Shared/Components/Forms/TextInput.vue';
export default {
    components: {InputLabel, TextInput, Multiselect },
    props: ['divisions'],
    data(){
        return {
            currentUrl: window.location.origin,
            form: useForm({
                id: null,
                name: null,
                short: null,
                division_id: null,
                is_active: null,
                option: 'unit'
            }),
            showModal: false,
            editable: false
        }
    },
    methods: { 
        show(){
            this.form.reset();
            this.editable = false;
            this.showModal = true;
        },
        edit(data){
            this.form.id = data.id;
            this.form.name = data.name;
            this.form.short = data.short;
            this.form.is_active = (data.is_active) ? true : false;
            this.form.division_id = data.division_id;
            this.editable = true;
            this.showModal = true;
        },
        submit(){
            if(this.editable){
                this.form.put('/references/update',{
                    preserveScroll: true,
                    onSuccess: (response) => {
                        this.form.reset();
                        this.hide();
                    },
                });
            }else{
                this.form.post('/references',{
                    preserveScroll: true,
                    onSuccess: (response) => {
                        this.form.reset();
                        this.hide();
                        this.$emit('success',true);
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
        }
    }
}
</script>