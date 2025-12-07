<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryAdjustmentRequest;
use App\Http\Resources\InventoryAdjustmentResource;
use App\Services\Modules\InventoryAdjustmentClass as InventoryAdjustmentService;
use Illuminate\Http\Request;
use App\Traits\HandlesTransaction;

class InventoryAdjustmentController extends Controller
{
    use HandlesTransaction;

    protected $service;

    public function __construct(InventoryAdjustmentService $service)
    {
        $this->service = $service;
    }

    public function store(InventoryAdjustmentRequest $request){
        $result = $this->handleTransaction(function () use ($request) {
            return $this->service->save($request);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'] ?? true,
        ]);
    }
}
