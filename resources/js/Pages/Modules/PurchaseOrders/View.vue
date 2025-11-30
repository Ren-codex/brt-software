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
                  <template v-if="data.status?.name === 'pending' && data.created_by_id === $page.props.user.id">
                    <b-button @click="onDelete" variant="danger" v-b-tooltip.hover title="Delete" style="margin-right: -10px">
                      <i class="ri-delete-bin-line align-bottom"></i>
                    </b-button>
                    <b-button @click="openEdit" variant="info" v-b-tooltip.hover title="Edit" style="margin-right: -10px">
                      <i class="ri-pencil-fill align-bottom"></i>
                    </b-button>
                  </template>
                  <b-button @click="$inertia.visit('/inventory?tab=purchaseOrders')">
                    <i class="ri-arrow-left-line align-bottom"></i>
                  </b-button>
                </div>
              </div>
            </div>
          </div>

          <div class="card-body" v-if="data">
            <div class="row">
              <div class="col-lg-8">
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

              <div class="col-lg-4">
                <h6 class="mb-3">Transaction Logs</h6>
                <div class="table-responsive" v-if="data.logs && data.logs.length">
                  <table class="table table-bordered">
                    <thead>
                      <tr>
                        <th>Date</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Remarks</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(log, index) in data.logs" :key="log.id">
                        <td>{{ formatDate(log.created_at) }}</td>
                        <td>{{ log.user ? log.user.profile.name : 'N/A' }}</td>
                        <td>{{ log.action }}</td>
                        <td>{{ log.remarks }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <p v-else>No transaction logs available.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <CreatePurchaseOrderModal ref="createModal" :dropdowns="dropdowns" @add="handleEditSuccess" />
  <Delete ref="delete" @delete="handleDeleteSuccess" />
</template>

<script>
import CreatePurchaseOrderModal from '../Inventory/Modal/CreatePurchaseOrderModal.vue';
import Delete from '@/Shared/Components/Modals/Delete.vue';

export default {
  components: {
    CreatePurchaseOrderModal,
    Delete,
  },
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
    },
    openEdit() {
      this.$refs.createModal.edit(this.purchase_order_data.data);
    },
    onDelete() {
      let title = "Purchase Order";
      this.$refs.delete.show(this.data.id, title, '/purchase-orders');
    },
    handleDeleteSuccess() {
      this.$inertia.visit('/inventory?tab=purchaseOrders');
    },
    handleEditSuccess() {
      this.$inertia.reload();
    }
  }
};
</script>
