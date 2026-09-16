<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Accounting\JournalEntryService;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * A bank account and its ledger account are made together, and the bank's
 * GL code is that account's code.
 *
 * On production six banks carried hand-typed codes that matched nothing, so
 * Cash Management showed every one at ₱0 with "No GL", and a ₱1,000,000
 * opening balance posted to a real cash account never appeared under any bank.
 */
class BankAccountLedgerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        $role = ListRole::create(['name' => 'Accountant ' . uniqid(), 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        $this->user = User::factory()->create();
        UserRole::create(['user_id' => $this->user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $this->user->id]);
        RolePermission::create([
            'role_id' => $role->id,
            'module_id' => Module::where('key', 'accounting')->firstOrFail()->id,
            'submodule_id' => null,
            'access_level' => 'admin',
        ]);
    }

    private function addBank(array $overrides = [])
    {
        return $this->actingAs($this->user)->postJson('/accounting/bank-accounts', array_merge([
            'bank_name' => 'BDO',
            'account_name' => 'Bouyant Rice Trading',
            'account_number' => '0084-0800-4614',
        ], $overrides));
    }

    /** Reuses an account the baseline chart migration already seeded. */
    private function account(string $code, string $slug, string $name, string $type = 'asset', ?string $subtype = 'current_asset'): Account
    {
        return Account::firstOrCreate(
            ['code' => $code],
            ['slug' => $slug, 'name' => $name, 'type' => $type, 'subtype' => $subtype, 'is_active' => true]
        );
    }

    public function test_adding_a_bank_creates_its_ledger_account_with_the_same_code(): void
    {
        $this->addBank()->assertOk()->assertJsonPath('gl_code', '1020');

        $bank = BankAccount::firstOrFail();
        $this->assertSame('1020', $bank->gl_code);

        $this->assertDatabaseHas('accounts', [
            'code' => '1020',
            'slug' => 'bank_1020',
            'name' => 'BDO — Bouyant Rice Trading',
            'type' => 'asset',
        ]);
    }

    public function test_a_typed_gl_code_is_ignored(): void
    {
        $this->addBank(['gl_code' => '1218'])->assertOk();

        $this->assertSame('1020', BankAccount::firstOrFail()->gl_code);
        $this->assertDatabaseMissing('accounts', ['code' => '1218']);
    }

    public function test_codes_skip_ones_already_taken_by_the_chart_or_another_bank(): void
    {
        $this->account('1020', 'bank_1020', 'Old BDO');
        BankAccount::create(['bank_name' => 'Legacy', 'account_name' => 'X', 'gl_code' => '1021', 'is_active' => true]);

        $this->addBank(['bank_name' => 'PNB'])->assertOk()->assertJsonPath('gl_code', '1022');
    }

    public function test_a_full_bank_block_is_refused_with_a_reason(): void
    {
        for ($code = 1020; $code <= 1049; $code++) {
            $this->account((string) $code, 'filler_' . $code, 'Filler ' . $code);
        }

        $this->addBank()->assertStatus(422)->assertJsonValidationErrors('bank_name');
        $this->assertSame(0, BankAccount::count());
    }

    public function test_renaming_a_bank_renames_its_ledger_account_and_keeps_the_code(): void
    {
        $this->addBank()->assertOk();
        $bank = BankAccount::firstOrFail();

        $this->actingAs($this->user)->putJson("/accounting/bank-accounts/{$bank->id}", [
            'bank_name' => 'BDO Unibank',
            'account_name' => 'Operations',
            'gl_code' => '1049',
        ])->assertOk();

        $this->assertSame('1020', $bank->fresh()->gl_code);
        $this->assertDatabaseHas('accounts', ['code' => '1020', 'name' => 'BDO Unibank — Operations']);
        $this->assertSame(1, Account::where('code', 'like', '10%')->where('slug', 'like', 'bank_%')->count());
    }

    public function test_a_bank_transfer_posts_into_that_same_account_not_a_new_one(): void
    {
        $this->addBank()->assertOk();
        $bank = BankAccount::firstOrFail();
        $before = Account::count();

        $resolve = new \ReflectionMethod(JournalEntryService::class, 'resolveCashAccountByPaymentMode');
        $resolve->setAccessible(true);
        $account = $resolve->invoke(app(JournalEntryService::class), 'Bank Transfer', $bank->id);

        $this->assertSame('1020', $account->code);
        $this->assertSame($before, Account::count());
    }

    public function test_an_opening_balance_posted_to_the_bank_account_shows_as_that_banks_balance(): void
    {
        $this->addBank()->assertOk();
        $bankLedger = Account::where('code', '1020')->firstOrFail();
        $equity = $this->account('3900', 'opening_balance_equity', 'Opening Balance Equity', 'equity', 'opening_balance');

        $this->actingAs($this->user)->postJson('/accounting/journal-entries', [
            'entry_date' => now()->toDateString(),
            'lines' => [
                ['account_id' => $bankLedger->id, 'line_type' => 'debit', 'amount' => 1000000],
                ['account_id' => $equity->id, 'line_type' => 'credit', 'amount' => 1000000],
            ],
        ])->assertSuccessful();

        $listed = collect($this->actingAs($this->user)->getJson('/accounting/bank-accounts/list')->json())
            ->firstWhere('gl_code', '1020');

        $this->assertEquals(1000000, $listed['balance']);
    }

    public function test_manual_entries_cannot_post_to_cash_in_bank(): void
    {
        // Cash Management never splits Cash in Bank by bank, so money posted
        // there by hand disappears from every bank's balance.
        $cashInBank = $this->account('1011', 'cash_in_bank', 'Cash in Bank', 'asset', 'cash');
        $equity = $this->account('3900', 'opening_balance_equity', 'Opening Balance Equity', 'equity', 'opening_balance');

        $this->actingAs($this->user)->postJson('/accounting/journal-entries', [
            'entry_date' => now()->toDateString(),
            'lines' => [
                ['account_id' => $cashInBank->id, 'line_type' => 'debit', 'amount' => 500],
                ['account_id' => $equity->id, 'line_type' => 'credit', 'amount' => 500],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('lines.0.account_id');

        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_cash_in_bank_is_not_offered_on_the_manual_entry_screen(): void
    {
        $this->account('1011', 'cash_in_bank', 'Cash in Bank', 'asset', 'cash');
        $this->account('1000', 'cash', 'Cash');

        $this->actingAs($this->user)->get('/accounting/journal-entries')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('accounts', fn ($accounts) => collect($accounts)->pluck('code')->contains('1000')
                    && ! collect($accounts)->pluck('code')->contains('1011')));
    }

    public function test_the_migration_gives_existing_banks_their_accounts(): void
    {
        // The production shape: codes typed by hand, none matching the chart.
        foreach ([['PNB', '1213'], ['BDO', '0813'], ['SECURITY BANK', '0611'], ['BPI', '1218']] as [$name, $code]) {
            BankAccount::create(['bank_name' => $name, 'account_name' => 'BOUYANT RICE TRADING', 'gl_code' => $code, 'is_active' => true]);
        }
        // Already mapped, possibly with history: must not move.
        $this->account('1030', 'bank_1030', 'METROBANK — Mapped');
        BankAccount::create(['bank_name' => 'METROBANK', 'account_name' => 'Mapped', 'gl_code' => '1030', 'is_active' => true]);
        // In the block but unmapped: keeps its code.
        BankAccount::create(['bank_name' => 'PBCOM', 'account_name' => 'BRT', 'gl_code' => '1045', 'is_active' => true]);

        (require database_path('migrations/2026_09_17_000001_give_every_bank_account_its_own_ledger_account.php'))->up();

        $codes = BankAccount::pluck('gl_code', 'bank_name')->all();
        $this->assertSame(
            ['BDO' => '1020', 'BPI' => '1021', 'METROBANK' => '1030', 'PBCOM' => '1045', 'PNB' => '1022', 'SECURITY BANK' => '1023'],
            collect($codes)->sortKeys()->all()
        );

        foreach ($codes as $code) {
            $this->assertTrue(Account::where('code', $code)->exists(), "No ledger account for {$code}");
        }
        $this->assertDatabaseHas('accounts', ['code' => '1020', 'slug' => 'bank_1020', 'name' => 'BDO — BOUYANT RICE TRADING']);
        $this->assertDatabaseHas('accounts', ['code' => '1030', 'name' => 'METROBANK — Mapped']);
        $this->assertSame(1, Account::where('code', '1030')->count());
    }
}
