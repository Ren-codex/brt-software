<template>
    <BContainer fluid>
        <div id="two-column-menu"></div>
        <ul class="navbar-nav h-100" id="navbar-nav">

            <!-- ── OVERVIEW ── -->
            <li class="nav-group-label" v-if="$page.props.roles.length">Overview</li>
            <li class="nav-item" v-if="$page.props.roles.length">
                <Link href="/" class="nav-link menu-link"
                    :class="{ 'active': $page.component.startsWith('Modules/Dashboard/') }">
                <i class="ri-apps-line"></i>
                <span class="fw-semibold fs-14" data-key="t-dashboards">Dashboard</span>
                <span class="menu-link-bg"></span>
                <span class="active-indicator"></span>
                </Link>
            </li>

            <!-- ── MANAGEMENT ── -->
            <li class="nav-group-label"
                v-if="$page.props.roles.includes('HR Manager') || $page.props.roles.includes('Super Admin') || $page.props.roles.includes('Human Resource Officer') || $page.props.roles.includes('Warehouse Manager') || $page.props.roles.includes('Sales Rep') || $page.props.roles.includes('Mini Admin') || $page.props.roles.includes('Administrator')">
                Management
            </li>

            <template
                v-if="$page.props.roles.includes('HR Manager') || $page.props.roles.includes('Super Admin')">
                <li class="nav-item">
                    <Link href="/employees" class="nav-link menu-link"
                        :class="{ 'active': $page.component.startsWith('Modules/Employees/') }">
                    <i class="ri-team-fill"></i>
                    <span class="fw-semibold fs-14" data-key="t-dashboards">Employees</span>
                    <span class="menu-link-bg"></span>
                    <span class="active-indicator"></span>
                    </Link>
                </li>
            </template>

            <template
                v-if="$page.props.roles.includes('Administrator') || $page.props.roles.includes('Sales Rep') || $page.props.roles.includes('Warehouse Manager') || $page.props.roles.includes('HR Manager') || $page.props.roles.includes('Accountant') || $page.props.roles.includes('Super Admin')">
                <li class="nav-item"
                    v-if="$page.props.roles.includes('Super Admin') || $page.props.roles.includes('Human Resource Officer')">
                    <Link href="/users" class="nav-link menu-link"
                        :class="{ 'active': $page.url.startsWith('/users') }">
                    <i class="ri-team-fill"></i>
                    <span class="fw-semibold fs-14" data-key="t-dashboards">Accounts</span>
                    <span class="menu-link-bg"></span>
                    <span class="active-indicator"></span>
                    </Link>
                </li>

                <li class="nav-item"
                    v-if="$page.props.roles.includes('Warehouse Manager')  || $page.props.roles.includes('Super Admin')">
                    <Link href="/inventory" class="nav-link menu-link"
                        :class="{ 'active': $page.component.startsWith('Modules/Inventory/Index') }">
                    <i class="ri-survey-fill"></i>
                    <span class="fw-semibold fs-14" data-key="t-dashboards">Inventory Management</span>
                    <span class="menu-link-bg"></span>
                    <span class="active-indicator"></span>
                    </Link>
                </li>

                <li class="nav-item"
                    v-if="$page.props.roles.includes('Sales Rep') || $page.props.roles.includes('Super Admin')">
                    <Link href="/sales-orders" class="nav-link menu-link"
                    :class="{'active': $page.url.startsWith('/sales-orders') && !$page.url.startsWith('/sales-orders-external') }">
                    <i class="ri-shopping-cart-fill"></i>
                    <span class="fw-semibold fs-14" data-key="t-dashboards">Sales Management</span>
                    <span class="menu-link-bg"></span>
                    <span class="active-indicator"></span>
                    </Link>
                </li>

                <li class="nav-item" v-if="$page.props.roles.includes('Mini Admin') || $page.props.roles.includes('Administrator') || $page.props.roles.includes('Super Admin')">
                    <Link href="/customers" class="nav-link menu-link"
                        :class="{ 'active': $page.url.startsWith('/customers') }">
                    <i class="ri-team-fill"></i>
                    <span class="fw-semibold fs-14" data-key="t-dashboards">Customers</span>
                    <span class="menu-link-bg"></span>
                    <span class="active-indicator"></span>
                    </Link>
                </li>

                <li class="nav-item" v-if="$page.props.roles.includes('Administrator') || $page.props.roles.includes('Super Admin')">
                    <Link href="/suppliers" class="nav-link menu-link"
                        :class="{ 'active': $page.url.startsWith('/libraries/suppliers') }">
                    <i class="ri-money-dollar-circle-fill"></i>
                    <span class="fw-semibold fs-14" data-key="t-dashboards">Suppliers</span>
                    <span class="menu-link-bg"></span>
                    <span class="active-indicator"></span>
                    </Link>
                </li>

                <!-- ── FINANCE ── -->
                <li class="nav-group-label"
                    v-if="$page.props.roles.includes('Super Admin') || $page.props.roles.includes('Accountant') || $page.props.roles.includes('HR Manager')">
                    Finance
                </li>

                <li class="nav-item" v-if="$page.props.roles.includes('Super Admin') || $page.props.roles.includes('Accountant')">
                    <Link href="/accounting" class="nav-link menu-link"
                        :class="{ 'active': $page.url.startsWith('/accounting') }">
                    <i class="ri-bank-card-fill"></i>
                    <span class="fw-semibold fs-14" data-key="t-dashboards">Accounting</span>
                    <span class="menu-link-bg"></span>
                    <span class="active-indicator"></span>
                    </Link>
                </li>

                <li class="nav-item" v-if="$page.props.roles.includes('Super Admin') || $page.props.roles.includes('HR Manager')">
                    <Link href="/payrolls" class="nav-link menu-link"
                        :class="{ 'active': $page.url.startsWith('/payrolls') }">
                    <i class="ri-wallet-2-line"></i>
                    <span class="fw-semibold fs-14" data-key="t-dashboards">Payroll</span>
                    <span class="menu-link-bg"></span>
                    <span class="active-indicator"></span>
                    </Link>
                </li>

                <!-- ── SYSTEM ── -->
                <li class="nav-group-label"
                    v-if="$page.props.roles.includes('Administrator') || $page.props.roles.includes('Super Admin')">
                    System
                </li>

                <li class="nav-item" v-if="$page.props.roles.includes('Administrator') || $page.props.roles.includes('Super Admin')">
                    <Link href="/contacts" class="nav-link menu-link"
                    :class="{'active': $page.url.startsWith('/contacts') }">
                    <i class="ri-mail-send-line"></i>
                    <span class="fw-semibold fs-14" data-key="t-dashboards">Guest Messages</span>
                    <span class="menu-link-bg"></span>
                    <span class="active-indicator"></span>
                    </Link>
                </li>

                <li class="nav-item" v-if="$page.props.roles.includes('Administrator') || $page.props.roles.includes('Super Admin')">
                   <BLink class="nav-link menu-link" href="#sidebarDashboards"
                    :class="{'active': $page.url.startsWith('/libraries') }"
                    data-bs-toggle="collapse" role="button" :aria-expanded="$page.url.startsWith('/libraries')" aria-controls="sidebarDashboards">
                        <i class="ri-folder-2-fill"></i>
                        <span data-key="t-dashboards">Libraries</span>
                        <i class="ri-arrow-down-s-line collapse-icon"></i>
                        <span class="menu-link-bg"></span>
                        <span class="active-indicator"></span>
                    </BLink>

                    <div class="collapse menu-dropdown" id="sidebarDashboards">
                        <ul class="nav nav-sm flex-column">
                            <li class="nav-item submenu-item">
                                <Link href="/libraries/products" class="nav-link submenu-link"
                                    :class="{ 'active': $page.url === '/libraries/products' }" data-key="t-basic">
                                <span class="submenu-dot"></span>
                                <span class="submenu-text">Products</span>
                                </Link>
                            </li>

                            <li class="nav-item submenu-item">
                                <Link href="/libraries/statuses" class="nav-link submenu-link"
                                    :class="{ 'active': $page.url === '/libraries/statuses' }" data-key="t-basic">
                                <span class="submenu-dot"></span>
                                <span class="submenu-text">Statuses</span>
                                </Link>
                            </li>

                            <li class="nav-item submenu-item">
                                <Link href="/libraries/brands" class="nav-link submenu-link"
                                    :class="{ 'active': $page.url === '/libraries/brands' }" data-key="t-basic">
                                <span class="submenu-dot"></span>
                                <span class="submenu-text">Brands</span>
                                </Link>
                            </li>

                            <li class="nav-item submenu-item">
                                <Link href="/libraries/units" class="nav-link submenu-link"
                                    :class="{ 'active': $page.url === '/libraries/units' }" data-key="t-basic">
                                <span class="submenu-dot"></span>
                                <span class="submenu-text">Units</span>
                                </Link>
                            </li>

                            <li class="nav-item submenu-item">
                                <Link href="/libraries/locations" class="nav-link submenu-link"
                                    :class="{'active': $page.url === '/libraries/locations' }"
                                    data-key="t-basic">
                                    <span class="submenu-dot"></span>
                                    <span class="submenu-text">Locations</span>
                                </Link>
                            </li>

                            <li class="nav-item submenu-item">
                                <Link href="/libraries/roles" class="nav-link submenu-link"
                                    :class="{ 'active': $page.url === '/libraries/roles' }" data-key="t-basic">
                                <span class="submenu-dot"></span>
                                <span class="submenu-text">Roles</span>
                                </Link>
                            </li>

                    
                            <li class="nav-item submenu-item">
                                <Link href="/libraries/positions" class="nav-link submenu-link"
                                    :class="{ 'active': $page.url === '/libraries/positions' }" data-key="t-basic">
                                <span class="submenu-dot"></span>
                                <span class="submenu-text">Positions</span>
                                </Link>
                            </li>

                            <li class="nav-item submenu-item">
                                <Link href="/libraries/funds" class="nav-link submenu-link"
                                    :class="{ 'active': $page.url === '/libraries/funds' }" data-key="t-basic">
                                <span class="submenu-dot"></span>
                                <span class="submenu-text">Petty Cash Funds</span>
                                </Link>
                            </li>

                        </ul>
                    </div>
                </li>
            </template>
        </ul>
    </BContainer>
