<template>
  <div class="purchase-order-details">
    <div class="row" v-if="purchaseOrder">
      <div class="col-sm-8">
        <div class="row">
          <div class="col-lg-12 mb-4">
            <div class="library-card">
              <div class="library-card-header">
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
                    <template v-if="purchaseOrder.status?.name === 'pending' && purchaseOrder.created_by_id === $page.props.user.id">
                      <button @click="editPurchaseOrder" class="create-btn" v-b-tooltip.hover title="Edit">
                        <i class="ri-pencil-fill"></i>
                      </button>
                      <button @click="deletePurchaseOrder" class="create-btn" v-b-tooltip.hover title="Delete">
                        <i class="ri-delete-bin-line"></i>
                      </button>
                    </template>
                    <button v-if="canVoid" class="create-btn" v-b-tooltip.hover title="Void" @click="voidPurchaseOrder">
                      <i class="ri-close-circle-line"></i>
                      <span>Void</span>
                    </button>
                    <button @click="printPurchaseOrder" class="create-btn" v-b-tooltip.hover title="Print">
                      <i class="ri-printer-line"></i>
                    </button>
                    <button @click="$emit('back')" class="create-btn" v-b-tooltip.hover title="Back">
                      <i class="ri-arrow-left-line"></i>
                    </button>
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
                      <div class="col-md-6">
                        <div class="mb-3">
                          <label class="form-label">Approved By</label>
                          <p class="text-muted">
                            {{ purchaseOrder.approved_by?.name || 'N/A' }}
                            <span v-if="purchaseOrder.approved_by && purchaseOrder.approved_date">
                              ({{ formatDate(purchaseOrder.approved_date) }})
                            </span>
                          </p>
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
                          <th>Received</th>
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
                            {{ Math.floor(item.received_quantity) }}
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

        <div class="row" v-if="activeTab === 'purchaseOrders'">
          <div class="col-lg-12 mb-4">
            <div class="library-card">
              <div class="library-card-header">
                <div class="d-flex align-items-center gap-3">
                  <div class="header-icon">
                    <i class="ri-barcode-box-line"></i>
                  </div>
                  <div>
                    <h4 class="header-title mb-1">Batches</h4>
                    <p class="header-subtitle mb-0">Batch records for this purchase order</p>
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
                          <th>Batch Code</th>
                          <th>Date Received</th>
                          <th>Product</th>
                          <th>Received Qty</th>
                          <th>Current Qty</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="(batch, index) in batchRows" :key="`${batch.batch_code}-${batch.product_id}-${index}`">
                          <td>{{ index + 1 }}</td>
                          <td><strong>{{ batch.batch_code }}</strong></td>
                          <td>{{ formatDate(batch.received_date) || 'N/A' }}</td>
                          <td>{{ batch.product_name || 'N/A' }}</td>
                          <td>{{ Math.floor(batch.received_quantity || 0) }}</td>
                          <td>{{ Math.floor(batch.quantity || 0) }}</td>
                        </tr>
                        <tr v-if="batchRows.length === 0">
                          <td colspan="6" class="text-center py-3 text-muted">No batches yet</td>
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
        <TransactionLogs :logs="purchaseOrder.logs" :compact="true" :initial-visible="5" :logs-per-page="5" />
      </div>
    </div>
  </div>
  <div v-if="showModal" class="modal-overlay active" @click.self="onCancel">
    <div class="modal-container modal-lg">
      <div class="modal-header">
        <h2>Approve Purchase Order</h2>
        <button class="close-btn" @click="onCancel">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label" for="remarks">Remarks</label>
          <textarea
            id="remarks"
            v-model="remarks"
            class="form-control textarea-control"
            rows="4"
            placeholder="Enter your remarks here..."
          ></textarea>
        </div>
        <div class="form-actions">
        <button class="btn btn-cancel" @click="onCancel">Cancel</button>
        <button class="btn btn-cancel" @click="updateStatus('Disapproved')">Disapprove</button>
        <button class="btn btn-save" @click="updateStatus('Approved')">Approve</button>
      </div>
      </div>
      
    </div>
  </div>

  <div v-if="showVoidModal" class="modal-overlay active" @click.self="onCancelVoid">
    <div class="modal-container modal-lg">
      <div class="modal-header">
        <h2>Void Purchase Order</h2>
        <button class="close-btn" @click="onCancelVoid">&times;</button>
      </div>
      <div class="modal-body">
        <div class="void-warning">
          <div class="void-warning-icon">
            <i class="ri-alert-fill"></i>
          </div>
          <div>
            <h5>You're about to void PO #{{ purchaseOrder?.po_number || purchaseOrder?.pr_number }}</h5>
            <p>This action cannot be undone. The purchase order will be cancelled and can no longer be approved or received against.</p>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label" for="void-remarks">Reason for voiding</label>
          <textarea
            id="void-remarks"
            v-model="voidRemarks"
            class="form-control textarea-control"
            rows="4"
            placeholder="Enter the reason for voiding this purchase order..."
          ></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-cancel" @click="onCancelVoid">Keep Order</button>
        <button class="btn btn-void" @click="confirmVoid">
          <i class="ri-close-circle-line"></i>
          Void Purchase Order
        </button>
      </div>
    </div>
  </div>

  <!-- Toast Notification -->
  <div v-if="isToastVisible" class="toast-notification">
    <div class="toast-content">
      <i class="ri-check-line text-white me-2"></i>
      {{ toastMessage }}
    </div>
  </div>
