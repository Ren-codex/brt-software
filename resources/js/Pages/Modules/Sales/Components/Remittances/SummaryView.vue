<template>
    <div class="library-card">
        <div class="library-card-header">
            <div class="d-flex align-items-center gap-3">
                <div class="header-icon">
                    <i class="ri-bar-chart-grouped-line fs-20"></i>
                </div>
                <div>
                    <h4 class="header-title mb-1">Daily Summary</h4>
                    <p class="header-subtitle mb-0">Remittances grouped by sales rep</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <input type="date" class="date-input" v-model="filter.from" @change="fetch">
                <span class="date-sep">to</span>
                <input type="date" class="date-input" v-model="filter.to" @change="fetch">
                <button class="create-btn" @click="$emit('back')">
                    <i class="ri-arrow-left-line"></i>
                </button>
            </div>
        </div>

        <div class="library-card-body">
            <div v-if="loading" class="empty-state">
                <i class="ri-loader-4-line spin"></i>
                <p>Loading summary...</p>
            </div>

            <div v-else-if="!rows.length" class="empty-state">
                <i class="ri-inbox-line"></i>
                <p>No remittances found for the selected period.</p>
            </div>

            <div v-else class="rep-list">
                <div v-for="rep in rows" :key="rep.rep_id" class="rep-block">
                    <!-- Rep header row -->
                    <div
                        class="rep-header"
                        @click="toggleRep(rep.rep_id)"
                        :class="{ expanded: expandedReps[rep.rep_id] }"
                    >
                        <div class="d-flex align-items-center gap-3">
                            <div class="rep-avatar">
                                {{ initials(rep.rep_name) }}
                            </div>
                            <div>
                                <div class="rep-name">{{ rep.rep_name }}</div>
                                <div class="rep-meta">{{ rep.remittance_count }} remittance{{ rep.remittance_count !== 1 ? 's' : '' }}</div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <div class="rep-total">{{ formatCurrency(rep.total_amount) }}</div>
                            <i :class="['ri-arrow-down-s-line chevron', { rotated: expandedReps[rep.rep_id] }]"></i>
                        </div>
                    </div>

                    <!-- Date breakdown rows -->
                    <div v-show="expandedReps[rep.rep_id]" class="date-rows">
                        <div v-for="day in rep.dates" :key="day.date" class="date-row">
                            <div class="date-cell">
                                <i class="ri-calendar-line"></i>
                                {{ formatDate(day.date) }}
                            </div>
                            <div class="date-stat">
                                <span class="stat-label">Remittances</span>
                                <span class="stat-value">{{ day.count }}</span>
                            </div>
                            <div class="date-stat">
                                <span class="stat-label">Total Amount</span>
                                <span class="stat-value">{{ formatCurrency(day.total_amount) }}</span>
                            </div>
                            <div class="date-stat">
                                <span class="stat-label">Received</span>
                                <span class="stat-value" :class="day.received_amount > 0 ? 'text-ok' : 'text-muted'">
                                    {{ day.received_amount > 0 ? formatCurrency(day.received_amount) : '—' }}
                                </span>
                            </div>
                            <div class="date-statuses">
                                <span
                                    v-for="(count, slug) in day.statuses"
                                    :key="slug"
                                    :class="['status-pill', `status-${slug}`]"
                                >
                                    {{ slug }} {{ count }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'SummaryView',
    emits: ['back'],
    data() {
        const now = new Date();
        const firstOfMonth = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10);
        const lastOfMonth  = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().slice(0, 10);

        return {
            loading: false,
            rows: [],
            expandedReps: {},
            filter: {
                from: firstOfMonth,
                to:   lastOfMonth,
            },
        };
    },
    created() {
        this.fetch();
    },
    methods: {
        fetch() {
            this.loading = true;
            axios.get('/remittances', {
                params: {
                    option: 'summary',
                    from: this.filter.from,
                    to:   this.filter.to,
                },
            })
            .then(res => {
                this.rows = res.data.data ?? [];
                // auto-expand first rep
                if (this.rows.length && !Object.keys(this.expandedReps).length) {
                    this.expandedReps = { [this.rows[0].rep_id]: true };
                }
            })
            .catch(err => console.error(err))
            .finally(() => { this.loading = false; });
        },
        toggleRep(id) {
            this.expandedReps = {
                ...this.expandedReps,
                [id]: !this.expandedReps[id],
            };
        },
        formatCurrency(value) {
            if (!value && value !== 0) return '—';
            return '₱' + Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        formatDate(value) {
            if (!value) return '—';
            return new Date(value + 'T00:00:00').toLocaleDateString(undefined, {
                year: 'numeric', month: 'short', day: 'numeric',
            });
        },
        initials(name) {
            if (!name) return '?';
            return name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
        },
    },
};
</script>

<style scoped>
.date-input {
    padding: 0.3rem 0.6rem;
    border: 1px solid #c4d9d2;
    border-radius: 8px;
    font-size: 0.82rem;
    color: #16322e;
    background: #fff;
}
.date-sep { font-size: 0.8rem; color: #6b8c85; }

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: #6b8c85;
}
.empty-state i { font-size: 2rem; display: block; margin-bottom: 0.5rem; }

.rep-list { display: flex; flex-direction: column; gap: 10px; }

.rep-block {
    border: 1px solid #d8e9e5;
    border-radius: 12px;
    overflow: hidden;
}

.rep-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.85rem 1.1rem;
    background: #f4fbf8;
    cursor: pointer;
    user-select: none;
    transition: background 0.15s;
}
.rep-header:hover { background: #eaf5f1; }
.rep-header.expanded { background: #e3f2ed; }

.rep-avatar {
    width: 36px; height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #2fa485, #1a7e67);
    color: #fff;
    font-size: 0.8rem;
    font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.rep-name { font-size: 0.9rem; font-weight: 700; color: #16322e; }
.rep-meta { font-size: 0.75rem; color: #6b8c85; }
.rep-total { font-size: 1rem; font-weight: 700; color: #16423c; }

.chevron { font-size: 1.1rem; color: #6b8c85; transition: transform 0.2s; }
.chevron.rotated { transform: rotate(180deg); }

.date-rows { border-top: 1px solid #d8e9e5; }

.date-row {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    padding: 0.65rem 1.1rem;
    border-bottom: 1px solid #edf5f2;
    flex-wrap: wrap;
}
.date-row:last-child { border-bottom: none; }

.date-cell {
    min-width: 130px;
    font-size: 0.82rem;
    font-weight: 600;
    color: #35524d;
    display: flex; align-items: center; gap: 5px;
}
.date-stat { display: flex; flex-direction: column; min-width: 90px; }
.stat-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.04em; color: #6b8c85; font-weight: 600; }
.stat-value { font-size: 0.85rem; font-weight: 700; color: #16322e; }
.text-ok { color: #2e7d32; }
.text-muted { color: #adb5bd; }

.date-statuses { display: flex; gap: 5px; flex-wrap: wrap; margin-left: auto; }
.status-pill {
    display: inline-flex; align-items: center;
    padding: 2px 9px; border-radius: 20px;
    font-size: 0.72rem; font-weight: 700; text-transform: capitalize;
}
.status-open      { background: #fff8e1; color: #9a6b19; }
.status-liquidated { background: #e8f5e9; color: #2e7d32; }
.status-disapproved { background: #ffebee; color: #c62828; }
.status-unknown   { background: #f0f0f0; color: #6c757d; }

@keyframes spin { to { transform: rotate(360deg); } }
.spin { display: inline-block; animation: spin 0.9s linear infinite; }
</style>
