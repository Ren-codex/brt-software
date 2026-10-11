/**
 * The text of the in-app module guides (ModuleGuide.vue).
 *
 * One entry per module. Each says what the module is for, the stages its
 * records pass through, how to do the everyday job, and the things worth
 * knowing before something goes wrong.
 *
 * What is deliberately NOT here: which role does what. That is read live from
 * the role permissions (GuideController), because roles are edited under
 * Libraries → Roles and a guide that named them in its text would go stale.
 * `permissionModule` is the key used for that lookup.
 *
 * `paths` decides which guide the top-bar button opens: the first module whose
 * path prefix matches the current address.
 *
 * These describe rules the server enforces. When a rule changes in the code,
 * the matching line here has to change with it.
 */

import { BEGINNER } from './beginnerNotes.js';
import { UI, GUIDES_TL, BEGINNER_TL, LEVELS_TL } from './guideTagalog.js';

/** What each access level lets a role do, in plain words. */
export const LEVELS = {
    admin: { label: 'Full control', hint: 'Everything in this area, including deleting' },
    approver: { label: 'Approves', hint: 'Approves or rejects what others submit' },
    releaser: { label: 'Releases', hint: 'Pays out or releases what was approved' },
    encoder: { label: 'Creates & edits', hint: 'Enters new records and edits them' },
    void: { label: 'Voids', hint: 'Cancels or voids a record' },
    view: { label: 'Views', hint: 'Can open and read, but not change' },
};

