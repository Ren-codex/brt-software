<template>
  <div class="stock-return-details">
    <div class="row" v-if="data">
      <div class="col-sm-8">
        <div class="library-card">
          <div class="library-card-header">
            <div class="d-flex align-items-center justify-content-between">
              <div class="d-flex align-items-center gap-3">
                <div class="header-icon">
                  <i class="ri-arrow-go-back-line"></i>
                </div>
                <div>
                  <h4 class="header-title mb-1">
                    Stock Return #{{ data.stock_return_no || data.id }}
                  </h4>
                  <p class="header-subtitle mb-0">View stock return details</p>
                </div>
              </div>
              <div class="d-flex gap-2">
                <button class="create-btn" @click="approveStockReturn()" v-if="data.status.slug == 'pending'">
                  <span>{{ approving ? 'Saving...' : 'Approve Return' }}</span>
                </button>
                <button
                  class="create-btn"
                  @click="logStockReturnDelivery()"
                  v-if="canLogDelivery"
                >
                  <span>{{ receiving ? 'Saving...' : 'Supplier Received the Items' }}</span>
                </button>
                <button @click="$emit('back')" class="create-btn" v-b-tooltip.hover title="Back">
                  <i class="ri-arrow-left-line"></i>
                </button>
              </div>
            </div>
          </div>
          <div class="library-card-body" style="padding: 1.5rem;">
            <!-- Info Grid -->
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
              <!-- PO Number -->
              <div style="background: #f8f9fa; padding: 0.75rem 1rem; border-radius: 10px;">
                <span style="color: #6c757d; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.3px; display: block;">PO Number</span>
                <span style="color: #2c3e50; font-weight: 500;">{{ data.purchase_order?.po_number || 'N/A' }}</span>
              </div>

              <!-- Supplier -->
              <div style="background: #f8f9fa; padding: 0.75rem 1rem; border-radius: 10px;">
                <span style="color: #6c757d; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.3px; display: block;">Supplier</span>
                <span style="color: #2c3e50; font-weight: 500;">{{ data.purchase_order?.supplier?.name || 'N/A' }}</span>
              </div>

              <!-- Status -->
              <div style="background: #f8f9fa; padding: 0.75rem 1rem; border-radius: 10px;">
                <span style="color: #6c757d; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.3px; display: block;">Status</span>
                <span :style="getStatusStyle(data.status)" class="status-badge">
                  {{ data.status?.name || 'Pending' }}
                </span>
              </div>

              <!-- Created By -->
              <div style="background: #f8f9fa; padding: 0.75rem 1rem; border-radius: 10px;">
                <span style="color: #6c757d; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.3px; display: block;">Created By</span>
                <span style="color: #2c3e50; font-weight: 500;">{{ data.created_by?.fullname || 'N/A' }}</span>
              </div>

              <!-- Reason (full width) -->
              <div style="grid-column: span 2; background: #f8f9fa; padding: 0.75rem 1rem; border-radius: 10px;">
                <span style="color: #6c757d; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.3px; display: block;">Reason</span>
                <span style="color: #2c3e50;">{{ data.reason || 'N/A' }}</span>
              </div>

              <!-- Created At -->
              <div style="background: #f8f9fa; padding: 0.75rem 1rem; border-radius: 10px;">
                <span style="color: #6c757d; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.3px; display: block;">Created At</span>
                <span style="color: #2c3e50;">{{ formatDate(data.created_at) }}</span>
              </div>

              <!-- Approved At -->
              <div style="background: #f8f9fa; padding: 0.75rem 1rem; border-radius: 10px;">
                <span style="color: #6c757d; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.3px; display: block;">Approved At</span>
                <span style="color: #2c3e50;" v-if="data.approved_at">{{ formatDate(data.approved_at) }} by <b>{{ data.approved_by?.fullname || 'N/A' }}</b></span>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-sm-4">
        <TransactionLogs
          :logs="normalizedStockReturnLogs"
          title="Transaction Logs"
          subtitle="History of actions for this stock return"
          :compact="true"
          :initial-visible="3"
          :logs-per-page="3"
        />
      </div>
      <div class="col-sm-12">
        <div class="library-card mt-3">
          <div class="library-card-header">
            <div class="d-flex align-items-center gap-3">
              <div class="header-icon">
                <i class="ri-list-check"></i>
              </div>
              <div>
                <h4 class="header-title mb-1">Returned Items</h4>
                <p class="header-subtitle mb-0">Products and quantities included in this return</p>
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
                      <th>Returned Qty</th>
                      <th>Replaced Qty</th>
                      <th>Loss Qty</th>
                      <th>Status</th>
                      <th>Remarks</th>
                      <th>Received By</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(item, index) in data.items || []" :key="item.id">
                      <td>{{ index + 1 }}</td>
                      <td>{{ item.purchase_order_item?.product?.name || 'N/A' }}</td>
                      <td>{{ item.quantity || 0 }}</td>
                      <td>{{ item.replaced_quantity || 0 }}</td>
                      <td>{{ item.loss_quantity || 0 }}</td>
                      <td>
                        <span :style="getStatusStyle(item.status)" class="status-badge">
                          {{ item.status?.name || 'Pending' }}
                        </span>
                      </td>
                      <td>{{ item.remarks || '' }}</td>
                      <td>{{ item.received_by?.fullname || '-' }}</td>
                      <td v-if="canReceiveReturnedItem(item)">
                        <div class="action-buttons">
                          <button
                            class="action-btn action-btn-receive"
                            @click="receivedReturnItem(item)"
                            v-b-tooltip.hover
                            title="Receive Return Item"
                          >
                            Receive Stock
                          </button>
                        </div>
                      </td>
                      <td v-else>
                        <span class="text-muted">No action</span>
                      </td>
                    </tr>
                    <tr v-if="!(data.items || []).length">
                      <td colspan="9" class="text-center py-3">No return items found.</td>
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

  <div v-if="showModal" class="modal-overlay active" @click.self="onCancel">
    <div class="modal-container modal-lg">
      <div class="modal-header">
        <h2>Approve Stock Return</h2>
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
          <button class="btn btn-cancel" @click="onCancel" :disabled="approving">Cancel</button>
          <button class="btn btn-cancel" @click="updateStatus('disapproved')" :disabled="approving">Disapprove</button>
          <button class="btn btn-save" @click="updateStatus('approved')" :disabled="approving">Approve</button>
        </div>
      </div>
    </div>
  </div>

  <ReceiveReturnItemModal
    :show="showReceiveModal"
    :selected-return-item="selectedReturnItem"
    :receive-form="receiveForm"
    :receiving="receiving"
    @close="onReceiveCancel"
    @save="saveReceivedReturnItem"
    @update-receive-form="updateReceiveForm"
  />
