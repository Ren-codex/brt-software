<?php

namespace App\Console\Commands;

use App\Models\ArInvoice;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Receipt;
use App\Models\SalesOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Removes the second copy of an opening-receivables import that was run twice.
 *
 * The importer numbers each invoice freshly rather than using the reference on
 * the sheet, so a second run collides with nothing: it simply doubles both
 * Accounts Receivable and Opening Balance Equity.
 *
 * Deleting the later batch wholesale would destroy real money, because
 * collections made since have landed on whichever copy the collector opened.
 * So this works pair by pair: keep the copy holding the payments, delete its
 * empty twin along with the order behind it and the journal entry that put it
 * on the books. A pair where both copies took money is reported, never
 * touched — that one needs a person.
 *
 * Dry run unless --apply.
 */
class UndoDuplicateOpeningReceivables extends Command
{
    protected $signature = 'accounting:undo-duplicate-opening-ar
        {--apply : Commit the removal. Without it nothing is saved.}';

    protected $description = 'Remove duplicate opening AR invoices left by a repeated import (dry run unless --apply)';

    public function handle(): int
    {
        $invoices = ArInvoice::with(['sales_order.customer'])
            ->whereHas('sales_order', fn ($q) => $q->where('so_number', 'like', 'SO-OB%'))
            ->orderBy('id')
            ->get();

        if ($invoices->isEmpty()) {
            $this->info('No opening receivables on the books.');

            return self::SUCCESS;
        }

        // Grouped by what the sheet actually said, not by customer id: a second
        // run can invent a second customer record for the same name, and those
        // two rows are still the same debt.
        $groups = $invoices->groupBy(fn ($i) => implode('|', [
            mb_strtoupper(trim((string) optional(optional($i->sales_order)->customer)->name)),
            number_format((float) $i->amount_due, 2, '.', ''),
            (string) $i->invoice_date,
        ]))->filter(fn ($g) => $g->count() > 1);

        if ($groups->isEmpty()) {
            $this->info('No duplicated opening receivables found.');

            return self::SUCCESS;
        }

        $doomed = collect();
        $needsPerson = collect();

        foreach ($groups as $group) {
            $withMoney = $group->filter(fn ($i) => $this->holdsMoney($i));

            if ($withMoney->count() > 1) {
                $needsPerson->push($group);

                continue;
            }

            // The keeper is the copy carrying the money; with none, the one
            // entered first, because the later batch is the mistake.
            $keeper = $withMoney->first() ?: $group->sortBy('id')->first();
            foreach ($group as $invoice) {
                if ($invoice->id !== $keeper->id) {
                    $doomed->push($invoice);
                }
            }
        }

        $this->report($groups, $doomed, $needsPerson);

        if ($doomed->isEmpty()) {
            return self::SUCCESS;
        }

        if (! $this->option('apply')) {
            $this->newLine();
            $this->warn('Dry run. Nothing was changed. Re-run with --apply to commit.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($doomed) {
            foreach ($doomed as $invoice) {
                $this->remove($invoice);
            }
        });

        $this->newLine();
        $this->info('Removed '.$doomed->count().' duplicate opening invoice(s).');
        $this->emptyCustomers();

        return self::SUCCESS;
    }

    /** Anything that makes a copy the real one: a payment, or a receipt against it. */
    private function holdsMoney(ArInvoice $invoice): bool
    {
        return (float) $invoice->amount_paid > 0
            || Receipt::where('ar_invoice_id', $invoice->id)->exists();
    }

    private function report($groups, $doomed, $needsPerson): void
    {
        $this->info('Opening receivables: '.$groups->count().' duplicated entr(ies) found.');

        foreach ($needsPerson as $group) {
            $this->error('  Both copies took money, so this one needs a person: '
                .optional(optional($group->first()->sales_order)->customer)->name
                .'  '.number_format((float) $group->first()->amount_due, 2)
                .'  '.$group->pluck('invoice_number')->implode(', '));
        }

        foreach ($doomed as $invoice) {
            $this->line('  delete '.$invoice->invoice_number
                .'  ('.optional(optional($invoice->sales_order)->customer)->name.')'
                .'  '.number_format((float) $invoice->amount_due, 2)
                .'  order '.optional($invoice->sales_order)->so_number);
        }

        $this->newLine();
        $this->line('  To remove: '.$doomed->count().' invoice(s), '
            .number_format((float) $doomed->sum('amount_due'), 2).' off Accounts Receivable and Opening Balance Equity.');
    }

    /** The invoice, the order that only exists to carry it, and its ledger entry. */
    private function remove(ArInvoice $invoice): void
    {
        $entries = JournalEntry::where('source_type', ArInvoice::class)
            ->where('source_id', $invoice->id)->get();
        foreach ($entries as $entry) {
            DB::table('journal_entry_lines')->where('journal_entry_id', $entry->id)->delete();
            $entry->delete();
        }

        $order = $invoice->sales_order;
        $invoice->delete();

        // Only if nothing else ever attached to it. An opening order carries no
        // items, deliveries or returns, but a guard costs nothing.
        if ($order && ! ArInvoice::where('sales_order_id', $order->id)->exists()) {
            $hasHistory = DB::table('sales_order_items')->where('sales_order_id', $order->id)->exists()
                || DB::table('sales_order_incentives')->where('sales_order_id', $order->id)->exists()
                || DB::table('sales_returns')->where('sales_order_id', $order->id)->exists()
                || DB::table('sales_order_delivery_refusals')->where('sales_order_id', $order->id)->exists();

            if (! $hasHistory) {
                $order->delete();
            }
        }
    }

    /** Customer records the repeated run invented, now holding nothing. */
    private function emptyCustomers(): void
    {
        $names = Customer::select('name')->groupBy('name')
            ->havingRaw('COUNT(*) > 1')->pluck('name');

        if ($names->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->warn('Customer names held by more than one record — merge these by hand:');
        foreach ($names as $name) {
            $rows = Customer::where('name', $name)->orderBy('id')->get();
            $parts = $rows->map(function ($c) {
                $orders = SalesOrder::where('customer_id', $c->id)->count();

                return '#'.$c->id.' ('.$orders.' order'.($orders === 1 ? '' : 's').')';
            });
            $this->line('  '.$name.': '.$parts->implode(', '));
        }
    }
}
