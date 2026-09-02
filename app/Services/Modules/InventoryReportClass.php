<?php

namespace App\Services\Modules;

use App\Models\InventoryStocks;
use Illuminate\Support\Collection;

/**
 * The stock position: what is held right now and what it is worth.
 *
 * Deliberately not filtered by date. Stock on hand is a balance, not a flow —
 * asking "what was received this month" answers a different question, and doing
 * that on the dashboard reported ₱0 of stock on any day nothing came in.
 */
class InventoryReportClass
{
    /** A batch at or below this many days from expiry is worth flagging. */
    private const EXPIRING_WITHIN_DAYS = 30;

    public function summary(array $filters): array
    {
        $rows = $this->rows($filters);

        return [
            'generated_at' => now()->toDateTimeString(),
            'filters'      => $filters,
            'totals'       => $this->totals($rows),
            'rows'         => $rows->values()->all(),
            'by_brand'     => $this->byBrand($rows),
        ];
    }

    /**
     * One row per batch. A batch's product comes either from the received item
     * it arrived on, or directly from product_id when it was produced by a
     * conversion — conversions have no received item behind them.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(array $filters): Collection
    {
        $query = InventoryStocks::query()
            ->with([
                'product.brand', 'product.unit',
                'receivedItem.product.brand', 'receivedItem.product.unit',
            ])
            ->where('is_archived', 0);

        if (!($filters['include_empty'] ?? false)) {
            $query->where('quantity', '>', 0);
        }

        // 'current' means everything on hand, which is what a stock position is.
        // The other periods narrow to batches that ARRIVED in that window — a
        // different question, and labelled as such on screen so the two are
        // never confused the way the dashboard once confused them.
        $range = $this->periodRange(
            $filters['period'] ?? 'current',
            $filters['period_value'] ?? null
        );

        if ($range) {
            $query->whereBetween('inventory_stocks.created_at', $range);
        }

        return $query->get()
            ->map(fn ($stock) => $this->describe($stock))
            ->filter(fn ($row) => $this->matchesFilters($row, $filters))
            ->sortBy([['product_name', 'asc'], ['batch_code', 'asc']]);
    }

    /** Supported periods, in the order the screen offers them. */
    public const PERIODS = ['current', 'week', 'month', 'quarter', 'year'];

