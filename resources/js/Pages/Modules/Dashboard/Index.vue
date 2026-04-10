<template>
    <Head title="Dashboard"/>

    <div class="dashboard-hero">
        <div class="dashboard-hero-copy">
            <span class="dashboard-kicker">{{ dashboardConfig.kicker }}</span>
            <h1 class="dashboard-title">{{ dashboardConfig.title }}</h1>
            <p class="dashboard-subtitle">{{ dashboardConfig.subtitle }}</p>
        </div>
        <div class="dashboard-badges">
            <span v-for="badge in dashboardConfig.badges" :key="badge" class="dashboard-badge">{{ badge }}</span>
        </div>
    </div>

    <div v-if="showTabs" class="tab-container">
        <div class="tab-buttons">
            <button v-for="tab in availableTabs" :key="tab.value"
                    @click="activeTab = tab.value"
                    :class="['tab-btn', { active: activeTab === tab.value }]">
                <i :class="tab.icon"></i>
                <span>{{ tab.label }}</span>
            </button>
        </div>
    </div>

    <div class="filter-container">
        <div class="filter-buttons">
            <button
                v-for="filter in dateFilters"
                :key="filter.value"
                @click="selectedFilter = filter.value"
                :class="['filter-btn', { active: selectedFilter === filter.value }]"
            >
                {{ filter.label }}
            </button>
        </div>
        <div v-if="selectedFilter === 'today'" class="selected-date-input">
            <input
                v-model="selectedDateValue"
                type="date"
                class="date-input"
            >
        </div>
    </div>

    <!-- Sales Dashboard - Modern Design -->
    <SalesDashboard
        v-if="activeTab === 'sales'"
        :sales-stats="salesStatCards"
        :top-products="topProducts"
        :sales-chart="salesChart"
        :payment-chart="paymentChart"
        :payment-breakdown="paymentBreakdown"
        :recent-transactions="recentTransactions"
    />

    <!-- Inventory Dashboard - Modern Design -->
    <InventoryDashboard
        v-else-if="activeTab === 'inventory'"
        :inventory-stats="inventoryStatCards"
        :stock-chart="stockChart"
        :health-chart="healthChart"
        :health-data="healthData"
        :low-stock-items="lowStockItems"
    />

    <!-- Employee Dashboard - Modern Design -->
    <EmployeeDashboard
        v-else-if="activeTab === 'employee'"
        :employee-stats="teamStatCards"
        :dept-chart="deptChart"
        :workforce-summary="workforceSummary"
        :recent-employees="recentEmployees"
        :payroll-groups="payrollGroups"
    />
</template>

<script>
import SalesDashboard from '@/Pages/Modules/Dashboard/Partials/SalesDashboard.vue';
import InventoryDashboard from '@/Pages/Modules/Dashboard/Partials/InventoryDashboard.vue';
import EmployeeDashboard from '@/Pages/Modules/Dashboard/Partials/EmployeeDashboard.vue';

