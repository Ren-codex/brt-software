<template>
    <div>
        <PageHeader title="Accounting Module" pageTitle="Balance Sheet" />

        <div class="inventory-container">
            <AccountingSidebar active-tab="balance_sheet" :stats="stats" />

            <div class="inventory-main">
                <div class="report-shell">
                    <div class="report-header">
                        <div>
                            <p class="report-kicker mb-1">Financial Position</p>
                            <h3 class="report-title mb-1">Balance Sheet</h3>
                            <p class="report-subtitle mb-0">
                                Assets, liabilities, equity, and current-period earnings based on posted balances.
                            </p>
                        </div>
                    </div>

                    <div class="summary-grid">
                        <div v-for="card in summaryCards" :key="card.title" class="summary-card">
                            <div class="summary-icon"><i :class="card.icon"></i></div>
                            <div>
                                <p class="summary-label">{{ card.title }}</p>
                                <h4 class="summary-value">{{ card.value }}</h4>
                                <p class="summary-note mb-0">{{ card.description }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="!dataReady" class="empty-panel">
                        <i class="ri-information-line"></i>
                        <span>Accounting tables are not ready yet. Run the accounting migrations to populate the balance sheet.</span>
                    </div>

                    <template v-else>
                        <div class="statement-grid">
                            <div class="report-panel">
                                <div class="panel-head">
                                    <h5 class="panel-title">Assets</h5>
                                </div>
                                <div class="table-responsive">
                                    <table class="table report-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Code</th>
                                                <th>Account</th>
                                                <th>Balance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="account in assetAccounts" :key="account.id">
                                                <td>{{ account.code }}</td>
                                                <td>{{ account.name }}</td>
                                                <td>{{ account.balance_formatted }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="report-panel">
                                <div class="panel-head">
                                    <h5 class="panel-title">Liabilities</h5>
                                </div>
                                <div class="table-responsive">
                                    <table class="table report-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Code</th>
                                                <th>Account</th>
                                                <th>Balance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="account in liabilityAccounts" :key="account.id">
                                                <td>{{ account.code }}</td>
                                                <td>{{ account.name }}</td>
                                                <td>{{ account.balance_formatted }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="statement-grid mt-4">
                            <div class="report-panel">
                                <div class="panel-head">
                                    <h5 class="panel-title">Equity</h5>
                                </div>
                                <div class="table-responsive">
                                    <table class="table report-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Code</th>
                                                <th>Account</th>
                                                <th>Balance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="account in equityAccounts" :key="account.id">
                                                <td>{{ account.code }}</td>
                                                <td>{{ account.name }}</td>
                                                <td>{{ account.balance_formatted }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="report-panel">
                                <div class="panel-head">
                                    <h5 class="panel-title">Statement Totals</h5>
                                </div>
                                <div class="totals-grid">
                                    <div class="total-row"><span>Total Assets</span><strong>{{ totals.assets }}</strong></div>
                                    <div class="total-row"><span>Total Liabilities</span><strong>{{ totals.liabilities }}</strong></div>
                                    <div class="total-row"><span>Book Equity</span><strong>{{ totals.equity }}</strong></div>
                                    <div class="total-row"><span>Current Period Earnings</span><strong>{{ totals.current_period_earnings }}</strong></div>
                                    <div class="total-row total-emphasis"><span>Liabilities + Equity</span><strong>{{ totals.liabilities_and_equity }}</strong></div>
                                    <div class="total-row"><span>Status</span><strong>{{ totals.is_balanced ? 'Balanced' : 'Needs Review' }}</strong></div>
                                </div>
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
        assetAccounts: Array,
        liabilityAccounts: Array,
        equityAccounts: Array,
        totals: Object,
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
.summary-note {
    color: #648b74;
}

.summary-grid,
.statement-grid {
    display: grid;
    gap: 1rem;
}

.summary-grid {
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.statement-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
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
}

.summary-value {
    margin-bottom: 0.25rem;
    color: #20413a;
    font-weight: 700;
}

.report-table thead th {
    background: #edf5f2;
    color: #527267;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
}

.totals-grid {
    display: grid;
    gap: 0.8rem;
}

.total-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.9rem 1rem;
    border-radius: 16px;
    background: #f4faf8;
    color: #355f55;
}

.total-emphasis {
    background: #e7f7f2;
    color: #1e5f4f;
    font-weight: 700;
}

.empty-panel {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    color: #7b6a2f;
    background: linear-gradient(180deg, #fff8e7 0%, #fffdf7 100%);
}

@media (max-width: 991.98px) {
    .statement-grid {
        grid-template-columns: 1fr;
    }
}
</style>
