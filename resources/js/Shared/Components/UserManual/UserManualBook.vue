<template>
    <Teleport to="body">
        <Transition name="um-fade">
            <div v-if="modelValue" class="um-overlay" role="dialog" aria-modal="true" aria-label="User manual"
                @click.self="close">
                <!-- ------------------------------------------------ top bar -->
                <header class="um-bar">
                    <div class="um-bar-title">
                        <i class="ri-book-open-line"></i>
                        <div>
                            <strong>User Manual</strong>
                            <span>Roles &amp; system processes</span>
                        </div>
                    </div>

                    <div v-if="visibleRoles.length" class="um-bar-roles">
                        <button v-for="role in visibleRoles" :key="role.key" type="button" class="um-role-chip"
                            :class="{ 'is-mine': isMyRole(role) }"
                            :style="{ '--chip': role.accent }"
                            @click="jumpToRole(role.key)">
                            <i :class="role.icon"></i>
                            <span>{{ role.name }}</span>
                        </button>
                    </div>

                    <button v-if="canFilter" type="button" class="um-filter"
                        :class="{ 'is-on': roleFilter }" @click="toggleFilter">
                        <i :class="roleFilter ? 'ri-user-star-line' : 'ri-book-2-line'"></i>
                        <span>{{ roleFilter ? 'My role' : 'Everything' }}</span>
                    </button>

                    <button type="button" class="um-close" aria-label="Close manual" @click="close">
                        <i class="ri-close-line"></i>
                    </button>
                </header>

                <div class="um-main">
                    <!-- ------------------------------------------- chapter rail -->
                    <aside class="um-rail" :class="{ 'is-open': railOpen }">
                        <button type="button" class="um-rail-toggle" @click="railOpen = !railOpen">
                            <i :class="railOpen ? 'ri-menu-fold-line' : 'ri-menu-unfold-line'"></i>
                            <span>Contents</span>
                        </button>

                        <nav class="um-rail-body">
                            <button type="button" class="um-rail-item um-rail-item--top"
                                :class="{ 'is-active': flipped === 0 }" @click="jumpToPage(0)">
                                <i class="ri-bookmark-line"></i> Cover
                            </button>

                            <div v-for="group in toc" :key="group.part" class="um-rail-group">
                                <button type="button" class="um-rail-part" @click="jumpToPage(group.index)">
                                    {{ group.part }}
                                </button>
                                <button v-for="entry in group.entries" :key="entry.index" type="button"
                                    class="um-rail-item" :class="{ 'is-active': isCurrent(entry.index) }"
                                    @click="jumpToPage(entry.index)">
                                    {{ entry.title }}
                                </button>
                            </div>
                        </nav>
                    </aside>

                    <!-- ------------------------------------------------- book -->
                    <div class="um-stage">
                        <div class="um-book" :class="bookClass" :style="{ '--flip-ms': flipMs + 'ms' }">
                            <!-- static paper stack beneath the sheets -->
                            <div class="um-base um-base--left"></div>
                            <div class="um-base um-base--right"></div>
                            <div class="um-spine"></div>

                            <!-- turnable sheets -->
                            <div v-for="(sheet, i) in sheets" :key="i" class="um-sheet"
                                :class="{ 'is-flipped': i < flipped, 'is-flipping': i === flipping }"
                                :style="{ zIndex: sheetZ(i) }">
                                <div class="um-face um-face--front">
                                    <ManualPage :page="sheet.front" :page-number="folio(sheet.frontIndex)" :toc="toc"
                                        :roles="roles" :user-roles="userRoles" @navigate="jumpToPage" />
                                    <span class="um-shade"></span>
                                </div>
                                <div class="um-face um-face--back">
                                    <ManualPage :page="sheet.back" :page-number="folio(sheet.backIndex)" :toc="toc"
                                        :roles="roles" :user-roles="userRoles" @navigate="jumpToPage" />
                                    <span class="um-shade"></span>
                                </div>
                            </div>

                            <!-- edge hit areas for turning -->
                            <button type="button" class="um-edge um-edge--left" aria-label="Previous page"
                                :disabled="flipped === 0" @click="prev"></button>
                            <button type="button" class="um-edge um-edge--right" aria-label="Next page"
                                :disabled="flipped >= sheets.length" @click="next"></button>
                        </div>
                    </div>
                </div>

                <!-- ---------------------------------------------- bottom bar -->
                <footer class="um-controls">
                    <button type="button" class="um-nav" :disabled="flipped === 0" @click="prev">
                        <i class="ri-arrow-left-s-line"></i> Previous
                    </button>

                    <div class="um-progress">
                        <div class="um-progress-track">
                            <div class="um-progress-fill" :style="{ width: progress + '%' }"></div>
                        </div>
                        <span class="um-progress-label">{{ spreadLabel }}</span>
                    </div>

                    <button type="button" class="um-nav" :disabled="flipped >= sheets.length" @click="next">
                        Next <i class="ri-arrow-right-s-line"></i>
                    </button>
                </footer>
            </div>
        </Transition>
    </Teleport>
