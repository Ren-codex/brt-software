<template>
    <div v-if="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="hide">
        <div class="modal-container modal-sm">
            <div class="modal-header">
                <h2>Confirm Delete</h2>
                <button class="close-btn" @click="hide">
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
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1050;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease;
}

.modal-overlay.active {
    opacity: 1;
    visibility: visible;
}

.modal-container {
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    max-width: 400px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    transform: scale(0.9);
    transition: transform 0.3s ease;
}

.modal-overlay.active .modal-container {
    transform: scale(1);
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: #212529;
}

.close-btn {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #6c757d;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 0.25rem;
    transition: color 0.15s ease;
}

.close-btn:hover {
    color: #495057;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid #e9ecef;
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

.btn {
    padding: 0.5rem 1rem;
    border: 1px solid transparent;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    font-weight: 500;
    text-align: center;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.15s ease;
}

.btn-light {
    color: #6c757d;
    background-color: #f8f9fa;
    border-color: #f8f9fa;
}

.btn-light:hover {
    color: #495057;
    background-color: #e9ecef;
    border-color: #e9ecef;
}

.btn-danger {
    color: #fff;
    background-color: #dc3545;
    border-color: #dc3545;
}

.btn-danger:hover {
    color: #fff;
    background-color: #c82333;
    border-color: #bd2130;
}

.btn:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.spinner-border-sm {
    width: 1rem;
    height: 1rem;
}

.text-center {
    text-align: center;
}

.text-danger {
    color: #dc3545;
}

.text-muted {
    color: #6c757d;
}

.fs-48 {
    font-size: 3rem;
}

.mb-3 {
    margin-bottom: 1rem;
}

.mb-4 {
    margin-bottom: 1.5rem;
}
</style>
