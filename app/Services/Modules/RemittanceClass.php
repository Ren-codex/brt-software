<?php

namespace App\Services\Modules;


use App\Models\Remittance;
use App\Models\Receipt;
use App\Models\ListLocation;
use App\Http\Resources\Libraries\RemittanceResource;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Services\SeriesService;
use Illuminate\Support\Facades\DB;
use App\Models\ListStatus;
use Illuminate\Validation\ValidationException;

class RemittanceClass
{
    protected $series_service;

    public function __construct(
        SeriesService $series_service,
    ) {
        $this->series_service = $series_service;
    }

    public function lists($request){
        $mainOffice = ListLocation::where('name', 'Zamboanga City')->first();
        $location_id = $request->location_id ?? $mainOffice?->id;

        $data = RemittanceResource::collection(
            Remittance::with(['receipts.arInvoice.sales_order', 'receipts.customer', 'receipts.status', 'status', 'createdBy.employee', 'approvedBy.employee'])
            ->whereHas('receipts', function ($q) use ($location_id) {
                $q->where(function ($inner) use ($location_id) {
                    $inner->whereHas('arInvoice.sales_order', function ($soQ) use ($location_id) {
                        $soQ->where('location_id', $location_id)
                            ->orWhereNull('location_id');
                    })->orWhereDoesntHave('arInvoice');
                });
            })
            ->when($request->keyword, function ($query,$keyword) {
                $query->where('remittance_no', 'LIKE', "%{$keyword}%");
            })
            ->when($request->status, function ($query, $status) {
                $query->whereHas('status', function ($q) use ($status) {
                    $q->where('slug', $status);
                });
            })
            ->orderBy('created_at', 'DESC')
            ->paginate($request->count)
        );
        return $data;
    }

    public function save($request)
    {
        try {
            $receiptIds = collect($request->receipts ?? [])->unique()->values();
            $pendingStatusId = ListStatus::getBySlug('pending')?->id;

            $receipts = Receipt::whereIn('id', $receiptIds)->get();

            if ($receipts->count() !== $receiptIds->count()) {
                throw ValidationException::withMessages([
                    'receipts' => 'Some selected receipts are invalid.',
                ]);
            }

            $hasUnavailableReceipt = $receipts->contains(function ($receipt) use ($pendingStatusId) {
                return (int) $receipt->status_id !== (int) $pendingStatusId || !is_null($receipt->remittance_id);
            });

            if ($hasUnavailableReceipt) {
                throw ValidationException::withMessages([
                    'receipts' => 'One or more selected receipts are no longer pending.',
                ]);
            }

            $data = Remittance::create([
                'remittance_no'   => $this->series_service->get('remittance'),
                'remittance_date' => Carbon::now(),
                'summary'         => $request->summary,
                'total_amount'    => $request->total_amount,
                'status_id'       => ListStatus::getBySlug('open')?->id,
                'created_by_id'   => Auth::user()->id,
            ]);

            foreach ($receiptIds as $receiptId) {
                Receipt::where('id', $receiptId)->update([
                    'status_id'    => ListStatus::getBySlug('open')?->id,
                    'remittance_id' => $data->id,
                ]);
            }

            return [
                'data'    => new RemittanceResource($data),
                'message' => 'Remittance saved was successful!',
                'info'    => "You've successfully saved the remittance",
            ];
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return [
                'data'    => null,
                'message' => 'Remittance save failed!',
                'info'    => $e->getMessage(),
                'status'  => false,
            ];
        }
    }

    public function delete($id)
    {
        try {
            $data = Remittance::with('status')->findOrFail($id);

            if ($data->status?->slug !== 'open') {
                throw new \Exception('Only open remittances can be deleted.');
            }

            $receipts = Receipt::where('remittance_id', $id)->get();
            foreach ($receipts as $receipt) {
                $receipt->status_id   = ListStatus::getBySlug('pending')?->id;
                $receipt->remittance_id = null;
                $receipt->save();
            }

            $data->delete();

            return [
                'data'    => $data,
                'message' => 'Remittance deleted was successful!',
                'info'    => "You've successfully deleted the remittance",
            ];
        } catch (\Exception $e) {
            return [
                'data'    => null,
                'message' => 'Remittance delete failed!',
                'info'    => $e->getMessage(),
                'status'  => false,
            ];
        }
    }

