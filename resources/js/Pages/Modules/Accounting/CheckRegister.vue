<template>
    <div>
        <!-- Forecast: what falls due, and whether it is covered -->
        <div class="library-card mb-3">
            <div class="library-card-header">
                <div class="d-flex align-items-center gap-3">
                    <div class="header-icon"><i class="ri-calendar-check-line"></i></div>
                    <div>
                        <h4 class="header-title mb-0">Cash Forecast</h4>
                        <p class="header-subtitle mb-0">
                            Checks still to move, by the date each can be cashed. Counts only checks in hand — never invoices.
                        </p>
                    </div>
                </div>
            </div>
            <div class="library-card-body">
                <div v-if="forecastLoading" class="chk-empty"><i class="ri-loader-4-line spin"></i> Loading…</div>

                <div v-else-if="!forecast.accounts?.length" class="chk-empty">
                    <i class="ri-calendar-check-line"></i>
                    <p class="mb-0">No bank accounts to project.</p>
                </div>

                <div v-else class="fc-grid">
                    <div v-for="acct in forecast.accounts" :key="acct.bank_account_id" class="fc-account" :class="{ 'fc-at-risk': acct.shortfall_date }">
                        <div class="fc-account-head">
                            <div>
                                <strong>{{ acct.bank_name }}</strong>
                                <span class="fc-account-name">{{ acct.account_name }}</span>
                            </div>
                            <span class="fc-opening">{{ money(acct.opening_balance) }}</span>
                        </div>

                        <p v-if="acct.shortfall_date" class="fc-warning">
                            <i class="ri-alert-line"></i>
                            Short on <strong>{{ acct.shortfall_date }}</strong> — lowest point {{ money(acct.lowest_balance) }}
                        </p>
                        <p v-else-if="acct.rows.length" class="fc-clear">
                            <i class="ri-check-line"></i> Covered throughout — lowest point {{ money(acct.lowest_balance) }}
                        </p>
                        <p v-else class="fc-none">No checks outstanding.</p>

                        <table v-if="acct.rows.length" class="table fc-table mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th class="text-end">In</th>
                                    <th class="text-end">Out</th>
                                    <th class="text-end">Balance after</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in acct.rows" :key="row.date" :class="{ 'fc-short': row.short }">
                                    <td class="text-nowrap">{{ row.date }}</td>
                                    <td class="text-end">{{ row.in ? money(row.in) : '—' }}</td>
                                    <td class="text-end">{{ row.out ? money(row.out) : '—' }}</td>
                                    <td class="text-end fw-semibold">
                                        {{ money(row.balance) }}
                                        <i v-if="row.short" class="ri-alert-line fc-short-icon" title="Short on this date"></i>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <p v-if="forecast.unassigned_received > 0" class="fc-unassigned">
                    <i class="ri-information-line"></i>
                    {{ money(forecast.unassigned_received) }} of received checks are not counted above — they have not been
                    deposited yet, so which account they land in is not known.
                </p>
            </div>
        </div>

        <div class="library-card">
            <div class="library-card-header">
                <div class="d-flex align-items-center gap-3">
                    <div class="header-icon"><i class="ri-bill-line"></i></div>
                    <div>
                        <h4 class="header-title mb-0">Check Register</h4>
                        <p class="header-subtitle mb-0">
                            Every check received and issued. A check moves no money until it is confirmed cleared.
                        </p>
                    </div>
                </div>
            </div>

            <div class="chk-filters">
                <select v-model="filters.direction" class="form-select chk-filter" @change="fetch">
                    <option value="">All directions</option>
                    <option value="received">Received from customers</option>
                    <option value="issued">Issued to suppliers</option>
                </select>
                <select v-model="filters.status" class="form-select chk-filter" @change="fetch">
                    <option value="">All statuses</option>
                    <option v-for="s in statuses" :key="s" :value="s">{{ label(s) }}</option>
                </select>
                <div class="chk-search">
                    <i class="ri-search-line"></i>
                    <input v-model="filters.keyword" type="text" placeholder="Check number or bank..." @input="debouncedFetch" />
                </div>
            </div>

            <div class="library-card-body p-0">
                <div v-if="loading" class="chk-empty"><i class="ri-loader-4-line spin"></i> Loading…</div>

                <div v-else-if="!rows.length" class="chk-empty">
                    <i class="ri-bill-line"></i>
                    <p class="mb-1">No checks recorded</p>
                    <small>Checks appear here as they are taken from customers or issued to suppliers.</small>
                </div>

                <div v-else class="table-responsive">
                    <table class="table cm-table mb-0">
                        <thead>
                            <tr>
                                <th>Direction</th>
                                <th>Check No</th>
                                <th>Check Date</th>
                                <th>Counterparty</th>
                                <th>Bank</th>
                                <th class="text-end">Amount</th>
                                <th>Status</th>
                                <th>Received By</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="c in rows" :key="c.id" :class="{ 'chk-overdue': isOverdue(c) }">
                                <td>
                                    <span class="chk-chip" :class="c.direction">
                                        <i :class="c.direction === 'issued' ? 'ri-arrow-up-line' : 'ri-arrow-down-line'"></i>
                                        {{ c.direction === 'issued' ? 'Issued' : 'Received' }}
                                    </span>
                                </td>
                                <td class="font-monospace">{{ c.check_number }}</td>
                                <td class="text-nowrap">
                                    {{ c.check_date }}
                                    <div v-if="isOverdue(c)" class="chk-overdue-note">due — not yet confirmed</div>
                                </td>
                                <td>{{ c.customer?.name || c.supplier?.name || '—' }}</td>
                                <td class="text-muted">{{ c.bank_name || c.bank_account?.bank_name || '—' }}</td>
                                <td class="text-end fw-semibold">{{ money(c.amount) }}</td>
                                <td>
                                    <span class="chk-status" :class="c.status">{{ label(c.status) }}</span>
                                    <div v-if="c.status === 'bounced' && c.bounce_reason" class="chk-reason">{{ c.bounce_reason }}</div>
                                </td>
                                <td class="text-muted small">{{ repName(c) }}</td>
                                <td class="text-center">
                                    <template v-if="c.status === 'pending' && canApprove">
                                        <button class="action-btn confirm" title="Confirm it cleared" @click="confirm(c)">
                                            <i class="ri-check-line"></i>
                                        </button>
                                        <button class="action-btn delete" title="Record a bounce" @click="openBounce(c)">
                                            <i class="ri-close-circle-line"></i>
                                        </button>
                                    </template>
                                    <span v-else-if="c.status !== 'pending'" class="text-muted small">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Bounce modal -->
        <div v-if="bounce.open" class="modal-overlay active" @click.self="bounce.open = false">
            <div class="modal-container" style="max-width:480px">
                <div class="modal-header">
                    <div class="modal-header-icon"><i class="ri-close-circle-line"></i></div>
                    <div>
                        <h5 class="modal-title">Record a bounced check</h5>
                        <p class="modal-subtitle">Nothing is posted. The customer still owes this amount.</p>
                    </div>
                    <button class="close-btn ms-auto" @click="bounce.open = false"><i class="ri-close-line"></i></button>
                </div>
                <div class="modal-body">
                    <p class="chk-bounce-summary">
                        Check <strong>{{ bounce.check?.check_number }}</strong> for
                        <strong>{{ money(bounce.check?.amount) }}</strong>
                        <span v-if="repName(bounce.check)"> — {{ repName(bounce.check) }} will be told to follow this up.</span>
                    </p>
                    <label class="form-label">Why did it bounce? <span class="text-danger">*</span></label>
                    <input v-model.trim="bounce.reason" type="text" class="form-control" placeholder="e.g. Insufficient funds" />
                    <div v-if="bounce.error" class="error-msg">{{ bounce.error }}</div>
                </div>
                <div class="modal-footer">
                    <button class="acct-btn-secondary" @click="bounce.open = false">Cancel</button>
                    <button class="acct-btn-primary" :disabled="bounce.saving" @click="submitBounce">
                        <span v-if="bounce.saving"><i class="ri-loader-4-line spin"></i> Saving…</span>
                        <span v-else>Record bounce</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from "axios";
