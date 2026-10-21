<template>
    <PageHeader title="Employee Management" pageTitle="List" />
    <BRow>
        <div class="col-md-12">
            <div class="library-card">
                <div class="library-card-header">
                        <div class="d-flex align-items-center gap-3">
                            <div class="header-icon">
                                <i class="ri-shopping-cart-line fs-24"></i>
                            </div>
                            <div>
                                <h4 class="header-title mb-1">List of Employees</h4>
                                <p class="header-subtitle mb-0">A comprehensive list of employees</p>
                            </div>
                        </div>
                        <button v-if="can('employees', 'encoder')" class="create-btn" @click="openCreate">
                            <i class="ri-add-line"></i>
                            <span>Employee</span>
                        </button>
                </div>


                <div class="library-card-body">
                    <div class="search-section">
                        <div class="search-wrapper">
                            <i class="ri-search-line search-icon"></i>
                            <input type="text" v-model="localKeyword" @input="updateKeyword($event.target.value)"
                                placeholder="Search employee..." class="search-input">
                        </div>
                        <div class="sort-wrapper">
                            <label for="employee-sort" class="sort-label"><i class="ri-sort-desc"></i> Sort by</label>
                            <select id="employee-sort" class="sort-select" :value="sortOption" @change="applySortOption($event.target.value)">
                                <option value="created_at:desc">Latest added</option>
                                <option value="created_at:asc">Oldest added</option>
                                <option value="fullname:asc">Name (A–Z)</option>
                                <option value="fullname:desc">Name (Z–A)</option>
                                <option value="position:asc">Position (A–Z)</option>
                                <option value="position:desc">Position (Z–A)</option>
                                <option value="email:asc">Email (A–Z)</option>
                                <option value="email:desc">Email (Z–A)</option>
                            </select>
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
                                    <TableLoadingRow v-if="loading" :colspan="14" message="Loading employees..." />
                                    <template v-else>
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
                                                        @error="handleAvatarError($event, list)"
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
                                            <i v-if="list.is_blacklisted === 1" class="ri-check-line text-danger fs-16"></i>
                                            <i v-else class="ri-close-line text-muted fs-16"></i>
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
                                                <b-button @click="openView(list)" variant="light" v-b-tooltip.hover title="View" size="sm" class="btn-icon">
                                                    <i class="ri-eye-line"></i>
                                                </b-button>
                                                <b-button v-if="can('employees', 'encoder')" @click="openEdit(list,index)" variant="info" v-b-tooltip.hover title="Edit" size="sm" class="btn-icon">
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
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="pagination-section">
                        <Pagination class="ms-2 me-2 mt-n1" v-if="meta" @fetch="fetch" :lists="lists.length" :links="links" :pagination="meta" />
                    </div>
                </div>

            </div>
        </div>
    </BRow>

    <!-- View Employee: full screen over the list, so closing it lands back
         exactly where the list was. -->
    <div v-if="viewOpen" class="modal-overlay active" @click.self="backToList">
        <div class="modal-container employee-view-modal" ref="fullscreenTarget" @click.stop>
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="modal-header-icon"><i class="ri-user-3-line"></i></div>
                    <div>
                        <h5 class="mb-0">{{ selectedEmployee.fullname || 'Employee' }}</h5>
                        <small>{{ selectedEmployee.position?.title || 'Employee profile' }}</small>
                    </div>
                </div>
                <div class="header-actions">
                    <button v-if="can('employees', 'encoder')" type="button" class="btn btn-sm btn-light"
                        @click="$refs.details.openEdit(selectedEmployee)">
                        <i class="ri-pencil-fill"></i> Edit
                    </button>
                    <!-- The modal alone takes the whole screen; the page stays behind it. -->
                    <button type="button" class="close-btn" @click="toggleModalFullscreen"
                        :title="modalFullscreen ? 'Exit full screen' : 'Full screen'"
                        :aria-label="modalFullscreen ? 'Exit full screen' : 'Full screen'">
                        <i :class="modalFullscreen ? 'bx bx-exit-fullscreen' : 'bx bx-fullscreen'"></i>
                    </button>
                    <button class="close-btn" type="button" @click="backToList" aria-label="Close">
                        <i class="ri-close-line"></i>
                    </button>
                </div>
            </div>
            <div class="modal-body">
                <Details @update="fetch()" :employee="selectedEmployee" :dropdowns="dropdowns" :backToList="backToList"
                    :embedded="true" ref="details" />
            </div>
        </div>
    </div>

    <Create @add="fetch()" @update="fetch()"  :dropdowns="dropdowns" ref="create" />
