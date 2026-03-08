<template>
    <div v-if="showModal" class="loan-modal-overlay" @click.self="close">
        <div class="loan-modal-card">
            <div class="loan-modal-header">
                <h3>
                    <i class="ri-bank-card-line"></i>
                    Loan Details
                </h3>
                <button type="button" class="loan-modal-close" @click="close">
                    <i class="ri-close-line"></i>
                </button>
            </div>

            <div v-if="loan" class="loan-modal-body">
                <div class="loan-history">
                    <div class="loan-history-header">
                        <h4>Payment History</h4>
                    </div>

                    <div v-if="paymentHistory.length" class="loan-history-table-wrap">
                        <table class="loan-history-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Paid Period</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="payment in paymentHistory" :key="payment.id">
                                    <td>{{ formatDate(paymentDateValue(payment)) }}</td>
                                    <td>{{ payment.paid_date || '-' }}</td>
                                    <td>{{ formatCurrency(payment.amount) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-else class="loan-empty-state">
                        No loan payments recorded yet.
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    data() {
        return {
            showModal: false,
            loan: null,
        };
    },
    computed: {
        paymentHistory() {
            if (!Array.isArray(this.loan?.payments)) {
                return [];
            }

            return [...this.loan.payments].sort((a, b) => {
                const aDate = new Date(this.paymentDateValue(a) || 0).getTime();
                const bDate = new Date(this.paymentDateValue(b) || 0).getTime();
                return bDate - aDate;
            });
        },
        termUnitFactor() {
            const termMonths = this.toNumber(this.loan?.term_months, 0);
            const remainingUnits = this.toNumber(this.loan?.remaining_term_to_pay, 0);
            if (remainingUnits > termMonths) {
                return 2;
            }
            return 1;
        },
        unpaidMonths() {
            const remainingUnits = this.toNumber(this.loan?.remaining_term_to_pay, null);
            if (remainingUnits === null) return '-';
            const value = Math.max(remainingUnits / this.termUnitFactor, 0);
            return Number.isInteger(value) ? value : Number(value.toFixed(1));
        },
        totalTermUnits() {
            return this.toNumber(this.loan?.term_months, 0) * this.termUnitFactor;
        },
        paymentScheduleLabel() {
            return this.termUnitFactor === 2 ? 'Semi-monthly' : 'Monthly';
        },
        paymentTermsLabel() {
            const months = this.toNumber(this.loan?.term_months, 0);
            const units = this.totalTermUnits;
            if (!months || !units) return 'No term set';
            return `${months} month(s) / ${units} payroll term(s)`;
        },
    },
    methods: {
        open(loanData) {
            if (!loanData) return;
            this.loan = { ...loanData };
            this.showModal = true;
        },
        close() {
            this.showModal = false;
            this.loan = null;
        },
        formatCurrency(amount) {
            if (amount === null || amount === undefined || amount === '') {
                return '-';
            }

            const value = Number(amount);
            if (!Number.isFinite(value)) {
                return '-';
            }

            return new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP'
            }).format(value);
        },
        formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            if (Number.isNaN(date.getTime())) return dateString;

            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        },
        toNumber(value, fallback = 0) {
            if (value === null || value === undefined || value === '') {
                return fallback;
            }

            const numeric = Number(value);
            return Number.isFinite(numeric) ? numeric : fallback;
        },
        extractPaymentDateCandidates(value) {
            if (!value) return [];

            const text = String(value).trim();
            if (!text) return [];

            const monthToken = '(?:Jan(?:uary)?\\.?|Feb(?:ruary)?\\.?|Mar(?:ch)?\\.?|Apr(?:il)?\\.?|May|Jun(?:e)?\\.?|Jul(?:y)?\\.?|Aug(?:ust)?\\.?|Sep(?:t(?:ember)?)?\\.?|Oct(?:ober)?\\.?|Nov(?:ember)?\\.?|Dec(?:ember)?\\.?)';
            const sameMonthRangeRegex = new RegExp(`\\b(${monthToken})\\s+(\\d{1,2})\\s*-\\s*(\\d{1,2}),\\s*(\\d{4})\\b`, 'gi');
            const crossMonthRangeRegex = new RegExp(`\\b(${monthToken})\\s+(\\d{1,2})\\s*-\\s*(${monthToken})\\s+(\\d{1,2}),\\s*(\\d{4})\\b`, 'gi');

            const rangeEndDates = [];
            for (const match of text.matchAll(crossMonthRangeRegex)) {
                const endMonth = match[3];
                const endDay = match[4];
                const year = match[5];
                rangeEndDates.push(`${endMonth} ${endDay}, ${year}`);
            }
            for (const match of text.matchAll(sameMonthRangeRegex)) {
                const month = match[1];
                const endDay = match[3];
                const year = match[4];
                rangeEndDates.push(`${month} ${endDay}, ${year}`);
            }

            const isoMatches = text.match(/\b\d{4}-\d{2}-\d{2}\b/g) || [];
            const slashMatches = text.match(/\b\d{1,2}\/\d{1,2}\/\d{2,4}\b/g) || [];
            const dashMatches = text.match(/\b\d{1,2}-\d{1,2}-\d{2,4}\b/g) || [];
            const monthNameMatches = text.match(
                /\b(?:Jan(?:uary)?\.?|Feb(?:ruary)?\.?|Mar(?:ch)?\.?|Apr(?:il)?\.?|May|Jun(?:e)?\.?|Jul(?:y)?\.?|Aug(?:ust)?\.?|Sep(?:t(?:ember)?)?\.?|Oct(?:ober)?\.?|Nov(?:ember)?\.?|Dec(?:ember)?\.?)\s+\d{1,2}(?:,\s*|\s+)\d{4}\b/gi
            ) || [];

            const candidates = [...new Set([...rangeEndDates, ...isoMatches, ...slashMatches, ...dashMatches, ...monthNameMatches])];
            return candidates.length ? candidates : [text];
        },
        parseSinglePaymentDate(value) {
            if (!value) return null;

            const candidates = this.extractPaymentDateCandidates(value);
            const parsedDates = candidates
                .map((candidate) => new Date(candidate))
                .filter((date) => !Number.isNaN(date.getTime()))
                .sort((a, b) => b.getTime() - a.getTime());

            return parsedDates.length ? parsedDates[0] : null;
        },
        paymentDateValue(payment) {
            if (!payment) return null;

            return (
                this.parseSinglePaymentDate(payment.paid_date) ||
                this.parseSinglePaymentDate(payment.payment_date) ||
                this.parseSinglePaymentDate(payment.created_at) ||
                null
            );
        },
    },
};
</script>

