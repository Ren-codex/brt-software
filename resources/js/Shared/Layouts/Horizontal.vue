<script>
import Footer from '@/Shared/Layouts/Components/Footer.vue';
import Navigation from '@/Shared/Layouts/Components/Navigation.vue';
import Configuration from '@/Shared/Layouts/Components/Configuration.vue';
import Menu from '@/Shared/Layouts/Components/Menu.vue';
export default {
  mounted() {
    this.initActiveMenu();
  },
  methods: {
    initActiveMenu(ele) {
      setTimeout(() => {
        var currentPath = window.location.pathname;
        if (document.querySelector("#navbar-nav")) {
          let a = document.querySelector("#navbar-nav").querySelector('[href="' + currentPath + '"]');

          if (a) {
            a.classList.add("active");
            let parentCollapseDiv = a.closest(".collapse.menu-dropdown");
            if (parentCollapseDiv) {
              parentCollapseDiv.classList.add("show");
              parentCollapseDiv.parentElement.children[0].classList.add("active");
              parentCollapseDiv.parentElement.children[0].setAttribute("aria-expanded", "true");
              if (parentCollapseDiv.parentElement.closest(".collapse.menu-dropdown")) {
                parentCollapseDiv.parentElement.closest(".collapse").classList.add("show");
                if (parentCollapseDiv.parentElement.closest(".collapse").previousElementSibling)
                  parentCollapseDiv.parentElement.closest(".collapse").previousElementSibling.classList.add("active");
              }
            }
          }
        }
      }, 1000);
    },
  },
  components: {
    Navigation,
    Configuration,
    Footer,
    Menu
  },
};
</script>

<template>
  <div>
    <div id="layout-wrapper">
      <Navigation />
      <!-- ========== App Menu ========== -->
      <div class="app-menu navbar-menu">
        <!-- LOGO -->
        <div class="navbar-brand-box">
          <!-- Dark Logo-->
          <Link href="/" class="logo logo-dark">
          <span class="logo-sm">
            <img src="@assets/images/logo-sm.png" alt="" height="22" />
          </span>
          <span class="logo-lg">
            <img src="@assets/images/logo-dark.png" alt="" height="17" />
          </span>
          </Link>
          <!-- Light Logo-->
          <Link href="/" class="logo logo-light">
          <span class="logo-sm">
            <img src="@assets/images/logo-sm.png" alt="" height="22" /> 
          </span>
          <span class="logo-lg">
            <img src="@assets/images/logo-light.png" alt="" height="17" />
          </span>
          </Link>
          <button type="button" class="btn btn-sm p-0 fs-20 header-item float-end btn-vertical-sm-hover"
            id="vertical-hover">
            <i class="ri-record-circle-line"></i>
          </button>
        </div>
        <div id="scrollbar">
            <Menu></Menu>
          <!-- Sidebar -->
        </div>
        <!-- Left Sidebar End -->
        <!-- Vertical Overlay-->
        <div class="vertical-overlay"></div>
      </div>
      <!-- ============================================================== -->
      <!-- Start Page Content here -->
      <!-- ============================================================== -->

      <div class="main-content">
        <div class="page-content">
          <!-- Start Content-->
          <BContainer fluid>
            <slot />
          </BContainer>
        </div>
        <Footer />
      </div>
      <Configuration />
    </div>
  </div>
</template>