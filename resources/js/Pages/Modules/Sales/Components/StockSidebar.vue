<template>
    <div class="stock-section mb-3">
        <button type="button" class="stock-toggle-btn mb-3" @click="togglePanel">
            <span class="stock-toggle-label">
                <i class="ri-line-chart-line"></i>
                Stock Availability
            </span>
            <i class="ri-arrow-down-s-line stock-toggle-arrow" :class="{ 'is-open': showStock }"></i>
        </button>

        <b-collapse v-model="showStock" class="stock-floating-panel">
            <div class="stock-dashboard-card quick-stats-sidebar">
                <div class="card-header-modern">
                    <h4 class="mb-0"><i class="ri-stack-line me-2"></i> Current Inventory Snapshot</h4>
                </div>
                <div class="card-body-custom">
                    <div class="stat-card mb-3">
                        <div class="stat-icon stat-icon-success"><i class="ri-scales-3-line"></i></div>
                        <div class="stat-info">
                            <span class="stat-label">Total KG Left</span>
                            <div class="stat-value-wrapper"><span class="stat-value">{{ stock.total_kg_left || 0 }} kg</span></div>
                        </div>
                    </div>
                    <div class="stat-card mb-3">
                        <div class="stat-icon stat-icon-info"><i class="ri-archive-line"></i></div>
                        <div class="stat-info">
                            <span class="stat-label">5kg Sacks Left</span>
                            <div class="stat-value-wrapper"><span class="stat-value">{{ stock.five_kg_sacks_left || 0 }}</span></div>
                        </div>
                    </div>
                    <div class="stat-card mb-3">
                        <div class="stat-icon stat-icon-warning"><i class="ri-box-3-line"></i></div>
                        <div class="stat-info">
                            <span class="stat-label">10kg Sacks Left</span>
                            <div class="stat-value-wrapper"><span class="stat-value">{{ stock.ten_kg_sacks_left || 0 }}</span></div>
                        </div>
                    </div>
                    <div class="stat-card mb-3">
                        <div class="stat-icon stat-icon-primary"><i class="ri-archive-stack-line"></i></div>
                        <div class="stat-info">
                            <span class="stat-label">25kg Sacks Left</span>
                            <div class="stat-value-wrapper"><span class="stat-value">{{ stock.twenty_five_kg_sacks_left || 0 }}</span></div>
                        </div>
                    </div>

                    <div v-if="stock.products && stock.products.length > 0" class="stock-products mt-3">
                        <h6 class="stock-products-title">Product Details by Brand</h6>
                        <div v-for="(brandGroup, brandIndex) in groupedProducts" :key="brandIndex" class="stat-card mb-2 stock-brand-item">
                            <div class="stat-info w-100">
                                <div class="stock-brand-header">
                                    <h6><i class="ri-building-line me-2"></i>{{ brandGroup.brand || 'No Brand' }}</h6>
                                    <span class="stock-brand-count">{{ brandGroup.products.length }} items</span>
                                </div>
                                <div class="stock-product-list">
                                    <div v-for="product in brandGroup.products" :key="product.product_name" class="stock-product-item">
                                        <span class="stock-product-name">{{ product.product_name }}</span>
                                        <span class="stock-product-meta">{{ product.total_quantity }} x {{ product.pack_size }} {{ product.unit }} ({{ product.total_kg }} kg)</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </b-collapse>
    </div>
</template>

<script>
export default {
    data() {
        return {
            stock: {
                products: [],
            },
            showStock: false,
        };
    },
    computed: {
        groupedProducts() {
            if (!this.stock.products || this.stock.products.length === 0) {
                return [];
            }

            const grouped = {};
            this.stock.products.forEach((product) => {
                const brand = product.brand_name || 'No Brand';
                if (!grouped[brand]) {
                    grouped[brand] = {
                        brand,
                        products: [],
                    };
                }
                grouped[brand].products.push(product);
            });

            return Object.values(grouped);
        },
    },
    created() {
        this.fetchStock();
    },
    methods: {
        togglePanel() {
            this.showStock = !this.showStock;
            this.$emit('toggle', this.showStock);
        },
        closePanel() {
            if (!this.showStock) return;
            this.showStock = false;
            this.$emit('toggle', false);
        },
        fetchStock() {
            axios
                .get('/sales-orders', {
                    params: {
                        option: 'stock',
                    },
                })
                .then((response) => {
                    if (response) {
                        this.stock = response.data;
                    }
                })
                .catch((err) => console.log(err));
        },
    },
};
</script>

<style scoped>
.stock-toggle-btn {
    position: static;
    width: 100%;
    border: 1px solid #d5e4df;
    border-radius: 12px;
    background: linear-gradient(135deg, #f9fcfb 0%, #eef6f3 100%);
    padding: 0.7rem 1rem;
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    gap: 0;
    color: #267a4c;
    font-weight: 600;
    transition: all 0.25s ease;
    box-shadow: none;
}

.stock-toggle-btn:hover {
    background: #3d8d7a;
    color: #fff;
    border-color: #3d8d7a;
}

.stock-toggle-label {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    writing-mode: initial;
    text-orientation: mixed;
    transform: none;
    letter-spacing: 0;
    font-size: inherit;
    white-space: normal;
}

.stock-toggle-label i {
    font-size: 1rem;
    transform: none;
}

.stock-toggle-arrow {
    font-size: 1rem;
    transition: transform 0.25s ease;
}

.stock-toggle-arrow.is-open {
    transform: rotate(180deg);
}

.stock-dashboard-card {
    border: 1px solid #dce7e2;
    border-radius: 14px;
    background: #ffffff;
    padding: 1rem;
    box-shadow: 0 8px 24px rgba(61, 141, 122, 0.08);
}

.stock-floating-panel {
    position: static;
    width: 100%;
    max-height: none;
    overflow: visible;
    z-index: auto;
}

.stock-dashboard-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
    border-bottom: 1px solid #edf2f0;
    padding-bottom: 1rem;
}

