<template>
    <div class="dashboard-content">
        <div class="health-cards">
            <div v-for="stat in inventoryStats" :key="stat.label" class="health-card" :class="stat.cardClass">
                <div class="health-icon" :style="{ background: stat.iconBg }">
                    <i :class="stat.icon"></i>
                </div>
                <div class="health-info">
                    <span class="health-label">{{ stat.label }}</span>
                    <div class="health-value-wrapper">
                        <span class="health-value">{{ stat.value }}</span>
                        <span class="health-unit">{{ stat.unit }}</span>
                    </div>
                    <span class="health-trend" :class="stat.trendClass">
                        <i :class="stat.trendIcon"></i> {{ stat.trend }} from last month
                    </span>
                </div>
            </div>
        </div>

        <div class="charts-row">
            <div class="chart-card large">
                <div class="card-header-modern">
                    <h3>Stock Distribution by Category</h3>
                </div>
                <div class="chart-body">
                    <apexchart
                        v-if="stockChart.series.length"
                        type="bar"
                        height="300"
                        :options="stockChart.options"
                        :series="stockChart.series"
                    />
                </div>
            </div>

            <div class="chart-card">
                <div class="card-header-modern">
                    <h3>Stock Health</h3>
                </div>
                <div class="chart-body">
                    <apexchart
                        v-if="healthChart.series.length"
                        type="radialBar"
                        height="280"
                        :options="healthChart.options"
                        :series="healthChart.series"
                    />

                    <div class="health-legend">
                        <div class="legend-item" v-for="(item, i) in healthData" :key="i">
                            <span class="color-dot" :style="{ background: item.color }"></span>
                            <span>{{ item.label }}</span>
                            <span class="ms-auto fw-semibold">{{ item.value }}%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modern-card">
            <div class="card-header-modern">
                <div class="d-flex align-items-center gap-3">
                    <h3>Low Stock Alert</h3>
                    <span class="alert-badge">{{ lowStockItems.length }} items need attention</span>
                </div>
                <div class="d-flex gap-2">
                    <input type="text" class="search-input" placeholder="Search products...">
                    <button class="btn-outline-modern">Filter</button>
                </div>
            </div>
            <div class="alert-grid">
                <div v-for="item in lowStockItems" :key="item.id" class="alert-item-card" :class="getAlertClass(item)">
                    <div class="alert-item-header">
                        <span class="product-code">{{ item.code }}</span>
                        <span class="stock-badge" :class="getStockBadge(item)">{{ getStockStatus(item) }}</span>
                    </div>
                    <h4 class="product-name">{{ item.name }}</h4>
                    <div class="product-category">{{ item.category }}</div>
                    <div class="stock-levels">
                        <div class="stock-info">
                            <span class="label">Current</span>
                            <span class="value current">{{ item.current_stock }}</span>
                        </div>
                        <div class="stock-info">
                            <span class="label">Minimum</span>
                            <span class="value min">{{ item.minimum_stock }}</span>
                        </div>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" :style="{ width: getStockPercentage(item) + '%' }"></div>
                    </div>
                    <button class="reorder-btn">
                        <i class="bx bx-cart me-2"></i>Reorder Now
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';

export default {
    name: 'InventoryDashboard',
    components: {
        apexchart: VueApexCharts
    },
    props: {
        inventoryStats: {
            type: Array,
            default: () => []
        },
        stockChart: {
            type: Object,
            default: () => ({ series: [], options: {} })
        },
        healthChart: {
            type: Object,
            default: () => ({ series: [], options: {} })
        },
        healthData: {
            type: Array,
            default: () => []
        },
        lowStockItems: {
            type: Array,
            default: () => []
        }
    },
    methods: {
        getStockStatus(item) {
            if (item.current_stock <= 0) return 'Out';
            if (item.current_stock <= item.minimum_stock * 0.5) return 'Critical';
            if (item.current_stock <= item.minimum_stock) return 'Low';
            return 'Good';
        },
        getStockBadge(item) {
            if (item.current_stock <= 0) return 'badge-out';
            if (item.current_stock <= item.minimum_stock * 0.5) return 'badge-critical';
            if (item.current_stock <= item.minimum_stock) return 'badge-low';
            return 'badge-good';
        },
        getAlertClass(item) {
            if (item.current_stock <= 0) return 'alert-out';
            if (item.current_stock <= item.minimum_stock * 0.5) return 'alert-critical';
            return 'alert-low';
        },
        getStockPercentage(item) {
            return Math.min((item.current_stock / item.minimum_stock) * 100, 100);
        }
    }
};
</script>

