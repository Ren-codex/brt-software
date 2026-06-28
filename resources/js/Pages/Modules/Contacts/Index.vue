<template>
    <PageHeader title="Contact Management" pageTitle="List" />
    <BRow>
        <div class="col-md-12">
            <div class="library-card">
                <!-- Header -->
                <div class="library-card-header">
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

                <div class="library-card-body p-0">
                    <!-- Stats Bar -->
                    <div class="stats-bar">
                        <div class="stat-item">
                            <div class="stat-dot dot-total"></div>
                            <span class="stat-label">Total</span>
                            <span class="stat-num">{{ meta.total || 0 }}</span>
                        </div>
                        <div class="stat-item">
                            <div class="stat-dot dot-unread"></div>
                            <span class="stat-label">Unread</span>
                            <span class="stat-num unread-num">{{ lists.filter(m => !m.is_read).length }}</span>
                        </div>
                        <div class="stat-item">
                            <div class="stat-dot dot-read"></div>
                            <span class="stat-label">Read</span>
                            <span class="stat-num read-num">{{ lists.filter(m => m.is_read).length }}</span>
                        </div>
                    </div>

                    <!-- Split Layout -->
                    <div class="split-layout">
                        <!-- Left Panel: Contact List -->
                        <div class="split-left">
                            <!-- Search -->
                            <div class="split-search">
                                <i class="ri-search-line search-icon"></i>
                                <input
                                    type="text"
                                    v-model="localKeyword"
                                    @input="updateKeyword($event.target.value)"
                                    placeholder="Search..."
                                    class="search-input"
                                >
                            </div>

                            <!-- List -->
                            <div class="contact-list">
                                <div
                                    v-for="(list, index) in lists"
                                    :key="index"
                                    class="contact-item"
                                    :class="{
                                        'is-unread': !list.is_read,
                                        'is-active': selectedContact && selectedContact.id === list.id
                                    }"
                                    @click="viewMessage(list)"
                                >
                                    <div class="contact-avatar" :style="{ background: avatarColor(list.name) }">
                                        {{ list.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <div class="contact-info">
                                        <div class="contact-name">{{ list.name }}</div>
                                        <div class="contact-preview">{{ truncateMessage(list.message, 40) }}</div>
                                        <div class="contact-date">{{ formatDate(list.created_at) }}</div>
                                    </div>
                                    <div v-if="!list.is_read" class="unread-dot"></div>
                                </div>

                                <div v-if="lists.length === 0" class="empty-list">
                                    <i class="ri-inbox-archive-line"></i>
                                    <p>No messages found</p>
                                </div>
                            </div>

                            <!-- Pagination -->
                            <div class="split-pagination" v-if="meta.last_page > 1">
                                <button class="page-btn" :disabled="!meta.prev_page_url" @click="changePage(meta.prev_page_url)">
                                    <i class="ri-arrow-left-s-line"></i>
                                </button>
                                <span class="page-indicator">{{ meta.current_page }} / {{ meta.last_page }}</span>
                                <button class="page-btn" :disabled="!meta.next_page_url" @click="changePage(meta.next_page_url)">
                                    <i class="ri-arrow-right-s-line"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Right Panel: Message Detail -->
                        <div class="split-right">
                            <!-- No selection state -->
                            <div v-if="!selectedContact" class="no-selection">
                                <i class="ri-mail-open-line"></i>
                                <p>Select a message to read</p>
                            </div>

                            <!-- Message Detail -->
                            <div v-else class="detail-view">
                                <!-- Sender Header -->
                                <div class="detail-header">
                                    <div class="detail-avatar" :style="{ background: avatarColor(selectedContact.name) }">
                                        {{ selectedContact.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <div class="detail-sender">
                                        <div class="detail-name">{{ selectedContact.name }}</div>
                                        <div class="detail-email">
                                            <i class="ri-mail-line"></i> {{ selectedContact.email }}
                                        </div>
                                        <div class="detail-phone" v-if="selectedContact.phone">
                                            <i class="ri-phone-line"></i> {{ selectedContact.phone }}
                                        </div>
                                    </div>
                                    <div class="detail-meta">
                                        <span class="status-badge" :class="selectedContact.is_read ? 'badge-read' : 'badge-unread'">
                                            <i :class="selectedContact.is_read ? 'ri-check-line' : 'ri-mail-unread-line'"></i>
                                            {{ selectedContact.is_read ? 'Read' : 'Unread' }}
                                        </span>
                                        <div class="detail-date">{{ formatFullDate(selectedContact.created_at) }}</div>
                                    </div>
                                </div>

                                <!-- Message Body -->
                                <div class="detail-body">
                                    <div class="message-label">
                                        <i class="ri-message-2-line"></i> Message
                                    </div>
                                    <div class="message-text">{{ selectedContact.message }}</div>
                                </div>

                                <!-- Actions -->
                                <div class="detail-actions">
                                    <button class="msg-btn btn-mark" @click="markAsRead" v-if="!selectedContact.is_read">
                                        <i class="ri-check-double-line"></i> Mark as Read
                                    </button>
                                    <button class="msg-btn btn-reply" @click="replyToContact" v-if="selectedContact.email">
                                        <i class="ri-mail-send-line"></i> Reply
                                    </button>
                                    <button class="msg-btn btn-delete" @click="deleteContact(selectedContact.id)">
                                        <i class="ri-delete-bin-line"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </BRow>

    <DeleteModal @update="fetch()" ref="deleteModal" />
</template>

<script>
import _ from 'lodash';
import PageHeader from '@/Shared/Components/PageHeader.vue';
import DeleteModal from '@/Shared/Components/Modals/DeleteModal.vue';

const AVATAR_COLORS = ['#3d8d7a','#e67e22','#e74c3c','#8e44ad','#2980b9','#27ae60','#c0392b','#16a085','#d35400','#7f8c8d'];

export default {
    components: { PageHeader, DeleteModal },
    data() {
        return {
            lists: [],
            meta: {},
            links: [],
            filter: { keyword: '' },
            loading: false,
            selectedContact: null,
            localKeyword: '',
        };
    },
    created() {
        this.fetch();
    },
    methods: {
        checkSearchStr: _.debounce(function () { this.fetch(); }, 300),

        updateKeyword(value) {
            this.localKeyword = value;
            this.filter.keyword = value;
            this.checkSearchStr();
        },

        fetch() {
            this.loading = true;
            axios.get('/contacts', {
                params: { keyword: this.filter.keyword, option: 'lists', count: 20 }
            })
            .then(response => {
                this.lists = response.data.data || [];
                this.meta  = response.data.meta  || {};
                this.links = response.data.links  || [];
                if (!this.selectedContact && this.lists.length > 0) {
                    this.selectedContact = this.lists[0];
                }
            })
            .catch(err => console.log(err))
            .finally(() => { this.loading = false; });
        },

        changePage(url) {
            if (!url) return;
            axios.get(url).then(response => {
                this.lists = response.data.data || [];
                this.meta  = response.data.meta  || {};
                this.links = response.data.links  || [];
            });
        },

        viewMessage(contact) {
            this.selectedContact = contact;
            if (!contact.is_read) {
                axios.patch(`/contacts/${contact.id}/mark-read`)
                .then(() => {
                    const c = this.lists.find(m => m.id === contact.id);
                    if (c) c.is_read = true;
                    this.selectedContact = { ...this.selectedContact, is_read: true };
                })
                .catch(err => console.log(err));
            }
        },

        markAsRead() {
            if (!this.selectedContact) return;
            axios.patch(`/contacts/${this.selectedContact.id}/mark-read`)
            .then(() => {
                const c = this.lists.find(m => m.id === this.selectedContact.id);
                if (c) c.is_read = true;
                this.selectedContact = { ...this.selectedContact, is_read: true };
                this.$toast?.success('Marked as read');
            })
            .catch(err => console.log(err));
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
                .then(() => {
                    this.$toast?.success('Contact deleted successfully');
                    this.selectedContact = null;
                    this.fetch();
                })
                .catch(err => console.log(err));
            }
        },

        avatarColor(name) {
            if (!name) return AVATAR_COLORS[0];
            const i = name.charCodeAt(0) % AVATAR_COLORS.length;
            return AVATAR_COLORS[i];
        },

        truncateMessage(msg, len = 60) {
            if (!msg) return '';
            return msg.length > len ? msg.substring(0, len) + '...' : msg;
        },

        formatDate(dateStr) {
            if (!dateStr) return '-';
            return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        },

        formatFullDate(dateStr) {
            if (!dateStr) return '-';
            return new Date(dateStr).toLocaleDateString('en-US', {
                weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
                hour: '2-digit', minute: '2-digit'
            });
        },
    }
}
</script>

<style scoped>
.spin { animation: spin 1s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

/* ── Stats Bar ── */
.stats-bar {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 10px 20px;
    background: #fff;
    border-bottom: 1px solid #e4eeeb;
}
.stat-item { display: flex; align-items: center; gap: 6px; }
.stat-dot { width: 8px; height: 8px; border-radius: 50%; }
.dot-total  { background: #3d8d7a; }
.dot-unread { background: #c47f00; }
.dot-read   { background: #1a7e5a; }
.stat-label { font-size: 0.75rem; color: #6b8c85; font-weight: 600; }
.stat-num   { font-size: 0.85rem; font-weight: 700; color: #16322e; }
.unread-num { color: #c47f00; }
.read-num   { color: #1a7e5a; }

/* ── Split Layout ── */
.split-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    height: calc(100vh - 260px);
    min-height: 500px;
}

/* ── Left Panel ── */
.split-left {
    border-right: 1px solid #e4eeeb;
    display: flex;
    flex-direction: column;
    background: #fff;
    overflow: hidden;
}

.split-search {
    position: relative;
    padding: 10px 12px;
    border-bottom: 1px solid #e4eeeb;
    flex-shrink: 0;
}
.split-search .search-icon {
    position: absolute;
    left: 22px;
    top: 50%;
    transform: translateY(-50%);
    color: #6b8c85;
    font-size: 0.95rem;
}
.split-search .search-input {
    width: 100%;
    padding: 7px 10px 7px 32px;
    border: 1px solid #dce8e4;
    border-radius: 8px;
    font-size: 0.8rem;
    background: #f5f9f8;
    color: #16322e;
    outline: none;
}
.split-search .search-input:focus {
    border-color: #3d8d7a;
    background: #fff;
}

.contact-list {
    flex: 1;
    overflow-y: auto;
}
.contact-list::-webkit-scrollbar { width: 4px; }
.contact-list::-webkit-scrollbar-thumb { background: #c4d9d2; border-radius: 4px; }

.contact-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 14px;
    border-bottom: 1px solid #f0f5f3;
    cursor: pointer;
    transition: background 0.15s;
    position: relative;
}
.contact-item:hover { background: #f5faf9; }
.contact-item.is-active { background: #edf6f2; border-left: 3px solid #3d8d7a; }
.contact-item.is-unread { background: #fffdf5; border-left: 3px solid #c47f00; }
.contact-item.is-active.is-unread { background: #edf6f2; border-left: 3px solid #3d8d7a; }

.contact-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    color: #fff;
    font-size: 0.85rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 2px;
}
.contact-info { flex: 1; min-width: 0; }
.contact-name    { font-size: 0.8rem; font-weight: 700; color: #16322e; margin-bottom: 2px; }
.contact-preview { font-size: 0.72rem; color: #6b8c85; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 2px; }
.contact-date    { font-size: 0.65rem; color: #9ab5ae; }

.unread-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #c47f00;
    flex-shrink: 0;
    margin-top: 6px;
}

.empty-list {
    text-align: center;
    padding: 40px 20px;
    color: #9ab5ae;
}
.empty-list i { font-size: 2rem; display: block; margin-bottom: 8px; }
.empty-list p  { font-size: 0.8rem; }

.split-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 8px;
    border-top: 1px solid #e4eeeb;
    flex-shrink: 0;
}
.page-btn {
    width: 28px; height: 28px; border-radius: 6px;
    border: 1px solid #dce8e4; background: #fff;
    color: #3d8d7a; display: flex; align-items: center;
    justify-content: center; cursor: pointer; font-size: 0.9rem;
}
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.page-btn:not(:disabled):hover { background: #3d8d7a; color: #fff; border-color: #3d8d7a; }
.page-indicator { font-size: 0.75rem; font-weight: 600; color: #16322e; }

/* ── Right Panel ── */
.split-right {
    display: flex;
    flex-direction: column;
    background: #fafcfb;
    overflow: hidden;
}

.no-selection {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #9ab5ae;
}
.no-selection i { font-size: 3rem; margin-bottom: 12px; }
.no-selection p { font-size: 0.85rem; }

.detail-view {
    display: flex;
    flex-direction: column;
    height: 100%;
}

/* Detail Header */
.detail-header {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 18px 22px;
    background: linear-gradient(to right, #cfe0d9, #edf6f2);
    border-bottom: 1px solid #c4d9d2;
    flex-shrink: 0;
}
.detail-avatar {
    width: 46px; height: 46px; border-radius: 50%;
    color: #fff; font-size: 1.1rem; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
}
.detail-sender { flex: 1; }
.detail-name  { font-size: 1rem; font-weight: 700; color: #16322e; margin-bottom: 3px; }
.detail-email, .detail-phone {
    font-size: 0.76rem; color: #4a6a62;
    display: flex; align-items: center; gap: 5px; margin-bottom: 2px;
}
.detail-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 6px; flex-shrink: 0; }
.detail-date { font-size: 0.7rem; color: #6b8c85; text-align: right; }

.status-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 9px; border-radius: 20px;
    font-size: 0.68rem; font-weight: 700;
}
.badge-read   { background: #d4edda; color: #155724; }
.badge-unread { background: #fff3cd; color: #856404; }

/* Detail Body */
.detail-body {
    flex: 1;
    padding: 20px 22px;
    overflow-y: auto;
}
.message-label {
    display: flex; align-items: center; gap: 6px;
    font-size: 0.75rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.4px; color: #3d8d7a; margin-bottom: 10px;
}
.message-text {
    font-size: 0.88rem; line-height: 1.75; color: #2e4d47;
    background: #fff; border: 1px solid #e0ece8;
    border-radius: 10px; padding: 16px 18px;
    white-space: pre-wrap; word-break: break-word;
    min-height: 120px;
}

/* Detail Actions */
.detail-actions {
    display: flex; gap: 10px; padding: 14px 22px; flex-wrap: wrap;
    border-top: 1px solid #e0ece8; flex-shrink: 0;
    background: #fff;
}
.msg-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 14px; border-radius: 8px;
    font-size: 0.78rem; font-weight: 600;
    cursor: pointer; border: 1px solid transparent;
    transition: all 0.18s;
    white-space: nowrap;
}
.btn-mark   { background: #3d8d7a; color: #fff; }
.btn-mark:hover { background: #2d6a5e; }
.btn-reply  { background: #fff; color: #3d8d7a; border-color: #c4d9d2; }
.btn-reply:hover { background: #edf6f2; }
.btn-delete { background: #fff; color: #c44040; border-color: #f5c0c0; }
.btn-delete:hover { background: #fdf0f0; }
</style>
