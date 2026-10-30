<template>
    <!-- Same chrome as the Sales Orders list: library card, gradient header,
         status pills, shared .sales-table. Nothing here restyles those. -->
    <div class="library-card">
        <div class="library-card-header">
            <div class="d-flex align-items-center gap-3">
                <div class="header-icon">
                    <i class="ri-truck-line"></i>
                </div>
                <h4 class="header-title mb-0">Deliveries</h4>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="delivery-hint d-none d-xl-inline">{{ currentStage.hint }}</span>
                <button class="acct-btn-secondary" @click="fetch" :disabled="loading">
                    <i class="ri-refresh-line me-1"></i>{{ loading ? 'Loading...' : 'Refresh' }}
                </button>
                <!-- Only when opened from inside the Sales Orders tab. As a
                     sidebar tab of its own there is no list behind it. -->
                <button v-if="showBack" class="acct-btn-secondary" @click="$emit('back')">
                    <i class="ri-arrow-left-line me-1"></i>Back to list
                </button>
            </div>
        </div>

        <div class="library-card-body">
            <!-- Search, stages and sort on one row. Each stage keeps its own
                 search and its own sort, so looking something up under "To
                 collect" does not empty the other three. -->
            <div class="tabs-search-row">
                <div class="status-tab-bar">
                    <button
                        v-for="stage in stages"
                        :key="stage.key"
                        class="status-tab-btn"
                        :class="{ active: activeStage === stage.key }"
                        @click="activeStage = stage.key"
                    >
                        {{ stage.label }}
                        <span class="stage-count">{{ stage.rows.length }}</span>
                    </button>
                </div>

                <div class="search-section">
                    <div class="search-wrapper">
                        <i class="ri-search-line search-icon"></i>
                        <input
                            v-model.trim="stageKeyword[activeStage]"
                            type="text"
                            class="search-input"
                            :placeholder="`Search ${currentStage.label.toLowerCase()}...`"
                            title="Search by reference, customer, or who it is with"
                        >
                    </div>
                    <SortSelect v-model="stageSort[activeStage]" :added="false" :fields="sortFields" />
                </div>
            </div>

            <div class="table-responsive">
                <table class="table sales-table mb-0">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Customer</th>
                            <th class="text-center">Type</th>
                            <th v-if="activeStage === 'all'">Stage</th>
                            <th>With</th>
                            <th class="text-end">Amount</th>
                            <th class="text-center">Waiting</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fs-12">
                        <tr v-if="loading">
                            <td :colspan="activeStage === 'all' ? 8 : 7" class="delivery-empty">Loading deliveries...</td>
                        </tr>
                        <tr v-else-if="visibleRows.length === 0">
                            <td :colspan="activeStage === 'all' ? 8 : 7" class="delivery-empty">
                                {{ stageKeyword[activeStage] ? `Nothing here matches "${stageKeyword[activeStage]}".` : currentStage.empty }}
                            </td>
                        </tr>
                        <tr v-for="row in visibleRows" :key="row.stage + row.id" class="main-table-row">
                            <td class="fw-semibold text-nowrap">
                                {{ row.reference }}
                                <!-- Back on the road because an approver sent the
                                     delivery back; this is what to put right. -->
                                <span v-if="row.rejection" class="rejection-note" :title="row.rejection">
                                    <i class="ri-arrow-go-back-line"></i>{{ row.rejection }}
                                </span>
                            </td>
                            <td>{{ row.customer || 'Walk-in customer' }}</td>
                            <td class="text-center">
                                <span class="payment-pill" :class="paymentTone(row.payment_mode)">{{ row.payment_mode || '—' }}</span>
                            </td>
                            <td v-if="activeStage === 'all'">
                                <span class="stage-pill" :class="row.tone">{{ row.stageLabel }}</span>
                            </td>
                            <td :class="{ 'text-muted': !row.person }">
                                {{ row.person || 'Nobody named' }}
                                <span v-if="row.mode" class="mode-note">
                                    {{ row.mode }}<span v-if="!row.confirmed" class="unconfirmed">unconfirmed</span>
                                </span>
                            </td>
                            <td class="text-end fw-semibold text-nowrap">{{ formatCurrency(row.amount) }}</td>
                            <td class="text-center">
                                <span v-if="row.days !== null" class="delivery-age" :class="{ 'is-late': row.days >= 2 }">
                                    {{ ageLabel(row.days) }}
                                </span>
                                <span v-else class="text-muted">&mdash;</span>
                                <!-- The list is ordered by this, so it has to be
                                     on the row: sorting by something invisible
                                     just looks like no order at all. -->
                                <span v-if="row.updated_at" class="delivery-updated">{{ updatedLabel(row.updated_at) }}</span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <!-- On credit nobody has approved yet: it
                                         does not go out, so there is no button. -->
                                    <span v-if="row.stage === 'out' && row.credit_pending" class="waiting-note">
                                        Waiting for credit approval
                                    </span>
                                    <button
                                        v-else-if="row.stage === 'out'"
                                        class="action-btn success"
                                        v-b-tooltip.hover title="Mark Delivered"
                                        @click="openDelivery(row)"
                                    >
                                        <i class="ri-truck-line"></i>
                                    </button>
                                    <button
                                        v-else-if="row.stage === 'collect'"
                                        class="action-btn edit"
                                        v-b-tooltip.hover title="Record Collection"
                                        @click="openPayment(row)"
                                    >
                                        <i class="ri-money-peso-circle-fill"></i>
                                    </button>
                                    <!-- Nothing to chase here: the customer has
                                         paid and the paper is in. It clears in
                                         Receipts, where the bank is confirmed. -->
                                    <span v-else-if="row.stage === 'bank'" class="waiting-note">
                                        Waiting to clear
                                    </span>
                                    <!-- The driver hands the money in. Only the holder
                                         changes — the collection was recorded at the door,
                                         and the remittance is what finally clears it.
                                         A bank transfer never left with the crew, so there
                                         is nothing to hand over — it is the rep's to remit. -->
                                    <button
                                        v-else-if="row.needs_handover"
                                        class="action-btn approve"
                                        v-b-tooltip.hover title="Record Handover"
                                        @click="openHandover(row)"
                                    >
                                        <i class="ri-hand-coin-line"></i>
                                    </button>
                                    <span v-else class="waiting-note" v-b-tooltip.hover title="Already the rep's to remit — no handover needed">
                                        Ready to remit
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal chrome comes from _library-modal.scss; nothing here restyles it. -->
        <div v-if="handover.open" class="modal-overlay active" @click.self="closeHandover">
            <div class="modal-container" style="max-width: 460px">
                <div class="modal-header">
                    <div class="modal-header-icon"><i class="ri-hand-coin-line"></i></div>
                    <div>
                        <h5 class="modal-title">Record handover</h5>
                        <p class="modal-subtitle">
                            {{ handover.row?.reference }} &middot; {{ formatCurrency(handover.row?.amount) }}
                            &middot; carried by {{ handover.row?.person || 'nobody named' }}
                        </p>
                    </div>
                    <button class="close-btn ms-auto" @click="closeHandover" aria-label="Close">
                        <i class="ri-close-line"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- Nothing to choose: you are signed in, so you are the one
                         taking it, and it is recorded against your name. -->
                    <p class="handover-confirm">
                        Recording that <strong>you</strong> received this from
                        {{ handover.row?.person || 'the driver' }}.
                    </p>
                    <p v-if="handover.error" class="handover-error">{{ handover.error }}</p>
                    <p class="handover-note">
                        This records who is accountable for the money. It does not remit it —
                        the remittance is what finally clears it.
                    </p>
                </div>
                <div class="modal-footer">
                    <button class="acct-btn-secondary" @click="closeHandover">Cancel</button>
                    <button
                        class="acct-btn-primary"
                        :disabled="handover.saving"
                        @click="saveHandover"
                    >
                        {{ handover.saving ? 'Saving…' : 'Record handover' }}
                    </button>
                </div>
            </div>
        </div>

        <MarkDelivered @delivered="fetch" ref="markDelivered" />
        <Payment @add="fetch" ref="payment" />
    </div>
