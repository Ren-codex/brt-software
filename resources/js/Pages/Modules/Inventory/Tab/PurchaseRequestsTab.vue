<template>
  <BRow>
    <div class="col-md-12">
    <div class="library-card">
        <div class="library-card-header">
          <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
              <div class="header-icon">
                <i class="ri-shopping-cart-line"></i>
              </div>
              <div>
                <h4 class="header-title mb-1">Purchase Request Management</h4>
                <p class="header-subtitle mb-0">Manage and organize your purchase request catalog</p>
              </div>
            </div>
            <button class="create-btn" @click="openCreatePurchaseOrder">
              <i class="ri-add-line"></i>
              <span>Add Purchase Request</span>
            </button>
          </div>
        </div>

        <div class="library-card-body">
          <div class="tabs-section">
            <div class="tabs-wrapper">
              <button
                :class="['tab-btn', { active: activeTab === 'pending' }]"
                @click="setActiveTab('pending')"
              >
                Pending
              </button>
              <button
                :class="['tab-btn', { active: activeTab === 'approved' }]"
                @click="setActiveTab('approved')"
              >
                Approved
              </button>
              <button
                :class="['tab-btn', { active: activeTab === 'disapproved' }]"
                @click="setActiveTab('disapproved')"
              >
                Disapproved
              </button>
            </div>
          </div>

          <div class="search-section">
            <div class="search-wrapper">
              <i class="ri-search-line search-icon"></i>
              <input
                type="text"
                v-model="localKeyword"
                @input="updateKeyword($event.target.value)"
                placeholder="Search purchase request..."
                class="search-input"
              >
            </div>
          
          </div>

          <div class="table-section">
            <div class="table-responsive" style="overflow: visible; max-height: none;">
              <table class="table align-middle table-centered mb-0">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>
                      <div class="sortable-header" @click="toggleSort('pr_number')">
                        PR Number
                        <i v-if="sortBy === 'pr_number'" 
                          :class="sortDirection === 'asc' ? 'ri-arrow-up-line' : 'ri-arrow-down-line'">
                        </i>
                      </div>
                    </th>
                    <th>
                      <div class="sortable-header" @click="toggleSort('date')">
                        Date
                        <i v-if="sortBy === 'date'" 
                          :class="sortDirection === 'asc' ? 'ri-arrow-up-line' : 'ri-arrow-down-line'">
                        </i>
                      </div>
                    </th>
                    <th>Supplier</th>
                    <th>
                      <div class="sortable-header" @click="toggleSort('amount')">
                        Total Amount
                        <i v-if="sortBy === 'amount'" 
                          :class="sortDirection === 'asc' ? 'ri-arrow-up-line' : 'ri-arrow-down-line'">
                        </i>
                      </div>
                    </th>
                    <th>
                      <div class="sortable-header" @click="toggleSort('status')">
                        Status
                        <i v-if="sortBy === 'status'" 
                          :class="sortDirection === 'asc' ? 'ri-arrow-up-line' : 'ri-arrow-down-line'">
                        </i>
                      </div>
                    </th>
                    <th>Actions</th>
                  </tr>
                </thead>

                <tbody>
                  <tr 
                    v-for="(list,index) in filteredAndSortedList" 
                    v-bind:key="list.id" 
                    @click="openView(list)" 
                    style="cursor: pointer;"
                    :style="getRowStyle(list.status)"
                  >
                    <td>{{ index + 1 }}</td>
                    <td>
                      <strong>{{ list.pr_number }}</strong>
                    </td>
                    <td>{{ formatDate(list.po_date) }}</td>
                    <td>
                      <div class="supplier-info">
                        <div class="supplier-name">{{ list.supplier ? list.supplier.name : '' }}</div>
                        <small class="text-muted" v-if="list.supplier?.contact_person">
                          {{ list.supplier.contact_person }}
                        </small>
                      </div>
                    </td>
                    <td>
                      <div class="amount-cell">
                        <span class="amount-value">{{ formatCurrency(list.total_amount) }}</span>
                      </div>
                    </td>
                    <td>
                      <span 
                        class="status-badge" 
                        :style="getStatusStyle(list.status)"
                      >
                        <i v-if="list.status?.icon" :class="list.status.icon" class="me-1"></i>
                        {{ list.status ? list.status.name : '' }}
                      </span>
                    </td>
                    <td>
                      <div class="action-buttons" @click.stop>
                        <button @click.stop="openEdit(list, index)" class="action-btn action-btn-edit" v-b-tooltip.hover title="Edit">
                          <i class="ri-pencil-line"></i>
                        </button>
                        <button @click.stop="onDelete(list.id)" class="action-btn action-btn-delete" v-b-tooltip.hover title="Delete">
                          <i class="ri-delete-bin-line"></i>
                        </button>
                        <button @click.stop="printPurchaseOrder(list.id)" class="action-btn action-btn-print" v-b-tooltip.hover title="Print">
                          <i class="ri-printer-line"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="filteredAndSortedList.length === 0">
                    <td colspan="7" class="text-center py-4">
                      <i class="ri-inbox-line text-muted" style="font-size: 3rem;"></i>
                      <p class="mt-2 mb-0">{{ getEmptyStateMessage() }}</p>
                      <small class="text-muted">Try changing your search or filter criteria</small>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="pagination-section">
            <Pagination v-if="meta" @fetch="$emit('fetch')" :lists="listPurchaseOrders.length" :links="links" :pagination="meta" />
          </div>
        </div>
      
    </div>
    </div>
  </BRow>
  
  <CreatePurchaseOrderModal ref="createModal" :dropdowns="dropdowns" @add="$emit('fetch')" />
  <Delete ref="delete" @delete="handleDeleteSuccess" />
