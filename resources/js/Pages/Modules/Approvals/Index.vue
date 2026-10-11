<template>
<Head title="Approvals" />
    <PageHeader title="Approvals" pageTitle="Pending" />

    <BRow>
        <div class="col-md-12">
            <div class="library-card">
                <div class="library-card-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="header-icon">
                            <i class="ri-checkbox-circle-line"></i>
                        </div>
                        <div>
                            <h4 class="header-title mb-1">Approvals</h4>
                            <p class="header-subtitle mb-0">
                                Everything waiting on you, across every module
                                <span v-if="lastUpdatedLabel()" class="ms-1 text-nowrap">&middot; {{ lastUpdatedLabel() }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="approval-total-badge" :class="{ 'is-zero': total === 0 }">
                            {{ total }} pending
                        </span>
                        <button class="create-btn" @click="refresh" :disabled="loading">
                            <i class="ri-refresh-line" :class="{ 'spin': loading }"></i>
                            <span>Refresh</span>
                        </button>
                    </div>
                </div>

                <div class="library-card-body">
                    <!-- Nobody has granted this account an approver level anywhere. -->
                    <div v-if="!groups.length" class="approval-empty">
                        <i class="ri-lock-line"></i>
                        <h6>No approval access</h6>
                        <p>Your account does not hold an approver level on any module. Ask an administrator if you expect to approve something here.</p>
                    </div>

                    <template v-else>
                        <!-- Source filter. Counts stay exact even when a group's
                             rows are capped, so the pills double as a summary. -->
                        <div class="search-section">
                            <div class="search-wrapper">
                                <i class="ri-search-line search-icon"></i>
                                <input
                                    type="text"
                                    v-model="keyword"
                                    placeholder="Search reference, details, requester, amount..."
                                    class="search-input"
                                >
                            </div>
                            <!-- Sorted in the browser: every pending row is already
                                 loaded here, unlike the paged lists elsewhere. -->
                            <SortSelect v-model="sortOption" :added="false" :fields="[
                                { field: 'requested_at', label: 'Requested', type: 'date' },
                                { field: 'amount', label: 'Amount', type: 'number' },
                                { field: 'reference', label: 'Reference' },
                                { field: 'label', label: 'Type' },
                            ]" />
                        </div>

                        <div class="approval-filters">
                            <button class="approval-pill" :class="{ active: activeSource === null }"
                                @click="activeSource = null">
                                All
                                <span class="pill-count">{{ total }}</span>
                            </button>
                            <button v-for="g in groups" :key="g.source"
                                class="approval-pill"
                                :class="{ active: activeSource === g.source, 'is-empty': g.count === 0 }"
                                @click="activeSource = g.source">
                                <i :class="g.icon"></i>
                                {{ g.plural }}
                                <span class="pill-count">{{ g.count }}</span>
                            </button>
                        </div>

                        <div class="table-section">
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Reference</th>
                                            <th>Details</th>
                                            <th class="text-end">Amount</th>
                                            <th>Requested</th>
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <TableLoadingRow v-if="loading && !rows.length" :colspan="6" message="Loading approvals..." />
                                        <tr v-else-if="!rows.length">
                                            <td colspan="6">
                                                <div class="approval-empty py-4">
                                                    <i class="ri-checkbox-multiple-line"></i>
                                                    <template v-if="searchTerm">
                                                        <h6>No matches</h6>
                                                        <p class="mb-0">Nothing pending matches "{{ keyword.trim() }}".</p>
                                                    </template>
                                                    <template v-else>
                                                        <h6>Nothing waiting</h6>
                                                        <p class="mb-0">Everything you can approve has been actioned.</p>
                                                    </template>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-for="row in rows" :key="row.source + '-' + row.id"
                                            :class="{ 'is-focused': isFocused(row) }"
                                            :ref="el => registerRowRef(row, el)">
                                            <td>
                                                <span class="type-chip">
                                                    <i :class="row.icon"></i>
                                                    {{ row.label }}
                                                </span>
                                            </td>
                                            <td class="fw-semibold">{{ row.reference }}</td>
                                            <td class="text-muted">{{ row.summary }}</td>
                                            <td class="text-end fw-semibold">
                                                <span v-if="row.amount !== null">{{ peso(row.amount) }}</span>
                                                <span v-else class="text-muted">&mdash;</span>
                                            </td>
                                            <td>
                                                <div class="fs-12">{{ shortDate(row.requested_at) }}</div>
                                                <div class="fs-11 text-muted" v-if="row.requested_by">{{ row.requested_by }}</div>
                                            </td>
                                            <td class="text-end">
                                                <div class="d-flex gap-1 justify-content-end flex-wrap">
                                                    <button v-if="row.can_approve_inline"
                                                        class="act-btn act-approve"
                                                        :disabled="busyKey === rowKey(row)"
                                                        @click="open(row, 'approved')">
                                                        <i class="ri-check-line"></i> Approve
                                                    </button>
                                                    <button v-if="row.can_reject_inline"
                                                        class="act-btn act-reject"
                                                        :disabled="busyKey === rowKey(row)"
                                                        @click="open(row, 'disapproved')">
                                                        <i class="ri-close-line"></i> Reject
                                                    </button>
                                                    <!-- Some workflows need input this page does not
                                                         collect, so they link out instead of offering a
                                                         button that cannot complete. -->
                                                    <Link v-if="!row.can_approve_inline || !row.can_reject_inline"
                                                        :href="row.url" class="act-btn act-review">
                                                        <i class="ri-external-link-line"></i> Review
                                                    </Link>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="pagination-section" v-if="filteredRows.length">
                            <Pagination @fetch="goToPage" :lists="rows.length" :links="pageLinks" :pagination="pageMeta" />
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </BRow>

    <!-- Decision confirmation. Buttons live in .modal-footer so they stay put
         while a long remark scrolls (see the sticky footer rule in CLAUDE.md). -->
    <div class="modal-overlay" :class="{ active: pending !== null }" v-if="pending !== null">
        <div class="modal-container" style="max-width: 460px;">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="modal-header-icon">
                        <i :class="pending.decision === 'approved' ? 'ri-check-line' : 'ri-close-line'"></i>
                    </div>
                    <div>
                        <h5 class="mb-0">{{ pending.decision === 'approved' ? 'Approve' : 'Reject' }} {{ pending.row.label }}</h5>
                        <small>{{ pending.row.reference }}</small>
                    </div>
                </div>
                <button class="close-btn" @click="pending = null"><i class="ri-close-line"></i></button>
            </div>
            <div class="modal-body">
                <p class="fs-13 mb-3">
                    {{ pending.row.summary }}
                    <span v-if="pending.row.amount !== null" class="fw-semibold d-block mt-1">{{ peso(pending.row.amount) }}</span>
                </p>
                <label class="form-label fs-12 fw-semibold">Remarks <span class="text-muted">(optional)</span></label>
                <textarea v-model="remarks" rows="3" class="form-control" maxlength="1000"
                    placeholder="Why this decision?"></textarea>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" @click="pending = null">Cancel</button>
                <button class="btn"
                    :class="pending.decision === 'approved' ? 'btn-success' : 'btn-danger'"
                    :disabled="submitting"
                    @click="submit">
                    <span v-if="submitting">Working…</span>
                    <span v-else>{{ pending.decision === 'approved' ? 'Approve' : 'Reject' }}</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import PageHeader from '@/Shared/Components/PageHeader.vue';
