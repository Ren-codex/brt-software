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
        $location_id = $request->location_id ?? $mainOffice->id;

        $data = RemittanceResource::collection(
            Remittance::whereHas('receipts.arInvoice.sales_order', function ($q) use ($request, $location_id) {
                $q->where(function ($subQ) use ($location_id) {
                    $subQ->where('location_id', $location_id)
                            ->orWhereNull('location_id');
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
        try{
            DB::beginTransaction();

            $remittanceType = strtolower((string) $request->input('remittance_type', 'cash'));
            $receiptIds = collect($request->receipts ?? [])->unique()->values();
            $pendingStatusId = ListStatus::getBySlug('pending')->id;

            $receipts = Receipt::with('arInvoice.sales_order')
                ->whereIn('id', $receiptIds)
                ->get();

            if ($receipts->count() !== $receiptIds->count()) {
                throw ValidationException::withMessages([
                    'receipts' => 'Some selected receipts are invalid.',
                ]);
            }

            $hasInvalidType = $receipts->contains(function ($receipt) use ($remittanceType) {
                $mode = strtolower(trim((string) optional(optional($receipt->arInvoice)->sales_order)->payment_mode));
                $isCredit = $this->isCreditSalesMode($mode);
                return $remittanceType === 'credit' ? !$isCredit : $isCredit;
            });

            if ($hasInvalidType) {
                throw ValidationException::withMessages([
                    'receipts' => "Selected receipts must all match {$remittanceType} sales.",
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
                'remittance_no' =>  $this->series_service->get('remittance'),
                'remittance_date' =>  Carbon::now(),
                'summary' =>  $request->summary,
                'total_amount' =>  $request->total_amount,
                'status_id' =>  ListStatus::getBySlug('open')->id,
                'created_by_id' =>  Auth::user()->id,
            ]);

            foreach ($receiptIds as $receiptId) {
                Receipt::where('id', $receiptId)->update([
                    'status_id' => ListStatus::getBySlug('open')->id,
                    'remittance_id' => $data->id,
                ]);
            }

            DB::commit();

            return [
                'data' => new RemittanceResource($data),
                'message' => 'Remittance saved was successful!',
                'info' => "You've successfully saved the remittance"
            ];
        } catch (ValidationException $e){
            DB::rollBack();
            throw $e;
        } catch (\Exception $e){
            DB::rollBack();
            return [
                'data' => null,
                'message' => 'Remittance save failed!',
                'info' => $e->getMessage()
            ];
        }
    }

    public function delete($id)
    {
        try {
            db::beginTransaction();

            $receipts = Receipt::where('remittance_id', $id)->get();
            foreach ($receipts as $receipt) {
                $receipt->status_id = ListStatus::getBySlug('pending')->id;
                $receipt->remittance_id = null;
                $receipt->update();
            }

            $data = Remittance::findOrFail($id);
            $data->delete();
            
            db::commit();

            return [
                'data' => $data,
                'message' => 'Remittance deleted was successful!',
                'info' => "You've successfully deleted the remittance"
            ];
        } catch (\Exception $e){
            return [
                'data' => null,
                'message' => 'Remittance delete failed!',
                'info' => $e->getMessage()
            ];
        }
    }

    public function approve($request, $id)
    {
        try{
            db::beginTransaction();

            $data = Remittance::findOrFail($id);
            $data->status_id = ($request->status == 'Approve') ? ListStatus::getBySlug('liquidated')->id : ListStatus::getBySlug('disapproved')->id;
            $data->approved_by_id = Auth::user()->id;
            $data->approved_at = Carbon::now();
            $data->remarks = $request->remarks;
            $data->save();
    
            $receipts = Receipt::where('remittance_id', $data->id)->get();
            foreach ($receipts as $receipt) {
                $receipt->status_id = $request->status == 'Approve' ? ListStatus::getBySlug('liquidated')->id : ListStatus::getBySlug('pending')->id;
                $receipt->update();
            }
    
            db::commit();
            return [
                'data' => new RemittanceResource($data),
                'message' => 'Remittance approval was successful!',
                'info' => "You've successfully approved the remittance"
            ];
        } catch (\Exception $e){
            return [
                'data' => null,
                'message' => 'Remittance approval failed!',
                'info' => $e->getMessage()
            ];
        }
    } 

    private function isCreditSalesMode(string $paymentMode): bool
    {
        return in_array(strtolower(trim($paymentMode)), ['credit', 'credit sales'], true);
    }
}
