<template>
    <BRow>
        <div class="col-lg-12 mb-4">
            <div class="library-card">
                <div class="library-card-header">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="header-icon">
                                <i class="ri-trophy-line fs-24"></i>
                            </div>
                            <div>
                                <h4 class="header-title mb-1">Sales Incentives</h4>
                                <p class="header-subtitle mb-0">A comprehensive list of Sales Incentives</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body m-2 p-3">
                    <div class="search-section">
                        <div class="row">
                            <div class="col-md-6">
                                <br>
                                <div class="search-wrapper">
                                    <i class="ri-search-line search-icon"></i>
                                    <input type="text" v-model="localKeyword" @input="updateKeyword($event.target.value)"
                                        placeholder="Search employee..." class="search-input">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group mb-0">
                                    <label class="form-label" style="font-size: 12px;">From Date</label>
                                    <input type="date" v-model="filter.created_date_from" @input="fetchIncentives"
                                        class="form-control form-control-sm">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group mb-0">
                                    <label class="form-label" style="font-size: 12px;">To Date</label>
                                    <input type="date" v-model="filter.created_date_to" @input="fetchIncentives"
                                        class="form-control form-control-sm">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive table-card">
                        <table class="table align-middle table-hover mb-0"
                            style="border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                            <thead style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                                <tr class="fs-12 fw-bold text-muted">
                                    <th style="width: 3%; border: none;">#</th>
                                    <th style="width: 20%;" class="text-center border-none">Employee</th>
                                    <th style="width: 15%;" class="text-center border-none">Sold Quantity</th>
                                    <th style="width: 15%;" class="text-center border-none">Total Product KG</th>
                                    <th style="width: 15%;" class="text-center border-none">Collected Points</th>
                                </tr>
                            </thead>
                            <tbody class="fs-12">
                                <tr v-for="(incentive, index) in incentives" :key="incentive.id" class="transition-all" style="transition: all 0.3s ease;">
                                    <td class="text-center">{{ index + 1 }}</td>
                                    <td class="text-center fw-semibold">{{ incentive.employee?.fullname }}</td>
                                    <td class="text-center">{{ incentive.total_sold_quantity || 0 }}</td>
                                    <td class="text-center">{{ incentive.total_product_total_kg || 0 }}</td>
                                    <td class="text-center">{{ incentive.total_amount }}</td>
                                </tr>
                                <tr v-if="incentives.length === 0">
                                    <td colspan="8" class="text-center text-muted py-3">No incentives found</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light border-0 m-3">
                    <Pagination class="ms-2 me-2 mt-n1" v-if="meta" @fetch="fetchIncentives" :lists="incentives.length"
                        :links="links" :pagination="meta" />
                </div>
            </div>
        </div>
    </BRow>
    <!-- Create/Edit Modal -->
    <div v-if="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="closeModal">
        <div class="modal-container modal-lg" @click.stop>
            <div class="modal-header">
                <h3>{{ selectedIncentive?.id ? 'Edit' : 'Create' }} Incentive</h3>
                <button class="close-btn" @click="closeModal">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body">
                <form @submit.prevent="saveIncentive" class="incentive-form">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Employee *</label>
                                <select v-model="form.employee_id" class="form-control" required>
                                    <option value="">Select Employee</option>
                                    <option v-for="emp in dropdowns.employees" :key="emp.id" :value="emp.id">
                                        {{ emp.name }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Status *</label>
                                <select v-model="form.status" class="form-control" required>
                                    <option value="pending">Pending</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Sold Quantity *</label>
                                <input v-model.number="form.sold_quantity" type="number" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Product Total KG *</label>
                                <input v-model.number="form.product_total_kg" type="number" step="0.01" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Amount *</label>
                                <input v-model.number="form.amount" type="number" step="0.01" class="form-control" required>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" @click="closeModal">
                    <i class="ri-close-line"></i>
                    Cancel
                </button>
                <button type="submit" class="btn btn-save" @click="saveIncentive">
                    <i class="ri-save-line"></i>
                    {{ selectedIncentive?.id ? 'Update' : 'Create' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import _ from 'lodash';
import axios from 'axios'
import Pagination from "@/Shared/Components/Pagination.vue";

export default {
  components: { Pagination },
  props: ['dropdowns'],
  data() {
    return {
      incentives: [],
      meta: {},
      links: {},
      filter: {
        keyword: null,
        created_date_from: null,
        created_date_to: null
      },
      localKeyword: '',
      showModal: false,
      selectedIncentive: null,
      form: {
        employee_id: '',
        sold_quantity: 0,
        product_total_kg: 0,
        amount: 0,
        status: 'pending'
      }
    }
  },
  watch: {
    "filter.keyword"(newVal) {
      this.checkSearchStr(newVal);
    }
  },
  created() {
    // bind debounced fetch so `this` is the Vue instance
    this.debouncedFetch = _.debounce(() => {
      this.fetchIncentives();
    }, 300);

    this.setDefaultDateRange();
    this.fetchIncentives();
  },
  methods: {
    setDefaultDateRange() {
      if (!this.filter.created_date_from || !this.filter.created_date_to) {
        const now = new Date();
        const year = now.getFullYear();
        const month = now.getMonth();
        const first = new Date(year, month, 1);
        const last = new Date(year, month + 1, 0);
        const fmt = (d) => {
          const yyyy = d.getFullYear();
          const mm = String(d.getMonth() + 1).padStart(2, '0');
          const dd = String(d.getDate()).padStart(2, '0');
          return `${yyyy}-${mm}-${dd}`;
        };
        this.filter.created_date_from = fmt(first);
        this.filter.created_date_to = fmt(last);
      }
    },
    // wrapper called by the watcher; delegates to the bound debouncedFetch
    checkSearchStr(newVal) {
      this.debouncedFetch();
    },
    updateKeyword(value) {
      this.filter.keyword = value;
      // reset to first page when searching
      if (this.meta && this.meta.current_page) {
        this.meta.current_page = 1;
      }
      // debug: inspect keyword and current filters
      try { console.debug('updateKeyword:', value, this.filter); } catch (e) {}
    },
    clearDateFilter() {
      this.filter.created_date_from = null;
      this.filter.created_date_to = null;
      this.fetchIncentives();
    },
    async fetchIncentives() {
      try {
        const params = {
          ...this.filter,
          page: this.meta.current_page || 1
        }

        try { console.debug('fetchIncentives params:', params); } catch (e) {}

        const response = await axios.get('/sales-incentives', { params })
        if (response.data) {
          this.incentives = response.data.data || [];
          this.meta = {
            current_page: response.data.current_page || 1,
            last_page: response.data.last_page || 1,
            total: response.data.total || 0,
            per_page: response.data.per_page || 15,
            links: {
              prev: response.data.prev_page_url || null,
              next: response.data.next_page_url || null
            }
          }
          this.links = response.data.links || {}
        } else {
          this.incentives = []
          this.meta = {}
          this.links = {}
        }
      } catch (error) {
        console.error('Error fetching incentives:', error)
        this.$toast.error('Failed to load incentives')
        this.incentives = []
        this.meta = {}
        this.links = {}
      }
    },
    editIncentive(incentive) {
      this.selectedIncentive = { ...incentive };
      this.form = {
        employee_id: incentive.employee_id,
        sold_quantity: incentive.sold_quantity,
        product_total_kg: incentive.product_total_kg,
        amount: incentive.amount,
        status: incentive.status
      };
      this.showModal = true;
    },
    async saveIncentive() {
      try {
        const url = this.selectedIncentive?.id
          ? `/api/sales-incentives/${this.selectedIncentive.id}`
          : '/api/sales-incentives';
        const method = this.selectedIncentive?.id ? 'put' : 'post';

        const config = { method, url, data: this.form };
        await axios(config);

        this.$toast?.success?.(
          this.selectedIncentive?.id
            ? 'Incentive updated successfully'
            : 'Incentive created successfully'
        );
        this.closeModal();
        this.fetchIncentives();
      } catch (error) {
        console.error('Error saving incentive:', error);
        this.$toast?.error?.('Failed to save incentive');
      }
    },
    confirmDelete(incentive) {
      if (confirm(`Are you sure you want to delete this incentive?`)) {
        this.deleteIncentive(incentive.id);
      }
    },
    async deleteIncentive(id) {
      try {
        await axios.delete(`/api/sales-incentives/${id}`);
        this.$toast?.success?.('Incentive deleted successfully');
        this.fetchIncentives();
      } catch (error) {
        console.error('Error deleting incentive:', error);
        this.$toast?.error?.('Failed to delete incentive');
      }
    },
    closeModal() {
      this.showModal = false;
      this.selectedIncentive = null;
      this.form = {
        employee_id: '',
        sold_quantity: 0,
        product_total_kg: 0,
        amount: 0,
        status: 'pending'
      };
    },
    formatCurrency(amount) {
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
      }).format(amount);
    },
    getStatusStyle(status) {
      const statusMap = {
        pending: { bg_color: '#ffc107', text_color: '#000000' },
        approved: { bg_color: '#28a745', text_color: '#ffffff' },
        rejected: { bg_color: '#dc3545', text_color: '#ffffff' }
      };
      const statusInfo = statusMap[status] || { bg_color: '#6c757d', text_color: '#ffffff' };
      return {
        color: statusInfo.text_color,
        backgroundColor: statusInfo.bg_color,
        border: `1px solid ${statusInfo.bg_color}40`,
        boxShadow: `0 2px 4px ${statusInfo.bg_color}20`
      };
    }
  }
}
</script>

<style scoped>
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
</style>
