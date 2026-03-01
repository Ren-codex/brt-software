<template>
    <Head title="Dashboard"/>
    
  

    <!-- Modern Tab Design -->
    <div class="tab-container">
        <div class="tab-buttons">
            <button v-for="tab in tabs" :key="tab.value"
                    @click="activeTab = tab.value"
                    :class="['tab-btn', { active: activeTab === tab.value }]">
                <i :class="tab.icon"></i>
                <span>{{ tab.label }}</span>
            </button>
        </div>
    </div>

    <!-- Sales Dashboard - Modern Design -->
    <div v-if="activeTab === 'sales'" class="dashboard-content">
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div v-for="stat in salesStats" :key="stat.label" class="stat-card">
                <div class="stat-icon" :style="{ background: stat.iconBg }">
                    <i :class="stat.icon" :style="{ color: stat.iconColor }"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-label">{{ stat.label }}</span>
                    <div class="stat-value-wrapper">
                        <span class="stat-value">{{ stat.showCurrency ? '₱' : '' }}{{ formatNumber(stat.value) }}</span>
                        <span class="stat-trend" :class="stat.trendClass">
                            <i :class="stat.trendIcon"></i> {{ stat.trend }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row - Modern Cards -->
        <div class="charts-row">
            <div class="chart-card large">
                <div class="card-header-modern">
                    <div>
                        <h3>Revenue Overview</h3>
                        <p class="text-muted-600">Monthly sales performance</p>
                    </div>
                    <!-- <div class="chart-actions">
                        <select class="chart-select">
                            <option>Last 6 months</option>
                            <option>Last year</option>
                        </select>
                    </div> -->
                </div>
                <div class="chart-body">
                    <apexchart 
                        v-if="salesChart.series.length" 
                        type="area" height="320"
                        :options="salesChart.options" 
                        :series="salesChart.series">
                    </apexchart>
                </div>
            </div>

            <div class="chart-card">
                <div class="card-header-modern">
                    <h3>Payment Methods</h3>
                </div>
                <div class="chart-body">
                    <apexchart 
                        v-if="paymentChart.series.length" 
                        type="donut" height="280"
                        :options="paymentChart.options" 
                        :series="paymentChart.series">
                    </apexchart>
                    
                    <!-- Payment Legend -->
                    <div class="payment-legend">
                        <div v-for="(item, i) in paymentBreakdown" :key="i" class="legend-item">
                            <span class="color-dot" :style="{ background: item.color }"></span>
                            <span class="flex-grow-1">{{ item.method }}</span>
                            <span class="fw-semibold">₱{{ formatNumber(item.amount) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transactions Table - Modern Design -->
        <div class="modern-card">
            <div class="card-header-modern">
                <div>
                    <h3>Recent Transactions</h3>
                    <p class="text-muted-600">Latest sales activities</p>
                </div>
                <!-- <button class="btn-outline-modern">
                    View All <i class="bx bx-right-arrow-alt ms-2"></i>
                </button> -->
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
                            <td class="fw-semibold">₱{{ formatNumber(t.amount) }}</td>
                            <td><span class="payment-badge">{{ t.payment_method }}</span></td>
                            <td><span class="status-badge completed">Completed</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Inventory Dashboard - Modern Design -->
    <div v-else-if="activeTab === 'inventory'" class="dashboard-content">
        <!-- Stock Health Overview -->
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

        <!-- Inventory Charts -->
        <div class="charts-row">
            <div class="chart-card large">
                <div class="card-header-modern">
                    <h3>Stock Distribution by Category</h3>
                </div>
                <div class="chart-body">
                    <apexchart 
                        v-if="stockChart.series.length" 
                        type="bar" height="300"
                        :options="stockChart.options" 
                        :series="stockChart.series">
                    </apexchart>
                </div>
            </div>

            <div class="chart-card">
                <div class="card-header-modern">
                    <h3>Stock Health</h3>
                </div>
                <div class="chart-body">
                    <apexchart 
                        v-if="healthChart.series.length" 
                        type="radialBar" height="280"
                        :options="healthChart.options" 
                        :series="healthChart.series">
                    </apexchart>
                    
                    <!-- Stock Health Legend -->
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

        <!-- Low Stock Alert - Modern Grid -->
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
                <div v-for="item in lowStockItems" :key="item.id" class="alert-item-card" 
                     :class="getAlertClass(item)">
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

    <!-- Employee Dashboard - Modern Design -->
    <div v-else-if="activeTab === 'employee'" class="dashboard-content">
        <!-- Team Stats -->
        <div class="team-stats">
            <div v-for="stat in employeeStats" :key="stat.label" class="team-stat-card">
                <div class="stat-main">
                    <span class="stat-main-label">{{ stat.label }}</span>
                    <span class="stat-main-value">{{ stat.value }}</span>
                </div>
                <div class="stat-footer">
                    <span :class="stat.trendClass">
                        <i :class="stat.trendIcon"></i> {{ stat.trend }}
                    </span>
                    <span class="text-muted-600">vs last month</span>
                </div>
            </div>
        </div>

        <!-- Employee Charts -->
        <div class="charts-row">
            <div class="chart-card">
                <div class="card-header-modern">
                    <h3>Department Distribution</h3>
                </div>
                <div class="chart-body">
                    <apexchart 
                        v-if="deptChart.series.length" 
                        type="bar" height="280"
                        :options="deptChart.options" 
                        :series="deptChart.series">
                    </apexchart>
                </div>
            </div>
            <div class="chart-card">
                <div class="card-header-modern">
                    <h3>Attendance Overview</h3>
                </div>
                <div class="chart-body">
                    <div class="attendance-stats">
                        <div class="attendance-circle">
                            <div class="circle-item present">
                                <span class="number">{{ attendanceStats.present }}</span>
                                <span class="label">Present</span>
                            </div>
                            <div class="circle-item late">
                                <span class="number">{{ attendanceStats.late }}</span>
                                <span class="label">Late</span>
                            </div>
                            <div class="circle-item absent">
                                <span class="number">{{ attendanceStats.absent }}</span>
                                <span class="label">Absent</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance & Leave - Modern Split View -->
        <div class="split-view">
            <div class="modern-card">
                <div class="card-header-modern">
                    <h3>Today's Attendance</h3>
                    <span class="badge-new">{{ recentAttendance.length }} records</span>
                </div>
                <div class="attendance-list">
                    <div v-for="a in recentAttendance" :key="a.id" class="attendance-item">
                        <div class="employee-avatar">
                            {{ getInitials(a.employee_name) }}
                        </div>
                        <div class="employee-info">
                            <h4>{{ a.employee_name }}</h4>
                            <span>{{ a.department }}</span>
                        </div>
                        <div class="attendance-time">{{ a.time_in }}</div>
                        <span class="status-pill" :class="a.status === 'On Time' ? 'ontime' : 'late'">
                            {{ a.status }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="modern-card">
                <div class="card-header-modern">
                    <h3>Upcoming Leaves</h3>
                    <span class="badge-new">{{ upcomingLeaves.length }} pending</span>
                </div>
                <div class="leaves-list">
                    <div v-for="l in upcomingLeaves" :key="l.id" class="leave-item">
                        <div class="leave-date">
                            <span class="month">{{ formatMonth(l.start_date) }}</span>
                            <span class="day">{{ formatDay(l.start_date) }}</span>
                        </div>
                        <div class="leave-info">
                            <h4>{{ l.employee_name }}</h4>
                            <span>{{ l.leave_type }}</span>
                        </div>
                        <span class="status-pill" :class="l.status.toLowerCase()">
                            {{ l.status }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import PageHeader from '@/Shared/Components/PageHeader.vue';
import VueApexCharts from 'vue3-apexcharts';

export default {
    props: {
        stats: Object,
        charts: Object,
        inventoryStats: Object,
        inventoryCharts: Object,
        lowStockItems: Array,
        employeeStats: Object,
        employeeCharts: Object,
        recentTransactions: Array
    },
    components: {
        PageHeader,
        apexchart: VueApexCharts
    },
    data() {
        return {
            activeTab: 'sales',
            tabs: [
                { label: 'Sales', value: 'sales', icon: 'bx bx-store' },
                { label: 'Inventory', value: 'inventory', icon: 'bx bx-package' },
                { label: 'Team', value: 'employee', icon: 'bx bx-user' }
            ],
            // Demo data
            recentAttendance: [
                { id: 1, employee_name: 'John Smith', department: 'Sales', time_in: '08:45 AM', status: 'On Time' },
                { id: 2, employee_name: 'Maria Garcia', department: 'IT', time_in: '08:50 AM', status: 'On Time' },
                { id: 3, employee_name: 'David Lee', department: 'Sales', time_in: '09:15 AM', status: 'Late' },
                { id: 4, employee_name: 'Sarah Johnson', department: 'HR', time_in: '08:55 AM', status: 'On Time' }
            ],
            upcomingLeaves: [
                { id: 1, employee_name: 'Emily Davis', leave_type: 'Vacation', start_date: '2024-01-20', status: 'Approved' },
                { id: 2, employee_name: 'James Wilson', leave_type: 'Sick', start_date: '2024-01-18', status: 'Pending' },
                { id: 3, employee_name: 'Lisa Anderson', leave_type: 'Personal', start_date: '2024-01-22', status: 'Pending' }
            ]
        };
    },
    computed: {
        salesStats() {
            const s = this.stats || {};
            return [
                { label: 'Total Revenue', value: s.totalSales || 0, icon: 'bx bx-dollar', iconBg: '#E6F9ED', iconColor: '#10b981', trend: '+12.5%', trendClass: 'trend-up', trendIcon: 'bx bx-up-arrow-alt', showCurrency: true },
                { label: 'Total Orders', value: s.totalReceipts || 0, icon: 'bx bx-receipt', iconBg: '#E5F0FF', iconColor: '#3b82f6', trend: '+8.2%', trendClass: 'trend-up', trendIcon: 'bx bx-up-arrow-alt', showCurrency: false },
                { label: 'Outstanding', value: s.totalOutstanding || 0, icon: 'bx bx-credit-card', iconBg: '#FFF0E5', iconColor: '#f97316', trend: '-2.1%', trendClass: 'trend-down', trendIcon: 'bx bx-down-arrow-alt', showCurrency: true },
                { label: 'Avg Order', value: s.avgOrderValue || 0, icon: 'bx bx-calculator', iconBg: '#F3E8FF', iconColor: '#8b5cf6', trend: '+5.8%', trendClass: 'trend-up', trendIcon: 'bx bx-up-arrow-alt', showCurrency: false }
            ];
        },
        inventoryStats() {
            const i = this.inventoryStats || {};
            return [
                { label: 'Total Products', value: i.totalProducts || 0, unit: 'items', icon: 'bx bx-package', iconBg: '#E5F0FF', trend: '+5.2%', trendClass: 'trend-up', trendIcon: 'bx bx-up-arrow-alt', cardClass: 'health-blue' },
                { label: 'Low Stock', value: i.lowStockItems || 0, unit: 'items', icon: 'bx bx-error', iconBg: '#FFF0E5', trend: '+12%', trendClass: 'trend-up', trendIcon: 'bx bx-up-arrow-alt', cardClass: 'health-orange' },
                { label: 'Out of Stock', value: i.outOfStock || 0, unit: 'items', icon: 'bx bx-block', iconBg: '#FFE5E5', trend: '+3%', trendClass: 'trend-up', trendIcon: 'bx bx-up-arrow-alt', cardClass: 'health-red' },
                { label: 'Inventory Value', value: i.totalValue || 0, unit: '', icon: 'bx bx-dollar', iconBg: '#E6F9ED', trend: '+8.1%', trendClass: 'trend-up', trendIcon: 'bx bx-up-arrow-alt', cardClass: 'health-green' }
            ];
        },
        employeeStats() {
            const e = this.employeeStats || {};
            return [
                { label: 'Total Team', value: e.totalEmployees || 48, trend: '+4.5%', trendClass: 'trend-up', trendIcon: 'bx bx-up-arrow-alt' },
                { label: 'Present Today', value: e.presentToday || 38, total: e.totalEmployees || 48, trend: '+2.1%', trendClass: 'trend-up', trendIcon: 'bx bx-up-arrow-alt' },
                { label: 'On Leave', value: e.onLeave || 5, trend: '-1.2%', trendClass: 'trend-down', trendIcon: 'bx bx-down-arrow-alt' },
                { label: 'Departments', value: e.totalDepartments || 6, trend: '0%', trendClass: 'trend-neutral', trendIcon: 'bx bx-minus' }
            ];
        },
        attendanceStats() {
            return {
                present: 38,
                late: 4,
                absent: 6
            };
        },
        salesChart() {
            const data = this.charts?.monthlySales || [
                { month: 'Jan', sales: 45000 },
                { month: 'Feb', sales: 52000 },
                { month: 'Mar', sales: 48000 },
                { month: 'Apr', sales: 60000 },
                { month: 'May', sales: 55000 },
                { month: 'Jun', sales: 68000 }
            ];
            return {
                series: [{ name: 'Revenue', data: data.map(d => d.sales) }],
                options: {
                    chart: { toolbar: false, sparkline: false },
                    colors: ['#3b82f6'],
                    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.7, opacityTo: 0.2 } },
                    stroke: { curve: 'smooth', width: 3 },
                    xaxis: { categories: data.map(d => d.month), labels: { style: { colors: '#64748b' } } },
                    yaxis: { labels: { formatter: (v) => '₱' + v.toLocaleString() } },
                    grid: { borderColor: '#e2e8f0', strokeDashArray: 5 }
                }
            };
        },
        paymentChart() {
            const data = this.charts?.paymentMethods || [
                { method: 'Cash', total: 45000 },
                { method: 'Card', total: 38000 },
                { method: 'Bank Transfer', total: 22000 }
            ];
            this.paymentBreakdown = data.map((d, i) => ({
                ...d,
                color: ['#3b82f6', '#10b981', '#f97316'][i]
            }));
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
            const data = this.inventoryCharts?.stockDistribution || [
                { status: 'Healthy', percentage: 65 },
                { status: 'Low', percentage: 20 },
                { status: 'Critical', percentage: 10 },
                { status: 'Out', percentage: 5 }
            ];
            this.healthData = data.map((d, i) => ({
                ...d,
                color: ['#10b981', '#f97316', '#ef4444', '#6b7280'][i]
            }));
            return {
                series: [75], // Overall health score
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
            const data = this.employeeCharts?.employeesByDepartment || [
                { department: 'Sales', count: 15 },
                { department: 'IT', count: 8 },
                { department: 'HR', count: 5 },
                { department: 'Ops', count: 12 },
                { department: 'Finance', count: 8 }
            ];
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
    methods: {
        formatNumber(val) {
            if (!val && val !== 0) return '0';
            return val.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },
        getInitials(name) {
            return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
        },
        formatMonth(date) {
            return new Date(date).toLocaleString('default', { month: 'short' });
        },
        formatDay(date) {
            return new Date(date).getDate();
        },
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
/* Modern Dashboard Styles */
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
    .stats-grid, .health-cards, .team-stats {
        grid-template-columns: 1fr;
    }
    .tab-buttons {
        flex-direction: column;
    }
}
</style>