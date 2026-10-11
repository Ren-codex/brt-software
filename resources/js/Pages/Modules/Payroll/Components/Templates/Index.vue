<template>
    <BRow>
        <div class="col-lg-12 mb-4">
            <div class="library-card">
                <div class="library-card-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="header-icon">
                            <i class="ri-group-line"></i>
                        </div>
                        <div>
                            <h4 class="header-title mb-1">Payroll Groups</h4>
                            <p class="header-subtitle mb-0">Group employees who are paid together on one payroll</p>
                        </div>
                    </div>
                    <button v-if="can('payroll', 'payroll_templates', 'encoder')" class="create-btn" @click="showCreateModal = true">
                        <i class="ri-add-line"></i>
                        <span>Add Group</span>
                    </button>
                </div>

                <div class="library-card-body">
                    <!-- Nothing created yet: one clear prompt instead of two empty panels. -->
                    <div v-if="!loading && payrollTemplates.length === 0" class="groups-empty">
                        <div class="groups-empty-icon"><i class="ri-group-line"></i></div>
                        <h5>No payroll groups yet</h5>
                        <p>A group is the set of employees a payroll is run for. Create one to get started.</p>
                        <button v-if="can('payroll', 'payroll_templates', 'encoder')" class="groups-primary-btn" @click="showCreateModal = true">
                            <i class="ri-add-line"></i> Add Group
                        </button>
                    </div>

                    <div v-else class="groups-layout">
                        <!-- Left: the groups -->
                        <aside class="groups-panel">
                            <div class="panel-heading">
                                <span>Groups</span>
                                <span class="panel-count">{{ meta?.total ?? payrollTemplates.length }}</span>
                            </div>

                            <div class="group-list">
                                <div
                                    v-for="template in payrollTemplates"
                                    :key="template.id"
                                    class="group-card"
                                    :class="{ active: isSelected(template) }"
                                    role="button"
                                    tabindex="0"
                                    @click="selectTemplate(template)"
                                    @keydown.enter="selectTemplate(template)"
                                >
                                    <span class="group-avatar">{{ initials(template.name) }}</span>
                                    <div class="group-info">
                                        <div class="group-name">{{ template.name }}</div>
                                        <div class="group-meta">
                                            <span><i class="ri-user-line"></i> {{ template.employees.length }} {{ template.employees.length === 1 ? 'employee' : 'employees' }}</span>
                                            <span class="dot">·</span>
                                            <span>Updated {{ formatDate(template.updated_at) }}</span>
                                        </div>
                                    </div>
                                    <div class="group-actions">
                                        <button
                                            v-if="can('payroll', 'payroll_templates', 'encoder')"
                                            type="button" class="icon-btn" v-b-tooltip.hover title="Rename group"
                                            @click.stop="editTemplate(template)"
                                        >
                                            <i class="ri-pencil-line"></i>
                                        </button>
                                        <button
                                            v-if="can('payroll', 'payroll_templates', 'admin')"
                                            type="button" class="icon-btn danger" v-b-tooltip.hover title="Delete group"
                                            @click.stop="confirmDelete(template)"
                                        >
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </div>
                                    <i class="ri-arrow-right-s-line group-chevron"></i>
                                </div>
                            </div>

                            <!-- Pages through the groups, so it sits under the groups. -->
                            <div class="group-pagination" v-if="meta && meta.last_page > 1">
                                <Pagination @fetch="fetchPayrollTemplates" :lists="payrollTemplates.length" :links="links" :pagination="meta" />
                            </div>
                        </aside>

                        <!-- Right: the employees in the selected group -->
                        <section class="employees-panel">
                            <template v-if="hasSelection">
                                <div class="employees-head">
                                    <div class="employees-title">
                                        <span class="group-avatar large">{{ initials(selectedTemplate.name) }}</span>
                                        <div>
                                            <h5>{{ selectedTemplate.name }}</h5>
                                            <p>{{ selectedTemplate.employees.length }} {{ selectedTemplate.employees.length === 1 ? 'employee' : 'employees' }} in this group</p>
                                        </div>
                                    </div>
                                    <div class="employees-tools">
                                        <div class="search-wrapper">
                                            <i class="ri-search-line search-icon"></i>
                                            <input type="text" v-model="localKeyword" placeholder="Search employees..." class="search-input">
                                        </div>
                                        <button v-if="can('payroll', 'payroll_templates', 'encoder')" class="groups-primary-btn" @click="addEmployee(selectedTemplate)">
                                            <i class="ri-user-add-line"></i> Add Employees
                                        </button>
                                    </div>
                                </div>

                                <div class="table-section employees-table">
                                    <div class="table-responsive">
                                        <table class="table align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th style="width: 48px;">#</th>
                                                    <th>Employee</th>
                                                    <th>Position</th>
                                                    <th class="text-center">Status</th>
                                                    <th class="text-end" style="width: 80px;"></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <TableLoadingRow v-if="loading" :colspan="5" message="Loading employees..." />
                                                <template v-else>
                                                    <tr v-for="(employee, index) in filteredEmployees" :key="employee.id">
                                                        <td class="text-muted">{{ index + 1 }}</td>
                                                        <td>
                                                            <div class="employee-cell">
                                                                <span class="employee-avatar">{{ initials(employee.fullname) }}</span>
                                                                <span class="employee-name">{{ employee.fullname }}</span>
                                                            </div>
                                                        </td>
                                                        <td>{{ employee.position ? employee.position.title : '—' }}</td>
                                                        <td class="text-center">
                                                            <span class="status-pill" :class="employee.is_active == 1 ? 'is-active' : 'is-inactive'">
                                                                {{ employee.is_active == 1 ? 'Active' : 'Inactive' }}
                                                            </span>
                                                        </td>
                                                        <td class="text-end">
                                                            <button
                                                                v-if="can('payroll', 'payroll_templates', 'encoder')"
                                                                type="button" class="icon-btn danger" v-b-tooltip.hover title="Remove from group"
                                                                @click.stop="removeEmployee(employee)"
                                                            >
                                                                <i class="ri-user-unfollow-line"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    <tr v-if="filteredEmployees.length === 0">
                                                        <td colspan="5">
                                                            <div class="employees-empty">
                                                                <i class="ri-user-search-line"></i>
                                                                <h6>{{ localKeyword ? 'No matches' : 'No employees in this group yet' }}</h6>
                                                                <p v-if="localKeyword">Nobody in this group matches "{{ localKeyword }}".</p>
                                                                <p v-else>Add the employees who should be paid on this group's payroll.</p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </template>

                            <div v-else class="select-prompt">
                                <i class="ri-cursor-line"></i>
                                <h5>Select a group</h5>
                                <p>Pick a group on the left to see and manage its employees.</p>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </BRow>

    <!-- Create/Edit Modal -->
    <PayrollTemplateModal
      v-if="showCreateModal || showEditTitleModal || showEmployeeModal"
      :template="selectedTemplate"
      :is-edit-title="showEditTitleModal"
      :is-add-employee="showEmployeeModal"
      @close="closeModal"
      @saved="handleSaved"
    />
    <!-- Toast Notification -->
    <div v-if="isToastVisible" class="toast-notification">
      <div class="toast-content">
        <i class="ri-check-line text-white me-2"></i>
        {{ toastMessage }}
      </div>
    </div>
