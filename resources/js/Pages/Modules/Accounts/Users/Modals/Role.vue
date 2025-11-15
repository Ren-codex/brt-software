
<template>
    <b-modal v-model="showModal" style="--vz-modal-width: 800px;" header-class="p-3 bg-light" title="User Roles" class="v-modal-custom" modal-class="zoomIn" centered no-close-on-backdrop>
        <div v-if="user" class="card bg-light-subtle shadow-none border mb-0">
            <div class="card-header bg-light-subtle">
                <div class="d-flex mb-n3">
                    <div class="flex-shrink-0 me-2">
                        <div style="height:2.2rem;width:2.2rem;">
                             <div class="avatar-xs chat-user-img online">
                                <img :src="user.avatar" alt="" class="avatar-xs rounded-circle">
                            </div>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="mb-0 fs-13">
                            <span class="text-body">{{user.fullname}}</span>
                        </h5>
                        <p class="text-muted text-truncate-two-lines fs-11">List of roles assigned to this user</p>
                    </div>
                    <div class="flex-shrink-0">
                        <BButton @click="openRole()" variant="danger" class="btn-sm waves-effect waves-light">
                           Add Role
                        </BButton>
                    </div>
                </div>
            </div>
            <div class="card-body bg-white">
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
                                    <h5 class="fs-12 fw-semibold mb-0 text-primary">{{list.name}}</h5>
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
                                    <button v-else type="button" class="btn btn-sm btn-light waves-effect waves-light me-1" v-b-tooltip.hover title="Remove" disabled>
                                        <i class="ri-shield-user-fill align-bottom"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <template v-slot:footer>
            <b-button @click="hide()" variant="light" block>Close</b-button>
        </template>
    </b-modal>
    <AddRole @update="addData" :roles="roles" ref="addrole"/>
    <Remove @update="updateData" ref="remove"/>
</template>
<script>
import AddRole from './AddRole.vue';
import Remove from './Remove.vue';
export default {
    props: ['roles'],
    components: { Remove, AddRole },
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
            this.sortRoles();
            this.showModal = true;
        },
        openRemove(data,index){
            this.index = index;
            this.$refs.remove.show(data);
        },
        openRole(){
            this.$refs.addrole.show(this.user);
        },
        updateData(data){
            this.user.roles[this.index] = data;
            this.sortRoles();
        },
        addData(data){
            this.user.roles.push(data);
            this.sortRoles();
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