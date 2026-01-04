<template>
  <div>
    <Head title="Inventory" />
    <PageHeader title="Inventory Management" pageTitle="List" />
    
      <div class="inventory-container">
        <!-- Minimal Vertical Tabs -->
        <div class="inventory-sidebar">
          <div class="inventory-sidebar-header">
            <i class="ri-stack-line"></i>
            <h4>Inventory</h4>
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
              <div class="inventory-tab-text">
                <span class="inventory-tab-title">{{ tab.label }}</span>
                <span class="inventory-tab-subtitle">{{ tab.description }}</span>
              </div>
            </button>
          </div>
          
          <div class="inventory-sidebar-footer">
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
                <PurchaseOrdersTab 
                  v-else-if="activeTab === 'purchaseOrders'" 
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

                <!-- Purchase Request List -->
                <PurchaseRequestsTab
                  v-else-if="activeTab === 'purchaseRequests'"
                  :listPurchaseRequests="listPurchaseRequests"
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

                <ProductsTab 
                  v-else-if="activeTab === 'products'" 
                  :listProducts="listProducts" 
                  :meta="meta" 
                  :links="links"
                  :filter="filter" 
                  :dropdowns="dropdowns" 
                  @fetch="fetchProducts" 
                  @update-keyword="updateKeyword"
                  @toast="showToast" 
                />

                <InventoryStocksTab 
                  v-else-if="activeTab === 'inventoryStocks'" 
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
    CreatePurchaseOrderModal,
    CreateReceivedStockModal,
    Delete
  },
  props: ['dropdowns'],
  emits: ['fetch'],
  data() {
    return {
      activeTab: 'purchaseRequests',
      currentView: 'list',
      filter: {
        keyword: '',
      },
      listProducts: [],
      listPurchaseOrders: [],
      listPurchaseRequests: [],
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
    changeTab(tab) {
      this.activeTab = tab;
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
  },
};
</script>

<style scoped>
/* Add these styles for the purchase order details container */
.purchase-order-details-container {
  height: 100%;
  animation: fade-in 0.3s ease;
}

/* Add this for inventory stock details container */
.inventory-stock-details-container {
  height: 100%;
  animation: fade-in 0.3s ease;
}

@keyframes fade-in {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}


/* Main Wrapper */
.inventory-wrapper {
  padding: 16px;
  background: #f8fafc;
  min-height: calc(100vh - 120px);
}

.inventory-container {
  display: flex;
  background: white;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  min-height: 500px;
  animation: container-appear 0.4s ease-out;
}

@keyframes container-appear {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Minimal Sidebar with #267a4c */
.inventory-sidebar {
  width: 240px;
  background: #3D8D7A;
  color: white;
  display: flex;
  flex-direction: column;
  border-right: 1px solid rgba(255, 255, 255, 0.1);
  transition: all 0.3s ease;
}

.inventory-sidebar-header {
  padding: 20px 16px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  display: flex;
  align-items: center;
  gap: 10px;
  animation: slide-in-left 0.5s ease-out;
}

@keyframes slide-in-left {
  from {
    opacity: 0;
    transform: translateX(-10px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

.inventory-sidebar-header i {
  font-size: 18px;
  color: rgba(255, 255, 255, 0.9);
  transition: transform 0.3s ease;
}

.inventory-sidebar:hover .inventory-sidebar-header i {
  transform: rotate(-5deg);
}

.inventory-sidebar-header h4 {
  margin: 0;
  font-size: 14px;
  font-weight: 600;
  letter-spacing: 0.5px;
  color: rgba(255, 255, 255, 0.95);
}

/* Sidebar Tabs */
.inventory-sidebar-tabs {
  flex: 1;
  padding: 8px 0;
}

.inventory-sidebar-tab {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  background: transparent;
  border: none;
  color: rgba(255, 255, 255, 0.85);
  text-align: left;
  cursor: pointer;
  position: relative;
  transition: all 0.25s ease;
  width: 100%;
  margin: 2px 0;
  animation: tab-appear 0.6s ease-out;
  animation-fill-mode: both;
}

.inventory-sidebar-tab:nth-child(1) { animation-delay: 0.1s; }
.inventory-sidebar-tab:nth-child(2) { animation-delay: 0.2s; }
.inventory-sidebar-tab:nth-child(3) { animation-delay: 0.3s; }

@keyframes tab-appear {
  from {
    opacity: 0;
    transform: translateX(-15px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

.inventory-sidebar-tab:hover {
  background: rgba(255, 255, 255, 0.05);
  color: white;
  padding-left: 20px;
}

.inventory-sidebar-tab.inventory-tab-active {
  background: rgba(255, 255, 255, 0.08);
  color: white;
}

.inventory-sidebar-tab.inventory-tab-active::after {
  content: '';
  position: absolute;
  right: 0;
  top: 50%;
  transform: translateY(-50%);
  width: 2px;
  height: 20px;
  background: white;
  border-radius: 1px;
  animation: active-indicator 0.3s ease-out;
}

@keyframes active-indicator {
  from {
    opacity: 0;
    transform: translateY(-50%) scaleX(0);
  }
  to {
    opacity: 1;
    transform: translateY(-50%) scaleX(1);
  }
}

/* Tab Icon */
.inventory-tab-icon {
  width: 28px;
  height: 28px;
  border-radius: 6px;
  background: rgba(255, 255, 255, 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  flex-shrink: 0;
  transition: all 0.3s ease;
}

.inventory-sidebar-tab:hover .inventory-tab-icon {
  background: rgba(255, 255, 255, 0.15);
  transform: scale(1.05);
}

.inventory-sidebar-tab.inventory-tab-active .inventory-tab-icon {
  background: rgba(255, 255, 255, 0.2);
  transform: scale(1.05);
}

/* Tab Text */
.inventory-tab-text {
  flex: 1;
  min-width: 0;
  overflow: hidden;
}

.inventory-tab-title {
  display: block;
  font-weight: 500;
  font-size: 13px;
  margin-bottom: 1px;
  transition: transform 0.3s ease;
}

.inventory-sidebar-tab:hover .inventory-tab-title {
  transform: translateX(2px);
}

.inventory-tab-subtitle {
  display: block;
  font-size: 11px;
  color: rgba(255, 255, 255, 0.6);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  transition: opacity 0.3s ease;
}

.inventory-sidebar-tab:hover .inventory-tab-subtitle {
  opacity: 0.9;
}

.inventory-sidebar-tab.inventory-tab-active .inventory-tab-subtitle {
  color: rgba(255, 255, 255, 0.8);
}

/* Sidebar Footer */
.inventory-sidebar-footer {
  padding: 12px 16px;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  background: rgba(0, 0, 0, 0.05);
  animation: slide-up 0.5s ease-out 0.4s both;
}

@keyframes slide-up {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.inventory-stats {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.inventory-stat-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 11px;
  color: rgba(255, 255, 255, 0.7);
  transition: all 0.3s ease;
}

.inventory-stat-item:hover {
  color: rgba(255, 255, 255, 0.9);
  transform: translateX(2px);
}

.inventory-stat-item i {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.8);
}

/* Main Content Area */
.inventory-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 500px;
  background: #E9EFEC;
}

/* Main Content */
.inventory-main-content {
  flex: 1;
  overflow: auto;
  padding: 20px;
}

.inventory-tab-content {
  animation: content-fade 0.4s ease-out;
}

@keyframes content-fade {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Minimal Fade Transition */
.inventory-fade-enter-active {
  animation: inventory-fade-in 0.3s ease-out;
}

.inventory-fade-leave-active {
  animation: inventory-fade-out 0.2s ease-in;
}

@keyframes inventory-fade-in {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes inventory-fade-out {
  from {
    opacity: 1;
    transform: translateY(0);
  }
  to {
    opacity: 0;
    transform: translateY(-10px);
  }
}

/* Toast */
.inventory-toast {
  position: fixed;
  top: 16px;
  right: 16px;
  z-index: 9999;
  background: #267a4c;
  color: white;
  padding: 10px 14px;
  border-radius: 6px;
  box-shadow: 0 2px 8px rgba(38, 122, 76, 0.3);
  animation: toast-slide 0.3s ease-out, toast-fade 3s ease-out forwards;
}

@keyframes toast-slide {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

@keyframes toast-fade {
  0%, 80% {
    opacity: 1;
  }
  100% {
    opacity: 0;
  }
}

.inventory-toast-content {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 500;
}

.inventory-toast-content i {
  font-size: 14px;
}

/* Responsive */
@media (max-width: 992px) {
  .inventory-container {
    flex-direction: column;
  }
  
  .inventory-sidebar {
    width: 100%;
    border-right: none;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  }
  
  .inventory-sidebar-tabs {
    display: flex;
    padding: 8px;
  }
  
  .inventory-sidebar-tab {
    flex-direction: column;
    text-align: center;
    min-width: 100px;
    padding: 12px;
    margin: 0 2px;
  }
  
  .inventory-sidebar-tab:hover {
    padding-left: 12px;
  }
  
  .inventory-sidebar-tab.inventory-tab-active::after {
    top: 0;
    left: 0;
    right: 0;
    bottom: auto;
    width: auto;
    height: 2px;
    transform: none;
  }
  
  .inventory-main-content {
    padding: 16px;
  }
}

@media (max-width: 768px) {
  .inventory-wrapper {
    padding: 12px;
  }
  
  .inventory-main-content {
    padding: 12px;
  }
}
</style>