</template>
<script>
import _ from 'lodash';
import Multiselect from "@vueform/multiselect";
import PageHeader from '@/Shared/Components/PageHeader.vue';
import Pagination from "@/Shared/Components/Pagination.vue";
import DeleteModal from '@/Shared/Components/Modals/DeleteModal.vue';
import TableLoadingRow from '@/Shared/Components/TableLoadingRow.vue';
import Create from './Modals/Create.vue';
import Details from './Details.vue';
import { modalFullscreenMixin } from '@/Shared/modalFullscreen.js';

export default {
    components: { PageHeader, Pagination, Multiselect, Create, Details, DeleteModal, TableLoadingRow },
    mixins: [modalFullscreenMixin],
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
            // The View Employee modal.
            viewOpen: false,
            units: [],
            deleteModalTitle: 'Delete Employee',
            deleteModalMessage: 'Are you sure you want to delete this employee? This action cannot be undone.',
            localKeyword: '',
            sortBy: 'created_at',
            sortDirection: 'desc',
            defaultAvatar: '/images/default-avatar.png',
            loading: false
        }
    },
    computed: {
        // The server sorts across every page, so the rows are shown as they
        // arrive. Re-sorting here would only reorder the current ten.
        filteredAndSortedList() {
            return this.lists;
        },
        sortOption() {
            return `${this.sortBy}:${this.sortDirection}`;
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
    mounted() {
        document.addEventListener('keydown', this.onEscape);
    },
    beforeUnmount() {
        document.removeEventListener('keydown', this.onEscape);
    },
    methods: {
        /** Escape closes View Employee, unless its Edit form is open on top. */
        onEscape(event) {
            if (event.key !== 'Escape' || !this.viewOpen) return;
            if (this.$refs.details?.$refs?.create?.showModal) return;
            this.backToList();
        },
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
            this.fetch();
        },

        applySortOption(option) {
            const [field, direction] = option.split(':');
            this.sortBy = field;
            this.sortDirection = direction;
            this.fetch();
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
            this.loading = true;
            axios.get(page_url, {
                params: {
                    keyword: this.filter.keyword,
                    sort_by: this.sortBy,
                    sort_direction: this.sortDirection,
                    count: 10,
                    option: 'lists'
                }
            })
                .then(response => {
                    if (response) {
                        this.lists = response.data.data;
                        this.meta = response.data.meta;
                        this.links = response.data.links;

                        // Keep details view in sync after edit/save.
                        if (this.viewOpen && this.selectedEmployee?.id) {
                            const updatedEmployee = this.lists.find(item => item.id === this.selectedEmployee.id);
                            if (updatedEmployee) {
                                this.selectedEmployee = updatedEmployee;
                            }
                        }
                    }
                })
                .catch(err => console.log(err))
                .finally(() => {
                    this.loading = false;
                });
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
            this.viewOpen = true;
        },

        backToList() {
            this.exitModalFullscreen();
            this.viewOpen = false;
            this.selectedEmployee = {};
            this.selectedRow = null;
        },

        onEmployeeSaved() {
            this.fetch();
        },

        genderAvatar(sex) {
            // Male is the default whenever sex is unknown/blank.
            return sex === 'Female' ? '/images/female-profile.png' : '/images/male-profile.png';
        },

        getAvatarSrc(employee) {
            if (!employee?.avatar || employee.avatar === 'noavatar.jpg') {
                return this.genderAvatar(employee?.sex);
            }
            return `/storage/${employee.avatar}`;
        },

        handleAvatarError(event, employee) {
            if (event?.target) {
                event.target.onerror = null;
                event.target.src = this.genderAvatar(employee?.sex);
            }
        }
    }
}
</script>

<style scoped>
/* View Employee fills the screen; the chrome itself comes from _library-modal.scss. */
.employee-view-modal {
    width: 100vw;
    max-width: 100vw;
    height: 100vh;
    max-height: 100vh;
    border-radius: 0;
}

.employee-view-modal .header-actions .btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border: 1px solid #c4d9d2;
    border-radius: 10px;
    font-weight: 600;
}

/* Sortable Header */
.search-section {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.search-section .search-wrapper {
    flex: 1 1 260px;
}

.sort-wrapper {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.sort-label {
    margin: 0;
    font-size: 0.78rem;
    font-weight: 600;
    color: #6b7280;
    white-space: nowrap;
}

.sort-select {
    padding: 0.55rem 2rem 0.55rem 0.75rem;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    background-color: #f9fafb;
    font-size: 0.8125rem;
    color: #374151;
    cursor: pointer;
}

.sort-select:focus {
    outline: none;
    border-color: var(--brt-focus);
    background-color: #fff;
    box-shadow: 0 0 0 3px rgba(var(--brt-focus-rgb), 0.1);
}

.sortable-header {
    display: flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    user-select: none;
    transition: color 0.3s ease;
}

.sortable-header:hover {
    color: var(--brt-focus);
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
    background-color: var(--hover-color, rgba(var(--brt-focus-rgb), 0.05)) !important;
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