import Pagination from '@/Shared/Components/Pagination.vue';
import TableLoadingRow from '@/Shared/Components/TableLoadingRow.vue';
import { pollingMixin } from '@/Shared/polling.js';
import { onApprovalChange, announceApprovalChange } from '@/Shared/realtime.js';

export default {
    components: { PageHeader, Pagination, TableLoadingRow },
    mixins: [pollingMixin],
    props: {
        initial: { type: Object, default: () => ({ groups: [], total: 0 }) },
        focusSource: { type: String, default: null },
        focusId: { type: [String, Number], default: null },
    },
    data() {
        return {
            groups: this.initial.groups || [],
            total: this.initial.total || 0,
            activeSource: this.focusSource || null,
            keyword: '',
            // "field:direction"; empty means newest request first.
            sortOption: '',
            page: 1,
            perPage: 10,
            loading: false,
            pending: null,      // { row, decision }
            remarks: '',
            submitting: false,
            busyKey: null,
            stopListening: null,
        };
    },
    created() {
        // Deliberately not in data(): these are DOM nodes, and making them
        // reactive gains nothing while giving Vue a deep object to track.
        this.rowEls = {};
        this.ignoreNextChange = false;
    },
    computed: {
        searchTerm() {
            return this.keyword.trim().toLowerCase();
        },
        /** Flattened rows for the active filter and search, newest request first. */
        filteredRows() {
            const groups = this.activeSource
                ? this.groups.filter(g => g.source === this.activeSource)
                : this.groups;

            const term = this.searchTerm;

            return groups
                .flatMap(g => g.items)
                .filter(row => !term || this.searchText(row).includes(term))
                .sort(this.rowComparator);
        },
        /** Compares two rows by the chosen sort; newest request first by default. */
        rowComparator() {
            const [field, direction] = (this.sortOption || 'requested_at:desc').split(':');
            const sign = direction === 'asc' ? 1 : -1;

            const value = row => {
                if (field === 'requested_at') return new Date(row.requested_at || 0).getTime();
                // Rows with no amount (stock returns) sort as zero rather than NaN.
                if (field === 'amount') return Number(row.amount) || 0;
                return String(row[field] ?? '').toLowerCase();
            };

            return (a, b) => {
                const x = value(a);
                const y = value(b);
                if (x < y) return -1 * sign;
                if (x > y) return 1 * sign;
                // Ties fall back to newest first, so the order is always stable.
                return new Date(b.requested_at || 0) - new Date(a.requested_at || 0);
            };
        },
        lastPage() {
            return Math.max(1, Math.ceil(this.filteredRows.length / this.perPage));
        },
        /** The slice of filteredRows the table is showing. */
        rows() {
            const start = (this.page - 1) * this.perPage;
            return this.filteredRows.slice(start, start + this.perPage);
        },
        // The shared Pagination component was built for Laravel's paginator, so
        // these two give it the same shape. The rows are already in the browser,
        // which is why the "links" are page numbers rather than URLs.
        pageMeta() {
            return {
                current_page: this.page,
                per_page: this.perPage,
                last_page: this.lastPage,
                total: this.filteredRows.length,
            };
        },
        pageLinks() {
            return {
                first: 1,
                prev: this.page > 1 ? this.page - 1 : null,
                next: this.page < this.lastPage ? this.page + 1 : null,
                last: this.lastPage,
            };
        },
    },
    watch: {
        // A new search or filter starts from its first page.
        searchTerm() { this.page = 1; },
        sortOption() { this.page = 1; },
        activeSource() { this.page = 1; },
        // Approving the last row on the last page must not leave an empty page.
        lastPage(last) {
            if (this.page > last) this.page = last;
        },
    },
    methods: {
        goToPage(page) {
            if (page) this.page = page;
        },
        /**
         * Everything on a row someone might type to find it. The amount is
         * included both raw and formatted so "15000" and "15,000.00" both hit.
         */
        searchText(row) {
            return [
                row.label,
                row.reference,
                row.summary,
                row.requested_by,
                row.amount !== null ? row.amount : null,
                row.amount !== null ? this.peso(row.amount) : null,
                this.shortDate(row.requested_at),
            ].filter(v => v !== null && v !== undefined).join(' ').toLowerCase();
        },
        rowKey(row) {
            return `${row.source}-${row.id}`;
        },
        isFocused(row) {
            return this.focusSource === row.source && String(this.focusId) === String(row.id);
        },
        registerRowRef(row, el) {
            if (el) this.rowEls[this.rowKey(row)] = el;
        },
        peso(value) {
            return '₱' + Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        shortDate(value) {
            if (!value) return '—';
            const d = new Date(value);
            if (Number.isNaN(d.getTime())) return String(value);
            return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
        },
        fetch({ quiet = false } = {}) {
            if (!quiet) this.loading = true;

            return axios.get('/approvals?option=lists')
                .then(res => {
                    this.groups = res.data.groups || [];
                    this.total = res.data.total || 0;
                })
                .catch(() => {
                    if (!quiet) this.$toast?.error('Could not load approvals.');
                })
                .finally(() => { this.loading = false; });
        },
        refresh() {
            return this.fetch();
        },
        open(row, decision) {
            this.pending = { row, decision };
            this.remarks = '';
        },
        submit() {
            if (!this.pending || this.submitting) return;

            const { row, decision } = this.pending;
            this.submitting = true;
            this.busyKey = this.rowKey(row);

            axios.post(`/approvals/${row.source}/${row.id}/decide`, {
                decision,
                remarks: this.remarks || null,
            })
                .then(res => {
                    this.$toast?.success(res.data.message || 'Decision recorded.');
                    // The response carries the refreshed list, so the row
                    // disappears without a second round trip.
                    if (res.data.pending) {
                        this.groups = res.data.pending.groups || [];
                        this.total = res.data.pending.total || 0;
                    }
                    this.pending = null;
                    // Updates the bell in this browser at once; other approvers are
                    // told by the server-side broadcast.
                    this.ignoreNextChange = true;
                    announceApprovalChange();
                })
                .catch(err => {
                    // 409 means somebody else got there first; 422 is a module's
                    // own rule refusing it. Both are worth showing verbatim.
                    const message = err?.response?.data?.message
                        || 'Could not record that decision.';
                    this.$toast?.error(message);
                    if (err?.response?.status === 409) this.fetch({ quiet: true });
                })
                .finally(() => {
                    this.submitting = false;
                    this.busyKey = null;
                });
        },
    },
    mounted() {
        // Live where the socket works, polled where it does not.
        this.stopListening = onApprovalChange(this, () => {
            // Our own announcement: the decide response already carried the
            // fresh list, so refetching it again would be a wasted request.
            if (this.ignoreNextChange) {
                this.ignoreNextChange = false;
                return;
            }
            this.fetch({ quiet: true });
        });
        this.startPolling(() => this.fetch({ quiet: true }), 10000);

        if (this.focusSource && this.focusId) {
            // Turn to the page the row is on before scrolling to it.
            const index = this.filteredRows.findIndex(row => this.isFocused(row));
            if (index >= 0) this.page = Math.floor(index / this.perPage) + 1;

            this.$nextTick(() => {
                const el = this.rowEls[`${this.focusSource}-${this.focusId}`];
                el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        }
    },
    beforeUnmount() {
        this.stopListening?.();
    },
};
</script>

<style scoped>
/* Content-specific only — modal chrome comes from _library-modal.scss. */
.approval-total-badge {
    background: rgba(var(--brt-primary-rgb), 0.12);
    border: 1px solid rgba(var(--brt-primary-rgb), 0.2);
    color: var(--brt-ink);
    font-size: 0.78rem;
    font-weight: 700;
    padding: 0.35rem 0.7rem;
    border-radius: 8px;
    white-space: nowrap;
}
.approval-total-badge.is-zero {
    background: #f3f4f6;
    border-color: #e5e7eb;
    color: #6b7280;
}

.approval-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-bottom: 1.1rem;
}
.approval-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: #f9fafb;
    border: 1.5px solid #e5e7eb;
    color: #374151;
    border-radius: 999px;
    padding: 0.35rem 0.8rem;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.18s ease;
}
.approval-pill:hover { border-color: var(--brt-primary); color: var(--brt-ink); }
.approval-pill.active {
    background: var(--brt-primary);
    border-color: var(--brt-primary);
    color: #fff;
}
.approval-pill.is-empty { opacity: 0.55; }
.pill-count {
    background: rgba(0, 0, 0, 0.08);
    border-radius: 999px;
    padding: 0.05rem 0.4rem;
    font-size: 0.7rem;
}
.approval-pill.active .pill-count { background: rgba(255, 255, 255, 0.25); }

.type-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    background: rgba(var(--brt-primary-rgb), 0.1);
    color: var(--brt-primary-dark);
    border-radius: 6px;
    padding: 0.2rem 0.5rem;
    font-size: 0.72rem;
    font-weight: 600;
    white-space: nowrap;
}

