<?php

namespace App\Services\Modules;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use App\Models\ArInvoice;
use App\Models\SalesOrder;
use App\Models\Receipt;
use App\Models\ListStatus;
use App\Http\Resources\Modules\ArInvoiceResource;
use App\Models\SalesOrderIncentive;
use App\Services\PrintClass;
use App\Services\Accounting\JournalEntryService;
use App\Services\System\Permission\PermissionService;


class ArInvoiceClass
{
    protected $print;

    public function __construct(PrintClass $print, protected JournalEntryService $journalEntryService)
    {
        $this->print = $print;
    }
    public function lists($request){
        $user = Auth::user();
        $employeeId = ($user && !app(PermissionService::class)->userHasAccess($user, 'sales', null, 'admin'))
            ? $user->employee?->id
            : null;

        $data = ArInvoiceResource::collection(
            ArInvoice::with(['sales_order.customer', 'sales_order.salesRep', 'sales_order.created_by.employee', 'sales_order.items.product', 'sales_order.status', 'status', 'receipts.status'])
                ->whereHas('sales_order', function ($q) {
                    $q->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(payment_mode)'), ['credit', 'credit sales']);
                })
                ->when($employeeId, function ($query) use ($employeeId) {
                    $query->whereHas('sales_order', function ($soQuery) use ($employeeId) {
                        $soQuery->where(function ($salesOrderQuery) use ($employeeId) {
                            $salesOrderQuery
                                ->where('added_by_id', $employeeId)
                                ->orWhere('sales_rep_id', $employeeId);
                        });
                    });
                })
                ->when($request->location_id, function ($query, $locationId) {
                    $query->whereHas('sales_order', function ($q) use ($locationId) {
                        $q->where('location_id', $locationId);
                    });
                })
                ->when($request->status, function ($query, $status) {
                    $query->whereHas('status', function ($q) use ($status) {
                        $q->where('slug', $status);
                    });
                })
                ->when($request->keyword, function ($query,$keyword) {
                    $query->where('invoice_number', 'LIKE', "%{$keyword}%")
                          ->orWhereHas('sales_order', function($q) use ($keyword){
                              $q->where('so_number', 'LIKE', "%{$keyword}%")
                                ->orWhereHas('customer', function($cq) use ($keyword){
                                    $cq->where('name', 'LIKE', "%{$keyword}%");
                                });
                          })
                          ->orWhereHas('status', function($sq) use ($keyword){
                              $sq->where('name', 'LIKE', "%{$keyword}%");
                          });
                })
                ->orderBy('created_at', 'DESC')
                ->paginate($request->count)
        );
        return $data;
    }

    public function remittanceCandidates($request)
    {
        $user = Auth::user();
        $employeeId = ($user && !app(PermissionService::class)->userHasAccess($user, 'sales', null, 'admin'))
            ? $user->employee?->id
            : null;

        $data = ArInvoiceResource::collection(
            ArInvoice::with(['sales_order.customer', 'sales_order.salesRep', 'status'])
                ->whereHas('sales_order', function ($q) {
                    $q->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(payment_mode)'), ['credit', 'credit sales']);
                })
                ->where('balance_due', '>', 0)
                ->when($employeeId, function ($query) use ($employeeId) {
                    $query->whereHas('sales_order', function ($soQuery) use ($employeeId) {
                        $soQuery->where(function ($salesOrderQuery) use ($employeeId) {
                            $salesOrderQuery
                                ->where('added_by_id', $employeeId)
                                ->orWhere('sales_rep_id', $employeeId);
                        });
                    });
                })
                ->when($request->location_id, function ($query, $locationId) {
                    $query->whereHas('sales_order', function ($soQuery) use ($locationId) {
                        $soQuery->where('location_id', $locationId);
                    });
                })
                ->orderBy('created_at', 'DESC')
                ->paginate($request->count ?: 10)
        );

        return $data;
    }


    public function dashboard(){
        $user = Auth::user();
        $employeeId = ($user && !app(PermissionService::class)->userHasAccess($user, 'sales', null, 'admin'))
            ? $user->employee?->id
            : null;
        $cancelledId = ListStatus::getBySlug('cancelled')?->id ?? 0;

        $base = ArInvoice::where('status_id', '!=', $cancelledId)
            ->when($employeeId, function ($query) use ($employeeId) {
                $query->whereHas('sales_order', function ($soQuery) use ($employeeId) {
                    $soQuery->where(function ($salesOrderQuery) use ($employeeId) {
                        $salesOrderQuery
                            ->where('added_by_id', $employeeId)
                            ->orWhere('sales_rep_id', $employeeId);
                    });
                });
            });

        $total_invoices      = (clone $base)->count();
        $outstanding_balance = (clone $base)->sum('balance_due') ?? 0.00;
        $paid_invoices       = (clone $base)->where('balance_due', '<=', 0)->count();
        $pending_invoices    = (clone $base)->where('balance_due', '>', 0)->count();
        $today_invoices      = (clone $base)->whereDate('created_at', today())->count();

        return [
            'total_invoices'      => $total_invoices,
            'outstanding_balance' => (float) $outstanding_balance,
            'paid_invoices'       => $paid_invoices,
            'pending_invoices'    => $pending_invoices,
            'today_invoices'      => $today_invoices,
        ];
    }

