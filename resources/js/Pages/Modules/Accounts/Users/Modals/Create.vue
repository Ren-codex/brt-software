<template>
    <b-modal v-model="showModal" size="xl" header-class="p-3 bg-light" :title="(editable) ? 'Update Account' : 'Add Account'" class="v-modal-custom" modal-class="zoomIn" centered no-close-on-backdrop>
        <form class="customform">
            <BRow>
                <BCol lg="12">
                    <BRow class="g-3 mt-n1">
                        <BCol lg="12">Personal Information</BCol><hr class="text-muted mt-n1 mb-4"/>

                        <BCol lg="3" class="mt-n1 mb-3">
                            <InputLabel value="Firstname" :message="form.errors.firstname"/>
                            <TextInput v-model="form.firstname" type="text" class="form-control" placeholder="Please enter firstname" @input="handleInput('firstname')" :light="true" />
                        </BCol>
                        <BCol lg="3" class="mt-n1 mb-3">
                            <InputLabel value="Middlename" :message="form.errors.middlename"/>
                            <TextInput v-model="form.middlename" type="text" class="form-control" placeholder="Please enter middlename" @input="handleInput('middlename')" :light="true" />
                        </BCol>
                        <BCol lg="3" class="mt-n1 mb-3">
                            <InputLabel value="Lastname" :message="form.errors.lastname"/>
                            <TextInput v-model="form.lastname" type="text" class="form-control" placeholder="Please enter lastname" @input="handleInput('lastname')" :light="true" />
                        </BCol>

                        <BCol lg="3" class="mt-n1 mb-3">
                            <InputLabel value="Suffix" :message="form.errors.suffix"/>
                            <TextInput v-model="form.suffix" type="text" class="form-control" placeholder="Please enter suffix" @input="handleInput('suffix')" :light="true" />
                        </BCol>
                        
                        <BCol lg="3" class="mt-n1 mb-3">
                            <InputLabel value="Birthdate" :message="form.errors.birthdate"/>
                            <TextInput v-model="form.birthdate" type="date" class="form-control" placeholder="Please enter birthdate" @input="handleInput('birthdate')" :light="true" />
                        </BCol>

                        <BCol lg="3" class="mt-n1 mb-3">
                            <InputLabel value="Religion" :message="form.errors.religion"/>
                            <Multiselect :options="religions" :searchable="true" label="name" v-model="form.religion" placeholder="Select Sex" @input="handleInput('sex')"/>
                        </BCol>

                        <BCol lg="3" class="mt-n1 mb-3">
                            <InputLabel value="Sex" :message="form.errors.sex"/>
                            <Multiselect :options="['Male', 'Female' ]" :searchable="true" label="name" v-model="form.sex" placeholder="Select Sex" @input="handleInput('sex')"/>
                        </BCol>

                        <BCol lg="3" class="mt-n1 mb-3">
                            <InputLabel value="Mobile Number" :message="form.errors.short"/>
                            <TextInput v-model="form.mobile" type="text" class="form-control" placeholder="Please enter mobile" @input="handleInput('short')" :light="true" />
                        </BCol>

                        <BCol lg="12">Credentials</BCol><hr class="text-muted mt-n1 mb-4"/>

                        <BCol lg="6" class="mt-n1 mb-3">
                            <InputLabel value="Username" :message="form.errors.email"/>
                            <TextInput v-model="form.username" type="username" class="form-control" placeholder="Please enter username" @input="handleInput('short')" :light="true" />
                        </BCol>

                        <BCol lg="6" class="mt-n1 mb-3">
                            <InputLabel value="Email" :message="form.errors.email"/>
                            <TextInput v-model="form.email" type="email" class="form-control" placeholder="Please enter email" @input="handleInput('short')" :light="true" />
                        </BCol>

                        <BCol lg="6" class="mt-n1 mb-3">
                            <InputLabel value="Password" :message="form.errors.password"/>

                            <div class="position-relative">

                                <TextInput
                                    v-model="form.password"
                                    :type="togglePassword ? 'text' : 'password'"
                                    class="form-control pe-5"
                                    placeholder="Please enter password"
                                    @input="validatePassword"
                                    :class="{ 'is-invalid': passwordMismatch }"
                                    :light="true"
                                />

                                <BButton
                                    variant="link"
                                    class="position-absolute end-0 top-50 translate-middle-y text-decoration-none text-muted px-2"
                                    type="button"
                                    @click="togglePassword = !togglePassword"
                                >
                                    <i :class="togglePassword ? 'ri-eye-off-fill' : 'ri-eye-fill'" class="align-middle"></i>
                                </BButton>

                            </div>
                        </BCol>

                        <BCol lg="6" class="mt-n1 mb-3">
                            <InputLabel value="Confirm Password" />

                            <div class="position-relative">

                                <TextInput
                                    v-model="form.confirm_password"
                                    :type="toggleConfirm ? 'text' : 'password'"
                                    class="form-control pe-5"
                                    placeholder="Please confirm password"
                                    @input="validatePassword"
                                    :class="{ 'is-invalid': passwordMismatch }"
                                    :light="true"
                                />

                                <BButton
                                    variant="link"
                                    class="position-absolute end-0 top-50 translate-middle-y text-decoration-none text-muted px-2"
                                    type="button"
                                    @click="toggleConfirm = !toggleConfirm"
                                >
                                    <i :class="toggleConfirm ? 'ri-eye-off-fill' : 'ri-eye-fill'" class="align-middle"></i>
                                </BButton>

                            </div>

                            <!-- Password mismatch warning -->
                            <small v-if="passwordMismatch" class="text-danger">
                                Passwords do not match.
                            </small>
                        </BCol>

                        <BCol lg="12" class="mt-n1 mb-3">
                            <InputLabel value="Roles" :message="form.errors.role_ids"/>
                            <Multiselect :options="dropdowns.roles" :searchable="true" label="name" v-model="form.role_ids" placeholder="Select Roles" @input="handleInput('roles')" mode="tags"/>
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
                firstname: null,
                middlename: null,
                lastname: null,
                suffix: null,
                birthdate: null,
                sex: null,
                religion: null,
                mobile: null,
                address: null,
                email: null,
                password: null,
                username: null,
                role_ids: null,
                option: 'users'
            }),
            togglePassword: false,
            toggleConfirm: false,
            passwordMismatch: false,
            showModal: false,
            editable: false,
            religions: [
                'Roman Catholic',
                'Iglesia ni Cristo',
                'Evangelical (Born Again)',
                'Aglipayan / Philippine Independent Church',
                'Seventh-day Adventist',
                'Jehovah’s Witnesses',
                'Bible Baptist',
                'UCCP (United Church of Christ in the Philippines)',
                'Pentecostal',
                'Lutheran',
                'Methodist',
                'Anglican / Episcopal',
                'Jesus Is Lord (JIL)',
                'Victory Christian Fellowship',
                'El Shaddai',
                'Islam',
                'Buddhism',
                'Hinduism',
                'Taoism',
                'Confucianism',
                'Indigenous / Anito / Animism',
                'Baháʼí Faith',
                'Judaism',
                'Sikhism',
                'Non-religious / Atheist / Agnostic'
            ]
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
            this.form.firstname = data.firstname;
            this.form.middlename = data.middlename;
            this.form.lastname = data.lastname;
            this.form.suffix = data.suffix;
            this.form.sex = data.sex;
            this.form.religion = data.religion;
            this.form.mobile = data.mobile;
            this.form.birthdate = data.birthdate;
            this.form.username = data.username;
            this.form.email = data.email;
            this.form.role_ids = data.role_ids;
            this.editable = true;
            this.showModal = true;
        },
        validatePassword() {
            this.passwordMismatch =
                this.form.password &&
                this.form.confirm_password &&
                this.form.password !== this.form.confirm_password;
        },

        submit(){
            console.log(this.editable);
            if(this.editable){
                this.form.put('/users/update',{
                    preserveScroll: true,
                    onSuccess: (response) => {
                        this.$emit('add', true);
                        this.form.reset();
                        this.hide();
                    },
                });
            }else{
                this.form.post('/users',{
                    preserveScroll: true,
                    onSuccess: (response) => {
                        this.$emit('add', true);
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