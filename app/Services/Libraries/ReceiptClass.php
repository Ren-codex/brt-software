<?php

namespace App\Services\Libraries;


use App\Models\Receipt;
use App\Models\ArInvoice;
use App\Models\BankDeposit;
use App\Models\Check;
use App\Models\SalesOrder;
use App\Models\ListStatus;
use App\Models\SalesOrderIncentive;
use App\Http\Resources\Libraries\ReceiptResource;
use App\Services\Accounting\JournalEntryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\System\Permission\PermissionService;


class ReceiptClass
{
    public function __construct(protected JournalEntryService $journalEntryService)
    {
    }

    public function lists($request){
        $user = Auth::user();
        // null = a sales administrator, unrestricted. Any other value filters,
        // and -1 matches nothing: a user with no employee record sees no one's
        // sales rather than everyone's. See PermissionService::salesScopeEmployeeId.
        $employeeId = app(PermissionService::class)->salesScopeEmployeeId($user);

        return ReceiptResource::collection(
            Receipt::with(['arInvoice.sales_order.customer', 'arInvoice.sales_order.salesRep', 'heldBy', 'status', 'sourceReceipt'])
                ->where(function ($query) {
                    $query->whereNull('receipt_type')
                        ->orWhere('receipt_type', '!=', 'refund');
                })
                ->when($employeeId, function ($query) use ($employeeId) {
                    $query->whereHas('arInvoice.sales_order', function ($soQuery) use ($employeeId) {
                        $soQuery->where(function ($salesOrderQuery) use ($employeeId) {
                            $salesOrderQuery
                                ->where('added_by_id', Auth::id() ?? -1)
                                ->orWhere('sales_rep_id', $employeeId);
                        });
                    });
                })
                ->when($request->location_id, function ($query, $locationId) {
                    $query->whereHas('arInvoice.sales_order', function ($q) use ($locationId) {
                        $q->where('location_id', $locationId);
                    });
                })
                ->when($request->keyword, function($query) use ($request) {
                    $query->whereHas('arInvoice.sales_order.customer', function($q) use ($request) {
                        $q->where('name', 'like', '%' . $request->keyword . '%');
                    })->orWhere('receipt_number', 'like', '%' . $request->keyword . '%');
                })
                ->when($request->status, function ($query, $status) {
                    $query->whereHas('status', function ($q) use ($status) {
                        $q->where('slug', $status);
                    });
                    // Receipts already inside a remittance must never appear in the pending picker
                    if ($status === 'pending') {
                        $query->whereNull('remittance_id');
                    }
                })
                // Scope to the logged-in user's own receipts (used by Prepare Remittance modal)
                ->when($request->scope_to_rep, function ($query) {
                    $employee = Auth::user()?->employee;
                    if ($employee) {
                        $query->whereHas('arInvoice.sales_order', function ($q) use ($employee) {
                            $q->where('sales_rep_id', $employee->id);
                        });
                    }
                })
                ->orderBy('created_at', 'desc')
                ->sortFromRequest()
                ->paginate($request->count ?? 10)
        );
    }

    public function dashboard(){
        $user = Auth::user();
        // null = a sales administrator, unrestricted. Any other value filters,
        // and -1 matches nothing: a user with no employee record sees no one's
        // sales rather than everyone's. See PermissionService::salesScopeEmployeeId.
        $employeeId = app(PermissionService::class)->salesScopeEmployeeId($user);
        // A payment that was cancelled or undone is not a payment, and has no
        // business in a figure headed "collections".
        $excluded = ListStatus::whereIn('slug', ['cancelled', 'voided'])->pluck('id')->all() ?: [0];

        $base = Receipt::whereNotIn('status_id', $excluded)
            ->when($employeeId, function ($query) use ($employeeId) {
                $query->whereHas('arInvoice.sales_order', function ($soQuery) use ($employeeId) {
                    $soQuery->where(function ($salesOrderQuery) use ($employeeId) {
                        $salesOrderQuery
                            ->where('added_by_id', Auth::id() ?? -1)
                            ->orWhere('sales_rep_id', $employeeId);
                    });
                });
            });

        return [
            'total_receipts' => (clone $base)
                ->where(function ($q) {
                    $q->whereNull('receipt_type')->orWhere('receipt_type', '!=', 'refund');
                })->count(),
            'total_amount_collected' => (clone $base)
                ->where(function ($q) {
                    $q->whereNull('receipt_type')->orWhereNotIn('receipt_type', ['refund', 'updated']);
                })->sum('amount_paid'),
        ];
    }

