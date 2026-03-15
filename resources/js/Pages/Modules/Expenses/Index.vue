<template>
    <div>
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
                                    <th style="width: 4%;" class="text-center">View</th>
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
                                <template v-for="(list,index) in lists" :key="list.id || index">
                                <tr
                                    @click="toggleExpanded(index)"
                                    :class="{
                                        'bg-info-subtle': index === selectedRow,
                                        'expanded-row': isExpanded(index),
                                        'main-table-row': true
                                    }"
                                >
                                    <td class="text-center">
                                        {{ index + 1}}
                                    </td>
                                    <td class="text-center">
                                        <button
                                            type="button"
                                            class="collapse-btn"
                                            @click.stop="toggleExpanded(index)"
                                        >
                                            <div class="expand-icon" :class="{ rotated: isExpanded(index) }">
                                                <i class="ri-arrow-right-s-line"></i>
                                            </div>
                                        </button>
                                    </td>

                                    <td>{{ list.expense_type || '-' }}</td>
                                    <td>{{ list.amount ? '₱' + parseFloat(list.amount).toLocaleString() : '-' }}</td>
                                    <td>{{ list.expense_date || '-' }}</td>
                                    <td>{{ list.description || '-' }}</td>
                                    <td>
                                        <span class="status-badge" :style="getStatusStyle(list.status_info)">
                                            {{ list.status_info ? list.status_info.name : (list.status || '-') }}
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
                                            <b-button
                                                @click.stop="approveExpense(list)"
                                                variant="success"
                                                v-b-tooltip.hover
                                                title="Approve"
                                                size="sm"
                                                class="btn-icon"
                                                v-if="canApproveExpense(list)"
                                            >
                                                <i class="ri-check-double-line"></i>
                                            </b-button>
                                            <b-button
                                                @click.stop="releaseExpense(list)"
                                                variant="primary"
                                                v-b-tooltip.hover
                                                title="Release"
                                                size="sm"
                                                class="btn-icon"
                                                v-if="canReleaseExpense(list)"
                                            >
                                                <i class="ri-money-dollar-circle-line"></i>
                                            </b-button>
                                            <b-button
                                                @click.stop="openEdit(list,index)"
                                                variant="info"
                                                v-b-tooltip.hover
                                                title="Edit"
                                                size="sm"
                                                class="btn-icon"
                                                v-if="canManagePendingExpense(list)"
                                            >
                                                <i class="ri-pencil-fill"></i>
                                            </b-button>
                                            <b-button
                                                @click.stop="onDelete(list.id)"
                                                variant="danger"
                                                v-b-tooltip.hover
                                                title="Delete"
                                                size="sm"
                                                class="btn-icon"
                                                v-if="canManagePendingExpense(list)"
                                            >
                                                <i class="ri-delete-bin-line"></i>
                                            </b-button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="isExpanded(index)" class="details-row">
                                    <td colspan="10">
                                        <div class="details-container">
                                            <div class="details-content">
                                                <div class="collapse-actions">
                                                    <b-button
                                                        @click="approveExpense(list)"
                                                        variant="success"
                                                        size="sm"
                                                        v-if="canApproveExpense(list)"
                                                        class="system-action-btn system-action-success"
                                                    >
                                                        <i class="ri-check-double-line me-1"></i>
                                                        Approve Expense
                                                    </b-button>
                                                    <b-button
                                                        @click="releaseExpense(list)"
                                                        variant="primary"
                                                        size="sm"
                                                        v-if="canReleaseExpense(list)"
                                                        class="system-action-btn system-action-primary"
                                                    >
                                                        <i class="ri-money-dollar-circle-line me-1"></i>
                                                        Release Expense
                                                    </b-button>
                                                    <b-button
                                                        @click="openEdit(list,index)"
                                                        variant="info"
                                                        size="sm"
                                                        v-if="canManagePendingExpense(list)"
                                                        class="system-action-btn system-action-info"
                                                    >
                                                        <i class="ri-pencil-fill me-1"></i>
                                                        Edit
                                                    </b-button>
                                                    <b-button
                                                        @click="onDelete(list.id)"
                                                        variant="danger"
                                                        size="sm"
                                                        v-if="canManagePendingExpense(list)"
                                                        class="system-action-btn system-action-danger"
                                                    >
                                                        <i class="ri-delete-bin-line me-1"></i>
                                                        Delete
                                                    </b-button>
                                                </div>
                                                <div class="row g-4">
                                                    <div class="col-md-6">
                                                        <div class="info-card">
                                                            <div class="info-card-header">
                                                                <i class="ri-file-list-3-line"></i>
                                                                <h6>Expense Information</h6>
                                                            </div>
                                                            <div class="info-card-body">
                                                                <div class="info-item">
                                                                    <span class="info-label">Expense Type</span>
                                                                    <span class="info-value">{{ list.expense_type || '-' }}</span>
                                                                </div>
                                                                <div class="info-item">
                                                                    <span class="info-label">Amount</span>
                                                                    <span class="info-value">{{ list.amount ? 'P' + parseFloat(list.amount).toLocaleString() : '-' }}</span>
                                                                </div>
                                                                <div class="info-item">
                                                                    <span class="info-label">Expense Date</span>
                                                                    <span class="info-value">{{ list.expense_date || '-' }}</span>
                                                                </div>
                                                                <div class="info-item">
                                                                    <span class="info-label">Created</span>
                                                                    <span class="info-value">{{ list.created_at || '-' }}</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="info-card">
                                                            <div class="info-card-header">
                                                                <i class="ri-user-settings-line"></i>
                                                                <h6>Status and Actions</h6>
                                                            </div>
                                                            <div class="info-card-body">
                                                                <div class="info-item">
                                                                    <span class="info-label">Status</span>
                                                                    <span class="status-badge" :style="getStatusStyle(list.status_info)">
                                                                        {{ list.status_info ? list.status_info.name : (list.status || '-') }}
                                                                    </span>
                                                                </div>
                                                                <div class="info-item">
                                                                    <span class="info-label">Added By</span>
                                                                    <span class="info-value">{{ list.added_by ? list.added_by.name : '-' }}</span>
                                                                </div>
                                                                <div class="info-item info-item-description">
                                                                    <span class="info-label">Description</span>
                                                                    <span class="info-value">{{ list.description || '-' }}</span>
                                                                </div>
                                                            </div>
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
    </div>
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
    computed: {
        localKeyword: {
            get() {
                return this.filter.keyword || '';
            },
            set(value) {
                this.filter.keyword = value || null;
            }
        },
        isTopManagement() {
            const roles = this.$page?.props?.roles || [];
            const userRoles = Array.isArray(roles) ? roles : Object.values(roles);
            return userRoles.some(role => ['Top Management', 'Administrator'].includes(role));
        }
    },
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
            expandedRow: null,
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
        updateKeyword(value) {
            this.localKeyword = value;
        },
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
        approveExpense(expense) {
            if (!expense?.id || expense.status === 'approved') return;

            axios.patch(`/expenses/${expense.id}/approve`)
                .then((response) => {
                    this.fetch();
                    this.showToast('success', response.data?.message || 'Expense approved successfully');
                })
                .catch((err) => {
                    console.log(err);
                    this.showToast('error', 'Failed to approve expense');
                });
        },
        releaseExpense(expense) {
            if (!expense?.id || !this.canReleaseExpense(expense)) return;

            axios.patch(`/expenses/${expense.id}/release`)
                .then((response) => {
                    this.fetch();
                    this.showToast('success', response.data?.message || 'Expense released successfully');
                })
                .catch((err) => {
                    console.log(err);
                    this.showToast('error', 'Failed to release expense');
                });
        },

        selectRow(index) {
            if (this.selectedRow === index) {
                this.selectedRow = null;
            } else {
                this.selectedRow = index;
            }
        },
        toggleExpanded(index) {
            if (this.expandedRow === index) {
                this.expandedRow = null;
                return;
            }
            this.expandedRow = index;
        },
        isExpanded(index) {
            return this.expandedRow === index;
        },
        canApproveExpense(expense) {
            if (!this.isTopManagement || !expense) return false;
            const statusName = expense.status_info?.name?.toLowerCase?.() || '';
            const statusSlug = expense.status_info?.slug?.toLowerCase?.() || '';
            const statusValue = expense.status?.toLowerCase?.() || '';
            const pendingStatuses = ['pending', 'for approval', 'for-approval', 'for_approval'];
            return pendingStatuses.includes(statusName) ||
                pendingStatuses.includes(statusSlug) ||
                pendingStatuses.includes(statusValue);
        },
        canReleaseExpense(expense) {
            if (!this.isTopManagement || !expense) return false;
            const statusName = expense.status_info?.name?.toLowerCase?.() || '';
            const statusSlug = expense.status_info?.slug?.toLowerCase?.() || '';
            const statusValue = expense.status?.toLowerCase?.() || '';
            return statusName === 'approved' || statusSlug === 'approved' || statusValue === 'approved';
        },
        canManagePendingExpense(expense) {
            if (!expense) return false;
            const statusName = expense.status_info?.name?.toLowerCase?.() || '';
            const statusSlug = expense.status_info?.slug?.toLowerCase?.() || '';
            const statusValue = expense.status?.toLowerCase?.() || '';
            const pendingStatuses = ['pending', 'for approval', 'for-approval', 'for_approval'];
            return pendingStatuses.includes(statusName) ||
                pendingStatuses.includes(statusSlug) ||
                pendingStatuses.includes(statusValue);
        },

        openView(data) {
            this.selectedExpense = data;
            // Implement view details if needed
        },
        showToast(type, message) {
            const toast = this.$toast;
            if (toast && typeof toast[type] === 'function') {
                toast[type](message);
            }
        },

        getStatusStyle(status) {
            if (!status) {
                return {
                    color: '#6c757d',
                    backgroundColor: '#e2e3e5',
                    border: '1px solid #cccccc'
                };
            }

            return {
                color: status.text_color || '#000000',
                backgroundColor: status.bg_color || '#ffffff',
                border: `1px solid ${status.bg_color ? status.bg_color + '40' : '#cccccc'}`,
                boxShadow: `0 2px 4px ${status.bg_color ? status.bg_color + '20' : 'rgba(0,0,0,0.1)'}`
            };
        }
    }
}
</script>

