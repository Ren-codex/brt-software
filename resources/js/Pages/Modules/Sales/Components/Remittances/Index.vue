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
                                            <th style="width: 20%;" class="text-center border-none">Customer</th>
                                            <th style="width: 15%;" class="text-center border-none">Date</th>
                                            <th style="width: 15%;" class="text-center border-none">Amount</th>
                                            <th style="width: 15%;" class="text-center border-none">Status</th>
                                            <th style="width: 7%;" class="text-center border-none">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="fs-12">
                                        <template v-for="(item,index) in lists" :key="index">
                                            <tr @click="toggleRowExpansion(index)" :class="{ 'bg-primary bg-opacity-10': index === selectedRow, 'cursor-pointer': true }">
                                                <td class="text-center">{{ index + 1 }}</td>
                                                <td class="text-center">{{ item.remittance_number || '-' }}</td>
                                                <td class="text-center">{{ item.customer?.name || '-' }}</td>
                                                <td class="text-center">{{ item.date || item.created_at }}</td>
                                                <td class="text-center">{{ item.amount ? '\u20B1' + Number(item.amount).toFixed(2) : '-' }}</td>
                                                <td class="text-center">
                                                    <b-badge :style="{ 'background-color': item.status === 'open' ? '#28a745' : '#6c757d', color: '#fff' }" class="px-3 py-2 rounded-pill">
                                                        {{ item.status }}
                                                    </b-badge>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex justify-content-center gap-1">
                                                        <b-button @click.stop="onPrint(item.id)" variant="outline-info" size="sm" class="btn-icon rounded-circle">
                                                            <i class="ri-printer-line"></i>
                                                        </b-button>
                                                        <b-button @click.stop="openEdit(item, index)" variant="outline-primary" size="sm" class="btn-icon rounded-circle">
                                                            <i class="ri-pencil-fill"></i>
                                                        </b-button>
                                                        <b-button @click.stop="onDelete(item.id)" variant="outline-danger" size="sm" class="btn-icon rounded-circle">
                                                            <i class="ri-close-line"></i>
                                                        </b-button>
                                                    </div>
                                                </td>
                                            </tr>

                                            <tr v-if="expandedRows.includes(index)" class="bg-light">
                                                <td colspan="7" class="p-0">
                                                    <div class="p-4">
                                                        <h6 class="text-primary mb-3"><i class="ri-file-list-line me-2"></i>Remittance Details</h6>
                                                        <div class="row g-3">
                                                            <div class="col-md-6">
                                                                <div class="card border-0 shadow-sm bg-white">
                                                                    <div class="card-body">
                                                                        <p class="mb-1"><strong>Date:</strong> {{ item.date || item.created_at }}</p>
                                                                        <p class="mb-1"><strong>Bank:</strong> {{ item.bank || '-' }}</p>
                                                                        <p class="mb-0"><strong>Reference:</strong> {{ item.reference || '-' }}</p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="card border-0 shadow-sm bg-white">
                                                                    <div class="card-body">
                                                                        <p class="mb-1"><strong>Amount:</strong> {{ item.amount ? '\u20B1' + Number(item.amount).toFixed(2) : '-' }}</p>
                                                                        <p class="mb-1"><strong>Added By:</strong> {{ item.added_by?.name || '-' }}</p>
                                                                        <p class="mb-0"><strong>Notes:</strong> {{ item.notes || '-' }}</p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
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
                                            <th style="width: 20%;" class="text-center border-none">Customer</th>
                                            <th style="width: 15%;" class="text-center border-none">Date</th>
                                            <th style="width: 15%;" class="text-center border-none">Amount</th>
                                            <th style="width: 15%;" class="text-center border-none">Status</th>
                                            <th style="width: 7%;" class="text-center border-none">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="fs-12">
                                        <template v-for="(item,index) in lists" :key="index">
                                            <tr @click="toggleRowExpansion(index)" :class="{ 'bg-primary bg-opacity-10': index === selectedRow, 'cursor-pointer': true }">
                                                <td class="text-center">{{ index + 1 }}</td>
                                                <td class="text-center">{{ item.remittance_number || '-' }}</td>
                                                <td class="text-center">{{ item.customer?.name || '-' }}</td>
                                                <td class="text-center">{{ item.date || item.created_at }}</td>
                                                <td class="text-center">{{ item.amount ? '\u20B1' + Number(item.amount).toFixed(2) : '-' }}</td>
                                                <td class="text-center">
                                                    <b-badge :style="{ 'background-color': item.status === 'open' ? '#28a745' : '#6c757d', color: '#fff' }" class="px-3 py-2 rounded-pill">
                                                        {{ item.status }}
                                                    </b-badge>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex justify-content-center gap-1">
                                                        <b-button @click.stop="onPrint(item.id)" variant="outline-info" size="sm" class="btn-icon rounded-circle">
                                                            <i class="ri-printer-line"></i>
                                                        </b-button>
                                                        <b-button @click.stop="openEdit(item, index)" variant="outline-primary" size="sm" class="btn-icon rounded-circle">
                                                            <i class="ri-pencil-fill"></i>
                                                        </b-button>
                                                        <b-button @click.stop="onDelete(item.id)" variant="outline-danger" size="sm" class="btn-icon rounded-circle">
                                                            <i class="ri-close-line"></i>
                                                        </b-button>
                                                    </div>
                                                </td>
                                            </tr>

                                            <tr v-if="expandedRows.includes(index)" class="bg-light">
                                                <td colspan="7" class="p-0">
                                                    <div class="p-4">
                                                        <h6 class="text-primary mb-3"><i class="ri-file-list-line me-2"></i>Remittance Details</h6>
                                                        <div class="row g-3">
                                                            <div class="col-md-6">
                                                                <div class="card border-0 shadow-sm bg-white">
                                                                    <div class="card-body">
                                                                        <p class="mb-1"><strong>Date:</strong> {{ item.date || item.created_at }}</p>
                                                                        <p class="mb-1"><strong>Bank:</strong> {{ item.bank || '-' }}</p>
                                                                        <p class="mb-0"><strong>Reference:</strong> {{ item.reference || '-' }}</p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="card border-0 shadow-sm bg-white">
                                                                    <div class="card-body">
                                                                        <p class="mb-1"><strong>Amount:</strong> {{ item.amount ? '\u20B1' + Number(item.amount).toFixed(2) : '-' }}</p>
                                                                        <p class="mb-1"><strong>Added By:</strong> {{ item.added_by?.name || '-' }}</p>
                                                                        <p class="mb-0"><strong>Notes:</strong> {{ item.notes || '-' }}</p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
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
    <Create @created="fetch" ref="create" :dropdowns="dropdowns" />
</template>
    
<script>
import _ from 'lodash';
import Pagination from "@/Shared/Components/Pagination.vue";
import Create from './Modals/Create.vue';

export default {
    components: { Pagination, Create },
    props: ['dropdowns'],
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
        this.fetchMetrics();
    },
    methods: {
        fetch(page_url){
            page_url = page_url || '/remittances';
            axios.get(page_url,{
                params : {
                    status: this.activeStatus,
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
        fetchMetrics(){
            axios.get('/remittances',{ params: { option: 'dashboard' }})
            .then(response => {
                if(response){
                    this.metrics = response.data;
                }
            })
            .catch(err => console.log(err));
        },
        toggleRowExpansion(index){
            if(this.expandedRows.includes(index)){
                this.expandedRows = this.expandedRows.filter(i => i !== index);
            } else {
                this.expandedRows.push(index);
            }
        },
        openCreate(){
            this.$refs.create.show();
        },
        openEdit(data,index){
            this.selectedRow = index;
            // placeholder for edit
        },
        onPrint(id){
            window.open(`/remittances/${id}?option=print&type=remittance`);
        },
        onDelete(id){
            // placeholder delete
            console.log('delete', id);
        }
    }
}
</script>

<style scoped>
</style>