    public function remit($id)
    {
        try {
            $data = Remittance::with('status')->findOrFail($id);

            if (!in_array($data->status?->slug, ['open'])) {
                throw new \Exception('Only open remittances can be marked as remitted.');
            }

            $remittedStatusId = ListStatus::getBySlug('remitted')?->id;
            $data->status_id = $remittedStatusId;
            $data->save();

            Receipt::where('remittance_id', $data->id)->update(['status_id' => $remittedStatusId]);

            return [
                'data'    => new RemittanceResource($data),
                'message' => 'Remittance marked as remitted!',
                'info'    => "The remittance has been submitted for approval.",
            ];
        } catch (\Exception $e) {
            return [
                'data'    => null,
                'message' => 'Failed to mark as remitted.',
                'info'    => $e->getMessage(),
                'status'  => false,
            ];
        }
    }

    public function approve($request, $id)
    {
        try {
            $data = Remittance::with('status')->findOrFail($id);

            if ($data->status?->slug !== 'remitted') {
                throw new \Exception('Only remitted remittances can be approved or disapproved.');
            }

            $isApprove = $request->status === 'Approve';

            $data->status_id      = $isApprove ? ListStatus::getBySlug('liquidated')?->id : ListStatus::getBySlug('disapproved')?->id;
            $data->approved_by_id = Auth::user()->id;
            $data->approved_at    = Carbon::now();
            $data->remarks        = $request->remarks;

            if ($isApprove && $request->received_amount !== null) {
                $data->received_amount = $request->received_amount;
                $data->variance        = round((float) $request->received_amount - (float) $data->total_amount, 2);
            }

            $data->save();

            $receipts = Receipt::where('remittance_id', $data->id)->get();
            foreach ($receipts as $receipt) {
                $receipt->status_id = $isApprove
                    ? ListStatus::getBySlug('liquidated')?->id
                    : ListStatus::getBySlug('pending')?->id;
                if (!$isApprove) {
                    $receipt->remittance_id = null;
                }
                $receipt->save();
            }

            return [
                'data'    => new RemittanceResource($data),
                'message' => 'Remittance approval was successful!',
                'info'    => "You've successfully approved the remittance",
            ];
        } catch (\Exception $e) {
            return [
                'data'    => null,
                'message' => 'Remittance approval failed!',
                'info'    => $e->getMessage(),
                'status'  => false,
            ];
        }
    }

    public function myHoldings()
    {
        $employee = Auth::user()->employee;

        if (!$employee) {
            return response()->json(['total_amount' => 0, 'receipt_count' => 0]);
        }

        $receipts = Receipt::whereNull('remittance_id')
            ->whereHas('arInvoice.sales_order', function ($q) use ($employee) {
                $q->where('sales_rep_id', $employee->id);
            })
            ->selectRaw('COUNT(*) as receipt_count, SUM(amount_paid) as total_amount')
            ->first();

        return response()->json([
            'total_amount'  => (float) ($receipts->total_amount ?? 0),
            'receipt_count' => (int)   ($receipts->receipt_count ?? 0),
        ]);
    }

    public function summary($request)
    {
        $from = $request->from
            ? Carbon::parse($request->from)->startOfDay()
            : Carbon::now()->startOfMonth()->startOfDay();

        $to = $request->to
            ? Carbon::parse($request->to)->endOfDay()
            : Carbon::now()->endOfMonth()->endOfDay();

        $remittances = Remittance::with(['createdBy.employee', 'status'])
            ->whereBetween('remittance_date', [$from, $to])
            ->orderBy('remittance_date', 'DESC')
            ->get();

        $grouped = $remittances
            ->groupBy(fn($r) => optional($r->createdBy)->id ?? 0)
            ->map(function ($repRemittances) {
                $rep = optional($repRemittances->first()->createdBy)->employee;
                $byDate = $repRemittances
                    ->groupBy(fn($r) => Carbon::parse($r->remittance_date)->toDateString())
                    ->map(fn($dayRows) => [
                        'date'            => Carbon::parse($dayRows->first()->remittance_date)->toDateString(),
                        'count'           => $dayRows->count(),
                        'total_amount'    => $dayRows->sum('total_amount'),
                        'received_amount' => $dayRows->whereNotNull('received_amount')->sum('received_amount'),
                        'statuses'        => $dayRows->groupBy(fn($r) => $r->status?->slug ?? 'unknown')
                                                     ->map->count(),
                    ])
                    ->values();

                return [
                    'rep_id'           => optional($repRemittances->first()->createdBy)->id,
                    'rep_name'         => $rep?->fullname ?? 'Unknown',
                    'total_amount'     => $repRemittances->sum('total_amount'),
                    'remittance_count' => $repRemittances->count(),
                    'dates'            => $byDate,
                ];
            })
            ->values();

        return response()->json([
            'data' => $grouped,
            'from' => $from->toDateString(),
            'to'   => $to->toDateString(),
        ]);
    }

}