</template>

<script>
import ManualPage from './ManualPage.vue';
import { pages as sourcePages, roles } from './manualContent.js';

const SINGLE_FLIP_MS = 900;
const RIFFLE_FLIP_MS = 420;

export default {
    name: 'UserManualBook',
    components: { ManualPage },
    props: {
        modelValue: { type: Boolean, default: false },
    },
    emits: ['update:modelValue'],
    data() {
        return {
            roles,
            flipped: 0,
            flipping: -1,
            flipMs: SINGLE_FLIP_MS,
            railOpen: true,
            // Show only the pages written for the reader's role by default.
            roleFilter: true,
            flipTimer: null,
            riffleTimer: null,
        };
    },
    computed: {
        /** Role keys held by the reader, matched against page audiences. */
        userRoleKeys() {
            return roles.filter((r) => this.userRoles.includes(r.name)).map((r) => r.key);
        },
        /** True when we know the reader's role and can therefore filter for them. */
        canFilter() {
            return this.userRoleKeys.length > 0;
        },
        /**
         * Role shortcuts in the toolbar. While filtered, only the reader's own
         * roles are offered, since the other roles' chapters are not in the book.
         * Switching to "Everything" brings the full set back.
         */
        visibleRoles() {
            if (!this.roleFilter || !this.canFilter) return roles;
            return roles.filter((role) => this.userRoleKeys.includes(role.key));
        },
        /**
         * Pages written for this reader. A page with no audience is general and
         * always kept; a part divider is dropped if nothing under it survives.
         */
        visiblePages() {
            if (!this.roleFilter || !this.canFilter) return sourcePages;

            const kept = sourcePages.filter((page) => (
                !page.audience || page.audience.some((key) => this.userRoleKeys.includes(key))
            ));

            return kept.filter((page) => (
                page.kind !== 'part' || kept.some((other) => other.part === page.part && other.kind !== 'part')
            ));
        },
        /**
         * Visible pages padded so the back cover always sits on the reverse of a
         * sheet, which keeps it as the last thing the reader sees.
         */
        paddedPages() {
            const list = [...this.visiblePages];
            if (list.length % 2 !== 0) {
                list.splice(list.length - 1, 0, { id: 'blank', kind: 'blank' });
            }
            return list;
        },
        sheets() {
            const out = [];
            for (let i = 0; i < this.paddedPages.length; i += 2) {
                out.push({
                    front: this.paddedPages[i],
                    back: this.paddedPages[i + 1],
                    frontIndex: i,
                    backIndex: i + 1,
                });
            }
            return out;
        },
        toc() {
            const groups = [];
            this.paddedPages.forEach((page, index) => {
                if (!page.part || page.kind === 'blank') return;

                let group = groups.find((g) => g.part === page.part);
                if (!group) {
                    group = { part: page.part, index, entries: [] };
                    groups.push(group);
                }
                if (page.kind === 'part') {
                    group.index = index;
                    return;
                }
                group.entries.push({ index, title: page.title, roleKey: page.roleKey });
            });
            return groups;
        },
        userRoles() {
            const shared = this.$page?.props?.roles;
            return Array.isArray(shared) ? shared : [];
        },
        bookClass() {
            return {
                'is-closed': this.flipped === 0,
                'is-ended': this.flipped >= this.sheets.length,
            };
        },
        progress() {
            if (!this.sheets.length) return 0;
            return Math.round((this.flipped / this.sheets.length) * 100);
        },
        spreadLabel() {
            if (this.flipped === 0) return 'Front cover';
            if (this.flipped >= this.sheets.length) return 'Back cover';
            const left = this.flipped * 2 - 1;
            return `Pages ${left}–${left + 1} of ${this.paddedPages.length - 1}`;
        },
    },
    watch: {
        modelValue(open) {
            if (open) {
                document.body.style.overflow = 'hidden';
                window.addEventListener('keydown', this.onKeydown);
            } else {
                document.body.style.overflow = '';
                window.removeEventListener('keydown', this.onKeydown);
                this.stopTimers();
            }
        },
    },
    beforeUnmount() {
        document.body.style.overflow = '';
        window.removeEventListener('keydown', this.onKeydown);
        this.stopTimers();
    },
    methods: {
        close() {
            this.$emit('update:modelValue', false);
        },
        stopTimers() {
            clearTimeout(this.flipTimer);
            clearInterval(this.riffleTimer);
            this.flipTimer = null;
            this.riffleTimer = null;
            this.flipping = -1;
        },
        /** Stacking order: flipped sheets pile up on the left, unflipped on the right. */
        sheetZ(i) {
            const total = this.sheets.length;
            const base = i < this.flipped ? i : total - i;
            return i === this.flipping ? base + total + 10 : base;
        },
        folio(index) {
            const page = this.paddedPages[index];
            if (!page || page.kind === 'cover' || page.kind === 'back' || page.kind === 'blank') return '';
            return index;
        },
        markFlipping(index) {
            this.flipping = index;
            clearTimeout(this.flipTimer);
            this.flipTimer = setTimeout(() => {
                this.flipping = -1;
            }, this.flipMs);
        },
        next() {
            if (this.flipped >= this.sheets.length) return;
            clearInterval(this.riffleTimer);
            this.flipMs = SINGLE_FLIP_MS;
            this.markFlipping(this.flipped);
            this.flipped += 1;
        },
        prev() {
            if (this.flipped === 0) return;
            clearInterval(this.riffleTimer);
            this.flipMs = SINGLE_FLIP_MS;
            this.flipped -= 1;
            this.markFlipping(this.flipped);
        },
        isCurrent(pageIndex) {
            return this.flipped === Math.ceil(pageIndex / 2);
        },
        /**
         * Turn to the sheet that shows `pageIndex`. A page on a front face needs
         * its sheet unflipped; a page on a back face needs it flipped.
         */
        jumpToPage(pageIndex) {
            const target = Math.ceil(pageIndex / 2);
            if (target === this.flipped) return;

            this.stopTimers();
            const distance = Math.abs(target - this.flipped);

            if (distance === 1) {
                target > this.flipped ? this.next() : this.prev();
                return;
            }

            // Riffle through the intervening sheets rather than jump-cutting.
            this.flipMs = RIFFLE_FLIP_MS;
            const step = target > this.flipped ? 1 : -1;
            const interval = distance > 6 ? 70 : 130;

            this.riffleTimer = setInterval(() => {
                if (this.flipped === target) {
                    clearInterval(this.riffleTimer);
                    this.riffleTimer = null;
                    this.flipTimer = setTimeout(() => {
                        this.flipping = -1;
                        this.flipMs = SINGLE_FLIP_MS;
                    }, this.flipMs);
                    return;
                }
                if (step > 0) {
                    this.flipping = this.flipped;
                    this.flipped += 1;
                } else {
                    this.flipped -= 1;
                    this.flipping = this.flipped;
                }
            }, interval);
        },
        /**
         * Page indices shift when the filter changes, so any in-flight turn is
         * cancelled and the book returns to the cover.
         */
        toggleFilter() {
            this.stopTimers();
            this.roleFilter = !this.roleFilter;
            this.flipped = 0;
        },
        jumpToRole(roleKey) {
            const index = this.paddedPages.findIndex((p) => p.roleKey === roleKey);

            if (index !== -1) {
                this.jumpToPage(index);
                return;
            }

            // That role's pages are filtered out. Show everything, then jump
            // once the wider page list has rendered.
            if (this.roleFilter) {
                this.toggleFilter();
                this.$nextTick(() => {
                    const wider = this.paddedPages.findIndex((p) => p.roleKey === roleKey);
                    if (wider !== -1) this.jumpToPage(wider);
                });
            }
        },
        isMyRole(role) {
            return this.userRoles.includes(role.name);
        },
        onKeydown(event) {
            if (event.key === 'Escape') {
                this.close();
            } else if (event.key === 'ArrowRight' || event.key === 'PageDown') {
                event.preventDefault();
                this.next();
            } else if (event.key === 'ArrowLeft' || event.key === 'PageUp') {
                event.preventDefault();
                this.prev();
            } else if (event.key === 'Home') {
                this.jumpToPage(0);
            }
        },
    },
};
</script>

