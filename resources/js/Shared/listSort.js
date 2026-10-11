/**
 * State for a list page's "Sort by" dropdown (see SortSelect.vue).
 *
 * Gives the page `sortOption` — "field:direction", or '' for the list's own
 * default order — and `sortParams`, which is spread into the list request so
 * the server sorts across every page:
 *
 *   mixins: [listSortMixin],
 *   ...
 *   axios.get(url, { params: { ...this.sortParams, keyword, count, option: 'lists' } })
 *
 * Nothing is sent until the person picks a sort, so every list still opens in
 * the order it always has.
 */
export const listSortMixin = {
    data() {
        return {
            sortOption: '',
        };
    },
    computed: {
        sortParams() {
            if (!this.sortOption) {
                return {};
            }

            const [field, direction] = String(this.sortOption).split(':');
            return { sort_by: field, sort_direction: direction };
        },
    },
};
