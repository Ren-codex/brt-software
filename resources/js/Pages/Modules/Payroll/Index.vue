<template>
  <div>
    <Head title="Payroll" />
    <PageHeader title="Payroll Management" pageTitle="List" />

    <div class="inventory-container">
      <!-- Minimal Vertical Tabs -->
      <div class="inventory-sidebar" :class="{ 'sidebar-collapsed': isSidebarCollapsed }">
        <div class="inventory-sidebar-header">
          <i class="ri-money-peso-circle-line"></i>
          <h4 v-if="!isSidebarCollapsed">Payroll</h4>
          <button class="sidebar-toggle-btn" @click="toggleSidebar" title="Toggle Sidebar">
            <i :class="isSidebarCollapsed ? 'ri-arrow-right-s-line' : 'ri-arrow-left-s-line'"></i>
          </button>
        </div>

        <div class="inventory-sidebar-tabs">
          <button
            v-for="tab in visibleTabs"
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
              <i class="ri-money-peso-circle-line"></i>
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
            <div :key="currentView" class="inventory-tab-content">
              <div v-if="currentView === 'loanDetails' && selectedLoan" class="payroll-details-container">
                <LoanView
                  :loan="selectedLoan"
                  :dropdowns="dropdowns"
                  @back="backToLoanList"
                  @view="openLoanDetails"
                />
              </div>

              <!-- Payroll Management List -->
              <div v-if="activeTab === 'payroll_management' && currentView === 'list'" class="shadow-sm p-3">
                <PayrollManagement :dropdowns="dropdowns" @view="openPayrollDetails" />
              </div>
              <div v-if="activeTab === 'payroll_templates' && currentView === 'list'" class="shadow-sm p-3">
                <PayrollTemplate :dropdowns="dropdowns" />
              </div>
              <div v-if="activeTab === 'payroll_items' && currentView === 'list'" class="shadow-sm p-3">
                <PayrollItems :dropdowns="dropdowns" />
              </div>
              <div v-if="activeTab === 'sales_incentives' && currentView === 'list'" class="shadow-sm p-3">
                <SalesIncentives :dropdowns="dropdowns" />
              </div>
              <div v-if="activeTab === 'loan_management' && currentView === 'list'" class="shadow-sm p-3">
                <LoanManagement ref="loanManagement" :dropdowns="dropdowns" @view="openLoanDetails" />
              </div>
            </div>
          </transition>
        </div>
      </div>
    </div>

    <!-- A payroll opens over the list instead of replacing it, so closing it
         returns to the same page, search and sort the user was on. The
         overlay carries .modal-overlay.active so the list's background
         refresh pauses while it is open, as it does for every other modal. -->
    <div v-if="selectedPayroll" class="modal-overlay active payroll-view-overlay"
      :class="{ 'is-fullscreen': payrollFullscreen }" @click.self="backToList">
      <div class="payroll-view-dialog" role="dialog" aria-modal="true">
        <!-- Window controls in the corner where people look for them. The bar
             sticks to the top, so they stay in reach while a long payroll
             scrolls underneath. -->
        <div class="payroll-view-bar">
          <span class="payroll-view-bar-title">
            <i class="ri-money-peso-circle-line"></i> Payroll {{ selectedPayroll.payroll_no }}
          </span>
          <div class="payroll-view-bar-actions">
            <button type="button" class="window-btn window-btn-text" v-b-tooltip.hover title="How payroll works"
              @click="openPayrollGuide">
              <i class="ri-book-open-line"></i> <span>Guide</span>
            </button>
            <button type="button" class="window-btn" v-b-tooltip.hover
              :title="payrollFullscreen ? 'Exit full screen' : 'Full screen'"
              @click="payrollFullscreen = !payrollFullscreen">
              <i :class="payrollFullscreen ? 'ri-fullscreen-exit-line' : 'ri-fullscreen-line'"></i>
            </button>
            <button type="button" class="window-btn window-btn-close" v-b-tooltip.hover title="Close" @click="backToList">
              <i class="ri-close-line"></i>
            </button>
          </div>
        </div>
        <PayrollView
          :payroll="selectedPayroll"
          :dropdowns="dropdowns"
          :fullscreen="payrollFullscreen"
          @toggle-fullscreen="payrollFullscreen = !payrollFullscreen"
          @back="backToList"
          @toast="showToast"
        />
      </div>
    </div>

    <!-- Toast -->
    <div v-if="isToastVisible" class="inventory-toast">
      <div class="inventory-toast-content">
        <i class="ri-check-line"></i>
        {{ toastMessage }}
      </div>
    </div>
  </div>
