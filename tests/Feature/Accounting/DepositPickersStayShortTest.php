<?php

namespace Tests\Feature\Accounting;

use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The deposit form carries two lists that only ever grow: collections waiting
 * to be banked, and cheques on hand. Both were sent whole, oldest first, on
 * every visit to Cash Management — so a business that falls behind on its
 * banking pays for it on every page load, and the rows most likely to be
 * banked today sit at the bottom of a list nobody scrolls.
 *
 * They are capped and ordered now, with a count saying what is really
 * waiting, so a backlog reads as a number instead of a wall of rows.
 */
class DepositPickersStayShortTest extends TestCase
{
    use RefreshDatabase;

    private const LIMIT = 100;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
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

    /** A backlog of verified remittances nobody has banked. */
    private function remittances(int $count): void
    {
        $author = User::factory()->create()->id;
        $liquidated = ListStatus::firstOrCreate(
            ['slug' => 'liquidated'],
            ['name' => 'Liquidated', 'text_color' => '#fff', 'bg_color' => '#333']
        )->id;

        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = [
                'remittance_no' => 'REM-'.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                // Oldest first by date, so "newest first" is visible in the output.
                'remittance_date' => now()->subDays($count - $i)->toDateString(),
                'total_amount' => 1000 * $i,
                'received_amount' => 1000 * $i,
                'summary' => json_encode([]),
                'created_by_id' => $author,
                'status_id' => $liquidated,
                'bank_deposit_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('remittances')->insert($rows);
    }

    private function props(User $as): array
    {
        $page = $this->actingAs($as)->get('/accounting/cash-management');
        $page->assertOk();

        return $page->viewData('page')['props'];
    }

    public function test_a_long_backlog_does_not_all_travel_to_the_page(): void
    {
        $this->remittances(143);

        $props = $this->props($this->accountant());

        $this->assertCount(self::LIMIT, $props['undepositedRemittances']);
        $this->assertSame(143, $props['undepositedRemittanceTotal'], 'The count tells the truth about the backlog.');
    }

    public function test_the_newest_come_first(): void
    {
        $this->remittances(5);

        $numbers = collect($this->props($this->accountant())['undepositedRemittances'])
            ->pluck('remittance_no')->all();

        // REM-00005 is the most recent, and the one likely being banked today.
        $this->assertSame('REM-00005', $numbers[0]);
        $this->assertSame('REM-00001', $numbers[4]);
    }

    public function test_a_short_list_is_sent_whole_and_counted_plainly(): void
    {
        $this->remittances(4);

        $props = $this->props($this->accountant());

        $this->assertCount(4, $props['undepositedRemittances']);
        $this->assertSame(4, $props['undepositedRemittanceTotal']);
    }

    public function test_a_banked_remittance_leaves_both_the_list_and_the_count(): void
    {
        $this->remittances(3);

        // Banked against a real deposit: the column is a foreign key, and a
        // made-up id would only prove SQLite enforces it.
        $bank = \App\Models\BankAccount::create([
            'bank_name' => 'BDO', 'account_name' => 'BRT', 'account_number' => '0001',
            'gl_code' => '1020', 'is_active' => 1,
        ]);
        $cash = \App\Models\Account::firstOrCreate(
            ['code' => '1000'],
            ['slug' => 'cash', 'name' => 'Cash', 'type' => 'asset', 'subtype' => 'current_asset', 'is_active' => true]
        );
        $deposit = \App\Models\BankDeposit::create([
            'deposit_no' => 'BD-TEST-1', 'cash_account_id' => $cash->id, 'bank_account_id' => $bank->id,
            'deposit_type' => 'cash', 'amount' => 2000, 'deposit_date' => now()->toDateString(),
            'status' => \App\Models\BankDeposit::STATUS_POSTED,
        ]);

        DB::table('remittances')->where('remittance_no', 'REM-00002')
            ->update(['bank_deposit_id' => $deposit->id]);

        $props = $this->props($this->accountant());

        $this->assertSame(2, $props['undepositedRemittanceTotal']);
        $this->assertNotContains(
            'REM-00002',
            collect($props['undepositedRemittances'])->pluck('remittance_no')->all()
        );
    }

    public function test_the_cheque_list_is_counted_too(): void
    {
        $props = $this->props($this->accountant());

        $this->assertArrayHasKey('undepositedCheckTotal', $props);
        $this->assertSame(0, $props['undepositedCheckTotal']);
        $this->assertCount(0, $props['undepositedChecks']);
    }
}
