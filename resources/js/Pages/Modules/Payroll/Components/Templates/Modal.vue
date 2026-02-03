<template>
    <div v-if="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="hide">
        <div class="modal-container modal-lg">
            <div class="modal-header">
                <h2 v-if="isAddEmployee">Add Employee to Payroll Template</h2>
                <h2 v-else>{{ isEditTitle ? 'Edit' : 'Create' }} Payroll Template</h2>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body">
                <form @submit.prevent="saveTemplate" class="payroll-template-form">
                    <!-- Template Name -->
                    <div class="form-group" v-if="!isAddEmployee">
                        <label class="form-label">Template Name</label>
                        <div class="input-wrapper">
                            <i class="ri-file-text-line input-icon"></i>
                            <input type="text" v-model="form.name" class="form-control" required placeholder="Enter template name">
                        </div>

                    </div>

                    <!-- Select Employees -->
                    <div class="form-group" v-if="!isEditTitle">
                        <label class="form-label">Select Employees</label>
                        <div class="employee-selection" v-if="paginatedEmployees.length > 0">
                            <div class="select-all">
                                <input type="checkbox" :checked="isAllSelected" @change="toggleSelectAll" id="select-all">
                                <label for="select-all" class="select-all-label">Select All</label>
                            </div>
                            <div class="search-employee">
                                <div class="input-wrapper">
                                    <i class="ri-search-line input-icon"></i>
                                    <input type="text" v-model="searchQuery" class="form-control" placeholder="Search employees...">
                                </div>
                            </div>
                            <div class="employee-list">
                                <div class="employee-item" v-for="employee in paginatedEmployees" :key="employee.id">
                                    <input type="checkbox" :value="employee.id" v-model="form.employee_ids" :id="'employee-' + employee.id">
                                    <label :for="'employee-' + employee.id" class="employee-label">{{ employee.lastname }}, {{ employee.firstname }} {{ employee.middlename }} <span class="employee-position">({{ employee.position?.title || 'No Position' }})</span></label>
                                </div>
                            </div>
                            <div v-if="totalPages > 1" class="pagination">
                                <button class="btn-pagination" @click="currentPage--" :disabled="currentPage === 1">
                                    <i class="ri-arrow-left-line"></i>
                                    Previous
                                </button>
                                <span class="pagination-info">Page {{ currentPage }} of {{ totalPages }}</span>
                                <button class="btn-pagination" @click="currentPage++" :disabled="currentPage === totalPages">
                                    Next
                                    <i class="ri-arrow-right-line"></i>
                                </button>
                            </div>
                        </div>
                        <div v-else>
                            <p>No employee available to select.</p>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="button" class="btn btn-cancel" @click="hide">
                            <i class="ri-close-line"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-save" :disabled="loading">
                            <i class="ri-save-line" v-if="!loading"></i>
                            <i class="ri-loader-4-line spinner" v-else></i>
                            {{ loading ? 'Processing...' : (isEditTitle ? 'Update Template' : 'Create Template') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios'

export default {
  props: {
    template: Object,
    isEditTitle: Boolean,
    isAddEmployee: Boolean,
  },

  data() {
    return {
      form: {
        name: '',
        employee_ids: []
      },
      employees: [],
      selectedEmployees: [],
      loading: false,
      showModal: false,
      searchQuery: '',
      currentPage: 1,
      perPage: 10
    }
  },

  mounted() {
    if (this.isEditTitle && this.template) {
      this.form = { ...this.template }
    }
    this.showModal = true
    this.fetchEmployees()
  },

  watch: {
    searchQuery() {
      this.resetPagination()
    }
  },

  computed: {
    isAllSelected() {
      return this.employees.length > 0 && this.form.employee_ids.length === this.employees.length
    },
    filteredEmployees() {
      if (!this.searchQuery) {
        return this.employees
      }
      const query = this.searchQuery.toLowerCase()
      return this.employees.filter(employee => {
        const fullName = `${employee.lastname}, ${employee.firstname} ${employee.middlename || ''}`.toLowerCase()
        return fullName.includes(query)
      })
    },
    paginatedEmployees() {
      const start = (this.currentPage - 1) * this.perPage
      const end = start + this.perPage
      return this.filteredEmployees.slice(start, end)
    },
    totalPages() {
      return Math.ceil(this.filteredEmployees.length / this.perPage)
    }
  },

  methods: {
    async fetchEmployees() {
      try {
        const response = await axios.get('/payroll-templates/available-employees')
        this.employees = response.data.data || []
      } catch (error) {
        console.error('Error fetching employees:', error)
      }
    },

    toggleSelectAll() {
      if (this.isAllSelected) {
        this.form.employee_ids = []
      } else {
        this.form.employee_ids = this.employees.map(employee => employee.id)
      }
    },

    async saveTemplate() {
      this.loading = true

      try {
        if (this.isEditTitle) {
          await axios.put(`/payroll-templates/${this.template.id}`, this.form);
          this.$emit('saved')
        } else if (this.isAddEmployee) {
          await axios.post(`/payroll-templates/${this.template.id}/add-employees`, {
            payroll_template_id: this.template.id,
            employee_ids: this.form.employee_ids
          });
          // Emit the added employees
          const addedEmployees = this.employees.filter(employee => this.form.employee_ids.includes(employee.id));
          this.$emit('saved', addedEmployees)
        } else {
          await axios.post('/payroll-templates', this.form);
          this.$emit('saved')
        }

        this.hide()
      } finally {
        this.loading = false
      }
    },

    hide() {
      this.showModal = false
      this.$emit('close')
    },

    resetPagination() {
      this.currentPage = 1
    }
  }
}
</script>

<style scoped>
/* Modal Overlay */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1050;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    padding: 15px;
}

.modal-overlay.active {
    opacity: 1;
    visibility: visible;
}

/* Modal Container */
.modal-container {
    background: white;
    border-radius: 16px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    width: 100%;
    max-width: 500px;
    overflow: hidden;
    transform: translateY(25px) scale(0.95);
    transition: all 0.3s ease;
}

.modal-container.modal-md {
    max-width: 600px;
}

.modal-overlay.active .modal-container {
    transform: translateY(0) scale(1);
}

/* Modal Header */
.modal-header {
    background: #3a8674;
    color: white;
    padding: 0.875rem 1.25rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    font-weight: 700;
    font-size: 1.1rem;
    margin: 0;
}

.close-btn {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1.1rem;
}

.close-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg);
}

