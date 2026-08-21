<template>
    <div v-if="total" class="brt-pagination" style="padding-right: 56px">
        <div class="pagination-info">
            Showing
            <span class="fw-semibold">{{ rangeStart }}–{{ rangeEnd }}</span>
            of
            <span class="fw-semibold">{{ total }}</span>
            {{ noun }}
        </div>

        <div class="pagination-controls">
            <button class="pg-btn" :disabled="modelValue === 1" @click="go(1)" title="First page">
                <i class="ri-skip-left-line"></i>
            </button>
            <button class="pg-btn" :disabled="modelValue === 1" @click="go(modelValue - 1)" title="Previous page">
                <i class="ri-arrow-left-s-line"></i>
            </button>
            <span class="pg-current">{{ modelValue }} / {{ lastPage }}</span>
            <button class="pg-btn" :disabled="modelValue === lastPage" @click="go(modelValue + 1)" title="Next page">
                <i class="ri-arrow-right-s-line"></i>
            </button>
            <button class="pg-btn" :disabled="modelValue === lastPage" @click="go(lastPage)" title="Last page">
                <i class="ri-skip-right-line"></i>
            </button>
        </div>
    </div>
</template>

<script>
/**
 * Paging for lists the browser already holds in full.
 *
 * Shares its look with Shared/Components/Pagination.vue, which pages through
 * the server. This one exists because these tabs filter client-side: paging on
 * the server would filter only the page in hand rather than the whole list.
 */
export default {
    name: 'ClientPagination',
    props: {
        /** Current page, via v-model. */
        modelValue: { type: Number, default: 1 },
        total: { type: Number, default: 0 },
        perPage: { type: Number, default: 15 },
        /** What is being counted — 'results', 'batches', 'records'. */
        noun: { type: String, default: 'results' },
    },
    emits: ['update:modelValue'],
    computed: {
        lastPage() {
            return Math.max(1, Math.ceil(this.total / this.perPage));
        },
        rangeStart() {
            return this.total ? (this.modelValue - 1) * this.perPage + 1 : 0;
        },
        rangeEnd() {
            return Math.min(this.modelValue * this.perPage, this.total);
        },
    },
    watch: {
        /** A shorter list must not strand the reader past the end of it. */
        total() {
            if (this.modelValue > this.lastPage) {
                this.$emit('update:modelValue', 1);
            }
        },
    },
    methods: {
        go(page) {
            const target = Math.min(Math.max(page, 1), this.lastPage);
            if (target !== this.modelValue) {
                this.$emit('update:modelValue', target);
            }
        },
    },
};
</script>

<style scoped>
.brt-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 0.75rem;
    padding-bottom: 0.25rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.pagination-info {
    font-size: 0.78rem;
    color: #6b7280;
}

.pagination-controls {
    display: flex;
    align-items: center;
    gap: 4px;
}

.pg-btn {
    width: 30px;
    height: 30px;
    border: 1px solid #c4d9d2;
    border-radius: 8px;
    background: #ffffff;
    color: #3d8d7a;
    font-size: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s ease;
    padding: 0;
    line-height: 1;
}

.pg-btn:hover:not(:disabled) {
    background: #3d8d7a;
    border-color: #3d8d7a;
    color: #ffffff;
}

.pg-btn:disabled {
    opacity: 0.35;
    cursor: not-allowed;
    color: #9ca3af;
    border-color: #e5e7eb;
}

.pg-current {
    font-size: 0.76rem;
    font-weight: 600;
    color: #16322e;
    padding: 0 6px;
    min-width: 44px;
    text-align: center;
}
</style>
