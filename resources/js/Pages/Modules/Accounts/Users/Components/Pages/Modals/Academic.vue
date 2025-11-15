<template>
    <b-modal v-model="showModal" header-class="p-3 bg-light" :title="(editable) ? 'Update Academic' : 'Add Academic'" class="v-modal-custom" modal-class="zoomIn" centered no-close-on-backdrop>
        <form class="customform">
            <BRow>
                <BCol lg="12">
                    <BRow class="g-3 mt-n1">
                        <BCol lg="12" class="mt-1">
                            <InputLabel value="School" :message="form.errors.school"/>
                            <TextInput v-model="form.school" type="text" class="form-control" placeholder="Please enter school" @input="handleInput('school')" :light="true" />
                        </BCol>
                        
                        <BCol lg="12" class="mt-n1">
                            <InputLabel value="Course" :message="form.errors.course"/>
                            <TextInput v-model="form.course" type="text" class="form-control" placeholder="Please enter course" @input="handleInput('course')" :light="true" />
                        </BCol>
                        <BCol lg="12" class="mt-0 mb-2">
                            <InputLabel value="Education Level" :message="form.errors.level_id"/>
                            <Multiselect :options="levels" :searchable="true" label="name" v-model="form.level_id" placeholder="Select Level" @input="handleInput('level_id')"/>
                        </BCol>
                        <BCol lg="12"><hr class="text-muted mt-n1 mb-n4"/></BCol>
                        <BCol lg="12" style="margin-top: 13px; margin-bottom: -10px;">
                            <div class="d-flex position-relative">
                                <div class="flex-shrink-0 fs-12" :class="(form.errors.is_ongoing) ? 'text-danger' : ''">
                                    Is this education still ongoing? :
                                </div>
                                <div class="flex-grow-1 ms-2"></div>
                                <div class="flex-shrink-0">
                                    <div class="d-inline-block" v-for="(list,index) in types"  v-bind:key="index">
                                        <div class="custom-control custom-radio mb-3 ms-4">
                                            <input type="radio" id="customRadio1" class="custom-control-input me-2" @input="handleInput('type_id')" :value="list.value" v-model="form.is_ongoing">
                                            <label class="custom-control-label fs-12 fw-normal" for="customRadio1">{{list.name}}</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </BCol>
                        <BCol lg="12"><hr class="text-muted mt-n2 mb-n4"/></BCol>
                        
                        <BCol lg="12" class="mt-0 mb-2" v-if="!form.is_ongoing">
                            <InputLabel value="Year Graduated" :message="form.errors.graduated_at"/>
                            <TextInput v-model="form.graduated_at" v-maska data-maska="####" type="text" class="form-control" placeholder="Please enter year" @input="handleInput('graduated_at')" :light="true" />
                        </BCol>
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
import { vMaska } from "maska/vue"
import { useForm } from '@inertiajs/vue3';
import Multiselect from "@vueform/multiselect";
import InputLabel from '@/Shared/Components/Forms/InputLabel.vue';
import TextInput from '@/Shared/Components/Forms/TextInput.vue';
export default {
    components: {InputLabel, TextInput, Multiselect },
    directives: { maska: vMaska },
    props: ['levels'],
    data(){
        return {
            currentUrl: window.location.origin,
            form: useForm({
                id: null,
                school: null,
                course: null,
                is_ongoing: null,
                level_id: null,
                user_id: null,
                graduated_at: null,
                option: 'academic'
            }),
            types: [
               {
                   'value': 1,
                   'name': 'Yes'
               },
               {
                   'value': 0,
                   'name': 'No'
               }
           ],
            showModal: false,
            editable: false
        }
    },
    methods: { 
        show(id){
            this.form.reset();
            this.form.user_id = id;
            this.showModal = true;
        },
        edit(data){
            this.form.id = data.id;
            this.form.grade = data.grade;
            this.form.upper_limit = data.upper_limit;
            this.form.lower_limit = data.lower_limit;
            this.form.is_failed = (data.is_failed) ? true : false;
            this.form.is_incomplete = (data.is_incomplete) ? true : false;
            this.form.campus_id = data.campus_id;
            this.editable = true;
            this.showModal = true;
        },
        submit(){
            if(this.editable){
                this.form.put('/management/update',{
                    preserveScroll: true,
                    onSuccess: (response) => {
                        this.form.reset();
                        this.hide();
                    },
                });
            }else{
                this.form.post('/employees',{
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