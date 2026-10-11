<template>
    <div v-show="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="hide">
        <div class="modal-container" style="max-width: 560px;" @click.stop>
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="modal-header-icon">
                        <i class="ri-route-line"></i>
                    </div>
                    <div>
                        <h5 class="mb-0">Status Timeline</h5>
                        <small>{{ timeline ? timeline.so_number : 'Sales order' }}</small>
                    </div>
                </div>
                <button class="close-btn" @click="hide"><i class="ri-close-line"></i></button>
            </div>

            <div class="modal-body">
                <div v-if="loading" class="timeline-message">
                    <i class="ri-loader-4-line timeline-spin"></i> Loading timeline…
                </div>

                <div v-else-if="error" class="timeline-message timeline-error">
                    <i class="ri-error-warning-line"></i> {{ error }}
                </div>

                <template v-else-if="timeline">
                    <!-- Where it stands, in one glance. -->
                    <div class="timeline-summary">
                        <div class="timeline-summary-top">
                            <div>
                                <div class="timeline-customer">{{ timeline.customer }}</div>
                                <div class="timeline-meta">{{ timeline.payment_mode }} · {{ peso(timeline.total_amount) }}</div>
                            </div>
                            <span class="timeline-status"
                                :style="{ color: timeline.status.text_color, backgroundColor: timeline.status.bg_color }">
                                {{ timeline.status.name || 'No status' }}
                            </span>
                        </div>
                        <p class="timeline-waiting">{{ timeline.summary }}</p>
                    </div>

                    <ol class="timeline-steps">
                        <li v-for="step in timeline.steps" :key="step.key" class="timeline-step" :class="'is-' + step.state">
                            <span class="timeline-dot">
                                <i :class="dotIcon(step)"></i>
                            </span>
                            <div class="timeline-content">
                                <div class="timeline-row">
                                    <span class="timeline-label">{{ step.label }}</span>
                                    <span class="timeline-tag" v-if="step.state === 'current'">Now</span>
                                    <span class="timeline-date" v-if="step.at">{{ formatDate(step.at) }}</span>
                                </div>
                                <div class="timeline-detail">{{ step.detail }}</div>
                                <div class="timeline-by" v-if="step.by">by {{ step.by }}</div>
                            </div>
                        </li>
                    </ol>
                </template>
            </div>

            <div class="modal-footer">
                <button class="btn btn-light" @click="hide">Close</button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    data() {
        return {
            showModal: false,
            loading: false,
            error: null,
            timeline: null,
        };
    },
    methods: {
        show(orderId) {
            this.showModal = true;
            this.loading = true;
            this.error = null;
            this.timeline = null;

            axios.get(`/sales-orders/${orderId}/timeline`)
                .then(res => { this.timeline = res.data; })
                .catch(err => {
                    this.error = err?.response?.data?.message || 'Could not load the timeline for this order.';
                })
                .finally(() => { this.loading = false; });
        },
        hide() {
            this.showModal = false;
        },
        dotIcon(step) {
            if (step.state === 'done') return 'ri-check-line';
            if (step.state === 'stopped') return 'ri-close-line';
            return step.icon;
        },
        peso(value) {
            return '₱' + Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        formatDate(value) {
            const d = new Date(value);
            if (Number.isNaN(d.getTime())) return '';
            return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
        },
    },
};
</script>

<style scoped>
/* Content only — the modal frame comes from _library-modal.scss. */
.timeline-message {
    padding: 2rem 1rem;
    text-align: center;
    color: #6b7280;
    font-size: 0.85rem;
}
.timeline-error { color: #b42318; }
.timeline-spin { display: inline-block; animation: timeline-spin 0.9s linear infinite; }
@keyframes timeline-spin { to { transform: rotate(360deg); } }

.timeline-summary {
    border: 1px solid var(--brt-c-dfe9e6);
    border-radius: 12px;
    background: var(--brt-c-f7faf9);
    padding: 0.85rem 1rem;
    margin-bottom: 1.1rem;
}
.timeline-summary-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
}
.timeline-customer { font-weight: 700; color: var(--brt-ink); font-size: 0.95rem; }
.timeline-meta { font-size: 0.78rem; color: var(--brt-muted); }
.timeline-status {
    flex-shrink: 0;
    padding: 0.25rem 0.65rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
    white-space: nowrap;
    background: #e5e7eb;
    color: #374151;
}
.timeline-waiting {
    margin: 0.6rem 0 0;
    font-size: 0.82rem;
    color: #374151;
}

.timeline-steps {
    list-style: none;
    margin: 0;
    padding: 0;
}
.timeline-step {
    position: relative;
    display: flex;
    gap: 0.85rem;
    padding-bottom: 1.1rem;
}
/* The line joining one dot to the next. */
.timeline-step:not(:last-child)::before {
    content: "";
    position: absolute;
    left: 15px;
    top: 32px;
    bottom: 0;
    width: 2px;
    background: var(--brt-c-e3ece8);
}
.timeline-step.is-done:not(:last-child)::before { background: var(--brt-primary); }
.timeline-step:last-child { padding-bottom: 0; }

.timeline-dot {
    position: relative;
    z-index: 1;
    flex-shrink: 0;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    background: #fff;
    border: 2px solid var(--brt-c-d5dedb);
    color: #9ca3af;
}
.is-done .timeline-dot { background: var(--brt-primary); border-color: var(--brt-primary); color: #fff; }
.is-current .timeline-dot {
    border-color: #f59e0b;
    color: #b45309;
    box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.18);
}
.is-stopped .timeline-dot { background: #dc2626; border-color: #dc2626; color: #fff; }
.is-skipped .timeline-dot { border-style: dashed; }

.timeline-content { flex: 1 1 auto; min-width: 0; padding-top: 0.2rem; }
.timeline-row { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.timeline-label { font-weight: 700; font-size: 0.88rem; color: var(--brt-ink); }
.is-upcoming .timeline-label,
.is-skipped .timeline-label { color: #9ca3af; font-weight: 600; }
.is-skipped .timeline-label { text-decoration: line-through; }
.is-stopped .timeline-label { color: #b42318; }
.timeline-tag {
    background: #fef3c7;
    color: #92400e;
    border-radius: 999px;
    padding: 0.05rem 0.45rem;
    font-size: 0.66rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.timeline-date { margin-left: auto; font-size: 0.74rem; color: #6b7280; white-space: nowrap; }
.timeline-detail { font-size: 0.8rem; color: #4b5563; margin-top: 0.1rem; }
.is-upcoming .timeline-detail,
.is-skipped .timeline-detail { color: #9ca3af; }
.timeline-by { font-size: 0.72rem; color: var(--brt-muted); margin-top: 0.1rem; }
</style>
