<template>
    <div class="dashboard-content">
        <div class="stats-grid">
            <div v-for="stat in salesStats" :key="stat.label" class="stat-card">
                <div class="stat-icon" :style="{ background: stat.iconBg }">
                    <i :class="stat.icon" :style="{ color: stat.iconColor }"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-label">{{ stat.label }}</span>
                    <div class="stat-value-wrapper">
                        <span class="stat-value">{{ stat.showCurrency ? '&#8369;' : '' }}{{ formatNumber(stat.value) }}</span>
                        <span class="stat-trend" :class="stat.trendClass">
                            <i :class="stat.trendIcon"></i> {{ stat.trend }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="modern-card">
            <div class="card-header-modern">
                <div>
                    <h3>Top Selling Products</h3>
                    <p class="text-muted-600">Best performing products this month</p>
                </div>
            </div>
            <div class="top-products-grid" v-if="topProducts.length > 0">
                <div v-for="(product, index) in topProducts" :key="product.id" class="top-product-card">
                    <div class="rank-badge" :class="getRankClass(index + 1)">{{ index + 1 }}</div>
                    <div class="product-info">
                        <h4 class="product-name">{{ product.name }}</h4>
                        <span class="product-brand">{{ product.brand }}</span>
                    </div>
                    <div class="product-stats">
                        <div class="stat-item">
                            <span class="stat-label">Sold</span>
                            <span class="stat-value">{{ formatNumber(product.quantity_sold) }}</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Revenue</span>
                            <span class="stat-value">&#8369;{{ formatNumber(product.revenue) }}</span>
                        </div>
                    </div>
                    <div class="product-progress">
                        <div class="progress-bar">
                            <div class="progress-fill" :style="{ width: product.percentage + '%', background: getProductColor(index + 1) }"></div>
                        </div>
                        <span class="percentage-label">{{ product.percentage }}%</span>
                    </div>
                </div>
            </div>
            <div v-else class="empty-state">
                <i class="bx bx-package"></i>
                <p>No top selling product to show</p>
            </div>
        </div>

        <div class="charts-row">
            <div class="chart-card large">
                <div class="card-header-modern">
                    <div>
                        <h3>Revenue Overview</h3>
                        <p class="text-muted-600">Monthly sales performance</p>
                    </div>
                </div>
                <div class="chart-body">
                    <apexchart
                        v-if="salesChart.series.length"
                        type="area"
                        height="320"
                        :options="salesChart.options"
                        :series="salesChart.series"
                    />
                </div>
            </div>

            <div class="chart-card">
                <div class="card-header-modern">
                    <h3>Payment Methods</h3>
                </div>
                <div class="chart-body">
                    <apexchart
                        v-if="paymentChart.series.length"
                        type="donut"
                        height="280"
                        :options="paymentChart.options"
                        :series="paymentChart.series"
                    />

                    <div class="payment-legend">
                        <div v-for="(item, i) in paymentBreakdown" :key="i" class="legend-item">
                            <span class="color-dot" :style="{ background: item.color }"></span>
                            <span class="flex-grow-1">{{ item.method }}</span>
                            <span class="fw-semibold">&#8369;{{ formatNumber(item.amount) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modern-card">
            <div class="card-header-modern">
                <div>
                    <h3>Recent Transactions</h3>
                    <p class="text-muted-600">Latest sales activities</p>
                </div>
            </div>
            <div class="table-container">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Receipt #</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Amount</th>
                            <th>Payment</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="t in recentTransactions" :key="t.id">
                            <td><span class="receipt-num">#{{ t.receipt_number }}</span></td>
                            <td>{{ t.customer_name }}</td>
                            <td>{{ t.date }}</td>
                            <td>{{ t.items_count || 3 }} items</td>
                            <td class="fw-semibold">&#8369;{{ formatNumber(t.amount) }}</td>
                            <td><span class="payment-badge">{{ t.payment_method }}</span></td>
                            <td><span class="status-badge completed">Completed</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';

export default {
    name: 'SalesDashboard',
    components: {
        apexchart: VueApexCharts
    },
    props: {
        salesStats: {
            type: Array,
            default: () => []
        },
        topProducts: {
            type: Array,
            default: () => []
        },
        salesChart: {
            type: Object,
            default: () => ({ series: [], options: {} })
        },
        paymentChart: {
            type: Object,
            default: () => ({ series: [], options: {} })
        },
        paymentBreakdown: {
            type: Array,
            default: () => []
        },
        recentTransactions: {
            type: Array,
            default: () => []
        }
    },
    methods: {
        formatNumber(val) {
            if (!val && val !== 0) return '0';
            return val.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },
        getRankClass(rank) {
            if (rank === 1) return 'rank-gold';
            if (rank === 2) return 'rank-silver';
            if (rank === 3) return 'rank-bronze';
            return 'rank-default';
        },
        getProductColor(rank) {
            const colors = ['#FFD700', '#C0C0C0', '#CD7F32', '#3b82f6', '#8b5cf6'];
            return colors[rank - 1] || '#64748b';
        }
    }
};
</script>

<style scoped>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 20px;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
}

.stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.stat-info {
    flex: 1;
}

.stat-label {
    color: #64748b;
    font-size: 0.9rem;
    display: block;
    margin-bottom: 0.25rem;
}

.stat-value-wrapper {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
}

.stat-trend {
    font-size: 0.875rem;
    padding: 0.25rem 0.5rem;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.trend-up {
    color: #10b981;
    background: #e6f9ed;
}

.trend-down {
    color: #ef4444;
    background: #fee2e2;
}

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

.card-header-modern p {
    font-size: 0.875rem;
    margin: 0.25rem 0 0 0;
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

.top-products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
    padding: 1.5rem;
}

.top-product-card {
    background: #f8fafc;
    border-radius: 16px;
    padding: 1.25rem;
    border: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    position: relative;
    transition: all 0.2s;
}

.top-product-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.rank-badge {
    position: absolute;
    top: -8px;
    left: -8px;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.75rem;
    color: white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.rank-gold {
    background: linear-gradient(135deg, #ffd700, #ffa500);
}

.rank-silver {
    background: linear-gradient(135deg, #c0c0c0, #a8a8a8);
}

.rank-bronze {
    background: linear-gradient(135deg, #cd7f32, #b87333);
}

.rank-default {
    background: #64748b;
}

.product-info {
    padding-top: 0.5rem;
}

.product-info .product-name {
    font-size: 1rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0 0 0.25rem 0;
}

.product-info .product-brand {
    font-size: 0.8rem;
    color: #64748b;
}

.product-stats {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
}

.product-stats .stat-item {
    display: flex;
    flex-direction: column;
}

.product-stats .stat-label {
    font-size: 0.7rem;
    color: #64748b;
    text-transform: uppercase;
}

.product-stats .stat-value {
    font-size: 0.95rem;
    font-weight: 600;
    color: #1e293b;
}

.product-progress {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.product-progress .progress-bar {
    flex: 1;
    height: 8px;
    background: #e2e8f0;
    border-radius: 4px;
    overflow: hidden;
    margin: 0;
}

.product-progress .progress-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}

.product-progress .percentage-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #64748b;
    min-width: 35px;
    text-align: right;
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    color: #64748b;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    color: #cbd5e1;
}

.empty-state p {
    font-size: 1rem;
    font-weight: 500;
}

.payment-legend {
    padding: 1rem;
    border-top: 1px solid #e2e8f0;
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

.table-container {
    padding: 1.5rem;
    overflow-x: auto;
}

.modern-table {
    width: 100%;
    border-collapse: collapse;
}

.modern-table th {
    text-align: left;
    padding: 1rem;
    color: #64748b;
    font-weight: 500;
    font-size: 0.875rem;
    border-bottom: 1px solid #e2e8f0;
}

.modern-table td {
    padding: 1rem;
    border-bottom: 1px solid #f1f5f9;
}

.receipt-num {
    font-weight: 600;
    color: #3b82f6;
}

.payment-badge {
    background: #f1f5f9;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
}

.status-badge.completed {
    background: #e6f9ed;
    color: #10b981;
}

@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .charts-row {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>
