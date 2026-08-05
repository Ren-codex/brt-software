<template>
    <!-- Front cover -->
    <div v-if="page.kind === 'cover'" class="mp mp--cover">
        <div class="cover-emboss">
            <span class="cover-edition">{{ page.edition }}</span>
            <h1 class="cover-title">{{ page.title }}</h1>
            <div class="cover-rule"></div>
            <p class="cover-subtitle">{{ page.subtitle }}</p>
        </div>
        <div class="cover-seal">
            <i class="ri-book-open-line"></i>
        </div>
        <span class="cover-hint">{{ ui.openHint }}</span>
    </div>

    <!-- Back cover -->
    <div v-else-if="page.kind === 'back'" class="mp mp--cover mp--back">
        <div class="cover-emboss">
            <h1 class="cover-title">{{ page.title }}</h1>
            <div class="cover-rule"></div>
            <p class="cover-subtitle">{{ page.subtitle }}</p>
        </div>
    </div>

    <!-- Part divider -->
    <div v-else-if="page.kind === 'part'" class="mp mp--part">
        <span class="part-number">{{ page.number }}</span>
        <h2 class="part-title">{{ page.title }}</h2>
        <div class="part-rule"></div>
        <p class="part-blurb">{{ page.blurb }}</p>
        <span class="mp-folio">{{ pageNumber }}</span>
    </div>

    <!-- Table of contents -->
    <div v-else-if="page.kind === 'toc'" class="mp">
        <span class="mp-kicker">{{ page.kicker }}</span>
        <h2 class="mp-title">{{ page.title }}</h2>
        <div class="mp-body">
            <div v-for="group in toc" :key="group.part" class="toc-group">
                <h3 class="toc-part">{{ group.part }}</h3>
                <button v-for="entry in group.entries" :key="entry.index" type="button" class="toc-entry"
                    @click.stop="$emit('navigate', entry.index)">
                    <span class="toc-label">{{ entry.title }}</span>
                    <span class="toc-dots"></span>
                    <span class="toc-page">{{ entry.index }}</span>
                </button>
            </div>
        </div>
        <span class="mp-folio">{{ pageNumber }}</span>
    </div>

    <!-- Blank filler -->
    <div v-else-if="page.kind === 'blank'" class="mp mp--blank">
        <span class="blank-mark">{{ ui.blankPage }}</span>
    </div>

    <!-- Standard content page -->
    <div v-else class="mp">
        <span v-if="page.kicker" class="mp-kicker">{{ page.kicker }}</span>

        <div class="mp-heading">
            <h2 class="mp-title">{{ page.title }}</h2>
            <span v-if="isMyRole" class="mp-yours">{{ ui.yourRole }}</span>
        </div>

        <div class="mp-body">
            <template v-for="(block, i) in page.blocks" :key="i">
                <p v-if="block.type === 'lead'" class="b-lead">{{ block.text }}</p>

                <p v-else-if="block.type === 'p'" class="b-p">{{ block.text }}</p>

                <ul v-else-if="block.type === 'list'" class="b-list">
                    <li v-for="(item, j) in block.items" :key="j">{{ item }}</li>
                </ul>

                <ol v-else-if="block.type === 'steps'" class="b-steps">
                    <li v-for="(item, j) in block.items" :key="j">
                        <span class="b-step-num">{{ j + 1 }}</span>
                        <span class="b-step-body">
                            <strong>{{ item.title }}</strong>
                            <span>{{ item.text }}</span>
                        </span>
                    </li>
                </ol>

                <ManualFigure v-else-if="block.type === 'figure'" :art="block.art" :src="block.src"
                    :caption="block.caption" :callouts="block.callouts || []" />

                <div v-else-if="block.type === 'grid'" class="b-grid">
                    <div v-for="(item, j) in block.items" :key="j" class="b-grid-cell">
                        <span class="b-grid-label">{{ item.label }}</span>
                        <span class="b-grid-value">{{ item.value }}</span>
                    </div>
                </div>

                <div v-else-if="block.type === 'note'" class="b-note" :class="'b-note--' + (block.tone || 'info')">
                    <i :class="noteIcon(block.tone)"></i>
                    <span class="b-note-body">
                        <strong>{{ block.title }}</strong>
                        <span>{{ block.text }}</span>
                    </span>
                </div>

                <table v-else-if="block.type === 'table'" class="b-table">
                    <thead>
                        <tr>
                            <th v-for="(h, j) in block.head" :key="j">{{ h }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, j) in block.rows" :key="j">
                            <td v-for="(cell, k) in row" :key="k">{{ cell }}</td>
                        </tr>
                    </tbody>
                </table>
            </template>
        </div>

        <span class="mp-folio">{{ pageNumber }}</span>
    </div>
</template>

<script>
import ManualFigure from './ManualFigure.vue';
import { UI_STRINGS } from './manualContent.js';

