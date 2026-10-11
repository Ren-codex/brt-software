<script setup>
import { router } from '@inertiajs/vue3';
import { layoutMethods } from '../../State/helpers';
const logout = () => {
  router.post('/logout');
};
</script>

<script>
import simplebar from "simplebar-vue";
import ModuleGuide from '@/Shared/Components/ModuleGuide/ModuleGuide.vue';
import { pollingMixin } from '@/Shared/polling.js';
import { onUserNotification, onApprovalChange } from '@/Shared/realtime.js';
export default {
  data() {
    return {
      currentUrl: window.location.origin,
      text: null,
      value: null,
      myVar: 1,
      notifications: [],
      unreadCount: 0,
      approvalCount: 0,
      notifTab: 'all',
      stopListening: null,
      stopApprovalListening: null,
    };
  },
  mixins: [pollingMixin],
  computed: {
    /**
     * What the bell shows: unread notifications, plus any approval still
     * waiting on you that no unread notification is already announcing.
     *
     * Reading a notification does not do the work it was about. Counting only
     * unread ones left the bell blank with requests still sitting on the
     * Approvals page.
     */
    bellCount() {
      const announced = this.notifications
        .filter(n => !n.read_at && (n.type || n.data?.type) === 'approval_request').length;
      return this.unreadCount + Math.max(0, this.approvalCount - announced);
    },
    visibleNotifications() {
      return this.notifTab === 'unread'
        ? this.notifications.filter(n => !n.read_at)
        : this.notifications;
    },
  },
  components: {
    simplebar,
    ModuleGuide
  },

  methods: {
    ...layoutMethods,
    toggleHamburgerMenu() {
      var windowSize = document.documentElement.clientWidth;
      let layoutType = document.documentElement.getAttribute("data-layout");

      document.documentElement.setAttribute("data-sidebar-visibility", "show");
      let visiblilityType = document.documentElement.getAttribute("data-sidebar-visibility");

      if (windowSize > 767)
        document.querySelector(".hamburger-icon").classList.toggle("open");

      //For collapse horizontal menu
      if (
        document.documentElement.getAttribute("data-layout") === "horizontal"
      ) {
        document.body.classList.contains("menu") ?
          document.body.classList.remove("menu") :
          document.body.classList.add("menu");
      }

      //For collapse vertical menu

      if (visiblilityType === "show" && (layoutType === "vertical" || layoutType === "semibox")) {
        if (windowSize < 1025 && windowSize > 767) {
          document.body.classList.remove("vertical-sidebar-enable");
          document.documentElement.getAttribute("data-sidebar-size") == "sm" ?
            document.documentElement.setAttribute("data-sidebar-size", "") :
            document.documentElement.setAttribute("data-sidebar-size", "sm");
        } else if (windowSize > 1025) {
          document.body.classList.remove("vertical-sidebar-enable");
          document.documentElement.getAttribute("data-sidebar-size") == "lg" ?
            document.documentElement.setAttribute("data-sidebar-size", "sm") :
            document.documentElement.setAttribute("data-sidebar-size", "lg");
        } else if (windowSize <= 767) {
          document.body.classList.add("vertical-sidebar-enable");
          document.documentElement.setAttribute("data-sidebar-size", "lg");
        }
      }

      //Two column menu
      if (document.documentElement.getAttribute("data-layout") == "twocolumn") {
        document.body.classList.contains("twocolumn-panel") ?
          document.body.classList.remove("twocolumn-panel") :
          document.body.classList.add("twocolumn-panel");
      }
    },
    toggleMenu() {
      this.$parent.toggleMenu();
    },
    toggleRightSidebar() {
      this.$parent.toggleRightSidebar();
    },
    initFullScreen() {
      document.body.classList.toggle("fullscreen-enable");
      if (
        !document.fullscreenElement &&
        /* alternative standard method */
        !document.mozFullScreenElement &&
        !document.webkitFullscreenElement
      ) {
        // current working methods
        if (document.documentElement.requestFullscreen) {
          document.documentElement.requestFullscreen();
        } else if (document.documentElement.mozRequestFullScreen) {
          document.documentElement.mozRequestFullScreen();
        } else if (document.documentElement.webkitRequestFullscreen) {
          document.documentElement.webkitRequestFullscreen(
            Element.ALLOW_KEYBOARD_INPUT
          );
        }
      } else {
        if (document.cancelFullScreen) {
          document.cancelFullScreen();
        } else if (document.mozCancelFullScreen) {
          document.mozCancelFullScreen();
        } else if (document.webkitCancelFullScreen) {
          document.webkitCancelFullScreen();
        }
      }
    },
    toggleDarkMode() {

      if (document.documentElement.getAttribute("data-bs-theme") == "dark") {
        document.documentElement.setAttribute("data-bs-theme", "light");
      } else {
        document.documentElement.setAttribute("data-bs-theme", "dark");
      }

      const mode = document.documentElement.getAttribute("data-bs-theme")
      this.changeMode({
        mode: mode,
      });
    },
    removeItem(cartItem) {
      this.cartItems = this.cartItems.filter(item => item.id !== cartItem.id)
      this.$emit("cart-item-price", this.cartItems.length);
    },
    openInNewTab(url) {
      window.open(url, '_blank');
    },
    fetchNotifications() {
        return axios.get('/notifications')
            .then(res => {
                this.notifications = res.data.notifications;
                this.unreadCount   = res.data.unread_count;
                this.approvalCount = res.data.approval_count ?? 0;
            })
            .catch(() => {});
    },
    markAllRead() {
        axios.patch('/notifications/read-all')
            .then(() => {
                this.unreadCount = 0;
                this.notifications = this.notifications.map(n => ({ ...n, read_at: new Date().toISOString() }));
            })
            .catch(() => {});
    },
    /**
     * Clicking a notification goes where it points and marks only that one read.
     *
     * The panel used to mark *everything* read simply on opening, which made the
     * bell useless for approvals — glancing at it cleared the record of what you
     * still had to action.
     */
    openNotification(n) {
        if (!n.read_at && n.id) {
            axios.patch(`/notifications/${n.id}/read`).catch(() => {});
            n.read_at = new Date().toISOString();
            this.unreadCount = Math.max(0, this.unreadCount - 1);
        }

        const url = n.data?.url;
        if (url) {
            this.$inertia.visit(url);
        }
    },
    /** Heading shown for each notification type in the panel. */
    notificationTitle(n) {
        const d = n.data || {};
        switch (d.type) {
            case 'approval_request':
                return d.resolved
                    ? `${d.source_label} request actioned`
                    : `${d.source_label} awaiting approval`;
            case 'low_balance':                 return `${d.fund_name} is low`;
            case 'low_stock':                   return `${d.product_name} stock is low`;
            case 'overdue_invoice':             return `${d.invoice_number} is overdue`;
            case 'unpaid_same_day_sales_order': return `${d.so_number} still unpaid today`;
            case 'bounced_check':               return `Check ${d.check_number} bounced`;
            case 'outstanding_deliveries':      return 'Outstanding deliveries';
            default:                            return 'Notification';
        }
    },
    /** Supporting line under the heading. */
    notificationDetail(n) {
        const d = n.data || {};
        switch (d.type) {
            case 'approval_request':
                return [d.reference, d.summary, d.amount != null ? this.peso(d.amount) : null]
                    .filter(Boolean).join(' · ');
            case 'low_balance':
                return `Balance ${this.peso(d.balance)} · threshold ${this.peso(d.threshold)}`;
            case 'low_stock':
                return `${d.current_stock} remaining · minimum ${d.minimum_stock}`;
            case 'overdue_invoice':
                return `${d.customer_name} · ${this.peso(d.balance_due)}`;
            case 'unpaid_same_day_sales_order':
                return `${d.customer_name} · ${d.payment_mode} · ${this.peso(d.balance_due)}`;
            case 'bounced_check':
                return [d.customer_name, this.peso(d.amount), d.bounce_reason].filter(Boolean).join(' · ');
            case 'outstanding_deliveries':
                return d.message;
            default:
                return '';
        }
    },
    /** Left-hand icon, tinted by how much the notification wants attention. */
    notificationIcon(n) {
        const type = n.data?.type;
        if (type === 'approval_request') return { icon: 'ri-checkbox-circle-line', tone: 'tone-approval' };
        if (type === 'low_stock')        return { icon: 'ri-box-3-line',           tone: 'tone-warn' };
        if (type === 'low_balance')      return { icon: 'ri-wallet-3-line',        tone: 'tone-warn' };
        if (type === 'bounced_check')    return { icon: 'ri-error-warning-line',   tone: 'tone-danger' };
        if (type === 'overdue_invoice')  return { icon: 'ri-time-line',            tone: 'tone-danger' };
        return { icon: 'ri-notification-3-line', tone: 'tone-info' };
    },
    peso(value) {
        return '₱' + Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });
    },
    timeAgo(dateString) {
        if (!dateString) return '';
        const diff = Math.floor((Date.now() - new Date(dateString)) / 1000);
        if (diff < 60)    return diff + 's ago';
        if (diff < 3600)  return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return Math.floor(diff / 86400) + 'd ago';
    },
  },

  mounted() {
    document.addEventListener("scroll", function () {
      var pageTopbar = document.getElementById("page-topbar");
      if (pageTopbar) {
        document.body.scrollTop >= 50 || document.documentElement.scrollTop >= 50 ? pageTopbar.classList.add(
          "topbar-shadow") : pageTopbar.classList.remove("topbar-shadow");
      }
    });
    if (document.getElementById("topnav-hamburger-icon"))
      document.getElementById("topnav-hamburger-icon").addEventListener("click", this.toggleHamburgerMenu);

    this.fetchNotifications();
    // The bell is database-backed, so polling picks up anything raised while
    // the user was elsewhere. Echo only makes it arrive sooner — see realtime.js
    // for why delivery is never relied on.
    this.startPolling(() => this.fetchNotifications(), 10000);

    // No type filter: the panel renders every type it knows and falls back to a
    // generic row for anything new, so filtering here would only drop
    // notifications the panel could already have shown.
    this.stopListening = onUserNotification(this, notification => {
        this.unreadCount++;
        if (notification.type === 'approval_request') {
            this.approvalCount++;
        }
        this.notifications.unshift({
            id:         notification.id || null,
            type:       notification.type,
            data:       notification,
            read_at:    null,
            created_at: new Date().toISOString(),
        });
    });

    // A request was raised or actioned — here, on another screen, or by another
    // approver. Refetch rather than patch the list, so the badge, the pinned
    // row and the rows themselves can never disagree with the server.
    this.stopApprovalListening = onApprovalChange(this, () => this.fetchNotifications());
  },
  beforeUnmount() {
    this.stopListening?.();
    this.stopApprovalListening?.();
  },
};
</script>

