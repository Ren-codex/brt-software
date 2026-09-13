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
            <div class="cm-search">
                <i class="ri-search-line"></i>
                <input v-model="keyword" type="text" placeholder="Check number or bank..." @input="debouncedFetch" />
            </div>
        </div>

        <div class="library-card-body p-0">
            <div v-if="loading" class="cm-empty"><i class="ri-loader-4-line spin"></i> Loading…</div>

            <div v-else-if="!rows.length" class="cm-empty">
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
                                <span class="cm-status" :class="c.status">{{ label(c.status) }}</span>
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
.cm-search { position: relative; min-width: 220px; }
.cm-search i { position: absolute; left: 0.7rem; top: 50%; transform: translateY(-50%); color: #6b8c85; }
.cm-search input {
    width: 100%; padding: 0.45rem 0.7rem 0.45rem 2rem;
    border: 1px solid #d8e6e1; border-radius: 8px; font-size: 0.85rem;
}

.cm-empty { padding: 2.5rem; text-align: center; color: #6b8c85; }
.cm-empty i { font-size: 1.8rem; display: block; margin-bottom: 0.4rem; }

.cm-check-table tbody td { font-size: 0.85rem; vertical-align: middle; }

.cm-status {
    display: inline-block; padding: 2px 8px; border-radius: 6px;
    font-size: 0.78rem; font-weight: 600; white-space: nowrap;
}
.cm-status.pending { background: #fdf3e3; color: #8a5a10; border: 1px solid #f0d49a; }
.cm-status.cleared { background: #e9f5f0; color: #1b6b4a; border: 1px solid #a8dcc8; }
.cm-status.bounced { background: #fdeaea; color: #96231f; border: 1px solid #f2b8b5; }

.cm-note { font-size: 0.72rem; color: #96231f; margin-top: 2px; }
.cm-note.cleared { color: #6b8c85; }

.spin { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
