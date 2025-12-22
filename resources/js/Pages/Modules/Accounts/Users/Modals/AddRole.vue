
<template>
    <div 
        v-if="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"
    >
        <div class="modal-container modal-lg" @click.stop>
            <div class="modal-header">
                <h2>{{ editable ? 'Update Role' : 'Add Role' }}</h2>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body">
                <form @submit.prevent="submit">
                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label for="role_id" class="form-label">Role</label>
                            <div class="input-wrapper">
                                <i class="ri-bar-chart-2-line input-icon"></i>
                                <b-form-select
                                class="form-control"
                                v-model="form.role_id"
                                :options="filteredRoles"
                                :class="{ 'input-error': form.errors.role_id }"
                                text-field="name"
                                value-field="value"
                                >
                                 <template #first>
                                    <b-form-select-option :value="null" disabled  >Select Role</b-form-select-option>
                                </template>
                                </b-form-select>    
                            </div>
                            <span class="error-message" v-if="form.errors.role_id">{{ form.errors.role_id }}</span>
                        </div>

                    </div>

  
                    <div class="success-alert" v-if="saveSuccess">
                        <i class="ri-checkbox-circle-fill"></i>
                        <span>Role has been saved successfully!</span>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-cancel" @click="hide">
                            <i class="ri-close-line"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-save" :disabled="form.processing">
                            <i class="ri-save-line" v-if="!form.processing"></i>
                            <i class="ri-loader-4-line spinner" v-else></i>
                            {{ form.processing ? 'Saving...' : 'Save Role' }}
                        </button>
                    </div>  


                </form>
            </div>
 
        </div>
    </div>
</template>
<script>
import { useForm } from '@inertiajs/vue3';
import Multiselect from "@vueform/multiselect";
import InputLabel from '@/Shared/Components/Forms/InputLabel.vue';
export default {
    components: { Multiselect, InputLabel },
    props: ['list_roles' , 'roles'],
    data(){
        return {
            form: useForm({
                user_id: null,
                role_id: null,
                type: 'add',
                option: 'role'
            }),
            user: {},
            type: null,
            showModal: false,
        }
    },
    computed: {
    filteredRoles() {
        if (!Array.isArray(this.roles) || !Array.isArray(this.user?.roles)) {
            return [];
        }

        // Use Set for fast lookup
        const assignedRoleIds = new Set(
            this.roles
                .filter(role => role && role.role.id )
                .map(role => role.role.id)
        );

        console.log(assignedRoleIds, 'assignedRoleIds');


        return this.list_roles
            .filter(role => role && !assignedRoleIds.has(role.value))
            .map(role => ({
                value: role.value,
                name: role.name
            }));
    }

        },

        mounted() {
            console.log(this.list_roles, 'list_roles');
            console.log(this.roles, 'existingroles');
        },
    methods: { 
        show(data){
            this.user = data;
            this.form.user_id = this.user.id,
            this.showModal = true;
        },
        submit(){
            this.form.put('/users/update', {
                preserveScroll: true,
                onSuccess: () => {
                    this.$emit('update',this.$page.props.flash.data.data);
                    this.hide();
                },
            });
        },
        handleInput(field) {
            this.form.errors[field] = false;
        },
        hide(){
            this.user = {};
            this.form.reset();
            this.form.clearErrors();
            this.showModal = false;
        }
    }
}
</script>