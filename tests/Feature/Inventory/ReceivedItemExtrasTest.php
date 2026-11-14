<?php

namespace Tests\Feature\Inventory;

use App\Http\Resources\ReceivedStockResource;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\ListBrand;
use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\ListUnit;
use App\Models\Module;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReceivedItem;
use App\Models\ReceivedStock;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The optional per-item Tracking Expense and Remarks entered while receiving
 * stock must survive the whole path — form → request → validated() → service →
 * received_items — and come back out through the resource for the View screen.
 */
class ReceivedItemExtrasTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private int $supplierId;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        foreach (['pending', 'completed', 'unpaid'] as $slug) {
            ListStatus::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug), 'text_color' => '#fff', 'bg_color' => '#333']);
        }

        $this->user = User::factory()->create();
        $role = ListRole::create(['name' => 'Receiver', 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        UserRole::create(['user_id' => $this->user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $this->user->id]);
        $inventory = Module::where('key', 'inventory')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $inventory->id,
            'submodule_id' => $inventory->submodules()->where('key', 'receiving')->firstOrFail()->id,
            'access_level' => 'encoder',
        ]);
        $accounting = Module::where('key', 'accounting')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $accounting->id,
            'submodule_id' => $accounting->submodules()->where('key', 'accounts_payable')->firstOrFail()->id,
            'access_level' => 'encoder',
        ]);

        $this->supplierId = \DB::table('list_suppliers')->insertGetId([
            'name' => 'Extras Supplier', 'address' => 'Addr', 'contact_person' => 'P',
            'contact_number' => '09000000000', 'email' => 'x@test.com', 'tin' => '000',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->product = Product::create([
            'code' => 'EX-'.uniqid(), 'weight' => 25,
            'unit_id' => ListUnit::create(['name' => 'Sack'])->id,
            'brand_id' => ListBrand::create(['name' => 'Brand'])->id,
            'is_active' => 1, 'minimum_stock' => 1,
        ]);

        foreach ([['Received No', 'received_no', 'RS-'], ['Batch Code', 'batch_code', 'B-']] as [$name, $slug, $prefix]) {
            \DB::table('series')->insert([
                'name' => $name, 'slug' => $slug, 'prefix' => $prefix,
                'current_date' => now()->toDateString(), 'starting_value' => 1, 'max_digit' => 4,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->fundCash(50000);
    }

    private function fundCash(float $amount): void
    {
        $cash = Account::firstOrCreate(['code' => '1000'], [
            'slug' => 'acct-1000', 'name' => 'Cash', 'type' => 'asset',
            'subtype' => 'current_asset', 'is_active' => true,
        ]);
        $equity = Account::firstOrCreate(['code' => '3900'], [
            'slug' => 'opening-balance-equity', 'name' => 'Opening Balance Equity',
            'type' => 'equity', 'subtype' => 'opening_balance', 'is_active' => true,
        ]);
        $entry = JournalEntry::create([
            'journal_number' => 'JE-EX-'.uniqid(), 'entry_date' => now()->toDateString(),
            'entry_type' => 'manual', 'status' => 'posted', 'posted_at' => now(),
        ]);
        JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $cash->id, 'line_type' => 'debit', 'amount' => $amount, 'line_order' => 1]);
        JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $equity->id, 'line_type' => 'credit', 'amount' => $amount, 'line_order' => 2]);
    }

    public function test_tracking_expense_and_remarks_persist_and_come_back_out(): void
    {
        $po = PurchaseOrder::create([
            'po_date' => now()->toDateString(), 'total_amount' => 10000,
            'status_id' => ListStatus::where('slug', 'pending')->first()->id,
            'supplier_id' => $this->supplierId, 'created_by_id' => $this->user->id,
        ]);
        $poItem = PurchaseOrderItem::create([
            'po_id' => $po->id, 'product_id' => $this->product->id,
            'quantity' => 20, 'unit_cost' => 500, 'total_cost' => 10000,
        ]);

        $this->actingAs($this->user)->postJson('/received-stocks', [
            'po_id' => $po->id,
            'supplier_id' => $this->supplierId,
            'received_date' => now()->toDateString(),
            'payment_mode' => 'Split',
            'payment_lines' => [['payment_mode' => 'Cash on Hand', 'payment_amount' => 10000]],
            'items' => [[
                'product_id' => $this->product->id, 'product_name' => 'Test', 'quantity' => 20,
                'unit_cost' => 500, 'total_cost' => 10000, 'to_received_quantity' => 20,
                'po_item_id' => $poItem->id, 'retail_price' => 600, 'wholesale_price' => 550,
                'tracking_expense' => 250.50, 'remarks' => 'Hauling via truck',
                'expiration_date' => null,
            ]],
        ])->assertSuccessful();

        // Persisted on the received item.
        $item = ReceivedItem::where('po_item_id', $poItem->id)->firstOrFail();
        $this->assertEquals(250.50, (float) $item->tracking_expense);
        $this->assertSame('Hauling via truck', $item->remarks);

        // Exposed by the resource the View modal reads.
        $stock = ReceivedStock::with('items.product')->find($item->received_id);
        $payload = (new ReceivedStockResource($stock))->toArray(request());
        $this->assertEquals(250.50, $payload['items'][0]['tracking_expense']);
        $this->assertSame('Hauling via truck', $payload['items'][0]['remarks']);
    }

    public function test_the_extras_are_optional(): void
    {
        $po = PurchaseOrder::create([
            'po_date' => now()->toDateString(), 'total_amount' => 10000,
            'status_id' => ListStatus::where('slug', 'pending')->first()->id,
            'supplier_id' => $this->supplierId, 'created_by_id' => $this->user->id,
        ]);
        $poItem = PurchaseOrderItem::create([
            'po_id' => $po->id, 'product_id' => $this->product->id,
            'quantity' => 20, 'unit_cost' => 500, 'total_cost' => 10000,
        ]);

        $this->actingAs($this->user)->postJson('/received-stocks', [
            'po_id' => $po->id,
            'supplier_id' => $this->supplierId,
            'received_date' => now()->toDateString(),
            'payment_mode' => 'Split',
            'payment_lines' => [['payment_mode' => 'Cash on Hand', 'payment_amount' => 10000]],
            'items' => [[
                'product_id' => $this->product->id, 'product_name' => 'Test', 'quantity' => 20,
                'unit_cost' => 500, 'total_cost' => 10000, 'to_received_quantity' => 20,
                'po_item_id' => $poItem->id, 'retail_price' => 600, 'wholesale_price' => 550,
                'expiration_date' => null,
            ]],
        ])->assertSuccessful();

        $item = ReceivedItem::where('po_item_id', $poItem->id)->firstOrFail();
        $this->assertNull($item->tracking_expense);
        $this->assertNull($item->remarks);
    }
}
