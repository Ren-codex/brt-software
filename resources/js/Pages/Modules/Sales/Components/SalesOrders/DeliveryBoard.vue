<template>
    <div class="board">
        <div class="board-head">
            <div>
                <h3>Deliveries</h3>
                <p>
                    Everything on the road or still owed on goods that went out. Longest wait at
                    the top, with whatever needs doing next on the row itself.
                </p>
            </div>
            <div class="board-head-actions">
                <button class="btn btn-sm btn-outline-secondary" @click="fetch" :disabled="loading">
                    <i class="ri-refresh-line me-1"></i>{{ loading ? 'Loading...' : 'Refresh' }}
                </button>
                <!-- Only when the board was opened from inside the Sales Orders
                     tab. As a sidebar tab of its own there is no list behind it
                     to go back to. -->
                <button v-if="showBack" class="btn btn-sm btn-outline-secondary" @click="$emit('back')">
                    <i class="ri-arrow-left-line me-1"></i>Back to list
                </button>
            </div>
        </div>

        <!-- One stage at a time, the same pill pattern the Sales Orders list
             uses. The three stages carry the same fields, so they share a
             table rather than needing one each. -->
        <div class="stage-bar">
            <button
                v-for="stage in stages"
                :key="stage.key"
                class="stage-btn"
                :class="{ active: activeStage === stage.key }"
                @click="activeStage = stage.key"
            >
                {{ stage.label }}
                <span class="stage-count">{{ stage.rows.length }}</span>
            </button>
        </div>

        <p class="stage-hint">{{ currentStage.hint }}</p>

        <div class="table-responsive">
            <table class="table delivery-table mb-0">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Customer</th>
                        <th v-if="activeStage === 'all'">Stage</th>
                        <th>With</th>
                        <th class="text-end">Amount</th>
                        <th class="text-center">Waiting</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td :colspan="activeStage === 'all' ? 7 : 6" class="delivery-empty">Loading deliveries...</td>
                    </tr>
                    <tr v-else-if="visibleRows.length === 0">
                        <td :colspan="activeStage === 'all' ? 7 : 6" class="delivery-empty">{{ currentStage.empty }}</td>
                    </tr>
                    <tr v-for="row in visibleRows" :key="row.stage + row.id" class="delivery-row">
                        <td class="delivery-ref">{{ row.reference }}</td>
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
                        <td class="text-end delivery-amount">{{ formatCurrency(row.amount) }}</td>
                        <td class="text-center">
                            <span v-if="row.days !== null" class="delivery-age" :class="{ 'is-late': row.days >= 2 }">
                                {{ ageLabel(row.days) }}
                            </span>
                            <span v-else class="text-muted">&mdash;</span>
                        </td>
                        <td class="text-center">
                            <button v-if="row.action" class="delivery-action" @click="row.action.run(row)">
                                {{ row.action.label }}
                            </button>
                            <span v-else class="text-muted">&mdash;</span>
                        </td>
                    </tr>
                </tbody>
            </table>
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
    },
    data() {
        return {
            loading: false,
            activeStage: 'all',
            board: { out_for_delivery: [], to_collect: [], with_driver: [] },
        };
    },
    computed: {
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
.board {
    background: #fff;
    border: 1px solid #c4d9d2;
    border-radius: 12px;
    padding: 1.2rem 1.35rem 1.5rem;
}

.board-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1rem;
}

.board-head h3 {
    font-size: 1.05rem;
    font-weight: 600;
    color: #16322e;
    margin: 0 0 0.2rem;
}

.board-head p {
    color: #6b8c85;
    font-size: 0.85rem;
    margin: 0;
    max-width: 58ch;
}

.board-head-actions {
    display: flex;
    gap: 0.5rem;
    flex-shrink: 0;
}

/* Same pill bar as the Sales Orders list, so the two read as one system. */
.stage-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 0.6rem;
}

.stage-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 6px 14px;
    border-radius: 8px;
    border: 1px solid #c4d9d2;
    background: #fff;
    color: #6b8c85;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.stage-btn:hover { background: #edf6f2; color: #16322e; }
.stage-btn.active { background: #3D8D7A; border-color: #3D8D7A; color: #fff; }

.stage-count {
    font-size: 11px;
    font-variant-numeric: tabular-nums;
    opacity: 0.75;
}

.stage-hint {
    color: #6b8c85;
    font-size: 0.8rem;
    margin: 0 0 0.75rem;
}

.delivery-table {
    font-size: 0.8125rem;
}

.delivery-table thead th {
    font-size: 0.7rem;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #6b8c85;
    font-weight: 600;
    background: #edf6f2;
    border-bottom: 1px solid #c4d9d2;
    white-space: nowrap;
    padding: 0.55rem 0.7rem;
}

.delivery-table tbody td {
    padding: 0.55rem 0.7rem;
    vertical-align: middle;
    border-bottom: 1px solid #eef4f2;
}

.delivery-row:hover { background: #f6fbf9; }

.delivery-ref {
    font-weight: 600;
    color: #16322e;
    white-space: nowrap;
}

.delivery-amount {
    font-variant-numeric: tabular-nums;
    font-weight: 600;
    white-space: nowrap;
}

.delivery-empty {
    text-align: center;
    color: #8aa49d;
    padding: 1.6rem 0;
    font-size: 0.85rem;
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

.delivery-action {
    border: 1px solid #3D8D7A;
    background: #fff;
    color: #3D8D7A;
    border-radius: 6px;
    padding: 3px 11px;
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
    cursor: pointer;
    transition: all 0.2s ease;
}

.delivery-action:hover { background: #3D8D7A; color: #fff; }

@media (max-width: 767px) {
    .board { padding: 1rem; }
    .board-head { flex-direction: column; }
}
</style>
