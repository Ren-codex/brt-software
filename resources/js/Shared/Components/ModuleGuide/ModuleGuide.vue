<template>
    <Teleport to="body">
        <div v-if="showModal" class="modal-overlay active gb-overlay" :class="{ 'is-full': fullscreen }"
            role="dialog" aria-modal="true" aria-label="Guide book" @click.self="hide">
            <div class="gb-book">
                <!-- ───────────── Contents: the chapters down the left ───────────── -->
                <aside class="gb-rail" :class="{ 'is-open': railOpen }">
                    <div class="gb-rail-head">
                        <i class="ri-book-open-line"></i>
                        <div>
                            <strong>{{ ui.bookTitle }}</strong>
                            <span>{{ ui.bookSubtitle }}</span>
                        </div>
                    </div>

                    <nav class="gb-chapters">
                        <div v-for="(chapter, index) in chapters" :key="chapter.key" class="gb-chapter"
                            :class="{ 'is-current': chapter.key === current.chapter.key }">
                            <button type="button" class="gb-chapter-btn" @click="goToChapter(chapter.key)">
                                <span class="gb-chapter-no">{{ index + 1 }}</span>
                                <i :class="chapter.icon"></i>
                                <span class="gb-chapter-title">{{ chapter.title }}</span>
                                <i v-if="!chapter.mine" class="ri-lock-line gb-chapter-lock" :title="ui.notYours"></i>
                            </button>
                            <!-- Only the open chapter lists its pages, to keep the rail short. -->
                            <div v-if="chapter.key === current.chapter.key" class="gb-chapter-pages">
                                <button v-for="page in chapter.pages" :key="page.index" type="button"
                                    class="gb-page-link" :class="{ 'is-active': page.index === pageIndex }"
                                    @click="goTo(page.index)">
                                    {{ page.title }}
                                </button>
                            </div>
                        </div>
                    </nav>

                    <!-- The book opens on the reader's own chapters; this shows the rest. -->
                    <div v-if="hiddenCount > 0 || showAll" class="gb-rail-foot">
                        <span>{{ showAll ? ui.allChapters : ui.myChapters }}</span>
                        <button type="button" @click="toggleAll">
                            <i :class="showAll ? 'ri-user-star-line' : 'ri-book-2-line'"></i>
                            {{ showAll ? ui.showMine : ui.showAll }}
                        </button>
                    </div>
                </aside>

                <!-- ───────────── The page ───────────── -->
                <section class="gb-main">
                    <header class="gb-topbar">
                        <button type="button" class="gb-icon-btn gb-rail-toggle" :title="ui.contents" @click="railOpen = !railOpen">
                            <i class="ri-menu-line"></i>
                        </button>
                        <span class="gb-crumb">
                            <i :class="current.chapter.icon"></i>
                            {{ ui.chapter }} {{ current.chapterNo }} · {{ current.chapter.title }}
                        </span>
                        <div class="gb-topbar-actions">
                            <!-- Shows the language you would switch TO. -->
                            <button type="button" class="gb-lang-btn" @click="toggleLang">
                                <i class="ri-translate-2"></i> <span>{{ ui.switchTo }}</span>
                            </button>
                            <button type="button" class="gb-icon-btn" :title="fullscreen ? ui.exitFullScreen : ui.fullScreen"
                                @click="fullscreen = !fullscreen">
                                <i :class="fullscreen ? 'ri-fullscreen-exit-line' : 'ri-fullscreen-line'"></i>
                            </button>
                            <button type="button" class="gb-icon-btn gb-close" :title="ui.close" @click="hide">
                                <i class="ri-close-line"></i>
                            </button>
                        </div>
                    </header>

                    <div class="gb-paper" ref="paper">
                        <!-- Re-keyed per page so each one slides in like a turned leaf. -->
                        <article :key="pageIndex" class="gb-page" :class="'turn-' + turn">
                            <div class="gb-page-kicker">
                                <span class="gb-page-icon"><i :class="current.page.icon"></i></span>
                                {{ current.chapter.title }}
                            </div>
                            <h2 class="gb-page-title">{{ current.page.title }}</h2>

                            <template v-for="(block, i) in current.page.blocks" :key="i">
                                <p v-if="block.type === 'lead'" class="gb-lead">{{ block.text }}</p>

                                <p v-else-if="block.type === 'text'" class="gb-text">{{ block.text }}</p>

                                <h3 v-else-if="block.type === 'heading'" class="gb-subhead">{{ block.text }}</h3>

                                <!-- The idea in three lines. -->
                                <div v-else-if="block.type === 'inShort'" class="gb-inshort">
                                    <div class="gb-inshort-title"><i class="ri-timer-flash-line"></i> {{ ui.inOneMinute }}</div>
                                    <ul>
                                        <li v-for="line in block.items" :key="line"><i class="ri-check-line"></i><span>{{ line }}</span></li>
                                    </ul>
                                </div>

                                <!-- What this reader, with their role, can do here. -->
                                <div v-else-if="block.type === 'yourPart'" class="gb-yours" :class="{ 'is-none': !block.areas.length }">
                                    <div class="gb-yours-title"><i class="ri-user-star-line"></i> {{ ui.yourPart }}</div>
                                    <p v-if="!block.areas.length" class="gb-yours-none">{{ ui.yourPartNone }}</p>
                                    <template v-else>
                                        <p class="gb-yours-lead">{{ ui.yourPartLead }}</p>
                                        <div v-for="area in block.areas" :key="area.name" class="gb-yours-area">
                                            <div v-if="block.areas.length > 1 || !area.whole" class="gb-yours-area-name">
                                                {{ area.whole ? ui.wholeModule : area.name }}
                                            </div>
                                            <ul>
                                                <li v-for="level in area.levels" :key="level">
                                                    <span class="gb-level" :class="'level-' + level">{{ levelInfo(level).label }}</span>
                                                    <span>{{ levelInfo(level).hint }}</span>
                                                </li>
                                            </ul>
                                        </div>
                                    </template>
                                </div>

                                <!-- One real case, start to finish. -->
                                <div v-else-if="block.type === 'example'" class="gb-example">
                                    <div class="gb-example-title"><i class="ri-chat-quote-line"></i> {{ block.title }}</div>
                                    <ol>
                                        <li v-for="(line, n) in block.items" :key="line">
                                            <span class="gb-example-no">{{ n + 1 }}</span>
                                            <span>{{ line }}</span>
                                        </li>
                                    </ol>
                                </div>

                                <ol v-else-if="block.type === 'steps'" class="gb-steps">
                                    <li v-for="(line, n) in block.items" :key="line">
                                        <span class="gb-step-no">{{ n + 1 }}</span>
                                        <span class="gb-step-text">{{ line }}</span>
                                    </li>
                                </ol>

                                <ol v-else-if="block.type === 'stages'" class="gb-stages">
                                    <li v-for="(stage, n) in block.items" :key="stage.name" class="gb-stage"
                                        :class="{ 'is-here': stage.slug && stage.slug === stageSlug }">
                                        <span class="gb-stage-dot">{{ n + 1 }}</span>
                                        <div class="gb-stage-body">
                                            <div class="gb-stage-name">
                                                {{ stage.name }}
                                                <span v-if="stage.slug && stage.slug === stageSlug" class="gb-here-tag">
                                                    <i class="ri-map-pin-2-fill"></i> {{ ui.youAreHere }}
                                                </span>
                                            </div>
                                            <p>{{ stage.what }}</p>
                                            <p v-if="stage.next && stage.slug === stageSlug" class="gb-stage-next">
                                                <strong>{{ ui.nextStep }}</strong> {{ stage.next }}
                                            </p>
                                        </div>
                                    </li>
                                </ol>

                                <div v-else-if="block.type === 'tip'" class="gb-tip">
                                    <i class="ri-lightbulb-flash-line"></i>
                                    <span>{{ block.text }}</span>
                                </div>

                                <dl v-else-if="block.type === 'words'" class="gb-words">
                                    <div v-for="[term, meaning] in block.items" :key="term" class="gb-word">
                                        <dt>{{ term }}</dt>
                                        <dd>{{ meaning }}</dd>
                                    </div>
                                </dl>

                                <div v-else-if="block.type === 'formula'" class="gb-formula">
                                    <div v-for="[name, how] in block.rows" :key="name">
                                        <span>{{ name }}</span><span>{{ how }}</span>
                                    </div>
                                </div>

                                <div v-else-if="block.type === 'table'" class="gb-table-wrap">
                                    <table class="gb-table">
                                        <thead><tr><th v-for="cell in block.head" :key="cell">{{ cell }}</th></tr></thead>
                                        <tbody>
                                            <tr v-for="(row, r) in block.rows" :key="r"><td v-for="cell in row" :key="cell">{{ cell }}</td></tr>
                                        </tbody>
                                    </table>
                                </div>

                                <ul v-else-if="block.type === 'icons'" class="gb-icons">
                                    <li v-for="item in block.items" :key="item.title">
                                        <span class="gb-icon-chip" :class="'tone-' + item.tone"><i :class="item.icon"></i></span>
                                        <div><strong>{{ item.title }}</strong><p>{{ item.text }}</p></div>
                                    </li>
                                </ul>

                                <!-- Who does what: read live from the role permissions. -->
                                <div v-else-if="block.type === 'roles'" class="gb-roles">
                                    <div v-if="rolesLoading" class="gb-muted">{{ ui.loadingRoles }}</div>
                                    <div v-else-if="rolesError" class="gb-muted">{{ ui.rolesError }}</div>
                                    <template v-else>
                                        <div class="gb-legend">
                                            <span v-for="(info, level) in levels" :key="level" class="gb-legend-item">
                                                <span class="gb-level" :class="'level-' + level">{{ info.label }}</span>
                                                {{ info.hint }}
                                            </span>
                                        </div>
                                        <div v-for="area in areas" :key="area.key || area.name" class="gb-area">
                                            <div class="gb-area-name">{{ area.name }}</div>
                                            <div v-for="role in mineFirst(area.roles)" :key="role.name" class="gb-role"
                                                :class="{ 'is-mine': myRoles.includes(role.name) }">
                                                <span class="gb-role-name">
                                                    <i class="ri-user-3-line"></i> {{ role.name }}
                                                    <span v-if="myRoles.includes(role.name)" class="gb-you">{{ ui.thatsYou }}</span>
                                                </span>
                                                <span class="gb-role-levels">
                                                    <span v-for="level in role.levels" :key="level" class="gb-level" :class="'level-' + level">
                                                        {{ levelInfo(level).label }}
                                                    </span>
                                                </span>
                                            </div>
                                            <div v-if="!area.roles.length" class="gb-muted gb-area-empty">{{ ui.noRoles }}</div>
                                        </div>
                                        <div class="gb-tip">
                                            <i class="ri-shield-star-line"></i>
                                            <span>{{ ui.superAdmin[0] }}<strong>{{ ui.superAdmin[1] }}</strong>{{ ui.superAdmin[2] }}</span>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </article>
                    </div>

                    <!-- ───────────── Turning pages ───────────── -->
                    <footer class="gb-footer">
                        <button type="button" class="gb-nav-btn" :disabled="pageIndex === 0" @click="previous">
                            <i class="ri-arrow-left-line"></i> <span>{{ ui.back }}</span>
                        </button>
                        <div class="gb-progress">
                            <div class="gb-progress-text">
                                {{ ui.pageOf(current.pageNo, current.chapter.pages.length) }}
                            </div>
                            <div class="gb-dots">
                                <button v-for="page in current.chapter.pages" :key="page.index" type="button"
                                    class="gb-dot" :class="{ 'is-active': page.index === pageIndex, 'is-read': page.index < pageIndex }"
                                    :title="page.title" @click="goTo(page.index)"></button>
                            </div>
                        </div>
                        <button type="button" class="gb-nav-btn gb-nav-next" :disabled="pageIndex === pages.length - 1" @click="next">
                            <span>{{ nextLabel }}</span> <i class="ri-arrow-right-line"></i>
                        </button>
                    </footer>
                </section>
            </div>
        </div>
    </Teleport>
