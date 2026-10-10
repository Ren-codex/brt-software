<?php

namespace Tests\Feature\Sales;

use App\Models\Employee;
use App\Models\ListStatus;
use App\Models\Module;
use App\Models\Receipt;
use App\Models\RolePermission;
use App\Models\SalesOrder;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * The driver hands the money to the rep whose sale it is, or to an
 * administrator when that rep is away. Nobody else.
 *
 * Handing it to a third person strands it: they cannot remit a sale that is
 * not theirs, and the rep cannot remit money they are not holding, so only an
 * administrator could ever clear it. Refusing the handover makes that
 * impossible rather than merely unlikely.
 */
class HandoverGoesToTheRepTest extends TestCase
{
    use BuildsCodOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();
        $this->grantArInvoiceAccess($this->user);
        $this->grantReceiptAccess($this->user);
        ListStatus::firstOrCreate(['slug' => 'for-turnover'], [
            'name' => 'For Turnover', 'text_color' => '#fff', 'bg_color' => '#b0702a',
        ]);
    }

    private function person(string $first, ?int $userId = null): Employee
    {
        return Employee::create([
            'firstname' => $first, 'lastname' => 'P',
            'mobile' => '0917'.random_int(1000000, 9999999),
            'birthdate' => '1990-01-01', 'sex' => 'Female', 'religion' => 'None',
            'user_id' => $userId,
        ]);
    }

    private function collected(Employee $rep): Receipt
    {
        $this->postCod(['sales_rep_id' => $rep->id])->assertSessionHasNoErrors();
        $order = SalesOrder::latest('id')->firstOrFail();

        $this->actingAs($this->user)->put('/sales-orders/'.$order->id, [
            'action' => 'mark-delivered',
            'delivered_at' => now()->toDateString(),
            'collected_amount' => 3000,
            'collected_mode' => 'Cash',
        ])->assertSessionHasNoErrors();

        return Receipt::latest('id')->firstOrFail();
    }

    private function handTo(Receipt $receipt, int $employeeId)
    {
        return $this->actingAs($this->user)
            ->putJson('/receipts/'.$receipt->id.'/turn-over', ['held_by_employee_id' => $employeeId]);
    }

    public function test_the_orders_own_rep_may_take_it(): void
    {
        $rep = $this->person('Remy');
        $receipt = $this->collected($rep);

        $this->handTo($receipt, $rep->id)->assertOk();
        $this->assertSame($rep->id, $receipt->fresh()->held_by_employee_id);
    }

    public function test_another_rep_may_not(): void
    {
        $rep = $this->person('Remy');
        $other = $this->person('Olive');
        $receipt = $this->collected($rep);

        $this->handTo($receipt, $other->id)->assertStatus(422);
        // Still with the driver, which is the honest record.
        $this->assertSame(SalesOrder::latest('id')->firstOrFail()->driver_id, $receipt->fresh()->held_by_employee_id);
    }

    public function test_an_administrator_may_when_the_rep_is_away(): void
    {
        $rep = $this->person('Remy');
        $receipt = $this->collected($rep);

        // An employee whose user carries the sales admin grant.
        $adminUser = \App\Models\User::factory()->create();
        $role = \App\Models\ListRole::create(['name' => 'Adm '.uniqid(), 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        UserRole::create(['user_id' => $adminUser->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $adminUser->id]);
        RolePermission::create([
            'role_id' => $role->id,
            'module_id' => Module::where('key', 'sales')->firstOrFail()->id,
            'submodule_id' => null, 'access_level' => 'admin',
        ]);
        $admin = $this->person('Ada', $adminUser->id);

        $this->handTo($receipt, $admin->id)->assertOk();
        $this->assertSame($admin->id, $receipt->fresh()->held_by_employee_id);
    }

    public function test_the_driver_may_not_keep_passing_it_around(): void
    {
        $rep = $this->person('Remy');
        $receipt = $this->collected($rep);
        $otherDriver = $this->person('Dan');

        $this->handTo($receipt, $otherDriver->id)->assertStatus(422);
    }

    public function test_it_records_whoever_is_signed_in_when_nobody_is_named(): void
    {
        // The rep is signed in and taking the money, so there is nothing to
        // choose — the screen sends no holder and the server fills it in.
        $rep = $this->person('Remy', $this->user->id);
        $receipt = $this->collected($rep);

        $this->actingAs($this->user)
            ->putJson('/receipts/'.$receipt->id.'/turn-over', [])
            ->assertOk();

        $this->assertSame($rep->id, $receipt->fresh()->held_by_employee_id);
    }

    public function test_signing_in_as_the_wrong_person_is_still_refused(): void
    {
        // Defaulting to the signed-in user must not become a way around the
        // rule: it is checked the same as a named one.
        $rep = $this->person('Remy');
        $receipt = $this->collected($rep);
        $this->person('Nosey', $this->user->id);

        $this->actingAs($this->user)
            ->putJson('/receipts/'.$receipt->id.'/turn-over', [])
            ->assertStatus(422);
    }
}
