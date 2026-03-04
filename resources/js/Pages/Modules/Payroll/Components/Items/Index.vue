<template>
  <BRow>
    <div class="col-lg-12 mb-4">
      <div class="library-card">
        <div class="library-card-header">
          <div class="d-flex align-items-center gap-3">
            <div class="header-icon">
              <i class="ri-settings-2-line fs-24"></i>
            </div>
            <div>
              <h4 class="header-title mb-1">Payroll Items</h4>
              <p class="header-subtitle mb-0">Select a type to view payroll items</p>
            </div>
          </div>
        </div>

        <div class="card-body m-2 p-3">
          <div class="payroll-items-container">
            <div class="row g-3">
              <div class="col-lg-4 col-xl-3">
                <div class="groups-list-section">
                  <div
                    v-for="group in itemGroups"
                    :key="group.key"
                    class="group-card-wrapper"
                    :class="{ 'selected-group': selectedGroup === group.key }"
                  >
                    <b-card
                      :class="['group-card', { 'active-group': selectedGroup === group.key }]"
                      @click="selectGroup(group.key)"
                    >
                      <div class="d-flex justify-content-between align-items-center">
                    <div>
                          <h6 class="group-name mb-1">{{ group.label }}</h6>
                          <span class="badge bg-light text-muted">
                            {{ group.count }} items
                          </span>
                        </div>
                        <i class="ri-arrow-right-line fs-16 text-muted"></i>
                      </div>
                    </b-card>
                  </div>
                </div>
              </div>

              <div class="col-lg-8 col-xl-9">
                <div v-if="selectedGroup" class="items-section">
                  <div class="section-header d-flex justify-content-between align-items-center mb-3">
                    <div>
                      <h5 class="mb-1">{{ selectedGroupLabel }}</h5>
                      <p class="text-muted mb-0">{{ filteredItems.length }} item(s)</p>
                    </div>
                    <div class="section-actions d-flex align-items-center gap-2">
                      <div class="search-wrapper item-search">
                        <i class="ri-search-line search-icon"></i>
                        <input
                          v-model="keyword"
                          type="text"
                          placeholder="Search items..."
                          class="search-input"
                        >
                      </div>
                      <b-button variant="primary" size="sm" @click="openItemModal(selectedGroup)" class="ms-1">
                        <i class="ri-add-line me-1"></i>
                      </b-button>
                    </div>
                  </div>

                  <div class="card items-table-card">
                    <div class="card-body p-0">
                      <div class="table-responsive">
                        <table class="table align-middle table-hover mb-0">
                          <thead class="table-light">
                            <tr>
                              <th class="ps-4" style="width: 10%">#</th>
                              <th style="width: 50%" class="text-start">Name</th>
                              <th style="width: 20%" class="text-start">Description</th>
                              <th style="width: 10%">Status</th>
                              <th style="width: 20%" class="text-center">Actions</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr
                              v-for="(item, index) in filteredItems"
                              :key="item.id"
                              class="item-row"
                            >
                              <td class="ps-4 fw-semibold text-muted">{{ index + 1 }}</td>
                              <td class="fw-semibold">{{ item.name }}</td>
                              <td>{{ item.description || '-' }}</td>
                              <td>
                                <b-form-checkbox
                                    :checked="item.is_active === true"
                                    @change="toggleActive(item)"
                                    switch
                                    size="md"
                                />
                              </td>
                              <td>
                                <div class="d-flex justify-content-center align-items-center gap-2">
                                  <b-button variant="outline-secondary" size="sm" @click="editItem(item)" class="btn-icon rounded-circle">
                                    <i class="ri-edit-line"></i>
                                  </b-button>
                                  <!-- <b-button variant="outline-danger" size="sm" @click.stop="deleteItem(item)" class="btn-icon rounded-circle">
                                    <i class="ri-delete-bin-line"></i>
                                  </b-button> -->
                                </div>
                              </td>
                            </tr>
                          </tbody>
                        </table>

                        <div v-if="filteredItems.length === 0" class="text-center py-5">
                          <div class="mb-2">
                            <i class="ri-inbox-line text-muted" style="font-size: 44px;"></i>
                          </div>
                          <h6 class="text-muted mb-1">No items found</h6>
                          <p class="text-muted mb-0" v-if="keyword">No results for "{{ keyword }}"</p>
                          <p class="text-muted mb-0" v-else>No {{ selectedGroupLabel.toLowerCase() }} items available</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div v-else class="text-center py-5 empty-state">
                  <div class="mb-3">
                    <i class="ri-layout-grid-line text-muted" style="font-size: 56px;"></i>
                  </div>
                  <h5 class="text-muted mb-2">Select a Group</h5>
                  <p class="text-muted mb-0">Choose Earnings or Deductions from the left panel.</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </BRow>

  <ItemModal
    :show="showItemModal"
    :form="form"
    :form-errors="formErrors"
    :is-saving="isSavingItem"
    :is-edit="Boolean(editingItemId)"
    @close="closeItemModal"
    @save="submitItem"
  />
