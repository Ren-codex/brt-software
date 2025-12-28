<template>
    <BRow>
        <div class="col-md-12">
            <div class="card shadow-lg border-0" >
                <div class="card-header bg-primary"  >
                    <div class="d-flex mb-n3">
                        <div class="flex-shrink-0 me-3">
                            <div style="height:2.5rem;width:2.5rem;">
                                <span class="avatar-title rounded p-2 mt-n1">
                                    <i class="ri-file-list-line text-white fs-24"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class=" fs-14"><span class="text-white">Remittances</span></h5>
                            <p class="text-white-50 text-truncate-two-lines fs-12">Manage remittances</p>
                        </div>

                    </div>
                </div>
                <div class="card-body bg-white">
                    <b-row class="mb-3 ms-1 me-1">
                        <b-col lg>
                            <div class="input-group">
                                <span class="input-group-text bg-primary text-white">
                                    <i class="ri-search-line"></i>
                                </span>
                                <input type="text" v-model="filter.keyword" @input="debouncedSearch" placeholder="Search Remittances" class="form-control border-primary">
                                <b-button type="button" variant="primary" @click="openCreate" class="d-flex align-items-center">
                                    <i class="ri-add-circle-fill me-1"></i> Prepare Remittance
                                </b-button>
                            </div>
                        </b-col>
                    </b-row>

                    <div class="table-responsive table-card mt-5">
                        <b-tabs v-model="tabIndex">
                            <b-tab title="Open">
                                <table class="table align-middle table-hover mb-0" style="border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                                    <thead style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                                        <tr class="fs-12 fw-bold text-muted">
                                            <th style="width: 3%; border: none;">#</th>
                                            <th style="width: 15%;" class="text-center border-none">Remittance No.</th>
                                            <th style="width: 15%;" class="text-center border-none">Date</th>
                                            <th style="width: 15%;" class="text-center border-none">Amount</th>
                                            <th style="width: 15%;" class="text-center border-none">Status</th>
                                            <th style="width: 20%;" class="text-center border-none">Collector Name</th>
                                            <th style="width: 7%;" class="text-center border-none">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="fs-12">
                                        <template v-for="(item,index) in openRemittance" :key="index">
                                            <tr @click="toggleRowExpansion(index)" :class="{ 'bg-primary bg-opacity-10': index === selectedRow, 'cursor-pointer': true }">
                                                <td class="text-center">{{ index + 1 }}</td>
                                                <td class="text-center">{{ item.remittance_no || '-' }}</td>
                                                <td class="text-center">{{ item.date || item.remittance_date }}</td>
                                                <td class="text-center">{{ item.total_amount ? '\u20B1' + Number(item.total_amount).toFixed(2) : '-' }}</td>
                                                <td class="text-center">
                                                    <span :style="{ 'background-color': item.status.name === 'disapproved' ? '#ff0000' : '#6c757d', color: '#fff' }" class="px-3 py-2 rounded-pill badge">
                                                        {{ item.status.name }}
                                                    </span>
                                                </td>
                                                <td class="text-center">{{ item.created_by?.username || '-' }}</td>
                                                <td class="text-center">
                                                    <div class="d-flex justify-content-center gap-1">
                                                        <b-button @click="toggleRowExpansion(index)" variant="outline-default" size="sm" class="btn-icon rounded-circle">
                                                            <i class="ri-arrow-up-s-line" v-if="expandedRows.includes(index)"></i>
                                                            <i class="ri-arrow-down-s-line" v-else></i>
                                                        </b-button>
                                                    </div>
                                                </td>
                                            </tr>

                                            <RemittanceDetails v-if="expandedRows.includes(index)" :item="item" @delete="onDelete" />
                                        </template>
                                    </tbody>
                                </table>
                            </b-tab>

                            <b-tab title="Liquidated">
                                <table class="table align-middle table-hover mb-0" style="border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                                    <thead style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                                        <tr class="fs-12 fw-bold text-muted">
                                            <th style="width: 3%; border: none;">#</th>
                                            <th style="width: 15%;" class="text-center border-none">Remittance No.</th>
                                            <th style="width: 15%;" class="text-center border-none">Date</th>
                                            <th style="width: 15%;" class="text-center border-none">Amount</th>
                                            <!-- <th style="width: 15%;" class="text-center border-none">Status</th> -->
                                            <th style="width: 20%;" class="text-center border-none">Collector Name</th>
                                            <!-- <th style="width: 7%;" class="text-center border-none">Actions</th> -->
                                        </tr>
                                    </thead>
                                    <tbody class="fs-12">
                                        <template v-for="(item,index) in liquidatedRemittance" :key="index">
                                            <tr @click="toggleRowExpansion(index)" :class="{ 'bg-primary bg-opacity-10': index === selectedRow, 'cursor-pointer': true }">
                                                <td class="text-center">{{ index + 1 }}</td>
                                                <td class="text-center">{{ item.remittance_no || '-' }}</td>
                                                <td class="text-center">{{ item.date || item.remittance_date }}</td>
                                                <td class="text-center">{{ item.total_amount ? '\u20B1' + Number(item.total_amount).toFixed(2) : '-' }}</td>
                                                <!-- <td class="text-center">
                                                    <span :style="{ 'background-color': item.status.name === 'disapproved' ? '#ff0000' : '#6c757d', color: '#fff' }" class="px-3 py-2 rounded-pill badge">
                                                        {{ item.status.name }}
                                                    </span>
                                                </td> -->
                                                <td class="text-center">{{ item.created_by?.username || '-' }}</td>
                                                <!-- <td class="text-center">
                                                    <div class="d-flex justify-content-center gap-1">
                                                        <b-button @click.stop="onDelete(item.id)" variant="outline-danger" size="sm" class="btn-icon rounded-circle">
                                                            <i class="ri-close-line"></i>
                                                        </b-button>
                                                    </div>
                                                </td> -->
                                            </tr>

                                            <RemittanceDetails v-if="expandedRows.includes(index)" :item="item" />
                                        </template>
                                    </tbody>
                                </table>
                            </b-tab>
                        </b-tabs>
                    </div>
                </div>
                <div class="card-footer bg-light border-0">
                    <Pagination class="ms-2 me-2 mt-n1" v-if="meta" @fetch="fetch" :lists="lists.length" :links="links" :pagination="meta" />
                </div>
            </div>
        </div>
    </BRow>
    <Create @add="fetch" ref="create"/>
