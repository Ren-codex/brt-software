<template>
    <div class="inventory-sidebar" :class="{ 'sidebar-collapsed': isSidebarCollapsed }">
        <div class="inventory-sidebar-header">
            <i class="ri-bank-line"></i>
            <h4 v-if="!isSidebarCollapsed">Accounting</h4>
            <button class="sidebar-toggle-btn" @click="toggleSidebar" title="Toggle Sidebar">
                <i :class="isSidebarCollapsed ? 'ri-arrow-right-s-line' : 'ri-arrow-left-s-line'"></i>
            </button>
        </div>

        <div class="inventory-sidebar-tabs">
            <template v-for="group in groups" :key="group.label">

                <button v-if="!isSidebarCollapsed"
                    class="sidebar-group-label"
                    @click="toggleGroup(group.label)"
                >
                    <span>{{ group.label }}</span>
                    <i class="group-chevron" :class="isGroupOpen(group.label) ? 'ri-arrow-down-s-line' : 'ri-arrow-right-s-line'"></i>
                </button>
                <div v-else class="sidebar-group-divider"></div>

                <template v-if="isSidebarCollapsed || isGroupOpen(group.label)">
                    <Link
                        v-for="tab in group.tabs"
                        :key="tab.id"
                        :href="tab.href"
                        class="inventory-sidebar-tab accounting-nav-link"
                        :class="{ 'inventory-tab-active': activeTab === tab.id }"
                        :preserve-scroll="true"
                    >
                        <div class="inventory-tab-icon">
                            <i :class="tab.icon"></i>
                        </div>
                        <div v-if="!isSidebarCollapsed" class="inventory-tab-text">
                            <span class="inventory-tab-title">{{ tab.label }}</span>
                            <span class="inventory-tab-subtitle">{{ tab.description }}</span>
                        </div>
                    </Link>
                </template>

            </template>
        </div>

        <div class="inventory-sidebar-footer" v-if="!isSidebarCollapsed">
            <div class="inventory-stats">
                <div class="inventory-stat-item">
                    <i class="ri-file-list-3-line"></i>
                    <span>{{ stats.pending_entries || 0 }} Posted Entries</span>
                </div>
                <div class="inventory-stat-item">
                    <i class="ri-book-2-line"></i>
                    <span>{{ totalTabs }} Sections</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { Link } from "@inertiajs/vue3";

export default {
    components: { Link },
    props: {
        activeTab: {
            type: String,
            required: true,
        },
        stats: {
            type: Object,
            default: () => ({ pending_entries: 0 }),
        },
    },
    data() {
        const defaults = { 'Reports': true, 'Receivables & Payables': true, 'Cash & Banking': true, 'Configuration': true };
        const saved = (() => {
            try { return { ...defaults, ...JSON.parse(localStorage.getItem('acct_sidebar_groups') || '{}') }; }
            catch { return defaults; }
        })();
        return {
            isSidebarCollapsed: false,
            collapsedGroups: saved,
            groups: [
                {
                    label: 'Reports',
                    tabs: [
                        {
                            id: "dashboard",
                            label: "Dashboard",
                            icon: "ri-dashboard-line",
                            description: "Financial overview",
                            href: "/accounting",
                        },
                        {
                            id: "general_ledger",
                            label: "General Ledger",
                            icon: "ri-book-open-line",
                            description: "Ledger balances and activity",
                            href: "/accounting/general-ledger",
                        },
                        {
                            id: "trial_balance",
                            label: "Trial Balance",
                            icon: "ri-scales-3-line",
                            description: "Debit and credit checks",
                            href: "/accounting/trial-balance",
                        },
                        {
                            id: "profit_loss",
                            label: "Profit & Loss",
                            icon: "ri-line-chart-line",
                            description: "Income and expense results",
                            href: "/accounting/profit-loss",
                        },
                        {
                            id: "balance_sheet",
                            label: "Balance Sheet",
                            icon: "ri-bank-card-line",
                            description: "Assets and obligations",
                            href: "/accounting/balance-sheet",
                        },
                        {
                            id: "cash_flow",
                            label: "Cash Flow",
                            icon: "ri-funds-box-line",
                            description: "Operating, investing, financing",
                            href: "/accounting/cash-flow",
                        },
                    ],
                },
                {
                    label: 'Receivables & Payables',
                    tabs: [
                        {
                            id: "accounts_receivable",
                            label: "Accounts Receivable",
                            icon: "ri-file-list-3-line",
                            description: "Customer balances and aging",
                            href: "/accounting/accounts-receivable",
                        },
                        {
                            id: "accounts_payable",
                            label: "Accounts Payable",
                            icon: "ri-inbox-archive-line",
                            description: "Supplier balances and aging",
                            href: "/accounting/accounts-payable",
                        },
                    ],
                },
                {
                    label: 'Cash & Banking',
                    tabs: [
                        {
                            id: "cash_management",
                            label: "Cash Management",
                            icon: "ri-exchange-dollar-line",
                            description: "Position, transfers, petty cash",
                            href: "/accounting/cash-management",
                        },
                        {
                            id: "bank_reconciliation",
                            label: "Bank Reconciliation",
                            icon: "ri-bank-card-line",
                            description: "Match books against statements",
                            href: "/accounting/bank-reconciliation",
                        },
                    ],
                },
                {
                    label: 'Configuration',
                    tabs: [
                        {
                            id: "settings",
                            label: "Settings",
                            icon: "ri-settings-3-line",
                            description: "Chart of accounts & bank accounts",
                            href: "/accounting/settings",
                        },
                        {
                            id: "journal_entries",
                            label: "Journal Entries",
                            icon: "ri-book-2-line",
                            description: "Generated postings and audit trail",
                            href: "/accounting/journal-entries",
                        },
                    ],
                },
            ],
        };
    },
    computed: {
        totalTabs() {
            return this.groups.reduce((sum, g) => sum + g.tabs.length, 0);
        },
    },
    watch: {
        activeTab(tabId) {
            const group = this.groups.find(g => g.tabs.some(t => t.id === tabId));
            if (group && !this.isGroupOpen(group.label)) {
                this.collapsedGroups[group.label] = false;
                this.saveGroups();
            }
        },
    },
    methods: {
        toggleSidebar() {
            this.isSidebarCollapsed = !this.isSidebarCollapsed;
        },
        isGroupOpen(label) {
            return this.collapsedGroups[label] !== true;
        },
        toggleGroup(label) {
            this.collapsedGroups[label] = !this.collapsedGroups[label];
            this.saveGroups();
        },
        saveGroups() {
            try { localStorage.setItem('acct_sidebar_groups', JSON.stringify(this.collapsedGroups)); }
            catch {}
        },
    },
};
</script>

<style scoped>
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

.inventory-sidebar.sidebar-collapsed .inventory-sidebar-tab:hover {
    transform: none;
}

.accounting-nav-link {
    text-decoration: none;
    color: inherit;
}

/* Section group labels */
.sidebar-group-label {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 0.6rem 1rem 0.25rem;
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.5);
    margin-top: 0.35rem;
    background: transparent;
    border: none;
    cursor: pointer;
    transition: color 0.15s;
}

.sidebar-group-label:hover {
    color: rgba(255, 255, 255, 0.8);
}

.group-chevron {
    font-size: 0.9rem;
    transition: transform 0.2s ease;
}

/* Thin divider shown in collapsed mode instead of label */
.sidebar-group-divider {
    height: 1px;
    background: rgba(148, 163, 184, 0.2);
    margin: 0.4rem 12px;
}
</style>
