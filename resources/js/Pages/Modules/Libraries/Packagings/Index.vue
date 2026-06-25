<template>
<Head title="Packaging Management"/>
    <PageHeader title="Packaging Management" pageTitle="List" />
    <BRow>
        <div class="col-md-12">
            <div class="library-card">
                <div class="library-card-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="header-icon">
                            <i class="ri-archive-2-line"></i>
                        </div>
                        <div>
                            <h4 class="header-title mb-1">Packaging Management</h4>
                            <p class="header-subtitle mb-0">Manage packaging types (sack, bag, box, etc.)</p>
                        </div>
                    </div>
                    <button class="create-btn" @click="openCreate">
                        <i class="ri-add-line"></i>
                        <span>Add Packaging</span>
                    </button>
                </div>

                <div class="library-card-body">
                    <div class="search-section">
                        <div class="search-wrapper">
                            <i class="ri-search-line search-icon"></i>
                            <input
                                type="text"
                                v-model="filter.keyword"
                                placeholder="Search packagings..."
                                class="search-input"
                            >
                        </div>
                    </div>

                    <div class="table-section">
                        <div class="table-responsive">
                            <table class="table align-middle table-centered mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(list, index) in lists"
                                        :key="index"
                                        @click="selectRow(index)"
                                        :class="{ 'bg-info-subtle': index === selectedRow }"
                                    >
                                        <td>{{ index + 1 }}</td>
                                        <td>{{ list.name }}</td>
                                        <td>{{ list.description }}</td>
                                        <td>
                                            <div class="action-buttons">
                                                <button @click="openEdit(list, index)" class="action-btn action-btn-edit" v-b-tooltip.hover title="Edit">
                                                    <i class="ri-pencil-line"></i>
                                                </button>
                                                <button @click="onDelete(list, index)" class="action-btn action-btn-delete" v-b-tooltip.hover title="Delete">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="pagination-section">
                        <Pagination v-if="meta" @fetch="fetch" :lists="lists.length" :links="links" :pagination="meta" />
                    </div>
                </div>
            </div>
        </div>
    </BRow>
    <Create @add="fetch()" ref="create" />
</template>

<script>
import _ from 'lodash';
import PageHeader from '@/Shared/Components/PageHeader.vue';
import Pagination from '@/Shared/Components/Pagination.vue';
import Create from './Modals/Create.vue';
import Swal from 'sweetalert2';

export default {
    components: { PageHeader, Pagination, Create },
    data() {
        return {
            currentUrl: window.location.origin,
            lists: [],
            meta: {},
            links: {},
            filter: { keyword: null },
            selectedRow: null,
        };
    },
    watch: {
        'filter.keyword'(newVal) {
            this.checkSearchStr(newVal);
        },
    },
    created() {
        this.fetch();
    },
    methods: {
        checkSearchStr: _.debounce(function () {
            this.fetch();
        }, 300),

        fetch(page_url) {
            page_url = page_url || '/libraries/packagings';
            axios.get(page_url, {
                params: {
                    keyword: this.filter.keyword,
                    count: 10,
                    option: 'lists',
                },
            })
            .then(response => {
                if (response) {
                    this.lists = response.data.data;
                    this.meta  = response.data.meta;
                    this.links = response.data.links;
                }
            })
            .catch(err => console.log(err));
        },

        openCreate() {
            this.$refs.create.show();
        },

        openEdit(data, index) {
            this.selectedRow = index;
            this.$refs.create.edit(data, index);
        },

        async onDelete(list, index) {
            const result = await Swal.fire({
                title: 'Are you sure?',
                text: 'You want to delete this packaging?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!',
            });

            if (result.isConfirmed) {
                axios.delete(`/libraries/packagings/${list.id}`)
                    .then(() => {
                        this.fetch();
                        Swal.fire('Deleted!', 'Packaging deleted successfully!', 'success');
                    })
                    .catch(() => {
                        Swal.fire('Error!', 'Failed to delete packaging.', 'error');
                    });
            }
        },

        selectRow(index) {
            this.selectedRow = this.selectedRow === index ? null : index;
        },
    },
};
</script>
