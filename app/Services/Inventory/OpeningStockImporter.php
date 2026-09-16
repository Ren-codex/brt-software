<?php

namespace App\Services\Inventory;

use App\Models\InventoryStocks;
use App\Models\ListBrand;
use App\Models\ListPackaging;
use App\Models\ListStatus;
use App\Models\ListSupplier;
use App\Models\ListUnit;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderLog;
use App\Models\ReceivedItem;
use App\Models\ReceivedStock;
use App\Models\User;
use App\Services\Accounting\JournalEntryService;
use App\Services\SeriesService;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Brings stock that was already on the shelf into the system.
 *
 * One sheet row is one batch. Rows are grouped by supplier into an "Opening
 * Balance" purchase order and receipt, so every batch traces back to the
 * supplier it came from -- stock returns validate exactly that chain, and
 * inventory with nothing behind it cannot be returned. Each receipt is recorded
 * as already settled (nothing owed) and posts DR Inventory / CR Opening Balance
 * Equity rather than a purchase.
 *
 * Brand names are normalised on the way: a product's name is brand + weight +
 * unit, so a brand that still carries its weight ("Master Chef Aroma 25kg")
 * prints it twice and puts a stray digit in the product code. Size-specific
 * brands of the same rice are merged into one.
 *
 * Callers own the transaction: the command runs this inside one and rolls it
 * back for a dry run.
 */
class OpeningStockImporter
{
    public const PAYMENT_MODE = 'Opening Balance';

    /**
     * Names that do not match by spelling alone, keyed by normalised name.
     * Used for both the sheet's names and the brands already in the system.
     */
    private const BRAND_ALIASES = [
        // The sheet names every Princess Bea size "Red"; the 10kg and 5kg
        // brands were entered without the colour.
        'princessbea'         => 'Princess Bea Red',
        'masterchefglutinous' => 'Master Chef Glutinous Rice',
        'alasglutinous'       => 'Alas Glutinous Rice',
        'wjjredv160'          => 'Wjj Red V-160 Rice',
        'kuhakopinkjaponica'  => 'Kuhaku Japonica Pink',
    ];

    private const SUPPLIER_ALIASES = [
        'sodatradecorp'      => 'Sodatrade Corporation',
        'renzymarketinginc'  => 'Renzy International Marketing',
        'raccorporationinc'  => 'RAC Commercial Corporation',
        'oneprconsolidited'  => 'One PR Consolidated OPC',
        'trisnacompanyinc'   => 'Trisna Company Inc.',
        'youngsmolavemillingcorp' => 'Youngs Molave Milling Corp.',
    ];

    /** Required on a supplier; left for someone who knows the details to fill in. */
    private const NOT_RECORDED = 'Not yet recorded';

    private array $report;

    public function __construct(
        private SeriesService $series,
        private JournalEntryService $journal,
    ) {}

    /**
     * Rows from a CSV export of the sheet.
     *
     * @return list<array{line:int, supplier:string, product:string, quantity:int, cost:?float, wholesale:?float, retail:?float}>
     */
    public static function rowsFromCsv(string $path): array
    {
        if (!is_readable($path)) {
            throw new RuntimeException("Cannot read {$path}.");
        }

        $handle = fopen($path, 'r');
        fgetcsv($handle); // header
        $rows = [];
        $line = 1;

        while (($cells = fgetcsv($handle)) !== false) {
            $line++;
            if (count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }

            $rows[] = [
                'line'      => $line,
                'supplier'  => trim((string) ($cells[0] ?? '')),
                'product'   => trim((string) ($cells[1] ?? '')),
                'quantity'  => (int) self::money($cells[2] ?? '0'),
                'cost'      => self::money($cells[3] ?? ''),
                'wholesale' => self::money($cells[4] ?? ''),
                'retail'    => self::money($cells[5] ?? ''),
            ];
        }

        fclose($handle);

        return $rows;
    }

