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
                <div v-if="currentView === 'purchaseOrderDetails' && selectedPurchaseOrder" class="purchase-order-details-container">
                  <!-- Your purchase order details component here -->
                  <PurchaseOrderDetails
                    :purchase-order="selectedPurchaseOrder"
                    :dropdowns="dropdowns"
                    @back="backToList"
                    @toast="showToast"
                    @fetch="fetchPurchaseOrders"
                  />
                </div>

                <!-- Inventory Stock Details View -->
                <div v-else-if="currentView === 'inventoryStockDetails' && selectedInventoryStock" class="inventory-stock-details-container">
                  <!-- Your inventory stock details component here -->
                  <InventoryStockDetails 
                    :stock="selectedInventoryStock"
                    :dropdowns="dropdowns"
                    @back="backToStocksList"
                    @toast="showToast"
                    @fetch="fetchInventoryStocks"
                  />
                </div>

                <!-- Purchase Orders List -->
                <div class="row" v-if="activeTab === 'purchaseOrders' && currentView === 'list'">
                  <div class="col-md-9">
                    <PurchaseOrdersTab
                      :listPurchaseOrders="listPurchaseOrders"
                      :meta="meta"
                      :links="links"
                      :filter="filter"
                      :dropdowns="dropdowns"
                      @fetch="fetchPurchaseOrders"
                      @update-keyword="updateKeyword"
                      @toast="showToast"
                      @view-details="openPurchaseOrderDetails"
                    />
                  </div>
                  <div class="col-md-3">
                    <QuickStatsSidebar
                      :activeTab="activeTab"
                      :listProducts="listProducts"
                      :listPurchaseOrders="listPurchaseOrders"
                      :listPurchaseRequests="listPurchaseRequests"
                      :listInventoryStocks="listInventoryStocks"
                    />
                  </div>
                </div>

                <!-- Purchase Request List -->
                <div class="row" v-if="activeTab === 'purchaseRequests' && currentView === 'list'">
                  <div class="col-md-9">
                    <PurchaseRequestsTab
                      :listPurchaseRequests="listPurchaseRequests"
                      :listPurchaseOrders="listPurchaseOrders"
                      :listPRDisapproved="listPRDisapproved"
                      :meta="meta"
                      :links="links"
                      :filter="filter"
                      :dropdowns="dropdowns"
                      @fetch="fetchPurchaseOrders"
                      @update-keyword="updateKeyword"
                      @toast="showToast"
                      @view-details="openPurchaseOrderDetails"
                    />
                  </div>
                  <div class="col-md-3">
                    <QuickStatsSidebar
                      :activeTab="activeTab"
                      :listProducts="listProducts"
                      :listPurchaseOrders="listPurchaseOrders"
                      :listPurchaseRequests="listPurchaseRequests"
                      :listInventoryStocks="listInventoryStocks"
                    />
                  </div>
                </div>

                <div class="row" v-if="activeTab === 'products'">
                  <div class="col-md-9">
                    <ProductsTab
                      :listProducts="listProducts"
                      :meta="meta"
                      :links="links"
                      :filter="filter"
                      :dropdowns="dropdowns"
                      @fetch="fetchProducts"
                      @update-keyword="updateKeyword"
                      @toast="showToast"
                    />
                  </div>
                  <div class="col-md-3">
                    <QuickStatsSidebar
                      :totalProducts="listProducts.length"
                      :totalPurchaseOrders="listPurchaseOrders.length"
                      :totalStockItems="listInventoryStocks.length"
                      :inventoryValue="calculateInventoryValue()"
                      :pendingRequests="listPurchaseRequests.length"
                      :lowStockItems="calculateLowStockItems()"
                    />
                  </div>
                </div>

                <div class="row" v-if="activeTab === 'inventoryStocks' && currentView === 'list'">
                  <div class="col-md-9">
                    <InventoryStocksTab
                      :listInventoryStocks="listInventoryStocks"
                      :meta="meta"
                      :links="links"
                      :filter="filter"
                      :dropdowns="dropdowns"
                      @fetch="fetchInventoryStocks"
                      @update-keyword="updateKeyword"
                      @toast="showToast"
                      @view-details="openInventoryStockDetails"
                    />
                  </div>
                  <div class="col-md-3">
                    <QuickStatsSidebar
                      :activeTab="activeTab"
                      :listProducts="listProducts"
                      :listPurchaseOrders="listPurchaseOrders"
                      :listPurchaseRequests="listPurchaseRequests"
                      :listInventoryStocks="listInventoryStocks"
                    />
                  </div>
                </div>
              </div>
            </transition>
          </div>
        </div>
      </div>

    <!-- Toast -->
    <div v-if="isToastVisible" class="inventory-toast">
      <div class="inventory-toast-content">
        <i class="ri-check-line"></i>
        {{ toastMessage }}
      </div>
    </div>
    
    <!-- Modals -->
    <CreatePurchaseOrderModal ref="createModal" :dropdowns="dropdowns" @add="handlePurchaseOrderUpdate" :purchaseOrder="selectedPurchaseOrder"/>
    <CreateReceivedStockModal ref="receiveModal" :dropdowns="dropdowns" :purchaseOrder="selectedPurchaseOrder" @add="handleReceiveSuccess" />
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
import PurchaseOrderDetails from './Components/PurchaseOrders/View.vue';
import InventoryStockDetails from './Components/InventoryStocks/View.vue'; // Add this import
import QuickStatsSidebar from './Components/QuickStatsSidebar.vue';
import CreatePurchaseOrderModal from './Modal/CreatePurchaseOrderModal.vue';
import CreateReceivedStockModal from './Modal/CreateReceivedStockModal.vue';
import Delete from '@/Shared/Components/Modals/Delete.vue';

