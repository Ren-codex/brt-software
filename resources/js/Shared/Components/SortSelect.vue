<template>
    <div class="sort-select-wrap">
        <label :for="id" class="sort-select-label"><i class="ri-sort-desc"></i> Sort by</label>
        <select :id="id" class="sort-select-input" :value="modelValue" @change="pick($event.target.value)">
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
    </div>
</template>

<script>
/**
 * The "Sort by" dropdown used on list pages.
 *
 * The value is "field:direction" (for example "name:asc"); listSortMixin turns
 * that into the two request parameters the server's sortFromRequest() reads.
 *
 * `fields` describes the columns a page can be sorted by, and each one becomes
 * a pair of options worded for its kind of data — A–Z / Z–A for text, highest /
 * lowest for amounts, newest / oldest for dates:
 *
 *   <SortSelect v-model="sortOption" :fields="[{ field: 'name', label: 'Name' }]" @change="fetch()" />
 *
 * "Default order" comes first and sends nothing, leaving the list as it
 * normally opens. "Latest added" and "Oldest added" follow unless the page
 * passes :added="false" (for a table with no created_at).
 */
let uid = 0;

const WORDING = {
    text: ['A–Z', 'Z–A', 'asc', 'desc'],
    number: ['highest', 'lowest', 'desc', 'asc'],
    date: ['newest', 'oldest', 'desc', 'asc'],
};

export default {
    props: {
        modelValue: { type: String, default: '' },
        fields: { type: Array, default: () => [] },
        added: { type: Boolean, default: true },
    },
    emits: ['update:modelValue', 'change'],
    data() {
        return { id: `sort-select-${++uid}` };
    },
    computed: {
        options() {
            // The empty value sends no sort at all: the list as it normally opens.
            const options = [{ value: '', label: 'Default order' }];

            if (this.added) {
                options.push({ value: 'created_at:desc', label: 'Latest added' });
                options.push({ value: 'created_at:asc', label: 'Oldest added' });
            }

            this.fields.forEach(({ field, label, type = 'text' }) => {
                const [first, second, firstDir, secondDir] = WORDING[type] || WORDING.text;
                options.push({ value: `${field}:${firstDir}`, label: `${label} (${first})` });
                options.push({ value: `${field}:${secondDir}`, label: `${label} (${second})` });
            });

            return options;
        },
    },
    methods: {
        pick(value) {
            this.$emit('update:modelValue', value);
            // After the model has updated, so the page's fetch sees the new sort.
            this.$nextTick(() => this.$emit('change', value));
        },
    },
};
</script>
