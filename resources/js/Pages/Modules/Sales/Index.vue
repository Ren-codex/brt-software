<template>
  <div>
    <Head title="Sales" />
    <PageHeader title="Sales Management" pageTitle="List" />

    <div class="inventory-container">
      <!-- Minimal Vertical Tabs -->
      <div class="inventory-sidebar">
        <div class="inventory-sidebar-header">
          <i class="ri-shopping-cart-2-line"></i>
          <h4>Sales</h4>
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
            <div :key="activeTab" class="inventory-tab-content">
              <div v-if="activeTab === 'sales_orders'" class="shadow-sm p-3">
                <SalesOrders :dropdowns="dropdowns"/>
              </div>

              <div v-if="activeTab === 'ar_invoices'" class="shadow-sm p-3">
                <ARInvoices :dropdowns="dropdowns"/>
              </div>

              <div v-if="activeTab === 'receipts'" class="shadow-sm p-3">
                 <Receipts :dropdowns="dropdowns"/>
              </div>

              <div v-if="activeTab === 'remittance'" class="shadow-sm p-3">
                <Remittances :dropdowns="dropdowns" />
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
import Pagination from '@/Shared/Components/Pagination.vue';
import SalesOrders from "@/Pages/Modules/Sales/Components/SalesOrders/Index.vue";
import ARInvoices from "@/Pages/Modules/Sales/Components/ARInvoices/Index.vue";
import Receipts from "@/Pages/Modules/Sales/Components/Receipts/Index.vue";
import Remittances from "@/Pages/Modules/Sales/Components/Remittances/Index.vue";

export default {
  components: { PageHeader, Pagination, SalesOrders, ARInvoices, Receipts, Remittances },
  props: ['dropdowns'],
  data() {
    return {
      activeTab: 'sales_orders',
      filter: {
        keyword: '',
      },
      listCustomers: [],
      meta: null,
      links: null,
      selectedRow: null,
      tabs: [
        {
          id: 'sales_orders',
          label: 'Sales Orders',
          icon: 'ri-shopping-bag-line',
          description: 'Manage sales orders'
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
      ]
    };
  },
  watch: {
    'activeTab'(newVal) {
      this.fetchSalesOrders();
    }
  },
  created() {
    this.fetchSalesOrders();
    this.debouncedSearch = _.debounce(this.fetchSalesOrders, 500);
  },
  methods: {
    changeTab(tab) {
      this.activeTab = tab;
      this.selectedRow = null;
      this.filter.keyword = '';
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
