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
              :class="{ active: activeTab === 'products' }"
              @click.prevent="changeTab('products')"
              >Product List</a
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
              :class="{ active: activeTab === 'receivingStocks' }"
              @click.prevent="changeTab('receivingStocks')"
              >Receiving of Stocks</a
            >
          </li>
          <li class="nav-item">
            <a
              href="#"
              class="nav-link"
              :class="{ active: activeTab === 'adjustmentStocks' }"
              @click.prevent="changeTab('adjustmentStocks')"
              >Adjustment of Stocks</a
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
        />

        <div v-if="activeTab === 'receivingStocks'" class="card shadow-sm p-3">
          <h5>Receiving of Stocks</h5>
          <p>Receiving stocks table or content goes here.</p>
        </div>

        <div v-if="activeTab === 'adjustmentStocks'" class="card shadow-sm p-3">
          <h5>Adjustment of Stocks</h5>
          <p>Adjustment stocks table or content goes here.</p>
        </div>
      </BCol>
    </BRow>
  </div>
</template>

<script>
import _ from 'lodash';
import PageHeader from '@/Shared/Components/PageHeader.vue';
import PurchaseOrdersTab from './PurchaseOrdersTab.vue';
import ProductsTab from './ProductsTab.vue';

export default {
  name: "InventoryManagement",
  components: { PageHeader, PurchaseOrdersTab, ProductsTab },
  props: ['dropdowns'],
  data() {
    return {
      activeTab: 'products',
      filter: {
        keyword: '',
      },
      listProducts: [],
      listPurchaseOrders: [],
      meta: null,
      links: null,
    };
  },
  watch: {
    'activeTab'(newVal) {
      if (newVal === 'products') {
        this.fetchProducts();
      } else if (newVal === 'purchaseOrders') {
        this.fetchPurchaseOrders();
      }
    }
  },
  created() {
    this.fetchProducts();
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
  },
};
</script>
