<?php

namespace App\Http\Resources;

use App\Http\Resources\Libraries\ProductResource;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryStockResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                    => $this->id,
            'batch_code'            => $this->batch_code,
            'quantity'              => $this->quantity,
            'conversion_id'         => $this->conversion_id,
            'received_item'         => $this->receivedItem ? new ReceivedItemResource($this->receivedItem) : null,
            'product'               => $this->product ? new ProductResource($this->product) : null,
            'conversion'            => $this->whenLoaded('conversion', fn () => $this->formatConversion($this->conversion)),
            'conversions_out'       => $this->whenLoaded('conversionsOut', fn () => $this->conversionsOut->map(fn ($c) => $this->formatConversionOut($c))->values()),
            'created_at'            => $this->created_at,
            'updated_at'            => $this->updated_at,
            'inventory_adjustments' => $this->inventoryAdjustments ? InventoryAdjustmentResource::collection($this->inventoryAdjustments) : [],
            'retail_price'          => $this->retail_price,
            'wholesale_price'       => $this->wholesale_price,
            'unit_cost'             => (float) $this->unit_cost,
            'expiration_date'       => $this->expiration_date,
            'notes'                 => $this->notes,
            'is_archived'           => (bool) $this->is_archived,
            'is_expired'            => (bool) $this->is_expired,
            'weight_losses'         => $this->whenLoaded('weightLosses', fn () => $this->weightLosses->map(fn ($wl) => [
                'id'              => $wl->id,
                'affected_sacks'  => $wl->affected_sacks,
                'loss_per_sack'   => $wl->loss_per_sack,
                'loss_kg'         => $wl->loss_kg,
                'reason'          => $wl->reason,
                'notes'           => $wl->notes,
                'recorded_by'     => $wl->recordedBy?->employee?->fullname ?? $wl->recordedBy?->username,
                'recorded_at'     => $wl->recorded_at,
                'created_at'      => $wl->created_at,
                'converted_at'    => $wl->converted_at,
                'converted_by'    => $wl->convertedBy?->employee?->fullname ?? $wl->convertedBy?->username,
                'conversion_id'   => $wl->conversion_id,
            ])->values()),
            'total_weight_loss'    => $this->whenLoaded('weightLosses', fn () => $this->weightLosses->filter(fn ($wl) => is_null($wl->converted_at))->sum('loss_kg')),
            'total_affected_sacks' => $this->whenLoaded('weightLosses', fn () => $this->weightLosses->filter(fn ($wl) => is_null($wl->converted_at))->sum('affected_sacks')),
        ];
    }

    private function formatConversion($conversion): array
    {
        return [
            'id'               => $conversion->id,
            'conversion_date'  => $conversion->conversion_date,
            'source_qty_used'  => $conversion->source_qty_used,
            'conversion_ratio' => $conversion->conversion_ratio,
            'output_quantity'  => $conversion->output_quantity,
            'reason'           => $conversion->reason,
            'converted_by'     => $conversion->convertedBy?->employee?->fullname ?? $conversion->convertedBy?->username,
            'source_batch'     => $conversion->sourceStock?->batch_code,
            'source_product'   => $conversion->sourceStock?->receivedItem?->product?->name
                                ?? $conversion->sourceStock?->product?->name,
        ];
    }

    private function formatConversionOut($conversion): array
    {
        return [
            'id'               => $conversion->id,
            'conversion_date'  => $conversion->conversion_date,
            'source_qty_used'  => $conversion->source_qty_used,
            'conversion_ratio' => $conversion->conversion_ratio,
            'output_quantity'  => $conversion->output_quantity,
            'reason'           => $conversion->reason,
            'output_stock_id'  => $conversion->output_stock_id,
            'output_batch'     => $conversion->outputStock?->batch_code,
            'output_product'   => $conversion->outputStock?->receivedItem?->product?->name
                                ?? $conversion->outputStock?->product?->name,
        ];
    }
}
