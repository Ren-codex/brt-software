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
            <div class="search-section mb-0">
                <div class="search-wrapper">
                    <i class="ri-search-line search-icon"></i>
                    <input
                        v-model="keyword"
                        type="text"
                        class="form-control"
                        placeholder="Check number or bank..."
                        @input="debouncedFetch"
                    />
                </div>
            </div>
        </div>

        <div class="library-card-body p-0">
            <div v-if="loading" class="table-empty-state"><i class="ri-loader-4-line spin"></i> Loading…</div>

            <div v-else-if="!rows.length" class="table-empty-state">
                <i class="ri-bill-line"></i>
                <p class="mb-1">No checks yet</p>
                <small>Checks you take from customers appear here until they clear.</small>
            </div>

            <div v-else class="table-responsive">
                <table class="table mb-0 cm-check-table">
                    <thead>
                        <tr>
                            <th>Check No</th>
                            <th>Check Date</th>
                            <th>Customer</th>
                            <th>Bank</th>
                            <th class="text-end">Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in rows" :key="c.id">
                            <td class="font-monospace">{{ c.check_number }}</td>
                            <td class="text-nowrap">{{ c.check_date }}</td>
                            <td>{{ c.customer?.name || '—' }}</td>
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
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script>
import axios from "axios";

export default {
    data() {
        return { rows: [], loading: true, keyword: '', timer: null };
    },
    mounted() {
        this.fetch();
    },
    methods: {
        fetch() {
            this.loading = true;
            axios.get('/sales/check-monitoring', { params: { keyword: this.keyword } })
                .then(({ data }) => { this.rows = data.data ?? []; })
                .catch(err => console.error('Could not load your checks', err))
                .finally(() => { this.loading = false; });
        },
        debouncedFetch() {
            clearTimeout(this.timer);
            this.timer = setTimeout(this.fetch, 300);
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
        money(value) {
            return `PHP ${Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        },
    },
};
</script>

<style scoped>


.cm-check-table tbody td { font-size: 0.85rem; vertical-align: middle; }


.cm-note { font-size: 0.72rem; color: #96231f; margin-top: 2px; }
.cm-note.cleared { color: #6b8c85; }

.spin { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