    // Bug 19 fix: wrap in DB::transaction so all writes are atomic
    public function save($request){
        return DB::transaction(function () use ($request) {
            $arInvoice = ArInvoice::with('sales_order')->findOrFail($request->ar_invoice_id);

            if ($request->amount_paid <= 0) {
                throw ValidationException::withMessages(['amount_paid' => 'Payment amount must be greater than zero.']);
            }

            if ($request->amount_paid > $arInvoice->balance_due) {
                throw ValidationException::withMessages(['amount_paid' => 'Payment of ₱' . number_format($request->amount_paid, 2) . ' exceeds the remaining balance of ₱' . number_format($arInvoice->balance_due, 2) . '.']);
            }

            // Compute updated balance before creating the receipt so we can store it
            $newBalanceDue = $arInvoice->balance_due - $request->amount_paid;

            // Bug 4 fix: include balance_due on the receipt record
            $receipt = Receipt::create([
                'ar_invoice_id'  => $request->ar_invoice_id,
                'status_id'      => ListStatus::getBySlug('pending')?->id,
                'receipt_number' => Receipt::generateReceiptNumber(),
                'receipt_type'   => 'payment',
                'receipt_date'   => $request->receipt_date,
                'amount_paid'    => $request->amount_paid,
                'balance_due'    => max(0, $newBalanceDue),
                'payment_mode'   => $request->payment_mode,
                'customer_id'    => optional($arInvoice->sales_order)->customer_id,
            ]);

            $arInvoice->amount_paid += $request->amount_paid;
            $arInvoice->balance_due  = $newBalanceDue;

            if ($arInvoice->balance_due <= 0) {
                $arInvoice->status_id = ListStatus::getBySlug('paid')?->id;
            } elseif ($arInvoice->amount_paid > 0) {
                $arInvoice->status_id = ListStatus::getBySlug('partially-paid')?->id;
            }

            $arInvoice->save();

            $salesOrder = $arInvoice->sales_order;
            if ($salesOrder) {
                $salesOrder->loadMissing('status');
                $currentSlug       = $salesOrder->status?->slug ?? '';
                $isFullyReturned   = ($currentSlug === 'sales-returned');
                $isPartialReturned = ($currentSlug === 'partially-returned');

                if ($arInvoice->balance_due <= 0) {
                    if (!$isFullyReturned) {
                        $salesOrder->stampCounterDelivery();
                        if ($settledStatusId = $salesOrder->settledStatusId()) {
                            $salesOrder->update(['status_id' => $settledStatusId]);
                        }

                        if ($salesOrder->sales_rep_id && !SalesOrderIncentive::where('sales_order_id', $salesOrder->id)->exists()) {
                            $sold_quantity    = $salesOrder->items->sum('quantity');
                            $product_total_kg = $salesOrder->items->sum(fn($item) => ($item->product->weight ?? 0) * $item->quantity);

                            SalesOrderIncentive::create([
                                'sales_order_id'   => $salesOrder->id,
                                'employee_id'      => $salesOrder->sales_rep_id,
                                'sold_quantity'    => $sold_quantity,
                                'product_total_kg' => $product_total_kg,
                                'amount'           => $product_total_kg / 25,
                                'payroll_id'       => null,
                            ]);
                        }
                    }
                } elseif (!$isFullyReturned && !$isPartialReturned) {
                    $salesOrder->update(['status_id' => ListStatus::getBySlug('partially-paid')?->id]);
                }
            }

            $this->journalEntryService->recordReceiptEntry($receipt);

            return [
                'data'    => $receipt,
                'message' => 'Receipt saved successfully!',
                'info'    => "You've successfully saved the receipt",
            ];
        });
    }

