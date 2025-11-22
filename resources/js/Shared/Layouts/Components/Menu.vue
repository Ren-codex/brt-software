<template>
    <BContainer fluid>
        <div id="two-column-menu"></div>
        <ul class="navbar-nav h-100" id="navbar-nav">
            <li class="nav-item">
                <Link href="/" class="nav-link menu-link"
                    :class="{'active': $page.component.startsWith('Modules/Portal/Dashboard') }">
                <i class="ri-apps-line"></i>
                <span class="fw-semibold fs-14" data-key="t-dashboards">Dashboard</span>
                </Link>
            </li>
            <tempalte v-if="$page.props.roles.includes('Human Resource Officer')">
                <li class="menu-title">
                    <i class="ri-more-fill" aria-expanded="false"></i>
                    <span data-key="t-menu">Human Resource</span>
                </li>
                <li class="nav-item">
                    <Link href="/employees" class="nav-link menu-link"
                    :class="{'active': $page.component.startsWith('Modules/HumanResource/Employees') }">
                    <i class="ri-team-fill"></i>
                    <span class="fw-semibold fs-14" data-key="t-dashboards">Employees</span>
                    </Link>
                </li>

            </tempalte>
            <tempalte v-if="$page.props.roles.includes('Administrator')">
                <li class="nav-item">
                    <Link href="/users" class="nav-link menu-link"
                    :class="{'active': $page.component.startsWith('Modules/System/Users') }">
                    <i class="ri-team-fill"></i>
                    <span class="fw-semibold fs-14" data-key="t-dashboards">Accounts</span>
                    </Link>
                </li>
                <li class="nav-item">
                    <Link href="/users" class="nav-link menu-link"
                    :class="{'active': $page.component.startsWith('Modules/System/Users') }">
                    <i class="ri-bar-chart-2-fill"></i>
                    <span class="fw-semibold fs-14" data-key="t-dashboards">Reports</span>
                    </Link>
                </li>
                <li class="nav-item">
                    <Link href="/users" class="nav-link menu-link"
                    :class="{'active': $page.component.startsWith('Modules/System/Users') }">
                    <i class="ri-survey-fill"></i>
                    <span class="fw-semibold fs-14" data-key="t-dashboards">Inventory Management</span>
                    </Link>
                </li>
                <li class="nav-item">
                    <Link href="/users" class="nav-link menu-link"
                    :class="{'active': $page.component.startsWith('Modules/System/Users') }">
                    <i class="ri-shopping-cart-fill"></i>
                    <span class="fw-semibold fs-14" data-key="t-dashboards">Sales Order</span>
                    </Link>
                </li>
                  <li class="nav-item">
                    <Link href="/users" class="nav-link menu-link"
                    :class="{'active': $page.component.startsWith('Modules/System/Users') }">
                    <i class="ri-team-fill"></i>
                    <span class="fw-semibold fs-14" data-key="t-dashboards">Customers</span>
                    </Link>
                </li>
                <li class="nav-item">
                    <Link href="/users" class="nav-link menu-link"
                    :class="{'active': $page.component.startsWith('Modules/System/Users') }">
                    <i class="ri-file-list-3-fill"></i>
                    <span class="fw-semibold fs-14" data-key="t-dashboards">Receipts</span>
                    </Link>
                </li>

                <li class="nav-item">
                    <Link href="/users" class="nav-link menu-link"
                    :class="{'active': $page.component.startsWith('Modules/System/Users') }">
                    <i class="ri-money-dollar-circle-fill"></i>
                    <span class="fw-semibold fs-14" data-key="t-dashboards">Expenses</span>
                    </Link>
                </li>
                <li class="nav-item">
                    <Link href="/users" class="nav-link menu-link"
                    :class="{'active': $page.component.startsWith('Modules/System/Users') }">
                    <i class="ri-bank-card-fill"></i>
                    <span class="fw-semibold fs-14" data-key="t-dashboards">Payroll</span>
                    </Link>
                </li>
                <li class="nav-item">
                    <Link href="/users" class="nav-link menu-link"
                    :class="{'active': $page.component.startsWith('Modules/System/Users') }">
                    <i class="ri-bank-card-fill"></i>
                    <span class="fw-semibold fs-14" data-key="t-dashboards">Loans</span>
                    </Link>
                </li>
                 <li class="nav-item">
                   <BLink class="nav-link menu-link" href="#sidebarDashboards"
                    :class="{'active': $page.component.startsWith('Modules/System/Users') }"
                    data-bs-toggle="collapse" role="button" :aria-expanded="$page.url.startsWith('/references')" aria-controls="sidebarDashboards">
                        <i class="ri-folder-2-fill"></i>
                        <span data-key="t-dashboards">Libraries</span>
                    </BLink>
                    <div class="collapse menu-dropdown" id="sidebarDashboards">
                        <ul class="nav nav-sm flex-column">
                            <li class="nav-item">
                                <Link href="/libraries/suppliers"  class="nav-link" data-key="t-basic">
                                    Suppliers
                                </Link>
                            </li>
                            <li class="nav-item">
                                <Link href="/libraries/units"  class="nav-link" data-key="t-basic">
                                    Units
                                <Link href="/libraries/statuses"  class="nav-link" data-key="t-basic">
                                    Statuses
                                </Link>
                            </li>
                        </ul>
                    </div>
                </li>


            </tempalte>
        </ul>
        
    </BContainer>