    /** Everything the rows would change, and any rows that look wrong. */
    public function import(array $rows, User $user, string $date): array
    {
        $this->report = [
            'anomalies' => $this->anomalies($rows),
            'brands' => ['renamed' => [], 'merged' => [], 'created' => []],
            'suppliers' => ['matched' => [], 'created' => []],
            'products' => ['created' => [], 'reused' => []],
            'receipts' => [],
            'totals' => ['batches' => 0, 'units' => 0, 'cost' => 0.0],
        ];

        $this->cleanUpBrands();

        $unitId = $this->unitId('Kg');
        $packaging = $this->packaging('Sack');
        $completedStatusId = ListStatus::where('slug', 'completed')->value('id')
            ?? throw new RuntimeException('The "completed" status is missing.');

        // Resolve every row first, so products exist for zero-stock rows too.
        $resolved = collect($rows)->map(function (array $row) use ($unitId, $packaging) {
            [$brandName, $weight] = $this->parseProduct($row['product']);
            $brand = $this->brand($brandName);
            $product = $this->product($brand, $weight, $unitId, $packaging);

            return $row + ['product_model' => $product];
        });

        $resolved->where('quantity', '>', 0)
            ->groupBy(fn ($row) => $this->normalise($row['supplier']))
            ->each(function (Collection $supplierRows) use ($user, $date, $completedStatusId) {
                $this->receiveOpeningStock($supplierRows, $user, $date, $completedStatusId);
            });

        $this->report['totals']['cost'] = round($this->report['totals']['cost'], 2);

        return $this->report;
    }

    // ── Rows ─────────────────────────────────────────────────────────────

    private function anomalies(array $rows): array
    {
        $found = [];

        foreach ($rows as $row) {
            $where = "Row {$row['line']} ({$row['product']}, {$row['supplier']})";

            if ($row['product'] === '') {
                $found[] = "{$where}: no product name.";
            }
            if ($row['quantity'] < 0) {
                $found[] = "{$where}: negative quantity.";
            }
            if ($row['quantity'] > 0) {
                if ($row['supplier'] === '') {
                    $found[] = "{$where}: stock with no supplier.";
                }
                foreach (['cost', 'wholesale', 'retail'] as $price) {
                    if ($row[$price] === null) {
                        $found[] = "{$where}: stock with no {$price} price.";
                    }
                }
            }
            if ($row['wholesale'] !== null && $row['retail'] !== null && $row['wholesale'] > $row['retail']) {
                $found[] = "{$where}: wholesale " . number_format($row['wholesale'], 2)
                    . ' is above retail ' . number_format($row['retail'], 2) . '.';
            }
            if ($row['cost'] !== null && $row['wholesale'] !== null && $row['cost'] > $row['wholesale']) {
                $found[] = "{$where}: cost " . number_format($row['cost'], 2)
                    . ' is above wholesale ' . number_format($row['wholesale'], 2) . '.';
            }
        }

        return $found;
    }

    /** "PRINCESS BEA RED10KG" -> ["Princess Bea Red", 10]. No weight means a 25kg sack. */
    private function parseProduct(string $name): array
    {
        $name = trim(preg_replace('/\s+/', ' ', str_replace(['(', ')'], ' ', $name)));

        if (preg_match('/^(.*?)\s*(\d+)\s*KG$/i', $name, $m)) {
            return [trim($m[1]), (int) $m[2]];
        }

        return [$name, 25];
    }

    private static function money(mixed $value): ?float
    {
        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);

