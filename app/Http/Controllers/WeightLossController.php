<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWeightLossRequest;
use App\Services\Modules\WeightLossService;
use App\Traits\HandlesTransaction;

class WeightLossController extends Controller
{
    use HandlesTransaction;

    public function __construct(private WeightLossService $service) {}

    public function store(StoreWeightLossRequest $request)
    {
        $result = $this->handleTransaction(fn () => $this->service->store($request));

        return back()->with([
            'data'    => $result['data'],
            'message' => $result['message'],
            'info'    => $result['info'],
            'status'  => $result['status'],
        ]);
    }
}