<style scoped>
.loan-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 1200;
    background: rgba(7, 22, 19, 0.58);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
}

.loan-modal-card {
    width: min(920px, 100%);
    max-height: calc(100vh - 32px);
    overflow: hidden;
    border-radius: 18px;
    border: 1px solid #d4e4df;
    background: #fff;
    box-shadow: 0 20px 52px rgba(10, 35, 30, 0.25);
    display: flex;
    flex-direction: column;
}

.loan-modal-header {
    padding: 14px 16px;
    border-bottom: 1px solid #e1ece9;
    background: #f4fbf8;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.loan-modal-header h3 {
    margin: 0;
    font-size: 0.98rem;
    color: #102723;
    display: inline-flex;
    align-items: center;
    gap: 7px;
}

.loan-modal-close {
    border: 0;
    background: transparent;
    width: 30px;
    height: 30px;
    border-radius: 8px;
    color: #35524d;
    cursor: pointer;
}

.loan-modal-close:hover {
    background: #e9f3ef;
}

.loan-modal-body {
    padding: 16px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.loan-summary-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
}

.loan-terms-card {
    border: 1px solid #d6e8e2;
    border-radius: 12px;
    background: linear-gradient(145deg, #f7fdfb 0%, #eef8f4 100%);
    padding: 10px 12px;
}

.loan-terms-title {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #1a5e4d;
    font-size: 0.85rem;
    font-weight: 700;
}

.loan-terms-meta {
    margin-top: 8px;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.loan-terms-chip {
    min-height: 26px;
    padding: 0 9px;
    border-radius: 999px;
    border: 1px solid #cde1da;
    background: #ffffff;
    color: #2c4f48;
    font-size: 0.76rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
}

.loan-summary-item {
    border: 1px solid #e0ece8;
    border-radius: 11px;
    background: #fbfefd;
    padding: 9px 10px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.loan-summary-item span {
    font-size: 0.72rem;
    color: #5c7974;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.04em;
}

.loan-summary-item strong {
    font-size: 0.9rem;
    color: #102723;
}

.loan-history {
    border: 1px solid #e0ece8;
    border-radius: 12px;
    overflow: hidden;
}

.loan-history-header {
    padding: 10px 12px;
    border-bottom: 1px solid #e0ece8;
    background: #f8fdfb;
}

.loan-history-header h4 {
    margin: 0;
    font-size: 0.88rem;
    color: #1a3d37;
}

.loan-history-table-wrap {
    overflow-x: auto;
}

.loan-history-table {
    width: 100%;
    border-collapse: collapse;
}

.loan-history-table th,
.loan-history-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #edf4f1;
    text-align: left;
    font-size: 0.83rem;
}

.loan-history-table th {
    color: #4f6c66;
    font-weight: 700;
    background: #fbfefd;
}

.loan-history-table td {
    color: #17332f;
}

.loan-empty-state {
    padding: 20px 12px;
    text-align: center;
    color: #5c7974;
    font-size: 0.84rem;
}

@media (max-width: 900px) {
    .loan-summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 640px) {
    .loan-summary-grid {
        grid-template-columns: 1fr;
    }
}
</style>
