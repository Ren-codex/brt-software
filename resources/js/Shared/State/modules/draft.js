/**
 * A purchase request someone started and set aside.
 *
 * A modal cannot survive leaving the page — the component is destroyed — so a
 * minimised draft lives here and in localStorage. The store makes the floating
 * bar react to it; localStorage is what carries it across a page change, and
 * it is per-browser by nature, which suits a personal half-written form.
 */
const STORAGE_KEY = 'brt.purchase-request-draft';

const read = () => {
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch (e) {
        // Private windows and blocked site data both throw; a missing draft is
        // a worse outcome than a broken page, so carry on without one.
        return null;
    }
};

const write = (draft) => {
    try {
        if (draft) {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(draft));
        } else {
            window.localStorage.removeItem(STORAGE_KEY);
        }
    } catch (e) {
        // Nothing to do: the draft stays in memory for this page at least.
    }
};

const state = {
    purchaseRequest: read(),
    // Whether the form is on screen right now. Not persisted: a page change
    // destroys the modal, so on the next page it is closed again by definition.
    purchaseRequestOpen: false,
};

const getters = {
    purchaseRequestDraft: (state) => state.purchaseRequest,
    hasPurchaseRequestDraft: (state) => !!state.purchaseRequest,
    // The bar is a way back to a form you cannot see; while it is open there
    // is nothing to go back to.
    purchaseRequestMinimised: (state) => !!state.purchaseRequest && ! state.purchaseRequestOpen,
};

const mutations = {
    setPurchaseRequestDraft(state, draft) {
        state.purchaseRequest = draft;
        write(draft);
    },
    setPurchaseRequestOpen(state, open) {
        state.purchaseRequestOpen = open;
    },
};

const actions = {
    keepPurchaseRequest({ commit }, draft) {
        commit('setPurchaseRequestDraft', { ...draft, saved_at: new Date().toISOString() });
    },
    discardPurchaseRequest({ commit }) {
        commit('setPurchaseRequestDraft', null);
        commit('setPurchaseRequestOpen', false);
    },
    purchaseRequestOpened({ commit }) {
        commit('setPurchaseRequestOpen', true);
    },
    purchaseRequestClosed({ commit }) {
        commit('setPurchaseRequestOpen', false);
    },
};

export default { state, getters, mutations, actions };
