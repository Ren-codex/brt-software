<?php

namespace App\Services\Modules;

use App\Models\InventoryStocks;
use App\Models\InventoryWeightLoss;
use App\Services\Accounting\JournalEntryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class WeightLossService
{
    public function __construct(private JournalEntryService $journalEntryService) {}

    public function store($request): array
    {
        $stock = InventoryStocks::with(['receivedItem.product', 'product'])->findOrFail($request->inventory_stock_id);

        $unitCost      = (float) optional($stock->receivedItem)->unit_cost;
        $productWeight = (float) ($stock->product?->weight ?? $stock->receivedItem?->product?->weight ?? 0);

        if ($unitCost <= 0) {
            throw ValidationException::withMessages([
                'items' => 'Cannot record loss: this stock has no unit cost set. Please update the stock price first.',
            ]);
        }

        if ($productWeight <= 0) {
            throw ValidationException::withMessages([
                'items' => 'Cannot record loss: the product has no weight (kg) set. Please update the product first.',
            ]);
        }

        $losses    = [];
        $totalLoss = 0;
        $totalSacks = 0;

        foreach ($request->items as $item) {
            $lossKg = round($item['affected_sacks'] * $item['loss_per_sack'], 2);

            $losses[] = InventoryWeightLoss::create([
                'inventory_stock_id' => $request->inventory_stock_id,
                'affected_sacks'     => $item['affected_sacks'],
                'loss_per_sack'      => $item['loss_per_sack'],
                'loss_kg'            => $lossKg,
                'reason'             => $request->reason,
                'notes'              => $request->notes,
                'recorded_by_id'     => Auth::id(),
                'recorded_at'        => $request->recorded_at,
            ]);

            $totalLoss  += $lossKg;
            $totalSacks += $item['affected_sacks'];
        }

        $this->journalEntryService->recordWeightLossEntry($losses[0]->fresh(), round($totalLoss, 2));

        return [
            'data'    => $losses,
            'message' => 'Weight loss recorded successfully.',
            'info'    => number_format($totalLoss, 2) . ' kg total loss across ' . $totalSacks . ' sacks recorded.',
            'status'  => true,
        ];
    }
}