.stock-header-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.stock-header-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: rgba(61, 141, 122, 0.14);
    color: #267a4c;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

.stock-header-title h6 {
    color: #267a4c;
    font-weight: 700;
}

.stock-header-title p {
    color: #6c757d;
    font-size: 0.85rem;
}

.stock-header-pill {
    background: rgba(61, 141, 122, 0.12);
    color: #267a4c;
    border-radius: 20px;
    padding: 0.35rem 0.7rem;
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
}

.stock-metric-card {
    background: #f8fbfa;
    border: 1px solid #e5efeb;
    border-radius: 12px;
    padding: 0.85rem;
    height: 100%;
    transition: all 0.25s ease;
}

.stock-metric-card:hover {
    transform: translateY(-2px);
    border-color: #cde0d9;
    box-shadow: 0 8px 18px rgba(44, 122, 93, 0.12);
}

.stock-metric-icon {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.55rem;
}

.stock-metric-icon.total {
    background: rgba(38, 122, 76, 0.16);
    color: #267a4c;
}

.stock-metric-icon.five {
    background: rgba(25, 135, 84, 0.14);
    color: #198754;
}

.stock-metric-icon.ten {
    background: rgba(13, 110, 253, 0.14);
    color: #0d6efd;
}

.stock-metric-icon.twenty-five {
    background: rgba(255, 193, 7, 0.22);
    color: #9a6b00;
}

.stock-metric-label {
    color: #6c757d;
    font-size: 0.78rem;
    margin-bottom: 0.2rem;
}

.stock-metric-value {
    color: #2b3459;
    font-weight: 700;
    margin-bottom: 0;
}

.stock-products-title {
    font-size: 0.9rem;
    color: #267a4c;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 700;
    margin-bottom: 0.75rem;
}

.stock-brand-card {
    border: 1px solid #e5efeb;
    border-radius: 12px;
    background: #fbfdfc;
    padding: 0.85rem;
    height: 100%;
}

.stock-brand-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.65rem;
}

.stock-brand-header h6 {
    color: #267a4c;
    font-weight: 600;
    margin: 0;
    font-size: 0.9rem;
}

.stock-brand-count {
    font-size: 0.7rem;
    padding: 0.25rem 0.6rem;
    border-radius: 12px;
    background: #eaf4f0;
    color: #267a4c;
    font-weight: 600;
}

.stock-product-list {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.stock-product-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    border: 1px solid #edf2f0;
    background: #ffffff;
    border-radius: 10px;
    padding: 0.5rem 0.65rem;
}

.stock-product-name {
    color: #2b3459;
    font-size: 0.82rem;
    font-weight: 500;
}

.stock-product-meta {
    color: #5d6472;
    background: #f4f7f6;
    border-radius: 10px;
    padding: 0.25rem 0.45rem;
    font-size: 0.72rem;
    white-space: nowrap;
}

@media (max-width: 768px) {
    .stock-toggle-btn {
        position: static;
        transform: none;
        width: 100%;
        border-right: 1px solid #d5e4df;
        border-radius: 12px;
        background: linear-gradient(135deg, #f9fcfb 0%, #eef6f3 100%);
        padding: 0.7rem 1rem;
        display: flex;
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        gap: 0;
        color: #267a4c;
        box-shadow: none;
    }

    .stock-floating-panel {
        position: static;
        width: 100%;
        max-height: none;
        overflow: visible;
    }

    .stock-toggle-label {
        writing-mode: initial;
        text-orientation: mixed;
        transform: none;
        letter-spacing: 0;
        font-size: inherit;
        white-space: normal;
        gap: 0.5rem;
    }

    .stock-toggle-label i {
        transform: none;
        font-size: 1rem;
    }

    .stock-dashboard-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .stock-product-item {
        flex-direction: column;
        align-items: flex-start;
    }

    .stock-product-meta {
        white-space: normal;
    }
}

/* Match QuickStats layout */
.quick-stats-sidebar {
    background: #fff;
    border-radius: 20px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
}

.card-header-modern {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #e2e8f0;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
}

.card-header-modern h4 {
    font-size: 1rem;
    font-weight: 600;
    color: #1e293b;
    display: flex;
    align-items: center;
}

.card-header-modern h4 i {
    color: #2e8b57;
}

.card-body-custom {
    padding: 1rem;
}

.stat-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 1rem 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.stat-icon-primary { background: linear-gradient(135deg, #e5f0ff 0%, #dbeafe 100%); color: #3b82f6; }
.stat-icon-success { background: linear-gradient(135deg, #e6f9ed 0%, #d1fae5 100%); color: #10b981; }
.stat-icon-warning { background: linear-gradient(135deg, #fff0e5 0%, #ffedd5 100%); color: #f97316; }
.stat-icon-info { background: linear-gradient(135deg, #e5f0ff 0%, #dbeafe 100%); color: #3b82f6; }

.stat-info { flex: 1; }
.stat-label { color: #64748b; font-size: 0.8rem; font-weight: 500; display: block; margin-bottom: 0.25rem; }
.stat-value { font-size: 1.2rem; font-weight: 700; color: #1e293b; }

.stock-brand-item {
    padding: 0.8rem 1rem;
    display: block;
}
</style>
