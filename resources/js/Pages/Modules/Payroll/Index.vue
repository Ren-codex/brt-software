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
  methods: {
    toggleSidebar() {
      this.isSidebarCollapsed = !this.isSidebarCollapsed;
    },
    changeTab(tab) {
      this.activeTab = tab;
      localStorage.setItem('payroll_active_tab', tab);
    },
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
