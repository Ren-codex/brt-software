<template>
    <div 
        v-if="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"
    >
        <div class="modal-container" ref="fullscreenTarget" @click.stop>
            <!-- Simple Header -->
            <div class="modal-header">
                <div class="header-title">
                    <i :class="editable ? 'ri-edit-box-line' : 'ri-user-add-line'"></i>
                    <h2>{{ editable ? 'Update Employee' : 'Add New Employee' }}</h2>
                </div>
                <div class="header-actions">
                    <!-- The form alone takes the whole screen; the page stays
                         behind it. Same look as the close button beside it. -->
                    <button type="button" class="close-btn" @click="toggleModalFullscreen"
                        :title="modalFullscreen ? 'Exit full screen' : 'Full screen'"
                        :aria-label="modalFullscreen ? 'Exit full screen' : 'Full screen'">
                        <i :class="modalFullscreen ? 'bx bx-exit-fullscreen' : 'bx bx-fullscreen'"></i>
                    </button>
                    <button type="button" class="close-btn" @click="hide" aria-label="Close">
                        <i class="ri-close-line"></i>
                    </button>
                </div>
            </div>

            <!-- Simple Form -->
            <div class="modal-body">
                <form id="employee-form" class="employee-form" @submit.prevent="submit">
                    <!-- Profile Picture - Simple -->
                    <div class="profile-section" :class="{ 'has-error': form.errors.avatar }">
                        <div class="profile-section-header">
                            <label>Profile Photo</label>
                            <span class="error-text" v-if="form.errors.avatar">{{ form.errors.avatar }}</span>
                        </div>
                        <div class="profile-picture" :class="{ 'error-border': form.errors.avatar }">
                            <img v-if="previewImage" :src="previewImage" alt="Profile">
                            <div v-else class="profile-placeholder">
                                <i class="ri-user-line"></i>
                            </div>
                        </div>
                        <div class="profile-actions">
                            <input
                                type="file"
                                ref="avatarInput"
                                @change="handleAvatarChange"
                                accept="image/*"
                                hidden
                            >
                            <button type="button" @click="$refs.avatarInput.click()" class="btn-outline">
                                <i class="ri-camera-line"></i>
                                {{ form.avatar ? 'Change' : 'Upload Photo' }}
                            </button>
                            <button type="button" v-if="form.avatar" @click="removeAvatar" class="btn-outline btn-outline-danger">
                                <i class="ri-delete-bin-line"></i>
                                Remove
                            </button>
                        </div>
                    </div>

                    <!-- One section at a time. A tab with a field the server
                         refused carries a red dot, and a failed save opens it. -->
                    <div class="section-tabs" role="tablist">
                        <button v-for="section in visibleSections" :key="section.key" type="button"
                            class="section-tab" :class="{ active: activeSection === section.key }"
                            role="tab" :aria-selected="activeSection === section.key"
                            @click="activeSection = section.key">
                            <i :class="section.icon"></i>
                            <span>{{ section.label }}</span>
                            <span v-if="sectionHasError(section)" class="section-tab-error" title="Has an error"></span>
                        </button>
                    </div>

                    <!-- Personal Information Section -->
                    <div class="form-section" v-show="activeSection === 'personal'">
                        <h3 class="section-title">
                            <i class="ri-user-info-line"></i>
                            Personal Information
                        </h3>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>First Name <span class="required">*</span></label>
                                <input
                                    type="text"
                                    v-model="form.firstname"
                                    :class="{ 'error': form.errors.firstname }"
                                    placeholder="Enter first name"
                                    @input="handleInput('firstname')"
                                >
                                <span class="error-text" v-if="form.errors.firstname">{{ form.errors.firstname }}</span>
                            </div>

                            <div class="form-group">
                                <label>Middle Name</label>
                                <input
                                    type="text"
                                    v-model="form.middlename"
                                    :class="{ 'error': form.errors.middlename }"
                                    placeholder="Enter middle name"
                                    @input="handleInput('middlename')"
                                >
                                <span class="error-text" v-if="form.errors.middlename">{{ form.errors.middlename }}</span>
                            </div>

                            <div class="form-group">
                                <label>Last Name <span class="required">*</span></label>
                                <input
                                    type="text"
                                    v-model="form.lastname"
                                    :class="{ 'error': form.errors.lastname }"
                                    placeholder="Enter last name"
                                    @input="handleInput('lastname')"
                                >
                                <span class="error-text" v-if="form.errors.lastname">{{ form.errors.lastname }}</span>
                            </div>

                            <div class="form-group">
                                <label>Suffix</label>
                                <input
                                    type="text"
                                    v-model="form.suffix"
                                    :class="{ 'error': form.errors.suffix }"
                                    placeholder="e.g., Jr., Sr., III"
                                    @input="handleInput('suffix')"
                                >
                                <span class="error-text" v-if="form.errors.suffix">{{ form.errors.suffix }}</span>
                            </div>

                            <div class="form-group">
                                <label>Birthdate <span class="required">*</span></label>
                                <input
                                    type="date"
                                    v-model="form.birthdate"
                                    :class="{ 'error': form.errors.birthdate }"
                                    @input="handleInput('birthdate')"
                                >
                                <span class="error-text" v-if="form.errors.birthdate">{{ form.errors.birthdate }}</span>
                            </div>

                            <div class="form-group">
                                <label>Sex <span class="required">*</span></label>
                                <select
                                    v-model="form.sex"
                                    :class="{ 'error': form.errors.sex }"
                                >
                                    <option value="" disabled selected>Select sex</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                                <span class="error-text" v-if="form.errors.sex">{{ form.errors.sex }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information Section -->
                    <div class="form-section" v-show="activeSection === 'contact'">
                        <h3 class="section-title">
                            <i class="ri-contacts-line"></i>
                            Contact Information
                        </h3>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Contact Number <span class="required">*</span></label>
                                <input
                                    type="tel"
                                    v-model="form.mobile"
                                    :class="{ 'error': form.errors.mobile }"
                                    placeholder="09XXXXXXXXX"
                                    maxlength="11"
                                    @input="handleInput('mobile')"
                                >
                                <span class="error-text" v-if="form.errors.mobile">{{ form.errors.mobile }}</span>
                            </div>

                            <div class="form-group">
                                <label>Email Address</label>
                                <input
                                    type="email"
                                    v-model="form.email"
                                    :class="{ 'error': form.errors.email }"
                                    placeholder="Enter email address"
                                    @input="handleInput('email')"
                                >
                                <span class="error-text" v-if="form.errors.email">{{ form.errors.email }}</span>
                            </div>

                            <div class="form-group full-width">
                                <label>Complete Address</label>
                                <input
                                    type="text"
                                    v-model="form.address"
                                    :class="{ 'error': form.errors.address }"
                                    placeholder="Enter complete address"
                                    @input="handleInput('address')"
                                >
                                <span class="error-text" v-if="form.errors.address">{{ form.errors.address }}</span>
                            </div>

                            <div class="form-group">
                                <label>Religion</label>
                                <input
                                    type="text"
                                    v-model="form.religion"
                                    :class="{ 'error': form.errors.religion }"
                                    placeholder="Enter religion"
                                    @input="handleInput('religion')"
                                >
                                <span class="error-text" v-if="form.errors.religion">{{ form.errors.religion }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Employment Details Section -->
                    <div class="form-section" v-show="activeSection === 'employment'">
                        <h3 class="section-title">
                            <i class="ri-briefcase-line"></i>
                            Employment Details
                        </h3>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Position <span class="required">*</span></label>
                                <select
                                    v-model="form.position_id"
                                    :class="{ 'error': form.errors.position_id }"
                                >
                                    <option :value="null" disabled selected>Select position</option>
                                    <option 
                                        v-for="position in dropdowns.positions" 
                                        :key="position.value" 
                                        :value="position.value"
                                    >
                                        {{ position.title }}
                                    </option>
                                </select>
                                <span class="error-text" v-if="form.errors.position_id">{{ form.errors.position_id }}</span>
                            </div>

                            <div class="form-group">
                                <label>Hours Per Day <span class="required">*</span></label>
                                <input
                                    type="number"
                                    v-model.number="form.hours_per_day"
                                    :class="{ 'error': form.errors.hours_per_day }"
                                    placeholder="8"
                                    min="0"
                                    max="24"
                                    step="0.5"
                                    @input="handleInput('hours_per_day')"
                                >
                                <span class="error-text" v-if="form.errors.hours_per_day">{{ form.errors.hours_per_day }}</span>
                            </div>

                            <div class="form-group">
                                <label>Overtime Rate (₱ / hour) <span class="required">*</span></label>
                                <input
                                    type="number"
                                    v-model.number="form.overtime_rate"
                                    :class="{ 'error': form.errors.overtime_rate }"
                                    placeholder="0.00"
                                    min="0"
                                    step="0.01"
                                    @input="handleInput('overtime_rate')"
                                >
                                <span class="error-text" v-if="form.errors.overtime_rate">{{ form.errors.overtime_rate }}</span>
                            </div>

                            <div class="form-group">
                                <label>Status</label>
                                <div class="toggle-wrapper">
                                    <label class="toggle-switch">
                                        <input type="checkbox" v-model="form.is_regular" :true-value="1" :false-value="0">
                                        <span class="toggle-slider"></span>
                                    </label>
                                    <span class="toggle-label">{{ form.is_regular ? 'Regular' : 'Probationary' }}</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Account Status</label>
                                <div class="toggle-wrapper">
                                    <label class="toggle-switch">
                                        <input type="checkbox" v-model="form.is_active" :true-value="1" :false-value="0">
                                        <span class="toggle-slider"></span>
                                    </label>
                                    <span class="toggle-label" :class="{ 'text-success': form.is_active, 'text-danger': !form.is_active }">
                                        {{ form.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Login account: only when adding, and only for someone who
                         may create accounts in User Management. -->
                    <div class="form-section" v-if="!editable && can('user_management', 'encoder')" v-show="activeSection === 'account'">
                        <h3 class="section-title">
                            <i class="ri-shield-user-line"></i>
                            Login Account
                        </h3>

                        <div class="form-group">
                            <label>Will this employee sign in to the system?</label>
                            <div class="toggle-wrapper">
                                <label class="toggle-switch">
                                    <input type="checkbox" v-model="form.create_account" :true-value="1" :false-value="0"
                                        :disabled="saveSuccess" @change="handleInput('create_account')">
                                    <span class="toggle-slider"></span>
                                </label>
                                <span class="toggle-label">
                                    {{ form.create_account ? 'Yes, create a login account' : 'No, no account needed' }}
                                </span>
                            </div>
                            <span class="error-text" v-if="form.errors.create_account">{{ form.errors.create_account }}</span>
                        </div>

                        <div class="form-grid account-fields" v-if="form.create_account">
                            <div class="form-group full-width">
                                <label>Roles <span class="required">*</span></label>
                                <!-- The same picker User Management uses for roles. -->
                                <Multiselect
                                    v-model="form.role_ids"
                                    :options="roleOptions"
                                    label="name"
                                    mode="tags"
                                    :searchable="true"
                                    :close-on-select="false"
                                    :disabled="saveSuccess"
                                    placeholder="Select roles"
                                    class="role-multiselect"
                                    :class="{ 'has-error': form.errors.role_ids }"
                                    @change="handleInput('role_ids')"
                                />
                                <span class="error-text" v-if="form.errors.role_ids">{{ form.errors.role_ids }}</span>
                            </div>
                            <!-- What the login will be, worked out from the name and
                                 birthdate above the same way the server makes it. -->
                            <div class="account-preview full-width">
                                <div class="account-preview-title">
                                    <i class="ri-information-line"></i>
                                    How their login is made
                                </div>
                                <div class="account-preview-row">
                                    <span class="account-preview-label">Username</span>
                                    <div v-if="usernamePreview">
                                        <code>{{ usernamePreview.username }}</code>
                                        <span class="account-preview-note">
                                            initials <b>{{ usernamePreview.initials }}</b>
                                            + birth month &amp; day <b>{{ usernamePreview.monthDay }}</b>
                                        </span>
                                    </div>
                                    <div v-else>
                                        <span class="account-preview-note">
                                            e.g. <b>Juan Reyes Santos</b>, born <b>March 29</b> &rarr; <code>jrs0329</code>.
                                            Fill in the name and birthdate to see theirs.
                                        </span>
                                    </div>
                                </div>
                                <div class="account-preview-row">
                                    <span class="account-preview-label">Password</span>
                                    <span class="account-preview-note">
                                        The default password, shown after saving. They must change it the first time they sign in.
                                    </span>
                                </div>
                                <small class="account-preview-foot">
                                    If the username is already taken, a number is added, e.g. <code>{{ (usernamePreview?.username || 'jrs0329') + '-2' }}</code>.
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Login account on edit: link an account that already exists
                         (or clear it), for someone who may manage accounts. -->
                    <div class="form-section" v-if="editable && can('user_management', 'encoder')" v-show="activeSection === 'account'">
                        <h3 class="section-title">
                            <i class="ri-shield-user-line"></i>
                            Login Account
                        </h3>

                        <div class="form-group">
                            <label>Account this employee signs in with</label>
                            <Multiselect
                                v-model="form.account_id"
                                :options="accountOptions"
                                label="name"
                                :searchable="true"
                                :can-clear="true"
                                :loading="accountsLoading"
                                :disabled="saveSuccess"
                                placeholder="No account linked — pick one"
                                no-options-text="Every account is already linked to an employee"
                                class="role-multiselect"
                                :class="{ 'has-error': form.errors.account_id }"
                                @change="handleInput('account_id')"
                            />
                            <span class="error-text" v-if="form.errors.account_id">{{ form.errors.account_id }}</span>
                        </div>

                        <div class="account-preview">
                            <div class="account-preview-title">
                                <i class="ri-information-line"></i>
                                {{ accountChange ? 'On save' : 'How this works' }}
                            </div>
                            <span v-if="accountChange === 'link'" class="account-preview-note">
                                <b>{{ chosenAccountName }}</b> is linked to this employee. Wherever that account is listed —
                                Payroll Clerk, Approved By and the like — it will show <b>{{ previewFullname }}</b>
                                instead of the username, and it can save its own profile.
                            </span>
                            <span v-else-if="accountChange === 'unlink'" class="account-preview-note">
                                <b>{{ originalAccount.username }}</b> is unlinked. That account goes back to showing its
                                username, and can no longer save a profile.
                            </span>
                            <span v-else class="account-preview-note">
                                Only accounts no other employee is linked to are listed. To give them a brand-new account,
                                use User Management &rarr; Add Account and pick this employee.
                            </span>
                        </div>
                    </div>

                    <transition name="fade">
                        <div class="submit-message submit-message-error" v-if="submitError" ref="submitError">
                            <div class="submit-message-icon">
                                <i class="ri-error-warning-line"></i>
                            </div>
                            <div class="submit-message-body">
                                <strong>Unable to save employee</strong>
                                <span>{{ submitError }}</span>
                            </div>
                        </div>
                    </transition>

                    <!-- Simple Success Message -->
                    <transition name="fade">
                        <div class="success-message" v-if="saveSuccess" ref="successMessage">
                            <div class="success-message-icon">
                                <i class="ri-checkbox-circle-line"></i>
                            </div>
                            <div class="success-message-body">
                                <strong>Employee saved</strong>
                                <span>{{ successMessage }}</span>
                                <!-- Kept on screen until closed, so the details can be passed on. -->
                                <div v-if="createdAccount" class="account-credentials">
                                    <div><span>Username</span><strong>{{ createdAccount.username }}</strong></div>
                                    <div><span>Default password</span><strong>{{ createdAccount.password }}</strong></div>
                                    <small>They will be asked to set a new password the first time they sign in.</small>
                                </div>
                            </div>
                        </div>
                    </transition>

                </form>
            </div>

            <!-- Outside the scrolling body so the buttons stay in view; the
                 submit button reaches the form through its id. -->
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" @click="hide">
                    {{ saveSuccess ? 'Close' : 'Cancel' }}
                </button>
                <button type="submit" form="employee-form" class="btn btn-primary" :disabled="form.processing || saveSuccess">
                    <i class="ri-save-line" v-if="!form.processing"></i>
                    <i class="ri-loader-4-line spinner" v-else></i>
                    {{ form.processing ? 'Saving...' : (editable ? 'Update Employee' : 'Save Employee') }}
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { useForm } from '@inertiajs/vue3';
import Multiselect from '@vueform/multiselect';
import { modalFullscreenMixin } from '@/Shared/modalFullscreen.js';

export default {
    components: { Multiselect },
    mixins: [modalFullscreenMixin],
    props: ['dropdowns'],
    data() {
        return {
            currentUrl: window.location.origin,
            form: useForm({
                id: null,
                firstname: null,
                middlename: null,
                lastname: null,
                suffix: null,
                email: null,
                mobile: null,
                birthdate: null,
                sex: null,
                religion: null,
                address: null,
                position_id: null,
                hours_per_day: 8,
                overtime_rate: 0,
                avatar: null,
                is_regular: 0,
                is_active: 1,
                is_blacklisted: 0,
                // The Login Account switch, and the roles the account gets.
                create_account: 0,
                role_ids: [],
                // Edit only: the account the employee is linked to. Always sent
                // as it stands, so leaving the picker alone changes nothing.
                account_id: null,
                option: 'lists'
            }),
            showModal: false,
            editable: false,
            saveSuccess: false,
            successMessage: 'Employee saved successfully!',
            submitError: '',
            previewImage: null,
            // { username, password } once an account has been made.
            createdAccount: null,
            // Edit: the account linked when the form opened, and the picker's list.
            originalAccount: null,
            accountOptions: [],
            accountsLoading: false,
            // The tab on show; see visibleSections.
            activeSection: 'personal',
        }
    },
    computed: {
        /** The form's tabs, with the fields each holds so an error can find its tab. */
        visibleSections() {
            return [
                { key: 'personal', label: 'Personal', icon: 'ri-user-info-line',
                    fields: ['firstname', 'middlename', 'lastname', 'suffix', 'birthdate', 'sex'] },
                { key: 'contact', label: 'Contact', icon: 'ri-contacts-line',
                    fields: ['mobile', 'email', 'address', 'religion'] },
                { key: 'employment', label: 'Employment', icon: 'ri-briefcase-line',
                    fields: ['position_id', 'hours_per_day', 'overtime_rate', 'is_regular', 'is_active'] },
                { key: 'account', label: 'Login Account', icon: 'ri-shield-user-line',
                    fields: ['create_account', 'role_ids', 'account_id', 'username'],
                    hidden: !this.can('user_management', 'encoder') },
            ].filter(section => !section.hidden);
        },
        roleOptions() {
            return Array.isArray(this.dropdowns?.roles) ? this.dropdowns.roles : [];
        },
        /** 'link', 'unlink', or null when the picker still shows what is linked. */
        accountChange() {
            const before = this.originalAccount?.id ?? null;
            const after = this.form.account_id ?? null;
            if (before === after) return null;
            return after === null ? 'unlink' : 'link';
        },
        chosenAccountName() {
            return this.accountOptions.find(option => option.value === this.form.account_id)?.name ?? '';
        },
        /** The name the account will show, built as Employee::getFullnameAttribute builds it. */
        previewFullname() {
            const middle = (this.form.middlename || '').trim();
            const name = [this.form.firstname, middle ? middle[0].toUpperCase() + '.' : '', this.form.lastname]
                .map(part => (part || '').trim())
                .filter(Boolean)
                .join(' ');
            return this.form.suffix ? `${name}, ${this.form.suffix}` : name;
        },
        /**
         * The username the account will get, made as UserClass::generateUsername
         * makes it: first, middle and last initials plus the birth month and day.
         * Null until the name and birthdate are in.
         */
        usernamePreview() {
            const first = (this.form.firstname || '').trim();
            const middle = (this.form.middlename || '').trim();
            const last = (this.form.lastname || '').trim();
            const [, month, day] = (this.form.birthdate || '').split('-');
            if (!first || !last || !month || !day) return null;

            const initials = first[0] + (middle ? middle[0] : '') + last[0];

            return {
                username: (initials + month + day).toLowerCase(),
                initials: initials.toUpperCase().split('').join(' · '),
                monthDay: month + day,
            };
        },
    },
    watch: {
        // The messages sit at the foot of a body that scrolls, under the fixed
        // buttons, so bring each into view when it appears.
        submitError(message) {
            if (message) this.$nextTick(() => this.$refs.submitError?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
        },
        saveSuccess(saved) {
            if (saved) this.$nextTick(() => this.$refs.successMessage?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
        },
        // Switching the account off drops the roles picked for it.
        'form.create_account'(on) {
            if (!on) {
                this.form.role_ids = [];
                this.form.errors.role_ids = false;
            }
        },
    },
    methods: {
        show() {
            this.form.reset();
            this.form.is_active = 1;
            this.form.is_regular = 0;
            this.form.create_account = 0;
            this.form.role_ids = [];
            this.form.account_id = null;
            this.activeSection = 'personal';
            this.originalAccount = null;
            this.createdAccount = null;
            this.previewImage = null;
            this.editable = false;
            this.saveSuccess = false;
            this.successMessage = 'Employee saved successfully!';
            this.submitError = '';
            this.showModal = true;
        },
        edit(data) {
            this.form.id = data.id;
            this.form.firstname = data.firstname;
            this.form.middlename = data.middlename;
            this.form.lastname = data.lastname;
            this.form.suffix = data.suffix;
            this.form.email = data.email;
            this.form.mobile = data.mobile;
            this.form.birthdate = data.birthdate;
            this.form.sex = data.sex;
            this.form.religion = data.religion;
            this.form.address = data.address;
            this.form.position_id = data.position_id;
            this.form.hours_per_day = data.hours_per_day ?? 8;
            this.form.overtime_rate = data.overtime_rate ?? 0;
            this.form.is_regular = data.is_regular;
            this.form.is_active = data.is_active;
            this.form.is_blacklisted = data.is_blacklisted;
            this.form.account_id = data.user?.id ?? null;
            this.originalAccount = data.user ? { id: data.user.id, username: data.user.username } : null;
            if (this.can('user_management', 'encoder')) this.loadAccountOptions();

            // Handle avatar preview - construct the correct storage path
            if (data.avatar) {
                // Check if avatar already has 'avatars/' prefix or just the filename
                const avatarPath = data.avatar.startsWith('avatars/') || data.avatar.startsWith('storage/') 
                    ? data.avatar 
                    : `avatars/${data.avatar}`;
                this.previewImage = `${this.currentUrl}/storage/${avatarPath}`;
            } else {
                this.previewImage = null;
            }
            
            this.activeSection = 'personal';
            this.editable = true;
            this.saveSuccess = false;
            this.successMessage = 'Employee updated successfully!';
            this.submitError = '';
            this.showModal = true;
        },
        submit() {
            this.saveSuccess = false;
            this.submitError = '';

            if (this.editable) {
                const linkChanged = this.accountChange !== null;
                this.form.put(`/employees/${this.form.id}`, {
                    preserveScroll: true,
                    onSuccess: (page) => {
                        const flash = page?.props?.flash || {};
                        if (flash.status === false) {
                            this.submitError = flash.info || flash.message || 'Failed to update employee. Please review the form and try again.';
                            return;
                        }

                        // A changed link is worth saying in so many words.
                        this.successMessage = (linkChanged && flash.info) || flash.message || 'Employee updated successfully!';
                        this.saveSuccess = true;
                        setTimeout(() => {
                            this.$emit('update', true);
                            this.hide();
                        }, 1500);
                    },
                    onError: (errors) => {
                        this.submitError = this.formatSubmitError(errors, 'Please check the highlighted fields and try again.');
                        this.openFirstErrorSection(errors);
                    },
                });
            } else {
                this.form.post('/employees', {
                    preserveScroll: true,
                    onSuccess: (page) => {
                        const flash = page?.props?.flash || {};
                        if (flash.status === false) {
                            this.submitError = flash.info || flash.message || 'Failed to save employee. Please review the form and try again.';
                            return;
                        }

                        this.successMessage = flash.message || 'Employee saved successfully!';
                        this.saveSuccess = true;

                        // An account was made: keep its login details on screen
                        // until the modal is closed, rather than for a second
                        // and a half.
                        const saved = flash.data?.data || flash.data || {};
                        if (flash.password && saved.user?.username) {
                            this.createdAccount = { username: saved.user.username, password: flash.password };
                            this.$emit('add', true);
                            return;
                        }

                        setTimeout(() => {
                            this.$emit('add', true);
                            this.hide();
                        }, 1500);
                    },
                    onError: (errors) => {
                        this.submitError = this.formatSubmitError(errors, 'Please check the highlighted fields and try again.');
                        this.openFirstErrorSection(errors);
                    },
                });
            }
        },
        sectionHasError(section) {
            return section.fields.some(field => this.form.errors[field]);
        },
        /** After a refused save, show the first tab holding a refused field. */
        openFirstErrorSection(errors) {
            const section = this.visibleSections.find(s => s.fields.some(field => errors?.[field]));
            if (section) this.activeSection = section.key;
        },
        /** Accounts nobody is linked to, plus this employee's own, fetched fresh each edit. */
        loadAccountOptions() {
            const own = this.originalAccount
                ? [{ value: this.originalAccount.id, name: `${this.originalAccount.username} (linked now)` }]
                : [];
            this.accountOptions = own;
            this.accountsLoading = true;

            axios.get('/employees', { params: { option: 'unlinked_accounts' } })
                .then(res => { this.accountOptions = [...own, ...(res.data || [])]; })
                .catch(() => { this.accountOptions = own; })
                .finally(() => { this.accountsLoading = false; });
        },
        handleInput(field) {
            this.form.errors[field] = false;
            if (this.submitError) {
                this.submitError = null;
            }
        },
        formatSubmitError(errors, fallbackMessage) {
            const messages = Object.values(errors || {})
                .flat()
                .filter(Boolean);

            if (!messages.length) {
                return fallbackMessage;
            }

            return messages.join(' | ');
        },
        handleAvatarChange(event) {
            const file = event.target.files[0];
            if (file) {
                this.form.avatar = file;
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.previewImage = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        },
        removeAvatar() {
            this.form.avatar = null;
            this.previewImage = null;
            if (this.$refs.avatarInput) {
                this.$refs.avatarInput.value = '';
            }
        },
        hide() {
            this.exitModalFullscreen();
            this.form.reset();
            this.form.clearErrors();
            this.createdAccount = null;
            this.previewImage = null;
            this.editable = false;
            this.saveSuccess = false;
            this.successMessage = 'Employee saved successfully!';
            this.submitError = '';
            this.showModal = false;
        },
    }
}
</script>

<style scoped>
.modal-overlay {
    --ink-900: var(--brt-c-102b26);
    --ink-700: var(--brt-c-2f5550);
    --ink-500: var(--brt-c-5d7d79);
    --line-200: var(--brt-c-d7e4e1);
    --line-100: var(--brt-c-e9f1ef);
    --mint-700: var(--brt-c-1e7e67);
    --mint-500: var(--brt-c-2da487);
    --mint-100: var(--brt-c-e8f8f3);
    --danger-500: #d65b4e;
    --danger-100: #fff2f0;
    z-index: 1050;
}

.modal-container {
    max-width: min(1320px, 100%);
    max-height: calc(100vh - 36px);
    overflow: hidden;
    font-family: "Poppins", "Segoe UI", sans-serif;
}

/* The header's full-screen button: the browser sizes it to the screen. */
.modal-container:fullscreen {
    max-height: none;
    border-radius: 0;
}

.modal-header {
    padding: 16px 22px;
    border-bottom: 1px solid var(--line-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(140deg, var(--brt-c-d7ece5) 0%, var(--brt-c-c7e2d9) 100%);
}

.header-title {
    display: flex;
    align-items: center;
    gap: 10px;
}

.header-title i {
    width: 38px;
    height: 38px;
    border-radius: 11px;
    display: grid;
    place-items: center;
    background: rgba(var(--brt-c-1a6857-rgb), 0.15);
    color: var(--brt-c-1a6857);
    font-size: 21px;
}

.header-title h2 {
    margin: 0;
    font-size: 1.16rem;
    color: var(--ink-900);
    font-weight: 700;
}

.close-btn {
    width: 34px;
    height: 34px;
    border: 0;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.75);
    color: var(--ink-700);
    font-size: 19px;
    display: grid;
    place-items: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.close-btn:hover {
    background: #fff;
    transform: rotate(90deg);
}

.modal-body {
    padding: 18px;
    overflow-y: auto;
}

.employee-form {
    display: grid;
    grid-template-columns: 320px minmax(0, 1fr);
    gap: 14px;
}

.profile-section {
    grid-column: 1;
    grid-row: 1 / span 4;
    border: 1px solid var(--line-200);
    border-radius: 18px;
    background: linear-gradient(165deg, #f8fffc 0%, var(--brt-c-f1faf7) 100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
    padding: 18px;
    position: sticky;
    top: 0;
}

.profile-section.has-error {
    border-color: #efb3ab;
}

.profile-section-header {
    width: 100%;
    text-align: center;
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.profile-section-header label {
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.06em;
    color: var(--ink-500);
    font-weight: 700;
}

.profile-picture {
    width: 130px;
    height: 130px;
    border-radius: 999px;
    overflow: hidden;
    border: 3px solid #fff;
    box-shadow: 0 14px 26px rgba(var(--brt-c-0e3029-rgb), 0.18);
}

.profile-picture.error-border {
    border-color: #efb3ab;
}

.profile-picture img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(145deg, var(--brt-c-2f967d) 0%, var(--brt-c-1f6656) 100%);
    display: grid;
    place-items: center;
    color: #fff;
    font-size: 40px;
}

.profile-actions {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

/* Personal | Contact | Employment | Login Account */
.section-tabs {
    grid-column: 2;
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    padding: 4px;
    border-radius: 14px;
    background: var(--brt-c-eaf2f0);
    align-self: start;
}

.section-tab {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 0;
    background: transparent;
    color: var(--brt-c-4a7a70);
    font-weight: 600;
    font-size: 0.82rem;
    padding: 0.5rem 0.95rem;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.18s ease;
    white-space: nowrap;
}

.section-tab i {
    font-size: 1rem;
}

.section-tab:hover:not(.active) {
    background: rgba(var(--brt-primary-rgb), 0.1);
    color: var(--brt-primary);
}

.section-tab.active {
    background: var(--brt-primary);
    color: #fff;
    box-shadow: 0 2px 8px rgba(var(--brt-primary-rgb), 0.28);
}

.section-tab-error {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--danger-500);
    box-shadow: 0 0 0 2px #fff;
}

.form-section {
    grid-column: 2;
    border: 1px solid var(--line-100);
    border-radius: 16px;
    background: #fff;
    padding: 16px;
    box-shadow: 0 8px 24px rgba(var(--brt-c-153931-rgb), 0.06);
}

.section-title {
    margin: 0 0 12px;
    padding-bottom: 10px;
    border-bottom: 1px dashed var(--brt-c-d6e4e1);
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    color: var(--brt-c-1f5149);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 700;
}

.section-title i {
    width: 24px;
    height: 24px;
    border-radius: 7px;
    display: grid;
    place-items: center;
    background: var(--mint-100);
    color: var(--mint-700);
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group label {
    font-size: 0.76rem;
    color: var(--ink-700);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-weight: 700;
}

.required {
    color: var(--danger-500);
}

.form-group input,
.form-group select {
    height: 40px;
    border: 1px solid var(--line-200);
    border-radius: 10px;
    background: #fbfefd;
    color: var(--brt-c-1a302c);
    padding: 0 11px;
    font-size: 0.86rem;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--mint-500);
    box-shadow: 0 0 0 3px rgba(var(--brt-c-2da487-rgb), 0.14);
}

.form-group input.error,
.form-group select.error {
    border-color: #e59c92;
    background: var(--danger-100);
}

.error-text {
    font-size: 0.73rem;
    color: var(--danger-500);
}

.account-fields {
    margin-top: 12px;
}

/* Matched to this form's inputs; picked roles show as removable tags. */
.role-multiselect {
    --ms-bg: #fbfefd;
    --ms-border-color: var(--line-200);
    --ms-border-color-active: var(--mint-500);
    --ms-radius: 10px;
    --ms-py: 0.42rem;
    --ms-px: 0.7rem;
    --ms-font-size: 0.86rem;
    --ms-ring-width: 3px;
    --ms-ring-color: rgba(var(--brt-c-2da487-rgb), 0.14);
    --ms-placeholder-color: var(--ink-500);
    --ms-tag-bg: var(--mint-700);
    --ms-tag-color: #fff;
    --ms-tag-radius: 999px;
    --ms-tag-font-weight: 600;
    --ms-dropdown-radius: 10px;
    --ms-dropdown-border-color: var(--line-200);
    --ms-option-font-size: 0.84rem;
    --ms-option-bg-pointed: var(--brt-c-f2fbf7);
    --ms-option-color-pointed: var(--ink-900);
    --ms-option-bg-selected: var(--mint-700);
    --ms-option-bg-selected-pointed: var(--mint-500);
    --ms-max-height: 14rem;
}

.role-multiselect.has-error {
    --ms-border-color: #e59c92;
    --ms-bg: var(--danger-100);
}

.account-preview {
    grid-column: 1 / -1;
    border: 1px solid var(--line-200);
    border-radius: 12px;
    background: var(--brt-c-f2fbf7);
    padding: 10px 12px;
    display: grid;
    gap: 6px;
    font-size: 0.78rem;
    color: var(--ink-700);
}

.account-preview-title {
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 700;
    color: var(--mint-700);
}

.account-preview-row {
    display: grid;
    grid-template-columns: 78px minmax(0, 1fr);
    gap: 8px;
    align-items: baseline;
}

.account-preview-label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--ink-500);
}

.account-preview-note {
    color: var(--ink-500);
}

.account-preview code {
    font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--ink-900);
    background: #fff;
    border: 1px solid var(--line-200);
    border-radius: 6px;
    padding: 1px 6px;
    margin-right: 6px;
}

.account-preview-foot {
    color: var(--ink-500);
    font-size: 0.72rem;
}

.account-preview-foot code {
    font-size: 0.72rem;
    font-weight: 600;
}

.account-credentials {
    margin-top: 8px;
    display: grid;
    gap: 4px;
    font-size: 0.82rem;
}

.account-credentials div {
    display: flex;
    justify-content: space-between;
    gap: 12px;
}

.account-credentials strong {
    font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
}

.password-input {
    position: relative;
    display: flex;
    align-items: center;
}

.password-input input {
    width: 100%;
    padding-right: 36px;
}

.password-toggle {
    position: absolute;
    right: 8px;
    border: 0;
    background: transparent;
    color: var(--brt-c-6f8b87);
    font-size: 1rem;
    cursor: pointer;
}

.password-toggle:hover {
    color: var(--mint-700);
}

.toggle-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 4px 0;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    inset: 0;
    cursor: pointer;
    border-radius: 26px;
    background-color: var(--brt-c-cedad7);
    transition: 0.25s;
}

.toggle-slider:before {
    content: "";
    position: absolute;
    width: 20px;
    height: 20px;
    left: 3px;
    bottom: 3px;
    border-radius: 50%;
    background: #fff;
    transition: 0.25s;
}

input:checked + .toggle-slider {
    background: linear-gradient(120deg, var(--brt-c-2ca386) 0%, #1b7c65 100%);
}

input:checked + .toggle-slider:before {
    transform: translateX(24px);
}

.toggle-label {
    font-size: 0.84rem;
    color: var(--ink-700);
    font-weight: 600;
}

.text-success {
    color: #1a8a61;
}

.text-danger {
    color: var(--danger-500);
}

.btn-outline {
    width: 100%;
    min-height: 38px;
    border: 1px solid var(--brt-c-cfe2de);
    border-radius: 10px;
    background: #fff;
    color: var(--ink-700);
    font-size: 0.83rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    cursor: pointer;
}

.btn-outline:hover {
    background: #f6fcfa;
}

.btn-outline-danger {
    border-color: #efc7c1;
    color: var(--danger-500);
}

.btn-outline-danger:hover {
    background: #fff5f4;
}

.success-message {
    grid-column: 2;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 14px 16px;
    border-radius: 14px;
    border: 1px solid var(--brt-c-9fdcc4);
    background: linear-gradient(120deg, #dbf9ed 0%, #effdf7 100%);
    color: var(--brt-c-1f6b4f);
    box-shadow: 0 8px 20px rgba(var(--brt-c-1f6b4f-rgb), 0.08);
}

.success-message-icon {
    width: 34px;
    height: 34px;
    min-width: 34px;
    border-radius: 10px;
    background: rgba(var(--brt-c-1f6b4f-rgb), 0.12);
    display: grid;
    place-items: center;
    font-size: 1rem;
    color: var(--brt-c-1f6b4f);
}

.success-message-body {
    display: flex;
    flex-direction: column;
    gap: 3px;
    line-height: 1.45;
}

.success-message-body strong {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--brt-c-16533b);
}

.success-message-body span {
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--brt-c-1f6b4f);
}

.submit-message {
    grid-column: 2;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 14px 16px;
    border-radius: 14px;
    font-size: 0.84rem;
    font-weight: 600;
}

.submit-message-error {
    border: 1px solid #efb8b0;
    background: linear-gradient(120deg, #fff1ee 0%, #fff9f8 100%);
    color: #982f26;
    box-shadow: 0 8px 20px rgba(163, 63, 53, 0.08);
}

.submit-message-icon {
    width: 34px;
    height: 34px;
    min-width: 34px;
    border-radius: 10px;
    background: rgba(214, 91, 78, 0.12);
    display: grid;
    place-items: center;
    font-size: 1rem;
}

.submit-message-body {
    display: flex;
    flex-direction: column;
    gap: 3px;
    line-height: 1.45;
}

.submit-message-body strong {
    font-size: 0.85rem;
    color: #7f2119;
}

.submit-message-body span {
    font-size: 0.8rem;
    font-weight: 500;
    color: #a33f35;
    white-space: normal;
    word-break: break-word;
}

.btn {
    min-height: 40px;
    padding: 0 17px;
    border-radius: 10px;
    border: 0;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.84rem;
    font-weight: 700;
    cursor: pointer;
}

.btn-primary {
    background: linear-gradient(120deg, var(--brt-c-2ca588) 0%, #1c7f67 100%);
    color: #fff;
    box-shadow: 0 10px 18px rgba(28, 122, 99, 0.28);
}

.btn-primary:hover:not(:disabled) {
    transform: translateY(-1px);
}

.btn-secondary {
    background: #fff;
    border: 1px solid var(--brt-c-d2dfdc);
    color: var(--ink-700);
}

.btn-secondary:hover {
    background: #f7fcfa;
}

.btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

@media (max-width: 1100px) {
    .employee-form {
        grid-template-columns: 1fr;
    }

    .profile-section {
        grid-column: 1;
        grid-row: auto;
        position: static;
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }

    .profile-section-header {
        text-align: left;
    }

    .profile-actions {
        width: auto;
        min-width: 190px;
    }

    .form-section,
    .section-tabs {
        grid-column: 1;
    }

    .submit-message,
    .success-message {
        grid-column: 1;
    }
}

@media (max-width: 700px) {
    .modal-container {
        width: 95%;
        border-radius: 18px;
    }

    .modal-body {
        padding: 14px;
    }

    .profile-section {
        flex-direction: column;
        text-align: center;
    }

    .profile-section-header {
        text-align: center;
    }

    .profile-actions {
        width: 100%;
        min-width: 0;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .form-group.full-width {
        grid-column: 1;
    }

    .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>
