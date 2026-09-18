# Delivery Acceptance Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Record what the customer actually accepted at the door, and watch the money collected there until it reaches the office.

**Architecture:** Four self-contained slices, each shippable alone. Delivery is a new action on the existing sales order action switch; short quantities reuse the order's own resize path (restore stock, resize invoice, reverse and re-post the ledger) rather than a second way to change an order's value. Custody is one column on `receipts`. Bank-transfer confirmation generalises the cheque confirmation that already exists. The field view is a read-only list.

**Tech Stack:** Laravel 11, Inertia + Vue 3, Pest/PHPUnit feature tests on SQLite, MySQL in production.

**Spec:** `docs/superpowers/specs/2026-09-19-delivery-acceptance-design.md`

## Global Constraints

- Every migration is additive and nullable. Production deploys never run migrations — each one is applied by hand, immediately after the deploy that carries it. Never run `migrate` against production without the user's explicit approval in that message.
- Tests run on SQLite while production is MySQL: a foreign key to `list_roles` must be `unsignedTinyInteger`, and `sales_orders`/`users` ids are `unsignedInteger`.
- The suite must stay green: `php artisan test` (696 passing as of this plan).
- Rebuild frontend assets with `npm run build` and commit `public/build` whenever a `.vue` file changes — the server has no Node.
- A sales order's money check is `SalesOrder::isOnAccount($mode)`; a granted-term check is `SalesOrder::isTermCredit($mode)`. Never re-introduce a literal `['credit', 'credit sales']`.
- Permissions are enforced with `$this->authorizePermission('sales', '<submodule>', '<level>')` inside controller actions.

---

### Task 1: Delivered stamp

**Files:**
- Create: `database/migrations/2026_09_19_000002_add_delivered_at_to_sales_orders_table.php`
- Modify: `app/Models/SalesOrder.php` (fillable + casts + `deliveredBy()` relation)
- Modify: `app/Services/Modules/SalesOrderClass.php` (new `markDelivered()`)
- Modify: `app/Http/Controllers/Modules/SalesOrderController.php:131` (action switch)
- Modify: `app/Http/Requests/Modules/SalesOrderRequest.php` (rules for the new action)
- Test: `tests/Feature/Sales/MarkDeliveredTest.php`

**Interfaces:**
- Consumes: `SalesOrder::isOnAccount()`, `authorizePermission()`, `handleTransaction()`.
- Produces: `SalesOrderClass::markDelivered($request)` returning the standard envelope `['data' => SalesOrderResource, 'message' => string, 'info' => string, 'status' => true]`; `sales_orders.delivered_at` (datetime, nullable), `sales_orders.delivered_by_id` (unsignedInteger, nullable, FK users); `SalesOrder::deliveredBy()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Sales;

use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkDeliveredTest extends TestCase
{
    use RefreshDatabase;

    // Fixture: copy setUp(), encoder() and payload() from
    // tests/Feature/Sales/CodSalesOrderTest.php verbatim — same statuses,
    // brand, unit, product, stock, conversion and customer.

    public function test_marking_an_order_delivered_stamps_when_and_who(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();

        $this->actingAs($this->user)
            ->put('/sales-orders/'.$order->id, ['action' => 'mark-delivered'])
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertNotNull($order->delivered_at);
        $this->assertSame($this->user->id, $order->delivered_by_id);
    }

    public function test_a_second_mark_keeps_the_first_timestamp(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();

        $this->actingAs($this->user)->put('/sales-orders/'.$order->id, ['action' => 'mark-delivered']);
        $first = $order->fresh()->delivered_at;

        $this->travel(2)->days();
        $this->actingAs($this->user)->put('/sales-orders/'.$order->id, ['action' => 'mark-delivered']);

        $this->assertEquals($first, $order->fresh()->delivered_at);
    }

    public function test_a_cancelled_order_cannot_be_marked_delivered(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();
        $order->update(['status_id' => \App\Models\ListStatus::where('slug', 'cancelled')->value('id')]);

        $this->actingAs($this->user)
            ->put('/sales-orders/'.$order->id, ['action' => 'mark-delivered'])
            ->assertSessionHasErrors('delivered_at');

        $this->assertNull($order->fresh()->delivered_at);
    }
}
```

