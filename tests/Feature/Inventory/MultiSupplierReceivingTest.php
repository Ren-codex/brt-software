<?php

namespace Tests\Feature\Inventory;

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
use App\Models\ReceivedStock;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A purchase order can mix suppliers, but a receipt — and the bill it raises —
 * belongs to one supplier. Receiving one supplier's lines bills that supplier
 * and leaves the other supplier's lines pending for a separate receipt; the
 * order is marked complete only once every supplier has been received.
 */
class MultiSupplierReceivingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private int $supplierA;
    private int $supplierB;

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

        $this->supplierA = $this->supplier('Supplier A');
        $this->supplierB = $this->supplier('Supplier B');

        foreach ([['Received No', 'received_no', 'RS-'], ['Batch Code', 'batch_code', 'B-']] as [$name, $slug, $prefix]) {
            \DB::table('series')->insert([
                'name' => $name, 'slug' => $slug, 'prefix' => $prefix,
                'current_date' => now()->toDateString(), 'starting_value' => 1, 'max_digit' => 4,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->fundCash(50000);
    }

    private function supplier(string $name): int
    {
        return \DB::table('list_suppliers')->insertGetId([
            'name' => $name, 'address' => 'Addr', 'contact_person' => 'P',
            'contact_number' => '0900', 'email' => uniqid().'@t.com', 'tin' => '000',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function product(string $code): Product
    {
        return Product::create([
            'code' => $code, 'weight' => 25,
            'unit_id' => ListUnit::firstOrCreate(['name' => 'Sack'], ['is_active' => 1])->id,
            'brand_id' => ListBrand::firstOrCreate(['name' => $code.' Brand'], ['is_active' => 1])->id,
            'is_active' => 1, 'minimum_stock' => 1,
        ]);
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
            'journal_number' => 'JE-MS-'.uniqid(), 'entry_date' => now()->toDateString(),
            'entry_type' => 'manual', 'status' => 'posted', 'posted_at' => now(),
        ]);
        JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $cash->id, 'line_type' => 'debit', 'amount' => $amount, 'line_order' => 1]);
        JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $equity->id, 'line_type' => 'credit', 'amount' => $amount, 'line_order' => 2]);
    }

    /** A two-supplier PO: line 1 from A, line 2 from B. */
    private function makeMixedPo(): array
    {
        $rice = $this->product('RICE');
        $corn = $this->product('CORN');

        $po = PurchaseOrder::create([
            'po_date' => now()->toDateString(), 'total_amount' => 2000,
            'status_id' => ListStatus::where('slug', 'pending')->first()->id,
            'supplier_id' => $this->supplierA, 'created_by_id' => $this->user->id,
        ]);
        $itemA = PurchaseOrderItem::create([
            'po_id' => $po->id, 'product_id' => $rice->id, 'supplier_id' => $this->supplierA,
            'quantity' => 10, 'unit_cost' => 100, 'total_cost' => 1000,
        ]);
        $itemB = PurchaseOrderItem::create([
            'po_id' => $po->id, 'product_id' => $corn->id, 'supplier_id' => $this->supplierB,
            'quantity' => 10, 'unit_cost' => 100, 'total_cost' => 1000,
        ]);

        return [$po, $itemA, $itemB];
    }

    private function receive(int $poId, int $supplierId, PurchaseOrderItem $line): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user)->postJson('/received-stocks', [
            'po_id' => $poId,
            'supplier_id' => $supplierId,
            'received_date' => now()->toDateString(),
            'payment_mode' => 'Split',
            'payment_lines' => [['payment_mode' => 'Cash on Hand', 'payment_amount' => 1000]],
            'items' => [[
                'product_id' => $line->product_id, 'product_name' => 'Line', 'quantity' => $line->quantity,
                'unit_cost' => $line->unit_cost, 'total_cost' => $line->total_cost,
                'to_received_quantity' => $line->quantity, 'po_item_id' => $line->id,
                'retail_price' => 150, 'wholesale_price' => 120, 'expiration_date' => null,
            ]],
        ]);
    }

    public function test_receiving_one_supplier_bills_only_them_and_leaves_the_other_pending(): void
    {
        [$po, $itemA, $itemB] = $this->makeMixedPo();

        $this->receive($po->id, $this->supplierA, $itemA)->assertSuccessful();

        // The receipt (and its payable) belongs to Supplier A.
        $receipt = ReceivedStock::where('po_id', $po->id)->latest('id')->firstOrFail();
        $this->assertSame($this->supplierA, $receipt->supplier_id);

        // A's line is received; B's is untouched and still pending.
        $this->assertSame('received', $itemA->fresh()->status);
        $this->assertSame('pending', $itemB->fresh()->status);

        // The order is not complete while Supplier B is still outstanding.
        $this->assertNotSame(
            ListStatus::where('slug', 'completed')->first()->id,
            $po->fresh()->status_id
        );
    }

    public function test_receiving_the_second_supplier_completes_the_order(): void
    {
        [$po, $itemA, $itemB] = $this->makeMixedPo();

        $this->receive($po->id, $this->supplierA, $itemA)->assertSuccessful();
        $this->receive($po->id, $this->supplierB, $itemB)->assertSuccessful();

        // Two separate receipts, one per supplier.
        $receipts = ReceivedStock::where('po_id', $po->id)->get();
        $this->assertCount(2, $receipts);
        $this->assertEqualsCanonicalizing(
            [$this->supplierA, $this->supplierB],
            $receipts->pluck('supplier_id')->all()
        );

        // Now every line is received, so the order is complete.
        $this->assertSame('received', $itemA->fresh()->status);
        $this->assertSame('received', $itemB->fresh()->status);
        $this->assertSame(
            ListStatus::where('slug', 'completed')->first()->id,
            $po->fresh()->status_id
        );
    }
}
