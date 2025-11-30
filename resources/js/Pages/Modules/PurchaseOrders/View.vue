<template>
  <div class="container-fluid">
    <div class="row">
      <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
          <h4 class="mb-sm-0">Purchase Order Details</h4>
          <div class="page-title-right">
            <ol class="breadcrumb m-0">
              <li class="breadcrumb-item">
                <router-link to="/inventory">Inventory</router-link>
              </li>
              <li class="breadcrumb-item">
                <router-link to="/inventory?tab=purchaseOrders">Purchase Orders</router-link>
              </li>
              <li class="breadcrumb-item active">View</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-lg-12">
        <div class="card">
          <div class="card-header">
            <div class="d-flex align-items-center">
              <h5 class="card-title mb-0 flex-grow-1">Purchase Order #{{ purchase_order_data.po_number }}</h5>
              <div class="flex-shrink-0">
                <div class="d-flex gap-2">
                  <b-button
                    v-if="canApprove && data.status?.name === 'pending'"
                    variant="success"
                    @click="approvePurchaseOrder"
                  >
                    <i class="ri-check-line align-bottom me-1"></i> Approve
                  </b-button>
                  <b-button variant="secondary" @click="$inertia.visit('/inventory?tab=purchaseOrders')">
                    <i class="ri-arrow-left-line align-bottom me-1"></i> Back
                  </b-button>
                </div>
              </div>
            </div>
          </div>

          <div class="card-body" v-if="data">
            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">PO Number</label>
                  <p class="text-muted">{{ data.po_number }}</p>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Date</label>
                  <p class="text-muted">{{ formatDate(data.po_date) }}</p>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Supplier</label>
                  <p class="text-muted">{{ data.supplier ? data.supplier.name : 'N/A' }}</p>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Status</label>
                  <span :style="{ color: data.status?.text_color, backgroundColor: data.status?.bg_color, padding: '0.25rem 0.5rem', borderRadius: '0.25rem' }">
                    {{ data.status?.name }}
                  </span>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Total Amount</label>
                  <p class="text-muted">{{ formatCurrency(data.total_amount) }}</p>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Created By</label>
                  <p class="text-muted">{{ data.created_by ? data.created_by.profile.name : 'N/A' }}</p>
                </div>
              </div>
            </div>
            <hr>

            <h6 class="mb-3">Items</h6>
            <div class="table-responsive">
              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Unit Cost</th>
                    <th>Total Cost</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in data.items" :key="item.id">
                    <td>{{ index + 1 }}</td>
                    <td>{{ item.product ? item.product.name : 'N/A' }}</td>
                    <td>{{ Math.floor(item.quantity) }}</td>
                    <td>{{ formatCurrency(item.unit_cost) }}</td>
                    <td>{{ formatCurrency(item.total_cost) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    purchase_order_data: Object,
    dropdowns: Object,
  },
  data() {
    return {
    //   data: null,
    };
  },
  computed: {
    data() {
      return this.purchase_order_data.data;
    },
    canApprove() {
      const roles = this.$page.props.roles;
      const userRoles = roles ? Object.values(roles) : [];
      return userRoles.some(role => ['Inventory Manager', 'Top Management', 'Administrator'].includes(role));
    }
  },
  methods: {
    formatDate(date) {
      return new Date(date).toLocaleDateString();
    },
    formatCurrency(amount) {
      return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP'
      }).format(amount);
    },
    approvePurchaseOrder() {
      if (confirm('Are you sure you want to approve this purchase order?')) {
        this.$inertia.put(`/purchase-orders/${this.purchase_order_data.id}`, {
          status_id: this.dropdowns.statuses.find(s => s.name === 'Approved').id
        }, {
          onSuccess: () => {
            this.$toast.success('Purchase order approved successfully');
          },
          onError: () => {
            this.$toast.error('Failed to approve purchase order');
          }
        });
      }
    }
  }
};
</script>
