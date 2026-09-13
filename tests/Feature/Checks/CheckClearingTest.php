<?php

namespace Tests\Feature\Checks;

use App\Models\Check;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\Receipt;
use App\Models\User;
use App\Notifications\BouncedCheckNotification;
use App\Services\Modules\CheckRegisterClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CheckClearingTest extends TestCase
{
    use RefreshDatabase;
    use MakesCheckFixtures;

    public function test_bouncing_leaves_the_invoice_owed_and_posts_nothing(): void
    {
        $receipt = $this->receipt('Check', '000123');
        $check = app(CheckRegisterClass::class)->registerReceived($receipt);
        $balanceBefore = (float) $receipt->arInvoice->balance_due;

        app(CheckRegisterClass::class)->markBounced($check, 'Insufficient funds');

        $this->assertSame(Check::STATUS_BOUNCED, $check->fresh()->status);
        $this->assertSame('Insufficient funds', $check->fresh()->bounce_reason);
        $this->assertSame($balanceBefore, (float) $receipt->arInvoice->fresh()->balance_due);
        $this->assertSame(0, JournalEntry::where('source_type', Receipt::class)->where('source_id', $receipt->id)->count());
    }

    public function test_a_cleared_check_cannot_be_bounced(): void
    {
        $receipt = $this->receipt('Check', '000123');
        $check = app(CheckRegisterClass::class)->registerReceived($receipt);
        $check->update(['status' => Check::STATUS_CLEARED]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(CheckRegisterClass::class)->markBounced($check, 'Too late');
    }

    /**
     * `notifyReceivingRep()` resolves the User via the Employee side of the
     * relation (User::employee() is hasOne(Employee, 'user_id') — Users don't
     * have an employee_id column). This seeds an Employee linked to a User via
     * user_id, and confirms the notification actually reaches that User.
     */
    public function test_bouncing_notifies_the_receiving_rep(): void
    {
        Notification::fake();

        $receipt = $this->receipt('Check', '000123');
        $repUser = User::factory()->create();
        Employee::where('id', $receipt->arInvoice->sales_order->sales_rep_id)
            ->update(['user_id' => $repUser->id]);

        $check = app(CheckRegisterClass::class)->registerReceived($receipt);

        app(CheckRegisterClass::class)->markBounced($check, 'Insufficient funds');

        Notification::assertSentTo($repUser, BouncedCheckNotification::class);
    }
}