</template>
<script>
    import {
        layoutComputed
    } from "@/Shared/State/helpers";
    import simplebar from "simplebar-vue";

    export default {
        components: {
            simplebar,
        },
        data() {
            return {
                currentUrl: window.location.origin,
                menus: [],
                settings: {
                    minScrollbarLength: 60,
                },
            };
        },
        computed: {
            ...layoutComputed,
            layoutType: {
                get() {
                    return this.$store ? this.$store.state.layout.layoutType : {} || {};
                },
            },
            filteredFinance() {
                const cashier = ['Dashboard', 'Receipts', 'Deposits', 'Collection Type', 'OR Series', 'Names',
                    'Reports'];
                const accountant = ['Dashboard', 'Order of Payment', 'Collection Type', 'Reports'];

                const role = this.$page.props.user.data.assigned_role;
                const menus = this.$page.props.menus.finance;

                const isMenuNameInArray = (menuName, nameArray) =>
                    nameArray.some(name => menuName.toLowerCase().includes(name.toLowerCase()));

                if (role === 'Cashier') {
                    return menus.filter(menu => isMenuNameInArray(menu.main.name, cashier));
                } else if (role === 'Accountant') {
                    return menus.filter(menu => isMenuNameInArray(menu.main.name, accountant));
                }

                return [];
            }
        },
        mounted() {
            this.initActiveMenu();
            this.onRoutechange();
            // this.fetch();
            if (document.querySelectorAll(".navbar-nav .collapse")) {
                let collapses = document.querySelectorAll(".navbar-nav .collapse");

                collapses.forEach((collapse) => {
                    // Hide sibling collapses on `show.bs.collapse`
                    collapse.addEventListener("show.bs.collapse", (e) => {
                        e.stopPropagation();
                        let closestCollapse = collapse.parentElement.closest(".collapse");
                        if (closestCollapse) {
                            let siblingCollapses =
                                closestCollapse.querySelectorAll(".collapse");
                            siblingCollapses.forEach((siblingCollapse) => {
                                if (siblingCollapse.classList.contains("show")) {
                                    siblingCollapse.classList.remove("show");
                                    siblingCollapse.parentElement.firstChild.setAttribute(
                                        "aria-expanded", "false");
                                }
                            });
                        } else {
                            let getSiblings = (elem) => {
                                // Setup siblings array and get the first sibling
                                let siblings = [];
                                let sibling = elem.parentNode.firstChild;
                                // Loop through each sibling and push to the array
                                while (sibling) {
                                    if (sibling.nodeType === 1 && sibling !== elem) {
                                        siblings.push(sibling);
                                    }
                                    sibling = sibling.nextSibling;
                                }
                                return siblings;
                            };
                            let siblings = getSiblings(collapse.parentElement);
                            siblings.forEach((item) => {
                                if (item.childNodes.length > 2) {
                                    item.firstElementChild.setAttribute("aria-expanded",
                                        "false");
                                    item.firstElementChild.classList.remove("active");
                                }
                                let ids = item.querySelectorAll("*[id]");
                                ids.forEach((item1) => {
                                    item1.classList.remove("show");
                                    item1.parentElement.firstChild.setAttribute(
                                        "aria-expanded", "false");
                                    item1.parentElement.firstChild.classList.remove(
                                        "active");
                                    if (item1.childNodes.length > 2) {
                                        let val = item1.querySelectorAll("ul li a");

                                        val.forEach((subitem) => {
                                            if (subitem.hasAttribute(
                                                    "aria-expanded"))
                                                subitem.setAttribute(
                                                    "aria-expanded", "false");
                                        });
                                    }
                                });
                            });
                        }
                    });

                    // Hide nested collapses on `hide.bs.collapse`
                    collapse.addEventListener("hide.bs.collapse", (e) => {
                        e.stopPropagation();
                        let childCollapses = collapse.querySelectorAll(".collapse");
                        childCollapses.forEach((childCollapse) => {
                            let childCollapseInstance = childCollapse;
                            childCollapseInstance.classList.remove("show");
                            childCollapseInstance.parentElement.firstChild.setAttribute(
                                "aria-expanded", "false");
                        });
                    });
                });
            }
        },

        methods: {
            checkUrl() {
                const path = window.location.pathname;
                return path.split('/')[1] || null;
            },
            onRoutechange() {
                // this.initActiveMenu();
                setTimeout(() => {
                    var currentPath = window.location.pathname;
                    if (document.querySelector("#navbar-nav")) {
                        let currentPosition = document.querySelector("#navbar-nav").querySelector('[href="' +
                            currentPath + '"]') ?.offsetTop;
                        if (currentPosition > document.documentElement.clientHeight) {
                            document.querySelector("#scrollbar .simplebar-content-wrapper") ? document
                                .querySelector("#scrollbar .simplebar-content-wrapper").scrollTop =
                                currentPosition + 300 : '';
                        }
                    }
                }, 500);
            },

            initActiveMenu() {
                setTimeout(() => {
                    var currentPath = window.location.pathname;
                    if (document.querySelector("#navbar-nav")) {
                        let a = document.querySelector("#navbar-nav").querySelector('[href="' + currentPath +
                            '"]');
                        if (a) {
                            a.classList.add("active");
                            let parentCollapseDiv = a.closest(".collapse.menu-dropdown");
                            if (parentCollapseDiv) {
                                parentCollapseDiv.classList.add("show");
                                parentCollapseDiv.parentElement.children[0].classList.add("active");
                                parentCollapseDiv.parentElement.children[0].setAttribute("aria-expanded",
                                    "true");
                                if (parentCollapseDiv.parentElement.closest(".collapse.menu-dropdown")) {
                                    parentCollapseDiv.parentElement.closest(".collapse").classList.add("show");
                                    if (parentCollapseDiv.parentElement.closest(".collapse")
                                        .previousElementSibling)
                                        parentCollapseDiv.parentElement.closest(".collapse")
                                        .previousElementSibling.classList.add("active");
                                    const grandparent = parentCollapseDiv.parentElement.closest(".collapse")
                                        .previousElementSibling.parentElement.closest(".collapse");
                                    if (grandparent && grandparent && grandparent.previousElementSibling) {
                                        grandparent.previousElementSibling.classList.add("active");
                                        grandparent.classList.add("show");
                                    }
                                }
                            }
                        }
                    }
                }, 0);
            },
        },
    };

</script>
