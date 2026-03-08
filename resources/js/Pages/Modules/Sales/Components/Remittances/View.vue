<template>
    <div v-if="item" class="employee-details-page remittance-details-page">
        <div class="library-card">
            <div class="library-card-header">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="header-icon">
                            <i class="ri-bank-card-line"></i>
                        </div>
                        <div>
                            <h4 class="header-title mb-1">Remittance Details</h4>
                            <p class="header-subtitle mb-0">View and manage remittance details</p>
                        </div>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <button v-if="item.status?.slug === 'open'" class="create-btn" @click="openApprovalModal">
                            <i class="ri-check-line"></i>
                            <span>Approval</span>
                        </button>
                        <button v-if="item.status?.slug === 'open'" class="create-btn" @click="openDelete(item.id)">
                            <i class="ri-delete-bin-line"></i>
                        </button>
                        <button class="create-btn" @click="onPrint(item.id)">
                            <i class="ri-printer-line"></i>
                        </button>
                        <button class="create-btn" @click="$emit('back')">
                            <i class="ri-arrow-left-line"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="library-card-body">
                <div class="details-grid">
                <aside class="details-sidebar">
                    <div class="profile-card">
                        <div class="profile-subcard" style="margin-top: -10px">
                            <div class="details-card-header">
                                <h3>
                                    <i class="ri-bar-chart-box-line"></i>
                                    Overview
                                </h3>
                            </div>
                            <div class="profile-subcard-body">
                                <div class="sidebar-stat-grid">
                                    <div class="details-stat">
                                        <div class="details-stat-label">Total Amount</div>
                                        <div class="details-stat-value">{{ formatCurrency(item.total_amount || 0) }}</div>
                                    </div>
                                    <div class="details-stat">
                                        <div class="details-stat-label">Receipts</div>
                                        <div class="details-stat-value">{{ receiptCount }}
                                            <span class="status-chip" :class="getStatusClass(item.status?.slug)">
                                                {{ item.status?.name || '-' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="profile-info-list">
                            <div class="profile-info-item">
                                <div class="profile-label">Remittance No.</div>
                                <div class="profile-value">
                                    {{ item.remittance_no || item.id }}
                                </div>
                            </div>
                            <div class="profile-info-item">
                                <div class="profile-label">Date</div>
                                <div class="profile-value">
                                    <i class="ri-calendar-line"></i>
                                    {{ formatDate(item.date || item.remittance_date) }}
                                </div>
                            </div>
                            <div class="profile-info-item">
                                <div class="profile-label">Collector</div>
                                <div class="profile-value">
                                    <i class="ri-user-line"></i>
                                    {{ item.created_by?.fullname || '-' }}
                                </div>
                            </div>
                            <div class="profile-info-item">
                                <div class="profile-label">Approved By</div>
                                <div class="profile-value">
                                    <i class="ri-shield-user-line"></i>
                                    {{ item.approved_by?.fullname || '-' }}
                                </div>
                            </div>
                            <div class="profile-info-item">
                                <div class="profile-label">Approved At</div>
                                <div class="profile-value">
                                    <i class="ri-time-line"></i>
                                    {{ formatDate(item.approved_at) }}
                                </div>
                            </div>
                        </div>

                        <div class="profile-subcard">
                            <div class="profile-subcard-header">
                                <i class="ri-file-text-line"></i>
                                <h3>Remarks</h3>
                            </div>
                            <div class="profile-subcard-body">
                                <div class="remarks-card">
                                    {{ item.remarks || 'No remarks provided.' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>

                <section class="details-main">
                    <div class="details-card">
                        <div class="details-card-header">
                            <h3>
                                <i class="ri-pie-chart-2-line"></i>
                                Summary
                            </h3>
                        </div>
                        <template v-if="Array.isArray(item.summary) && item.summary.length">
                            <div class="info-panel-grid info-panel-grid-summary">
                                <div v-for="(summaryItem, index) in item.summary" :key="index" class="info-panel-item">
                                    <span>Summary {{ index + 1 }}</span>
                                    <strong>{{ summaryItem }}</strong>
                                </div>
                            </div>
                        </template>
                        <template v-else-if="item.summary && typeof item.summary === 'object' && Object.keys(item.summary).length">
                            <div class="info-panel-grid info-panel-grid-summary">
                                <div v-for="(value, key) in item.summary" :key="key" class="info-panel-item">
                                    <span>{{ formatSummaryKey(key) }}</span>
                                    <strong :class="{ 'muted-value': Number(value) === 0 }">
                                        {{ formatSummaryValue(value) }}
                                    </strong>
                                </div>
                            </div>
                        </template>
                        <div v-else class="loan-history-empty">
                            {{ item.summary || 'No summary available.' }}
                        </div>
                    </div>

                    <div class="details-card">
                        <div class="details-card-header details-card-header-between">
                            <h3>
                                <i class="ri-receipt-line"></i>
                                Receipts
                            </h3>
                            <span class="loan-history-count">{{ receiptCount }} record(s)</span>
                        </div>

                        <div v-if="receiptCount" class="loan-history-table-wrap">
                            <table class="loan-history-table">
                                <thead>
                                    <tr>
                                        <th>Receipt No.</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th>Customer</th>
                                        <th>Payment Mode</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="receipt in item.receipts" :key="receipt.id">
                                        <td>
                                            <div class="loan-history-loan-cell">
                                                <strong>{{ receipt.receipt_number || '-' }}</strong>
                                            </div>
                                        </td>
                                        <td>{{ formatCurrency(receipt.amount_paid) }}</td>
                                        <td>{{ formatDate(receipt.receipt_date) }}</td>
                                        <td>{{ receipt.customer?.name || '-' }}</td>
                                        <td>
                                            <span class="payment-badge" :class="getPaymentClass(receipt.payment_mode)">
                                                {{ receipt.payment_mode || '-' }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="loan-history-empty">
                            No receipts found.
                        </div>
                    </div>
                </section>
                </div>
            </div>
        </div>
        <ApprovalModal :item="item" ref="approvalModal" @reload="reload" />
    </div>
</template>

<script>
import ApprovalModal from './Modals/ApprovalModal.vue';
import Swal from 'sweetalert2';

export default {
    name: 'RemittanceView',
    components: {
        ApprovalModal,
    },
    props: {
        item: {
            type: Object,
            required: true,
        },
        dropdowns: {
            type: Object,
            default: () => ({}),
        },
    },
    emits: ['back', 'reload'],
    computed: {
        receiptCount() {
            return Array.isArray(this.item?.receipts) ? this.item.receipts.length : 0;
        },
    },
    methods: {
        openApprovalModal() {
            this.$refs.approvalModal.show();
        },
        formatSummaryKey(key) {
            if (!key) return '';

            return String(key)
                .split('_')
                .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
                .join(' ');
        },
        formatSummaryValue(value) {
            const num = Number(value);
            return Number.isFinite(num) ? this.formatCurrency(num) : value || '-';
        },
        formatCurrency(val) {
            const num = Number(val);
            if (!isFinite(num)) return val;

            return '\u20B1' + num.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },
        formatRemittanceType(type) {
            return String(type || '').toLowerCase() === 'credit' ? 'Credit Sales' : 'Cash Sales';
        },
        formatDate(value) {
            if (!value) return '-';

            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return value;

            return date.toLocaleDateString(undefined, {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
            });
        },
        getStatusClass(slug) {
            const value = String(slug || '').toLowerCase();

            if (['approved', 'completed', 'liquidated'].includes(value)) return 'emp-status-success';
            if (['rejected', 'disapproved', 'cancelled'].includes(value)) return 'emp-status-danger';
            if (['open', 'pending'].includes(value)) return 'emp-status-warning';

            return 'profile-badge-neutral';
        },
        statusBadgeClass(slug) {
            const statusClass = this.getStatusClass(slug);

            if (statusClass === 'emp-status-success') return 'profile-badge-success';
            if (statusClass === 'emp-status-danger') return 'profile-badge-danger';
            if (statusClass === 'emp-status-warning') return 'profile-badge-warning';

            return 'profile-badge-neutral';
        },
        async openDelete(id) {
            const result = await Swal.fire({
                title: 'Are you sure?',
                text: 'You want to delete this remittance?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!'
            });

            if (!result.isConfirmed) return;

            const url = `/remittances/${id}`;

            try {
                await axios.delete(url, { headers: { 'X-Request-Origin': 'remittance-delete' } });
                this.reload();
                Swal.fire('Deleted!', 'Remittance deleted successfully!', 'success');
            } catch (error) {
                if (error && error.response && error.response.status === 405) {
                    try {
                        await axios.post(url, { _method: 'DELETE' }, { headers: { 'X-Request-Origin': 'remittance-delete' } });
                        this.reload();
                        Swal.fire('Deleted!', 'Remittance deleted successfully!', 'success');
                        return;
                    } catch (postErr) {
                        console.error('remittance delete via POST error', postErr);
                    }
                } else {
                    console.error('remittance delete error', error);
                }

                Swal.fire('Error!', 'Failed to delete remittance.', 'error');
            }
        },
        onPrint(id) {
            window.open(`/remittances/${id}?option=print&type=remittance`);
        },
        reload() {
            this.$emit('reload');
        },
        getPaymentClass(mode) {
            return String(mode || '').toLowerCase();
        },
    }
};
</script>

<style scoped>
.employee-details-page {
    --ink-900: #102723;
    --ink-700: #35524d;
    --ink-500: #5c7974;
    --line-200: #d2e4df;
    --mint-700: #1a7e67;
    --mint-500: #2fa485;
    --surface: #ffffff;
    --surface-soft: #f7fcfa;
    --danger-600: #c44f47;
    max-width: 1360px;
    margin: 0 auto;
}

.details-btn {
    min-height: 40px;
    padding: 0 16px;
    border-radius: 10px;
    border: 1px solid transparent;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 0.86rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
}

.details-btn-primary {
    color: #fff;
    background: linear-gradient(125deg, var(--mint-500) 0%, var(--mint-700) 100%);
    box-shadow: 0 10px 20px rgba(28, 120, 99, 0.28);
}

.details-btn-danger {
    color: #fff;
    background: linear-gradient(135deg, #d86f67 0%, #b94b44 100%);
    box-shadow: 0 10px 20px rgba(185, 75, 68, 0.24);
}

.details-btn-primary:hover,
.details-btn-danger:hover {
    transform: translateY(-1px);
}

.details-btn-outline {
    color: var(--ink-700);
    background: #fff;
    border-color: #ceded9;
}

.details-btn-outline:hover {
    background: #f8fdfb;
}

.details-grid {
    display: grid;
    grid-template-columns: minmax(310px, 390px) minmax(0, 1fr);
    gap: 18px;
    align-items: start;
    background: white;
}

.details-sidebar {
    position: sticky;
    top: 12px;
}

.profile-card {
    border: 1px solid var(--line-200);
    border-radius: 22px;
    padding: 20px;
    background: linear-gradient(160deg, #f9fffd 0%, #eff9f6 100%);
    box-shadow: 0 12px 32px rgba(28, 64, 56, 0.08);
}

.profile-avatar-wrap {
    display: flex;
    justify-content: center;
    margin-bottom: 12px;
    position: relative;
}

.profile-avatar {
    width: 136px;
    height: 136px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid #fff;
    box-shadow: 0 10px 28px rgba(14, 47, 41, 0.2);
}

.profile-avatar-placeholder {
    width: 100%;
    height: 100%;
    display: grid;
    place-items: center;
    color: white;
    font-size: 42px;
    background: linear-gradient(145deg, #2d947c 0%, #1d6454 100%);
}

.profile-heading {
    text-align: center;
    margin-bottom: 14px;
}

.profile-heading h2 {
    margin: 0;
    font-size: 1.22rem;
    color: var(--ink-900);
}

.profile-badges {
    margin-top: 10px;
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 6px;
}

.profile-badge {
    padding: 4px 9px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
}

.profile-badge-primary {
    background: #d9f0e8;
    color: #1b6f5a;
}

.profile-badge-neutral {
    background: #edf0f4;
    color: #4f6072;
}

.profile-badge-success,
.emp-status-success {
    background: #dcf6eb;
    color: #157856;
}

.profile-badge-danger,
.emp-status-danger {
    background: #ffe4e0;
    color: #b04740;
}

.profile-badge-warning,
.emp-status-warning {
    color: #9a6b19;
    background: #fff1d8;
}

.profile-info-list {
    padding-top: 12px;
}

.profile-info-item {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    padding: 7px 0;
}

.profile-label {
    color: var(--ink-500);
    font-size: 0.8rem;
    font-weight: 600;
}

.profile-value {
    color: var(--ink-900);
    font-size: 0.82rem;
    font-weight: 600;
    text-align: right;
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    gap: 6px;
}

.profile-highlight {
    color: #176954;
}

.profile-subcard {
    margin-top: 12px;
    padding-top: 12px;
}

.profile-subcard-header {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-bottom: 6px;
    color: #1a6e5a;
}

.profile-subcard-header h3 {
    margin: 0;
    font-size: 0.9rem;
}

.remarks-card {
    border: 1px solid #dbe9e5;
    border-radius: 14px;
    padding: 12px;
    background: rgba(255, 255, 255, 0.7);
    color: var(--ink-700);
    font-size: 0.82rem;
    line-height: 1.55;
}

.details-main {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.details-card {
    border: 1px solid #dbe9e5;
    border-radius: 20px;
    padding: 18px;
    background: var(--surface);
    box-shadow: 0 8px 28px rgba(22, 58, 50, 0.06);
}

.details-card-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
    color: var(--ink-900);
}

.details-card-header h3 {
    margin: 0;
    font-size: 1.04rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.details-card-header-between {
    justify-content: space-between;
}

.details-stat-grid,
.info-panel-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.info-panel-grid-summary {
    grid-template-columns: repeat(4, minmax(0, 1fr));
    align-items: stretch;
}

.sidebar-stat-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
}

.details-stat,
.info-panel-item {
    border: 1px solid #e2eeeb;
    border-radius: 15px;
    padding: 13px;
    background: var(--surface-soft);
}

.details-stat-label,
.info-panel-item span {
    color: var(--ink-500);
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.details-stat-value,
.info-panel-item strong {
    margin-top: 8px;
    font-size: 1.18rem;
    font-weight: 700;
    color: var(--ink-900);
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.info-panel-item strong {
    display: block;
    font-size: 0.9rem;
}

.status-chip {
    padding: 5px 10px;
    border-radius: 999px;
    font-size: 0.74rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.loan-history-count {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--ink-700);
    background: #edf7f4;
    border: 1px solid #d6e8e2;
    border-radius: 999px;
    padding: 4px 9px;
}

.loan-history-table-wrap {
    margin-top: 10px;
    border: 1px solid #deebe7;
    border-radius: 12px;
    overflow: auto;
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.loan-history-table-wrap::-webkit-scrollbar {
    display: none;
}

.loan-history-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 700px;
}

.loan-history-table th,
.loan-history-table td {
    padding: 9px 10px;
    font-size: 0.78rem;
    border-bottom: 1px solid #e6f0ed;
}

.loan-history-table th {
    background: #f6fcfa;
    color: var(--ink-700);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.loan-history-table tbody tr:hover {
    background: #f9fdfb;
}

.loan-history-loan-cell {
    display: flex;
    flex-direction: column;
    gap: 1px;
}

.loan-history-loan-cell strong {
    color: var(--ink-900);
}

.loan-history-loan-cell span,
.muted-value {
    color: var(--ink-500);
}

.loan-history-empty {
    margin-top: 10px;
    border: 1px dashed #d2e4df;
    border-radius: 12px;
    padding: 14px;
    color: var(--ink-500);
    font-size: 0.82rem;
    text-align: center;
    background: #f8fdfb;
}

.payment-badge {
    display: inline-block;
    padding: 0.25rem 1rem;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 500;
}

.payment-badge.cash {
    background: #c4dad2;
    color: #2c6b5c;
}

.payment-badge.credit {
    background: #fff3e0;
    color: #f39c12;
}

.payment-badge.bank,
.payment-badge.check {
    background: #e3f2fd;
    color: #1976d2;
}

@media (max-width: 1140px) {
    .details-grid {
        grid-template-columns: 1fr;
    }

    .details-sidebar {
        position: static;
    }

    .details-stat-grid,
    .info-panel-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .info-panel-grid-summary {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}

@media (max-width: 700px) {
    .employee-details-page {
        padding: 12px;
    }

    .details-btn {
        width: 100%;
        justify-content: center;
    }

    .details-card-header-between {
        flex-direction: column;
        align-items: flex-start;
        gap: 9px;
    }

    .details-stat-grid,
    .info-panel-grid {
        grid-template-columns: 1fr;
    }

    .info-panel-grid-summary {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}
</style>
