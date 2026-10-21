<?php

namespace App\Services\Approvals;

use App\Models\Check;
use App\Models\Expense;
use App\Models\Loan;
use App\Models\Payroll;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Remittance;
use App\Models\ReplenishmentRequest;
use App\Models\SalesOrder;
use App\Models\StockReturn;
use App\Models\User;

/**
 * What the View button on the Approvals page shows for one record.
 *
 * Every source comes back in one shape, so the page needs one modal:
 *
 *   title      what kind of record it is
 *   reference  its number
 *   fields     [{label, value, type}] — type is text, money, date or datetime,
 *              and tells the page how to format the value
 *   lines      its line items, when it has any: {title, columns, rows, total}
 *
 * Blank fields are left out rather than shown as dashes.
 */
class ApprovalDetails
{
    public function for(string $source, int $id): array
    {
        return match ($source) {
            'purchase_order' => $this->purchaseOrder($id),
            'stock_return' => $this->stockReturn($id),
            'payroll' => $this->payroll($id),
            'loan' => $this->loan($id),
            'expense' => $this->expense($id),
            'remittance' => $this->remittance($id),
            'check' => $this->check($id),
            'credit_order', 'delivery' => $this->salesOrder($id, $source),
            'replenishment' => $this->replenishment($id),
            default => abort(404, 'Unknown approval type.'),
        };
    }

    protected function purchaseOrder(int $id): array
    {
        $po = PurchaseOrder::with(['supplier', 'status', 'created_by.employee', 'approved_by.employee', 'items.product.brand', 'items.product.unit'])
            ->findOrFail($id);

        return [
            'title' => 'Purchase Order',
            'reference' => $po->po_number ?: $po->pr_number ?: "PR #{$po->id}",
            'fields' => $this->fields([
                ['PR No.', $po->pr_number],
                ['PO No.', $po->po_number],
                ['Supplier', $po->supplier->name ?? null],
                ['Date', $po->po_date, 'date'],
                ['Status', $po->status->name ?? null],
                ['Requested by', $this->who($po->created_by)],
                ['Approved by', $this->who($po->approved_by)],
                ['Approved on', $po->approved_date, 'date'],
                ['Total', $po->total_amount, 'money'],
            ]),
            'lines' => $this->lines('Items', [
                ['product', 'Product'],
                ['quantity', 'Qty', 'number'],
                ['unit_cost', 'Unit cost', 'money'],
                ['amount', 'Amount', 'money'],
            ], $po->items->map(fn ($item) => [
                'product' => $this->productName($item->product),
                'quantity' => $item->quantity,
                'unit_cost' => (float) $item->unit_cost,
                'amount' => (float) ($item->total_cost ?? $item->quantity * $item->unit_cost),
            ]), (float) $po->total_amount),
        ];
    }

    protected function stockReturn(int $id): array
    {
        $sr = StockReturn::with(['status', 'createdBy.employee', 'approvedBy.employee', 'purchaseOrder.supplier',
            'items.purchaseOrderItem.product.brand', 'items.purchaseOrderItem.product.unit'])
            ->findOrFail($id);

        $rows = $sr->items->map(fn ($item) => [
            'product' => $this->productName($item->purchaseOrderItem?->product),
            'quantity' => $item->quantity,
            'unit_cost' => (float) ($item->purchaseOrderItem->unit_cost ?? 0),
            'amount' => (float) $item->quantity * (float) ($item->purchaseOrderItem->unit_cost ?? 0),
            'remarks' => $item->remarks,
        ]);

        return [
            'title' => 'Stock Return',
            'reference' => $sr->stock_return_no,
            'fields' => $this->fields([
                ['Purchase order', $sr->purchaseOrder?->po_number ?: $sr->purchaseOrder?->pr_number],
                ['Supplier', $sr->purchaseOrder?->supplier?->name],
                ['Reason', $sr->reason],
                ['Status', $sr->status->name ?? null],
                ['Requested by', $this->who($sr->createdBy)],
                ['Requested on', $sr->created_at, 'datetime'],
                ['Decided by', $this->who($sr->approvedBy)],
                ['Decided on', $sr->approved_at, 'datetime'],
            ]),
            'lines' => $this->lines('Items returned', [
                ['product', 'Product'],
                ['quantity', 'Qty', 'number'],
                ['unit_cost', 'Unit cost', 'money'],
                ['amount', 'Amount', 'money'],
                ['remarks', 'Remarks'],
            ], $rows, $rows->sum('amount')),
        ];
    }

