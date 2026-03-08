<template>
  <div>
    <Head title="Sales" />
    <PageHeader title="Sales Management" pageTitle="List" />



    <div class="inventory-container">
      <!-- Minimal Vertical Tabs -->
      <div class="inventory-sidebar" :class="{ 'sidebar-collapsed': isSidebarCollapsed }">
        <div class="inventory-sidebar-header">
          <i class="ri-shopping-cart-2-line"></i>
          <h4 v-if="!isSidebarCollapsed">Sales</h4>
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
              <i class="ri-shopping-cart-2-line"></i>
              <span>{{ listCustomers.length || 0 }} Orders</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Content -->
      <div class="inventory-main">
        <!-- Tab Content -->
        <div class="inventory-main-content">
          <transition name="inventory-fade" mode="out-in">
            <div :key="currentView" class="inventory-tab-content">
              <!-- Sales Orders -->
              <div v-if="activeTab === 'sales_orders'" class="row">
                <div :class="isRightSidebarCollapsed ? 'col-md-12' : 'col-md-9'">
                 
                    <SalesOrders :dropdowns="dropdowns" :metrics="metrics" />
                 
                </div>
                <div v-show="!isRightSidebarCollapsed" class="col-md-3">
                  <QuickStatsSidebar :activeTab="activeTab" :metrics="metrics" 
                    :listInvoices="listInvoices" :listReceipts="listReceipts" 
                    :listRemittances="listRemittances" :isRightSidebarCollapsed="isRightSidebarCollapsed"
                    @toggle="toggleRightSidebar" />
                </div>
              </div>

              <!-- Sales Returns -->
              <div v-if="activeTab === 'sales_returns'" class="row">
                <div :class="isRightSidebarCollapsed ? 'col-md-12' : 'col-md-9'">
                 
                    <SalesReturns :dropdowns="dropdowns" :metrics="metrics" />
                
                </div>
                <div v-show="!isRightSidebarCollapsed" class="col-md-3">
                  <QuickStatsSidebar :activeTab="activeTab" :metrics="metrics"
                    :listInvoices="listInvoices" :listReceipts="listReceipts" 
                    :listRemittances="listRemittances" :isRightSidebarCollapsed="isRightSidebarCollapsed"
                    @toggle="toggleRightSidebar" />
                </div>
              </div>

              <!-- AR Invoices -->
              <div v-if="activeTab === 'ar_invoices'" class="row">
                <div :class="isRightSidebarCollapsed ? 'col-md-12' : 'col-md-9'">
              
                    <ARInvoices :dropdowns="dropdowns" :isExternal="false"/>
                 
                </div>
                <div v-show="!isRightSidebarCollapsed" class="col-md-3">
                  <QuickStatsSidebar :activeTab="activeTab" :metrics="metrics"
                    :listInvoices="listInvoices" :listReceipts="listReceipts" 
                    :listRemittances="listRemittances" :isRightSidebarCollapsed="isRightSidebarCollapsed"
                    @toggle="toggleRightSidebar" />
                </div>
              </div>

              <!-- Receipts -->
              <div v-if="activeTab === 'receipts'" class="row">
                <div :class="isRightSidebarCollapsed ? 'col-md-12' : 'col-md-9'">
                
                     <Receipts :dropdowns="dropdowns"/>
               
                </div>
                <div v-show="!isRightSidebarCollapsed" class="col-md-3">
                  <QuickStatsSidebar :activeTab="activeTab" :metrics="metrics"
                    :listInvoices="listInvoices" :listReceipts="listReceipts" 
                    :listRemittances="listRemittances" :isRightSidebarCollapsed="isRightSidebarCollapsed"
                    @toggle="toggleRightSidebar" />
                </div>
              </div>

              <!-- Remittances -->
              <div v-if="activeTab === 'remittance'" class="row">
                <div :class="isRightSidebarCollapsed ? 'col-md-12' : 'col-md-9'">
                  
                    <Remittances :dropdowns="dropdowns" />
                 
                </div>
                <div v-show="!isRightSidebarCollapsed" class="col-md-3">
                  <QuickStatsSidebar :activeTab="activeTab" :metrics="metrics"
                    :listInvoices="listInvoices" :listReceipts="listReceipts" 
                    :listRemittances="listRemittances" :remittanceMetrics="remittanceMetrics"
                    :isRightSidebarCollapsed="isRightSidebarCollapsed"
                    @toggle="toggleRightSidebar" />
                </div>
              </div>

              <div v-if="activeTab === 'sales-reports'" class="shadow-sm p-3">
                <SalesReports :locations="dropdowns.locations || []" />
              </div>
            </div>
          </transition>
        </div>
      </div>
    </div>

    <!-- Floating Quick Stats Toggle Button -->
    <button class="quick-stats-floating-toggle" @click="toggleRightSidebar"
      :title="isRightSidebarCollapsed ? 'Show Quick Stats' : 'Hide Quick Stats'">
      <span>Quick Stats</span>
    </button>

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
import Pagination from '@/Shared/Components/Pagination.vue';
import SalesOrders from "@/Pages/Modules/Sales/Components/SalesOrders/Index.vue";
import SalesReturns from "@/Pages/Modules/Sales/Components/SalesReturns/Index.vue";
import ARInvoices from "@/Pages/Modules/Sales/Components/ARInvoices/Index.vue";
import Receipts from "@/Pages/Modules/Sales/Components/Receipts/Index.vue";
import Remittances from "@/Pages/Modules/Sales/Components/Remittances/Index.vue";
import SalesReports from "@/Pages/Modules/Sales/Components/SalesReports/Index.vue";
import QuickStatsSidebar from "@/Pages/Modules/Sales/Components/QuickStatsSidebar.vue";