export default {
    name: 'ManualPage',
    components: { ManualFigure },
    props: {
        page: { type: Object, required: true },
        pageNumber: { type: [Number, String], default: '' },
        toc: { type: Array, default: () => [] },
        userRoles: { type: Array, default: () => [] },
        roles: { type: Array, default: () => [] },
        lang: { type: String, default: 'en' },
    },
    emits: ['navigate'],
    computed: {
        ui() {
            return UI_STRINGS[this.lang];
        },
        isMyRole() {
            if (!this.page.roleKey) return false;
            const role = this.roles.find((r) => r.key === this.page.roleKey);
            return !!role && this.userRoles.includes(role.name);
        },
    },
    methods: {
        noteIcon(tone) {
            if (tone === 'warn') return 'ri-alert-line';
            if (tone === 'tip') return 'ri-lightbulb-flash-line';
            return 'ri-information-line';
        },
    },
};
</script>

<style scoped>
/* ---------------------------------------------------------- page shell */
.mp {
    position: relative;
    height: 100%;
    display: flex;
    flex-direction: column;
    padding: clamp(1.4rem, 2.6vh, 2.6rem) clamp(1.5rem, 2.4vw, 2.8rem) 2.4rem;
    color: #33302c;
    font-size: clamp(0.76rem, 0.92vw, 0.9rem);
    line-height: 1.62;
}

.mp-kicker {
    font-size: 0.66em;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: #a9743d;
    margin-bottom: 0.55rem;
}

.mp-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
}

.mp-title {
    font-family: Georgia, 'Times New Roman', serif;
    font-size: clamp(1.15rem, 1.85vw, 1.7rem);
    font-weight: 700;
    line-height: 1.18;
    color: #1f1c19;
    margin: 0 0 0.35rem;
}

.mp-yours {
    flex-shrink: 0;
    margin-top: 0.2rem;
    font-size: 0.6em;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding: 0.2em 0.7em;
    border-radius: 999px;
    background: #1f8a5b;
    color: #fff;
    white-space: nowrap;
}

.mp-body {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding-right: 0.4rem;
    padding-top: 0.5rem;
    border-top: 1px solid rgba(120, 96, 66, 0.22);
    scrollbar-width: thin;
    scrollbar-color: rgba(120, 96, 66, 0.35) transparent;
}

.mp-body::-webkit-scrollbar {
    width: 5px;
}

.mp-body::-webkit-scrollbar-thumb {
    background: rgba(120, 96, 66, 0.3);
    border-radius: 3px;
}

.mp-folio {
    position: absolute;
    bottom: 0.95rem;
    left: 0;
    right: 0;
    text-align: center;
    font-size: 0.7em;
    letter-spacing: 0.12em;
    color: #a29585;
}

/* --------------------------------------------------------- content blocks */
.b-lead {
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 1.1em;
    line-height: 1.55;
    color: #45403a;
    margin: 0 0 0.9rem;
}

.b-p {
    margin: 0 0 0.85rem;
}

.b-list {
    margin: 0 0 0.95rem;
    padding-left: 0;
    list-style: none;
}

.b-list li {
    position: relative;
    padding-left: 1.1rem;
    margin-bottom: 0.42rem;
}

.b-list li::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0.62em;
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #c08a4a;
}

.b-steps {
    margin: 0 0 0.95rem;
    padding: 0;
    list-style: none;
    counter-reset: step;
}

.b-steps li {
    display: flex;
    gap: 0.65rem;
    margin-bottom: 0.62rem;
}

.b-step-num {
    flex-shrink: 0;
    width: 1.5em;
    height: 1.5em;
    margin-top: 0.12em;
    border-radius: 50%;
    background: #2f2a24;
    color: #f6efe3;
    font-size: 0.72em;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
}

.b-step-body {
    display: block;
}

.b-step-body strong {
    display: block;
    color: #1f1c19;
    font-weight: 700;
}

.b-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
    margin: 0 0 0.95rem;
}

.b-grid-cell {
    background: rgba(160, 128, 84, 0.09);
    border-left: 2px solid #c08a4a;
    border-radius: 0 6px 6px 0;
    padding: 0.42rem 0.6rem;
}

.b-grid-label {
    display: block;
    font-size: 0.72em;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #8c7c66;
}

.b-grid-value {
    display: block;
    font-weight: 600;
    color: #2c2823;
}

.b-note {
    display: flex;
    gap: 0.6rem;
    padding: 0.62rem 0.75rem;
    border-radius: 8px;
    margin: 0 0 0.9rem;
    font-size: 0.94em;
}

.b-note i {
    font-size: 1.15em;
    line-height: 1.4;
}

.b-note-body strong {
    display: block;
    font-weight: 700;
}

.b-note--info {
    background: rgba(59, 130, 246, 0.1);
    color: #1e4b8f;
}

.b-note--warn {
    background: rgba(217, 119, 6, 0.13);
    color: #8a4b06;
}