    protected function payroll(int $id): array
    {
        $payroll = Payroll::with(['status', 'template', 'creator.employee', 'approvedBy.employee', 'items.employee'])
            ->findOrFail($id);

        return [
            'title' => 'Payroll',
            'reference' => $payroll->payroll_no,
            'fields' => $this->fields([
                ['Payroll group', $payroll->template?->name],
                ['Pay period', $payroll->pay_period_start && $payroll->pay_period_end
                    ? $payroll->pay_period_start->format('M j').' – '.$payroll->pay_period_end->format('M j, Y')
                    : null],
                ['Status', $payroll->status->name ?? null],
                ['Payroll clerk', $this->who($payroll->creator)],
                ['Approved by', $this->who($payroll->approvedBy)],
                ['Approved on', $payroll->approved_at, 'datetime'],
                ['Employees', $payroll->items->count()],
                ['Total net salary', $payroll->total_amount, 'money'],
            ]),
            'lines' => $this->lines('Employees', [
                ['employee', 'Employee'],
                ['days', 'Days', 'number'],
                ['basic', 'Basic', 'money'],
                ['earnings', 'Earnings', 'money'],
                ['deductions', 'Deductions', 'money'],
                ['net', 'Net', 'money'],
            ], $payroll->items->map(fn ($item) => [
                'employee' => $item->employee?->fullname,
                'days' => (float) $item->total_days,
                'basic' => (float) $item->basic_salary,
                'earnings' => (float) $item->total_earnings,
                'deductions' => (float) $item->total_deductions,
                'net' => (float) $item->net_salary,
            ]), (float) $payroll->total_amount),
        ];
    }

    protected function loan(int $id): array
    {
        $loan = Loan::with(['employee', 'added_by.employee', 'approved_by.employee'])->findOrFail($id);

        return [
            'title' => 'Employee Loan',
            'reference' => $loan->loan_no ?: "LOAN #{$loan->id}",
            'fields' => $this->fields([
                ['Employee', $loan->employee?->fullname],
                ['Loan type', $loan->loan_type],
                ['Payment type', $loan->payment_type],
                ['Amount', $loan->amount, 'money'],
                ['Interest rate', $loan->interest_rate !== null ? rtrim(rtrim(number_format((float) $loan->interest_rate, 2), '0'), '.').'%' : null],
                ['Term', $loan->term_months ? $loan->term_months.' month'.($loan->term_months == 1 ? '' : 's') : null],
                ['Purpose', $loan->purpose],
                ['Status', $loan->status ? ucfirst($loan->status) : null],
                ['Remaining balance', $loan->remaining_balance, 'money'],
                ['Requested by', $this->who($loan->added_by)],
                ['Requested on', $loan->created_at, 'datetime'],
                ['Approved by', $this->who($loan->approved_by)],
                ['Approved on', $loan->approved_at, 'datetime'],
            ]),
            'lines' => null,
        ];
    }

    protected function expense(int $id): array
    {
        $expense = Expense::with(['added_by.employee', 'approvedBy.employee', 'glAccount', 'bankAccount'])->findOrFail($id);

        return [
            'title' => 'Expense',
            'reference' => $expense->voucher_no ?: "EXP #{$expense->id}",
            'fields' => $this->fields([
                ['Payee', $expense->payee],
                ['Company', $expense->company],
                ['TIN', $expense->tin],
                ['Address', $expense->business_address],
                ['Description', $expense->description],
                ['Type', $expense->expense_type ? ucwords(str_replace('_', ' ', $expense->expense_type)) : null],
                ['Account', $expense->glAccount ? trim($expense->glAccount->code.' '.$expense->glAccount->name) : null],
                ['Payment method', $expense->payment_method ? ucwords(str_replace('_', ' ', $expense->payment_method)) : null],
                ['Bank account', $expense->bankAccount?->display_name],
                ['Reference no.', $expense->reference_no],
                ['Expense date', $expense->expense_date, 'date'],
                ['Amount', $expense->amount, 'money'],
                ['Status', $expense->status ? ucfirst($expense->status) : null],
                ['Recorded by', $this->who($expense->added_by)],
                ['Approved by', $this->who($expense->approvedBy)],
                ['Approved on', $expense->approved_at, 'datetime'],
            ]),
            'lines' => null,
        ];
    }