export const MODULE_GUIDES = [
    {
        key: 'sales',
        permissionModule: 'sales',
        title: 'Sales',
        icon: 'ri-shopping-cart-line',
        paths: ['/sales-orders', '/ar-invoices', '/receipts', '/remittances', '/sales/'],
        summary: 'Selling to customers: taking the order, delivering it, collecting the money, and getting that money into the office.',
        stagesTitle: 'A sales order\'s stages',
        stages: [
            { name: 'For Payment', what: 'The order is placed and nothing, or only part, has been collected.' },
            { name: 'Partially Paid', what: 'Some of the amount has been collected; a balance is still owed.' },
            { name: 'For Release', what: 'Fully paid, but the goods have not been delivered or handed over yet.' },
            { name: 'For Turnover', what: 'Delivered and paid, but the money is still with the driver or checker.' },
            { name: 'Closed', what: 'Paid, delivered, and the money is in the office. Nothing left to do.' },
            { name: 'Cancelled / Sales Returned', what: 'The order was cancelled, or the customer returned the goods.' },
        ],
        steps: [
            'Create the order: choose the customer, the sales rep, the items and the payment mode (Cash, COD or Credit).',
            'For a delivery, assign a Driver. Add a Checker when one rides along — the checker is the one who takes the customer\'s money.',
            'Mark the order delivered from the Deliveries board. Money collected at the door is recorded there.',
            'The driver or checker hands the money to the sales rep, and the handover is recorded.',
            'The sales rep remits the collections; an approver verifies the remittance against what was received.',
        ],
        notes: [
            'Cash is paid at the counter. COD is collected on delivery. Credit is paid later, by the due date, and counts against the customer\'s credit limit.',
            'Use the timeline button on a sales order to see exactly which stage it is at and what it is waiting on.',
            'A discount is per unit, from ₱0 to ₱100.',
            'A sales return has to be made within the allowed number of days after the receipt and goes through approval.',
            'A sales rep sees only their own orders; a sales administrator sees everyone\'s.',
        ],
    },
    {
        key: 'inventory',
        permissionModule: 'inventory',
        title: 'Inventory',
        icon: 'ri-archive-line',
        paths: ['/inventory', '/purchase-orders', '/stock-returns', '/received-stocks', '/suppliers'],
        summary: 'Buying stock from suppliers, receiving it into the warehouse, and keeping track of what is on hand.',
        stagesTitle: 'From request to stock',
        stages: [
            { name: 'Purchase Request (Pending)', what: 'Someone has asked to buy stock. It waits for an approver.' },
            { name: 'Approved', what: 'The request becomes a Purchase Order and is given its PO number.' },
            { name: 'Receiving', what: 'Goods arrive and are received against the PO, in full or in part. Each delivery becomes a batch of stock.' },
            { name: 'Completed', what: 'Everything on the PO has been received.' },
            { name: 'Disapproved / Voided', what: 'The request was turned down, or the PO was voided.' },
        ],
        steps: [
            'Create a purchase request: choose the supplier and the products and quantities needed.',
            'An approver approves it, which turns it into a Purchase Order with a PO number.',
            'When the goods arrive, receive them against the PO. Enter the batch, the quantity and the prices.',
            'The received quantity is added to Inventory Stocks and is ready to sell.',
            'If goods are bad or short, raise a Stock Return against the PO. Once approved, record what the supplier replaced and what was a loss.',
        ],
        notes: [
            'Each product has a minimum stock level. When stock drops below it, administrators get a low-stock notification.',
            'Stock is held in batches, so the same product can have different costs and prices.',
            'Purchase requests and stock returns waiting for a decision also appear on the Approvals page.',
        ],
    },
    {
        key: 'payroll',
        permissionModule: 'payroll',
        title: 'Payroll',
        icon: 'ri-money-peso-circle-line',
        paths: ['/payrolls', '/loans', '/payroll-', '/sales-incentives'],
        summary: 'Paying employees: preparing a payroll for a group, getting it approved, releasing it, and managing employee loans.',
        stagesTitle: 'A payroll\'s stages',
        stages: [
            { slug: 'draft', name: 'Draft', what: 'Being prepared. It can be changed or deleted freely by the person who created it.', next: 'Finish the entries, then use Submit for Approval.' },
            { slug: 'approval', name: 'For Approval', what: 'Submitted and waiting for an approver to check it. It also appears on the Approvals page.', next: 'An approver opens it and chooses Approve or Disapprove.' },
            { slug: 'for-release', name: 'For Release', what: 'Approved. The figures are agreed and it is ready to be paid out.', next: 'When the pay is going out, use Release Payroll.' },
            { slug: 'completed', name: 'Completed', what: 'Released. The journal entry is posted, loan deductions are recorded, and it is locked.', next: 'Nothing more to do. You can still print it and its payslips.' },
        ],
        stagesNote: 'Disapproved is a side exit from For Approval. An approver can mark the payroll back to Draft so it can be corrected and submitted again.',
        extraPages: [
            {
                title: 'How the pay is worked out',
                icon: 'ri-calculator-line',
                blocks: [
                    { type: 'lead', text: 'Every employee\'s pay on a payroll is built the same way, from three simple sums.' },
                    { type: 'formula', rows: [
                        ['Basic pay', 'daily rate × days worked'],
                        ['Total earnings', 'basic pay + overtime and other earnings'],
                        ['Net pay', 'total earnings − total deductions'],
                    ] },
                    { type: 'tip', text: 'The daily rate comes from the employee\'s position. Change it under Libraries → Positions.' },
                    { type: 'tip', text: 'An employee can be on only one unfinished payroll for a period. If the system says an ongoing payroll already exists, release or remove that one first.' },
                ],
            },
            {
                title: 'Editing and releasing',
                icon: 'ri-lock-line',
                blocks: [
                    { type: 'lead', text: 'Who can change a payroll depends on how far along it is.' },
                    { type: 'table', head: ['Stage', 'Who can edit'], rows: [
                        ['Draft', 'The person who created it'],
                        ['For Approval, For Release, Disapproved', 'Super Admin only'],
                        ['Completed (released)', 'Nobody — the books are already posted'],
                    ] },
                    { type: 'heading', text: 'What releasing does' },
                    { type: 'text', text: 'Releasing is the point of no return. It marks the payroll Completed, posts its journal entry to the books, and records the loan payments deducted on it against each employee\'s loan. That is why a released payroll can no longer be edited. Check the figures before you release.' },
                ],
            },
            {
                title: 'Printing',
                icon: 'ri-printer-line',
                blocks: [
                    { type: 'lead', text: 'There are four things you can print from a payroll. Each opens in a new tab, where it can be read, saved or printed.' },
                    { type: 'icons', items: [
                        { icon: 'ri-printer-line', tone: 'grey', title: 'Print', text: 'The payroll report: every employee on one sheet, with totals.' },
                        { icon: 'ri-file-list-3-line', tone: 'grey', title: 'Payslips', text: 'A compact slip for every employee, two to a page, to cut and hand out.' },
                        { icon: 'ri-file-list-3-line', tone: 'green', title: 'Payslip (green button on a row)', text: 'That one employee\'s slip.' },
                        { icon: 'ri-file-text-line', tone: 'blue', title: 'Pay statement (blue button on a row)', text: 'A full page for one employee showing how each amount was worked out.' },
                    ] },
                ],
            },
        ],
        steps: [
            'Set up a Payroll Group: the employees who are paid together.',
            'Add a payroll, choose the group and the pay period, then check each employee\'s days, earnings and deductions.',
            'Save as Draft, or Submit for Approval when it is ready.',
            'An approver approves it; when the pay goes out, it is released.',
            'Print the payroll report, the payslips, or one employee\'s full-page pay statement.',
        ],
        notes: [
            'Net pay = (daily rate × days worked) + other earnings − deductions. The daily rate comes from the employee\'s position.',
            'An employee loan goes Pending → Approved → Active (released) → Completed, and is paid down by payroll deductions or direct payments.',
            'Payrolls waiting for approval also appear on the Approvals page.',
        ],
    },
    {
        key: 'employees',
        permissionModule: 'employees',
        title: 'Employees',
        icon: 'ri-team-line',
        paths: ['/employees', '/employee'],
        summary: 'The record of everyone who works here: their details, position and status.',
        steps: [
            'Add an employee with their personal details and their Position.',
            'The position sets the daily rate used by payroll, so choose it carefully.',
            'Mark an employee inactive when they leave, rather than deleting the record.',
            'To let an employee sign in, create a user account for them under Users.',
        ],
        notes: [
            'Drivers are picked on a sales order by position: anyone whose position has "driver" in its title.',
            'Checkers are picked by user role instead: give the person the Checker role under Users.',
            'An employee\'s loans and their balances are shown on their profile.',
        ],
    },
    {
        key: 'customers',
        permissionModule: 'customers',
        title: 'Customers',
        icon: 'ri-user-heart-line',
        paths: ['/customers', '/contacts'],
        summary: 'The people and businesses you sell to, with their contact details, credit limit and standing.',
        steps: [
            'Search before adding, so the same customer is not entered twice.',
            'Add the customer with their name, address and contact details.',
            'Set a credit limit if they are allowed to buy on credit.',
            'Open a customer to see their orders and purchase history.',
        ],
        notes: [
            'A credit order is checked against the customer\'s credit limit when it is created and when it is edited.',
            'Mark a customer inactive instead of deleting them; their past orders stay intact.',
            'A blacklisted customer is flagged so staff know before selling to them.',
        ],
    },
    {
        key: 'accounting',
        permissionModule: 'accounting',
        title: 'Accounting',
        icon: 'ri-calculator-line',
        paths: ['/accounting', '/replenishments'],
        summary: 'The books: journal entries, expenses, petty cash, bank and cash, and the financial reports built from them.',
        stagesTitle: 'An expense\'s stages',
        stages: [
            { name: 'Draft', what: 'Recorded and waiting for an approver.' },
            { name: 'Approved', what: 'Checked against the available cash or bank balance, then posted to the books as a journal entry.' },
            { name: 'Voided', what: 'Cancelled. If it had been approved, its journal entry is reversed.' },
        ],
        steps: [
            'Most journal entries are created for you — by sales, receipts, payroll and expenses. Enter one by hand only for adjustments.',
            'Record an expense with its account, amount and how it was paid. An approver approves it.',
            'For petty cash, spend from a fund, then submit a replenishment request. The approver says whether the top-up comes from cash or from a bank account.',
            'Use the reports (Trial Balance, Profit & Loss, Balance Sheet, Cash Flow) to review a period.',
        ],
        notes: [
            'An expense cannot be approved for more than the cash on hand or the bank balance it is paid from.',
            'Entries are corrected by reversing them, not by deleting them, so the trail stays complete.',
            'Expenses and replenishments waiting for a decision also appear on the Approvals page.',
        ],
    },
    {
        key: 'user_management',
        permissionModule: 'user_management',
        title: 'Users',
        icon: 'ri-shield-user-line',
        paths: ['/users'],
        summary: 'Who can sign in, and what each person is allowed to do.',
        steps: [
            'Create a user account for an employee who needs to sign in.',
            'Give the account one or more Roles. A person can hold several roles at once.',
            'What each role can do is set under Libraries → Roles, per module and per area.',
            'Deactivate an account when someone leaves, and reset a password when they are locked out.',
        ],
        notes: [
            'Roles decide access. Position decides pay. They are separate things.',
            'The Checker role makes a person selectable as the checker on a sales order.',
            'A Super Admin can do everything, whatever the role settings say.',
        ],
    },
    {
        key: 'libraries',
        permissionModule: 'libraries',
        title: 'Libraries',
        icon: 'ri-book-2-line',
        paths: ['/libraries'],
        summary: 'The lists the rest of the system picks from: products, brands, units, packagings, locations, positions, roles and statuses.',
        steps: [
            'Add brands, units and packagings first; a product is built from them.',
            'Add products with their weight and minimum stock level.',
            'Add positions with their daily rate; employees are assigned to them.',
            'Set what each role can do under Roles → Permissions.',
        ],
        notes: [
            'Changing a library entry affects every record that uses it, so rename with care.',
            'Deactivate an entry instead of deleting it when it is no longer used.',
        ],
    },
    {
        key: 'approvals',
        permissionModule: null,
        title: 'Approvals',
        icon: 'ri-checkbox-circle-line',
        paths: ['/approvals'],
        summary: 'One place for everything waiting on your decision, across every module.',
        steps: [
            'The list shows only what you are allowed to approve.',
            'Use the pills to filter by type, the search box to find a record, and Sort by to reorder.',
            'Approve or Reject straight from the row, with an optional remark.',
            'Rows with a Review button need more information than this page collects — open them in their own module to finish.',
        ],
        notes: [
            'Remittances and replenishment approvals are finished on their own screens, because they need details about how the money was received or where it comes from.',
            'A cheque waits here from the day it is received or issued until someone confirms the bank cleared it. Approving confirms it cleared; a bounced cheque is recorded on the Check Register, where a reason and a supervisor are required.',
            'When someone else actions a request, it disappears from your list and your notification is marked done.',
            'Who can approve each type is set by the "Approves" access on that module — open that module\'s guide to see the roles.',
        ],
    },
    {
        key: 'dashboard',
        permissionModule: null,
        title: 'Getting around',
        icon: 'ri-compass-3-line',
        paths: ['/'],
        summary: 'How the system is laid out and where to find help.',
        steps: [
            'The menu on the left lists the modules you have access to. Yours may be shorter than a colleague\'s.',
            'The bell at the top shows your notifications; click one to go straight to what it is about.',
            'On list pages, use the search box and the Sort by dropdown to find records.',
            'Open this Guide button on any page to read about the module you are in.',
        ],
        notes: [
            'If a button or page is missing, your role does not include it. Ask an administrator.',
            'The full user manual is on the Dashboard.',
        ],
    },
];