</template>

<style>
/* ========== SIDEBAR NAVIGATION ========== */

#navbar-nav {
    position: relative;
    padding: 0.25rem 0;
    height: auto !important;
    justify-content: flex-start;
    align-items: stretch;
}

/* ── Section Group Labels ── */
.nav-group-label {
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.35);
    padding: 0.9rem 1.5rem 0.3rem;
    margin-top: 0.25rem;
    list-style: none;
    user-select: none;
    pointer-events: none;
    border-top: 1px solid rgba(255, 255, 255, 0.06);
}

.nav-group-label:first-child {
    margin-top: 0;
    border-top: none;
}

/* Collapsed sidebar (icon-only, not hovered): hide text, show only separator line */
html[data-sidebar-size="sm-hover"] .navbar-menu:not(:hover) .nav-group-label {
    font-size: 0;
    padding: 0.5rem 0.75rem 0;
    margin-top: 0.25rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

html[data-sidebar-size="sm-hover"] .navbar-menu:not(:hover) .nav-group-label:first-child {
    border-top: none;
    padding: 0;
    margin-top: 0;
}

/* ── Nav Items — slide in smoothly (no bounce) ── */
.nav-item {
    margin: 0.1rem 0;
    position: relative;
    opacity: 0;
    transform: translateX(-12px);
    animation: slideInLeft 0.35s ease-out var(--animation-delay, 0s) forwards;
}

/* ── Menu Links ── */
.menu-link {
    display: flex;
    align-items: center;
    padding: 0.525rem 1rem;
    text-decoration: none;
    border-radius: 10px;
    transition: background 0.18s ease-out, border-color 0.18s ease-out,
                box-shadow 0.18s ease-out, transform 0.18s ease-out;
    position: relative;
    overflow: visible;
    z-index: 1;
    border: 1px solid transparent;
    background: transparent;
    min-height: 40px;
}

.menu-link-bg {
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(45, 164, 135, 0.14), rgba(30, 126, 103, 0.07));
    border-radius: 10px;
    opacity: 0;
    transition: opacity 0.18s ease-out;
    z-index: -1;
}

