<?php

namespace Tests\Feature\Notifications;

use App\Models\ArInvoice;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\ListStatus;
use App\Models\SalesOrder;
use App\Models\User;
use App\Notifications\OverdueInvoiceNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverdueInvoiceNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_has_correct_type_and_channels(): void
    {
        $user = User::factory()->create();
        $status = ListStatus::create(['name' => 'Overdue Test', 'slug' => 'overdue_test_' . uniqid(), 'text_color' => '#000', 'bg_color' => '#ccc']);
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
            'so_number'   => 'SO-TEST-0001',
            'order_date'  => now()->toDateString(),
            'customer_id' => $customer->id,
            'status_id'   => $status->id,
            'total_amount' => 5000,
            'payment_mode' => 'cash',
            'added_by_id' => $user->id,
            'sales_rep_id' => $employee->id,
            'driver_id' => $employee->id,
        ]);
        $invoice = ArInvoice::create([
            'sales_order_id' => $so->id,
            'status_id'      => $status->id,
            'invoice_number' => 'AR-202505-0001',
            'invoice_date'   => now()->toDateString(),
            'due_date'       => now()->subDay()->toDateString(),
            'balance_due'    => 4500.00,
            'amount_paid'    => 500.00,
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
}
