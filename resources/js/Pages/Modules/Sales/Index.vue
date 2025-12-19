<template>
  <div>
    <Head title="Sales" />
    <PageHeader title="Sales Management" pageTitle="List" />
    <BRow no-gutters>
      <BCol md="2" class="border-end" style="min-height: 80vh;">
        <ul class="nav flex-column nav-pills">

          <li class="nav-item">
            <a
              href="#"
              class="nav-link"
              :class="{ active: activeTab === 'sales_orders' }"
              @click.prevent="changeTab('sales_orders')"
              >Sales Orders</a
            >
          </li>

          <li class="nav-item">
            <a
              href="#"
              class="nav-link"
              :class="{ active: activeTab === 'ar_invoices' }"
              @click.prevent="changeTab('ar_invoices')"
              >Account Receivable Invoices</a
            >
          </li>
          <li class="nav-item">
            <a
              href="#"
              class="nav-link"
              :class="{ active: activeTab === 'receipts' }"
              @click.prevent="changeTab('receipts')"
              >Receipts</a
            >
          </li>
        </ul>
      </BCol>
      <BCol md="10">

        <div v-if="activeTab === 'sales_orders'" class="card shadow-sm p-3">
          <SalesOrders :dropdowns="dropdowns"/>
        </div>


        <div v-if="activeTab === 'ar_invoices'" class="card shadow-sm p-3">
          <ARInvoices :dropdowns="dropdowns"/>
        </div>

        <div v-if="activeTab === 'receipts'" class="card shadow-sm p-3">
          <h5>Receipts</h5>
          <p>>Receipts content goes here.</p>
        </div>
      </BCol>
    </BRow>
  </div>

    
</template>

<script>
import _ from 'lodash';
import PageHeader from '@/Shared/Components/PageHeader.vue';
import Pagination from '@/Shared/Components/Pagination.vue';
import SalesOrders from "@/Pages/Modules/Sales/Components/SalesOrders/Index.vue";
import ARInvoices from "@/Pages/Modules/Sales/Components/ARInvoices/Index.vue";

export default {
  components: { PageHeader, Pagination, SalesOrders, ARInvoices },
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
