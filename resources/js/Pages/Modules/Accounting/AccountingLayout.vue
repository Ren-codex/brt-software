<template>
    <div>
        <PageHeader title="Accounting Module" :pageTitle="pageTitle" />
        <div class="inventory-container">
            <AccountingSidebar :active-tab="activeTab" :stats="$page.props.stats || {}" />
            <div class="inventory-main">
                <div class="inventory-main-content">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { usePage } from "@inertiajs/vue3";
import PageHeader from "@/Shared/Components/PageHeader.vue";
import AccountingSidebar from "@/Pages/Modules/Accounting/Components/AccountingSidebar.vue";

const PAGE_META = {
    'Modules/Accounting/Dashboard':          { tab: 'dashboard',           title: 'Dashboard' },
    'Modules/Accounting/GeneralLedger':      { tab: 'general_ledger',      title: 'General Ledger' },
    'Modules/Accounting/TrialBalance':       { tab: 'trial_balance',       title: 'Trial Balance' },
    'Modules/Accounting/ProfitLoss':         { tab: 'profit_loss',         title: 'Profit & Loss' },
    'Modules/Accounting/BalanceSheet':       { tab: 'balance_sheet',       title: 'Balance Sheet' },
    'Modules/Accounting/CashFlow':           { tab: 'cash_flow',           title: 'Cash Flow' },
    'Modules/Accounting/AccountsReceivable': { tab: 'accounts_receivable', title: 'Accounts Receivable' },
    'Modules/Accounting/AccountsPayable':    { tab: 'accounts_payable',    title: 'Accounts Payable' },
    'Modules/Accounting/CashManagement':      { tab: 'cash_management',     title: 'Cash Management' },
    'Modules/Accounting/BankReconciliation':  { tab: 'bank_reconciliation', title: 'Bank Reconciliation' },
    'Modules/Accounting/BankAccounts':        { tab: 'settings',            title: 'Settings' },
    'Modules/Accounting/ChartOfAccounts':    { tab: 'settings',            title: 'Settings' },
    'Modules/Accounting/AccountingSettings': { tab: 'settings',            title: 'Settings' },
    'Modules/Accounting/JournalEntries':     { tab: 'journal_entries',     title: 'Journal Entries' },
};

export default {
    components: { PageHeader, AccountingSidebar },
    setup() {
        return { $page: usePage() };
    },
    computed: {
        meta() {
            return PAGE_META[this.$page.component] || { tab: 'dashboard', title: 'Accounting' };
        },
        activeTab() { return this.meta.tab; },
        pageTitle()  { return this.meta.title; },
    },
};
</script>