    public function stockAvailability(){
        // AR Invoices don't directly relate to stock, return empty data
        return [
            'total_kg_left' => 0,
            'five_kg_sacks_left' => 0,
            'ten_kg_sacks_left' => 0,
            'twenty_five_kg_sacks_left' => 0,
            'products' => []
        ];
    }

    /**
     * What is actually owed on an invoice right now, read from the database.
     *
     * The payment screen shows and validates against this rather than the list
     * row it was opened from: the row is a snapshot, and a payment recorded
     * anywhere else leaves it wrong.
     */
    public function currentBalance($id): array
    {
        $invoice = ArInvoice::with('status')->findOrFail($id);

        return [
            'id'            => $invoice->id,
            'amount_due'    => round((float) $invoice->amount_due, 2),
            'amount_paid'   => round((float) $invoice->amount_paid, 2),
            'balance_due'   => round((float) $invoice->balance_due, 2),
            'status'        => optional($invoice->status)->name,
            'is_settled'    => round((float) $invoice->balance_due, 2) <= 0,
        ];
    }

    public function payment($request, $id = null){
        $ar_invoice = ArInvoice::findOrFail($request->id);

        // A payment is either one {payment_mode, amount_paid} pair, or a 'splits'
        // array of them (e.g. part Cash, part GCash) applied together in one go.
        $splits = collect($request->splits ?? [])
            ->map(fn ($s) => [
                'payment_mode' => (string) ($s['payment_mode'] ?? ''),
                'amount' => round((float) ($s['amount'] ?? 0), 2),
                'bank_account_id' => $s['bank_account_id'] ?? null,
                'reference_number' => $s['reference_number'] ?? null,
            ])
            ->filter(fn ($s) => $s['amount'] > 0)
            ->values();

        if ($splits->isEmpty()) {
            $splits = collect([[
                'payment_mode' => (string) $request->payment_mode,
                'amount' => round((float) $request->amount_paid, 2),
                'bank_account_id' => $request->bank_account_id,
                'reference_number' => $request->reference_number,
            ]]);
        }

        // A transfer or check without its reference can't be matched against the
        // bank statement later, so it isn't accepted without one.
        $missingReference = $splits->first(fn ($s) => in_array($s['payment_mode'], ['Bank Transfer', 'Check'], true)
            && blank($s['reference_number']));

        if ($missingReference) {
            throw ValidationException::withMessages([
                'splits' => $missingReference['payment_mode'] === 'Check'
                    ? 'Enter the check number for the check payment.'
                    : 'Enter the reference number for the bank transfer.',
            ]);
        }

        $totalPayment = round((float) $splits->sum('amount'), 2);

        // Server-authoritative overpayment guard (mirrors ReceiptClass::save):
        // validate the payment against the invoice's actual DB balance, not any
        // client-supplied value.
        if ($totalPayment <= 0) {
            throw ValidationException::withMessages(['amount_paid' => 'Payment amount must be greater than zero.']);
        }
        if ($totalPayment > (float) $ar_invoice->balance_due) {
            throw ValidationException::withMessages([
                'amount_paid' => 'Payment of ₱' . number_format($totalPayment, 2) . ' exceeds the outstanding balance of ₱' . number_format((float) $ar_invoice->balance_due, 2) . '.',
            ]);
        }

        // A check isn't real money until someone confirms it cleared (see
        // confirmCheck()) — only non-check splits are recognized against the
        // invoice balance immediately. Cash and Bank Transfer are unaffected.
        $immediateTotal = round((float) $splits->reject(fn ($s) => $s['payment_mode'] === 'Check')->sum('amount'), 2);

        if ($immediateTotal > 0) {
            $this->applyPaymentToInvoice($ar_invoice, $immediateTotal);
        }

        $pendingStatusId = ListStatus::getBySlug('pending')?->id;
        $lastReceipt = null;
        $receiptIds = [];

        foreach ($splits as $split) {
            $isCheck = $split['payment_mode'] === 'Check';

            $receipt = Receipt::create([
                'receipt_number' => Receipt::generateReceiptNumber(),
                'receipt_type'   => 'payment',
                'receipt_date'   => $request->payment_date,
                'amount_paid'    => $split['amount'],
                // The invoice's resulting balance is the same for every receipt in
                // this batch — they were all applied together, not sequentially.
                // A check's own amount is deliberately excluded until confirmed.
                'balance_due'    => $ar_invoice->balance_due,
                'payment_mode'   => $split['payment_mode'],
                'bank_account_id' => $split['bank_account_id'],
                'reference_number' => $split['reference_number'],
                'status_id'      => $pendingStatusId,
                'customer_id'    => optional($ar_invoice->sales_order)->customer_id,
                'ar_invoice_id'  => $ar_invoice->id,
                'check_status'   => $isCheck ? 'on_hand' : null,
            ]);

            $this->journalEntryService->recordReceiptEntry($receipt);
            $receiptIds[] = $receipt->id;
            $lastReceipt = $receipt;
        }

        return [
            'data' => new ArInvoiceResource($ar_invoice),
            'receipt_id' => $lastReceipt?->id,
            'receipt_ids' => $receiptIds,
            'message' => 'Payment saved successfully!',
            'info' => "Payment successfully saved"
        ];
    }