</template>

<script>
import { guidesFor, levelsFor, uiFor, guideKeyForPath, pagesFor } from './moduleGuides.js';

/**
 * The guide book: every module's guide as a chapter, read a page at a time.
 *
 * Opened from the book icon in the top bar, on the chapter for whichever module
 * the user is in, or from anywhere by firing `guide:open` (see openGuide() in
 * moduleGuides.js) — the payroll screens do that, passing the status of the
 * payroll that is open so its stage is marked "You are here".
 *
 * Pages are one flat list across all chapters, so Next at the end of a chapter
 * simply carries on into the following one, the way a book does.
 */
export default {
    data() {
        // The reader's language is remembered between visits.
        let lang = 'en';
        try {
            lang = localStorage.getItem('guide_lang') === 'tl' ? 'tl' : 'en';
        } catch {
            // Storage unavailable (private mode): English, and nothing is saved.
        }

        return {
            showModal: false,
            fullscreen: false,
            railOpen: false,
            lang,
            // Off: only the chapters for the reader's own role. On: the whole book.
            showAll: false,
            pageIndex: 0,
            // Which way the last turn went, for the slide direction.
            turn: 'forward',
            stageSlug: null,
            rolesByModule: {},
            rolesLoading: false,
            rolesError: false,
        };
    },
    computed: {
        /**
         * The whole book in the chosen language. Both languages produce the same
         * chapters and pages in the same order, so pageIndex means the same page
         * in either and switching language keeps the reader's place.
         */
        book() {
            const guides = guidesFor(this.lang).filter(guide => this.showAll || this.isMine(guide));
            // "Getting around" first: it is the chapter a newcomer should meet first.
            const ordered = [
                ...guides.filter(guide => guide.key === 'dashboard'),
                ...guides.filter(guide => guide.key !== 'dashboard'),
            ];

            const pages = [];
            const chapters = ordered.map(guide => {
                const chapterPages = pagesFor(guide, this.lang).map((page, pageNo) => {
                    const blocks = [...page.blocks];

                    // The opening page of a module's chapter says what this
                    // reader can do there, straight after the summary.
                    if (pageNo === 0 && guide.permissionModule) {
                        blocks.push({ type: 'yourPart', areas: this.myAreas(guide.permissionModule) });
                    }

                    const entry = { ...page, blocks, index: pages.length, chapterKey: guide.key };
                    pages.push(entry);
                    return entry;
                });

                return { ...guide, mine: this.isMine(guide), pages: chapterPages };
            });

            return { chapters, pages };
        },
        chapters() {
            return this.book.chapters;
        },
        pages() {
            return this.book.pages;
        },
        ui() {
            return uiFor(this.lang);
        },
        /** How many chapters are outside this reader's role. */
        hiddenCount() {
            return guidesFor(this.lang).filter(guide => !this.isMine(guide)).length;
        },
        levels() {
            return levelsFor(this.lang);
        },
        current() {
            const page = this.pages[this.pageIndex] || this.pages[0];
            const chapterIndex = this.chapters.findIndex(chapter => chapter.key === page.chapterKey);
            const chapter = this.chapters[chapterIndex];

            return {
                page,
                chapter,
                chapterNo: chapterIndex + 1,
                pageNo: chapter.pages.findIndex(entry => entry.index === page.index) + 1,
            };
        },
        areas() {
            return this.rolesByModule[this.current.chapter.permissionModule] || [];
        },
        myRoles() {
            const roles = this.$page?.props?.roles;
            return roles ? Object.values(roles) : [];
        },
        /** Says where Next leads when it is about to leave the chapter. */
        nextLabel() {
            const following = this.pages[this.pageIndex + 1];
            if (!following) return this.ui.theEnd;
            if (following.chapterKey !== this.current.chapter.key) {
                return this.ui.nextChapter;
            }
            return this.ui.next;
        },
    },
    watch: {
        pageIndex() {
            this.loadRoles();
            this.$nextTick(() => { if (this.$refs.paper) this.$refs.paper.scrollTop = 0; });
        },
    },
    mounted() {
        this.onOpenRequest = event => this.show(event.detail?.key, event.detail?.stage);
        this.onKey = event => {
            if (!this.showModal) return;
            if (event.key === 'ArrowRight') this.next();
            else if (event.key === 'ArrowLeft') this.previous();
            else if (event.key === 'Escape') this.hide();
        };
        window.addEventListener('guide:open', this.onOpenRequest);
        window.addEventListener('keydown', this.onKey);
    },
    beforeUnmount() {
        window.removeEventListener('guide:open', this.onOpenRequest);
        window.removeEventListener('keydown', this.onKey);
    },
    methods: {
        /**
         * Opens on a chapter — the given one, or the one for the current page.
         * With a stage it opens straight on that chapter's stages page.
         */
        show(key = null, stage = null) {
            const chapterKey = key || guideKeyForPath(this.$page?.url);

            // Asked for a chapter this reader does not normally see: open the
            // whole book rather than quietly landing them somewhere else.
            if (!this.chapters.some(item => item.key === chapterKey)) {
                this.showAll = true;
            }

            const chapter = this.chapters.find(item => item.key === chapterKey) || this.chapters[0];

            this.stageSlug = stage || null;

            const stagesPage = stage
                ? chapter.pages.find(page => page.blocks.some(block => block.type === 'stages'))
                : null;

            this.turn = 'forward';
            this.pageIndex = (stagesPage || chapter.pages[0]).index;
            this.showModal = true;
            this.railOpen = false;
            this.loadRoles();
        },
        hide() {
            this.showModal = false;
        },
        goTo(index) {
            if (index === this.pageIndex) return;
            this.turn = index > this.pageIndex ? 'forward' : 'back';
            this.pageIndex = index;
            this.railOpen = false;
        },
        goToChapter(key) {
            const chapter = this.chapters.find(item => item.key === key);
            if (chapter) this.goTo(chapter.pages[0].index);
        },
        next() {
            if (this.pageIndex < this.pages.length - 1) this.goTo(this.pageIndex + 1);
        },
        previous() {
            if (this.pageIndex > 0) this.goTo(this.pageIndex - 1);
        },
        /** Fetches a chapter's roles the first time a page needs them. */
        loadRoles() {
            const module = this.current.chapter.permissionModule;
            const needsRoles = this.current.page.blocks.some(block => block.type === 'roles');
            this.rolesError = false;

            if (!module || !needsRoles || this.rolesByModule[module]) {
                return;
            }

            this.rolesLoading = true;
            axios.get('/guides/roles', { params: { module } })
                .then(res => { this.rolesByModule[module] = res.data.areas || []; })
                .catch(() => { this.rolesError = true; })
                .finally(() => { this.rolesLoading = false; });
        },
        levelInfo(level) {
            return this.levels[level] || { label: level, hint: '' };
        },
        /**
         * Is this chapter part of the reader's job?
         *
         * A module's chapter is, when their roles hold any access to that
         * module. Approvals is, when they can approve something. The general
         * chapter always is. This uses the same permission map the menu uses, so
         * the book and the menu agree about what belongs to a person.
         */
        isMine(guide) {
            if (guide.key === 'approvals') {
                return this.canApproveAnything();
            }
            if (!guide.permissionModule) {
                return true;
            }

            return this.myAreas(guide.permissionModule).length > 0;
        },
        /**
         * What the reader can do in a module, as a list of areas with levels.
         * Full control is shown on its own, since it already includes the rest.
         */
        myAreas(moduleKey) {
            const order = ['admin', 'approver', 'releaser', 'encoder', 'void', 'view'];
            const grants = this.$page?.props?.permissions?.[moduleKey] || {};

            const tidy = levels => {
                const unique = [...new Set(levels || [])];
                if (unique.includes('admin')) return ['admin'];
                return order.filter(level => unique.includes(level));
            };

            return Object.entries(grants)
                .map(([key, levels]) => ({
                    whole: key === '_module',
                    // "payroll_processing" → "Payroll processing"
                    name: key.replace(/_/g, ' ').replace(/^./, letter => letter.toUpperCase()),
                    levels: tidy(levels),
                }))
                .filter(area => area.levels.length)
                // The module-wide line first, then the areas alphabetically.
                .sort((a, b) => (b.whole - a.whole) || a.name.localeCompare(b.name));
        },
        /** The reader's own roles at the top of each list. */
        mineFirst(roles) {
            return [...roles].sort((a, b) => this.myRoles.includes(b.name) - this.myRoles.includes(a.name));
        },
        /** Switch between "my chapters" and the whole book, keeping the place. */
        toggleAll() {
            const key = this.current.chapter.key;
            const pageNo = this.current.pageNo;

            this.showAll = !this.showAll;

            const chapter = this.chapters.find(item => item.key === key) || this.chapters[0];
            this.pageIndex = chapter.pages[Math.min(pageNo, chapter.pages.length) - 1].index;
        },
        toggleLang() {
            this.lang = this.lang === 'tl' ? 'en' : 'tl';
            try {
                localStorage.setItem('guide_lang', this.lang);
            } catch {
                // Not saved; the choice still holds until the page is reloaded.
            }
        },
    },
};
</script>

