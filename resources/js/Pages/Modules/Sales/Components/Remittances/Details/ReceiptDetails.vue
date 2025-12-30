<template>
    <div v-if="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="hide">
        <div class="modal-container" style="max-width: 900px;">
            <div class="modal-header">
                <h2>Receipt Details</h2>
                <button class="close-btn" @click="hide"><i class="ri-close-line"></i></button>
            </div>

            <div class="modal-body">
                <div class="table-responsive" style="max-height: 450px; overflow:auto;">
                    <table class="table table-striped">
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
                                <td>{{ receipt.receipt_number || '-' }}</td>
                                <td>{{ receipt.amount_paid ? formatCurrency(receipt.amount_paid) : '-' }}</td>
                                <td>{{ receipt.receipt_date || '-' }}</td>
                                <td>{{ receipt.customer?.name || '-' }}</td>
                                <td>{{ receipt.payment_mode || '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    props: {
        item: {
            type: Object,
            required: true
        }
    },
    data() {
        return {
            showModal: true
        }
    },
    methods: {
        formatCurrency(val) {
            const num = Number(val);
            if (!isFinite(num)) return val;
            return '\u20B1' + num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        hide() {
            this.showModal = false;
            this.$emit('hide');
        },
    }
}
</script>
<style scoped>
.modal-overlay{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.4);z-index:50;}
.modal-container{background:#fff;border-radius:8px;overflow:hidden;width:100%;padding: 0px;}
.modal-header{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #eee}
.modal-body{padding:16px}
.close-btn{background:transparent;border:0}
.form-actions .btn{min-width:140px}
</style>
