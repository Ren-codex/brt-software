<template>
  <div class="library-card">
    <div class="library-card-header">
      <div class="d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
          <div class="header-icon">
            <i class="ri-bank-card-fill fs-24"></i>
          </div>
          <div>
            <h4 class="header-title mb-1">List of Loans</h4>
            <p class="header-subtitle mb-0">A comprehensive list of employee loans</p>
          </div>
        </div>
        <button class="create-btn" @click="openCreate">
          <i class="ri-add-line"></i>
          <span>Loan</span>
        </button>
      </div>
    </div>

    <div class="card-body m-2 p-3">
      <div class="search-section">
        <div class="search-wrapper">
          <i class="ri-search-line search-icon"></i>
          <input
            type="text"
            v-model="filter.keyword"
            placeholder="Search loans..."
            class="search-input"
          >
        </div>
      </div>

      <div class="table-responsive table-card" style="overflow: auto;">
        <table class="table align-middle table-striped table-centered mb-0">
          <thead class="table-light thead-fixed">
            <tr class="fs-11">
              <th style="width: 3%;">#</th>
              <th style="width: 10%;">Loan No.</th>
              <th style="width: 15%;">Employee</th>
              <th style="width: 10%;">Loan Type</th>
              <th style="width: 10%;">Amount</th>
              <th style="width: 8%;">Interest Rate</th>
              <th style="width: 8%;">Term (Months)</th>
              <th style="width: 8%;">Status</th>
              <th style="width: 10%;">Created</th>
              <th style="width: 10%;" class="text-center">Actions</th>
            </tr>
          </thead>

          <tbody class="table-white fs-12">
            <tr
              v-for="(loan, index) in loans"
              :key="index"
              :class="{
                'bg-info-subtle': index === selectedRow,
                'bg-danger-subtle': loan.status === 'overdue'
              }"
            >
              <td class="text-center">
                {{ index + 1 }}
              </td>
              <td>{{ loan.loan_no || '-' }}</td>
              <td>
                <div class="d-flex align-items-center">
                  <div class="avatar-xs me-2">
                    <img
                      :src="getAvatarSrc(loan)"
                      @error="handleAvatarError"
                      alt="Avatar"
                      class="rounded-circle avatar-xs"
                    >
                  </div>
                  <span>{{ loan.employee ? loan.employee.fullname : '-' }}</span>
                </div>
              </td>
              <td>{{ loan.loan_type || '-' }}</td>
              <td>{{ formatAmount(loan.amount) }}</td>
              <td>{{ loan.interest_rate ? loan.interest_rate + '%' : '-' }}</td>
              <td>{{ loan.term_months || '-' }}</td>
              <td>
                <span :class="getStatusClass(loan.status)">
                  {{ loan.status || '-' }}
                </span>
              </td>
              <td>{{ loan.created_at }}</td>

              <td class="text-center">
                <div class="d-flex justify-content-center gap-1">
                  <b-button @click.stop="viewLoan(loan)" variant="outline-primary"
                    v-b-tooltip.hover title="View" size="sm"
                    class="btn-icon rounded-circle">
                    <i class="ri-eye-line"></i>
                  </b-button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card-footer">
      <Pagination
        class="ms-2 me-2 mt-n1"
        v-if="meta"
        @fetch="fetch()"
        :loans="loans.length"
        :links="links"
        :pagination="meta"
      />
    </div>
  </div>

  <Create @add="fetch()" @update="fetch()" :dropdowns="dropdowns" ref="create" />
  <DeleteModal ref="deleteModal" />
</template>

<script>
import _ from 'lodash';
import Pagination from '@/Shared/Components/Pagination.vue';
import DeleteModal from '@/Shared/Components/Modals/DeleteModal.vue';
import Create from './Create.vue';

export default {
  components: { Pagination, Create, DeleteModal },
  props: ['dropdowns'],
  emits: ['view'],
  data() {
    return {
      loans: [],
      meta: {},
      links: {},
      filter: {
        keyword: ''
      },
      selectedRow: null,
      defaultAvatar: '/images/default-avatar.png'
    };
  },
  watch: {
    'filter.keyword': _.debounce(function () {
      this.fetch();
    }, 300)
  },
  created() {
    this.fetch();
  },
  methods: {
    fetch(page_url) {
      const url = page_url || '/loans';
      axios.get(url, {
        params: {
          keyword: this.filter.keyword,
          count: 10,
          option: 'lists'
        }
      })
      .then(response => {
        this.loans = response?.data?.data || [];
        this.meta = response?.data?.meta || {};
        this.links = response?.data?.links || {};
      })
      .catch(err => console.log(err));
    },
    getAvatarSrc(list) {
      if (!list?.employee?.avatar) {
        return this.defaultAvatar;
      }
      return `/storage/${list.employee.avatar}`;
    },
    handleAvatarError(event) {
      if (event?.target) {
        event.target.onerror = null;
        event.target.src = this.defaultAvatar;
      }
    },
    openCreate() {
      this.$refs.create.show();
    },
    viewLoan(loan) {
      this.$emit('view', loan);
    },
    openEdit(data, index) {
      this.selectedRow = index;
      this.$refs.create.edit(data, index);
    },
    async onDelete(id) {
      const confirmed = await this.$refs.deleteModal.show(
        'Delete Loan',
        'Are you sure you want to delete this loan? This action cannot be undone.'
      );

      if (!confirmed) {
        return;
      }

      axios.delete(`/loans/${id}`)
      .then(response => {
        this.fetch();
        this.$toast.success(response.data.message || 'Loan deleted successfully');
      })
      .catch(err => {
        console.log(err);
        this.$toast.error('Failed to delete loan');
      });
    },
    formatAmount(amount) {
      if (!amount) {
        return '-';
      }
      return `PHP ${parseFloat(amount).toLocaleString()}`;
    },
    getStatusClass(status) {
      switch (status) {
        case 'active':
          return 'badge bg-success';
        case 'pending':
          return 'badge bg-warning';
        case 'completed':
          return 'badge bg-info';
        case 'rejected':
        case 'overdue':
          return 'badge bg-danger';
        default:
          return 'badge bg-secondary';
      }
    }
  }
};
</script>
