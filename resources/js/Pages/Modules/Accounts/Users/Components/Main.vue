<template>
    <div class="card bg-light-subtle shadow-none border">
        <div class="card-header bg-light-subtle">
            <div class="d-flex mb-n3">
                <div class="flex-shrink-0 me-3">
                    <div style="height:2.5rem;width:2.5rem;">
                        <span class="avatar-title bg-primary-subtle rounded p-2 mt-n1">
                            <i class="ri-history-line text-primary fs-24"></i>
                        </span>
                    </div>
                </div>
                <div class="flex-grow-1">
                    <h5 class="mb-0 fs-14"><span class="text-body">User Logs</span></h5>
                    <p class="text-muted text-truncate-two-lines fs-12">These logs are essential for monitoring security, identifying potential threats, and ensuring compliance with security policies.</p>
                </div>
            </div>
        </div>
        
        <div class="card bg-white rounded-bottom shadow-none mb-0">
            <div class="step-arrow-nav mt-0">
                <ul class="nav nav-pills nav-justified custom-nav" role="tablist">
                    <li class="nav-item" role="presentation" v-for="(menu, index) in menus" v-bind:key="index">
                        <button class="nav-link fs-12 p-3" :class="(index == 0) ? 'active' : ''" 
                            :id="menu+'-tab'" data-bs-toggle="pill" :data-bs-target="'#'+menu" 
                            type="button" role="tab" :aria-controls="menu" aria-selected="true">
                            {{menu}}
                        </button>
                    </li>
                </ul>
            </div>
        </div>
        <div class="card-body bg-white rounded-bottom">
            <div class="tab-content">
                <div class="tab-pane" :class="(index == 0) ? 'show active' : ''" :id="menu" role="tabpanel" :aria-labelledby="menu+'-tab'" v-for="(menu, index) in menus" v-bind:key="index">
                    
                    <div class="carousel-container">
                        <div class="carousel-content">
                            <transition mode="out-in">
                                <div :key="index" class="tab-content">
                                   <Authentication :code="user.code" v-if="menu == 'Authentication'" />
                                   <Activity :code="user.code" v-if="menu == 'Activities'" />
                                </div>
                            </transition>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</template>
<script>
import simplebar from "simplebar-vue";
import Activity from  './Pages/Activity.vue';
import Authentication from './Pages/Authentication.vue';
export default {
    components: { simplebar, Activity, Authentication },
    props: ['user','dropdowns'],
    data(){
        return {
            menus: [
                'Overview','Authentication','Activities'
            ],
            index: null,
        }
    },
    methods: {
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