const ROLE_DASHBOARD_MAP = {
    'Super Admin': {
        tabs: ['sales', 'inventory', 'employee'],
        defaultTab: 'sales',
        kicker: 'Executive Command Center',
        title: 'Business Performance Dashboard',
        subtitle: 'A full cross-functional view of sales, inventory, and workforce activity.',
        badges: ['Full access', 'Operations', 'Financial visibility']
    },
    'Administrator': {
        tabs: ['sales', 'inventory', 'employee'],
        defaultTab: 'sales',
        kicker: 'Administrative Overview',
        title: 'Operations Dashboard',
        subtitle: 'Monitor the core business lines and manage daily operations from one place.',
        badges: ['System admin', 'Cross-module access', 'Monitoring']
    },
    'Mini Admin': {
        tabs: ['sales', 'inventory'],
        defaultTab: 'sales',
        kicker: 'Operations Overview',
        title: 'Mini Admin Dashboard',
        subtitle: 'Track business flow across sales and stock movement with a lighter admin view.',
        badges: ['Operations', 'Sales', 'Inventory']
    },
    'Sales Rep': {
        tabs: ['sales'],
        defaultTab: 'sales',
        kicker: 'Sales Workspace',
        title: 'Sales Dashboard',
        subtitle: 'Stay focused on revenue, collections, and recent customer transactions.',
        badges: ['Sales', 'Collections', 'Performance']
    },
    'Area Business Manager': {
        tabs: ['sales'],
        defaultTab: 'sales',
        kicker: 'Regional Performance',
        title: 'Area Business Dashboard',
        subtitle: 'Review sales output, customer activity, and revenue trends across the territory.',
        badges: ['Area performance', 'Revenue', 'Growth']
    },
    'Warehouse Manager': {
        tabs: ['inventory'],
        defaultTab: 'inventory',
        kicker: 'Warehouse Control',
        title: 'Inventory Dashboard',
        subtitle: 'Focus on stock health, low-stock alerts, and inventory value in one view.',
        badges: ['Stock control', 'Warehouse', 'Replenishment']
    },
    'Logistic Coordinator': {
        tabs: ['inventory'],
        defaultTab: 'inventory',
        kicker: 'Logistics Overview',
        title: 'Logistics Dashboard',
        subtitle: 'Monitor inventory pressure points and stock availability that affect delivery flow.',
        badges: ['Logistics', 'Movement', 'Coordination']
    },
    'HR Manager': {
        tabs: ['employee'],
        defaultTab: 'employee',
        kicker: 'People Operations',
        title: 'HR Dashboard',
        subtitle: 'Track team health, attendance, and upcoming leave activity in a focused HR view.',
        badges: ['Employees', 'Attendance', 'Leave']
    },
    'Accountant': {
        tabs: ['sales'],
        defaultTab: 'sales',
        kicker: 'Financial Overview',
        title: 'Accounting Dashboard',
        subtitle: 'Review revenue flow, receipts, and outstanding balances from an accounting lens.',
        badges: ['Accounting', 'Receivables', 'Revenue']
    }
};