</template>

<script>
import TransactionLogs from '@/Shared/Components/TransactionLogsCard.vue';

export default {
  name: "PurchaseOrderDetails",
  components: { TransactionLogs },
  props: {
    purchaseOrder: Object,
    dropdowns: Object,
    activeTab: {
      type: String,
      default: '',
    },
  },
  emits: ['back', 'toast', 'fetch'],
  data() {
    return {
      showModal: false,
      remarks: '',
      showVoidModal: false,
      voidRemarks: '',
      isToastVisible: false,
      toastMessage: '',
    };
  },
  computed: {
    canApprove() {
      return this.can('inventory', 'purchase_orders', 'approver');
    },
    canVoid() {
      if (!this.can('inventory', 'purchase_orders', 'approver')) return false;
      if (!this.purchaseOrder) return false;
      const statusName = this.purchaseOrder.status?.name;
      if (['Voided', 'Disapproved'].includes(statusName)) return false;
      const hasReceivedItems = (this.purchaseOrder.items || []).some(item => Number(item.received_quantity || 0) > 0);
      if (hasReceivedItems) return false;
      if (!this.purchaseOrder.approved_by_id) return false;
      return this.purchaseOrder.approved_by_id === this.$page.props.user.data.id;
    },
    batchRows() {
      if (!this.purchaseOrder?.items?.length) return [];

      return this.purchaseOrder.items.flatMap((item) => {
        return (item.batches || []).map((batch) => ({
          ...batch,
          product_id: item.product?.id || item.product_id || null,
          product_name: item.product?.brand?.name || item.product?.name || 'N/A',
        }));
      });
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
          setTimeout(() => {
            this.$inertia.visit('/inventory');
          }, 1200);
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

    voidPurchaseOrder() {
      this.showVoidModal = true;
    },

    confirmVoid() {
      this.$inertia.patch(`/purchase-orders/${this.purchaseOrder.id}/void`, {
        remarks: this.voidRemarks,
      }, {
        onSuccess: () => {
          this.showVoidModal = false;
          this.voidRemarks = '';
          this.showToast('Purchase order voided successfully');
          setTimeout(() => {
            this.$inertia.visit('/inventory');
          }, 1200);
        },
        onError: (errors) => {
          this.showToast(errors.void || 'Failed to void purchase order');
        }
      });
    },

    onCancelVoid() {
      this.showVoidModal = false;
      this.voidRemarks = '';
    },
    showToast(message) {
      this.toastMessage = message;
      this.isToastVisible = true;
      setTimeout(() => {
        this.isToastVisible = false;
      }, 3000);
    },
  }
};
</script>

<style scoped>
.void-warning {
  display: flex;
  gap: 0.75rem;
  align-items: flex-start;
  border-radius: 12px;
  padding: 0.85rem 1rem;
  margin-bottom: 1rem;
  border: 1px solid rgba(220, 53, 69, 0.25);
  background: rgba(220, 53, 69, 0.06);
}

.void-warning-icon {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  flex-shrink: 0;
  display: grid;
  place-items: center;
  background: rgba(220, 53, 69, 0.12);
  color: #dc3545;
  font-size: 1.1rem;
}

.void-warning h5 {
  margin: 0 0 0.2rem;
  font-size: 0.9rem;
  font-weight: 700;
  color: #58151c;
}

.void-warning p {
  margin: 0;
  font-size: 0.8rem;
  line-height: 1.5;
  color: #7a3c42;
}

.btn-void {
  background: linear-gradient(135deg, #dc3545 0%, #b91c1c 100%);
  color: #fff;
  box-shadow: 0 4px 12px rgba(185, 28, 28, 0.3);
}

.btn-void:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(185, 28, 28, 0.4);
}

.btn-void:active {
  transform: translateY(0);
}

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