</template>

<script>
import axios from 'axios';
import { onApprovalChange } from '@/Shared/realtime.js';
import { paymentTone } from '@/Shared/utils/paymentType.js';
import MarkDelivered from './Modals/MarkDelivered.vue';
import Payment from '../ARInvoices/Modals/Payment.vue';

export default {
    components: { MarkDelivered, Payment },
    emits: ['back'],
    props: {
        showBack: { type: Boolean, default: true },
        dropdowns: { type: Object, default: () => ({}) },
    },
    data() {
        return {
            loading: false,
            activeStage: 'all',
            // One search and one sort per stage, keyed like the stages.
            stageKeyword: { all: '', out: '', collect: '', money: '', bank: '' },
            stageSort: { all: '', out: '', collect: '', money: '', bank: '' },
            board: { out_for_delivery: [], to_collect: [], with_driver: [], awaiting_bank: [] },
            handover: { open: false, row: null, holder: null, error: '', saving: false },
        };
    },
    computed: {
        /** The stages of a delivery, in the order one passes through them. */
        stages() {
            return [
                {
                    key: 'all',
                    label: 'All',
                    hint: 'Everything on the road or still owed, most recently updated first.',
                    empty: 'Nothing out and nothing owed.',
                    tone: 'tone-neutral',
                    rows: [...this.stageRows('out'), ...this.stageRows('collect'), ...this.stageRows('money'), ...this.stageRows('bank')],
                },
                {
                    key: 'out',
                    label: 'Out for delivery',
                    hint: 'Promised to travel, not recorded as arrived.',
                    empty: 'Nothing waiting to go out.',
                    tone: 'tone-neutral',
                    rows: this.stageRows('out'),
                },
                {
                    key: 'collect',
                    label: 'To collect',
                    hint: 'Goods with the customer, nobody has paid yet.',
                    empty: 'Nothing owed on delivered goods.',
                    tone: 'tone-warn',
                    rows: this.stageRows('collect'),
                },
                {
                    key: 'money',
                    label: 'With driver / checker',
                    hint: 'Collected, not yet turned in.',
                    empty: 'Nobody is carrying collections.',
                    tone: 'tone-money',
                    rows: this.stageRows('money'),
                },
                {
                    key: 'bank',
                    label: 'Waiting for the bank',
                    hint: 'Paid by cheque or transfer and handed in. Confirm it in Receipts once the bank pays.',
                    empty: 'Nothing waiting on the bank.',
                    tone: 'tone-bank',
                    rows: this.stageRows('bank'),
                },
            ];
        },
        currentStage() {
            return this.stages.find((s) => s.key === this.activeStage) ?? this.stages[0];
        },
        /**
         * Most recently changed first. Waiting time was the old order, but
         * nearly every row reads the same number of days, so it sorted them
         * into an order nobody could account for. What moved last is at least
         * a question the list can answer.
         *
         * Falls back to waiting time where a row has no timestamp.
         */
        visibleRows() {
            const lastChanged = (a, b) => {
                const at = a.updated_at ? Date.parse(a.updated_at) : null;
                const bt = b.updated_at ? Date.parse(b.updated_at) : null;
                if (at && bt && at !== bt) return bt - at;
                if (at && !bt) return -1;
                if (bt && !at) return 1;
                return (b.days ?? -999) - (a.days ?? -999);
            };

            const keyword = (this.stageKeyword[this.activeStage] || '').toLowerCase();
            const rows = this.currentStage.rows.filter((row) => !keyword
                || [row.reference, row.customer, row.person, row.stageLabel, row.mode]
                    .some((text) => String(text ?? '').toLowerCase().includes(keyword)));

            const [field, direction] = (this.stageSort[this.activeStage] || '').split(':');
            if (!field) return rows.sort(lastChanged);

            const sign = direction === 'asc' ? 1 : -1;
            const numeric = ['amount', 'days'].includes(field);
            const value = (row) => (numeric
                ? Number(row[field] ?? 0)
                : String(row[field] ?? '').toLowerCase());

            return rows.sort((a, b) => {
                const av = value(a);
                const bv = value(b);
                if (av < bv) return -1 * sign;
                if (av > bv) return 1 * sign;
                return lastChanged(a, b);
            });
        },
        /** What a stage can be sorted by; the default is what changed last. */
        sortFields() {
            return [
                { field: 'reference', label: 'Reference' },
                { field: 'customer', label: 'Customer' },
                { field: 'person', label: 'With' },
                { field: 'amount', label: 'Amount', type: 'number' },
                { field: 'days', label: 'Waiting', type: 'number' },
            ];
        },
    },
    mounted() {
        this.fetch();
        // Someone approving from the Approvals page, or another screen, moves
        // rows here too.
        this.stopListening = onApprovalChange(this, () => {
            if (!this.decision.saving) this.fetch();
        });
    },
    beforeUnmount() {
        if (this.stopListening) this.stopListening();
    },
    methods: {
        /**
         * Rows for one stage, each tagged with where it sits and what can be
         * done to it — so the All view can mix the three and still show the
         * right label and the right button on every line.
         */
        stageRows(key) {
            const shape = {
                out: {
                    stageLabel: 'Out for delivery',
                    tone: 'tone-neutral',
                    source: this.board.out_for_delivery,
                    action: { label: 'Mark delivered', run: (row) => this.openDelivery(row) },
                },
                collect: {
                    stageLabel: 'To collect',
                    tone: 'tone-warn',
                    source: this.board.to_collect,
                    action: { label: 'Record collection', run: (row) => this.openPayment(row) },
                },
                money: {
                    stageLabel: 'With driver / checker',
                    tone: 'tone-money',
                    source: this.board.with_driver,
                    action: null,
                },
                bank: {
                    stageLabel: 'Waiting for the bank',
                    tone: 'tone-bank',
                    // "With" reads as who handed it in and what the cheque is
                    // dated: the two things worth knowing while it is out.
                    source: (this.board.awaiting_bank ?? []).map((row) => ({
                        ...row,
                        mode: row.due ? `${row.mode} dated ${this.dueLabel(row.due)}` : row.mode,
                    })),
                    action: null,
                },
            }[key];

            return (shape.source ?? []).map((row) => ({
                ...row,
                stage: key,
                stageLabel: shape.stageLabel,
                tone: shape.tone,
                action: shape.action,
            }));
        },
        /**
         * How long a card has been waiting. The day count is signed, so an
         * order promised for a date still ahead comes back negative — which
         * read as "-2d" on the card and meant nothing to anyone. A delivery due
         * on Friday is waiting for Friday, not overdue by minus two days.
         */
        /** When the row last changed, short enough to sit under the wait. */
        updatedLabel(iso) {
            const d = new Date(iso);
            if (Number.isNaN(d.getTime())) return '';
            const today = new Date();
            const sameDay = d.toDateString() === today.toDateString();
            return sameDay
                ? d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })
                : d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
        },
        /** A cheque's own date, which is when the bank will pay it. */
        dueLabel(date) {
            const d = new Date(date);
            if (Number.isNaN(d.getTime())) return date;

            return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
        },
        ageLabel(days) {
            if (days === 0) return 'today';
            if (days === -1) return 'tomorrow';
            if (days < 0) return `in ${Math.abs(days)}d`;
            return `${days}d`;
        },
        fetch() {
            this.loading = true;
            axios.get('/sales-orders', { params: { option: 'delivery-board' } })
                .then((res) => { this.board = res.data || this.board; })
                .catch((err) => console.error(err))
                .finally(() => { this.loading = false; });
        },
        /** The modal wants the order itself, so fetch the row it belongs to. */
        openDelivery(row) {
            axios.get('/sales-orders', { params: { option: 'lists', count: 1, keyword: row.reference } })
                .then((res) => {
                    const order = (res.data?.data ?? [])[0];
                    if (order) this.$refs.markDelivered.show(order);
                })
                .catch((err) => console.error(err));
        },
        openHandover(row) {
            this.handover = { open: true, row, error: '', saving: false };
        },
        closeHandover() {
            this.handover.open = false;
        },
        saveHandover() {
            this.handover.saving = true;
            this.handover.error = '';

            // No holder sent: the server records it against whoever is signed in.
            axios.put(`/receipts/${this.handover.row.id}/turn-over`, {})
                .then(() => {
                    this.handover.open = false;
                    this.fetch();
                })
                .catch((err) => {
                    this.handover.error = err.response?.data?.message
                        || 'Could not record the handover.';
                })
                .finally(() => { this.handover.saving = false; });
        },
        openPayment(row) {
            this.$refs.payment.show(
                {
                    id: row.invoice_id,
                    invoice_number: row.invoice_number,
                    invoice_date: row.invoice_date,
                    due_date_formatted: row.due_date,
                    balance_due: row.amount,
                    sales_order: {
                        payment_mode: row.payment_mode,
                        customer: { name: row.customer },
                    },
                },
                row.reference,
                '/ar-invoices'
            );
        },
        paymentTone,
        formatCurrency(value) {
            return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(value) || 0);
        },
    },
};
</script>