<style scoped>
/* ------------------------------------------------------------- overlay */
.um-overlay {
    position: fixed;
    inset: 0;
    z-index: 20000;
    display: flex;
    flex-direction: column;
    background:
        radial-gradient(ellipse at 50% 0%, rgba(72, 92, 78, 0.5), transparent 60%),
        linear-gradient(160deg, #14181c 0%, #0b0e11 60%, #05070a 100%);
    backdrop-filter: blur(6px);
}

.um-fade-enter-active,
.um-fade-leave-active {
    transition: opacity 0.35s ease;
}

.um-fade-enter-from,
.um-fade-leave-to {
    opacity: 0;
}

@keyframes umBookIn {
    from {
        transform: translateX(-25%) translateY(40px) rotateX(28deg) rotateZ(-4deg) scale(0.82);
        opacity: 0;
    }
}

/* ---------------------------------------------------------------- bars */
.um-bar {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 1.25rem;
    padding: 0.85rem clamp(0.85rem, 2vw, 1.75rem);
    border-bottom: 1px solid rgba(255, 255, 255, 0.07);
    color: #e8eef0;
}

.um-bar-title {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    flex-shrink: 0;
}

.um-bar-title i {
    font-size: 1.5rem;
    color: #7fd4a8;
}

.um-bar-title strong {
    display: block;
    font-size: 0.98rem;
    line-height: 1.2;
}

.um-bar-title span {
    display: block;
    font-size: 0.74rem;
    color: rgba(232, 238, 240, 0.5);
}

.um-bar-roles {
    flex: 1;
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    justify-content: center;
}

.um-role-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.34rem 0.8rem;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.13);
    background: rgba(255, 255, 255, 0.05);
    color: rgba(232, 238, 240, 0.8);
    font-size: 0.76rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.um-role-chip i {
    color: var(--chip);
    font-size: 0.95rem;
}

