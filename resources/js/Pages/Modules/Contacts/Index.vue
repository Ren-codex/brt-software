<template>
    <PageHeader :title="currentView === 'list' ? 'Contact Management' : 'Contact Details'" :pageTitle="currentView === 'list' ? 'List' : 'Details'" />
    <BRow>
        <div class="col-md-12">
            <div class="library-card">
                <!-- List View -->
                <template v-if="currentView === 'list'">
                    <div class="library-card-header">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-3">
                                <div class="header-icon">
                                    <i class="ri-mail-send-line fs-24"></i>
                                </div>
                                <div>
                                    <h4 class="header-title mb-1">Contact Messages</h4>
                                    <p class="header-subtitle mb-0">Manage contact form submissions</p>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="create-btn" @click="fetch" :disabled="loading">
                                    <i class="ri-refresh-line"></i>
                                    <span>Refresh</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card-body m-2 p-3">
<!-- Search -->
                        <div class="search-section">
                            <div class="search-wrapper">
                                <i class="ri-search-line search-icon"></i>
                                <input type="text" v-model="filter.keyword" @input="updateKeyword($event.target.value)"
                                    placeholder="Search contacts..." class="search-input">
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="table-responsive table-card" style="overflow: auto;">
                            <table class="table align-middle table-striped table-centered mb-0">
                                <thead class="table-light thead-fixed">
                                    <tr class="fs-11">
                                        <th style="width: 5%;">#</th>
                                        <th style="width: 20%;">Name</th>
                                        <th style="width: 20%;">Email</th>
                                        <th style="width: 15%;">Phone</th>
                                        <th style="width: 25%;">Message</th>
                                        <th style="width: 10%;">Date</th>
                                        <th style="width: 5%;">Status</th>
                                        <th style="width: 10%;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="table-white fs-12">
                                    <tr v-for="(list, index) in lists" :key="index" 
                                        :class="{
                                            'unread-row': !list.is_read,
                                            'animate-fade-in': true
                                        }"
                                        :style="{ animationDelay: `${index * 50}ms` }">
                                        <td class="text-center">
                                            <span class="text-muted fw-medium">{{ index + 1 }}</span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-xs me-2">
                                                    <div class="avatar-title rounded-circle bg-gradient-teal text-white">
                                                        {{ list.name.charAt(0).toUpperCase() }}
                                                    </div>
                                                </div>
                                                <span class="fw-medium text-dark">{{ list.name }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="ri-mail-line text-muted me-2 fs-14"></i>
                                                <a :href="'mailto:' + list.email" class="text-primary text-decoration-none">
                                                    {{ list.email }}
                                                </a>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center" v-if="list.phone">
                                                <i class="ri-phone-line text-muted me-2 fs-14"></i>
                                                <a :href="'tel:' + list.phone" class="text-dark">
                                                    {{ list.phone }}
                                                </a>
                                            </div>
                                            <span v-else class="text-muted">-</span>
                                        </td>
                                        <td>
                                            <span class="text-truncate d-inline-block message-cell" :title="list.message">
                                                {{ list.message }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="fw-medium">{{ formatDate(list.created_at) }}</span>
                                                <small class="text-muted">{{ formatTime(list.created_at) }}</small>
                                            </div>
                                        </td>
                                        <td>
                                            <span v-if="list.is_read" class="badge badge-soft-success">
                                                <i class="ri-check-line me-1"></i>Read
                                            </span>
                                            <span v-else class="badge badge-soft-warning">
                                                <i class="ri-mail-unread-line me-1"></i>Unread
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex justify-content-center gap-1">
                                                <BButton @click="viewMessage(list)" variant="primary" size="sm" v-b-tooltip.hover title="View" class="btn-icon">
                                                    <i class="ri-eye-line"></i>
                                                </BButton>
                                                <BButton @click="deleteContact(list.id)" variant="danger" size="sm" v-b-tooltip.hover title="Delete" class="btn-icon">
                                                    <i class="ri-delete-bin-line"></i>
                                                </BButton>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="lists.length === 0">
                                        <td colspan="8" class="text-center py-5">
                                            <div class="empty-state">
                                                <div class="empty-icon">
                                                    <i class="ri-inbox-archive-line"></i>
                                                </div>
                                                <h5 class="empty-title">No Messages Found</h5>
                                                <p class="empty-description">There are no contact messages yet. Messages from the contact form will appear here.</p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="card-footer-custom">
                            <div class="pagination-info">
                                <span class="text-muted">Showing <strong>{{ lists.length }}</strong> of <strong>{{ meta.total || 0 }}</strong> results</span>
                            </div>
                            <div v-if="meta.last_page > 1" class="pagination-wrapper">
                                <BButtonGroup>
                                    <BButton 
                                        v-for="(link, index) in links" 
                                        :key="index"
                                        :variant="link.active ? 'primary' : 'outline-secondary'"
                                        :disabled="!link.url"
                                        @click="changePage(link.url)"
                                        size="sm"
                                        class="pagination-btn"
                                    >
                                        <span v-html="link.label"></span>
                                    </BButton>
                                </BButtonGroup>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Details View -->
                <div v-if="currentView === 'details'" class="p-4">
                    <div class="detail-card">
                        <div class="detail-header mb-4">
                            <button class="btn btn-light mb-3" @click="backToList">
                                <i class="ri-arrow-left-line me-1"></i> Back to List
                            </button>
                            <div class="d-flex align-items-center gap-3">
                                <div class="detail-avatar-lg">
                                    <div class="avatar-title-lg rounded-circle bg-gradient-teal text-white">
                                        {{ selectedContact?.name?.charAt(0).toUpperCase() }}
                                    </div>
                                </div>
                                <div>
                                    <h3 class="mb-1">{{ selectedContact?.name }}</h3>
                                    <span v-if="selectedContact?.is_read" class="badge badge-soft-success">
                                        <i class="ri-check-line me-1"></i>Read
                                    </span>
                                    <span v-else class="badge badge-soft-warning">
                                        <i class="ri-mail-unread-line me-1"></i>Unread
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="detail-body">
                            <div class="detail-item mb-3">
                                <div class="detail-label">
                                    <i class="ri-mail-line"></i>
                                    <span>Email</span>
                                </div>
                                <div class="detail-value">
                                    <a :href="'mailto:' + selectedContact?.email" class="text-primary">
                                        {{ selectedContact?.email }}
                                    </a>
                                </div>
                            </div>
                            
                            <div class="detail-item mb-3">
                                <div class="detail-label">
                                    <i class="ri-phone-line"></i>
                                    <span>Phone</span>
                                </div>
                                <div class="detail-value">
                                    <template v-if="selectedContact?.phone">
                                        <a :href="'tel:' + selectedContact?.phone" class="text-dark">
                                            {{ selectedContact?.phone }}
                                        </a>
                                    </template>
                                    <span v-else class="text-muted">Not provided</span>
                                </div>
                            </div>
                            
                            <div class="detail-item mb-3">
                                <div class="detail-label">
                                    <i class="ri-calendar-line"></i>
                                    <span>Date</span>
                                </div>
                                <div class="detail-value">
                                    {{ formatFullDate(selectedContact?.created_at) }}
                                </div>
                            </div>
                            
                            <div class="detail-message mt-4 pt-3">
                                <div class="detail-label mb-2">
                                    <i class="ri-message-2-line"></i>
                                    <span>Message</span>
                                </div>
                                <div class="message-content">
                                    {{ selectedContact?.message }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-top" v-if="!selectedContact?.is_read">
                            <button class="btn btn-success" @click="markAsRead">
                                <i class="ri-check-line me-1"></i> Mark as Read
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </BRow>

    <!-- Delete Confirmation Modal -->
    <DeleteModal @update="fetch()" ref="deleteModal" />
</template>

<script>
import { usePage } from '@inertiajs/vue3';
import PageHeader from '@/Shared/Components/PageHeader.vue';
import DeleteModal from '@/Shared/Components/Modals/DeleteModal.vue';

export default {
    components: { PageHeader, DeleteModal },
    data() {
        return {
            currentUrl: window.location.origin,
            lists: [],
            meta: {},
            links: [],
            filter: {
                keyword: '',
                unread: null,
            },
            loading: false,
            currentView: 'list',
            selectedContact: null,
        };
    },
    watch: {
        '$page.url': {
            handler() {
                this.fetch();
            },
            immediate: false
        }
    },
    created() {
        this.fetch();
    },
    methods: {
        fetch() {
            this.loading = true;
            let page_url = '/contacts';
            
            const params = new URLSearchParams();
            if (this.filter.keyword) params.append('keyword', this.filter.keyword);
            if (this.filter.unread !== null) params.append('unread', this.filter.unread);
            params.append('option', 'lists');
            params.append('count', 10);
            
            const queryString = params.toString();
            if (queryString) page_url += '?' + queryString;
            
            axios.get(page_url, {
                params: {
                    keyword: this.filter.keyword,
                    unread: this.filter.unread,
                    option: 'lists',
                    count: 10,
                }
            })
            .then(response => {
                if (response.data) {
                    this.lists = response.data.data || [];
                    this.meta = response.data.meta || {};
                    this.links = response.data.links || [];
                }
            })
            .catch(err => console.log(err))
            .finally(() => {
                this.loading = false;
            });
        },
        
        updateKeyword(value) {
            clearTimeout(this.debounce);
            this.debounce = setTimeout(() => {
                this.filter.keyword = value;
                this.fetch();
            }, 500);
        },
        
        changePage(url) {
            if (url) {
                axios.get(url).then(response => {
                    this.lists = response.data.data || [];
                    this.meta = response.data.meta || {};
                    this.links = response.data.links || [];
                });
            }
        },
        
        viewMessage(contact) {
            this.selectedContact = contact;
            this.currentView = 'details';
            
            // Auto-mark as read when viewing
            if (!contact.is_read) {
                axios.patch(`/contacts/${contact.id}/mark-read`)
                .then(response => {
                    // Event will be broadcast via Reverb
                })
                .catch(err => console.log(err));
            }
        },
        
        backToList() {
            this.currentView = 'list';
            this.selectedContact = null;
            this.fetch();
        },
        
        markAsRead() {
            if (!this.selectedContact) return;
            
            axios.patch(`/contacts/${this.selectedContact.id}/mark-read`)
            .then(response => {
                this.$toast.success('Marked as read');
                this.selectedContact.is_read = true;
                // Update in list too
                const contact = this.lists.find(c => c.id === this.selectedContact.id);
                if (contact) {
                    contact.is_read = true;
                }
            })
            .catch(err => {
                console.log(err);
                this.$toast.error('Failed to mark as read');
            });
        },
        
        async deleteContact(id) {
            const confirmed = await this.$refs.deleteModal.show(
                'Delete Contact',
                'Are you sure you want to delete this contact message?'
            );

            if (confirmed) {
                axios.delete(`/contacts/${id}`)
                .then(response => {
                    this.$toast.success('Contact deleted successfully');
                    this.fetch();
                })
                .catch(err => {
                    console.log(err);
                    this.$toast.error('Failed to delete contact');
                });
            }
        },
        
        formatDate(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        },
        
        formatTime(dateStr) {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        },
        
        formatFullDate(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        },
    }
}
</script>

<style scoped>
/* Card Styling */
.library-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
    overflow: hidden;
    border: 1px solid rgba(0, 0, 0, 0.03);
}

.library-card-header {
    padding: 24px 24px 0;
}

/* Header Icon - Teal Gradient */
.header-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    box-shadow: 0 4px 12px rgba(20, 184, 166, 0.25);
}

.header-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e293b;
    letter-spacing: -0.3px;
}