export default {
    props: {
        stats: Object,
        charts: Object,
        inventoryStats: Object,
        inventoryCharts: Object,
        lowStockItems: Array,
        employeeStats: Object,
        employeeCharts: Object,
        workforceSummary: Object,
        recentTransactions: Array,
        recentEmployees: Array,
        payrollGroups: Array,
        filter: {
            type: String,
            default: 'today'
        },
        selectedDate: {
            type: String,
            default: ''
        }
    },
    components: {
        SalesDashboard,
        InventoryDashboard,
        EmployeeDashboard
    },
    mounted() {
        // Set filter from backend prop
        if (this.filter) {
            this.selectedFilter = this.filter;
        }
        if (this.selectedDate) {
            this.selectedDateValue = this.selectedDate;
        }
    },
    data() {
        const today = new Date().toISOString().slice(0, 10);
        return {
            activeTab: 'sales',
            selectedFilter: 'today',
            selectedDateValue: today,
            dateFilters: [
                { label: 'Date', value: 'today' },
                { label: 'Weekly', value: 'weekly' },
                { label: 'Monthly', value: 'monthly' },
                { label: 'Annually', value: 'annually' }
            ],
            tabs: [
                { label: 'Sales', value: 'sales', icon: 'bx bx-store' },
                { label: 'Inventory', value: 'inventory', icon: 'bx bx-package' },
                { label: 'Team', value: 'employee', icon: 'bx bx-user' }
            ]
        };
    },
    computed: {
        userRoles() {
            return this.$page?.props?.roles || [];
        },
        primaryRole() {
            const rolePriority = [
                'Super Admin',
                'Administrator',
                'Mini Admin',
                'Area Business Manager',
                'Accountant',
                'HR Manager',
                'Warehouse Manager',
                'Logistic Coordinator',
                'Sales Rep'
            ];

            return rolePriority.find(role => this.userRoles.includes(role)) || this.userRoles[0] || 'User';
        },
        dashboardConfig() {
            return ROLE_DASHBOARD_MAP[this.primaryRole] || {
                tabs: ['sales'],
                defaultTab: 'sales',
                kicker: 'Workspace Overview',
                title: 'Dashboard',
                subtitle: 'A quick snapshot of the data available to your current role.',
                badges: ['Overview']
            };
        },
        availableTabs() {
            const allowedTabs = this.dashboardConfig.tabs || ['sales'];
            return this.tabs.filter(tab => allowedTabs.includes(tab.value));
        },
        showTabs() {
            return this.availableTabs.length > 1;
        },
        topProducts() {
            return this.charts?.topProducts || [];
        },
        salesStatCards() {
            const s = this.stats || {};
            return [
                { label: 'Total Revenue', value: s.totalSales || 0, icon: 'bx bx-dollar', iconBg: '#E6F9ED', iconColor: '#10b981', trend: null, trendClass: '', trendIcon: '', showCurrency: true },
                { label: 'Total Receipts', value: s.totalReceipts || 0, icon: 'bx bx-receipt', iconBg: '#E5F0FF', iconColor: '#3b82f6', trend: null, trendClass: '', trendIcon: '', showCurrency: false },
                { label: 'Outstanding', value: s.totalOutstanding || 0, icon: 'bx bx-credit-card', iconBg: '#FFF0E5', iconColor: '#f97316', trend: null, trendClass: '', trendIcon: '', showCurrency: true },
                { label: 'Avg Revenue', value: s.avgOrderValue || 0, icon: 'bx bx-calculator', iconBg: '#F3E8FF', iconColor: '#8b5cf6', trend: null, trendClass: '', trendIcon: '', showCurrency: true }
            ];
        },
        inventoryStatCards() {
            const i = this.inventoryStats || {};
            return [
                { label: 'Total Products', value: i.totalProducts || 0, unit: 'items', icon: 'bx bx-package', iconBg: '#E5F0FF', trend: null, trendClass: '', trendIcon: '', cardClass: 'health-blue' },
                { label: 'Low Stock', value: i.lowStockItems || 0, unit: 'items', icon: 'bx bx-error', iconBg: '#FFF0E5', trend: null, trendClass: '', trendIcon: '', cardClass: 'health-orange' },
                { label: 'Out of Stock', value: i.outOfStock || 0, unit: 'items', icon: 'bx bx-block', iconBg: '#FFE5E5', trend: null, trendClass: '', trendIcon: '', cardClass: 'health-red' },
                { label: 'Inventory Value', value: i.totalValue || 0, unit: '', icon: 'bx bx-dollar', iconBg: '#E6F9ED', trend: null, trendClass: '', trendIcon: '', cardClass: 'health-green' }
            ];
        },
        teamStatCards() {
            const e = this.employeeStats || {};
            return [
                { label: 'Total Team', value: e.totalEmployees || 0, trend: `${e.activeEmployees || 0} active`, trendClass: 'trend-up', trendIcon: 'bx bx-group' },
                { label: 'Payroll Groups', value: e.employeesInPayrollGroups || 0, trend: 'employees assigned', trendClass: 'trend-neutral', trendIcon: 'bx bx-layer' },
                { label: 'Active Loans', value: e.employeesWithLoans || 0, trend: 'employees with balance', trendClass: 'trend-down', trendIcon: 'bx bx-wallet' },
                { label: 'Positions', value: e.totalPositions || 0, trend: `${e.employeesWithAccounts || 0} with accounts`, trendClass: 'trend-neutral', trendIcon: 'bx bx-briefcase' }
            ];
        },
        salesChart() {
            const data = this.charts?.monthlySales || [];
            return {
                series: [{ name: 'Revenue', data: data.map(d => d.sales) }],
                options: {
                    chart: { toolbar: false, sparkline: false },
                    colors: ['#3b82f6'],
                    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.7, opacityTo: 0.2 } },
                    stroke: { curve: 'smooth', width: 3 },
                    xaxis: { categories: data.map(d => d.month), labels: { style: { colors: '#64748b' } } },
                    yaxis: { labels: { formatter: (v) => 'P' + v.toLocaleString() } },
                    grid: { borderColor: '#e2e8f0', strokeDashArray: 5 }
                }
            };
        },
        paymentChart() {
            const data = this.charts?.paymentMethods || [];
            return {
                series: data.map(d => d.total),
                options: {
                    chart: { type: 'donut' },
                    labels: data.map(d => d.method),
                    colors: ['#3b82f6', '#10b981', '#f97316'],
                    legend: { show: false },
                    plotOptions: { pie: { donut: { size: '65%' } } },
                    dataLabels: { enabled: false }
                }
            };
        },
        paymentBreakdown() {
            const data = this.charts?.paymentMethods || [];
            return data.map((d, i) => ({
                method: d.method,
                amount: d.total,
                color: ['#3b82f6', '#10b981', '#f97316'][i]
            }));
        },
        stockChart() {
            const data = this.inventoryCharts?.stockByCategory || [
                { category: 'Basmati', quantity: 450 },
                { category: 'Jasmine', quantity: 380 },
                { category: 'IRRI-6', quantity: 620 },
                { category: 'Ponni', quantity: 290 }
            ];
            return {
                series: [{ name: 'Stock', data: data.map(d => d.quantity) }],
                options: {
                    chart: { toolbar: false },
                    colors: ['#3b82f6'],
                    xaxis: { categories: data.map(d => d.category) },
                    grid: { borderColor: '#e2e8f0' }
                }
            };
        },
        healthChart() {
            const data = this.inventoryCharts?.stockDistribution || [];
            this.healthData = data.map((d, i) => ({
                ...d,
                label: d.status,
                value: d.percentage,
                color: ['#10b981', '#f97316', '#ef4444', '#6b7280'][i]
            }));

            const total = data.reduce((sum, item) => sum + (item.percentage || 0), 0);
            const inStock = data.find(item => item.status === 'In Stock')?.percentage || 0;
            const lowStock = data.find(item => item.status === 'Low Stock')?.percentage || 0;
            const outOfStock = data.find(item => item.status === 'Out of Stock')?.percentage || 0;
            const healthScore = total > 0
                ? Math.max(0, Math.min(100, Math.round(((inStock * 1) + (lowStock * 0.45) + (outOfStock * 0)) / total * 100)))
                : 0;

            return {
                series: [healthScore],
                options: {
                    chart: { type: 'radialBar' },
                    plotOptions: {
                        radialBar: {
                            hollow: { size: '70%' },
                            dataLabels: {
                                show: true,
                                name: { show: false },
                                value: { fontSize: '24px', fontWeight: 600, color: '#1e293b' }
                            }
                        }
                    },
                    colors: ['#10b981'],
                    labels: ['Stock Health']
                }
            };
        },
        deptChart() {
            const data = this.employeeCharts?.employeesByDepartment || [];
            return {
                series: [{ name: 'Employees', data: data.map(d => d.count) }],
                options: {
                    chart: { toolbar: false },
                    colors: ['#8b5cf6'],
                    xaxis: { categories: data.map(d => d.department) },
                    grid: { borderColor: '#e2e8f0' }
                }
            };
        }
    },
    watch: {
        selectedFilter() {
            this.loadDashboardData();
        },
        selectedDateValue() {
            if (this.selectedFilter === 'today') {
                this.loadDashboardData();
            }
        },
        availableTabs: {
            immediate: true,
            handler(tabs) {
                if (!tabs.length) {
                    this.activeTab = 'sales';
                    return;
                }

                const currentTabStillAllowed = tabs.some(tab => tab.value === this.activeTab);
                if (!currentTabStillAllowed) {
                    this.activeTab = this.dashboardConfig.defaultTab || tabs[0].value;
                }
            }
        }
    },
    methods: {
        loadDashboardData() {
            this.$inertia.get('/', {
                filter: this.selectedFilter,
                selected_date: this.selectedDateValue
            }, {
                preserveState: true,
                replace: true
            });
        }
    }
};
</script>