/** The guide for the page the user is on; the general one when nothing matches. */
export function guideKeyForPath(path) {
    const clean = String(path || '/').split('?')[0];

    const match = MODULE_GUIDES
        .filter(guide => guide.key !== 'dashboard')
        .find(guide => guide.paths.some(prefix => clean.startsWith(prefix)));

    return match ? match.key : 'dashboard';
}

/**
 * Turn one module's guide into the pages of its chapter.
 *
 * Every chapter reads in the same order — what it is for and who looks after
 * it, the stages, the steps, any extra pages of its own, then the tips — so
 * once someone has read one chapter they know where to look in the next.
 */
export function pagesFor(guide, lang = 'en') {
    // English fills in for anything a translation has not covered.
    const beginner = (lang === 'tl' && BEGINNER_TL[guide.key]) || BEGINNER[guide.key] || {};
    const titles = (UI[lang] || UI.en).pages;

    // Page one is for the first-day reader: the idea in three lines before
    // anything else, and only then who looks after it.
    const pages = [
        {
            title: titles.start,
            icon: guide.icon,
            blocks: [
                { type: 'lead', text: guide.summary },
                ...(beginner.inShort ? [{ type: 'inShort', items: beginner.inShort }] : []),
            ],
        },
    ];

    // The story comes before the rules: it is far easier to follow the stages
    // and steps after seeing one real case go from start to finish.
    if (beginner.example) {
        pages.push({
            title: titles.example,
            icon: 'ri-user-smile-line',
            blocks: [{ type: 'example', title: beginner.example.title, items: beginner.example.steps }],
        });
    }

    if (guide.permissionModule) {
        pages.push({
            title: titles.who,
            icon: 'ri-team-line',
            blocks: [
                { type: 'lead', text: titles.whoLead },
                { type: 'roles' },
            ],
        });
    }

    if (guide.stages?.length) {
        pages.push({
            title: guide.stagesTitle || titles.stages,
            icon: 'ri-route-line',
            blocks: [
                { type: 'stages', items: guide.stages },
                ...(guide.stagesNote ? [{ type: 'tip', text: guide.stagesNote }] : []),
            ],
        });
    }

    pages.push({
        title: titles.steps,
        icon: 'ri-list-ordered',
        blocks: [{ type: 'steps', items: guide.steps }],
    });

    (guide.extraPages || []).forEach(page => pages.push(page));

    if (guide.notes?.length) {
        pages.push({
            title: titles.notes,
            icon: 'ri-lightbulb-line',
            blocks: guide.notes.map(text => ({ type: 'tip', text })),
        });
    }

    if (beginner.words?.length) {
        pages.push({
            title: titles.words,
            icon: 'ri-translate-2',
            blocks: [{ type: 'words', items: beginner.words }],
        });
    }

    return pages;
}

