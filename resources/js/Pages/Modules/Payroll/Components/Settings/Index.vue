<template>
    <BRow>
        <div class="col-lg-12 mb-4">
            <div class="library-card">
                <div class="library-card-header">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="header-icon">
                                <i class="ri-settings-line fs-24"></i>
                            </div>
                            <div>
                                <h4 class="header-title mb-1">Payroll Settings</h4>
                                <p class="header-subtitle mb-0">Manage payroll calculation settings</p>
                            </div>
                        </div>
                        <!-- <button class="create-btn" @click="showCreateModal = true">
                            <i class="ri-add-line"></i>
                            <span>Add Setting</span>
                        </button> -->
                    </div>
                </div>
                <div class="card-body m-2 p-3">
                    <div class="search-section">
                        <div class="search-wrapper">
                            <i class="ri-search-line search-icon"></i>
                            <input type="text" v-model="localKeyword" @input="updateKeyword($event.target.value)"
                                placeholder="Search settings..." class="search-input">
                        </div>
                    </div>

                    <div class="table-responsive table-card">
                        <table class="table align-middle table-hover mb-0"
                            style="border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                            <thead style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                                <tr class="fs-12 fw-bold text-muted">
                                    <th style="width: 3%; border: none;">#</th>
                                    <th style="width: 20%;" class="text-center border-none">Field Name</th>
                                    <th style="width: 20%;" class="text-center border-none">Formula</th>
                                    <th style="width: 20%;" class="text-center border-none">Value (x)</th>
                                    <!-- <th style="width: 10%;" class="text-center border-none">Status</th> -->
                                    <th style="width: 17%;" class="text-center border-none">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="fs-12">
                                <tr v-for="(setting, index) in payrollSettings" :key="setting.id"
                                    class="transition-all" style="transition: all 0.3s ease;">
                                    <td class="text-center">{{ index + 1 }}</td>
                                    <td class="text-center fw-semibold">{{ setting.field_name }}</td>
                                    <td class="text-center">{{ setting.formula }}</td>
                                    <td class="text-center">{{ setting.value }}</td>
                                    <!-- <td class="text-center">
                                        <span class="status-badge" :style="getStatusStyle(setting.is_active ? 'active' : 'inactive')">
                                            {{ setting.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td> -->
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-1">
                                            <b-button @click.stop="viewSetting(setting)" variant="outline-info"
                                                v-b-tooltip.hover title="View" size="sm"
                                                class="btn-icon rounded-circle">
                                                <i class="ri-eye-line"></i>
                                            </b-button>
                                            <b-button @click.stop="editSetting(setting)" variant="outline-primary"
                                                v-b-tooltip.hover title="Edit" size="sm"
                                                class="btn-icon rounded-circle">
                                                <i class="ri-pencil-fill"></i>
                                            </b-button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light border-0 m-3">
                    <Pagination class="ms-2 me-2 mt-n1" v-if="meta" @fetch="fetchPayrollSettings" :lists="payrollSettings.length"
                        :links="links" :pagination="meta" />
                </div>
            </div>
        </div>
    </BRow>
    <!-- Create/Edit Modal -->
    <PayrollSettingModal
      v-if="showCreateModal || showEditModal"
      :setting="selectedSetting"
      :is-edit="showEditModal"
      @close="closeModal"
      @saved="handleSaved"
    />
</template>

<script>
import _ from 'lodash';
import axios from 'axios'
import PayrollSettingModal from './Modal.vue'
import Pagination from "@/Shared/Components/Pagination.vue";

export default {
  components: { PayrollSettingModal, Pagination },
  props: ['dropdown'],
  data() {
    return {
      payrollSettings: [],
      meta: {},
      links: {},
      filter: {
        keyword: null
      },
      localKeyword: '',
      showCreateModal: false,
      showEditModal: false,
      selectedSetting: null
    }
  },
  watch: {
    "filter.keyword"(newVal) {
      this.checkSearchStr(newVal);
    }
  },
  created() {
    this.fetchPayrollSettings();
  },
  methods: {
    checkSearchStr: _.debounce(function (string) {
      this.fetchPayrollSettings();
    }, 300),
    async fetchPayrollSettings(page_url) {
      page_url = page_url || '/payroll-settings';
      axios.get(page_url, {
        params: {
          keyword: this.filter.keyword,
          count: 10
        }
      })
        .then(response => {
          if (response) {
            this.payrollSettings = response.data.data;
            this.meta = response.data.meta;
            this.links = response.data.links;
          }
        })
        .catch(err => console.log(err));
    },
    updateKeyword(value) {
      this.filter.keyword = value;
    },
    viewSetting(setting) {
      this.selectedSetting = setting
      this.$toast.info(`Viewing setting`)
    },
    editSetting(setting) {
      this.selectedSetting = { ...setting }
      this.showEditModal = true
    },
    closeModal() {
      this.showCreateModal = false
      this.showEditModal = false
      this.selectedSetting = null
    },
    handleSaved() {
      this.closeModal()
      this.fetchPayrollSettings()
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