.header-subtitle {
    font-size: 0.875rem;
    color: #64748b;
}

/* Create Button */
.create-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%);
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 8px rgba(20, 184, 166, 0.2);
}

.create-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(20, 184, 166, 0.35);
}

.create-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

/* Search Section */
.search-section {
    display: flex;
    gap: 16px;
    margin-bottom: 20px;
    padding: 20px;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}

.search-wrapper {
    flex: 1;
    position: relative;
}

.search-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 1.1rem;
}

.search-input {
    width: 100%;
    padding: 12px 12px 12px 42px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 0.9rem;
    transition: all 0.25s ease;
    background: white;
}

.search-input:focus {
    outline: none;
    border-color: #14b8a6;
    box-shadow: 0 0 0 4px rgba(20, 184, 166, 0.1);
}

.filter-wrapper .form-select {
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 0.9rem;
    background: white;
    cursor: pointer;
    transition: all 0.25s ease;
    min-width: 150px;
}

.filter-wrapper .form-select:focus {
    border-color: #14b8a6;
    box-shadow: 0 0 0 4px rgba(20, 184, 166, 0.1);
}

/* Table Styling */
.table-card {
    margin-top: 0;
    border-radius: 12px;
    overflow: hidden;
}

.table thead th {
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    font-size: 0.7rem;
    letter-spacing: 0.8px;
    border-bottom: 2px solid #cbd5e1;
    padding: 14px 12px;
}

