<template>
    <div class="card bg-light-subtle shadow-none border">
        <div class="card-header bg-light-subtle">
            <div class="d-flex mb-n3">
                <div class="flex-shrink-0 me-3">
                    <div style="height:2.5rem;width:2.5rem;">
                        <span class="avatar-title bg-primary-subtle rounded p-2 mt-n1">
                            <i class="ri-profile-fill  text-primary fs-24"></i>
                        </span>
                    </div>
                </div>
                <div class="flex-grow-1">
                    <h5 class="mb-0 fs-14"><span class="text-body">Profile Information</span></h5>
                    <p class="text-muted text-truncate-two-lines fs-12">Update your account's profile information and email address.</p>
                </div>
                <div class="flex-shrink-0">
                  
                </div>
            </div>
        </div>
        <div class="card-body bg-white rounded-bottom" style="height: calc(100vh - 366px); overflow: auto;">
            <form class="customform">
                <div class="row g-3 p-3">
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <TextInput id="email" v-model="form.username" type="text" class="form-control" required :class="{ 'is-invalid': form.errors.username }" :readonly="true"/>                
                            <InputLabel for="username" value="Username" />
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <TextInput id="email" v-model="form.email" type="email" class="form-control" required :class="{ 'is-invalid': form.errors.email }" :readonly="true"/>                
                            <InputLabel for="email" value="Email" />
                        </div>
                    </div>
                    <div class="col-md-12 mb-1 mt-1">
                        <hr class="text-muted mt-0 mb-0"/>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating">
                            <TextInput id="firstname" v-model="form.firstname" type="text" class="form-control" required :class="{ 'is-invalid': form.errors.firstname }" :readonly="false"/>                
                            <InputLabel for="firstname" value="Firstname" />
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating">
                            <TextInput id="middlename" v-model="form.middlename" type="text" class="form-control" :class="{ 'is-invalid': form.errors.middlename }" :readonly="false"/>                
                            <InputLabel for="middlename" value="Middlename" />
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating">
                            <TextInput id="lastname" v-model="form.lastname" type="text" class="form-control" :class="{ 'is-invalid': form.errors.lastname }" :readonly="false"/>                
                            <InputLabel for="lastname" value="Lastname" />
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating">
                            <TextInput id="suffix" v-model="form.suffix" type="text" class="form-control" :class="{ 'is-invalid': form.errors.suffix }" :readonly="false"/>                
                            <InputLabel for="suffix" value="Suffix" />
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating mt-n1">
                            <TextInput id="mobile" v-model="form.mobile" type="text" class="form-control" :class="{ 'is-invalid': form.errors.mobile }" :readonly="false"/>                
                            <InputLabel for="mobile" value="Mobile" />
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating mt-n1">
                            
                            <select v-model="form.gender" class="form-select" id="floatingSelect">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                            <InputLabel for="gender" value="Gender" />
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-footer">
            <BButton  @click="submit('ok')" variant="primary w-lg float-end" type="button" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">Save</BButton>
        </div>
    </div>
</template>
<script>
import { useForm } from '@inertiajs/vue3';
import TextInput from '@/Shared/Components/Forms/TextInput.vue';
import InputLabel from '@/Shared/Components/Forms/InputLabel.vue';
export default {
    components: { InputLabel, TextInput },
    data(){
        return {
            form: useForm({
                username: this.$page.props.user.data.username,
                email: this.$page.props.user.data.email,
                firstname: this.$page.props.user.data.firstname,
                middlename: this.$page.props.user.data.middlename,
                lastname: this.$page.props.user.data.lastname,
                suffix: this.$page.props.user.data.suffix,
                gender: this.$page.props.user.data.gender,
                mobile: this.$page.props.user.data.mobile,
            }),
        }
    },
    methods: {
        submit(){
            this.form.put('/profile/updated', {
                errorBag: "submit",
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {},
            });
        }
    }
}
</script>