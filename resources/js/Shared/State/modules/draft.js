/**
 * Forms someone started and set aside.
 *
 * A modal cannot survive leaving the page — the component is destroyed — so a
 * minimised form lives here and in localStorage. The store makes the floating
 * bar react to it; localStorage is what carries it across a page change, and
 * it is per-browser by nature, which suits a personal half-written form.
 *
 * Each kind knows where it belongs, so the bar can offer a way back to a form
 * that may be two modules away.
 */
export const DRAFT_KINDS = {
    'purchase-request': {
        title: 'Purchase request draft',
        storageKey: 'brt.purchase-request-draft',
        event: 'resume-purchase-request',
        path: '/inventory?tab=purchase-request&resume=1',
        belongsTo: (pathname) => pathname.startsWith('/inventory'),
    },
    'sales-order': {
        title: 'Sales order draft',
        storageKey: 'brt.sales-order-draft',
        event: 'resume-sales-order',
        path: '/sales-orders?resume=1',
        belongsTo: (pathname) => pathname.startsWith('/sales-orders'),
    },
};

const read = (kind) => {
    try {
        const raw = window.localStorage.getItem(DRAFT_KINDS[kind].storageKey);
        return raw ? JSON.parse(raw) : null;
    } catch (e) {
        // Private windows and blocked site data both throw; a missing draft is
        // a worse outcome than a broken page, so carry on without one.
        return null;
    }
};

const write = (kind, draft) => {
    try {
        const key = DRAFT_KINDS[kind].storageKey;
        if (draft) {
            window.localStorage.setItem(key, JSON.stringify(draft));
        } else {
            window.localStorage.removeItem(key);
        }
    } catch (e) {
        // Nothing to do: the draft stays in memory for this page at least.
    }
};

const state = {
    drafts: Object.keys(DRAFT_KINDS).reduce((all, kind) => ({ ...all, [kind]: read(kind) }), {}),
    // Which forms are on screen right now. Not persisted: a page change
    // destroys the modal, so on the next page it is closed by definition.
    open: {},
};

const getters = {
    draftFor: (state) => (kind) => state.drafts[kind] ?? null,
    /**
     * The bar is a way back to a form you cannot see, so a form that is open
     * is not on it.
     */
    minimisedDrafts: (state) => Object.entries(state.drafts)
        .filter(([kind, draft]) => draft && ! state.open[kind])
        .map(([kind, draft]) => ({ kind, ...DRAFT_KINDS[kind], ...draft })),
};

const mutations = {
    setDraft(state, { kind, draft }) {
        state.drafts = { ...state.drafts, [kind]: draft };
        write(kind, draft);
    },
    setDraftOpen(state, { kind, open }) {
        state.open = { ...state.open, [kind]: open };
    },
};

const actions = {
    keepDraft({ commit }, { kind, summary, payload }) {
        commit('setDraft', { kind, draft: { summary, payload, saved_at: new Date().toISOString() } });
        commit('setDraftOpen', { kind, open: false });
    },
    discardDraft({ commit }, kind) {
        commit('setDraft', { kind, draft: null });
        commit('setDraftOpen', { kind, open: false });
    },
    draftOpened({ commit }, kind) {
        commit('setDraftOpen', { kind, open: true });
    },
    draftClosed({ commit }, kind) {
        commit('setDraftOpen', { kind, open: false });
    },
};

export default { state, getters, mutations, actions };