.um-role-chip:hover {
    background: rgba(255, 255, 255, 0.12);
    color: #fff;
    transform: translateY(-1px);
}

.um-role-chip.is-mine {
    border-color: var(--chip);
    box-shadow: 0 0 0 1px var(--chip) inset;
    color: #fff;
}

.um-filter {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.42rem 0.9rem;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.14);
    background: rgba(255, 255, 255, 0.05);
    color: rgba(232, 238, 240, 0.8);
    font-size: 0.78rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.um-filter i {
    font-size: 0.95rem;
}

.um-filter:hover {
    background: rgba(255, 255, 255, 0.12);
    color: #fff;
}

.um-filter.is-on {
    border-color: #7fd4a8;
    background: rgba(127, 212, 168, 0.16);
    color: #fff;
}

.um-close {
    flex-shrink: 0;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    border: 1px solid rgba(255, 255, 255, 0.13);
    background: rgba(255, 255, 255, 0.05);
    color: #e8eef0;
    font-size: 1.15rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.um-close:hover {
    background: #ef4444;
    border-color: #ef4444;
    transform: rotate(90deg);
}

/* ---------------------------------------------------------------- main */
.um-main {
    flex: 1;
    min-height: 0;
    display: flex;
}

/* ---------------------------------------------------------------- rail */
.um-rail {
    flex-shrink: 0;
    width: 250px;
    display: flex;
    flex-direction: column;
    border-right: 1px solid rgba(255, 255, 255, 0.07);
    transition: width 0.3s ease;
    overflow: hidden;
}

.um-rail:not(.is-open) {
    width: 54px;
}

.um-rail-toggle {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.85rem 1rem;
    border: none;
    background: none;
    color: rgba(232, 238, 240, 0.6);
    font-size: 0.72rem;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    cursor: pointer;
    white-space: nowrap;
}

.um-rail-toggle i {
    font-size: 1.15rem;
}

.um-rail-toggle:hover {
    color: #fff;
}

.um-rail-body {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding: 0 0.6rem 1.5rem;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.15) transparent;
}

