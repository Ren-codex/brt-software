<?php

namespace App\Services\Modules;

use App\Http\Resources\Modules\CheckMonitoringResource;
use App\Models\Receipt;
use Illuminate\Validation\ValidationException;

/**
 * Punch-list #10: tracks checks on hand — where they are in the custody
 * lifecycle (on_hand → released → matured) and whether they've been
 * bank-confirmed. Confirmation itself (which also gates the AR invoice
 * balance) lives in ArInvoiceClass::confirmCheck() — this class only handles
 * the custody side.
 */
class CheckMonitoringClass
{
    public function lists($request)
    {
        $data = CheckMonitoringResource::collection(
            Receipt::with(['customer', 'confirmedBy', 'arInvoice.sales_order.salesRep'])
                ->where('payment_mode', 'Check')
                ->when($request->check_status, function ($query, $status) {
                    $query->where('check_status', $status);
                })
                ->when($request->keyword, function ($query, $keyword) {
                    $query->where(function ($q) use ($keyword) {
                        $q->where('reference_number', 'LIKE', "%{$keyword}%")
                          ->orWhere('bank_name', 'LIKE', "%{$keyword}%")
                          ->orWhereHas('customer', function ($cq) use ($keyword) {
                              $cq->where('name', 'LIKE', "%{$keyword}%");
                          });
                    });
                })
                ->orderBy('created_at', 'DESC')
                ->paginate($request->count ?: 15)
        );

        return $data;
    }

    public function updateCheckDate($id, $checkDate)
    {
        $receipt = Receipt::findOrFail($id);

        if ($receipt->payment_mode !== 'Check') {
            throw ValidationException::withMessages([
                'payment_mode' => 'Only check receipts have a check date.',
            ]);
        }

        $receipt->update(['check_date' => $checkDate]);

        return [
            'data' => new CheckMonitoringResource($receipt->fresh(['customer', 'confirmedBy'])),
            'message' => 'Check date updated.',
            'info' => 'The check date has been updated.',
        ];
    }

    public function markReleased($id)
    {
        $receipt = Receipt::findOrFail($id);

        if ($receipt->payment_mode !== 'Check') {
            throw ValidationException::withMessages([
                'payment_mode' => 'Only check receipts can be released.',
            ]);
        }

        if ($receipt->check_status !== 'on_hand') {
            throw ValidationException::withMessages([
                'check_status' => 'Only a check that is still on hand can be released.',
            ]);
        }

        $receipt->update([
            'check_status' => 'released',
            'released_at' => now(),
        ]);

        return [
            'data' => new CheckMonitoringResource($receipt->fresh(['customer', 'confirmedBy'])),
            'message' => 'Check marked as released.',
            'info' => 'This check now matures automatically 7 days from today.',
        ];
    }
}
