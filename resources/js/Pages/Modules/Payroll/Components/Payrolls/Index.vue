<template>
    <BRow>
        <div class="col-lg-12 mb-4">
            <div class="library-card">
                <div class="library-card-header">
                        <div class="d-flex align-items-center gap-3">
                            <div class="header-icon">
                                <i class="ri-money-peso-circle-line fs-24"></i>
                            </div>
                            <div>
                                <h4 class="header-title mb-1">Payroll Management</h4>
                                <p class="header-subtitle mb-0">A comprehensive list of Payrolls</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="guide-btn" @click="openGuide()">
                                <i class="ri-book-open-line"></i>
                                <span>Guide</span>
                            </button>
                            <button v-if="can('payroll', 'payroll_processing', 'encoder')" class="create-btn" @click="showCreateModal = true">
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
                        <SortSelect v-model="sortOption" :fields="[{ field: 'payroll_no', label: 'Payroll no.' }, { field: 'pay_period_start', label: 'Pay period', type: 'date' }, { field: 'total_amount', label: 'Total', type: 'number' }]" @change="fetchPayrolls()" />
                    </div>

                    <div class="table-responsive table-card">
                        <table class="table align-middle table-hover mb-0"
                            style="border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                            <thead style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                                <tr class="fs-12 fw-bold text-muted">
                                    <th style="width: 3%; border: none;">#</th>
                                    <th style="width: 15%;" class="text-center border-none">Pay Period</th>
                                    <th style="width: 20%;" class="text-center border-none">Payroll No.</th>
                                    <th style="width: 20%;" class="text-center border-none">Payroll Group</th>
                                    <th style="width: 10%;" class="text-center border-none">Total Net Salary</th>
                                    <th style="width: 10%;" class="text-center border-none">Payroll Clerk</th>
                                    <th style="width: 10%;" class="text-center border-none">Approved By</th>
                                    <th style="width: 10%;" class="text-center border-none">Status</th>
                                    <th style="width: 12%;" class="text-center border-none">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="fs-12">
                                <TableLoadingRow v-if="loading" :colspan="9" message="Loading payrolls..." />
                                <template v-else>
                                    <template v-for="(payroll, index) in payrolls" :key="payroll.id">
                                        <tr @click="viewPayroll(payroll)" :class="{
                                            'bg-primary bg-opacity-10 ': index === selectedRow,
                                            'cursor-pointer': true
                                        }" class="transition-all" style="transition: all 0.3s ease;">
                                            <td class="text-center">{{ index + 1 }}</td>
                                            <td class="text-center fw-semibold">{{ payroll.payroll_period }}</td>
                                            <td class="text-center">
                                                {{ payroll.payroll_no }}
                                            </td>
                                            <td class="text-center">{{ payroll.payroll_name }}</td>
                                            <td class="text-center fw-bold">{{ formatCurrency(payroll.total_amount) }}</td>
                                            <td class="text-center">{{ payroll.created_by }}</td>
                                            <td class="text-center">{{ payroll.approved_by }}</td>
                                            <td class="text-center">
                                                <span class="status-badge"
                                                  :style="{ color: payroll.status?.text_color, backgroundColor: payroll.status?.bg_color, padding: '0.25rem 0.5rem', borderRadius: '0.5rem' }">
                                                    {{ payroll.status.name }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex justify-content-center gap-1">
                                                    <!-- View opens the payroll to read; Edit opens the form.
                                                         They used to share one eye icon labelled "Edit". -->
                                                    <b-button @click.stop="viewPayroll(payroll)" variant="outline-primary"
                                                      v-b-tooltip.hover title="View" size="sm"
                                                      class="btn-icon rounded-circle">
                                                      <i class="ri-eye-line"></i>
                                                    </b-button>
                                                    <b-button v-if="canEdit(payroll)" @click.stop="openEdit(payroll)" variant="outline-success"
                                                      v-b-tooltip.hover title="Edit" size="sm"
                                                      class="btn-icon rounded-circle">
                                                      <i class="ri-pencil-line"></i>
                                                    </b-button>
                                                    <b-button @click.stop="printPayroll(payroll)" variant="outline-info"
                                                        v-b-tooltip.hover title="Print" size="sm"
                                                        class="btn-icon rounded-circle"
                                                        v-if="payroll.status?.slug !== 'draft'">
                                                        <i class="ri-printer-line"></i>
                                                    </b-button>
                                                    <b-button v-if="payroll.status?.slug === 'draft' && can('payroll', 'payroll_processing', 'admin')" @click.stop="confirmDelete(payroll)" variant="outline-danger"
                                                        v-b-tooltip.hover title="Delete" size="sm"
                                                        class="btn-icon rounded-circle">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </b-button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr v-if="loadError">
                                        <td colspan="8" class="text-center text-danger">{{ loadError }}</td>
                                    </tr>
                                    <tr v-else-if="!payrolls.length">
                                        <td colspan="8" class="text-center">No data available</td>
                                    </tr>
                                </template>
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
    <PayrollModal
      v-if="showCreateModal"
      :payroll="selectedPayroll"
      :dropdowns="dropdowns"
      @close="closeModal"
      @saved="handleSaved"
    />
    <PayrollModal
      v-if="showEditModal && selectedPayroll"
      :payroll="selectedPayroll"
      :is-edit="true"
      :keep-status="selectedPayroll.status?.slug !== 'draft'"
      :dropdowns="dropdowns"
      @close="closeModal"
      @saved="handleSaved"
    />
</template>

<script>
import _ from 'lodash';
import { listSortMixin } from '@/Shared/listSort.js';
import axios from 'axios'
import PayrollModal from './Modal.vue'
import { openGuide } from '@/Shared/Components/ModuleGuide/moduleGuides.js';
import Pagination from "@/Shared/Components/Pagination.vue";
import TableLoadingRow from '@/Shared/Components/TableLoadingRow.vue';
import Swal from 'sweetalert2';

import { openDocument } from '@/Shared/utils/printDocument';
export default {
  components: { PayrollModal, Pagination, TableLoadingRow },
  mixins: [listSortMixin],
  props: ['dropdowns'],
  data() {
    return {
      payrolls: [],
      // Null until a response says otherwise, so the pagination control is
      // not rendered against an envelope that is not there yet.
      meta: null,
      links: {},
      loadError: null,
      filter: {
        keyword: null
      },
      localKeyword: '',
      showCreateModal: false,
      showEditModal: false,
      selectedPayroll: null,
      selectedRow: null,
      loading: false,
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
    async fetchPayrolls(page_url) {
      this.loading = true;
      this.loadError = null;
      axios.get(page_url || '/payrolls', {
        params: {
          keyword: this.filter.keyword,
          count: 10,
          ...this.sortParams, option: 'lists',
        }
      })
        .then(response => {
          // The rows live under `data`; the envelope beside them is what the
          // pagination control runs on.
          this.payrolls = response.data?.data ?? [];
          this.meta = response.data?.meta ?? null;
          this.links = response.data?.links ?? {};
        })
        .catch(err => {
          // Swallowing this is how a payroll that saved looked like one that
          // never did: the list failed and the screen said nothing.
          console.error(err);
          this.payrolls = [];
          this.meta = null;
          this.loadError = err.response?.status === 403
            ? 'You do not have permission to view payrolls.'
            : 'Could not load payrolls. Reload the page to try again.';
        })
        .finally(() => {
          this.loading = false;
        });
    },
    updateKeyword(value) {
      this.filter.keyword = value;
    },
    viewPayroll(payroll) {
      this.$emit('view', payroll)
    },
    // Payroll documents open in their own tab, where they can be read,
    // zoomed, saved or printed, rather than raising the print dialog here.
    openPrint(url) {
      if (!openDocument(url)) {
        this.$toast?.error("Your browser blocked the new tab. Allow pop-ups for this site and try again.");
      }
    },
    // Opens the guide book on the payroll chapter.
    openGuide() {
      openGuide('payroll');
    },
    printPayroll(payroll) {
      this.openPrint(`/payrolls/${payroll.id}/print`);
    },
    /**
     * Who gets the pencil — the same rule the server enforces when saving:
     * a draft belongs to whoever created it, a submitted payroll can be
     * corrected only by a Super Admin, and a released one by nobody.
     */
    canEdit(payroll) {
      const slug = payroll.status?.slug;
      if (slug === 'completed') return false;

      const roles = this.$page.props.roles;
      const isSuperAdmin = (roles ? Object.values(roles) : []).includes('Super Admin');
      if (isSuperAdmin) return true;

      return slug === 'draft'
        && this.can('payroll', 'payroll_processing', 'encoder')
        && payroll.created_by_id === this.$page.props.user.data.id;
    },
    openEdit(payroll) {
      this.selectedPayroll = payroll;
      this.showEditModal = true;
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
/* Quieter than Add Payroll: it is help, not the main action. */
.guide-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.42rem 0.85rem;
  border: 1px solid var(--brt-c-b7cec4);
  border-radius: 8px;
  background: #fff;
  color: var(--brt-primary-dark);
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s ease;
}

.guide-btn:hover {
  background: var(--brt-c-f1f8f5);
  border-color: var(--brt-primary);
}

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
