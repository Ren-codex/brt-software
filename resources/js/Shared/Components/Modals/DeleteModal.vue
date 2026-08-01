<template>
    <div v-if="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="hide">
        <div class="modal-container modal-sm">
            <div class="modal-header">
                <h2>Confirm Delete</h2>
                <button type="button" class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center">
                    <div class="mb-3">
                        <i class="ri-delete-bin-line text-danger fs-48"></i>
                    </div>
                    <h5 class="mb-3">{{ title }}</h5>
                    <p class="text-muted mb-4">{{ message }}</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" @click="hide">Cancel</button>
                <button type="button" class="btn btn-danger" @click="confirmDelete" :disabled="loading">
                    <span v-if="loading" class="spinner-border spinner-border-sm me-2"></span>
                    {{ loading ? 'Deleting...' : 'Delete' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'DeleteModal',
    props: {
        title: {
            type: String,
            default: 'Are you sure?'
        },
        message: {
            type: String,
            default: 'This action cannot be undone.'
        }
    },
    data() {
        return {
            showModal: false,
            loading: false,
            resolvePromise: null,
            rejectPromise: null
        }
    },
    methods: {
        show() {
            this.showModal = true;
            return new Promise((resolve, reject) => {
                this.resolvePromise = resolve;
                this.rejectPromise = reject;
            });
        },
        hide() {
            this.showModal = false;
            if (this.rejectPromise) {
                this.rejectPromise(false);
            }
        },
        async confirmDelete() {
            this.loading = true;
            try {
                if (this.resolvePromise) {
                    this.resolvePromise(true);
                     this.$emit('update', true);
                }
                this.showModal = false;
            } catch (error) {
                if (this.rejectPromise) {
                    this.rejectPromise(error);
                }
            } finally {
                this.loading = false;
               
            }
        }
    }
}
</script>

<style scoped>
.fs-48 {
    font-size: 3rem;
}
</style>
