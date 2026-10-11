<?php

namespace App\Console\Commands;

use App\Models\ArInvoice;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\InventoryStocks;
use App\Models\ListStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\User;
use App\Observers\ApprovalRequestObserver;
use App\Services\DropdownClass;
use App\Services\Modules\ArInvoiceClass;
use App\Services\Modules\SalesOrderClass;
use App\Services\ReceivedStockService;
use App\Services\System\PurchaseOrder\PurchaseOrderClass;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Sample purchase orders, stock and sales orders for trying out the Inventory
 * and Sales screens.
 *
 * Nothing here is written straight into the tables. Every record is made by the
 * same service the screen uses — a purchase request is saved, approved and
 * received; a sales order is saved, delivered and paid — so the demo data
 * carries everything a real record does: PO numbers, stock batches, invoices,
 * receipts and journal entries that agree with one another. Hand-inserted rows
 * would look right in a list and fall apart the moment someone opened one.
 *
 * Because those services write to many tables, --clear does not try to know
 * them all. Seeding notes the highest id in every table before and after, and
 * clearing removes exactly the rows in between. It refuses when newer real
 * records exist on top of the demo data, since those may depend on it (a real
 * sale drawing on demo stock, say).
 */
class SeedDemoInventorySales extends Command
{
    protected $signature = 'demo:inventory-sales
        {--clear : Remove the demo data instead of adding it}
        {--force : With --clear, remove it even if newer records exist}';

    protected $description = 'Add (or clear) sample purchase orders, stock and sales orders';

    private const MANIFEST = 'demo-inventory-sales.json';

    /** Reference and setup tables that clearing must never touch. */
    private const KEEP = '/^(list_|migrations$|series$|modules$|submodules$|role_permissions$|user_roles$|users$|employees$|customers$|products$|accounts$|password_|sessions$|cache|jobs$|failed_jobs$|job_batches$|personal_access_tokens$|payroll|loans?$|loan_|supervisor_)/';

    public function handle(): int
    {
        // Demo orders are built already delivered and paid; the approval steps
        // that would hold a real one are switched off for this run only.
        config(['sales.credit_approval' => false, 'sales.delivery_approval' => false]);

        if (app()->environment('production')) {
            $this->error('Refusing to write demo data in production.');

            return self::FAILURE;
        }

        if ($this->option('clear')) {
            return $this->clear();
        }

        if (Storage::disk('local')->exists(self::MANIFEST)) {
            $this->error('Demo inventory and sales data already exists. Run with --clear first.');

            return self::FAILURE;
        }

        $actor = User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();
        $products = Product::with(['brand', 'unit'])->where('is_active', 1)->orderBy('id')->limit(4)->get();
        $suppliers = DB::table('list_suppliers')->where('is_active', 1)->orderBy('id')->limit(3)->get();
        $customers = Customer::where('is_active', 1)->orderBy('id')->limit(5)->get();
        $driverId = app(DropdownClass::class)->drivers()->first()['value'] ?? null;
        $repId = app(DropdownClass::class)->sales_reps()->first()['value'] ?? null;

        if (! $actor || $products->count() < 2 || $suppliers->count() < 3 || $customers->count() < 5 || ! $driverId) {
            $this->error('Needs a Super Admin, at least 2 active products, 3 suppliers, 5 customers and one driver.');

            return self::FAILURE;
        }

        // The services record whoever is signed in as the author of each step.
        Auth::login($actor);

        $before = $this->maxIds();

        try {
            DB::transaction(function () use ($products, $suppliers, $customers, $driverId, $repId) {
                $this->seedInventory($products, $suppliers);
                $this->seedSales($products, $customers, $driverId, $repId);
            });
        } catch (\Throwable $e) {
            $this->error('Nothing was added. '.$e->getMessage());

            return self::FAILURE;
        }

        Storage::disk('local')->put(self::MANIFEST, json_encode([
            'created_at' => now()->toDateTimeString(),
            'before' => $before,
            'after' => $this->maxIds(),
        ], JSON_PRETTY_PRINT));

        $this->info('Demo inventory and sales data added. Run with --clear to remove it.');

        return self::SUCCESS;
    }