</template>

<script>
import axios from 'axios';
import Swal from 'sweetalert2';
import ItemModal from './Modal.vue';

export default {
  components: { ItemModal },
  props: ['dropdowns'],
  data() {
    return {
      payrollItems: [],
      selectedGroup: 'earning',
      keyword: '',
      showItemModal: false,
      isSavingItem: false,
      editingItemId: null,
      formErrors: {},
      form: {
        name: '',
        slug: '',
        description: '',
        type: 'earning',
        is_active: true,
      },
    };
  },
  computed: {
    itemGroups() {
      return [
        {
          key: 'earning',
          label: 'Earnings',
          count: this.groupedItems.earning.length,
        },
        {
          key: 'deduction',
          label: 'Deductions',
          count: this.groupedItems.deduction.length,
        },
      ];
    },
    groupedItems() {
      return {
        earning: this.payrollItems.filter((item) => this.matchesGroup(item.type, 'earning')),
        deduction: this.payrollItems.filter((item) => this.matchesGroup(item.type, 'deduction')),
      };
    },
    selectedGroupLabel() {
      return this.selectedGroup === 'earning' ? 'Earnings' : 'Deductions';
    },
    filteredItems() {
      const source = this.groupedItems[this.selectedGroup] || [];
      const key = this.keyword.trim().toLowerCase();
      if (!key) return source;

      return source.filter((item) =>
        item.name?.toLowerCase().includes(key) ||
        item.slug?.toLowerCase().includes(key) ||
        item.description?.toLowerCase().includes(key)
      );
    },
  },
  created() {
    this.fetchPayrollItems();
  },
  methods: {
    async fetchPayrollItems() {
      try {
        const response = await axios.get('/libraries/payroll-items', {
          params: {
            option: 'lists',
            count: 1000,
          },
        });

        this.payrollItems = response?.data?.data || [];
      } catch (error) {
        console.log(error);
        this.payrollItems = [];
      }
    },
    selectGroup(group) {
      this.selectedGroup = group;
      this.keyword = '';

      if (window.innerWidth < 768) {
        this.$nextTick(() => {
          document.querySelector('.items-section')?.scrollIntoView({
            behavior: 'smooth',
          });
        });
      }
    },
    openItemModal(type) {
      this.editingItemId = null;
      this.form = {
        name: '',
        description: '',
        type,
      };
      this.formErrors = {};
      this.showItemModal = true;
    },
    editItem(item) {
      this.editingItemId = item.id;
      this.form = {
        name: item.name || '',
        description: item.description || '',
        type: this.normalizeType(item.type) || this.selectedGroup,
      };
      this.formErrors = {};
      this.showItemModal = true;
    },
    closeItemModal() {
      this.showItemModal = false;
      this.editingItemId = null;
      this.formErrors = {};
    },
    async submitItem(payload) {
      if (this.isSavingItem) return;
      this.isSavingItem = true;
      this.formErrors = {};
      const normalizedType = this.normalizeType(payload.type);
      const requestPayload = {
        ...payload,
        type: normalizedType,
      };
      this.form = { ...requestPayload };

      try {
        if (this.editingItemId) {
          await axios.put(`/libraries/payroll-items/${this.editingItemId}`, requestPayload);
        } else {
          await axios.post('/libraries/payroll-items', requestPayload);
        }
        this.selectedGroup = normalizedType;
        Swal.fire({
          icon: 'success',
          title: 'Success!',
          text: 'Payroll Item saved successfully!',
          showConfirmButton: true,
          timer: 1500
        });
        this.closeItemModal();
        await this.fetchPayrollItems();
      } catch (error) {
        if (error?.response?.status === 422) {
          this.formErrors = error.response.data.errors || {};
        } else {
          console.log(error);
        }
      } finally {
        this.isSavingItem = false;
      }
    },
    normalizeType(type) {
      return String(type || '').trim().toLowerCase().replace(/s$/, '');
    },
    matchesGroup(type, group) {
      return this.normalizeType(type) === group;
    },
    async toggleActive(item) {
      const updatedStatus = !Boolean(item.is_active);

      try {
        const response = await axios.patch(`/libraries/payroll-items/${item.id}/toggle-active`, {
          is_active: updatedStatus,
        });

        if (response?.data?.status) {
          item.is_active = updatedStatus;
        }
      } catch (error) {
        console.log(error);
        Swal.fire('Error', 'Failed to update payroll item status.', 'error');
      }
    },
    async deleteItem(item) {
      const result = await Swal.fire({
        title: 'Are you sure?',
        text: `Delete "${item.name}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it',
      });

      if (!result.isConfirmed) return;

      try {
        await axios.delete(`/libraries/payroll-items/${item.id}`);
        Swal.fire({
          icon: 'success',
          title: 'Success!',
          text: 'Payroll Item deleted successfully!',
          showConfirmButton: false,
          timer: 1500
        });
        await this.fetchPayrollItems();
      } catch (error) {
        console.log(error);
        Swal.fire('Error', 'Failed to delete payroll item.', 'error');
      }
    },
  },
};
</script>

<style scoped>
.payroll-items-container {
  min-height: 500px;
}

.groups-list-section {
  position: sticky;
  top: 20px;
}

.group-card-wrapper {
  margin-bottom: 12px;
  border-radius: 8px;
  transition: all 0.3s ease;
}

.group-card-wrapper.selected-group {
  box-shadow: 0 0 0 2px rgb(6, 107.4, 93.6);
}

.group-card {
  border: 1px solid #e9ecef;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s ease;
  background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
}

.group-card:hover {
  border-color: rgb(6, 107.4, 93.6);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(74, 108, 247, 0.15);
}

.group-card.active-group {
  border-color: rgb(6, 107.4, 93.6);
  background: linear-gradient(135deg, #ffffff 0%, #e1fffb 100%);
}

.group-name {
  color: #343a40;
  font-weight: 600;
  font-size: 1rem;
}

.search-wrapper {
  position: relative;
}

.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #6c757d;
}

.search-input {
  width: 100%;
  padding: 8px 16px 8px 40px;
  border: 1px solid #dee2e6;
  border-radius: 6px;
  transition: all 0.3s ease;
  background-color: #f8f9fa;
}

.search-input:focus {
  outline: none;
  border-color: #4a6cf7;
  background-color: #ffffff;
  box-shadow: 0 0 0 3px rgba(74, 108, 247, 0.1);
}

.item-search {
  max-width: 280px;
}

.create-btn {
  border: none;
  color: #fff;
  padding: 8px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: all 0.2s ease;
}

.create-btn:hover {
  transform: translateY(-1px);
}

.earning-btn {
  background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
}

.deduction-btn {
  background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%);
}

.items-table-card {
  border: none;
  border-radius: 12px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

.table {
  margin-bottom: 0;
}

.table thead th {
  border-top: none;
  border-bottom: 2px solid #e9ecef;
  font-weight: 600;
  color: #495057;
  padding: 14px 12px;
  text-transform: uppercase;
  font-size: 0.78rem;
  letter-spacing: 0.5px;
}

.table tbody tr:hover {
  background-color: #f8f9ff;
}

.item-row {
  border-bottom: 1px solid #f1f3f4;
}

.item-row:last-child {
  border-bottom: none;
}

.item-row td {
  padding: 14px 12px;
  vertical-align: middle;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 72px;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
}

.status-active {
  background-color: #d4edda;
  color: #155724;
  border: 1px solid #c3e6cb;
}

.status-inactive {
  background-color: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
}

.empty-state {
  background: linear-gradient(135deg, #f8f9ff 0%, #ffffff 100%);
  border-radius: 12px;
  border: 2px dashed #dee2e6;
}

@media (max-width: 992px) {
  .groups-list-section {
    position: static;
  }

  .item-search {
    max-width: 100%;
  }

  .section-actions {
    width: 100%;
    flex-wrap: wrap;
    justify-content: flex-start;
  }
}

@media (max-width: 768px) {
  .section-header {
    gap: 10px;
    flex-direction: column;
    align-items: stretch !important;
  }
}
</style>
