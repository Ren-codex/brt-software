<template>
    <div class="dashboard-content">
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

        <div class="charts-row">
            <div class="chart-card">
                <div class="card-header-modern">
                    <h3>Department Distribution</h3>
                </div>
                <div class="chart-body">
                    <apexchart
                        v-if="deptChart.series.length"
                        type="bar"
                        height="280"
                        :options="deptChart.options"
                        :series="deptChart.series"
                    />
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
import VueApexCharts from 'vue3-apexcharts';

export default {
    name: 'EmployeeDashboard',
    components: {
        apexchart: VueApexCharts
    },
    props: {
        employeeStats: {
            type: Array,
            default: () => []
        },
        deptChart: {
            type: Object,
            default: () => ({ series: [], options: {} })
        },
        attendanceStats: {
            type: Object,
            default: () => ({ present: 0, late: 0, absent: 0 })
        },
        recentAttendance: {
            type: Array,
            default: () => []
        },
        upcomingLeaves: {
            type: Array,
            default: () => []
        }
    },
    methods: {
        getInitials(name) {
            return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
        },
        formatMonth(date) {
            return new Date(date).toLocaleString('default', { month: 'short' });
        },
        formatDay(date) {
            return new Date(date).getDate();
        }
    }
};
</script>

<style scoped>
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

.split-view {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

.badge-new {
    background: #f1f5f9;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    color: #64748b;
}

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

@media (max-width: 1200px) {
    .team-stats {
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
    .team-stats {
        grid-template-columns: 1fr;
    }
}
</style>
