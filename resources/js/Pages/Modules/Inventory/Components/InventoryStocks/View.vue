<template>
  <div class="inventory-stock-details">
    <div class="row" v-if="data">
      <!-- Main card with tabs -->
      <div class="col-sm-8">
        <div class="library-card mb-4">
          <div class="library-card-header">
            <div class="d-flex align-items-center gap-3">
              <div class="header-icon">
                <i class="ri-archive-line"></i>
              </div>
              <div>
                <h4 class="header-title mb-1">{{ data.batch_code }}</h4>
                <p class="header-subtitle mb-0">View and manage inventory stock details</p>
              </div>
            </div>
            <div class="d-flex gap-2 align-items-center">
              <button @click="$emit('back')" class="create-btn" v-b-tooltip.hover title="Back">
                <i class="ri-arrow-left-line"></i>
              </button>

              <!-- Actions kebab dropdown -->
              <div class="action-dropdown" ref="actionDropdown">
                <button class="create-btn action-kebab-btn" @click="showActions = !showActions">
                  <i class="ri-more-2-fill"></i>
                </button>
                <div class="action-dropdown-menu" v-if="showActions">
                  <button class="action-dropdown-item" @click="updatePrice(); showActions = false">
                    <i class="ri-price-tag-3-line"></i> Update Price
                  </button>
                  <button v-if="data.quantity > 0" class="action-dropdown-item" @click="adjustStock(); showActions = false">
                    <i class="ri-edit-line"></i> Adjust Stocks
                  </button>
                  <button v-if="data.quantity > 0" class="action-dropdown-item" @click="convertStock(); showActions = false">
                    <i class="ri-recycle-line"></i> Convert / Repack
                  </button>
                  <button class="action-dropdown-item" @click="recordLoss(); showActions = false">
                    <i class="ri-scales-3-line"></i> Record Loss
                  </button>
                  <div class="action-dropdown-divider"></div>
                  <button class="action-dropdown-item" @click="openSettings(); showActions = false">
                    <i class="ri-settings-3-line"></i> Settings
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Tab bar -->
          <div class="view-tab-bar">
            <button class="view-tab" :class="{ active: activeTab === 'details' }" @click="activeTab = 'details'">
              <i class="ri-box-3-line"></i> Stock Details
            </button>
            <button class="view-tab" :class="{ active: activeTab === 'weight-loss' }" @click="activeTab = 'weight-loss'">
              <i class="ri-scales-3-line"></i> Weight Loss
              <span v-if="weightLosses.length > 0" class="tab-badge">{{ weightLosses.length }}</span>
            </button>
            <button class="view-tab" :class="{ active: activeTab === 'sacks' }" @click="activeTab = 'sacks'">
              <i class="ri-stack-line"></i> Short Weight Sacks
              <span v-if="shortWeightSacks.length > 0" class="tab-badge tab-badge-red">{{ shortWeightSacks.length }}</span>
            </button>
            <button class="view-tab" :class="{ active: activeTab === 'conversions' }" @click="activeTab = 'conversions'">
              <i class="ri-recycle-line"></i> Conversions
              <span v-if="conversionsOut.length > 0" class="tab-badge tab-badge-teal">{{ conversionsOut.length }}</span>
            </button>
          </div>

          <div class="library-card-body">

            <!-- Tab: Stock Details -->
            <div v-if="activeTab === 'details'">
              <div class="row">
                <div class="col-12 mb-1">
                  <p class="section-label">Pricing &amp; Quantity</p>
                </div>
                <div class="col-md-6">
                  <div class="mb-3">
                    <label class="form-label">Unit Cost</label>
                    <p class="text-muted">{{ formatCurrency(data.unit_cost || data.received_item?.unit_cost) }}</p>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="mb-3">
                    <label class="form-label">Quantity</label>
                    <p class="text-muted">{{ data.quantity }} units</p>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="mb-3">
                    <label class="form-label">Retail Price</label>
                    <p class="text-muted">{{ formatCurrency(data.retail_price) }}</p>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="mb-3">
                    <label class="form-label">Wholesale Price</label>
                    <p class="text-muted">{{ formatCurrency(data.wholesale_price) }}</p>
                  </div>
                </div>

                <!-- Conversion info (replaces Received Info for converted batches) -->
                <template v-if="data.conversion">
                  <div class="col-12 mb-1 mt-2">
                    <p class="section-label">Conversion Information</p>
                  </div>
                  <div class="col-12">
                    <div class="conversion-info-banner">
                      <i class="ri-recycle-line"></i>
                      <div>
                        <span class="conv-info-label">Converted from</span>
                        <span class="conv-info-batch">{{ data.conversion.source_batch }}</span>
                        <span class="conv-info-meta">
                          {{ data.conversion.source_qty_used }} units → ratio {{ data.conversion.conversion_ratio }} → {{ data.conversion.output_quantity }} units
                          &nbsp;·&nbsp; {{ formatDate(data.conversion.conversion_date) }}
                          <template v-if="data.conversion.converted_by">&nbsp;·&nbsp; by {{ data.conversion.converted_by }}</template>
                        </span>
                        <span v-if="data.conversion.reason" class="conv-info-reason">{{ data.conversion.reason }}</span>
                      </div>
                    </div>
                  </div>
                </template>

                <!-- Received Information (only for purchased batches) -->
                <template v-if="data.received_item">
                  <div class="col-12 mb-1 mt-2">
                    <p class="section-label">Received Information</p>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label class="form-label">Received Date</label>
                      <p class="text-muted">{{ formatDate(data.received_item?.received_stock?.received_date) || 'N/A' }}</p>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label class="form-label">Product</label>
                      <p class="text-muted">{{ data.received_item?.product?.name || 'N/A' }}</p>
                    </div>
                  </div>
                  <div class="col-md-12">
                    <div class="mb-3">
                      <label class="form-label">Supplier</label>
                      <p class="text-muted">{{ data.received_item?.received_stock?.supplier?.name || 'N/A' }}</p>
                    </div>
                  </div>
                </template>

                <div class="col-12"><hr class="section-divider"></div>

                <div class="col-12 mb-1">
                  <p class="section-label">Additional Information</p>
                </div>
                <div class="col-md-4">
                  <div class="mb-3">
                    <label class="form-label">Expiration Date</label>
                    <p class="text-muted" :class="getExpirationClass(data.expiration_date)">
                      {{ formatDate(data.expiration_date) || 'No expiration' }}
                    </p>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="mb-3">
                    <label class="form-label">Created At</label>
                    <p class="text-muted">{{ formatDate(data.created_at) }}</p>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="mb-3">
                    <label class="form-label">Last Updated</label>
                    <p class="text-muted">{{ formatDate(data.updated_at) }}</p>
                  </div>
                </div>

              </div>
            </div>

            <!-- Tab: Weight Loss -->
            <div v-if="activeTab === 'weight-loss'">
              <div v-if="weightLosses.length === 0" class="tab-empty-state">
                <i class="ri-scales-3-line"></i>
                <p>No weight loss records yet.</p>
                <span>Click <strong>Record Loss</strong> in the header to add one.</span>
              </div>
              <template v-else>
                <div class="d-flex align-items-center justify-content-between mb-3">
                  <p class="section-label mb-0">Weight Loss History</p>
                  <span class="loss-summary-badge">Total: {{ formatKg(totalWeightLoss) }}</span>
                </div>
                <div class="conv-history-table-wrap">
                  <table class="conv-history-table">
                    <thead>
                      <tr>
                        <th>Date</th>
                        <th>Short Sacks</th>
                        <th>Per Sack</th>
                        <th>Total Loss</th>
                        <th>Reason</th>
                        <th>Recorded By</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="wl in paginatedLosses" :key="wl.id">
                        <td>{{ formatDate(wl.recorded_at) }}</td>
                        <td><span class="sack-count-badge">{{ wl.affected_sacks ?? '—' }} sacks</span></td>
                        <td class="text-muted">{{ wl.loss_per_sack ? wl.loss_per_sack + ' kg' : '—' }}</td>
                        <td class="text-danger fw-bold">- {{ formatKg(wl.loss_kg) }}</td>
                        <td>{{ wl.reason }}</td>
                        <td>{{ wl.recorded_by || '—' }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <div class="sacks-pagination" v-if="lossTotalPages > 1">
                  <button class="page-btn" :disabled="lossPage === 1" @click="lossPage--">
                    <i class="ri-arrow-left-s-line"></i>
                  </button>
                  <span class="page-info">
                    Page <strong>{{ lossPage }}</strong> of <strong>{{ lossTotalPages }}</strong>
                    &nbsp;·&nbsp; {{ weightLosses.length }} records
                  </span>
                  <button class="page-btn" :disabled="lossPage === lossTotalPages" @click="lossPage++">
                    <i class="ri-arrow-right-s-line"></i>
                  </button>
                </div>
              </template>
            </div>

            <!-- Tab: Short Weight Sacks -->
            <div v-if="activeTab === 'sacks'">
              <div v-if="shortWeightSacks.length === 0" class="tab-empty-state">
                <i class="ri-stack-line"></i>
                <p>No short weight sacks recorded.</p>
                <span>Record a weight loss to flag individual sacks.</span>
              </div>
              <template v-else>
                <div class="d-flex align-items-center justify-content-between mb-3">
                  <p class="section-label mb-0">
                    Short Weight Sacks &nbsp;
                    <span class="sack-count-badge">{{ shortWeightSacks.length }} total</span>
                  </p>
                  <span class="loss-summary-badge">
                    {{ shortWeightSacks.filter(s => !s.converted).length }} available · {{ shortWeightSacks.filter(s => s.converted).length }} converted
                  </span>
                </div>
                <div class="conv-history-table-wrap">
                  <table class="conv-history-table">
                    <thead>
                      <tr>
                        <th>#</th>
                        <th>Actual Weight</th>
                        <th>Loss</th>
                        <th>Reason</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="sack in paginatedSacks" :key="`${sack.group}-${sack.num}`"
                          :class="{ 'sack-row-converted': sack.converted }">
                        <td><span class="sack-num-badge">{{ sack.num }}</span></td>
                        <td class="fw-semibold">{{ formatKg(sack.actualKg) }}</td>
                        <td class="text-danger">- {{ formatKg(sack.lossKg) }}</td>
                        <td class="text-muted" style="font-size:0.78rem;">{{ sack.reason || '—' }}</td>
                        <td>
                          <span class="sack-status converted" v-if="sack.converted">
                            <i class="ri-recycle-line"></i> Converted
                          </span>
                          <span class="sack-status available" v-else>
                            <i class="ri-scales-3-line"></i> Short Weight
                          </span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <div class="sacks-pagination" v-if="sacksTotalPages > 1">
                  <button class="page-btn" :disabled="sacksPage === 1" @click="sacksPage--">
                    <i class="ri-arrow-left-s-line"></i>
                  </button>
                  <span class="page-info">
                    Page <strong>{{ sacksPage }}</strong> of <strong>{{ sacksTotalPages }}</strong>
                    &nbsp;·&nbsp; {{ shortWeightSacks.length }} sacks
                  </span>
                  <button class="page-btn" :disabled="sacksPage === sacksTotalPages" @click="sacksPage++">
                    <i class="ri-arrow-right-s-line"></i>
                  </button>
                </div>
              </template>
            </div>

            <!-- Tab: Conversions -->
            <div v-if="activeTab === 'conversions'">
              <div v-if="conversionsOut.length === 0" class="tab-empty-state">
                <i class="ri-recycle-line"></i>
                <p>No conversions yet.</p>
                <span>Use <strong>Convert / Repack</strong> in the header to create one.</span>
              </div>
              <template v-else>
                <div class="d-flex align-items-center justify-content-between mb-3">
                  <p class="section-label mb-0">Conversion History</p>
                  <span class="loss-summary-badge">{{ conversionsOut.length }} total</span>
                </div>
                <div class="conv-history-table-wrap">
                  <table class="conv-history-table">
                    <thead>
                      <tr>
                        <th>Date</th>
                        <th>Output Batch</th>
                        <th>Output Product</th>
                        <th>Qty Used</th>
                        <th>Ratio</th>
                        <th>Output Qty</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="c in paginatedConversions" :key="c.id">
                        <td>{{ formatDate(c.conversion_date) }}</td>
                        <td class="batch-mono">
                          <button
                            v-if="c.output_stock_id"
                            class="conv-batch-link"
                            @click="$refs.stockQuickView.show(c.output_stock_id)"
                          >{{ c.output_batch }}</button>
                          <span v-else>{{ c.output_batch || '—' }}</span>
                        </td>
                        <td>{{ c.output_product || '—' }}</td>
                        <td>{{ c.source_qty_used }}</td>
                        <td>{{ c.conversion_ratio }}</td>
                        <td><strong>{{ c.output_quantity }}</strong></td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <div class="sacks-pagination" v-if="convTotalPages > 1">
                  <button class="page-btn" :disabled="convPage === 1" @click="convPage--">
                    <i class="ri-arrow-left-s-line"></i>
                  </button>
                  <span class="page-info">
                    Page <strong>{{ convPage }}</strong> of <strong>{{ convTotalPages }}</strong>
                    &nbsp;·&nbsp; {{ conversionsOut.length }} records
                  </span>
                  <button class="page-btn" :disabled="convPage === convTotalPages" @click="convPage++">
                    <i class="ri-arrow-right-s-line"></i>
                  </button>
                </div>
              </template>
            </div>

          </div>
        </div>

        <!-- Logs -->
        <TransactionLogs
          :logs="adjustmentLogs"
          title="Inventory Adjustment Logs"
          subtitle="Activity history and adjustments"
          :compact="true"
        />
      </div>

      <!-- Right sidebar -->
      <div class="col-sm-4">
        <!-- Financial Summary -->
        <div class="summary-panel mb-3">
          <p class="section-label mb-3">Financial Summary</p>
          <div class="mb-3">
            <label class="form-label">Total Value</label>
            <p class="text-muted mb-0">{{ calculateTotalValue() }}</p>
          </div>
          <div class="mb-3">
            <label class="form-label">Profit Margin (Retail)</label>
            <p class="summary-margin text-success mb-0">{{ calculateProfitMargin('retail') }}</p>
          </div>
          <div class="mb-0">
            <label class="form-label">Profit Margin (Wholesale)</label>
            <p class="summary-margin text-warning mb-0">{{ calculateProfitMargin('wholesale') }}</p>
          </div>
        </div>

        <!-- Sack & Weight Summary (only when relevant) -->
        <div class="summary-panel weight-panel" v-if="totalWeightLoss > 0 || grossWeight > 0">
          <p class="section-label mb-3" style="color:#b91c1c;">Weight Summary</p>

          <template v-if="totalAffectedSacks > 0">
            <div class="sack-breakdown mb-3">
              <div class="sack-breakdown-row normal">
                <span class="sack-dot normal-dot"></span>
                <span class="sack-label">Normal sacks</span>
                <span class="sack-val">{{ normalSacks }} pcs</span>
              </div>
              <div class="sack-breakdown-row short">
                <span class="sack-dot short-dot"></span>
                <span class="sack-label">Short weight</span>
                <span class="sack-val text-danger">{{ totalAffectedSacks }} pcs</span>
              </div>
              <div class="sack-breakdown-divider"></div>
              <div class="sack-breakdown-row total">
                <span class="sack-dot" style="background:transparent"></span>
                <span class="sack-label fw-bold">Total</span>
                <span class="sack-val fw-bold">{{ data.quantity }} pcs</span>
              </div>
            </div>
          </template>

          <div class="d-flex justify-content-between align-items-center mb-2">
            <label class="form-label mb-0">Gross Weight</label>
            <span class="text-muted fw-semibold">{{ formatKg(grossWeight) }}</span>
          </div>
          <div class="d-flex justify-content-between align-items-center mb-2">
            <label class="form-label mb-0" style="color:#b91c1c;">Total Loss</label>
            <span class="text-danger fw-bold">- {{ formatKg(totalWeightLoss) }}</span>
          </div>
          <div class="adjusted-weight-box">
            <span class="adjusted-weight-label">Adjusted Weight</span>
            <span class="adjusted-weight-val">{{ formatKg(adjustedWeight) }}</span>
          </div>
        </div>

      </div>
    </div>

    <UpdatePriceModal
      :inventoryStock="data"
      @saved="$inertia.reload()"
      ref="updatePriceDialog"
    />

    <AdjustStockModal
      :inventoryStock="data"
      @saved="$inertia.reload()"
      ref="adjustStockDialog"
    />

    <ConvertStockModal
      :inventoryStock="data"
      :dropdowns="dropdowns"
      @saved="refreshData()"
      ref="convertStockDialog"
    />

    <RecordWeightLossModal
      :inventoryStock="data"
      @saved="refreshData()"
      ref="weightLossDialog"
    />

    <StockSettingsModal
      :stock="data"
      @saved="refreshData()"
      ref="settingsDialog"
    />

    <StockQuickViewModal ref="stockQuickView" />
  </div>
</template>

<script>
import { Link } from '@inertiajs/vue3';
import AdjustStockModal from '../../Modal/AdjustStockModal.vue';
import UpdatePriceModal from '../../Modal/UpdatePriceModal.vue';
import ConvertStockModal from '../../Modal/ConvertStockModal.vue';
import RecordWeightLossModal from '../../Modal/RecordWeightLossModal.vue';
import StockSettingsModal from '../../Modal/StockSettingsModal.vue';
import StockQuickViewModal from '../../Modal/StockQuickViewModal.vue';
import TransactionLogs from '@/Shared/Components/TransactionLogsCard.vue';

export default {
  emits: ['back'],
  components: {
    Link,
    AdjustStockModal,
    UpdatePriceModal,
    ConvertStockModal,
    RecordWeightLossModal,
    StockSettingsModal,
    StockQuickViewModal,
    TransactionLogs,
  },
  props: {
    stock: Object,
    inventory_stock_data: Object,
    dropdowns: Object,
  },
  data() {
    return {
      localStock:    null,
      activeTab:     'details',
      showActions:   false,
      sacksPage:     1,
      sacksPerPage:  10,
      lossPage:      1,
      lossPerPage:   10,
      convPage:      1,
      convPerPage:   10,
    };
  },
  mounted() {
    const id = this.stock?.id ?? this.inventory_stock_data?.data?.id;
    if (id) {
      axios.get(`/inventory-stocks?option=detail&id=${id}`)
        .then(res => { this.localStock = res.data.data; });
    }

    const hash = window.location.hash.replace('#', '');
    if (['details', 'weight-loss', 'sacks', 'conversions'].includes(hash)) {
      this.activeTab = hash;
    }

    this._outsideClick = (e) => {
      if (this.$refs.actionDropdown && !this.$refs.actionDropdown.contains(e.target)) {
        this.showActions = false;
      }
    };
    document.addEventListener('click', this._outsideClick);
  },
  unmounted() {
    document.removeEventListener('click', this._outsideClick);
  },
  watch: {
    activeTab(val) {
      window.location.hash = val;
      if (val === 'sacks') this.sacksPage = 1;
      if (val === 'weight-loss') this.lossPage = 1;
      if (val === 'conversions') this.convPage = 1;
    },
  },
  computed: {
    data() {
      if (this.localStock) return this.localStock;
      if (this.stock) return this.stock;
      return this.inventory_stock_data?.data || null;
    },
    adjustmentLogs() {
      const adjustments = (this.data?.inventory_adjustments || []).map(log => ({
        id: `adj-${log.id}`,
        created_at: log.adjustment_date,
        action: `Stock adjusted: ${log.previous_quantity} → ${log.new_quantity}`,
        actioned_by: log.received_by?.fullname || 'System',
        remarks: log.reason || null,
      }));

      const losses = (this.data?.weight_losses || []).map(wl => ({
        id: `wl-${wl.id}`,
        created_at: wl.recorded_at,
        action: wl.affected_sacks
          ? `Weight loss: ${wl.affected_sacks} sack${wl.affected_sacks !== 1 ? 's' : ''} × ${wl.loss_per_sack} kg = -${parseFloat(wl.loss_kg).toFixed(2)} kg`
          : `Weight loss: -${parseFloat(wl.loss_kg).toFixed(2)} kg`,
        actioned_by: wl.recorded_by || 'System',
        remarks: [wl.reason, wl.notes].filter(Boolean).join(' — ') || null,
      }));

      return [...adjustments, ...losses];
    },
    conversionsOut() {
      return this.data?.conversions_out || [];
    },
    weightLosses() {
      return [...(this.data?.weight_losses || [])].sort((a, b) => {
        const dateDiff = new Date(b.recorded_at || b.created_at) - new Date(a.recorded_at || a.created_at);
        return dateDiff !== 0 ? dateDiff : b.id - a.id;
      });
    },
    totalWeightLoss() {
      return this.weightLosses.reduce((sum, wl) => sum + parseFloat(wl.loss_kg || 0), 0);
    },
    totalAffectedSacks() {
      return this.weightLosses.reduce((sum, wl) => sum + (parseInt(wl.affected_sacks) || 0), 0);
    },
    normalSacks() {
      return Math.max(0, (this.data?.quantity || 0) - this.totalAffectedSacks);
    },
    grossWeight() {
      const qty = this.data?.quantity || 0;
      const weight = parseFloat(
        this.data?.product?.weight ||
        this.data?.received_item?.product?.weight ||
        0
      );
      return qty * weight;
    },
    adjustedWeight() {
      return Math.max(0, this.grossWeight - this.totalWeightLoss);
    },
    shortWeightSacks() {
      const packSize = parseFloat(
        this.data?.product?.weight ||
        this.data?.received_item?.product?.weight || 0
      );
      let sackNum = 0;
      const sacks = [];
      for (const wl of this.weightLosses) {
        const count = parseInt(wl.affected_sacks) || 0;
        for (let i = 0; i < count; i++) {
          sackNum++;
          sacks.push({
            num:         sackNum,
            actualKg:    packSize - (parseFloat(wl.loss_per_sack) || 0),
            lossKg:      parseFloat(wl.loss_per_sack) || 0,
            reason:      wl.reason,
            converted:   !!wl.converted_at,
            convertedAt: wl.converted_at,
            group:       wl.id,
          });
        }
      }
      return sacks;
    },
    paginatedSacks() {
      const start = (this.sacksPage - 1) * this.sacksPerPage;
      return this.shortWeightSacks.slice(start, start + this.sacksPerPage);
    },
    sacksTotalPages() {
      return Math.max(1, Math.ceil(this.shortWeightSacks.length / this.sacksPerPage));
    },
    paginatedLosses() {
      const start = (this.lossPage - 1) * this.lossPerPage;
      return this.weightLosses.slice(start, start + this.lossPerPage);
    },
    lossTotalPages() {
      return Math.max(1, Math.ceil(this.weightLosses.length / this.lossPerPage));
    },
    paginatedConversions() {
      const start = (this.convPage - 1) * this.convPerPage;
      return this.conversionsOut.slice(start, start + this.convPerPage);
    },
    convTotalPages() {
      return Math.max(1, Math.ceil(this.conversionsOut.length / this.convPerPage));
    },
  },
  methods: {
    updatePrice() {
      this.$refs.updatePriceDialog.show();
    },
    adjustStock() {
      this.$refs.adjustStockDialog.show();
    },
    convertStock() {
      this.$refs.convertStockDialog.show();
    },
    recordLoss() {
      this.$refs.weightLossDialog.show();
    },
    openSettings() {
      this.$refs.settingsDialog.show();
    },
    refreshData() {
      const id = this.data?.id;
      if (!id) return;
      axios.get(`/inventory-stocks?option=detail&id=${id}`)
        .then(res => { this.localStock = res.data.data; });
    },
    formatKg(value) {
      return parseFloat(value || 0).toFixed(2) + ' kg';
    },
    formatDate(date) {
      return new Date(date).toLocaleDateString();
    },
    formatCurrency(amount) {
      return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP'
      }).format(amount);
    },
    getExpirationClass(date) {
      if (!date) return 'text-muted';
      const today = new Date();
      const expDate = new Date(date);
      const daysUntilExp = Math.ceil((expDate - today) / (1000 * 60 * 60 * 24));
      
      if (daysUntilExp < 0) return 'text-danger fw-bold';
      if (daysUntilExp < 30) return 'text-warning fw-bold';
      return 'text-success';
    },
    calculateTotalValue() {
      const quantity = this.data.quantity || 0;
      const unitCost = this.data.unit_cost || this.data.received_item?.unit_cost || 0;
      return this.formatCurrency(quantity * unitCost);
    },
    calculateProfitMargin(type) {
      const unitCost = this.data.unit_cost || this.data.received_item?.unit_cost || 0;
      const price = type === 'retail' ? this.data.retail_price : this.data.wholesale_price;
      
      if (!unitCost || !price) return 'N/A';
      
      const margin = ((price - unitCost) / price) * 100;
      return margin.toFixed(1) + '%';
    }
  }
};
</script>
<style scoped>
.section-label {
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  color: #6b8c85;
  margin-bottom: 0;
}

