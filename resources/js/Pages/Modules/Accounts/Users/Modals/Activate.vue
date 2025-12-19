<template>
      <div 
        v-if="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"
    >
        <div class="modal-container modal-lg" @click.stop>
            <div class="modal-header">
                <h2>Set Active Role</h2>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body">
                <!-- {{  list_roles }} -->
                <form @submit.prevent="submit">
                    <div class="form-row">
                        <div class="form-group form-group-half">
                             <div class="alert alert-danger alert-dismissible alert-additional fade show mb-xl-0 material-shadow" role="alert">
                                <div class="alert-body">
                                    <div class="d-flex mt-n1 mb-n2">
                                        <div class="flex-shrink-0 me-2">
                                            <i class="ri-alert-line fs-14 align-middle"></i>
                                        </div>
                                        <div class="flex-grow-1 mt-1">
                                            <h5 class="fs-11 text-primary">Are you sure you want to set this <b class="text-uppercase ">{{ selected?.role?.name }}</b> role to <span class="text-primary">"ACTIVE"</span>?</h5>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-primary p-2 rounded alert-content">
                                    <p class="mb-0 fs-10">Setting this role will allow the user’s access and permissions associated with the <strong class="text-decoration-underline">{{ selected.name }}</strong> module.</p>
                                </div>
                            </div>
                        </div>

                    </div>

  
                    <div class="success-alert" v-if="saveSuccess">
                        <i class="ri-checkbox-circle-fill"></i>
                        <span>Role has been set to active successfully!</span>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-cancel" @click="hide">
                            <i class="ri-close-line"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary" :disabled="form.processing">
                            <i class="ri-delete-bin-line" v-if="!form.processing"></i>
                            <i class="ri-loader-4-line spinner" v-else></i>
                            {{ form.processing ? 'Setting Active...' : 'Set Active' }}
                        </button>
                    </div>  


                </form>
            </div>
 
        </div>
    </div>
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
                role_id: null,
                user_id: null,
                type: 'set_role_active',
                option: 'set_role_active'
            }),
            selected: null,
            showModal: false
        }
    },
    methods: { 
        show(selected){
            this.form.reset();
            this.form.role_id = selected.role.id;
            this.form.user_id = selected.user.id;
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