<style scoped>
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
    cursor: default;
}

.collapse-btn {
    width: 30px;
    height: 30px;
    border: none;
    border-radius: 50%;
    background: transparent;
    color: #2e8b57;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.collapse-btn:hover {
    background: rgba(61, 141, 122, 0.08);
}

.main-table-row {
    cursor: pointer;
    transition: all 0.2s ease;
    border-left: 3px solid transparent;
}

.main-table-row:hover {
    background-color: rgba(61, 141, 122, 0.05) !important;
    border-left-color: #3D8D7A;
}

.main-table-row.expanded-row {
    background: linear-gradient(90deg, rgba(61, 141, 122, 0.08) 0%, rgba(61, 141, 122, 0.02) 100%);
    border-left-color: #3D8D7A;
}

.expand-icon {
    display: inline-block;
    transition: transform 0.3s ease;
    color: #6c757d;
}

.expand-icon i {
    font-size: 18px;
    vertical-align: middle;
}

.expand-icon.rotated {
    transform: rotate(90deg);
    color: #3D8D7A;
}

.details-row {
    background-color: #f8fafd;
    border-bottom: 2px solid #e9ecef;
}

.details-row td {
    padding: 0 !important;
    border-top: none !important;
}

.details-container {
    animation: slideDown 0.3s ease-out;
}

