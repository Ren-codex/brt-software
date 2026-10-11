<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\InventoryStocks;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\DropdownClass;
use App\Services\Modules\SalesOrderClass;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Sample orders for the Deliveries page: several out for delivery, one credit
 * sale waiting for approval, and one delivery encoded and waiting for approval.
 *
 * Like the other demo commands, every order is placed through the same service
 * the screen uses, so each has its invoice, its stock movement and its ledger
 * entries. They sell from whatever stock is already on hand.
 *
 * --clear cancels them rather than deleting them: cancelling is the system's
 * own way of undoing an order, and it puts the stock back.
 */
class SeedDemoDeliveries extends Command
{
    protected $signature = 'demo:deliveries
        {--clear : Cancel the demo deliveries instead of adding them}';

    protected $description = 'Add (or cancel) sample out-for-delivery sales orders';

    private const MANIFEST = 'demo-deliveries.json';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to write demo data in production.');

            return self::FAILURE;
        }

        $actor = User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();

        if (! $actor) {
            $this->error('Needs a Super Admin to place the orders as.');

            return self::FAILURE;
        }

        // The services record whoever is signed in as the author of each step.
        Auth::login($actor);

        if ($this->option('clear')) {
            return $this->clear();
        }

        if (Storage::disk('local')->exists(self::MANIFEST)) {
            $this->error('Demo deliveries already exist. Run with --clear first.');

            return self::FAILURE;
        }

        $customers = Customer::where('is_active', 1)->orderBy('id')->limit(6)->get();
        $dropdowns = app(DropdownClass::class);
        $driverId = $dropdowns->drivers()->first()['value'] ?? null;
        $checkerId = $dropdowns->checkers()->first()['value'] ?? null;
        $repId = $dropdowns->sales_reps()->first()['value'] ?? null;

        if ($customers->isEmpty() || ! $driverId) {
            $this->error('Needs at least one active customer and one driver.');

            return self::FAILURE;
        }

        // The checker has to be someone other than the driver.
        if ((int) $checkerId === (int) $driverId) {
            $checkerId = null;
        }

        $ids = [];

        try {
            DB::transaction(function () use ($customers, $driverId, $checkerId, $repId, &$ids) {
                $ids = $this->seed($customers, (int) $driverId, $checkerId ? (int) $checkerId : null, $repId);
            });
        } catch (\Throwable $e) {
            $this->error('Nothing was added. '.$e->getMessage());

            return self::FAILURE;
        }

        Storage::disk('local')->put(self::MANIFEST, json_encode([
            'created_at' => now()->toDateTimeString(),
            'sales_order_ids' => $ids,
        ]));

        $this->info(count($ids).' demo orders added. Open Sales → Deliveries to see them.');

        return self::SUCCESS;
    }

    /** @return int[] the ids of the orders placed */
    private function seed($customers, int $driverId, ?int $checkerId, $repId): array
    {
        $sales = app(SalesOrderClass::class);
        $ids = [];
        $customer = fn (int $i) => $customers[$i % $customers->count()];

        $line = function (int $quantity) {
            $stock = InventoryStocks::with('receivedItem')
                ->where('quantity', '>=', $quantity)
                ->where('retail_price', '>', 0)
                ->whereHas('receivedItem')
                ->orderByDesc('quantity')
                ->first();

            if (! $stock) {
                throw new \RuntimeException('Not enough stock on hand to sell from. Receive some stock first (or run demo:inventory-sales).');
            }

            return [
                'product_id' => $stock->receivedItem->product_id,
                'quantity' => $quantity,
                'price' => (float) $stock->retail_price,
                'price_type' => 'retail',
                'batch_code' => $stock->batch_code,
                'discount_per_unit' => 0,
            ];
        };

        $place = function (string $label, array $payload) use ($sales, &$ids) {
            $sales->save(new Request($payload));

            $order = SalesOrder::latest('id')->firstOrFail();
            $ids[] = $order->id;
            $this->line("  {$order->so_number}: {$label}");

            return $order;
        };

        $base = fn (int $i, array $items, string $mode, string $deliveryDate) => [
            'order_date' => now()->toDateString(),
            'customer_id' => $customer($i)->id,
            'sales_rep_id' => $repId,
            // "Zamboanga City" exactly is what makes an order a local one.
            'delivery_location' => 'Zamboanga City',
            'items' => $items,
            'payment_mode' => $mode,
            'driver_id' => $driverId,
            'delivery_date' => $deliveryDate,
        ];
        $total = fn (array $items) => round(collect($items)->sum(fn ($item) => $item['price'] * $item['quantity']), 2);

        // Out for delivery, COD: due today, due in two days, and one with a checker.
        $place('COD, out for delivery today', $base(0, [$line(4)], 'COD', now()->toDateString()));
        $place('COD, out for delivery in two days', $base(1, [$line(6)], 'COD', now()->addDays(2)->toDateString()));
        $place('COD, out for delivery tomorrow'.($checkerId ? ', with a checker' : ''),
            $base(2, [$line(3)], 'COD', now()->addDay()->toDateString()) + ['checker_id' => $checkerId]);

        // Credit, already approved: out for delivery like any other.
        $order = $place('credit, approved, out for delivery', $base(3, [$line(5)], 'Credit', now()->addDay()->toDateString()) + [
            'due_date' => now()->addDays(30)->toDateString(),
        ]);
        if ($order->awaitsCreditApproval()) {
            $sales->approveCredit($order->id);
        }

        // Credit, waiting: it shows on the board but cannot go out yet.
        $place('credit, waiting for credit approval', $base(4, [$line(5)], 'Credit', now()->addDays(3)->toDateString()) + [
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        // COD, encoded as delivered and paid: waiting under "Pending approval".
        $items = [$line(2)];
        $order = $place('COD, delivery encoded and waiting for approval', $base(5, $items, 'COD', now()->toDateString()));
        $sales->markDelivered(new Request([
            'id' => $order->id,
            'collected_amount' => $total($items),
            'collected_mode' => 'Cash',
        ]));

        return $ids;
    }

    private function clear(): int
    {
        if (! Storage::disk('local')->exists(self::MANIFEST)) {
            $this->info('There are no demo deliveries to cancel.');

            return self::SUCCESS;
        }

        $manifest = json_decode(Storage::disk('local')->get(self::MANIFEST), true) ?: [];
        $sales = app(SalesOrderClass::class);
        $cancelled = 0;

        foreach ($manifest['sales_order_ids'] ?? [] as $id) {
            if (! SalesOrder::whereKey($id)->exists()) {
                continue;
            }

            try {
                DB::transaction(fn () => $sales->cancel($id, 'Demo delivery removed'));
                $cancelled++;
            } catch (\Throwable $e) {
                $this->warn("  Order #{$id} was left as it is: ".$e->getMessage());
            }
        }

        Storage::disk('local')->delete(self::MANIFEST);
        $this->info("{$cancelled} demo orders cancelled; their stock is back on hand.");

        return self::SUCCESS;
    }
}
