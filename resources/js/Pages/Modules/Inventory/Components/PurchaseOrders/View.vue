<template>
  <div class="purchase-order-details">
    <div class="row" v-if="purchaseOrder">
      <div class="col-sm-8">
        <div class="row">
          <div class="col-lg-12 mb-4">
            <div class="library-card">
              <div class="library-card-header">
                <div class="d-flex align-items-center justify-content-between">
                  <div class="d-flex align-items-center gap-3">
                    <div class="header-icon">
                      <i class="ri-shopping-cart-line"></i>
                    </div>
                    <div>
                      <h4 class="header-title mb-1">Purchase Order #{{ purchaseOrder.po_number }}</h4>
                      <p class="header-subtitle mb-0">View and manage purchase order details</p>
                    </div>
                  </div>
                  <div class="d-flex gap-2">
                    <button v-if="canApprove && purchaseOrder.status?.name === 'Pending'" class="create-btn"
                            @click="approvePurchaseOrder">
                      <i class="ri-check-line"></i>
                      <span>Approval</span>
                    </button>
                    <button v-if="purchaseOrder.status?.name === 'Approved'" class="create-btn" @click="receiveStock">
                      <i class="ri-inbox-line"></i>
                      <span>Receive Stock</span>
                    </button>
                    <template v-if="purchaseOrder.status?.name === 'pending' && purchaseOrder.created_by_id === $page.props.user.id">
                      <button @click="editPurchaseOrder" class="create-btn" v-b-tooltip.hover title="Edit">
                        <i class="ri-pencil-fill"></i>
                      </button>
                      <button @click="deletePurchaseOrder" class="create-btn" v-b-tooltip.hover title="Delete">
                        <i class="ri-delete-bin-line"></i>
                      </button>
                    </template>
                    <button @click="printPurchaseOrder" class="create-btn" v-b-tooltip.hover title="Print">
                      <i class="ri-printer-line"></i>
                    </button>
                    <button @click="$emit('back')" class="create-btn" v-b-tooltip.hover title="Back">
                      <i class="ri-arrow-left-line"></i>
                    </button>
                  </div>
                </div>
              </div>
              <div class="library-card-body">
                <div class="row">
                  <div class="col-lg-8">
                    <div class="row">
                      <div class="col-md-6">
                        <div class="mb-3">
                          <label class="form-label">Date</label>
                          <p class="text-muted">{{ formatDate(purchaseOrder.po_date) }}</p>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="mb-3">
                          <label class="form-label">Supplier</label>
                          <p class="text-muted">{{ purchaseOrder.supplier?.name || 'N/A' }}</p>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="mb-3">
                          <label class="form-label">Status</label>
                          <span
                            :style="{ color: purchaseOrder.status?.text_color, backgroundColor: purchaseOrder.status?.bg_color, padding: '0.25rem 0.5rem', borderRadius: '0.25rem' }">
                            {{ purchaseOrder.status?.name }}
                          </span>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="mb-3">
                          <label class="form-label">Total Amount</label>
                          <p class="text-muted">{{ formatCurrency(purchaseOrder.total_amount) }}</p>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="mb-3">
                          <label class="form-label">Created By</label>
                          <p class="text-muted">{{ purchaseOrder.created_by?.name || 'N/A' }}</p>
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
          <div class="col-lg-12 mb-4">
            <div class="library-card">
              <div class="library-card-header">
                <div class="d-flex align-items-center gap-3">
                  <div class="header-icon">
                    <i class="ri-list-check"></i>
                  </div>
                  <div>
                    <h4 class="header-title mb-1">Items</h4>
                    <p class="header-subtitle mb-0">Purchase order items and details</p>
                  </div>
                </div>
              </div>
              <div class="library-card-body">
                <div class="table-section">
                  <div class="table-responsive">
                    <table class="table align-middle table-centered mb-0">
                      <thead>
                        <tr>
                          <th>#</th>
                          <th>Product</th>
                          <th>Quantity</th>
                          <th>Unit Cost</th>
                          <th>Total Cost</th>
                          <th>Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="(item, index) in purchaseOrder.items" :key="item.id">
                          <td>{{ index + 1 }}</td>
                          <td>{{ item.product?.brand?.name || 'N/A' }}</td>
                          <td>{{ Math.floor(item.quantity) }}</td>
                          <td>{{ formatCurrency(item.unit_cost) }}</td>
                          <td>{{ formatCurrency(item.total_cost) }}</td>
                          <td>
                            <span v-if="item.status === 'received'" class="badge bg-success">Received</span>
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
      </div>
      <div class="col-sm-4">
        <div class="library-card">
          <div class="library-card-header">
            <div class="d-flex align-items-center gap-3">
              <div class="header-icon">
                <i class="ri-history-line"></i>
              </div>
              <div>
                <h4 class="header-title mb-1">Transaction Logs</h4>
                <p class="header-subtitle mb-0">Activity history and remarks</p>
              </div>
            </div>
          </div>
          <div class="library-card-body">
            <div class="table-section" v-if="purchaseOrder.logs && purchaseOrder.logs.length">
              <div class="table-responsive">
                <table class="table align-middle table-centered mb-0">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>User</th>
                      <th>Action</th>
                      <th>Remarks</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(log, index) in purchaseOrder.logs" :key="log.id">
                      <td>{{ formatDate(log.created_at) }}</td>
                      <td>{{ log.user?.name || 'N/A' }}</td>
                      <td>{{ log.action }}</td>
                      <td>{{ log.remarks }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <p v-else>No transaction logs available.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
  <b-modal v-model="showModal" title="Approve Purchase Order" size="lg" centered hide-footer>
    <div class="mb-3">
      <label for="remarks" class="form-label">Remarks</label>
      <textarea
        id="remarks"
        v-model="remarks"
        class="form-control"
        rows="4"
        placeholder="Enter your remarks here..."
      ></textarea>
    </div>
    <div slot="modal-footer">
      <b-button variant="secondary" @click="onCancel">Cancel</b-button>
      <b-button variant="danger" @click="updateStatus('Disapproved')">Disapprove</b-button>
      <b-button variant="success" @click="updateStatus('Approved')">Approve</b-button>
    </div>
  </b-modal>
  
  <!-- Toast Notification -->
  <div v-if="isToastVisible" class="toast-notification">
    <div class="toast-content">
      <i class="ri-check-line text-white me-2"></i>
      {{ toastMessage }}
    </div>
  </div>

  <CreateReceivedStockModal ref="receiveModal" :dropdowns="dropdowns" @add="handleReceiveSuccess" />
</template>

<script>
import CreateReceivedStockModal from '../../Modal/CreateReceivedStockModal.vue';

export default {
  name: "PurchaseOrderDetails",
  components: {
    CreateReceivedStockModal,
  },
  props: {
    purchaseOrder: Object,
    dropdowns: Object,
  },
  emits: ['back', 'toast', 'fetch'],
  data() {
    return {
      showModal: false,
      remarks: '',
      isToastVisible: false,
      toastMessage: '',
    };
  },
  computed: {
    canApprove() {
      const roles = this.$page.props.roles;
      const userRoles = roles ? Object.values(roles) : [];
      return userRoles.some(role => ['Inventory Manager', 'Top Management', 'Administrator'].includes(role));
    }
  },
  methods: {
    formatDate(dateString) {
      if (!dateString) return '';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    },
    
    formatCurrency(value) {
      if (!value && value !== 0) return '₱0.00';
      return '₱' + Number(value).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    },
    
    getStatusStyle(status) {
      if (!status) return {};
      return {
        color: status.text_color || '#000000',
        backgroundColor: status.bg_color || '#ffffff',
        border: `1px solid ${status.bg_color ? status.bg_color + '40' : '#cccccc'}`,
      };
    },
    
    approvePurchaseOrder() {
      this.showModal = true;
    },
    
    editPurchaseOrder() {
      // This would open the edit modal in the parent component
      this.$emit('toast', 'Edit functionality would open the edit modal');
    },
    
    deletePurchaseOrder() {
      // This would trigger the delete modal in the parent component
      this.$emit('toast', 'Delete functionality would open the delete modal');
    },
    
    receiveStock() {
      this.$refs.receiveModal.show(this.purchaseOrder);
    },
    
    printPurchaseOrder() {
      if (this.purchaseOrder) {
        window.open(`/purchase-orders/${this.purchaseOrder.id}/print?type=purchase_order`, '_blank');
      }
    },

    updateStatus(status) {
      this.$inertia.put(`/purchase-orders/${this.purchaseOrder.id}/status`, {
        status_id: this.dropdowns.statuses.find(s => s.name === status).value,
        id: this.purchaseOrder.id,
        remarks: this.remarks
      }, {
        onSuccess: () => {
          this.showToast('Purchase order updated successfully');
          this.showModal = false;
          this.remarks = '';
          this.$inertia.visit('/inventory');
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
    handleReceiveSuccess(status) {
      console.log(status);
      
      if(status == 'success') {
        this.showToast('Stock received successfully');
        this.$inertia.visit('/inventory');
      } else {
        this.showToast('Failed to receive stock');
      }
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