<style scoped>
/* The pills are the Sales Orders bar, repeated here because that bar lives in
   that component's scoped styles rather than in a shared partial. */
.status-tab-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 0.85rem;
}

.status-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 6px 16px;
    border-radius: 8px;
    border: 1px solid var(--brt-border);
    background: #fff;
    color: var(--brt-muted);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.status-tab-btn:hover { background: var(--brt-tint-2); color: var(--brt-ink); }
.status-tab-btn.active { background: var(--brt-primary); border-color: var(--brt-primary); color: #fff; }

/* The count as a pill badge, so the number reads as a count and not as part
   of the label. Tinted on the idle tabs, white on the selected one. */
.stage-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    margin-left: 0.4rem;
    padding: 0 6px;
    border-radius: 999px;
    background: rgba(var(--brt-primary-rgb), 0.14);
    color: var(--brt-primary-dark);
    font-size: 11px;
    font-weight: 700;
    line-height: 1;
    font-variant-numeric: tabular-nums;
}

.status-tab-btn.active .stage-count {
    background: #fff;
    color: var(--brt-primary-dark);
}

/* What the chosen stage means, kept beside the controls so it costs no row. */
.delivery-hint {
    font-size: 0.76rem;
    color: var(--brt-muted);
    margin-right: 0.3rem;
}

