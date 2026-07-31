<?php

namespace App\Services\Modules;


use App\Models\Remittance;
use App\Models\Receipt;
use App\Models\ListLocation;
use App\Models\ListStatus;
use App\Http\Resources\Libraries\RemittanceResource;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Services\SeriesService;
use Illuminate\Validation\ValidationException;

class RemittanceClass
{
    /** Sales orders at this location (or with none set) are treated as internal. */
    private const INTERNAL_LOCATION = 'Zamboanga City';

    protected $series_service;

    public function __construct(
        SeriesService $series_service,
    ) {
        $this->series_service = $series_service;
    }

    /**
     * Statuses are resolved by slug rather than hardcoded ids, matching
     * ArInvoiceClass. The previous hardcoded ids pointed at the wrong rows.
     */
    private function statusId(string $slug): int
    {
        $status = ListStatus::getBySlug($slug);

        if (! $status) {
            throw ValidationException::withMessages([
                'status' => "The '{$slug}' status is missing from list_statuses.",
            ]);
        }

        return $status->id;
    }

    public function lists($request)
    {
        $query = Remittance::query()
            ->when($request->keyword, function ($query, $keyword) {
                $query->where('remittance_no', 'LIKE', "%{$keyword}%");
            })
            ->when($request->status, function ($query, $status) {
                $query->whereHas('status', function ($q) use ($status) {
                    $q->where('slug', $status);
                });
            })
            ->when($request->location_id, function ($query, $locationId) {
                $query->whereHas('receipts.arInvoice.sales_order', function ($q) use ($locationId) {
                    $q->where('location_id', $locationId);
                });
            });

        // Internal is expressed as "has no external receipt" rather than
        // "has an internal receipt", so a remittance is never hidden merely
        // because a receipt is not yet linked to a sales order.
        $externalLocationIds = ListLocation::where('name', '!=', self::INTERNAL_LOCATION)->pluck('id');

        if ($request->is_external) {
            $query->whereHas('receipts.arInvoice.sales_order', function ($q) use ($externalLocationIds) {
                $q->whereIn('location_id', $externalLocationIds);
            });
        } else {
            $query->whereDoesntHave('receipts.arInvoice.sales_order', function ($q) use ($externalLocationIds) {
                $q->whereIn('location_id', $externalLocationIds);
            });
        }

        return RemittanceResource::collection(
            $query->orderBy('created_at', 'DESC')
                  ->paginate($request->count ?: 10)
        );
    }

    /**
     * Exceptions are allowed to propagate so the DB::transaction wrapper in
     * HandlesTransaction rolls back. Catching them here previously committed
     * partial writes while reporting failure.
     */
    public function save($request)
    {
        $receiptIds = collect($request->receipts ?? [])
            ->filter()
            ->unique()
            ->values();

        if ($receiptIds->isEmpty()) {
            throw ValidationException::withMessages([
                'receipts' => 'Select at least one receipt to remit.',
            ]);
        }

        // Locked for the duration of the transaction so two remittances cannot
        // claim the same receipt concurrently.
        $receipts = Receipt::whereIn('id', $receiptIds)->lockForUpdate()->get();

        if ($receipts->count() !== $receiptIds->count()) {
            throw ValidationException::withMessages([
                'receipts' => 'One or more of the selected receipts no longer exist.',
            ]);
        }

        $claimed = $receipts->filter(fn ($receipt) => ! is_null($receipt->remittance_id));

        if ($claimed->isNotEmpty()) {
            throw ValidationException::withMessages([
                'receipts' => 'Already remitted: ' . $claimed->pluck('receipt_number')->implode(', '),
            ]);
        }

        // Authoritative total, computed from the receipts rather than trusted
        // from the client payload.
        $totalAmount = $receipts->sum('amount_paid');

        $data = Remittance::create([
            'remittance_no' => $this->series_service->get('remittance'),
            'remittance_date' => Carbon::now(),
            'summary' => $request->summary,
            'total_amount' => $totalAmount,
            'status_id' => $this->statusId('pending'),
            'created_by_id' => Auth::id(),
        ]);

        Receipt::whereIn('id', $receiptIds)->update([
            'status_id' => $this->statusId('open'),
            'remittance_id' => $data->id,
        ]);

        return [
            'data' => new RemittanceResource($data->fresh('receipts')),
            'status' => true,
            'message' => 'Remittance saved was successful!',
            'info' => "You've successfully saved the remittance",
        ];
    }

    public function delete($id)
    {
        $data = Remittance::with('status')->findOrFail($id);

        if ($data->status && $data->status->slug === 'approved') {
            throw ValidationException::withMessages([
                'remittance' => 'An approved remittance cannot be deleted. Reverse it instead.',
            ]);
        }

        // Release the receipts so they can be remitted again.
        Receipt::where('remittance_id', $data->id)->update([
            'status_id' => $this->statusId('pending'),
            'remittance_id' => null,
        ]);

        $data->delete();

        return [
            'data' => $data,
            'status' => true,
            'message' => 'Remittance deleted was successful!',
            'info' => "You've successfully deleted the remittance",
        ];
    }

    public function approve($request, $id)
    {
        $data = Remittance::with('status')->findOrFail($id);

        // A remittance may only be decided once, from the pending state.
        if ($data->status && $data->status->slug !== 'pending') {
            throw ValidationException::withMessages([
                'status' => "This remittance has already been {$data->status->name}.",
            ]);
        }

        $isApproved = $request->status === 'Approve';

        $data->status_id = $isApproved ? $this->statusId('approved') : $this->statusId('disapproved');
        // Only stamp the approver when actually approving; a disapproval is not
        // an approval and must not read as one.
        $data->approved_by_id = $isApproved ? Auth::id() : null;
        $data->approved_at = $isApproved ? Carbon::now() : null;
        $data->remarks = $request->remarks;
        $data->save();

        if ($isApproved) {
            // Approval is what actually settles the cash, so the receipts move
            // on now rather than at submission time.
            Receipt::where('remittance_id', $data->id)->update([
                'status_id' => $this->statusId('liquidated'),
            ]);
        }

        return [
            'data' => new RemittanceResource($data->fresh('receipts')),
            'status' => true,
            'message' => $isApproved ? 'Remittance approval was successful!' : 'Remittance was disapproved.',
            'info' => $isApproved
                ? "You've successfully approved the remittance"
                : "You've disapproved the remittance",
        ];
    }
}
