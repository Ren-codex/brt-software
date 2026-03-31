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
            <Link
                v-for="tab in tabs"
                :key="tab.id"
                :href="tab.href"
                class="inventory-sidebar-tab accounting-nav-link"
                :class="{ 'inventory-tab-active': activeTab === tab.id }"
                @click.prevent="handleTabClick(tab)"
            >
                <div class="inventory-tab-icon">
                    <i :class="tab.icon"></i>
                </div>
                <div v-if="!isSidebarCollapsed" class="inventory-tab-text">
                    <span class="inventory-tab-title">{{ tab.label }}</span>
                    <span class="inventory-tab-subtitle">{{ tab.description }}</span>
                </div>
            </Link>
        </div>

        <div class="inventory-sidebar-footer" v-if="!isSidebarCollapsed">
            <div class="inventory-stats">
                <div class="inventory-stat-item">
                    <i class="ri-file-list-3-line"></i>
                    <span>{{ stats.pending_entries || 0 }} Posted Entries</span>
                </div>
                <div class="inventory-stat-item">
                    <i class="ri-book-2-line"></i>
                    <span>{{ tabs.length }} Accounting Sections</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { Link, router } from "@inertiajs/vue3";

export default {
    components: { Link },
    props: {
        activeTab: {
            type: String,
            required: true,
        },
        stats: {
            type: Object,
            default: () => ({
                pending_entries: 0,
            }),
        },
    },
    data() {
        return {
            isSidebarCollapsed: false,
            tabs: [
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
                    id: "accounts_receivable",
                    label: "Accounts Receivable",
                    icon: "ri-file-list-3-line",
                    description: "Customer balances and aging",
                    href: "/accounting/accounts-receivable",
                },
                {
                    id: "accounts_payable",
                    label: "Accounts Payable",
                    icon: "ri-wallet-3-line",
                    description: "Supplier balances and due items",
                    href: "/accounting/accounts-payable",
                },
                {
                    id: "chart_of_accounts",
                    label: "Chart Of Accounts",
                    icon: "ri-node-tree",
                    description: "Account structure and mapping",
                    href: "/accounting/chart-of-accounts",
                },
                {
                    id: "journal_entries",
                    label: "Journal Entries",
                    icon: "ri-book-2-line",
                    description: "Generated postings and audit trail",
                    href: "/accounting/journal-entries",
                },
            ],
        };
    },
    methods: {
        toggleSidebar() {
            this.isSidebarCollapsed = !this.isSidebarCollapsed;
        },
        handleTabClick(tab) {
            localStorage.setItem("accounting_active_tab", tab.id);

            const currentPath = window.location.pathname.replace(/\/+$/, "") || "/";
            const targetUrl = new URL(tab.href, window.location.origin);
            const targetPath = targetUrl.pathname.replace(/\/+$/, "") || "/";

            if (currentPath === targetPath) {
                return;
            }

            router.visit(tab.href, {
                preserveScroll: true,
            });
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
</style>
