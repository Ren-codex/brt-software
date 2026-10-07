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

            <div class="table-responsive">
                <table class="table sales-table mb-0">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Customer</th>
                            <th v-if="activeStage === 'all'">Stage</th>
                            <th>With</th>
                            <th class="text-end">Amount</th>
                            <th class="text-center">Waiting</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fs-12">
                        <tr v-if="loading">
                            <td :colspan="activeStage === 'all' ? 7 : 6" class="delivery-empty">Loading deliveries...</td>
                        </tr>
                        <tr v-else-if="visibleRows.length === 0">
                            <td :colspan="activeStage === 'all' ? 7 : 6" class="delivery-empty">{{ currentStage.empty }}</td>
                        </tr>
                        <tr v-for="row in visibleRows" :key="row.stage + row.id" class="main-table-row">
                            <td class="fw-semibold text-nowrap">{{ row.reference }}</td>
                            <td>{{ row.customer || 'Walk-in customer' }}</td>
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
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <button
                                        v-if="row.stage === 'out'"
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
                                        <i class="ri-money-dollar-circle-fill"></i>
                                    </button>
                                    <!-- The driver hands the money in. Only the holder
                                         changes — the collection was recorded at the door,
                                         and the remittance is what finally clears it. -->
                                    <button
                                        v-else
                                        class="action-btn approve"
                                        v-b-tooltip.hover title="Record Handover"
                                        @click="openHandover(row)"
                                    >
                                        <i class="ri-hand-coin-line"></i>
                                    </button>
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
                    <label class="form-label" for="handover_holder">Who has the money now?</label>
                    <select id="handover_holder" v-model="handover.holder" class="form-control">
                        <option :value="null" disabled>Select the person receiving it</option>
                        <option v-for="person in holders" :key="person.value" :value="person.value">
                            {{ person.name }}
                        </option>
                    </select>
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
                        :disabled="!handover.holder || handover.saving"
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
            board: { out_for_delivery: [], to_collect: [], with_driver: [] },
            handover: { open: false, row: null, holder: null, error: '', saving: false },
        };
    },
    computed: {
        /** Anyone who can carry money: the drivers and the reps. */
        holders() {
            const drivers = Array.isArray(this.dropdowns?.drivers) ? this.dropdowns.drivers : [];
            const reps = Array.isArray(this.dropdowns?.sales_reps) ? this.dropdowns.sales_reps : [];
            const seen = new Set();

            return [...drivers, ...reps].filter((person) => {
                if (seen.has(person.value)) return false;
                seen.add(person.value);
                return true;
            });
        },
        /** The three stages of a delivery, in the order one passes through them. */
        stages() {
            return [
                {
                    key: 'all',
                    label: 'All',
                    hint: 'Everything on the road or still owed, oldest first.',
                    empty: 'Nothing out and nothing owed.',
                    tone: 'tone-neutral',
                    rows: [...this.stageRows('out'), ...this.stageRows('collect'), ...this.stageRows('money')],
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
                    hint: 'Goods with the customer, nothing collected yet.',
                    empty: 'Nothing owed on delivered goods.',
                    tone: 'tone-warn',
                    rows: this.stageRows('collect'),
                },
                {
                    key: 'money',
                    label: 'With the driver',
                    hint: 'Collected, not yet turned in.',
                    empty: 'Nobody is carrying collections.',
                    tone: 'tone-money',
                    rows: this.stageRows('money'),
                },
            ];
        },
        currentStage() {
            return this.stages.find((s) => s.key === this.activeStage) ?? this.stages[0];
        },
        /** Longest-waiting first, so whoever to chase sits at the top. */
        visibleRows() {
            return [...this.currentStage.rows].sort((a, b) => (b.days ?? -999) - (a.days ?? -999));
        },
    },
    mounted() {
        this.fetch();
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
                    stageLabel: 'With the driver',
                    tone: 'tone-money',
                    source: this.board.with_driver,
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
            this.handover = { open: true, row, holder: null, error: '', saving: false };
        },
        closeHandover() {
            this.handover.open = false;
        },
        saveHandover() {
            if (!this.handover.holder) {
                this.handover.error = 'Name who is taking it — that is the whole point of the record.';
                return;
            }

            this.handover.saving = true;
            this.handover.error = '';

            axios.put(`/receipts/${this.handover.row.id}/turn-over`, {
                held_by_employee_id: this.handover.holder,
            })
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
                { id: row.invoice_id, balance_due: row.amount },
                row.reference,
                '/ar-invoices'
            );
        },
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
    border: 1px solid #c4d9d2;
    background: #fff;
    color: #6b8c85;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.status-tab-btn:hover { background: #edf6f2; color: #16322e; }
.status-tab-btn.active { background: #3D8D7A; border-color: #3D8D7A; color: #fff; }

.stage-count {
    font-size: 11px;
    font-variant-numeric: tabular-nums;
    opacity: 0.75;
}

/* What the chosen stage means, kept beside the controls so it costs no row. */
.delivery-hint {
    font-size: 0.76rem;
    color: #6b8c85;
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

.stage-pill.tone-neutral { background: #e8f1ee; color: #2f6b5c; }
.stage-pill.tone-warn { background: #fdf0dd; color: #8a6412; }
.stage-pill.tone-money { background: #e5eef8; color: #2a5b86; }

.mode-note {
    display: block;
    font-size: 0.7rem;
    color: #8aa49d;
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
    color: #6b8c85;
    white-space: nowrap;
}

/* Two days is the point where somebody should be asking about it. */
.delivery-age.is-late {
    color: #a9681a;
    font-weight: 600;
}

.delivery-empty {
    text-align: center;
    color: #8aa49d;
    padding: 1.6rem 0;
    font-size: 0.85rem;
}

.handover-note {
    margin: 0.75rem 0 0;
    font-size: 0.78rem;
    color: #6b8c85;
}

.handover-error {
    margin: 0.5rem 0 0;
    font-size: 0.8rem;
    color: #b02a1b;
}

</style>
