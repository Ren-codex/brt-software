/**
 * Content source for the immersive User Manual book.
 *
 * Written for someone on their first day. Assume no prior knowledge: spell out
 * where to click, what will appear, and what to do when it does not.
 *
 * Pages are rendered two-up as a book spread. `pages` is a flat, ordered list:
 * index 0 is the front cover, the final entry is the back cover, and everything
 * in between is a printed page. The book component pads the list so the back
 * cover always lands on the reverse side of a sheet.
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

/**
 * Role names as they are actually enforced in the UI and route middleware
 * (see Shared/Layouts/Components/Menu.vue and routes/web.php).
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

const rawPages = [
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

/**
 * Which roles each page is written for, by page id.
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

export const pages = rawPages.map((page) => (
    AUDIENCE[page.id] ? { ...page, audience: AUDIENCE[page.id] } : page
));
