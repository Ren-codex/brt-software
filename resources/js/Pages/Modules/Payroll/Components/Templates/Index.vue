<template>
    <BRow>
        <div class="col-lg-12 mb-4">
            <div class="library-card">
                <div class="library-card-header">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="header-icon">
                                <i class="ri-file-list-3-line fs-24"></i>
                            </div>
                            <div>
                                <h4 class="header-title mb-1">Payroll Templates</h4>
                                <p class="header-subtitle mb-0">Manage payroll templates</p>
                            </div>
                        </div>
                        <button class="create-btn" @click="showCreateModal = true">
                            <i class="ri-add-line"></i>
                            <span>Add Template</span>
                        </button>
                    </div>
                </div>
                <div class="card-body m-2 p-3">
                  <div class="payroll-templates-container">
                    <div class="row">
                      <!-- Left Column - Templates List -->
                      <div class="col-lg-5 col-xl-4">
                        <div class="templates-list-section">
                          <div class="templates-grid" v-if="payrollTemplates.length > 0">
                            <div 
                              v-for="template in payrollTemplates" 
                              :key="template.id"
                              class="template-card-wrapper"
                              :class="{ 'selected-template': selectedTemplate && selectedTemplate.id === template.id }"
                            >
                              <b-card 
                                :class="['template-card', { 'active-template': selectedTemplate && selectedTemplate.id === template.id }]"
                                @click="selectTemplate(template)"
                              >
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                  <div>
                                    <h6 class="template-name mb-1">{{ template.name }}</h6>
                                    <span class="badge bg-light text-muted">
                                      <i class="ri-user-line me-1"></i>
                                      {{ template.employees.length }} employees
                                    </span>
                                  </div>
                                  <div class="template-actions">
                                    <b-button 
                                      @click.stop="editTemplate(template)" 
                                      variant="outline-primary"
                                      v-b-tooltip.hover title="Edit Template"
                                      size="sm"
                                      class="btn-icon rounded-circle me-1"
                                    >
                                      <i class="ri-pencil-fill"></i>
                                    </b-button>
                                    <b-button 
                                      @click.stop="confirmDelete(template)" 
                                      variant="outline-danger"
                                      v-b-tooltip.hover title="Delete Template"
                                      size="sm"
                                      class="btn-icon rounded-circle"
                                    >
                                      <i class="ri-delete-bin-line"></i>
                                    </b-button>
                                  </div>
                                </div>
                                <br>
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                  <small class="text-muted">
                                    Last updated: {{ formatDate(template.updated_at) }}
                                  </small>
                                  <b-button 
                                    @click.stop="viewEmployees(template)" 
                                    variant="primary"
                                    v-b-tooltip.hover :title="selectedTemplate && selectedTemplate.id === template.id ? 'Viewing Employees' : 'View Employees'"
                                    size="sm"
                                    class="view-btn btn-outline-primary"
                                  >
                                    <i class="ri-arrow-right-line me-1"></i>
                                  </b-button>
                                </div>
                              </b-card>
                            </div>
                          </div>
                          <div v-else class="text-center py-5">
                            <div class="mb-4">
                              <i class="ri-file-list-3-line text-muted" style="font-size: 64px;"></i>
                            </div>
                            <h4 class="text-muted mb-3">No Templates Found</h4>
                            <p class="text-muted mb-4">Create a new payroll template to get started</p>
                          </div>
                        </div>
                      </div>
                      <!-- Right Column - Selected Template Details -->
                      <div class="col-lg-7 col-xl-8">
                        <div class="employees-section" v-if="selectedTemplate && selectedTemplate.length !== 0">
                          <div class="section-header d-flex justify-content-between align-items-center mb-4">
                            <div>
                              <h5 class="mb-1">Employees in <span class="text-primary">{{ selectedTemplate.name }}</span> Template</h5>
                              <p class="text-muted mb-0">{{ selectedTemplate.employees.length }} employees assigned</p>
                            </div>
                            <div class="search-section d-flex">
                              <div class="search-wrapper">
                                <i class="ri-search-line search-icon"></i>
                                <input
                                  type="text"
                                  v-model="localKeyword"
                                  placeholder="Search employees..."
                                  class="search-input"
                                >
                              </div>
                              <b-button variant="primary" size="sm" @click="addEmployee(selectedTemplate)" class="ms-1">
                                <i class="ri-add-line me-1"></i>
                              </b-button>
                            </div>
                          </div>
      
                          <div class="card employees-table-card">
                            <div class="card-body p-0">
                              <div class="table-responsive">
                                <table class="table align-middle table-hover mb-0">
                                  <thead class="table-light">
                                    <tr>
                                      <th class="ps-4" style="width: 5%">#</th>
                                      <th style="width: 25%" class="text-start">Employee</th>
                                      <th style="width: 25%" class="text-start">Position</th>
                                      <th style="width: 15%" class="text-center">Status</th>
                                      <th style="width: 10%" class="text-end pe-4">Actions</th>
                                    </tr>
                                  </thead>
                                  <tbody>
                                    <tr 
                                      v-for="(employee, index) in filteredEmployees" 
                                      :key="employee.id"
                                      class="employee-row"
                                    >
                                      <td class="ps-4 fw-semibold text-muted">{{ index + 1 }}</td>
                                      <td>
                                        <div class="d-flex align-items-center">
                                          <div class="avatar-sm me-3">
                                            <div class="avatar-title bg-light rounded-circle">
                                              <i class="ri-user-line text-primary"></i>
                                            </div>
                                          </div>
                                          <div>
                                            <h6 class="mb-0">{{ employee.firstname }}, {{ employee.lastname }}</h6>
                                          </div>
                                        </div>
                                      </td>
                                      <td>
                                        <span class="badge bg-light text-dark">
                                          {{ employee.position ? employee.position.title : 'N/A' }}
                                        </span>
                                      </td>
                                      <td class="text-center">
                                        <span class="badge bg-success-subtle text-success">
                                          <i class="ri-checkbox-circle-line me-1"></i>
                                          {{ employee.is_active == 1 ? 'Active' : 'Inactive' }}
                                        </span>
                                      </td>
                                      <td class="text-end pe-4">
                                        <b-button 
                                          @click.stop="removeEmployee(employee)" 
                                          variant="outline-danger"
                                          v-b-tooltip.hover title="Remove from template"
                                          size="sm"
                                          class="btn-icon rounded-circle"
                                        >
                                          <i class="ri-close-line"></i>
                                        </b-button>
                                      </td>
                                    </tr>
                                  </tbody>
                                </table>
                                
                                <!-- Empty State -->
                                <div v-if="filteredEmployees.length === 0" class="text-center py-5">
                                  <div class="mb-3">
                                    <i class="ri-user-search-line text-muted" style="font-size: 48px;"></i>
                                  </div>
                                  <h5 class="text-muted mb-2">No employees found</h5>
                                  <p class="text-muted mb-0" v-if="localKeyword">
                                    No results for "{{ localKeyword }}"
                                  </p>
                                  <p class="text-muted mb-0" v-else>
                                    No employees assigned to this template
                                  </p>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                        
                        <!-- Empty State when no template selected -->
                        <div v-else class="text-center py-5 empty-state">
                          <div class="mb-4">
                            <i class="ri-layout-grid-line text-muted" style="font-size: 64px;"></i>
                          </div>
                          <h4 class="text-muted mb-3">Select a Template</h4>
                          <p class="text-muted mb-4">Choose a payroll template from the list to view assigned employees</p>
                        </div>
                        <div class="card-footer bg-light border-0 m-3">
                            <Pagination class="ms-2 me-2 mt-n1" v-if="meta" @fetch="fetchPayrollTemplates" :lists="payrollTemplates.length"
                                :links="links" :pagination="meta" />
                        </div>
                      </div>
                    </div>
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

    <DeleteModal ref="delete" @delete="successDeletion" />
    <DeleteModal ref="removeEmployee" @delete="successRemoval" />
