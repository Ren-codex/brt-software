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
};

const getters = {
    purchaseRequestDraft: (state) => state.purchaseRequest,
    hasPurchaseRequestDraft: (state) => !!state.purchaseRequest,
};

const mutations = {
    setPurchaseRequestDraft(state, draft) {
        state.purchaseRequest = draft;
        write(draft);
    },
};

const actions = {
    keepPurchaseRequest({ commit }, draft) {
        commit('setPurchaseRequestDraft', { ...draft, saved_at: new Date().toISOString() });
    },
    discardPurchaseRequest({ commit }) {
        commit('setPurchaseRequestDraft', null);
    },
};

export default { state, getters, mutations, actions };
