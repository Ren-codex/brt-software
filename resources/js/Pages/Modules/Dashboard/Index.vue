<template>
    <Head title="Dashboard"/>
    <PageHeader title="Dashboard" pageTitle="Overview" />

    <div class="row">
        <!-- Statistics Cards -->
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
                                ₱<span class="counter-value">{{ stats.totalSales.toLocaleString() }}</span>
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
                                ₱<span class="counter-value">{{ stats.totalReceipts.toLocaleString() }}</span>
                            </h4>
                            <a href="#" class="text-decoration-underline">View all receipts</a>
                        </div>
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-info-subtle rounded fs-3">
                                <i class="bx bx-money text-info"></i>
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
                                ₱<span class="counter-value">{{ stats.totalOutstanding.toLocaleString() }}</span>
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
                            <p class="text-uppercase fw-medium text-muted mb-0">Total Customers</p>
                        </div>
                        <div class="flex-shrink-0">
                            <h5 class="text-success fs-14 mb-0">
                                <i class="ri-arrow-up-line fs-13 align-middle"></i> +15.3%
                            </h5>
                        </div>
                    </div>
                    <div class="d-flex align-items-end justify-content-between mt-4">
                        <div>
                            <h4 class="fs-22 fw-semibold ff-secondary mb-4">
                                <span class="counter-value">{{ stats.totalCustomers }}</span>
                            </h4>
                            <a href="#" class="text-decoration-underline">View all customers</a>
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
    </div>

    <!-- Charts Row -->
    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Monthly Sales</h4>
                </div>
                <div class="card-body">
                    <apexchart
                        type="bar"
                        height="350"
                        :options="salesChartOptions"
                        :series="salesSeries"
                    ></apexchart>
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
                        type="pie"
                        height="350"
                        :options="paymentChartOptions"
                        :series="paymentSeries"
                    ></apexchart>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import PageHeader from '@/Shared/Components/PageHeader.vue';
import VueApexCharts from 'vue3-apexcharts';

export default {
    props: ['stats', 'charts'],
    components: {
        PageHeader,
        apexchart: VueApexCharts
    },
    computed: {
        salesSeries() {
            return [{
                name: 'Sales',
                data: this.charts.monthlySales.map(item => item.sales)
            }];
        },
        salesChartOptions() {
            return {
                chart: {
                    type: 'bar',
                    height: 350
                },
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: '55%',
                        endingShape: 'rounded'
                    },
                },
                dataLabels: {
                    enabled: false
                },
                stroke: {
                    show: true,
                    width: 2,
                    colors: ['transparent']
                },
                xaxis: {
                    categories: this.charts.monthlySales.map(item => item.month),
                },
                yaxis: {
                    title: {
                        text: '₱ (thousands)'
                    }
                },
                fill: {
                    opacity: 1
                },
                tooltip: {
                    y: {
                        formatter: function (val) {
                            return "₱" + val.toLocaleString();
                        }
                    }
                }
            };
        },
        paymentSeries() {
            return this.charts.paymentMethods.map(item => item.total);
        },
        paymentChartOptions() {
            return {
                chart: {
                    type: 'pie',
                },
                labels: this.charts.paymentMethods.map(item => item.method),
                colors: ['#008FFB', '#00E396', '#FEB019', '#FF4560', '#775DD0'],
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    y: {
                        formatter: function (val) {
                            return "₱" + val.toLocaleString();
                        }
                    }
                }
            };
        }
    },
    methods: {
        openInformation() {
            // Keep existing functionality if needed
        }
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
</style>