        return $clean === '' || $clean === '-' ? null : (float) $clean;
    }

    private function normalise(string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($name));
    }

    // ── Brands ───────────────────────────────────────────────────────────

    /** "Master Chef Aroma 25kg" -> "Master Chef Aroma", following aliases. */
    private function canonicalBrandName(string $name): string
    {
        $withoutWeight = trim(preg_replace('/\s*\d+\s*kg\s*$/i', '', $name));

        return self::BRAND_ALIASES[$this->normalise($withoutWeight)] ?? $withoutWeight;
    }

    private function cleanUpBrands(): void
    {
        $groups = ListBrand::orderBy('id')->get()
            ->groupBy(fn (ListBrand $brand) => $this->normalise($this->canonicalBrandName($brand->name)));

        foreach ($groups as $brands) {
            $canonical = $this->canonicalBrandName($brands->first()->name);
            $keeper = $brands->first(fn ($b) => $b->name === $canonical) ?? $brands->first();

            foreach ($brands as $brand) {
                if ($brand->is($keeper)) {
                    continue;
                }
                Product::where('brand_id', $brand->id)->update(['brand_id' => $keeper->id]);
                $this->report['brands']['merged'][] = "{$brand->name} -> {$canonical}";
                $brand->delete();
            }

            if ($keeper->name !== $canonical) {
                $this->report['brands']['renamed'][] = "{$keeper->name} -> {$canonical}";
                $keeper->update(['name' => $canonical]);
            }
        }
    }

    private function brand(string $sheetName): ListBrand
    {
        $canonical = $this->canonicalBrandName($sheetName);
        $key = $this->normalise($canonical);

        $existing = ListBrand::all()->first(fn ($b) => $this->normalise($b->name) === $key);
        if ($existing) {
            return $existing;
        }

        // Title case, but leave short all-capital words ("AAA") as they are.
        $name = collect(explode(' ', $canonical))
            ->map(fn ($w) => preg_match('/^[A-Z]{2,3}$/', $w) ? $w : ucfirst(strtolower($w)))
            ->implode(' ');
        $this->report['brands']['created'][] = $name;

        return ListBrand::create(['name' => $name]);
    }

    // ── Suppliers ────────────────────────────────────────────────────────

    private function supplier(string $sheetName): ListSupplier
    {
        $key = $this->normalise($sheetName);
        $name = self::SUPPLIER_ALIASES[$key] ?? $sheetName;

        $existing = ListSupplier::all()->first(fn ($s) => $this->normalise($s->name) === $this->normalise($name));
        if ($existing) {
            $this->report['suppliers']['matched'][$sheetName] = $existing->name;

            return $existing;
        }

        $this->report['suppliers']['created'][] = $name;

        return ListSupplier::create([
            'name' => $name,
            'address' => self::NOT_RECORDED,
            'contact_person' => self::NOT_RECORDED,
            'contact_number' => self::NOT_RECORDED,
            'email' => '',
            'is_active' => 1,
            'is_blacklisted' => 0,
        ]);
    }

    // ── Products ─────────────────────────────────────────────────────────

    private function unitId(string $name): int
    {
        return ListUnit::whereRaw('LOWER(name) = ?', [strtolower($name)])->value('id')
            ?? ListUnit::create(['name' => $name])->id;
    }

    private function packaging(string $name): ListPackaging
    {
        return ListPackaging::whereRaw('LOWER(name) = ?', [strtolower($name)])->first()
            ?? ListPackaging::create(['name' => $name]);
    }

    private function product(ListBrand $brand, int $weight, int $unitId, ListPackaging $packaging): Product
    {
        $existing = Product::where('brand_id', $brand->id)->where('weight', $weight)->first();
        $label = "{$brand->name} {$weight}kg";

        if ($existing) {
            if (!isset($this->report['products']['reused'][$existing->code])) {
                $this->report['products']['reused'][$existing->code] = $label;
            }

            return $existing;
        }

        $product = Product::create([
            'code' => $this->nextProductCode($brand, $packaging, $weight),
            'brand_id' => $brand->id,
            'weight' => $weight,
            'unit_id' => $unitId,
            'packaging_id' => $packaging->id,
            'is_active' => 1,
        ]);

        $this->report['products']['created'][$product->code] = $label;

        return $product;
    }

    /** The scheme the product form uses: brand initials + packaging initial + weight + sequence. */
    private function nextProductCode(ListBrand $brand, ListPackaging $packaging, int $weight): string
    {
        $initials = fn (string $s) => collect(preg_split('/\s+/', trim($s)))
            ->map(fn ($w) => strtoupper(substr($w, 0, 1)))
            ->implode('');

        $prefix = $initials($brand->name) . $initials($packaging->name) . $weight;

        $highest = Product::where('code', 'like', $prefix . '%')->pluck('code')
            ->map(fn ($code) => substr($code, strlen($prefix)))
            ->filter(fn ($suffix) => ctype_digit($suffix))
            ->map(fn ($suffix) => (int) $suffix)
            ->max() ?? 0;

        return $prefix . str_pad($highest + 1, 3, '0', STR_PAD_LEFT);
    }

    // ── Receiving ────────────────────────────────────────────────────────

    private function receiveOpeningStock(Collection $rows, User $user, string $date, int $completedStatusId): void
    {
        $supplier = $this->supplier($rows->first()['supplier']);
        $total = round($rows->sum(fn ($r) => $r['quantity'] * $r['cost']), 2);

        $po = PurchaseOrder::create([
            'po_number' => $this->series->get('purchase_order'),
            'po_date' => $date,
            'total_amount' => $total,
            'status_id' => $completedStatusId,
            'supplier_id' => $supplier->id,
            'created_by_id' => $user->id,
            'approved_by_id' => $user->id,
            'approved_date' => $date,
        ]);

        PurchaseOrderLog::create([
            'po_id' => $po->id,
            'user_id' => $user->id,
            'action' => 'Opening Stock',
            'remarks' => 'Stock already on hand when the system started, brought in by import.',
        ]);

        $receipt = ReceivedStock::create([
            'po_id' => $po->id,
            'supplier_id' => $supplier->id,
            'received_date' => $date,
            'received_no' => $this->series->get('received_no'),
            'payment_mode' => self::PAYMENT_MODE,
            // Already paid for before the system existed: nothing is owed, so
            // it stays off Accounts Payable.
            'amount_paid' => $total,
            'received_by_id' => $user->id,
            'remarks' => 'Opening stock import.',
        ]);

        $batches = [];
        foreach ($rows as $row) {
            $lineCost = round($row['quantity'] * $row['cost'], 2);

            $poItem = PurchaseOrderItem::create([
                'po_id' => $po->id,
                'product_id' => $row['product_model']->id,
                'quantity' => $row['quantity'],
                'unit_cost' => $row['cost'],
                'total_cost' => $lineCost,
                'status' => 'received',
                'received_quantity' => $row['quantity'],
            ]);

            $item = ReceivedItem::create([
                'received_id' => $receipt->id,
                'product_id' => $row['product_model']->id,
                'po_item_id' => $poItem->id,
                'quantity' => $row['quantity'],
                'unit_cost' => $row['cost'],
                'total_cost' => $lineCost,
            ]);

            $stock = InventoryStocks::create([
                'received_item_id' => $item->id,
                'product_id' => $row['product_model']->id,
                'batch_code' => $this->series->get('batch_code'),
                'quantity' => $row['quantity'],
                'unit_cost' => $row['cost'],
                'wholesale_price' => $row['wholesale'],
                'retail_price' => $row['retail'],
            ]);

            $batches[] = sprintf(
                '%s  %-8s %6d x %9s  (wholesale %s, retail %s)  row %d',
                $stock->batch_code,
                $row['product_model']->code,
                $row['quantity'],
                number_format($row['cost'], 2),
                number_format($row['wholesale'], 2),
                number_format($row['retail'], 2),
                $row['line'],
            );

            $this->report['totals']['batches']++;
            $this->report['totals']['units'] += $row['quantity'];
            $this->report['totals']['cost'] += $lineCost;
        }

        $this->journal->recordOpeningStockReceipt($receipt);

        $this->report['receipts'][] = [
            'supplier' => $supplier->name,
            'po_number' => $po->po_number,
            'received_no' => $receipt->received_no,
            'units' => $rows->sum('quantity'),
            'cost' => $total,
            'batches' => $batches,
        ];
    }
}
