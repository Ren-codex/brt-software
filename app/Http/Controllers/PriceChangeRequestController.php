<?php

namespace App\Http\Controllers;

use App\Models\InventoryStocks;
use App\Models\ListStatus;
use App\Models\PriceChangeRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * A price change is requested here and applied only once an approver confirms
 * it (see ApprovalService::decidePriceChange). The product's own prices are
 * left untouched until then, so sales keep using the current price meanwhile.
 */
class PriceChangeRequestController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'inventory_stocks_id' => 'required|integer|exists:inventory_stocks,id',
            'retail_price' => 'required|numeric|min:0',
            'wholesale_price' => 'required|numeric|min:0',
            'reason' => 'nullable|string|max:1000',
        ]);

        if (round((float) $data['wholesale_price'], 2) >= round((float) $data['retail_price'], 2)) {
            throw ValidationException::withMessages([
                'wholesale_price' => 'Wholesale price must be less than the retail price.',
            ]);
        }

        $stock = InventoryStocks::findOrFail($data['inventory_stocks_id']);

        // Nothing to approve when neither price actually moves.
        if (round((float) $data['retail_price'], 2) === round((float) $stock->retail_price, 2)
            && round((float) $data['wholesale_price'], 2) === round((float) $stock->wholesale_price, 2)) {
            throw ValidationException::withMessages([
                'retail_price' => 'These are the current prices — change one before requesting approval.',
            ]);
        }

        $pending = ListStatus::getBySlug('pending');

        PriceChangeRequest::create([
            'inventory_stocks_id' => $stock->id,
            'product_id' => $stock->product_id,
            'retail_price' => $data['retail_price'],
            'wholesale_price' => $data['wholesale_price'],
            'previous_retail_price' => $stock->retail_price,
            'previous_wholesale_price' => $stock->wholesale_price,
            'reason' => $data['reason'] ?? null,
            'status_id' => $pending?->id,
            'requested_by_id' => auth()->id(),
        ]);

        return back()->with([
            'message' => 'Price change submitted for approval.',
            'info' => 'The current price stays until an approver confirms it.',
            'status' => true,
        ]);
    }
}
