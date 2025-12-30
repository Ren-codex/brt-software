<?php

namespace App\Services\Modules;


use App\Models\Remittance;
use App\Models\Receipt;
use App\Http\Resources\Libraries\RemittanceResource;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Services\SeriesService;
use Illuminate\Support\Facades\DB;

class RemittanceClass
{
    protected $series_service;

    public function __construct(
        SeriesService $series_service,
    ) { 
        $this->series_service = $series_service;
    }

    public function lists($request){
        $data = RemittanceResource::collection(
            Remittance::when($request->keyword, function ($query,$keyword) {
                    $query->where('remittance_no', 'LIKE', "%{$keyword}%");
                })
                ->orderBy('created_at', 'DESC')
                ->paginate($request->count)
        );
        return $data;
    }

    public function save($request)
    {
        try{
            db::beginTransaction();

            $data = Remittance::create([
                'remittance_no' =>  $this->series_service->get('remittance'),
                'remittance_date' =>  Carbon::now(),
                'summary' =>  $request->summary,
                'total_amount' =>  $request->total_amount,
                'status_id' =>  1,
                'created_by_id' =>  Auth::user()->id,
            ]);

            if ($request->has('receipts') && is_array($request->receipts)) {
                foreach ($request->receipts as $receiptId) {
                    Receipt::where('id', $receiptId)->update([
                        'status_id' => 8,
                        'remittance_id' => $data->id,
                    ]);
                }
            }

            db::commit();

            return [
                'data' => new RemittanceResource($data),
                'message' => 'Remittance saved was successful!',
                'info' => "You've successfully saved the remittance"
            ];
        } catch (\Exception $e){
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
                $receipt->status_id = 1;
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
            $data->status_id = ($request->status == 'Approve') ? 7 : 6;
            $data->approved_by_id = Auth::user()->id;
            $data->approved_at = Carbon::now();
            $data->remarks = $request->remarks;
            $data->save();
    
            if($request->status == 'Approve'){
                $receipts = Receipt::where('remittance_id', $data->id)->get();
                foreach ($receipts as $receipt) {
                    $receipt->status_id = 9;
                    $receipt->update();
                }
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
}