<template>
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
    <div class="col-sm-8">
      <div class="row">
        <div class="col-lg-12">
          <div class="card">
            <div class="card-header">
              <div class="d-flex align-items-center">
                <h5 class="card-title mb-0 flex-grow-1">Purchase Order #{{ purchase_order_data.po_number }}</h5>
                <div class="flex-shrink-0">
                  <div class="d-flex gap-2">
                    <b-button v-if="canApprove && data.status?.name === 'pending'" variant="success"
                      @click="approvePurchaseOrder">
                      Approval
                    </b-button>
                    <b-button v-if="data.status?.name === 'approved'" variant="primary" @click="receiveStock">
                      Receive Stock
                    </b-button>
                    <template v-if="data.status?.name === 'pending' && data.created_by_id === $page.props.user.id">
                      <b-button @click="onDelete" variant="danger" v-b-tooltip.hover title="Delete"
                        style="margin-right: -10px">
                        <i class="ri-delete-bin-line align-bottom"></i>
                      </b-button>
                      <b-button @click="openEdit" variant="info" v-b-tooltip.hover title="Edit"
                        style="margin-right: -10px">
                        <i class="ri-pencil-fill align-bottom"></i>
                      </b-button>
                    </template>
                    <b-button @click="printPurchaseOrder" variant="secondary" v-b-tooltip.hover
                      style="margin-right: -2px">
                      <i class="ri-printer-line align-bottom"></i>
                    </b-button>
                    <b-button @click="$inertia.visit('/inventory?tab=purchaseOrders')">
                      <i class="ri-arrow-left-line align-bottom"></i>
                    </b-button>
                  </div>
                </div>
              </div>
            </div>
            <div class="card-body" v-if="data">
              <div class="row">
                <div class="col-lg-12">
                  <div class="row g-3">
                    <div class="col-md-4">
                      <div class="d-flex align-items-center">
                        <div class="avatar-xs me-3">
                          <div class="avatar-title bg-primary-subtle rounded">
                            <i class="ri-file-text-line text-primary fs-18"></i>
                          </div>
                        </div>
                        <div>
                          <h6 class="mb-1">PO Number</h6>
                          <p class="text-muted mb-0">{{ data.po_number }}</p>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="d-flex align-items-center">
                        <div class="avatar-xs me-3">
                          <div class="avatar-title bg-success-subtle rounded">
                            <i class="ri-calendar-line text-success fs-18"></i>
                          </div>
                        </div>
                        <div>
                          <h6 class="mb-1">Date</h6>
                          <p class="text-muted mb-0">{{ formatDate(data.po_date) }}</p>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="d-flex align-items-center">
                        <div class="avatar-xs me-3">
                          <div class="avatar-title bg-info-subtle rounded">
                            <i class="ri-store-line text-info fs-18"></i>
                          </div>
                        </div>
                        <div>
                          <h6 class="mb-1">Supplier</h6>
                          <p class="text-muted mb-0">{{ data.supplier ? data.supplier.name : 'N/A' }}</p>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="d-flex align-items-center">
                        <div class="avatar-xs me-3">
                          <div class="avatar-title bg-warning-subtle rounded">
                            <i class="ri-flag-line text-warning fs-18"></i>
                          </div>
                        </div>
                        <div>
                          <h6 class="mb-1">Status</h6>
                          <span
                            :style="{ color: data.status?.text_color, backgroundColor: data.status?.bg_color, padding: '0.25rem 0.5rem', borderRadius: '0.25rem' }">
                            {{ data.status?.name }}
                          </span>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="d-flex align-items-center">
                        <div class="avatar-xs me-3">
                          <div class="avatar-title bg-danger-subtle rounded">
                            <i class="ri-money-dollar-circle-line text-danger fs-18"></i>
                          </div>
                        </div>
                        <div>
                          <h6 class="mb-1">Total Amount</h6>
                          <p class="text-muted mb-0 fw-semibold">{{ formatCurrency(data.total_amount) }}</p>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="d-flex align-items-center">
                        <div class="avatar-xs me-3">
                          <div class="avatar-title bg-secondary-subtle rounded">
                            <i class="ri-user-line text-secondary fs-18"></i>
                          </div>
                        </div>
                        <div>
                          <h6 class="mb-1">Created By</h6>
                          <p class="text-muted mb-0">{{ data.created_by ? data.created_by.name : 'N/A' }}</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

              </div>
            </div>
          </div>
        </div>

      </div>

      <div class="row">
        <div class="col-lg-12">
          <div class="card">
            <div class="card-body">
              <h6 class="mb-3">Items</h6>
              <div class="table-responsive table-card">
                <table class="table align-middle table-striped table-centered mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>#</th>
                      <th>Product</th>
                      <th>Quantity</th>
                      <th>Unit Cost</th>
                      <th>Total Cost</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody class="table-white">
                    <tr v-for="(item, index) in data.items" :key="item.id">
                      <td>{{ index + 1 }}</td>
                      <td>{{ item.product ? item.product.brand.name : 'N/A' }}</td>
                      <td>{{ Math.floor(item.quantity) }}</td>
                      <td>{{ formatCurrency(item.unit_cost) }}</td>
                      <td>{{ formatCurrency(item.total_cost) }}</td>
                      <td>
                        <span v-if="item.status == 'received'" class="badge bg-success">Received</span>
                        <span v-else>-</span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

      </div>

    </div>
    <div class="col-sm-4">
      <div class="card">
        <div class="card-header"><label class="form-label mb-3">Transaction Logs</label></div>
        <div class="card-body">
          <div class="table-responsive table-card" v-if="data.logs && data.logs.length">
          <table class="table align-middle table-striped table-centered mb-0">
            <thead class="table-light">
              <tr>
                <th>Date</th>
                <th>User</th>
                <th>Action</th>
                <th>Remarks</th>
              </tr>
            </thead>
            <tbody class="table-white">
              <tr v-for="(log, index) in data.logs" :key="log.id">
                <td>{{ formatDate(log.created_at) }}</td>
                <td>{{ log.user ? log.user.name : 'N/A' }}</td>
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

  <CreatePurchaseOrderModal ref="createModal" :dropdowns="dropdowns" @add="handleEditSuccess" />
  <CreateReceivedStockModal ref="receiveModal" :dropdowns="dropdowns" :purchaseOrder="data"
    @add="handleReceiveSuccess" />
  <Delete ref="delete" @delete="handleDeleteSuccess" />

  <b-modal v-model="showModal" title="Approve Purchase Order" size="lg" centered hide-footer>
    <div class="mb-3">
      <label for="remarks" class="form-label">Remarks</label>
      <textarea id="remarks" v-model="remarks" class="form-control" rows="4"
        placeholder="Enter your remarks here..."></textarea>
    </div>
    <div slot="modal-footer">
      <b-button variant="secondary" @click="onCancel">Cancel</b-button>
      <b-button variant="danger" @click="updateStatus('disapproved')">Disapprove</b-button>
      <b-button variant="success" @click="updateStatus('approved')">Approve</b-button>
    </div>
  </b-modal>

  <!-- Toast Notification -->
  <div v-if="isToastVisible" class="toast-notification">
    <div class="toast-content">
      <i class="ri-check-line text-white me-2"></i>
      {{ toastMessage }}
    </div>
  </div>
