/**
 * Content source for the immersive User Manual book.
 *
 * Written for someone on their first day. Assume no prior knowledge: spell out
 * where to click, what will appear, and what to do when it does not.
 *
 * The manual ships in English and Tagalog. Both language variants share the
 * exact same page `id`, `kind`, `part` and `roleKey` values so that the TOC,
 * role-audience filtering and page-jump logic in UserManualBook.vue work
 * identically no matter which language array is in use — only the displayed
 * text differs. On-screen system labels (menu names, statuses, module names
 * such as "Sales Management" or "Purchase Order") are deliberately left in
 * English in both variants, since the running application itself is in
 * English and a translated label would no longer match what the reader sees
 * on their screen.
 *
 * Pages are rendered two-up as a book spread. Each `pagesXx` array is flat and
 * ordered: index 0 is the front cover, the final entry is the back cover, and
 * everything in between is a printed page. The book component pads the list
 * so the back cover always lands on the reverse side of a sheet.
 *
 * Block types understood by the renderer:
 *   lead   { text }                     - opening paragraph, larger type
 *   p      { text }                     - body paragraph
 *   list   { items: [] }                - bulleted list
 *   steps  { items: [{ title, text }] } - numbered walkthrough
 *   grid   { items: [{ label, value }] }- two-column fact grid
 *   note   { tone, title, text }        - callout; tone: info | warn | tip
 *   table  { head: [], rows: [[]] }     - compact reference table
 *   figure { art, caption, callouts }   - schematic of a screen
 *
 * ---------------------------------------------------------------------------
 * SWAPPING IN REAL SCREENSHOTS
 * ---------------------------------------------------------------------------
 * Every figure below draws a built-in schematic via `art`. To use a real
 * screenshot instead, drop the image in public/images/manual/ and replace
 * `art` with `src`:
 *
 *   { type: 'figure', art: 'dashboard', caption: 'The dashboard.' }
 *   ->
 *   { type: 'figure', src: '/images/manual/dashboard.png', caption: 'The dashboard.' }
 *
 * No component changes are needed. Keep images around 1200px wide; they are
 * displayed at roughly half a book page. Available `art` names:
 *   login, sidebar, dashboard, sales-order, purchase-order,
 *   stock-alert, payroll, employee, remittance, libraries
 */

export const PART_START = 'Getting Started';
export const PART_ROLES = 'Roles & Access';
export const PART_PROCESS = 'System Processes';

const PART_START_TL = 'Mga Unang Hakbang';
const PART_ROLES_TL = 'Mga Tungkulin & Access';
const PART_PROCESS_TL = 'Mga Proseso ng Sistema';

/**
 * Role names as they are actually enforced in the UI and route middleware
 * (see Shared/Layouts/Components/Menu.vue and routes/web.php). Names are not
 * translated: they are matched directly against the role names assigned in
 * the database and shown as-is in both language variants.
 */
export const roles = [
    {
        key: 'administrator',
        name: 'Administrator',
        tagline: 'Owns the system',
        icon: 'ri-shield-star-line',
        accent: '#8b5cf6',
    },
    {
        key: 'sales-rep',
        name: 'Sales Rep',
        tagline: 'Sells and collects',
        icon: 'ri-shopping-cart-2-line',
        accent: '#3b82f6',
    },
    {
        key: 'sales-manager',
        name: 'Sales Manager',
        tagline: 'Owns the customer book',
        icon: 'ri-line-chart-line',
        accent: '#0ea5e9',
    },
    {
        key: 'inventory-manager',
        name: 'Inventory Manager',
        tagline: 'Buys and stocks',
        icon: 'ri-archive-2-line',
        accent: '#f97316',
    },
    {
        key: 'hr-officer',
        name: 'Human Resource Officer',
        tagline: 'Runs people operations',
        icon: 'ri-team-line',
        accent: '#10b981',
    },
];

/** Static chrome text for the book UI itself (toolbar, nav, small labels). */
export const UI_STRINGS = {
    en: {
        bookTitle: 'User Manual',
        bookSubtitle: 'Roles & system processes',
        contents: 'Contents',
        cover: 'Cover',
        myRole: 'My role',
        everything: 'Everything',
        previous: 'Previous',
        next: 'Next',
        frontCover: 'Front cover',
        backCover: 'Back cover',
        pagesOf: (left, right, last) => `Pages ${left}–${right} of ${last}`,
        closeManual: 'Close manual',
        previousPage: 'Previous page',
        nextPage: 'Next page',
        yourRole: 'Your role',
        openHint: 'Click the page edge to open',
        blankPage: 'This page intentionally left blank',
        switchTo: 'Tagalog',
    },
    tl: {
        bookTitle: 'Manwal ng Gumagamit',
        bookSubtitle: 'Mga tungkulin at proseso ng sistema',
        contents: 'Nilalaman',
        cover: 'Pabalat',
        myRole: 'Aking Tungkulin',
        everything: 'Lahat',
        previous: 'Nakaraan',
        next: 'Susunod',
        frontCover: 'Harap na pabalat',
        backCover: 'Likod na pabalat',
        pagesOf: (left, right, last) => `Pahina ${left}–${right} ng ${last}`,
        closeManual: 'Isara ang manwal',
        previousPage: 'Nakaraang pahina',
        nextPage: 'Susunod na pahina',
        yourRole: 'Iyong tungkulin',
        openHint: 'I-click ang gilid ng pahina para buksan',
        blankPage: 'Sadyang iniwang walang laman ang pahinang ito',
        switchTo: 'English',
    },
};

/**
 * Which roles each page is written for, by page id. Shared by both language
 * variants since ids match one-to-one.
 *
 * A page listed here is shown only to those roles while the reader has the
 * "My role" filter on, so a Sales Rep opens the book and finds their own job
 * rather than everyone else's. Any page NOT listed here is considered general
 * and is always shown — that covers the cover, contents, all of Getting
 * Started, the access matrix and the reference pages at the back.
 *
 * Values are role `key`s from the `roles` array above.
 */
const AUDIENCE = {
    // Part II - each role reads only its own pages
    'role-administrator': ['administrator'],
    'role-administrator-day': ['administrator'],
    'role-sales-rep': ['sales-rep'],
    'role-sales-rep-day': ['sales-rep'],
    'role-sales-manager': ['sales-manager'],
    'role-inventory-manager': ['inventory-manager'],
    'role-inventory-manager-day': ['inventory-manager'],
    'role-hr-officer': ['hr-officer'],

    // Part III - processes go to whoever actually performs them
    'process-sales': ['sales-rep', 'administrator'],
    'process-receipt': ['sales-rep', 'administrator'],
    'process-ar': ['sales-rep', 'administrator'],
    'process-customers': ['sales-manager', 'administrator'],
    'process-purchasing': ['inventory-manager', 'administrator'],
    'process-receiving': ['inventory-manager', 'administrator'],
    'process-inventory': ['inventory-manager', 'administrator'],
    'process-employees': ['hr-officer', 'administrator'],
    'process-payroll': ['hr-officer', 'administrator'],
    'process-loans': ['hr-officer', 'administrator'],
    'process-libraries': ['administrator'],
    'process-expenses': ['administrator'],
};

const withAudience = (list) => list.map((page) => (
    AUDIENCE[page.id] ? { ...page, audience: AUDIENCE[page.id] } : page
));

/* =========================================================================
 * ENGLISH
 * ========================================================================= */