</template>

<script>
import Pagination from '@/Shared/Components/Pagination.vue';
import Delete from "@/Shared/Components/Modals/Delete.vue";
import CreatePurchaseOrderModal from '../Modal/CreatePurchaseOrderModal.vue';

export default {
  name: "PurchaseOrdersTab",
  components: { Pagination, Delete, CreatePurchaseOrderModal },
  props: {
    listPurchaseRequests: Array,
    listPurchaseOrders: Array,
    listPRDisapproved: Array,
    meta: Object,
    links: Object,
    filter: Object,
    dropdowns: Object,
  },
  emits: ['fetch', 'update-keyword', 'toast', 'update-filter', 'view-details'],
  data() {
    return {
      selectedRow: null,
      localKeyword: this.filter.keyword || '',
      selectedStatus: this.filter.status || '',
      sortBy: this.filter.sort_by || 'date',
      sortDirection: this.filter.sort_direction || 'desc',
      activeTab: 'pending',
    };
  },
  computed: {
    filteredAndSortedList() {
      let filtered = [];
      // Filter by active tab
      if (this.activeTab === 'pending') {
        filtered = this.listPurchaseRequests;
      } else if (this.activeTab === 'approved') {
        filtered = this.listPurchaseOrders;
      } else if (this.activeTab === 'disapproved') {
        filtered = this.listPRDisapproved;
      }

      // Additional filter by selectedStatus if needed
      if (this.selectedStatus) {
        filtered = filtered.filter(order =>
          order.status && order.status.id == this.selectedStatus
        );
      }

      return this.sortList(filtered);
    }
  },
  watch: {
    'filter.keyword'(newVal) {
      this.localKeyword = newVal;
    },
    'filter.status'(newVal) {
      this.selectedStatus = newVal;
    },
    'filter.sort_by'(newVal) {
      this.sortBy = newVal;
    },
    'filter.sort_direction'(newVal) {
      this.sortDirection = newVal;
    }
  },
  methods: {
    setActiveTab(tab) {
      this.activeTab = tab;
    },

    openView(purchaseOrder) {
      this.$emit('view-details', purchaseOrder);
    },
    
    openCreatePurchaseOrder() {
      this.$refs.createModal.show();
    },
    
    openEdit(data, index) {      
      this.$refs.createModal.edit(data, index);
    },
    
    onDelete(id) {
      let title = "Purchase Order";
      this.$refs.delete.show(id, title, '/purchase-orders');
    },
    
    printPurchaseOrder(id) {
        window.open(`/purchase-orders/${id}/print?type=purchase_order`, '_blank');
    },
    
    updateKeyword(keyword) {
      this.localKeyword = keyword;
      this.emitFilterUpdate();
    },
    
    toggleSort(field) {
      if (this.sortBy === field) {
        this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
      } else {
        this.sortBy = field;
        this.sortDirection = 'desc';
      }
      this.emitFilterUpdate();
    },
    
    sortList(list) {
      return [...list].sort((a, b) => {
        let aValue, bValue;
        
        switch (this.sortBy) {
          case 'date':
            aValue = new Date(a.po_date || 0);
            bValue = new Date(b.po_date || 0);
            break;
          case 'amount':
            aValue = parseFloat(a.total_amount) || 0;
            bValue = parseFloat(b.total_amount) || 0;
            break;
          case 'status':
            aValue = a.status ? a.status.name : '';
            bValue = b.status ? b.status.name : '';
            break;
          default:
            aValue = a[this.sortBy] || '';
            bValue = b[this.sortBy] || '';
        }
        
        if (aValue < bValue) return this.sortDirection === 'asc' ? -1 : 1;
        if (aValue > bValue) return this.sortDirection === 'asc' ? 1 : -1;
        return 0;
      });
    },
    
    emitFilterUpdate() {
      const filter = {
        keyword: this.localKeyword,
        status: this.selectedStatus,
        sort_by: this.sortBy,
        sort_direction: this.sortDirection
      };
      this.$emit('update-filter', filter);
    },
    
    handleDeleteSuccess() {
      this.$emit('toast', 'Purchase Order deleted successfully');
      this.$emit('fetch');
    },
    
    formatCurrency(value) {
      if (!value && value !== 0) return '₱0.00';
      return '₱' + Number(value).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    },
    
    formatDate(dateString) {
      if (!dateString) return '';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
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
    
    getRowStyle(status) {
      if (!status || !status.bg_color) return {};

      return {
        '--hover-color': status.bg_color + '10'
      };
    },

    getEmptyStateMessage() {
      switch (this.activeTab) {
        case 'pending':
          return 'No pending purchase request found';
        case 'approved':
          return 'No approved purchase request found';
        case 'disapproved':
          return 'No disapproved purchase request found';
        default:
          return 'No purchase request found';
      }
    }
  },
};
</script>


<style scoped>
/* Tabs Section */
.tabs-section {
  margin-bottom: 1rem;
}

.tabs-wrapper {
  display: flex;
  gap: 8px;
  border-bottom: 1px solid #e9ecef;
}

.tab-btn {
  padding: 8px 16px;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  color: #6c757d;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
}

.tab-btn:hover {
  color: #2e8b57;
}

.tab-btn.active {
  color: #2e8b57;
  border-bottom-color: #2e8b57;
}

/* Filter Section */
.filter-section {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 1rem;
  padding: 1rem;
  background: #f8f9fa;
  border-radius: 10px;
  border: 1px solid #e9ecef;
}

.status-filter {
  display: flex;
  align-items: center;
  gap: 10px;
}

.filter-label {
  font-weight: 600;
  color: #495057;
  margin-bottom: 0;
}

.status-select {
  padding: 8px 12px;
  border: 1px solid #ced4da;
  border-radius: 6px;
  background: white;
  color: #495057;
  font-size: 14px;
  min-width: 180px;
  transition: all 0.3s ease;
}

.status-select:focus {
  outline: none;
  border-color: #2e8b57;
  box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.1);
}