</template>

<script>
import CreatePurchaseOrderModal from '../../Modal/CreatePurchaseOrderModal.vue';
import CreateReceivedStockModal from '../../Modal/CreateReceivedStockModal.vue';
import Delete from '@/Shared/Components/Modals/Delete.vue';

export default {
  components: {
    CreatePurchaseOrderModal,
    CreateReceivedStockModal,
    Delete,
  },
  props: {
    purchase_order_data: Object,
    dropdowns: Object,
  },
  data() {
    return {
      showModal: false,
      remarks: '',
      isToastVisible: false,
      toastMessage: '',
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
      this.showModal = true;
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
    },
    receiveStock() {
      this.$refs.receiveModal.show();
    },
    handleReceiveSuccess(status) {
      if (status == 'success') {
        this.showToast('Stock received successfully');
        this.$inertia.reload();
      } else {
        this.showToast('Failed to receive stock');
      }
    },
    updateStatus(status) {
      this.$inertia.put(`/purchase-orders/${this.data.id}/status`, {
        status_id: this.dropdowns.statuses.find(s => s.name === status).value,
        id: this.data.id,
        remarks: this.remarks
      }, {
        onSuccess: () => {
          this.showToast('Purchase order updated successfully');
          this.showModal = false;
          this.remarks = '';
        },
        onError: () => {
          this.showToast('Failed to approve purchase order');
        }
      });
    },
    onCancel() {
      this.showModal = false;
      this.remarks = '';
    },
    showToast(message) {
      this.toastMessage = message;
      this.isToastVisible = true;
      setTimeout(() => {
        this.isToastVisible = false;
      }, 3000);
    },
    printPurchaseOrder() {
      window.open(`/purchase-orders/${this.data.id}/print?type=purchase_order`, '_blank');
    },
  }
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