<style scoped>
/* A book: a dark cover-coloured contents rail, and warm paper to read on. */
.gb-overlay {
    padding: 1.25rem;
    background: rgba(var(--brt-c-0d1c19-rgb), 0.72);
}
.gb-overlay.is-full { padding: 0; }

.gb-book {
    display: grid;
    grid-template-columns: 270px minmax(0, 1fr);
    width: 100%;
    max-width: 1120px;
    height: min(760px, calc(100vh - 2.5rem));
    border-radius: 18px;
    overflow: hidden;
    background: #fffdf7;
    box-shadow: 0 30px 70px -15px rgba(0, 0, 0, 0.6);
}
.is-full .gb-book {
    max-width: none;
    height: 100vh;
    border-radius: 0;
}

/* ── Contents rail ── */
.gb-rail {
    display: flex;
    flex-direction: column;
    min-height: 0;
    background: linear-gradient(180deg, var(--brt-c-1b3b35) 0%, var(--brt-c-14302b) 100%);
    color: var(--brt-c-d7e6e1);
}
.gb-rail-head {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding: 1.1rem 1.1rem 0.9rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}
.gb-rail-head > i { font-size: 1.7rem; color: #f3c969; }
.gb-rail-head strong {
    display: block;
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 1.15rem;
    color: #fff;
}
.gb-rail-head span { font-size: 0.72rem; color: var(--brt-c-9fbdb4); }

.gb-chapters {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    padding: 0.6rem 0.6rem 1rem;
}
.gb-chapter-btn {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    width: 100%;
    padding: 0.55rem 0.6rem;
    border: none;
    border-radius: 9px;
    background: transparent;
    color: var(--brt-c-d7e6e1);
    font-size: 0.86rem;
    font-weight: 600;
    text-align: left;
    cursor: pointer;
    transition: background-color 0.15s ease;
}
.gb-chapter-btn:hover { background: rgba(255, 255, 255, 0.07); }
.gb-chapter.is-current .gb-chapter-btn {
    background: rgba(243, 201, 105, 0.16);
    color: #fff;
}
.gb-chapter-no {
    flex-shrink: 0;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.12);
    font-size: 0.66rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
}
.gb-chapter.is-current .gb-chapter-no { background: #f3c969; color: var(--brt-c-14302b); }
.gb-chapter-btn > i { font-size: 1rem; opacity: 0.85; }
.gb-chapter-title { flex: 1 1 auto; min-width: 0; }
.gb-chapter-btn > .gb-chapter-lock { font-size: 0.85rem; opacity: 0.5; }

.gb-rail-foot {
    flex-shrink: 0;
    padding: 0.7rem 0.9rem 0.9rem;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    font-size: 0.72rem;
    color: var(--brt-c-9fbdb4);
}
.gb-rail-foot span { display: block; margin-bottom: 0.4rem; }
.gb-rail-foot button {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    width: 100%;
    justify-content: center;
    padding: 0.45rem 0.6rem;
    border: 1px solid rgba(255, 255, 255, 0.22);
    border-radius: 8px;
    background: transparent;
    color: var(--brt-c-e6f0ed);
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
}
.gb-rail-foot button:hover { background: rgba(255, 255, 255, 0.08); }

.gb-chapter-pages {
    margin: 0.2rem 0 0.5rem 1.55rem;
    padding-left: 0.6rem;
    border-left: 1px solid rgba(255, 255, 255, 0.14);
}
.gb-page-link {
    display: block;
    width: 100%;
    padding: 0.3rem 0.5rem;
    border: none;
    border-radius: 6px;
    background: transparent;
    color: var(--brt-c-a9c4bc);
    font-size: 0.78rem;
    text-align: left;
    cursor: pointer;
}
.gb-page-link:hover { color: #fff; }
.gb-page-link.is-active {
    color: #f3c969;
    font-weight: 700;
    background: rgba(255, 255, 255, 0.06);
}

/* ── Main column ── */
.gb-main {
    display: flex;
    flex-direction: column;
    min-width: 0;
    min-height: 0;
}
.gb-topbar {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.6rem 0.9rem 0.6rem 1.4rem;
    border-bottom: 1px solid #efe8d6;
    background: #fffaf0;
}
.gb-crumb {
    flex: 1 1 auto;
    min-width: 0;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: #8a7a52;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.gb-topbar-actions { display: flex; gap: 0.35rem; }
.gb-icon-btn {
    width: 34px;
    height: 34px;
    border: 1px solid #e3d9bf;
    border-radius: 10px;
    background: #fff;
    color: #4a4230;
    font-size: 1.05rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s ease;
}
.gb-icon-btn:hover { background: var(--brt-primary); border-color: var(--brt-primary); color: #fff; }
.gb-lang-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    height: 34px;
    padding: 0 0.75rem;
    border: 1px solid #e3d9bf;
    border-radius: 10px;
    background: #fff;
    color: #4a4230;
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
}
.gb-lang-btn i { font-size: 1rem; color: var(--brt-primary); }
.gb-lang-btn:hover { border-color: var(--brt-primary); color: var(--brt-primary-dark); }
.gb-close:hover { background: #dc2626; border-color: #dc2626; }
.gb-rail-toggle { display: none; }

.gb-paper {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    background: #fffdf7;
}
.gb-page {
    max-width: 760px;
    margin: 0 auto;
    padding: 1.8rem 2.2rem 2.2rem;
}
.gb-page.turn-forward { animation: gb-in-right 0.28s ease; }
.gb-page.turn-back { animation: gb-in-left 0.28s ease; }
@keyframes gb-in-right { from { opacity: 0; transform: translateX(28px); } to { opacity: 1; transform: none; } }
@keyframes gb-in-left { from { opacity: 0; transform: translateX(-28px); } to { opacity: 1; transform: none; } }

.gb-page-kicker {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--brt-primary);
}
.gb-page-icon {
    width: 38px;
    height: 38px;
    border-radius: 11px;
    background: rgba(var(--brt-primary-rgb), 0.12);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}
.gb-page-title {
    margin: 0.55rem 0 1rem;
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 1.9rem;
    line-height: 1.2;
    color: var(--brt-c-1d2b28);
}

/* Reading text is deliberately large: this is for someone new. */
.gb-lead {
    margin: 0 0 1.1rem;
    font-size: 1.08rem;
    line-height: 1.6;
    color: var(--brt-c-2b3a36);
}
.gb-text {
    margin: 0 0 1rem;
    font-size: 0.98rem;
    line-height: 1.65;
    color: var(--brt-c-33423e);
}
.gb-subhead {
    margin: 1.4rem 0 0.5rem;
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 1.2rem;
    color: var(--brt-c-1d2b28);
}
.gb-muted { font-size: 0.88rem; color: #9a9483; }

.gb-inshort {
    margin-bottom: 1.1rem;
    padding: 1rem 1.1rem;
    border-radius: 14px;
    background: var(--brt-c-eef7f3);
    border: 1px solid var(--brt-c-cfe5dc);
}
.gb-inshort-title {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-bottom: 0.55rem;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--brt-primary-dark);
}
.gb-inshort ul { list-style: none; margin: 0; padding: 0; }
.gb-inshort li {
    display: flex;
    gap: 0.55rem;
    margin-bottom: 0.45rem;
    font-size: 1rem;
    line-height: 1.5;
    color: var(--brt-c-1d2b28);
}
.gb-inshort li:last-child { margin-bottom: 0; }
.gb-inshort li i { color: var(--brt-primary); font-size: 1.15rem; flex-shrink: 0; }

.gb-yours {
    margin-bottom: 1.1rem;
    padding: 1rem 1.1rem;
    border-radius: 14px;
    background: #f3f0ff;
    border: 1px solid #ddd6fe;
}
.gb-yours.is-none { background: #f6f5f1; border-color: #e6e1d2; }
.gb-yours-title {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-bottom: 0.5rem;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: #5b21b6;
}
.gb-yours.is-none .gb-yours-title { color: #6b6453; }
.gb-yours-lead,
.gb-yours-none {
    margin: 0 0 0.6rem;
    font-size: 0.98rem;
    line-height: 1.5;
    color: var(--brt-c-2b3a36);
}
.gb-yours-none { margin-bottom: 0; color: #5c5646; }
.gb-yours-area { margin-bottom: 0.55rem; }
.gb-yours-area:last-child { margin-bottom: 0; }
.gb-yours-area-name { font-size: 0.86rem; font-weight: 700; color: #3b2a78; margin-bottom: 0.25rem; }
.gb-yours ul { list-style: none; margin: 0; padding: 0; }
.gb-yours li {
    display: flex;
    align-items: baseline;
    gap: 0.55rem;
    margin-bottom: 0.3rem;
    font-size: 0.94rem;
    color: var(--brt-c-33423e);
}

.gb-example {
    padding: 1.1rem 1.2rem;
    border-radius: 14px;
    background: #fff8e6;
    border: 1px solid #f1dfae;
}
.gb-example-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.85rem;
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 1.15rem;
    font-weight: 700;
    color: #5a4410;
}
.gb-example-title i { color: #d19a1c; font-size: 1.3rem; }
.gb-example ol { list-style: none; margin: 0; padding: 0; }
.gb-example li {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 0.7rem;
    font-size: 0.98rem;
    line-height: 1.55;
    color: #3d3320;
}
.gb-example li:last-child { margin-bottom: 0; }
.gb-example-no {
    flex-shrink: 0;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: #f3c969;
    color: #4a3806;
    font-size: 0.8rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
}

.gb-steps { list-style: none; margin: 0; padding: 0; }
.gb-steps li {
    display: flex;
    align-items: flex-start;
    gap: 0.9rem;
    margin-bottom: 0.6rem;
    padding: 0.85rem 1rem;
    border: 1px solid #ebe5d3;
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 1px 2px rgba(60, 50, 20, 0.04);
}
.gb-step-no {
    flex-shrink: 0;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: var(--brt-primary);
    color: #fff;
    font-size: 1rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
}
.gb-step-text {
    padding-top: 0.3rem;
    font-size: 0.98rem;
    line-height: 1.55;
    color: var(--brt-c-2b3a36);
}

.gb-stages { list-style: none; margin: 0 0 1rem; padding: 0; }
.gb-stage {
    position: relative;
    display: flex;
    gap: 0.9rem;
    padding-bottom: 1rem;
}
.gb-stage:not(:last-child)::before {
    content: "";
    position: absolute;
    left: 16px;
    top: 34px;
    bottom: 0;
    width: 2px;
    background: #e3ddc9;
}
.gb-stage:last-child { padding-bottom: 0; }
.gb-stage-dot {
    position: relative;
    z-index: 1;
    flex-shrink: 0;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #fff;
    border: 2px solid var(--brt-tint-1);
    color: var(--brt-primary-dark);
    font-weight: 700;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    justify-content: center;
}
.gb-stage.is-here .gb-stage-dot {
    background: var(--brt-primary);
    border-color: var(--brt-primary);
    color: #fff;
    box-shadow: 0 0 0 5px rgba(var(--brt-primary-rgb), 0.18);
}
.gb-stage-body { flex: 1 1 auto; min-width: 0; padding-top: 0.25rem; }
.gb-stage-name {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    font-size: 1.02rem;
    font-weight: 700;
    color: var(--brt-c-1d2b28);
}
.gb-here-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    padding: 0.1rem 0.55rem;
    border-radius: 999px;
    background: var(--brt-primary);
    color: #fff;
    font-size: 0.7rem;
    font-weight: 700;
}
.gb-stage-body p {
    margin: 0.15rem 0 0;
    font-size: 0.94rem;
    line-height: 1.5;
    color: var(--brt-c-4a5753);
}
.gb-stage-body .gb-stage-next {
    margin-top: 0.4rem;
    padding: 0.45rem 0.65rem;
    border-radius: 8px;
    background: var(--brt-c-eef7f3);
    color: var(--brt-c-1d2b28);
}

.gb-tip {
    display: flex;
    gap: 0.65rem;
    margin-bottom: 0.6rem;
    padding: 0.8rem 0.95rem;
    border-radius: 12px;
    background: #fff8e6;
    border: 1px solid #f1dfae;
    font-size: 0.95rem;
    line-height: 1.55;
    color: #3d3320;
}
.gb-tip > i { flex-shrink: 0; font-size: 1.2rem; color: #d19a1c; }

.gb-words { margin: 0; }
.gb-word {
    display: grid;
    grid-template-columns: minmax(120px, 190px) 1fr;
    gap: 0.9rem;
    padding: 0.7rem 0;
    border-bottom: 1px dashed #e6dfca;
}
.gb-word:last-child { border-bottom: none; }
.gb-word dt { font-weight: 700; font-size: 0.98rem; color: var(--brt-c-1d2b28); }
.gb-word dd { margin: 0; font-size: 0.96rem; line-height: 1.5; color: var(--brt-c-4a5753); }

.gb-formula {
    margin-bottom: 1rem;
    border: 1px solid #e6dfca;
    border-radius: 12px;
    overflow: hidden;
    font-size: 0.98rem;
    background: #fff;
}
.gb-formula div {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.7rem 1rem;
    border-bottom: 1px solid #f0ead8;
}
.gb-formula div:last-child { border-bottom: none; background: var(--brt-c-eef7f3); font-weight: 700; }
.gb-formula span:first-child { font-weight: 700; color: var(--brt-c-1d2b28); }
.gb-formula span:last-child { color: var(--brt-c-4a5753); text-align: right; }

.gb-table-wrap { overflow-x: auto; margin-bottom: 1rem; }
.gb-table { width: 100%; border-collapse: collapse; font-size: 0.95rem; background: #fff; }
.gb-table th {
    text-align: left;
    padding: 0.6rem 0.8rem;
    border: 1px solid #e6dfca;
    background: #faf5e6;
    font-size: 0.76rem;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #6b5f3f;
}
.gb-table td { padding: 0.6rem 0.8rem; border: 1px solid #e6dfca; color: var(--brt-c-33423e); }

.gb-icons { list-style: none; margin: 0; padding: 0; }
.gb-icons li {
    display: flex;
    gap: 0.8rem;
    margin-bottom: 0.7rem;
    padding: 0.75rem 0.9rem;
    border: 1px solid #ebe5d3;
    border-radius: 12px;
    background: #fff;
}
.gb-icons strong { font-size: 0.98rem; color: var(--brt-c-1d2b28); }
.gb-icons p { margin: 0.1rem 0 0; font-size: 0.93rem; color: var(--brt-c-4a5753); }
.gb-icon-chip {
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}
.tone-grey { background: var(--brt-c-eef2f1); color: var(--brt-c-355f55); }
.tone-green { background: rgba(var(--brt-primary-rgb), 0.14); color: var(--brt-primary-dark); }
.tone-blue { background: rgba(37, 99, 235, 0.12); color: #1d4ed8; }

/* ── Who does what ── */
.gb-legend {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 0.4rem 1rem;
    margin-bottom: 1rem;
    padding: 0.8rem 0.95rem;
    border-radius: 12px;
    background: #faf5e6;
    border: 1px solid #efe6cc;
    font-size: 0.84rem;
    color: #4a4230;
}
.gb-legend-item { display: flex; align-items: center; gap: 0.5rem; }
.gb-area {
    margin-bottom: 0.7rem;
    border: 1px solid #e6dfca;
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
}
.gb-area-name {
    padding: 0.5rem 0.9rem;
    background: #faf5e6;
    border-bottom: 1px solid #e6dfca;
    font-weight: 700;
    font-size: 0.92rem;
    color: var(--brt-c-1d2b28);
}
.gb-role {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
    padding: 0.55rem 0.9rem;
    border-bottom: 1px dashed #efe9d6;
}
.gb-role:last-child { border-bottom: none; }
.gb-role.is-mine { background: var(--brt-c-f1f8f5); }
.gb-role-name {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--brt-c-2b3a36);
}
.gb-you {
    padding: 0.05rem 0.5rem;
    border-radius: 999px;
    background: var(--brt-primary);
    color: #fff;
    font-size: 0.68rem;
    font-weight: 700;
}
.gb-role-levels { display: flex; flex-wrap: wrap; gap: 0.3rem; justify-content: flex-end; }
.gb-area-empty { padding: 0.6rem 0.9rem; }
.gb-level {
    flex-shrink: 0;
    padding: 0.12rem 0.55rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    white-space: nowrap;
    background: var(--brt-c-eef2f1);
    color: #4b5563;
}
.level-admin { background: #fde8e8; color: #9b1c1c; }
.level-approver { background: #fef3c7; color: #92400e; }
.level-releaser { background: #ede9fe; color: #5b21b6; }
.level-encoder { background: #dcfce7; color: #166534; }
.level-void { background: #ffe4e6; color: #9f1239; }
.level-view { background: #e0f2fe; color: #075985; }

/* ── Footer: turning pages ── */
.gb-footer {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.7rem 1.1rem;
    border-top: 1px solid #efe8d6;
    background: #fffaf0;
}
.gb-nav-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    min-width: 110px;
    justify-content: center;
    padding: 0.6rem 1rem;
    border: 1px solid #d9cfb3;
    border-radius: 10px;
    background: #fff;
    color: #4a4230;
    font-size: 0.9rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
}
.gb-nav-btn:hover:not(:disabled) { border-color: var(--brt-primary); color: var(--brt-primary-dark); }
.gb-nav-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.gb-nav-next {
    background: var(--brt-primary);
    border-color: var(--brt-primary);
    color: #fff;
}
.gb-nav-next:hover:not(:disabled) { background: var(--brt-primary-hover); color: #fff; }
.gb-progress { text-align: center; min-width: 0; }
.gb-progress-text { font-size: 0.76rem; color: #8a7a52; margin-bottom: 0.3rem; }
.gb-dots { display: flex; justify-content: center; gap: 0.3rem; flex-wrap: wrap; }
.gb-dot {
    width: 9px;
    height: 9px;
    padding: 0;
    border: none;
    border-radius: 50%;
    background: #e3d9bf;
    cursor: pointer;
}
.gb-dot.is-read { background: var(--brt-c-9fc4b8); }
.gb-dot.is-active { background: var(--brt-primary); transform: scale(1.35); }

/* ── Tablets and phones: the contents rail becomes a slide-over ── */
@media (max-width: 899.98px) {
    .gb-overlay { padding: 0; }
    .gb-book {
        grid-template-columns: 1fr;
        height: 100vh;
        max-width: none;
        border-radius: 0;
        position: relative;
    }
    .gb-rail {
        position: absolute;
        inset: 0 auto 0 0;
        width: min(290px, 86vw);
        z-index: 5;
        transform: translateX(-100%);
        transition: transform 0.22s ease;
        box-shadow: 8px 0 30px rgba(0, 0, 0, 0.35);
    }
    .gb-rail.is-open { transform: none; }
    .gb-rail-toggle { display: inline-flex; }
    .gb-topbar { padding-left: 0.8rem; }
    .gb-page { padding: 1.3rem 1.1rem 1.6rem; }
    .gb-page-title { font-size: 1.5rem; }
    .gb-word { grid-template-columns: 1fr; gap: 0.15rem; }
    .gb-nav-btn { min-width: 0; padding: 0.6rem 0.75rem; }
    .gb-progress-text { display: none; }
}

@media (prefers-reduced-motion: reduce) {
    .gb-page.turn-forward,
    .gb-page.turn-back { animation: none; }
    .gb-rail { transition: none; }
}
</style>