import MainLayout from "@/Shared/Layouts/Main.vue";
import AccountingLayout from "@/Pages/Modules/Accounting/AccountingLayout.vue";

export default {
    layout: [MainLayout, AccountingLayout],
    props: {
        directions: { type: Array, default: () => [] },
        statuses:   { type: Array, default: () => [] },
    },
    data() {
        return {
            rows: [],
            loading: true,
            filters: { direction: '', status: '', keyword: '' },
            bounce: { open: false, check: null, reason: '', error: '', saving: false },
            forecast: { accounts: [], unassigned_received: 0 },
            forecastLoading: true,
            searchTimer: null,
        };
    },
    computed: {
        canApprove() {
            return this.can('accounting', 'check_register', 'approver');
        },
    },
    mounted() {
        this.fetch();
        this.fetchForecast();
    },
    methods: {
        fetch() {
            this.loading = true;
            axios.get('/accounting/check-register', { params: { option: 'lists', ...this.filters } })
                .then(({ data }) => { this.rows = data.data ?? []; })
                .catch(err => console.error('Could not load the register', err))
                .finally(() => { this.loading = false; });
        },
        fetchForecast() {
            this.forecastLoading = true;
            axios.get('/accounting/check-register', { params: { option: 'forecast' } })
                .then(({ data }) => { this.forecast = data; })
                .catch(err => console.error('Could not load the forecast', err))
                .finally(() => { this.forecastLoading = false; });
        },
        debouncedFetch() {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(this.fetch, 300);
        },
        label(status) {
            return { pending: 'Pending', cleared: 'Cleared', bounced: 'Bounced' }[status] ?? status;
        },
        repName(check) {
            const rep = check?.received_by;
            return rep ? `${rep.firstname} ${rep.lastname}` : '';
        },
        money(value) {
            return `PHP ${Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        },
        // A pending check whose date has passed is money that should have
        // arrived and has not been confirmed — the row worth looking at.
        isOverdue(check) {
            return check.status === 'pending' && check.check_date < new Date().toISOString().slice(0, 10);
        },
        confirm(check) {
            axios.put(`/accounting/check-register/${check.id}/confirm`)
                .then(() => { this.fetch(); this.fetchForecast(); })
                .catch(err => alert(err.response?.data?.message || 'Could not confirm this check.'));
        },
        openBounce(check) {
            this.bounce = { open: true, check, reason: '', error: '', saving: false };
        },
        submitBounce() {
            if (!this.bounce.reason) {
                this.bounce.error = 'Say why it bounced — the rep needs it to chase the customer.';
                return;
            }
            this.bounce.saving = true;
            axios.put(`/accounting/check-register/${this.bounce.check.id}/bounce`, { bounce_reason: this.bounce.reason })
                .then(() => { this.bounce.open = false; this.fetch(); this.fetchForecast(); })
                .catch(err => { this.bounce.error = err.response?.data?.message || 'Could not record the bounce.'; })
                .finally(() => { this.bounce.saving = false; });
        },
    },
};
</script>

<style scoped>
.chk-filters { display: flex; gap: 0.6rem; padding: 0.9rem 1.1rem; flex-wrap: wrap; }
.chk-filter { max-width: 220px; }
.chk-search { position: relative; flex: 1; min-width: 200px; }
.chk-search i { position: absolute; left: 0.7rem; top: 50%; transform: translateY(-50%); color: #6b8c85; }
.chk-search input {
    width: 100%; padding: 0.45rem 0.7rem 0.45rem 2rem;
    border: 1px solid #d8e6e1; border-radius: 8px; font-size: 0.85rem;
}

.chk-empty { padding: 2.5rem; text-align: center; color: #6b8c85; }
.chk-empty i { font-size: 1.8rem; display: block; margin-bottom: 0.4rem; }

.chk-chip, .chk-status {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; border-radius: 6px; font-size: 0.78rem; font-weight: 600; white-space: nowrap;
}
.chk-chip.received { background: #e9f5f0; color: #1b6b4a; border: 1px solid #a8dcc8; }
.chk-chip.issued   { background: #fff0e9; color: #9a3b1b; border: 1px solid #f9c5a8; }

.chk-status.pending { background: #fdf3e3; color: #8a5a10; border: 1px solid #f0d49a; }
.chk-status.cleared { background: #e9f5f0; color: #1b6b4a; border: 1px solid #a8dcc8; }
.chk-status.bounced { background: #fdeaea; color: #96231f; border: 1px solid #f2b8b5; }

.chk-reason, .chk-overdue-note { font-size: 0.7rem; color: #96231f; margin-top: 2px; }
.chk-overdue { background: #fffaf3; }

.chk-bounce-summary { font-size: 0.85rem; color: #33564f; margin-bottom: 0.9rem; }

.fc-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); }
.fc-account { border: 1px solid #d8e6e1; border-radius: 10px; padding: 0.9rem; background: #fbfdfc; }
.fc-account.fc-at-risk { border-color: #f2b8b5; background: #fffafa; }
.fc-account-head { display: flex; justify-content: space-between; align-items: baseline; gap: 0.5rem; margin-bottom: 0.5rem; }
.fc-account-name { color: #6b8c85; font-size: 0.78rem; margin-left: 0.35rem; }
.fc-opening { font-weight: 600; color: #16322e; }

.fc-warning, .fc-clear, .fc-none { font-size: 0.78rem; margin: 0 0 0.6rem; display: flex; align-items: center; gap: 4px; }
.fc-warning { color: #96231f; }
.fc-clear { color: #1b6b4a; }
.fc-none { color: #6b8c85; }

.fc-table { font-size: 0.8rem; }
.fc-table thead th { color: #6b8c85; font-weight: 600; border-bottom: 1px solid #e3efeb; }
.fc-short { background: #fdeaea; }
.fc-short-icon { color: #96231f; margin-left: 3px; }

.fc-unassigned {
    margin: 0.9rem 0 0; padding: 0.6rem 0.8rem; border-radius: 8px;
    background: #f4f8f7; border: 1px solid #d8e6e1; color: #33564f; font-size: 0.78rem;
}

.action-btn.confirm { color: #1b6b4a; }
.spin { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
