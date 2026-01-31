<template>
    <BRow>
        <div class="col-lg-12 mb-4">
            <div class="library-card">
                <div class="library-card-header">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="header-icon">
                                <i class="ri-money-dollar-circle-line fs-24"></i>
                            </div>
                            <div>
                                <h4 class="header-title mb-1">Payroll Management</h4>
                                <p class="header-subtitle mb-0">A comprehensive list of Payrolls</p>
                            </div>
                        </div>
                        <button class="create-btn" @click="showCreateModal = true">
                            <i class="ri-add-line"></i>
                            <span>Add Payroll</span>
                        </button>
                    </div>
                </div>
                <div class="card-body m-2 p-3">
                    <div class="search-section">
                        <div class="search-wrapper">
                            <i class="ri-search-line search-icon"></i>
                            <input type="text" v-model="localKeyword" @input="updateKeyword($event.target.value)"
                                placeholder="Search payrolls..." class="search-input">
                        </div>
                    </div>

                    <div class="table-responsive table-card">
                        <table class="table align-middle table-hover mb-0"
                            style="border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                            <thead style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                                <tr class="fs-12 fw-bold text-muted">
                                    <th style="width: 3%; border: none;">#</th>
                                    <th style="width: 15%;" class="text-center border-none">Pay Period</th>
                                    <th style="width: 20%;" class="text-center border-none">Employees</th>
                                    <th style="width: 10%;" class="text-center border-none">Total Basic Salary</th>
                                    <th style="width: 10%;" class="text-center border-none">Total Overtime</th>
                                    <th style="width: 10%;" class="text-center border-none">Total Deductions</th>
                                    <th style="width: 10%;" class="text-center border-none">Total Net Salary</th>
                                    <th style="width: 10%;" class="text-center border-none">Status</th>
                                    <th style="width: 12%;" class="text-center border-none">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="fs-12">
                                <template v-for="(payroll, index) in payrolls" :key="payroll.id">
                                    <tr @click="toggleRowExpansion(index)" :class="{
                                        'bg-primary bg-opacity-10 ': index === selectedRow,
                                        'cursor-pointer': true
                                    }" class="transition-all" style="transition: all 0.3s ease;">
                                        <td class="text-center">
                                            <i v-if="expandedRows.includes(index)"
                                                class="ri-arrow-down-s-line text-primary"></i>
                                            <i v-else class="ri-arrow-right-s-line text-muted"></i>
                                            {{ index + 1 }}
                                        </td>
                                        <td class="text-center fw-semibold">{{ formatDate(payroll.pay_period_start) }} - {{ formatDate(payroll.pay_period_end) }}</td>
                                        <td class="text-center">
                                            <div v-if="payroll.items && payroll.items.length > 0">
                                                <span v-for="(item, idx) in payroll.items.slice(0, 3)" :key="item.id" class="badge bg-secondary me-1">
                                                    {{ item.employee?.fullname || 'N/A' }}
                                                </span>
                                                <span v-if="payroll.items.length > 3" class="text-muted">+{{ payroll.items.length - 3 }} more</span>
                                            </div>
                                            <span v-else class="text-muted">No employees</span>
                                        </td>
                                        <td class="text-center">{{ formatCurrency(getTotalBasicSalary(payroll)) }}</td>
                                        <td class="text-center">{{ formatCurrency(getTotalOvertime(payroll)) }}</td>
                                        <td class="text-center">{{ formatCurrency(getTotalDeductions(payroll)) }}</td>
                                        <td class="text-center fw-bold">{{ formatCurrency(getTotalNetSalary(payroll)) }}</td>
                                        <td class="text-center">
                                            <span class="status-badge" :style="getStatusStyle(payroll.status)">
                                                {{ payroll.status }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-1">
                                                <b-button @click.stop="viewPayroll(payroll)" variant="outline-info"
                                                    v-b-tooltip.hover title="View" size="sm"
                                                    class="btn-icon rounded-circle">
                                                    <i class="ri-eye-line"></i>
                                                </b-button>
                                                <b-button @click.stop="editPayroll(payroll)" variant="outline-primary"
                                                    v-b-tooltip.hover title="Edit" size="sm"
                                                    class="btn-icon rounded-circle">
                                                    <i class="ri-pencil-fill"></i>
                                                </b-button>
                                                <b-button @click.stop="confirmDelete(payroll)" variant="outline-danger"
                                                    v-b-tooltip.hover title="Delete" size="sm"
                                                    class="btn-icon rounded-circle">
                                                    <i class="ri-trash-line"></i>
                                                </b-button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="expandedRows.includes(index)" style="background-color: #c4dad2e0;">
                                        <td colspan="9" class="p-0">
                                            <div class="p-4">
                                                <h6 class="text-primary mb-3">
                                                    <i class="ri-file-list-line me-2"></i>Payroll Details
                                                </h6>
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <div class="card border-0 shadow-sm">
                                                            <div class="card-body">
                                                                <h6 class="card-title text-muted small mb-2">Payroll Information</h6>
                                                                <p class="mb-1"><strong>Pay Period:</strong> {{ formatDate(payroll.pay_period_start) }} - {{ formatDate(payroll.pay_period_end) }}</p>
                                                                <p class="mb-1"><strong>Status:</strong> {{ payroll.status }}</p>
                                                                <p class="mb-0"><strong>Total Employees:</strong> {{ payroll.items?.length || 0 }}</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="card border-0 shadow-sm">
                                                            <div class="card-body">
                                                                <h6 class="card-title text-muted small mb-2">Employee Items</h6>
                                                                <div v-if="payroll.items && payroll.items.length > 0">
                                                                    <table class="table table-sm table-borderless mb-0">
                                                                        <thead>
                                                                            <tr>
                                                                                <th class="fw-semibold">Employee</th>
                                                                                <th class="fw-semibold">Basic Salary</th>
                                                                                <th class="fw-semibold">Net Salary</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            <tr v-for="item in payroll.items" :key="item.id">
                                                                                <td>{{ item.employee?.fullname || 'N/A' }}</td>
                                                                                <td>{{ formatCurrency(item.basic_salary || 0) }}</td>
                                                                                <td>{{ formatCurrency(item.net_salary || 0) }}</td>
                                                                            </tr>
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                                <p v-else class="text-muted mb-0">No items found</p>
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
                <div class="card-footer bg-light border-0 m-3">
                    <Pagination class="ms-2 me-2 mt-n1" v-if="meta" @fetch="fetchPayrolls" :lists="payrolls.length"
                        :links="links" :pagination="meta" />
                </div>
            </div>
        </div>
    </BRow>
    <!-- Create/Edit Modal -->
    <PayrollModal
      v-if="showCreateModal || showEditModal"
      :payroll="selectedPayroll"
      :is-edit="showEditModal"
      :dropdowns="dropdowns"
      @close="closeModal"
      @saved="handleSaved"
    />
</template>

<script>
import _ from 'lodash';
import axios from 'axios'
import PayrollModal from './Modal.vue'
import Pagination from "@/Shared/Components/Pagination.vue";

export default {
  components: { PayrollModal, Pagination },
  props: ['dropdowns'],
  data() {
    return {
      payrolls: [],
      meta: {},
      links: {},
      filter: {
        keyword: null
      },
      localKeyword: '',
      showCreateModal: false,
      showEditModal: false,
      selectedPayroll: null,
      selectedRow: null,
      expandedRows: [],
    }
  },
  watch: {
    "filter.keyword"(newVal) {
      this.checkSearchStr(newVal);
    }
  },
  created() {
  },
  methods: {
    checkSearchStr: _.debounce(function (string) {
    }, 300),
    updateKeyword(value) {
      this.filter.keyword = value;
    },
    toggleRowExpansion(index) {
      if (this.expandedRows.includes(index)) {
        this.expandedRows = this.expandedRows.filter(i => i !== index);
      } else {
        this.expandedRows.push(index);
      }
    },
    viewPayroll(payroll) {
      this.selectedPayroll = payroll
      // You can implement a detailed view modal here
      this.$toast.info(`Viewing payroll`)
    },
    editPayroll(payroll) {
      this.selectedPayroll = { ...payroll }
      this.showEditModal = true
    },
    confirmDelete(payroll) {
      if (confirm(`Are you sure you want to delete payroll?`)) {
        this.deletePayroll(payroll.id)
      }
    },
    closeModal() {
      this.showCreateModal = false
      this.showEditModal = false
      this.selectedPayroll = null
    },
    handleSaved() {
      this.closeModal()
    },
    formatDate(date) {
      return new Date(date).toLocaleDateString()
    },
    formatCurrency(amount) {
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
      }).format(amount)
    },
    getStatusStyle(status) {
      const statusMap = {
        pending: { bg_color: '#ffc107', text_color: '#000000' },
        approved: { bg_color: '#17a2b8', text_color: '#ffffff' },
        paid: { bg_color: '#28a745', text_color: '#ffffff' },
        cancelled: { bg_color: '#dc3545', text_color: '#ffffff' }
      };
      const statusInfo = statusMap[status] || { bg_color: '#6c757d', text_color: '#ffffff' };
      return {
        color: statusInfo.text_color,
        backgroundColor: statusInfo.bg_color,
        border: `1px solid ${statusInfo.bg_color}40`,
        boxShadow: `0 2px 4px ${statusInfo.bg_color}20`
      };
    },
    getTotalBasicSalary(payroll) {
      return payroll.items?.reduce((sum, item) => sum + (item.basic_salary || 0), 0) || 0
    },
    getTotalOvertime(payroll) {
      return payroll.items?.reduce((sum, item) => sum + ((item.overtime_hours || 0) * (item.overtime_rate || 0)), 0) || 0
    },
    getTotalDeductions(payroll) {
      return payroll.items?.reduce((sum, item) => sum + (item.deductions || 0), 0) || 0
    },
    getTotalNetSalary(payroll) {
      return payroll.items?.reduce((sum, item) => sum + (item.net_salary || 0), 0) || 0
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
  text-transform: uppercase;
  letter-spacing: 0.5px;
  transition: all 0.3s ease;
  cursor: default;
}
</style>