<style scoped>
/* Modern Dashboard Styles */
.dashboard-hero {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 1.6fr) auto;
    gap: 2rem;
    align-items: start;
    margin-bottom: 2rem;
    padding: 2.25rem 2.4rem;
    overflow: hidden;
    border-radius: 32px;
    background:
        radial-gradient(circle at 88% 18%, rgba(255, 215, 122, 0.22), transparent 18%),
        radial-gradient(circle at 18% 12%, rgba(255, 255, 255, 0.12), transparent 20%),
        linear-gradient(120deg, #0c4f4d 0%, #116564 28%, #1d827d 58%, #3d9b8f 100%);
    box-shadow:
        0 22px 55px rgba(19, 111, 99, 0.20),
        inset 0 1px 0 rgba(255, 255, 255, 0.14);
}

.dashboard-hero::before {
    content: '';
    position: absolute;
    inset: auto -6% -36% auto;
    width: 440px;
    height: 440px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.14) 0%, rgba(255, 255, 255, 0.03) 54%, transparent 72%);
    pointer-events: none;
}

.dashboard-hero::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 32px;
    border: 1px solid rgba(255, 255, 255, 0.14);
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.05), transparent 28%),
        linear-gradient(90deg, rgba(6, 35, 34, 0.18), transparent 35%, transparent 70%, rgba(255, 255, 255, 0.05));
    pointer-events: none;
}

