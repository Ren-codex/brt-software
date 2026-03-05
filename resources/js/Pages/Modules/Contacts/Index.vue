<template>
    <PageHeader :title="currentView === 'list' ? 'Contact Management' : 'Contact Details'" :pageTitle="currentView === 'list' ? 'List' : 'Details'" />
    <BRow>
        <div class="col-md-12">
            <!-- Library Card Design -->
            <div class="library-card">
                <!-- List View -->
                <template v-if="currentView === 'list'">
                    <!-- Header -->
                    <div class="library-card-header">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-3">
                                <div class="header-icon">
                                    <i class="ri-mail-send-line fs-24"></i>
                                </div>
                                <div>
                                    <h4 class="header-title mb-1">Contact Messages</h4>
                                    <p class="header-subtitle mb-0">Manage and respond to contact form submissions</p>
                                </div>
                            </div>
                            <button class="create-btn" @click="fetch" :disabled="loading">
                                <i class="ri-refresh-line" :class="{ 'spin': loading }"></i>
                                <span>Refresh</span>
                            </button>
                        </div>
                    </div>

                    <div class="library-card-body">
                        <!-- Search Section (Employee Module Design) -->
                        <div class="search-section">
                            <div class="search-wrapper">
                                <i class="ri-search-line search-icon"></i>
                                <input 
                                    type="text" 
                                    v-model="localKeyword" 
                                    @input="updateKeyword($event.target.value)"
                                    placeholder="Search by name, email, or message..." 
                                    class="search-input"
                                >
                            </div>
                        </div>

                        <!-- Stats Summary Cards -->
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-icon bg-primary-subtle">
                                    <i class="ri-mail-line text-primary"></i>
                                </div>
                                <div class="stat-info">
                                    <span class="stat-label">Total Messages</span>
                                    <span class="stat-value">{{ meta.total || 0 }}</span>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon bg-warning-subtle">
                                    <i class="ri-mail-unread-line text-warning"></i>
                                </div>
                                <div class="stat-info">
                                    <span class="stat-label">Unread</span>
                                    <span class="stat-value">{{ lists.filter(m => !m.is_read).length }}</span>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon bg-success-subtle">
                                    <i class="ri-check-double-line text-success"></i>
                                </div>
                                <div class="stat-info">
                                    <span class="stat-label">Read</span>
                                    <span class="stat-value">{{ lists.filter(m => m.is_read).length }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Modern Table -->
                        <div class="table-container">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Contact</th>
                                        <th>Message</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(list, index) in lists" :key="index" 
                                        :class="{
                                            'unread-message': !list.is_read,
                                            'message-row': true
                                        }"
                                        @click="viewMessage(list)"
                                        style="cursor: pointer;">
                                        <td class="text-center fw-medium">{{ index + 1 }}</td>
                                        <td>
                                            <div class="contact-info">
                                                <div class="contact-avatar" :class="{ 'unread-avatar': !list.is_read }">
                                                    {{ list.name.charAt(0).toUpperCase() }}
                                                </div>
                                                <div class="contact-details">
                                                    <div class="contact-name">{{ list.name }}</div>
                                                    <div class="contact-email">
                                                        <i class="ri-mail-line"></i>
                                                        {{ list.email }}
                                                    </div>
                                                    <div class="contact-phone" v-if="list.phone">
                                                        <i class="ri-phone-line"></i>
                                                        {{ list.phone }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="message-preview" :title="list.message">
                                                {{ truncateMessage(list.message) }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="date-badge">
                                                <div class="date-main">{{ formatDate(list.created_at) }}</div>
                                                <div class="date-time">{{ formatTime(list.created_at) }}</div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge" :class="list.is_read ? 'status-read' : 'status-unread'">
                                                <i :class="list.is_read ? 'ri-check-line' : 'ri-mail-unread-line'"></i>
                                                <span>{{ list.is_read ? 'Read' : 'Unread' }}</span>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-group" @click.stop>
                                                <button class="action-btn delete-btn" @click="deleteContact(list.id)" title="Delete">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="lists.length === 0">
                                        <td colspan="6">
                                            <div class="empty-state-modern">
                                                <div class="empty-icon-modern">
                                                    <i class="ri-inbox-archive-line"></i>
                                                </div>
                                                <h5>No Messages Found</h5>
                                                <p>Messages from the contact form will appear here</p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Modern Pagination -->
                        <div class="pagination-modern">
                            <div class="pagination-info">
                                Showing <span class="fw-semibold">{{ lists.length }}</span> of 
                                <span class="fw-semibold">{{ meta.total || 0 }}</span> messages
                            </div>
                            <div class="pagination-controls" v-if="meta.last_page > 1">
                                <button 
                                    class="page-btn"
                                    :disabled="!meta.prev_page_url"
                                    @click="changePage(meta.prev_page_url)"
                                >
                                    <i class="ri-arrow-left-s-line"></i>
                                </button>
                                <span class="page-indicator">{{ meta.current_page }} / {{ meta.last_page }}</span>
                                <button 
                                    class="page-btn"
                                    :disabled="!meta.next_page_url"
                                    @click="changePage(meta.next_page_url)"
                                >
                                    <i class="ri-arrow-right-s-line"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Enhanced Details View -->
                <div v-if="currentView === 'details'" class="modern-detail-view">
                    <!-- Back Button -->
                    <button class="back-button" @click="backToList">
                        <i class="ri-arrow-left-line"></i>
                        <span>Back to List</span>
                    </button>

                    <!-- Message Detail Card -->
                    <div class="detail-card-modern">
                        <div class="detail-header-modern">
                            <div class="detail-header-left">
                                <div class="detail-avatar-large">
                                    {{ selectedContact?.name?.charAt(0).toUpperCase() }}
                                </div>
                                <div class="detail-title-section">
                                    <h2>{{ selectedContact?.name }}</h2>
                                    <div class="detail-meta">
                                        <span class="status-badge-large" :class="selectedContact?.is_read ? 'status-read' : 'status-unread'">
                                            <i :class="selectedContact?.is_read ? 'ri-check-line' : 'ri-mail-unread-line'"></i>
                                            <span>{{ selectedContact?.is_read ? 'Read' : 'Unread' }}</span>
                                        </span>
                                        <span class="detail-date">
                                            <i class="ri-calendar-line"></i>
                                            {{ formatFullDate(selectedContact?.created_at) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="detail-content">
                            <!-- Contact Information Grid -->
                            <div class="info-grid">
                                <div class="info-card">
                                    <div class="info-icon">
                                        <i class="ri-mail-line"></i>
                                    </div>
                                    <div class="info-content">
                                        <span class="info-label">Email Address</span>
                                        <a :href="'mailto:' + selectedContact?.email" class="info-value email-link">
                                            {{ selectedContact?.email }}
                                            <i class="ri-external-link-line"></i>
                                        </a>
                                    </div>
                                </div>

                                <div class="info-card" v-if="selectedContact?.phone">
                                    <div class="info-icon">
                                        <i class="ri-phone-line"></i>
                                    </div>
                                    <div class="info-content">
                                        <span class="info-label">Phone Number</span>
                                        <a :href="'tel:' + selectedContact?.phone" class="info-value phone-link">
                                            {{ selectedContact?.phone }}
                                            <i class="ri-external-link-line"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Message Section -->
                            <div class="message-section">
                                <div class="message-header">
                                    <i class="ri-message-2-line"></i>
                                    <span>Message Content</span>
                                </div>
                                <div class="message-body">
                                    {{ selectedContact?.message }}
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="detail-actions">
                                <button class="action-button primary" @click="replyToContact" v-if="selectedContact?.email">
                                    <i class="ri-mail-send-line"></i>
                                    Reply via Email
                                </button>
                    
                                <button class="action-button danger" @click="deleteContact(selectedContact?.id)">
                                    <i class="ri-delete-bin-line"></i>
                                    Delete
                                </button>
                            </div>
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
import _ from 'lodash';
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
            localKeyword: '',
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
        checkSearchStr: _.debounce(function (string) {
            this.fetch();
        }, 300),
        
        updateKeyword(value) {
            this.localKeyword = value;
            this.filter.keyword = value;
            this.checkSearchStr(value);
        },
        
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
            
            if (!contact.is_read) {
                axios.patch(`/contacts/${contact.id}/mark-read`)
                .then(response => {
                    const contactInList = this.lists.find(c => c.id === contact.id);
                    if (contactInList) {
                        contactInList.is_read = true;
                    }
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
        
        replyToContact() {
            if (this.selectedContact?.email) {
                window.location.href = `mailto:${this.selectedContact.email}?subject=Re: Contact Form Submission`;
            }
        },
        
        async deleteContact(id) {
            const confirmed = await this.$refs.deleteModal.show(
                'Delete Contact',
                'Are you sure you want to delete this contact message? This action cannot be undone.'
            );

            if (confirmed) {
                axios.delete(`/contacts/${id}`)
                .then(response => {
                    this.$toast.success('Contact deleted successfully');
                    if (this.currentView === 'details') {
                        this.backToList();
                    } else {
                        this.fetch();
                    }
                })
                .catch(err => {
                    console.log(err);
                    this.$toast.error('Failed to delete contact');
                });
            }
        },
        
        truncateMessage(message) {
            if (!message) return '';
            return message.length > 60 ? message.substring(0, 60) + '...' : message;
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
.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Search Section (Employee Module Design) */
.search-section {
    margin-bottom: 24px;
}

.search-wrapper {
    position: relative;
    width: 100%;
    max-width: 400px;
}

.search-icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 1.25rem;
    z-index: 1;
}

.search-input {
    width: 100%;
    padding: 12px 16px 12px 48px;
    font-size: 0.95rem;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    transition: all 0.3s ease;
    background: white;
}

.search-input:focus {
    outline: none;
    border-color: #059669;
    box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.15);
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 28px;
}

.stat-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
    background: white;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.06);
    border-color: #cbd5e1;
}

.stat-icon {
    width: 52px;
    height: 52px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.bg-primary-subtle { background: rgba(5, 150, 105, 0.1); }
.bg-warning-subtle { background: rgba(245, 158, 11, 0.1); }
.bg-success-subtle { background: rgba(16, 185, 129, 0.1); }

.text-primary { color: #059669; }
.text-warning { color: #f59e0b; }
.text-success { color: #10b981; }

.stat-info {
    display: flex;
    flex-direction: column;
}

.stat-label {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
    margin-bottom: 4px;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.2;
}

/* Modern Table */
.table-container {
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    margin-bottom: 24px;
    background: white;
}

.modern-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8rem;
}

.modern-table thead th {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #475569;
    padding: 10px 12px;
    text-align: left;
    border-bottom: 2px solid #e2e8f0;
}

.modern-table tbody td {
    padding: 8px 12px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.8rem;
    line-height: 1.3;
}

.message-row {
    transition: all 0.2s ease;
}

.message-row:hover {
    background: #f8fafc;
    box-shadow: inset 0 0 0 1px rgba(5, 150, 105, 0.1);
}

.unread-message {
    background: linear-gradient(135deg, #fef9e7 0%, #fef3c7 100%);
    border-left: 3px solid #f59e0b;
}

.unread-message:hover {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
}

/* Contact Info */
.contact-info {
    display: flex;
    align-items: center;
    gap: 8px;
}

.contact-avatar {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: linear-gradient(135deg, #059669 0%, #10b981 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
    box-shadow: 0 2px 6px rgba(5, 150, 105, 0.2);
}

.unread-avatar {
    background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
    box-shadow: 0 2px 6px rgba(245, 158, 11, 0.2);
}

.contact-details {
    display: flex;
    flex-direction: column;
    gap: 1px;
}

.contact-name {
    font-weight: 600;
    color: #1e293b;
    font-size: 0.8rem;
}

.contact-email, .contact-phone {
    font-size: 0.7rem;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 3px;
}

.contact-email i, .contact-phone i {
    font-size: 0.7rem;
}

/* Message Preview */
.message-preview {
    max-width: 220px;
    color: #475569;
    font-size: 0.75rem;
    line-height: 1.3;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Date Badge */
.date-badge {
    background: #f8fafc;
    padding: 3px 8px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    display: inline-block;
}

.date-main {
    font-weight: 600;
    color: #1e293b;
    font-size: 0.7rem;
    white-space: nowrap;
}

.date-time {
    font-size: 0.6rem;
    color: #64748b;
}

/* Status Badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 600;
    white-space: nowrap;
}

.status-read {
    background: linear-gradient(135deg, #e6f7ed 0%, #d1f2e0 100%);
    color: #166534;
    border: 1px solid #86efac;
}

.status-unread {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    color: #92400e;
    border: 1px solid #fcd34d;
}

/* Action Group */
.action-group {
    display: flex;
    gap: 4px;
}

.action-btn {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.9rem;
}

.view-btn {
    background: rgba(5, 150, 105, 0.1);
    color: #059669;
}

.view-btn:hover {
    background: #059669;
    color: white;
    transform: scale(1.05);
}

.delete-btn {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.delete-btn:hover {
    background: #ef4444;
    color: white;
    transform: scale(1.05);
}

/* Empty State */
.empty-state-modern {
    text-align: center;
    padding: 48px 24px;
}

.empty-icon-modern {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    border-radius: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    color: #94a3b8;
}

.empty-state-modern h5 {
    font-size: 1.1rem;
    font-weight: 600;
    color: #334155;
    margin-bottom: 8px;
}

.empty-state-modern p {
    font-size: 0.875rem;
    color: #94a3b8;
    margin: 0;
}

/* Modern Pagination */
.pagination-modern {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 0 8px;
    border-top: 1px solid #e2e8f0;
}

.pagination-info {
    font-size: 0.875rem;
    color: #64748b;
}

.pagination-controls {
    display: flex;
    align-items: center;
    gap: 12px;
}

.page-btn {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    background: white;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.page-btn:hover:not(:disabled) {
    background: #059669;
    color: white;
    border-color: #059669;
    transform: translateY(-2px);
}

.page-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.page-indicator {
    font-weight: 600;
    color: #1e293b;
    padding: 0 8px;
}

/* Modern Detail View */
.modern-detail-view {
    padding: 28px;
}

.back-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: white;
    color: #475569;
    font-weight: 600;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-bottom: 24px;
}

.back-button:hover {
    background: #f8fafc;
    transform: translateX(-4px);
    border-color: #059669;
    color: #059669;
}

/* Detail Card */
.detail-card-modern {
    background: white;
    border-radius: 24px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.04);
}

.detail-header-modern {
    padding: 28px;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-bottom: 1px solid #e2e8f0;
}

.detail-header-left {
    display: flex;
    align-items: center;
    gap: 20px;
}

.detail-avatar-large {
    width: 80px;
    height: 80px;
    border-radius: 24px;
    background: linear-gradient(135deg, #059669 0%, #10b981 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: 700;
    box-shadow: 0 8px 24px rgba(5, 150, 105, 0.25);
}

.detail-title-section h2 {
    font-size: 1.75rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 8px 0;
    letter-spacing: -0.5px;
}

.detail-meta {
    display: flex;
    align-items: center;
    gap: 16px;
}

.status-badge-large {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 30px;
    font-size: 0.875rem;
    font-weight: 600;
}

.detail-date {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #64748b;
    font-size: 0.875rem;
}

/* Detail Content */
.detail-content {
    padding: 28px;
}

/* Info Grid */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 16px;
    margin-bottom: 28px;
}

.info-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
    background: #f8fafc;
    border-radius: 18px;
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
}

.info-card:hover {
    border-color: #059669;
    box-shadow: 0 8px 20px rgba(5, 150, 105, 0.1);
    transform: translateY(-2px);
}

.info-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: linear-gradient(135deg, #059669 0%, #10b981 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
}

.info-content {
    flex: 1;
}

.info-label {
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
    display: block;
    margin-bottom: 4px;
}

.info-value {
    font-size: 0.95rem;
    font-weight: 600;
    color: #1e293b;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
}

.email-link:hover, .phone-link:hover {
    color: #059669;
}

.email-link i, .phone-link i {
    font-size: 0.8rem;
    opacity: 0;
    transition: all 0.2s ease;
}

.email-link:hover i, .phone-link:hover i {
    opacity: 1;
}

/* Message Section */
.message-section {
    background: #f8fafc;
    border-radius: 18px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    margin-bottom: 28px;
}

.message-header {
    padding: 16px 20px;
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: #1e293b;
}

.message-header i {
    color: #059669;
    font-size: 1.1rem;
}

.message-body {
    padding: 20px;
    font-size: 0.95rem;
    line-height: 1.8;
    color: #334155;
    white-space: pre-wrap;
    word-break: break-word;
    min-height: 120px;
}

/* Detail Actions */
.detail-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.action-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
}

.action-button.primary {
    background: #059669;
    color: white;
}

.action-button.primary:hover {
    background: #047857;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(5, 150, 105, 0.3);
}

.action-button.success {
    background: #fbbf24;
    color: #1e293b;
}

.action-button.success:hover {
    background: #f59e0b;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(245, 158, 11, 0.3);
}

.action-button.danger {
    background: #ef4444;
    color: white;
}

.action-button.danger:hover {
    background: #dc2626;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
}

/* Responsive */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .detail-header-left {
        flex-direction: column;
        text-align: center;
    }
    
    .detail-meta {
        flex-direction: column;
    }
    
    .detail-actions {
        flex-direction: column;
    }
    
    .action-button {
        width: 100%;
        justify-content: center;
    }
}
</style>