</template>

<script>
import _ from 'lodash';
import axios from 'axios'
import PayrollTemplateModal from './Modal.vue'
import Pagination from "@/Shared/Components/Pagination.vue";
import DeleteModal from '@/Shared/Components/Modals/Delete.vue';

export default {
  components: { PayrollTemplateModal, Pagination, DeleteModal },
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
    }
  },
  computed: {
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
        employee.email?.toLowerCase().includes(keyword) ||
        employee.position?.name?.toLowerCase().includes(keyword)
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
      axios.get(page_url, {
        params: {
          keyword: this.filter.keyword,
          count: 10
        }
      })
        .then(response => {
          if (response) {
            this.payrollTemplates = response.data.data;
            this.meta = response.data.meta;
            this.links = response.data.links;
          }
        })
        .catch(err => console.log(err));
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
      // Add your date formatting logic here
      return new Date(dateString).toLocaleDateString()
    },

    editTemplate(template) {
      this.selectedTemplate = { ...template }
      this.showEditTitleModal = true;
    },
    confirmDelete(template) {
      let title = "Payroll Template";
      this.$refs.delete.show(template.id , title, '/payroll-templates');
    },
    successDeletion() {
      this.fetchPayrollTemplates();
      this.showToast('Payroll template deleted successfully');
      this.selectedTemplate = [];
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
        this.showToast('Employee(s) added to template successfully');
      } else {
        this.fetchPayrollTemplates()
        this.showToast('Payroll template saved successfully');
        this.selectedTemplate = null
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
    async removeEmployee(employee) {
      let title = "Employee from Payroll Template";
      this.$refs.removeEmployee.show(employee.id , title, `/payroll-templates/${this.selectedTemplate.id}/employees`);
      this.selectedEmployee = employee;
    },
    successRemoval() {
      this.fetchPayrollTemplates();
      const index = this.selectedTemplate.employees.findIndex(emp => emp.id === this.selectedEmployee.id);
      if (index !== -1) {
        this.selectedTemplate.employees.splice(index, 1);
      }
      this.showToast('Employee removed from template successfully');
    },
    addEmployee(template) {
      this.selectedTemplate = { ...template }
      this.showEmployeeModal = true;
    },
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
  text-transform: uppercase;
  letter-spacing: 0.5px;
  transition: all 0.3s ease;
  cursor: default;
}

