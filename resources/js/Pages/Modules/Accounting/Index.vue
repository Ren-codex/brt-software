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
                                        <p class="panel-subtitle mb-0">Clicking a tab shows its table here immediately.</p>
                                    </div>
                                </div>

                                <div v-if="activeTab === 'general_ledger'" class="table-responsive">
                                    <table class="table preview-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Code</th>
                                                <th>Account</th>
                                                <th>Debit</th>
                                                <th>Credit</th>
                                                <th>Balance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="row in activePreviewData.account_balances || []" :key="row.id">
                                                <td>{{ row.code }}</td>
                                                <td>{{ row.name }}</td>
                                                <td>{{ row.debit_total_formatted }}</td>
                                                <td>{{ row.credit_total_formatted }}</td>
                                                <td>{{ row.balance_formatted }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div v-else-if="activeTab === 'trial_balance'" class="table-responsive">
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
                                            <tr v-for="row in activePreviewData.rows || []" :key="row.id">
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
                                </div>

                                <div v-else-if="activeTab === 'profit_loss'" class="report-split-grid">
                                    <div>
                                        <h5 class="preview-heading">Revenue Accounts</h5>
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
                                                    <tr v-for="row in activePreviewData.revenue_accounts || []" :key="row.id">
                                                        <td>{{ row.code }}</td>
                                                        <td>{{ row.name }}</td>
                                                        <td>{{ row.balance_formatted }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div>
                                        <h5 class="preview-heading">Expense Accounts</h5>
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
                                                    <tr v-for="row in activePreviewData.expense_accounts || []" :key="row.id">
                                                        <td>{{ row.code }}</td>
                                                        <td>{{ row.name }}</td>
                                                        <td>{{ row.balance_formatted }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
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

                                <div v-else-if="activeTab === 'accounts_receivable'" class="table-responsive">
                                    <table class="table preview-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Journal</th>
                                                <th>Entry Type</th>
                                                <th>Line Type</th>
                                                <th>Amount</th>
                                                <th>Description</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="row in activePreviewData.rows || []" :key="row.id">
                                                <td>{{ row.entry_date }}</td>
                                                <td>{{ row.journal_number }}</td>
                                                <td>{{ row.entry_type }}</td>
                                                <td>{{ row.line_type }}</td>
                                                <td>{{ row.amount }}</td>
                                                <td>{{ row.description }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div v-else-if="activeTab === 'accounts_payable'" class="table-responsive">
                                    <table class="table preview-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Journal</th>
                                                <th>Entry Type</th>
                                                <th>Line Type</th>
                                                <th>Amount</th>
                                                <th>Description</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="row in activePreviewData.rows || []" :key="row.id">
                                                <td>{{ row.entry_date }}</td>
                                                <td>{{ row.journal_number }}</td>
                                                <td>{{ row.entry_type }}</td>
                                                <td>{{ row.line_type }}</td>
                                                <td>{{ row.amount }}</td>
                                                <td>{{ row.description }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div v-else-if="activeTab === 'chart_of_accounts'" class="table-responsive">
                                    <table class="table preview-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Code</th>
                                                <th>Account</th>
                                                <th>Type</th>
                                                <th>Subtype</th>
                                                <th>Status</th>
                                                <th>Balance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="row in activePreviewData.rows || []" :key="row.id">
                                                <td>{{ row.code }}</td>
                                                <td>{{ row.name }}</td>
                                                <td>{{ row.type }}</td>
                                                <td>{{ row.subtype }}</td>
                                                <td>{{ row.status }}</td>
                                                <td>{{ row.balance_formatted }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
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
                                            <tr v-for="row in activePreviewData.rows || []" :key="row.id">
                                                <td>{{ row.entry_date }}</td>
                                                <td>{{ row.journal_number }}</td>
                                                <td>{{ row.entry_type }}</td>
                                                <td>{{ row.status }}</td>
                                                <td>{{ row.memo }}</td>
                                            </tr>
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
            tabs: [
                {
                    id: "general_ledger",
                    label: "General Ledger",
                    icon: "ri-book-open-line",
                    description: "Ledger balances and account activity",
                },
                {
                    id: "trial_balance",
                    label: "Trial Balance",
                    icon: "ri-scales-3-line",
                    description: "Debit and credit balance checks",
                },
                {
                    id: "profit_loss",
                    label: "Profit & Loss",
                    icon: "ri-line-chart-line",
                    description: "Income and expense performance",
                },
                {
                    id: "balance_sheet",
                    label: "Balance Sheet",
                    icon: "ri-bank-card-line",
                    description: "Assets, liabilities, and equity",
                },
                {
                    id: "accounts_receivable",
                    label: "Accounts Receivable",
                    icon: "ri-file-list-3-line",
                    description: "Customer balances and aging",
                },
                {
                    id: "accounts_payable",
                    label: "Accounts Payable",
                    icon: "ri-wallet-3-line",
                    description: "Supplier balances and due items",
                },
                {
                    id: "chart_of_accounts",
                    label: "Chart Of Accounts",
                    icon: "ri-node-tree",
                    description: "Account structure and mappings",
                },
                {
                    id: "journal_entries",
                    label: "Journal Entries",
                    icon: "ri-book-2-line",
                    description: "Auto-generated accounting postings",
                },
            ],
            sectionContent: {
                general_ledger: {
                    badge: "Core Ledger",
                    title: "General Ledger Workspace",
                    summary: "Review account movement, posted balances, and transaction flow across every source module in one ledger-driven workspace.",
                    note: "The general ledger should be the single source of truth after sales, purchases, expenses, payments, and inventory events are posted.",
                    panelTitle: "General Ledger Focus",
                    panelSubtitle: "Core capabilities commonly expected in an operational ledger screen.",
                    cards: [
                        {
                            title: "Account Activity",
                            description: "Show chronological postings per account with references back to the originating transaction.",
                            icon: "ri-time-line",
                        },
                        {
                            title: "Running Balance",
                            description: "Track how each debit and credit changes the live balance of the selected account.",
                            icon: "ri-bar-chart-grouped-line",
                        },
                        {
                            title: "Source Traceability",
                            description: "Link ledger lines to sales orders, receipts, expenses, stock receipts, and adjustments.",
                            icon: "ri-links-line",
                        },
                        {
                            title: "Period Review",
                            description: "Filter by posting period, posting date, or account range for month-end review.",
                            icon: "ri-calendar-check-line",
                        },
                    ],
                    steps: [
                        "Add account-level ledger views with running balances.",
                        "Support date range, account code, and source document filters.",
                        "Show drill-down from ledger line to transaction origin.",
                        "Protect closed periods from new postings.",
                    ],
                },
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
                profit_loss: {
                    badge: "Reporting",
                    title: "Profit & Loss Reporting",
                    summary: "Turn posted revenue and expense activity into a clean operating performance view for daily and monthly decision-making.",
                    note: "Profit and loss should summarize posted activity only, not draft or incomplete transactions.",
                    panelTitle: "P&L Structure",
                    panelSubtitle: "Recommended layers for a practical income statement in your system.",
                    cards: [
                        {
                            title: "Revenue Sections",
                            description: "Separate rice sales, returns, allowances, and other income for better visibility.",
                            icon: "ri-funds-line",
                        },
                        {
                            title: "Cost Of Sales",
                            description: "Present cost of goods sold directly from inventory-out postings and stock returns.",
                            icon: "ri-shopping-basket-line",
                        },
                        {
                            title: "Operating Expenses",
                            description: "Group utilities, transportation, supplies, maintenance, and other operating costs.",
                            icon: "ri-wallet-line",
                        },
                        {
                            title: "Net Income",
                            description: "Summarize final profitability for the selected period with comparative trend context.",
                            icon: "ri-arrow-up-circle-line",
                        },
                    ],
                    steps: [
                        "Map journal lines to revenue, cost, and expense categories.",
                        "Support current month, prior month, and YTD views.",
                        "Expose net sales after returns and allowances.",
                        "Add printable management report formatting.",
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
                accounts_receivable: {
                    badge: "Collections",
                    title: "Accounts Receivable Control",
                    summary: "Monitor open customer balances, aging, collections, and receipt application from posted sales activity.",
                    note: "Receivables should come from credit sales and shrink only when receipts or credit adjustments are posted.",
                    panelTitle: "Receivable Workstreams",
                    panelSubtitle: "Useful views for keeping customer balances accurate and collectible.",
                    cards: [
                        {
                            title: "Customer Aging",
                            description: "Break down balances into current, 30-day, 60-day, and overdue buckets.",
                            icon: "ri-user-received-2-line",
                        },
                        {
                            title: "Invoice Status",
                            description: "Track open, partial, paid, overdue, and voided receivable items.",
                            icon: "ri-file-warning-line",
                        },
                        {
                            title: "Receipt Matching",
                            description: "Connect receipts to invoices and keep the receivable ledger synchronized.",
                            icon: "ri-exchange-dollar-line",
                        },
                        {
                            title: "Collection Notes",
                            description: "Surface due dates, follow-up priorities, and collection trends for operations.",
                            icon: "ri-chat-check-line",
                        },
                    ],
                    steps: [
                        "Add customer-level receivable balances and aging buckets.",
                        "Show invoice-to-receipt application details.",
                        "Flag overdue balances prominently.",
                        "Support collection and follow-up reporting.",
                    ],
                },
                accounts_payable: {
                    badge: "Disbursements",
                    title: "Accounts Payable Control",
                    summary: "Manage supplier obligations created by stock receipts, billings, and expense recognition workflows.",
                    note: "Payables should increase when inventory or billable obligations are recognized and decrease only when payments are posted.",
                    panelTitle: "Payable Workstreams",
                    panelSubtitle: "Practical controls for monitoring vendor obligations and due dates.",
                    cards: [
                        {
                            title: "Supplier Aging",
                            description: "See payable balances by supplier and due-date bucket to schedule payments well.",
                            icon: "ri-group-line",
                        },
                        {
                            title: "Open Obligations",
                            description: "List received-but-unpaid stock receipts and other outstanding supplier claims.",
                            icon: "ri-inbox-archive-line",
                        },
                        {
                            title: "Payment Planning",
                            description: "Prepare due-date and cash-flow views before posting supplier payments.",
                            icon: "ri-calendar-todo-line",
                        },
                        {
                            title: "Vendor Traceability",
                            description: "Link payable balances back to purchase orders, receipts, and payment records.",
                            icon: "ri-route-line",
                        },
                    ],
                    steps: [
                        "Build supplier balance and aging summaries.",
                        "Link payable records to received stock and expense sources.",
                        "Track partial and full settlements cleanly.",
                        "Add payable due-date reporting for cash planning.",
                    ],
                },
                chart_of_accounts: {
                    badge: "Structure",
                    title: "Chart Of Accounts Setup",
                    summary: "Define the account hierarchy and posting rules that every operational module will use when generating journals.",
                    note: "The chart of accounts is the mapping backbone for automatic posting across sales, purchases, inventory, and expenses.",
                    panelTitle: "Chart Of Accounts Foundations",
                    panelSubtitle: "Configuration blocks needed before deeper reporting becomes reliable.",
                    cards: [
                        {
                            title: "Account Codes",
                            description: "Use clear numbering for assets, liabilities, equity, revenue, and expenses.",
                            icon: "ri-hashtag",
                        },
                        {
                            title: "Posting Rules",
                            description: "Map each source transaction type to the correct debit and credit accounts.",
                            icon: "ri-git-merge-line",
                        },
                        {
                            title: "Grouping",
                            description: "Tag accounts for general ledger, trial balance, P&L, and balance sheet reporting.",
                            icon: "ri-folders-line",
                        },
                        {
                            title: "Activation Control",
                            description: "Keep old accounts inactive without losing historical reporting continuity.",
                            icon: "ri-toggle-line",
                        },
                    ],
                    steps: [
                        "Create account master records with codes, names, and report groups.",
                        "Support parent-child account hierarchy.",
                        "Add account activation and posting restrictions.",
                        "Map source modules to default posting accounts.",
                    ],
                },
                journal_entries: {
                    badge: "Audit Trail",
                    title: "Journal Entries Workspace",
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
            return this.sectionContent[this.activeTab] || this.sectionContent.general_ledger;
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
