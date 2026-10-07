<template>
    <div class="board">
        <div class="board-head">
            <div>
                <h3>Deliveries</h3>
                <p>
                    A delivery's whole life, left to right. Each card carries what needs doing next,
                    and how long it has been waiting.
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

        <div class="board-columns">
            <section v-for="column in columns" :key="column.key" class="board-column">
                <header class="board-column-head" :class="column.tone">
                    <span class="board-column-title">{{ column.title }}</span>
                    <span class="board-column-count">{{ column.rows.length }}</span>
                </header>
                <p class="board-column-hint">{{ column.hint }}</p>

                <p v-if="!loading && column.rows.length === 0" class="board-empty">{{ column.empty }}</p>

                <article v-for="row in column.rows" :key="column.key + row.id" class="board-card">
                    <div class="board-card-top">
                        <strong>{{ row.reference }}</strong>
                        <span class="board-amount">{{ formatCurrency(row.amount) }}</span>
                    </div>
                    <div class="board-card-mid">{{ row.customer || 'Walk-in customer' }}</div>
                    <div class="board-card-foot">
                        <span class="board-person">
                            <i class="ri-user-line"></i>{{ row.person || 'Nobody named' }}
                        </span>
                        <span v-if="row.days !== null" class="board-age" :class="{ 'is-late': row.days >= 2 }">
                            {{ ageLabel(row.days) }}
                        </span>
                    </div>
                    <div v-if="row.mode" class="board-card-mode">
                        {{ row.mode }}
                        <span v-if="!row.confirmed" class="board-unconfirmed">unconfirmed</span>
                    </div>
                    <button v-if="column.action" class="board-action" @click="column.action.run(row)">
                        {{ column.action.label }}
                    </button>
                </article>
            </section>
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
            board: { out_for_delivery: [], to_collect: [], with_driver: [] },
        };
    },
    computed: {
        columns() {
            return [
                {
                    key: 'out',
                    title: 'Out for delivery',
                    hint: 'Promised to travel, not recorded as arrived.',
                    empty: 'Nothing waiting to go out.',
                    tone: 'tone-neutral',
                    rows: this.board.out_for_delivery ?? [],
                    action: { label: 'Mark delivered', run: (row) => this.openDelivery(row) },
                },
                {
                    key: 'collect',
                    title: 'Delivered, to collect',
                    hint: 'Goods with the customer, nothing collected yet.',
                    empty: 'Nothing owed on delivered goods.',
                    tone: 'tone-warn',
                    rows: this.board.to_collect ?? [],
                    action: { label: 'Record collection', run: (row) => this.openPayment(row) },
                },
                {
                    key: 'money',
                    title: 'With the driver',
                    hint: 'Collected, not yet turned in.',
                    empty: 'Nobody is carrying collections.',
                    tone: 'tone-money',
                    rows: this.board.with_driver ?? [],
                    action: null,
                },
            ];
        },
    },
    mounted() {
        this.fetch();
    },
    methods: {
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
    margin-bottom: 1.2rem;
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
    gap: 0.4rem;
}

.board-columns {
    display: grid;
    grid-template-columns: repeat(3, minmax(220px, 1fr));
    gap: 1rem;
}

@media (max-width: 900px) {
    .board-columns {
        grid-template-columns: 1fr;
    }
}

.board-column-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.tone-neutral { background: #edf6f2; color: #2f6f60; }
.tone-warn { background: #f7ecdd; color: #b0702a; }
.tone-money { background: #e8f1ff; color: #2456a6; }

.board-column-count {
    font-variant-numeric: tabular-nums;
}

.board-column-hint {
    color: #6b8c85;
    font-size: 0.75rem;
    margin: 0.4rem 0 0.7rem;
}

.board-empty {
    color: #9bb5ad;
    font-size: 0.82rem;
    padding: 0.8rem 0;
    margin: 0;
}

.board-card {
    border: 1px solid #dfeae6;
    border-radius: 10px;
    padding: 0.7rem 0.8rem;
    margin-bottom: 0.6rem;
    background: #fdfefe;
}

.board-card-top {
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: #16322e;
}

.board-amount {
    font-variant-numeric: tabular-nums;
    font-weight: 600;
}

.board-card-mid {
    color: #4d6b64;
    font-size: 0.82rem;
    margin-top: 0.15rem;
}

.board-card-foot {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0.45rem;
    font-size: 0.76rem;
    color: #6b8c85;
}

.board-person i {
    margin-right: 0.25rem;
}

.board-age.is-late {
    color: #b0702a;
    font-weight: 600;
}

.board-card-mode {
    margin-top: 0.35rem;
    font-size: 0.75rem;
    color: #6b8c85;
}

.board-unconfirmed {
    margin-left: 0.3rem;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
    background: #f7ecdd;
    color: #b0702a;
    text-transform: uppercase;
    font-size: 0.65rem;
}

.board-action {
    margin-top: 0.6rem;
    width: 100%;
    border: 1px solid #c4d9d2;
    background: #fff;
    color: #3d8d7a;
    font-size: 0.8rem;
    font-weight: 600;
    border-radius: 8px;
    padding: 0.35rem 0;
    cursor: pointer;
}

.board-action:hover {
    background: #f2f9f6;
}
</style>