</template>

<script>
import _ from 'lodash';
import axios from 'axios'
import PayrollTemplateModal from './Modal.vue'
import Pagination from "@/Shared/Components/Pagination.vue";
import TableLoadingRow from '@/Shared/Components/TableLoadingRow.vue';
import Swal from 'sweetalert2';

export default {
  components: { PayrollTemplateModal, Pagination, TableLoadingRow },
  props: ['dropdowns'],
  data() {
    return {
      payrollTemplates: [],
      meta: {},
      links: {},
      filter: {
        keyword: null
      },
      localKeyword: '',
      showCreateModal: false,
      showEditTitleModal: false,
      showEmployeeModal: false,
      selectedTemplate: null,
      isToastVisible: false,
      toastMessage: '',
      employeeSortOrder: 'asc',
      selectedEmployee: null,
      reselectId: null,
      loading: false,
    }
  },
  computed: {
    // A group is open. Deleting used to leave an empty array behind, which is
    // truthy, so this checks for a real record.
    hasSelection() {
      return !!(this.selectedTemplate && this.selectedTemplate.id);
    },
    sortedEmployees() {
      return _.orderBy(this.selectedTemplate?.employees || [], 'firstname', this.employeeSortOrder);
    },
    filteredEmployees() {
      if (!this.selectedTemplate || !this.localKeyword) {
        return this.selectedTemplate?.employees || []
      }
      
      const keyword = this.localKeyword.toLowerCase()
      return this.selectedTemplate.employees.filter(employee => 
        employee.firstname?.toLowerCase().includes(keyword) ||
        employee.lastname?.toLowerCase().includes(keyword) ||
        employee.fullname?.toLowerCase().includes(keyword) ||
        employee.email?.toLowerCase().includes(keyword) ||
        employee.position?.title?.toLowerCase().includes(keyword)
      )
    }
  },
  watch: {
    "filter.keyword"(newVal) {
      this.checkSearchStr(newVal);
    }
  },
  created() {
    this.fetchPayrollTemplates();
  },
  methods: {
    checkSearchStr: _.debounce(function (string) {
      this.fetchPayrollTemplates();
    }, 300),
    async fetchPayrollTemplates(page_url) {
      if (typeof page_url !== 'string' || !page_url) {
        page_url = '/payroll-templates';
      }
      this.loading = true;
      axios.get(page_url, {
        params: {
          keyword: this.filter.keyword,
          count: 10
        }
      })
        .then(response => {
          if (response) {
            this.payrollTemplates = response.data.data;
            const keepId = this.reselectId ?? this.selectedTemplate?.id;
            this.reselectId = null;
            this.selectedTemplate = this.payrollTemplates.find(t => t.id === keepId)
              || this.payrollTemplates[0]
              || null;
            this.meta = response.data.meta;
            this.links = response.data.links;
          }
        })
        .catch(err => console.log(err))
        .finally(() => {
          this.loading = false;
        });
    },
    updateKeyword(value) {
      this.filter.keyword = value;
    },
    viewEmployees(template) {
      this.selectedTemplate = template;
      if (window.innerWidth < 768) {
        this.$nextTick(() => {
          document.querySelector('.employees-section')?.scrollIntoView({ 
            behavior: 'smooth' 
          })
        })
      }
    },
    
    selectTemplate(template) {
      this.selectedTemplate = template
    },

    formatDate(dateString) {
      const date = new Date(dateString);
      if (Number.isNaN(date.getTime())) return '';
      return date.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
    },
    isSelected(template) {
      return this.hasSelection && this.selectedTemplate.id === template.id;
    },
    // Up to two letters for the round badge: "Regular Staff" becomes "RS".
    initials(name) {
      return String(name || '?').trim().split(/\s+/).slice(0, 2).map(word => word[0]).join('');
    },

    editTemplate(template) {
      this.selectedTemplate = { ...template }
      this.showEditTitleModal = true;
    },
    async confirmDelete(template) {
      const result = await Swal.fire({
          title: 'Are you sure?',
          text: 'You want to delete this group?',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Yes, delete it!'
      });
      if (result.isConfirmed) {
          axios.delete(`/payroll-templates/${template.id}`)
              .then(response => {
                  if (response.data && response.data.info && response.data.message && response.data.status === false) {
                    Swal.fire({
                      title: response.data.message,
                      text: response.data.info,
                      icon: 'info',
                      confirmButtonText: 'OK'
                    });
                  } else {
                    this.fetchPayrollTemplates();
                    this.showToast('Payroll group deleted successfully');
                    this.selectedTemplate = null;
                  }
              })
              .catch(error => {
                  console.error(error);
                  Swal.fire(
                      'Error!',
                      'Failed to delete the group.',
                      'error'
                  );
              });
      }
    },
    closeModal() {
      this.showCreateModal = false
      this.showEditTitleModal = false
      this.showEmployeeModal = false
    },
    handleSaved(addedEmployees = null) {
      this.closeModal()
      if (addedEmployees && this.selectedTemplate) {
        this.selectedTemplate.employees.push(...addedEmployees)
        const templateIndex = this.payrollTemplates.findIndex(t => t.id === this.selectedTemplate.id)
        if (templateIndex !== -1) {
          this.payrollTemplates[templateIndex].employees = this.selectedTemplate.employees
        }
        this.showToast('Employee(s) added to group successfully');
      } else {
        this.reselectId = this.selectedTemplate?.id ?? null
        this.fetchPayrollTemplates()
        this.showToast('Payroll group saved successfully');
      }
    },
    showToast(message) {
      this.toastMessage = message;
      this.isToastVisible = true;
      setTimeout(() => {
        this.isToastVisible = false;
      }, 3000);
    },
    getStatusStyle(status) {
      return status === 'active' ? 'background-color: #28a745; color: white;' : 'background-color: #6c757d; color: white;';
    },
    successRemoval() {
      this.fetchPayrollTemplates();
      const index = this.selectedTemplate.employees.findIndex(emp => emp.id === this.selectedEmployee.id);
      if (index !== -1) {
        this.selectedTemplate.employees.splice(index, 1);
      }
      this.showToast('Employee removed from group successfully');
    },
    async removeEmployee(employee) {
      const result = await Swal.fire({
          title: 'Are you sure?',
          text: 'You want to remove employee from this group?',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Yes, remove it!'
      });
      if (result.isConfirmed) {
          axios.delete(`/payroll-templates/${this.selectedTemplate.id}/employees/${employee.id}`)
              .then(response => {
                  if (response.data && response.data.info && response.data.message && response.data.status === false) {
                    Swal.fire({
                      title: response.data.message,
                      text: response.data.info,
                      icon: 'info',
                      confirmButtonText: 'OK'
                    });
                  } else {
                    this.successRemoval();
                  }
              })
              .catch(error => {
                  console.error(error);
                  Swal.fire(
                      'Error!',
                      'Failed to remove the employee.',
                      'error'
                  );
              });
      }
      this.selectedEmployee = employee;
    },
    addEmployee(template) {
      this.selectedTemplate = { ...template }
      this.showEmployeeModal = true;
    },
  }
}
</script>
<style scoped>
.toast-notification {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 9999;
  background-color: var(--brt-primary);
  color: white;
  padding: 12px 16px;
  border-radius: 8px;
  box-shadow: 0 6px 18px rgba(var(--brt-c-10322e-rgb), 0.25);
  animation: slideIn 0.3s ease-out;
}

