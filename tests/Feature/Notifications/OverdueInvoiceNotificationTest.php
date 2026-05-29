<?php

namespace Tests\Feature\Notifications;

use App\Models\ArInvoice;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\SalesOrder;
use App\Models\User;
use App\Notifications\OverdueInvoiceNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OverdueInvoiceNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        // Create required statuses
        ListStatus::firstOrCreate(['slug' => 'overdue'], ['name' => 'Overdue', 'text_color' => '#fff', 'bg_color' => '#dc3545']);
        ListStatus::firstOrCreate(['slug' => 'unpaid'], ['name' => 'Unpaid', 'text_color' => '#fff', 'bg_color' => '#28a745']);
        ListStatus::firstOrCreate(['slug' => 'cancelled'], ['name' => 'Cancelled', 'text_color' => '#fff', 'bg_color' => '#6c757d']);
        ListStatus::firstOrCreate(['slug' => 'paid'], ['name' => 'Paid', 'text_color' => '#fff', 'bg_color' => '#000000']);
        // Create and attach administrator role
        $adminRole = ListRole::firstOrCreate(['name' => 'Administrator'], ['type' => 'role', 'definition' => '', 'is_active' => true]);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($adminRole->id, ['added_by_id' => $this->admin->id]);
    }

    public function test_notification_has_correct_type_and_channels(): void
    {
        $user = User::factory()->create();
        $status = ListStatus::create(['name' => 'Overdue Test', 'slug' => 'overdue_test_'.uniqid(), 'text_color' => '#000', 'bg_color' => '#ccc']);
        $customer = Customer::create([
            'name' => 'Juan dela Cruz',
            'address' => '123 Main St',
            'contact_number' => '555-1234',
            'is_active' => true,
            'added_by_id' => $user->id,
        ]);
        $employee = Employee::create([
            'lastname' => 'Dela',
            'firstname' => 'Cruz',
            'mobile' => '09123456789',
            'birthdate' => '1990-01-01',
            'sex' => 'M',
            'religion' => 'Christian',
        ]);
        $so = SalesOrder::create([
            'so_number' => 'SO-TEST-0001',
            'order_date' => now()->toDateString(),
            'customer_id' => $customer->id,
            'status_id' => $status->id,
            'total_amount' => 5000,
            'payment_mode' => 'cash',
            'added_by_id' => $user->id,
            'sales_rep_id' => $employee->id,
            'driver_id' => $employee->id,
        ]);
        $invoice = ArInvoice::create([
            'sales_order_id' => $so->id,
            'status_id' => $status->id,
            'invoice_number' => 'AR-202505-0001',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->subDay()->toDateString(),
            'balance_due' => 4500.00,
            'amount_paid' => 500.00,
            'total_discount' => 0,
        ]);
        $invoice->load('sales_order.customer');

        $notification = new OverdueInvoiceNotification($invoice);
        $data = $notification->toDatabase(null);

        $this->assertSame('overdue_invoice', $data['type']);
        $this->assertSame($invoice->id, $data['invoice_id']);
        $this->assertSame('AR-202505-0001', $data['invoice_number']);
        $this->assertSame('Juan dela Cruz', $data['customer_name']);
        $this->assertSame(4500.0, $data['balance_due']);
        $this->assertContains('database', $notification->via(null));
        $this->assertContains('broadcast', $notification->via(null));
        $this->assertSame('overdue_invoice', $notification->broadcastType());
    }

    public function test_mark_overdue_command_sends_notification_for_each_newly_overdue_invoice(): void
    {
        Notification::fake();

        $overdueStatus = ListStatus::getBySlug('overdue');
        $unpaidStatus = ListStatus::getBySlug('unpaid');
        $cancelledStatus = ListStatus::getBySlug('cancelled');
        $paidStatus = ListStatus::getBySlug('paid');

        $customer = Customer::create([
            'name' => 'Test Customer',
            'address' => '123 Test St',
            'contact_number' => '555-1234',
            'is_active' => true,
            'added_by_id' => $this->admin->id,
        ]);
        $employee = Employee::create([
            'lastname' => 'Test',
            'firstname' => 'Employee',
            'mobile' => '09123456789',
            'birthdate' => '1990-01-01',
            'sex' => 'M',
            'religion' => 'Christian',
        ]);
        $so = SalesOrder::create([
            'so_number' => 'SO-TEST-0001',
            'order_date' => now()->toDateString(),
            'customer_id' => $customer->id,
            'status_id' => $unpaidStatus->id,
            'total_amount' => 5000,
            'payment_mode' => 'cash',
            'added_by_id' => $this->admin->id,
            'sales_rep_id' => $employee->id,
            'driver_id' => $employee->id,
        ]);
        ArInvoice::create([
            'sales_order_id' => $so->id,
            'status_id' => $unpaidStatus->id,
            'invoice_number' => 'AR-202505-9001',
            'invoice_date' => now()->subDays(30)->toDateString(),
            'due_date' => now()->subDay()->toDateString(),
            'balance_due' => 4500.00,
            'amount_paid' => 500.00,
            'total_discount' => 0,
        ]);

        $this->artisan('invoices:mark-overdue');

        Notification::assertSentTo($this->admin, OverdueInvoiceNotification::class);
    }

    public function test_mark_overdue_command_does_not_notify_when_no_invoices_become_overdue(): void
    {
        Notification::fake();

        ListStatus::getBySlug('overdue');
        ListStatus::getBySlug('cancelled');
        ListStatus::getBySlug('paid');

        $this->artisan('invoices:mark-overdue');

        Notification::assertNothingSent();
    }
}
