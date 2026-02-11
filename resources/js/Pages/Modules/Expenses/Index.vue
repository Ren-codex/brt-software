<template>
    <PageHeader :title="'Expense Management'" :pageTitle="'List'" />
    <BRow>
        <div class="col-md-12">
            <div class="library-card">
                <div class="library-card-header">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="header-icon">
                                <i class="ri-money-dollar-circle-fill fs-24"></i>
                            </div>
                            <div>
                                <h4 class="header-title mb-1">List of Expenses</h4>
                                <p class="header-subtitle mb-0">A comprehensive list of company expenses</p>
                            </div>
                        </div>
                        <button class="create-btn" @click="openCreate">
                            <i class="ri-add-line"></i>
                            <span>Expense</span>
                        </button>
                    </div>
                </div>

                <div class="card-body m-2 p-3">
                    <div class="search-section">
                        <div class="search-wrapper">
                            <i class="ri-search-line search-icon"></i>
                            <input type="text" v-model="localKeyword" @input="updateKeyword($event.target.value)"
                                placeholder="Search expenses..." class="search-input">
                        </div>
                    </div>

                    <div class="table-responsive table-card" style="overflow: auto;">
                        <table class="table align-middle table-striped table-centered mb-0">
                            <thead class="table-light thead-fixed">
                                <tr class="fs-11">
                                    <th style="width: 3%;">#</th>
                                    <th style="width: 12%;">Expense Type</th>
                                    <th style="width: 10%;">Amount</th>
                                    <th style="width: 10%;">Expense Date</th>
                                    <th style="width: 20%;">Description</th>
                                    <th style="width: 8%;">Status</th>
                                    <th style="width: 10%;">Added By</th>
                                    <th style="width: 10%;">Created</th>
                                    <th style="width: 10%;" class="text-center">Actions</th>
                                </tr>
                            </thead>

                            <tbody class="table-white fs-12">
                                <tr v-for="(list,index) in lists" v-bind:key="index" :class="{
                                    'bg-info-subtle': index === selectedRow
                                }">
                                    <td class="text-center">
                                        {{ index + 1}}
                                    </td>

                                    <td>{{ list.expense_type || '-' }}</td>
                                    <td>{{ list.amount ? '₱' + parseFloat(list.amount).toLocaleString() : '-' }}</td>
                                    <td>{{ list.expense_date || '-' }}</td>
                                    <td>{{ list.description || '-' }}</td>
                                    <td>
                                        <span :class="getStatusClass(list.status)">
                                            {{ list.status || '-' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-xs me-2">
                                                <img v-if="list.added_by && list.added_by.avatar" :src="'/storage/' + list.added_by.avatar" alt="Avatar" class="rounded-circle avatar-xs">
                                                <div v-else class="avatar-xs rounded-circle bg-light d-flex align-items-center justify-content-center">
                                                    <i class="ri-user-line text-muted"></i>
                                                </div>
                                            </div>
                                            <span>{{ list.added_by ? list.added_by.name : '-' }}</span>
                                        </div>
                                    </td>
                                    <td>{{ list.created_at }}</td>

                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-1">
                                            <b-button @click="openEdit(list,index)" variant="info" v-b-tooltip.hover title="Edit" size="sm" class="btn-icon">
                                                <i class="ri-pencil-fill"></i>
                                            </b-button>
                                            <b-button @click="onDelete(list.id)" variant="danger" v-b-tooltip.hover title="Delete" size="sm" class="btn-icon">
                                                <i class="ri-delete-bin-line"></i>
                                            </b-button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer">
                    <Pagination class="ms-2 me-2 mt-n1" v-if="meta" @fetch="fetch()" :lists="lists.length" :links="links" :pagination="meta" />
                </div>
            </div>
        </div>
    </BRow>
    <Create @add="fetch()" @update="fetch()" :dropdowns="dropdowns" ref="create" />
    <DeleteModal ref="deleteModal" />
</template>

<script>
import _ from 'lodash';
import Multiselect from "@vueform/multiselect";
import PageHeader from '@/Shared/Components/PageHeader.vue';
import Pagination from "@/Shared/Components/Pagination.vue";
import DeleteModal from '@/Shared/Components/Modals/DeleteModal.vue';
import Create from './Modals/Create.vue';

export default {
    components: { PageHeader, Pagination, Multiselect, Create, DeleteModal },
    props: ['dropdowns'],
    data() {
        return {
            currentUrl: window.location.origin,
            lists: [],
            meta: {},
            links: {},
            filter: {
                keyword: null
            },
            index: null,
            selectedRow: null,
            deleteModalTitle: 'Delete Expense',
            deleteModalMessage: 'Are you sure you want to delete this expense? This action cannot be undone.'
        }
    },
    watch: {
        "filter.keyword"(newVal) {
            this.checkSearchStr(newVal);
        }
    },
    created() {
        this.fetch();
    },
    methods: {
        checkSearchStr: _.debounce(function (string) {
            this.fetch();
        }, 300),
        fetch(page_url) {
            page_url = page_url || '/expenses';
            axios.get(page_url, {
                params: {
                    keyword: this.filter.keyword,
                    count: 10,
                    option: 'lists'
                }
            })
                .then(response => {
                    if (response) {
                        this.lists = response.data.data;
                        this.meta = response.data.meta;
                        this.links = response.data.links;
                    }
                })
                .catch(err => console.log(err));
        },
        openCreate() {
            this.$refs.create.show();
        },

        openEdit(data, index) {
            this.selectedRow = index;
            this.$refs.create.edit(data, index);
        },

        async onDelete(id) {
            this.deleteModalTitle = 'Delete Expense';
            this.deleteModalMessage = 'Are you sure you want to delete this expense? This action cannot be undone.';
            const confirmed = await this.$refs.deleteModal.show();

            if (confirmed) {
                axios.delete(`/expenses/${id}`)
                .then(response => {
                    this.fetch();
                    this.$toast.success(response.data.message);
                })
                .catch(err => {
                    console.log(err);
                    this.$toast.error('Failed to delete expense');
                });
            }
        },

        selectRow(index) {
            if (this.selectedRow === index) {
                this.selectedRow = null;
            } else {
                this.selectedRow = index;
            }
        },

        openView(data) {
            this.selectedExpense = data;
            // Implement view details if needed
        },

        getStatusClass(status) {
            switch (status) {
                case 'paid':
                    return 'badge bg-success';
                case 'pending':
                    return 'badge bg-warning';
                case 'approved':
                    return 'badge bg-info';
                case 'rejected':
                    return 'badge bg-danger';
                default:
                    return 'badge bg-secondary';
            }
        }
    }
}
</script>

<style scoped>
/* Add any specific styles if needed */
</style>