    /**
     * Recognizes `$amount` against an invoice: reduces balance_due, moves
     * amount_paid, and re-derives the invoice/sales-order status — settling
     * the order and awarding the incentive if this closes the balance.
     * Shared by payment() (for non-check splits, applied immediately) and
     * confirmCheck() (for a check split, applied once confirmed).
     */
    private function applyPaymentToInvoice(ArInvoice $ar_invoice, float $amount): void
    {
        $ar_invoice->amount_paid = $ar_invoice->amount_paid + $amount;
        $ar_invoice->balance_due = $ar_invoice->balance_due - $amount;

        $sales_order = SalesOrder::findOrFail($ar_invoice->sales_order_id);

        if ($ar_invoice->balance_due <= 0) {
            $ar_invoice->status_id = ListStatus::getBySlug('paid')->id;
            $sales_order->update([
                'status_id' => ListStatus::getBySlug('closed')->id,
            ]);

            $existingIncentive = SalesOrderIncentive::where('sales_order_id', $sales_order->id)->first();
            if (!$existingIncentive) {
                $sold_quantity    = $sales_order->items->sum('quantity');
                $product_total_kg = $sales_order->items->sum(fn($item) => ($item->product->weight ?? 0) * $item->quantity);

                SalesOrderIncentive::create([
                    'sales_order_id'   => $sales_order->id,
                    'employee_id'      => $sales_order->sales_rep_id,
                    'sold_quantity'    => $sold_quantity,
                    'product_total_kg' => $product_total_kg,
                    'amount'           => $product_total_kg / 25,
                    'payroll_id'       => null,
                ]);
            }
        } else {
            $ar_invoice->status_id = ListStatus::getBySlug('partially-paid')->id;
            $sales_order->update([
                'status_id' => ListStatus::getBySlug('partially-paid')->id,
            ]);
        }
        $ar_invoice->save();
    }

    /**
     * The manual bank-confirmation step for a check receipt (punch-list #11).
     * This is what releases the check's amount into the invoice's balance
     * (punch-list #12) — before this, the check sits "on hand" and the
     * customer's balance still reflects it as unpaid.
     */
    public function confirmCheck($receiptId, $bankName, $checkDate = null)
    {
        $receipt = Receipt::with('arInvoice.sales_order')->findOrFail($receiptId);

        if (strcasecmp((string) $receipt->payment_mode, 'Check') !== 0) {
            throw ValidationException::withMessages([
                'payment_mode' => 'Only check receipts can be confirmed this way.',
            ]);
        }

        if ($receipt->confirmed_at) {
            throw ValidationException::withMessages([
                'confirmed_at' => 'This check has already been confirmed.',
            ]);
        }

        if (blank($bankName)) {
            throw ValidationException::withMessages([
                'bank_name' => 'Enter the bank name to confirm this check.',
            ]);
        }

        $ar_invoice = $receipt->arInvoice;
        if (!$ar_invoice) {
            throw ValidationException::withMessages([
                'receipt' => 'This receipt is not linked to an AR invoice.',
            ]);
        }

        $this->applyPaymentToInvoice($ar_invoice, (float) $receipt->amount_paid);

        $receipt->update([
            'bank_name' => $bankName,
            'confirmed_at' => now(),
            'confirmed_by_id' => auth()->id(),
            'check_date' => $checkDate ?: $receipt->check_date,
            'balance_due' => $ar_invoice->fresh()->balance_due,
        ]);

        return [
            'data' => new ArInvoiceResource($ar_invoice->fresh()),
            'message' => 'Check confirmed and payment applied to the invoice balance.',
            'info' => "Check from {$bankName} confirmed successfully.",
        ];
    }

    public function print($request){
        $arInvoice = ArInvoice::with(['sales_order.customer', 'sales_order.items.product', 'status'])->find($request->id);
        return $this->print->generate($arInvoice, 'ar_invoice');
    }
}