.toast-content {
  display: flex;
  align-items: center;
  font-size: 14px;
}

@keyframes slideIn {
  from { transform: translateX(100%); opacity: 0; }
  to { transform: translateX(0); opacity: 1; }
}

/* Two panels: groups on the left, the selected group's people on the right. */
.groups-layout {
  display: grid;
  grid-template-columns: minmax(280px, 360px) minmax(0, 1fr);
  gap: 1.25rem;
  align-items: start;
}

.groups-panel {
  position: sticky;
  top: 20px;
}

.panel-heading {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.6rem;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #648b74;
}

.panel-count {
  background: var(--brt-c-eef5f2);
  color: var(--brt-primary-dark);
  border-radius: 999px;
  padding: 0.05rem 0.5rem;
  font-size: 0.7rem;
}

.group-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.group-card {
  position: relative;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 0.8rem;
  border: 1px solid var(--brt-c-e3ece8);
  border-radius: 12px;
  background: #fff;
  cursor: pointer;
  transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
}

.group-card:hover {
  border-color: var(--brt-c-9fc4b8);
  box-shadow: 0 4px 12px rgba(var(--brt-c-10322e-rgb), 0.08);
}

.group-card:focus-visible {
  outline: 2px solid var(--brt-primary);
  outline-offset: 2px;
}

