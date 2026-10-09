<?php

namespace Tests\Feature\Sales;

use App\Models\Employee;
use App\Models\ListStatus;
use App\Models\Receipt;
use App\Models\SalesOrder;
use App\Models\Series;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * Remitting finishes an order that was only waiting on the money arriving.
 *
 * The handover normally closes a For Turnover order. But an administrator can
 * take cash straight from a driver and remit it without one, and then nothing
 * re-derived the status — the order sat in For Turnover for good, while
 * moneyIsIn() already read true because a remitted receipt is no longer out.
 */
class RemittingClosesTheOrderTest extends TestCase
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

        // An administrator: unrestricted, so they can take money in from a
        // driver without the handover the scoped guard would insist on.
        \App\Models\RolePermission::create([
            'role_id' => \App\Models\UserRole::where('user_id', $this->user->id)->value('role_id'),
            'module_id' => \App\Models\Module::where('key', 'sales')->firstOrFail()->id,
            'submodule_id' => null,
            'access_level' => 'admin',
        ]);
    }

    private function me(): Employee
    {
        return Employee::firstOrCreate(
            ['user_id' => $this->user->id],
            [
                'firstname' => 'Me', 'lastname' => 'Rep', 'mobile' => '09170003333',
                'birthdate' => '1990-01-01', 'sex' => 'Female', 'religion' => 'None',
            ]
        );
    }

    private function collectedAtTheDoor(): Receipt
    {
        $this->postCod(['sales_rep_id' => $this->me()->id])->assertSessionHasNoErrors();
        $order = SalesOrder::latest('id')->firstOrFail();

        $this->actingAs($this->user)->put('/sales-orders/'.$order->id, [
            'action' => 'mark-delivered',
            'delivered_at' => now()->toDateString(),
            'collected_amount' => 3000,
            'collected_mode' => 'Cash',
        ])->assertSessionHasNoErrors();

        return Receipt::latest('id')->firstOrFail();
    }

    public function test_remitting_without_a_handover_still_closes_it(): void
    {
        $receipt = $this->collectedAtTheDoor();
        $this->assertSame('for-turnover', SalesOrder::firstOrFail()->fresh('status')->status->slug);

        // Straight to the remittance, as an administrator taking cash in from
        // the driver does: no handover, the driver is still the holder.
        app(\App\Services\Modules\RemittanceClass::class)->save(new \Illuminate\Http\Request([
            'remittance_date' => now()->toDateString(),
            'summary' => [['payment_mode' => 'Cash', 'amount' => 3000]],
            'total_amount' => 3000,
            'receipts' => [$receipt->id],
        ]));

        $this->assertSame('closed', SalesOrder::firstOrFail()->fresh('status')->status->slug);
        $this->assertNotNull($receipt->fresh()->remittance_id);
    }

    public function test_it_does_not_disturb_an_order_that_was_not_waiting(): void
    {
        $receipt = $this->collectedAtTheDoor();
        $order = SalesOrder::firstOrFail();

        // An old record: closed long ago, with no delivery stamp. Re-deriving
        // it would read as For Release and drag it backwards.
        $order->update([
            'delivered_at' => null,
            'status_id' => ListStatus::where('slug', 'closed')->firstOrFail()->id,
        ]);

        app(\App\Services\Modules\RemittanceClass::class)->save(new \Illuminate\Http\Request([
            'remittance_date' => now()->toDateString(),
            'summary' => [['payment_mode' => 'Cash', 'amount' => 3000]],
            'total_amount' => 3000,
            'receipts' => [$receipt->id],
        ]));

        $this->assertSame('closed', $order->fresh('status')->status->slug);
    }
}
