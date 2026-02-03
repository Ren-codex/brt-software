<template>
    <BContainer fluid>
        <div id="two-column-menu"></div>
        <ul class="navbar-nav h-100" id="navbar-nav">
            <li class="nav-item">
                <Link href="/" class="nav-link menu-link"
                    :class="{'active': $page.component.startsWith('Modules/Dashboard/') }">
                <i class="ri-apps-line"></i>
                <span class="fw-semibold fs-14" data-key="t-dashboards">Dashboard</span>
                <span class="menu-link-bg"></span>
                <span class="active-indicator"></span>
                </Link>
            </li>
            <template v-if="$page.props.roles.includes('Human Resource Officer') || $page.props.roles.includes('Administrator')">
                <li class="menu-title">
                    <div class="menu-title-content">
                        <i class="ri-more-fill menu-title-icon"></i>
                        <span class="menu-title-text" data-key="t-menu">Human Resource</span>
                        <div class="menu-title-line"></div>
                    </div>
                </li>
                <li class="nav-item">
                    <Link href="/employees" class="nav-link menu-link"
                    :class="{'active': $page.component.startsWith('Modules/Employees/') }">
                    <i class="ri-team-fill"></i>
                    <span class="fw-semibold fs-14" data-key="t-dashboards">Employees</span>
                    <span class="menu-link-bg"></span>
                    <span class="active-indicator"></span>
                    </Link>
                </li>

            </template>
            <template v-if="$page.props.roles.includes('Administrator') || $page.props.roles.includes('Sales Rep') || $page.props.roles.includes('Inventory Manager')">
                <li class="nav-item" v-if="$page.props.roles.includes('Administrator') || $page.props.roles.includes('Human Resource Officer')">
                    <Link href="/users" class="nav-link menu-link"
                    :class="{'active': $page.url.startsWith('/users') }">
                    <i class="ri-team-fill"></i>
                    <span class="fw-semibold fs-14" data-key="t-dashboards">Accounts</span>
                    <span class="menu-link-bg"></span>
                    <span class="active-indicator"></span>
                    </Link>
                </li>

                <!-- <li class="nav-item">
                    <Link href="/reports" class="nav-link menu-link"
                    :class="{'active': $page.url.startsWith('/reports') }">
                    <i class="ri-bar-chart-2-fill"></i>
                    <span class="fw-semibold fs-14" data-key="t-dashboards">Reports</span>
                    <span class="menu-link-bg"></span>
                    <span class="active-indicator"></span>
                    </Link>
                </li> -->
                <li class="nav-item" v-if="$page.props.roles.includes('Inventory Manager') || $page.props.roles.includes('Administrator')">
                    <Link href="/inventory" class="nav-link menu-link"
                    :class="{'active': $page.component.startsWith('Modules/Inventory/Index') }">
                    <i class="ri-survey-fill"></i>
                    <span class="fw-semibold fs-14" data-key="t-dashboards">Inventory Management</span>
                    <span class="menu-link-bg"></span>
                    <span class="active-indicator"></span>
                    </Link>
                </li>
                <li class="nav-item" v-if="$page.props.roles.includes('Sales Rep') || $page.props.roles.includes('Administrator')">
                    <Link href="/sales-orders" class="nav-link menu-link"
                    :class="{'active': $page.url.startsWith('/sales-orders') }">
                    <i class="ri-shopping-cart-fill"></i>
                    <span class="fw-semibold fs-14" data-key="t-dashboards">Sales Management</span>
                    <span class="menu-link-bg"></span>
                    <span class="active-indicator"></span>
                    </Link>
                </li>
                  <li class="nav-item" v-if="$page.props.roles.includes('Sales Manager') || $page.props.roles.includes('Administrator')">
                    <Link href="/customers" class="nav-link menu-link"
                    :class="{'active': $page.url.startsWith('/customers') }">
                    <i class="ri-team-fill"></i>
                    <span class="fw-semibold fs-14" data-key="t-dashboards">Customers</span>
                    <span class="menu-link-bg"></span>
                    <span class="active-indicator"></span>
                    </Link>
                </li>

                <li class="nav-item" v-if="$page.props.roles.includes('Administrator')">
                    <Link href="/expenses" class="nav-link menu-link"
                    :class="{'active': $page.url.startsWith('/expenses') }">
                    <i class="ri-money-dollar-circle-fill"></i>
                    <span class="fw-semibold fs-14" data-key="t-dashboards">Expenses</span>
                    <span class="menu-link-bg"></span>
                    <span class="active-indicator"></span>
                    </Link>
                </li>
                <li class="nav-item">
                    <Link href="payrolls" class="nav-link menu-link"
                    :class="{'active': $page.url.startsWith('/payrolls') }">
                    <i class="ri-wallet-2-line"></i>
                    <span class="fw-semibold fs-14" data-key="t-dashboards">Payroll</span>
                    <span class="menu-link-bg"></span>
                    <span class="active-indicator"></span>
                    </Link>
                </li>
                <li class="nav-item" v-if="$page.props.roles.includes('Administrator') || $page.props.roles.includes('Human Resource Officer')">
                    <Link href="/loans" class="nav-link menu-link"
                    :class="{'active': $page.url.startsWith('/loans') }">
                    <i class="ri-bank-card-fill"></i>
                    <span class="fw-semibold fs-14" data-key="t-dashboards">Loans</span>
                    <span class="menu-link-bg"></span>
                    <span class="active-indicator"></span>
                    </Link>
                </li>
                 <li class="nav-item" v-if="$page.props.roles.includes('Administrator')">
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
                                    :class="{'active': $page.url === '/libraries/products' }"
                                    data-key="t-basic">
                                    <!-- <span class="submenu-dot"></span> -->
                                    <span class="submenu-text">Products</span>
                                </Link>
                            </li>
                            <li class="nav-item submenu-item">
                                <Link href="/libraries/suppliers" class="nav-link submenu-link" 
                                    :class="{'active': $page.url === '/libraries/suppliers' }"
                                    data-key="t-basic">
                                    <!-- <span class="submenu-dot"></span> -->
                                    <span class="submenu-text">Suppliers</span>
                                </Link>
                            </li>
                            <li class="nav-item submenu-item">
                                <Link href="/libraries/statuses" class="nav-link submenu-link"
                                    :class="{'active': $page.url === '/libraries/statuses' }"
                                    data-key="t-basic">
                                    <!-- <span class="submenu-dot"></span> -->
                                    <span class="submenu-text">Statuses</span>
                                </Link>
                            </li>

                            <li class="nav-item submenu-item">
                                <Link href="/libraries/brands" class="nav-link submenu-link"
                                    :class="{'active': $page.url === '/libraries/brands' }"
                                    data-key="t-basic">
                                    <!-- <span class="submenu-dot"></span> -->
                                    <span class="submenu-text">Brands</span>
                                  </Link>
                            </li>

                            <li class="nav-item submenu-item">
                                <Link href="/libraries/units" class="nav-link submenu-link"
                                    :class="{'active': $page.url === '/libraries/units' }"
                                    data-key="t-basic">
                                    <!-- <span class="submenu-dot"></span> -->
                                    <span class="submenu-text">Units</span>
                                </Link>
                            </li>

                            <li class="nav-item submenu-item">
                                <Link href="/libraries/roles" class="nav-link submenu-link"
                                    :class="{'active': $page.url === '/libraries/roles' }"
                                    data-key="t-basic">
                                    <!-- <span class="submenu-dot"></span> -->
                                    <span class="submenu-text">Roles</span>
                                </Link>
                            </li>

                    


                            <li class="nav-item submenu-item">
                                <Link href="/libraries/positions" class="nav-link submenu-link"
                                    :class="{'active': $page.url === '/libraries/positions' }"
                                    data-key="t-basic">
                                    <!-- <span class="submenu-dot"></span> -->
                                    <span class="submenu-text">Positions</span>
                                </Link>
                            </li>

                            <!-- <li class="nav-item submenu-item">
                                <Link href="/libraries/salaries" class="nav-link submenu-link"
                                    :class="{'active': $page.url === '/libraries/salaries' }"
                                    data-key="t-basic">
                                    <span class="submenu-dot"></span>
                                    <span class="submenu-text">Salaries</span>
                                </Link>
                            </li> -->

                        </ul>
                    </div>
                </li>
            </template>
        </ul>
    </BContainer>
