<template>
    <div>
        <PageHeader title="Accounting Module" pageTitle="Overview" />

        <div class="inventory-container accounting-layout">
            <div class="inventory-sidebar" :class="{ 'sidebar-collapsed': isSidebarCollapsed }">
                <div class="inventory-sidebar-header">
                    <i class="ri-bank-line"></i>
                    <h4 v-if="!isSidebarCollapsed">Accounting</h4>
                    <button class="sidebar-toggle-btn" @click="toggleSidebar" title="Toggle Sidebar">
                        <i :class="isSidebarCollapsed ? 'ri-arrow-right-s-line' : 'ri-arrow-left-s-line'"></i>
                    </button>
                </div>

                <div class="inventory-sidebar-tabs">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        type="button"
                        class="inventory-sidebar-tab accounting-tab-button"
                        :class="{ 'inventory-tab-active': activeTab === tab.id }"
                        @click="changeTab(tab.id)"
                    >
                        <div class="inventory-tab-icon">
                            <i :class="tab.icon"></i>
                        </div>
                        <div v-if="!isSidebarCollapsed" class="inventory-tab-text">
                            <span class="inventory-tab-title">{{ tab.label }}</span>
                            <span class="inventory-tab-subtitle">{{ tab.description }}</span>
                        </div>
                    </button>
                </div>

                <div class="inventory-sidebar-footer" v-if="!isSidebarCollapsed">
                    <div class="inventory-stats">
                        <div class="inventory-stat-item">
                            <i class="ri-file-list-3-line"></i>
                            <span>{{ statsState.pending_entries || 0 }} Posted Entries</span>
                        </div>
                        <div class="inventory-stat-item">
                            <i class="ri-book-2-line"></i>
                            <span>{{ tabs.length }} Accounting Sections</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="inventory-main">
                <div class="inventory-main-content">
                    <transition name="inventory-fade" mode="out-in">
                        <div :key="activeTab" class="inventory-tab-content">
                            <div class="accounting-header-card">
                                <div>
                                    <span class="header-badge">{{ activeSection.badge }}</span>
                                    <h2 class="header-title">{{ activeSection.title }}</h2>
                                </div>
                            </div>

                            <div class="report-filter-bar">
                                <div class="filter-field">
                                    <label class="filter-label" for="accounting-date-from">Date From</label>
                                    <input id="accounting-date-from" v-model="filter.date_from" type="date" class="filter-input" />
                                </div>
                                <div class="filter-field">
                                    <label class="filter-label" for="accounting-date-to">Date To</label>
                                    <input id="accounting-date-to" v-model="filter.date_to" type="date" class="filter-input" />
                                </div>
                                <div class="filter-actions">
                                    <button type="button" class="filter-btn filter-btn-secondary" @click="clearDateFilter">
                                        Clear
                                    </button>
                                    <button type="button" class="filter-btn filter-btn-primary" @click="applyDateFilter">
                                        Apply Filter
                                    </button>
                                </div>
                            </div>

                            <BRow class="g-4 mt-1">
                                <BCol md="6" xl="3" v-for="card in statCards" :key="card.title">
                                    <div class="stat-card">
                                        <div class="stat-icon">
                                            <i :class="card.icon"></i>
                                        </div>
                                        <div>
                                            <p class="stat-label">{{ card.title }}</p>
                                            <h3 class="stat-value">{{ card.value }}</h3>
                                        </div>
                                    </div>
                                </BCol>

                               
                            </BRow>

                            <div v-if="dataReadyState" class="module-panel preview-panel mt-4">
                                 <div class="panel-header">
                                     <div>
                                         <h4 class="panel-title">Live Data</h4>
                                         <p class="panel-subtitle mb-0">Switch tabs to preview each report here, then click rows where available to open details.</p>
                                     </div>
                                 </div>

                                <div v-if="activeTab === 'trial_balance'" class="table-responsive">
                                    <table class="table preview-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Account</th>
                                                <th>Number</th>
                                                <th>Debit</th>
                                                <th>Credit</th>
                                                <th>Balance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="row in activePreviewData.rows || []"
                                                :key="row.id"
                                                class="account-row"
                                                :class="{ 'table-active': expandedAccountId === row.id }"
                                                @click="showAccountDetails(row)"
                                                style="cursor: pointer;">
                                                <td>{{ row.name }}</td>
                                                <td>{{ row.code }}</td>
                                                <td>{{ row.debit_total_formatted }}</td>
                                                <td>{{ row.credit_total_formatted }}</td>
                                                <td :class="{ 'negative-balance': Number(row.balance) < 0 }">
                                                    {{ formatBalance(row.balance) }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <!-- Account Details Panel -->
                                    <div v-if="selectedAccount && journalDetails" class="account-details-panel mt-4">
                                        <div class="details-header">
                                            <div>
                                                <h5>Journal Activity: {{ selectedAccount.name }} ({{ selectedAccount.code }})</h5>
                                                <p class="mb-0 text-muted">
                                                    {{ journalDetails.debits.length }} debits • {{ journalDetails.credits.length }} credits
                                                    • Period: {{ filter.date_from || 'All' }} to {{ filter.date_to || 'All' }}
                                                </p>
                                            </div>
                                            <button @click="showAccountDetails(selectedAccount)" class="btn-close" title="Close">&times;</button>
                                        </div>

                                        <div class="journal-split-grid">
                                            <!-- Debits Left -->
                                            <div class="journal-column">
                                                <h6><i class="ri-arrow-left-line"></i> Debits</h6>
                                                <div class="journal-lines">
                                                    <div v-for="line in journalDetails.debits" :key="line.id" class="journal-line debit">
                                                        <div class="line-meta">
                                                            <strong>{{ line.journal_number }}</strong>
                                                            <small>{{ line.entry_date }}</small>
                                                        </div>
                                                        <div class="line-amount">{{ line.amount_formatted }}</div>
                                                        <div class="line-desc">{{ line.description }}</div>
                                                    </div>
                                                    <div v-if="!journalDetails.debits.length" class="empty-lines">No debit entries</div>
                                                </div>
                                                <div class="total-line debit-total">
                                                    <span>Total Debit:</span>
                                                    <strong>{{ journalDetails.summary?.total_debit_formatted || '0.00' }}</strong>
                                                </div>
                                            </div>

                                            <!-- Credits Right -->
                                            <div class="journal-column">
                                                <h6><i class="ri-arrow-right-line"></i> Credits</h6>
                                                <div class="journal-lines">
                                                    <div v-for="line in journalDetails.credits" :key="line.id" class="journal-line credit">
                                                        <div class="line-meta">
                                                            <strong>{{ line.journal_number }}</strong>
                                                            <small>{{ line.entry_date }}</small>
                                                        </div>
                                                        <div class="line-amount">{{ line.amount_formatted }}</div>
                                                        <div class="line-desc">{{ line.description }}</div>
                                                    </div>
                                                    <div v-if="!journalDetails.credits.length" class="empty-lines">No credit entries</div>
                                                </div>
                                                <div class="total-line credit-total">
                                                    <span>Total Credit:</span>
                                                    <strong>{{ journalDetails.summary?.total_credit_formatted || '0.00' }}</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-else-if="activeTab === 'balance_sheet'" class="report-split-grid">
                                    <div>
                                        <h5 class="preview-heading">Assets</h5>
                                        <div class="table-responsive">
                                            <table class="table preview-table mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Code</th>
                                                        <th>Account</th>
                                                        <th>Balance</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="row in activePreviewData.asset_accounts || []" :key="row.id">
                                                        <td>{{ row.code }}</td>
                                                        <td>{{ row.name }}</td>
                                                        <td>{{ row.balance_formatted }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div>
                                        <h5 class="preview-heading">Liabilities And Equity</h5>
                                        <div class="table-responsive">
                                            <table class="table preview-table mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Code</th>
                                                        <th>Account</th>
                                                        <th>Balance</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="row in liabilityAndEquityRows" :key="`${row.type}-${row.id}`">
                                                        <td>{{ row.code }}</td>
                                                        <td>{{ row.name }}</td>
                                                        <td>{{ row.balance_formatted }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <div v-else-if="activeTab === 'journal_entries'" class="table-responsive">
                                    <table class="table preview-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Journal</th>
                                                <th>Entry Type</th>
                                                <th>Status</th>
                                                <th>Memo</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template v-for="row in activePreviewData.rows || []" :key="row.id">
                                                <tr
                                                    class="journal-entry-row"
                                                    :class="{ 'table-active': expandedJournalEntryId === row.id }"
                                                    @click="toggleJournalEntryDetails(row)"
                                                    style="cursor: pointer;"
                                                >
                                                    <td>{{ row.entry_date }}</td>
                                                    <td>{{ row.journal_number }}</td>
                                                    <td>{{ row.entry_type }}</td>
                                                    <td>{{ row.status }}</td>
                                                    <td>{{ row.memo }}</td>
                                                </tr>
                                                <tr v-if="expandedJournalEntryId === row.id" class="details-row">
                                                    <td colspan="5">
                                                        <div class="account-details-panel journal-entry-details-panel">
                                                            <div class="details-header">
                                                                <div>
                                                                    <h5>Journal Entry: {{ row.journal_number }}</h5>
                                                                    <p class="mb-1 text-muted">
                                                                        {{ row.entry_date || 'No date' }} |
                                                                        {{ row.entry_type || 'Uncategorized' }} |
                                                                        {{ row.status || 'Unknown' }}
                                                                    </p>
                                                                    <p class="mb-0 text-muted">Memo: {{ row.memo || '-' }}</p>
                                                                </div>
                                                                <button
                                                                    type="button"
                                                                    class="btn-close"
                                                                    title="Close"
                                                                    @click.stop="toggleJournalEntryDetails(row)"
                                                                >&times;</button>
                                                            </div>

                                                            <div class="journal-preview-header">
                                                                <div>
                                                                    <h6 class="detail-title mb-1">Journal Entry Format</h6>
                                                                    <p class="journal-preview-meta mb-0">
                                                                        {{ journalLines(row, 'debit').length }} debit line(s) |
                                                                        {{ journalLines(row, 'credit').length }} credit line(s)
                                                                    </p>
                                                                </div>
                                                                <span class="balance-chip" :class="{ balanced: isBalancedEntry(row), unbalanced: !isBalancedEntry(row) }">
                                                                    {{ isBalancedEntry(row) ? 'Balanced' : 'Needs Review' }}
                                                                </span>
                                                            </div>

                                                            <div class="journal-split-grid">
                                                                <div class="journal-column">
                                                                    <h6><i class="ri-arrow-left-line"></i> Debit</h6>
                                                                    <div class="journal-lines">
                                                                        <div v-for="line in journalLines(row, 'debit')" :key="line.id" class="journal-line debit">
                                                                            <div class="line-meta">
                                                                                <strong>{{ line.account || '-' }}</strong>
                                                                                <!-- <small>{{ line.account_code || 'No code' }}</small> -->
                                                                            </div>
                                                                            <div class="line-amount">{{ formatJournalAmount(line.amount) }}</div>
                                                                            <div class="line-desc">{{ line.description || row.memo || '-' }}</div>
                                                                        </div>
                                                                        <div v-if="!journalLines(row, 'debit').length" class="empty-lines">No debit entries</div>
                                                                    </div>
                                                                    <div class="total-line debit-total">
                                                                        <span>Total Debit</span>
                                                                        <strong>{{ formatJournalAmount(journalTotal(row, 'debit')) }}</strong>
                                                                    </div>
                                                                </div>

                                                                <div class="journal-column">
                                                                    <h6><i class="ri-arrow-right-line"></i> Credit</h6>
                                                                    <div class="journal-lines">
                                                                        <div v-for="line in journalLines(row, 'credit')" :key="line.id" class="journal-line credit">
                                                                            <div class="line-meta">
                                                                                <strong>{{ line.account || '-' }}</strong>
                                                                                <!-- <small>{{ line.account_code || 'No code' }}</small> -->
                                                                            </div>
                                                                            <div class="line-amount">{{ formatJournalAmount(line.amount) }}</div>
                                                                            <div class="line-desc">{{ line.description || row.memo || '-' }}</div>
                                                                        </div>
                                                                        <div v-if="!journalLines(row, 'credit').length" class="empty-lines">No credit entries</div>
                                                                    </div>
                                                                    <div class="total-line credit-total">
                                                                        <span>Total Credit</span>
                                                                        <strong>{{ formatJournalAmount(journalTotal(row, 'credit')) }}</strong>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>

                                <div v-if="!hasPreviewRows" class="empty-preview-state">
                                    No records available yet for this tab.
                                </div>
                            </div>
                        </div>
                    </transition>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import PageHeader from "@/Shared/Components/PageHeader.vue";

export default {
    components: { PageHeader },
    props: {
        stats: {
            type: Object,
            default: () => ({
                open_periods: 0,
                pending_entries: 0,
                unreconciled_items: 0,
                generated_reports: 0,
            }),
        },
        sectionMetrics: {
            type: Object,
            default: () => ({}),
        },
        reportData: {
            type: Object,
            default: () => ({}),
        },
        dataReady: {
            type: Boolean,
            default: false,
        },
        filters: {
            type: Object,
            default: () => ({
                date_from: null,
                date_to: null,
            }),
        },
    },
data() {
        return {
            isSidebarCollapsed: false,
            activeTab: localStorage.getItem("accounting_active_tab") || "trial_balance",
            statsState: this.stats,
            sectionMetricsState: this.sectionMetrics,
            reportDataState: this.reportData,
            dataReadyState: this.dataReady,
            isFiltering: false,
            filter: {
                date_from: this.filters?.date_from || "",
                date_to: this.filters?.date_to || "",
            },
            selectedAccount: null,
            journalDetails: null,
            expandedAccountId: null,
            expandedJournalEntryId: null,
            tabs: [
                {
                    id: "trial_balance",
                    label: "Trial Balance",
                    icon: "ri-scales-3-line",
                    description: "Debit and credit balance checks",
                },
                {
                    id: "balance_sheet",
                    label: "Balance Sheet",
                    icon: "ri-bank-card-line",
                    description: "Assets, liabilities, and equity",
                },
                {
                    id: "journal_entries",
                    label: "Journal Entries",
                    icon: "ri-book-2-line",
                    description: "Auto-generated accounting postings",
                },
            ],
            sectionContent: {
                trial_balance: {
                    badge: "Validation",
                    title: "Trial Balance Checkpoint",
                    summary: "Validate that posted debits and credits remain balanced before reporting, closing, and external review.",
                    note: "This view should highlight imbalances fast and help accounting trace them back to their transaction source.",
                    panelTitle: "Trial Balance Priorities",
                    panelSubtitle: "Controls that help the team verify posting accuracy before report generation.",
                    cards: [
                        {
                            title: "Debit Vs Credit",
                            description: "Compare account totals and make it obvious when the books are out of balance.",
                            icon: "ri-scales-3-line",
                        },
                        {
                            title: "Period Snapshots",
                            description: "Generate trial balances by day, month, or fiscal period for audit and closing workflows.",
                            icon: "ri-calendar-event-line",
                        },
                        {
                            title: "Exception Review",
                            description: "Flag accounts with unusual movements, missing mappings, or failed posting logic.",
                            icon: "ri-alert-line",
                        },
                        {
                            title: "Export Ready",
                            description: "Prepare print and spreadsheet versions for finance review and reconciliation packs.",
                            icon: "ri-download-2-line",
                        },
                    ],
                    steps: [
                        "Compute debit and credit totals directly from posted journal lines.",
                        "Add period selector and account grouping.",
                        "Highlight imbalanced states clearly at the top of the screen.",
                        "Support export to PDF or spreadsheet.",
                    ],
                },
                balance_sheet: {
                    badge: "Financial Position",
                    title: "Balance Sheet Overview",
                    summary: "Present the company’s assets, liabilities, and equity from posted balances at a point in time.",
                    note: "This screen should update from the chart of accounts and journal postings without manual re-encoding.",
                    panelTitle: "Balance Sheet Essentials",
                    panelSubtitle: "Foundation pieces for a reliable statement of financial position.",
                    cards: [
                        {
                            title: "Current Assets",
                            description: "Cash, receivables, inventory, and other near-term asset balances should roll up automatically.",
                            icon: "ri-coins-line",
                        },
                        {
                            title: "Liabilities",
                            description: "Show payable and other obligation balances sourced from posted transactions.",
                            icon: "ri-secure-payment-line",
                        },
                        {
                            title: "Equity",
                            description: "Display owner’s equity and retained earnings from cumulative posted activity.",
                            icon: "ri-building-line",
                        },
                        {
                            title: "Balance Check",
                            description: "Keep total assets equal to liabilities plus equity on every reporting run.",
                            icon: "ri-shield-check-line",
                        },
                    ],
                    steps: [
                        "Tag chart of accounts records with proper report groupings.",
                        "Aggregate ending balances by statement category.",
                        "Show as-of date reporting instead of transaction ranges only.",
                        "Add variance comparison against prior period.",
                    ],
                },
                journal_entries: {
                    badge: "Audit Trail",
                    title: "Journal Entries",
                    summary: "Inspect the system-generated accounting entries produced by sales, receipts, expenses, stock receipts, and inventory adjustments.",
                    note: "Routine operations should generate journal entries automatically. Manual journals should be limited to adjustments and special accounting events.",
                    panelTitle: "Journal Entry Coverage",
                    panelSubtitle: "What this screen is intended to track across the accounting module.",
                    cards: [
                        {
                            title: "Original Entries",
                            description: "Show posted source-generated entries with account lines, amounts, and memo references.",
                            icon: "ri-file-paper-2-line",
                        },
                        {
                            title: "Reversal Linkage",
                            description: "Trace updated, refunded, deleted, or voided transactions back to reversal postings.",
                            icon: "ri-arrow-go-back-line",
                        },
                        {
                            title: "Source Trace",
                            description: "Connect every journal back to the transaction that created it for audit review.",
                            icon: "ri-links-fill",
                        },
                        {
                            title: "Posting Review",
                            description: "Filter by status, type, and keyword to verify generated accounting activity.",
                            icon: "ri-search-eye-line",
                        },
                    ],
                    steps: [
                        "Continue using the dedicated Journal Entries screen for full list and drill-down.",
                        "Show reversal linkage once the latest accounting migration is applied.",
                        "Add source filters and date-range filters for audit review.",
                        "Support print and export for accounting review packs.",
                    ],
                },
            },
        };
    },
    computed: {
        statCards() {
            return [
                {
                    title: "Open Periods",
                    value: this.statsState.open_periods,
                    icon: "ri-calendar-check-line",
                },
                {
                    title: "Pending Entries",
                    value: this.statsState.pending_entries,
                    icon: "ri-file-list-3-line",
                },
                {
                    title: "Unreconciled Items",
                    value: this.statsState.unreconciled_items,
                    icon: "ri-scales-3-line",
                },
                {
                    title: "Generated Reports",
                    value: this.statsState.generated_reports,
                    icon: "ri-bar-chart-box-line",
                },
            ];
        },
        activeSection() {
            return this.sectionContent[this.activeTab] || this.sectionContent.trial_balance;
        },
        activeMetricCards() {
            return this.sectionMetricsState[this.activeTab] || this.activeSection.cards;
        },
        activePreviewData() {
            return this.reportDataState[this.activeTab] || null;
        },
        liabilityAndEquityRows() {
            if (this.activeTab !== "balance_sheet" || !this.activePreviewData) {
                return [];
            }

            return [
                ...(this.activePreviewData.liability_accounts || []),
                ...(this.activePreviewData.equity_accounts || []),
            ];
        },
        hasPreviewRows() {
            if (!this.activePreviewData) {
                return false;
            }

            if (Array.isArray(this.activePreviewData.rows)) {
                return this.activePreviewData.rows.length > 0;
            }

            if (Array.isArray(this.activePreviewData.account_balances)) {
                return this.activePreviewData.account_balances.length > 0;
            }

            if (Array.isArray(this.activePreviewData.revenue_accounts) || Array.isArray(this.activePreviewData.expense_accounts)) {
                return (this.activePreviewData.revenue_accounts || []).length > 0
                    || (this.activePreviewData.expense_accounts || []).length > 0;
            }

            if (Array.isArray(this.activePreviewData.asset_accounts)) {
                return this.activePreviewData.asset_accounts.length > 0 || this.liabilityAndEquityRows.length > 0;
            }

            return false;
        },
    },
    created() {
        const params = new URLSearchParams(window.location.search);
        const tabParam = params.get("tab");

        if (tabParam && this.tabs.some((tab) => tab.id === tabParam)) {
            this.activeTab = tabParam;
        }
    },
    methods: {
        toggleSidebar() {
            this.isSidebarCollapsed = !this.isSidebarCollapsed;
        },
        changeTab(tab) {
            this.clearOpenDetails();
            this.activeTab = tab;
            localStorage.setItem("accounting_active_tab", tab);

            const url = new URL(window.location);
            url.searchParams.set("tab", tab);
            if (this.filter.date_from) {
                url.searchParams.set("date_from", this.filter.date_from);
            } else {
                url.searchParams.delete("date_from");
            }
            if (this.filter.date_to) {
                url.searchParams.set("date_to", this.filter.date_to);
            } else {
                url.searchParams.delete("date_to");
            }
            window.history.pushState({}, "", url);
        },
        applyDateFilter() {
            this.fetchFilteredData();
        },
        clearDateFilter() {
            this.filter.date_from = "";
            this.filter.date_to = "";
            this.fetchFilteredData();
        },
        fetchFilteredData() {
            this.isFiltering = true;
            this.clearOpenDetails();

            axios.get("/accounting", {
                params: {
                    option: "report_data",
                    date_from: this.filter.date_from || null,
                    date_to: this.filter.date_to || null,
                },
            }).then((response) => {
                this.statsState = response.data.stats || {};
                this.sectionMetricsState = response.data.sectionMetrics || {};
                this.reportDataState = response.data.reportData || {};
                this.dataReadyState = !!response.data.dataReady;

                const url = new URL(window.location);
                url.searchParams.set("tab", this.activeTab);
                if (this.filter.date_from) {
                    url.searchParams.set("date_from", this.filter.date_from);
                } else {
                    url.searchParams.delete("date_from");
                }
                if (this.filter.date_to) {
                    url.searchParams.set("date_to", this.filter.date_to);
                } else {
                    url.searchParams.delete("date_to");
                }
                window.history.replaceState({}, "", url);
            }).finally(() => {
                this.isFiltering = false;
            });
        },
        formatBalance(value) {
            const amount = Number(value || 0);
            const formatted = Math.abs(amount).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });

            return amount < 0 ? `(${formatted})` : formatted;
        },
        clearOpenDetails() {
            this.closeAccountDetails();
            this.closeJournalEntryDetails();
        },
        closeAccountDetails() {
            this.expandedAccountId = null;
            this.selectedAccount = null;
            this.journalDetails = null;
        },
        closeJournalEntryDetails() {
            this.expandedJournalEntryId = null;
        },
        showAccountDetails(account) {
            if (this.expandedAccountId === account.id) {
                this.closeAccountDetails();
                return;
            }

            this.closeJournalEntryDetails();
            this.selectedAccount = account;
            this.expandedAccountId = account.id;
            this.fetchAccountJournalDetails(account.id);
        },
        toggleJournalEntryDetails(entry) {
            if (this.expandedJournalEntryId === entry.id) {
                this.closeJournalEntryDetails();
                return;
            }

            this.closeAccountDetails();
            this.expandedJournalEntryId = entry.id;
        },
        async fetchAccountJournalDetails(accountId) {
            try {
                const response = await axios.get(`/api/accounting/${accountId}/journal-details`, {
                    params: {
                        date_from: this.filter.date_from || null,
                        date_to: this.filter.date_to || null,
                    }
                });
                this.journalDetails = response.data;
            } catch (error) {
                console.error('Failed to fetch journal details:', error);
                this.journalDetails = null;
            }
        },
        journalLines(entry, type) {
            return (entry?.lines || []).filter((line) => this.normalizeLineType(line.line_type) === type);
        },
        journalTotal(entry, type) {
            return this.journalLines(entry, type).reduce((total, line) => total + this.normalizeAmount(line.amount), 0);
        },
        isBalancedEntry(entry) {
            return Math.abs(this.journalTotal(entry, "debit") - this.journalTotal(entry, "credit")) < 0.005;
        },
        normalizeLineType(lineType) {
            return String(lineType || "").trim().toLowerCase();
        },
        normalizeAmount(amount) {
            const parsedAmount = Number(String(amount || 0).replace(/[^0-9.-]/g, ""));

            return Number.isFinite(parsedAmount) ? parsedAmount : 0;
        },
        formatJournalAmount(amount) {
            return `P ${this.normalizeAmount(amount).toLocaleString("en-PH", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            })}`;
        },
    },
};
</script>

<style scoped>
.accounting-layout {
    gap: 1.5rem;
}

.inventory-sidebar-header {
    position: relative;
}

.sidebar-toggle-btn {
    position: absolute;
    top: 15px;
    right: 15px;
    background: transparent;
    border: 1px solid rgba(108, 117, 125, 0.3);
    border-radius: 6px;
    color: #6c757d;
    font-size: 16px;
    cursor: pointer;
    padding: 6px 8px;
    transition: all 0.3s ease;
    z-index: 10;
}

.sidebar-toggle-btn:hover {
    background: rgba(108, 117, 125, 0.1);
    color: #495057;
    border-color: rgba(108, 117, 125, 0.5);
}

.inventory-sidebar {
    transition: width 0.3s ease;
}

.inventory-sidebar.sidebar-collapsed {
    width: 80px;
}

.inventory-sidebar.sidebar-collapsed .inventory-sidebar-header h4 {
    display: none;
}

.inventory-sidebar.sidebar-collapsed .inventory-sidebar-tab {
    justify-content: center;
    padding: 12px;
    transition: all 0.3s ease;
}

.inventory-sidebar.sidebar-collapsed .inventory-tab-text {
    display: none;
}

.inventory-sidebar-tab {
    transition: all 0.3s ease;
}

.inventory-sidebar-tab:hover {
    background: rgba(61, 141, 122, 0.12);
    transform: translateX(2px);
}

.accounting-tab-button {
    width: 100%;
    border: 0;
    background: transparent;
    color: inherit;
    text-align: left;
}

.inventory-sidebar.sidebar-collapsed .inventory-sidebar-tab:hover {
    transform: none;
}

.accounting-header-card {
    padding: 1.1rem 1.3rem;
    border: 1px solid rgba(61, 141, 122, 0.12);
    border-radius: 22px;
    background: linear-gradient(180deg, #ffffff 0%, #f7fbfa 100%);
    box-shadow: 0 14px 30px rgba(28, 49, 45, 0.08);
}

.report-filter-bar {
    display: grid;
    gap: 0.85rem;
    grid-template-columns: repeat(2, minmax(0, 220px)) auto;
    align-items: end;
}

.filter-field {
    display: grid;
    gap: 0.35rem;
}

.filter-label {
    color: #648b74;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.filter-input {
    width: 100%;
    min-height: 42px;
    padding: 0.65rem 0.8rem;
    border: 1px solid rgba(61, 141, 122, 0.16);
    border-radius: 14px;
    background: #ffffff;
    color: #20413a;
    outline: none;
    box-shadow: inset 0 1px 2px rgba(28, 49, 45, 0.04);
}

.filter-actions {
    display: flex;
    gap: 0.65rem;
    justify-content: flex-start;
}

.filter-btn {
    min-height: 42px;
    padding: 0.65rem 1rem;
    border-radius: 14px;
    border: 0;
    font-weight: 700;
    transition: all 0.2s ease;
}

.filter-btn-primary {
    background: #3d8d7a;
    color: #ffffff;
}

.filter-btn-secondary {
    background: #eaf4f1;
    color: #20413a;
}

.header-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.28rem 0.72rem;
    border-radius: 999px;
    background: rgba(61, 141, 122, 0.12);
    color: #3d8d7a;
    font-size: 0.74rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.header-title {
    margin: 0.5rem 0 0.25rem;
    font-size: 1.45rem;
    font-weight: 700;
    color: #20413a;
}

.stat-card,
.module-panel {
    height: 100%;
    border: 1px solid rgba(61, 141, 122, 0.12);
    border-radius: 20px;
    background: linear-gradient(180deg, #ffffff 0%, #f7fbfa 100%);
    box-shadow: 0 14px 30px rgba(28, 49, 45, 0.08);
}

.stat-card {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 1rem 1.05rem;
}

.stat-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 46px;
    height: 46px;
    border-radius: 14px;
    background: linear-gradient(135deg, rgba(61, 141, 122, 0.16), rgba(196, 218, 210, 0.5));
    color: #2b6658;
    font-size: 1.2rem;
}

.stat-label {
    margin-bottom: 0.2rem;
    color: #648b74;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.stat-value {
    margin: 0;
    color: #20413a;
    font-size: 1.45rem;
    font-weight: 700;
}

.module-panel {
    padding: 1.15rem;
}

.preview-panel {
    overflow: hidden;
}

.panel-header {
    margin-bottom: 0.85rem;
}

.panel-title {
    margin-bottom: 0.35rem;
    color: #20413a;
    font-size: 1.15rem;
    font-weight: 700;
}

.panel-subtitle {
    color: #648b74;
    font-size: 0.92rem;
}

.workstream-grid {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.workstream-card {
    display: flex;
    gap: 0.9rem;
    padding: 1rem;
    border-radius: 18px;
    background: #f4faf8;
    border: 1px solid rgba(61, 141, 122, 0.12);
}

.workstream-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border-radius: 14px;
    background: #e4f1ed;
    color: #2f7666;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.workstream-title {
    margin-bottom: 0.35rem;
    color: #20413a;
    font-size: 1rem;
    font-weight: 700;
}

.workstream-metric {
    color: #2d6a5c;
    font-size: 1.05rem;
    font-weight: 700;
}

.workstream-text {
    color: #5f786e;
    font-size: 0.9rem;
    line-height: 1.5;
}

.preview-heading {
    margin-bottom: 0.55rem;
    color: #20413a;
    font-size: 1rem;
    font-weight: 700;
}

.preview-table thead th {
    color: #45685f;
    font-size: 0.82rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    background: #eef6f3;
    border-bottom: 1px solid rgba(61, 141, 122, 0.12);
}

.preview-table tbody td {
    color: #2f4942;
    vertical-align: middle;
}

.report-split-grid {
    display: grid;
    gap: 1.25rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.negative-balance {
    color: #d74c3c;
    font-weight: 700;
}

.empty-preview-state {
    padding: 0.85rem;
    border-radius: 16px;
    background: #f4faf8;
    border: 1px dashed rgba(61, 141, 122, 0.18);
    color: #5f786e;
    text-align: center;
    margin-top: 0.75rem;
}

.account-row:hover {
    background-color: rgba(61, 141, 122, 0.08);
}

.journal-entry-row:hover {
    background-color: rgba(61, 141, 122, 0.08);
}

.details-row td {
    padding: 1rem;
    background: #fbfdfc;
    border-top: 0;
}

.account-details-panel {
    border: 1px solid rgba(61, 141, 122, 0.16);
    border-radius: 18px;
    background: #f9fcfb;
    padding: 1.25rem;
}

.journal-entry-details-panel {
    background: #ffffff;
}

.details-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid rgba(61, 141, 122, 0.12);
}

.btn-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #6c757d;
    cursor: pointer;
    padding: 0;
    line-height: 1;
}

.btn-close:hover {
    color: #495057;
}

.journal-preview-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
}