.library-card-body {
    padding-top: 0.9rem;
}

/* Which stage a row sits in, only shown when the stages are mixed. */
.stage-pill {
    display: inline-block;
    font-size: 0.68rem;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 10px;
    white-space: nowrap;
}

.stage-pill.tone-neutral { background: var(--brt-c-e8f1ee); color: var(--brt-c-2f6b5c); }
.stage-pill.tone-warn { background: #fdf0dd; color: #8a6412; }
.stage-pill.tone-pending { background: #efe9fb; color: #5b3d9a; }

.rejection-note {
    display: block;
    max-width: 260px;
    margin-top: 2px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.7rem;
    font-weight: 500;
    color: #b02a1b;
}

.rejection-note i { margin-right: 3px; }

.waiting-note {
    font-size: 0.72rem;
    color: var(--brt-muted);
    white-space: nowrap;
}

.decision-label {
    display: block;
    margin: 0.75rem 0 0.3rem;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--brt-ink);
}

.decision-remarks {
    width: 100%;
    padding: 0.55rem 0.7rem;
    border: 1px solid var(--brt-border);
    border-radius: 8px;
    font-size: 0.82rem;
    resize: vertical;
}

.decision-remarks:focus {
    outline: none;
    border-color: var(--brt-focus);
}
.stage-pill.tone-money { background: #e5eef8; color: #2a5b86; }
/* Out of anyone's hands here, so cooler and quieter than money in a pocket. */
.stage-pill.tone-bank { background: #eaeef1; color: #4a5a68; }

.mode-note {
    display: block;
    font-size: 0.7rem;
    color: var(--brt-c-8aa49d);
}

.unconfirmed {
    margin-left: 0.3rem;
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #a9681a;
}

.delivery-age {
    font-size: 0.75rem;
    font-variant-numeric: tabular-nums;
    color: var(--brt-muted);
    white-space: nowrap;
}

/* Two days is the point where somebody should be asking about it. */
.delivery-age.is-late {
    color: #a9681a;
    font-weight: 600;
}

.delivery-empty {
    text-align: center;
    color: var(--brt-c-8aa49d);
    padding: 1.6rem 0;
    font-size: 0.85rem;
}

.handover-note {
    margin: 0.75rem 0 0;
    font-size: 0.78rem;
    color: var(--brt-muted);
}

.handover-error {
    margin: 0.5rem 0 0;
    font-size: 0.8rem;
    color: #b02a1b;
}


/* The timestamp the list is ordered by. Quiet, because the wait above it is
   the number people act on. */
.delivery-updated {
    display: block;
    font-size: 0.65rem;
    color: var(--brt-c-a3b8b2);
    white-space: nowrap;
    margin-top: 1px;
}


.handover-confirm {
    margin: 0;
    font-size: 0.9rem;
    color: var(--brt-ink);
}

</style>