/* Modal Body */
.modal-body {
    padding: 1.5rem;
    max-height: 75vh;
    overflow-y: auto;
}

/* Form Group */
.form-group {
    margin-bottom: 1.25rem;
}

.form-label {
    display: block;
    margin-bottom: 0.375rem;
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.9rem;
}

/* Input Wrapper */
.input-wrapper {
    position: relative;
}

.input-icon {
    position: absolute;
    left: 0.875rem;
    top: 50%;
    transform: translateY(-50%);
    color: #7f8c8d;
    font-size: 1.1rem;
    z-index: 1;
}

.form-control {
    width: 100%;
    padding: 0.7rem 0.875rem 0.7rem 2.75rem;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    background-color: white;
}

.form-control:focus {
    outline: none;
    border-color: #2e8b57;
    box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.1);
}

.form-control[readonly] {
    background-color: #f8f9fa;
    cursor: not-allowed;
}

.form-control[rows] {
    padding-top: 0.7rem;
    padding-bottom: 0.7rem;
    resize: vertical;
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    margin-top: 1.5rem;
}

.btn {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.8125rem;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.btn-cancel {
    background-color: transparent;
    color: #7f8c8d;
    border: 1px solid #e9ecef;
}

.btn-cancel:hover {
    background-color: #f8f9fa;
    border-color: #7f8c8d;
}

.btn-save {
    background: #3a8674;
    color: white;
    box-shadow: 0 4px 12px rgba(46, 139, 87, 0.3);
}

.btn-save:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(46, 139, 87, 0.4);
}

.btn-save:disabled {
    background: #95a5a6;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* Spinner Animation */
.spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}

/* Employee Selection */
.employee-selection {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 1rem;
    background-color: #f8f9fa;
}

.select-all {
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #dee2e6;
}

.select-all input[type="checkbox"] {
    margin-right: 0.5rem;
}

.select-all-label {
    font-weight: 600;
    color: #2c3e50;
    cursor: pointer;
}

.search-employee {
    margin-bottom: 1rem;
}

.employee-list {
    max-height: 300px;
    overflow-y: auto;
}

.employee-item {
    display: flex;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f1f3f4;
}

.employee-item:last-child {
    border-bottom: none;
}

.employee-item input[type="checkbox"] {
    margin-right: 0.75rem;
    transform: scale(1.1);
}

.employee-label {
    font-size: 0.9rem;
    color: #495057;
    cursor: pointer;
    flex: 1;
}

.employee-position {
    font-style: italic;
    color: #6c757d;
    font-size: 0.85rem;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.75rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #dee2e6;
}

.btn-pagination {
    padding: 0.375rem 0.75rem;
    font-size: 0.8125rem;
    background-color: #3a8674;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.btn-pagination:hover:not(:disabled) {
    background-color: #2e6b5a;
    transform: translateY(-1px);
}

.btn-pagination:disabled {
    background-color: #95a5a6;
    cursor: not-allowed;
    transform: none;
}

.pagination-info {
    font-size: 0.875rem;
    color: #495057;
    font-weight: 600;
}

/* Responsive Design */
@media (max-width: 768px) {
    .modal-header {
        padding: 0.75rem 1rem;
    }

    .modal-header h2 {
        font-size: 1rem;
    }

    .modal-body {
        padding: 1.25rem;
    }

    .form-actions {
        flex-direction: column-reverse;
        gap: 0.625rem;
    }

    .btn {
        width: 100%;
        justify-content: center;
    }

    .employee-list {
        max-height: 200px;
    }
}

@media (max-width: 480px) {
    .modal-overlay {
        padding: 10px;
    }

    .modal-header {
        padding: 0.625rem 0.875rem;
    }

    .modal-header h2 {
        font-size: 0.95rem;
    }

    .modal-body {
        padding: 1rem;
    }

    .form-control {
        font-size: 0.9rem;
        padding: 0.625rem 0.75rem 0.625rem 2.5rem;
    }

    .btn {
        padding: 0.5rem 0.875rem;
        font-size: 0.75rem;
    }

    .employee-selection {
        padding: 0.75rem;
    }

    .employee-list {
        max-height: 150px;
    }
}
</style>
