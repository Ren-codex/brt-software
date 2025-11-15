<template>
    <div class="card bg-light-subtle shadow-none border">
        <div class="card-header bg-light-subtle">
            <div class="d-flex mb-n3">
                <div class="flex-shrink-0 me-3">
                    <div style="height:2.5rem;width:2.5rem;">
                        <span class="avatar-title bg-primary-subtle rounded p-2 mt-n1">
                            <i class="ri-shield-keyhole-fill text-primary fs-24"></i>
                        </span>
                    </div>
                </div>
                <div class="flex-grow-1">
                    <h5 class="mb-0 fs-14"><span class="text-body">Authentication Logs</span></h5>
                    <p class="text-muted text-truncate-two-lines fs-12">These logs are essential for monitoring security, identifying potential threats, and ensuring compliance with security policies.</p>
                </div>
                <div class="flex-shrink-0">
                  
                </div>
            </div>
        </div>
        <div class="card-body bg-white rounded-bottom">
            <div class="table-responsive table-card" style="height: calc(100vh - 352px); overflow: auto;">
                <table class="table table-nowrap align-middle mb-0">
                    <thead class="table-light thead-fixed">
                        <tr class="fs-11">
                            <th></th>
                            <th style="width: 25%;">Browser</th>
                            <th style="width: 17%;" class="text-center">IP Address</th>
                            <th style="width: 20%;" class="text-center">Location</th>
                            <th style="width: 20%;" class="text-center">Date</th>
                            <th style="width: 15%;" class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(list,index) in lists" v-bind:key="index">
                            <td>
                                <div class="flex-shrink-0 avatar-xs">
                                    <div class="avatar-title bg-light text-primary rounded-3 fs-15">
                                        <i :class="'ri-'+list.type+'-fill '+list.attempt"></i>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <h5 class="fs-13 mb-0 text-dark">{{list.platform}} <span class="fs-12 text-muted">({{list.browser}})</span></h5>
                                <p v-if="list.location" class="fs-12 text-muted mb-0">{{ list.location.city }},  {{ list.location.state_name }}, {{ list.location.country }}</p>
                                <p v-else class="fs-12 text-muted mb-0">Not Available</p>
                            </td>
                            <td class="text-center">{{list.ip}} </td>
                            <td class="text-center text-muted fs-11">{{list.created_at}}</td>
                            <td class="text-center text-muted fs-11">{{list.created_at}}</td>
                            <td class="text-center">
                                <span :class="(!list.is_failed) ? 'badge bg-success' : 'badge bg-danger'">
                                    <span v-if="list.is_suspicious">Suspicious</span>
                                    <span v-if="!list.is_failed">Successful</span>
                                    <span v-else>Failed</span>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <Pagination class="ms-2 me-2 mt-n1" v-if="meta" @fetch="fetch" :lists="lists.length" :links="links" :pagination="meta" />
        </div>
    </div>
</template>
<script>
import Pagination from "@/Shared/Components/Pagination.vue";
export default {
    components: { Pagination },
    data(){
        return {
            lists: [],
            meta: {},
            links: {},
        }
    },
    created(){
        this.fetch();
    },
    methods : {
        fetch(page_url){
            page_url = page_url || '/profile';
            return axios.get(page_url,{
                params : {
                    option: 'authentication-logs',
                    count: 20,
                }
            })
            .then(response => {
                this.lists = response.data.data;
                this.meta = response.data.meta;
                this.links = response.data.links;      
            });
        }
    }
}
</script>