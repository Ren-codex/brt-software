<template>
    <div>
        <PageHeader title="Accounting Module" pageTitle="Journal Entries" />

        <div class="inventory-container">
            <AccountingSidebar active-tab="journal_entries" :stats="stats" />

            <div class="inventory-main">
                <div class="journal-shell">
                    <div class="journal-header">
                        <div>
                            <p class="journal-kicker mb-1">Accounting Audit Trail</p>
                            <h3 class="journal-title mb-1">Journal Entries And Reversals</h3>
                            <p class="journal-subtitle mb-0">
                                Review auto-generated postings, see their source transaction, and trace every reversal back to the original journal entry.
                            </p>
                        </div>
                    </div>

                    <div class="journal-filters">
                        <div class="filter-control">
                            <i class="ri-search-line"></i>
                            <input v-model="filter.keyword" type="text" class="filter-input" placeholder="Search journal number, memo, or status" />
                        </div>
                        <div class="filter-control">
                            <i class="ri-filter-3-line"></i>
                            <select v-model="filter.status" class="filter-input">
                                <option :value="null">All Statuses</option>
                                <option value="posted">Posted</option>
                                <option value="reversed">Reversed</option>
                                <option value="reversal_posted">Reversal Posted</option>
                            </select>
                        </div>
                        <div class="filter-control">
                            <i class="ri-file-list-3-line"></i>
                            <select v-model="filter.entry_type" class="filter-input">
                                <option :value="null">All Entry Types</option>
                                <option v-for="type in entryTypes" :key="type" :value="type">
                                    {{ formatLabel(type) }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="stats-row">
                        <div class="mini-stat">
                            <span class="mini-stat-label">Pending Entries</span>
                            <strong class="mini-stat-value">{{ stats.pending_entries }}</strong>
                        </div>
                        <div class="mini-stat">
                            <span class="mini-stat-label">Active Originals</span>
                            <strong class="mini-stat-value">{{ stats.unreconciled_items }}</strong>
                        </div>
                        <div class="mini-stat">
                            <span class="mini-stat-label">Reversals Logged</span>
                            <strong class="mini-stat-value">{{ stats.generated_reports }}</strong>
                        </div>
                    </div>

                    <div v-if="!journalFeatures.reversal_ready" class="compatibility-banner">
                        <div class="compatibility-icon">
                            <i class="ri-error-warning-line"></i>
                        </div>
                        <div>
                            <p class="compatibility-title mb-1">Migration Needed For Full Reversal Tracking</p>
                            <p class="compatibility-text mb-0">
                                {{ journalFeatures.compatibility_message }}
                            </p>
                        </div>
                    </div>

                    <div class="table-responsive journal-table-wrap">
                        <table class="table align-middle journal-table mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Journal No.</th>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Source</th>
                                    <th>Status</th>
                                    <th>Original / Reversal Link</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="lists.length === 0">
                                    <td colspan="7" class="empty-cell">
                                        <div class="empty-state">
                                            <i class="ri-book-open-line"></i>
                                            <p class="mb-0">No journal entries found.</p>
                                        </div>
                                    </td>
                                </tr>
                                <template v-for="(entry, index) in lists" :key="entry.id">
                                    <tr class="journal-row" @click="toggleExpanded(entry.id)">
                                        <td>{{ index + 1 }}</td>
                                        <td>
                                            <div class="journal-primary">
                                                <strong>{{ entry.journal_number }}</strong>
                                                <small>{{ entry.memo || "No memo" }}</small>
                                            </div>
                                        </td>
                                        <td>{{ entry.entry_date }}</td>
                                        <td>{{ entry.entry_type }}</td>
                                        <td>{{ entry.source_type }} #{{ entry.source_id || "-" }}</td>
                                        <td>
                                            <span class="status-chip" :class="statusClass(entry.status)">
                                                {{ formatLabel(entry.status) }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="link-stack">
                                                <span v-if="entry.reversal_of" class="link-chip reversal-origin">
                                                    Reversal of {{ entry.reversal_of.journal_number }}
                                                </span>
                                                <span v-else-if="entry.reversals.length" class="link-chip reversal-has">
                                                    {{ entry.reversals.length }} reversal(s)
                                                </span>
                                                <span v-else class="link-chip link-neutral">
                                                    Original only
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="expandedRows.includes(entry.id)" class="details-row">
                                        <td colspan="7">
                                            <div class="details-grid">
                                                <div class="detail-card">
                                                    <h6 class="detail-title">Entry Details</h6>
                                                    <p><strong>Memo:</strong> {{ entry.memo || "-" }}</p>
                                                    <p><strong>Source:</strong> {{ entry.source_type }} #{{ entry.source_id || "-" }}</p>
                                                    <p><strong>Reversed At:</strong> {{ entry.reversed_at || "-" }}</p>
                                                    <p><strong>Reason:</strong> {{ entry.reversal_reason || "-" }}</p>
                                                </div>

                                                <div class="detail-card">
                                                    <h6 class="detail-title">Linked Journals</h6>
                                                    <p v-if="entry.reversal_of">
                                                        <strong>Original:</strong> {{ entry.reversal_of.journal_number }} on {{ entry.reversal_of.entry_date }}
                                                    </p>
                                                    <div v-if="entry.reversals.length">
                                                        <p class="mb-2"><strong>Reversals:</strong></p>
                                                        <div v-for="reversal in entry.reversals" :key="reversal.id" class="reversal-item">
                                                            <span>{{ reversal.journal_number }}</span>
                                                            <small>{{ reversal.entry_date }} • {{ formatLabel(reversal.status) }}</small>
                                                        </div>
                                                    </div>
                                                    <p v-if="!entry.reversal_of && !entry.reversals.length" class="mb-0">No linked reversal journal.</p>
                                                </div>
                                            </div>

                                            <div class="line-table-wrap">
                                                <table class="table line-table mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>Account</th>
                                                            <th>Code</th>
                                                            <th>Line Type</th>
                                                            <th>Amount</th>
                                                            <th>Description</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr v-for="line in entry.lines" :key="line.id">
                                                            <td>{{ line.account_name || "-" }}</td>
                                                            <td>{{ line.account_code || "-" }}</td>
                                                            <td>{{ formatLabel(line.line_type) }}</td>
                                                            <td>₱{{ line.amount }}</td>
                                                            <td>{{ line.description || "-" }}</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="journal-footer">
                        <Pagination
                            v-if="meta && meta.links"
                            :lists="lists.length"
                            :links="links"
                            :pagination="meta"
                            @fetch="fetch"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import _ from "lodash";
import PageHeader from "@/Shared/Components/PageHeader.vue";
import AccountingSidebar from "@/Pages/Modules/Accounting/Components/AccountingSidebar.vue";
import Pagination from "@/Shared/Components/Pagination.vue";

export default {
    components: { PageHeader, AccountingSidebar, Pagination },
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
        journalFeatures: {
            type: Object,
            default: () => ({
                reversal_ready: true,
                compatibility_message: null,
            }),
        },
    },
    data() {
        return {
            lists: [],
            meta: {},
            links: {},
            expandedRows: [],
            filter: {
                keyword: null,
                status: null,
                entry_type: null,
            },
            entryTypes: [
                "sales_revenue",
                "inventory_out",
                "receipt_collection",
                "refund_receipt",
                "sales_return_revenue",
                "sales_return_inventory",
                "expense_release",
                "purchase_receipt",
                "purchase_receipt_cash",
                "inventory_adjustment",
                "reversal",
            ],
        };
    },
    watch: {
        "filter.keyword": _.debounce(function () {
            this.fetch();
        }, 300),
        "filter.status"() {
            this.fetch();
        },
        "filter.entry_type"() {
            this.fetch();
        },
    },
    created() {
        this.fetch();
    },
    methods: {
        fetch(pageUrl) {
            pageUrl = pageUrl || "/accounting/journal-entries";

            axios.get(pageUrl, {
                params: {
                    option: "lists",
                    keyword: this.filter.keyword,
                    status: this.filter.status,
                    entry_type: this.filter.entry_type,
                    count: 10,
                },
            }).then((response) => {
                this.lists = response.data.data || [];
                this.meta = response.data.meta || {};
                this.links = response.data.links || {};
            });
        },
        toggleExpanded(id) {
            if (this.expandedRows.includes(id)) {
                this.expandedRows = this.expandedRows.filter((rowId) => rowId !== id);
                return;
            }

            this.expandedRows.push(id);
        },
        formatLabel(value) {
            return String(value || "")
                .replace(/_/g, " ")
                .replace(/\b\w/g, (char) => char.toUpperCase());
        },
        statusClass(status) {
            return {
                posted: status === "posted",
                reversed: status === "reversed",
                reversal: status === "reversal_posted",
            };
        },
    },
};
</script>

<style scoped>
.journal-shell {
    border: 1px solid rgba(61, 141, 122, 0.12);
    border-radius: 24px;
    background: linear-gradient(180deg, #ffffff 0%, #f7fbfa 100%);
    box-shadow: 0 14px 30px rgba(28, 49, 45, 0.08);
    overflow: hidden;
}

.journal-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.5rem 1.5rem 1rem;
}

.journal-kicker {
    color: #3d8d7a;
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.journal-title {
    color: #20413a;
    font-weight: 700;
}

.journal-subtitle {
    color: #648b74;
    max-width: 760px;
}

.journal-filters,
.stats-row {
    display: grid;
    gap: 1rem;
    padding: 0 1.5rem 1rem;
}

.journal-filters {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.stats-row {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.filter-control,
.mini-stat {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.9rem 1rem;
    border: 1px solid rgba(61, 141, 122, 0.12);
    border-radius: 16px;
    background: #f4faf8;
}

.filter-control i,
.mini-stat-label {
    color: #648b74;
}

.filter-input {
    width: 100%;
    border: 0;
    background: transparent;
    color: #20413a;
    outline: none;
}

.mini-stat {
    flex-direction: column;
    align-items: flex-start;
}

.mini-stat-value {
    color: #20413a;
    font-size: 1.55rem;
}

.compatibility-banner {
    display: flex;
    align-items: flex-start;
    gap: 0.9rem;
    margin: 0 1.5rem 1.25rem;
    padding: 1rem 1.1rem;
    border: 1px solid rgba(214, 141, 41, 0.24);
    border-radius: 18px;
    background: linear-gradient(180deg, #fff7e8 0%, #fffaf1 100%);
}

.compatibility-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    border-radius: 14px;
    background: rgba(214, 141, 41, 0.14);
    color: #b06d18;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.compatibility-title {
    color: #7d5016;
    font-weight: 700;
}

.compatibility-text {
    color: #916437;
    line-height: 1.55;
}

.journal-table-wrap {
    padding: 0 1.5rem 1.5rem;
}

.journal-table thead th {
    background: #edf5f2;
    color: #527267;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.journal-row {
    cursor: pointer;
}

.journal-primary {
    display: grid;
    gap: 0.2rem;
}

.journal-primary strong {
    color: #20413a;
}

.journal-primary small {
    color: #648b74;
}

.status-chip,
.link-chip {
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

.status-chip.reversal {
    background: #eef1fb;
    color: #4c5f9d;
}

.link-stack {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.reversal-origin {
    background: #fff1e7;
    color: #a0641b;
}

.reversal-has {
    background: #e8effe;
    color: #4568b0;
}

.link-neutral {
    background: #edf4f1;
    color: #537267;
}

.details-row td {
    background: #fbfdfc;
}

.details-grid {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    margin-bottom: 1rem;
}

.detail-card {
    padding: 1rem;
    border: 1px solid rgba(61, 141, 122, 0.12);
    border-radius: 16px;
    background: #ffffff;
}

.detail-card p {
    margin-bottom: 0.5rem;
    color: #46655b;
}

.detail-title {
    color: #20413a;
    font-weight: 700;
    margin-bottom: 0.85rem;
}

.reversal-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.65rem 0.75rem;
    margin-bottom: 0.5rem;
    border-radius: 12px;
    background: #f5faf8;
}

.reversal-item span {
    color: #20413a;
    font-weight: 600;
}

.reversal-item small {
    color: #648b74;
}

.line-table-wrap {
    overflow: hidden;
    border: 1px solid rgba(61, 141, 122, 0.12);
    border-radius: 16px;
}

.line-table thead th {
    background: #edf5f2;
    color: #527267;
}

.empty-cell {
    padding: 2rem 1rem;
}

.empty-state {
    display: grid;
    justify-items: center;
    gap: 0.5rem;
    color: #648b74;
}

.empty-state i {
    font-size: 2rem;
}

.journal-footer {
    padding: 0 1.5rem 1.25rem;
}

@media (max-width: 991.98px) {
    .journal-header,
    .details-grid {
        grid-template-columns: 1fr;
        display: grid;
    }

    .journal-filters,
    .stats-row {
        grid-template-columns: 1fr;
    }
}
</style>
