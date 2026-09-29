<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\Employee;
use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\Receipt;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\UserRole;
use App\Notifications\OutstandingDeliveriesNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * Every list in this module waits to be looked at. Nobody is told when goods
 * have been delivered for days with nothing collected, or when cash has sat in
 * a truck over a weekend — so once a day the office is told, and each rep is
 * told their own share.
 */
class OutstandingDeliveriesDigestTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCodOrders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();
    }

    private function admin(): User
    {
        $role = ListRole::firstOrCreate(['name' => 'Administrator'], ['type' => 'role', 'definition' => 't', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        return $user;
    }

    private function employee(string $firstname, ?User $user = null): Employee
    {
        return Employee::create([
            'firstname' => $firstname, 'lastname' => 'Cruz', 'mobile' => '09170000000',
            'birthdate' => '1990-01-01', 'sex' => 'Female', 'religion' => 'None',
            'user_id' => $user?->id,
        ]);
    }

    /** A delivery made $daysAgo with nothing collected against it. */
    private function uncollectedDelivery(int $daysAgo, ?Employee $rep = null): SalesOrder
    {
        $order = SalesOrder::create([
            'so_number' => 'SO-'.uniqid(),
            'order_date' => now()->subDays($daysAgo + 1)->toDateString(),
            'customer_id' => $this->customer->id,
            'status_id' => ListStatus::where('slug', 'for-payment')->value('id'),
            'payment_mode' => 'COD',
            'sales_rep_id' => $rep?->id,
            'delivered_at' => now()->subDays($daysAgo),
            'total_amount' => 12000, 'total_discount' => 0,
            'added_by_id' => $this->user->id,
        ]);

        ArInvoice::create([
            'sales_order_id' => $order->id,
            'invoice_number' => 'AR-'.uniqid(),
            'invoice_date' => now()->subDays($daysAgo + 1)->toDateString(),
            'amount_due' => 12000, 'amount_paid' => 0, 'balance_due' => 12000,
            'total_discount' => 0,
            'status_id' => ListStatus::where('slug', 'unpaid')->value('id'),
        ]);

        return $order;
    }

    /** Money collected $daysAgo and still in someone's hands. */
    private function heldCash(int $daysAgo, Employee $holder, ?Employee $rep = null): Receipt
    {
        $order = $this->uncollectedDelivery($daysAgo, $rep);
        $invoice = ArInvoice::where('sales_order_id', $order->id)->firstOrFail();
        $invoice->update(['amount_paid' => 12000, 'balance_due' => 0]);

        return Receipt::create([
            'receipt_number' => 'OR-'.uniqid(),
            'receipt_type' => 'payment',
            'receipt_date' => now()->subDays($daysAgo)->toDateString(),
            'amount_paid' => 12000, 'balance_due' => 0,
            'payment_mode' => 'Cash',
            'status_id' => ListStatus::where('slug', 'pending')->value('id'),
            'customer_id' => $this->customer->id,
            'ar_invoice_id' => $invoice->id,
            'held_by_employee_id' => $holder->id,
        ]);
    }

    public function test_the_office_is_told_what_is_outstanding(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $this->uncollectedDelivery(3);

        $this->artisan('deliveries:notify-outstanding');

        Notification::assertSentTo($admin, OutstandingDeliveriesNotification::class,
            function ($notification) {
                $payload = $notification->toDatabase($notification);

                return $payload['uncollected_count'] === 1
                    && (float) $payload['uncollected_amount'] === 12000.0;
            });
    }

    public function test_nothing_is_sent_when_nothing_is_outstanding(): void
    {
        Notification::fake();
        $this->admin();

        $this->artisan('deliveries:notify-outstanding');

        Notification::assertNothingSent();
    }

    public function test_a_fresh_delivery_is_not_chased_yet(): void
    {
        // Delivered today: the driver may still be on the road.
        Notification::fake();
        $this->admin();
        $this->uncollectedDelivery(0);

        $this->artisan('deliveries:notify-outstanding');

        Notification::assertNothingSent();
    }

    public function test_cash_sitting_in_the_field_is_counted(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $holder = $this->employee('Ana');
        $this->heldCash(4, $holder);

        $this->artisan('deliveries:notify-outstanding');

        Notification::assertSentTo($admin, OutstandingDeliveriesNotification::class,
            function ($notification) {
                $payload = $notification->toDatabase($notification);

                return $payload['held_count'] === 1 && (float) $payload['held_amount'] === 12000.0;
            });
    }

    public function test_a_rep_is_told_their_own_share(): void
    {
        Notification::fake();
        $this->admin();
        $repUser = User::factory()->create();
        $rep = $this->employee('Bea', $repUser);

        $this->uncollectedDelivery(3, $rep);
        $this->uncollectedDelivery(3);

        $this->artisan('deliveries:notify-outstanding');

        Notification::assertSentTo($repUser, OutstandingDeliveriesNotification::class,
            function ($notification) {
                $payload = $notification->toDatabase($notification);

                return $payload['uncollected_count'] === 1 && $payload['scope'] === 'mine';
            });
    }
}