/* The selected group: tinted, with a bar on the left edge so the choice is
   visible without relying on colour alone. */
.group-card.active {
  border-color: var(--brt-primary);
  background: var(--brt-c-f1f8f5);
  box-shadow: inset 4px 0 0 var(--brt-primary);
}

.group-avatar {
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: rgba(var(--brt-primary-rgb), 0.12);
  border: 1px solid rgba(var(--brt-primary-rgb), 0.18);
  color: var(--brt-primary-dark);
  font-weight: 700;
  font-size: 0.85rem;
  display: flex;
  align-items: center;
  justify-content: center;
  text-transform: uppercase;
}

.group-card.active .group-avatar,
.group-avatar.large {
  background: var(--brt-primary);
  border-color: var(--brt-primary);
  color: #fff;
}

.group-avatar.large {
  width: 46px;
  height: 46px;
  font-size: 0.95rem;
}

.group-info {
  flex: 1 1 auto;
  min-width: 0;
}

.group-name {
  font-weight: 700;
  font-size: 0.92rem;
  color: var(--brt-ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.group-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.3rem;
  font-size: 0.74rem;
  color: var(--brt-muted);
}

.group-meta .dot { color: var(--brt-border); }

.group-actions {
  display: flex;
  gap: 0.25rem;
  flex-shrink: 0;
}

.group-chevron {
  flex-shrink: 0;
  font-size: 1.2rem;
  color: var(--brt-border);
}

.group-card.active .group-chevron { color: var(--brt-primary); }

.icon-btn {
  width: 30px;
  height: 30px;
  border: 1px solid var(--brt-c-d7e5de);
  border-radius: 8px;
  background: #fff;
  color: var(--brt-primary);
  font-size: 0.95rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s ease;
}

.icon-btn:hover {
  background: var(--brt-primary);
  border-color: var(--brt-primary);
  color: #fff;
}

.icon-btn.danger {
  color: #b42318;
  border-color: #f3d2ce;
}

.icon-btn.danger:hover {
  background: #dc2626;
  border-color: #dc2626;
  color: #fff;
}

.group-pagination {
  margin-top: 0.75rem;
}

.group-pagination :deep(.brt-pagination) {
  padding-right: 0 !important;
  flex-wrap: wrap;
  gap: 0.5rem;
}

/* Right panel */
.employees-panel {
  min-width: 0;
  border: 1px solid var(--brt-c-e3ece8);
  border-radius: 14px;
  background: #fff;
  overflow: hidden;
}

.employees-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
  padding: 0.9rem 1rem;
  border-bottom: 1px solid var(--brt-c-e3ece8);
  background: var(--brt-c-f7faf9);
}