    protected function remittance(int $id): array
    {
        $remittance = Remittance::with(['status', 'createdBy.employee', 'approvedBy.employee', 'receipts.customer'])
            ->findOrFail($id);

        return [
            'title' => 'Remittance',
            'reference' => $remittance->remittance_no,
            'fields' => $this->fields([
                ['Date', $remittance->remittance_date, 'date'],
                ['Status', $remittance->status->name ?? null],
                ['Submitted by', $this->who($remittance->createdBy)],
                ['Total', $remittance->total_amount, 'money'],
                ['Received', $remittance->received_amount, 'money'],
                ['Variance', $remittance->variance, 'money'],
                ['Received via', $remittance->received_via],
                ['Reference no.', $remittance->reference_no],
                ['Remarks', $remittance->remarks],
                ['Verified by', $this->who($remittance->approvedBy)],
                ['Verified on', $remittance->approved_at, 'datetime'],
            ]),
            'lines' => $this->lines('Receipts', [
                ['receipt', 'Receipt No.'],
                ['customer', 'Customer'],
                ['mode', 'Mode'],
                ['reference', 'Reference'],
                ['amount', 'Amount', 'money'],
            ], $remittance->receipts->map(fn ($receipt) => [
                'receipt' => $receipt->receipt_number,
                'customer' => $receipt->customer?->name,
                'mode' => $receipt->payment_mode ?: 'Cash',
                'reference' => $receipt->reference_number,
                'amount' => (float) $receipt->amount_paid,
            ]), (float) $remittance->total_amount),
        ];
    }

    protected function check(int $id): array
    {
        $check = Check::with(['customer', 'supplier', 'receivedBy', 'bankAccount', 'clearedBy.employee', 'bouncedBy.employee'])
            ->findOrFail($id);
        $issued = $check->direction === Check::DIRECTION_ISSUED;

        return [
            'title' => 'Cheque',
            'reference' => 'CHK '.($check->check_number ?: '#'.$check->id),
            'fields' => $this->fields([
                ['Direction', $issued ? 'Issued' : 'Received'],
                [$issued ? 'Issued to' : 'Received from', $issued ? $check->supplier?->name : $check->customer?->name],
                ['Bank', $check->bank_name],
                ['Deposit account', $check->bankAccount?->display_name],
                ['Cheque date', $check->check_date, 'date'],
                ['Amount', $check->amount, 'money'],
                ['Status', $check->status ? ucfirst($check->status) : null],
                ['Received by', $check->receivedBy?->fullname],
                ['Cleared by', $this->who($check->clearedBy)],
                ['Cleared on', $check->cleared_at, 'datetime'],
                ['Bounced by', $this->who($check->bouncedBy)],
                ['Bounced on', $check->bounced_at, 'datetime'],
                ['Bounce reason', $check->bounce_reason],
                ['Notes', $check->notes],
            ]),
            'lines' => null,
        ];
    }

    /** A credit sale and a delivery are both a sales order, read for a different question. */
    protected function salesOrder(int $id, string $source): array
    {
        $so = SalesOrder::with(['customer', 'status', 'salesRep', 'driver', 'created_by.employee',
            'creditApprovedBy.employee', 'deliveryApprovedBy.employee', 'items.product.brand', 'items.product.unit'])
            ->findOrFail($id);

        $submission = $so->delivery_submission ?? [];
        $collected = round((float) ($submission['payload']['collected_amount'] ?? 0), 2);
        $submittedBy = ! empty($submission['submitted_by_id']) ? User::with('employee')->find($submission['submitted_by_id']) : null;

        $fields = [
            ['Customer', $so->customer->name ?? 'Walk-in customer'],
            ['Order date', $so->order_date, 'date'],
            ['Payment mode', $so->payment_mode],
            ['Due date', $so->due_date, 'date'],
            ['Deliver to', $so->delivery_location],
            ['Sales rep', $so->salesRep?->fullname],
            ['Driver', $so->driver?->fullname],
            ['Status', $so->status->name ?? null],
            ['Encoded by', $this->who($so->created_by)],
            ['Discount', $so->total_discount > 0 ? $so->total_discount : null, 'money'],
            ['Total', $so->total_amount, 'money'],
        ];

        if ($source === 'credit_order') {
            $fields[] = ['Credit approval', $so->credit_approval ? ucfirst($so->credit_approval) : null];
            $fields[] = ['Decided by', $this->who($so->creditApprovedBy)];
            $fields[] = ['Decided on', $so->credit_approved_at, 'datetime'];
            $fields[] = ['Remarks', $so->credit_approval_remarks];
        } else {
            $fields[] = ['Collected at the door', $collected > 0
                ? '₱'.number_format($collected, 2).' ('.($submission['payload']['collected_mode'] ?? 'Cash').')'
                : ($submission ? 'Nothing collected' : null)];
            $fields[] = ['Submitted by', $this->who($submittedBy)];
            $fields[] = ['Submitted on', $submission['submitted_at'] ?? null, 'datetime'];
            $fields[] = ['Delivered on', $so->delivered_at, 'datetime'];
            $fields[] = ['Approved by', $this->who($so->deliveryApprovedBy)];
            $fields[] = ['Sent back', $so->delivery_rejection];
        }

        return [
            'title' => $source === 'credit_order' ? 'Credit Sale' : 'Delivery',
            'reference' => $so->so_number,
            'fields' => $this->fields($fields),
            'lines' => $this->lines('Items', [
                ['product', 'Product'],
                ['quantity', 'Qty', 'number'],
                ['price', 'Price', 'money'],
                ['discount', 'Discount / unit', 'money'],
                ['amount', 'Amount', 'money'],
            ], $so->items->map(fn ($item) => [
                'product' => $this->productName($item->product),
                'quantity' => $item->quantity,
                'price' => (float) $item->price,
                'discount' => (float) ($item->discount_per_unit ?? 0),
                'amount' => round($item->quantity * ((float) $item->price - (float) ($item->discount_per_unit ?? 0)), 2),
            ]), (float) $so->total_amount),
        ];
    }

