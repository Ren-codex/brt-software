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
                    @refresh="fetchPurchaseOrderDetails(selectedPurchaseOrder.id)"
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
    <CreatePurchaseOrderModal ref="createModal" :dropdowns="dropdowns" @add="handlePurchaseOrderUpdate" />
    <CreateReceivedStockModal ref="receiveModal" :dropdowns="dropdowns" :purchaseOrder="selectedPurchaseOrder" @add="handleReceiveSuccess" />
    <Delete ref="deleteModal" @delete="handleDeleteSuccess" />
  </div>
</template>

<script>
import _ from 'lodash';
import PageHeader from '@/Shared/Components/PageHeader.vue';
import PurchaseOrdersTab from './Tab/PurchaseOrdersTab.vue';
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
    ProductsTab, 
    InventoryStocksTab, 
    PurchaseOrderDetails,
    InventoryStockDetails, // Add this component
    CreatePurchaseOrderModal,
    CreateReceivedStockModal,
    Delete
  },
  props: ['dropdowns'],
  emits: ['fetch'],
  data() {
    return {
      activeTab: 'purchaseOrders',
      currentView: 'list', // 'list' or 'details'
      filter: {
        keyword: '',
      },
      listProducts: [],
      listPurchaseOrders: [],
      listInventoryStocks: [],
      meta: null,
      links: null,
      isToastVisible: false,
      toastMessage: '',
      selectedPurchaseOrder: null,
      selectedInventoryStock: null, // Add this
      tabs: [
        { 
          id: 'purchaseOrders', 
          label: 'Purchase Requests', 
          icon: 'ri-shopping-cart-2-line',
          description: 'Manage purchase requests'
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
      } else if (newVal === 'purchaseOrders') {
        this.currentView = 'list';
        this.fetchPurchaseOrders();
      } else if (newVal === 'inventoryStocks') {
        this.currentView = 'list';
        this.fetchInventoryStocks();
      }
    }
  },
  created() {
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
      if (this.activeTab === 'purchaseOrders' && this.currentView === 'list') {
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
              this.listPurchaseOrders = response.data.data;
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