- [ ] **Step 2: Run the test and watch it fail**

Run: `php artisan test --filter=MarkDeliveredTest`
Expected: FAIL — `delivered_at` does not exist; the action falls through to `update()` and errors on missing items.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the goods reached the customer, and who recorded it. Nullable: every
 * order encoded before this simply has neither.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable()->after('delivery_date');
            $table->unsignedInteger('delivered_by_id')->nullable()->after('delivered_at');
            $table->foreign('delivered_by_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropForeign(['delivered_by_id']);
            $table->dropColumn(['delivered_at', 'delivered_by_id']);
        });
    }
};
```

Run: `php artisan migrate`

- [ ] **Step 4: Add the model plumbing**

In `app/Models/SalesOrder.php`, add `'delivered_at'` and `'delivered_by_id'` to `$fillable`, add `'delivered_at' => 'datetime'` to `$casts`, and add:

```php
    public function deliveredBy()
    {
        return $this->belongsTo(User::class, 'delivered_by_id');
    }
```

- [ ] **Step 5: Write the service method**

In `app/Services/Modules/SalesOrderClass.php`:

```php
    /**
     * The goods reached the customer. Recorded by the office, usually when the
     * driver returns with the signed delivery receipt.
     *
     * Marking twice keeps the first stamp: a stray second click must not
     * rewrite when the delivery happened.
     */
    public function markDelivered($request)
    {
        $data = SalesOrder::findOrFail($request->id);

        if (optional($data->status)->slug === 'cancelled') {
            throw ValidationException::withMessages([
                'delivered_at' => 'A cancelled order cannot be marked delivered.',
            ]);
        }

        if (! $data->delivered_at) {
            $data->update([
                'delivered_at' => now(),
                'delivered_by_id' => auth()->user()->id,
            ]);
        }

        return [
            'data' => new SalesOrderResource($data->fresh(['items', 'customer', 'status', 'arInvoices'])),
            'message' => 'Delivery recorded!',
            'info' => 'This order is marked delivered.',
            'status' => true,
        ];
    }
```

- [ ] **Step 6: Wire the action and its validation**

In `app/Http/Controllers/Modules/SalesOrderController.php`, inside `update()`'s switch, above `case 'adjustment':`:

```php
                    case 'mark-delivered':
                        $this->authorizePermission('sales', 'sales_orders', 'encoder');
                        $request->merge(['id' => $id]);

                        return $this->sales_order->markDelivered($request);
                    break;
```

In `app/Http/Requests/Modules/SalesOrderRequest.php`, beside the existing `$action` branches:

```php
        else if($action == 'mark-delivered'){
            return [
                'id' => 'nullable|exists:sales_orders,id',
            ];
        }