</template>

<style>
/* ========== NAVIGATION MENU REDESIGN ========== */

#navbar-nav {
  
    position: relative;
}

/* Menu Title Styling */
.menu-title {
    margin: 1.5rem 0 0.5rem 0;
    opacity: 0;
    animation: fadeInUp 0.5s ease-out var(--animation-delay, 0s) forwards;
}

.menu-title-content {
    position: relative;
    padding: 0 1rem;
}

.menu-title-icon {
    color: #8b5cf6;
    font-size: 0.875rem;
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    animation: pulseIcon 2s infinite;
}

.menu-title-text {
    color: #64748b;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: block;
    padding-left: 1.25rem;
}

.menu-title-line {
    position: absolute;
    left: 0;
    right: 0;
    bottom: -4px;
    height: 1px;
    background: linear-gradient(90deg, 
        transparent 0%, 
        rgba(92, 246, 213, 0.3) 20%, 
        rgba(92, 246, 184, 0.3) 80%, 
        transparent 100%);
    animation: lineExpand 1s ease-out;
}

/* Main Menu Items */
.nav-item {
    margin: 0.125rem 0;
    position: relative;
    opacity: 0;
    transform: translateX(-20px);
    animation: slideInLeft 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) var(--animation-delay, 0s) forwards;
}