</template>

<script>
import _ from 'lodash';
import PageHeader from '@/Shared/Components/PageHeader.vue';
import PayrollManagement from './Components/Payrolls/Index.vue';
import PayrollView from './Components/Payrolls/View.vue';
import { openGuide } from '@/Shared/Components/ModuleGuide/moduleGuides.js';
import PayrollSettings from './Components/Settings/Index.vue';
import PayrollTemplate from './Components/Templates/Index.vue';
import SalesIncentives from './Components/SalesIncentives/Index.vue';
import LoanManagement from './Components/Loans/Index.vue';
import LoanView from './Components/Loans/View.vue';
import PayrollItems from './Components/Items/Index.vue';

export default {
  components: { PageHeader, PayrollManagement, PayrollView, PayrollItems, PayrollTemplate, SalesIncentives, LoanManagement, LoanView },
  props: ['dropdowns'],
  data() {
    return {
      isSidebarCollapsed: false,
      activeTab: localStorage.getItem('payroll_active_tab') || 'payroll_management',
      currentView: 'list',
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
      // Whether the payroll modal fills the screen. Kept while moving between
      // payrolls, so someone who prefers it large does not re-click each time.
      payrollFullscreen: false,
      selectedLoan: null,
      isToastVisible: false,
      toastMessage: '',
      tabs: [
        {
          id: 'payroll_management',
          label: 'Payroll',
          icon: 'ri-currency-line',
          description: 'Manage payroll records'
        },
        {
          id: 'payroll_templates',
          label: 'Payroll Groups',
          icon: 'ri-file-list-3-line',
          description: 'Manage payroll groups'
        },
        {
          id: 'payroll_items',
          label: 'Payroll Items',
          icon: 'ri-settings-2-line',
          description: 'Manage payroll items'
        },
        {
          id: 'sales_incentives',
          label: 'Sales Incentives',
          icon: 'ri-trophy-line',
          description: 'Manage sales incentives'
        },
        {
          id: 'loan_management',
          label: 'Loans',
          icon: 'ri-bank-card-line',
          description: 'Manage employee loans'
        },
      ]
    }
  },
  computed: {
    visibleTabs() {
      // sales_incentives is outside this pilot's submodule catalog (spec
      // §14) — stays visible to anyone who can see Payroll at all.
      const tabToSubmodule = {
        payroll_management: 'payroll_processing',
        payroll_templates: 'payroll_templates',
        loan_management: 'loans',
        payroll_items: 'payroll_settings',
      };
      return this.tabs.filter((tab) => {
        const submoduleKey = tabToSubmodule[tab.id];
        if (!submoduleKey) {
          return true;
        }
        return this.canAny('payroll', submoduleKey);
      });
    },
  },
  created() {
    // activeTab is restored from localStorage and defaults to payroll_management,
    // neither of which is guaranteed to be a tab this holder can see -- a
    // loans-only holder would land on a hidden tab and get an empty page.
    if (!this.visibleTabs.some((tab) => tab.id === this.activeTab)) {
      this.activeTab = this.visibleTabs.length ? this.visibleTabs[0].id : null;
    }
  },
  methods: {
    toggleSidebar() {
      this.isSidebarCollapsed = !this.isSidebarCollapsed;
    },
    changeTab(tab) {
      this.activeTab = tab;
      localStorage.setItem('payroll_active_tab', tab);
      this.currentView = 'list';
      this.selectedPayroll = null;
      this.selectedLoan = null;
      this.filters.search = '';
    },
    // Opens the guide book on the payroll chapter, at the stages page, with
    // the open payroll's own stage marked.
    openPayrollGuide() {
      openGuide('payroll', this.selectedPayroll?.status?.slug);
    },
    // Opens the payroll in a modal; the list stays where it is underneath.
    openPayrollDetails(payroll) {
      this.selectedPayroll = payroll;
    },
    openLoanDetails(loan) {
      this.selectedLoan = loan;
      this.currentView = 'loanDetails';
    },
    backToList() {
      this.currentView = 'list';
      this.selectedPayroll = null;
      this.selectedLoan = null;
      // Optionally fetch updated data
      // this.fetchPayrolls();
    },
    backToLoanList() {
      this.currentView = 'list';
      this.selectedLoan = null;
      this.fetchLoans();
      this.changeTab('loan_management');
    },
    showToast(message) {
      this.toastMessage = message;
      this.isToastVisible = true;
      setTimeout(() => {
        this.isToastVisible = false;
      }, 3000);
    },
  }
}
</script>

