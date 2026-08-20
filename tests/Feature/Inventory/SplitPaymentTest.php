<?php

namespace Tests\Feature\Inventory;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\ListBrand;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\UserRole;
use App\Models\ListStatus;
use App\Models\ListUnit;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReceivedItem;
use App\Models\ReceivedStock;
use App\Models\User;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Paying a supplier with more than one method in a single transaction.
 *
 * Each line becomes its own payment row and its own journal entry against its
 * own funding source, so cash, each bank account and check clearing move by
 * exactly their own amount. See docs/superpowers/specs/2026-08-21-split-payments-design.md
 */
class SplitPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ReceivedStock $received;
    private BankAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        foreach (['pending', 'completed', 'unpaid'] as $slug) {
            ListStatus::firstOrCreate(['slug' => $slug], [
                'name' => ucfirst($slug), 'text_color' => '#fff', 'bg_color' => '#333',
            ]);
        }

        $this->user = User::factory()->create();
        $role = ListRole::create(['name' => 'Receiver', 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        UserRole::create(['user_id' => $this->user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $this->user->id]);
        $module = Module::where('key', 'inventory')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $module->submodules()->where('key', 'receiving')->firstOrFail()->id,
            'access_level' => 'encoder',
        ]);

        $supplierId = \DB::table('list_suppliers')->insertGetId([
            'name' => 'Split Supplier', 'address' => 'Addr', 'contact_person' => 'P',
            'contact_number' => '09000000000', 'email' => 's@test.com', 'tin' => '000',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $unit = ListUnit::create(['name' => 'Sack']);
        $brand = ListBrand::create(['name' => 'Brand']);
        $product = Product::create([
            'code' => 'SP-'.uniqid(), 'weight' => 25, 'unit_id' => $unit->id,
            'brand_id' => $brand->id, 'is_active' => 1, 'minimum_stock' => 1,
        ]);

        $po = PurchaseOrder::create([
            'po_date' => now()->toDateString(), 'total_amount' => 30000,
            'status_id' => ListStatus::where('slug', 'pending')->first()->id,
            'supplier_id' => $supplierId, 'created_by_id' => $this->user->id,
        ]);
        $poItem = PurchaseOrderItem::create([
            'po_id' => $po->id, 'product_id' => $product->id,
            'quantity' => 60, 'unit_cost' => 500, 'total_cost' => 30000,
        ]);

        $this->received = ReceivedStock::create([
            'po_id' => $po->id, 'supplier_id' => $supplierId,
            'received_date' => now()->toDateString(),
            'received_no' => 'RS-SPLIT-'.uniqid(), 'payment_mode' => 'Credit', 'amount_paid' => 0,
        ]);
        ReceivedItem::create([
            'received_id' => $this->received->id, 'product_id' => $product->id,
            'po_item_id' => $poItem->id, 'quantity' => 60, 'unit_cost' => 500, 'total_cost' => 30000,
        ]);

        $this->bank = BankAccount::create([
            'bank_name' => 'BDO', 'account_name' => 'BRT', 'account_number' => '123',
            'gl_code' => '1020', 'is_active' => true,
        ]);

        $this->fundAccount('1000', 'Cash', 50000);
        $this->fundAccount('1020', 'BDO — BRT', 50000);
    }

    /** Give a GL account an opening balance so payments have something to draw on. */
    private function fundAccount(string $code, string $name, float $amount): void
    {
        $account = Account::firstOrCreate(['code' => $code], [
            'slug' => 'acct-'.$code, 'name' => $name, 'type' => 'asset',
            'subtype' => 'current_asset', 'is_active' => true,
        ]);
        $equity = Account::firstOrCreate(['code' => '3900'], [
            'slug' => 'opening-balance-equity', 'name' => 'Opening Balance Equity',
            'type' => 'equity', 'subtype' => 'opening_balance', 'is_active' => true,
        ]);
        $entry = JournalEntry::create([
            'journal_number' => 'JE-SPLIT-'.uniqid(), 'entry_date' => now()->toDateString(),
            'entry_type' => 'manual', 'status' => 'posted', 'posted_at' => now(),
        ]);
        JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $account->id, 'line_type' => 'debit',  'amount' => $amount, 'line_order' => 1]);
        JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $equity->id,  'line_type' => 'credit', 'amount' => $amount, 'line_order' => 2]);
    }

    private function pay(array $body)
    {
        return $this->actingAs($this->user)
            ->postJson("/received-stocks/{$this->received->id}/pay", $body);
    }

    public function test_a_three_way_split_records_a_row_and_an_entry_per_line(): void
    {
        $this->pay(['lines' => [
            ['payment_mode' => 'Cash on Hand', 'payment_amount' => 5000],
            ['payment_mode' => 'Bank Transfer', 'payment_amount' => 10000, 'bank_account_id' => $this->bank->id, 'bank_name' => 'BDO', 'reference_number' => 'TRN-1'],
            ['payment_mode' => 'Check',         'payment_amount' => 3000,  'reference_number' => 'CHK-1'],
        ]])->assertOk();

        $payments = $this->received->fresh()->payments;
        $this->assertCount(3, $payments, 'One payment row per line.');
        $this->assertEqualsWithDelta(18000, (float) $this->received->fresh()->amount_paid, 0.01);

        $entries = JournalEntry::where('entry_type', 'accounts_payable_payment')->get();
        $this->assertCount(3, $entries, 'One journal entry per line.');

        // Each line credits its own funding source: cash to cash, the bank line
        // to that specific bank account, the check to check clearing.
        $creditFor = function (string $mode) use ($payments, $entries) {
            $payment = $payments->firstWhere('payment_mode', $mode);
            $entry = $entries->firstWhere('source_id', $payment->id);
            return $entry->lines->firstWhere('line_type', 'credit')->account->code;
        };

        $this->assertSame('1000', $creditFor('Cash on Hand'), 'Cash line credits Cash.');
        $this->assertSame('1020', $creditFor('Bank Transfer'), 'Bank line credits that specific bank account, not generic Cash in Bank.');
        $this->assertSame('1011', $creditFor('Check'), 'Check line credits Cash in Bank (check clearing).');
    }

    public function test_lines_cannot_exceed_the_remaining_payable(): void
    {
        $this->pay(['lines' => [
            ['payment_mode' => 'Cash on Hand', 'payment_amount' => 20000],
            ['payment_mode' => 'Cash on Hand', 'payment_amount' => 20000],
        ]])->assertStatus(422);

        $this->assertCount(0, $this->received->fresh()->payments);
    }

    /**
     * The subtle one: two lines on the same source each pass an individual
     * balance check while together overdrawing it.
     */
    public function test_two_lines_on_the_same_source_are_checked_against_the_combined_balance(): void
    {
        // Cash holds 50,000. Two 30,000 lines pass individually, not together.
        $this->fundAccount('1000', 'Cash', 0); // no-op, balance already 50,000

        $res = $this->pay(['lines' => [
            ['payment_mode' => 'Cash on Hand', 'payment_amount' => 14000],
            ['payment_mode' => 'Cash on Hand', 'payment_amount' => 14000],
        ]]);

        // 28,000 total is under both the payable (30,000) and cash (50,000): allowed.
        $res->assertOk();
        $this->assertCount(2, $this->received->fresh()->payments);
    }

    public function test_combined_same_source_overdraft_is_rejected(): void
    {
        $this->received->update(['amount_paid' => 0]);
        // Drain cash to 20,000 so two 12,000 lines individually fit but jointly do not.
        $this->fundAccount('1000', 'Cash', -30000);

        $this->pay(['lines' => [
            ['payment_mode' => 'Cash on Hand', 'payment_amount' => 12000],
            ['payment_mode' => 'Cash on Hand', 'payment_amount' => 12000],
        ]])->assertStatus(422);

        $this->assertCount(0, $this->received->fresh()->payments);
    }

    public function test_bank_line_requires_a_reference_number(): void
    {
        $this->pay(['lines' => [
            ['payment_mode' => 'Bank Transfer', 'payment_amount' => 1000, 'bank_account_id' => $this->bank->id, 'bank_name' => 'BDO'],
        ]])->assertStatus(422);
    }

    public function test_check_line_requires_a_reference_number(): void
    {
        $this->pay(['lines' => [
            ['payment_mode' => 'Check', 'payment_amount' => 1000],
        ]])->assertStatus(422);
    }

    public function test_zero_amount_line_is_rejected(): void
    {
        $this->pay(['lines' => [
            ['payment_mode' => 'Cash on Hand', 'payment_amount' => 0],
        ]])->assertStatus(422);
    }

    public function test_one_bad_line_rolls_back_the_whole_payment(): void
    {
        $this->pay(['lines' => [
            ['payment_mode' => 'Cash on Hand', 'payment_amount' => 5000],
            ['payment_mode' => 'Check', 'payment_amount' => 1000], // missing reference
        ]])->assertStatus(422);

        $this->assertCount(0, $this->received->fresh()->payments, 'Nothing recorded.');
        $this->assertSame(0, JournalEntry::where('entry_type', 'accounts_payable_payment')->count());
    }

    public function test_legacy_single_payment_body_still_works(): void
    {
        $this->pay([
            'payment_mode' => 'Cash on Hand',
            'payment_amount' => 2500,
        ])->assertOk();

        $this->assertCount(1, $this->received->fresh()->payments);
        $this->assertEqualsWithDelta(2500, (float) $this->received->fresh()->amount_paid, 0.01);
    }
}
