<template>
    <div class="client-profile-container">
        <div>
            <div class="d-flex gap-2 justify-content-end">
                <button class="create-btn" @click="backToList">
                    <i class="ri-arrow-left-line"></i>
                    Back to List
                </button>
                <button @click="openEdit(selectedCustomer, selectedRow)" variant="info" v-b-tooltip.hover title="Edit"
                    class="create-btn">
                    <i class="ri-pencil-fill"></i>
                    Edit
                </button>
            </div>
        </div>
        <!-- Main Profile Header -->
        <div class="row mt-4">
            <!-- Left Column - Basic Information -->
            <div class="col-md-8">
                <!-- Contact Information Card -->
                <div class="info-card">
                    <div class="profile-header">
                        <div class="profile-avatar-section">
                            <div class="avatar-container">
                                <div class="avatar-preview">
                                    <div class="avatar-placeholder">
                                        <i class="ri-user-line"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="profile-title">
                        <h1>{{ customer.name || 'Customer Name' }}</h1>
                        <div class="profile-badges">
                            <span class="badge badge-primary">
                                Customer
                            </span>
                            <span class="badge" :class="customer.is_regular ? 'badge-primary' : 'badge-secondary'">
                                {{ customer.is_regular === 1 ? 'Regular' : 'Irregular' }}
                            </span>
                            <span class="badge" :class="customer.is_active === 1 ? 'badge-success' : 'badge-danger'">
                                {{ customer.is_active === 1 ? 'Active' : 'Inactive' }}
                            </span>
                            <span v-if="customer.is_blacklisted === 1" class="badge badge-dark">
                                Blacklisted
                            </span>
                        </div>
                    </div>

                    <div class="info-card-body">
                        <div class="info-row">
                            <div class="info-label">Email</div>
                            <div class="info-value">
                                <i class="ri-mail-line"></i>
                                {{ customer.email || 'No email' }}
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Phone</div>
                            <div class="info-value">
                                <i class="ri-phone-line"></i>
                                {{ customer.contact_number || 'No phone number' }}
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Address</div>
                            <div class="info-value">
                                <i class="ri-map-pin-line"></i>
                                {{ customer.address || 'No address' }}
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="info-card-header">
                        <i class="ri-briefcase-line"></i>
                        <h3>Customer Details</h3>
                    </div>
                    <div class="info-card-body">
                        <div class="info-row">
                            <div class="info-label">Status</div>
                            <div class="status-indicator" :class="getStatusClass()">
                                {{ getStatusText() }}
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Created At</div>
                            <div class="info-value">{{ formatDate(customer.created_at) }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Customer ID</div>
                            <div class="info-value">{{ customer.id || 'N/A' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</template>

<script>
export default {
    props: ['customer', 'backToList', 'openEdit', 'selectedCustomer', 'selectedRow'],
    name: 'CustomerDetails',
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
        getStatusClass() {
            if (this.customer.is_blacklisted === 1) return 'status-danger';
            if (this.customer.is_active === 1) return 'status-success';
            return 'status-warning';
        },
        getStatusText() {
            if (this.customer.is_blacklisted === 1) return 'Blacklisted';
            if (this.customer.is_active === 1) return 'Active';
            return 'Inactive';
        }
    }
}
</script>

<style scoped>
.create-btn {
    background: #3D8D7A;
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.8125rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    cursor: pointer;
    transition: all 0.3s ease;

    &:hover {
        background: rgba(255, 255, 255, 0.3);
        color: rgba(12, 78, 57, 0.5);
        border-color: rgba(12, 78, 57, 0.5);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    i {
        font-size: 0.95rem;
    }
}

.client-profile-container {
    background: #f8f9fa;
    min-height: 100vh;
    padding: 24px;
}

/* Profile Header */
.profile-header {
    padding: 24px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    background-color: #edf5f3;
}

.profile-avatar-section {
    display: flex;
    align-items: center;
    gap: 24px;
}

.avatar-container {
    position: relative;
}

.avatar-preview {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid #e0e0e0;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
}

.avatar-placeholder {
    color: white;
    font-size: 48px;
    opacity: 0.9;
}

.profile-title h1 {
    font-size: 28px;
    font-weight: 700;
    color: #2c3e50;
    margin: 0 0 12px 0;
}

.profile-title {
    text-align: center;
}

.profile-badges {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: center;
}

.badge {
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.3px;
    text-transform: uppercase;
}

.badge-primary {
    background: #e3f2fd;
    color: #1976d2;
    border: 1px solid #bbdefb;
}

.badge-secondary {
    background: #f3e5f5;
    color: #7b1fa2;
    border: 1px solid #e1bee7;
}

.badge-success {
    background: #e8f5e9;
    color: #388e3c;
    border: 1px solid #c8e6c9;
}

.badge-danger {
    background: #ffebee;
    color: #d32f2f;
    border: 1px solid #ffcdd2;
}

.badge-dark {
    background: #424242;
    color: white;
    border: 1px solid #616161;
}

.info-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.info-card-header {
    padding: 9px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.info-card-header i {
    font-size: 18px;
    color: #6c757d;
}

.info-card-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
}

.info-card-body {
    padding: 24px;
}

.info-row {
    margin-bottom: 20px;
}

.info-row:last-child {
    margin-bottom: 0;
}

.info-label {
    font-size: 12px;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
    font-weight: 500;
}

.info-value {
    font-size: 16px;
    color: #2c3e50;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-value i {
    color: #adb5bd;
}

.status-indicator {
    display: inline-block;
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.status-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.status-warning {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}
</style>
