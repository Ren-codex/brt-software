<?php

namespace Tests\Feature\Accounting;

use App\Models\ArInvoice;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Receipt;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\Accounting\OpeningReceivablesImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Undoing an opening-receivables import that was run twice.
 *
 * The importer numbers each invoice freshly instead of using the reference on
 * the sheet, so a second run collides with nothing and quietly doubles both
 * Accounts Receivable and Opening Balance Equity. That is what happened in
 * production on 15 Nov 2026: two identical batches of 56, fourteen hours
 * apart.
 *
 * It cannot be undone by deleting the later batch, because collections since
 * then have landed on whichever copy the collector happened to open. So the
 * rule is per pair: keep the copy holding the money, delete its empty twin.
 */
class UndoDuplicateOpeningReceivablesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ListStatusesTableSeeder::class);
        $this->user = User::factory()->create();
    }

    /** One row of the sheet, imported. Run twice to duplicate it. */
    private function importRow(string $customer, float $amount, string $date = '2026-11-05'): void
    {
        app(OpeningReceivablesImporter::class)->import(
            [['customer' => $customer, 'invoice_no' => 'X', 'amount' => $amount,
                'invoice_date' => $date, 'due_date' => $date, 'line' => 2]],
            $this->user,
            $date
        );
    }

    private function openingInvoices()
    {
        return ArInvoice::whereHas('sales_order', fn ($q) => $q->where('so_number', 'like', 'SO-OB%'))->get();
    }

    private function undo(bool $apply = false): string
    {
        $code = \Illuminate\Support\Facades\Artisan::call(
            'accounting:undo-duplicate-opening-ar',
            $apply ? ['--apply' => true] : []
        );
        $this->assertSame(0, $code);

        return \Illuminate\Support\Facades\Artisan::output();
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $this->importRow('ABENG STORE', 177000);
        $this->importRow('ABENG STORE', 177000);
        $this->assertCount(2, $this->openingInvoices());

        $this->undo();

        $this->assertCount(2, $this->openingInvoices(), 'Nothing is removed without --apply.');
    }

    public function test_it_removes_the_empty_twin_and_keeps_the_one_holding_money(): void
    {
        $this->importRow('ALIAN', 35100);
        $this->importRow('ALIAN', 35100);

        // The collector happened to open the SECOND copy, so that is the one
        // carrying the payment — and the one that must survive.
        $paid = $this->openingInvoices()->sortBy('id')->last();
        $paid->update(['amount_paid' => 35100, 'balance_due' => 0]);

        $this->undo(true);

        $left = $this->openingInvoices();
        $this->assertCount(1, $left);
        $this->assertSame($paid->id, $left->first()->id, 'The copy with the money stays.');
    }

    public function test_the_ledger_comes_back_down_with_it(): void
    {
        $this->importRow('KATHRENE', 279450);
        $this->importRow('KATHRENE', 279450);

        $before = $this->equityTotal();
        $this->assertSame(558900.0, $before, 'Both runs posted to equity.');

        $this->undo(true);

        $this->assertSame(279450.0, $this->equityTotal(), 'Equity is no longer doubled.');
        $this->assertSame(1, JournalEntry::where('entry_type', 'opening_entry')->count());
    }

    public function test_an_unpaired_opening_invoice_is_left_alone(): void
    {
        $this->importRow('MONTUNO', 9900);

        $this->undo(true);

        $this->assertCount(1, $this->openingInvoices(), 'A single entry is not a duplicate.');
    }

    public function test_two_different_customers_at_the_same_amount_are_not_a_pair(): void
    {
        $this->importRow('HADJI AL', 20100);
        $this->importRow('HADJI BEN', 20100);

        $this->undo(true);

        $this->assertCount(2, $this->openingInvoices());
    }

    public function test_a_twin_carrying_a_receipt_is_never_deleted(): void
    {
        $this->importRow('MICHAEL', 72750);
        $this->importRow('MICHAEL', 72750);

        // Both copies took money: deleting either would destroy a real receipt,
        // so the pair is reported and left for a person to settle.
        foreach ($this->openingInvoices() as $invoice) {
            $invoice->update(['amount_paid' => 1000, 'balance_due' => 71750]);
        }

        $output = $this->undo(true);

        $this->assertCount(2, $this->openingInvoices(), 'Nothing is deleted.');
        $this->assertStringContainsString('needs a person', $output);
    }

    public function test_the_order_behind_a_deleted_invoice_goes_too(): void
    {
        $this->importRow('BAMA', 60220);
        $this->importRow('BAMA', 60220);

        $this->undo(true);

        $this->assertSame(1, SalesOrder::where('so_number', 'like', 'SO-OB%')->count(),
            'An opening order with no invoice is litter.');
    }

    public function test_a_pair_split_across_two_customer_records_is_still_a_pair(): void
    {
        // Production holds seven names on two customer records each. Matching on
        // the customer id would miss those pairs entirely and leave the debt
        // doubled under two spellings of one shop.
        $this->importRow('DELA CRUZ', 23050);
        $original = $this->openingInvoices()->first();

        $twinCustomer = Customer::create(['name' => 'DELA CRUZ', 'address' => 'Same shop, typed twice', 'added_by_id' => $this->user->id]);
        $this->importRow('DELA CRUZ', 23050);
        $twin = $this->openingInvoices()->sortBy('id')->last();
        $twin->sales_order->update(['customer_id' => $twinCustomer->id]);

        $this->assertSame(2, Customer::where('name', 'DELA CRUZ')->count());

        $output = $this->undo(true);

        $left = $this->openingInvoices();
        $this->assertCount(1, $left, 'Still one pair, across two customer records.');
        $this->assertSame($original->id, $left->first()->id);
        $this->assertStringContainsString('DELA CRUZ', $output, 'And it names the record to merge.');
    }

    public function test_running_it_twice_is_harmless(): void
    {
        $this->importRow('JOE', 39000);
        $this->importRow('JOE', 39000);

        $this->undo(true);
        $this->undo(true);

        $this->assertCount(1, $this->openingInvoices());
    }

    public function test_a_whole_sheet_imported_twice_comes_back_to_the_sheet(): void
    {
        // The production shape: one sheet, run twice, with collections since
        // landing on whichever copy the collector happened to open.
        $sheet = [
            ['ABENG STORE', 177000], ['ADHEEN', 31300], ['ALIAN', 35100],
            ['ARSENIA', 48100], ['BAMA', 60220], ['CARPIO', 20000],
            ['DAVE STORE', 66400], ['HALIM', 79000], ['JOMAR', 192000],
            ['KATHRENE', 279450], ['MICHAEL', 190550], ['MY2x', 183400],
        ];
        $sheetTotal = array_sum(array_column($sheet, 1));

        foreach ([1, 2] as $run) {
            foreach ($sheet as [$name, $amount]) {
                $this->importRow($name, $amount);
            }
        }

        $all = $this->openingInvoices()->sortBy('id')->values();
        $this->assertCount(24, $all);
        $this->assertSame((float) $sheetTotal * 2, (float) $all->sum('amount_due'));

        // Payments land on copy 2 for most, copy 1 for a couple, some partial.
        $paidOnSecond = [2, 5, 8, 11];   // index within the second batch
        foreach ($paidOnSecond as $i) {
            $invoice = $all[12 + $i];
            $invoice->update(['amount_paid' => $invoice->amount_due, 'balance_due' => 0]);
        }
        $all[10]->update(['amount_paid' => 50000, 'balance_due' => $all[10]->amount_due - 50000]);

        $collected = (float) $this->openingInvoices()->sum('amount_paid');

        $this->undo(true);

        $left = $this->openingInvoices();
        $this->assertCount(12, $left, 'One copy of each row survives.');
        $this->assertSame((float) $sheetTotal, (float) $left->sum('amount_due'),
            'Receivables match the sheet again.');
        $this->assertSame($collected, (float) $left->sum('amount_paid'),
            'Every peso collected is still on the books.');
        $this->assertSame((float) $sheetTotal, $this->equityTotal(),
            'Opening Balance Equity is no longer doubled.');
    }

    private function equityTotal(): float
    {
        return (float) DB::table('journal_entry_lines as jl')
            ->join('accounts as a', 'a.id', '=', 'jl.account_id')
            ->where('a.code', '3900')->where('jl.line_type', 'credit')
            ->sum('jl.amount');
    }
}
