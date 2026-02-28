<template>
    <Head title="Dashboard"/>
    
    <!-- Tab Navigation -->
    <div class="row mb-4">
        <div class="col-lg-12">
            <ul class="nav nav-tabs nav-tabs-custom nav-justified" role="tablist">
                <li class="nav-item">
                    <a class="nav-link" :class="{ 'active': activeTab === 'sales' }" 
                       @click.prevent="activeTab = 'sales'" href="#" role="tab">
                        <span class="d-block d-sm-none"><i class="bx bx-store"></i></span>
                        <span class="d-none d-sm-block">
                            <i class="bx bx-store align-middle me-1"></i> Sales Dashboard
                        </span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" :class="{ 'active': activeTab === 'inventory' }" 
                       @click.prevent="activeTab = 'inventory'" href="#" role="tab">
                        <span class="d-block d-sm-none"><i class="bx bx-package"></i></span>
                        <span class="d-none d-sm-block">
                            <i class="bx bx-package align-middle me-1"></i> Inventory Dashboard
                        </span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" :class="{ 'active': activeTab === 'employee' }" 
                       @click.prevent="activeTab = 'employee'" href="#" role="tab">
                        <span class="d-block d-sm-none"><i class="bx bx-user"></i></span>
                        <span class="d-none d-sm-block">
                            <i class="bx bx-user align-middle me-1"></i> Employee Dashboard
                        </span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Sales Dashboard Tab -->
    <div v-show="activeTab === 'sales'" class="tab-pane">
        <PageHeader title="Sales Dashboard" pageTitle="Overview" />
        
        <!-- Sales Statistics -->
        <div class="row">
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-medium text-muted mb-0">Total Sales</p>
                            </div>
                            <div class="flex-shrink-0">
                                <h5 class="text-success fs-14 mb-0">
                                    <i class="ri-arrow-up-line fs-13 align-middle"></i> +12.5%
                                </h5>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-4">
                            <div>
                                <h4 class="fs-22 fw-semibold ff-secondary mb-4">
                                    ₱<span class="counter-value">{{ formatNumber(stats?.totalSales || 0) }}</span>
                                </h4>
                                <a href="#" class="text-decoration-underline">View all sales</a>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-success-subtle rounded fs-3">
                                    <i class="bx bx-shopping-bag text-success"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-medium text-muted mb-0">Total Receipts</p>
                            </div>
                            <div class="flex-shrink-0">
                                <h5 class="text-success fs-14 mb-0">
                                    <i class="ri-arrow-up-line fs-13 align-middle"></i> +8.2%
                                </h5>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-4">
                            <div>
                                <h4 class="fs-22 fw-semibold ff-secondary mb-4">
                                    <span class="counter-value">{{ formatNumber(stats?.totalReceipts || 0) }}</span>
                                </h4>
                                <a href="#" class="text-decoration-underline">View all receipts</a>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-info-subtle rounded fs-3">
                                    <i class="bx bx-receipt text-info"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-medium text-muted mb-0">Outstanding Balance</p>
                            </div>
                            <div class="flex-shrink-0">
                                <h5 class="text-danger fs-14 mb-0">
                                    <i class="ri-arrow-down-line fs-13 align-middle"></i> -2.1%
                                </h5>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-4">
                            <div>
                                <h4 class="fs-22 fw-semibold ff-secondary mb-4">
                                    ₱<span class="counter-value">{{ formatNumber(stats?.totalOutstanding || 0) }}</span>
                                </h4>
                                <a href="#" class="text-decoration-underline">View outstanding</a>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-warning-subtle rounded fs-3">
                                    <i class="bx bx-credit-card text-warning"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-medium text-muted mb-0">Avg. Order Value</p>
                            </div>
                            <div class="flex-shrink-0">
                                <h5 class="text-success fs-14 mb-0">
                                    <i class="ri-arrow-up-line fs-13 align-middle"></i> +5.8%
                                </h5>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-4">
                            <div>
                                <h4 class="fs-22 fw-semibold ff-secondary mb-4">
                                    ₱<span class="counter-value">{{ formatNumber(stats?.avgOrderValue || 0) }}</span>
                                </h4>
                                <a href="#" class="text-decoration-underline">View details</a>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-primary-subtle rounded fs-3">
                                    <i class="bx bx-calculator text-primary"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales Charts -->
        <div class="row">
            <div class="col-xl-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Monthly Sales Trend</h4>
                    </div>
                    <div class="card-body">
                        <apexchart
                            v-if="hasSalesData"
                            type="bar"
                            height="350"
                            :options="salesChartOptions"
                            :series="salesSeries"
                        ></apexchart>
                        <div v-else class="text-center py-5">
                            <p class="text-muted mb-0">No sales data available</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Payment Methods</h4>
                    </div>
                    <div class="card-body">
                        <apexchart
                            v-if="hasPaymentData"
                            type="pie"
                            height="350"
                            :options="paymentChartOptions"
                            :series="paymentSeries"
                        ></apexchart>
                        <div v-else class="text-center py-5">
                            <p class="text-muted mb-0">No payment data available</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Recent Transactions</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Receipt #</th>
                                        <th>Customer</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Payment Method</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="recentTransactions.length === 0">
                                        <td colspan="6" class="text-center py-4">No recent transactions</td>
                                    </tr>
                                    <tr v-for="transaction in recentTransactions" :key="transaction.id">
                                        <td>{{ transaction.receipt_number }}</td>
                                        <td>{{ transaction.customer_name }}</td>
                                        <td>{{ transaction.date }}</td>
                                        <td>₱{{ formatNumber(transaction.amount) }}</td>
                                        <td>{{ transaction.payment_method }}</td>
                                        <td><span class="badge bg-success">Completed</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Inventory Dashboard Tab -->
    <div v-show="activeTab === 'inventory'" class="tab-pane">
        <PageHeader title="Inventory Dashboard" pageTitle="Stock Management" />
        
        <!-- Inventory Statistics -->
        <div class="row">
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-medium text-muted mb-0">Total Products</p>
                            </div>
                            <div class="flex-shrink-0">
                                <h5 class="text-success fs-14 mb-0">
                                    <i class="ri-arrow-up-line fs-13 align-middle"></i> +5.2%
                                </h5>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-4">
                            <div>
                                <h4 class="fs-22 fw-semibold ff-secondary mb-4">
                                    <span class="counter-value">{{ formatNumber(inventoryStats?.totalProducts || 0) }}</span>
                                </h4>
                                <a href="#" class="text-decoration-underline">View all products</a>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-primary-subtle rounded fs-3">
                                    <i class="bx bx-package text-primary"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-medium text-muted mb-0">Low Stock Items</p>
                            </div>
                            <div class="flex-shrink-0">
                                <h5 class="text-danger fs-14 mb-0">
                                    <i class="ri-arrow-up-line fs-13 align-middle"></i> +12%
                                </h5>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-4">
                            <div>
                                <h4 class="fs-22 fw-semibold ff-secondary mb-4">
                                    <span class="counter-value">{{ formatNumber(inventoryStats?.lowStockItems || 0) }}</span>
                                </h4>
                                <a href="#" class="text-decoration-underline text-warning">Reorder now</a>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-warning-subtle rounded fs-3">
                                    <i class="bx bx-error text-warning"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-medium text-muted mb-0">Out of Stock</p>
                            </div>
                            <div class="flex-shrink-0">
                                <h5 class="text-danger fs-14 mb-0">
                                    <i class="ri-arrow-up-line fs-13 align-middle"></i> +3%
                                </h5>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-4">
                            <div>
                                <h4 class="fs-22 fw-semibold ff-secondary mb-4">
                                    <span class="counter-value">{{ formatNumber(inventoryStats?.outOfStock || 0) }}</span>
                                </h4>
                                <a href="#" class="text-decoration-underline text-danger">Restock</a>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-danger-subtle rounded fs-3">
                                    <i class="bx bx-block text-danger"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-medium text-muted mb-0">Inventory Value</p>
                            </div>
                            <div class="flex-shrink-0">
                                <h5 class="text-success fs-14 mb-0">
                                    <i class="ri-arrow-up-line fs-13 align-middle"></i> +8.1%
                                </h5>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-4">
                            <div>
                                <h4 class="fs-22 fw-semibold ff-secondary mb-4">
                                    ₱<span class="counter-value">{{ formatNumber(inventoryStats?.totalValue || 0) }}</span>
                                </h4>
                                <a href="#" class="text-decoration-underline">View valuation</a>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-success-subtle rounded fs-3">
                                    <i class="bx bx-dollar text-success"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventory Charts -->
        <div class="row">
            <div class="col-xl-7">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Stock Levels by Category</h4>
                    </div>
                    <div class="card-body">
                        <apexchart
                            v-if="hasInventoryChartData"
                            type="bar"
                            height="350"
                            :options="inventoryChartOptions"
                            :series="inventorySeries"
                        ></apexchart>
                        <div v-else class="text-center py-5">
                            <p class="text-muted mb-0">No inventory data available</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Stock Distribution</h4>
                    </div>
                    <div class="card-body">
                        <apexchart
                            v-if="hasStockDistributionData"
                            type="donut"
                            height="350"
                            :options="stockDistributionOptions"
                            :series="stockDistributionSeries"
                        ></apexchart>
                        <div v-else class="text-center py-5">
                            <p class="text-muted mb-0">No stock distribution data available</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Low Stock Alert Table -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Low Stock Alert</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Product Code</th>
                                        <th>Product Name</th>
                                        <th>Category</th>
                                        <th>Current Stock</th>
                                        <th>Minimum Stock</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="lowStockItems.length === 0">
                                        <td colspan="7" class="text-center py-4">No low stock items</td>
                                    </tr>
                                    <tr v-for="item in lowStockItems" :key="item.id">
                                        <td>{{ item.code }}</td>
                                        <td>{{ item.name }}</td>
                                        <td>{{ item.category }}</td>
                                        <td>{{ item.current_stock }}</td>
                                        <td>{{ item.minimum_stock }}</td>
                                        <td>
                                            <span class="badge" :class="getStockStatusClass(item)">
                                                {{ getStockStatus(item) }}
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary">Reorder</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Employee Dashboard Tab -->
    <div v-show="activeTab === 'employee'" class="tab-pane">
        <PageHeader title="Employee Dashboard" pageTitle="HR Overview" />
        
        <!-- Employee Statistics -->
        <div class="row">
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-medium text-muted mb-0">Total Employees</p>
                            </div>
                            <div class="flex-shrink-0">
                                <h5 class="text-success fs-14 mb-0">
                                    <i class="ri-arrow-up-line fs-13 align-middle"></i> +4.5%
                                </h5>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-4">
                            <div>
                                <h4 class="fs-22 fw-semibold ff-secondary mb-4">
                                    <span class="counter-value">{{ formatNumber(employeeStats?.totalEmployees || 0) }}</span>
                                </h4>
                                <a href="#" class="text-decoration-underline">View all employees</a>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-primary-subtle rounded fs-3">
                                    <i class="bx bx-user text-primary"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-medium text-muted mb-0">Present Today</p>
                            </div>
                            <div class="flex-shrink-0">
                                <h5 class="text-success fs-14 mb-0">
                                    <i class="ri-arrow-up-line fs-13 align-middle"></i> +2.1%
                                </h5>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-4">
                            <div>
                                <h4 class="fs-22 fw-semibold ff-secondary mb-4">
                                    <span class="counter-value">{{ formatNumber(employeeStats?.presentToday || 0) }}</span> / {{ formatNumber(employeeStats?.totalEmployees || 0) }}
                                </h4>
                                <a href="#" class="text-decoration-underline">View attendance</a>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-success-subtle rounded fs-3">
                                    <i class="bx bx-check-circle text-success"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-medium text-muted mb-0">On Leave</p>
                            </div>
                            <div class="flex-shrink-0">
                                <h5 class="text-warning fs-14 mb-0">
                                    <i class="ri-arrow-down-line fs-13 align-middle"></i> -1.2%
                                </h5>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-4">
                            <div>
                                <h4 class="fs-22 fw-semibold ff-secondary mb-4">
                                    <span class="counter-value">{{ formatNumber(employeeStats?.onLeave || 0) }}</span>
                                </h4>
                                <a href="#" class="text-decoration-underline">View leave requests</a>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-warning-subtle rounded fs-3">
                                    <i class="bx bx-calendar text-warning"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-medium text-muted mb-0">Departments</p>
                            </div>
                            <div class="flex-shrink-0">
                                <h5 class="text-info fs-14 mb-0">
                                    <i class="ri-arrow-up-line fs-13 align-middle"></i> 0%
                                </h5>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-4">
                            <div>
                                <h4 class="fs-22 fw-semibold ff-secondary mb-4">
                                    <span class="counter-value">{{ formatNumber(employeeStats?.totalDepartments || 0) }}</span>
                                </h4>
                                <a href="#" class="text-decoration-underline">View departments</a>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-info-subtle rounded fs-3">
                                    <i class="bx bx-building text-info"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Employee Charts -->
        <div class="row">
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Employees by Department</h4>
                    </div>
                    <div class="card-body">
                        <apexchart
                            v-if="hasDepartmentData"
                            type="bar"
                            height="350"
                            :options="departmentChartOptions"
                            :series="departmentSeries"
                        ></apexchart>
                        <div v-else class="text-center py-5">
                            <p class="text-muted mb-0">No department data available</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Employee Status</h4>
                    </div>
                    <div class="card-body">
                        <apexchart
                            v-if="hasEmployeeStatusData"
                            type="pie"
                            height="350"
                            :options="employeeStatusOptions"
                            :series="employeeStatusSeries"
                        ></apexchart>
                        <div v-else class="text-center py-5">
                            <p class="text-muted mb-0">No employee status data available</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Attendance & Upcoming Leaves -->
        <div class="row">
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Recent Attendance</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Time In</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="recentAttendance.length === 0">
                                        <td colspan="4" class="text-center py-4">No attendance data available</td>
                                    </tr>
                                    <tr v-for="attendance in recentAttendance" :key="attendance.id">
                                        <td>{{ attendance.employee_name }}</td>
                                        <td>{{ attendance.department }}</td>
                                        <td>{{ attendance.time_in }}</td>
                                        <td>
                                            <span class="badge" :class="getAttendanceStatusClass(attendance.status)">
                                                {{ attendance.status }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Upcoming Leaves</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Leave Type</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="upcomingLeaves.length === 0">
                                        <td colspan="5" class="text-center py-4">No upcoming leaves</td>
                                    </tr>
                                    <tr v-for="leave in upcomingLeaves" :key="leave.id">
                                        <td>{{ leave.employee_name }}</td>
                                        <td>{{ leave.leave_type }}</td>
                                        <td>{{ leave.start_date }}</td>
                                        <td>{{ leave.end_date }}</td>
                                        <td>
                                            <span class="badge" :class="getLeaveStatusClass(leave.status)">
                                                {{ leave.status }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
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
        stats: {
            type: Object,
            default: () => ({})
        },
        charts: {
            type: Object,
            default: () => ({})
        },
        inventoryStats: {
            type: Object,
            default: () => ({})
        },
        inventoryCharts: {
            type: Object,
            default: () => ({})
        },
        lowStockItems: {
            type: Array,
            default: () => []
        },
        employeeStats: {
            type: Object,
            default: () => ({})
        },
        employeeCharts: {
            type: Object,
            default: () => ({})
        },
        recentTransactions: {
            type: Array,
            default: () => []
        }
    },
    components: {
        PageHeader,
        apexchart: VueApexCharts
    },
    data() {
        return {
            activeTab: 'sales',
            recentAttendance: [],
            upcomingLeaves: []
        };
    },
    computed: {
        // Sales Dashboard Computed Properties
        hasSalesData() {
            return this.charts?.monthlySales?.length > 0;
        },
        hasPaymentData() {
            return this.charts?.paymentMethods?.length > 0;
        },
        salesSeries() {
            if (!this.hasSalesData) return [];
            return [{
                name: 'Sales',
                data: this.charts.monthlySales.map(item => item.sales || 0)
            }];
        },
        salesChartOptions() {
            const categories = this.hasSalesData 
                ? this.charts.monthlySales.map(item => item.month || '')
                : [];
            
            return {
                chart: {
                    type: 'bar',
                    height: 350,
                    toolbar: { show: false }
                },
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: '55%',
                        endingShape: 'rounded'
                    },
                },
                dataLabels: { enabled: false },
                stroke: {
                    show: true,
                    width: 2,
                    colors: ['transparent']
                },
                xaxis: {
                    categories: categories
                },
                yaxis: {
                    title: { text: '₱ (thousands)' }
                },
                fill: { opacity: 1 },
                tooltip: {
                    y: {
                        formatter: (val) => "₱" + this.formatNumber(val)
                    }
                },
                colors: ['#556ee6'],
                noData: {
                    text: 'No data available',
                    align: 'center',
                    verticalAlign: 'middle',
                    style: {
                        fontSize: '14px',
                        fontFamily: undefined
                    }
                }
            };
        },
        paymentSeries() {
            return this.hasPaymentData 
                ? this.charts.paymentMethods.map(item => item.total || 0)
                : [];
        },
        paymentChartOptions() {
            return {
                chart: {
                    type: 'pie',
                    toolbar: { show: false }
                },
                labels: this.hasPaymentData 
                    ? this.charts.paymentMethods.map(item => item.method || 'Unknown')
                    : [],
                colors: ['#556ee6', '#34c38f', '#f46a6a', '#f1b44c', '#74788d'],
                legend: { position: 'bottom' },
                tooltip: {
                    y: {
                        formatter: (val) => "₱" + this.formatNumber(val)
                    }
                },
                responsive: [{
                    breakpoint: 480,
                    options: { chart: { width: 200 }, legend: { position: 'bottom' } }
                }],
                noData: {
                    text: 'No data available',
                    align: 'center',
                    verticalAlign: 'middle'
                }
            };
        },

        // Inventory Dashboard Computed Properties
        hasInventoryChartData() {
            return this.inventoryCharts?.stockByCategory?.length > 0;
        },
        hasStockDistributionData() {
            return this.inventoryCharts?.stockDistribution?.length > 0;
        },
        inventorySeries() {
            if (!this.hasInventoryChartData) return [];
            return [{
                name: 'Stock Quantity',
                data: this.inventoryCharts.stockByCategory.map(item => item.quantity || 0)
            }];
        },
        inventoryChartOptions() {
            const categories = this.hasInventoryChartData
                ? this.inventoryCharts.stockByCategory.map(item => item.category || '')
                : [];
            
            return {
                chart: {
                    type: 'bar',
                    height: 350,
                    toolbar: { show: false }
                },
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: '55%',
                        endingShape: 'rounded'
                    },
                },
                dataLabels: { enabled: false },
                stroke: {
                    show: true,
                    width: 2,
                    colors: ['transparent']
                },
                xaxis: {
                    categories: categories
                },
                yaxis: {
                    title: { text: 'Stock Quantity' }
                },
                fill: { opacity: 1 },
                colors: ['#34c38f'],
                noData: {
                    text: 'No data available',
                    align: 'center',
                    verticalAlign: 'middle'
                }
            };
        },
        stockDistributionSeries() {
            return this.hasStockDistributionData
                ? this.inventoryCharts.stockDistribution.map(item => item.percentage || 0)
                : [];
        },
        stockDistributionOptions() {
            return {
                chart: {
                    type: 'donut',
                    toolbar: { show: false }
                },
                labels: this.hasStockDistributionData
                    ? this.inventoryCharts.stockDistribution.map(item => item.status || 'Unknown')
                    : [],
                colors: ['#34c38f', '#f1b44c', '#f46a6a', '#556ee6'],
                legend: { position: 'bottom' },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '70%',
                            labels: {
                                show: true,
                                total: {
                                    show: true,
                                    label: 'Total Items',
                                    formatter: (w) => {
                                        return w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                    }
                                }
                            }
                        }
                    }
                },
                responsive: [{
                    breakpoint: 480,
                    options: { chart: { width: 200 }, legend: { position: 'bottom' } }
                }],
                noData: {
                    text: 'No data available',
                    align: 'center',
                    verticalAlign: 'middle'
                }
            };
        },

        // Employee Dashboard Computed Properties
        hasDepartmentData() {
            return this.employeeCharts?.employeesByDepartment?.length > 0;
        },
        hasEmployeeStatusData() {
            return this.employeeCharts?.employeeStatus?.length > 0;
        },
        departmentSeries() {
            if (!this.hasDepartmentData) return [];
            return [{
                name: 'Employees',
                data: this.employeeCharts.employeesByDepartment.map(item => item.count || 0)
            }];
        },
        departmentChartOptions() {
            const categories = this.hasDepartmentData
                ? this.employeeCharts.employeesByDepartment.map(item => item.department || '')
                : [];
            
            return {
                chart: {
                    type: 'bar',
                    height: 350,
                    toolbar: { show: false }
                },
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: '55%',
                        endingShape: 'rounded'
                    },
                },
                dataLabels: { enabled: false },
                stroke: {
                    show: true,
                    width: 2,
                    colors: ['transparent']
                },
                xaxis: {
                    categories: categories
                },
                yaxis: {
                    title: { text: 'Number of Employees' }
                },
                fill: { opacity: 1 },
                colors: ['#556ee6'],
                noData: {
                    text: 'No data available',
                    align: 'center',
                    verticalAlign: 'middle'
                }
            };
        },
        employeeStatusSeries() {
            return this.hasEmployeeStatusData
                ? this.employeeCharts.employeeStatus.map(item => item.count || 0)
                : [];
        },
        employeeStatusOptions() {
            return {
                chart: {
                    type: 'pie',
                    toolbar: { show: false }
                },
                labels: this.hasEmployeeStatusData
                    ? this.employeeCharts.employeeStatus.map(item => item.status || 'Unknown')
                    : [],
                colors: ['#34c38f', '#f46a6a', '#f1b44c', '#556ee6'],
                legend: { position: 'bottom' },
                tooltip: {
                    y: {
                        formatter: (val) => val + " employees"
                    }
                },
                responsive: [{
                    breakpoint: 480,
                    options: { chart: { width: 200 }, legend: { position: 'bottom' } }
                }],
                noData: {
                    text: 'No data available',
                    align: 'center',
                    verticalAlign: 'middle'
                }
            };
        }
    },
    watch: {
        activeTab(newTab) {
            this.loadTabData(newTab);
        }
    },
    methods: {
        formatNumber(value) {
            if (value === null || value === undefined) return '0';
            return value.toLocaleString();
        },
        loadTabData(tab) {
            switch(tab) {
                case 'employee':
                    this.loadEmployeeData();
                    break;
            }
        },
        loadEmployeeData() {
            this.recentAttendance = [
                { id: 1, employee_name: 'John Smith', department: 'Sales', time_in: '08:45 AM', status: 'On Time' },
                { id: 2, employee_name: 'Maria Garcia', department: 'IT', time_in: '08:50 AM', status: 'On Time' },
                { id: 3, employee_name: 'David Lee', department: 'Sales', time_in: '09:15 AM', status: 'Late' },
                { id: 4, employee_name: 'Sarah Johnson', department: 'HR', time_in: '08:55 AM', status: 'On Time' },
                { id: 5, employee_name: 'Mike Chen', department: 'IT', time_in: '09:30 AM', status: 'Late' }
            ];
            
            this.upcomingLeaves = [
                { id: 1, employee_name: 'Emily Davis', department: 'Sales', leave_type: 'Vacation', start_date: '2024-01-20', end_date: '2024-01-25', status: 'Approved' },
                { id: 2, employee_name: 'James Wilson', department: 'IT', leave_type: 'Sick', start_date: '2024-01-18', end_date: '2024-01-19', status: 'Approved' },
                { id: 3, employee_name: 'Lisa Anderson', department: 'HR', leave_type: 'Personal', start_date: '2024-01-22', end_date: '2024-01-22', status: 'Pending' },
                { id: 4, employee_name: 'Robert Taylor', department: 'Sales', leave_type: 'Vacation', start_date: '2024-01-25', end_date: '2024-01-30', status: 'Pending' }
            ];
        },
        getStockStatus(item) {
            if (item.current_stock <= 0) return 'Out of Stock';
            if (item.current_stock <= item.minimum_stock * 0.5) return 'Critical';
            if (item.current_stock <= item.minimum_stock) return 'Low Stock';
            return 'In Stock';
        },
        getStockStatusClass(item) {
            if (item.current_stock <= 0) return 'bg-danger';
            if (item.current_stock <= item.minimum_stock * 0.5) return 'bg-danger';
            if (item.current_stock <= item.minimum_stock) return 'bg-warning';
            return 'bg-success';
        },
        getAttendanceStatusClass(status) {
            return status === 'On Time' ? 'bg-success' : 'bg-warning';
        },
        getLeaveStatusClass(status) {
            return status === 'Approved' ? 'bg-info' : 'bg-warning';
        },
        openInformation() {
            // Keep existing functionality if needed
        }
    },
    mounted() {
        this.loadTabData(this.activeTab);
    }
}
</script>

<style scoped>
.card-animate {
    transition: all 0.3s ease;
}
.card-animate:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}
.nav-tabs-custom {
    border-bottom: 2px solid #f1f5f9;
}
.nav-tabs-custom .nav-item {
    margin-bottom: -2px;
}
.nav-tabs-custom .nav-link {
    border: none;
    color: #495057;
    font-weight: 500;
    padding: 1rem 1.5rem;
    background-color: transparent;
    transition: all 0.2s;
    cursor: pointer;
}
.nav-tabs-custom .nav-link:hover {
    color: #556ee6;
}
.nav-tabs-custom .nav-link.active {
    color: #556ee6;
    border-bottom: 2px solid #556ee6;
    background-color: transparent;
}
.tab-pane {
    animation: fadeIn 0.5s;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.table th {
    font-weight: 600;
    color: #495057;
    border-top: none;
}
.badge {
    padding: 0.5rem 0.7rem;
    font-weight: 500;
}
</style>