    /**
     * Three purchase requests at different points: one received in full, one
     * received in part, and one still waiting for an approver.
     */
    private function seedInventory($products, $suppliers): void
    {
        $purchasing = app(PurchaseOrderClass::class);
        $receiving = app(ReceivedStockService::class);
        $approvedId = ListStatus::getBySlug('approved')->id;

        $plans = [
            ['supplier' => $suppliers[0], 'quantity' => 200, 'receive' => 1.0],
            ['supplier' => $suppliers[1], 'quantity' => 120, 'receive' => 0.5],
            ['supplier' => $suppliers[2], 'quantity' => 80, 'receive' => null],
        ];

        foreach ($plans as $index => $plan) {
            // Each request covers a different pair of products.
            $lines = $products->slice($index % 2, 2)->values()->map(function ($product) use ($plan) {
                $cost = $this->unitCost($product);

                return [
                    'product_id' => $product->id,
                    'quantity' => $plan['quantity'],
                    'unit_cost' => $cost,
                    'total_cost' => round($cost * $plan['quantity'], 2),
                ];
            });

            $purchasing->save(new Request([
                'supplier_id' => $plan['supplier']->id,
                'total_amount' => $lines->sum('total_cost'),
                'items' => $lines->all(),
            ]));

            $order = PurchaseOrder::with('items.product.brand', 'items.product.unit')->latest('id')->firstOrFail();

            if ($plan['receive'] === null) {
                $this->line("  Purchase request {$order->pr_number}: waiting for approval");

                continue;
            }

            $purchasing->updateStatus(new Request(['id' => $order->id, 'status_id' => $approvedId, 'remarks' => 'Demo approval']));

            $receiving->create([
                'po_id' => $order->id,
                'supplier_id' => $order->supplier_id,
                'received_date' => now()->toDateString(),
                // On credit, so the demo does not depend on a cash or bank
                // balance existing: it leaves an open payable instead.
                'payment_mode' => 'Credit',
                'due_date' => now()->addDays(30)->toDateString(),
                'remarks' => 'Demo delivery',
                'items' => $order->items->map(function ($item) use ($plan) {
                    $cost = (float) $item->unit_cost;

                    return [
                        'po_item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $this->productName($item->product),
                        'quantity' => $item->quantity,
                        'unit_cost' => $cost,
                        'total_cost' => (float) $item->total_cost,
                        'to_received_quantity' => (int) floor($item->quantity * $plan['receive']),
                        'retail_price' => round($cost * 1.28, 2),
                        'wholesale_price' => round($cost * 1.15, 2),
                        'expiration_date' => null,
                    ];
                })->all(),
            ]);

            $order->refresh();
            $this->line("  Purchase order {$order->po_number}: received ".($plan['receive'] * 100).'% ('.optional($order->status)->name.')');
        }
    }

    /**
     * Five sales orders, one at each stage worth seeing: a finished counter
     * sale, a COD still out for delivery, a COD delivered with the money not yet
     * handed in, a credit sale owing in full, and one part paid.
     */
    private function seedSales($products, $customers, int $driverId, ?int $repId): void
    {
        $sales = app(SalesOrderClass::class);
        $invoices = app(ArInvoiceClass::class);

        $line = function ($product, int $quantity) {
            // A batch knows its product through the delivery line it came from.
            $stock = InventoryStocks::whereHas('receivedItem', fn ($q) => $q->where('product_id', $product->id))
                ->where('quantity', '>=', $quantity)
                ->orderBy('id')
                ->first();

            if (! $stock) {
                throw new \RuntimeException('Not enough demo stock was received to sell from.');
            }

            return [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'price' => (float) $stock->retail_price,
                'price_type' => 'retail',
                'batch_code' => $stock->batch_code,
                'discount_per_unit' => 0,
            ];
        };

        $place = function (string $label, array $payload) use ($sales) {
            $sales->save(new Request($payload));

            $order = SalesOrder::with('status')->latest('id')->firstOrFail();
            $this->line("  Sales order {$order->so_number}: {$label}");

            return $order;
        };

        $base = fn (Customer $customer, array $items) => [
            'order_date' => now()->toDateString(),
            'customer_id' => $customer->id,
            'sales_rep_id' => $repId,
            // "Zamboanga City" exactly is what makes an order a local one;
            // any other place files it under external sales orders.
            'delivery_location' => 'Zamboanga City',
            'items' => $items,
        ];
        $total = fn (array $items) => round(collect($items)->sum(fn ($item) => $item['price'] * $item['quantity']), 2);

        // 1. Cash at the counter: paid and handed over on the spot.
        $items = [$line($products[0], 5)];
        $place('cash counter sale, finished', $base($customers[0], $items) + [
            'payment_mode' => 'Cash',
            'payment_lines' => [['payment_mode' => 'Cash', 'payment_amount' => $total($items)]],
        ]);

        // 2. COD, not delivered yet.
        $place('COD, out for delivery', $base($customers[1], [$line($products[1], 10)]) + [
            'payment_mode' => 'COD',
            'driver_id' => $driverId,
            'delivery_date' => now()->addDays(2)->toDateString(),
        ]);

        // 3. COD, delivered and collected; the money is still with the driver.
        $items = [$line($products[0], 8)];
        $order = $place('COD, delivered, money not yet turned in', $base($customers[2], $items) + [
            'payment_mode' => 'COD',
            'driver_id' => $driverId,
            'delivery_date' => now()->toDateString(),
        ]);
        $sales->completeDelivery(new Request([
            'id' => $order->id,
            'collected_amount' => $total($items),
            'collected_mode' => 'Cash',
        ]));

        // 4. Credit, delivered, nothing paid yet. Sent out of town, so the
        //    external sales orders list has something in it too.
        $order = $place('credit, delivered, unpaid (external: Dipolog City)', ['delivery_location' => 'Dipolog City'] + $base($customers[3], [$line($products[1], 12)]) + [
            'payment_mode' => 'Credit',
            'driver_id' => $driverId,
            'due_date' => now()->addDays(30)->toDateString(),
        ]);
        $sales->completeDelivery(new Request(['id' => $order->id]));

        // 5. Credit, delivered, half paid at the office.
        $items = [$line($products[0], 10)];
        $order = $place('credit, delivered, partly paid', $base($customers[4], $items) + [
            'payment_mode' => 'Credit',
            'driver_id' => $driverId,
            'due_date' => now()->addDays(15)->toDateString(),
        ]);
        $sales->completeDelivery(new Request(['id' => $order->id]));

        $invoice = ArInvoice::where('sales_order_id', $order->id)->firstOrFail();
        $invoices->payment(new Request([
            'id' => $invoice->id,
            'option' => 'payment',
            'balance_due' => (float) $invoice->balance_due,
            'amount_paid' => round($total($items) / 2, 2),
            'payment_date' => now()->toDateString(),
            'payment_mode' => 'Cash',
        ]), $invoice->id);
    }

