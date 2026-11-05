<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankDeposit;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\Module;
use App\Models\Remittance;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Banking cash carries no cheques, and the form says so by sending an empty
 * cheque list. The deposit rules once read that empty list as "at least one
 * cheque, please" and refused every cash deposit — the picker filled the
 * amount, "Record Deposit" threw a 422, and the error landed on a cheque field
 * the cash form never shows, so the button simply looked dead.
 *
 * A cash deposit must go through with no cheques, and the cheque list is only
 * consulted when the deposit is a cheque deposit.
 */
class CashDepositWithoutChequesTest extends TestCase
{
    use RefreshDatabase;

    private BankAccount $bank;

    private Account $cash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        $this->bank = BankAccount::create([
            'bank_name' => 'BDO', 'account_name' => 'BRT', 'account_number' => '0001',
            'gl_code' => '1020', 'is_active' => 1,
        ]);

        $this->cash = Account::firstOrCreate(
            ['code' => '1000'],
            ['slug' => 'cash', 'name' => 'Cash', 'type' => 'asset', 'subtype' => 'current_asset', 'is_active' => true]
        );
    }

    private function accountant(): User
    {
        $user = User::factory()->create();
        $role = ListRole::create(['name' => 'R'.uniqid(), 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $module = Module::where('key', 'accounting')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $module->submodules()->where('key', 'cash_management')->firstOrFail()->id,
            'access_level' => 'encoder',
        ]);

        return $user;
    }

    /** Put real money in the Cash account so the balance guard has something to clear. */
    private function cashOnHand(float $amount): void
    {
        $entry = JournalEntry::create([
            'journal_number' => 'JE-TEST-'.uniqid(),
            'entry_date' => now()->toDateString(),
            'entry_type' => 'manual',
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        JournalEntryLine::create([
            'journal_entry_id' => $entry->id, 'account_id' => $this->cash->id,
            'line_type' => 'debit', 'amount' => $amount, 'line_order' => 1,
        ]);
    }

    /** A verified remittance waiting to be banked. */
    private function liquidatedRemittance(float $amount): Remittance
    {
        $liquidated = ListStatus::firstOrCreate(
            ['slug' => 'liquidated'],
            ['name' => 'Liquidated', 'text_color' => '#fff', 'bg_color' => '#333']
        )->id;

        $id = DB::table('remittances')->insertGetId([
            'remittance_no' => 'REM-'.uniqid(),
            'remittance_date' => now()->toDateString(),
            'total_amount' => $amount, 'received_amount' => $amount,
            'summary' => json_encode([]),
            'created_by_id' => User::factory()->create()->id,
            'status_id' => $liquidated, 'bank_deposit_id' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return Remittance::findOrFail($id);
    }

    /** The exact shape the cash form posts: a cheque list that is empty. */
    private function deposit(User $as, array $extra = [])
    {
        return $this->actingAs($as)->postJson('/accounting/bank-deposits', array_merge([
            'deposit_type' => 'cash',
            'cash_account_id' => $this->cash->id,
            'bank_account_id' => $this->bank->id,
            'amount' => '6500.00',
            'deposit_date' => now()->toDateString(),
            'check_ids' => [],
            'remittance_ids' => [],
            'reference' => null,
            'notes' => null,
        ], $extra));
    }

    public function test_a_cash_deposit_goes_through_with_no_cheques(): void
    {
        $this->cashOnHand(10000);

        $this->deposit($this->accountant())
            ->assertOk()
            ->assertJsonMissingValidationErrors('check_ids');

        $this->assertSame(1, BankDeposit::where('deposit_type', 'cash')->count());
    }

    public function test_an_empty_cheque_list_is_not_a_validation_error_for_cash(): void
    {
        // Even with the balance too thin to clear, the refusal is about the
        // money — never about the cheque list the cash form cannot help but send.
        $this->deposit($this->accountant())
            ->assertJsonMissingValidationErrors('check_ids');
    }

    public function test_a_cash_deposit_banks_its_remittance(): void
    {
        $this->cashOnHand(10000);
        $remittance = $this->liquidatedRemittance(6500);

        $this->deposit($this->accountant(), ['remittance_ids' => [$remittance->id]])->assertOk();

        $this->assertNotNull($remittance->fresh()->bank_deposit_id, 'The banked remittance is linked to its deposit.');
    }

    public function test_a_cheque_deposit_still_needs_a_cheque(): void
    {
        $this->actingAs($this->accountant())
            ->postJson('/accounting/bank-deposits', [
                'deposit_type' => 'check',
                'bank_account_id' => $this->bank->id,
                'deposit_date' => now()->toDateString(),
                'check_ids' => [],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('check_ids');
    }
}
