<template>
  <div>
    <Head title="Payroll" />
    <PageHeader title="Payroll Management" pageTitle="List" />

    <div class="inventory-container">
      <!-- Minimal Vertical Tabs -->
      <div class="inventory-sidebar" :class="{ 'sidebar-collapsed': isSidebarCollapsed }">
        <div class="inventory-sidebar-header">
          <i class="ri-money-dollar-circle-line"></i>
          <h4 v-if="!isSidebarCollapsed">Payroll</h4>
          <button class="sidebar-toggle-btn" @click="toggleSidebar" title="Toggle Sidebar">
            <i :class="isSidebarCollapsed ? 'ri-arrow-right-s-line' : 'ri-arrow-left-s-line'"></i>
          </button>
        </div>

        <div class="inventory-sidebar-tabs">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            class="inventory-sidebar-tab"
            :class="{ 'inventory-tab-active': activeTab === tab.id }"
            @click="changeTab(tab.id)"
          >
            <div class="inventory-tab-icon">
              <i :class="tab.icon"></i>
            </div>
            <div class="inventory-tab-text" v-if="!isSidebarCollapsed">
              <span class="inventory-tab-title">{{ tab.label }}</span>
              <span class="inventory-tab-subtitle">{{ tab.description }}</span>
            </div>
          </button>
        </div>

        <div class="inventory-sidebar-footer" v-if="!isSidebarCollapsed">
          <div class="inventory-stats">
            <div class="inventory-stat-item">
              <i class="ri-money-dollar-circle-line"></i>
              <span>{{ payrolls.length || 0 }} Payrolls</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Content -->
      <div class="inventory-main">
        <!-- Tab Content -->
        <div>
          <transition name="inventory-fade" mode="out-in">
            <div :key="activeTab" class="inventory-tab-content">
              <div v-if="activeTab === 'payroll_management'" class="shadow-sm p-3">
                <PayrollManagement :dropdowns="dropdowns" />
              </div>
              <div v-if="activeTab === 'payroll_templates'" class="shadow-sm p-3">
                <PayrollTemplate :dropdowns="dropdowns" />
              </div>
              <div v-if="activeTab === 'payroll_settings'" class="shadow-sm p-3">
                <PayrollSettings :dropdowns="dropdowns" />
              </div>
              <div v-if="activeTab === 'sales_incentives'" class="shadow-sm p-3">
                <SalesIncentives :dropdowns="dropdowns" />
              </div>
            </div>
          </transition>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import _ from 'lodash';
import PageHeader from '@/Shared/Components/PageHeader.vue';
import PayrollManagement from './Components/Payrolls/Index.vue';
import PayrollSettings from './Components/Settings/Index.vue';
import PayrollTemplate from './Components/Templates/Index.vue';
import SalesIncentives from './Components/SalesIncentives/Index.vue';