const rawPagesEn = [
    /* ---------------------------------------------------------------- 0 */
    {
        id: 'cover',
        kind: 'cover',
        title: 'User Manual',
        subtitle: 'A first-day guide to the system, its roles, and how work gets done',
        edition: 'BRT Software — Operations Handbook',
    },

    /* ---------------------------------------------------------------- 1 */
    {
        id: 'how-to-use',
        kind: 'plain',
        kicker: 'Read this first',
        title: 'How to use this manual',
        blocks: [
            {
                type: 'lead',
                text: 'You do not have to read this cover to cover. It is built to be opened at the page you need, on the day you need it.',
            },
            {
                type: 'steps',
                items: [
                    {
                        title: 'New here? Start at Getting Started',
                        text: 'It explains what the system is for, how to log in, and what everything on screen is called.',
                    },
                    {
                        title: 'Then read your own role',
                        text: 'Part I tells you which menu items you will see and what you are responsible for.',
                    },
                    {
                        title: 'Keep Part II open while you work',
                        text: 'Every task is written as numbered steps you can follow along with.',
                    },
                ],
            },
            {
                type: 'note',
                tone: 'tip',
                title: 'Turning pages',
                text: 'Click the outer edge of a page, use the left and right arrow keys, or pick any chapter from the list on the left. Press Esc to close the book.',
            },
            {
                type: 'note',
                tone: 'info',
                title: 'The pictures are diagrams',
                text: 'Illustrations show the shape and layout of each screen so you know what to look for. Your real screen will have actual names and numbers in it.',
            },
        ],
    },

    /* ---------------------------------------------------------------- 2 */
    {
        id: 'contents',
        kind: 'toc',
        kicker: 'Navigation',
        title: 'Contents',
    },

    /* ---------------------------------------------------------------- 3 */
    {
        id: 'part-start',
        kind: 'part',
        part: PART_START,
        number: 'Part I',
        title: 'Getting Started',
        blurb: 'For your first day. No prior knowledge assumed.',
    },

    /* ---------------------------------------------------------------- 4 */
    {
        id: 'what-is-this',
        kind: 'plain',
        part: PART_START,
        kicker: 'Part I · Orientation',
        title: 'What this system is for',
        blocks: [
            {
                type: 'lead',
                text: 'This system keeps track of four things: what the business buys, what it holds in stock, what it sells, and who it pays. Every screen you will use belongs to one of those four.',
            },
            {
                type: 'steps',
                items: [
                    {
                        title: 'We buy',
                        text: 'A purchase order is sent to a supplier. When the goods arrive, they are received into stock.',
                    },
                    {
                        title: 'We hold',
                        text: 'Stock levels go up when goods arrive and down when they are sold. The system warns you when an item is running low.',
                    },
                    {
                        title: 'We sell',
                        text: 'A sales order records what a customer bought. A receipt records what they paid.',
                    },
                    {
                        title: 'We pay',
                        text: 'Employees are paid through payroll, minus any loan repayments and deductions.',
                    },
                ],
            },
            {
                type: 'note',
                tone: 'info',
                title: 'Why this matters',
                text: 'These four flows connect. A sale reduces stock. Low stock triggers a purchase. Every screen is a step in one of those chains, which is why entering something in the wrong place causes trouble later.',
            },
        ],
    },

    /* ---------------------------------------------------------------- 5 */
    {
        id: 'first-login',
        kind: 'plain',
        part: PART_START,
        kicker: 'Part I · Step by step',
        title: 'Your first sign-in',
        blocks: [
            {
                type: 'figure',
                art: 'login',
                caption: 'The sign-in screen. Your username is not your email address.',
            },
            {
                type: 'steps',
                items: [
                    {
                        title: 'Open the address',
                        text: 'Use the web address your Administrator gave you. Bookmark it.',
                    },
                    {
                        title: 'Type your username',
                        text: 'This is the short name you were given, not your full email.',
                    },
                    {
                        title: 'Type your password',
                        text: 'If this is your first time, use the temporary password you were sent.',
                    },
                    {
                        title: 'Change your password',
                        text: 'Set your own password straight away. Nobody else should know it, including your supervisor.',
                    },
                ],
            },
            {
                type: 'note',
                tone: 'warn',
                title: 'If it will not let you in',
                text: 'Do not keep retrying. Either your account has not been activated yet, or your email address has not been verified. Ask an Administrator to check both.',
            },
        ],
    },

    /* ---------------------------------------------------------------- 6 */
    {
        id: 'screen-tour',
        kind: 'plain',
        part: PART_START,
        kicker: 'Part I · Orientation',
        title: 'What everything is called',
        blocks: [
            {
                type: 'figure',
                art: 'sidebar',
                caption: 'The dark strip down the left is the menu. The large area to the right is the page.',
                callouts: ['Menu items you do not have permission for are simply not shown'],
            },
            {
                type: 'list',
                items: [
                    'Menu (or sidebar) — the dark strip on the left. Every part of the system is reached from here.',
                    'Page — the large white area. It changes when you click a menu item.',
                    'List — most pages open as a list of existing records, newest first.',
                    'Record — one row in that list: one sale, one employee, one purchase order.',
                    'Form — the screen where you type in a new record or edit an existing one.',
                ],
            },
            {
                type: 'note',
                tone: 'tip',
                title: 'Your menu is shorter than your colleague\'s',
                text: 'That is normal and not a fault. The menu only shows what your role is allowed to open. Part II explains which role sees what.',
            },
        ],
    },

    /* ---------------------------------------------------------------- 7 */
    {
        id: 'dashboard-tour',
        kind: 'plain',
        part: PART_START,
        kicker: 'Part I · Orientation',
        title: 'Your home screen',
        blocks: [
            {
                type: 'figure',
                art: 'dashboard',
                caption: 'The dashboard. Tabs across the top, summary cards below, charts underneath.',
            },
            {
                type: 'p',
                text: 'The dashboard is the first thing you see after signing in. It is a summary only — nothing here is edited directly.',
            },
            {
                type: 'steps',
                items: [
                    {
                        title: 'Pick a tab',
                        text: 'Sales, Inventory and Team each show a different side of the business.',
                    },
                    {
                        title: 'Read the cards',
                        text: 'The row of boxes shows totals for right now, with the change since last month.',
                    },
                    {
                        title: 'Check the charts',
                        text: 'The graphs show the trend over recent months, so you can see direction, not just today.',
                    },
                    {
                        title: 'Act on the alerts',
                        text: 'The Inventory tab lists items running low. That list is your to-do list for reordering.',
                    },
                ],
            },
            {
                type: 'note',
                tone: 'tip',
                title: 'You are reading it now',
                text: 'The User Manual button sits at the top right of the dashboard. This book is always one click away.',
            },
        ],
    },

    /* ---------------------------------------------------------------- 8 */
    {
        id: 'words',
        kind: 'plain',
        part: PART_START,
        kicker: 'Part I · Plain English',
        title: 'Words you will hear',
        blocks: [
            {
                type: 'p',
                text: 'Nobody will explain these to you twice. Here they are in plain language.',
            },
            {
                type: 'table',
                head: ['Word', 'What it actually means'],
                rows: [
                    ['Sales order', 'A record of what a customer bought'],
                    ['Receipt', 'A record of money the customer paid'],
                    ['AR invoice', 'A bill for a sale not yet fully paid'],
                    ['Outstanding', 'Money a customer still owes us'],
                    ['Remittance', 'Cash a rep hands back to the office'],
                    ['Purchase order (PO)', 'An order we send to a supplier'],
                    ['Received stock', 'Goods that actually arrived from a PO'],
                    ['Batch', 'One delivery, given a code so it can be traced'],
                    ['Adjustment', 'A correction to a saved record'],
                    ['Minimum stock', 'The level at which we should reorder'],
                    ['Payroll run', 'One pay period being calculated'],
                    ['Deduction', 'Money taken off pay, e.g. a loan repayment'],
                    ['Library', 'A master list the dropdowns are filled from'],
                    ['Role', 'What you are allowed to open and do'],
                ],
            },
        ],
    },

    /* ---------------------------------------------------------------- 9 */
    {
        id: 'golden-rules',
        kind: 'plain',
        part: PART_START,
        kicker: 'Part I · Habits',
        title: 'Six rules that keep you safe',
        blocks: [
            {
                type: 'steps',
                items: [
                    {
                        title: 'Search before you create',
                        text: 'Duplicate customers and products are the single most common mistake. Look first.',
                    },
                    {
                        title: 'Correct, do not delete',
                        text: 'Use an adjustment. Deleting destroys the history that reports and audits depend on.',
                    },
                    {
                        title: 'Deactivate, do not remove',
                        text: 'When someone leaves or a product is discontinued, set it inactive. Old records still need it.',
                    },
                    {
                        title: 'Enter what happened',
                        text: 'Record the quantity actually delivered and the amount actually paid, even when it differs from what was expected.',
                    },
                    {
                        title: 'Finish what you start',
                        text: 'A half-entered order blocks the next person. Complete it or cancel it.',
                    },
                    {
                        title: 'Ask before approving',
                        text: 'Approval is a signature. If you are unsure, ask first — undoing one is much harder.',
                    },
                ],
            },
            {
                type: 'note',
                tone: 'warn',
                title: 'The one thing never to do',
                text: 'Never share your sign-in. Every record stores who created it. If someone uses your account, their mistake carries your name.',
            },
        ],
    },

    /* --------------------------------------------------------------- 10 */
    {
        id: 'part-roles',
        kind: 'part',
        part: PART_ROLES,
        number: 'Part II',
        title: 'Roles & Access',
        blurb: 'Who can do what, and where it lives in the menu.',
    },

    /* --------------------------------------------------------------- 11 */
    {
        id: 'role-administrator',
        kind: 'plain',
        part: PART_ROLES,
        roleKey: 'administrator',
        kicker: 'Part II · Role',
        title: 'Administrator',
        blocks: [
            {
                type: 'lead',
                text: 'Full access to everything. The Administrator is the only role allowed into users, libraries, purchasing, payroll, loans and expenses.',
            },
            {
                type: 'grid',
                items: [
                    { label: 'Menu access', value: 'Everything' },
                    { label: 'Typical holder', value: 'System owner / IT' },
                    { label: 'Approves', value: 'Remittances, payroll' },
                    { label: 'Manages roles', value: 'Yes' },
                ],
            },
            {
                type: 'list',
                items: [
                    'Create user accounts and give or remove roles.',
                    'Maintain every library: products, brands, units, locations, statuses, suppliers, positions, roles.',
                    'Approve remittances and move payroll and purchase orders along.',
                    'Read guest messages sent from the public contact form.',
                ],
            },
            {
                type: 'note',
                tone: 'warn',
                title: 'Handle with care',
                text: 'Deactivating a user or a role takes effect immediately. There is no warning and no grace period.',
            },
        ],
    },

    /* --------------------------------------------------------------- 12 */
    {
        id: 'role-administrator-day',
        kind: 'plain',
        part: PART_ROLES,
        roleKey: 'administrator',
        kicker: 'Part II · Administrator',
        title: 'A day as Administrator',
        blocks: [
            {
                type: 'steps',
                items: [
                    {
                        title: 'Check the dashboard',
                        text: 'Sales, Inventory and Team tabs give the health of the business in one screen.',
                    },
                    {
                        title: 'Clear approvals',
                        text: 'Remittances waiting for approval, and payrolls sitting in Draft, block everyone downstream.',
                    },
                    {
                        title: 'Review stock alerts',
                        text: 'Raise purchase orders for anything flagged Low or Critical before it reaches zero.',
                    },
                    {
                        title: 'Audit accounts',
                        text: 'Under Accounts, deactivate anyone who has left and check nobody holds a role they no longer need.',
                    },
                ],
            },
        ],
    },

    /* --------------------------------------------------------------- 13 */
    {
        id: 'role-sales-rep',
        kind: 'plain',
        part: PART_ROLES,
        roleKey: 'sales-rep',
        kicker: 'Part II · Role',
        title: 'Sales Rep',
        blocks: [
            {
                type: 'lead',
                text: 'The front line. A Sales Rep records what customers buy, collects the money, and hands it back to the office.',
            },
            {
                type: 'grid',
                items: [
                    { label: 'Menu access', value: 'Sales Management, Payroll' },
                    { label: 'You create', value: 'Sales orders, receipts' },
                    { label: 'You cannot', value: 'Edit libraries or users' },
                    { label: 'Reports to', value: 'Sales Manager' },
                ],
            },
            {
                type: 'list',
                items: [
                    'Record a sale against a customer and the products they bought.',
                    'Collect payment and issue the receipt that closes the order.',
                    'Hand cash back to the office through a remittance.',
                    'Keep track of which customers still owe money.',
                ],
            },
            {
                type: 'note',
                tone: 'info',
                title: 'Stock drops when you sell',
                text: 'Saving an order reduces the stock count. If a product will not appear in the list, check whether it has run out before assuming the screen is broken.',
            },
        ],
    },

    /* --------------------------------------------------------------- 14 */
    {
        id: 'role-sales-rep-day',
        kind: 'plain',
        part: PART_ROLES,
        roleKey: 'sales-rep',
        kicker: 'Part II · Sales Rep',
        title: 'A day as Sales Rep',
        blocks: [
            {
                type: 'steps',
                items: [
                    {
                        title: 'Open Sales Management',
                        text: 'Review yesterday\'s orders and anything still marked Unpaid or Partially Paid.',
                    },
                    {
                        title: 'Record new orders',
                        text: 'One order per customer transaction. Add every line before saving.',
                    },
                    {
                        title: 'Issue receipts',
                        text: 'Record the payment as you collect it, so the order moves to Paid.',
                    },
                    {
                        title: 'Remit at end of day',
                        text: 'Submit a remittance covering the cash you collected. An Administrator approves it.',
                    },
                ],
            },
            {
                type: 'note',
                tone: 'tip',
                title: 'Made a mistake?',
                text: 'A saved order can still be corrected with an adjustment. You do not need to delete it and start again.',
            },
        ],
    },

    /* --------------------------------------------------------------- 15 */
    {
        id: 'role-sales-manager',
        kind: 'plain',
        part: PART_ROLES,
        roleKey: 'sales-manager',
        kicker: 'Part II · Role',
        title: 'Sales Manager',
        blocks: [
            {
                type: 'lead',
                text: 'Owns the customer list. This role unlocks the Customers menu, where accounts and contact details are kept up to date.',
            },
            {
                type: 'grid',
                items: [
                    { label: 'Menu access', value: 'Customers, Payroll' },
                    { label: 'You own', value: 'Customer records' },
                    { label: 'You watch', value: 'Money owed to us' },
                    { label: 'You cannot', value: 'Approve remittances' },
                ],
            },
            {
                type: 'list',
                items: [
                    'Create and maintain the customer records every sales order selects from.',
                    'Keep addresses and contact numbers current so deliveries and invoices arrive.',
                    'Watch which customers carry the largest unpaid balances.',
                    'Work with Administrators on incentives paid through payroll.',
                ],
            },
            {
                type: 'note',
                tone: 'warn',
                title: 'Never create a duplicate customer',
                text: 'Search first. Two records for one customer split their payment history in half and make collection almost impossible.',
            },
        ],
    },

    /* --------------------------------------------------------------- 16 */
    {
        id: 'role-inventory-manager',
        kind: 'plain',
        part: PART_ROLES,
        roleKey: 'inventory-manager',
        kicker: 'Part II · Role',
        title: 'Inventory Manager',
        blocks: [
            {
                type: 'lead',
                text: 'Everything that arrives and everything on the shelf. This role covers purchase orders, deliveries and stock levels.',
            },
            {
                type: 'grid',
                items: [
                    { label: 'Menu access', value: 'Inventory Management' },
                    { label: 'You create', value: 'POs, received stock' },
                    { label: 'You maintain', value: 'Stock levels, batches' },
                    { label: 'You work with', value: 'Suppliers' },
                ],
            },
            {
                type: 'list',
                items: [
                    'Send purchase orders to suppliers and track where each one has got to.',
                    'Receive deliveries into batches, recording what actually arrived.',
                    'Post adjustments when a physical count disagrees with the system.',
                    'Update selling prices held against stock.',
                ],
            },
            {
                type: 'note',
                tone: 'info',
                title: 'Batches matter',
                text: 'Each delivery gets a batch code. Receiving goods against the wrong batch makes stock impossible to trace later.',
            },
        ],
    },

    /* --------------------------------------------------------------- 17 */
    {
        id: 'role-inventory-manager-day',
        kind: 'plain',
        part: PART_ROLES,
        roleKey: 'inventory-manager',
        kicker: 'Part II · Inventory Manager',
        title: 'A day as Inventory Manager',
        blocks: [
            {
                type: 'steps',
                items: [
                    {
                        title: 'Read the low stock alert',
                        text: 'The dashboard Inventory tab lists everything below its minimum, worst first.',
                    },
                    {
                        title: 'Raise purchase orders',
                        text: 'The PO number is filled in for you. Pick the supplier, add the items, save.',
                    },
                    {
                        title: 'Receive deliveries',
                        text: 'Match the delivery to its open PO and record it under a batch code.',
                    },
                    {
                        title: 'Reconcile',
                        text: 'Where the count differs from the system, post an adjustment with a reason.',
                    },
                ],
            },
        ],
    },

    /* --------------------------------------------------------------- 18 */
    {
        id: 'role-hr-officer',
        kind: 'plain',
        part: PART_ROLES,
        roleKey: 'hr-officer',
        kicker: 'Part II · Role',
        title: 'Human Resource Officer',
        blocks: [
            {
                type: 'lead',
                text: 'People operations. This role unlocks the Employees and Loans menus, and prepares the information payroll runs on.',
            },
            {
                type: 'grid',
                items: [
                    { label: 'Menu access', value: 'Employees, Loans, Accounts' },
                    { label: 'You maintain', value: 'Employee records' },
                    { label: 'You process', value: 'Loans, deductions' },
                    { label: 'You cannot', value: 'Edit inventory or sales' },
                ],
            },
            {
                type: 'list',
                items: [
                    'Create employee records and keep positions, salaries and locations correct.',
                    'Record loans and the deduction schedule that repays them.',
                    'Prepare the list of employees that feeds each payroll.',
                    'Track attendance and leave.',
                ],
            },
            {
                type: 'note',
                tone: 'warn',
                title: 'Employee data decides pay',
                text: 'Position and salary on the employee record drive the payroll figures. Fix them before the run, not after.',
            },
        ],
    },

    /* --------------------------------------------------------------- 19 */
    {
        id: 'role-matrix',
        kind: 'plain',
        part: PART_ROLES,
        kicker: 'Part II · Reference',
        title: 'Access at a glance',
        blocks: [
            {
                type: 'p',
                text: 'A menu item appears only if your role is listed against it. If you hold more than one role, you see the menus for all of them combined.',
            },
            {
                type: 'table',
                head: ['Menu', 'Roles that see it'],
                rows: [
                    ['Dashboard', 'Everyone'],
                    ['Employees', 'HR Officer, Administrator'],
                    ['Accounts', 'HR Officer, Administrator'],
                    ['Inventory Management', 'Inventory Manager, Administrator'],
                    ['Sales Management', 'Sales Rep, Administrator'],
                    ['Customers', 'Sales Manager, Administrator'],
                    ['Suppliers', 'Administrator'],
                    ['Expenses', 'Administrator'],
                    ['Payroll', 'Sales Rep, Inventory Mgr, Administrator'],
                    ['Loans', 'HR Officer, Administrator'],
                    ['Guest Messages', 'Administrator'],
                    ['Libraries', 'Administrator'],
                ],
            },
        ],
    },

    /* --------------------------------------------------------------- 20 */
    {
        id: 'part-process',
        kind: 'part',
        part: PART_PROCESS,
        number: 'Part III',
        title: 'System Processes',
        blurb: 'Every task, written as steps you can follow along with.',
    },

    /* --------------------------------------------------------------- 21 */
    {
        id: 'process-signin',
        kind: 'plain',
        part: PART_PROCESS,
        kicker: 'Part III · Process',
        title: 'Accounts & security',
        blocks: [
            {
                type: 'lead',
                text: 'Four things must all be true before any page will open: you are signed in, your email is verified, your account is active, and you have passed two-factor if it is switched on.',
            },
            {
                type: 'steps',
                items: [
                    { title: 'Sign in', text: 'Username and password.' },
                    { title: 'Verify your email', text: 'Until you click the emailed link you will be sent back to the verification page.' },
                    { title: 'Two-factor code', text: 'If enabled on your account, enter the code from your authenticator app.' },
                    { title: 'Set your own password', text: 'New accounts are flagged so you are prompted to replace the temporary one.' },
                ],
            },
            {
                type: 'note',
                tone: 'warn',
                title: 'Locked out',
                text: 'An account marked inactive cannot sign in at all, no matter how correct the password is. Only an Administrator can reactivate it under Accounts.',
            },
        ],
    },

    /* --------------------------------------------------------------- 22 */
    {
        id: 'process-sales',
        kind: 'plain',
        part: PART_PROCESS,
        kicker: 'Part III · Process',
        title: 'Recording a sale',
        blocks: [
            {
                type: 'figure',
                art: 'sales-order',
                caption: 'A sales order. Customer at the top, one row per product, total at the bottom.',
                callouts: ['Pick the customer first', 'One row per product'],
            },
            {
                type: 'steps',
                items: [
                    { title: 'Open Sales Management', text: 'From the menu on the left, then start a new order.' },
                    { title: 'Choose the customer', text: 'Search by name. If they are missing, ask the Sales Manager to add them — do not invent one.' },
                    { title: 'Add each product', text: 'One row per product, with quantity and price. Add every item before saving.' },
                    { title: 'Check the total', text: 'Compare it against what the customer is actually paying.' },
                    { title: 'Save', text: 'The order is recorded as Unpaid and the stock is reduced.' },
                ],
            },
            {
                type: 'note',
                tone: 'info',
                title: 'Sales made outside the usual channel',
                text: 'These go under Sales Orders External. Same steps, reported separately.',
            },
        ],
    },

    /* --------------------------------------------------------------- 23 */
    {
        id: 'process-receipt',
        kind: 'plain',
        part: PART_PROCESS,
        kicker: 'Part III · Process',
        title: 'Taking payment',
        blocks: [
            {
                type: 'lead',
                text: 'An order says what was bought. A receipt says what was paid. A sale is only finished when both exist and agree.',
            },
            {
                type: 'steps',
                items: [
                    { title: 'Open the order', text: 'Find it in the Sales Management list.' },
                    { title: 'Record the receipt', text: 'Enter the amount handed over and how it was paid — cash, card or bank transfer.' },
                    { title: 'Watch the status change', text: 'Paying in full moves it to Paid. Paying part of it moves it to Partially Paid.' },
                    { title: 'Leave the rest outstanding', text: 'Whatever is unpaid stays as an AR invoice until it is collected.' },
                ],
            },
            {
                type: 'note',
                tone: 'warn',
                title: 'Never receipt money you have not received',
                text: 'The receipt is the record that the cash exists. Once entered, you are accountable for it.',
            },
        ],
    },

    /* --------------------------------------------------------------- 24 */
    {
        id: 'process-ar',
        kind: 'plain',
        part: PART_PROCESS,
        kicker: 'Part III · Process',
        title: 'Handing cash back to the office',
        blocks: [
            {
                type: 'figure',
                art: 'remittance',
                caption: 'A remittance is only complete once an Administrator has approved it.',
                callouts: ['Submitted is not the same as cleared'],
            },
            {
                type: 'steps',
                items: [
                    { title: 'Count what you collected', text: 'Total the cash you took in over the period.' },
                    { title: 'Create the remittance', text: 'Enter the amount and the period it covers.' },
                    { title: 'Submit it', text: 'It now sits waiting for an Administrator.' },
                    { title: 'Get it approved', text: 'Until that happens the money is still recorded against you.' },
                    { title: 'Print the copy', text: 'An approved remittance can be printed as your acknowledgement.' },
                ],
            },
            {
                type: 'note',
                tone: 'warn',
                title: 'Chase the approval',
                text: 'A submitted but unapproved remittance does not clear your accountability. Follow it up the same day.',
            },
        ],
    },

    /* --------------------------------------------------------------- 25 */
    {
        id: 'process-customers',
        kind: 'plain',
        part: PART_PROCESS,
        kicker: 'Part III · Process',
        title: 'Managing customers',
        blocks: [
            {
                type: 'lead',
                text: 'Every sales order has to point at a customer record. Keeping that list clean is what makes the receivables figures trustworthy.',
            },
            {
                type: 'steps',
                items: [
                    { title: 'Search first', text: 'Always. Check for the customer under any spelling before creating a new one.' },
                    { title: 'Create the record', text: 'Customers, then new. Capture the business name, contact person and address.' },
                    { title: 'Keep it current', text: 'Update the phone number and address as soon as they change, or deliveries go astray.' },
                    { title: 'Watch the balances', text: 'Review which customers carry the largest unpaid amounts and follow them up.' },
                ],
            },
            {
                type: 'note',
                tone: 'warn',
                title: 'Duplicates are expensive',
                text: 'Two records for the same customer split their payment history. Neither shows the true balance, and collection stalls.',
            },
        ],
    },

    /* --------------------------------------------------------------- 26 */
    {
        id: 'process-purchasing',
        kind: 'plain',
        part: PART_PROCESS,
        kicker: 'Part III · Process',
        title: 'Ordering from a supplier',
        blocks: [
            {
                type: 'figure',
                art: 'purchase-order',
                caption: 'A purchase order and the three states it moves through.',
                callouts: ['The PO number is generated for you'],
            },
            {
                type: 'steps',
                items: [
                    { title: 'Open Purchase Orders', text: 'Under Inventory Management. Start a new one.' },
                    { title: 'Pick the supplier', text: 'From the Suppliers library. The PO number fills in automatically.' },
                    { title: 'List what you need', text: 'One row per product, with the quantity ordered.' },
                    { title: 'Save and print', text: 'Print it to send to the supplier.' },
                    { title: 'Track it', text: 'It moves Pending to Approved to Completed as things progress.' },
                ],
            },
        ],
    },

    /* --------------------------------------------------------------- 26 */
    {
        id: 'process-receiving',
        kind: 'plain',
        part: PART_PROCESS,
        kicker: 'Part III · Process',
        title: 'Receiving a delivery',
        blocks: [
            {
                type: 'lead',
                text: 'The purchase order says what you asked for. Received stock says what actually turned up. They are often not the same, and that is exactly why both exist.',
            },
            {
                type: 'steps',
                items: [
                    { title: 'Find the open PO', text: 'Match the delivery to the order it belongs to.' },
                    { title: 'Create a received stock record', text: 'A batch code is generated for this delivery.' },
                    { title: 'Count what arrived', text: 'Physically count it. Do not assume the delivery note is right.' },
                    { title: 'Enter the real quantity', text: 'Record what arrived, not what was ordered, even if it is short.' },
                    { title: 'Save', text: 'Stock levels go up by the amount you entered.' },
                ],
            },
            {
                type: 'note',
                tone: 'warn',
                title: 'Short deliveries',
                text: 'If you enter the ordered quantity instead of the delivered quantity, the system believes stock exists that is not on the shelf. Somebody will sell it.',
            },
        ],
    },

    /* --------------------------------------------------------------- 27 */
    {
        id: 'process-inventory',
        kind: 'plain',
        part: PART_PROCESS,
        kicker: 'Part III · Process',
        title: 'Watching stock levels',
        blocks: [
            {
                type: 'figure',
                art: 'stock-alert',
                caption: 'The low stock alert. Colour shows how urgent each item is.',
            },
            {
                type: 'table',
                head: ['Label', 'What to do'],
                rows: [
                    ['Good', 'Nothing'],
                    ['Low', 'Reorder soon'],
                    ['Critical', 'Reorder today'],
                    ['Out', 'Cannot be sold — reorder now'],
                ],
            },
            {
                type: 'steps',
                items: [
                    { title: 'Count', text: 'Compare the physical shelf against the system figure.' },
                    { title: 'Adjust', text: 'Post an adjustment for the difference.' },
                    { title: 'Say why', text: 'Spoilage, breakage, counting error. An unexplained adjustment looks like theft.' },
                ],
            },
        ],
    },

    /* --------------------------------------------------------------- 28 */
    {
        id: 'process-employees',
        kind: 'plain',
        part: PART_PROCESS,
        kicker: 'Part III · Process',
        title: 'Adding a new employee',
        blocks: [
            {
                type: 'figure',
                art: 'employee',
                caption: 'An employee record. Position and salary here decide what payroll pays.',
                callouts: ['Link the sign-in account here'],
            },
            {
                type: 'steps',
                items: [
                    { title: 'Create the employee', text: 'Employees, then new. Fill in the personal details.' },
                    { title: 'Set position and salary', text: 'Chosen from the libraries. These drive their pay.' },
                    { title: 'Create their account', text: 'Under Accounts, so they can sign in.' },
                    { title: 'Give them a role', text: 'Without a role they can see the dashboard and nothing else.' },
                    { title: 'When they leave', text: 'Deactivate the account. Do not delete the employee — payroll history depends on it.' },
                ],
            },
        ],
    },

    /* --------------------------------------------------------------- 29 */
    {
        id: 'process-payroll',
        kind: 'plain',
        part: PART_PROCESS,
        kicker: 'Part III · Process',
        title: 'Running payroll',
        blocks: [
            {
                type: 'figure',
                art: 'payroll',
                caption: 'A payroll run, and the states it passes through before anyone is paid.',
            },
            {
                type: 'steps',
                items: [
                    { title: 'Check the settings', text: 'Payroll Settings holds the rates and deductions applied to every run.' },
                    { title: 'Build the template', text: 'Create it, then add the employees who belong in it.' },
                    { title: 'Generate the run', text: 'Create the payroll for the period from that template.' },
                    { title: 'Review every line', text: 'Check gross pay, deductions, loan repayments and incentives per person.' },
                    { title: 'Move it along', text: 'Draft, then approval, then For Release, then Completed.' },
                    { title: 'Print payslips', text: 'Once released.' },
                ],
            },
            {
                type: 'note',
                tone: 'warn',
                title: 'Check before approving',
                text: 'Fixing an approved payroll is far harder than reviewing it properly first.',
            },
        ],
    },

    /* --------------------------------------------------------------- 30 */
    {
        id: 'process-loans',
        kind: 'plain',
        part: PART_PROCESS,
        kicker: 'Part III · Process',
        title: 'Employee loans',
        blocks: [
            {
                type: 'lead',
                text: 'A loan is recorded once, then repays itself automatically out of each payroll until the balance reaches zero.',
            },
            {
                type: 'steps',
                items: [
                    { title: 'Record the loan', text: 'Loans, then new. Pick the employee and enter the amount.' },
                    { title: 'Set the deduction', text: 'How much comes off each pay period.' },
                    { title: 'Let payroll do the rest', text: 'Every run deducts it and reduces the balance.' },
                    { title: 'Close it', text: 'When fully repaid it becomes Closed or Liquidated.' },
                ],
            },
            {
                type: 'note',
                tone: 'warn',
                title: 'Never delete a running loan',
                text: 'That erases the repayment history and past payrolls stop reconciling. Close it instead.',
            },
        ],
    },

    /* --------------------------------------------------------------- 31 */
    {
        id: 'process-libraries',
        kind: 'plain',
        part: PART_PROCESS,
        kicker: 'Part III · Process',
        title: 'Libraries — the master lists',
        blocks: [
            {
                type: 'figure',
                art: 'libraries',
                caption: 'The eight libraries. Everything you pick from a dropdown comes from one of these.',
            },
            {
                type: 'p',
                text: 'If a product, supplier or position is missing from a dropdown, it is missing from its library — or it has been set inactive.',
            },
            {
                type: 'note',
                tone: 'warn',
                title: 'Deactivate, never delete',
                text: 'Old orders still point at these entries. Setting one inactive hides it from new records while leaving history intact.',
            },
        ],
    },

    /* --------------------------------------------------------------- 32 */
    {
        id: 'process-expenses',
        kind: 'plain',
        part: PART_PROCESS,
        kicker: 'Part III · Process',
        title: 'Expenses & guest messages',
        blocks: [
            {
                type: 'p',
                text: 'Two smaller Administrator-only areas.',
            },
            {
                type: 'list',
                items: [
                    'Expenses records running costs that are not purchases or wages, so the profit picture is complete.',
                    'Guest Messages collects enquiries sent through the contact form on the public website.',
                    'Mark a message as read once it has been dealt with.',
                ],
            },
            {
                type: 'note',
                tone: 'tip',
                title: 'Keep the inbox clean',
                text: 'Marking messages read is the only way to tell handled enquiries from new ones.',
            },
        ],
    },

    /* --------------------------------------------------------------- 33 */
    {
        id: 'reference-statuses',
        kind: 'plain',
        part: PART_PROCESS,
        kicker: 'Part III · Reference',
        title: 'Status glossary',
        blocks: [
            {
                type: 'p',
                text: 'These words mean the same thing everywhere they appear.',
            },
            {
                type: 'table',
                head: ['Status', 'Meaning'],
                rows: [
                    ['Draft', 'Started, not submitted'],
                    ['Pending', 'Submitted, waiting'],
                    ['Approval', 'Sitting with an approver'],
                    ['Approved', 'Cleared to proceed'],
                    ['Disapproved', 'Rejected, needs rework'],
                    ['Open', 'Active and in progress'],
                    ['Unpaid', 'Nothing collected yet'],
                    ['Partially Paid', 'Some balance remains'],
                    ['Paid', 'Settled in full'],
                    ['For Release', 'Cleared, awaiting payout'],
                    ['For Payment', 'Queued for disbursement'],
                    ['Adjusted', 'Corrected after saving'],
                    ['Sales Returned', 'Goods came back'],
                    ['Liquidated', 'Obligation settled'],
                    ['Completed', 'Finished'],
                    ['Closed', 'Archived'],
                    ['Cancelled', 'Voided before completion'],
                ],
            },
        ],
    },

    /* --------------------------------------------------------------- 34 */
    {
        id: 'reference-help',
        kind: 'plain',
        part: PART_PROCESS,
        kicker: 'Part III · Reference',
        title: 'When something goes wrong',
        blocks: [
            {
                type: 'table',
                head: ['What you see', 'What it usually means'],
                rows: [
                    ['A menu item is missing', 'Your role does not include it'],
                    ['Cannot sign in', 'Account inactive or email unverified'],
                    ['Product not in the list', 'Out of stock, or set inactive'],
                    ['Customer not in the list', 'Not created yet — ask the Sales Manager'],
                    ['Payroll amount looks wrong', 'Employee salary or position is out of date'],
                    ['Loan still being deducted', 'Loan has not been closed'],
                    ['Remittance not cleared', 'Waiting for Administrator approval'],
                    ['Stock does not match the shelf', 'Adjustment not posted yet'],
                ],
            },
            {
                type: 'note',
                tone: 'info',
                title: 'Asking for help',
                text: 'Tell an Administrator three things: the page you were on, what you clicked, and what you expected to happen. That is usually enough to solve it immediately.',
            },
        ],
    },

    /* --------------------------------------------------------------- 35 */
    {
        id: 'back-cover',
        kind: 'back',
        title: 'End of manual',
        subtitle: 'Keep this open while you learn. Nobody is expected to remember it all.',
    },
];

