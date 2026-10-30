<template>
    <input
        type="text"
        inputmode="decimal"
        :value="displayValue"
        :placeholder="placeholder"
        :disabled="disabled"
        @input="onInput"
    />
</template>

<script>
/**
 * A money input that shows thousand separators as you type (153180 → 153,180)
 * while still giving the parent a plain number through v-model. The commas are
 * display only; whatever is emitted and stored is the bare numeric value.
 */
export default {
    name: 'AmountInput',
    props: {
        modelValue: { type: [Number, String], default: null },
        placeholder: { type: String, default: '0.00' },
        disabled: { type: Boolean, default: false },
    },
    emits: ['update:modelValue'],
    data() {
        return { displayValue: this.toDisplay(this.modelValue) };
    },
    watch: {
        modelValue(value) {
            // Resync on a real outside change (a reset or a prefill), but leave
            // the half-typed figure alone when the change is our own echo —
            // otherwise a trailing "." or "0" the user just typed is wiped.
            if (this.toNumber(this.displayValue) !== this.toNumber(value)) {
                this.displayValue = this.toDisplay(value);
            }
        },
    },
    methods: {
        toNumber(value) {
            if (value === null || value === undefined || value === '') return null;
            const n = Number(String(value).replace(/,/g, ''));
            return Number.isNaN(n) ? null : n;
        },
        toDisplay(value) {
            if (this.toNumber(value) === null) return '';
            return this.group(String(value).replace(/,/g, ''));
        },
        group(raw) {
            const [intPart = '', decPart] = raw.split('.');
            const intClean = intPart.replace(/^0+(?=\d)/, '') || '0';
            const grouped = intClean.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            return decPart !== undefined ? grouped + '.' + decPart : grouped;
        },
        onInput(event) {
            let raw = event.target.value.replace(/,/g, '').replace(/[^0-9.]/g, '');

            const firstDot = raw.indexOf('.');
            if (firstDot !== -1) {
                const intPart = raw.slice(0, firstDot);
                const decPart = raw.slice(firstDot + 1).replace(/\./g, '').slice(0, 2);
                raw = intPart + '.' + decPart;
            }

            const display = raw === '' ? '' : this.group(raw);
            this.displayValue = display;

            // Keep the caret at the end once Vue writes the formatted value back,
            // so inserting a comma does not leave it a character behind.
            this.$nextTick(() => {
                if (event.target === document.activeElement) {
                    const end = event.target.value.length;
                    try { event.target.setSelectionRange(end, end); } catch (e) { /* noop */ }
                }
            });

            this.$emit('update:modelValue', this.toNumber(display));
        },
    },
};
</script>