<template>
  <header id="page-topbar">
    <div class="layout-width">
      <div class="navbar-header">
        <div class="d-flex">
          <!-- LOGO -->
          <div class="navbar-brand-box horizontal-logo">
            <Link href="/" class="logo logo-dark">
              <span class="logo-sm">
                <img src="@assets/images/official-logo.png" alt="" height="22" />
              </span>
              <span class="logo-lg">
                <img src="@assets/images/logo-dark.png" alt="" height="25" />
              </span>
            </Link>

            <Link href="/" class="logo logo-light">
              <span class="logo-sm">
                <img src="@assets/images/official-logo.png" alt="" height="22" />
              </span>
              <span class="logo-lg">
                <img src="@assets/images/logo-light.png" alt="" height="25" />
              </span>
            </Link>
          </div>

          <button type="button" class="btn btn-sm px-3 fs-16 header-item vertical-menu-btn topnav-hamburger"
            id="topnav-hamburger-icon">
            <span class="hamburger-icon">
              <span></span>
              <span></span>
              <span></span>
            </span>
          </button>

        </div>

        <div class="d-flex align-items-center">
          <BDropdown class="dropdown d-md-none topbar-head-dropdown header-item" variant="ghost-secondary" dropstart
            :offset="{ alignmentAxis: 55, crossAxis: 15, mainAxis: 0 }"
            toggle-class="btn-icon btn-topbar rounded-circle arrow-none"
            menu-class="dropdown-menu-lg dropdown-menu-end p-0">
            <template #button-content>
              <i class="bx bx-search fs-22"></i>
            </template>
            <BDropdownItem aria-labelledby="page-header-search-dropdown">
              <form class="p-3">
                <div class="form-group m-0">
                  <div class="input-group">
                    <input type="text" class="form-control" placeholder="Search ..." aria-label="Recipient's username" />
                    <BButton variant="primary" type="submit">
                      <i class="mdi mdi-magnify"></i>
                    </BButton>
                  </div>
                </div>
              </form>
            </BDropdownItem>
          </BDropdown>

          <!-- Opens the guide for whichever module the user is in. -->
          <div class="ms-1 header-item">
            <BButton type="button" variant="ghost-secondary" class="btn-icon btn-topbar rounded-circle"
              v-b-tooltip.hover title="Guide for this page" @click="$refs.moduleGuide.show()">
              <i class="ri-book-open-line fs-22"></i>
            </BButton>
          </div>

          <div class="ms-1 header-item d-none d-sm-flex">
            <BButton type="button" variant="ghost-secondary" class="btn-icon btn-topbar rounded-circle"
              data-toggle="fullscreen" @click="initFullScreen">
              <i class="bx bx-fullscreen fs-22"></i>
            </BButton>
          </div>