    protected function replenishment(int $id): array
    {
        $request = ReplenishmentRequest::with(['fund', 'createdBy.employee', 'reviewedBy.employee', 'expenses'])->findOrFail($id);

        return [
            'title' => 'Replenishment',
            'reference' => $request->reference_no,
            'fields' => $this->fields([
                ['Fund', $request->fund?->name],
                ['Period', $request->period_label],
                ['Expenses', $request->expense_count],
                ['Total', $request->total_amount, 'money'],
                ['Status', $request->status ? ucfirst($request->status) : null],
                ['Prepared by', $this->who($request->createdBy)],
                ['Submitted on', $request->submitted_at, 'datetime'],
                ['Reviewed by', $this->who($request->reviewedBy)],
                ['Reviewed on', $request->reviewed_at, 'datetime'],
                ['Review notes', $request->review_notes],
            ]),
            // A rejected request's expenses are released back to the fund,
            // so it has none left to list.
            'lines' => $this->lines('Expenses', [
                ['voucher', 'Voucher'],
                ['date', 'Date', 'date'],
                ['payee', 'Payee'],
                ['description', 'Description'],
                ['amount', 'Amount', 'money'],
            ], $request->expenses->map(fn ($expense) => [
                'voucher' => $expense->voucher_no,
                'date' => $expense->expense_date,
                'payee' => $expense->payee,
                'description' => $expense->description,
                'amount' => (float) $expense->amount,
            ]), (float) $request->total_amount),
        ];
    }

    /** [label, value, type?] triples into fields, skipping the blank ones. */
    protected function fields(array $triples): array
    {
        return collect($triples)
            ->filter(fn ($f) => $f[1] !== null && $f[1] !== '')
            ->map(fn ($f) => [
                'label' => $f[0],
                'value' => $f[1] instanceof \DateTimeInterface ? $f[1]->format(DATE_ATOM) : $f[1],
                'type' => $f[2] ?? 'text',
            ])
            ->values()
            ->all();
    }

    /** A line-item table, or null when there are no lines to show. */
    protected function lines(string $title, array $columns, $rows, ?float $total = null): ?array
    {
        $rows = collect($rows)->values();
        if ($rows->isEmpty()) {
            return null;
        }

        return [
            'title' => $title,
            'columns' => collect($columns)->map(fn ($c) => ['key' => $c[0], 'label' => $c[1], 'type' => $c[2] ?? 'text'])->all(),
            'rows' => $rows->all(),
            'total' => $total,
        ];
    }

    protected function who(?User $user): ?string
    {
        return $user?->employee?->fullname ?? $user?->username;
    }

    /** Products carry no name of their own; a line is named by brand, weight and unit. */
    protected function productName(?Product $product): ?string
    {
        if (! $product) {
            return null;
        }

        return trim(($product->brand?->name ?? '').' '.($product->weight ?? '').' '.($product->unit?->name ?? ''))
            ?: ($product->code ?? null);
    }
}
