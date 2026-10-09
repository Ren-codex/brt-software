<?php

namespace Tests\Feature\Sales;

use App\Models\Employee;
use App\Models\ListStatus;
use App\Models\Receipt;
use App\Models\SalesOrder;
use App\Models\Series;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * Money that moves leaves a record of every hand it passed through.
 *
 * The receipt logs one field, held_by_employee_id, and that is the whole
 * custody trail. Remitting used to clear it with a query-builder mass update,
 * which skips model events — so an administrator taking cash straight from a
 * driver emptied custody silently. The log read "the driver has it" and
 * stopped, with nothing to say the money had ever left them.
 */
class CustodyTrailTest extends TestCase
{
    use BuildsCodOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();
        $this->grantArInvoiceAccess($this->user);
        $this->grantReceiptAccess($this->user);
        $this->grantRemittanceAccess($this->user);

        Series::firstOrCreate(['slug' => 'remittance'], [
            'name' => 'Remittance', 'prefix' => 'RM-', 'starting_value' => 1, 'max_digit' => 4,
        ]);
        ListStatus::firstOrCreate(['slug' => 'for-verification'], [
            'name' => 'For Verification', 'text_color' => '#fff', 'bg_color' => '#333',
        ]);
        \App\Models\RolePermission::create([
            'role_id' => \App\Models\UserRole::where('user_id', $this->user->id)->value('role_id'),
            'module_id' => \App\Models\Module::where('key', 'sales')->firstOrFail()->id,
            'submodule_id' => null,
            'access_level' => 'admin',
        ]);
    }

    /** Every custody change recorded against this receipt, oldest first. */
    private function custodyTrail(int $receiptId): array
    {
        return DB::table('activity_log')
            ->where('subject_type', Receipt::class)
            ->where('subject_id', $receiptId)
            ->orderBy('id')
            ->pluck('description')
            ->all();
    }

    private function collectedAtTheDoor(): Receipt
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::latest('id')->firstOrFail();

        $this->actingAs($this->user)->put('/sales-orders/'.$order->id, [
            'action' => 'mark-delivered',
            'delivered_at' => now()->toDateString(),
            'collected_amount' => 3000,
            'collected_mode' => 'Cash',
        ])->assertSessionHasNoErrors();

        return Receipt::latest('id')->firstOrFail();
    }

    private function remit(Receipt $receipt): void
    {
        app(\App\Services\Modules\RemittanceClass::class)->save(new \Illuminate\Http\Request([
            'remittance_date' => now()->toDateString(),
            'summary' => [['payment_mode' => 'Cash', 'amount' => 3000]],
            'total_amount' => 3000,
            'receipts' => [$receipt->id],
        ]));
    }

    public function test_remitting_straight_from_the_driver_still_records_the_money_leaving_them(): void
    {
        $receipt = $this->collectedAtTheDoor();
        $this->assertSame(['created'], $this->custodyTrail($receipt->id));

        $this->remit($receipt);

        // The second entry is the point of this: without it the trail says the
        // driver is still carrying money that has been banked.
        $this->assertSame(['created', 'updated'], $this->custodyTrail($receipt->id));
        $this->assertNull($receipt->fresh()->held_by_employee_id);
    }

    public function test_a_handover_adds_its_own_step(): void
    {
        $office = Employee::create([
            'firstname' => 'Office', 'lastname' => 'Person', 'mobile' => '09170004444',
            'birthdate' => '1990-01-01', 'sex' => 'Female', 'religion' => 'None',
        ]);

        $receipt = $this->collectedAtTheDoor();
        $this->actingAs($this->user)
            ->putJson('/receipts/'.$receipt->id.'/turn-over', ['held_by_employee_id' => $office->id])
            ->assertOk();
        $this->remit($receipt->fresh());

        // Driver, then the office, then banked — three steps, one per hand.
        $this->assertSame(['created', 'updated', 'updated'], $this->custodyTrail($receipt->id));
    }

    public function test_the_receipt_still_lands_on_the_remittance(): void
    {
        // Saving one at a time rather than in bulk must not change the outcome.
        $receipt = $this->collectedAtTheDoor();
        $this->remit($receipt);

        $fresh = $receipt->fresh();
        $this->assertNotNull($fresh->remittance_id);
        $this->assertSame('for-verification', $fresh->status->slug);
    }
}
