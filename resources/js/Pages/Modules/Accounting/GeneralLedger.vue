<template>
    <div>
        <PageHeader title="Accounting Module" pageTitle="General Ledger" />

        <div class="inventory-container">
            <AccountingSidebar active-tab="general_ledger" :stats="stats" />

            <div class="inventory-main">
                <div class="report-shell">
                    <div class="report-header">
                        <div>
                            <p class="report-kicker mb-1">Accounting Detail</p>
                            <h3 class="report-title mb-1">General Ledger</h3>
                            <p class="report-subtitle mb-0">
                                Review account balances and recent posted lines from the live accounting ledger.
                            </p>
                        </div>
                    </div>

                    <div class="summary-grid">
                        <div v-for="card in summaryCards" :key="card.title" class="summary-card">
                            <div class="summary-icon">
                                <i :class="card.icon"></i>
                            </div>
                            <div>
                                <p class="summary-label">{{ card.title }}</p>
                                <h4 class="summary-value">{{ card.value }}</h4>
                                <p class="summary-note mb-0">{{ card.description }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="!dataReady" class="empty-panel">
                        <i class="ri-information-line"></i>
                        <span>Accounting tables are not ready yet. Run the accounting migrations to populate live ledger data.</span>
                    </div>

                    <template v-else>
                        <div class="report-panel">
                            <div class="panel-head">
                                <h5 class="panel-title">Account Balances</h5>
                                <p class="panel-subtitle mb-0">Live debit, credit, and balance movement by account.</p>
                            </div>

                            <div class="table-responsive">
                                <table class="table report-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Account</th>
                                            <th>Type</th>
                                            <th>Debit</th>
                                            <th>Credit</th>
                                            <th>Balance</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="account in accountBalances" :key="account.id">
                                            <td>{{ account.code }}</td>
                                            <td>{{ account.name }}</td>
                                            <td>{{ formatLabel(account.type) }}</td>
                                            <td>{{ account.debit_total_formatted }}</td>
                                            <td>{{ account.credit_total_formatted }}</td>
                                            <td>{{ account.balance_formatted }}</td>
                                            <td>
                                                <span class="status-chip" :class="account.is_active ? 'posted' : 'reversed'">
                                                    {{ account.is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="report-panel mt-4">
                            <div class="panel-head">
                                <h5 class="panel-title">Recent Ledger Lines</h5>
                                <p class="panel-subtitle mb-0">Latest detailed postings captured in the accounting journal.</p>
                            </div>

                            <div class="table-responsive">
                                <table class="table report-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Journal No.</th>
                                            <th>Type</th>
                                            <th>Account</th>
                                            <th>Line Type</th>
                                            <th>Amount</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="line in recentLines" :key="line.id">
                                            <td>{{ line.entry_date }}</td>
                                            <td>{{ line.journal_number }}</td>
                                            <td>{{ line.entry_type }}</td>
                                            <td>{{ line.account_code }} - {{ line.account_name }}</td>
                                            <td>{{ line.line_type }}</td>
                                            <td>{{ line.amount }}</td>
                                            <td>{{ line.description || '-' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import PageHeader from "@/Shared/Components/PageHeader.vue";
import AccountingSidebar from "@/Pages/Modules/Accounting/Components/AccountingSidebar.vue";

export default {
    components: { PageHeader, AccountingSidebar },
    props: {
        stats: Object,
        dataReady: Boolean,
        summaryCards: Array,
        accountBalances: Array,
        recentLines: Array,
    },
    methods: {
        formatLabel(value) {
            return String(value || "")
                .replace(/_/g, " ")
                .replace(/\b\w/g, (char) => char.toUpperCase());
        },
    },
};
</script>

<style scoped>
.report-shell {
    display: grid;
    gap: 1.5rem;
}

.report-header,
.report-panel,
.empty-panel {
    border: 1px solid rgba(61, 141, 122, 0.12);
    border-radius: 24px;
    background: linear-gradient(180deg, #ffffff 0%, #f7fbfa 100%);
    box-shadow: 0 14px 30px rgba(28, 49, 45, 0.08);
}

.report-header,
.report-panel {
    padding: 1.5rem;
}

.report-kicker {
    color: #3d8d7a;
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.report-title,
.panel-title {
    color: #20413a;
    font-weight: 700;
}

.report-subtitle,
.panel-subtitle,
.summary-note {
    color: #648b74;
}

.summary-grid {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.summary-card {
    display: flex;
    gap: 0.9rem;
    padding: 1.1rem;
    border-radius: 20px;
    border: 1px solid rgba(61, 141, 122, 0.12);
    background: #ffffff;
    box-shadow: 0 10px 24px rgba(28, 49, 45, 0.06);
}

.summary-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: #e4f1ed;
    color: #2f7666;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.summary-label {
    margin-bottom: 0.2rem;
    color: #648b74;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.summary-value {
    margin-bottom: 0.25rem;
    color: #20413a;
    font-weight: 700;
}

.panel-head {
    margin-bottom: 1rem;
}

.report-table thead th {
    background: #edf5f2;
    color: #527267;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
}

.empty-panel {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    color: #7b6a2f;
    background: linear-gradient(180deg, #fff8e7 0%, #fffdf7 100%);
}

.status-chip {
    display: inline-flex;
    align-items: center;
    padding: 0.35rem 0.7rem;
    border-radius: 999px;
    font-size: 0.76rem;
    font-weight: 700;
}

.status-chip.posted {
    background: #e7f7f2;
    color: #277660;
}

.status-chip.reversed {
    background: #fff1f1;
    color: #b15050;
}
</style>
