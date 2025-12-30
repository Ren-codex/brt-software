<template>
    <input type="text" class="form-control test" v-money="money" v-model.lazy="value" :readonly="readonly" style="min-height: 38.4px !important; background-color: #f5f6f7;">
</template>
<script>
import { VMoney } from 'v-money'
export default {
    props: ['readonly', 'initialValue'],
    data(){
        return {
            displayValue: this.initialValue || 0,
            money: {
                decimal: '.',
                thousands: ',',
                prefix: '₱',
                precision: 2,
                masked: false
            },
        }
    },
    watch: {
        initialValue: {
            handler(newVal) {
                if (newVal !== undefined && newVal !== null && newVal !== '') {
                    this.displayValue = newVal;
                }
            },
            immediate: true
        },
        displayValue: function(val){
            this.$emit('amount', val)
        }
    },
    methods: {
        empty(){
            this.value = 0;
        },
        emitValue(value){
            this.value = value;
        }
    },
    directives: {money: VMoney},
}
</script>
<style scoped>
input::placeholder {
    font-size: 12px;
}
</style>