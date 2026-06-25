<template>
  <Teleport to="body">
    <div v-if="showModal" class="modal-overlay active" @click.self="hide">
      <div class="modal-container convert-modal-container" @click.stop>
        <div class="modal-header">
          <h2><i class="ri-recycle-line me-2"></i>Convert / Repack Stock</h2>
          <button class="close-btn" @click="hide">
            <i class="ri-close-line"></i>
          </button>
        </div>

        <div class="modal-body convert-body">
          <div class="convert-layout">

            <!-- ══ LEFT: FORMS ══ -->
            <div class="convert-form-col">

              <!-- ── Confirmation panel ── -->
              <div class="confirm-panel" v-if="showConfirm">
                <div class="confirm-icon"><i class="ri-error-warning-line"></i></div>
                <div class="confirm-title">Confirm Conversion</div>
                <div class="confirm-desc">
                  Convert <strong>{{ confirmSummary.sourceQty }}</strong> sack{{ confirmSummary.sourceQty !== 1 ? 's' : '' }}
                  from batch <strong>{{ inventoryStock?.batch_code }}</strong>
                  into <strong>{{ confirmSummary.outputQty }}</strong> sack{{ confirmSummary.outputQty !== 1 ? 's' : '' }}
                  of <strong>{{ confirmSummary.outputLabel }}</strong>?
                </div>
                <div class="confirm-remaining" :class="{ zero: remainingAfterConversion === 0 }">
                  <i :class="remainingAfterConversion === 0 ? 'ri-alert-line' : 'ri-stack-line'"></i>
                  <span v-if="remainingAfterConversion > 0">{{ remainingAfterConversion }} sacks will remain in source batch</span>
                  <span v-else>Source batch will be fully depleted</span>
                </div>
              </div>

              <!-- ── Success panel ── -->
              <div class="success-panel" v-else-if="successResult">
                <div class="success-icon"><i class="ri-checkbox-circle-fill"></i></div>
                <div class="success-title">Conversion Complete!</div>
                <div class="success-flow">
                  <div class="success-flow-item source">
                    <div class="sflow-num">{{ successResult.sourceQty }}</div>
                    <div class="sflow-label">sacks in</div>
                    <div class="sflow-batch">{{ successResult.sourceBatch }}</div>
                  </div>
                  <div class="success-flow-arrow"><i class="ri-arrow-right-line"></i></div>
                  <div class="success-flow-item target">
                    <div class="sflow-num">{{ successResult.outputQty }}</div>
                    <div class="sflow-label">new sacks</div>
                    <div class="sflow-batch">{{ successResult.outputLabel }}</div>
                  </div>
                </div>
                <div class="success-remaining" v-if="successResult.remaining > 0">
                  <i class="ri-information-line"></i>
                  {{ successResult.remaining }} sacks still available in source batch
                </div>
              </div>

              <!-- ── Normal form ── -->
              <template v-else>

              <div class="error-alert" v-if="localErrors.product_id || localErrors.source_stock_id">
                <i class="ri-error-warning-line"></i>
                <span>{{ localErrors.product_id || localErrors.source_stock_id }}</span>
              </div>

              <form @submit.prevent="submit">

                <!-- Target product -->
                <div class="cf-label">Convert To Product</div>

                <div class="product-selector-group">
                  <div v-if="selectedProduct" class="selected-product-chip">
                    <i class="ri-box-3-line"></i>
                    <span class="selected-product-name">{{ selectedProduct.name }}</span>
                    <button type="button" class="chip-clear-btn" @click="clearProduct">
                      <i class="ri-close-line"></i>
                    </button>
                  </div>
                  <template v-else>
                    <div class="product-search-row">
                      <div class="input-wrapper" style="flex:1;min-width:0;">
                        <i class="ri-search-line input-icon"></i>
                        <input type="text" v-model="productSearch" class="form-control"
                          :class="{ 'input-error': localErrors.product_id }"
                          placeholder="Search product name or code..." />
                      </div>
                      <button type="button" class="btn-add-product" :class="{ active: showAddProduct }"
                        @click="showAddProduct = !showAddProduct" title="Add new product">
                        <i class="ri-add-line"></i>
                      </button>
                    </div>
                    <div v-if="productSearch.trim()" class="product-dropdown">
                      <div v-if="filteredProducts.length === 0" class="product-dropdown-empty">
                        <i class="ri-search-line"></i> No products found — click <strong>+</strong> to add one
                      </div>
                      <button v-else v-for="p in filteredProducts.slice(0, 8)" :key="p.id"
                        type="button" class="product-dropdown-item" @click="selectProduct(p)">
                        <span class="pdi-name">{{ p.name }}</span>
                        <span class="pdi-code">{{ p.code }}</span>
                      </button>
                    </div>
                  </template>
                  <span class="error-message" v-if="localErrors.product_id">{{ localErrors.product_id }}</span>
                </div>

                <div v-if="showAddProduct && !selectedProduct" class="add-product-panel">
                  <div class="add-product-header"><i class="ri-add-circle-line"></i> Add New Product</div>
                  <div class="row g-2">
                    <div class="col-12">
                      <label class="form-label">Brand</label>
                      <select v-model="newProduct.brand_id" class="form-control form-select" :class="{ 'input-error': addProductErrors.brand_id }">
                        <option value="">Select brand...</option>
                        <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                      </select>
                      <span class="error-message" v-if="addProductErrors.brand_id">{{ addProductErrors.brand_id }}</span>
                    </div>
                    <div class="col-5">
                      <label class="form-label">Weight</label>
                      <input type="number" v-model="newProduct.weight" class="form-control" :class="{ 'input-error': addProductErrors.weight }" min="0.01" step="0.01" placeholder="e.g. 5" />
                      <span class="error-message" v-if="addProductErrors.weight">{{ addProductErrors.weight }}</span>
                    </div>
                    <div class="col-4">
                      <label class="form-label">Unit</label>
                      <select v-model="newProduct.unit_id" class="form-control form-select" :class="{ 'input-error': addProductErrors.unit_id }">
                        <option value="">Unit...</option>
                        <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
                      </select>
                      <span class="error-message" v-if="addProductErrors.unit_id">{{ addProductErrors.unit_id }}</span>
                    </div>
                    <div class="col-3 d-flex align-items-end">
                      <button type="button" class="btn btn-save w-100" :disabled="addProductLoading" @click="saveNewProduct">
                        <span v-if="addProductLoading"><i class="ri-loader-4-line"></i></span>
                        <span v-else><i class="ri-save-line"></i> Save</span>
                      </button>
                    </div>
                    <div class="col-12">
                      <label class="form-label">Code <span class="optional">(optional)</span></label>
                      <input type="text" v-model="newProduct.code" class="form-control" placeholder="Auto-generated if blank" />
                    </div>
                  </div>
                </div>

                <!-- Conversion -->
                <div class="cf-label">Conversion</div>

                <div class="basis-toggle-group" v-if="totalAffectedSacks > 0">
                  <div class="basis-toggle-label">Convert based on</div>
                  <div class="basis-toggle">
                    <button type="button" class="basis-btn" :class="{ active: conversionBasis === 'sacks' }" @click="setBasis('sacks')">
                      <span class="sack-dot normal-dot"></span> Normal Sacks
                      <span class="basis-count">{{ normalSacks }}</span>
                    </button>
                    <button type="button" class="basis-btn" :class="{ active: conversionBasis === 'weight' }" @click="setBasis('weight')">
                      <i class="ri-scales-3-line"></i> Short Weight
                      <span class="basis-count">{{ totalAffectedSacks }} sacks</span>
                    </button>
                  </div>
                </div>

                <!-- Short weight mode -->
                <template v-if="conversionBasis === 'weight'">
                  <div class="sw-panel">
                    <div class="sw-panel-header">
                      <span>Select short weight groups</span>
                      <span v-if="selectedTotalKg > 0" class="sw-selected-total">Selected: <strong>{{ formatKg(selectedTotalKg) }}</strong></span>
                    </div>
                    <div class="sw-loss-list">
                      <label class="sw-loss-row" :class="{ selected: selectedLossIds.includes(wl.id) }"
                        v-for="wl in weightLosses" :key="wl.id">
                        <input type="checkbox" class="sw-check" :value="wl.id" v-model="selectedLossIds" />
                        <span class="sack-dot short-dot"></span>
                        <span class="sw-sacks">{{ wl.affected_sacks }} sacks</span>
                        <span class="sw-op">×</span>
                        <span class="sw-per">{{ formatKg(packSize - wl.loss_per_sack) }} kg/sack</span>
                        <span class="sw-op">=</span>
                        <span class="sw-kg">{{ formatKg(wl.affected_sacks * (packSize - wl.loss_per_sack)) }}</span>
                        <span v-if="wl.reason" class="sw-reason">{{ wl.reason }}</span>
                      </label>
                    </div>
                  </div>
                  <span class="error-message" v-if="localErrors.source_qty_used || form.errors.source_qty_used">{{ localErrors.source_qty_used || form.errors.source_qty_used }}</span>
                  <span class="error-message" v-if="localErrors.conversion_ratio || form.errors.conversion_ratio">{{ localErrors.conversion_ratio || form.errors.conversion_ratio }}</span>
                </template>

                <!-- Normal sacks mode -->
                <template v-else>
                  <div class="form-group">
                    <label class="form-label">
                      Quantity to Convert
                      <button type="button" class="max-qty-btn"
                        @click="form.source_qty_used = totalAffectedSacks > 0 ? normalSacks : (inventoryStock?.quantity || 0)">
                        Max
                      </button>
                    </label>
                    <div class="input-wrapper">
                      <i class="ri-subtract-line input-icon"></i>
                      <input type="number" v-model="form.source_qty_used" class="form-control"
                        :class="{ 'input-error': localErrors.source_qty_used || form.errors.source_qty_used }"
                        min="1" :max="totalAffectedSacks > 0 ? normalSacks : inventoryStock?.quantity"
                        placeholder="e.g. 10"
                        @input="localErrors.source_qty_used = null"
                        @change="clearError('source_qty_used')" />
                    </div>
                    <span class="error-message" v-if="localErrors.source_qty_used || form.errors.source_qty_used">{{ localErrors.source_qty_used || form.errors.source_qty_used }}</span>
                  </div>
                  <span class="error-message" v-if="localErrors.conversion_ratio || form.errors.conversion_ratio">{{ localErrors.conversion_ratio || form.errors.conversion_ratio }}</span>
                </template>

                <!-- Pricing -->
                <div class="cf-label">
                  Pricing for New Batch
                  <span class="price-cost-hint" v-if="newSackUnitCost > 0">Unit cost: <strong>{{ formatCurrency(newSackUnitCost) }}</strong></span>
                </div>

                <div class="row g-3 mb-3">
                  <div class="col-6">
                    <div class="form-group mb-0">
                      <label class="form-label">Wholesale Price</label>
                      <div class="input-wrapper">
                        <i class="ri-stack-line input-icon"></i>
                        <input type="number" v-model="form.wholesale_price" class="form-control"
                          :class="{ 'input-error': form.errors.wholesale_price || wholesalePriceWarning }"
                          min="0" step="0.01" placeholder="0.00" />
                      </div>
                      <span class="error-message" v-if="form.errors.wholesale_price">{{ form.errors.wholesale_price }}</span>
                      <span class="price-warning" v-else-if="wholesalePriceWarning"><i class="ri-error-warning-line"></i> {{ wholesalePriceWarning }}</span>
                    </div>
                  </div>
                  <div class="col-6">
                    <div class="form-group mb-0">
                      <label class="form-label">Retail Price</label>
                      <div class="input-wrapper">
                        <i class="ri-price-tag-line input-icon"></i>
                        <input type="number" v-model="form.retail_price" class="form-control"
                          :class="{ 'input-error': form.errors.retail_price || retailPriceWarning }"
                          min="0" step="0.01" placeholder="0.00" />
                      </div>
                      <span class="error-message" v-if="form.errors.retail_price">{{ form.errors.retail_price }}</span>
                      <span class="price-warning" v-else-if="retailPriceWarning"><i class="ri-error-warning-line"></i> {{ retailPriceWarning }}</span>
                    </div>
                  </div>
                </div>

                <div class="row g-3">
                  <div class="col-6">
                    <div class="form-group mb-0">
                      <label class="form-label">Expiration Date <span class="optional">(optional)</span></label>
                      <div class="input-wrapper">
                        <i class="ri-calendar-line input-icon"></i>
                        <input type="date" v-model="form.expiration_date" class="form-control" :class="{ 'input-error': form.errors.expiration_date }" />
                      </div>
                      <span class="error-message" v-if="form.errors.expiration_date">{{ form.errors.expiration_date }}</span>
                    </div>
                  </div>
                  <div class="col-6">
                    <div class="form-group mb-0">
                      <label class="form-label">Reason / Notes <span class="optional">(optional)</span></label>
                      <div class="input-wrapper">
                        <textarea v-model="form.reason" class="form-control" rows="2"
                          placeholder="e.g. Repacking 50kg bags into 10kg retail packs"></textarea>
                      </div>
                    </div>
                  </div>
                </div>

              </form>

              </template><!-- end normal form -->
            </div>

            <!-- ══ RIGHT: DATA ══ -->
            <div class="convert-data-col">

              <!-- Source batch -->
              <div class="data-card source-card">
                <div class="data-card-label"><i class="ri-archive-line"></i> Source Batch</div>
                <div class="data-batch-code">{{ inventoryStock?.batch_code }}</div>
                <div class="data-product-name">{{ sourceProductName }}</div>
                <div class="data-qty-row">
                  <i class="ri-stack-line"></i> Available: <strong>{{ inventoryStock?.quantity }}</strong> units
                </div>
                <template v-if="totalAffectedSacks > 0">
                  <div class="data-divider"></div>
                  <div class="data-sack-row">
                    <span class="data-sack-item">
                      <span class="sack-dot normal-dot"></span> Normal <strong>{{ normalSacks }}</strong>
                    </span>
                    <span class="data-sack-item short">
                      <span class="sack-dot short-dot"></span> Short <strong>{{ totalAffectedSacks }}</strong>
                    </span>
                  </div>
                  <div class="data-weight-row">
                    Weight left: <strong>{{ formatKg(adjustedWeight) }}</strong>
                    <span class="data-loss">(-{{ formatKg(totalWeightLoss) }})</span>
                  </div>
                </template>
                <template v-if="remainingAfterConversion >= 0 && remainingAfterConversion < (inventoryStock?.quantity || 0)">
                  <div class="data-divider"></div>
                  <div class="data-remaining-row" :class="{ 'data-remaining-zero': remainingAfterConversion === 0 }">
                    <i :class="remainingAfterConversion === 0 ? 'ri-alert-line' : 'ri-corner-down-right-line'"></i>
                    After: <strong>{{ remainingAfterConversion }}</strong> sacks remain
                  </div>
                </template>
              </div>

              <!-- Output preview: normal mode -->
              <div class="data-card preview-card" v-if="conversionBasis === 'sacks' && selectedProduct && outputQuantity > 0">
                <div class="data-card-label"><i class="ri-exchange-line"></i> Output Preview</div>
                <div class="preview-flow">
                  <div class="preview-box source-box">
                    <div class="preview-num">{{ form.source_qty_used }}</div>
                    <div class="preview-unit-label">sacks in</div>
                    <div class="preview-kg-label" v-if="packSize > 0">{{ packSize }}kg ea.</div>
                  </div>
                  <div class="preview-arrow"><i class="ri-arrow-right-line"></i></div>
                  <div class="preview-box target-box">
                    <div class="preview-num">{{ outputQuantity }}</div>
                    <div class="preview-unit-label">sacks out</div>
                    <div class="preview-kg-label" v-if="selectedProduct.weight">{{ selectedProduct.weight }}kg ea.</div>
                  </div>
                </div>
                <div class="preview-cost-row" v-if="newSackUnitCost > 0">
                  <span>Per sack <strong>{{ formatCurrency(newSackUnitCost) }}</strong></span>
                  <span>Total <strong>{{ formatCurrency(newSackUnitCost * outputQuantity) }}</strong></span>
                </div>
              </div>

              <!-- Output preview: short weight mode -->
              <div class="data-card preview-card" v-if="conversionBasis === 'weight' && selectedTotalKg > 0"
                   :class="{ 'preview-card-warn': insufficientKgForOutput }">
                <div class="data-card-label"><i class="ri-exchange-line"></i> Output Preview</div>
                <template v-if="repackSize > 0">
                  <!-- Not enough kg for even one output sack -->
                  <div class="insufficient-kg-warn" v-if="insufficientKgForOutput">
                    <i class="ri-error-warning-fill"></i>
                    <div>
                      <strong>{{ formatKg(selectedTotalKg) }}</strong> selected — not enough to fill one <strong>{{ repackSize }} kg</strong> bag.
                      <br>Choose a product ≤ <strong>{{ formatKg(selectedTotalKg) }}</strong> per sack.
                    </div>
                  </div>
                  <template v-else>
                    <div class="preview-flow">
                      <div class="preview-box source-box">
                        <div class="preview-num" style="font-size:1.1rem;">{{ formatKg(selectedTotalKg) }}</div>
                        <div class="preview-unit-label">selected kg</div>
                      </div>
                      <div class="preview-arrow"><i class="ri-arrow-right-line"></i></div>
                      <div class="preview-box target-box">
                        <div class="preview-num">{{ totalOutputSacks }}</div>
                        <div class="preview-unit-label">sacks out</div>
                        <div class="preview-kg-label">× {{ repackSize }}kg</div>
                      </div>
                    </div>
                    <div class="preview-breakdown">
                      <div class="pb-row"><i class="ri-checkbox-circle-line"></i> {{ fullSacks }} full × {{ repackSize }}kg → new batch</div>
                      <div class="pb-row remainder" v-if="remainderKg > 0.001">
                        <i class="ri-corner-down-left-line"></i>
                        {{ formatKg(remainderKg) }} remainder → stays on source as short weight
                      </div>
                    </div>
                    <div class="preview-cost-row" v-if="totalConvertedCost > 0">
                      <span>Per sack <strong>{{ formatCurrency(fullSackCost) }}</strong></span>
                      <span>Total <strong>{{ formatCurrency(totalConvertedCost) }}</strong></span>
                    </div>
                  </template>
                </template>
                <div v-else class="preview-hint">
                  <i class="ri-box-3-line"></i> Select a product above to see output
                </div>
              </div>

            </div>
          </div>
        </div>

        <div class="modal-footer">
          <!-- Success state -->
          <template v-if="successResult">
            <button type="button" class="btn btn-cancel" @click="hide">
              <i class="ri-close-line"></i> Done
            </button>
            <button type="button" class="btn btn-save" @click="show">
              <i class="ri-recycle-line"></i> Convert Another
            </button>
          </template>
          <!-- Confirmation state -->
          <template v-else-if="showConfirm">
            <button type="button" class="btn btn-cancel" @click="showConfirm = false">
              <i class="ri-arrow-left-line"></i> Back
            </button>
            <button type="button" class="btn btn-save" @click="confirmSubmit" :disabled="form.processing">
              <i class="ri-check-line"></i>
              <span v-if="form.processing">Converting...</span>
              <span v-else>Confirm &amp; Convert</span>
            </button>
          </template>
          <!-- Normal state -->
          <template v-else>
            <button type="button" class="btn btn-cancel" @click="hide">
              <i class="ri-close-line"></i> Cancel
            </button>
            <button type="button" class="btn btn-save" @click="submit"
              :disabled="form.processing || insufficientKgForOutput">
              <i class="ri-recycle-line"></i> Convert
            </button>
          </template>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script>
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';

