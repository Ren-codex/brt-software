<template>
  <div>
    <Head title="Inventory" />
    <PageHeader title="Inventory Management" pageTitle="List" />
    <BRow no-gutters>
      <BCol md="2" class="border-end" style="min-height: 80vh;">
        <ul class="nav flex-column nav-pills">
          <li class="nav-item">
            <a
              href="#"
              class="nav-link"
              :class="{ active: activeTab === 'inventoryStocks' }"
              @click.prevent="changeTab('inventoryStocks')"
              >Inventory Stocks</a
            >
          </li>
          <li class="nav-item">
            <a
              href="#"
              class="nav-link"
              :class="{ active: activeTab === 'purchaseOrders' }"
              @click.prevent="changeTab('purchaseOrders')"
              >Purchase Orders</a
            >
          </li>
          <li class="nav-item">
            <a
              href="#"
              class="nav-link"
              :class="{ active: activeTab === 'products' }"
              @click.prevent="changeTab('products')"
              >Product List</a
            >
          </li>
        </ul>
      </BCol>
      <BCol md="10">
        <ProductsTab
          v-if="activeTab === 'products'"
          :listProducts="listProducts"
          :meta="meta"
          :links="links"
          :filter="filter"
          :dropdowns="dropdowns"
          @fetch="fetchProducts"
          @update-keyword="updateKeyword"
          @toast="showToast"
        />

        <PurchaseOrdersTab
          v-if="activeTab === 'purchaseOrders'"
          :listPurchaseOrders="listPurchaseOrders"
          :meta="meta"
          :links="links"
          :filter="filter"
          :dropdowns="dropdowns"
          @fetch="fetchPurchaseOrders"
          @update-keyword="updateKeyword"
          @toast="showToast"
        />

        <InventoryStocksTab
          v-if="activeTab === 'inventoryStocks'"
          :listInventoryStocks="listInventoryStocks"
          :meta="meta"
          :links="links"
          :filter="filter"
          :dropdowns="dropdowns"
          @fetch="fetchInventoryStocks"
          @update-keyword="updateKeyword"
          @toast="showToast"
        />
      </BCol>
    </BRow>

    <!-- Toast Notification -->
    <div v-if="isToastVisible" class="toast-notification">
      <div class="toast-content">
        <i class="ri-check-line text-white me-2"></i>
        {{ toastMessage }}
      </div>
    </div>
  </div>
</template>

<script>
import _ from 'lodash';
import PageHeader from '@/Shared/Components/PageHeader.vue';
import PurchaseOrdersTab from './Tab/PurchaseOrdersTab.vue';
import ProductsTab from './Tab/ProductsTab.vue';
import InventoryStocksTab from './Tab/InventoryStocksTab.vue';

export default {
  name: "InventoryManagement",
  components: { PageHeader, PurchaseOrdersTab, ProductsTab, InventoryStocksTab },
  props: ['dropdowns'],
  data() {
    return {
      activeTab: 'inventoryStocks',
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
    };
  },
  watch: {
    'activeTab'(newVal) {
      if (newVal === 'products') {
        this.fetchProducts();
      } else if (newVal === 'purchaseOrders') {
        this.fetchPurchaseOrders();
      } else if (newVal === 'inventoryStocks') {
        this.fetchInventoryStocks();
      }
    }
  },
  created() {
    this.fetchInventoryStocks();
  },
  methods: {
    changeTab(tab) {
      this.activeTab = tab;
      this.filter.keyword = '';
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
      } else {
        this.listProducts = [];
        this.meta = null;
        this.links = null;
      }
    },
    fetchPurchaseOrders(page_url) {
      if (this.activeTab === 'purchaseOrders') {
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
      } else {
        this.listPurchaseOrders = [];
        this.meta = null;
        this.links = null;
      }
    },
    showToast(message) {
      this.toastMessage = message;
      this.isToastVisible = true;
      setTimeout(() => {
        this.isToastVisible = false;
      }, 3000);
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
      } else {
        this.listInventoryStocks = [];
        this.meta = null;
        this.links = null;
      }
    },
  },
};
</script>

<style scoped>
.toast-notification {
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

.toast-content {
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
</style>
