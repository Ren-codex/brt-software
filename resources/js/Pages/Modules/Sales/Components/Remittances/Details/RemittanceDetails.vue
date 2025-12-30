<template>
    <tr class="bg-light">
        <td colspan="7" class="p-0">
            <div class="p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-primary mb-0">#{{ item.remittance_no }}</h6>
                    <div>
                        <b-button @click.stop="openApprovalModal()" size="sm" class="btn-success me-1" v-if="item.status.id == 1">
                            <i class="ri-check-line"></i>
                        </b-button>
                        <b-button @click.stop="onPrint(item.id)" size="sm" class="btn-default me-1">
                            <i class="ri-printer-line"></i>
                        </b-button>
                        <b-button @click.stop="openDelete(item.id)" size="sm" class="btn-danger" v-if="item.status.id != 7">
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
                                <p class="mb-0"><strong>Remarks:</strong>
                                    &nbsp;<b-badge variant="dark" style="font-size:12px">{{ item.remarks }}</b-badge></p><br>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </td>
    </tr>

    <ReceiptDetails :item="item" v-if="showReceipts" @hide="showReceipts = false" />

    <ApprovalModal :item="item" ref="approvalModal" @reload="reload()" />
</template>

<script>
import ReceiptDetails from './ReceiptDetails.vue';
import ApprovalModal from '../Modals/ApprovalModal.vue';
import Swal from 'sweetalert2';

export default {
    components: {
        ReceiptDetails,
        ApprovalModal,
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
        openApprovalModal() {
            this.$refs.approvalModal.show();
        },
        formatSummaryKey(key) {
            return String(key).replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        },
        formatCurrency(val) {
            const num = Number(val);
            if (!isFinite(num)) return val;
            return '\u20B1' + num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        async openDelete(id){
            const result = await Swal.fire({
                title: 'Are you sure?',
                text: 'You want to delete this remittance?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!'
            });

            if (result.isConfirmed) {
                const url = `/remittances/${id}`;
                try {
                    const response = await axios.delete(url, { headers: { 'X-Request-Origin': 'remittance-delete' } });
                    this.reload();
                    Swal.fire(
                        'Deleted!',
                        'Remittance deleted successfully!',
                        'success'
                    );
                } catch (error) {
                    console.error('remittance delete error', error);
                    // If server rejects DELETE with 405, retry using POST method spoofing
                    if (error && error.response && error.response.status === 405) {
                        console.log('DELETE returned 405 — retrying with POST _method=DELETE');
                        try {
                            const postResponse = await axios.post(url, { _method: 'DELETE' }, { headers: { 'X-Request-Origin': 'remittance-delete' } });
                            console.log('remittance delete via POST response', postResponse);
                            this.reload();
                            Swal.fire(
                                'Deleted!',
                                'Remittance deleted successfully!',
                                'success'
                            );
                            return;
                        } catch (postErr) {
                            console.error('remittance delete via POST error', postErr);
                            if (postErr && postErr.response && postErr.response.config) {
                                console.log('postErr response config url:', postErr.response.config.url);
                                console.log('postErr response config method:', postErr.response.config.method);
                            }
                        }
                    } else {
                        if (error && error.response && error.response.config) {
                            console.log('error response config url:', error.response.config.url);
                            console.log('error response config method:', error.response.config.method);
                        }
                    }

                    Swal.fire(
                        'Error!',
                        'Failed to delete remittance.',
                        'error'
                    );
                }
            }
        },
        onPrint(id){
            window.open(`/remittances/${id}?option=print&type=remittance`);
        },
        reload(){
            this.$emit('reload');
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
