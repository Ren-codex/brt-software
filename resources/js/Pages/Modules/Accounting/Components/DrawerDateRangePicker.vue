<template>
    <div class="drp-wrap" ref="wrap">

        <!-- Trigger -->
        <div class="drp-trigger" :class="{ 'drp-active': open }" @click="toggle">
            <i class="ri-calendar-line drp-icon"></i>
            <span class="drp-value">{{ displayLabel }}</span>
            <button v-if="dateFrom || dateTo" class="drp-clear" @click.stop="clearRange">
                <i class="ri-close-line"></i>
            </button>
            <i v-else class="ri-arrow-down-s-line drp-arrow"></i>
        </div>

        <!-- Calendar dropdown — teleported to body to escape overflow:hidden parents -->
        <Teleport to="body">
            <div v-if="open" class="drp-dropdown" :style="dropdownStyle">

                <!-- Month navigation -->
                <div class="drp-nav-row">
                    <button class="drp-nav-btn" @click="prevMonth">
                        <i class="ri-arrow-left-s-line"></i>
                    </button>
                    <span class="drp-month-title">{{ monthLabel }}</span>
                    <button class="drp-nav-btn" @click="nextMonth">
                        <i class="ri-arrow-right-s-line"></i>
                    </button>
                </div>

                <!-- Grid: weekday headers + day cells -->
                <div class="drp-grid">
                    <div v-for="wd in ['Su','Mo','Tu','We','Th','Fr','Sa']" :key="wd" class="drp-wd">
                        {{ wd }}
                    </div>
                    <div
                        v-for="cell in cells"
                        :key="cell.key"
                        class="drp-cell"
                        :class="cellClass(cell)"
                        @click="cell.date && selectDay(cell.date)"
                        @mouseenter="cell.date && (hoverDate = cell.date)"
                        @mouseleave="hoverDate = null"
                    >
                        <span class="drp-day-num">{{ cell.day }}</span>
                    </div>
                </div>

                <!-- Hint -->
                <div class="drp-hint">
                    <i class="ri-checkbox-blank-circle-fill drp-step-dot" :class="step === 'from' ? 'active' : ''"></i>
                    <i class="ri-checkbox-blank-circle-fill drp-step-dot" :class="step === 'to' ? 'active' : ''"></i>
                    <span>{{ step === 'from' ? 'Click to set start date' : 'Click to set end date' }}</span>
                </div>

            </div>
        </Teleport>
    </div>
</template>

<script>
export default {
    props: {
        dateFrom: { type: String, default: '' },
        dateTo:   { type: String, default: '' },
    },
    emits: ['update:dateFrom', 'update:dateTo'],
    data() {
        const now = new Date();
        return {
            open:          false,
            viewYear:      now.getFullYear(),
            viewMonth:     now.getMonth(),
            step:          'from',
            tempFrom:      null,
            hoverDate:     null,
            dropdownStyle: { position: 'fixed', top: '0px', left: '0px', zIndex: 9999 },
        };
    },
    computed: {
        displayLabel() {
            if (!this.dateFrom && !this.dateTo) return 'All periods';
            return (this.dateFrom || '—') + ' — ' + (this.dateTo || '—');
        },
        monthLabel() {
            return new Date(this.viewYear, this.viewMonth, 1)
                .toLocaleString('en-US', { month: 'long', year: 'numeric' });
        },
        cells() {
            const y = this.viewYear, m = this.viewMonth;
            const firstDow    = new Date(y, m, 1).getDay();
            const daysInMonth = new Date(y, m + 1, 0).getDate();
            const pad = n => String(n).padStart(2, '0');
            const result = [];
            for (let i = 0; i < firstDow; i++) {
                result.push({ key: `e${i}`, day: '', date: null });
            }
            for (let d = 1; d <= daysInMonth; d++) {
                const ds = `${y}-${pad(m + 1)}-${pad(d)}`;
                result.push({ key: ds, day: d, date: ds });
            }
            return result;
        },
    },
    methods: {
        toggle() {
            if (this.open) { this.open = false; return; }
            this.step      = 'from';
            this.tempFrom  = null;
            this.hoverDate = null;
            if (this.dateFrom) {
                const [y, m] = this.dateFrom.split('-');
                this.viewYear  = +y;
                this.viewMonth = +m - 1;
            } else {
                const now      = new Date();
                this.viewYear  = now.getFullYear();
                this.viewMonth = now.getMonth();
            }
            this.open = true;
            this.$nextTick(() => this.positionDropdown());
        },
        positionDropdown() {
            if (!this.$refs.wrap) return;
            const rect = this.$refs.wrap.getBoundingClientRect();
            this.dropdownStyle = {
                position: 'fixed',
                top:      (rect.bottom + 6) + 'px',
                left:     rect.left + 'px',
                zIndex:   9999,
            };
        },
        prevMonth() {
            if (this.viewMonth === 0) { this.viewYear--;  this.viewMonth = 11; }
            else                        this.viewMonth--;
        },
        nextMonth() {
            if (this.viewMonth === 11) { this.viewYear++; this.viewMonth = 0; }
            else                         this.viewMonth++;
        },
        selectDay(date) {
            if (this.step === 'from') {
                this.tempFrom = date;
                this.step     = 'to';
            } else {
                let from = this.tempFrom, to = date;
                if (from > to) [from, to] = [to, from];
                this.$emit('update:dateFrom', from);
                this.$emit('update:dateTo',   to);
                this.open = false; this.step = 'from'; this.tempFrom = null; this.hoverDate = null;
            }
        },
        clearRange() {
            this.$emit('update:dateFrom', '');
            this.$emit('update:dateTo',   '');
        },
        cellClass(cell) {
            if (!cell.date) return {};
            const d = cell.date;

            let from = this.step === 'to' ? this.tempFrom            : (this.dateFrom || null);
            let to   = this.step === 'to' ? (this.hoverDate || null) : (this.dateTo   || null);
            if (from && to && from > to) [from, to] = [to, from];

            const isFrom   = !!from && d === from;
            const isTo     = !!to   && d === to;
            const hasRange = !!from && !!to && from !== to;
            const inRange  = hasRange && d > from && d < to;

            return {
                'drp-cell-day':  true,
                'drp-from':      isFrom && hasRange,
                'drp-to':        isTo   && hasRange,
                'drp-single':    (isFrom || isTo) && !hasRange,
                'drp-in-range':  inRange,
            };
        },
        onClickOutside(e) {
            if (!this.open) return;
            const wrap = this.$refs.wrap;
            const dropdown = document.querySelector('.drp-dropdown');
            if (wrap && !wrap.contains(e.target) && dropdown && !dropdown.contains(e.target)) {
                this.open = false;
            }
        },
        onScroll() { this.open = false; },
    },
    mounted() {
        document.addEventListener('mousedown', this.onClickOutside);
        window.addEventListener('scroll', this.onScroll, true);
    },
    beforeUnmount() {
        document.removeEventListener('mousedown', this.onClickOutside);
        window.removeEventListener('scroll', this.onScroll, true);
    },
};
</script>