export default {
  components: { PageHeader, PayrollManagement, PayrollSettings, PayrollTemplate, SalesIncentives },
  props: ['dropdowns'],
  data() {
    return {
      isSidebarCollapsed: false,
      activeTab: localStorage.getItem('payroll_active_tab') || 'payroll_management',
      payrolls: [],
      meta: null,
      filters: {
        status: '',
        month: '',
        search: '',
        page: 1
      },
      showCreateModal: false,
      showEditModal: false,
      selectedPayroll: null,
      tabs: [
        {
          id: 'payroll_management',
          label: 'Payroll',
          icon: 'ri-currency-line',
          description: 'Manage payroll records'
        },
        {
          id: 'payroll_templates',
          label: 'Payroll Templates',
          icon: 'ri-file-list-3-line',
          description: 'Manage payroll templates'
        },
        {
          id: 'payroll_settings',
          label: 'Settings',
          icon: 'ri-settings-2-line',
          description: 'Manage payroll settings'
        },
        {
          id: 'sales_incentives',
          label: 'Sales Incentives',
          icon: 'ri-trophy-line',
          description: 'Manage sales incentives'
        },
      ]
    }
  },
  watch: {
    'activeTab'(newVal) {
      this.fetchPayrolls();
    }
  },
  mounted() {
    this.fetchPayrolls()
    this.debouncedSearch = _.debounce(this.fetchPayrolls, 500);
  },
  methods: {
    toggleSidebar() {
      this.isSidebarCollapsed = !this.isSidebarCollapsed;
    },
    changeTab(tab) {
      this.activeTab = tab;
      localStorage.setItem('payroll_active_tab', tab);
    },
    async fetchPayrolls() {
      try {
        const params = {
          ...this.filters,
          page: this.filters.page
        }

        const response = await axios.get('/api/payrolls', { params })
        if (response.data) {
          this.payrolls = response.data.data || []
          this.meta = {
            current_page: response.data.current_page || 1,
            last_page: response.data.last_page || 1,
            total: response.data.total || 0,
            links: {
              prev: response.data.prev_page_url || null,
              next: response.data.next_page_url || null
            }
          }
        } else {
          this.payrolls = []
          this.meta = null
        }
      } catch (error) {
        console.error('Error fetching payrolls:', error)
        this.$toast.error('Failed to load payroll data')
        this.payrolls = []
        this.meta = null
      }
    },
    changePage(page) {
      this.filters.page = page
      this.fetchPayrolls()
    },
    viewPayroll(payroll) {
      this.selectedPayroll = payroll
      // You can implement a detailed view modal here
      this.$toast.info(`Viewing payroll for ${payroll.employee?.name}`)
    },
    editPayroll(payroll) {
      this.selectedPayroll = { ...payroll }
      this.showEditModal = true
    },
    confirmDelete(payroll) {
      if (confirm(`Are you sure you want to delete payroll for ${payroll.employee?.name}?`)) {
        this.deletePayroll(payroll.id)
      }
    },
    async deletePayroll(id) {
      try {
        await axios.delete(`/api/payrolls/${id}`)
        this.$toast.success('Payroll deleted successfully')
        this.fetchPayrolls()
      } catch (error) {
        console.error('Error deleting payroll:', error)
        this.$toast.error('Failed to delete payroll')
      }
    },
    closeModal() {
      this.showCreateModal = false
      this.showEditModal = false
      this.selectedPayroll = null
    },
    handleSaved() {
      this.closeModal()
      this.fetchPayrolls()
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
    getStatusColor(status) {
      const colors = {
        draft: 'secondary',
        pending: 'warning',
        approved: 'info',
        paid: 'success'
      }
      return colors[status] || 'secondary'
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
.inventory-sidebar-header {
  position: relative;
}

.sidebar-toggle-btn {
  position: absolute;
  top: 15px;
  right: 15px;
  background: transparent;
  border: 1px solid rgba(108, 117, 125, 0.3);
  border-radius: 6px;
  color: #6c757d;
  font-size: 16px;
  cursor: pointer;
  padding: 6px 8px;
  transition: all 0.3s ease;
  z-index: 10;
}

.sidebar-toggle-btn:hover {
  background: rgba(108, 117, 125, 0.1);
  color: #495057;
  border-color: rgba(108, 117, 125, 0.5);
}

.inventory-sidebar {
  transition: width 0.3s ease;
}

.inventory-sidebar.sidebar-collapsed {
  width: 80px;
}

.inventory-sidebar.sidebar-collapsed .inventory-sidebar-header h4 {
  display: none;
}

.inventory-sidebar.sidebar-collapsed .inventory-sidebar-tab {
  justify-content: center;
  padding: 12px;
  transition: all 0.3s ease;
}

.inventory-sidebar.sidebar-collapsed .inventory-tab-text {
  display: none;
}

.inventory-sidebar-tab {
  transition: all 0.3s ease;
}

.inventory-sidebar-tab:hover {
  background: rgba(0, 123, 255, 0.1);
  transform: translateX(2px);
}

.inventory-sidebar.sidebar-collapsed .inventory-sidebar-tab:hover {
  transform: none;
}
</style>
