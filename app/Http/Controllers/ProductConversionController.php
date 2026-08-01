<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductConversionRequest;
use App\Services\Modules\ProductConversionService;
use App\Traits\HandlesTransaction;

class ProductConversionController extends Controller
{
    use HandlesTransaction;

    public function __construct(private ProductConversionService $service) {}

    public function store(StoreProductConversionRequest $request)
    {
        $result = $this->handleTransaction(fn () => $this->service->store($request));

        return back()->with([
            'data'    => $result['data'],
            'message' => $result['message'],
            'info'    => $result['info'],
            'status'  => $result['status'],
            'errors'  => $result['errors'],
        ]);
    }
}