.table td {
    padding: 14px 12px;
    vertical-align: middle;
    font-size: 0.875rem;
    border-color: #f1f5f9;
}

.table tbody tr {
    transition: all 0.2s ease;
}

.table tbody tr:hover {
    background: #f8fafc !important;
}

/* Unread Row */
.unread-row {
    background: linear-gradient(135deg, #fef9c3 0%, #fef08a 0.15) !important;
}

.unread-row:hover {
    background: linear-gradient(135deg, #fef08a 0%, #fde047 0.15) !important;
}

/* Avatar */
.avatar-xs {
    width: 36px;
    height: 36px;
    flex-shrink: 0;
}

.avatar-title {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.875rem;
}

.avatar-title-lg {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
}

.avatar-lg {
    width: 64px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.5rem;
}

.detail-avatar-lg {
    width: 80px;
    height: 80px;
    flex-shrink: 0;
}

.bg-gradient-teal {
    background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%);
}

/* Badges */
.badge {
    padding: 6px 12px;
    font-size: 0.7rem;
    font-weight: 600;
    border-radius: 20px;
    letter-spacing: 0.3px;
}

.badge-soft-success {
    background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
    color: #166534;
    border: 1px solid #86efac;
}

.badge-soft-warning {
    background: linear-gradient(135deg, #fef9c3 0%, #fef08a 100%);
    color: #854d0e;
    border: 1px solid #fde047;
}

/* Button Icon */
.btn-icon {
    width: 32px;
    height: 32px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.btn-icon:hover {
    transform: scale(1.1);
}

/* Message Cell */
.message-cell {
    max-width: 220px;
    color: #64748b;
}

/* Empty State */
.empty-state {
    padding: 40px 20px;
}

.empty-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.empty-icon i {
    font-size: 2.5rem;
    color: #94a3b8;
}

.empty-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #334155;
    margin-bottom: 8px;
}

.empty-description {
    font-size: 0.875rem;
    color: #94a3b8;
    max-width: 300px;
    margin: 0 auto;
}

/* Pagination */
.card-footer-custom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 4px;
    border-top: 1px solid #f1f5f9;
    margin-top: 8px;
}

.pagination-info {
    font-size: 0.875rem;
}

.pagination-info strong {
    color: #14b8a6;
}

.pagination-btn {
    transition: all 0.2s ease;
}

.pagination-btn:hover:not(:disabled) {
    transform: translateY(-1px);
}

/* Animation */
.animate-fade-in {
    animation: fadeIn 0.4s ease-out forwards;
    opacity: 0;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Detail Card */
.detail-card {
    padding: 20px;
}

.detail-header h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
}

.detail-body {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.detail-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.detail-label {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 80px;
    color: #64748b;
    font-size: 0.875rem;
    font-weight: 500;
}

.detail-label i {
    font-size: 1rem;
    color: #14b8a6;
}

.detail-value {
    font-size: 0.9rem;
    color: #1e293b;
    font-weight: 500;
}

.detail-message {
    margin-top: 8px;
    padding-top: 16px;
    border-top: 1px solid #e2e8f0;
}

.message-content {
    padding: 16px;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    font-size: 0.95rem;
    line-height: 1.7;
    color: #334155;
    white-space: pre-wrap;
    word-break: break-word;
}

/* Responsive */
@media (max-width: 768px) {
    .search-section {
        flex-direction: column;
    }
    
    .card-footer-custom {
        flex-direction: column;
        gap: 12px;
    }
    
    .pagination-wrapper {
        width: 100%;
        overflow-x: auto;
    }
}
</style>
