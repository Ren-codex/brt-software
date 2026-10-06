<template>
    <div v-if="drafts.length" class="draft-stack">
        <div v-for="draft in drafts" :key="draft.kind" class="draft-bar" role="status">
            <div class="draft-bar-icon"><i class="ri-draft-line"></i></div>
            <div class="draft-bar-body">
                <span class="draft-bar-title">{{ draft.title }}</span>
                <span class="draft-bar-detail">{{ draft.summary }}</span>
            </div>
            <button class="draft-bar-resume" @click="resume(draft)">Resume</button>
            <button class="draft-bar-discard" title="Discard this draft" @click="discard(draft)">
                <i class="ri-close-line"></i>
            </button>
        </div>
    </div>
</template>

<script>
import { router } from '@inertiajs/vue3';

/**
 * Forms someone minimised, following them around the system so they can carry
 * on wherever they ended up. Resume goes back to where the form lives and
 * reopens it with everything still filled in.
 */
export default {
    computed: {
        drafts() {
            return this.$store.getters.minimisedDrafts;
        },
    },
    methods: {
        resume(draft) {
            if (draft.belongsTo(window.location.pathname)) {
                // Already on the right page: the screen listens for this.
                window.dispatchEvent(new CustomEvent(draft.event));

                return;
            }

            router.visit(draft.path);
        },
        discard(draft) {
            this.$store.dispatch('discardDraft', draft.kind);
        },
    },
};
</script>

<style scoped>
.draft-stack {
    position: fixed;
    right: 1.25rem;
    bottom: 1.25rem;
    z-index: 1080;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    align-items: flex-end;
}

.draft-bar {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding: 0.6rem 0.75rem;
    background: #fff;
    border: 1px solid #c4d9d2;
    border-radius: 12px;
    box-shadow: 0 10px 28px rgba(22, 50, 46, 0.16);
    max-width: min(420px, calc(100vw - 2.5rem));
}

.draft-bar-icon {
    display: grid;
    place-items: center;
    width: 32px;
    height: 32px;
    border-radius: 9px;
    background: rgba(61, 141, 122, 0.12);
    color: #3d8d7a;
    flex-shrink: 0;
}

.draft-bar-body {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.draft-bar-title {
    font-size: 0.8rem;
    font-weight: 600;
    color: #16322e;
    white-space: nowrap;
}

.draft-bar-detail {
    font-size: 0.74rem;
    color: #6b8c85;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.draft-bar-resume {
    border: 1px solid #c4d9d2;
    background: #fff;
    color: #3d8d7a;
    font-size: 0.78rem;
    font-weight: 600;
    border-radius: 8px;
    padding: 0.3rem 0.7rem;
    cursor: pointer;
    flex-shrink: 0;
}

.draft-bar-resume:hover {
    background: #f2f9f6;
}

.draft-bar-discard {
    border: none;
    background: none;
    color: #9bb5ad;
    font-size: 1rem;
    line-height: 1;
    cursor: pointer;
    flex-shrink: 0;
}

.draft-bar-discard:hover {
    color: #a2483c;
}
</style>
