<?php

namespace App\Services\Approvals;

use App\Models\Check;
use App\Models\Expense;
use App\Models\ListStatus;
use App\Models\Loan;
use App\Models\Payroll;
use App\Models\PurchaseOrder;
use App\Models\Remittance;
use App\Models\ReplenishmentRequest;
use App\Models\SalesOrder;
use App\Models\StockReturn;

/**
 * One description per thing in this system that waits on an approver.
 *
 * The modules were each built with their own approval shape, and this class is
 * where those differences are written down once instead of being re-discovered
 * at every call site. Three of them matter:
 *
 *  1. Approval state is stored two different ways. Purchase orders, stock
 *     returns, payrolls and remittances point `status_id` at `list_statuses`;
 *     loans, expenses and replenishments keep a plain string in `status`. Each
 *     source therefore declares its own `pending` query rather than sharing one.
 *
 *  2. The decision payloads disagree. A purchase order wants a `status_id`, a
 *     payroll wants a status *slug*, a stock return wants 'approved' /
 *     'disapproved'. The adapter in ApprovalService translates one decision into
 *     whichever of those the underlying service expects.
 *
 *  3. Not everything can be approved in one click, and pretending otherwise
 *     would quietly break things. Approving a remittance requires `received_via`,
 *     which is derived from the verification table on the remittance screen;
 *     approving a replenishment requires `source_type` (and a bank account when
 *     it is a bank) because the journal entry is wrong without it. Those two
 *     declare `can_approve_inline => false`, and the page sends the approver to
 *     the module screen instead of offering a button that cannot work.
 *
 * Cheques are here too: a cheque is "pending" from the day it is received or
 * issued until someone confirms the bank cleared it.
 *
 * A sales order itself is never approved — `requires_batch_approval` is
 * vestigial and hardcoded false on create, see SalesOrderClass::store(). Its
 * *delivery* is: "Mark Delivered" only submits one, and it is recorded, with
 * any money collected at the door, when an approver confirms it. That state is
 * not a status at all but a held submission, which is why the source names the
 * column to `watch` instead of relying on a status change. A sale on *credit*
 * is the other thing approved about an order, and works the same way: it is
 * the terms being cleared, not the order.
 */
class ApprovalRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function sources(): array
    {
        return [
            'purchase_order' => [
                'model' => PurchaseOrder::class,
                'label' => 'Purchase Order',
                'plural' => 'Purchase Orders',
                'module' => 'inventory',
                'submodule' => 'purchase_orders',
                'icon' => 'ri-file-list-3-line',
                'url' => '/purchase-orders',
                'can_approve_inline' => true,
                'can_reject_inline' => true,
                'pending' => fn () => PurchaseOrder::with(['supplier', 'created_by'])
                    ->whereHas('status', fn ($q) => $q->where('slug', 'pending')),
                'row' => fn (PurchaseOrder $po) => [
                    'reference' => $po->po_number ?: $po->pr_number ?: "PR #{$po->id}",
                    'summary' => $po->supplier->name ?? 'No supplier',
                    'amount' => (float) $po->total_amount,
                    'requested_by' => $po->created_by->name ?? null,
                    'requested_at' => $po->po_date ?? $po->created_at,
                ],
            ],

            'stock_return' => [
                'model' => StockReturn::class,
                'label' => 'Stock Return',
                'plural' => 'Stock Returns',
                'module' => 'inventory',
                'submodule' => 'stock_returns',
                'icon' => 'ri-arrow-go-back-line',
                'url' => '/stock-returns',
                'can_approve_inline' => true,
                'can_reject_inline' => true,
                'pending' => fn () => StockReturn::with(['createdBy'])
                    ->whereHas('status', fn ($q) => $q->where('slug', 'pending')),
                'row' => fn (StockReturn $sr) => [
                    'reference' => $sr->stock_return_no,
                    'summary' => $sr->reason ?: 'No reason given',
                    'amount' => null,
                    'requested_by' => $sr->createdBy->name ?? null,
                    'requested_at' => $sr->created_at,
                ],
            ],

            'payroll' => [
                'model' => Payroll::class,
                'label' => 'Payroll',
                'plural' => 'Payrolls',
                'module' => 'payroll',
                'submodule' => 'payroll_processing',
                'icon' => 'ri-wallet-3-line',
                'url' => '/payrolls',
                'can_approve_inline' => true,
                'can_reject_inline' => true,
                // 'approval' is the slug payrolls sit at while awaiting a
                // decision; approving moves them to 'for-release', which is a
                // separate control point held by a 'releaser'.
                'pending' => fn () => Payroll::whereHas('status', fn ($q) => $q->where('slug', 'approval')),
                'row' => fn (Payroll $p) => [
                    'reference' => $p->payroll_no,
                    'summary' => $p->pay_period_start && $p->pay_period_end
                        ? 'Period '.\Carbon\Carbon::parse($p->pay_period_start)->format('M j')
                            .' – '.\Carbon\Carbon::parse($p->pay_period_end)->format('M j, Y')
                        : 'Payroll run',
                    'amount' => (float) $p->total_amount,
                    'requested_by' => null,
                    'requested_at' => $p->created_at,
                ],
            ],

            'loan' => [
                'model' => Loan::class,
                'label' => 'Employee Loan',
                'plural' => 'Employee Loans',
                'module' => 'payroll',
                'submodule' => 'loans',
                'icon' => 'ri-hand-coin-line',
                'url' => '/payrolls',
                'can_approve_inline' => true,
                'can_reject_inline' => true,
                'pending' => fn () => Loan::with(['employee'])->where('status', 'pending'),
                'row' => fn (Loan $l) => [
                    'reference' => $l->loan_no ?: "LOAN #{$l->id}",
                    // Employee exposes `fullname`, not `name`.
                    'summary' => $l->employee?->fullname ?: ($l->loan_type ?? 'Loan'),
                    'amount' => (float) $l->amount,
                    'requested_by' => null,
                    'requested_at' => $l->created_at,
                ],
            ],

            'expense' => [
                'model' => Expense::class,
                'label' => 'Expense',
                'plural' => 'Expenses',
                'module' => 'accounting',
                'submodule' => 'expenses',
                'icon' => 'ri-receipt-line',
                'url' => '/accounting/expenses',
                // Approving can still fail its own business rule — a cash
                // expense over the cash-on-hand balance is refused — and that
                // 422 is surfaced to the approver rather than swallowed.
                'can_approve_inline' => true,
                // There is no reject endpoint for a general expense, so the
                // page offers Review instead of a button that would 404.
                'can_reject_inline' => false,
                'pending' => fn () => Expense::with(['added_by'])
                    ->whereNull('fund_id')
                    ->where('status', 'draft'),
                'row' => fn (Expense $e) => [
                    'reference' => $e->voucher_no ?: "EXP #{$e->id}",
                    'summary' => $e->payee ?: ($e->description ?: 'Expense'),
                    'amount' => (float) $e->amount,
                    'requested_by' => $e->added_by->name ?? null,
                    'requested_at' => $e->expense_date ?? $e->created_at,
                ],
            ],

            'remittance' => [
                'model' => Remittance::class,
                'label' => 'Remittance',
                'plural' => 'Remittances',
                'module' => 'sales',
                'submodule' => 'remittances',
                'icon' => 'ri-safe-2-line',
                'url' => '/remittances',
                // Approving requires received_via, which is derived from the
                // amounts entered in the verification table on the remittance
                // screen. There is nothing sensible to default it to here.
                'can_approve_inline' => false,
                'can_reject_inline' => false,
                'pending' => fn () => Remittance::with(['createdBy'])
                    ->whereHas('status', fn ($q) => $q->where('slug', 'for-verification')),
                'row' => fn (Remittance $r) => [
                    'reference' => $r->remittance_no,
                    'summary' => 'Variance ₱'.number_format((float) $r->variance, 2),
                    'amount' => (float) $r->total_amount,
                    'requested_by' => $r->createdBy->name ?? null,
                    'requested_at' => $r->remittance_date ?? $r->created_at,
                ],
            ],

            'check' => [
                'model' => Check::class,
                'label' => 'Cheque',
                'plural' => 'Cheques',
                'module' => 'accounting',
                'submodule' => 'check_register',
                'icon' => 'ri-bank-card-line',
                'url' => '/accounting/check-register',
                // Approving a cheque confirms it cleared: the amount is posted
                // to the bank, and for a customer's cheque it is released into
                // the invoice balance. It can still be refused by its own rules
                // (a bank deposit that cannot be matched, say), and that
                // message is shown to the approver.
                'can_approve_inline' => true,
                // Bouncing a cheque needs a reason and a supervisor's
                // authorization, both collected on the check register. It puts
                // the customer back in debt, so it is not a one-click action.
                'can_reject_inline' => false,
                'pending' => fn () => Check::with(['customer', 'supplier', 'receivedBy'])->pending(),
                'row' => fn (Check $c) => [
                    'reference' => 'CHK '.($c->check_number ?: '#'.$c->id),
                    'summary' => collect([
                        $c->direction === Check::DIRECTION_ISSUED
                            ? 'Issued to '.($c->supplier->name ?? 'supplier')
                            : 'Received from '.($c->customer->name ?? 'customer'),
                        $c->bank_name,
                        $c->check_date ? 'dated '.$c->check_date->format('M d, Y') : null,
                    ])->filter()->implode(' · '),
                    'amount' => (float) $c->amount,
                    'requested_by' => $c->receivedBy?->fullname,
                    'requested_at' => $c->created_at,
                ],
            ],

            'credit_order' => [
                'model' => SalesOrder::class,
                'label' => 'Credit Sale',
                'plural' => 'Credit Sales',
                'module' => 'sales',
                'submodule' => 'sales_orders',
                'icon' => 'ri-bank-card-2-line',
                'url' => '/sales-orders',
                'watch' => ['credit_approval'],
                'can_approve_inline' => true,
                // Rejecting cancels the order and puts its stock back.
                'can_reject_inline' => true,
                'pending' => fn () => SalesOrder::with(['customer', 'created_by'])->awaitingCreditApproval(),
                'row' => function (SalesOrder $so) {
                    // What the customer already owes on other invoices is the
                    // first thing an approver wants to know.
                    $owing = $so->customer_id
                        ? (float) \App\Models\ArInvoice::whereHas('sales_order', fn ($q) => $q
                            ->where('customer_id', $so->customer_id)
                            ->where('id', '!=', $so->id)
                            ->whereDoesntHave('status', fn ($s) => $s->where('slug', 'cancelled')))
                            ->where('balance_due', '>', 0)
                            ->sum('balance_due')
                        : 0.0;

                    return [
                        'reference' => $so->so_number,
                        'summary' => collect([
                            $so->customer->name ?? 'Walk-in customer',
                            $owing > 0
                                ? 'already owes ₱'.number_format($owing, 2)
                                : 'nothing else owing',
                            $so->due_date ? 'due '.$so->due_date->format('M d, Y') : null,
                        ])->filter()->implode(' · '),
                        'amount' => (float) $so->total_amount,
                        'requested_by' => $so->created_by->name ?? null,
                        'requested_at' => $so->created_at,
                    ];
                },
            ],

            'delivery' => [
                'model' => SalesOrder::class,
                'label' => 'Delivery',
                'plural' => 'Deliveries',
                'module' => 'sales',
                'submodule' => 'sales_orders',
                'icon' => 'ri-truck-line',
                'url' => '/sales-orders',
                // Pending is a held submission, not a status.
                'watch' => ['delivery_submission'],
                // Approving records the delivery as encoded. It can still be
                // refused — the order may have been paid or changed while it
                // waited — and that message is shown to the approver.
                'can_approve_inline' => true,
                'can_reject_inline' => true,
                'pending' => fn () => SalesOrder::with(['customer'])->awaitingDeliveryApproval(),
                'row' => function (SalesOrder $so) {
                    $submission = $so->delivery_submission ?? [];
                    $collected = round((float) ($submission['payload']['collected_amount'] ?? 0), 2);
                    $by = ! empty($submission['submitted_by_id'])
                        ? \App\Models\User::find($submission['submitted_by_id'])
                        : null;

                    return [
                        'reference' => $so->so_number,
                        'summary' => collect([
                            $so->customer->name ?? 'Walk-in customer',
                            $collected > 0
                                ? '₱'.number_format($collected, 2).' collected ('.($submission['payload']['collected_mode'] ?? 'Cash').')'
                                : 'nothing collected at the door',
                        ])->implode(' · '),
                        'amount' => (float) $so->total_amount,
                        'requested_by' => $by?->name,
                        'requested_at' => $submission['submitted_at'] ?? $so->updated_at,
                    ];
                },
            ],

            'replenishment' => [
                'model' => ReplenishmentRequest::class,
                'label' => 'Replenishment',
                'plural' => 'Replenishments',
                'module' => 'accounting',
                'submodule' => 'petty_cash',
                'icon' => 'ri-refresh-line',
                'url' => '/replenishments',
                // Approving requires source_type (cash or bank) and a bank
                // account when it is a bank: without it the journal entry falls
                // back to a catch-all account that drifts negative.
                'can_approve_inline' => false,
                'can_reject_inline' => true,
                'pending' => fn () => ReplenishmentRequest::with(['fund'])
                    ->where('status', 'submitted'),
                'row' => fn (ReplenishmentRequest $r) => [
                    'reference' => $r->reference_no,
                    'summary' => ($r->fund->name ?? 'Fund').' · '.$r->expense_count.' expense(s)',
                    'amount' => (float) $r->total_amount,
                    'requested_by' => null,
                    'requested_at' => $r->submitted_at ?? $r->created_at,
                ],
            ],
        ];
    }

    public function get(string $key): ?array
    {
        return $this->sources()[$key] ?? null;
    }

    public function keys(): array
    {
        return array_keys($this->sources());
    }

    /**
     * The status id a `list_statuses`-backed source should land on. Kept here so
     * a missing seed row fails loudly at the call site instead of writing null.
     */
    public function statusId(string $slug): int
    {
        $status = ListStatus::getBySlug($slug);

        if (! $status) {
            throw new \RuntimeException("Missing list_statuses row for slug '{$slug}'.");
        }

        return (int) $status->id;
    }
}
