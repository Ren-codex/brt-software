<?php

namespace App\Services\Accounting;

use App\Models\ArInvoice;
use App\Models\Customer;
use App\Models\ListStatus;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Brings the receivables that existed before go-live onto the books, one sheet
 * row per open invoice. Each row becomes a credit sales order marked as an
 * opening balance, an unpaid AR invoice for the amount still owed (so it shows
 * in the aging and can be collected), and a journal entry
 * DR Accounts Receivable / CR Opening Balance Equity.
 *
 * Customers are matched to existing records by name and created when missing.
 * Dry run unless committed by the caller.
 */
class OpeningReceivablesImporter
{
    private array $report;

    public function __construct(private JournalEntryService $journal)
    {
    }

    /** CSV columns: customer, invoice_no, amount, invoice_date, due_date. */
    public static function rowsFromCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open {$path}");
        }

        fgetcsv($handle); // header
        $rows = [];
        $line = 1;
        while (($cells = fgetcsv($handle)) !== false) {
            $line++;
            if (trim(implode('', array_map(fn ($c) => (string) $c, $cells))) === '') {
                continue;
            }
            $rows[] = [
                'customer'     => trim((string) ($cells[0] ?? '')),
                'invoice_no'   => trim((string) ($cells[1] ?? '')),
                'amount'       => self::money($cells[2] ?? ''),
                'invoice_date' => trim((string) ($cells[3] ?? '')),
                'due_date'     => trim((string) ($cells[4] ?? '')),
                'line'         => $line,
            ];
        }
        fclose($handle);

        return $rows;
    }

    public function import(array $rows, User $user, string $date): array
    {
        $this->report = [
            'anomalies' => [],
            'customers' => ['matched' => [], 'created' => []],
            'invoices'  => [],
            'totals'    => ['invoices' => 0, 'amount' => 0.0],
        ];

        $this->report['anomalies'] = $this->anomalies($rows);

        $completed = ListStatus::where('slug', 'completed')->firstOrFail()->id;
        $unpaid = ListStatus::where('slug', 'unpaid')->firstOrFail()->id;

        $byCustomer = collect($rows)->groupBy(fn ($r) => $this->normalise($r['customer']));

        foreach ($byCustomer as $customerRows) {
            $customer = $this->resolveCustomer($customerRows->first()['customer'], $user);

            $custTotal = 0.0;
            $custInvoices = [];

            foreach ($customerRows as $row) {
                if ($row['amount'] === null || $row['amount'] <= 0) {
                    continue;
                }

                $invoiceDate = $this->parseDate($row['invoice_date']) ?? Carbon::parse($date);
                $dueDate = $this->parseDate($row['due_date']) ?? (clone $invoiceDate)->addDays(7);

                $so = new SalesOrder();
                $so->so_number = SalesOrder::generateSoNumber(null, 'SO-OB');
                $so->customer_id = $customer->id;
                $so->payment_mode = 'Credit';
                $so->order_date = $invoiceDate->toDateString();
                $so->due_date = $dueDate->toDateString();
                $so->total_amount = $row['amount'];
                $so->total_discount = 0;
                $so->added_by_id = $user->id;
                $so->status_id = $completed;
                $so->save();

                $invoice = new ArInvoice();
                $invoice->sales_order_id = $so->id;
                $invoice->status_id = $unpaid;
                // Number uses the current period (the invoice DATE stays
                // historical below). Passing the historical date here would
                // collide, because the generator sequences by created_at.
                $invoice->invoice_number = ArInvoice::generateInvoiceNumber();
                $invoice->invoice_date = $invoiceDate->toDateString();
                $invoice->due_date = $dueDate->toDateString();
                $invoice->amount_due = $row['amount'];
                $invoice->amount_paid = 0;
                $invoice->balance_due = $row['amount'];
                $invoice->total_discount = 0;
                $invoice->save();

                $this->journal->recordOpeningReceivable($invoice);

                $custTotal += $row['amount'];
                $custInvoices[] = sprintf(
                    '%s  %s  ref #%s  —  %s  (due %s)',
                    $invoice->invoice_number,
                    $so->so_number,
                    $row['invoice_no'] ?: '—',
                    number_format($row['amount'], 2),
                    $dueDate->toDateString()
                );

                $this->report['totals']['invoices']++;
                $this->report['totals']['amount'] += $row['amount'];
            }

            if ($custInvoices) {
                $this->report['invoices'][] = [
                    'customer' => $customer->name,
                    'total'    => round($custTotal, 2),
                    'lines'    => $custInvoices,
                ];
            }
        }

        $this->report['totals']['amount'] = round($this->report['totals']['amount'], 2);

        return $this->report;
    }

    private function anomalies(array $rows): array
    {
        $found = [];
        foreach ($rows as $row) {
            $where = "Row {$row['line']} ({$row['customer']}, ref #{$row['invoice_no']})";
            if ($row['customer'] === '') {
                $found[] = "{$where}: no customer name.";
            }
            if ($row['amount'] === null || $row['amount'] <= 0) {
                $found[] = "{$where}: amount is missing or not positive.";
            }
            if ($row['invoice_date'] !== '' && $this->parseDate($row['invoice_date']) === null) {
                $found[] = "{$where}: invoice date '{$row['invoice_date']}' is unreadable.";
            }
        }

        return $found;
    }

    private function resolveCustomer(string $name, User $user): Customer
    {
        $key = $this->normalise($name);
        $existing = Customer::all()->first(fn ($c) => $this->normalise($c->name) === $key);

        if ($existing) {
            $this->report['customers']['matched'][] = "{$name} -> {$existing->name}";

            return $existing;
        }

        $customer = new Customer();
        $customer->name = $name;
        $customer->address = '';
        $customer->is_active = 1;
        $customer->is_regular = 0;
        $customer->is_blacklisted = 0;
        $customer->credit_limit = 0;
        $customer->added_by_id = $user->id;
        $customer->save();

        $this->report['customers']['created'][] = $name;

        return $customer;
    }

    private function parseDate(string $value): ?Carbon
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function money(mixed $value): ?float
    {
        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);

        return $clean === '' || $clean === '-' ? null : (float) $clean;
    }

    private function normalise(string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($name));
    }
}