.employees-title {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  min-width: 0;
}

.employees-title h5 {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: var(--brt-ink);
}

.employees-title p {
  margin: 0;
  font-size: 0.78rem;
  color: var(--brt-muted);
}

.employees-tools {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.search-wrapper {
  position: relative;
  width: 230px;
  max-width: 100%;
}

.search-icon {
  position: absolute;
  left: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  color: #6b7280;
  pointer-events: none;
}

.search-input {
  width: 100%;
  padding: 0.5rem 0.75rem 0.5rem 2.3rem;
  border: 2px solid #e5e7eb;
  border-radius: 8px;
  font-size: 0.8125rem;
  background: #fff;
  transition: all 0.2s ease;
}

.search-input:focus {
  outline: none;
  border-color: var(--brt-focus);
  box-shadow: 0 0 0 3px rgba(var(--brt-focus-rgb), 0.1);
}

.groups-primary-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  border: none;
  border-radius: 8px;
  background: var(--brt-primary);
  color: #fff;
  font-size: 0.8rem;
  font-weight: 600;
  padding: 0.55rem 0.9rem;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s ease;
}

.groups-primary-btn:hover {
  background: var(--brt-primary-hover);
  box-shadow: 0 4px 12px rgba(var(--brt-primary-rgb), 0.3);
}

.employees-table {
  border: none;
  border-radius: 0;
  margin-bottom: 0;
}

.employees-table .table-responsive {
  max-height: calc(100vh - 360px);
}

.employees-table tbody tr {
  cursor: default;
}

.employee-cell {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.employee-avatar {
  flex-shrink: 0;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--brt-c-eef5f2);
  color: var(--brt-primary-dark);
  font-size: 0.72rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  text-transform: uppercase;
}

.employee-name {
  font-weight: 600;
  color: var(--brt-ink);
}

.status-pill {
  display: inline-block;
  padding: 0.15rem 0.6rem;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 700;
}

.status-pill.is-active { background: #dcfce7; color: #166534; }
.status-pill.is-inactive { background: #f3f4f6; color: #6b7280; }

/* Empty and prompt states */
.employees-empty,
.select-prompt,
.groups-empty {
  text-align: center;
  color: #6b7280;
}

.employees-empty { padding: 2rem 1rem; }

.employees-empty i,
.select-prompt i {
  font-size: 2.2rem;
  color: var(--brt-c-9fc4b8);
  display: block;
  margin-bottom: 0.4rem;
}

.employees-empty h6,
.select-prompt h5,
.groups-empty h5 {
  color: var(--brt-ink);
  font-weight: 700;
  margin-bottom: 0.25rem;
}

.employees-empty p,
.select-prompt p,
.groups-empty p {
  font-size: 0.82rem;
  margin: 0 auto;
  max-width: 380px;
}

.select-prompt { padding: 4rem 1rem; }

.groups-empty { padding: 3.5rem 1rem; }

.groups-empty-icon {
  width: 64px;
  height: 64px;
  margin: 0 auto 0.9rem;
  border-radius: 16px;
  background: rgba(var(--brt-primary-rgb), 0.12);
  color: var(--brt-primary);
  font-size: 1.8rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.groups-empty .groups-primary-btn { margin-top: 1rem; }

/* Tablets and phones: one column, groups first. */
@media (max-width: 991.98px) {
  .groups-layout {
    grid-template-columns: 1fr;
  }

  .groups-panel {
    position: static;
  }

  .employees-table .table-responsive {
    max-height: none;
  }
}

@media (max-width: 575.98px) {
  .employees-tools,
  .search-wrapper {
    width: 100%;
  }

  .employees-tools .groups-primary-btn {
    flex: 1 1 auto;
    justify-content: center;
  }

  .group-meta .dot,
  .group-meta span:last-child {
    display: none;
  }
}
</style>
