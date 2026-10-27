/**
 * The beginner's layer of the guide book, one entry per module key.
 *
 * Written for someone on their first day who has never used the system:
 *
 *   inShort  three short lines that say what the module is, in everyday words
 *   example  one small story, start to finish, with a made-up customer or
 *            employee, so the steps have something to hang on
 *   words    the terms that page uses, each explained in one plain sentence
 *
 * Kept apart from moduleGuides.js so the reference text there stays compact and
 * this friendlier layer can be rewritten freely. The same rule applies to both:
 * these describe what the system really does, so they change when it does.
 */
export const BEGINNER = {
    sales: {
        inShort: [
            'This is where you record what a customer buys.',
            'An order is finished when three things are true: it is paid, it is delivered, and the money is in the office.',
            'The system keeps track of which of those three is still missing.',
        ],
        example: {
            title: 'Aling Rosa orders 10 sacks, cash on delivery',
            steps: [
                'You create a sales order for Aling Rosa: 10 sacks, payment mode COD. You choose the driver, and the checker who rides with him.',
                'The order shows For Payment — nothing has been collected yet.',
                'The truck delivers the rice. Aling Rosa pays the checker. On the Deliveries board you mark the order delivered and enter the amount collected.',
                'The order now shows For Turnover: it is paid and delivered, but the money is still with the checker.',
                'Back at the office the checker hands the money to the sales rep, and the handover is recorded. The order becomes Closed.',
            ],
        },
        words: [
            ['COD', 'Cash on delivery. The customer pays when the goods arrive.'],
            ['Credit', 'The customer pays later, by a due date.'],
            ['AR Invoice', 'The bill for an order. It shows how much is still owed.'],
            ['Receipt', 'The record of a payment the customer made.'],
            ['Remittance', 'Handing collected money in to the office, with a list of what it covers.'],
            ['For Turnover', 'Paid and delivered, but the money has not reached the office yet.'],
        ],
    },
    inventory: {
        inShort: [
            'This is where you buy stock and keep count of it.',
            'You ask to buy, someone approves, the goods arrive, and you receive them into the warehouse.',
            'Only received stock can be sold.',
        ],
        example: {
            title: 'The warehouse needs 200 sacks from a supplier',
            steps: [
                'You create a purchase request for 200 sacks from the supplier.',
                'An approver approves it. It becomes a Purchase Order and gets a PO number.',
                'The truck arrives with 150 sacks. You receive 150 against the PO. They are now in stock and can be sold.',
                'The other 50 arrive next week. You receive them too, and the PO is Completed.',
                'Five sacks turn out to be damaged. You raise a stock return, and once it is approved you record what the supplier replaced.',
            ],
        },
        words: [
            ['Purchase Request (PR)', 'Asking for permission to buy stock.'],
            ['Purchase Order (PO)', 'An approved request. This is what you send to the supplier.'],
            ['Receiving', 'Recording that the goods have actually arrived.'],
            ['Batch', 'One delivery of a product, with its own cost and price.'],
            ['Minimum stock', 'The level below which the system warns you to buy more.'],
            ['Stock Return', 'Sending bad or wrong goods back to the supplier.'],
        ],
    },
    payroll: {
        inShort: [
            'This is where you work out and pay employees\' wages.',
            'You prepare the payroll, someone approves it, then it is released when the pay goes out.',
            'Once released it is locked, so check the numbers first.',
        ],
        example: {
            title: 'Paying the warehouse team for the first half of the month',
            steps: [
                'The warehouse team is already saved as a Payroll Group.',
                'You click Add Payroll, choose that group, and set the period: the 1st to the 15th.',
                'For each person you enter the days worked. Carlo worked 13 days at ₱500 a day, so his basic pay is ₱6,500.',
                'Carlo also has 4 hours of overtime and a ₱500 cash advance to pay back. You add those as an earning and a deduction.',
                'You click Submit for Approval. An approver checks it and approves. On payday it is released, and you print the payslips.',
            ],
        },
        words: [
            ['Payroll Group', 'The employees who are paid together on one payroll.'],
            ['Pay period', 'The dates the pay is for, such as the 1st to the 15th.'],
            ['Daily rate', 'What the employee earns for one day. It comes from their position.'],
            ['Earnings', 'Anything added to basic pay: overtime, allowances.'],
            ['Deductions', 'Anything taken off: contributions, cash advances, loan payments.'],
            ['Net pay', 'What the employee actually takes home.'],
            ['Release', 'Marking the payroll as paid out. This cannot be undone.'],
        ],
    },
    employees: {
        inShort: [
            'This is the list of everyone who works here.',
            'Each person has a position, and the position decides their daily pay.',
            'When someone leaves, switch them to inactive. Do not delete them.',
        ],
        example: {
            title: 'A new cashier, Bianca, starts on Monday',
            steps: [
                'You open Employees and click to add a new one.',
                'You type her name, birthday, contact number and address.',
                'You set her Position to Cashier. Payroll will now use the cashier\'s daily rate for her.',
                'She needs to sign in, so under Users an account is created for her and given the right role.',
            ],
        },
        words: [
            ['Position', 'The person\'s job. It sets their daily rate for payroll.'],
            ['Regular', 'Marks a regular employee, as opposed to a new or temporary one.'],
            ['Inactive', 'No longer working here. The record is kept but hidden from everyday lists.'],
            ['User account', 'What lets an employee sign in. It is made separately, under Users.'],
        ],
    },
    customers: {
        inShort: [
            'This is the list of the people and stores you sell to.',
            'A customer can be given a credit limit: the most they are allowed to owe.',
            'Always search first, so the same customer is not added twice.',
        ],
        example: {
            title: 'A new store wants to buy on credit',
            steps: [
                'You search for the store\'s name to make sure it is not already in the list.',
                'It is not, so you add it with the owner\'s name, address and contact number.',
                'The manager agrees they may owe up to ₱50,000, so you set that as the credit limit.',
                'Later, when a credit order would take them over ₱50,000, the system stops the order.',
            ],
        },
        words: [
            ['Credit limit', 'The most a customer may owe at one time.'],
            ['Regular', 'A customer who buys often.'],
            ['Blacklisted', 'A warning flag: be careful before selling to this customer.'],
            ['Walk-in', 'A one-time buyer who is not saved as a customer.'],
        ],
    },
    accounting: {
        inShort: [
            'This is the money side: what came in, what went out, and what is left.',
            'Most of it fills itself in from sales, receipts, payroll and expenses.',
            'Mistakes are fixed by reversing an entry, never by deleting it.',
        ],
        example: {
            title: 'Paying ₱2,000 for truck repairs in cash',
            steps: [
                'You record an expense: Truck repairs, ₱2,000, paid in cash, with the receipt.',
                'It is saved as a Draft and waits for an approver.',
                'The approver opens it. The system checks there is at least ₱2,000 cash on hand.',
                'The approver approves it. The system writes the journal entry for you, and the cash balance goes down by ₱2,000.',
            ],
        },
        words: [
            ['Journal entry', 'One recorded movement of money, showing where it came from and where it went.'],
            ['Chart of accounts', 'The list of categories money is sorted into.'],
            ['Petty cash', 'A small fund of cash kept for minor expenses.'],
            ['Replenishment', 'Topping a petty cash fund back up after it has been spent.'],
            ['Reverse', 'Cancelling an entry by recording its opposite, so the history stays.'],
            ['Trial balance', 'A report that checks the books add up.'],
        ],
    },
    user_management: {
        inShort: [
            'This is where you decide who can sign in.',
            'A role is a set of permissions. Give a person the roles that match their job.',
            'One person can have more than one role.',
        ],
        example: {
            title: 'Carlo, the warehouse manager, will also coordinate deliveries',
            steps: [
                'You open Users and edit Carlo\'s account.',
                'In Roles he already has Warehouse Manager. You add Logistic Coordinator as well.',
                'You save. He keeps everything he could do before.',
                'He can now also do whatever the Logistic Coordinator role is allowed to do.',
            ],
        },
        words: [
            ['User', 'An account that can sign in.'],
            ['Role', 'A named set of permissions, such as Sales Rep or Accountant.'],
            ['Permission', 'What a role is allowed to do in one part of the system.'],
            ['Deactivate', 'Switching an account off without deleting it.'],
            ['Super Admin', 'The role that can do everything.'],
        ],
    },
    libraries: {
        inShort: [
            'These are the pick-lists the rest of the system uses.',
            'Set them up once; after that you just choose from them.',
            'A product, for example, is chosen from here whenever you sell or buy it.',
        ],
        example: {
            title: 'Adding a new product: Jasmine rice, 25 kg sack',
            steps: [
                'You check the brand exists under Brands. If not, you add it.',
                'You check "kg" is under Units and "Sack" is under Packagings.',
                'Under Products you add the new one: the brand, 25, kg, Sack.',
                'You set its minimum stock to 20, so you are warned when fewer than 20 are left.',
                'It can now be chosen on purchase requests and sales orders.',
            ],
        },
        words: [
            ['Brand', 'The maker or label of a product.'],
            ['Unit', 'How it is measured, such as kg.'],
            ['Packaging', 'How it is packed, such as a sack.'],
            ['Location', 'A place goods are delivered to.'],
            ['Status', 'The label that shows what stage a record is at.'],
        ],
    },
    approvals: {
        inShort: [
            'This page collects everything that is waiting for your yes or no.',
            'You only see what you are allowed to approve.',
            'One click to approve or reject, with a remark if you want.',
        ],
        example: {
            title: 'A loan request is waiting for you',
            steps: [
                'The bell shows a new notification: an employee loan is awaiting approval.',
                'You click it. The Approvals page opens with that loan highlighted.',
                'You read the employee\'s name and the amount.',
                'You click Approve, type a short remark, and confirm.',
                'The loan leaves your list, and the other approvers\' notifications are cleared too.',
            ],
        },
        words: [
            ['Pending', 'Waiting for someone to decide.'],
            ['Approve', 'Say yes. The record moves to its next stage.'],
            ['Reject', 'Say no. The record goes back or stops.'],
            ['Review', 'Open the record in its own module, because it needs more details.'],
            ['Remarks', 'A short note explaining your decision.'],
        ],
    },
    dashboard: {
        inShort: [
            'The menu on the left takes you to each part of the system.',
            'The bell tells you when something needs you.',
            'This book is always one click away, on the page you are on.',
        ],
        example: {
            title: 'Your first five minutes',
            steps: [
                'Sign in. You land on the Dashboard, which shows today\'s numbers.',
                'Look at the menu on the left. Those are the parts you are allowed to use.',
                'Click one. You see a list. The search box finds a record; Sort by reorders the list.',
                'Click the book icon at the top. This guide opens on the page you are looking at.',
                'Click the bell to see anything waiting for you.',
            ],
        },
        words: [
            ['Module', 'One part of the system, such as Sales or Payroll.'],
            ['Record', 'One item in a list: one order, one employee, one payroll.'],
            ['Status', 'The label on a record that says what stage it is at.'],
            ['Draft', 'Saved but not finished or submitted yet.'],
            ['Notification', 'A message from the system that something needs your attention.'],
        ],
    },
};