    /** A believable cost per sack from its weight: about ₱45 a kilo. */
    private function unitCost($product): float
    {
        return round(max((float) $product->weight, 1) * 45, 2);
    }

    private function productName($product): string
    {
        return trim(($product->brand->name ?? 'Product').' '.$product->weight.' '.($product->unit->name ?? ''));
    }

    /** The highest id in every table that has one. */
    private function maxIds(): array
    {
        $ids = [];

        foreach (Schema::getTableListing() as $table) {
            $table = last(explode('.', $table));

            if (Schema::hasColumn($table, 'id')) {
                $ids[$table] = (int) DB::table($table)->max('id');
            }
        }

        return $ids;
    }

    private function clear(): int
    {
        $disk = Storage::disk('local');

        if (! $disk->exists(self::MANIFEST)) {
            $this->info('There is no demo inventory and sales data to remove.');

            return self::SUCCESS;
        }

        $manifest = json_decode($disk->get(self::MANIFEST), true);
        $before = $manifest['before'];
        $after = $manifest['after'];

        // Records made after the demo may lean on it: a real sale selling demo
        // stock, a payment against a demo invoice. Removing the demo rows from
        // under them would leave those records pointing at nothing.
        $newer = collect(['purchase_orders', 'received_stocks', 'sales_orders', 'receipts', 'stock_returns'])
            ->filter(fn ($table) => isset($after[$table]) && (int) DB::table($table)->max('id') > $after[$table]);

        if ($newer->isNotEmpty() && ! $this->option('force')) {
            $this->error('Newer records exist in: '.$newer->implode(', ').'. They may depend on the demo data.');
            $this->line('Run with --clear --force to remove the demo data anyway.');

            return self::FAILURE;
        }

        $removed = 0;

        // Notifications are keyed by uuid, so they are matched by what they
        // point at instead: the demo purchase requests.
        $firstOrder = ($before['purchase_orders'] ?? 0) + 1;
        $lastOrder = $after['purchase_orders'] ?? 0;
        for ($id = $firstOrder; $id <= $lastOrder; $id++) {
            DB::table('notifications')
                ->where('data', 'like', '%"source":"purchase_order"%')
                ->where('data', 'like', '%"record_id":'.$id.',%')
                ->delete();
        }

        // Deleting by id range across many tables cannot follow a dependency
        // order, so the checks are lifted for the duration. Safe here because
        // every table's demo rows go together.
        Schema::disableForeignKeyConstraints();

        try {
            // Silenced: removing a pending demo request is not it being actioned.
            ApprovalRequestObserver::$enabled = false;

            foreach ($after as $table => $last) {
                $first = $before[$table] ?? 0;

                if ($last <= $first || preg_match(self::KEEP, $table) || ! Schema::hasTable($table)) {
                    continue;
                }

                $removed += DB::table($table)->where('id', '>', $first)->where('id', '<=', $last)->delete();
            }
        } finally {
            ApprovalRequestObserver::$enabled = true;
            Schema::enableForeignKeyConstraints();
        }

        $disk->delete(self::MANIFEST);
        $this->info("Removed the demo inventory and sales data ({$removed} rows).");

        return self::SUCCESS;
    }
}