.toast-notification {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 9999;
  background-color: #28a745;
  color: white;
  padding: 12px 16px;
  border-radius: 4px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
  animation: slideIn 0.3s ease-out;
}

.toast-content {
  display: flex;
  align-items: center;
  font-size: 14px;
}

@keyframes slideIn {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

.create-btn {
  background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
  border: none;
  color: white;
  padding: 10px 16px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 8px;
}

.create-btn:hover {
  background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(0, 123, 255, 0.3);
}

.payroll-templates-container {
  min-height: 500px;
}

/* Template Cards */
.templates-list-section {
  position: sticky;
  top: 20px;
}

.section-title {
  color: #495057;
  font-weight: 600;
}

.template-card-wrapper {
  margin-bottom: 12px;
  border-radius: 8px;
  transition: all 0.3s ease;
}

.template-card-wrapper.selected-template {
  box-shadow: 0 0 0 2px rgb(6, 107.4, 93.6);
}

.template-card {
  position: relative;
  border: 1px solid #e9ecef;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s ease;
  background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
}

.template-card:hover {
  border-color: rgb(6, 107.4, 93.6);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(74, 108, 247, 0.15);
}

.template-card.active-template {
  border-color: rgb(6, 107.4, 93.6);
  background: linear-gradient(135deg, rgb(255, 255, 255) 0%, rgb(225, 255, 251) 100%);
}

.template-name {
  color: #343a40;
  font-weight: 600;
  font-size: 1.1rem;
}

.view-btn {
  position: absolute;
  right: 10px;
  margin-bottom: 10px;
}

/* Search Section */
.search-section {
  max-width: 300px;
}

.search-wrapper {
  position: relative;
}

.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #6c757d;
}

.search-input {
  width: 100%;
  padding: 8px 16px 8px 40px;
  border: 1px solid #dee2e6;
  border-radius: 6px;
  transition: all 0.3s ease;
  background-color: #f8f9fa;
}

.search-input:focus {
  outline: none;
  border-color: #4a6cf7;
  background-color: #ffffff;
  box-shadow: 0 0 0 3px rgba(74, 108, 247, 0.1);
}

/* Employees Table */
.employees-table-card {
  border: none;
  border-radius: 12px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

.table {
  margin-bottom: 0;
}

.table thead th {
  border-top: none;
  border-bottom: 2px solid #e9ecef;
  font-weight: 600;
  color: #495057;
  padding: 16px 12px;
  text-transform: uppercase;
  font-size: 0.8rem;
  letter-spacing: 0.5px;
}

.table tbody tr {
  transition: all 0.2s ease;
}

.table tbody tr:hover {
  background-color: #f8f9ff;
}

.employee-row {
  border-bottom: 1px solid #f1f3f4;
}

.employee-row:last-child {
  border-bottom: none;
}

.employee-row td {
  padding: 16px 12px;
  vertical-align: middle;
}

/* Badge Styles */
.badge {
  padding: 4px 8px;
  font-size: 0.75rem;
  font-weight: 500;
}

/* Avatar */
.avatar-sm {
  width: 36px;
  height: 36px;
}

.avatar-title {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
}

/* Empty State */
.empty-state {
  background: linear-gradient(135deg, #f8f9ff 0%, #ffffff 100%);
  border-radius: 12px;
  border: 2px dashed #dee2e6;
  margin-top: 0px;
}

/* Responsive Design */
@media (max-width: 992px) {
  .templates-list-section {
    position: static;
  }
  
  .search-section {
    max-width: 100%;
  }
}

@media (max-width: 768px) {
  .template-actions {
    position: absolute;
    top: 16px;
    right: 16px;
  }
  
  .employees-table-card .table {
    font-size: 0.9rem;
  }
  
  .btn-icon {
    padding: 4px 8px;
  }
}

</style>