export default {
  name: "InventoryManagement",
  components: {
    PageHeader,
    PurchaseOrdersTab,
    PurchaseRequestsTab,
    ProductsTab,
    InventoryStocksTab,
    PurchaseOrderDetails,
    InventoryStockDetails,
    QuickStatsSidebar,
    CreatePurchaseOrderModal,
    CreateReceivedStockModal,
    Delete
  },
  props: ['dropdowns'],
  emits: ['fetch'],
  data() {
    return {
      isSidebarCollapsed: false,
      activeTab: localStorage.getItem('inventory_active_tab') || 'purchaseRequests',
      currentView: 'list',
      filter: {
        keyword: '',
      },
      listProducts: [],
      listPurchaseOrders: [],
      listPurchaseRequests: [],
      listPRDisapproved: [],
      listInventoryStocks: [],
      meta: null,
      links: null,
      isToastVisible: false,
      toastMessage: '',
      selectedPurchaseOrder: null,
      selectedInventoryStock: null, // Add this
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
          id: 'inventoryStocks',
          label: 'Inventory Stocks',
          icon: 'ri-box-3-line',
          description: 'Current stock levels'
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
      } else if (newVal === 'inventoryStocks') {
        this.currentView = 'list';
        this.fetchInventoryStocks();
      }
    }
  },
  created() {
    this.fetchPurchaseOrders();
    this.fetchInventoryStocks();
    const params = new URLSearchParams(window.location.search);
    const tabParam = params.get('tab');
    const stockIdParam = params.get('stock_id');
    
    if (tabParam && ['products', 'purchaseOrders', 'inventoryStocks'].includes(tabParam)) {
      this.activeTab = tabParam;
      this.changeTab(this.activeTab);
      
      // If there's a stock_id in URL and we're on inventoryStocks tab, load that stock
      if (tabParam === 'inventoryStocks' && stockIdParam) {
        this.fetchInventoryStockDetails(stockIdParam);
      }
    }
  },
  methods: {
    toggleSidebar() {
      this.isSidebarCollapsed = !this.isSidebarCollapsed;
    },
    changeTab(tab) {
      this.activeTab = tab;
      localStorage.setItem('inventory_active_tab', tab);
      this.currentView = 'list';
      this.selectedPurchaseOrder = null;
      this.selectedInventoryStock = null; // Clear stock selection
      this.filter.keyword = '';

      const url = new URL(window.location);
      url.searchParams.set('tab', tab);
      url.searchParams.delete('po_id');
      url.searchParams.delete('stock_id');
      window.history.pushState({}, '', url);

      if (tab === 'products') {
        this.fetchProducts();
      } else if (tab === 'purchaseOrders') {
        this.fetchPurchaseOrders();
      } else if (tab === 'inventoryStocks') {
        this.fetchInventoryStocks();
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
    
    handlePurchaseOrderUpdate() {
      this.showToast('Purchase order updated successfully');
      if (this.selectedPurchaseOrder) {
        // Refresh the details view if we're viewing a purchase order
        this.fetchPurchaseOrderDetails(this.selectedPurchaseOrder.id);
      }
      this.fetchPurchaseOrders();
    },
    
    handleReceiveSuccess() {
      this.showToast('Stock received successfully');
      if (this.selectedPurchaseOrder) {
        this.fetchPurchaseOrderDetails(this.selectedPurchaseOrder.id);
      }
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
    
    handleSearch: _.debounce(function() {
      if (this.activeTab === 'products') {
        this.fetchProducts();
      } else if (this.activeTab === 'purchaseOrders' && this.currentView === 'list') {
        this.fetchPurchaseOrders();
      } else if (this.activeTab === 'inventoryStocks') {
        this.fetchInventoryStocks();
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
</style>