</template>

<script>
import TransactionLogs from '@/Shared/Components/TransactionLogsCard.vue';
import ReceiveReturnItemModal from '../../Modal/ReceiveReturnItemModal.vue';

export default {
  name: 'StockReturnDetails',
  components: { TransactionLogs, ReceiveReturnItemModal },
  emits: ['back', 'toast', 'refresh'],
  props: {
    stockReturn: Object,
  },
  data() {
    return {
      approving: false,
      showModal: false,
      remarks: '',
      receiving: false,
      showReceiveModal: false,
      selectedReturnItem: null,
      receiveForm: {
        replaced_quantity: 0,
        loss_quantity: 0,
        remarks: '',
      },
    };
  },
  computed: {
    data() {
      return this.stockReturn || null;
    },
    isPending() {
      const statusSlug = String(this.data?.status?.slug || '').toLowerCase();
      const statusName = String(this.data?.status?.name || '').toLowerCase();
      return statusSlug === 'pending' || statusName === 'pending';
    },
    isApproved() {
      const statusSlug = String(this.data?.status?.slug || '').toLowerCase();
      const statusName = String(this.data?.status?.name || '').toLowerCase();
      return Boolean(this.data?.approved_at) || statusSlug === 'approved' || statusName === 'approved';
    },
    totalRequestedQty() {
      return (this.data?.items || []).reduce((total, item) => total + Number(item.quantity || 0), 0);
    },
    totalReturnedQty() {
      return (this.data?.items || []).reduce((total, item) => total + Number(item.returned_quantity || 0), 0);
    },
    stockReturnLogs() {
      return this.data?.stock_return_logs || [];
    },
    normalizedStockReturnLogs() {
      return this.stockReturnLogs.map((log) => ({
        ...log,
        actioned_by: log.actioned_by || log.user?.fullname || 'System',
      }));
    },
    canLogDelivery() {
      const statusSlug = String(this.data?.status?.slug || '').toLowerCase();
      if (statusSlug !== 'approved') return false;

      return !this.hasSupplierDeliveryLog;
    },
    hasSupplierDeliveryLog() {
      return this.stockReturnLogs.some((log) => String(log?.action || '').toLowerCase() === 'supplier delivery logged');
    },
  },
  methods: {
    formatDate(dateValue) {
      if (!dateValue) return 'N/A';
      const date = new Date(dateValue);
      if (Number.isNaN(date.getTime())) return 'N/A';
      return date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
      });
    },
    approveStockReturn() {
      if (!this.data?.id || this.approving || !this.isPending) return;
      this.showModal = true;
    },
    onCancel() {
      this.showModal = false;
      this.remarks = '';
    },
    isItemFinalized(item) {
      const statusSlug = String(item?.status?.slug || '').toLowerCase();
      return ['replaced', 'loss'].includes(statusSlug);
    },
    canReceiveReturnedItem(item) {
      const statusSlug = String(this.data?.status?.slug || '').toLowerCase();
      if (!['approved', 'delivered'].includes(statusSlug)) return false;
      if (!this.hasSupplierDeliveryLog) return false;

      return Number(item?.returned_quantity || 0) < Number(item?.quantity || 0);
    },
    receivedReturnItem(item) {
      if (!item?.id || this.receiving || this.isItemFinalized(item)) return;

      const remainingQty = Math.max(Number(item.quantity || 0) - Number(item.returned_quantity || 0), 0);
      this.selectedReturnItem = item;
      this.receiveForm.replaced_quantity = remainingQty > 0 ? remainingQty : 0;
      this.receiveForm.loss_quantity = 0;
      this.receiveForm.remarks = item.remarks || '';
      this.showReceiveModal = true;
    },
    onReceiveCancel(force = false) {
      if (this.receiving && !force) return;
      this.showReceiveModal = false;
      this.selectedReturnItem = null;
      this.receiveForm = {
        replaced_quantity: 0,
        loss_quantity: 0,
        remarks: '',
      };
    },
    updateReceiveForm(patch) {
      this.receiveForm = {
        ...this.receiveForm,
        ...patch,
      };
    },
    async saveReceivedReturnItem() {
      if (!this.data?.id || !this.selectedReturnItem?.id || this.receiving) return;

      const maxQty = Number(this.selectedReturnItem.quantity || 0);
      const alreadyResolvedQty = Number(this.selectedReturnItem.returned_quantity || 0);
      const remainingQty = Math.max(maxQty - alreadyResolvedQty, 0);
      const replacedQty = Number(this.receiveForm.replaced_quantity || 0);
      const lossQty = Number(this.receiveForm.loss_quantity || 0);
      const totalQty = replacedQty + lossQty;
      if (
        Number.isNaN(replacedQty)
        || Number.isNaN(lossQty)
        || replacedQty < 0
        || lossQty < 0
        || totalQty < 1
        || totalQty > remainingQty
      ) {
        this.$emit('toast', `Replacement quantity plus loss quantity must be between 1 and ${remainingQty}`);
        return;
      }

      this.receiving = true;
      try {
        const response = await axios.post(
          `/stock-returns/${this.data.id}/items/${this.selectedReturnItem.id}/receive`,
          {
            replaced_quantity: replacedQty,
            loss_quantity: lossQty,
            remarks: this.receiveForm.remarks,
          },
        );

        this.$emit('toast', response?.data?.message || 'Return item received successfully');
        this.onReceiveCancel(true);
        this.$emit('refresh', this.data.id);
      } catch (error) {
        this.$emit('toast', error?.response?.data?.message || 'Unable to receive return item');
      } finally {
        this.receiving = false;
      }
    },
    async logStockReturnDelivery() {
      if (!this.data?.id || this.receiving || this.hasSupplierDeliveryLog) return;

      this.receiving = true;
      try {
        const response = await axios.post(`/stock-returns/${this.data.id}/log-supplier-delivery`);
        this.$emit('toast', response?.data?.message || 'Supplier delivery logged successfully');
        this.$emit('refresh', this.data.id);
      } catch (error) {
        this.$emit('toast', error?.response?.data?.message || 'Unable to log supplier delivery');
      } finally {
        this.receiving = false;
      }
    },
    async updateStatus(status) {
      if (!this.data?.id || this.approving || !this.isPending) return;
      if (!['approved', 'disapproved'].includes(String(status))) return;

      this.approving = true;
      try {
        const response = await axios.post(`/stock-returns/${this.data.id}/approve`, {
          status,
          remarks: this.remarks,
        });
        this.showModal = false;
        this.remarks = '';
        this.$emit('toast', response?.data?.message || 'Stock return updated successfully');
        this.$emit('refresh', this.data.id);
      } catch (error) {
        this.$emit('toast', error?.response?.data?.message || 'Unable to update stock return');
      } finally {
        this.approving = false;
      }
    },
    getStatusStyle(status) {
      if (!status) return {};
      
      return {
        color: status.text_color || '#000000',
        backgroundColor: status.bg_color || '#ffffff',
        border: `1px solid ${status.bg_color ? status.bg_color + '40' : '#cccccc'}`,
        boxShadow: `0 2px 4px ${status.bg_color ? status.bg_color + '20' : 'rgba(0,0,0,0.1)'}`
      };
    },
  },
};
</script>