<!-- 
          <div class="ms-1 header-item d-none d-sm-flex">
            <BButton type="button" variant="ghost-secondary" class="btn-icon btn-topbar rounded-circle light-dark-mode"
              @click="toggleDarkMode">
              <i class="bx bx-moon fs-22"></i>
            </BButton>
          </div> -->

          <!-- Notification panel.
               `end` + `no-size` matter: without `end` the menu is positioned
               from the bell's left edge, and the dropdown's size middleware then
               writes an inline max-width equal to the sliver of screen to the
               right of the bell — which silently beat the CSS width and squeezed
               the panel to ~190px.

               Opening it does not mark everything read: each row is marked when
               it is clicked, so the bell keeps working as a list of what still
               needs doing. -->
          <BDropdown class="ms-1 header-item" variant="ghost-secondary" end no-size
            toggle-class="btn-icon btn-topbar rounded-circle arrow-none position-relative"
            menu-class="dropdown-menu-end notif-panel p-0">
            <template #button-content>
              <i class="bx bxs-bell fs-22"></i>
              <span v-if="bellCount > 0"
                class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger fs-10"
                style="padding: 2px 5px;">
                {{ bellCount > 9 ? '9+' : bellCount }}
              </span>
            </template>

            <div class="notif-head">
              <h4 class="notif-head-title">Notifications</h4>
              <a href="#" class="notif-head-action" v-if="unreadCount > 0" @click.prevent="markAllRead">
                Mark all as read
              </a>
            </div>

            <div class="notif-tabs">
              <button type="button" class="notif-tab" :class="{ active: notifTab === 'all' }"
                @click.stop="notifTab = 'all'">All</button>
              <button type="button" class="notif-tab" :class="{ active: notifTab === 'unread' }"
                @click.stop="notifTab = 'unread'">
                Unread <span v-if="unreadCount > 0">({{ unreadCount }})</span>
              </button>
            </div>

            <simplebar class="notif-scroll">
              <!-- Pinned while anything is pending. Counts live records, so it
                   shows even for requests raised before notifications existed. -->
              <Link v-if="approvalCount > 0" href="/approvals" class="notif-item notif-pinned">
                <span class="notif-avatar tone-approval">
                  <i class="ri-checkbox-circle-fill"></i>
                </span>
                <span class="notif-body">
                  <span class="notif-text">
                    <strong>{{ approvalCount }} {{ approvalCount === 1 ? 'request is' : 'requests are' }}</strong>
                    waiting on your approval
                  </span>
                  <span class="notif-time is-unread">Review &amp; approve</span>
                </span>
                <i class="ri-arrow-right-s-line notif-chevron"></i>
              </Link>

              <div class="notif-section-label" v-if="visibleNotifications.length">
                {{ notifTab === 'unread' ? 'Unread' : 'Earlier' }}
              </div>

              <button v-for="n in visibleNotifications" :key="n.id || n.created_at"
                type="button"
                class="notif-item"
                :class="{ unread: !n.read_at }"
                @click="openNotification(n)">
                <span class="notif-avatar" :class="notificationIcon(n).tone">
                  <i :class="notificationIcon(n).icon"></i>
                </span>
                <span class="notif-body">
                  <span class="notif-text">
                    <strong>{{ notificationTitle(n) }}</strong>
                    <span v-if="notificationDetail(n)"> &mdash; {{ notificationDetail(n) }}</span>
                  </span>
                  <span class="notif-time" :class="{ 'is-unread': !n.read_at }">{{ timeAgo(n.created_at) }}</span>
                </span>
                <span class="notif-dot" v-if="!n.read_at"></span>
              </button>

              <div v-if="!visibleNotifications.length" class="notif-empty">
                <i class="ri-notification-3-line"></i>
                <p class="mb-0">
                  {{ notifTab === 'unread' ? "You're all caught up" : 'No notifications yet' }}
                </p>
              </div>
            </simplebar>

            <div class="notif-foot">
              <Link href="/approvals" class="notif-foot-link">See all approvals</Link>
            </div>
          </BDropdown>

          <BDropdown variant="link" class="ms-sm-3 me-3 header-item topbar-user" toggle-class="arrow-none" menu-class="dropdown-menu-end" :offset="{ alignmentAxis: -14, crossAxis: 0, mainAxis: 0 }">
            <template #button-content>
              <span class="d-flex align-items-center">
                <img v-if="$page.props.user.data.avatar" class="rounded-circle header-profile-user" :src="$page.props.user.data.avatar" :alt="$page.props.user.data.username">
                <span v-else class="avatar-title rounded-circle bg-primary-subtle text-primary fs-20">
                  <i class="bx bx-user"></i>
                </span>
                <span class="text-start ms-xl-2">
                  <span class="d-none d-xl-inline-block ms-1 fw-medium user-name-text">{{ $page.props.user.data.name }}</span>
                  <span class="d-none d-xl-block ms-1 fs-12 user-name-sub-text">{{ $page.props.user.data.position}}</span>
                </span>
              </span>
            </template>
            <h6 class="dropdown-header fs-10">Welcome {{ $page.props.user.data.username }}!</h6>
            <Link class="dropdown-item" href="/profile"><i class="mdi mdi-account-circle text-muted fs-16 align-middle me-1"></i>
            <span class="align-middle"> Profile</span>
            </Link>
              <!-- <Link class="dropdown-item" href="/DTR"><i class="ri-calendar-todo-fill text-muted fs-16 align-middle me-1"></i>
            <span class="align-middle"> DTR</span>
            </Link> -->
            <div class="dropdown-divider"></div>
            <Link class="dropdown-item" href="/confirm-password"><i class="mdi mdi-lock text-muted fs-16 align-middle me-1"></i>
            <span class="align-middle"> Lock screen</span>
            </Link>

            <!-- Authentication -->
            <form method="POST" @submit.prevent="logout" class="dropdown-item">
              <BButton variant="none" type="submit" class="btn p-0"><i class="mdi mdi-logout text-muted fs-16 align-middle me-1"></i> Logout</BButton>
            </form>
          </BDropdown>
          
        </div>
      </div>
    </div>
  </header>
  <ModuleGuide ref="moduleGuide" />
</template>