/* =========================================================================
 * TAGALOG
 * ========================================================================= */
const rawPagesTl = [
    /* ---------------------------------------------------------------- 0 */
    {
        id: 'cover',
        kind: 'cover',
        title: 'Manwal ng Gumagamit',
        subtitle: 'Gabay para sa unang araw sa sistema, mga tungkulin, at kung paano nagagawa ang trabaho',
        edition: 'BRT Software — Gabay sa Operasyon',
    },

    /* ---------------------------------------------------------------- 1 */
    {
        id: 'how-to-use',
        kind: 'plain',
        kicker: 'Basahin muna ito',
        title: 'Paano gamitin ang manwal na ito',
        blocks: [
            {
                type: 'lead',
                text: 'Hindi mo kailangang basahin ito mula simula hanggang dulo. Ginawa itong mabuksan sa pahinang kailangan mo, sa araw na kailangan mo ito.',
            },
            {
                type: 'steps',
                items: [
                    {
                        title: 'Bago lang dito? Magsimula sa Mga Unang Hakbang',
                        text: 'Ipinapaliwanag dito kung para saan ang sistema, paano mag-log in, at kung ano ang tawag sa bawat bagay na makikita mo sa screen.',
                    },
                    {
                        title: 'Pagkatapos, basahin ang para sa iyong tungkulin',
                        text: 'Sasabihin sa iyo ng Bahagi I kung aling mga menu ang makikita mo at kung ano ang responsibilidad mo.',
                    },
                    {
                        title: 'Panatilihing bukas ang Bahagi II habang nagtatrabaho',
                        text: 'Nakasulat ang bawat gawain bilang mga hakbang na may numero na masusundan mo.',
                    },
                ],
            },
            {
                type: 'note',
                tone: 'tip',
                title: 'Pagbaling ng pahina',
                text: 'I-click ang labas na gilid ng pahina, gamitin ang kaliwa at kanang arrow key, o pumili ng kabanata mula sa listahan sa kaliwa. Pindutin ang Esc para isara ang aklat.',
            },
            {
                type: 'note',
                tone: 'info',
                title: 'Mga diagram lang ang mga larawan',
                text: 'Ipinapakita ng mga guhit ang hugis at ayos ng bawat screen para alam mo kung ano ang hahanapin. Ang totoong screen mo ay may aktwal na mga pangalan at numero.',
            },
        ],
    },

    /* ---------------------------------------------------------------- 2 */
    {
        id: 'contents',
        kind: 'toc',
        kicker: 'Nabigasyon',
        title: 'Nilalaman',
    },

    /* ---------------------------------------------------------------- 3 */
    {
        id: 'part-start',
        kind: 'part',
        part: PART_START_TL,
        number: 'Bahagi I',
        title: 'Mga Unang Hakbang',
        blurb: 'Para sa iyong unang araw. Walang ipinapalagay na dating kaalaman.',
    },

    /* ---------------------------------------------------------------- 4 */
    {
        id: 'what-is-this',
        kind: 'plain',
        part: PART_START_TL,
        kicker: 'Bahagi I · Oryentasyon',
        title: 'Para saan ang sistemang ito',
        blocks: [
            {
                type: 'lead',
                text: 'Sinusubaybayan ng sistemang ito ang apat na bagay: ang binibili ng negosyo, ang hawak nitong stock, ang ibinebenta nito, at kung sino ang binabayaran nito. Ang bawat screen na gagamitin mo ay kabilang sa isa sa apat na ito.',
            },
            {
                type: 'steps',
                items: [
                    {
                        title: 'Bumibili kami',
                        text: 'Nagpapadala ng purchase order sa isang supplier. Kapag dumating ang mga paninda, itinatala ang mga ito bilang natanggap na stock.',
                    },
                    {
                        title: 'Iniingatan namin',
                        text: 'Tumataas ang stock kapag dumating ang paninda at bumababa kapag naibenta. Bibigyan ka ng babala ng sistema kapag paubos na ang isang item.',
                    },
                    {
                        title: 'Nagbebenta kami',
                        text: 'Itinatala ng sales order kung ano ang binili ng customer. Itinatala ng receipt kung magkano ang binayad nila.',
                    },
                    {
                        title: 'Nagbabayad kami',
                        text: 'Binabayaran ang mga empleyado sa pamamagitan ng payroll, bawas ang anumang bayad sa loan at iba pang deductions.',
                    },
                ],
            },
            {
                type: 'note',
                tone: 'info',
                title: 'Bakit mahalaga ito',
                text: 'Magkakaugnay ang apat na daloy na ito. Ang benta ay nagpapababa ng stock. Ang mababang stock ay nagiging dahilan ng pagbili. Ang bawat screen ay isang hakbang sa isa sa mga kadenang ito, kaya naman ang paglagay ng impormasyon sa maling lugar ay nagdudulot ng problema sa hinaharap.',
            },
        ],
    },

    /* ---------------------------------------------------------------- 5 */
    {
        id: 'first-login',
        kind: 'plain',
        part: PART_START_TL,
        kicker: 'Bahagi I · Hakbang-hakbang',
        title: 'Ang iyong unang pag-sign in',
        blocks: [
            {
                type: 'figure',
                art: 'login',
                caption: 'Ang sign-in screen. Ang iyong username ay hindi ang iyong email address.',
            },
            {
                type: 'steps',
                items: [
                    {
                        title: 'Buksan ang address',
                        text: 'Gamitin ang web address na ibinigay sa iyo ng iyong Administrator. I-bookmark ito.',
                    },
                    {
                        title: 'I-type ang iyong username',
                        text: 'Ito ang maikling pangalang ibinigay sa iyo, hindi ang buo mong email.',
                    },
                    {
                        title: 'I-type ang iyong password',
                        text: 'Kung ito ang unang pagkakataon mo, gamitin ang pansamantalang password na ipinadala sa iyo.',
                    },
                    {
                        title: 'Palitan ang iyong password',
                        text: 'Agad na itakda ang sarili mong password. Walang ibang dapat makaalam nito, kabilang na ang iyong supervisor.',
                    },
                ],
            },
            {
                type: 'note',
                tone: 'warn',
                title: 'Kung hindi ka mapapasok',
                text: 'Huwag paulit-ulit na susubukan. Maaaring hindi pa na-activate ang account mo, o hindi pa na-verify ang iyong email address. Hilingin sa isang Administrator na tingnan pareho.',
            },
        ],
    },

    /* ---------------------------------------------------------------- 6 */
    {
        id: 'screen-tour',
        kind: 'plain',
        part: PART_START_TL,
        kicker: 'Bahagi I · Oryentasyon',
        title: 'Ang tawag sa bawat bagay',
        blocks: [
            {
                type: 'figure',
                art: 'sidebar',
                caption: 'Ang madilim na guhit sa kaliwa ay ang menu. Ang malaking bahagi sa kanan ay ang pahina.',
                callouts: ['Ang mga menu item na wala kang pahintulot ay hindi lang ipinapakita'],
            },
            {
                type: 'list',
                items: [
                    'Menu (o sidebar) — ang madilim na guhit sa kaliwa. Dito naaabot ang bawat bahagi ng sistema.',
                    'Page (Pahina) — ang malaking puting bahagi. Nagbabago ito kapag nag-click ka ng menu item.',
                    'List (Listahan) — karamihan sa mga pahina ay bumubukas bilang listahan ng mga umiiral na record, pinakabago muna.',
                    'Record — isang hilera sa listahang iyon: isang benta, isang empleyado, isang purchase order.',
                    'Form — ang screen kung saan mo ipinapasok ang bagong record o ineedit ang isang umiiral na.',
                ],
            },
            {
                type: 'note',
                tone: 'tip',
                title: 'Mas maikli ang menu mo kaysa sa kasamahan mo',
                text: 'Normal lang iyon at hindi kamalian. Ipinapakita lang ng menu ang pinapayagang buksan ng iyong tungkulin. Ipinapaliwanag sa Bahagi II kung sino ang nakakakita ng ano.',
            },
        ],
    },

    /* ---------------------------------------------------------------- 7 */
    {
        id: 'dashboard-tour',
        kind: 'plain',
        part: PART_START_TL,
        kicker: 'Bahagi I · Oryentasyon',
        title: 'Ang iyong home screen',
        blocks: [
            {
                type: 'figure',
                art: 'dashboard',
                caption: 'Ang dashboard. May mga tab sa itaas, mga summary card sa ibaba, at mga chart pa pababa.',
            },
            {
                type: 'p',
                text: 'Ang dashboard ang unang makikita mo pagkatapos mag-sign in. Isa lang itong buod — walang direktang ineedit dito.',
            },
            {
                type: 'steps',
                items: [
                    {
                        title: 'Pumili ng tab',
                        text: 'Ang Sales, Inventory, at Team ay bawat isa ay nagpapakita ng ibang bahagi ng negosyo.',
                    },
                    {
                        title: 'Basahin ang mga card',
                        text: 'Ipinapakita ng hilera ng mga kahon ang kabuuan sa kasalukuyan, kasama ang pagbabago mula noong nakaraang buwan.',
                    },
                    {
                        title: 'Tingnan ang mga chart',
                        text: 'Ipinapakita ng mga graph ang trend sa mga nakaraang buwan, para makita mo ang direksyon, hindi lang ang ngayon.',
                    },
                    {
                        title: 'Kumilos base sa mga alerto',
                        text: 'Nakalista sa Inventory tab ang mga item na paubos na. Ang listahang iyon ang iyong gagawin para sa muling pag-order.',
                    },
                ],
            },
            {
                type: 'note',
                tone: 'tip',
                title: 'Binabasa mo na ito ngayon',
                text: 'Nasa kanang itaas ng dashboard ang User Manual button. Isang click lang ang layo ng aklat na ito, kahit kailan.',
            },
        ],
    },

    /* ---------------------------------------------------------------- 8 */
    {
        id: 'words',
        kind: 'plain',
        part: PART_START_TL,
        kicker: 'Bahagi I · Payak na Paliwanag',
        title: 'Mga salitang maririnig mo',
        blocks: [
            {
                type: 'p',
                text: 'Walang magpapaliwanag nito sa iyo nang dalawang beses. Nasa payak na wika ang mga ito dito.',
            },
            {
                type: 'table',
                head: ['Salita', 'Ang aktwal na kahulugan'],
                rows: [
                    ['Sales order', 'Talaan ng binili ng customer'],
                    ['Receipt', 'Talaan ng pera na binayad ng customer'],
                    ['AR invoice', 'Bill para sa bentang hindi pa lubos na bayad'],
                    ['Outstanding', 'Perang utang pa sa amin ng customer'],
                    ['Remittance', 'Cash na isinasauli ng rep sa opisina'],
                    ['Purchase order (PO)', 'Order na ipinapadala namin sa supplier'],
                    ['Received stock', 'Mga paninda na aktwal na dumating mula sa isang PO'],
                    ['Batch', 'Isang delivery, binigyan ng code para masubaybayan'],
                    ['Adjustment', 'Pagwawasto sa isang naka-save na record'],
                    ['Minimum stock', 'Ang level kung saan dapat na kaming mag-order muli'],
                    ['Payroll run', 'Isang pay period na kinakalkula'],
                    ['Deduction', 'Perang bawas sa sahod, hal. bayad sa loan'],
                    ['Library', 'Master list na pinagmumulan ng laman ng mga dropdown'],
                    ['Role', 'Ang pinapayagan mong buksan at gawin'],
                ],
            },
        ],
    },

    /* ---------------------------------------------------------------- 9 */
    {
        id: 'golden-rules',
        kind: 'plain',
        part: PART_START_TL,
        kicker: 'Bahagi I · Mga Ugali',
        title: 'Anim na patakaran na nagpapanatili sa iyong ligtas',
        blocks: [
            {
                type: 'steps',
                items: [
                    {
                        title: 'Maghanap muna bago gumawa',
                        text: 'Ang duplicate na customer at produkto ang pinakakaraniwang pagkakamali. Tumingin muna.',
                    },
                    {
                        title: 'Iwasto, huwag burahin',
                        text: 'Gumamit ng adjustment. Ang pagbura ay sumisira sa history na inaasahan ng mga report at audit.',
                    },
                    {
                        title: 'I-deactivate, huwag alisin',
                        text: 'Kapag may umalis o may produktong hindi na ginagawa, gawin itong inactive. Kailangan pa rin ito ng mga lumang record.',
                    },
                    {
                        title: 'Ilagay ang aktwal na nangyari',
                        text: 'Itala ang aktwal na dami na na-deliver at ang aktwal na halagang binayad, kahit iba ito sa inaasahan.',
                    },
                    {
                        title: 'Tapusin ang sinimulan',
                        text: 'Ang kalahating na-enter na order ay humaharang sa susunod na tao. Tapusin ito o kanselahin.',
                    },
                    {
                        title: 'Magtanong bago mag-approve',
                        text: 'Ang approval ay parang pirma. Kung hindi ka sigurado, magtanong muna — mas mahirap bawiin ito.',
                    },
                ],
            },
            {
                type: 'note',
                tone: 'warn',
                title: 'Ang isang bagay na hinding-hindi dapat gawin',
                text: 'Huwag kailanman ibahagi ang iyong sign-in. Naitatala sa bawat record kung sino ang gumawa nito. Kung may gumamit ng account mo, ang pagkakamali nila ay magdadala ng pangalan mo.',
            },
        ],
    },

    /* --------------------------------------------------------------- 10 */
    {
        id: 'part-roles',
        kind: 'part',
        part: PART_ROLES_TL,
        number: 'Bahagi II',
        title: 'Mga Tungkulin & Access',
        blurb: 'Sino ang pwedeng gumawa ng ano, at saan ito matatagpuan sa menu.',
    },

    /* --------------------------------------------------------------- 11 */
    {
        id: 'role-administrator',
        kind: 'plain',
        part: PART_ROLES_TL,
        roleKey: 'administrator',
        kicker: 'Bahagi II · Tungkulin',
        title: 'Administrator',
        blocks: [
            {
                type: 'lead',
                text: 'Buong access sa lahat. Ang Administrator lang ang tungkuling pinapayagang pumasok sa users, libraries, purchasing, payroll, loans, at expenses.',
            },
            {
                type: 'grid',
                items: [
                    { label: 'Menu access', value: 'Lahat' },
                    { label: 'Karaniwang may-hawak', value: 'May-ari ng sistema / IT' },
                    { label: 'Ina-approve', value: 'Remittances, payroll' },
                    { label: 'Namamahala ng roles', value: 'Oo' },
                ],
            },
            {
                type: 'list',
                items: [
                    'Gumawa ng user account at magbigay o mag-alis ng roles.',
                    'Alagaan ang bawat library: products, brands, units, locations, statuses, suppliers, positions, roles.',
                    'Mag-approve ng remittances at ipagpatuloy ang payroll at purchase orders.',
                    'Basahin ang guest messages na ipinadala mula sa pampublikong contact form.',
                ],
            },
            {
                type: 'note',
                tone: 'warn',
                title: 'Ingatan sa paggamit',
                text: 'Agad na magkakabisa ang pag-deactivate ng user o role. Walang babala at walang grace period.',
            },
        ],
    },

    /* --------------------------------------------------------------- 12 */
    {
        id: 'role-administrator-day',
        kind: 'plain',
        part: PART_ROLES_TL,
        roleKey: 'administrator',
        kicker: 'Bahagi II · Administrator',
        title: 'Isang araw bilang Administrator',
        blocks: [
            {
                type: 'steps',
                items: [
                    {
                        title: 'Tingnan ang dashboard',
                        text: 'Ang mga tab na Sales, Inventory, at Team ay nagbibigay ng kalusugan ng negosyo sa isang screen.',
                    },
                    {
                        title: 'Linisin ang mga approval',
                        text: 'Ang mga remittance na naghihintay ng approval, at ang mga payroll na nasa Draft, ay humaharang sa lahat pagkatapos nito.',
                    },
                    {
                        title: 'Suriin ang stock alerts',
                        text: 'Gumawa ng purchase order para sa anumang naka-flag na Low o Critical bago ito maubos.',
                    },
                    {
                        title: 'I-audit ang mga account',
                        text: 'Sa ilalim ng Accounts, i-deactivate ang sinumang umalis na at tiyaking walang humahawak ng role na hindi na nila kailangan.',
                    },
                ],
            },
        ],
    },

    /* --------------------------------------------------------------- 13 */
    {
        id: 'role-sales-rep',
        kind: 'plain',
        part: PART_ROLES_TL,
        roleKey: 'sales-rep',
        kicker: 'Bahagi II · Tungkulin',
        title: 'Sales Rep',
        blocks: [
            {
                type: 'lead',
                text: 'Ang unahan. Itinatala ng Sales Rep ang binibili ng mga customer, kinokolekta ang pera, at isinasauli ito sa opisina.',
            },
            {
                type: 'grid',
                items: [
                    { label: 'Menu access', value: 'Sales Management, Payroll' },
                    { label: 'Ginagawa mo', value: 'Sales orders, receipts' },
                    { label: 'Hindi mo magagawa', value: 'I-edit ang libraries o users' },
                    { label: 'Nag-uulat kay', value: 'Sales Manager' },
                ],
            },
            {
                type: 'list',
                items: [
                    'Itala ang benta laban sa isang customer at ang mga produktong binili nila.',
                    'Kolektahin ang bayad at maglabas ng receipt na magsasara sa order.',
                    'Isauli ang cash sa opisina sa pamamagitan ng remittance.',
                    'Subaybayan kung sinong mga customer ang may utang pa.',
                ],
            },
            {
                type: 'note',
                tone: 'info',
                title: 'Bumababa ang stock kapag nagbenta ka',
                text: 'Ang pag-save ng order ay nagpapababa ng bilang ng stock. Kung hindi lumalabas ang isang produkto sa listahan, tingnan muna kung naubos ito bago ipagpalagay na sira ang screen.',
            },
        ],
    },

    /* --------------------------------------------------------------- 14 */
    {
        id: 'role-sales-rep-day',
        kind: 'plain',
        part: PART_ROLES_TL,
        roleKey: 'sales-rep',
        kicker: 'Bahagi II · Sales Rep',
        title: 'Isang araw bilang Sales Rep',
        blocks: [
            {
                type: 'steps',
                items: [
                    {
                        title: 'Buksan ang Sales Management',
                        text: 'Suriin ang mga order kahapon at ang anumang minarkahang Unpaid o Partially Paid pa.',
                    },
                    {
                        title: 'Itala ang mga bagong order',
                        text: 'Isang order bawat transaksyon ng customer. Idagdag ang bawat linya bago i-save.',
                    },
                    {
                        title: 'Maglabas ng receipts',
                        text: 'Itala ang bayad habang kinokolekta mo ito, para lumipat ang order sa Paid.',
                    },
                    {
                        title: 'Mag-remit sa katapusan ng araw',
                        text: 'Magsumite ng remittance na sasaklaw sa cash na nakolekta mo. Aaprubahan ito ng isang Administrator.',
                    },
                ],
            },
            {
                type: 'note',
                tone: 'tip',
                title: 'Nagkamali?',
                text: 'Ang naka-save nang order ay maaari pa ring iwasto gamit ang adjustment. Hindi mo na kailangang burahin ito at magsimula ulit.',
            },
        ],
    },

    /* --------------------------------------------------------------- 15 */
    {
        id: 'role-sales-manager',
        kind: 'plain',
        part: PART_ROLES_TL,
        roleKey: 'sales-manager',
        kicker: 'Bahagi II · Tungkulin',
        title: 'Sales Manager',
        blocks: [
            {
                type: 'lead',
                text: 'May-ari ng listahan ng customer. Binubuksan ng tungkuling ito ang Customers menu, kung saan pinananatiling updated ang mga account at contact details.',
            },
            {
                type: 'grid',
                items: [
                    { label: 'Menu access', value: 'Customers, Payroll' },
                    { label: 'Pag-aari mo', value: 'Customer records' },
                    { label: 'Binabantayan mo', value: 'Perang utang sa amin' },
                    { label: 'Hindi mo magagawa', value: 'I-approve ang remittances' },
                ],
            },
            {
                type: 'list',
                items: [
                    'Gumawa at alagaan ang mga customer record na pinipilian ng bawat sales order.',
                    'Panatilihing updated ang mga address at contact number para dumating ang mga delivery at invoice.',
                    'Bantayan kung sinong mga customer ang may pinakamalaking hindi bayad na balanse.',
                    'Makipagtulungan sa mga Administrator tungkol sa incentives na binabayaran sa pamamagitan ng payroll.',
                ],
            },
            {
                type: 'note',
                tone: 'warn',
                title: 'Huwag kailanman gumawa ng duplicate na customer',
                text: 'Maghanap muna. Ang dalawang record para sa isang customer ay naghahati sa kanilang payment history at halos imposible nang mangolekta.',
            },
        ],
    },

    /* --------------------------------------------------------------- 16 */
    {
        id: 'role-inventory-manager',
        kind: 'plain',
        part: PART_ROLES_TL,
        roleKey: 'inventory-manager',
        kicker: 'Bahagi II · Tungkulin',
        title: 'Inventory Manager',
        blocks: [
            {
                type: 'lead',
                text: 'Lahat ng dumarating at lahat ng nasa istante. Sinasaklaw ng tungkuling ito ang purchase orders, mga delivery, at stock levels.',
            },
            {
                type: 'grid',
                items: [
                    { label: 'Menu access', value: 'Inventory Management' },
                    { label: 'Ginagawa mo', value: 'POs, received stock' },
                    { label: 'Inaalagaan mo', value: 'Stock levels, batches' },
                    { label: 'Nakikipagtulungan ka sa', value: 'Suppliers' },
                ],
            },
            {
                type: 'list',
                items: [
                    'Magpadala ng purchase order sa mga supplier at subaybayan kung nasaan na ang bawat isa.',
                    'Tanggapin ang mga delivery sa batches, na itinatala kung ano ang aktwal na dumating.',
                    'Mag-post ng adjustment kapag hindi tugma ang physical count sa sistema.',
                    'I-update ang selling price na nakatakda sa stock.',
                ],
            },
            {
                type: 'note',
                tone: 'info',
                title: 'Mahalaga ang batches',
                text: 'Bawat delivery ay binibigyan ng batch code. Ang pagtanggap ng paninda sa maling batch ay nagpapahirap masubaybayan ang stock sa hinaharap.',
            },
        ],
    },

    /* --------------------------------------------------------------- 17 */
    {
        id: 'role-inventory-manager-day',
        kind: 'plain',
        part: PART_ROLES_TL,
        roleKey: 'inventory-manager',
        kicker: 'Bahagi II · Inventory Manager',
        title: 'Isang araw bilang Inventory Manager',
        blocks: [
            {
                type: 'steps',
                items: [
                    {
                        title: 'Basahin ang low stock alert',
                        text: 'Nakalista sa Inventory tab ng dashboard ang lahat ng nasa ibaba ng minimum nito, pinakamalala muna.',
                    },
                    {
                        title: 'Gumawa ng purchase order',
                        text: 'Awtomatikong nalalagay ang PO number. Piliin ang supplier, idagdag ang mga item, i-save.',
                    },
                    {
                        title: 'Tanggapin ang mga delivery',
                        text: 'Itugma ang delivery sa bukas nitong PO at itala ito sa ilalim ng batch code.',
                    },
                    {
                        title: 'I-reconcile',
                        text: 'Kapag iba ang count sa sistema, mag-post ng adjustment na may dahilan.',
                    },
                ],
            },
        ],
    },

    /* --------------------------------------------------------------- 18 */
    {
        id: 'role-hr-officer',
        kind: 'plain',
        part: PART_ROLES_TL,
        roleKey: 'hr-officer',
        kicker: 'Bahagi II · Tungkulin',
        title: 'Human Resource Officer',
        blocks: [
            {
                type: 'lead',
                text: 'Operasyon ukol sa mga tao. Binubuksan ng tungkuling ito ang mga menu na Employees at Loans, at inihahanda ang impormasyong pinagbabatayan ng payroll.',
            },
            {
                type: 'grid',
                items: [
                    { label: 'Menu access', value: 'Employees, Loans, Accounts' },
                    { label: 'Inaalagaan mo', value: 'Employee records' },
                    { label: 'Pinoproseso mo', value: 'Loans, deductions' },
                    { label: 'Hindi mo magagawa', value: 'I-edit ang inventory o sales' },
                ],
            },
            {
                type: 'list',
                items: [
                    'Gumawa ng employee record at panatilihing tama ang positions, salaries, at locations.',
                    'Itala ang mga loan at ang deduction schedule na magbabayad nito.',
                    'Ihanda ang listahan ng mga empleyadong pinagbabatayan ng bawat payroll.',
                    'Subaybayan ang attendance at leave.',
                ],
            },
            {
                type: 'note',
                tone: 'warn',
                title: 'Ang datos ng empleyado ang nagtatakda ng sahod',
                text: 'Ang position at salary sa employee record ang nagtutulak sa mga figure ng payroll. Ayusin ang mga ito bago ang run, hindi pagkatapos.',
            },
        ],
    },

    /* --------------------------------------------------------------- 19 */
    {
        id: 'role-matrix',
        kind: 'plain',
        part: PART_ROLES_TL,
        kicker: 'Bahagi II · Reference',
        title: 'Access sa isang tingin',
        blocks: [
            {
                type: 'p',
                text: 'Lumalabas lang ang isang menu item kung nakalista ang iyong role dito. Kung mayroon kang higit sa isang role, makikita mo ang mga menu ng lahat ng ito na pinagsama.',
            },
            {
                type: 'table',
                head: ['Menu', 'Mga role na nakakakita nito'],
                rows: [
                    ['Dashboard', 'Lahat'],
                    ['Employees', 'HR Officer, Administrator'],
                    ['Accounts', 'HR Officer, Administrator'],
                    ['Inventory Management', 'Inventory Manager, Administrator'],
                    ['Sales Management', 'Sales Rep, Administrator'],
                    ['Customers', 'Sales Manager, Administrator'],
                    ['Suppliers', 'Administrator'],
                    ['Expenses', 'Administrator'],
                    ['Payroll', 'Sales Rep, Inventory Mgr, Administrator'],
                    ['Loans', 'HR Officer, Administrator'],
                    ['Guest Messages', 'Administrator'],
                    ['Libraries', 'Administrator'],
                ],
            },
        ],
    },

    /* --------------------------------------------------------------- 20 */
    {
        id: 'part-process',
        kind: 'part',
        part: PART_PROCESS_TL,
        number: 'Bahagi III',
        title: 'Mga Proseso ng Sistema',
        blurb: 'Bawat gawain, nakasulat bilang mga hakbang na masusundan mo.',
    },

    /* --------------------------------------------------------------- 21 */
    {
        id: 'process-signin',
        kind: 'plain',
        part: PART_PROCESS_TL,
        kicker: 'Bahagi III · Proseso',
        title: 'Mga Account at Seguridad',
        blocks: [
            {
                type: 'lead',
                text: 'Apat na bagay ang dapat totoo bago mabuksan ang anumang pahina: naka-sign in ka, na-verify ang iyong email, aktibo ang iyong account, at naipasa mo ang two-factor kung naka-on ito.',
            },
            {
                type: 'steps',
                items: [
                    { title: 'Mag-sign in', text: 'Username at password.' },
                    { title: 'I-verify ang iyong email', text: 'Hangga\'t hindi mo na-click ang link na ipinadala sa email, ibabalik ka sa verification page.' },
                    { title: 'Two-factor code', text: 'Kung naka-enable sa iyong account, ilagay ang code mula sa iyong authenticator app.' },
                    { title: 'Itakda ang sarili mong password', text: 'Ang mga bagong account ay naka-flag para ma-prompt kang palitan ang pansamantalang password.' },
                ],
            },
            {
                type: 'note',
                tone: 'warn',
                title: 'Naka-lock out',
                text: 'Ang account na minarkahang inactive ay hindi talaga makaka-sign in, gaano man ka-tama ang password. Isang Administrator lang ang makaka-reactivate nito sa ilalim ng Accounts.',
            },
        ],
    },

    /* --------------------------------------------------------------- 22 */
    {
        id: 'process-sales',
        kind: 'plain',
        part: PART_PROCESS_TL,
        kicker: 'Bahagi III · Proseso',
        title: 'Pagtatala ng benta',
        blocks: [
            {
                type: 'figure',
                art: 'sales-order',
                caption: 'Isang sales order. Customer sa itaas, isang hilera bawat produkto, kabuuan sa ibaba.',
                callouts: ['Piliin muna ang customer', 'Isang hilera bawat produkto'],
            },
            {
                type: 'steps',
                items: [
                    { title: 'Buksan ang Sales Management', text: 'Mula sa menu sa kaliwa, pagkatapos ay magsimula ng bagong order.' },
                    { title: 'Piliin ang customer', text: 'Maghanap gamit ang pangalan. Kung wala sila, hilingin sa Sales Manager na idagdag sila — huwag gumawa ng peke.' },
                    { title: 'Idagdag ang bawat produkto', text: 'Isang hilera bawat produkto, kasama ang quantity at price. Idagdag ang bawat item bago i-save.' },
                    { title: 'Suriin ang kabuuan', text: 'Ikumpara ito sa aktwal na binabayaran ng customer.' },
                    { title: 'I-save', text: 'Naitatala ang order bilang Unpaid at nababawasan ang stock.' },
                ],
            },
            {
                type: 'note',
                tone: 'info',
                title: 'Mga bentang ginawa sa labas ng karaniwang channel',
                text: 'Napupunta ang mga ito sa ilalim ng Sales Orders External. Parehong hakbang, hiwalay na iniuulat.',
            },
        ],
    },

    /* --------------------------------------------------------------- 23 */
    {
        id: 'process-receipt',
        kind: 'plain',
        part: PART_PROCESS_TL,
        kicker: 'Bahagi III · Proseso',
        title: 'Pagtanggap ng bayad',
        blocks: [
            {
                type: 'lead',
                text: 'Sinasabi ng order kung ano ang binili. Sinasabi ng receipt kung magkano ang binayaran. Tapos lang ang benta kapag pareho itong umiiral at magkatugma.',
            },
            {
                type: 'steps',
                items: [
                    { title: 'Buksan ang order', text: 'Hanapin ito sa listahan ng Sales Management.' },
                    { title: 'Itala ang receipt', text: 'Ilagay ang halagang ibinigay at kung paano ito binayaran — cash, card, o bank transfer.' },
                    { title: 'Panoorin ang pagbabago ng status', text: 'Ang buong bayad ay lumilipat sa Paid. Ang bahagyang bayad ay lumilipat sa Partially Paid.' },
                    { title: 'Iwanang outstanding ang natitira', text: 'Ang anumang hindi bayad ay mananatiling AR invoice hanggang makolekta ito.' },
                ],
            },
            {
                type: 'note',
                tone: 'warn',
                title: 'Huwag kailanman mag-receipt ng perang hindi mo pa natatanggap',
                text: 'Ang receipt ang talaan na umiiral ang cash. Kapag na-enter na, ikaw ang mananagot dito.',
            },
        ],
    },

    /* --------------------------------------------------------------- 24 */
    {
        id: 'process-ar',
        kind: 'plain',
        part: PART_PROCESS_TL,
        kicker: 'Bahagi III · Proseso',
        title: 'Pagsauli ng cash sa opisina',
        blocks: [
            {
                type: 'figure',
                art: 'remittance',
                caption: 'Kumpleto lang ang remittance kapag na-approve na ito ng isang Administrator.',
                callouts: ['Ang naisumite ay hindi pareho sa naklir na'],
            },
            {
                type: 'steps',
                items: [
                    { title: 'Bilangin ang nakolekta mo', text: 'Total ang cash na nakuha mo sa loob ng panahong iyon.' },
                    { title: 'Gumawa ng remittance', text: 'Ilagay ang halaga at ang panahong sinasaklaw nito.' },
                    { title: 'Isumite ito', text: 'Naghihintay na ito ngayon sa isang Administrator.' },
                    { title: 'Ipa-approve ito', text: 'Hangga\'t hindi ito nangyayari, nakatala pa rin ang pera laban sa iyo.' },
                    { title: 'I-print ang kopya', text: 'Ang na-approve na remittance ay maaaring i-print bilang iyong patunay.' },
                ],
            },
            {
                type: 'note',
                tone: 'warn',
                title: 'Habulin ang approval',
                text: 'Ang naisumite ngunit hindi pa na-approve na remittance ay hindi nag-aalis ng iyong pananagutan. I-follow up ito sa parehong araw.',
            },
        ],
    },

    /* --------------------------------------------------------------- 25 */
    {
        id: 'process-customers',
        kind: 'plain',
        part: PART_PROCESS_TL,
        kicker: 'Bahagi III · Proseso',
        title: 'Pamamahala ng mga customer',
        blocks: [
            {
                type: 'lead',
                text: 'Bawat sales order ay dapat tumuro sa isang customer record. Ang pananatiling malinis ng listahang iyon ang gumagawang mapagkakatiwalaan ang mga figure ng receivables.',
            },
            {
                type: 'steps',
                items: [
                    { title: 'Maghanap muna', text: 'Palagi. Tingnan ang customer sa kahit anong spelling bago gumawa ng bago.' },
                    { title: 'Gumawa ng record', text: 'Customers, pagkatapos new. Kunin ang business name, contact person, at address.' },
                    { title: 'Panatilihing updated', text: 'I-update ang phone number at address sa sandaling magbago ang mga ito, o maliligaw ang mga delivery.' },
                    { title: 'Bantayan ang mga balanse', text: 'Suriin kung sinong mga customer ang may pinakamalaking hindi bayad na halaga at i-follow up sila.' },
                ],
            },
            {
                type: 'note',
                tone: 'warn',
                title: 'Mahal ang duplicates',
                text: 'Ang dalawang record para sa parehong customer ay naghahati sa kanilang payment history. Wala sa dalawa ang nagpapakita ng tunay na balanse, at natitigil ang koleksyon.',
            },
        ],
    },

    /* --------------------------------------------------------------- 26 */
    {
        id: 'process-purchasing',
        kind: 'plain',
        part: PART_PROCESS_TL,
        kicker: 'Bahagi III · Proseso',
        title: 'Pag-order mula sa supplier',
        blocks: [
            {
                type: 'figure',
                art: 'purchase-order',
                caption: 'Isang purchase order at ang tatlong estado na dinadaanan nito.',
                callouts: ['Awtomatikong nabubuo ang PO number'],
            },
            {
                type: 'steps',
                items: [
                    { title: 'Buksan ang Purchase Orders', text: 'Sa ilalim ng Inventory Management. Magsimula ng bago.' },
                    { title: 'Piliin ang supplier', text: 'Mula sa Suppliers library. Awtomatikong napupunan ang PO number.' },
                    { title: 'Ilista ang kailangan mo', text: 'Isang hilera bawat produkto, kasama ang dami na ini-order.' },
                    { title: 'I-save at i-print', text: 'I-print ito para ipadala sa supplier.' },
                    { title: 'Subaybayan ito', text: 'Lumilipat ito mula Pending papuntang Approved hanggang Completed habang umuusad ang mga bagay.' },
                ],
            },
        ],
    },

    /* --------------------------------------------------------------- 26 */
    {
        id: 'process-receiving',
        kind: 'plain',
        part: PART_PROCESS_TL,
        kicker: 'Bahagi III · Proseso',
        title: 'Pagtanggap ng delivery',
        blocks: [
            {
                type: 'lead',
                text: 'Sinasabi ng purchase order kung ano ang hiniling mo. Sinasabi ng received stock kung ano ang aktwal na dumating. Kadalasan hindi sila magkapareho, at iyon mismo ang dahilan kung bakit umiiral ang dalawa.',
            },
            {
                type: 'steps',
                items: [
                    { title: 'Hanapin ang bukas na PO', text: 'Itugma ang delivery sa order na pinagmulan nito.' },
                    { title: 'Gumawa ng received stock record', text: 'May nabubuong batch code para sa deliverying ito.' },
                    { title: 'Bilangin ang dumating', text: 'Personal na bilangin ito. Huwag ipagpalagay na tama ang delivery note.' },
                    { title: 'Ilagay ang totoong dami', text: 'Itala ang dumating, hindi ang ini-order, kahit kulang ito.' },
                    { title: 'I-save', text: 'Tumataas ang stock levels ayon sa halagang inilagay mo.' },
                ],
            },
            {
                type: 'note',
                tone: 'warn',
                title: 'Mga kulang na delivery',
                text: 'Kung ilalagay mo ang dami na ini-order sa halip na ang dami na na-deliver, aakalain ng sistema na may stock na wala naman sa istante. May magbebenta nito.',
            },
        ],
    },

    /* --------------------------------------------------------------- 27 */
    {
        id: 'process-inventory',
        kind: 'plain',
        part: PART_PROCESS_TL,
        kicker: 'Bahagi III · Proseso',
        title: 'Pagbabantay sa stock levels',
        blocks: [
            {
                type: 'figure',
                art: 'stock-alert',
                caption: 'Ang low stock alert. Ipinapakita ng kulay kung gaano kaurgent ang bawat item.',
            },
            {
                type: 'table',
                head: ['Label', 'Ano ang gagawin'],
                rows: [
                    ['Good', 'Wala'],
                    ['Low', 'Mag-order muli sa lalong madaling panahon'],
                    ['Critical', 'Mag-order muli ngayon'],
                    ['Out', 'Hindi na maibebenta — mag-order na ngayon'],
                ],
            },
            {
                type: 'steps',
                items: [
                    { title: 'Bilangin', text: 'Ikumpara ang aktwal na istante sa figure ng sistema.' },
                    { title: 'I-adjust', text: 'Mag-post ng adjustment para sa pagkakaiba.' },
                    { title: 'Sabihin kung bakit', text: 'Pagkasira, pagkabasag, pagkakamali sa pagbilang. Ang adjustment na walang paliwanag ay mukhang pagnanakaw.' },
                ],
            },
        ],
    },

    /* --------------------------------------------------------------- 28 */
    {
        id: 'process-employees',
        kind: 'plain',
        part: PART_PROCESS_TL,
        kicker: 'Bahagi III · Proseso',
        title: 'Pagdaragdag ng bagong empleyado',
        blocks: [
            {
                type: 'figure',
                art: 'employee',
                caption: 'Isang employee record. Ang position at salary dito ang nagtatakda kung magkano ang babayaran ng payroll.',
                callouts: ['I-link dito ang sign-in account'],
            },
            {
                type: 'steps',
                items: [
                    { title: 'Gumawa ng employee', text: 'Employees, pagkatapos new. Punan ang personal details.' },
                    { title: 'Itakda ang position at salary', text: 'Pinili mula sa libraries. Ito ang nagtutulak sa kanilang sahod.' },
                    { title: 'Gumawa ng account nila', text: 'Sa ilalim ng Accounts, para makapag-sign in sila.' },
                    { title: 'Bigyan sila ng role', text: 'Kung walang role, makikita lang nila ang dashboard at wala nang iba.' },
                    { title: 'Kapag umalis sila', text: 'I-deactivate ang account. Huwag burahin ang empleyado — umaasa dito ang payroll history.' },
                ],
            },
        ],
    },

    /* --------------------------------------------------------------- 29 */
    {
        id: 'process-payroll',
        kind: 'plain',
        part: PART_PROCESS_TL,
        kicker: 'Bahagi III · Proseso',
        title: 'Pagpapatakbo ng payroll',
        blocks: [
            {
                type: 'figure',
                art: 'payroll',
                caption: 'Isang payroll run, at ang mga estadong dinadaanan nito bago mabayaran ang sinuman.',
            },
            {
                type: 'steps',
                items: [
                    { title: 'Tingnan ang settings', text: 'Hawak ng Payroll Settings ang mga rate at deduction na ipinapatupad sa bawat run.' },
                    { title: 'Buuin ang template', text: 'Gawin ito, pagkatapos idagdag ang mga empleyadong kabilang dito.' },
                    { title: 'Gumawa ng run', text: 'Gumawa ng payroll para sa panahong iyon mula sa template na iyon.' },
                    { title: 'Suriin ang bawat linya', text: 'Tingnan ang gross pay, deductions, bayad sa loan, at incentives bawat tao.' },
                    { title: 'Ipagpatuloy ito', text: 'Draft, pagkatapos approval, pagkatapos For Release, pagkatapos Completed.' },
                    { title: 'I-print ang payslips', text: 'Kapag na-release na.' },
                ],
            },
            {
                type: 'note',
                tone: 'warn',
                title: 'Suriin bago mag-approve',
                text: 'Mas mahirap ayusin ang na-approve nang payroll kaysa suriin ito ng maayos muna.',
            },
        ],
    },

    /* --------------------------------------------------------------- 30 */
    {
        id: 'process-loans',
        kind: 'plain',
        part: PART_PROCESS_TL,
        kicker: 'Bahagi III · Proseso',
        title: 'Mga loan ng empleyado',
        blocks: [
            {
                type: 'lead',
                text: 'Itinatala ang loan nang isang beses, pagkatapos ay awtomatiko na itong nababayaran mula sa bawat payroll hanggang maging zero ang balanse.',
            },
            {
                type: 'steps',
                items: [
                    { title: 'Itala ang loan', text: 'Loans, pagkatapos new. Piliin ang empleyado at ilagay ang halaga.' },
                    { title: 'Itakda ang deduction', text: 'Magkano ang babawasin sa bawat pay period.' },
                    { title: 'Hayaang payroll ang bahala sa iba', text: 'Bawat run ay babawasin ito at pababababain ang balanse.' },
                    { title: 'Isara ito', text: 'Kapag lubos nang nabayaran, magiging Closed o Liquidated ito.' },
                ],
            },
            {
                type: 'note',
                tone: 'warn',
                title: 'Huwag kailanman burahin ang tumatakbong loan',
                text: 'Nabubura nito ang repayment history at hindi na tumutugma ang mga nakaraang payroll. Isara na lang ito.',
            },
        ],
    },

    /* --------------------------------------------------------------- 31 */
    {
        id: 'process-libraries',
        kind: 'plain',
        part: PART_PROCESS_TL,
        kicker: 'Bahagi III · Proseso',
        title: 'Libraries — ang mga master list',
        blocks: [
            {
                type: 'figure',
                art: 'libraries',
                caption: 'Ang walong library. Ang lahat ng pinipili mo sa dropdown ay nanggagaling sa isa sa mga ito.',
            },
            {
                type: 'p',
                text: 'Kung may nawawalang produkto, supplier, o position sa dropdown, nawawala ito sa library nito — o na-set itong inactive.',
            },
            {
                type: 'note',
                tone: 'warn',
                title: 'I-deactivate, huwag kailanman burahin',
                text: 'Ang mga lumang order ay tumuturo pa rin sa mga entry na ito. Ang pag-set ng isa bilang inactive ay nagtatago nito sa mga bagong record habang buo pa rin ang history.',
            },
        ],
    },

    /* --------------------------------------------------------------- 32 */
    {
        id: 'process-expenses',
        kind: 'plain',
        part: PART_PROCESS_TL,
        kicker: 'Bahagi III · Proseso',
        title: 'Expenses at guest messages',
        blocks: [
            {
                type: 'p',
                text: 'Dalawang mas maliit na lugar na para lang sa Administrator.',
            },
            {
                type: 'list',
                items: [
                    'Itinatala ng Expenses ang mga tumatakbong gastos na hindi purchases o wages, para kumpleto ang larawan ng profit.',
                    'Kinokolekta ng Guest Messages ang mga tanong na ipinadala sa pamamagitan ng contact form sa pampublikong website.',
                    'Markahan ang mensahe bilang read kapag naasikaso na ito.',
                ],
            },
            {
                type: 'note',
                tone: 'tip',
                title: 'Panatilihing malinis ang inbox',
                text: 'Ang pagmarka ng mga mensahe bilang read ang tanging paraan para malaman kung alin ang naasikaso na sa mga bago.',
            },
        ],
    },

    /* --------------------------------------------------------------- 33 */
    {
        id: 'reference-statuses',
        kind: 'plain',
        part: PART_PROCESS_TL,
        kicker: 'Bahagi III · Reference',
        title: 'Glossary ng status',
        blocks: [
            {
                type: 'p',
                text: 'Pareho ang kahulugan ng mga salitang ito kahit saan sila lumabas.',
            },
            {
                type: 'table',
                head: ['Status', 'Kahulugan'],
                rows: [
                    ['Draft', 'Sinimulan, hindi pa naisumite'],
                    ['Pending', 'Naisumite, naghihintay'],
                    ['Approval', 'Nasa kamay ng approver'],
                    ['Approved', 'Pinayagang ipagpatuloy'],
                    ['Disapproved', 'Tinanggihan, kailangan ayusin muli'],
                    ['Open', 'Aktibo at ginagawa'],
                    ['Unpaid', 'Wala pang nakolekta'],
                    ['Partially Paid', 'May natitirang balanse'],
                    ['Paid', 'Lubos nang nabayaran'],
                    ['For Release', 'Pinayagan na, naghihintay ng payout'],
                    ['For Payment', 'Nasa pila para sa disbursement'],
                    ['Adjusted', 'Naiwasto pagkatapos i-save'],
                    ['Sales Returned', 'Nabalik ang paninda'],
                    ['Liquidated', 'Nabayaran na ang obligasyon'],
                    ['Completed', 'Tapos na'],
                    ['Closed', 'Naka-archive'],
                    ['Cancelled', 'Nakansela bago matapos'],
                ],
            },
        ],
    },

    /* --------------------------------------------------------------- 34 */
    {
        id: 'reference-help',
        kind: 'plain',
        part: PART_PROCESS_TL,
        kicker: 'Bahagi III · Reference',
        title: 'Kapag may nagkamali',
        blocks: [
            {
                type: 'table',
                head: ['Ano ang nakikita mo', 'Ano karaniwang ibig sabihin nito'],
                rows: [
                    ['May nawawalang menu item', 'Hindi kasama ito sa iyong role'],
                    ['Hindi makapag-sign in', 'Inactive ang account o hindi pa na-verify ang email'],
                    ['Wala sa listahan ang produkto', 'Ubos na ang stock, o na-set na inactive'],
                    ['Wala sa listahan ang customer', 'Hindi pa nagawa — tanungin ang Sales Manager'],
                    ['Mukhang mali ang halaga ng payroll', 'Luma na ang salary o position ng empleyado'],
                    ['Binabawas pa rin ang loan', 'Hindi pa na-close ang loan'],
                    ['Hindi pa naklir ang remittance', 'Naghihintay ng approval ng Administrator'],
                    ['Hindi tugma ang stock sa istante', 'Hindi pa na-post ang adjustment'],
                ],
            },
            {
                type: 'note',
                tone: 'info',
                title: 'Paghingi ng tulong',
                text: 'Sabihin sa Administrator ang tatlong bagay: ang pahinang kinaroroonan mo, ang na-click mo, at ang inaasahan mong mangyari. Karaniwan nang sapat na iyon para agad na maayos ito.',
            },
        ],
    },

    /* --------------------------------------------------------------- 35 */
    {
        id: 'back-cover',
        kind: 'back',
        title: 'Katapusan ng manwal',
        subtitle: 'Panatilihing bukas ito habang natututo ka. Wala namang inaasahang maalala lahat nito.',
    },
];

export const pagesEn = withAudience(rawPagesEn);
export const pagesTl = withAudience(rawPagesTl);