    public function update($request){
        $receipt = Receipt::findOrFail($request->id);
        $oldAmount = $receipt->amount_paid;

        // Overpayment guard: the new amount can't exceed the invoice's current
        // balance plus this receipt's own old amount (which is being replaced).
        $guardInvoice = ArInvoice::find($receipt->ar_invoice_id);
        if ($guardInvoice) {
            $newAmount = round((float) $request->amount_paid, 2);
            $available = round((float) $guardInvoice->balance_due + (float) $oldAmount, 2);
            if ($newAmount <= 0) {
                throw ValidationException::withMessages(['amount_paid' => 'Payment amount must be greater than zero.']);
            }
            if ($newAmount > $available) {
                throw ValidationException::withMessages([
                    'amount_paid' => 'Payment of ₱' . number_format($newAmount, 2) . ' exceeds the invoice balance of ₱' . number_format($available, 2) . '.',
                ]);
            }
        }

        $this->journalEntryService->reverseEntriesForSource($receipt, 'Receipt updated. Previous receipt entry reversed.', $request->receipt_date);

        $receipt->update($request->only([
            'ar_invoice_id', 'receipt_date', 'amount_paid', 'payment_mode'
        ]));

        $arInvoice = ArInvoice::with('sales_order')->find($receipt->ar_invoice_id);
        $arInvoice->amount_paid = $arInvoice->amount_paid - $oldAmount + $request->amount_paid;
        $arInvoice->balance_due = $arInvoice->balance_due + $oldAmount - $request->amount_paid;

        if ($arInvoice->balance_due <= 0) {
            $arInvoice->status_id = ListStatus::getBySlug('paid')?->id;
        } elseif ($arInvoice->amount_paid > 0) {
            $arInvoice->status_id = ListStatus::getBySlug('partially-paid')?->id;
        } else {
            $arInvoice->status_id = ListStatus::getBySlug('unpaid')?->id;
        }

        $arInvoice->save();

        $receipt->update(['balance_due' => max(0, $arInvoice->balance_due)]);

        $salesOrder = $arInvoice->sales_order;
        if ($salesOrder) {
            $salesOrder->loadMissing('status');
            $currentSlug       = $salesOrder->status?->slug ?? '';
            $isFullyReturned   = ($currentSlug === 'sales-returned');
            $isPartialReturned = ($currentSlug === 'partially-returned');

            if ($arInvoice->balance_due <= 0) {
                if (!$isFullyReturned) {
                    $salesOrder->stampCounterDelivery();
                    if ($settledStatusId = $salesOrder->settledStatusId()) {
                        $salesOrder->update(['status_id' => $settledStatusId]);
                    }
                }
            } elseif (!$isFullyReturned && !$isPartialReturned) {
                if ($arInvoice->amount_paid > 0) {
                    $salesOrder->update(['status_id' => ListStatus::getBySlug('partially-paid')?->id]);
                } else {
                    $salesOrder->update(['status_id' => ListStatus::getBySlug('for-payment')?->id]);
                }
            }
        }

        $this->journalEntryService->recordReceiptEntry($receipt->fresh());

        return [
            'data'    => $receipt,
            'message' => 'Receipt updated successfully!',
            'info'    => "You've successfully updated the receipt",
        ];
    }

