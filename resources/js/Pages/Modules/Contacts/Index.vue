<template>
    <div>
        <PageHeader title="Contact Management" pageTitle="List" />
        <BRow>
            <div class="col-md-12">
                <div class="library-card">
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
                        <!-- Search and Filter -->
                        <div class="search-section">
                            <div class="search-wrapper">
                                <i class="ri-search-line search-icon"></i>
                                <input type="text" v-model="filter.keyword" @input="updateKeyword($event.target.value)"
                                    placeholder="Search contacts..." class="search-input">
                            </div>
                            <div class="filter-wrapper">
                                <select v-model="filter.unread" @change="fetch" class="form-select">
                                    <option :value="null">All Messages</option>
                                    <option :value="true">Unread</option>
                                    <option :value="false">Read</option>
                                </select>
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
                                <tbody>
                                    <tr v-for="(list, index) in lists" :key="index" :class="{'unread-row': !list.is_read}">
                                        <td>{{ index + 1 }}</td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-xs me-2">
                                                    <div class="avatar-title rounded-circle bg-light text-dark">
                                                        {{ list.name.charAt(0).toUpperCase() }}
                                                    </div>
                                                </div>
                                                <span class="fw-medium">{{ list.name }}</span>
                                            </div>
                                        </td>
                                        <td>{{ list.email }}</td>
                                        <td>{{ list.phone || '-' }}</td>
                                        <td>
                                            <span class="text-truncate d-inline-block" style="max-width: 200px;" :title="list.message">
                                                {{ list.message }}
                                            </span>
                                        </td>
                                        <td>{{ list.created_at }}</td>
                                        <td>
                                            <span v-if="list.is_read" class="badge bg-success">Read</span>
                                            <span v-else class="badge bg-warning text-dark">Unread</span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <BButton @click="viewMessage(list)" variant="info" size="sm" v-b-tooltip.hover title="View">
                                                    <i class="ri-eye-line"></i>
                                                </BButton>
                                                <BButton @click="deleteContact(list.id)" variant="danger" size="sm" v-b-tooltip.hover title="Delete">
                                                    <i class="ri-delete-bin-line"></i>
                                                </BButton>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="lists.length === 0">
                                        <td colspan="8" class="text-center py-4">
                                            <div class="d-flex flex-column align-items-center">
                                                <i class="ri-inbox-line fs-48 text-muted mb-2"></i>
                                                <p class="text-muted">No contact messages found</p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="text-muted">
                                Showing {{ lists.length }} of {{ meta.total }} results
                            </div>
                            <div v-if="meta.last_page > 1">
                                <BButtonGroup>
                                    <BButton 
                                        v-for="(link, index) in links" 
                                        :key="index"
                                        :variant="link.active ? 'primary' : 'outline-secondary'"
                                        :disabled="!link.url"
                                        @click="changePage(link.url)"
                                        size="sm"
                                    >
                                        <span v-html="link.label"></span>
                                    </BButton>
                                </BButtonGroup>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </BRow>

        <!-- View Message Modal -->
        <BModal v-model="showViewModal" :title="selectedContact?.name" size="lg" centered>
            <div v-if="selectedContact">
                <div class="mb-3">
                    <label class="fw-bold">Email:</label>
                    <p>{{ selectedContact.email }}</p>
                </div>
                <div class="mb-3">
                    <label class="fw-bold">Phone:</label>
                    <p>{{ selectedContact.phone || 'Not provided' }}</p>
                </div>
                <div class="mb-3">
                    <label class="fw-bold">Date:</label>
                    <p>{{ selectedContact.created_at }}</p>
                </div>
                <div class="mb-3">
                    <label class="fw-bold">Message:</label>
                    <div class="p-3 bg-light rounded">
                        {{ selectedContact.message }}
                    </div>
                </div>
            </div>
            <template #modal-footer>
                <BButton variant="secondary" @click="showViewModal = false">Close</BButton>
                <BButton variant="primary" @click="markAsRead" v-if="selectedContact && !selectedContact.is_read">
                    <i class="ri-check-line me-1"></i> Mark as Read
                </BButton>
            </template>
        </BModal>

        <!-- Delete Confirmation Modal -->
        <DeleteModal ref="deleteModal" @confirm="confirmDelete" />
    </div>
</template>

<script>
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
            showViewModal: false,
            selectedContact: null,
            deleteId: null,
        };
    },
    mounted() {
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
            this.showViewModal = true;
        },
        
        markAsRead() {
            if (!this.selectedContact) return;
            
            axios.patch(`/contacts/${this.selectedContact.id}/mark-read`)
            .then(response => {
                this.$toast.success('Marked as read');
                this.fetch();
                this.selectedContact.is_read = true;
            })
            .catch(err => {
                console.log(err);
                this.$toast.error('Failed to mark as read');
            });
        },
        
        deleteContact(id) {
            this.deleteId = id;
            this.$refs.deleteModal.show(
                'Delete Contact',
                'Are you sure you want to delete this contact message?'
            );
        },
        
        confirmDelete() {
            if (!this.deleteId) return;
            
            axios.delete(`/contacts/${this.deleteId}`)
            .then(response => {
                this.$toast.success('Contact deleted successfully');
                this.fetch();
            })
            .catch(err => {
                console.log(err);
                this.$toast.error('Failed to delete contact');
            });
        },
    }
}
</script>

<style scoped>
.library-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.library-card-header {
    padding: 24px 24px 0;
}

.header-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.header-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: #2d3748;
}

.header-subtitle {
    font-size: 0.875rem;
    color: #718096;
}

.create-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
}

.create-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.create-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.search-section {
    display: flex;
    gap: 16px;
    margin-bottom: 20px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.search-wrapper {
    flex: 1;
    position: relative;
}

.search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #a0aec0;
}

.search-input {
    width: 100%;
    padding: 10px 10px 10px 40px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.875rem;
    transition: all 0.2s ease;
}

.search-input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.filter-wrapper .form-select {
    padding: 10px 16px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.875rem;
}

.table-card {
    margin-top: 0;
}

.table thead th {
    background: #f8f9fa;
    font-weight: 600;
    color: #4a5568;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #e2e8f0;
    padding: 12px;
}

.table td {
    padding: 12px;
    vertical-align: middle;
    font-size: 0.875rem;
}

.unread-row {
    background: #fef3c7 !important;
}

.unread-row:hover {
    background: #fde68a !important;
}

.badge {
    padding: 6px 10px;
    font-size: 0.75rem;
    font-weight: 500;
}

.avatar-xs {
    width: 32px;
    height: 32px;
}

.avatar-title {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.875rem;
}
</style>
