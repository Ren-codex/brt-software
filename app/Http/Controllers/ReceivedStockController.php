<?php

namespace App\Http\Controllers;

use App\Models\ReceivedStock;
use App\Http\Requests\StoreReceivedStockRequest;
use App\Http\Resources\ReceivedStockResource;
use App\Services\ReceivedStockService;
use Illuminate\Http\Request;

class ReceivedStockController extends Controller
{
    protected $receivedStockService;

    public function __construct(ReceivedStockService $receivedStockService)
    {
        $this->receivedStockService = $receivedStockService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $receivedStocks = $this->receivedStockService->getAll();
        return ReceivedStockResource::collection($receivedStocks);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreReceivedStockRequest $request)
    {
        $receivedStock = $this->receivedStockService->create($request->validated());
        return new ReceivedStockResource($receivedStock);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $receivedStock = $this->receivedStockService->getById($id);
        return new ReceivedStockResource($receivedStock);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $receivedStock = $this->receivedStockService->update($id, $request->all());
        return new ReceivedStockResource($receivedStock);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $this->receivedStockService->delete($id);
        return response()->json(['message' => 'Received stock deleted successfully']);
    }

    public function getNextBatchCode()
    {
        $batchCode = $this->receivedStockService->getNextBatchCode();
        return response()->json(['batch_code' => $batchCode]);
    }
}