.detail-title {
    color: #20413a;
    font-weight: 700;
    margin-bottom: 0.85rem;
}

.journal-preview-meta {
    color: #648b74;
    font-size: 0.85rem;
}

.balance-chip {
    display: inline-flex;
    align-items: center;
    padding: 0.45rem 0.8rem;
    border-radius: 999px;
    font-size: 0.76rem;
    font-weight: 700;
    white-space: nowrap;
}

.balance-chip.balanced {
    background: #e7f7f2;
    color: #277660;
}

.balance-chip.unbalanced {
    background: #fff1f1;
    color: #b15050;
}

.journal-split-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

.journal-column h6 {
    margin-bottom: 0.75rem;
    color: #20413a;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.95rem;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.journal-lines {
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid rgba(61, 141, 122, 0.12);
    border-radius: 12px;
    padding: 0.75rem;
    background: #ffffff;
}

.journal-line {
    display: grid;
    grid-template-columns: 1fr auto 2fr;
    gap: 0.75rem;
    padding: 0.5rem 0;
    border-bottom: 1px solid rgba(0, 0, 0, 0.04);
}

.journal-line:last-child {
    border-bottom: none;
}

.line-meta {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.line-meta strong {
    font-size: 0.9rem;
    color: #20413a;
}

.line-meta small {
    font-size: 0.78rem;
    color: #6c757d;
}

.line-amount {
    font-weight: 700;
    font-size: 0.95rem;
    text-align: right;
}

.line-desc {
    color: #495057;
    font-size: 0.85rem;
}

.debit .line-amount,
.debit-total strong {
    color: #3d8d7a;
}

.credit .line-amount,
.credit-total strong {
    color: #d74c3c;
}

.total-line {
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 2px solid rgba(61, 141, 122, 0.2);
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 1.1rem;
    font-weight: 700;
}

.empty-lines {
    padding: 1.5rem;
    text-align: center;
    color: #6c757d;
    font-style: italic;
    font-size: 0.9rem;
}

@media (max-width: 991.98px) {
    .journal-preview-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .journal-split-grid {
        grid-template-columns: 1fr;
    }
}

.checklist {
    display: grid;
    gap: 0.9rem;
}

.checklist-item {
    display: flex;
    align-items: flex-start;
    gap: 0.7rem;
    padding: 0.9rem 1rem;
    border-radius: 16px;
    background: #f4faf8;
    border: 1px solid rgba(61, 141, 122, 0.12);
    color: #355f55;
}

.checklist-item i {
    color: #3d8d7a;
    font-size: 1.1rem;
    margin-top: 0.05rem;
}

@media (max-width: 991.98px) {
    .header-title {
        font-size: 1.28rem;
    }

    .report-filter-bar {
        grid-template-columns: 1fr;
    }

    .filter-actions {
        justify-content: stretch;
    }

    .filter-btn {
        width: 100%;
    }

    .report-split-grid {
        grid-template-columns: 1fr;
    }
}
</style>
