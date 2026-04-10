<template>
  <div>

    <Head title="Inventory" />
    <PageHeader title="Inventory Management" pageTitle="List" />

    <div class="inventory-container">
      <!-- Minimal Vertical Tabs -->
      <div class="inventory-sidebar" :class="{ 'sidebar-collapsed': isSidebarCollapsed }">
        <div class="inventory-sidebar-header">
          <i class="ri-stack-line"></i>
          <h4 v-if="!isSidebarCollapsed">Inventory</h4>
          <button class="sidebar-toggle-btn" @click="toggleSidebar" title="Toggle Sidebar">
            <i :class="isSidebarCollapsed ? 'ri-arrow-right-s-line' : 'ri-arrow-left-s-line'"></i>
          </button>
        </div>

        <div class="inventory-sidebar-tabs">
          <button v-for="tab in tabs" :key="tab.id" type="button" class="inventory-sidebar-tab"
            :class="{ 'inventory-tab-active': activeTab === tab.id }" @click="changeTab(tab.id)">
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
              <i class="ri-box-3-line"></i>
              <span>{{ listProducts.length || 0 }} Products</span>
            </div>
            <div class="inventory-stat-item">
              <i class="ri-shopping-cart-2-line"></i>
              <span>{{ listPurchaseOrders.length || 0 }} Orders</span>
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
              <!-- Purchase Order Details View -->
              <div v-if="currentView === 'purchaseOrderDetails' && selectedPurchaseOrder"
                class="purchase-order-details-container">
                <!-- Your purchase order details component here -->
                <PurchaseOrderDetails :purchase-order="selectedPurchaseOrder" :dropdowns="dropdowns" :active-tab="activeTab" @back="backToList"
                  @toast="showToast" @fetch="fetchPurchaseOrders" />
              </div>

              <!-- Inventory Stock Details View -->
              <div v-else-if="currentView === 'inventoryStockDetails' && selectedInventoryStock"
                class="inventory-stock-details-container">
                <!-- Your inventory stock details component here -->
                <InventoryStockDetails :stock="selectedInventoryStock" :dropdowns="dropdowns" @back="backToStocksList"
                  @toast="showToast" @fetch="fetchInventoryStocks" />
              </div>

              <!-- Stock Return Details View -->
              <div v-else-if="currentView === 'stockReturnDetails' && selectedStockReturn"
                class="stock-return-details-container">
                <StockReturnDetails
                  :stock-return="selectedStockReturn"
                  @back="backToStockReturnsList"
                  @toast="showToast"
                  @refresh="fetchStockReturnDetails"
                />
              </div>

              <!-- Purchase Orders List -->
              <div class="row" v-if="activeTab === 'purchaseOrders' && currentView === 'list'">
                <div :class="isRightSidebarCollapsed ? 'col-md-12' : 'col-md-9'">
                  <PurchaseOrdersTab :listPurchaseOrders="listPurchaseOrders" :meta="meta" :links="links"
                    :filter="filter" :dropdowns="dropdowns" @fetch="fetchPurchaseOrders" @update-keyword="updateKeyword"
                    @toast="showToast" @view-details="openPurchaseOrderDetails" />
                </div>
                <div v-show="!isRightSidebarCollapsed" class="col-md-3">
                  <QuickStatsSidebar :activeTab="activeTab" :listProducts="listProducts"
                    :listPurchaseOrders="listPurchaseOrders" :listPurchaseRequests="listPurchaseRequests"
                    :listInventoryStocks="listInventoryStocks" :listReceivedStocks="listReceivedStocks"
                    :isRightSidebarCollapsed="isRightSidebarCollapsed"
                    @toggle="toggleRightSidebar" />
                </div>
              </div>

              <!-- Purchase Request List -->
              <div class="row" v-if="activeTab === 'purchaseRequests' && currentView === 'list'">
                <div :class="isRightSidebarCollapsed ? 'col-md-12' : 'col-md-9'">
                  <PurchaseRequestsTab :listPurchaseRequests="listPurchaseRequests"
                    :listPurchaseOrders="listPurchaseOrders" :listPRDisapproved="listPRDisapproved" :meta="meta"
                    :links="links" :filter="filter" :dropdowns="dropdowns" @fetch="fetchPurchaseOrders"
                    @update-keyword="updateKeyword" @toast="showToast" @view-details="openPurchaseOrderDetails" />
                </div>
                <div v-show="!isRightSidebarCollapsed" class="col-md-3">
                  <QuickStatsSidebar :activeTab="activeTab" :listProducts="listProducts"
                    :listPurchaseOrders="listPurchaseOrders" :listPurchaseRequests="listPurchaseRequests"
                    :listInventoryStocks="listInventoryStocks" :listReceivedStocks="listReceivedStocks"
                    :isRightSidebarCollapsed="isRightSidebarCollapsed"
                    @toggle="toggleRightSidebar" />
                </div>
              </div>

              <div class="row" v-if="activeTab === 'receiving' && currentView === 'list'">
                <div :class="isRightSidebarCollapsed ? 'col-md-12' : 'col-md-9'">
                  <ReceivingTab :listReceivedStocks="listReceivedStocks" />
                </div>
                <div v-show="!isRightSidebarCollapsed" class="col-md-3">
                  <QuickStatsSidebar :activeTab="activeTab" :listProducts="listProducts"
                    :listPurchaseOrders="listPurchaseOrders" :listPurchaseRequests="listPurchaseRequests"
                    :listInventoryStocks="listInventoryStocks" :listReceivedStocks="listReceivedStocks"
                    :isRightSidebarCollapsed="isRightSidebarCollapsed"
                    @toggle="toggleRightSidebar" />
                </div>
              </div>

              <div class="row" v-if="activeTab === 'products'">
                <div :class="isRightSidebarCollapsed ? 'col-md-12' : 'col-md-9'">
                  <ProductsTab :listProducts="listProducts" :meta="meta" :links="links" :filter="filter"
                    :dropdowns="dropdowns" @fetch="fetchProducts" @update-keyword="updateKeyword" @toast="showToast" />
                </div>
                <div v-show="!isRightSidebarCollapsed" class="col-md-3">
                  <QuickStatsSidebar :activeTab="activeTab" :listProducts="listProducts"
                    :listPurchaseOrders="listPurchaseOrders" :listPurchaseRequests="listPurchaseRequests"
                    :listInventoryStocks="listInventoryStocks" :listReceivedStocks="listReceivedStocks"
                    :isRightSidebarCollapsed="isRightSidebarCollapsed"
                    @toggle="toggleRightSidebar" />
                </div>
              </div>

              <div class="row" v-if="activeTab === 'inventoryStocks' && currentView === 'list'">
                <div :class="isRightSidebarCollapsed ? 'col-md-12' : 'col-md-9'">
                  <InventoryStocksTab :listInventoryStocks="listInventoryStocks" :meta="meta" :links="links"
                    :filter="filter" :dropdowns="dropdowns" @fetch="fetchInventoryStocks"
                    @update-keyword="updateKeyword" @toast="showToast" @view-details="openInventoryStockDetails" />
                </div>
                <div v-show="!isRightSidebarCollapsed" class="col-md-3">
                  <QuickStatsSidebar :activeTab="activeTab" :listProducts="listProducts"
                    :listPurchaseOrders="listPurchaseOrders" :listPurchaseRequests="listPurchaseRequests"
                    :listInventoryStocks="listInventoryStocks" :listReceivedStocks="listReceivedStocks"
                    :isRightSidebarCollapsed="isRightSidebarCollapsed"
                    @toggle="toggleRightSidebar" />
                </div>
              </div>
              <div class="row" v-if="activeTab === 'productSummary' && currentView === 'list'">
                <div :class="isRightSidebarCollapsed ? 'col-md-12' : 'col-md-9'">
                  <ProductSummary :listProducts="listProducts" :meta="meta" :links="links" :filter="filter"
                    :dropdowns="dropdowns" @fetch="fetchProducts" @update-keyword="updateKeyword" @toast="showToast"
                    @view-details="openInventoryStockDetails" />
                </div>
              </div>
              <div class="row" v-if="activeTab === 'accountsPayable' && currentView === 'list'">
                <div class="col-md-12">
                  <AccountsPayableTab
                    :dataReady="accountsPayableDataReady"
                    :isLoading="isAccountsPayableLoading"
                    :summaryCards="accountsPayableSummaryCards"
                    :rows="accountsPayableRows"
                    :received-stocks="listReceivedStocks"
                    @refresh="handleAccountsPayablePaymentSuccess"
                    @toast="showToast"
                  />
                </div>
              </div>
              <div class="row" v-if="activeTab === 'stockReturns' && currentView === 'list'">
                <div :class="isRightSidebarCollapsed ? 'col-md-12' : 'col-md-9'">
                  <StockReturnsTab :listStockReturns="listStockReturns" :meta="meta" :links="links" :filter="filter"
                    :dropdowns="dropdowns" @fetch="fetchStockReturns" @update-keyword="updateKeyword"
                    @toast="showToast" @view-details="openStockReturnDetails" />
                </div>
                <div v-show="!isRightSidebarCollapsed" class="col-md-3">
                  <QuickStatsSidebar :activeTab="activeTab" :listProducts="listProducts"
                    :listPurchaseOrders="listPurchaseOrders" :listPurchaseRequests="listPurchaseRequests"
                    :listInventoryStocks="listInventoryStocks" :listReceivedStocks="listReceivedStocks"
                    :isRightSidebarCollapsed="isRightSidebarCollapsed"
                    @toggle="toggleRightSidebar" />
                </div>
              </div>
            </div>
          </transition>
        </div>
      </div>
    </div>

    <!-- Floating Quick Stats Toggle Button -->
    <button class="quick-stats-floating-toggle" @click="toggleRightSidebar"
      :title="isRightSidebarCollapsed ? 'Show Quick Stats' : 'Hide Quick Stats'">
      <!-- <i :class="isRightSidebarCollapsed ? 'ri-arrow-left-s-line' : 'ri-arrow-right-s-line'"></i> -->
      <span>Quick Stats</span>
    </button>

    <!-- Toast -->
    <div v-if="isToastVisible" class="inventory-toast">
      <div class="inventory-toast-content">
        <i class="ri-check-line"></i>
        {{ toastMessage }}
      </div>
    </div>

    <!-- Modals -->
    <CreatePurchaseOrderModal ref="createModal" :dropdowns="dropdowns" @add="handlePurchaseOrderUpdate"
      :purchaseOrder="selectedPurchaseOrder" />
    <CreateReceivedStockModal ref="receiveModal" :dropdowns="dropdowns" :purchaseOrder="selectedPurchaseOrder"
      @add="handleReceiveSuccess" />
    <Delete ref="deleteModal" @delete="handleDeleteSuccess" />
  </div>