.menu-link {
    display: flex;
    align-items: center;
    padding: 0.5rem 1rem;
    color: #475569;
    text-decoration: none;
    border-radius: 12px;
    transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    position: relative;
    overflow: visible;
    z-index: 1;
    border: 1px solid transparent;
    background: rgba(241, 248, 249, 0);
}

.menu-link-bg {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(92, 246, 218, 0.1), rgba(59, 246, 230, 0.05));
    opacity: 0;
    transition: opacity 0.4s ease;
    z-index: -1;
}

.menu-link:hover {
    color: #3D8D7A;
    border-color: rgba(61, 141, 122, 0.2);
    background: rgba(241, 247, 249, 0.5);
    transform: translateX(6px);
    box-shadow: 0 4px 20px rgba(61, 141, 122, 0.15);
}

.menu-link:hover .menu-link-bg {
    opacity: 1;
    animation: gradientShift 3s ease infinite;
}

.menu-link.active {
    color: #ffffff;
    background: linear-gradient(135deg, #3D8D7A, #C4DAD2);
    border-color: #3D8D7A;
    box-shadow: 0 6px 24px rgba(61, 141, 122, 0.4);
    transform: translateX(8px);
}

.menu-link.active .menu-link-bg {
  
    background: linear-gradient(135deg, #3D8D7A, #C4DAD2);
}

/* Active Indicator */
.active-indicator {
    position: absolute;
    right: -8px;
    top: 50%;
    transform: translateY(-50%) scale(0);
    width: 8px;
    height: 8px;
    background: #10b981;
    border-radius: 50%;
    transition: transform 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
}

.menu-link.active .active-indicator {
    transform: translateY(-50%) scale(1);
    animation: pulseActive 2s infinite;
}

/* Icons */
.menu-link i {
    font-size: 1.25rem;
    margin-right: 0.5rem;
    transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    position: relative;
}

.menu-link:hover i {
    transform: scale(1.2) rotate(5deg);
    color: #5cf6b1;
}

.menu-link.active i {
    color: white;
    transform: scale(1.2);
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
}

/* Collapse Icon */
.collapse-icon {
    margin-left: auto;
    font-size: 1.125rem;
    transition: transform 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

.menu-link[aria-expanded="true"] .collapse-icon {
    transform: rotate(180deg);
    color: #4f46e5;
}

/* Submenu */
.menu-dropdown {
    margin-left: 3rem;
    position: relative;
    overflow: hidden;
    max-height: 0;
    transition: max-height 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.menu-dropdown.show {
    max-height: 1000px;
}

.menu-dropdown::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(to bottom, 
        transparent 0%, 
        rgba(92, 246, 210, 0.4) 30%, 
        rgba(92, 246, 184, 0.4) 70%, 
        transparent 100%);
}

.submenu-item {
    margin: 0.125rem 0;
    opacity: 0;
    transform: translateY(-10px);
    animation: submenuSlide 0.4s ease-out var(--submenu-delay, 0s) forwards;
}

.submenu-link {
    display: flex;
    align-items: center;
    padding: 0.625rem 1rem 0.625rem 0;
    color: #648b74;
    border-radius: 8px;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    position: relative;
    overflow: hidden;
}

.submenu-link::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    width: 0;
    height: 100%;
    background: linear-gradient(90deg, rgba(92, 246, 200, 0.1), transparent);
    transition: width 0.3s ease;
    z-index: -1;
}

.submenu-link:hover {
    color: #46e5c3;
    transform: translateX(4px);
}

.submenu-link:hover::before {
    width: 100%;
}

.submenu-link.active {
    color: #46e5c3;
    background: rgba(92, 233, 246, 0.1);
    font-weight: 500;
}

.submenu-dot {
    width: 6px;
    height: 6px;
    background: #c7d2fe;
    border-radius: 50%;
    margin-right: 0.75rem;
    transition: all 0.3s ease;
    position: relative;
}

.submenu-link:hover .submenu-dot {
    background: #5cf6e9;
    transform: scale(1.3);
}

.submenu-link.active .submenu-dot {
    background: #46c8e5;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
    animation: dotPulse 1.5s infinite;
}

.submenu-text {
    transition: transform 0.3s ease;
}

.submenu-link:hover .submenu-text {
    transform: translateX(2px);
}

/* Animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideInLeft {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes submenuSlide {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes gradientShift {
    0%, 100% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
}

@keyframes pulseIcon {
    0%, 100% {
        opacity: 0.7;
        transform: translateY(-50%) scale(1);
    }
    50% {
        opacity: 1;
        transform: translateY(-50%) scale(1.1);
    }
}

@keyframes pulseActive {
    0% {
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    }
    70% {
        box-shadow: 0 0 0 6px rgba(16, 185, 129, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
    }
}

@keyframes dotPulse {
    0%, 100% {
        box-shadow: 0 0 0 0 rgba(139, 92, 246, 0.2);
    }
    50% {
        box-shadow: 0 0 0 4px rgba(139, 92, 246, 0);
    }
}

@keyframes lineExpand {
    from {
        transform: scaleX(0);
        opacity: 0;
    }
    to {
        transform: scaleX(1);
        opacity: 1;
    }
}

/* Hover Ripple Effect */
.menu-link::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 5px;
    height: 5px;
    background: rgba(139, 92, 246, 0.3);
    opacity: 0;
    border-radius: 100%;
    transform: scale(1, 1) translate(-50%, -50%);
    transform-origin: 50% 50%;
}

.menu-link:hover::after {
    animation: ripple 1s ease-out;
}

@keyframes ripple {
    0% {
        transform: scale(0, 0);
        opacity: 0.5;
    }
    100% {
        transform: scale(40, 40);
        opacity: 0;
    }
}

/* Staggered Animations */
.nav-item:nth-child(1) { --animation-delay: 0.1s; }
.nav-item:nth-child(2) { --animation-delay: 0.15s; }
.nav-item:nth-child(3) { --animation-delay: 0.2s; }
.nav-item:nth-child(4) { --animation-delay: 0.25s; }
.nav-item:nth-child(5) { --animation-delay: 0.3s; }
.nav-item:nth-child(6) { --animation-delay: 0.35s; }
.nav-item:nth-child(7) { --animation-delay: 0.4s; }
.nav-item:nth-child(8) { --animation-delay: 0.45s; }
.nav-item:nth-child(9) { --animation-delay: 0.5s; }
.nav-item:nth-child(10) { --animation-delay: 0.55s; }

.submenu-item:nth-child(1) { --submenu-delay: 0.1s; }
.submenu-item:nth-child(2) { --submenu-delay: 0.15s; }
.submenu-item:nth-child(3) { --submenu-delay: 0.2s; }
.submenu-item:nth-child(4) { --submenu-delay: 0.25s; }
.submenu-item:nth-child(5) { --submenu-delay: 0.3s; }
.submenu-item:nth-child(6) { --submenu-delay: 0.35s; }
.submenu-item:nth-child(7) { --submenu-delay: 0.4s; }
.submenu-item:nth-child(8) { --submenu-delay: 0.45s; }
.submenu-item:nth-child(9) { --submenu-delay: 0.5s; }

/* Responsive */
@media (max-width: 991.98px) {
    #navbar-nav {
        padding: 0.75rem 0.5rem;
    }
    
    .menu-link {
        padding: 0.625rem 0.75rem;
    }
    
    .menu-dropdown {
        margin-left: 2.5rem;
    }
}

/* Sidebar Collapsed */
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

/* Dark Mode */
@media (prefers-color-scheme: dark) {
    .menu-link {
        color: #cbd5e1;
        background: rgba(30, 41, 59, 0);
    }

    .menu-link:hover {
        color: #a5b4fc;
        background: rgba(30, 41, 59, 0.5);
        border-color: rgba(139, 92, 246, 0.3);
    }

    .menu-link.active {
        color: white;
        background: linear-gradient(135deg, #7c3aed, #5b21b6);
    }

    .menu-title-text {
        color: #94a3b8;
    }

    .menu-title-line {
        background: linear-gradient(90deg,
            transparent 0%,
            rgba(139, 92, 246, 0.5) 20%,
            rgba(139, 92, 246, 0.5) 80%,
            transparent 100%);
    }

    .submenu-link {
        color: #94a3b8;
    }

    .submenu-link:hover {
        color: #a5b4fc;
    }

    .submenu-link.active {
        color: #a5b4fc;
        background: rgba(139, 92, 246, 0.15);
    }
}
</style>