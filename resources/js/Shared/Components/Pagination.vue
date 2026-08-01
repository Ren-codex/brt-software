<template>
    <div class="brt-pagination" style="padding-right: 56px">
        <div class="pagination-info">
            Showing
            <span class="fw-semibold">
                {{ pagination.current_page === 1 ? '1' : ((pagination.current_page - 1) * pagination.per_page) + 1 }}–{{ pagination.last_page === pagination.current_page ? pagination.total : pagination.current_page * pagination.per_page }}
            </span>
            of
            <span class="fw-semibold">{{ pagination.total }}</span>
            results
        </div>

        <div class="pagination-controls">
            <button class="pg-btn" :disabled="!links.first || pagination.current_page === 1" @click="fetch(links.first)" title="First page">
                <i class="ri-skip-left-line"></i>
            </button>
            <button class="pg-btn" :disabled="!links.prev" @click="fetch(links.prev)" title="Previous page">
                <i class="ri-arrow-left-s-line"></i>
            </button>
            <span class="pg-current">{{ pagination.current_page }} / {{ pagination.last_page }}</span>
            <button class="pg-btn" :disabled="!links.next" @click="fetch(links.next)" title="Next page">
                <i class="ri-arrow-right-s-line"></i>
            </button>
            <button class="pg-btn" :disabled="!links.last || pagination.current_page === pagination.last_page" @click="fetch(links.last)" title="Last page">
                <i class="ri-skip-right-line"></i>
            </button>
        </div>
    </div>
</template>

<script>
export default {
    props: ['pagination', 'links', 'lists'],
    methods: {
        fetch(data) {
            this.$emit('fetch', data);
        }
    }
}
</script>

<style scoped>
.brt-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 0.75rem;
    padding-bottom: 0.25rem;
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