<style scoped>
.health-cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.health-card {
    background: white;
    padding: 1.5rem;
    border-radius: 20px;
    display: flex;
    align-items: center;
    gap: 1rem;
    border-left: 4px solid;
}

.health-blue { border-color: #3b82f6; }
.health-orange { border-color: #f97316; }
.health-red { border-color: #ef4444; }
.health-green { border-color: #10b981; }

.health-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.health-info {
    flex: 1;
}

.health-label {
    color: #64748b;
    font-size: 0.875rem;
    display: block;
    margin-bottom: 0.25rem;
}

.health-value-wrapper {
    display: flex;
    align-items: baseline;
    gap: 0.25rem;
    margin-bottom: 0.25rem;
}

.health-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
}

.health-unit {
    color: #64748b;
    font-size: 0.875rem;
}

.health-trend {
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.trend-up { color: #10b981; background: #e6f9ed; }
.trend-down { color: #ef4444; background: #fee2e2; }
.trend-neutral { color: #6b7280; background: #f3f4f6; }

.charts-row {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.chart-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
}

.chart-card.large {
    grid-column: span 1;
}

.card-header-modern {
    padding: 1.5rem;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header-modern h3 {
    font-size: 1.1rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
}

.chart-body {
    padding: 1rem;
}

.modern-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
    margin-bottom: 1.5rem;
}

.alert-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
    padding: 1.5rem;
}

.alert-item-card {
    background: #f8fafc;
    padding: 1.5rem;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
}

.alert-item-card.alert-critical { border-left: 4px solid #ef4444; }
.alert-item-card.alert-low { border-left: 4px solid #f97316; }
.alert-item-card.alert-out { border-left: 4px solid #6b7280; }

.alert-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.product-code {
    font-size: 0.875rem;
    color: #64748b;
}

.stock-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
}

.badge-critical { background: #fee2e2; color: #ef4444; }
.badge-low { background: #fff3e0; color: #f97316; }
.badge-out { background: #f3f4f6; color: #6b7280; }

.product-name {
    font-size: 1.1rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.25rem;
}

.product-category {
    color: #64748b;
    font-size: 0.875rem;
    margin-bottom: 1rem;
}

.stock-levels {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 0.75rem;
}

.stock-info {
    display: flex;
    flex-direction: column;
}

.stock-info .label {
    font-size: 0.75rem;
    color: #64748b;
}

.stock-info .value {
    font-size: 1.1rem;
    font-weight: 600;
}

.stock-info .value.current { color: #1e293b; }
.stock-info .value.min { color: #64748b; }

.progress-bar {
    height: 6px;
    background: #e2e8f0;
    border-radius: 3px;
    margin: 1rem 0;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: #3b82f6;
    border-radius: 3px;
}

.reorder-btn {
    width: 100%;
    padding: 0.75rem;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    color: #3b82f6;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.reorder-btn:hover {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
}

.alert-badge {
    background: #fee2e2;
    color: #ef4444;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
}

.health-legend {
    padding: 1rem;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0;
}

.color-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}

@media (max-width: 1200px) {
    .health-cards {
        grid-template-columns: repeat(2, 1fr);
    }

    .charts-row {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .health-cards {
        grid-template-columns: 1fr;
    }
}
</style>