</template>

<script>
import _ from 'lodash';
import PageHeader from '@/Shared/Components/PageHeader.vue';
import PurchaseOrdersTab from './Tab/PurchaseOrdersTab.vue';
import PurchaseRequestsTab from './Tab/PurchaseRequestsTab.vue';
import ProductsTab from './Tab/ProductsTab.vue';
import InventoryStocksTab from './Tab/InventoryStocksTab.vue';
import StockReturnsTab from './Tab/StockReturnsTab.vue';
import AccountsPayableTab from './Tab/AccountsPayableTab.vue';
import ReceivingTab from './Tab/ReceivingTab.vue';
import PurchaseOrderDetails from './Components/PurchaseOrders/View.vue';
import InventoryStockDetails from './Components/InventoryStocks/View.vue';
import StockReturnDetails from './Components/StockReturns/View.vue';
import QuickStatsSidebar from './Components/QuickStatsSidebar.vue';
import CreatePurchaseOrderModal from './Modal/CreatePurchaseOrderModal.vue';
import CreateReceivedStockModal from './Modal/CreateReceivedStockModal.vue';
import Delete from '@/Shared/Components/Modals/Delete.vue';
import ProductSummary from './Components/InventoryStocks/ProductSummary.vue';

export default {
  name: "InventoryManagement",
  components: {
    PageHeader,
    PurchaseOrdersTab,
    PurchaseRequestsTab,
    ProductsTab,
    InventoryStocksTab,
    StockReturnsTab,
    AccountsPayableTab,
    ReceivingTab,
    PurchaseOrderDetails,
    InventoryStockDetails,
    StockReturnDetails,
    QuickStatsSidebar,
    CreatePurchaseOrderModal,
    CreateReceivedStockModal,
    Delete,
    ProductSummary,
  },
  props: ['dropdowns'],
  emits: ['fetch'],
  data() {
    const storedInventoryTab = localStorage.getItem('inventory_active_tab');
    const normalizedInventoryTab = storedInventoryTab === 'accountPayable'
      ? 'accountsPayable'
      : storedInventoryTab;

    return {
      isSidebarCollapsed: false,
      isRightSidebarCollapsed: true,
      activeTab: normalizedInventoryTab || 'productSummary',
      currentView: 'list',
      filter: {
        keyword: '',
      },
      listProducts: [],
      listPurchaseOrders: [],
      listPurchaseRequests: [],
      listPRDisapproved: [],
      listInventoryStocks: [],
      listReceivedStocks: [],
      listStockReturns: [],
      meta: null,
      links: null,
      accountsPayableDataReady: false,
      accountsPayableSummaryCards: [],
      accountsPayableRows: [],
      isAccountsPayableLoading: false,
      isToastVisible: false,
      toastMessage: '',
      selectedPurchaseOrder: null,
      selectedInventoryStock: null, // Add this
      selectedStockReturn: null,
      tabs: [
        {
          id: 'purchaseRequests',
          label: 'Purchase Requests',
          icon: 'ri-shopping-cart-2-line',
          description: 'Manage purchase requests'
        },
        {
          id: 'purchaseOrders',
          label: 'Purchase Orders',
          icon: 'ri-box-3-line',
          description: 'List of purchase orders'
        },
        {
          id: 'receiving',
          label: 'Receiving',
          icon: 'ri-inbox-unarchive-line',
          description: 'List of paid purchase requests'
        },
        {
          id: 'accountsPayable',
          label: 'Accounts Payable',
          icon: 'ri-wallet-3-line',
          description: 'Review supplier obligations'
        },
        // {
        //   id: 'inventoryStocks',
        //   label: 'Inventory Stocks',
        //   icon: 'ri-box-3-line',
        //   description: 'Current stock levels'
        // },
        {
          id: 'productSummary',
          label: 'Product Inventory',
          icon: 'ri-survey-line',
          description: 'List of products'
        },
        {
          id: 'stockReturns',
          label: 'Stock Returns',
          icon: 'ri-text-wrap',
          description: 'List of stock returns'
        },
      ]
    };
  },
  watch: {
    activeTab(newVal) {
      if (newVal === 'products') {
        this.currentView = 'list';
        this.fetchProducts();
      } else if (newVal === 'purchaseOrders' || newVal === 'purchaseRequests') {
        this.currentView = 'list';
        this.fetchPurchaseOrders();
      } else if (newVal === 'receiving') {
        this.currentView = 'list';
        this.fetchReceivedStocks();
      } else if (newVal === 'accountsPayable') {
        this.currentView = 'list';
        this.fetchAccountsPayableData();
      } else if (newVal === 'inventoryStocks') {
        this.currentView = 'list';
        this.fetchInventoryStocks();
      } else if (newVal === 'stockReturns') {
        this.currentView = 'list';
        this.fetchStockReturns();
      }
    }
  },
  created() {
    this.fetchPurchaseOrders();
    this.fetchInventoryStocks();
    this.fetchStockReturns();
    const params = new URLSearchParams(window.location.search);
    const tabParam = params.get('tab');
    const stockIdParam = params.get('stock_id');
    const returnIdParam = params.get('return_id');
    const inventoryTabs = ['products', 'purchaseOrders', 'purchaseRequests', 'receiving', 'accountsPayable', 'inventoryStocks', 'stockReturns', 'productSummary'];

    if (tabParam && inventoryTabs.includes(tabParam)) {
      this.activeTab = tabParam;
      this.changeTab(this.activeTab);

      // If there's a stock_id in URL and we're on inventoryStocks tab, load that stock
      if (tabParam === 'inventoryStocks' && stockIdParam) {
        this.fetchInventoryStockDetails(stockIdParam);
      }

      if (tabParam === 'stockReturns' && returnIdParam) {
        this.fetchStockReturnDetails(returnIdParam);
      }
    } else if (this.activeTab === 'receiving') {
      this.fetchReceivedStocks();
    } else if (this.activeTab === 'accountsPayable') {
      this.fetchAccountsPayableData();
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
      localStorage.setItem('inventory_active_tab', tab);
      this.currentView = 'list';
      this.selectedPurchaseOrder = null;
      this.selectedInventoryStock = null; // Clear stock selection
      this.selectedStockReturn = null;
      this.filter.keyword = '';

      const url = new URL(window.location);
      url.searchParams.set('tab', tab);
      url.searchParams.delete('po_id');
      url.searchParams.delete('stock_id');
      url.searchParams.delete('return_id');
      window.history.pushState({}, '', url);

      if (tab === 'products') {
        this.fetchProducts();
      } else if (tab === 'purchaseOrders') {
        this.fetchPurchaseOrders();
      } else if (tab === 'purchaseRequests') {
        this.fetchPurchaseOrders();
      } else if (tab === 'receiving') {
        this.fetchReceivedStocks();
      } else if (tab === 'accountsPayable') {
        this.fetchAccountsPayableData();
      } else if (tab === 'inventoryStocks') {
        this.fetchInventoryStocks();
      } else if (tab === 'stockReturns') {
        this.fetchStockReturns();
      }
    },

    fetchProducts(page_url) {
      if (this.activeTab === 'products') {
        page_url = page_url || '/libraries/products';
        axios
          .get(page_url, {
            params: {
              keyword: this.filter.keyword,
              count: 10,
              option: 'lists',
            },
          })
          .then((response) => {
            if (response) {
              this.listProducts = response.data.data;
              this.meta = response.data.meta;
              this.links = response.data.links;
            }
          })
          .catch((err) => console.error(err));
      }
    },

    fetchPurchaseOrders(page_url) {
      if ((this.activeTab === 'purchaseOrders' || this.activeTab === 'purchaseRequests') && this.currentView === 'list') {
        page_url = page_url || '/purchase-orders';
        axios
          .get(page_url, {
            params: {
              keyword: this.filter.keyword,
              count: 10,
              option: 'list',
            },
          })
          .then((response) => {
            if (response) {
              const allPurchaseOrders = response.data.data;
              // Separate based on status
              this.listPurchaseRequests = allPurchaseOrders.filter(order => order.status?.name === 'Pending');
              this.listPurchaseOrders = allPurchaseOrders.filter(order => order.status?.name === 'Approved' || order.status?.name === 'Completed');
              this.listPRDisapproved = allPurchaseOrders.filter(order => order.status?.name === 'Disapproved');
              this.meta = response.data.meta;
              this.links = response.data.links;
            }
          })
          .catch((err) => console.error(err));
      }
    },

    fetchInventoryStocks(page_url) {
      if (this.activeTab === 'inventoryStocks') {
        page_url = page_url || '/inventory-stocks';
        axios
          .get(page_url, {
            params: {
              keyword: this.filter.keyword,
              count: 10,
              option: 'lists',
            },
          })
          .then((response) => {
            if (response) {
              this.listInventoryStocks = response.data.data;
              this.meta = response.data.meta;
              this.links = response.data.links;
            }
          })
          .catch((err) => console.error(err));
      }
    },

    async loadReceivedStocks() {
      const response = await axios.get('/received-stocks');
      this.listReceivedStocks = Array.isArray(response.data?.data) ? response.data.data : [];
      return this.listReceivedStocks;
    },

    fetchReceivedStocks() {
      if (this.activeTab !== 'receiving' || this.currentView !== 'list') {
        return;
      }

      this.loadReceivedStocks().catch((err) => {
        console.error(err);
        this.showToast('Failed to load receiving records');
      });
    },

    fetchStockReturns(page_url) {
      if (this.activeTab === 'stockReturns') {
        page_url = page_url || '/stock-returns';
        axios
          .get(page_url, {
            params: {
              keyword: this.filter.keyword,
              count: 10,
            },
          })
          .then((response) => {
            if (response) {
              this.listStockReturns = response.data.data;
              this.meta = response.data.meta;
              this.links = response.data.links;
            }
          })
          .catch((err) => console.error(err));
      }
    },

    async fetchAccountsPayableData() {
      if (this.activeTab !== 'accountsPayable') {
        return;
      }

      this.isAccountsPayableLoading = true;

      const [accountingResult, receivedStocksResult] = await Promise.allSettled([
        axios.get('/accounting', {
          params: {
            option: 'report_data',
          },
        }),
        this.loadReceivedStocks(),
      ]);

      if (accountingResult.status === 'fulfilled') {
        const response = accountingResult.value;
        this.accountsPayableDataReady = !!response.data.dataReady;
        this.accountsPayableSummaryCards = response.data.sectionMetrics?.accounts_payable || [];
        this.accountsPayableRows = response.data.reportData?.accounts_payable?.rows || [];
      } else {
        console.error(accountingResult.reason);
        this.accountsPayableDataReady = false;
        this.accountsPayableSummaryCards = [];
        this.accountsPayableRows = [];
      }

      if (receivedStocksResult.status === 'rejected') {
        console.error(receivedStocksResult.reason);
        this.showToast('Failed to load credit payable records');
      }

      this.isAccountsPayableLoading = false;
    },

    openPurchaseOrderDetails(purchaseOrder) {
      this.selectedPurchaseOrder = purchaseOrder;
      this.currentView = 'purchaseOrderDetails';

      const url = new URL(window.location);
      url.searchParams.set('tab', 'purchaseOrders');
      url.searchParams.set('po_id', purchaseOrder.id);
      window.history.pushState({}, '', url);
    },

    openInventoryStockDetails(stock) {
      this.selectedInventoryStock = stock;
      this.currentView = 'inventoryStockDetails';

      const url = new URL(window.location);
      url.searchParams.set('tab', 'inventoryStocks');
      url.searchParams.set('stock_id', stock.id);
      window.history.pushState({}, '', url);
    },

    openStockReturnDetails(stockReturn) {
      this.selectedStockReturn = stockReturn;
      this.currentView = 'stockReturnDetails';

      const url = new URL(window.location);
      url.searchParams.set('tab', 'stockReturns');
      url.searchParams.set('return_id', stockReturn.id);
      window.history.pushState({}, '', url);
    },

    backToList() {
      this.currentView = 'list';
      this.selectedPurchaseOrder = null;
      this.fetchPurchaseOrders();

      const url = new URL(window.location);
      url.searchParams.set('tab', 'purchaseOrders');
      url.searchParams.delete('po_id');
      window.history.pushState({}, '', url);
    },

    backToStocksList() {
      this.currentView = 'list';
      this.selectedInventoryStock = null;
      this.fetchInventoryStocks();

      const url = new URL(window.location);
      url.searchParams.set('tab', 'inventoryStocks');
      url.searchParams.delete('stock_id');
      window.history.pushState({}, '', url);
    },

    backToStockReturnsList() {
      this.currentView = 'list';
      this.selectedStockReturn = null;
      this.fetchStockReturns();

      const url = new URL(window.location);
      url.searchParams.set('tab', 'stockReturns');
      url.searchParams.delete('return_id');
      window.history.pushState({}, '', url);
    },

    fetchPurchaseOrderDetails(id) {
      axios
        .get(`/purchase-orders/${id}`)
        .then((response) => {
          this.selectedPurchaseOrder = response.data.data;
        })
        .catch((err) => {
          console.error(err);
          this.showToast('Failed to refresh purchase order details');
        });
    },

    fetchInventoryStockDetails(id) {
      axios
        .get(`/inventory-stocks/${id}`)
        .then((response) => {
          this.selectedInventoryStock = response.data.data;
          this.currentView = 'inventoryStockDetails';
        })
        .catch((err) => {
          console.error(err);
          this.showToast('Failed to load inventory stock details');
        });
    },

    fetchStockReturnDetails(id) {
      axios
        .get(`/stock-returns/${id}`)
        .then((response) => {
          this.selectedStockReturn = response.data.data;
          this.currentView = 'stockReturnDetails';
        })
        .catch((err) => {
          console.error(err);
          this.showToast('Failed to load stock return details');
        });
    },

    handlePurchaseOrderUpdate(payload = {}) {
      const action = payload?.action === 'updated' ? 'updated' : 'created';
      this.showToast(`Purchase request ${action} successfully`);
      if (this.selectedPurchaseOrder) {
        // Refresh the details view if we're viewing a purchase order
        this.fetchPurchaseOrderDetails(this.selectedPurchaseOrder.id);
      }
      this.fetchPurchaseOrders();
    },

    handleReceiveSuccess() {
      this.showToast('Stock received successfully');
      this.fetchPurchaseOrders();
      if (this.activeTab === 'accountsPayable') {
        this.fetchAccountsPayableData();
      }
      if (this.activeTab === 'receiving') {
        this.fetchReceivedStocks();
      }
      if (this.selectedPurchaseOrder) {
        this.fetchPurchaseOrderDetails(this.selectedPurchaseOrder.id);
      }
    },

    handleAccountsPayablePaymentSuccess() {
      this.fetchAccountsPayableData();
    },

    handleDeleteSuccess() {
      this.showToast('Purchase order deleted successfully');
      this.backToList();
    },

    showToast(message) {
      this.toastMessage = message;
      this.isToastVisible = true;
      setTimeout(() => {
        this.isToastVisible = false;
      }, 3000);
    },

    updateKeyword(keyword) {
      this.filter.keyword = keyword;
      this.handleSearch();
    },

    handleSearch: _.debounce(function () {
      if (this.activeTab === 'products') {
        this.fetchProducts();
      } else if (this.activeTab === 'purchaseOrders' && this.currentView === 'list') {
        this.fetchPurchaseOrders();
      } else if (this.activeTab === 'inventoryStocks') {
        this.fetchInventoryStocks();
      } else if (this.activeTab === 'stockReturns') {
        this.fetchStockReturns();
      }
    }, 500),

    calculateInventoryValue() {
      return this.listInventoryStocks.reduce((total, stock) => {
        return total + (stock.retail_price * stock.quantity);
      }, 0);
    },

    calculateLowStockItems() {
      return this.listInventoryStocks.filter(stock => stock.quantity < 10).length;
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

.inventory-stats-section {
  margin-bottom: 20px;
}

@media (max-width: 768px) {
  .inventory-stats-section {
    margin-bottom: 15px;
  }
}

.right-sidebar-toggle-btn {
  position: absolute;
  top: 15px;
  right: 0;
  background: transparent;
  border: 1px solid rgba(108, 117, 125, 0.3);
  border-radius: 6px 0 0 6px;
  color: #6c757d;
  font-size: 16px;
  cursor: pointer;
  padding: 6px 8px;
  transition: all 0.3s ease;
  z-index: 10;
}

.right-sidebar-toggle-btn:hover {
  background: rgba(108, 117, 125, 0.1);
  color: #495057;
  border-color: rgba(108, 117, 125, 0.5);
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
</style>