.act-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    border: none;
    border-radius: 7px;
    padding: 0.3rem 0.6rem;
    font-size: 0.73rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.18s ease;
    white-space: nowrap;
}
.act-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.act-approve { background: var(--brt-primary); color: #fff; }
.act-approve:hover:not(:disabled) { background: var(--brt-primary-hover); }
.act-reject { background: #fee2e2; color: #b42318; }
.act-reject:hover:not(:disabled) { background: #fecaca; }
.act-review { background: #eef2f7; color: #334155; }
.act-review:hover { background: #e2e8f0; color: #1e293b; }

tr.is-focused {
    /* The row a notification pointed at. */
    background-color: #fffbea !important;
    box-shadow: inset 3px 0 0 #f59e0b;
}

.approval-empty {
    text-align: center;
    padding: 2.5rem 1rem;
    color: #6b7280;
}
.approval-empty i { font-size: 2rem; color: #9ca3af; display: block; margin-bottom: 0.5rem; }
.approval-empty h6 { color: #374151; font-weight: 700; margin-bottom: 0.25rem; }
.approval-empty p { font-size: 0.8rem; max-width: 420px; margin: 0 auto; }

.spin { animation: approval-spin 0.9s linear infinite; }
@keyframes approval-spin { to { transform: rotate(360deg); } }

@media (max-width: 575.98px) {
    .approval-filters { gap: 0.3rem; }
    .approval-pill { font-size: 0.72rem; padding: 0.3rem 0.6rem; }
}
</style>