/* Sort Section */
.sort-section {
  display: flex;
  align-items: center;
  gap: 15px;
}

.sort-label {
  font-weight: 600;
  color: #495057;
  margin-bottom: 0;
}

.sort-options {
  display: flex;
  gap: 8px;
}

.sort-btn {
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 8px 16px;
  background: white;
  border: 1px solid #e9ecef;
  border-radius: 6px;
  color: #6c757d;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s ease;
}

.sort-btn:hover {
  background: #f8f9fa;
  border-color: #dee2e6;
}

.sort-btn.active {
  background: #2e8b57;
  border-color: #2e8b57;
  color: white;
}

.sort-btn.active i {
  font-size: 14px;
}

/* Sortable Header */
.sortable-header {
  display: flex;
  align-items: center;
  gap: 5px;
  cursor: pointer;
  user-select: none;
  transition: color 0.3s ease;
}

.sortable-header:hover {
  color: #2e8b57;
}

.sortable-header i {
  font-size: 14px;
  opacity: 0.7;
}

/* Status Badges - Dynamic from database */
.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  transition: all 0.3s ease;
  cursor: default;
}

/* Fallback CSS classes if database colors not available */
.status-pending {
  background-color: #fff3cd !important;
  color: #856404 !important;
  border: 1px solid #ffeaa7 !important;
}