<style scoped>
/* ── Wrapper ─────────────────────────────────────── */
.drp-wrap { position: relative; display: inline-block; }

/* ── Trigger ─────────────────────────────────────── */
.drp-trigger {
    display: inline-flex; align-items: center; gap: 0.4rem;
    height: 30px; padding: 0 0.6rem;
    border: 1px solid #c4d9d2; border-radius: 7px;
    background: #fff; cursor: pointer;
    font-size: 0.78rem; color: #1e3530;
    transition: border-color 0.12s;
    white-space: nowrap;
}
.drp-trigger:hover,
.drp-trigger.drp-active { border-color: #3d8d7a; }

.drp-icon  { color: #9ab8af; font-size: 0.85rem; flex-shrink: 0; }
.drp-arrow { color: #9ab8af; font-size: 0.85rem; flex-shrink: 0; }
.drp-value { flex: 1; }

.drp-clear {
    background: none; border: none; padding: 0;
    color: #9ab8af; cursor: pointer; font-size: 0.85rem;
    display: flex; align-items: center; flex-shrink: 0;
}
.drp-clear:hover { color: #527267; }
</style>

<style>
/* ── Dropdown (global — rendered via Teleport at body level) ── */
.drp-dropdown {
    background: #fff;
    border: 1px solid #d1e4dc; border-radius: 14px;
    box-shadow: 0 8px 32px rgba(10,28,25,0.13);
    padding: 0.75rem;
    min-width: 280px;
}

.drp-nav-row {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 0.6rem;
}
.drp-month-title { font-size: 0.84rem; font-weight: 700; color: #1e3530; }
.drp-nav-btn {
    background: none; border: 1px solid #e4eeea; border-radius: 7px;
    width: 26px; height: 26px; cursor: pointer; color: #527267;
    display: flex; align-items: center; justify-content: center; font-size: 1rem;
    transition: background 0.1s;
}
.drp-nav-btn:hover { background: #edf5f2; }

.drp-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 0;
}

.drp-wd {
    height: 28px; display: flex; align-items: center; justify-content: center;
    font-size: 0.67rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.04em; color: #9ab8af;
}

.drp-cell {
    position: relative;
    height: 34px;
    display: flex; align-items: center; justify-content: center;
    cursor: default;
}
.drp-cell-day { cursor: pointer; }

.drp-day-num {
    width: 30px; height: 30px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 50%;
    font-size: 0.82rem; color: #1e3530;
    position: relative; z-index: 1;
    transition: background 0.1s;
}

.drp-from::before,
.drp-to::before,
.drp-in-range::before {
    content: '';
    position: absolute;
    top: 4px; bottom: 4px; left: 0; right: 0;
    background: #dff0ec;
    z-index: 0;
}
.drp-from::before     { left: 50%; }
.drp-to::before       { right: 50%; }
.drp-in-range::before { left: 0; right: 0; }

.drp-from .drp-day-num,
.drp-to   .drp-day-num,
.drp-single .drp-day-num {
    background: #3d8d7a;
    color: #fff;
    font-weight: 700;
}

.drp-cell-day:not(.drp-from):not(.drp-to):not(.drp-single):hover .drp-day-num {
    background: #edf5f2;
}

.drp-hint {
    display: flex; align-items: center; gap: 0.35rem;
    margin-top: 0.5rem; padding-top: 0.5rem;
    border-top: 1px solid #f0f7f4;
    font-size: 0.72rem; color: #9ab8af;
}
.drp-step-dot { font-size: 0.45rem; color: #d5eae2; }
.drp-step-dot.active { color: #3d8d7a; }
</style>