    /**
     * Undo a payment that should never have been recorded as it was.
     *
     * The mode is not a label: it decides who is holding the money, whether
     * the bank has to confirm it, and whether a cheque appears in the
     * register. Editing it changed the word and none of the consequences, so
     * the way back is to undo the whole receipt and record it again.
     *
     * The receipt is kept, marked voided, with the reason and the name of
     * whoever did it — an auditor asking why a payment changed mode gets an
     * answer instead of a rewritten history.
     */
    public function void($id, ?string $reason)
    {
        $receipt = Receipt::with(['status', 'arInvoice.sales_order', 'remittance', 'registerCheck'])->findOrFail($id);
        $reason = trim((string) $reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Say why this is being voided — it stays on the record.',
            ]);
        }

        if (optional($receipt->status)->slug === 'voided') {
            throw ValidationException::withMessages([
                'receipt' => 'This receipt has already been voided.',
            ]);
        }

        // Money already counted into a remittance cannot quietly leave it:
        // the total was verified, and possibly banked, against this receipt.
        if ($receipt->remittance_id) {
            throw ValidationException::withMessages([
                'receipt' => 'This receipt is part of remittance '
                    .(optional($receipt->remittance)->remittance_no ?? '#'.$receipt->remittance_id)
                    .'. Remove it from the remittance first, then void it.',
            ]);
        }

        $check = $receipt->registerCheck;

        if ($check && $check->status === Check::STATUS_CLEARED) {
            throw ValidationException::withMessages([
                'receipt' => 'The bank has already paid this cheque. Reverse the clearing before voiding the receipt.',
            ]);
        }

        if ($check && BankDeposit::where('check_id', $check->id)->exists()) {
            throw ValidationException::withMessages([
                'receipt' => 'This cheque is on a bank deposit. Delete the deposit first, then void the receipt.',
            ]);
        }

        return DB::transaction(function () use ($receipt, $reason, $check) {
            $this->journalEntryService->reverseEntriesForSource(
                $receipt, 'Receipt voided: '.$reason, now()->toDateString()
            );

            // Only money the invoice actually counted comes back off it. An
            // uncleared cheque never reduced the balance, so crediting it here
            // would hand the customer a payment they never made.
            $orderMode = optional(optional($receipt->arInvoice)->sales_order)->payment_mode;
            $wasApplied = ! Receipt::modeAwaitsBankConfirmation($receipt->payment_mode, $orderMode)
                || ! is_null($receipt->confirmed_at);

            $invoice = $receipt->arInvoice;

            if ($invoice && $wasApplied) {
                $invoice->amount_paid = max(0, round((float) $invoice->amount_paid - (float) $receipt->amount_paid, 2));
                $invoice->balance_due = round((float) $invoice->balance_due + (float) $receipt->amount_paid, 2);
                $invoice->status_id = ListStatus::getBySlug(
                    $invoice->amount_paid > 0 ? 'partially-paid' : 'unpaid'
                )?->id;
                $invoice->save();
            }

            // A cheque typed by mistake was never on hand, so it leaves the
            // register rather than sitting there pending for ever.
            $check?->delete();

            $receipt->update([
                'status_id' => $this->voidedStatusId(),
                // Nobody is carrying money that was never taken.
                'held_by_employee_id' => null,
            ]);

            DB::table('void_receipts')->insert([
                'receipt_id' => $receipt->id,
                'reason' => $reason,
                'voided_by_id' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $salesOrder = optional($invoice)->sales_order;
            if ($salesOrder && ($statusId = $salesOrder->fresh()->settledStatusId())) {
                $salesOrder->update(['status_id' => $statusId]);
            }

            return [
                'data' => $receipt->fresh('status'),
                'message' => 'Receipt voided.',
                'info' => 'Record the payment again with the right details.',
                'status' => true,
            ];
        });
    }

    /**
     * Voided arrived after the original status list, which is a seeder that
     * rebuilds the whole table, so the row is made here on first use — the
     * same approach as SalesOrder::settledStatusId.
     */
    private function voidedStatusId(): ?int
    {
        if (! ListStatus::getBySlug('voided')) {
            ListStatus::create([
                'name' => 'Voided', 'slug' => 'voided',
                'description' => 'Recorded in error and undone',
                'text_color' => '#ffffff', 'bg_color' => '#96231f',
            ]);
        }

        return ListStatus::getBySlug('voided')?->id;
    }

    public function delete($id){
        $receipt = Receipt::with('status')->findOrFail($id);

        // Bug 6 fix: block deletion if receipt is inside a remittance
        if (!is_null($receipt->remittance_id)) {
            throw ValidationException::withMessages([
                'receipt' => 'This receipt belongs to a remittance and cannot be deleted directly. Remove it from the remittance first.',
            ]);
        }

        $this->journalEntryService->reverseEntriesForSource($receipt, 'Receipt deleted. Original collection entry reversed.', now()->toDateString());
        $arInvoice = ArInvoice::with('sales_order')->findOrFail($receipt->ar_invoice_id);

        $arInvoice->amount_paid -= $receipt->amount_paid;
        $arInvoice->balance_due += $receipt->amount_paid;

        if ($arInvoice->amount_paid <= 0) {
            $arInvoice->status_id = ListStatus::getBySlug('unpaid')?->id;
        } else {
            $arInvoice->status_id = ListStatus::getBySlug('partially-paid')?->id;
        }

        $arInvoice->save();

        // Bug 5 fix: sync SO status after reversing the payment
        $salesOrder = $arInvoice->sales_order;
        if ($salesOrder) {
            $salesOrder->loadMissing('status');
            $currentSlug    = $salesOrder->status?->slug ?? '';
            $isReturnStatus = in_array($currentSlug, ['partially-returned', 'sales-returned']);

            if (!$isReturnStatus) {
                if ($arInvoice->amount_paid <= 0) {
                    $salesOrder->update(['status_id' => ListStatus::getBySlug('for-payment')?->id]);
                } else {
                    $salesOrder->update(['status_id' => ListStatus::getBySlug('partially-paid')?->id]);
                }
            }
        }

        $receipt->delete();

        return [
            'data' => null,
            'message' => 'Receipt deleted successfully!',
            'info' => "You've successfully deleted the receipt",
        ];
    }

    public function show($id){
        return Receipt::with(['arInvoice.sales_order.customer', 'arInvoice.sales_order.items.product', 'status'])->findOrFail($id);
    }


}