.status-approved {
  background-color: #d1ecf1 !important;
  color: #0c5460 !important;
  border: 1px solid #bee5eb !important;
}

.status-processing {
  background-color: #d4edda !important;
  color: #155724 !important;
  border: 1px solid #c3e6cb !important;
}

.status-completed {
  background-color: #d4edda !important;
  color: #155724 !important;
  border: 1px solid #c3e6cb !important;
}

.status-cancelled {
  background-color: #f8d7da !important;
  color: #721c24 !important;
  border: 1px solid #f5c6cb !important;
}

.status-default {
  background-color: #e2e3e5 !important;
  color: #383d41 !important;
  border: 1px solid #d6d8db !important;
}

/* Table Row Hover Effects */
tbody tr {
  transition: background-color 0.3s ease;
}

tbody tr:hover {
  background-color: var(--hover-color, rgba(46, 139, 87, 0.05)) !important;
}

/* Supplier Info */
.supplier-info {
  line-height: 1.4;
}

.supplier-name {
  font-weight: 500;
}

/* Amount Cell */
.amount-cell {
  font-weight: 600;
}

.amount-value {
  color: #2e8b57;
}

/* Action Buttons */
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

.action-btn-edit {
  background-color: #e3f2fd;
  color: #1976d2;
}

.action-btn-edit:hover {
  background-color: #bbdefb;
  transform: translateY(-2px);
}

.action-btn-delete {
  background-color: #ffebee;
  color: #d32f2f;
}

.action-btn-delete:hover {
  background-color: #ffcdd2;
  transform: translateY(-2px);
}

.action-btn-print {
  background-color: #f3e5f5;
  color: #7b1fa2;
}

.action-btn-print:hover {
  background-color: #e1bee7;
  transform: translateY(-2px);
}

/* Empty State */
.text-center {
  color: #6c757d;
}

.text-center i {
  opacity: 0.5;
}

/* Responsive Design */
@media (max-width: 768px) {
  .filter-section {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }
  
  .status-filter {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .sort-section {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .sort-options {
    width: 100%;
    flex-wrap: wrap;
  }
  
  .sort-btn {
    flex: 1;
    min-width: 80px;
    justify-content: center;
  }
  
  .status-select {
    width: 100%;
  }
  
  .action-buttons {
    justify-content: center;
  }
}

@media (max-width: 576px) {
  .action-buttons {
    flex-wrap: wrap;
    gap: 4px;
  }
  
  .action-btn {
    width: 28px;
    height: 28px;
  }
  
  .status-badge {
    font-size: 10px;
    padding: 3px 8px;
  }
}
</style>