/** Ask the guide book (mounted in the top bar) to open, from anywhere. */
export function openGuide(key = null, stage = null) {
    window.dispatchEvent(new CustomEvent('guide:open', { detail: { key, stage } }));
}

/**
 * The chapters in one language.
 *
 * English is the structure: which chapters exist, their icons, paths, and the
 * status behind each stage. For Tagalog the translated words are laid over it,
 * field by field, so the two books always have the same chapters and the same
 * pages in the same order — which is what lets the reader switch language
 * without losing their place.
 */
export function guidesFor(lang = 'en') {
    if (lang !== 'tl') {
        return MODULE_GUIDES;
    }

    return MODULE_GUIDES.map(guide => {
        const tl = GUIDES_TL[guide.key];
        if (!tl) {
            return guide;
        }

        return {
            ...guide,
            title: tl.title ?? guide.title,
            summary: tl.summary ?? guide.summary,
            stagesTitle: tl.stagesTitle ?? guide.stagesTitle,
            stagesNote: tl.stagesNote ?? guide.stagesNote,
            steps: tl.steps ?? guide.steps,
            notes: tl.notes ?? guide.notes,
            // Stage names stay as they appear on screen; only the explanation
            // is translated.
            stages: guide.stages?.map((stage, index) => ({ ...stage, ...(tl.stages?.[index] || {}) })),
            // Extra pages keep their icons and layout; titles and text are
            // replaced block by block.
            extraPages: guide.extraPages?.map((page, index) => {
                const tlPage = tl.extraPages?.[index];
                if (!tlPage) {
                    return page;
                }

                return {
                    ...page,
                    title: tlPage.title ?? page.title,
                    blocks: page.blocks.map((block, b) => {
                        const tlBlock = tlPage.blocks?.[b];
                        if (!tlBlock) {
                            return block;
                        }
                        if (block.type === 'icons') {
                            return { ...block, items: block.items.map((item, i) => ({ ...item, ...(tlBlock.items?.[i] || {}) })) };
                        }

                        return { ...block, ...tlBlock };
                    }),
                };
            }),
        };
    });
}

export function levelsFor(lang = 'en') {
    return lang === 'tl' ? LEVELS_TL : LEVELS;
}

export function uiFor(lang = 'en') {
    return UI[lang] || UI.en;
}