.section-divider {
  border-color: #dee6e3;
  opacity: 1;
  margin: 0.5rem 0 1rem;
}

/* Tab bar */
.view-tab-bar {
  display: flex;
  border-bottom: 2px solid #dee6e3;
  padding: 0 1.1rem;
  background: #fafcfb;
}
.view-tab {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.55rem 0.9rem;
  font-size: 0.76rem;
  font-weight: 600;
  color: #94a3b8;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  cursor: pointer;
  transition: color 0.15s, border-color 0.15s;
}
.view-tab:hover { color: #64748b; }
.view-tab.active { color: #3d8d7a; border-bottom-color: #3d8d7a; }
.tab-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  background: #3d8d7a;
  color: #fff;
  font-size: 0.6rem;
  font-weight: 700;
  border-radius: 20px;
}

/* Summary panels */
.summary-panel {
  background: #f6fbf9;
  border: 1px solid #dee6e3;
  border-radius: 8px;
  padding: 1rem 1.1rem;
}
.summary-panel.weight-panel {
  background: #fef9f9;
  border-color: #fecaca;
}
.summary-margin {
  font-weight: 700;
  font-size: 1rem;
  margin-bottom: 0;
}
.adjusted-weight-box {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 8px;
  padding: 0.5rem 0.75rem;
  margin-top: 0.25rem;
}
.adjusted-weight-label {
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #15803d;
}
.adjusted-weight-val {
  font-size: 0.95rem;
  font-weight: 800;
  color: #15803d;
}

/* Tab empty state */
.tab-empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 2.5rem 1rem;
  text-align: center;
  color: #94a3b8;
}
.tab-empty-state i { font-size: 2rem; margin-bottom: 0.5rem; color: #c4d9d2; }
.tab-empty-state p { font-size: 0.85rem; font-weight: 600; margin: 0 0 0.25rem; color: #64748b; }
.tab-empty-state span { font-size: 0.78rem; }

/* Activity log list */
.activity-log-list { display: flex; flex-direction: column; gap: 0; }
.activity-log-entry {
  display: flex;
  gap: 0.75rem;
  align-items: flex-start;
  padding: 0.6rem 0;
  border-bottom: 1px solid #f0f7f4;
}
.activity-log-entry:last-child { border-bottom: none; }
.activity-log-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #3d8d7a;
  flex-shrink: 0;
  margin-top: 4px;
}
.activity-log-dot.red { background: #dc2626; }
.activity-log-content { flex: 1; min-width: 0; }
.activity-log-action { font-size: 0.8rem; font-weight: 600; color: #1e293b; }
.activity-log-meta { font-size: 0.72rem; color: #94a3b8; margin-top: 1px; }
.activity-log-remarks { font-size: 0.72rem; color: #64748b; font-style: italic; margin-top: 2px; }

/* Buttons */
.convert-btn {
  background: linear-gradient(135deg, #059669 0%, #047857 100%);
  border-color: #047857;
}
.loss-btn {
  background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
  border-color: #b91c1c;
}

/* Conversion banner */
.conversion-info-banner {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 10px;
  padding: 0.85rem 1rem;
  margin-bottom: 1rem;
  width: 100%;
}
.conversion-info-banner > i { font-size: 1.3rem; color: #16a34a; flex-shrink: 0; margin-top: 2px; }
.conv-info-label { display: block; font-size: 0.67rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #15803d; }
.conv-info-batch { display: block; font-size: 0.9rem; font-weight: 700; color: #14532d; font-family: 'Courier New', monospace; }
.conv-info-meta { display: block; font-size: 0.76rem; color: #166534; margin-top: 2px; }
.conv-info-reason { display: block; font-size: 0.76rem; color: #4ade80; font-style: italic; margin-top: 2px; }

/* Tables */
.conv-history-table-wrap {
  border: 1px solid #e4eeea;
  border-radius: 8px;
  overflow: hidden;
  margin-bottom: 1rem;
}
.conv-history-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
.conv-history-table thead th {
  background: #edf5f2;
  color: #527267;
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 0.5rem 0.75rem;
  white-space: nowrap;
}
.conv-history-table tbody td { padding: 0.5rem 0.75rem; border-top: 1px solid #f0f7f4; vertical-align: middle; color: #335c52; }
.conv-history-table tbody tr:hover { background: #fafcfb; }
.batch-mono { font-family: 'Courier New', monospace; color: #3d8d7a; font-weight: 700; font-size: 0.76rem; }
.conv-batch-link { font-family: 'Courier New', monospace; color: #3d8d7a; font-weight: 700; font-size: 0.76rem; text-decoration: underline; cursor: pointer; background: none; border: none; padding: 0; }
.conv-batch-link:hover { color: #2a6f5e; }

/* Sack breakdown */
.sack-breakdown { background: #f8fafb; border: 1px solid #e4eeea; border-radius: 8px; padding: 0.5rem 0.65rem; }
.sack-breakdown-row { display: flex; align-items: center; gap: 0.5rem; padding: 0.15rem 0; font-size: 0.8rem; }
.sack-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.normal-dot { background: #16a34a; }
.short-dot  { background: #dc2626; }
.sack-label { flex: 1; color: #475569; }
.sack-val   { font-weight: 600; color: #1e293b; white-space: nowrap; }
.sack-breakdown-divider { border-top: 1px solid #dee6e3; margin: 0.25rem 0; }

/* Badges */
.sack-count-badge {
  display: inline-block;
  background: #fff7ed;
  color: #c2410c;
  font-size: 0.72rem;
  font-weight: 700;
  padding: 0.1rem 0.45rem;
  border-radius: 20px;
  border: 1px solid #fed7aa;
}
.loss-summary-badge {
  display: inline-block;
  background: #fee2e2;
  color: #b91c1c;
  font-size: 0.68rem;
  font-weight: 700;
  padding: 0.15rem 0.55rem;
  border-radius: 20px;
  border: 1px solid #fca5a5;
}

/* Short weight sacks tab */
.tab-badge-red  { background: #dc2626; }
.tab-badge-teal { background: #0d9488; }

/* Actions kebab dropdown */
.action-dropdown { position: relative; }
.action-kebab-btn { padding: 0.4rem 0.6rem; }
.action-dropdown-menu {
  position: absolute;
  top: calc(100% + 6px);
  right: 0;
  min-width: 180px;
  background: #fff;
  border: 1px solid #c4d9d2;
  border-radius: 10px;
  box-shadow: 0 8px 24px rgba(0,0,0,0.10);
  z-index: 1050;
  padding: 4px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.action-dropdown-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0.5rem 0.75rem;
  border: none;
  background: transparent;
  color: #16322e;
  font-size: 0.82rem;
  font-weight: 500;
  border-radius: 7px;
  text-align: left;
  cursor: pointer;
  transition: background 0.15s;
}
.action-dropdown-item:hover { background: #edf6f2; }
.action-dropdown-item i { font-size: 1rem; color: #3d8d7a; }
.action-dropdown-divider { height: 1px; background: #e8ede8; margin: 2px 0; }
.sack-num-badge {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 24px; height: 20px; padding: 0 5px;
  background: #f1f5f9; border: 1px solid #dee6e3;
  border-radius: 4px; font-size: 0.68rem; font-weight: 700; color: #475569;
  font-family: 'Courier New', monospace;
}
.sack-row-converted td { opacity: 0.45; }
.sack-status {
  display: inline-flex; align-items: center; gap: 0.25rem;
  font-size: 0.68rem; font-weight: 700; border-radius: 20px; padding: 0.15rem 0.5rem;
}
.sack-status.available { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
.sack-status.converted { background: #f0fdf4; color: #15803d; border: 1px solid #86efac; }

.sacks-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 10px 0 4px;
  border-top: 1px solid #e8ede8;
  margin-top: 4px;
}
.page-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border: 1px solid #c4d9d2;
  border-radius: 6px;
  background: #fff;
  color: #16322e;
  cursor: pointer;
  font-size: 1rem;
  transition: background 0.15s;
}
.page-btn:hover:not(:disabled) { background: #edf6f2; }
.page-btn:disabled { opacity: 0.35; cursor: not-allowed; }
.page-info { font-size: 0.8rem; color: #6b8c85; }
</style>