export default {
  components: { PageHeader, Pagination, SalesOrders, SalesReturns, ARInvoices, Receipts, Remittances, SalesReports, QuickStatsSidebar },
  props: ['dropdowns'],
  data() {
    return {
      isSidebarCollapsed: false,
      isRightSidebarCollapsed: true,
      activeTab: localStorage.getItem('sales_active_tab') || 'sales_orders',
      currentView: 'list',
      filter: {
        keyword: '',
      },
      listCustomers: [],
      listInvoices: [],
      listReceipts: [],
      listRemittances: [],
      meta: null,
      links: null,
      selectedRow: null,
      isToastVisible: false,
      toastMessage: '',
      metrics: {
        total_sales_orders: 0,
        today_orders: 0,
        total_revenue: 0,
        pending_orders: 0,
        cancelled_orders: 0
      },
      remittanceMetrics: {
        total_remittances: 0,
        today_remittances: 0,
        open_remittances: 0,
        total_amount_remitted: 0
      },
      tabs: [
        {
          id: 'sales_orders',
          label: 'Sales Orders',
          icon: 'ri-shopping-bag-line',
          description: 'Manage sales orders'
        },
        {
          id: 'sales_returns',
          label: 'Sales Returns',
          icon: 'ri-shopping-bag-line',
          description: 'Manage sales returns'
        },
        {
          id: 'ar_invoices',
          label: 'AR Invoices',
          icon: 'ri-file-list-line',
          description: 'Account receivable invoices'
        },
        {
          id: 'receipts',
          label: 'Receipts',
          icon: 'ri-money-dollar-circle-line',
          description: 'Payment receipts'
        },
        {
          id: 'remittance',
          label: 'Remittances',
          icon: 'ri-bank-line',
          description: 'Remittance records'
        },
        {
          id: 'sales-reports',
          label: 'Sales Report',
          icon: 'ri-bar-chart-line',
          description: 'Sales reports'
        },
      ]
    };
  },
  watch: {
    activeTab(newVal) {
      this.currentView = 'list';
      this.fetchSalesOrders();
    }
  },
  created() {
    this.fetchSalesOrders();
    this.fetchMetrics();
    this.fetchRemittanceMetrics();
    
    // Check URL params for tab
    const params = new URLSearchParams(window.location.search);
    const tabParam = params.get('tab');
    
    if (tabParam && this.tabs.some(tab => tab.id === tabParam)) {
      this.activeTab = tabParam;
      this.changeTab(this.activeTab);
    }
  },
  methods: {
    toggleSidebar() {
      this.isSidebarCollapsed = !this.isSidebarCollapsed;
    },
    toggleRightSidebar() {
      this.isRightSidebarCollapsed = !this.isRightSidebarCollapsed;
    },
    changeTab(tab) {
      this.activeTab = tab;
      localStorage.setItem('sales_active_tab', tab);
      this.currentView = 'list';
      this.selectedRow = null;
      this.filter.keyword = '';

      // Update URL params
      const url = new URL(window.location);
      url.searchParams.set('tab', tab);
      window.history.pushState({}, '', url);
    },
    
    updateKeyword(keyword) {
      this.filter.keyword = keyword;
      this.handleSearch();
    },

    handleSearch: _.debounce(function () {
      this.fetchSalesOrders();
    }, 500),

    showToast(message) {
      this.toastMessage = message;
      this.isToastVisible = true;
      setTimeout(() => {
        this.isToastVisible = false;
      }, 3000);
    },
    fetchSalesOrders(page_url) {
      if (this.activeTab === 'sales_orders') {
        // Handle different types of page_url (string URL or pagination object)
        let url = '/customers';
        if (typeof page_url === 'string' && page_url) {
          url = page_url;
        } else if (page_url && page_url.url) {
          url = page_url.url;
        }

        axios
          .get(url, {
            params: {
              keyword: this.filter.keyword,
              count: 10,
              option: 'lists',
            },
          })
          .then((response) => {
            if (response) {
              this.listCustomers = response.data.data;
              this.meta = response.data.meta;
              this.links = response.data.links;
            }
          })
          .catch((err) => console.error(err));
      } else {
        this.listCustomers = [];
        this.meta = null;
        this.links = null;
      }
    },
    fetchMetrics() {
      axios.get('/sales-orders', {
        params: {
          option: 'dashboard'
        }
      })
        .then(response => {
          if (response) {
            this.metrics = response.data;
          }
        })
        .catch(err => console.log(err));
    },
    fetchRemittanceMetrics() {
      axios.get('/remittances', {
        params: {
          option: 'dashboard'
        }
      })
        .then(response => {
          if (response) {
            this.remittanceMetrics = response.data;
          }
        })
        .catch(err => console.log(err));
    },
    selectRow(index) {
      this.selectedRow = this.selectedRow === index ? null : index;
    },
    openCreate(){
        this.$refs.createProduct.show();
    },
    openEdit(data,index){
        this.selectedProductRow = index;
        this.$refs.createCustomer.edit(data , index);
    },
    onDelete(id){
        let title = "Customers";
        this.$refs.delete.show(id , title, '/customers');
    },

  },
};
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

.quick-stats-floating-toggle {
  position: fixed;
  top: 50%;
  right: 0;
  transform: translateY(-50%);
  background: #c4dad2;
  border: 1px solid rgba(108, 117, 125, 0.3);
  border-right: none;
  border-radius: 8px 0 0 8px;
  color: #16423c;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  padding: 30px 4px;
  transition: all 0.3s ease;
  z-index: 1000;
  box-shadow: -2px 0 10px rgba(0, 0, 0, 0.1);
}

.quick-stats-floating-toggle:hover {
  background: #3d8d7a;
  color: #ffffff;
}

.quick-stats-floating-toggle span {
  writing-mode: vertical-rl;
  text-orientation: mixed;
  transform: rotate(180deg);
  letter-spacing: 1px;
  white-space: nowrap;
}

.inventory-toast {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 9999;
  animation: slideInRight 0.3s ease-out;
}

.inventory-toast-content {
  background: #28a745;
  color: white;
  padding: 12px 20px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  gap: 10px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  font-weight: 500;
}

.inventory-toast-content i {
  font-size: 18px;
}

@keyframes slideInRight {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}
</style>
