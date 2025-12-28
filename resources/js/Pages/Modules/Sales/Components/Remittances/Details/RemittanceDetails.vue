<template>
    <tr class="bg-light">
        <td colspan="7" class="p-0">
            <div class="p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-primary mb-0">#{{ item.remittance_no }}</h6>
                    <div>
                        <b-button @click="$emit('delete', item.id)" size="sm" class="btn-success me-1">
                            <i class="ri-check-line"></i>
                        </b-button>
                        <b-button @click="$emit('delete', item.id)" size="sm" class="btn-default me-1">
                            <i class="ri-printer-line"></i>
                        </b-button>
                        <b-button @click="$emit('delete', item.id)" size="sm" class="btn-danger">
                            <i class="ri-delete-bin-line"></i>
                        </b-button>
                    </div>
                </div>
                <!-- Remittance Details -->
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm bg-white">
                            <div class="card-body">
                                <p class="mb-1"><strong>Summary:</strong></p>
                                <template v-if="Array.isArray(item.summary) && item.summary.length">
                                    <table class="table table-sm summary-table mb-0">
                                        <tbody>
                                            <tr v-for="(s, i) in item.summary" :key="i">
                                                <td class="py-1">{{ s }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </template>
                                <template v-else-if="item.summary && typeof item.summary === 'object' && Object.keys(item.summary).length">
                                    <table class="table table-sm summary-table mb-0">
                                        <tbody>
                                            <tr v-for="(val, key) in item.summary" :key="key">
                                                <th class="py-1 align-middle">{{ formatSummaryKey(key) }}</th>
                                                <td class="py-1 align-middle"><span :class="{ 'text-muted': Number(val) === 0 }">{{ formatCurrency(val) }}</span></td>
                                            </tr>
                                            <!-- <tr class="summary-total-row">
                                                <th class="py-1"></th>
                                                <td class="py-1" style="display:block; border-top:1px solid black;"><strong>{{ item.total_amount ? formatCurrency(item.total_amount) : '-' }}</strong></td>
                                            </tr> -->
                                        </tbody>
                                    </table>
                                </template>
                                <template v-else>
                                    <p class="mb-0">{{ item.summary || '-' }}</p>
                                </template>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm bg-white">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-1">
                                    <span class="me-3">
                                        <strong>Total Receipt:</strong> {{ item.receipts.length || '-' }}
                                    </span>
                                    <b-button @click="showReceiptsModal" size="xs" variant="outline-primary" style="border: 1px solid green;">
                                        <i class="ri-eye-line"></i> View Receipts
                                    </b-button>
                                </div><br>
                                <p class="mb-1"><strong>Approved By:</strong> {{ item.approved_by?.username || '-' }}</p>
                                <p class="mb-1"><strong>Date Approved:</strong> {{ item.approved_at || '-' }}</p>
                                <p class="mb-0"><strong>Remarks:</strong> {{ item.notes || '-' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </td>
    </tr>

    <ReceiptDetails :item="item" v-if="showReceipts" @hide="showReceipts = false" />
</template>

<script>
import ReceiptDetails from './ReceiptDetails.vue';

export default {
    components: {
        ReceiptDetails
    },
    props: {
        item: {
            type: Object,
            required: true
        }
    },
    data() {
        return {
            showReceipts: false
        }
    },
    methods: {
        showReceiptsModal() {
            this.showReceipts = true;
        },
        formatSummaryKey(key) {
            return String(key).replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        },
        formatCurrency(val) {
            const num = Number(val);
            if (!isFinite(num)) return val;
            return '\u20B1' + num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    }
}
</script>

<style scoped>
.summary-table th {
    width: 40%;
    font-weight: 600;
    color: #495057;
}
.summary-table td {
    width: 60%;
}
.summary-table th, .summary-table td {
    border: 0;
    padding-top: 0.2rem;
    padding-bottom: 0.2rem;
}
.summary-table .text-muted {
    opacity: 0.9;
}
.summary-total-row th, .summary-total-row td {
    border-top: 1px solid #e9ecef;
    padding-top: 0.4rem;
    padding-bottom: 0.2rem;
}
.summary-total-row th {
    font-weight: 700;
}
.summary-total-row td strong {
    font-weight: 700;
}
</style>
