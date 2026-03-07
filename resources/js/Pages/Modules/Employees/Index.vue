<template>
    <PageHeader :title="currentView === 'list' ? 'Employee Management' : 'Employee Details'" :pageTitle="currentView === 'list' ? 'List' : 'Details'" />
    <BRow>
        <div class="col-md-12">
            <div class="library-card">
                <div class="library-card-header" v-if="currentView === 'list'">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="header-icon">
                                <i class="ri-shopping-cart-line fs-24"></i>
                            </div>
                            <div>
                                <h4 class="header-title mb-1">List of Employees</h4>
                                <p class="header-subtitle mb-0">A comprehensive list of employees</p>
                            </div>
                        </div>
                        <button class="create-btn" @click="openCreate">
                            <i class="ri-add-line"></i>
                            <span>Employee</span>
                        </button>
                    </div>
                </div>


                <div class="library-card-body" v-if="currentView === 'list'">
                    <div class="search-section">
                        <div class="search-wrapper">
                            <i class="ri-search-line search-icon"></i>
                            <input type="text" v-model="localKeyword" @input="updateKeyword($event.target.value)"
                                placeholder="Search employee..." class="search-input">
                        </div>
                    </div>

                    <div class="table-section">
                        <div class="table-responsive" style="overflow: visible; max-height: none;">
                            <table class="table align-middle table-centered mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>
                                            <div class="sortable-header" @click="toggleSort('fullname')">
                                                Name
                                                <i v-if="sortBy === 'fullname'" 
                                                :class="sortDirection === 'asc' ? 'ri-arrow-up-line' : 'ri-arrow-down-line'">
                                                </i>
                                            </div>
                                        </th>
                                        <th>
                                            <div class="sortable-header" @click="toggleSort('position')">
                                                Position
                                                <i v-if="sortBy === 'position'" 
                                                :class="sortDirection === 'asc' ? 'ri-arrow-up-line' : 'ri-arrow-down-line'">
                                                </i>
                                            </div>
                                        </th>
                                        <th>
                                            <div class="sortable-header" @click="toggleSort('email')">
                                                Email
                                                <i v-if="sortBy === 'email'" 
                                                :class="sortDirection === 'asc' ? 'ri-arrow-up-line' : 'ri-arrow-down-line'">
                                                </i>
                                            </div>
                                        </th>
                                        <th>Contact</th>
                                        <th>Birthdate</th>
                                        <th>Sex</th>
                                        <th>Religion</th>
                                        <th>Address</th>
                                        <th class="text-center">Regular</th>
                                        <th class="text-center">Blacklisted</th>
                                        <th>Active</th>
                                        <th>
                                            <div class="sortable-header" @click="toggleSort('created_at')">
                                                Created
                                                <i v-if="sortBy === 'created_at'" 
                                                :class="sortDirection === 'asc' ? 'ri-arrow-up-line' : 'ri-arrow-down-line'">
                                                </i>
                                            </div>
                                        </th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>

                                <tbody class="fs-12">
                                    <tr 
                                        v-for="(list,index) in filteredAndSortedList" 
                                        v-bind:key="list.id" 
                                        @click="openView(list)"
                                        style="cursor: pointer;"
                                        :style="getRowStyle(list)"
                                    >
                                        <td>{{ index + 1 }}</td>

                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-xs me-2">
                                                    <img
                                                        :src="getAvatarSrc(list)"
                                                        @error="handleAvatarError"
                                                        alt="Avatar"
                                                        class="rounded-circle avatar-xs"
                                                    >
                                                </div>
                                                <span>{{ list.fullname }}</span>
                                            </div>
                                        </td>
                                        <td>{{ list.position ? list.position.title : '-' }}</td>
                                        <td>{{ list.email || '-' }}</td>
                                        <td>{{ list.mobile || '-' }}</td>
                                        <td>{{ list.birthdate || '-' }}</td>
                                        <td>{{ list.sex || '-' }}</td>
                                        <td>{{ list.religion || '-' }}</td>
                                        <td>{{ list.address || '-' }}</td>

                                        <td class="text-center">
                                            <i v-if="list.is_regular === 1" class="ri-check-line text-success fs-16"></i>
                                            <i v-else class="ri-close-line text-muted fs-16"></i>
                                        </td>

                                        <td class="text-center">
                                            <i v-if="list.is_blacklisted === 1" class="ri-close-line text-danger fs-16"></i>
                                            <i v-else class="ri-check-line text-success fs-16"></i>
                                        </td>

                                        <td>
                                            <b-form-checkbox
                                                :checked="list.is_active === 1"
                                                @change="toggleActive(list)"
                                                switch
                                                size="md"
                                            />
                                        </td>

                                        <td>
                                            {{ list.created_at }}
                                        </td>

                                        <td>
                                            <div class="action-buttons" @click.stop>
                                                <b-button @click="openEdit(list,index)" variant="info" v-b-tooltip.hover title="Edit" size="sm" class="btn-icon">
                                                    <i class="ri-pencil-fill"></i>
                                                </b-button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="filteredAndSortedList.length === 0">
                                        <td colspan="14" class="text-center py-4">
                                            <i class="ri-user-line text-muted" style="font-size: 3rem;"></i>
                                            <p class="mt-2 mb-0">No employees found</p>
                                            <small class="text-muted">Try changing your search or filter criteria</small>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="pagination-section">
                        <Pagination class="ms-2 me-2 mt-n1" v-if="meta" @fetch="fetch()" :lists="lists.length" :links="links" :pagination="meta" />
                    </div>
                </div>

                

                <div v-if="currentView === 'details'">
                    <Details @update="fetch()" :employee="selectedEmployee"   :backToList="backToList" ref="details" />
                </div>
            </div>
        </div>
    </BRow>
    <Create @add="fetch()" @update="fetch()"  :dropdowns="dropdowns" ref="create" />
