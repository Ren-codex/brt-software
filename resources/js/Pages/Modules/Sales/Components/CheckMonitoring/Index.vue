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
                                <th v-if="canApprove" class="text-center">Actions</th>
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
                                    <td v-if="canApprove" class="text-center">
                                        <button
                                            v-if="canClear(c)"
                                            class="action-btn approve"
                                            v-b-tooltip.hover title="Confirm it cleared"
                                            @click="openConfirm(c)"
                                        >
                                            <i class="ri-check-line"></i>
                                        </button>
                                        <!-- A bounce needs a reason and a
                                             supervisor, both collected on the
                                             register. Nothing half-done here. -->
                                        <span v-else class="cm-no-action">&mdash;</span>
                                    </td>
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

            <!-- Why there are no buttons here — or, for an approver, which
                 one is missing and where it lives. -->
            <p class="cm-readonly" v-if="!canApprove">
                <i class="ri-information-line"></i>
                This page is for following your checks. Confirming a check cleared, or recording a bounce, is done by
                Accounting on the Check Register.
            </p>
            <p class="cm-readonly" v-else>
                <i class="ri-information-line"></i>
                Confirming a cheque here is the same act as on the Check Register, and is recorded the same way.
                A bounce is not: it needs a reason and a supervisor's authorization, so it stays on the
                <Link href="/accounting/check-register">Check Register</Link>.
            </p>
        </div>

        <!-- Modal chrome is global (_library-modal.scss); nothing here restyles it. -->
        <div v-if="confirm.open" class="modal-overlay active" @click.self="closeConfirm">
            <div class="modal-container" style="max-width: 460px">
                <div class="modal-header">
                    <div class="modal-header-icon"><i class="ri-bank-card-line"></i></div>
                    <div>
                        <h5 class="modal-title">Confirm cheque cleared</h5>
                        <p class="modal-subtitle">
                            {{ confirm.check?.check_number }} &middot; {{ money(confirm.check?.amount) }}
                            &middot; {{ confirm.check?.customer?.name || 'customer' }}
                        </p>
                    </div>
                    <button class="close-btn ms-auto" @click="closeConfirm" aria-label="Close">
                        <i class="ri-close-line"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="cm-confirm-line">
                        Recording that the bank paid this cheque. The amount is posted and the customer's
                        invoice is settled by it.
                    </p>
                    <!-- Only when the cheque never carried one. A cheque taken
                         at the door often has no bank on it: nobody there knows
                         where it will be deposited. -->
                    <template v-if="!confirm.check?.bank_name">
                        <label class="form-label" for="cm_bank_name">Which bank paid it?</label>
                        <input
                            id="cm_bank_name"
                            v-model.trim="confirm.bank_name"
                            type="text"
                            class="form-control"
                            placeholder="e.g. BDO"
                            maxlength="255"
                            @keyup.enter="saveConfirm"
                        >
                    </template>
                    <p v-else class="cm-confirm-bank">Bank: <strong>{{ confirm.check.bank_name }}</strong></p>
                    <p v-if="confirm.error" class="cm-confirm-error">{{ confirm.error }}</p>
                </div>
                <div class="modal-footer">
                    <button class="acct-btn-secondary" @click="closeConfirm">Cancel</button>
                    <button class="acct-btn-primary" :disabled="confirm.saving" @click="saveConfirm">
                        {{ confirm.saving ? 'Saving…' : 'Confirm cleared' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from "axios";
import { Link } from "@inertiajs/vue3";
import Pagination from "@/Shared/Components/Pagination.vue";
import TableLoadingRow from "@/Shared/Components/TableLoadingRow.vue";
import { listSortMixin } from "@/Shared/listSort.js";

/**
 * The sales side of the check register: the same cheques, filters and layout
 * Accounting sees, but for following only.
 *
 * Read-only for almost everyone. Confirming a cheque is an Accounting act — a
 * rep able to clear their own cheque would make "cleared" mean nothing — so the
 * only person who sees a button here is one who already holds the Check
 * Register's approver grant, and it posts to that same endpoint, which refuses
 * anyone else whatever this screen chooses to show.
 *
 * A sales rep sees the cheques they took; a sales administrator sees them all.
 */
export default {
    components: { Pagination, TableLoadingRow, Link },
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
            confirm: { open: false, check: null, bank_name: '', error: '', saving: false },
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
        /**
         * The Check Register's own grant, asked for by name: this screen adds a
         * doorway to that approval, never a second way of being allowed it.
         */
        canApprove() {
            return this.can('accounting', 'check_register', 'approver');
        },
        columnCount() {
            return (this.seesAll ? 8 : 7) + (this.canApprove ? 1 : 0);
        },
    },
    mounted() {
        // An approver comes here to work through what is waiting, so that is
        // what they land on. Everyone else still arrives at the whole list.
        if (this.canApprove) {
            this.filters.status = 'pending';
        }

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
        /** Only a cheque from a customer, and only while it is still pending. */
        canClear(check) {
            return check.status === 'pending' && check.direction !== 'issued';
        },
        openConfirm(check) {
            this.confirm = { open: true, check, bank_name: '', error: '', saving: false };
        },
        closeConfirm() {
            this.confirm.open = false;
        },
        saveConfirm() {
            if (!this.confirm.check?.bank_name && !this.confirm.bank_name) {
                this.confirm.error = 'Name the bank that paid it — the amount has to be posted somewhere.';
                return;
            }

            this.confirm.saving = true;
            this.confirm.error = '';

            axios.put(`/accounting/check-register/${this.confirm.check.id}/confirm`, {
                bank_name: this.confirm.bank_name || null,
            })
                .then(() => {
                    this.confirm.open = false;
                    this.fetch();
                    this.fetchSummary();
                })
                // The server refuses for its own reasons too — a deposit it
                // cannot match, a cheque somebody else just cleared — and that
                // message is more use than anything this screen could guess.
                .catch(err => {
                    this.confirm.error = err.response?.data?.message || 'Could not confirm this cheque.';
                })
                .finally(() => { this.confirm.saving = false; });
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

/* Content inside the modal body only; the chrome around it is global. */
.cm-no-action { color: var(--brt-muted); }
.cm-confirm-line { font-size: 0.85rem; color: #16322e; margin-bottom: 0.9rem; }
.cm-confirm-bank { font-size: 0.85rem; color: var(--brt-muted); margin: 0; }
.cm-confirm-error { margin: 0.6rem 0 0; font-size: 0.78rem; color: #96231f; }

@media (max-width: 767.98px) {
    .cm-summary { grid-template-columns: 1fr; }
    .cm-filter { width: 100%; }
}
</style>
