<template>
    <div class="library-card">
        <div class="library-card-header">
            <div class="d-flex align-items-center gap-3">
                <div class="header-icon"><i class="ri-bill-line"></i></div>
                <div>
                    <h4 class="header-title mb-0">Check Monitoring</h4>
                    <p class="header-subtitle mb-0">
                        Checks you collected, and whether they have reached the owner's account yet.
                    </p>
                </div>
            </div>
        </div>

        <div class="library-card-body">
            <!-- Where the cheques stand, at a glance. Each card also filters the list. -->
            <div class="cm-summary">
                <button v-for="card in cards" :key="card.status" type="button" class="cm-card"
                    :class="['tone-' + card.status, { active: filters.status === card.status }]"
                    @click="toggleStatus(card.status)">
                    <span class="cm-card-icon"><i :class="card.icon"></i></span>
                    <span class="cm-card-body">
                        <span class="cm-card-label">{{ card.label }}</span>
                        <span class="cm-card-amount">{{ money(summary[card.status]?.amount) }}</span>
                        <span class="cm-card-count">
                            {{ summary[card.status]?.count || 0 }} {{ (summary[card.status]?.count || 0) === 1 ? 'check' : 'checks' }}
                        </span>
                    </span>
                </button>
            </div>

            <div class="search-section">
                <div class="search-wrapper">
                    <i class="ri-search-line search-icon"></i>
                    <input v-model="filters.keyword" type="text" class="search-input"
                        placeholder="Check number, bank or customer..." @input="debouncedFetch" />
                </div>
                <!-- Only for those who see every cheque. A rep's list is their
                     own received cheques, so "Issued" would always be empty. -->
                <select v-if="seesAll" v-model="filters.direction" class="cm-filter" @change="fetch()">
                    <option value="">All directions</option>
                    <option value="received">Received from customers</option>
                    <option value="issued">Issued to suppliers</option>
                </select>
                <select v-model="filters.status" class="cm-filter" @change="fetch()">
                    <option value="">All statuses</option>
                    <option v-for="s in statuses" :key="s" :value="s">{{ label(s) }}</option>
                </select>
                <SortSelect v-model="sortOption" :fields="[
                    { field: 'check_date', label: 'Check date', type: 'date' },
                    { field: 'amount', label: 'Amount', type: 'number' },
                    { field: 'check_number', label: 'Check no.' },
                ]" @change="fetch()" />
            </div>

            <div class="table-section">
                <div class="table-responsive">
                    <table class="table align-middle mb-0 cm-check-table">
                        <thead>
                            <tr>
                                <th v-if="seesAll">Direction</th>
                                <th>Check No</th>
                                <th>Check Date</th>
                                <th>{{ seesAll ? 'Counterparty' : 'Customer' }}</th>
                                <th>Bank</th>
                                <th class="text-end">Amount</th>
                                <th>Status</th>
                                <th>Received By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <TableLoadingRow v-if="loading" :colspan="columnCount" message="Loading checks..." />
                            <template v-else>
                                <tr v-for="c in rows" :key="c.id">
                                    <td v-if="seesAll">
                                        <span class="cm-direction" :class="c.direction">
                                            <i :class="c.direction === 'issued' ? 'ri-arrow-right-up-line' : 'ri-arrow-left-down-line'"></i>
                                            {{ c.direction === 'issued' ? 'Issued' : 'Received' }}
                                        </span>
                                    </td>
                                    <td class="font-monospace">{{ c.check_number }}</td>
                                    <td class="text-nowrap">{{ shortDate(c.check_date) }}</td>
                                    <td>{{ c.customer?.name || c.supplier?.name || '—' }}</td>
                                    <td class="text-muted">{{ c.bank_name || '—' }}</td>
                                    <td class="text-end fw-semibold">{{ money(c.amount) }}</td>
                                    <td>
                                        <span class="status-badge" :class="badgeTone(c.status)">{{ label(c.status) }}</span>
                                        <div v-if="c.status === 'bounced'" class="cm-note">
                                            {{ c.bounce_reason || 'Returned by the bank' }} — follow this up with the customer.
                                        </div>
                                        <div v-else-if="c.status === 'cleared'" class="cm-note cleared">
                                            Cleared — you are no longer accountable for this one.
                                        </div>
                                    </td>
                                    <td>{{ receivedBy(c) }}</td>
                                </tr>
                                <tr v-if="!rows.length">
                                    <td :colspan="columnCount">
                                        <div class="table-empty-state">
                                            <i class="ri-bill-line"></i>
                                            <p class="mb-1">{{ hasFilters ? 'No checks match' : 'No checks yet' }}</p>
                                            <small v-if="hasFilters">Try a different search or status.</small>
                                            <small v-else>Checks you take from customers appear here until they clear.</small>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="pagination-section" v-if="meta && meta.total > 0">
                <Pagination @fetch="fetch" :lists="rows.length" :links="links" :pagination="meta" />
            </div>

            <!-- Why there are no buttons here. -->
            <p class="cm-readonly">
                <i class="ri-information-line"></i>
                This page is for following your checks. Confirming a check cleared, or recording a bounce, is done by
                Accounting on the Check Register.
            </p>
        </div>
    </div>
</template>

<script>
import axios from "axios";
import Pagination from "@/Shared/Components/Pagination.vue";
import TableLoadingRow from "@/Shared/Components/TableLoadingRow.vue";
import { listSortMixin } from "@/Shared/listSort.js";

/**
 * The sales side of the check register: the same cheques, filters and layout
 * Accounting sees, but for following only.
 *
 * It is read-only on purpose. Confirming a cheque is an Accounting act — a rep
 * able to clear their own cheque would make "cleared" mean nothing — so there
 * are no action buttons here, and the server offers this route no way to act.
 * A sales rep sees the cheques they took; a sales administrator sees them all.
 */
