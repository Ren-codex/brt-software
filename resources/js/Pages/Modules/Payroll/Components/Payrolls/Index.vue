<template>
    <BRow>
        <div class="col-lg-12 mb-4">
            <div class="library-card">
                <div class="library-card-header">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="header-icon">
                                <i class="ri-money-dollar-circle-line fs-24"></i>
                            </div>
                            <div>
                                <h4 class="header-title mb-1">Payroll Management</h4>
                                <p class="header-subtitle mb-0">A comprehensive list of Payrolls</p>
                            </div>
                        </div>
                        <button class="create-btn" @click="showCreateModal = true">
                            <i class="ri-add-line"></i>
                            <span>Add Payroll</span>
                        </button>
                    </div>
                </div>
                <div class="card-body m-2 p-3">
                    <div class="search-section">
                        <div class="search-wrapper">
                            <i class="ri-search-line search-icon"></i>
                            <input type="text" v-model="localKeyword" @input="updateKeyword($event.target.value)"
                                placeholder="Search payrolls..." class="search-input">
                        </div>
                    </div>

                    <div class="table-responsive table-card">
                        <table class="table align-middle table-hover mb-0"
                            style="border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                            <thead style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                                <tr class="fs-12 fw-bold text-muted">
                                    <th style="width: 3%; border: none;">#</th>
                                    <th style="width: 15%;" class="text-center border-none">Pay Period</th>
                                    <th style="width: 20%;" class="text-center border-none">Payroll No.</th>
                                    <th style="width: 20%;" class="text-center border-none">Payroll Name</th>
                                    <th style="width: 10%;" class="text-center border-none">Total Net Salary</th>
                                    <th style="width: 10%;" class="text-center border-none">Payroll Clerk</th>
                                    <th style="width: 10%;" class="text-center border-none">Status</th>
                                    <th style="width: 12%;" class="text-center border-none">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="fs-12">
                                <template v-for="(payroll, index) in payrolls" :key="payroll.id">
                                    <tr @click="toggleRowExpansion(index)" :class="{
                                        'bg-primary bg-opacity-10 ': index === selectedRow,
                                        'cursor-pointer': true
                                    }" class="transition-all" style="transition: all 0.3s ease;">
                                        <td class="text-center">{{ index + 1 }}</td>
                                        <td class="text-center fw-semibold">{{ formatDate(payroll.pay_period_start) }} - {{ formatDate(payroll.pay_period_end) }}</td>
                                        <td class="text-center">
                                            {{ payroll.payroll_no }}
                                        </td>
                                        <td class="text-center">{{ payroll.payroll_name }}</td>
                                        <td class="text-center fw-bold">{{ formatCurrency(payroll.total_amount) }}</td>
                                        <td class="text-center">{{ payroll.created_by }}</td>
                                        <td class="text-center">
                                            <span class="status-badge" 
                                              :style="{ color: payroll.status?.text_color, backgroundColor: payroll.status?.bg_color, padding: '0.25rem 0.5rem', borderRadius: '0.5rem' }">
                                                {{ payroll.status.slug }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-1">
                                                <b-button @click.stop="editPayroll(payroll)" variant="outline-primary"
                                                  v-b-tooltip.hover title="Edit" size="sm"
                                                  class="btn-icon rounded-circle">
                                                  <i :class="payroll.status === 'draft' ? 'ri-pencil-fill' : 'ri-eye-line'"></i>
                                                </b-button>
                                                <b-button @click.stop="printPayroll(payroll)" variant="outline-info"
                                                    v-b-tooltip.hover title="Print" size="sm"
                                                    class="btn-icon rounded-circle"
                                                    v-if="payroll.status != 'draft'">
                                                    <i class="ri-printer-line"></i>
                                                </b-button>
                                                <b-button @click.stop="confirmDelete(payroll)" variant="outline-danger"
                                                    v-b-tooltip.hover title="Delete" size="sm"
                                                    class="btn-icon rounded-circle"
                                                    v-if="payroll.status == 'draft'">
                                                    <i class="ri-delete-bin-line"></i>
                                                </b-button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                                <tr v-if="!payrolls.length">
                                    <td colspan="8" class="text-center">No data available</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light border-0 m-3">
                    <Pagination class="ms-2 me-2 mt-n1" v-if="meta" @fetch="fetchPayrolls" :lists="payrolls.length"
                        :links="links" :pagination="meta" />
                </div>
            </div>
        </div>
    </BRow>
    <!-- Create/Edit Modal -->
    <PayrollModal
      v-if="showCreateModal || showEditModal"
      :payroll="selectedPayroll"
      :is-edit="showEditModal"
      :dropdowns="dropdowns"
      @close="closeModal"
      @saved="handleSaved"
    />
</template>

<script>
import _ from 'lodash';
import axios from 'axios'
import PayrollModal from './Modal.vue'
import Pagination from "@/Shared/Components/Pagination.vue";
import Swal from 'sweetalert2';

export default {
  components: { PayrollModal, Pagination },
  props: ['dropdowns'],
  data() {
    return {
      payrolls: [],
      meta: {},
      links: {},
      filter: {
        keyword: null
      },
      localKeyword: '',
      showCreateModal: false,
      showEditModal: false,
      selectedPayroll: null,
      selectedRow: null,
    }
  },
  watch: {
    "filter.keyword"(newVal) {
      this.checkSearchStr(newVal);
    }
  },
  created() {
    this.fetchPayrolls();
  },
  methods: {
    checkSearchStr: _.debounce(function (string) {
    }, 300),
    async fetchPayrolls() {
      axios.get('/payrolls', {
        params: {
          keyword: this.filter.keyword,
          count: 10,
          option: 'lists',
        }
      })
        .then(response => {
          if (response.data) {
            this.payrolls = response.data;
            
            this.meta = response.data.meta;
            this.links = response.data.links;
          }
        })
        .catch(err => console.log(err));
    },
    updateKeyword(value) {
      this.filter.keyword = value;
    },
    editPayroll(payroll) {
      if (payroll.status === 'draft') {
        this.selectedPayroll = { ...payroll }
        this.showEditModal = true
      } else {
        this.$emit('view', payroll)
      }
    },
    printPayroll(payroll) {
      window.open(`/payrolls/${payroll.id}/print`, '_blank');
    },
    async confirmDelete(template) {
      const result = await Swal.fire({
          title: 'Are you sure?',
          text: 'You want to delete this payroll?',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Yes, delete it!'
      });
      if (result.isConfirmed) {
          axios.delete(`/payrolls/${template.id}`)
              .then(response => {
                  if (response.data && response.data.info && response.data.message && response.data.status === false) {
                    Swal.fire({
                      title: response.data.message,
                      text: response.data.info,
                      icon: 'info',
                      confirmButtonText: 'OK'
                    });
                  } else {
                    this.fetchPayrolls();
                    Swal.fire({
                      title: response.data.message,
                      text: response.data.info,
                      icon: 'success',
                    });
                    this.selectedPayroll = null;
                  }
              })
              .catch(error => {
                  console.error(error);
                  Swal.fire(
                      'Error!',
                      'Failed to delete payroll.',
                      'error'
                  );
              });
      }
    },
    closeModal() {
      this.showCreateModal = false
      this.showEditModal = false
      this.selectedPayroll = null
      this.fetchPayrolls()
    },
    handleSaved() {
      this.closeModal()
    },
    formatDate(date) {
      return new Date(date).toLocaleDateString('en-PH', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
      })
    },
    formatCurrency(amount) {
      return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP'
      }).format(amount)
    },
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