</template>
<script>
import _ from 'lodash';
import Multiselect from "@vueform/multiselect";
import PageHeader from '@/Shared/Components/PageHeader.vue';
import Pagination from "@/Shared/Components/Pagination.vue";
import DeleteModal from '@/Shared/Components/Modals/DeleteModal.vue';
import Create from './Modals/Create.vue';
import Details from './Details.vue';

export default {
    components: { PageHeader, Pagination, Multiselect, Create, Details, DeleteModal },
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
            selectedEmployee: {},
            currentView: 'list',
            units: [],
            deleteModalTitle: 'Delete Employee',
            deleteModalMessage: 'Are you sure you want to delete this employee? This action cannot be undone.',
            localKeyword: '',
            sortBy: 'created_at',
            sortDirection: 'desc',
            defaultAvatar: '/images/default-avatar.png'
        }
    },
    computed: {
        filteredAndSortedList() {
            return this.sortList(this.lists);
        }
    },
    watch: {
        "filter.keyword"(newVal) {
            this.localKeyword = newVal;
            this.checkSearchStr(newVal);
        },
        "filter.sort_by"(newVal) {
            this.sortBy = newVal;
        },
        "filter.sort_direction"(newVal) {
            this.sortDirection = newVal;
        }
    },
    created() {
        this.fetch();
    },
    methods: {
        checkSearchStr: _.debounce(function (string) {
            this.fetch();
        }, 300),
        
        updateKeyword(keyword) {
            this.localKeyword = keyword;
            this.filter.keyword = keyword;
            this.checkSearchStr(keyword);
        },
        
        toggleSort(field) {
            if (this.sortBy === field) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortBy = field;
                this.sortDirection = 'asc';
            }
        },
        
        sortList(list) {
            return [...list].sort((a, b) => {
                let aValue, bValue;
                
                switch (this.sortBy) {
                    case 'fullname':
                        aValue = a.fullname || '';
                        bValue = b.fullname || '';
                        break;
                    case 'position':
                        aValue = a.position ? a.position.title : '';
                        bValue = b.position ? b.position.title : '';
                        break;
                    case 'email':
                        aValue = a.email || '';
                        bValue = b.email || '';
                        break;
                    case 'created_at':
                        aValue = new Date(a.created_at || 0);
                        bValue = new Date(b.created_at || 0);
                        break;
                    default:
                        aValue = a[this.sortBy] || '';
                        bValue = b[this.sortBy] || '';
                }
                
                if (aValue < bValue) return this.sortDirection === 'asc' ? -1 : 1;
                if (aValue > bValue) return this.sortDirection === 'asc' ? 1 : -1;
                return 0;
            });
        },
        
        getRowStyle(list) {
            let hoverColor = 'rgba(46, 139, 87, 0.05)';
            
            if (list.is_active === 0) {
                hoverColor = 'rgba(220, 53, 69, 0.05)';
            } else if (list.is_blacklisted === 1) {
                hoverColor = 'rgba(255, 193, 7, 0.1)';
            }
            
            return {
                '--hover-color': hoverColor
            };
        },
        
        fetch(page_url) {
            page_url = page_url || '/employees';
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

        openEdit(data) {
            this.$refs.create.edit(data);
        },

        toggleActive(data) {
            axios.patch(`/employees/toggle-active/${data.id}`, {
                is_active: data.is_active === 1 ? 0 : 1
            })
            .then(response => {
                this.fetch();
                this.$toast.success(response.data.message);
            })
            .catch(err => {
                console.log(err);
                this.$toast.error('Failed to update employee status');
            });
        },

        async onDelete(id) {
            this.deleteModalTitle = 'Delete Employee';
            this.deleteModalMessage = 'Are you sure you want to delete this employee? This action cannot be undone.';
            const confirmed = await this.$refs.deleteModal.show();

            if (confirmed) {
                axios.delete(`/employees/${id}`)
                .then(response => {
                    this.fetch();
                    this.$toast.success(response.data.message);
                })
                .catch(err => {
                    console.log(err);
                    this.$toast.error('Failed to delete employee');
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
            this.selectedEmployee = data;
            this.currentView = 'details';
        },

        backToList() {
            this.currentView = 'list';
            this.selectedEmployee = {};
            this.selectedRow = null;
        },

        onEmployeeSaved() {
            this.fetch();
        },

        getAvatarSrc(employee) {
            if (!employee?.avatar) {
                return this.defaultAvatar;
            }
            return `/storage/${employee.avatar}`;
        },

        handleAvatarError(event) {
            if (event?.target) {
                event.target.onerror = null;
                event.target.src = this.defaultAvatar;
            }
        }
    }
}
</script>

<style scoped>
/* Sortable Header */
.sortable-header {
    display: flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    user-select: none;
    transition: color 0.3s ease;
}

.sortable-header:hover {
    color: #2e8b57;
}

.sortable-header i {
    font-size: 14px;
    opacity: 0.7;
}

/* Table Row Hover Effects */
tbody tr {
    transition: background-color 0.3s ease;
}

tbody tr:hover {
    background-color: var(--hover-color, rgba(46, 139, 87, 0.05)) !important;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 8px;
    justify-content: flex-start;
    min-width: 80px;
}

/* Pagination Section */
.pagination-section {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #e9ecef;
}

/* Empty State */
.text-center {
    color: #6c757d;
}

.text-center i {
    opacity: 0.5;
}
</style>
