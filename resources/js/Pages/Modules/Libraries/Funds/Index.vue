<template>
<Head title="Petty Cash Funds"/>
    <PageHeader title="Fund Management" pageTitle="Libraries" />
    <BRow>
        <div class="col-md-12">
            <div class="library-card">
                <div class="library-card-header">
                        <div class="d-flex align-items-center gap-3">
                            <div class="header-icon">
                                <i class="ri-safe-line"></i>
                            </div>
                            <div>
                                <h4 class="header-title mb-1">Petty Cash Funds</h4>
                                <p class="header-subtitle mb-0">Manage petty cash funds, balances, and top-ups</p>
                            </div>
                        </div>
                        <button class="create-btn" @click="openCreate">
                            <i class="ri-add-line"></i>
                            <span>Add Fund</span>
                        </button>
                </div>

                <div class="library-card-body">
                    <div class="search-section">
                        <div class="search-wrapper">
                            <i class="ri-search-line search-icon"></i>
                            <input type="text" v-model="filter.keyword" placeholder="Search funds..." class="search-input">
                        </div>
                    </div>

                    <div class="table-section">
                        <div class="table-responsive">
                            <table class="table align-middle table-centered mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>GL Code</th>
                                        <th>Balance</th>
                                        <th>Weekly Budget</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(list, index) in lists" :key="list.id"
                                        @click="selectRow(index)"
                                        :class="{
                                            'bg-info-subtle': index === selectedRow,
                                            'bg-danger-subtle': !list.is_active && index !== selectedRow
                                        }">
                                        <td>{{ index + 1 }}</td>
                                        <td>{{ list.name }}</td>
                                        <td><code>{{ list.gl_code }}</code></td>
                                        <td>₱{{ Number(list.balance).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</td>
                                        <td>{{ list.weekly_budget ? '₱' + Number(list.weekly_budget).toLocaleString('en-PH', { minimumFractionDigits: 2 }) : '—' }}</td>
                                        <td>
                                            <span class="badge" :class="list.is_active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'">
                                                {{ list.is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button @click.stop="openTopUp(list)" class="action-btn action-btn-edit" v-b-tooltip.hover title="Top-up">
                                                    <i class="ri-add-circle-line"></i>
                                                </button>
                                                <button @click.stop="openAdjust(list)" class="action-btn" style="color:#6c757d;background:#f8f9fa;border:1px solid #dee2e6" v-b-tooltip.hover title="Adjust Balance">
                                                    <i class="ri-scales-line"></i>
                                                </button>
                                                <button @click.stop="openEdit(list)" class="action-btn action-btn-edit" v-b-tooltip.hover title="Edit">
                                                    <i class="ri-pencil-line"></i>
                                                </button>
                                                <button @click.stop="toggleActive(list)" class="action-btn" :class="list.is_active ? 'action-btn-delete' : 'action-btn-edit'" v-b-tooltip.hover :title="list.is_active ? 'Deactivate' : 'Activate'">
                                                    <i :class="list.is_active ? 'ri-forbid-line' : 'ri-check-line'"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="pagination-section">
                        <Pagination v-if="meta" @fetch="fetch" :lists="lists.length" :links="links" :pagination="meta" />
                    </div>
                </div>
            </div>
        </div>
    </BRow>

    <Create ref="create" @add="fetch()" @update="fetch()" />
    <TopUp ref="topup" @done="fetch()" />
    <AdjustBalance ref="adjust" @done="fetch()" />
</template>

<script>
import _ from 'lodash';
import PageHeader from '@/Shared/Components/PageHeader.vue';
import Pagination from '@/Shared/Components/Pagination.vue';
import Create from './Modals/Create.vue';
import TopUp from './Modals/TopUp.vue';
import AdjustBalance from './Modals/AdjustBalance.vue';
import Swal from 'sweetalert2';

export default {
    components: { PageHeader, Pagination, Create, TopUp, AdjustBalance },
    data() {
        return {
            lists: [],
            meta: {},
            links: {},
            filter: { keyword: null },
            selectedRow: null,
        };
    },
    watch: {
        'filter.keyword'(val) { this.checkSearchStr(val); },
    },
    created() { this.fetch(); },
    methods: {
        checkSearchStr: _.debounce(function() { this.fetch(); }, 300),
        fetch(page_url) {
            page_url = page_url || '/libraries/funds';
            axios.get(page_url, {
                params: { option: 'lists', keyword: this.filter.keyword, count: 10 }
            })
            .then(res => {
                this.lists = res.data.data;
                this.meta  = res.data.meta;
                this.links = res.data.links;
            })
            .catch(err => console.log(err));
        },
        selectRow(index) {
            if (this.selectedRow === index) {
                this.selectedRow = null;
            } else {
                this.selectedRow = index;
            }
        },
        openCreate() { this.$refs.create.show(); },
        openEdit(fund) { this.$refs.create.edit(fund); },
        openTopUp(fund) { this.$refs.topup.show(fund); },
        openAdjust(fund) { this.$refs.adjust.show(fund); },
        toggleActive(fund) {
            const action = fund.is_active ? 'deactivate' : 'activate';
            Swal.fire({
                title: `${action.charAt(0).toUpperCase() + action.slice(1)} fund?`,
                text: `Are you sure you want to ${action} "${fund.name}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes',
            }).then(result => {
                if (!result.isConfirmed) return;
                axios.patch(`/libraries/funds/${fund.id}/toggle-active`, { is_active: !fund.is_active })
                    .then(() => this.fetch())
                    .catch(err => Swal.fire('Error', err.response?.data?.message || 'Failed', 'error'));
            });
        },
    },
};
</script>