.um-rail:not(.is-open) .um-rail-body {
    opacity: 0;
    pointer-events: none;
}

.um-rail-body::-webkit-scrollbar {
    width: 5px;
}

.um-rail-body::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.14);
    border-radius: 3px;
}

.um-rail-group {
    margin-top: 1rem;
}

.um-rail-part {
    display: block;
    width: 100%;
    text-align: left;
    padding: 0.3rem 0.7rem;
    border: none;
    background: none;
    color: #7fd4a8;
    font-size: 0.66rem;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    cursor: pointer;
}

.um-rail-item {
    display: block;
    width: 100%;
    text-align: left;
    padding: 0.42rem 0.7rem;
    border: none;
    border-left: 2px solid transparent;
    background: none;
    color: rgba(232, 238, 240, 0.62);
    font-size: 0.8rem;
    line-height: 1.35;
    cursor: pointer;
    border-radius: 0 6px 6px 0;
    transition: all 0.18s ease;
}

.um-rail-item:hover {
    background: rgba(255, 255, 255, 0.06);
    color: #fff;
}

.um-rail-item.is-active {
    border-left-color: #7fd4a8;
    background: rgba(127, 212, 168, 0.12);
    color: #fff;
}

.um-rail-item--top {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

/* --------------------------------------------------------------- stage */
.um-stage {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: clamp(0.5rem, 2vh, 1.75rem);
    perspective: 2800px;
    perspective-origin: 50% 45%;
}

.um-book {
    position: relative;
    /* 165vh keeps the spread from going wide-and-short on shallow viewports */
    width: min(1120px, 100%, 165vh);
    height: min(690px, 100%);
    transform-style: preserve-3d;
    transform: rotateX(9deg);
    transition: transform 0.9s cubic-bezier(0.45, 0.05, 0.25, 1);
    filter: drop-shadow(0 42px 60px rgba(0, 0, 0, 0.6));
    /* `backwards` so the resting transform stays under transition control */
    animation: umBookIn 0.85s cubic-bezier(0.22, 1, 0.36, 1) backwards;
}

.um-book.is-closed {
    transform: translateX(-25%) rotateX(9deg);
}

.um-book.is-ended {
    transform: translateX(25%) rotateX(9deg);
}

/* paper stack visible beneath the turnable sheets */
.um-base {
    position: absolute;
    top: 0;
    height: 100%;
    width: 50%;
    background: linear-gradient(180deg, #efe7d8 0%, #e3d8c4 100%);
}

.um-base--left {
    left: 0;
    border-radius: 7px 0 0 7px;
    box-shadow: inset -14px 0 22px -14px rgba(60, 40, 20, 0.55);
}

.um-base--right {
    left: 50%;
    border-radius: 0 7px 7px 0;
    box-shadow: inset 14px 0 22px -14px rgba(60, 40, 20, 0.55);
}

/* page-edge thickness */
.um-base--left::before,
.um-base--right::before {
    content: '';
    position: absolute;
    top: 1.5%;
    bottom: 1.5%;
    width: 9px;
    background: repeating-linear-gradient(180deg, #d9cdb6 0 2px, #c8b99c 2px 3px);
}

.um-base--left::before {
    left: -9px;
    border-radius: 5px 0 0 5px;
}

.um-base--right::before {
    right: -9px;
    border-radius: 0 5px 5px 0;
}

.um-spine {
    position: absolute;
    top: 0;
    left: calc(50% - 11px);
    width: 22px;
    height: 100%;
    z-index: 900;
    pointer-events: none;
    background: linear-gradient(90deg,
            rgba(60, 40, 20, 0) 0%,
            rgba(60, 40, 20, 0.28) 42%,
            rgba(60, 40, 20, 0.4) 50%,
            rgba(60, 40, 20, 0.28) 58%,
            rgba(60, 40, 20, 0) 100%);
}

/* -------------------------------------------------------------- sheets */
.um-sheet {
    position: absolute;
    top: 0;
    left: 50%;
    width: 50%;
    height: 100%;
    transform-origin: left center;
    transform-style: preserve-3d;
    transition: transform var(--flip-ms, 900ms) cubic-bezier(0.45, 0.05, 0.25, 1);
}

.um-sheet.is-flipped {
    transform: rotateY(-180deg);
}

.um-face {
    position: absolute;
    inset: 0;
    overflow: hidden;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
    background-color: #fbf6ec;
}

.um-face--front {
    border-radius: 0 7px 7px 0;
    background-image: linear-gradient(90deg, rgba(60, 40, 20, 0.17), rgba(60, 40, 20, 0) 7%);
}

.um-face--back {
    transform: rotateY(180deg);
    border-radius: 7px 0 0 7px;
    background-image: linear-gradient(270deg, rgba(60, 40, 20, 0.17), rgba(60, 40, 20, 0) 7%);
}

/* travelling shadow that sells the turn */
.um-shade {
    position: absolute;
    inset: 0;
    pointer-events: none;
    opacity: 0;
    background: linear-gradient(90deg, rgba(20, 12, 4, 0.55), rgba(20, 12, 4, 0));
}

.um-sheet.is-flipping .um-shade {
    animation: umShade var(--flip-ms, 900ms) ease-in-out;
}

@keyframes umShade {

    0%,
    100% {
        opacity: 0;
    }

    50% {
        opacity: 0.5;
    }
}

/* ---------------------------------------------------------- edge hits */
.um-edge {
    position: absolute;
    top: 0;
    height: 100%;
    width: 8%;
    z-index: 950;
    border: none;
    background: transparent;
    cursor: pointer;
}

.um-edge:disabled {
    cursor: default;
}

.um-edge--left {
    left: 0;
}

.um-edge--right {
    right: 0;
}

.um-book.is-closed .um-edge--right {
    right: 0;
    width: 50%;
}

/* ------------------------------------------------------------ controls */
.um-controls {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: clamp(0.75rem, 3vw, 2.5rem);
    padding: 0.85rem clamp(0.85rem, 2vw, 1.75rem) 1.1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.07);
}

.um-nav {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.5rem 1.1rem;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.14);
    background: rgba(255, 255, 255, 0.05);
    color: #e8eef0;
    font-size: 0.82rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.um-nav:hover:not(:disabled) {
    background: rgba(127, 212, 168, 0.18);
    border-color: #7fd4a8;
}

.um-nav:disabled {
    opacity: 0.3;
    cursor: default;
}

.um-progress {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.4rem;
    min-width: min(320px, 40vw);
}

.um-progress-track {
    width: 100%;
    height: 3px;
    border-radius: 2px;
    background: rgba(255, 255, 255, 0.1);
    overflow: hidden;
}

.um-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #7fd4a8, #4ea87c);
    border-radius: 2px;
    transition: width 0.4s ease;
}

.um-progress-label {
    font-size: 0.72rem;
    letter-spacing: 0.08em;
    color: rgba(232, 238, 240, 0.55);
}

/* ---------------------------------------------------------- responsive */
@media (max-width: 991px) {
    .um-bar-roles {
        display: none;
    }

    .um-rail {
        position: absolute;
        top: 62px;
        bottom: 0;
        left: 0;
        z-index: 30;
        background: rgba(10, 14, 17, 0.96);
    }
}

@media (max-width: 767px) {

    /* one page at a time on small screens */
    .um-book {
        width: min(420px, 100%);
        aspect-ratio: 4 / 5;
    }

    .um-base--left,
    .um-spine {
        display: none;
    }

    .um-base--right {
        left: 0;
        width: 100%;
        border-radius: 7px;
    }

    .um-sheet {
        left: 0;
        width: 100%;
    }

    .um-book.is-closed,
    .um-book.is-ended {
        transform: rotateX(9deg);
    }
}

/* ------------------------------------------------------ reduced motion */
@media (prefers-reduced-motion: reduce) {

    .um-book,
    .um-sheet {
        transition-duration: 0.001ms;
    }

    .um-sheet.is-flipping .um-shade,
    .um-book {
        animation: none;
    }
}
</style>
