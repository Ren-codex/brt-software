<template>
    <div class="field-collections">
        <div class="fc-head">
            <div>
                <h3>Cash in the Field</h3>
                <p>
                    Collected but not yet turned in. A city run should clear the same day, so
                    anything with days on it is worth a phone call.
                </p>
            </div>
            <button class="btn btn-sm btn-outline-secondary" @click="fetch" :disabled="loading">
                <i class="ri-refresh-line me-1"></i>{{ loading ? 'Loading...' : 'Refresh' }}
            </button>
        </div>

        <div v-if="error" class="fc-error">{{ error }}</div>

        <div v-if="!loading && rows.length === 0" class="fc-empty">
            <i class="ri-checkbox-circle-line"></i>
            <p>Nothing is out. Every collection has been turned in.</p>
        </div>

        <div v-for="group in grouped" :key="group.holder" class="fc-group">
            <div class="fc-group-head">
                <span class="fc-holder">{{ group.holder }}</span>
                <span class="fc-total">{{ formatCurrency(group.total) }}</span>
            </div>
            <table class="fc-table">
                <thead>
                    <tr>
                        <th>Days out</th>
                        <th>Sales Order</th>
                        <th>Customer</th>
                        <th>Mode</th>
                        <th class="text-end">Amount</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in group.rows" :key="row.receipt_id">
                        <td>
                            <span class="fc-days" :class="{ 'fc-days-late': row.days_out >= 1 && !row.is_external }">
                                {{ row.days_out }}
                            </span>
                        </td>
                        <td>
                            {{ row.so_number }}
                            <span v-if="row.is_external" class="fc-ext" title="Out-of-town delivery">EXT</span>
                        </td>
                        <td>{{ row.customer || '-' }}</td>
                        <td>
                            {{ row.payment_mode }}
                            <span v-if="!row.confirmed" class="fc-unconfirmed" title="Not yet matched in the bank">
                                unconfirmed
                            </span>
                        </td>
                        <td class="text-end">{{ formatCurrency(row.amount) }}</td>
                        <td class="text-end">
                            <template v-if="handingOver === row.receipt_id">
                                <select v-model="newHolder" class="fc-select" :id="`holder_${row.receipt_id}`">
                                    <option :value="null" disabled>Who has it now?</option>
                                    <option v-for="person in holders" :key="person.value" :value="person.value">
                                        {{ person.name }}
                                    </option>
                                </select>
                                <button class="fc-link" :disabled="!newHolder || saving" @click="saveHandover(row)">
                                    {{ saving ? 'Saving...' : 'Save' }}
                                </button>
                                <button class="fc-link fc-link-quiet" @click="cancelHandover">Cancel</button>
                            </template>
                            <button v-else class="fc-link" @click="startHandover(row)">Hand over</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    props: ['dropdowns'],
    data() {
        return {
            rows: [],
            loading: false,
            handingOver: null,
            newHolder: null,
            saving: false,
            error: null,
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
        /** Grouped by holder, each person's oldest collection first. */
        grouped() {
            const byHolder = new Map();

            this.rows.forEach((row) => {
                if (!byHolder.has(row.holder)) {
                    byHolder.set(row.holder, { holder: row.holder, rows: [], total: 0 });
                }
                const group = byHolder.get(row.holder);
                group.rows.push(row);
                group.total += Number(row.amount) || 0;
            });

            return Array.from(byHolder.values())
                .sort((a, b) => (b.rows[0]?.days_out ?? 0) - (a.rows[0]?.days_out ?? 0));
        },
    },
    mounted() {
        this.fetch();
    },
    methods: {
        fetch() {
            this.loading = true;
            axios.get('/remittances', { params: { option: 'field-collections' } })
                .then((res) => { this.rows = Array.isArray(res.data) ? res.data : []; })
                .catch((err) => console.error(err))
                .finally(() => { this.loading = false; });
        },
        startHandover(row) {
            this.handingOver = row.receipt_id;
            this.newHolder = null;
            this.error = null;
        },
        cancelHandover() {
            this.handingOver = null;
            this.newHolder = null;
        },
        saveHandover(row) {
            this.saving = true;
            axios.put(`/receipts/${row.receipt_id}/turn-over`, { held_by_employee_id: this.newHolder })
                .then(() => { this.cancelHandover(); this.fetch(); })
                .catch((err) => { this.error = err?.response?.data?.message || 'Unable to record this handover.'; })
                .finally(() => { this.saving = false; });
        },
        formatCurrency(value) {
            return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(value) || 0);
        },
    },
};
</script>

<style scoped>
.field-collections {
    padding: 1rem 0;
}

.fc-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1.2rem;
}

.fc-head h3 {
    font-size: 1.05rem;
    font-weight: 600;
    color: #16322e;
    margin: 0 0 0.2rem;
}

.fc-head p {
    color: #6b8c85;
    font-size: 0.85rem;
    margin: 0;
    max-width: 52ch;
}

.fc-empty {
    text-align: center;
    padding: 2.5rem 1rem;
    color: #6b8c85;
}

.fc-empty i {
    font-size: 1.8rem;
    color: #3d8d7a;
}

.fc-group {
    border: 1px solid #c4d9d2;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 1rem;
}

.fc-group-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.65rem 0.9rem;
    background: linear-gradient(to right, #cfe0d9 0%, #edf6f2 100%);
}

.fc-holder {
    font-weight: 600;
    color: #16322e;
}

.fc-total {
    font-weight: 600;
    color: #16322e;
    font-variant-numeric: tabular-nums;
}

.fc-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
}

.fc-table th {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #6b8c85;
    text-align: left;
    padding: 0.5rem 0.9rem;
    border-bottom: 1px solid #edf6f2;
}

.fc-table td {
    padding: 0.6rem 0.9rem;
    border-bottom: 1px solid #f1f7f4;
    font-size: 0.88rem;
    color: #16322e;
}

.fc-days {
    display: inline-block;
    min-width: 28px;
    text-align: center;
    font-variant-numeric: tabular-nums;
}

.fc-days-late {
    color: #b0702a;
    font-weight: 600;
}

.fc-ext,
.fc-unconfirmed {
    display: inline-block;
    margin-left: 0.35rem;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.fc-ext {
    background: #edf6f2;
    color: #3d8d7a;
}

.fc-unconfirmed {
    background: #f7ecdd;
    color: #b0702a;
}

.fc-link {
    background: none;
    border: none;
    color: #3d8d7a;
    font-size: 0.82rem;
    font-weight: 600;
    padding: 0 0.3rem;
    cursor: pointer;
}

.fc-link:disabled {
    color: #9bb5ad;
    cursor: default;
}

.fc-link-quiet {
    color: #6b8c85;
    font-weight: 500;
}

.fc-select {
    font-size: 0.82rem;
    padding: 0.2rem 0.4rem;
    border: 1px solid #c4d9d2;
    border-radius: 6px;
    margin-right: 0.3rem;
}

.fc-error {
    margin-bottom: 0.8rem;
    padding: 0.6rem 0.8rem;
    border-radius: 8px;
    background: #fdecea;
    color: #9b1c1c;
    font-size: 0.85rem;
}
</style>