.dashboard-hero-copy,
.dashboard-badges {
    position: relative;
    z-index: 1;
}

.dashboard-hero-copy {
    max-width: 760px;
}

.dashboard-kicker {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
    padding: 0.55rem 1rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(10px);
    color: #f6fffd;
    font-size: 0.8rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.18);
}

.dashboard-title {
    margin: 0;
    color: #ffffff;
    font-size: clamp(2.3rem, 4vw, 3.5rem);
    font-weight: 900;
    line-height: 0.98;
    letter-spacing: -0.04em;
    text-wrap: balance;
    text-shadow: 0 10px 28px rgba(5, 28, 26, 0.24);
}

.dashboard-subtitle {
    max-width: 760px;
    margin: 1rem 0 0;
    color: rgba(239, 252, 248, 0.92);
    font-size: 1.1rem;
    line-height: 1.7;
    text-wrap: pretty;
}

.dashboard-badges {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    align-content: flex-start;
    gap: 0.75rem;
    max-width: 340px;
    padding-top: 0.25rem;
}

.dashboard-badge {
    padding: 0.72rem 1.15rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.10);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.18);
    color: #fbfffe;
    font-size: 0.9rem;
    font-weight: 700;
    white-space: nowrap;
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.12),
        0 10px 20px rgba(7, 42, 39, 0.16);
}

.dashboard-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 2rem 2rem;
    border-radius: 24px;
    margin-bottom: 2rem;
    color: white;
}

.tab-container {
    margin-bottom: 2rem;
}

.tab-buttons {
    display: flex;
    gap: 1rem;
    background: #f8fafc;
    padding: 0.5rem;
    border-radius: 16px;
    max-width: 500px;
}

.tab-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    padding: 1rem;
    border: none;
    background: transparent;
    border-radius: 12px;
    color: #64748b;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.tab-btn.active {
    background: white;
    color: #2e8b57;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