<style scoped>
.info-item {
  transition: transform 0.2s ease;
  padding: 0.5rem;
  border-radius: 0.5rem;
}

.info-item:hover {
  transform: translateX(5px);
  background-color: rgba(13, 110, 253, 0.03);
}

.tracking-wide {
  letter-spacing: 0.5px;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  padding: 4px 10px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  transition: all 0.3s ease;
  cursor: default;
}

.action-buttons {
  display: flex;
  gap: 8px;
  justify-content: flex-start;
  min-width: 120px;
}

.action-btn {
  width: 32px;
  height: 32px;
  border-radius: 6px;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s ease;
}

.action-btn-receive {
  background-color: #2e7d32;
  color: #e8f5e8;
  width: 80%;
}

.action-btn-receive:hover {
  background-color: #c8e6c9;
  transform: translateY(-2px);
}

.quick-summary-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.75rem;
}

.quick-summary-item {
  border: 1px solid #e9ecef;
  border-radius: 0.5rem;
  padding: 0.75rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.activity-log-panel {
  border: 1px solid #e9ecef;
  border-radius: 0.5rem;
  padding: 0.75rem;
  height: 100%;
}

.activity-log-table {
  max-height: 260px;
  overflow: auto;
}

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1050;
}

.modal-container {
  background: #ffffff;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
  display: flex;
  flex-direction: column;
  max-height: 85vh;
}

.modal-header {
  padding: 0.875rem 1.25rem;
  border-bottom: 1px solid #e9ecef;
}

.modal-header .header-title {
  color: #1e293b;
}

.close-btn {
  border: none;
  background: transparent;
  color: #6c757d;
  font-size: 1.3rem;
  line-height: 1;
}

.modal-body {
  padding: 1.25rem;
  flex: 1 1 auto;
  min-height: 0;
  overflow-y: auto;
}

.modal-footer {
  flex-shrink: 0;
  padding: 0.875rem 1.25rem;
  border-top: 1px solid #e9ecef;
  background: #fff;
}

.textarea-control {
  resize: vertical;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.6rem;
  margin-top: 1.25rem;
}

.btn {
  border: none;
  border-radius: 6px;
  padding: 0.5rem 0.85rem;
  font-weight: 500;
}

.btn-cancel {
  background: #e9ecef;
  color: #495057;
}

.btn-save {
  background: #2e8b57;
  color: #fff;
  border: none;
}

.btn-save:focus,
.btn-save:active {
  outline: none;
  box-shadow: none;
  border: none;
}

.btn:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

@media (max-width: 768px) {
  .quick-summary-grid {
    grid-template-columns: 1fr;
  }
}
</style>
