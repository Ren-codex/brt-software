<template>
    <div class="emp-profile-container">
        <div>
            <div class="d-flex gap-2 justify-content-end">
                <button class="emp-create-btn" @click="backToList">
                    <i class="ri-arrow-left-line"></i>
                    Back to List
                </button>
                <button @click="openEdit(supplier)" variant="info" v-b-tooltip.hover title="Edit"
                    class="emp-create-btn">
                    <i class="ri-pencil-fill"></i>
                    Edit
                </button>
            </div>
        </div>

        <div class="row mt-4">
            <!-- Left Column - Basic Information -->
            <div class="col-md-4">
                <!-- Contact Information Card -->
                <div class="emp-info-card">
                    <div class="emp-info-card-header">
                        <i class="ri-user-line"></i>
                        <h3>Supplier Information</h3>
                    </div>
                    <div class="emp-info-card-body">
                    <div class="emp-profile-title">
                        <h1>{{ supplier.name || 'Supplier Name' }}</h1>
                        <div class="emp-profile-badges">
                            <span class="emp-badge"
                                :class="supplier.is_active === 1 ? 'emp-badge-success' : 'emp-badge-danger'">
                                {{ supplier.is_active === 1 ? 'Active' : 'Inactive' }}
                            </span>
                            <span v-if="supplier.is_blacklisted === 1" class="emp-badge emp-badge-dark">
                                Blacklisted
                            </span>
                        </div>
                    </div>
                    </div>
                    <div class="emp-info-card-body">
                        <div class="emp-info-row">
                            <div class="emp-info-label">Email</div>
                            <div class="emp-info-value">
                                <i class="ri-mail-line"></i>
                                {{ supplier.email || 'No email' }}
                            </div>
                        </div>
                        <div class="emp-info-row">
                            <div class="emp-info-label">Contact Person</div>
                            <div class="emp-info-value">
                                <i class="ri-user-line"></i>
                                {{ supplier.contact_person || '-' }}
                            </div>
                        </div>
                        <div class="emp-info-row">
                            <div class="emp-info-label">Contact Number</div>
                            <div class="emp-info-value">
                                <i class="ri-phone-line"></i>
                                {{ supplier.contact_number || 'No contact number' }}
                            </div>
                        </div>
                        <div class="emp-info-row">
                            <div class="emp-info-label">Address</div>
                            <div class="emp-info-value">
                                <i class="ri-map-pin-line"></i>
                                {{ supplier.address || 'No address' }}
                            </div>
                        </div>
                        <div class="emp-info-row">
                            <div class="emp-info-label">TIN</div>
                            <div class="emp-info-value">
                                <i class="ri-file-list-3-line"></i>
                                {{ supplier.tin || '-' }}
                            </div>
                        </div>
                        <div class="emp-info-row">
                            <div class="emp-info-label">Created At</div>
                            <div class="emp-info-value">{{ formatDate(supplier.created_at) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Statistics & Records -->
            <div class="col-md-8">
                <!-- Purchase Orders Section -->
                <div class="emp-incentives-section">
                    <div class="emp-incentives-card">
                        <div class="emp-incentives-header">
                            <h3>
                                <i class="ri-shopping-cart-line"></i>
                                Purchase Orders
                            </h3>
                        </div>
                        <div class="emp-incentives-stats">
                            <div class="emp-incentives-stat">
                                <div class="emp-stat-label">Total Orders</div>
                                <div class="emp-stat-value">{{ purchaseOrders.length }}</div>
                            </div>
                            <div class="emp-incentives-stat">
                                <div class="emp-stat-label">Pending</div>
                                <div class="emp-stat-value">{{ pendingCount }}</div>
                            </div>
                            <div class="emp-incentives-stat">
                                <div class="emp-stat-label">Completed</div>
                                <div class="emp-stat-value">{{ completedCount }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity Section -->
                <div class="emp-stats-section mt-2">
                    <div class="emp-section-header">
                        <h2 class="emp-section-title">Recent Activity</h2>
                    </div>

                    <div class="emp-loan-summary-card">
                        <div class="emp-loan-main-header">
                            <div class="emp-loan-icon">
                                <i class="ri-history-line"></i>
                            </div>
                            <div class="emp-loan-title-section">
                                <h3 class="emp-loan-title">Supplier History</h3>
                                <div class="emp-loan-period">Record Timeline</div>
                            </div>
                        </div>

                        <div class="emp-loan-main-stats">
                            <div class="emp-primary-stat">
                                <div class="emp-stat-number">{{ supplier.id || 'N/A' }}</div>
                                <div class="emp-stat-label">Supplier ID</div>
                            </div>

                            <div class="emp-progress-section">
                                <div class="emp-progress-header">
                                    <span>Status</span>
                                    <span class="emp-progress-percentage" :class="supplier.is_active === 1 ? 'text-success' : 'text-danger'">
                                        {{ supplier.is_active === 1 ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                                <div class="emp-progress-bar">
                                    <div class="emp-progress-fill" :style="{ width: supplier.is_active === 1 ? '100%' : '0%' }"></div>
                                </div>
                            </div>
                        </div>

                        <div class="emp-loan-footer">
                            <div class="emp-footer-details">
                                <div class="emp-footer-detail">
                                    <span class="emp-footer-label">Last Updated</span>
                                    <span class="emp-footer-value">{{ formatDate(supplier.updated_at) }}</span>
                                </div>
                                <div class="emp-footer-detail">
                                    <span class="emp-footer-label">Created</span>
                                    <span class="emp-footer-value">{{ formatDate(supplier.created_at) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <Create @add="fetch()" @update="fetch()" :dropdowns="dropdowns" ref="create" />
</template>

<script>
import Create from './Modals/Create.vue';

export default {
    components: { Create },
    props: ['supplier', 'backToList', 'dropdowns'],
    name: 'SupplierDetails',
    data() {
        return {
            purchaseOrders: [],
        };
    },
    computed: {
        pendingCount() {
            return this.purchaseOrders.filter(po => po.status === 'pending').length;
        },
        completedCount() {
            return this.purchaseOrders.filter(po => po.status === 'completed').length;
        }
    },
    methods: {
        formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        },
        openEdit(data) {
            this.$refs.create.edit(data);
        },
    }
}
</script>

<style scoped>
.text-success {
    color: #28a745 !important;
}

.text-danger {
    color: #dc3545 !important;
}
</style>
