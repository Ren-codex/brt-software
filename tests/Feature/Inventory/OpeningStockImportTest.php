<?php

namespace Tests\Feature\Inventory;

use App\Http\Resources\ReceivedStockResource;
use App\Models\InventoryStocks;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\ListBrand;
use App\Models\ListPackaging;
use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\ListSupplier;
use App\Models\ListUnit;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\ReceivedStock;
use App\Models\User;
use App\Models\UserRole;
use App\Services\ReceivedStockService;
use App\Services\System\PurchaseOrder\StockReturnClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Opening stock from the sheet: one row per batch, each traceable to its
 * supplier, owing nothing, and on the books as inventory against opening equity.
 */
class OpeningStockImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $csv;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['pending' => 'Pending', 'completed' => 'Completed'] as $slug => $name) {
            ListStatus::firstOrCreate(['slug' => $slug], ['name' => $name, 'text_color' => '#fff', 'bg_color' => '#333']);
        }

        foreach ([['Purchase Order', 'purchase_order', 'PO'], ['Received No', 'received_no', 'REC'], ['Batch Code', 'batch_code', 'B'], ['Stock Return', 'stock_return', 'SR']] as [$name, $slug, $prefix]) {
            DB::table('series')->insert([
                'name' => $name, 'slug' => $slug, 'prefix' => $prefix, 'starting_value' => 1, 'max_digit' => 6,
                'current_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->admin = User::factory()->create(['username' => 'superadmin01']);
        $role = ListRole::create(['name' => 'Super Admin', 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        UserRole::create(['user_id' => $this->admin->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $this->admin->id]);

        // The production shape the import meets: brands half cleaned, one product
        // already entered, some suppliers under their full names.
        $kg = ListUnit::create(['name' => 'Kg']);
        $sack = ListPackaging::create(['name' => 'Sack']);
        foreach (['Princess Bea 10kg', 'Princess Bea Red 25kg', 'Princess Bea 5kg', 'Master Chef Aroma 25kg', 'Master Chef Aroma 10kg', 'Magnolia AAA Yellow 25kg', 'Princess Bea Yellow'] as $brand) {
            ListBrand::create(['name' => $brand]);
        }
        Product::create([
            'code' => 'PBYS25001', 'brand_id' => ListBrand::where('name', 'Princess Bea Yellow')->value('id'),
            'weight' => 25, 'unit_id' => $kg->id, 'packaging_id' => $sack->id, 'is_active' => 1,
        ]);
        foreach (['Sodatrade Corporation', 'Renzy International Marketing'] as $supplier) {
            ListSupplier::create(['name' => $supplier, 'address' => 'A', 'contact_person' => 'P', 'contact_number' => '1', 'email' => 'x@y.z', 'is_active' => 1, 'is_blacklisted' => 0]);
        }

        $this->csv = tempnam(sys_get_temp_dir(), 'opening');
        $this->writeSheet([
            ['SODATRADE CORP.', 'PRINCESS BEA RED 25KG', '488', '₱1,270.00', '₱1,350.00', '₱1,370.00'],
            ['RBS', 'PRINCESS BEA RED 25KG', '1200', '₱1,250.00', '₱1,350.00', '₱1,370.00'],
            ['RBS', 'PRINCESS BEA RED 25KG', '2400', '₱1,260.00', '₱1,350.00', '₱1,370.00'],
            ['RBS', 'PRINCESS BEA RED10KG', '96', '₱500.00', '₱560.00', '₱570.00'],
            ['TRISNA COMPANY INC.', 'MASTER CHEF AROMA 10KG', '13', '₱500.00', '₱560.00', '₱570.00'],
            ['RENZY MARKETING INC.', 'MAGNOLIA AAA YELLOW', '619', '₱1,020.00', '₱1,070.00', '₱1,090.00'],
            ['SODATRADE CORP.', 'PRINCESS BEA YELLOW 25KG', '272', '₱1,020.00', '₱1,070.00', '₱1,090.00'],
            ['SODATRADE CORP.', 'PLANTERS YELLOW 25KG', '0', '', '₱1,130.00', '₱1,150.00'],
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->csv);
        parent::tearDown();
    }

    private function writeSheet(array $rows): void
    {
        $handle = fopen($this->csv, 'w');
        fputcsv($handle, ['PRODUCT SUPPLIER', 'PRODUCT NAME', 'BEG. STOCKS', 'Cost', 'WHOLESALE PRICE', 'RETAIL PRICE']);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
    }

    private function import(array $options = []): int
    {
        return $this->artisan('inventory:import-opening-stock', ['path' => $this->csv] + $options)->run();
    }

    public function test_a_dry_run_saves_nothing(): void
    {
        $this->assertSame(0, $this->import());

        $this->assertSame(0, InventoryStocks::count());
        $this->assertSame(0, PurchaseOrder::count());
        $this->assertSame(1, Product::count());
        $this->assertSame(7, ListBrand::count());
        $this->assertSame(1, (int) DB::table('series')->where('slug', 'batch_code')->value('starting_value'), 'Series numbers roll back too.');
    }

    public function test_every_row_becomes_its_own_batch(): void
    {
        $this->assertSame(0, $this->import(['--apply' => true]));

        // 8 rows, one with no stock.
        $this->assertSame(7, InventoryStocks::count());

        $red25 = Product::where('code', 'PBRS25001')->firstOrFail();
        $batches = InventoryStocks::where('product_id', $red25->id)->orderBy('quantity')->get();
        $this->assertCount(3, $batches, 'Three rows of one product are three batches under one product.');
        $this->assertEquals([488, 1200, 2400], $batches->pluck('quantity')->all());
        $this->assertEquals([1270.0, 1250.0, 1260.0], $batches->map(fn ($b) => (float) $b->unit_cost)->all());
        $this->assertEquals(1350.0, (float) $batches[0]->wholesale_price);
        $this->assertEquals(1370.0, (float) $batches[0]->retail_price);
    }

    public function test_brands_lose_their_weight_and_sizes_of_one_rice_merge(): void
    {
        $this->import(['--apply' => true]);

        $names = ListBrand::orderBy('name')->pluck('name')->all();

        $this->assertContains('Princess Bea Red', $names);
        $this->assertContains('Master Chef Aroma', $names);
        $this->assertContains('Magnolia AAA Yellow', $names, 'Existing casing is kept.');
        foreach ($names as $name) {
            $this->assertDoesNotMatchRegularExpression('/\d+\s*kg$/i', $name);
        }
        $this->assertSame(1, ListBrand::where('name', 'Princess Bea Red')->count(), 'Princess Bea 10kg, 5kg and Red 25kg are one brand.');

        $redBrand = ListBrand::where('name', 'Princess Bea Red')->value('id');
        $this->assertEquals([10, 25], Product::where('brand_id', $redBrand)->orderBy('weight')->pluck('weight')->all());
    }

    public function test_an_existing_product_is_reused_not_duplicated(): void
    {
        $this->import(['--apply' => true]);

        $this->assertSame(1, Product::where('code', 'like', 'PBYS25%')->count());
        $this->assertSame(272, (int) InventoryStocks::where('product_id', Product::where('code', 'PBYS25001')->value('id'))->sum('quantity'));
    }

    public function test_a_row_with_no_stock_still_creates_its_product(): void
    {
        $this->import(['--apply' => true]);

        $planters = Product::where('code', 'PYS25001')->firstOrFail();
        $this->assertSame(0, InventoryStocks::where('product_id', $planters->id)->count());
    }

    public function test_suppliers_match_by_alias_and_missing_ones_are_created(): void
    {
        $this->import(['--apply' => true]);

        $this->assertSame(1, ListSupplier::where('name', 'Sodatrade Corporation')->count(), 'SODATRADE CORP. is the existing supplier.');
        $this->assertSame(1, ListSupplier::where('name', 'Renzy International Marketing')->count());
        $this->assertTrue(ListSupplier::where('name', 'RBS')->exists());
        $this->assertTrue(ListSupplier::where('name', 'Trisna Company Inc.')->exists());

        // One opening purchase order and receipt per supplier with stock.
        $this->assertSame(4, PurchaseOrder::count());
        $this->assertSame(4, ReceivedStock::where('payment_mode', 'Opening Balance')->count());
    }

    public function test_opening_stock_is_on_the_books_and_owes_nothing(): void
    {
        $this->import(['--apply' => true]);

        $expected = 488 * 1270 + 1200 * 1250 + 2400 * 1260 + 96 * 500 + 13 * 500 + 619 * 1020 + 272 * 1020;

        $net = JournalEntryLine::with('account')->get()
            ->groupBy(fn ($l) => $l->account->code)
            ->map(fn ($g) => round($g->sum(fn ($l) => $l->line_type === 'debit' ? (float) $l->amount : -(float) $l->amount), 2))
            ->filter(fn ($v) => abs($v) > 0.001)
            ->sortKeys()
            ->all();

        $this->assertEquals(['1200' => (float) $expected, '3900' => (float) -$expected], $net, 'Inventory against opening equity -- no payable, no cash.');
        $this->assertSame(4, JournalEntry::where('entry_type', 'opening_entry')->count());

        foreach (ReceivedStock::with('items')->get() as $receipt) {
            $resource = (new ReceivedStockResource($receipt->load('payments')))->toArray(request());
            $this->assertEquals(0, $resource['remaining_balance'], "{$receipt->received_no} must not appear on Accounts Payable.");
        }
    }

    public function test_imported_stock_can_be_returned_to_its_supplier(): void
    {
        // Returns validate received quantity AND real inventory behind the line --
        // the chain the import exists to build.
        $this->import(['--apply' => true]);
        $this->actingAs($this->admin);

        $poItem = PurchaseOrder::whereHas('supplier', fn ($q) => $q->where('name', 'RBS'))->firstOrFail()
            ->items()->where('quantity', 1200)->firstOrFail();

        $request = new Request();
        $request->merge(['reason' => 'Damaged', 'items' => [['po_item_id' => $poItem->id, 'quantity' => 5]]]);

        $result = app(StockReturnClass::class)->store($request);

        $this->assertTrue($result['status']);
    }

    public function test_voiding_an_opening_receipt_undoes_its_stock_and_entry(): void
    {
        $this->import(['--apply' => true]);
        $this->actingAs($this->admin);

        $receipt = ReceivedStock::whereHas('supplier', fn ($q) => $q->where('name', 'Trisna Company Inc.'))->firstOrFail();
        app(ReceivedStockService::class)->void($receipt->id, 'Wrong supplier');

        $trisnaNet = JournalEntryLine::whereIn('journal_entry_id', JournalEntry::where('source_type', ReceivedStock::class)->where('source_id', $receipt->id)->pluck('id'))
            ->get()->sum(fn ($l) => $l->line_type === 'debit' ? (float) $l->amount : -(float) $l->amount);

        $this->assertEquals(0.0, round($trisnaNet, 2));
    }

    public function test_a_row_that_looks_wrong_blocks_apply_unless_allowed(): void
    {
        $this->writeSheet([
            ['TRISNA COMPANY INC.', 'MASTER CHEF AROMA 25KG', '3000', '₱1,270.00', '₱13,330.00', '₱1,350.00'],
        ]);

        $this->assertSame(1, $this->import(['--apply' => true]));
        $this->assertSame(0, InventoryStocks::count());

        $this->assertSame(0, $this->import(['--apply' => true, '--allow-anomalies' => true]));
        $this->assertSame(1, InventoryStocks::count());
    }
}