```

- [ ] **Step 7: Run the tests**

Run: `php artisan test --filter=MarkDeliveredTest`
Expected: PASS (3 tests)

- [ ] **Step 8: Run the whole suite**

Run: `php artisan test`
Expected: PASS, 699 or more

- [ ] **Step 9: Commit**

```bash
git add database/migrations app/Models/SalesOrder.php app/Services/Modules/SalesOrderClass.php app/Http/Controllers/Modules/SalesOrderController.php app/Http/Requests/Modules/SalesOrderRequest.php tests/Feature/Sales/MarkDeliveredTest.php
git commit -m "Record when a sales order reached the customer"
```

---

### Task 2: Accepted quantities

**Files:**
- Create: `database/migrations/2026_09_19_000003_create_sales_order_delivery_refusals_table.php`
- Create: `app/Models/SalesOrderDeliveryRefusal.php`
- Modify: `app/Services/Modules/SalesOrderClass.php` (extend `markDelivered()`)
- Modify: `app/Http/Requests/Modules/SalesOrderRequest.php` (accepted-quantity rules)
- Test: `tests/Feature/Sales/DeliveryAcceptanceTest.php`

**Interfaces:**
- Consumes: `SalesOrderClass::markDelivered()` from Task 1; `InventoryService::addStock($productId, $quantity, $reason, $batchCode)`; `JournalEntryService::recordSalesOrderUpdateEntries(SalesOrder $order)` and `recordSaleEntries(SalesOrder $order)`.
- Produces: table `sales_order_delivery_refusals`; model `SalesOrderDeliveryRefusal`; `SalesOrder::deliveryRefusals()` hasMany; `markDelivered()` accepting `accepted_quantities` as `[sales_order_item_id => int]` and `refusal_reasons` as `[sales_order_item_id => string]`.

- [ ] **Step 1: Write the failing test**

```php
    public function test_a_short_quantity_returns_stock_and_shrinks_the_invoice(): void
    {
        // payload() orders 2 @ 1500 = 3000 from BATCH-001
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::with('items')->firstOrFail();
        $item = $order->items->first();
        $stockBefore = (int) \App\Models\InventoryStocks::where('batch_code', 'BATCH-001')->sum('quantity');

        $this->actingAs($this->user)->put('/sales-orders/'.$order->id, [
            'action' => 'mark-delivered',
            'accepted_quantities' => [$item->id => 1],
            'refusal_reasons' => [$item->id => 'Customer only needed one'],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1500.0, (float) $order->fresh()->total_amount);
        $this->assertSame(1500.0, (float) \App\Models\ArInvoice::firstOrFail()->balance_due);
        $this->assertSame($stockBefore + 1, (int) \App\Models\InventoryStocks::where('batch_code', 'BATCH-001')->sum('quantity'));

        $refusal = \App\Models\SalesOrderDeliveryRefusal::firstOrFail();
        $this->assertSame(1, $refusal->refused_quantity);
        $this->assertSame('Customer only needed one', $refusal->reason);
    }

    public function test_accepting_everything_changes_nothing(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::with('items')->firstOrFail();

        $this->actingAs($this->user)->put('/sales-orders/'.$order->id, [
            'action' => 'mark-delivered',
            'accepted_quantities' => [$order->items->first()->id => 2],
        ])->assertSessionHasNoErrors();

        $this->assertSame(3000.0, (float) $order->fresh()->total_amount);
        $this->assertSame(0, \App\Models\SalesOrderDeliveryRefusal::count());
    }

    public function test_refusing_everything_empties_the_invoice_but_still_delivers(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::with('items')->firstOrFail();

        $this->actingAs($this->user)->put('/sales-orders/'.$order->id, [
            'action' => 'mark-delivered',
            'accepted_quantities' => [$order->items->first()->id => 0],
        ])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(0.0, (float) $order->total_amount);
        $this->assertNotNull($order->delivered_at);
        $this->assertNotSame('cancelled', optional($order->status)->slug);
    }

    public function test_accepting_more_than_was_ordered_is_rejected(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::with('items')->firstOrFail();

        $this->actingAs($this->user)->put('/sales-orders/'.$order->id, [
            'action' => 'mark-delivered',
            'accepted_quantities' => [$order->items->first()->id => 5],
        ])->assertSessionHasErrors();

        $this->assertNull($order->fresh()->delivered_at);
    }

    public function test_acceptance_closes_once_a_payment_exists(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::with('items')->firstOrFail();
        $invoice = \App\Models\ArInvoice::firstOrFail();
        $invoice->update(['amount_paid' => 500, 'balance_due' => 2500]);

        $this->actingAs($this->user)->put('/sales-orders/'.$order->id, [
            'action' => 'mark-delivered',
            'accepted_quantities' => [$order->items->first()->id => 1],
        ])->assertSessionHasErrors('accepted_quantities');
    }
```

Put these in `tests/Feature/Sales/DeliveryAcceptanceTest.php` with the same fixture as Task 1.

- [ ] **Step 2: Run the tests and watch them fail**

Run: `php artisan test --filter=DeliveryAcceptanceTest`
Expected: FAIL — `SalesOrderDeliveryRefusal` does not exist; quantities are ignored.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Goods the customer sent back at the door, before any money changed hands.
 * Separate from sales_return_history, which reverses a payment and refunds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_order_delivery_refusals', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('sales_order_id');
            $table->unsignedInteger('sales_order_item_id')->nullable();
            $table->unsignedInteger('product_id');
            $table->integer('ordered_quantity');
            $table->integer('accepted_quantity');
            $table->integer('refused_quantity');
            $table->string('batch_code')->nullable();
            $table->string('reason')->nullable();
            $table->unsignedInteger('recorded_by_id')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();

            $table->foreign('sales_order_id')->references('id')->on('sales_orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('recorded_by_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_delivery_refusals');
    }
};
```

Run: `php artisan migrate`

- [ ] **Step 4: Write the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrderDeliveryRefusal extends Model
{
    protected $fillable = [
        'sales_order_id', 'sales_order_item_id', 'product_id',
        'ordered_quantity', 'accepted_quantity', 'refused_quantity',
        'batch_code', 'reason', 'recorded_by_id', 'recorded_at',
    ];

    protected $casts = ['recorded_at' => 'datetime'];

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
```

Add to `app/Models/SalesOrder.php`:

```php
    public function deliveryRefusals()
    {
        return $this->hasMany(SalesOrderDeliveryRefusal::class);
    }
```

- [ ] **Step 5: Extend the service**

Inside `markDelivered()`, after the cancelled check and before the stamp:

```php
        $accepted = collect($request->accepted_quantities ?? [])
            ->mapWithKeys(fn ($qty, $itemId) => [(int) $itemId => (int) $qty]);
        $reasons = collect($request->refusal_reasons ?? [])
            ->mapWithKeys(fn ($reason, $itemId) => [(int) $itemId => (string) $reason]);

        if ($accepted->isNotEmpty() && ! $data->delivered_at) {
            $invoice = $data->arInvoices()->first();

            // Goods coming back after money changed hands is a return, refund
            // and all — not a doorstep refusal.
            if ($invoice && round((float) $invoice->amount_paid, 2) > 0) {
                throw ValidationException::withMessages([
                    'accepted_quantities' => 'This order already has a recorded payment. Use a sales return instead.',
                ]);
            }

            $this->applyAcceptedQuantities($data, $accepted, $reasons);
        }
```

And add the private method:

```php
    /**
     * Resize the order to what the customer kept: the refused stock goes back
     * to its own batch, the invoice drops to the accepted total, and the sale's
     * ledger entries are reversed and re-posted at that figure. Nothing is
     * refunded — no money has moved yet.
     */
    private function applyAcceptedQuantities(SalesOrder $data, $accepted, $reasons): void
    {
        $data->loadMissing('items');

        foreach ($data->items as $item) {
            if (! $accepted->has($item->id)) {
                continue;
            }

            $acceptedQty = $accepted->get($item->id);

            if ($acceptedQty < 0 || $acceptedQty > (int) $item->quantity) {
                throw ValidationException::withMessages([
                    'accepted_quantities' => 'Accepted quantity must be between 0 and the quantity ordered.',
                ]);
            }

            $refusedQty = (int) $item->quantity - $acceptedQty;

            if ($refusedQty === 0) {
                continue;
            }

            SalesOrderDeliveryRefusal::create([
                'sales_order_id' => $data->id,
                'sales_order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'ordered_quantity' => (int) $item->quantity,
                'accepted_quantity' => $acceptedQty,
                'refused_quantity' => $refusedQty,
                'batch_code' => $item->batch_code,
                'reason' => $reasons->get($item->id),
                'recorded_by_id' => auth()->user()->id,
                'recorded_at' => now(),
            ]);

            $this->inventoryService->addStock(
                $item->product_id,
                $refusedQty,
                'Refused on delivery - SO#'.$data->so_number,
                $item->batch_code
            );

            if ($acceptedQty === 0) {
                $item->delete();
            } else {
                $item->update(['quantity' => $acceptedQty]);
            }
        }

        $data->load('items');

        $totalAmount = $data->items->sum(fn ($item) => ((float) $item->price - (float) $item->discount_per_unit) * (int) $item->quantity);
        $totalDiscount = $data->items->sum(fn ($item) => (float) $item->discount_per_unit * (int) $item->quantity);

        $data->update(['total_amount' => $totalAmount, 'total_discount' => $totalDiscount]);

        if ($invoice = $data->arInvoices()->first()) {
            $invoice->update([
                'amount_due' => $totalAmount,
                'balance_due' => $totalAmount,
                'total_discount' => $totalDiscount,
            ]);
        }

        $this->journalEntryService->recordSalesOrderUpdateEntries($data);
        $this->journalEntryService->recordSaleEntries($data->load('items'));
    }
```

Add `use App\Models\SalesOrderDeliveryRefusal;` at the top of the file.

- [ ] **Step 6: Extend the request rules**

Replace the `mark-delivered` branch in `SalesOrderRequest`:

```php
        else if($action == 'mark-delivered'){
            return [
                'id' => 'nullable|exists:sales_orders,id',
                'accepted_quantities' => 'nullable|array',
                'accepted_quantities.*' => 'integer|min:0',
                'refusal_reasons' => 'nullable|array',
                'refusal_reasons.*' => 'nullable|string|max:255',
            ];
        }
```

- [ ] **Step 7: Run the tests**

Run: `php artisan test --filter=DeliveryAcceptanceTest`
Expected: PASS (5 tests)

- [ ] **Step 8: Run the whole suite**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 9: Commit**

```bash
git add database/migrations app/Models app/Services/Modules/SalesOrderClass.php app/Http/Requests/Modules/SalesOrderRequest.php tests/Feature/Sales/DeliveryAcceptanceTest.php
git commit -m "Resize a delivery to what the customer accepted"
```

---

### Task 3: Delivery on the order screen

**Files:**
- Create: `resources/js/Pages/Modules/Sales/Components/SalesOrders/Modals/MarkDelivered.vue`
- Modify: `resources/js/Pages/Modules/Sales/Components/SalesOrders/Index.vue` (row action + delivered badge)
- Modify: `app/Http/Resources/Modules/SalesOrderResource.php`

**Interfaces:**
- Consumes: the `mark-delivered` action from Tasks 1 and 2.
- Produces: resource keys `delivered_at` (formatted `M d, Y`), `delivered_at_raw` (`Y-m-d`), `delivered_by` (name or null).

- [ ] **Step 1: Add the resource fields**

In `app/Http/Resources/Modules/SalesOrderResource.php`, beside `delivery_date`:

```php
            'delivered_at' => $this->delivered_at?->format('M d, Y'),
            'delivered_at_raw' => $this->delivered_at?->format('Y-m-d'),
            'delivered_by' => $this->deliveredBy ? ($this->deliveredBy->employee?->fullname ?? $this->deliveredBy->name) : null,
```

- [ ] **Step 2: Build the modal**

Create `MarkDelivered.vue` following the modal conventions in CLAUDE.md: `.modal-overlay > .modal-container > .modal-header/.modal-body/.modal-footer`, buttons in the footer, and **no scoped styles for modal chrome**. It lists `order.items` with a number input per line defaulting to `item.quantity`, an optional reason input shown only when the number is below the ordered quantity, and submits:

```js
this.form = useForm({
    action: 'mark-delivered',
    accepted_quantities: {},
    refusal_reasons: {},
});
this.form.put(`/sales-orders/${this.order.id}`, { preserveScroll: true, onSuccess: () => { this.$emit('saved'); this.hide(); } });
```

- [ ] **Step 3: Wire the row action**

In the sales order list's action menu, add a "Mark Delivered" item shown when `!item.delivered_at` and the status is not `cancelled`, opening the modal; when `item.delivered_at`, show a plain `Delivered {{ item.delivered_at }}` label instead.

- [ ] **Step 4: Build and check the screen renders**

Run: `npm run build`
Then load the Sales Orders tab and open the modal on an undelivered order. A Vue template that renders nothing is invisible to compile checks — look at the screen.

- [ ] **Step 5: Run the whole suite**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add resources/js public/build app/Http/Resources/Modules/SalesOrderResource.php
git commit -m "Mark a delivery from the sales order list"
```

---

### Task 4: Who is holding the money

**Files:**
- Create: `database/migrations/2026_09_19_000004_add_held_by_to_receipts_table.php`
- Modify: `app/Models/Receipt.php`
- Modify: `app/Services/Modules/ArInvoiceClass.php` (set the holder when a receipt is written)
- Modify: `app/Services/Modules/RemittanceClass.php` (clear it on remit)
- Test: `tests/Feature/Sales/ReceiptCustodyTest.php`

**Interfaces:**
- Consumes: `Receipt`, `ArInvoiceClass::applyPaymentToInvoice()`, `RemittanceClass::save()`.
- Produces: `receipts.held_by_employee_id` (unsignedInteger, nullable, FK employees); `Receipt::heldBy()`.

- [ ] **Step 1: Write the failing test**

```php
    public function test_a_field_collection_is_held_by_the_orders_driver(): void
    {
        // Build a COD order whose driver_id is a known employee, record a
        // payment against its invoice over HTTP, then:
        $this->assertSame($driver->id, \App\Models\Receipt::firstOrFail()->held_by_employee_id);
    }

    public function test_remitting_clears_the_holder(): void
    {
        // After RemittanceClass::save() carries the receipt:
        $this->assertNull(\App\Models\Receipt::firstOrFail()->held_by_employee_id);
    }
```

- [ ] **Step 2: Run and watch it fail**

Run: `php artisan test --filter=ReceiptCustodyTest`
Expected: FAIL — unknown column `held_by_employee_id`.

- [ ] **Step 3: Write the migration**

```php
        Schema::table('receipts', function (Blueprint $table) {
            $table->unsignedInteger('held_by_employee_id')->nullable()->after('remittance_id');
            $table->foreign('held_by_employee_id')->references('id')->on('employees')->nullOnDelete();
        });
```

- [ ] **Step 4: Set and clear the holder**

Add `'held_by_employee_id'` to `Receipt::$fillable` and a `heldBy()` belongsTo `Employee`. Where `ArInvoiceClass` creates a receipt, set `'held_by_employee_id' => optional($ar_invoice->sales_order)->driver_id ?? optional($ar_invoice->sales_order)->sales_rep_id`. Where `RemittanceClass::save()` attaches receipts to the remittance, also set `held_by_employee_id` to null.

- [ ] **Step 5: Stamp the delivery when the money comes back**

A COD collection proves the goods went out, so in the same place `ArInvoiceClass` writes a receipt, add:

```php
        // Money cannot come back unless the goods went out. Only fills a gap —
        // an explicitly recorded delivery date always wins.
        $order = $ar_invoice->sales_order;
        if ($order && ! $order->delivered_at && SalesOrder::isCod($order->payment_mode)) {
            $order->update([
                'delivered_at' => now(),
                'delivered_by_id' => auth()->id(),
            ]);
        }
```

Cover it with:

```php
    public function test_recording_a_cod_collection_stamps_the_delivery(): void
    {
        // Record a payment against an unstamped COD order's invoice.
        $this->assertNotNull(SalesOrder::firstOrFail()->delivered_at);
    }

    public function test_an_existing_delivery_stamp_is_not_moved_by_a_collection(): void
    {
        $order->update(['delivered_at' => now()->subDays(3)]);
        // Record the payment, then:
        $this->assertTrue($order->fresh()->delivered_at->isSameDay(now()->subDays(3)));
    }
```

- [ ] **Step 6: Run the tests, then the suite, then commit**

```bash
php artisan test --filter=ReceiptCustodyTest
php artisan test
git add -A && git commit -m "Record who is holding a collected receipt"
```

---

### Task 5: Confirming a field bank transfer

**Files:**
- Modify: `app/Services/Modules/ArInvoiceClass.php` (`confirmCheck()` → `confirmReceipt()`)
- Modify: `app/Http/Controllers/Modules/ReceiptController.php`
- Modify: `routes/web.php:50`
- Test: `tests/Feature/Sales/FieldTransferConfirmationTest.php`

**Interfaces:**
- Consumes: `ArInvoiceClass::applyPaymentToInvoice()`, `JournalEntryService::recordCheckCollectionEntry()`.
- Produces: `ArInvoiceClass::confirmReceipt($receiptId, $bankName, $checkDate = null)`, keeping `confirmCheck()` as a thin alias so existing callers and tests keep working.

- [ ] **Step 1: Write the failing test**

```php
    public function test_a_field_transfer_leaves_the_invoice_unpaid_until_confirmed(): void
    {
        // Record a Bank Transfer payment against a COD order's invoice.
        $this->assertSame(3000.0, (float) \App\Models\ArInvoice::firstOrFail()->balance_due);
    }

    public function test_confirming_a_field_transfer_releases_it(): void
    {
        $this->actingAs($this->user)
            ->put('/receipts/'.$receipt->id.'/confirm-check', ['bank_name' => 'BPI'])
            ->assertSessionHasNoErrors();

        $this->assertSame(0.0, (float) \App\Models\ArInvoice::firstOrFail()->balance_due);
    }

    public function test_a_counter_transfer_still_settles_immediately(): void
    {
        // A Cash-sale order settled by Bank Transfer is unaffected.
        $this->assertSame(0.0, (float) \App\Models\ArInvoice::firstOrFail()->balance_due);
    }
```

- [ ] **Step 2: Run and watch it fail**

Run: `php artisan test --filter=FieldTransferConfirmationTest`
Expected: FAIL — a transfer settles immediately today.

- [ ] **Step 3: Generalise the confirmation**

In `ArInvoiceClass`, rename `confirmCheck()` to `confirmReceipt()` and replace its `strcasecmp(..., 'Check')` guard with: a cheque, or a Bank Transfer whose receipt belongs to an on-account sales order (`SalesOrder::isOnAccount($receipt->arInvoice->sales_order->payment_mode)`). Leave `confirmCheck()` behind as `return $this->confirmReceipt(...)`.

Where payments are split and applied, exclude such transfers from `$immediateTotal` exactly as cheques are excluded today.

- [ ] **Step 4: Run the tests, then the suite, then commit**

```bash
php artisan test --filter=FieldTransferConfirmationTest
php artisan test
git add -A && git commit -m "Hold a field-collected bank transfer until the bank confirms it"
```

---

### Task 6: Cash in the field

**Files:**
- Modify: `app/Services/Modules/RemittanceClass.php` (add `fieldCollections()`)
- Modify: `app/Http/Controllers/Modules/RemittanceController.php` (`?option=field-collections`)
- Create: `resources/js/Pages/Modules/Sales/Components/Remittances/FieldCollections.vue`
- Test: `tests/Feature/Sales/FieldCollectionsListTest.php`

**Interfaces:**
- Consumes: `Receipt` with `held_by_employee_id` from Task 4.
- Produces: `RemittanceClass::fieldCollections($request)` returning rows of `['holder', 'receipt_number', 'so_number', 'customer', 'amount', 'payment_mode', 'collected_at', 'days_out', 'is_external', 'confirmed']`, sorted oldest first.

- [ ] **Step 1: Write the failing test**

```php
    public function test_it_lists_pending_receipts_by_holder_oldest_first(): void
    {
        // Two pending receipts held by different drivers, three days apart.
        $rows = (new RemittanceClass(...))->fieldCollections(new Request());

        $this->assertSame('Ana Cruz', $rows->first()['holder']);
        $this->assertSame(3, $rows->first()['days_out']);
    }

    public function test_a_remitted_receipt_drops_off_the_list(): void
    {
        $this->assertCount(0, (new RemittanceClass(...))->fieldCollections(new Request()));
    }
```

- [ ] **Step 2: Run and watch it fail**

Run: `php artisan test --filter=FieldCollectionsListTest`
Expected: FAIL — `fieldCollections()` undefined.

- [ ] **Step 3: Write the query**

```php
    /**
     * Money collected but not yet turned in, by the person carrying it. A city
     * run clears the same afternoon, so anything with days on it is worth a
     * phone call — and out-of-town orders (SO-EXT) legitimately take longer.
     */
    public function fieldCollections($request)
    {
        return Receipt::with(['heldBy', 'customer', 'arInvoice.sales_order'])
            ->whereNull('remittance_id')
            ->whereHas('status', fn ($q) => $q->where('slug', 'pending'))
            ->whereNotNull('held_by_employee_id')
            ->orderBy('receipt_date')
            ->get()
            ->map(function ($receipt) {
                $order = optional($receipt->arInvoice)->sales_order;

                return [
                    'holder' => optional($receipt->heldBy)->fullname ?? 'Unassigned',
                    'receipt_number' => $receipt->receipt_number,
                    'so_number' => optional($order)->so_number,
                    'customer' => optional($receipt->customer)->name,
                    'amount' => (float) $receipt->amount_paid,
                    'payment_mode' => $receipt->payment_mode,
                    'collected_at' => optional($receipt->receipt_date)->toDateString(),
                    'days_out' => $receipt->receipt_date
                        ? (int) $receipt->receipt_date->startOfDay()->diffInDays(now()->startOfDay())
                        : null,
                    'is_external' => str_starts_with((string) optional($order)->so_number, 'SO-EXT'),
                    'confirmed' => ! is_null($receipt->confirmed_at)
                        || ! in_array(strtolower((string) $receipt->payment_mode), ['check', 'cheque', 'bank transfer'], true),
                ];
            })
            ->values();
    }
```

- [ ] **Step 4: Build the screen**

A table under Sales → Remittances: holder, days out, SO number with an EXT marker, customer, amount, payment mode, confirmed state. Group by holder with a subtotal per person.

- [ ] **Step 5: Run the tests, build, then commit**

```bash
php artisan test
npm run build
git add -A && git commit -m "Show what each driver and rep is still holding"
```

---

## Deployment

After all six tasks, production needs four migrations, in this order:

```
2026_09_19_000001_add_shipping_and_delivery_dates_to_sales_orders_table
2026_09_19_000002_add_delivered_at_to_sales_orders_table
2026_09_19_000003_create_sales_order_delivery_refusals_table
2026_09_19_000004_add_held_by_to_receipts_table
```

Deploys here do not run migrations. Push, wait for the auto-deploy, then run `php artisan migrate --force` over SSH **with the user's explicit approval in that message**, having first checked `php artisan migrate:status` — production has been several migrations behind before. Between the deploy and the migration, sales order pages error on the missing columns, so the two steps belong back to back.