    /**
     * The window a period covers, or null for 'current' — which applies no date
     * filter at all, because stock on hand is a balance rather than a flow.
     *
     * $value picks which one: '2026-08' for a month, '2026-Q3' for a quarter,
     * '2026' for a year, '2026-W34' for a week. Omitted or unparseable, it
     * falls back to the period containing today rather than showing nothing.
     *
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}|null
     */
    private function periodRange(string $period, ?string $value = null): ?array
    {
        $anchor = $this->anchorFor($period, $value);

        return match ($period) {
            'week'    => [$anchor->copy()->startOfWeek(), $anchor->copy()->endOfWeek()],
            'month'   => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()],
            'quarter' => [$anchor->copy()->startOfQuarter(), $anchor->copy()->endOfQuarter()],
            'year'    => [$anchor->copy()->startOfYear(), $anchor->copy()->endOfYear()],
            default   => null,
        };
    }

    /** A date inside the requested period; today when none was given. */
    private function anchorFor(string $period, ?string $value): \Carbon\Carbon
    {
        if (blank($value)) {
            return now();
        }

        try {
            return match ($period) {
                'year'    => \Carbon\Carbon::createFromDate((int) $value, 1, 1),
                'month'   => \Carbon\Carbon::createFromFormat('Y-m', $value)->startOfMonth(),
                'quarter' => $this->quarterAnchor($value),
                'week'    => $this->weekAnchor($value),
                default   => now(),
            };
        } catch (\Throwable) {
            return now();
        }
    }

    /** '2026-Q3' → the first day of that quarter. */
    private function quarterAnchor(string $value): \Carbon\Carbon
    {
        [$year, $quarter] = explode('-Q', strtoupper($value));

        return \Carbon\Carbon::createFromDate((int) $year, ((int) $quarter - 1) * 3 + 1, 1);
    }

    /** '2026-W34' → a day inside ISO week 34 of that year. */
    private function weekAnchor(string $value): \Carbon\Carbon
    {
        [$year, $week] = explode('-W', strtoupper($value));

        return \Carbon\Carbon::now()->setISODate((int) $year, (int) $week);
    }

    private function describe(InventoryStocks $stock): array
    {
        $product = $stock->product ?: optional($stock->receivedItem)->product;

        $quantity = (int) $stock->quantity;
        // Stock that was received carries its cost on the received item; stock
        // produced by a conversion carries it on the row itself. Reading only
        // the row reported received batches at ₱0 and lost most of the value.
        //
        // Compared numerically on purpose: the column comes back as the string
        // '0.0000', which is truthy, so ?: would never reach the fallback.
        $ownCost = (float) $stock->unit_cost;
        $unitCost = round(
            $ownCost > 0 ? $ownCost : (float) optional($stock->receivedItem)->unit_cost,
            2
        );
        $retail   = round((float) $stock->retail_price, 2);
        $minimum  = (int) optional($product)->minimum_stock;

        $daysToExpiry = $stock->expiration_date
            ? now()->startOfDay()->diffInDays($stock->expiration_date, false)
            : null;

        return [
            'id'             => $stock->id,
            'batch_code'     => $stock->batch_code,
            'product_id'     => optional($product)->id,
            'product_name'   => $this->productName($product),
            'brand'          => optional(optional($product)->brand)->name ?? 'Unbranded',
            'is_converted'   => !is_null($stock->conversion_id),
            'quantity'       => $quantity,
            'minimum_stock'  => $minimum,
            'unit_cost'      => $unitCost,
            'retail_price'   => $retail,
            'wholesale_price'=> round((float) $stock->wholesale_price, 2),
            // What the stock cost, which is what it is worth on the books.
            'stock_value'    => round($unitCost * $quantity, 2),
            // What it would bring in if it all sold at retail — potential
            // revenue, kept separate so the two are never confused.
            'retail_value'   => round($retail * $quantity, 2),
            'expiration_date'=> optional($stock->expiration_date)->toDateString(),
            'days_to_expiry' => $daysToExpiry,
            'status'         => $this->status($quantity, $minimum, $daysToExpiry),
        ];
    }

    private function productName($product): string
    {
        if (!$product) {
            return 'Unknown product';
        }

        return trim(
            (optional($product->brand)->name ?? 'Unbranded')
            . ' ' . $product->weight
            . ' ' . (optional($product->unit)->name ?? '')
        );
    }

    /** Out of stock wins over low, and low over expiring — the worse news first. */
    private function status(int $quantity, int $minimum, ?int $daysToExpiry): string
    {
        if ($quantity <= 0) {
            return 'out_of_stock';
        }
        if ($minimum > 0 && $quantity <= $minimum) {
            return 'low_stock';
        }
        if (!is_null($daysToExpiry) && $daysToExpiry <= self::EXPIRING_WITHIN_DAYS) {
            return $daysToExpiry < 0 ? 'expired' : 'expiring';
        }

        return 'in_stock';
    }

    private function matchesFilters(array $row, array $filters): bool
    {
        if (!empty($filters['brand']) && $row['brand'] !== $filters['brand']) {
            return false;
        }

        if (!empty($filters['keyword'])) {
            $needle = mb_strtolower($filters['keyword']);
            $haystack = mb_strtolower($row['product_name'] . ' ' . $row['batch_code']);
            if (!str_contains($haystack, $needle)) {
                return false;
            }
        }

        if (!empty($filters['low_stock_only']) && !in_array($row['status'], ['low_stock', 'out_of_stock'], true)) {
            return false;
        }

        return true;
    }

    private function totals(Collection $rows): array
    {
        return [
            'batches'       => $rows->count(),
            'products'      => $rows->pluck('product_id')->filter()->unique()->count(),
            'quantity'      => (int) $rows->sum('quantity'),
            'stock_value'   => round($rows->sum('stock_value'), 2),
            'retail_value'  => round($rows->sum('retail_value'), 2),
            'low_stock'     => $rows->where('status', 'low_stock')->count(),
            'out_of_stock'  => $rows->where('status', 'out_of_stock')->count(),
            'expiring'      => $rows->whereIn('status', ['expiring', 'expired'])->count(),
        ];
    }

    private function byBrand(Collection $rows): array
    {
        return $rows->groupBy('brand')
            ->map(fn ($group, $brand) => [
                'brand'       => $brand,
                'batches'     => $group->count(),
                'quantity'    => (int) $group->sum('quantity'),
                'stock_value' => round($group->sum('stock_value'), 2),
            ])
            ->sortByDesc('stock_value')
            ->values()
            ->all();
    }
}