.details-content {
    padding: 1.5rem 2rem;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.info-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
    height: 100%;
    overflow: hidden;
}

.info-card:hover {
    box-shadow: 0 8px 25px rgba(61, 141, 122, 0.15);
    transform: translateY(-2px);
    border-color: #3D8D7A;
}

.info-card-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #e9ecef;
    background: #f9fafb;
}

.info-card-header i {
    font-size: 1.25rem;
    color: #3D8D7A;
    background: rgba(61, 141, 122, 0.1);
    padding: 0.5rem;
    border-radius: 8px;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.info-card-header h6 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 600;
    color: #267A4C;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-card-body {
    display: flex;
    flex-direction: column;
    padding: 0.5rem 1.25rem 1.25rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem 0;
    border-bottom: 1px dashed #e9ecef;
}

.info-item:last-child {
    border-bottom: none;
}

.info-item-description {
    align-items: flex-start;
}

.info-label {
    color: #6c757d;
    font-size: 0.85rem;
    font-weight: 500;
}

.info-value {
    color: #1e293b;
    font-size: 0.92rem;
    font-weight: 600;
    text-align: right;
}

.info-item-description .info-value {
    text-align: left;
    max-width: 60%;
}

.collapse-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #e2e8f0;
}

.system-action-btn {
    border: none;
    border-radius: 10px;
    padding: 0.65rem 1rem;
    font-size: 0.85rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08);
    transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
}

.system-action-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 20px rgba(15, 23, 42, 0.12);
}

.system-action-btn:disabled {
    opacity: 0.6;
    box-shadow: none;
}

.system-action-success {
    background: linear-gradient(135deg, #2e8b57 0%, #1f6b41 100%);
    color: #ffffff;
}

.system-action-primary {
    background: linear-gradient(135deg, #3d8d7a 0%, #267a4c 100%);
    color: #ffffff;
}

.system-action-info {
    background: linear-gradient(135deg, #0ea5e9 0%, #0369a1 100%);
    color: #ffffff;
}

.system-action-danger {
    background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);
    color: #ffffff;
}

@media (max-width: 768px) {
    .details-content {
        padding: 1rem;
    }

    .collapse-actions {
        flex-direction: column;
        align-items: stretch;
    }

    .info-item,
    .info-item-description {
        flex-direction: column;
        align-items: flex-start;
    }

    .info-item-description .info-value,
    .info-value {
        text-align: left;
        max-width: 100%;
    }
}
</style>
