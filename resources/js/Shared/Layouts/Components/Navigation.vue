<script setup>
import { router } from '@inertiajs/vue3';
import { layoutMethods } from '../../State/helpers';
const logout = () => {
  router.post('/logout');
};
</script>

<script>
import simplebar from "simplebar-vue";
export default {
  data() {
    return {
      currentUrl: window.location.origin,
      text: null,
      value: null,
      myVar: 1,
      notifications: [],
      unreadCount: 0,
    };
  },
  components: {
    simplebar
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
        axios.get('/notifications')
            .then(res => {
                this.notifications = res.data.notifications;
                this.unreadCount   = res.data.unread_count;
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
    const userId = this.$page?.props?.user?.data?.id;
    if (userId && window.Echo) {
        window.Echo.private('App.Models.User.' + userId)
            .notification(notification => {
                if (['low_balance', 'low_stock', 'overdue_invoice'].includes(notification.type)) {
                    this.unreadCount++;
                    this.notifications.unshift({
                        id:         notification.id || null,
                        type:       notification.type,
                        data:       notification,
                        read_at:    null,
                        created_at: new Date().toISOString(),
                    });
                }
            });
    }
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
                <img src="@assets/images/logo-sm.png" alt="" height="22" />
              </span>
              <span class="logo-lg">
                <img src="@assets/images/logo-dark.png" alt="" height="25" />
              </span>
            </Link>

            <Link href="/" class="logo logo-light">
              <span class="logo-sm">
                <img src="@assets/images/logo-sm.png" alt="" height="22" />
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

          <!-- Notification Bell -->
          <BDropdown class="ms-1 header-item" variant="ghost-secondary"
            toggle-class="btn-icon btn-topbar rounded-circle arrow-none position-relative"
            menu-class="dropdown-menu-end dropdown-menu-lg p-0"
            @show="markAllRead">
            <template #button-content>
              <i class="bx bxs-bell fs-22"></i>
              <span v-if="unreadCount > 0"
                class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger fs-10"
                style="padding: 2px 5px;">
                {{ unreadCount > 9 ? '9+' : unreadCount }}
              </span>
            </template>

            <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
              <h6 class="mb-0">Notifications</h6>
              <span class="badge bg-danger-subtle text-danger" v-if="unreadCount > 0">{{ unreadCount }} new</span>
            </div>

            <simplebar style="max-height: 300px;">
              <template v-if="notifications.length">
                <a v-for="n in notifications" :key="n.id"
                  class="dropdown-item d-flex align-items-start gap-2 py-2 px-3"
                  :style="n.read_at ? '' : 'background-color: #fffbea;'"
                  href="#">
                  <div class="flex-shrink-0 mt-1">
                    <i class="ri-alert-line text-warning fs-18"></i>
                  </div>
                  <div class="flex-grow-1">
                    <!-- low_balance -->
                    <template v-if="n.data.type === 'low_balance'">
                      <p class="mb-0 fs-12 fw-semibold text-truncate" style="max-width:240px;">
                        <strong>{{ n.data.fund_name }}</strong> balance dropped to
                        &#8369;{{ Number(n.data.balance).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
                      </p>
                      <p class="mb-0 fs-11 text-muted">
                        Threshold: &#8369;{{ Number(n.data.threshold).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
                        &middot; {{ timeAgo(n.created_at) }}
                      </p>
                    </template>

                    <!-- low_stock -->
                    <template v-else-if="n.data.type === 'low_stock'">
                      <p class="mb-0 fs-12 fw-semibold text-truncate" style="max-width:240px;">
                        <strong>{{ n.data.product_name }}</strong> stock is low
                      </p>
                      <p class="mb-0 fs-11 text-muted">
                        {{ n.data.current_stock }} remaining &middot; min {{ n.data.minimum_stock }}
                        &middot; {{ timeAgo(n.created_at) }}
                      </p>
                    </template>

                    <!-- overdue_invoice -->
                    <template v-else-if="n.data.type === 'overdue_invoice'">
                      <p class="mb-0 fs-12 fw-semibold text-truncate" style="max-width:240px;">
                        <strong>{{ n.data.invoice_number }}</strong> overdue
                      </p>
                      <p class="mb-0 fs-11 text-muted">
                        {{ n.data.customer_name }} &middot;
                        &#8369;{{ Number(n.data.balance_due).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
                        &middot; {{ timeAgo(n.created_at) }}
                      </p>
                    </template>
                  </div>
                </a>
              </template>
              <div v-else class="p-3 text-center text-muted fs-12">No notifications</div>
            </simplebar>

            <div class="p-2 border-top text-center">
              <a href="#" class="fs-12 text-muted" @click.prevent="markAllRead">Mark all as read</a>
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
</template>