export default {
  name: 'ConvertStockModal',
  props: {
    inventoryStock: Object,
    dropdowns:      Object,
  },
  emits: ['saved'],
  data() {
    return {
      form: useForm({
        source_stock_id:  '',
        product_id:       '',
        source_qty_used:  '',
        conversion_ratio: '',
        weight_loss_ids:  [],
        retail_price:     '',
        wholesale_price:  '',
        expiration_date:  '',
        reason:           '',
        remainder_kg:     0,
        unit_cost:        0,
      }),
      showModal:         false,
      saveSuccess:       false,
      showConfirm:       false,
      successResult:     null,
      conversionBasis:   'sacks',
      repackSize:        '',
      selectedLossIds:   [],
      localErrors:       {},
      products:          [],
      brands:            [],
      units:             [],
      productSearch:     '',
      selectedProduct:   null,
      showAddProduct:    false,
      newProduct:        { brand_id: '', weight: '', unit_id: '', code: '' },
      addProductLoading: false,
      addProductErrors:  {},
    };
  },
  computed: {
    filteredProducts() {
      const kw = this.productSearch.trim().toLowerCase();
      if (!kw) return this.products;
      return this.products.filter(p =>
        String(p.name || '').toLowerCase().includes(kw) ||
        String(p.code || '').toLowerCase().includes(kw)
      );
    },
    sourceProductName() {
      return this.inventoryStock?.received_item?.product?.name
          ?? this.inventoryStock?.product?.name
          ?? null;
    },
    weightLosses() {
      return (this.inventoryStock?.weight_losses || []).filter(wl => !wl.converted_at);
    },
    remainingAfterConversion() {
      const qty = this.inventoryStock?.quantity || 0;
      if (this.conversionBasis === 'sacks') {
        return qty - (Number(this.form.source_qty_used) || 0);
      }
      return qty - this.selectedAffectedSacks;
    },
    confirmSummary() {
      if (this.conversionBasis === 'sacks') {
        return {
          sourceQty:   Number(this.form.source_qty_used) || 0,
          outputQty:   this.outputQuantity,
          outputLabel: `${this.selectedProduct?.name || 'product'}`,
        };
      }
      return {
        sourceQty:   this.selectedAffectedSacks,
        outputQty:   this.totalOutputSacks,
        outputLabel: `${this.selectedProduct?.name || 'product'}`,
      };
    },
    selectedTotalKg() {
      // sum of REMAINING weight per group (affected_sacks × actual_kg_per_sack), not the lost amount
      return this.weightLosses
        .filter(wl => this.selectedLossIds.includes(wl.id))
        .reduce((sum, wl) => {
          const remaining = (parseInt(wl.affected_sacks) || 0) * (this.packSize - (parseFloat(wl.loss_per_sack) || 0));
          return sum + remaining;
        }, 0);
    },
    selectedAffectedSacks() {
      return this.weightLosses
        .filter(wl => this.selectedLossIds.includes(wl.id))
        .reduce((sum, wl) => sum + (parseInt(wl.affected_sacks) || 0), 0);
    },
    totalAffectedSacks() {
      return Number(this.inventoryStock?.total_affected_sacks || 0);
    },
    normalSacks() {
      return Math.max(0, (this.inventoryStock?.quantity || 0) - this.totalAffectedSacks);
    },
    totalWeightLoss() {
      return Number(this.inventoryStock?.total_weight_loss || 0);
    },
    packSize() {
      return parseFloat(
        this.inventoryStock?.received_item?.product?.weight ||
        this.inventoryStock?.product?.weight || 0
      );
    },
    grossWeight() {
      return (this.inventoryStock?.quantity || 0) * this.packSize;
    },
    adjustedWeight() {
      return Math.max(0, this.grossWeight - this.totalWeightLoss);
    },
    shortSacksActualWeight() {
      return Math.max(0, this.totalAffectedSacks * this.packSize - this.totalWeightLoss);
    },
    fullSacks() {
      const size = Number(this.repackSize) || 0;
      return size > 0 ? Math.floor(this.selectedTotalKg / size) : 0;
    },
    remainderKg() {
      const size = Number(this.repackSize) || 0;
      return size > 0 ? this.selectedTotalKg % size : 0;
    },
    totalOutputSacks() {
      // Partial sack stays on source batch — only full sacks go to output
      return this.fullSacks;
    },
    unitCost() {
      return parseFloat(this.inventoryStock?.received_item?.unit_cost || 0);
    },
    costPerKg() {
      return this.packSize > 0 ? this.unitCost / this.packSize : 0;
    },
    fullSackCost() {
      return this.costPerKg * Number(this.repackSize || 0);
    },
    remainderSackCost() {
      return this.costPerKg * this.remainderKg;
    },
    totalConvertedCost() {
      return this.costPerKg * this.selectedTotalKg;
    },
    newSackUnitCost() {
      if (this.conversionBasis === 'weight' && Number(this.repackSize) > 0) {
        return this.fullSackCost;
      }
      const ratio = Number(this.form.conversion_ratio) || 1;
      return ratio > 0 ? this.unitCost / ratio : 0;
    },
    retailPriceWarning() {
      const retail    = Number(this.form.retail_price)    || 0;
      const wholesale = Number(this.form.wholesale_price) || 0;
      const cost      = this.newSackUnitCost;
      if (retail > 0 && cost > 0 && retail < cost)
        return `Must be above unit cost (${this.formatCurrency(cost)})`;
      if (retail > 0 && wholesale > 0 && retail <= wholesale)
        return 'Must be higher than wholesale price';
      return null;
    },
    wholesalePriceWarning() {
      const retail    = Number(this.form.retail_price)    || 0;
      const wholesale = Number(this.form.wholesale_price) || 0;
      const cost      = this.newSackUnitCost;
      if (wholesale > 0 && cost > 0 && wholesale < cost)
        return `Must be above unit cost (${this.formatCurrency(cost)})`;
      if (wholesale > 0 && retail > 0 && wholesale >= retail)
        return 'Must be less than retail price';
      return null;
    },
    insufficientKgForOutput() {
      if (this.conversionBasis !== 'weight') return false;
      if (!this.selectedProduct || this.selectedLossIds.length === 0) return false;
      const repack = Number(this.repackSize) || 0;
      return repack > 0 && this.selectedTotalKg > 0 && this.selectedTotalKg < repack;
    },
    outputQuantity() {
      const qty   = Number(this.form.source_qty_used)  || 0;
      const ratio = Number(this.form.conversion_ratio) || 0;
      return qty > 0 && ratio > 0 ? Math.round(qty * ratio) : 0;
    },
  },
  mounted() {
    document.addEventListener('keydown', this._onEscape);
  },
  beforeUnmount() {
    document.removeEventListener('keydown', this._onEscape);
  },
  methods: {
    _onEscape(e) {
      if (e.key === 'Escape' && this.showModal) this.hide();
    },
    show() {
      this.form.reset();
      this.form.clearErrors();
      this.localErrors      = {};
      this.saveSuccess      = false;
      this.showConfirm      = false;
      this.successResult    = null;
      this.conversionBasis  = 'sacks';
      this.repackSize       = '';
      this.selectedLossIds  = [];
      this.productSearch    = '';
      this.selectedProduct  = null;
      this.showAddProduct   = false;
      this.newProduct       = { brand_id: '', weight: '', unit_id: '', code: '' };
      this.addProductErrors = {};
      this.showModal        = true;
      this.form.source_stock_id  = this.inventoryStock?.id ?? '';
      this.form.expiration_date  = this.inventoryStock?.expiration_date ?? '';
      this.fetchDropdowns();
    },
    hide() {
      this.form.reset();
      this.form.clearErrors();
      this.localErrors     = {};
      this.saveSuccess     = false;
      this.showConfirm     = false;
      this.successResult   = null;
      this.selectedProduct = null;
      this.productSearch   = '';
      this.showAddProduct  = false;
      this.showModal       = false;
    },
    setBasis(basis) {
      this.conversionBasis       = basis;
      this.selectedLossIds       = [];
      this.form.source_qty_used  = '';
      this.form.conversion_ratio = '';
      this.form.weight_loss_ids  = [];
      this.form.clearErrors();
      this.localErrors = {};
      // Re-apply repackSize from selected product so remainder_kg stays accurate
      if (this.selectedProduct) {
        const w = parseFloat(this.selectedProduct.weight) || 0;
        this.repackSize = w || '';
        if (basis === 'sacks' && this.packSize > 0 && w > 0) {
          this.form.conversion_ratio = parseFloat((this.packSize / w).toFixed(6));
        }
      } else {
        this.repackSize = '';
      }
    },
    formatKg(val) {
      return parseFloat(val || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' kg';
    },
    formatCurrency(val) {
      return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(val || 0);
    },
    clearError(field) {
      this.form.errors[field] = null;
    },
    async fetchDropdowns() {
      const [prodRes, brandRes, unitRes] = await Promise.all([
        axios.get('/libraries/products', { params: { option: 'lists', count: 500 } }),
        axios.get('/libraries/brands',   { params: { option: 'lists', count: 500 } }),
        axios.get('/libraries/units',    { params: { option: 'lists', count: 500 } }),
      ]);
      this.products = prodRes.data.data  || [];
      this.brands   = brandRes.data.data || [];
      this.units    = unitRes.data.data  || [];
    },
    selectProduct(p) {
      this.form.product_id = p.id;
      this.selectedProduct  = p;
      this.productSearch    = '';
      const targetWeight = parseFloat(p.weight) || 0;
      this.repackSize = targetWeight || '';
      if (this.packSize > 0 && targetWeight > 0) {
        this.form.conversion_ratio = parseFloat((this.packSize / targetWeight).toFixed(6));
      }
    },
    clearProduct() {
      this.form.product_id       = '';
      this.selectedProduct        = null;
      this.productSearch          = '';
      this.repackSize             = '';
      this.form.conversion_ratio  = '';
    },
    async saveNewProduct() {
      this.addProductErrors = {};
      if (!this.newProduct.brand_id) {
        this.addProductErrors.brand_id = 'Brand is required.';
        return;
      }
      if (!this.newProduct.weight || Number(this.newProduct.weight) <= 0) {
        this.addProductErrors.weight = 'Weight is required.';
        return;
      }
      if (!this.newProduct.unit_id) {
        this.addProductErrors.unit_id = 'Unit is required.';
        return;
      }
      this.addProductLoading = true;
      try {
        let code = (this.newProduct.code || '').trim();
        if (!code) {
          const codeRes = await axios.get('/libraries/products', { params: { option: 'next-code' } });
          code = codeRes.data.code;
        }
        const res = await axios.post('/libraries/products', {
          brand_id: this.newProduct.brand_id,
          weight:   this.newProduct.weight,
          unit_id:  this.newProduct.unit_id,
          code,
        }, { headers: { Accept: 'application/json' } });
        await this.fetchDropdowns();
        this.selectProduct(res.data.data);
        this.showAddProduct = false;
      } catch (err) {
        const data = err.response?.data;
        if (data?.errors) this.addProductErrors = data.errors;
        else this.addProductErrors.brand_id = data?.message || 'Failed to save product.';
      } finally {
        this.addProductLoading = false;
      }
    },
    submit() {
      this.localErrors = {};

      if (!this.form.product_id) {
        this.localErrors.product_id = 'Please select a product to convert to.';
        return;
      }

      if (this.conversionBasis === 'weight') {
        if (this.selectedLossIds.length === 0) {
          this.localErrors.source_qty_used = 'Please select at least one short weight group.';
          return;
        }
        if (!this.repackSize || Number(this.repackSize) <= 0) {
          this.localErrors.conversion_ratio = 'Please enter a valid repack size (kg).';
          return;
        }
        if (this.insufficientKgForOutput) {
          this.localErrors.source_qty_used = `Not enough kg: ${this.formatKg(this.selectedTotalKg)} selected but each output sack needs ${this.repackSize} kg. Select a product ≤ ${this.formatKg(this.selectedTotalKg)}.`;
          return;
        }
        const selectedSacks = this.selectedAffectedSacks || 1;
        const outputSacks   = this.totalOutputSacks || 1;
        this.form.source_qty_used  = selectedSacks;
        this.form.conversion_ratio = parseFloat((outputSacks / selectedSacks).toFixed(6));
        this.form.weight_loss_ids  = [...this.selectedLossIds];
        this.form.remainder_kg = this.remainderKg > 0.001 ? parseFloat(this.remainderKg.toFixed(4)) : 0;
      } else {
        if (!this.form.source_qty_used || Number(this.form.source_qty_used) < 1) {
          this.localErrors.source_qty_used = 'Please enter a quantity of at least 1.';
          return;
        }
        if (!this.form.conversion_ratio || Number(this.form.conversion_ratio) <= 0) {
          this.localErrors.conversion_ratio = 'Please enter a valid conversion ratio.';
          return;
        }
      }

      // Attach computed unit cost for the output batch
      this.form.unit_cost = parseFloat(this.newSackUnitCost.toFixed(4)) || 0;

      // Show confirmation before posting
      this.showConfirm = true;
    },
    confirmSubmit() {
      this.showConfirm = false;
      this.form.post('/inventory-stocks/conversions', {
        preserveScroll: true,
        onSuccess: () => {
          this.successResult = {
            sourceBatch:  this.inventoryStock?.batch_code,
            sourceQty:    this.confirmSummary.sourceQty,
            outputQty:    this.confirmSummary.outputQty,
            outputLabel:  this.confirmSummary.outputLabel,
            remaining:    this.remainingAfterConversion,
          };
          this.$emit('saved');
        },
        onError: (errors) => {
          this.localErrors = { ...errors };
        },
      });
    },
  },
};
</script>

<style scoped>
/* ── Modal width ── */
.convert-modal-container { max-width: 1060px; width: 100%; }

/* ── Two-column layout ── */
.convert-body { padding: 0 !important; }
.convert-layout {
  display: grid;
  grid-template-columns: 1fr 340px;
  min-height: 100%;
}
.convert-form-col {
  padding: 1.35rem 1.5rem 1.5rem;
  border-right: 1px solid #e4eeea;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
}
.convert-data-col {
  background: #f7fbf9;
  padding: 1.35rem 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
  align-self: start;
  position: sticky;
  top: 0;
}

/* ── Section labels (left col) ── */
.cf-label {
  font-size: 0.67rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: #6b8c85;
  border-top: 1px solid #e4eeea;
  padding-top: 0.85rem;
  margin: 1rem 0 0.7rem;
}
.cf-label:first-child { border-top: none; padding-top: 0; margin-top: 0; }
.price-cost-hint { font-size: 0.72rem; font-weight: 400; color: #6b8c85; margin-left: 0.5rem; text-transform: none; letter-spacing: 0; }
.price-warning { display: flex; align-items: center; gap: 0.25rem; font-size: 0.72rem; color: #d97706; margin-top: 0.25rem; }
.price-warning i { font-size: 0.85rem; }
.optional { font-size: 0.72rem; color: #9ab8af; font-weight: 400; margin-left: 0.3rem; }

/* ── Dots ── */
.sack-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; display: inline-block; }
.normal-dot { background: #16a34a; }
.short-dot  { background: #dc2626; }

/* ── Basis toggle ── */
.basis-toggle-group { margin-bottom: 1rem; }
.basis-toggle-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #6b8c85; margin-bottom: 0.4rem; }
.basis-toggle { display: flex; gap: 0.5rem; }
.basis-btn {
  flex: 1; display: flex; align-items: center; justify-content: center; gap: 0.4rem;
  padding: 0.55rem 0.75rem; border: 1.5px solid #dee6e3; border-radius: 8px;
  background: #f6fbf9; font-size: 0.78rem; font-weight: 600; color: #64748b;
  cursor: pointer; transition: all 0.15s;
}
.basis-btn:hover { border-color: #3d8d7a; color: #3d8d7a; }
.basis-btn.active { background: #3d8d7a; border-color: #3d8d7a; color: #fff; }
.basis-btn.active .sack-dot { border: 1.5px solid rgba(255,255,255,.5); }
.basis-count { background: rgba(0,0,0,.08); border-radius: 20px; padding: 0 6px; font-size: 0.68rem; font-weight: 700; }
.basis-btn.active .basis-count { background: rgba(255,255,255,.25); }

/* ── Short weight panel ── */
.sw-panel { background: #fef9f9; border: 1px solid #fecaca; border-radius: 8px; margin-bottom: 1rem; overflow: hidden; }
.sw-panel-header { display: flex; justify-content: space-between; align-items: center; padding: 0.55rem 0.85rem; font-size: 0.72rem; color: #64748b; border-bottom: 1px solid #fecaca; background: #fff5f5; }
.sw-selected-total { font-size: 0.76rem; color: #b91c1c; }
.sw-loss-list { display: flex; flex-direction: column; max-height: 200px; overflow-y: auto; }
.sw-loss-row { display: flex; align-items: center; gap: 0.4rem; padding: 0.45rem 0.85rem; font-size: 0.78rem; color: #475569; cursor: pointer; border-bottom: 1px solid #fef2f2; transition: background 0.1s; }
.sw-loss-row:last-child { border-bottom: none; }
.sw-loss-row:hover { background: #fef2f2; }
.sw-loss-row.selected { background: #fee2e2; }
.sw-check { accent-color: #dc2626; cursor: pointer; flex-shrink: 0; }
.sw-sacks { font-weight: 700; color: #b91c1c; min-width: 52px; }
.sw-op { color: #cbd5e1; font-size: 0.7rem; }
.sw-per { color: #64748b; }
.sw-kg { font-weight: 700; color: #dc2626; min-width: 62px; }
.sw-reason { font-size: 0.7rem; color: #94a3b8; font-style: italic; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* ── Product selector ── */
.product-selector-group { margin-bottom: 0.75rem; }
.product-search-row { display: flex; gap: 0.5rem; align-items: center; }
.btn-add-product {
  flex-shrink: 0; width: 38px; height: 38px;
  display: flex; align-items: center; justify-content: center;
  border: 1.5px solid #dee6e3; border-radius: 8px; background: #f6fbf9;
  color: #3d8d7a; font-size: 1.15rem; cursor: pointer; transition: all 0.15s;
}
.btn-add-product:hover { border-color: #3d8d7a; background: #e6f4f1; }
.btn-add-product.active { background: #3d8d7a; border-color: #3d8d7a; color: #fff; }
.selected-product-chip { display: flex; align-items: center; gap: 0.5rem; background: #e6f4f1; border: 1.5px solid #3d8d7a; border-radius: 8px; padding: 0.5rem 0.75rem; font-size: 0.85rem; color: #16322e; }
.selected-product-chip i { color: #3d8d7a; font-size: 1rem; flex-shrink: 0; }
.selected-product-name { font-weight: 600; flex: 1; }
.chip-clear-btn { background: none; border: none; padding: 0; cursor: pointer; color: #6b8c85; font-size: 1rem; line-height: 1; display: flex; align-items: center; }
.chip-clear-btn:hover { color: #b91c1c; }
.product-dropdown { border: 1px solid #dee6e3; border-radius: 8px; margin-top: 3px; overflow: hidden; max-height: 220px; overflow-y: auto; background: #fff; box-shadow: 0 4px 12px rgba(0,0,0,.08); }
.product-dropdown-empty { padding: 0.7rem 0.9rem; font-size: 0.79rem; color: #94a3b8; }
.product-dropdown-item { display: flex; align-items: center; justify-content: space-between; width: 100%; padding: 0.55rem 0.9rem; background: none; border: none; border-bottom: 1px solid #f1f5f9; text-align: left; cursor: pointer; transition: background 0.1s; }
.product-dropdown-item:last-child { border-bottom: none; }
.product-dropdown-item:hover { background: #f0fdf4; }
.pdi-name { font-size: 0.83rem; font-weight: 600; color: #1e293b; }
.pdi-code { font-size: 0.72rem; color: #94a3b8; font-family: 'Courier New', monospace; }
.add-product-panel { background: #f8fffe; border: 1.5px solid #a7d9cf; border-radius: 10px; padding: 0.85rem 1rem; margin-bottom: 0.85rem; }
.add-product-header { display: flex; align-items: center; gap: 0.4rem; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #3d8d7a; margin-bottom: 0.7rem; }
.add-product-header i { font-size: 0.95rem; }

/* ── Right: Data cards ── */
.data-card {
  background: #fff;
  border: 1px solid #ddeee8;
  border-radius: 10px;
  padding: 1rem 1.1rem;
}
.data-card-label {
  font-size: 0.65rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.07em; color: #6b8c85;
  display: flex; align-items: center; gap: 0.35rem;
  margin-bottom: 0.6rem;
}
.data-batch-code { font-size: 1rem; font-weight: 700; color: #0c4a6e; font-family: 'Courier New', monospace; letter-spacing: 0.03em; }
.data-product-name { font-size: 0.88rem; font-weight: 600; color: #16322e; margin-top: 2px; }
.data-qty-row { display: flex; align-items: center; gap: 0.35rem; font-size: 0.78rem; color: #5a7a73; margin-top: 0.35rem; }
.data-divider { border-top: 1px solid #e4eeea; margin: 0.65rem 0; }
.data-sack-row { display: flex; gap: 1rem; margin-bottom: 0.3rem; }
.data-sack-item { display: flex; align-items: center; gap: 0.35rem; font-size: 0.78rem; color: #5a7a73; }
.data-sack-item.short { color: #b91c1c; }
.data-weight-row { font-size: 0.78rem; color: #16322e; font-weight: 600; }
.data-loss { font-size: 0.72rem; color: #dc2626; font-weight: 400; margin-left: 0.25rem; }

/* ── Right: Preview card ── */
.preview-card { border-color: #bbf7d0; }
.preview-flow {
  display: flex; align-items: center; justify-content: space-around;
  gap: 0.5rem; margin: 0.5rem 0 0.75rem;
}
.preview-box {
  flex: 1; text-align: center;
  background: #f8fbfa; border-radius: 8px;
  padding: 0.65rem 0.5rem;
}
.source-box { border: 1px solid #bae6fd; background: #f0f9ff; }
.target-box { border: 1px solid #bbf7d0; background: #f0fdf4; }
.preview-num { font-size: 1.5rem; font-weight: 800; color: #16322e; line-height: 1; }
.preview-unit-label { font-size: 0.7rem; color: #6b8c85; margin-top: 2px; }
.preview-kg-label { font-size: 0.72rem; color: #3d8d7a; font-weight: 600; margin-top: 2px; }
.preview-arrow { font-size: 1.35rem; color: #a7d9cf; flex-shrink: 0; }
.preview-cost-row { display: flex; justify-content: space-between; font-size: 0.76rem; color: #475569; border-top: 1px solid #e4eeea; padding-top: 0.5rem; margin-top: 0.25rem; }
.preview-cost-row strong { color: #15803d; }
.preview-breakdown { font-size: 0.75rem; color: #475569; margin-bottom: 0.5rem; display: flex; flex-direction: column; gap: 0.2rem; }
.pb-row { display: flex; align-items: center; gap: 0.3rem; }
.pb-row i { font-size: 0.85rem; color: #16a34a; }
.pb-row.remainder i { color: #d97706; }
.pb-row.remainder { color: #d97706; }
.preview-hint { font-size: 0.78rem; color: #94a3b8; display: flex; align-items: center; gap: 0.4rem; padding: 0.25rem 0; }

/* ── Insufficient kg warning ── */
.preview-card-warn { border-color: #fca5a5; }
.insufficient-kg-warn {
  display: flex; align-items: flex-start; gap: 0.6rem;
  background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px;
  padding: 0.7rem 0.85rem; font-size: 0.8rem; color: #b91c1c; line-height: 1.5;
}
.insufficient-kg-warn i { font-size: 1.1rem; flex-shrink: 0; margin-top: 1px; }
.insufficient-kg-warn strong { color: #991b1b; }

/* ── Remaining after conversion (right panel) ── */
.data-remaining-row {
  display: flex; align-items: center; gap: 0.35rem;
  font-size: 0.78rem; color: #16a34a; font-weight: 600;
}
.data-remaining-row i { font-size: 0.85rem; }
.data-remaining-zero { color: #dc2626; }

/* ── Max button (qty label) ── */
.max-qty-btn {
  margin-left: 0.45rem;
  padding: 1px 7px;
  font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
  background: #e6f4f1; border: 1px solid #a7d9cf; border-radius: 4px;
  color: #3d8d7a; cursor: pointer; vertical-align: middle; line-height: 1.5;
  transition: all 0.12s;
}
.max-qty-btn:hover { background: #3d8d7a; border-color: #3d8d7a; color: #fff; }

/* ── Confirmation panel ── */
.confirm-panel {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; text-align: center;
  padding: 2.5rem 2rem; flex: 1;
}
.confirm-icon {
  font-size: 2.75rem; color: #d97706;
  background: #fffbeb; border: 2px solid #fde68a;
  border-radius: 50%; width: 68px; height: 68px;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 1.1rem;
}
.confirm-title { font-size: 1.1rem; font-weight: 700; color: #16322e; margin-bottom: 0.65rem; }
.confirm-desc { font-size: 0.88rem; color: #475569; line-height: 1.6; max-width: 320px; margin-bottom: 1.1rem; }
.confirm-desc strong { color: #16322e; }
.confirm-remaining {
  display: flex; align-items: center; gap: 0.4rem;
  font-size: 0.8rem; font-weight: 600; color: #16a34a;
  background: #f0fdf4; border: 1px solid #bbf7d0;
  border-radius: 8px; padding: 0.5rem 0.85rem;
}
.confirm-remaining i { font-size: 0.95rem; }
.confirm-remaining.zero { color: #dc2626; background: #fef2f2; border-color: #fecaca; }

/* ── Success panel ── */
.success-panel {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; text-align: center;
  padding: 2.5rem 2rem; flex: 1;
}
.success-icon {
  font-size: 2.75rem; color: #16a34a;
  background: #f0fdf4; border: 2px solid #bbf7d0;
  border-radius: 50%; width: 68px; height: 68px;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 1rem;
}
.success-title { font-size: 1.1rem; font-weight: 700; color: #16322e; margin-bottom: 1.1rem; }
.success-flow {
  display: flex; align-items: center; gap: 0.75rem;
  margin-bottom: 1.1rem;
}
.success-flow-item {
  background: #f8fbfa; border-radius: 10px;
  padding: 0.75rem 1.1rem; min-width: 90px;
}
.success-flow-item.source { border: 1.5px solid #bae6fd; background: #f0f9ff; }
.success-flow-item.target { border: 1.5px solid #bbf7d0; background: #f0fdf4; }
.sflow-num { font-size: 1.6rem; font-weight: 800; color: #16322e; line-height: 1; }
.sflow-label { font-size: 0.7rem; color: #6b8c85; margin-top: 2px; }
.sflow-batch { font-size: 0.72rem; color: #3d8d7a; font-weight: 600; margin-top: 3px; }
.success-flow-arrow { font-size: 1.4rem; color: #a7d9cf; }
.success-remaining {
  display: flex; align-items: center; gap: 0.4rem;
  font-size: 0.78rem; color: #475569;
  background: #f8fbfa; border: 1px solid #e4eeea;
  border-radius: 8px; padding: 0.45rem 0.8rem;
}
.success-remaining i { font-size: 0.9rem; color: #6b8c85; }
</style>
