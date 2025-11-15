<template>
    <b-row class="g-2 mb-4" style="margin-top: -12px;">
       <b-col lg>
           <div class="input-group mb-1">
               <span class="input-group-text"> <i class="ri-search-line search-icon"></i></span>
               <input type="text" placeholder="Search Keyword" class="form-control" style="width: 100px;">
               <span @click="refresh()" class="input-group-text" v-b-tooltip.hover title="Refresh" style="cursor: pointer;"> 
                   <i class="bx bx-refresh search-icon"></i>
               </span>
               <b-button type="button" variant="primary">
                   Search
               </b-button>
           </div>
       </b-col>
   </b-row>
   <div class="table-responsive table-card" style="height: calc(100vh - 497px);">
        <table class="table table-nowrap align-middle mb-0">
            <thead class="table-light thead-fixed">
                <tr class="fs-11">
                    <th style="width: 5%;"></th>
                    <th>Browser</th>
                    <th style="width: 17%;" class="text-center">IP Address</th>
                    <th style="width: 20%;" class="text-center">Date</th>
                    <th style="width: 15%;" class="text-center">Status</th>
                </tr>
            </thead>
           <tbody v-if="lists.length > 0">
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
                    <td class="text-center">{{list.created_at}}</td>
                    <td class="text-center">
                        <span :class="(!list.is_failed) ? 'badge bg-success' : 'badge bg-danger'">
                            <span v-if="list.is_suspicious">Suspicious</span>
                            <span v-if="!list.is_failed">Successful</span>
                            <span v-else>Failed</span>
                        </span>
                    </td>
                </tr>
            </tbody>
            <tbody v-else>
                <tr>
                    <td colspan="6" class="text-center text-muted">No records found.</td>
                </tr>
            </tbody>
        </table>
   </div>
    <div class="card-body ms-n3 me-n3 mb-n3 bg-white border-top rounded-bottom">
        <Pagination class="ms-2 me-2 mt-n1" v-if="meta" @fetch="fetch" :lists="lists.length" :links="links" :pagination="meta" />
    </div>
</template>
<script>
import Pagination from "@/Shared/Components/Pagination.vue";
export default {
    components: { Pagination },
    props: ['code'],
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
            page_url = page_url || '/users';
            return axios.get(page_url,{
                params : {
                    code: this.code,
                    option: 'authentication-logs',
                    count: 10,
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