<style scoped>
/* The payroll view modal. Not .modal-container: that class animates with a
   transform, and a transformed box would trap the approve and edit dialogs
   that open from inside the payroll, which are fixed to the screen. */
.payroll-view-overlay {
  /* Centred both ways. The dialog scrolls inside itself when the payroll is
     taller than the screen, so centring never pushes its top out of reach. */
  align-items: center;
  justify-content: center;
  padding: 1.25rem;
  /* The shared overlay blurs what is behind it. A backdrop-filter also traps
     fixed-position children the way a transform does, so it is switched off
     here for the same reason: the dialogs opened from inside must stay pinned
     to the screen even when this overlay is scrolled. */
  backdrop-filter: none;
  -webkit-backdrop-filter: none;
  background: rgba(15, 23, 42, 0.6);
}

.payroll-view-bar {
  position: sticky;
  top: 0;
  z-index: 5;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  /* Pulled out to the dialog's edges so content scrolling beneath it is
     covered right up to the corners. */
  margin: 0 -1.1rem 0.9rem;
  padding: 0.6rem 0.9rem 0.6rem 1.1rem;
  background: var(--brt-c-f3f6f5);
  border-bottom: 1px solid var(--brt-c-dfe9e6);
  border-radius: 16px 16px 0 0;
}

.payroll-view-bar-title {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--brt-ink);
}

.payroll-view-bar-title i {
  color: var(--brt-primary);
  font-size: 1.1rem;
}

.payroll-view-bar-actions {
  display: flex;
  gap: 0.4rem;
}

.window-btn {
  width: 34px;
  height: 34px;
  border: 1px solid var(--brt-border);
  border-radius: 10px;
  background: #fff;
  color: var(--brt-ink);
  font-size: 1.05rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s ease;
}

.window-btn-text {
  width: auto;
  gap: 0.35rem;
  padding: 0 0.7rem;
  font-size: 0.8rem;
  font-weight: 600;
}

.window-btn-text i {
  font-size: 1rem;
}

.window-btn:hover {
  background: var(--brt-primary);
  border-color: var(--brt-primary);
  color: #fff;
}

.window-btn-close:hover {
  background: #dc2626;
  border-color: #dc2626;
}

.payroll-view-dialog {
  width: 100%;
  max-width: 1280px;
  max-height: calc(100vh - 2.5rem);
  overflow-y: auto;
  margin: auto;
  padding: 0 1.1rem 0.25rem;
  border-radius: 16px;
  background: var(--brt-c-f3f6f5);
  box-shadow: 0 24px 60px -12px rgba(var(--brt-c-10322e-rgb), 0.45);
}

/* Full screen: edge to edge, no rounded corners, nothing of the page behind. */
.payroll-view-overlay.is-fullscreen {
  padding: 0;
}

.payroll-view-overlay.is-fullscreen .payroll-view-dialog {
  max-width: none;
  height: 100vh;
  max-height: none;
  border-radius: 0;
  box-shadow: none;
}

.payroll-view-overlay.is-fullscreen .payroll-view-bar {
  border-radius: 0;
}

@media (max-width: 767.98px) {
  .payroll-view-overlay {
    padding: 0.5rem;
  }

  .payroll-view-dialog {
    max-height: calc(100vh - 1rem);
  }

  .payroll-view-dialog {
    padding: 0 0.6rem 0;
    border-radius: 12px;
  }

  .payroll-view-bar {
    margin: 0 -0.6rem 0.6rem;
    padding: 0.5rem 0.6rem;
    border-radius: 12px 12px 0 0;
  }
}

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

.inventory-toast {
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

.inventory-toast-content {
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

.payroll-details-container {
  margin: 20px;
}
</style>
