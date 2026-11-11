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
                        <button class="create-btn" @click="refresh" :disabled="loading || historyLoading">
                            <i class="ri-refresh-line" :class="{ 'spin': loading || historyLoading }"></i>
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
                        <!-- Pending is what still needs a decision; Approved and
                             Disapproved are what has had one, here or on the
                             module's own screen. -->
                        <div class="filter-segment mb-3">
                            <button type="button" class="filter-segment-btn"
                                :class="{ active: tab === 'pending' }" @click="tab = 'pending'">
                                <i class="ri-time-line"></i>
                                <span>Pending</span>
                                <span class="segment-count">{{ total }}</span>
                            </button>
                            <button type="button" class="filter-segment-btn"
                                :class="{ active: tab === 'approved' }" @click="tab = 'approved'">
                                <i class="ri-checkbox-circle-line"></i>
                                <span>Approved</span>
                            </button>
                            <button type="button" class="filter-segment-btn"
                                :class="{ active: tab === 'rejected' }" @click="tab = 'rejected'">
                                <i class="ri-close-circle-line"></i>
                                <span>Disapproved</span>
                            </button>
                        </div>

                        <!-- Source filter. Counts stay exact even when a group's
                             rows are capped, so the pills double as a summary. -->
                        <div class="search-section">
                            <div class="search-wrapper">
                                <i class="ri-search-line search-icon"></i>
                                <input
                                    type="text"
                                    v-model="keyword"
                                    :placeholder="tab === 'pending'
                                        ? 'Search reference, details, requester, amount...'
                                        : 'Search reference, details, decision, decided by, remarks...'"
                                    class="search-input"
                                >
                            </div>
                            <!-- Sorted in the browser: every row is already loaded
                                 here, unlike the paged lists elsewhere. -->
                            <SortSelect v-model="sortOption" :added="false" :fields="sortFields" />
                        </div>

                        <div class="approval-filters">
                            <button class="approval-pill" :class="{ active: activeSource === null }"
                                @click="activeSource = null">
                                All
                                <span class="pill-count">{{ currentTotal }}</span>
                            </button>
                            <button v-for="g in currentGroups" :key="g.source"
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
                                            <template v-if="tab === 'pending'">
                                                <th>Requested</th>
                                                <th class="text-end">Action</th>
                                            </template>
                                            <template v-else>
                                                <th>Decision</th>
                                                <th>Decided</th>
                                                <th class="text-end">Action</th>
                                            </template>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <TableLoadingRow v-if="currentLoading && !rows.length" :colspan="columnCount"
                                            :message="tab === 'pending' ? 'Loading approvals...' : 'Loading decisions...'" />
                                        <tr v-else-if="!rows.length">
                                            <td :colspan="columnCount">
                                                <div class="approval-empty py-4">
                                                    <i class="ri-checkbox-multiple-line"></i>
                                                    <template v-if="searchTerm">
                                                        <h6>No matches</h6>
                                                        <p class="mb-0">Nothing {{ tabWord }} matches "{{ keyword.trim() }}".</p>
                                                    </template>
                                                    <template v-else-if="tab === 'pending'">
                                                        <h6>Nothing waiting</h6>
                                                        <p class="mb-0">Everything you can approve has been actioned.</p>
                                                    </template>
                                                    <template v-else>
                                                        <h6>Nothing {{ tabWord }} yet</h6>
                                                        <p class="mb-0">Anything {{ tabWord }} among what you can approve will be listed here.</p>
                                                    </template>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-for="row in rows" :key="rowKey(row)"
                                            :class="{ 'is-focused': tab === 'pending' && isFocused(row) }"
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
                                            <template v-if="tab !== 'pending'">
                                                <td>
                                                    <span class="decision-chip" :class="row.decision === 'approved' ? 'is-approved' : 'is-rejected'">
                                                        <i :class="row.decision === 'approved' ? 'ri-check-line' : 'ri-close-line'"></i>
                                                        {{ row.decision_label }}
                                                    </span>
                                                    <div v-if="row.remarks" class="fs-11 text-muted mt-1 decision-remarks">{{ row.remarks }}</div>
                                                </td>
                                                <td>
                                                    <div class="fs-12">{{ dateTime(row.decided_at) }}</div>
                                                    <div class="fs-11 text-muted" v-if="row.decided_by">{{ row.decided_by }}</div>
                                                </td>
                                                <td class="text-end">
                                                    <button class="act-btn act-view" v-b-tooltip.hover title="View"
                                                        aria-label="View" @click="viewDetails(row)">
                                                        <i class="ri-eye-line"></i>
                                                    </button>
                                                </td>
                                            </template>
                                            <td v-if="tab === 'pending'">
                                                <div class="fs-12">{{ shortDate(row.requested_at) }}</div>
                                                <div class="fs-11 text-muted" v-if="row.requested_by">{{ row.requested_by }}</div>
                                            </td>
                                            <td v-if="tab === 'pending'" class="text-end">
                                                <div class="d-flex gap-1 justify-content-end flex-wrap">
                                                    <!-- Icon only; the name is the tooltip. -->
                                                    <button class="act-btn act-view" v-b-tooltip.hover title="View"
                                                        aria-label="View" @click="viewDetails(row)">
                                                        <i class="ri-eye-line"></i>
                                                    </button>
                                                    <button v-if="row.can_approve_inline"
                                                        class="act-btn act-approve"
                                                        v-b-tooltip.hover title="Approve" aria-label="Approve"
                                                        :disabled="busyKey === rowKey(row)"
                                                        @click="open(row, 'approved')">
                                                        <i class="ri-check-line"></i>
                                                    </button>
                                                    <button v-if="row.can_reject_inline"
                                                        class="act-btn act-reject"
                                                        v-b-tooltip.hover title="Reject" aria-label="Reject"
                                                        :disabled="busyKey === rowKey(row)"
                                                        @click="open(row, 'disapproved')">
                                                        <i class="ri-close-line"></i>
                                                    </button>
                                                    <!-- Some workflows need input this page does not
                                                         collect, so they link out instead of offering a
                                                         button that cannot complete. -->
                                                    <Link v-if="!row.can_approve_inline || !row.can_reject_inline"
                                                        :href="reviewUrl(row)" class="act-btn act-review"
                                                        v-b-tooltip.hover title="Review" aria-label="Review">
                                                        <i class="ri-external-link-line"></i>
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

    <!-- View: one record's details, in the same shape for every type. -->
    <div class="modal-overlay active" v-if="detail" @click.self="detail = null">
        <div class="modal-container" style="max-width: 820px;">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="modal-header-icon">
                        <i :class="detail.row.icon"></i>
                    </div>
                    <div>
                        <h5 class="mb-0">{{ detail.data?.title || detail.row.label }}</h5>
                        <small>{{ detail.data?.reference || detail.row.reference }}</small>
                    </div>
                </div>
                <button class="close-btn" @click="detail = null"><i class="ri-close-line"></i></button>
            </div>
            <div class="modal-body">
                <div v-if="detail.loading" class="approval-empty py-4">
                    <i class="ri-loader-4-line spin"></i>
                    <p class="mb-0">Loading details…</p>
                </div>
                <div v-else-if="detail.error" class="approval-empty py-4">
                    <i class="ri-error-warning-line"></i>
                    <p class="mb-0">{{ detail.error }}</p>
                </div>
                <template v-else-if="detail.data">
                    <!-- Opened from Approved or Disapproved: the decision first. -->
                    <div v-if="detail.row.decision" class="detail-decision">
                        <span class="decision-chip" :class="detail.row.decision === 'approved' ? 'is-approved' : 'is-rejected'">
                            <i :class="detail.row.decision === 'approved' ? 'ri-check-line' : 'ri-close-line'"></i>
                            {{ detail.row.decision_label }}
                        </span>
                        <span class="fs-12">
                            <template v-if="detail.row.decided_by">by <b>{{ detail.row.decided_by }}</b></template>
                            on {{ dateTime(detail.row.decided_at) }}
                        </span>
                        <div v-if="detail.row.remarks" class="fs-12 text-muted w-100">"{{ detail.row.remarks }}"</div>
                    </div>

                    <!-- Price change: the product itself, front and centre. -->
                    <div v-if="detail.data.subject" class="detail-subject">
                        <div class="subject-icon"><i class="ri-archive-2-line"></i></div>
                        <div class="subject-text">
                            <div class="subject-name">{{ detail.data.subject.name }}</div>
                            <div v-if="detail.data.subject.batch" class="subject-batch">
                                <i class="ri-price-tag-3-line"></i> Batch {{ detail.data.subject.batch }}
                            </div>
                        </div>
                    </div>

                    <!-- Price change: the old → new prices, up front and big. -->
                    <div v-if="detail.data.price_update" class="price-update">
                        <div v-for="pu in detail.data.price_update" :key="pu.label" class="pu-card">
                            <div class="pu-label">{{ pu.label }}</div>
                            <div class="pu-flow">
                                <span class="pu-old">{{ peso(pu.old) }}</span>
                                <i class="ri-arrow-right-line pu-arrow"></i>
                                <span class="pu-new">{{ peso(pu.new) }}</span>
                            </div>
                            <div class="pu-delta" :class="pu.new >= pu.old ? 'up' : 'down'">
                                <i :class="pu.new >= pu.old ? 'ri-arrow-up-line' : 'ri-arrow-down-line'"></i>
                                {{ pu.new === pu.old ? 'No change' : peso(Math.abs(pu.new - pu.old)) }}
                            </div>
                        </div>
                    </div>

                    <dl class="detail-fields">
                        <div v-for="field in detail.data.fields" :key="field.label" class="detail-field">
                            <dt>{{ field.label }}</dt>
                            <dd :class="{ 'fw-semibold': field.type === 'money' }">{{ formatValue(field.value, field.type) }}</dd>
                        </div>
                    </dl>

                    <div v-if="detail.data.lines" class="detail-lines">
                        <h6>
                            {{ detail.data.lines.title }}
                            <span class="text-muted fw-normal">({{ detail.data.lines.rows.length }})</span>
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th v-for="col in detail.data.lines.columns" :key="col.key"
                                            :class="{ 'text-end': isNumeric(col.type) }">{{ col.label }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(line, i) in detail.data.lines.rows" :key="i">
                                        <td v-for="col in detail.data.lines.columns" :key="col.key"
                                            :class="{ 'text-end': isNumeric(col.type) }">{{ formatValue(line[col.key], col.type) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot v-if="detail.data.lines.total !== null && linesTotalIndex >= 0">
                                    <tr>
                                        <td v-for="(col, i) in detail.data.lines.columns" :key="col.key"
                                            :class="{ 'text-end': isNumeric(col.type) }">
                                            <b v-if="i === linesTotalIndex">{{ peso(detail.data.lines.total) }}</b>
                                            <b v-else-if="i === 0">Total</b>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </template>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" @click="detail = null">Close</button>
                <!-- Still pending: decide from here, once it has been looked at. -->
                <template v-if="tab === 'pending'">
                    <Link v-if="!detail.row.can_approve_inline || !detail.row.can_reject_inline"
                        :href="reviewUrl(detail.row)" class="btn btn-light">
                        <i class="ri-external-link-line"></i> Review
                    </Link>
                    <button v-if="detail.row.can_reject_inline" class="btn btn-danger" @click="decideFromDetail('disapproved')">
                        Reject
                    </button>
                    <button v-if="detail.row.can_approve_inline" class="btn btn-success" @click="decideFromDetail('approved')">
                        Approve
                    </button>
                </template>
            </div>
        </div>
    </div>

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

    <!-- Approval success: a big animated check, shown right after an approve. -->
    <div v-if="successModal" class="modal-overlay active approval-success-overlay" @click.self="closeApprovalSuccess">
        <div class="modal-container approval-success-modal" @click.stop>
            <div class="modal-body approval-success-body">
                <div class="approval-success-icon">
                    <svg class="approval-check" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                        <circle class="approval-check-circle" cx="26" cy="26" r="25" fill="none" />
                        <path class="approval-check-mark" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8" />
                    </svg>
                </div>
                <h4 class="approval-success-title">Approved</h4>
                <p class="approval-success-text">{{ successModal.label }} has been approved.</p>
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
            // 'pending', 'approved' or 'rejected' (the Disapproved tab).
            tab: 'pending',
            // Past decisions, fetched each time their tab is opened.
            history: {
                approved: { groups: [], total: 0 },
                rejected: { groups: [], total: 0 },
            },
            historyLoading: false,
            activeSource: this.focusSource || null,
            keyword: '',
            // "field:direction"; empty means newest request first.
            sortOption: '',
            page: 1,
            perPage: 10,
            loading: false,
            pending: null,      // { row, decision }
            detail: null,       // { row, loading, data, error } while View is open
            remarks: '',
            submitting: false,
            busyKey: null,
            stopListening: null,
            audioCtx: null,     // unlocked on the approve click, reused after
            successModal: null, // { label } while the approval success modal is shown
            successTimer: null,
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
        currentGroups() {
            return this.tab === 'pending' ? this.groups : this.history[this.tab].groups;
        },
        currentTotal() {
            return this.tab === 'pending' ? this.total : this.history[this.tab].total;
        },
        /** Pending has Requested + Action; the decided tabs have Decision, Decided + Action. */
        columnCount() {
            return this.tab === 'pending' ? 6 : 7;
        },
        /** The column the line-item total sits under: the last amount column. */
        linesTotalIndex() {
            const columns = this.detail?.data?.lines?.columns || [];
            return columns.map(c => c.type).lastIndexOf('money');
        },
        /** How the empty and no-match messages name what this tab lists. */
        tabWord() {
            return { pending: 'pending', approved: 'approved', rejected: 'disapproved' }[this.tab];
        },
        currentLoading() {
            return this.tab === 'pending' ? this.loading : this.historyLoading;
        },
        /** The date each tab is about: when it was asked for, or when it was decided. */
        dateField() {
            return this.tab === 'pending' ? 'requested_at' : 'decided_at';
        },
        sortFields() {
            return [
                this.tab === 'pending'
                    ? { field: 'requested_at', label: 'Requested', type: 'date' }
                    : { field: 'decided_at', label: 'Decided', type: 'date' },
                { field: 'amount', label: 'Amount', type: 'number' },
                { field: 'reference', label: 'Reference' },
                { field: 'label', label: 'Type' },
            ];
        },
        /** Flattened rows for the active tab, filter and search, newest first. */
        filteredRows() {
            const groups = this.activeSource
                ? this.currentGroups.filter(g => g.source === this.activeSource)
                : this.currentGroups;

            const term = this.searchTerm;

            return groups
                .flatMap(g => g.items)
                .filter(row => !term || this.searchText(row).includes(term))
                .sort(this.rowComparator);
        },
        /** Compares two rows by the chosen sort; newest first by default. */
        rowComparator() {
            const dateField = this.dateField;
            const [field, direction] = (this.sortOption || `${dateField}:desc`).split(':');
            const sign = direction === 'asc' ? 1 : -1;

            const value = row => {
                if (field === 'requested_at' || field === 'decided_at') return new Date(row[field] || 0).getTime();
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
                return new Date(b[dateField] || 0) - new Date(a[dateField] || 0);
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
        // Both tabs list the same sources, so the filter and search carry
        // over; the sort does not, since each tab sorts by its own date.
        tab(tab) {
            this.sortOption = '';
            this.page = 1;
            if (tab !== 'pending') this.fetchHistory(tab);
        },
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
                // Only on Approved and Disapproved rows.
                row.decision_label,
                row.decided_by,
                row.remarks,
                row.decided_at ? this.shortDate(row.decided_at) : null,
            ].filter(v => v !== null && v !== undefined).join(' ').toLowerCase();
        },
        viewDetails(row) {
            const key = this.rowKey(row);
            this.detail = { row, loading: true, data: null, error: null };
            // An answer for a row that has since been closed or replaced is dropped.
            const stillOpen = () => this.detail && this.rowKey(this.detail.row) === key;

            axios.get(`/approvals/${row.source}/${row.id}`)
                .then(res => { if (stillOpen()) this.detail.data = res.data; })
                .catch(err => {
                    if (stillOpen()) this.detail.error = err?.response?.data?.message || 'Could not load the details.';
                })
                .finally(() => { if (stillOpen()) this.detail.loading = false; });
        },
        decideFromDetail(decision) {
            const row = this.detail.row;
            this.detail = null;
            this.open(row, decision);
        },
        isNumeric(type) {
            return type === 'money' || type === 'number';
        },
        formatValue(value, type) {
            if (value === null || value === undefined || value === '') return '—';
            if (type === 'money') return this.peso(value);
            if (type === 'date') return this.shortDate(value);
            if (type === 'datetime') return this.dateTime(value);
            if (type === 'number') return Number(value).toLocaleString('en-PH', { maximumFractionDigits: 2 });
            return value;
        },
        /** Tells the module screen it was opened from here, so it can offer a way back. */
        reviewUrl(row) {
            return row.url + (row.url.includes('?') ? '&' : '?') + 'from=approvals';
        },
        rowKey(row) {
            // A decided row has its own key: one record decided twice is two rows.
            return row.key || `${row.source}-${row.id}`;
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
        dateTime(value) {
            if (!value) return '—';
            const d = new Date(value);
            if (Number.isNaN(d.getTime())) return String(value);
            return d.toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
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
        /** @param {'approved'|'rejected'} decision */
        fetchHistory(decision, { quiet = false } = {}) {
            if (!quiet) this.historyLoading = true;

            return axios.get('/approvals', { params: { option: 'history', decision } })
                .then(res => {
                    this.history[decision] = {
                        groups: res.data.groups || [],
                        total: res.data.total || 0,
                    };
                })
                .catch(() => {
                    if (!quiet) this.$toast?.error('Could not load past decisions.');
                })
                .finally(() => { this.historyLoading = false; });
        },
        refresh() {
            // The pending count in the header is shown on both tabs.
            return this.tab === 'pending'
                ? this.fetch()
                : Promise.all([this.fetch(), this.fetchHistory(this.tab)]);
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

            // Unlock the audio here, inside the click, so the chime is allowed
            // to play later in the async success handler — browsers gate audio
            // on a user gesture, and the network response is outside it.
            if (decision === 'approved') this.primeAudio();

            axios.post(`/approvals/${row.source}/${row.id}/decide`, {
                decision,
                remarks: this.remarks || null,
            })
                .then(res => {
                    // An approval gets the big-check modal and a chime; a
                    // rejection just gets the usual toast.
                    if (decision === 'approved') {
                        this.playApproveTone();
                        this.showApprovalSuccess(row.label);
                    } else {
                        this.$toast?.success(res.data.message || 'Decision recorded.');
                    }
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
        /** Show the big-check success modal, auto-dismissing after a moment. */
        showApprovalSuccess(label) {
            this.successModal = { label: label || 'The request' };
            if (this.successTimer) clearTimeout(this.successTimer);
            this.successTimer = setTimeout(() => { this.successModal = null; }, 2000);
        },
        closeApprovalSuccess() {
            if (this.successTimer) { clearTimeout(this.successTimer); this.successTimer = null; }
            this.successModal = null;
        },
        /** Create/resume the audio context from inside a user gesture. */
        primeAudio() {
            if (typeof window === 'undefined') return;
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            try {
                if (!this.audioCtx) this.audioCtx = new AudioCtx();
                if (this.audioCtx.state === 'suspended') this.audioCtx.resume();
            } catch (e) {
                // Audio unavailable — ignore; the toast still confirms.
            }
        },
        /** "Major Chord Climb" — three bright notes stepping up, on approval. */
        playApproveTone() {
            const ctx = this.audioCtx;
            if (!ctx) return;
            try {
                if (ctx.state === 'suspended') ctx.resume();
                const now = ctx.currentTime;
                // C5 → E5 → G5: a small ascending "ta-da".
                [[523.25, 0, 0.14], [659.25, 0.11, 0.14], [783.99, 0.22, 0.22]].forEach(([freq, offset, dur]) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    const t = now + offset;
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(freq, t);
                    gain.gain.setValueAtTime(0.0001, t);
                    gain.gain.exponentialRampToValueAtTime(0.16, t + 0.015);
                    gain.gain.exponentialRampToValueAtTime(0.0001, t + dur);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start(t);
                    osc.stop(t + dur + 0.03);
                });
            } catch (e) {
                // Audio unavailable or blocked — the toast already confirms it.
            }
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
            if (this.tab !== 'pending') this.fetchHistory(this.tab, { quiet: true });
        });
        this.startPolling(() => {
            this.fetch({ quiet: true });
            if (this.tab !== 'pending') this.fetchHistory(this.tab, { quiet: true });
        }, 10000);

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

/* ── Approval success modal (big animated check) ─────────────── */
.approval-success-modal { max-width: 380px; }
.approval-success-body {
    padding: 2rem 1.75rem 1.75rem;
    display: flex; flex-direction: column; align-items: center; text-align: center;
}
.approval-success-icon { width: 104px; height: 104px; margin: 0 auto 1rem; display: grid; place-items: center; }
.approval-check {
    width: 104px; height: 104px; border-radius: 50%; display: block;
    stroke-width: 3; stroke: var(--brt-primary, #3d8d7a); stroke-miterlimit: 10;
    box-shadow: inset 0 0 0 var(--brt-primary, #3d8d7a);
    animation: approvalCheckFill 0.4s ease-in-out 0.4s forwards, approvalCheckScale 0.3s ease-in-out 0.9s both;
}
.approval-check-circle {
    stroke-dasharray: 166; stroke-dashoffset: 166; stroke-width: 3; stroke-miterlimit: 10;
    stroke: var(--brt-primary, #3d8d7a); fill: none;
    animation: approvalCheckStroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
}
.approval-check-mark {
    transform-origin: 50% 50%; stroke: #fff; stroke-width: 4;
    stroke-linecap: round; stroke-linejoin: round;
    stroke-dasharray: 48; stroke-dashoffset: 48;
    animation: approvalCheckStroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
}
@keyframes approvalCheckStroke { 100% { stroke-dashoffset: 0; } }
@keyframes approvalCheckScale { 0%, 100% { transform: none; } 50% { transform: scale3d(1.1, 1.1, 1); } }
@keyframes approvalCheckFill { 100% { box-shadow: inset 0 0 0 52px var(--brt-primary, #3d8d7a); } }
.approval-success-title { margin: 0; font-weight: 800; color: #16322e; font-size: 1.3rem; }
.approval-success-text { margin: 0.35rem 0 1.25rem; color: #6b8c85; font-size: 0.9rem; }
.approval-success-btn { min-width: 130px; }

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

/* Pending | Approved | Disapproved switch, the same segmented style as the inventory tabs. */
.filter-segment {
    display: inline-flex;
    background: var(--brt-c-eaf2f0);
    border-radius: 10px;
    padding: 3px;
    gap: 2px;
}
.filter-segment-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: none;
    background: transparent;
    color: var(--brt-c-4a7a70);
    font-weight: 600;
    font-size: 0.8rem;
    padding: 0.38rem 0.85rem;
    border-radius: 8px;
    transition: all 0.18s ease;
    white-space: nowrap;
    cursor: pointer;
}
.filter-segment-btn i { font-size: 0.95rem; }
.filter-segment-btn:hover:not(.active) {
    background: rgba(var(--brt-primary-rgb), 0.1);
    color: var(--brt-primary);
}
.filter-segment-btn.active {
    background: var(--brt-primary);
    color: #fff;
    box-shadow: 0 2px 8px rgba(var(--brt-primary-rgb), 0.28);
}
.segment-count {
    background: rgba(0, 0, 0, 0.08);
    border-radius: 999px;
    padding: 0.05rem 0.45rem;
    font-size: 0.7rem;
}
.filter-segment-btn.active .segment-count { background: rgba(255, 255, 255, 0.25); }

.decision-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    border-radius: 6px;
    padding: 0.2rem 0.5rem;
    font-size: 0.72rem;
    font-weight: 700;
    white-space: nowrap;
}
.decision-chip.is-approved { background: #dcfce7; color: #166534; }
.decision-chip.is-rejected { background: #fee2e2; color: #b42318; }
.decision-remarks {
    max-width: 260px;
    white-space: normal;
}

/* Square icon buttons; each one's name is its tooltip. */
.act-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border: none;
    border-radius: 7px;
    padding: 0;
    font-size: 0.95rem;
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
.act-view { background: rgba(var(--brt-primary-rgb), 0.1); color: var(--brt-primary-dark); }
.act-view:hover { background: rgba(var(--brt-primary-rgb), 0.18); }

/* View details */
.detail-decision {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 0.75rem;
    margin-bottom: 1rem;
    border-radius: 10px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
}
.detail-subject {
    display: flex;
    align-items: center;
    gap: 0.9rem;
    padding: 0.9rem 1.1rem;
    margin: 0 0 1.1rem;
    border-radius: 14px;
    border: 1px solid #c4d9d2;
    background: linear-gradient(to right, #cfe0d9 0%, #edf6f2 100%);
}
.subject-icon {
    flex-shrink: 0;
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background: rgba(61, 141, 122, 0.14);
    color: #3d8d7a;
    font-size: 1.5rem;
}
.subject-text { min-width: 0; }
.subject-name {
    font-size: 1.35rem;
    font-weight: 800;
    line-height: 1.2;
    color: #16322e;
    word-break: break-word;
}
.subject-batch {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-top: 0.3rem;
    font-size: 0.78rem;
    font-weight: 600;
    color: #6b8c85;
}
.price-update {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 0.9rem;
    margin: 0 0 1.25rem;
}
.pu-card {
    border: 1px solid #d7e6e0;
    background: linear-gradient(180deg, #f3faf7 0%, #ffffff 100%);
    border-radius: 12px;
    padding: 0.9rem 1rem;
}
.pu-label {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #6b8c85;
    margin-bottom: 0.4rem;
}
.pu-flow {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.pu-old {
    font-size: 1rem;
    color: #9ca3af;
    text-decoration: line-through;
}
.pu-arrow { color: #9ca3af; font-size: 1.1rem; }
.pu-new {
    font-size: 1.5rem;
    font-weight: 800;
    color: #16423c;
}
.pu-delta {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    margin-top: 0.4rem;
    padding: 2px 9px;
    border-radius: 20px;
    font-size: 0.74rem;
    font-weight: 700;
}
.pu-delta.up { background: #e8f5e9; color: #2e7d32; }
.pu-delta.down { background: #fdecea; color: #c62828; }

.detail-fields {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 0.75rem 1.25rem;
    margin: 0 0 1rem;
}
.detail-field dt {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #6b7280;
    margin-bottom: 0.15rem;
}
.detail-field dd {
    margin: 0;
    font-size: 0.85rem;
    color: #1f2937;
    word-break: break-word;
}
.detail-lines h6 {
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--brt-ink);
    margin-bottom: 0.5rem;
}
.detail-lines table { font-size: 0.8rem; }
.detail-lines thead th {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #6b7280;
    white-space: nowrap;
}
.detail-lines tfoot td { border-top: 2px solid #e5e7eb; }

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