.b-note--tip {
    background: rgba(16, 185, 129, 0.12);
    color: #0d6a4b;
}

.b-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0 0 0.9rem;
    font-size: 0.92em;
}

.b-table th {
    text-align: left;
    font-size: 0.82em;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #8c7c66;
    padding: 0.3rem 0.5rem;
    border-bottom: 1.5px solid rgba(120, 96, 66, 0.3);
}

.b-table td {
    padding: 0.32rem 0.5rem;
    border-bottom: 1px solid rgba(120, 96, 66, 0.14);
    vertical-align: top;
}

.b-table tbody tr:last-child td {
    border-bottom: none;
}

.b-table td:first-child {
    font-weight: 600;
    color: #2c2823;
    white-space: nowrap;
}

/* -------------------------------------------------------------- covers */
.mp--cover {
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 2rem;
    background:
        radial-gradient(circle at 30% 20%, rgba(255, 255, 255, 0.16), transparent 55%),
        linear-gradient(150deg, #2f6d4f 0%, #1d4835 45%, #12301f 100%);
    color: #f3ead9;
}

.mp--cover::after {
    content: '';
    position: absolute;
    inset: clamp(0.8rem, 2vw, 1.5rem);
    border: 1px solid rgba(226, 200, 143, 0.4);
    border-radius: 3px;
    pointer-events: none;
}

.cover-emboss {
    position: relative;
    z-index: 1;
    max-width: 85%;
}

.cover-edition {
    display: block;
    font-size: 0.68rem;
    letter-spacing: 0.28em;
    text-transform: uppercase;
    color: rgba(226, 200, 143, 0.85);
    margin-bottom: 1.4rem;
}

.cover-title {
    font-family: Georgia, 'Times New Roman', serif;
    font-size: clamp(1.9rem, 3.6vw, 3.1rem);
    font-weight: 700;
    line-height: 1.05;
    letter-spacing: 0.01em;
    color: #f6edda;
    margin: 0;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.35);
}

.cover-rule {
    width: 62px;
    height: 2px;
    margin: 1.1rem auto;
    background: linear-gradient(90deg, transparent, #e2c88f, transparent);
}

.cover-subtitle {
    font-size: 0.86rem;
    line-height: 1.6;
    color: rgba(243, 234, 217, 0.78);
    margin: 0;
}

.cover-seal {
    position: absolute;
    bottom: clamp(2.4rem, 6vh, 4rem);
    width: 52px;
    height: 52px;
    border-radius: 50%;
    border: 1px solid rgba(226, 200, 143, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    color: #e2c88f;
}

.cover-hint {
    position: absolute;
    bottom: 1.1rem;
    font-size: 0.66rem;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: rgba(226, 200, 143, 0.55);
}

.mp--back {
    background:
        radial-gradient(circle at 70% 80%, rgba(255, 255, 255, 0.1), transparent 55%),
        linear-gradient(150deg, #12301f 0%, #1d4835 55%, #2f6d4f 100%);
}

/* --------------------------------------------------------- part divider */
.mp--part {
    align-items: center;
    justify-content: center;
    text-align: center;
    background: linear-gradient(165deg, #f6efe1 0%, #ead9bd 100%);
}

.part-number {
    font-size: 0.72rem;
    letter-spacing: 0.3em;
    text-transform: uppercase;
    color: #a9743d;
}

.part-title {
    font-family: Georgia, 'Times New Roman', serif;
    font-size: clamp(1.6rem, 2.8vw, 2.4rem);
    line-height: 1.1;
    color: #2b241a;
    margin: 0.7rem 0 0;
}

.part-rule {
    width: 54px;
    height: 2px;
    margin: 1rem 0;
    background: linear-gradient(90deg, transparent, #a9743d, transparent);
}

.part-blurb {
    max-width: 78%;
    font-size: 0.86em;
    color: #6b5c47;
    margin: 0;
}

/* ------------------------------------------------------------- contents */
.toc-group {
    margin-bottom: 1.1rem;
}

.toc-part {
    font-size: 0.68em;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: #a9743d;
    margin: 0 0 0.4rem;
}

.toc-entry {
    display: flex;
    align-items: baseline;
    gap: 0.4rem;
    width: 100%;
    padding: 0.2rem 0;
    border: none;
    background: none;
    text-align: left;
    color: #453f38;
    cursor: pointer;
    font: inherit;
}

.toc-entry:hover .toc-label {
    color: #1f8a5b;
}

.toc-dots {
    flex: 1;
    border-bottom: 1px dotted rgba(120, 96, 66, 0.4);
    transform: translateY(-0.22em);
}

.toc-page {
    font-variant-numeric: tabular-nums;
    color: #8c7c66;
    font-size: 0.9em;
}

/* ---------------------------------------------------------------- blank */
.mp--blank {
    align-items: center;
    justify-content: center;
}

.blank-mark {
    font-size: 0.72em;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: rgba(140, 124, 102, 0.5);
}
</style>
