<?php

namespace Tests\Feature\Checks;

use App\Models\JournalEntry;
use App\Models\Receipt;
use App\Services\Accounting\JournalEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnconfirmedCheckPostingTest extends TestCase
{
    use RefreshDatabase;
    use MakesCheckFixtures;

    public function test_a_check_receipt_posts_nothing_until_confirmed(): void
    {
        $receipt = $this->receipt('Check');

        $entry = app(JournalEntryService::class)->recordReceiptEntry($receipt);

        $this->assertNull($entry);
        $this->assertSame(0, JournalEntry::where('source_type', Receipt::class)->where('source_id', $receipt->id)->count());
    }

    public function test_a_cash_receipt_still_posts_immediately(): void
    {
        $receipt = $this->receipt('Cash');

        $entry = app(JournalEntryService::class)->recordReceiptEntry($receipt);

        $this->assertNotNull($entry);
    }
}
