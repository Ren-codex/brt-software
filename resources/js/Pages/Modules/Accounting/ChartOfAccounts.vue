<template>
    <div>
        <PageHeader title="Accounting Module" pageTitle="Chart Of Accounts" />

        <div class="inventory-container">
            <AccountingSidebar active-tab="chart_of_accounts" :stats="stats" />

            <div class="inventory-main">
                <div class="report-shell">
                    <div class="report-header">
                        <div>
                            <p class="report-kicker mb-1">Structure</p>
                            <h3 class="report-title mb-1">Chart Of Accounts</h3>
                            <p class="report-subtitle mb-0">
                                Review account codes, types, status, and balances used by the automatic posting engine.
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
                        <span>Accounting tables are not ready yet. Run the accounting migrations to populate the chart of accounts.</span>
                    </div>

                    <div v-else class="report-panel">
                        <div class="panel-head">
                            <h5 class="panel-title">Account Master List</h5>
                            <p class="panel-subtitle mb-0">Configured accounts available for posting and reporting.</p>
                        </div>

                        <div class="table-responsive">
                            <table class="table report-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Account</th>
                                        <th>Type</th>
                                        <th>Subtype</th>
                                        <th>Debit</th>
                                        <th>Credit</th>
                                        <th>Balance</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in rows" :key="row.id">
                                        <td>{{ row.code }}</td>
                                        <td>{{ row.name }}</td>
                                        <td>{{ formatLabel(row.type) }}</td>
                                        <td>{{ formatLabel(row.subtype || 'general') }}</td>
                                        <td>{{ row.debit_total_formatted }}</td>
                                        <td>{{ row.credit_total_formatted }}</td>
                                        <td>{{ row.balance_formatted }}</td>
                                        <td>
                                            <span class="status-chip" :class="row.is_active ? 'active' : 'inactive'">
                                                {{ row.is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
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
        rows: Array,
    },
    methods: {
        formatLabel(value) {
            return String(value || "-")
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

.status-chip {
    display: inline-flex;
    align-items: center;
    padding: 0.3rem 0.7rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 700;
}

.status-chip.active {
    background: rgba(40, 167, 69, 0.12);
    color: #2c8b48;
}

.status-chip.inactive {
    background: rgba(220, 53, 69, 0.12);
    color: #c23b4c;
}

.empty-panel {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    color: #7b6a2f;
    background: linear-gradient(180deg, #fff8e7 0%, #fffdf7 100%);
}
</style>
