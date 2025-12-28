<?php

namespace App\Services\Modules;


use App\Models\Remittance;
use App\Http\Resources\Libraries\RemittanceResource;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Services\SeriesService;

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

    public function save($request){

        $data = Remittance::create([
            'remittance_no' =>  $this->series_service->get('remittance'),
            'remittance_date' =>  Carbon::now(),
            'summary' =>  $request->summary,
            'total_amount' =>  $request->total_amount,
            'status_id' =>  1,
            'created_by_id' =>  Auth::user()->id,
        ]);

        return [
            'data' => new RemittanceResource($data),
            'message' => 'Remittance saved was successful!', 
            'info' => "You've successfully saved the remittance"
        ];
    }

    public function delete($id){
        $data = Remittance::findOrFail($id);
        $data->delete();

        return [
            'data' => $data,
            'message' => 'Remittance deleted was successful!',
            'info' => "You've successfully deleted the remittance"
        ];
    }
}