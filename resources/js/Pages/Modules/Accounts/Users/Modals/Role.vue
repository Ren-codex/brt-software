
<template>

       <div 
        v-if="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"
    >
        <div class="modal-container modal-lg" @click.stop>
            <div class="modal-header">
                <h2>User Roles</h2>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body">

                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <div class="card-body bg-white">
                          <div class="d-flex justify-content-end mb-3">
                            <b-button
                                type="button"
                                variant="success"
                                size="sm"
                                @click="openRole"
                            >
                                <i class="ri-plus-line"></i>
                                Add Role
                            </b-button>
                        </div>

                            <div class="table-responsive table-card">
                                <table class="table align-middle table-striped table-centered mb-0">
                                    <thead class="table-light thead-fixed">
                                        <tr class="fs-11">
                                            <th style="width: 5%;" class="text-center">#</th>
                                            <th>Role</th>
                                            <th style="width: 25%;" class="text-center">Added By</th>
                                            <th style="width: 25%;" class="text-center">Removed By</th>
                                            <th style="width: 10%;" class="text-center">Status</th>
                                            <th style="width: 8%;"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="table-white fs-11">
                                        <tr v-for="(list,index) in user.roles" v-bind:key="index" :class="{
                                            'bg-danger-subtle': list.is_active === 0
                                        }">
                                            <td class="text-center"> 
                                                {{ index+1 }}.
                                            </td>
                                            <td>
                                                <h5 class="fs-12 fw-semibold mb-0 text-primary">{{list.role?.name}}</h5>
                                            </td>
                                            <td class="text-center">{{ list.added }} <br /> <span style="font-size: 9.5px;">{{ list.created_at }}</span></td>
                                            <td class="text-center">{{ list.removed }} 
                                                <template v-if="list.removed_at != '-'">
                                                    <br /> <span style="font-size: 9.5px;">{{ list.removed_at }}</span>
                                                </template>
                                            </td>
                                            <td class="text-center">
                                                <span v-if="list.is_active" class="badge bg-success">Active</span>
                                                <span v-else class="badge bg-danger">Inactive</span>
                                            </td>
                                            <td class="text-end">
                                                <b-button v-if="list.is_active && list.name != 'Employee'" variant="soft-danger" @click="openRemove(list,index)" v-b-tooltip.hover title="Remove" size="sm" class="remove-list me-1">
                                                    <i class="ri-delete-bin-2-line align-bottom"></i>
                                                </b-button>
                                                <button v-else type="button" class="btn btn-sm btn-light waves-effect waves-light me-1" @click="openSetActive(list,index)" v-b-tooltip.hover title="Set to Active" >
                                                    <i class="ri-shield-user-fill align-bottom"></i>
                                                </button>

                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        </div>

                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-cancel" @click="hide">
                            <i class="ri-close-line"></i>
                            Close
                        </button>
                    </div>  

            </div>
 
        </div>
    </div>
    <AddRole @update="addData" :list_roles="roles" :roles="user.roles" ref="addrole"/>
    <Remove @update="updateData" ref="remove"/>
    <Activate @update="updateData" ref="activate"/>
</template>
<script>
import AddRole from './AddRole.vue';
import Remove from './Remove.vue';
import Activate from './Activate.vue';
export default {
    props: ['roles'],
    components: { Remove, AddRole , Activate },
    data(){
        return {
            currentUrl: window.location.origin,
            user: {},
            type: null,
            index: null,
            showModal: false,
        }
    },
    methods: { 
        show(data){
            this.user = data;
            this.showModal = true;
        },
        openRemove(data,index){
            this.index = index;
            this.$refs.remove.show(data);

        },

        openSetActive(data,index){
            this.index = index;
            this.$refs.activate.show(data);
        },

        openRole(){
            this.$refs.addrole.show(this.user);
        },
        updateData(data){
            this.user.roles[this.index] = data;
        },
        addData(data){
            this.user.roles.push(data);
        },
        sortRoles() {
            this.user.roles.sort((a, b) => {
                if (a.is_active !== b.is_active) {
                    return b.is_active - a.is_active;
                }
                if (a.is_active === 1) {
                    if (a.name === 'Employee' && b.name !== 'Employee') return 1;
                    if (a.name !== 'Employee' && b.name === 'Employee') return -1;
                }
                return 0;
            });
        },
        hide(){
            this.showModal = false;
        }
    }
}
</script>