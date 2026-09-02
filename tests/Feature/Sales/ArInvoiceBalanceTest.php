<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\Customer;
use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The payment screen used to show the balance from the list row it was opened
 * from. That row is a snapshot: once a payment is recorded anywhere else it
 * overstates what is owed, and the screen showed one figure while the server
 * enforced another — an invoice reading "Outstanding ₱20,400" and "covers the
 * full balance" while the save was refused for exceeding ₱6,399.
 */
class ArInvoiceBalanceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ArInvoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        // 'closed' and 'pending' matter: settling an invoice closes its sales
        // order and stamps the new receipt.
        foreach ([
            'unpaid' => 'Unpaid', 'partially-paid' => 'Partially Paid', 'paid' => 'Paid',
            'for-payment' => 'For Payment', 'closed' => 'Closed', 'pending' => 'Pending',
        ] as $slug => $name) {
            ListStatus::firstOrCreate(['slug' => $slug], ['name' => $name, 'text_color' => '#fff', 'bg_color' => '#333']);
        }

        $this->user = User::factory()->create();
        $role = ListRole::create(['name' => 'R' . uniqid(), 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        UserRole::create(['user_id' => $this->user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $this->user->id]);
        $module = Module::where('key', 'sales')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $module->submodules()->where('key', 'ar_invoices')->firstOrFail()->id,
            'access_level' => 'encoder',
        ]);
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $module->submodules()->where('key', 'ar_invoices')->firstOrFail()->id,
            'access_level' => 'view',
        ]);

        $customer = Customer::create([
            'name' => 'Juan Dela Cruz', 'address' => 'Zamboanga', 'contact_number' => '09170000000',
            'is_active' => 1, 'added_by_id' => $this->user->id,
        ]);

        // Settling an invoice in full awards the rep their incentive, so the
        // order needs one for that path to run.
        $rep = \App\Models\Employee::create([
            'lastname' => 'Cruz', 'firstname' => 'Juan', 'mobile' => '09170000002',
            'birthdate' => '1990-01-01', 'sex' => 'Male', 'religion' => 'N/A',
            'is_regular' => 1, 'is_blacklisted' => 0,
        ]);

        $order = SalesOrder::create([
            'so_number' => 'SO-' . uniqid(), 'payment_mode' => 'Credit Sales',
            'order_date' => now()->toDateString(), 'total_amount' => 20400, 'total_discount' => 0,
            'customer_id' => $customer->id, 'added_by_id' => $this->user->id, 'sales_rep_id' => $rep->id,
            'requires_batch_approval' => false,
            'status_id' => ListStatus::where('slug', 'for-payment')->first()->id,
        ]);

        // The screenshot's invoice: 20,400 due, 14,001 already collected.
        $this->invoice = ArInvoice::create([
            'sales_order_id' => $order->id, 'invoice_number' => 'AR-' . uniqid(),
            'invoice_date' => now()->toDateString(), 'amount_due' => 20400,
            'amount_paid' => 14001, 'balance_due' => 6399, 'total_discount' => 0,
            'status_id' => ListStatus::where('slug', 'partially-paid')->first()->id,
        ]);
    }

    public function test_the_balance_endpoint_reports_what_is_actually_owed(): void
    {
        $body = $this->actingAs($this->user)
            ->getJson('/ar-invoices?option=balance&id=' . $this->invoice->id)
            ->assertOk()
            ->json();

        $this->assertEquals(6399.0, $body['balance_due'], 'Must report the real balance, not the original amount due.');
        $this->assertEquals(20400.0, $body['amount_due']);
        $this->assertEquals(14001.0, $body['amount_paid']);
        $this->assertFalse($body['is_settled']);
    }

    public function test_paying_the_stale_figure_is_refused_with_the_true_balance(): void
    {
        // 20,400 is what the stale row showed; only 6,399 is actually owed.
        $this->actingAs($this->user)
            ->putJson('/ar-invoices/' . $this->invoice->id, [
                'id' => $this->invoice->id,
                'option' => 'payment',
                'balance_due' => 20400,
                'payment_date' => now()->toDateString(),
                'amount_paid' => 20400,
                'payment_mode' => 'Cash',
            ])
            ->assertStatus(422)
            ->assertJsonFragment([
                'amount_paid' => ['Payment of PHP 20,400.00 exceeds the outstanding balance of PHP 6,399.00.'],
            ]);
    }

    public function test_a_client_supplied_balance_cannot_widen_what_may_be_paid(): void
    {
        // The request carries balance_due; it must never be believed over the DB.
        $this->actingAs($this->user)
            ->putJson('/ar-invoices/' . $this->invoice->id, [
                'id' => $this->invoice->id,
                'option' => 'payment',
                'balance_due' => 999999,
                'payment_date' => now()->toDateString(),
                'amount_paid' => 10000,
                'payment_mode' => 'Cash',
            ])
            ->assertStatus(422);
    }

    public function test_paying_the_true_balance_is_accepted(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/ar-invoices/' . $this->invoice->id, [
                'id' => $this->invoice->id,
                'option' => 'payment',
                'balance_due' => 6399,
                'payment_date' => now()->toDateString(),
                'amount_paid' => 6399,
                'payment_mode' => 'Cash',
            ]);

        $this->assertNotEquals(422, $response->getStatusCode());
        $this->assertEquals(0.0, round((float) $this->invoice->fresh()->balance_due, 2));
    }

    public function test_a_settled_invoice_reports_itself_as_settled(): void
    {
        $this->invoice->update(['amount_paid' => 20400, 'balance_due' => 0]);

        $body = $this->actingAs($this->user)
            ->getJson('/ar-invoices?option=balance&id=' . $this->invoice->id)
            ->assertOk()->json();

        $this->assertTrue($body['is_settled']);
        $this->assertEquals(0.0, $body['balance_due']);
    }
}
