<?php

namespace Tests\Feature\Checks;

use App\Models\JournalEntry;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CheckConfirmationPostsTest extends TestCase
{
    use RefreshDatabase;
    use MakesCheckFixtures;

    public function test_confirming_a_check_posts_the_entry_and_reduces_the_balance(): void
    {
        $receipt = $this->receipt('Check');
        $invoice = $receipt->arInvoice;
        $this->actingAs(User::factory()->create());

        app(\App\Services\Modules\ArInvoiceClass::class)->confirmCheck($receipt->id, 'BDO', now()->toDateString());

        $this->assertSame(1, JournalEntry::where('source_type', Receipt::class)->where('source_id', $receipt->id)->count());
        $this->assertSame(0.0, (float) $invoice->fresh()->balance_due);
    }

    public function test_confirming_a_check_twice_is_rejected_and_posts_only_once(): void
    {
        $receipt = $this->receipt('Check');
        $this->actingAs(User::factory()->create());

        app(\App\Services\Modules\ArInvoiceClass::class)->confirmCheck($receipt->id, 'BDO', now()->toDateString());

        $this->expectException(ValidationException::class);

        try {
            app(\App\Services\Modules\ArInvoiceClass::class)->confirmCheck($receipt->id, 'BDO', now()->toDateString());
        } finally {
            $this->assertSame(1, JournalEntry::where('source_type', Receipt::class)->where('source_id', $receipt->id)->count());
        }
    }
}