</template>
    
<script>
import _ from 'lodash';
import Pagination from "@/Shared/Components/Pagination.vue";
import Create from './Modals/Create.vue';
import RemittanceDetails from './Details/RemittanceDetails.vue';

export default {
    components: { Pagination, Create, RemittanceDetails },
    data(){
        return {
            lists: [],
            meta: {},
            links: {},
            filter: { keyword: '' },
            tabIndex: 0,
            selectedRow: null,
            expandedRows: [],
            metrics: {
                total_remittances: 0,
                open_count: 0,
                liquidated_count: 0
            }
        }
    },
    computed: {
        activeStatus(){
            return this.tabIndex === 0 ? 'open' : 'liquidated';
        },
        openRemittance() {
            return this.lists.filter(r => r.status.name != 'liquidated');
        },
        liquidatedRemittance() {
            return this.lists.filter(r => r.status.name === 'liquidated');
        }
    },
    watch: {
        "filter.keyword": _.debounce(function(newVal){
            this.fetch();
        },300),
        tabIndex(){
            this.fetch();
        }
    },
    created(){
        this.debouncedSearch = _.debounce(this.fetch, 500);
        this.fetch();
    },
    methods: {
        fetch(){
            axios.get('/remittances',{
                params : {
                    keyword: this.filter.keyword,
                    count: 10,
                    option: 'lists'
                }
            })
            .then(response => {
                if(response){
                    this.lists = response.data.data;
                    this.meta = response.data.meta;
                    this.links = response.data.links;
                }
            })
            .catch(err => console.log(err));
        },
        openCreate(){
            this.$refs.create.show();
        },
        onDelete(id){
            // placeholder delete
            console.log('delete', id);
        },

        toggleRowExpansion(index) {
            if (this.expandedRows.includes(index)) {
                this.expandedRows = [];
            } else {
                this.expandedRows = [index];
            }
        }
    }
}
</script>

<style scoped>
  .badge {
    display: inline-block;
    padding: 4px 8px;
    font-size: 10px;
    font-weight: 600;
    color: white;
    background-color: #0d6efd;
    border-radius: 12px;
  }
.summary-table th {
    width: 40%;
    font-weight: 600;
    color: #495057;
}
.summary-table td {
    width: 60%;
}
.summary-table th, .summary-table td {
    border: 0;
    padding-top: 0.2rem;
    padding-bottom: 0.2rem;
}
.summary-table .text-muted {
    opacity: 0.9;
}
.summary-total-row th, .summary-total-row td {
    border-top: 1px solid #e9ecef;
    padding-top: 0.4rem;
    padding-bottom: 0.2rem;
}
.summary-total-row th {
    font-weight: 700;
}
.summary-total-row td strong {
    font-weight: 700;
}
</style>