/* Hover — subtle shift, no bounce */
.menu-link:hover {
    border-color: rgba(45, 164, 135, 0.2);
    background: rgba(255, 255, 255, 0.07);
    transform: translateX(4px);
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.12);
    text-decoration: none;
}

.menu-link:hover .menu-link-bg {
    opacity: 1;
}

/* Active — stays in place, full gradient */
.menu-link.active {
    color: #ffffff !important;
    background: linear-gradient(135deg, #1a7460, #2da487);
    border-color: rgba(26, 116, 96, 0.6);
    box-shadow: 0 4px 16px rgba(26, 116, 96, 0.4);
    transform: none;
}

.menu-link.active .menu-link-bg {
    opacity: 0;
}

/* ── Active Indicator dot ── */
.active-indicator {
    position: absolute;
    right: -6px;
    top: 50%;
    transform: translateY(-50%) scale(0);
    width: 7px;
    height: 7px;
    background: #4ade80;
    border-radius: 50%;
    transition: transform 0.2s ease-out;
    box-shadow: 0 0 0 0 rgba(74, 222, 128, 0.6);
}

.menu-link.active .active-indicator {
    transform: translateY(-50%) scale(1);
    animation: pulseActive 2.5s ease-out infinite;
}

/* ── Icons ── */
.menu-link i:not(.collapse-icon) {
    font-size: 1.2rem;
    margin-right: 0.5rem;
    transition: transform 0.18s ease-out, color 0.18s ease-out;
    flex-shrink: 0;
}

.menu-link:hover i:not(.collapse-icon) {
    transform: scale(1.15);
}

.menu-link.active i:not(.collapse-icon) {
    color: white;
    transform: scale(1.1);
    filter: drop-shadow(0 1px 3px rgba(0, 0, 0, 0.25));
}

/* Hide Bootstrap's native collapse caret — we use our own .collapse-icon */
.menu-link::after,
.menu-link::before {
    display: none !important;
}

/* ── Collapse Icon (Libraries chevron) ── */
.collapse-icon {
    margin-left: auto;
    font-size: 1rem;
    opacity: 0.7;
    transition: transform 0.22s ease-out, opacity 0.18s ease-out;
}

.menu-link[aria-expanded="true"] .collapse-icon {
    transform: rotate(180deg);
    opacity: 1;
}

/* ── Submenu container ── */
.menu-dropdown {
    margin-left: 1rem;
    padding-left: 1.25rem;
    position: relative;
    border-left: 1.5px solid rgba(255, 255, 255, 0.08);
}

/* Kill Velzon's default "–" dash on nav-sm links */
.menu-dropdown .nav-link::before,
.menu-dropdown .nav-link::after {
    display: none !important;
    content: none !important;
}

.submenu-item {
    margin: 0.1rem 0;
}

.submenu-link {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.4rem 0.75rem;
    border-radius: 7px;
    font-size: 0.8rem;
    font-weight: 400;
    text-decoration: none;
    color: rgba(255, 255, 255, 0.55);
    transition: background 0.15s ease, color 0.15s ease, padding-left 0.15s ease;
    position: relative;
}

/* Dot indicator */
.submenu-dot {
    width: 4px;
    height: 4px;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    flex-shrink: 0;
    transition: background 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
}

.submenu-link:hover {
    background: rgba(255, 255, 255, 0.06);
    color: rgba(255, 255, 255, 0.85);
    padding-left: 1rem;
    text-decoration: none;
}

.submenu-link:hover .submenu-dot {
    background: #4db89e;
    transform: scale(1.5);
}

.submenu-link.active {
    color: #ffffff;
    font-weight: 500;
    background: rgba(45, 164, 135, 0.15);
    padding-left: 1rem;
}

.submenu-link.active .submenu-dot {
    background: #4db89e;
    box-shadow: 0 0 0 2px rgba(77, 184, 158, 0.3);
    transform: scale(1.4);
}

.submenu-text {
    line-height: 1.3;
}

/* ── Staggered load — snappier delays ── */
.nav-item:nth-child(1)  { --animation-delay: 0.04s; }
.nav-item:nth-child(2)  { --animation-delay: 0.08s; }
.nav-item:nth-child(3)  { --animation-delay: 0.12s; }
.nav-item:nth-child(4)  { --animation-delay: 0.16s; }
.nav-item:nth-child(5)  { --animation-delay: 0.20s; }
.nav-item:nth-child(6)  { --animation-delay: 0.24s; }
.nav-item:nth-child(7)  { --animation-delay: 0.28s; }
.nav-item:nth-child(8)  { --animation-delay: 0.32s; }
.nav-item:nth-child(9)  { --animation-delay: 0.36s; }
.nav-item:nth-child(10) { --animation-delay: 0.40s; }

/* ── Keyframes ── */
@keyframes slideInLeft {
    from { opacity: 0; transform: translateX(-12px); }
    to   { opacity: 1; transform: translateX(0); }
}

@keyframes pulseActive {
    0%   { box-shadow: 0 0 0 0   rgba(74, 222, 128, 0.6); }
    60%  { box-shadow: 0 0 0 6px rgba(74, 222, 128, 0); }
    100% { box-shadow: 0 0 0 0   rgba(74, 222, 128, 0); }
}

/* ── Collapsed sidebar ── */
body.vertical-collapsed .menu-link {
    justify-content: center;
    padding: 0.5rem;
}

body.vertical-collapsed .menu-link span {
    display: none;
}

body.vertical-collapsed .menu-link .collapse-icon {
    display: none;
}

/* ── Responsive ── */
@media (max-width: 991.98px) {
    .menu-link { padding: 0.5rem 0.75rem; }
    .menu-dropdown { margin-left: 2.25rem; }
}

/* ── Reduced motion ── */
@media (prefers-reduced-motion: reduce) {
    .nav-item { animation: none; opacity: 1; transform: none; }
    .menu-link, .menu-link i, .collapse-icon,
    .submenu-link, .submenu-dot, .active-indicator { transition: none; }
    .menu-link.active .active-indicator { animation: none; }
}
</style>
