<?php

namespace Tests\Feature\Inventory;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Check;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\ListBrand;
use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\ListSupplier;
use App\Models\ListUnit;
use App\Models\Module;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReceivedItem;
use App\Models\ReceivedStock;
use App\Models\ReceivedStockPayment;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Accounting\CashManagementService;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every way of paying a supplier, end to end through the screen's own endpoint.
 * Cash and a transfer move money today; a check is a promise for a later date
 * and must not touch the bank until it clears.
 */
class PayablePaymentModesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ReceivedStock $stock;

    private BankAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
        $this->user = $this->payablesClerk();
        $this->stock = $this->payableOf(10000);
        $this->bank = BankAccount::firstOrCreate(
            ['gl_code' => '1020'],
            ['bank_name' => 'BDO', 'account_name' => 'BRT']
        );
    }

    private function payablesClerk(): User
    {
        $role = ListRole::firstOrCreate(['name' => 'Payables Clerk'], [
            'type' => 'role', 'definition' => 'test', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserRole::create([
            'user_id' => $user->id, 'role_id' => $role->id,
            'is_active' => 1, 'added_by_id' => $user->id,
        ]);

        $module = Module::where('key', 'accounting')->firstOrFail();
        RolePermission::firstOrCreate([
            'role_id' => $role->id,
            'module_id' => $module->id,
            'submodule_id' => $module->submodules()->where('key', 'accounts_payable')->firstOrFail()->id,
        ], ['access_level' => 'encoder']);

        return $user;
    }

    private function payableOf(float $amount): ReceivedStock
    {
        $supplier = ListSupplier::create([
            'name' => 'Test Supplier', 'address' => 'Zamboanga City',
            'contact_person' => 'Roberto Cruz', 'contact_number' => '09170000000',
            'email' => 'supplier@example.com', 'tin' => '000-000-000',
            'is_active' => 1, 'is_blacklisted' => 0,
        ]);

        $status = ListStatus::firstOrCreate(['slug' => 'pending'], [
            'name' => 'Pending', 'text_color' => '#fff', 'bg_color' => '#333',
        ]);

        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id, 'po_number' => 'PO-'.uniqid(),
            'po_date' => now()->toDateString(), 'total_amount' => $amount,
            'created_by_id' => $this->user->id, 'status_id' => $status->id,
        ]);

        $stock = ReceivedStock::create([
            'po_id' => $po->id, 'supplier_id' => $supplier->id,
            'received_no' => 'RS-'.uniqid(), 'received_date' => now(),
            'payment_mode' => 'Credit', 'amount_paid' => 0,
            'received_by_id' => $this->user->id,
        ]);

        $brand = ListBrand::create(['name' => 'Jasmine Rice', 'is_active' => 1]);
        $unit = ListUnit::create(['name' => 'Kg', 'is_active' => 1]);
        $product = Product::create([
            'code' => 'JR-25', 'brand_id' => $brand->id, 'weight' => 25,
            'unit_id' => $unit->id, 'is_active' => 1,
        ]);

        $poItem = PurchaseOrderItem::create([
            'po_id' => $po->id, 'product_id' => $product->id,
            'quantity' => 1, 'unit_cost' => $amount, 'total_cost' => $amount,
            'status' => 'received', 'received_quantity' => 1,
        ]);

        ReceivedItem::create([
            'received_id' => $stock->id, 'product_id' => $product->id,
            'po_item_id' => $poItem->id,
            'quantity' => 1, 'unit_cost' => $amount, 'total_cost' => $amount,
        ]);

        return $stock->fresh('items');
    }

    /** Put money into an account so it can be paid out of. */
    protected function fund(string $accountCode, float $amount): void
    {
        $account = Account::firstOrCreate(
            ['code' => $accountCode],
            ['name' => 'Funded '.$accountCode, 'type' => 'asset', 'slug' => $accountCode === '1000' ? 'cash' : 'bank-'.$accountCode]
        );

        $entry = JournalEntry::create([
            'journal_number' => 'JE-'.uniqid(),
            'entry_date' => now()->toDateString(),
            'entry_type' => 'opening',
            'memo' => 'Opening balance for the test',
            'status' => 'posted',
            'created_by_id' => $this->user->id,
            'posted_at' => now(),
        ]);

        JournalEntryLine::create([
            'journal_entry_id' => $entry->id, 'account_id' => $account->id,
            'line_type' => 'debit', 'amount' => $amount,
            'description' => 'Opening', 'line_order' => 1,
        ]);
    }

    /** The ledger entries a payment produced. Lines carry no source; entries do. */
    private function entriesFor(ReceivedStockPayment $payment)
    {
        return JournalEntry::where('source_type', ReceivedStockPayment::class)
            ->where('source_id', $payment->id);
    }

    protected function pay(array $body)
    {
        return $this->actingAs($this->user)
            ->postJson("/received-stocks/{$this->stock->id}/pay", $body);
    }

    public function test_cash_on_hand_is_refused_when_there_is_none(): void
    {
        // The guard that matters most: you cannot pay out cash you do not hold.
        $this->pay(['payment_mode' => 'Cash on Hand', 'payment_amount' => 5000])
            ->assertStatus(422)
            ->assertJsonValidationErrors('lines');

        $this->assertSame(0, ReceivedStockPayment::count());
    }

    public function test_cash_on_hand_pays_and_posts(): void
    {
        $this->fund('1000', 20000);

        $this->pay(['payment_mode' => 'Cash on Hand', 'payment_amount' => 5000])->assertOk();

        $payment = ReceivedStockPayment::firstOrFail();
        $this->assertSame('Cash on Hand', $payment->payment_mode);
        $this->assertEquals(5000, $this->stock->fresh()->amount_paid);

        // Cash leaves the moment it is handed over.
        $this->assertSame(1, $this->entriesFor($payment)->count());
        $this->assertEquals(15000, app(CashManagementService::class)->getCashOnHandBalance());
    }

    public function test_a_bank_transfer_pays_and_leaves_the_account(): void
    {
        $this->fund($this->bank->gl_code, 20000);

        $this->pay([
            'payment_mode' => 'Bank Transfer', 'payment_amount' => 5000,
            'bank_account_id' => $this->bank->id,
            'bank_name' => 'BDO', 'reference_number' => 'TRN-0001',
        ])->assertOk();

        $payment = ReceivedStockPayment::firstOrFail();
        $this->assertSame('Bank Transfer', $payment->payment_mode);
        $this->assertEquals(5000, $this->stock->fresh()->amount_paid);
        $this->assertEquals(15000, app(CashManagementService::class)->getBankAccountBalance($this->bank->id));
    }

    public function test_a_transfer_larger_than_the_account_holds_is_refused(): void
    {
        $this->fund($this->bank->gl_code, 1000);

        $this->pay([
            'payment_mode' => 'Bank Transfer', 'payment_amount' => 5000,
            'bank_account_id' => $this->bank->id,
            'bank_name' => 'BDO', 'reference_number' => 'TRN-0002',
        ])->assertStatus(422)->assertJsonValidationErrors('lines');

        $this->assertSame(0, ReceivedStockPayment::count());
    }

    public function test_a_check_without_its_date_is_refused(): void
    {
        // The fault that made this impossible on the sales side: the date is
        // collected, sent, and must survive all the way to the service.
        $this->pay([
            'payment_mode' => 'Check', 'payment_amount' => 5000,
            'bank_account_id' => $this->bank->id,
            'reference_number' => 'CHK-0000',
        ])->assertStatus(422);

        $this->assertSame(0, ReceivedStockPayment::count());
    }

    public function test_a_bank_transfer_needs_a_name_and_a_reference(): void
    {
        $this->pay([
            'payment_mode' => 'Bank Transfer', 'payment_amount' => 5000,
            'bank_account_id' => $this->bank->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['lines.0.bank_name', 'lines.0.reference_number']);
    }

    public function test_a_check_needs_its_number_and_its_date(): void
    {
        $this->pay([
            'payment_mode' => 'Check', 'payment_amount' => 5000,
            'bank_account_id' => $this->bank->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('lines.0.reference_number');
    }

    public function test_a_check_is_recorded_and_registered_but_moves_no_money(): void
    {
        $this->fund($this->bank->gl_code, 20000);

        $response = $this->pay([
            'payment_mode' => 'Check', 'payment_amount' => 5000,
            'bank_account_id' => $this->bank->id,
            'reference_number' => 'CHK-0001',
            'check_date' => now()->addDays(30)->toDateString(),
        ]);

        $response->assertOk();

        $payment = ReceivedStockPayment::firstOrFail();
        $this->assertSame('Check', $payment->payment_mode);
        $this->assertEquals(5000, $payment->amount_paid);

        // It belongs in the register, dated when it can be cashed.
        $check = Check::where('source_type', ReceivedStockPayment::class)
            ->where('source_id', $payment->id)->firstOrFail();
        $this->assertSame('CHK-0001', $check->check_number);
        $this->assertSame(now()->addDays(30)->toDateString(), $check->check_date->toDateString());
        $this->assertSame(Check::STATUS_PENDING, $check->status);

        // And nothing has left the bank: that happens when it clears.
        $this->assertSame(0, $this->entriesFor($payment)->count());
        $this->assertEquals(20000, app(CashManagementService::class)->getBankAccountBalance($this->bank->id));
    }

    public function test_the_payable_balance_moves_for_every_mode(): void
    {
        $this->pay([
            'payment_mode' => 'Check', 'payment_amount' => 4000,
            'bank_account_id' => $this->bank->id,
            'reference_number' => 'CHK-0002',
            'check_date' => now()->addDays(10)->toDateString(),
        ])->assertOk();

        $this->assertEquals(4000, $this->stock->fresh()->amount_paid);
    }

    public function test_a_payment_cannot_exceed_what_is_still_owed(): void
    {
        $this->pay([
            'payment_mode' => 'Check', 'payment_amount' => 12000,
            'bank_account_id' => $this->bank->id,
            'reference_number' => 'CHK-0003',
            'check_date' => now()->addDays(10)->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('lines');

        $this->assertEquals(0, $this->stock->fresh()->amount_paid);
    }

    public function test_a_settled_payable_takes_no_more(): void
    {
        $this->stock->update(['amount_paid' => 10000]);

        $this->pay([
            'payment_mode' => 'Check', 'payment_amount' => 100,
            'bank_account_id' => $this->bank->id,
            'reference_number' => 'CHK-0004',
            'check_date' => now()->addDays(10)->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('lines');
    }

    public function test_an_unknown_payment_mode_is_rejected(): void
    {
        $this->pay(['payment_mode' => 'GCash', 'payment_amount' => 100])
            ->assertStatus(422)
            ->assertJsonValidationErrors('lines.0.payment_mode');
    }

    public function test_a_split_across_two_modes_is_recorded_as_two_payments(): void
    {
        $this->pay(['lines' => [
            [
                'payment_mode' => 'Check', 'payment_amount' => 3000,
                'bank_account_id' => $this->bank->id,
                'reference_number' => 'CHK-0005',
                'check_date' => now()->addDays(15)->toDateString(),
            ],
            [
                'payment_mode' => 'Check', 'payment_amount' => 2000,
                'bank_account_id' => $this->bank->id,
                'reference_number' => 'CHK-0006',
                'check_date' => now()->addDays(45)->toDateString(),
            ],
        ]])->assertOk();

        $this->assertSame(2, ReceivedStockPayment::count());
        $this->assertSame(2, Check::where('source_type', ReceivedStockPayment::class)->count());
        $this->assertEquals(5000, $this->stock->fresh()->amount_paid);
    }
}