/* Filter Container */
.filter-container {
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.filter-buttons {
    display: flex;
    gap: 0.5rem;
    background: #f1f5f9;
    padding: 0.25rem;
    border-radius: 10px;
    width: fit-content;
}

.filter-btn {
    padding: 0.5rem 1rem;
    border: none;
    background: transparent;
    border-radius: 8px;
    color: #64748b;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.filter-btn:hover {
    color: #3b82f6;
}

.filter-btn.active {
    background: white;
    color: #3b82f6;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.selected-date-input {
    display: flex;
    align-items: center;
}

.date-input {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.5rem 0.75rem;
    color: #1e293b;
    background: #fff;
}

/* Stats Grid */
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
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
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

.trend-up { color: #10b981; background: #e6f9ed; }
.trend-down { color: #ef4444; background: #fee2e2; }
.trend-neutral { color: #6b7280; background: #f3f4f6; }

/* Health Cards */
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

/* Charts Row */
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
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
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

/* Modern Card */
.modern-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    margin-bottom: 1.5rem;
}

/* Table Styles */
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

/* Alert Grid */
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

/* Attendance Styles */
.attendance-list, .leaves-list {
    padding: 1rem;
}

.attendance-item, .leave-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-bottom: 1px solid #f1f5f9;
}

.attendance-item:last-child, .leave-item:last-child {
    border-bottom: none;
}

.employee-avatar {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.875rem;
}

.employee-info {
    flex: 1;
}

.employee-info h4 {
    font-size: 0.95rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
}

.employee-info span {
    font-size: 0.75rem;
    color: #64748b;
}

.attendance-time {
    font-weight: 500;
    color: #1e293b;
    margin-right: 1rem;
}

.status-pill {
    padding: 0.25rem 1rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-pill.ontime { background: #e6f9ed; color: #10b981; }
.status-pill.late { background: #fee2e2; color: #ef4444; }
.status-pill.approved { background: #e5f0ff; color: #3b82f6; }
.status-pill.pending { background: #fff3e0; color: #f97316; }

/* Leave Item */
.leave-date {
    text-align: center;
    min-width: 50px;
}

.leave-date .month {
    font-size: 0.75rem;
    color: #64748b;
    display: block;
    text-transform: uppercase;
}

.leave-date .day {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.2;
}

.leave-info {
    flex: 1;
}

.leave-info h4 {
    font-size: 0.95rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
}

.leave-info span {
    font-size: 0.75rem;
    color: #64748b;
}

/* Split View */
.split-view {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

/* Badge */
.badge-new {
    background: #f1f5f9;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    color: #64748b;
}

.alert-badge {
    background: #fee2e2;
    color: #ef4444;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
}

/* Top Selling Products */
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
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
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
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.rank-gold { background: linear-gradient(135deg, #FFD700, #FFA500); }
.rank-silver { background: linear-gradient(135deg, #C0C0C0, #A8A8A8); }
.rank-bronze { background: linear-gradient(135deg, #CD7F32, #B87333); }
.rank-default { background: #64748b; }

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

/* Empty State */
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

/* Payment Legend */
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

/* Team Stats */
.team-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}

.team-stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
}

.stat-main {
    margin-bottom: 1rem;
}

.stat-main-label {
    display: block;
    color: #64748b;
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
}

.stat-main-value {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
}

.stat-footer {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
}

/* Attendance Circle */
.attendance-stats {
    display: flex;
    justify-content: center;
    padding: 1rem;
}

.attendance-circle {
    display: flex;
    gap: 2rem;
}

.circle-item {
    text-align: center;
}

.circle-item .number {
    font-size: 2rem;
    font-weight: 700;
    display: block;
    line-height: 1;
}

.circle-item .label {
    font-size: 0.875rem;
    color: #64748b;
}

.circle-item.present .number { color: #10b981; }
.circle-item.late .number { color: #f97316; }
.circle-item.absent .number { color: #ef4444; }

/* Responsive */
@media (max-width: 1200px) {
    .stats-grid, .health-cards, .team-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    .charts-row {
        grid-template-columns: 1fr;
    }
    .split-view {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .dashboard-hero {
        flex-direction: column;
        padding: 1.25rem;
    }

    .dashboard-title {
        font-size: 1.9rem;
    }

    .dashboard-badges {
        justify-content: flex-start;
        max-width: none;
    }

    .stats-grid, .health-cards, .team-stats {
        grid-template-columns: 1fr;
    }
    .tab-buttons {
        flex-direction: column;
    }
}
</style>