export default {
    components: { Pagination, TableLoadingRow },
    mixins: [listSortMixin],
    data() {
        return {
            rows: [],
            meta: null,
            links: {},
            loading: true,
            timer: null,
            statuses: ['pending', 'cleared', 'bounced'],
            filters: { keyword: '', status: '', direction: '' },
            summary: {},
            cards: [
                { status: 'pending', label: 'Pending', icon: 'ri-time-line' },
                { status: 'cleared', label: 'Cleared', icon: 'ri-checkbox-circle-line' },
                { status: 'bounced', label: 'Bounced', icon: 'ri-error-warning-line' },
            ],
        };
    },
    computed: {
        hasFilters() {
            return !!(this.filters.keyword || this.filters.status || this.filters.direction);
        },
        /**
         * Sales administrators see every cheque, issued ones included; everyone
         * else sees the cheques they received. The same rule the server applies.
         */
        seesAll() {
            return this.can('sales', 'admin');
        },
        columnCount() {
            return this.seesAll ? 8 : 7;
        },
    },
    mounted() {
        this.fetch();
        this.fetchSummary();
    },
    methods: {
        fetch(page_url) {
            // The pagination control passes a URL; everything else passes nothing.
            const url = typeof page_url === 'string' && page_url ? page_url : '/sales/check-monitoring';
            this.loading = true;

            axios.get(url, {
                params: {
                    ...this.sortParams,
                    keyword: this.filters.keyword || null,
                    status: this.filters.status || null,
                    direction: this.filters.direction || null,
                    count: 10,
                },
            })
                .then(({ data }) => {
                    this.rows = data.data ?? [];
                    // The register answers with a plain paginator; reshape it
                    // into what the shared Pagination control expects.
                    this.meta = {
                        current_page: data.current_page,
                        last_page: data.last_page,
                        per_page: data.per_page,
                        total: data.total,
                    };
                    this.links = {
                        first: data.first_page_url,
                        last: data.last_page_url,
                        prev: data.prev_page_url,
                        next: data.next_page_url,
                    };
                })
                .catch(err => console.error('Could not load your checks', err))
                .finally(() => { this.loading = false; });
        },
        fetchSummary() {
            axios.get('/sales/check-monitoring', { params: { option: 'summary' } })
                .then(({ data }) => { this.summary = data || {}; })
                .catch(() => {});
        },
        debouncedFetch() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.fetch(), 300);
        },
        /** A card filters to its status; clicking it again shows everything. */
        toggleStatus(status) {
            this.filters.status = this.filters.status === status ? '' : status;
            this.fetch();
        },
        receivedBy(check) {
            const person = check.received_by;
            if (!person) return '—';
            return person.fullname || [person.firstname, person.lastname].filter(Boolean).join(' ') || '—';
        },
        // A check's status is a plain string, not a list_statuses record, but it
        // reads off the same five tones as every other status in Sales.
        badgeTone(status) {
            return {
                pending: 'attention',
                cleared: 'settled',
                bounced: 'problem',
            }[status] ?? 'progress';
        },
        label(status) {
            // "Pending" is the honest word: it is with the bank, not yet good.
            return { pending: 'Pending', cleared: 'Cleared', bounced: 'Bounced' }[status] ?? status;
        },
        shortDate(value) {
            if (!value) return '—';
            const d = new Date(value);
            if (Number.isNaN(d.getTime())) return String(value);
            return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
        },
        money(value) {
            return `PHP ${Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        },
    },
};
</script>

<style scoped>
.cm-summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.75rem;
    margin-bottom: 1.1rem;
}

.cm-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.8rem 0.9rem;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    background: #fff;
    text-align: left;
    cursor: pointer;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.cm-card:hover { box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06); }
/* The chosen card: a ring as well as a tint, so the selection does not depend
   on telling two colours apart. */
.cm-card.active { box-shadow: 0 0 0 2px currentColor inset; }

.cm-card-icon {
    flex-shrink: 0;
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}
.cm-card-body { display: flex; flex-direction: column; min-width: 0; }
.cm-card-label { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; }
.cm-card-amount { font-size: 1.05rem; font-weight: 700; color: #1f2937; }
.cm-card-count { font-size: 0.74rem; color: #6b7280; }

.tone-pending { color: #b45309; }
.tone-pending .cm-card-icon { background: #fef3c7; }
.tone-cleared { color: #166534; }
.tone-cleared .cm-card-icon { background: #dcfce7; }
.tone-bounced { color: #b42318; }
.tone-bounced .cm-card-icon { background: #fee2e2; }

.cm-filter {
    padding: 0.55rem 2rem 0.55rem 0.75rem;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    background-color: #f9fafb;
    font-size: 0.8125rem;
    color: #374151;
    cursor: pointer;
}
.cm-filter:focus {
    outline: none;
    border-color: var(--brt-focus);
    background-color: #fff;
}

.cm-direction {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.1rem 0.5rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 600;
    white-space: nowrap;
    background: #e0f2fe;
    color: #075985;
}
.cm-direction.issued { background: #ede9fe; color: #5b21b6; }

.cm-check-table tbody td { font-size: 0.85rem; vertical-align: middle; }
.cm-check-table tbody tr { cursor: default; }

.cm-note { font-size: 0.72rem; color: #96231f; margin-top: 2px; }
.cm-note.cleared { color: var(--brt-muted); }

.cm-readonly {
    display: flex;
    gap: 0.45rem;
    margin: 0.9rem 0 0;
    font-size: 0.76rem;
    color: var(--brt-muted);
}

@media (max-width: 767.98px) {
    .cm-summary { grid-template-columns: 1fr; }
    .cm-filter { width: 100%; }
}
</style>
