/**
 * Forms someone started and set aside.
 *
 * A modal cannot survive leaving the page — the component is destroyed — so a
 * minimised form lives here and, so it survives a refresh, on the server keyed
 * to the user. localStorage is kept too, as an instant local cache: the bar can
 * show the moment a page loads, before the server has answered, and it still
 * works offline. The server is the source of truth — field reps on mobile
 * browsers routinely have localStorage blocked or cleared, which is exactly why
 * a draft used to vanish on their next refresh.
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
        // The device blocks storage: the server copy is what carries it across a
        // refresh now, so there is nothing to recover here.
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
     * The bar is a way back to a form you cannot see. Any open form hides the
     * whole bar, not just its own card: a purchase request card floating over
     * an open sales order is in the way of the work being done.
     */
    minimisedDrafts: (state) => Object.values(state.open).some(Boolean)
        ? []
        : Object.entries(state.drafts)
            .filter(([, draft]) => draft)
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
    /**
     * Pull the user's drafts from the server once on start-up. The server wins
     * where it has a draft; a kind it does not know about is left to whatever
     * the local cache holds, so a draft made moments ago is never clobbered by
     * an empty server answer.
     */
    async loadDrafts({ commit, state }) {
        try {
            const { data } = await window.axios.get('/drafts');
            const serverDrafts = data?.data ?? {};
            Object.keys(DRAFT_KINDS).forEach((kind) => {
                const draft = serverDrafts[kind];
                if (draft && draft.payload) {
                    commit('setDraft', { kind, draft });
                }
            });
        } catch (e) {
            // Offline or a guest page: the local cache still serves the bar.
        }
    },
    keepDraft({ commit }, { kind, summary, payload }) {
        const draft = {
            summary,
            payload,
            return_path: window.location.pathname,
            saved_at: new Date().toISOString(),
        };
        commit('setDraft', { kind, draft });
        commit('setDraftOpen', { kind, open: false });

        // Mirror to the server so it survives a refresh on any device. A failure
        // here still leaves the local cache in place for this browser.
        window.axios?.put(`/drafts/${kind}`, {
            summary: draft.summary,
            payload: draft.payload,
            return_path: draft.return_path,
        }).catch(() => {});
    },
    discardDraft({ commit }, kind) {
        commit('setDraft', { kind, draft: null });
        commit('setDraftOpen', { kind, open: false });
        window.axios?.delete(`/drafts/${kind}`).catch(() => {});
    },
    draftOpened({ commit }, kind) {
        commit('setDraftOpen', { kind, open: true });
    },
    draftClosed({ commit }, kind) {
        commit('setDraftOpen', { kind, open: false });
    },
};

export default { state, getters, mutations, actions };
