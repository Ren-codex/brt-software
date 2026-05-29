<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class AccountingReportExport implements WithMultipleSheets
{
    public function __construct(
        private string $reportType,
        private array $data,
        private array $filters = []
    ) {}

    public function sheets(): array
    {
        $period = ($this->filters['date_from'] ?? 'All') . ' to ' . ($this->filters['date_to'] ?? 'All');

        $asOf = $this->filters['as_of'] ?? 'Today';

        $meta = [
            [strtoupper(str_replace('_', ' ', $this->reportType))],
            $this->reportType === 'accounts_payable'
                ? ['As Of', $asOf]
                : ['Period', $period],
            ['Generated', now()->format('Y-m-d H:i')],
            [],
        ];

        return match ($this->reportType) {
            'trial_balance'    => [$this->trialBalanceSheet($meta)],
            'profit_loss'      => [$this->profitLossSheet($meta)],
            'balance_sheet'    => [$this->balanceSheetSheet($meta)],
            'cash_flow'        => [$this->cashFlowSheet($meta)],
            'accounts_payable' => $this->accountsPayableSheets($meta),
            default            => [],
        };
    }

    private function trialBalanceSheet(array $meta): AccountingSheet
    {
        $rows = array_merge($meta, [
            ['Code', 'Account', 'Type', 'Debit', 'Credit', 'Balance'],
        ]);

        foreach ($this->data['rows'] ?? [] as $row) {
            $rows[] = [
                $row['code'] ?? '',
                $row['name'] ?? '',
                ucfirst($row['type'] ?? ''),
                (float) ($row['debit_total'] ?? 0),
                (float) ($row['credit_total'] ?? 0),
                (float) ($row['balance'] ?? 0),
            ];
        }

        $totals = $this->data['totals'] ?? [];
        $rows[] = [];
        $rows[] = ['', 'GRAND TOTAL', '', $totals['debits'] ?? '', $totals['credits'] ?? '', $totals['difference'] ?? ''];

        return new AccountingSheet('Trial Balance', $rows);
    }

    private function profitLossSheet(array $meta): AccountingSheet
    {
        $rows = array_merge($meta, [
            ['Section', 'Code', 'Account', 'Amount'],
        ]);

        foreach ($this->data['revenueAccounts'] ?? [] as $acct) {
            $rows[] = ['Revenue', $acct['code'] ?? '', $acct['name'] ?? '', (float) ($acct['balance'] ?? 0)];
        }
        $rows[] = ['', '', 'Total Revenue', $this->data['totals']['revenue'] ?? ''];
        $rows[] = [];

        foreach ($this->data['costOfSalesAccounts'] ?? [] as $acct) {
            $rows[] = ['Cost of Sales', $acct['code'] ?? '', $acct['name'] ?? '', (float) ($acct['balance'] ?? 0)];
        }
        $rows[] = ['', '', 'Total Cost of Sales', $this->data['totals']['cost_of_sales'] ?? ''];
        $rows[] = ['', '', 'Gross Profit', $this->data['totals']['gross_profit'] ?? ''];
        $rows[] = [];

        foreach ($this->data['operatingExpenseAccounts'] ?? [] as $acct) {
            $rows[] = ['Operating Expenses', $acct['code'] ?? '', $acct['name'] ?? '', (float) ($acct['balance'] ?? 0)];
        }
        $rows[] = ['', '', 'Total Operating Expenses', $this->data['totals']['operating_expenses'] ?? ''];
        $rows[] = [];
        $rows[] = ['', '', 'NET INCOME', $this->data['totals']['net_income'] ?? ''];

        return new AccountingSheet('Profit & Loss', $rows);
    }

    private function balanceSheetSheet(array $meta): AccountingSheet
    {
        $rows = array_merge($meta, [
            ['Section', 'Code', 'Account', 'Balance'],
        ]);

        foreach ($this->data['assetAccounts'] ?? [] as $acct) {
            $rows[] = ['Assets', $acct['code'] ?? '', $acct['name'] ?? '', (float) ($acct['balance'] ?? 0)];
        }
        $rows[] = ['', '', 'Total Assets', $this->data['totals']['assets'] ?? ''];
        $rows[] = [];

        foreach ($this->data['liabilityAccounts'] ?? [] as $acct) {
            $rows[] = ['Liabilities', $acct['code'] ?? '', $acct['name'] ?? '', (float) ($acct['balance'] ?? 0)];
        }
        $rows[] = ['', '', 'Total Liabilities', $this->data['totals']['liabilities'] ?? ''];
        $rows[] = [];

        foreach ($this->data['equityAccounts'] ?? [] as $acct) {
            $rows[] = ['Equity', $acct['code'] ?? '', $acct['name'] ?? '', (float) ($acct['balance'] ?? 0)];
        }
        $rows[] = ['Equity', '—', 'Current Period Earnings', $this->data['totals']['current_period_earnings'] ?? ''];
        $rows[] = ['', '', 'Total Equity', $this->data['totals']['total_equity'] ?? ''];
        $rows[] = [];
        $rows[] = ['', '', 'Total Liabilities + Equity', $this->data['totals']['liabilities_and_equity'] ?? ''];

        return new AccountingSheet('Balance Sheet', $rows);
    }

    private function accountsPayableSheets(array $meta): array
    {
        $summaryRows = array_merge($meta, [
            ['Supplier', 'Records', 'Current', '1-30 Days', '31-60 Days', '61-90 Days', '90+ Days', 'Total Outstanding'],
        ]);
        foreach ($this->data['agingRows'] ?? [] as $row) {
            $summaryRows[] = [
                $row['supplier_name'] ?? '',
                $row['record_count']  ?? 0,
                $row['current']       ?? '',
                $row['days_1_30']     ?? '',
                $row['days_31_60']    ?? '',
                $row['days_61_90']    ?? '',
                $row['days_90_plus']  ?? '',
                $row['total_outstanding'] ?? '',
            ];
        }

        $detailRows = array_merge($meta, [
            ['Received No.', 'Supplier', 'Received Date', 'Days Out', 'PO No.', 'Total Cost', 'Paid', 'Balance Due', 'Aging Bucket'],
        ]);
        foreach ($this->data['receivingRows'] ?? [] as $row) {
            $detailRows[] = [
                $row['received_no']   ?? '',
                $row['supplier_name'] ?? '',
                $row['received_date'] ?? '',
                $row['days_out']      ?? 0,
                $row['po_number']     ?? '',
                $row['total_cost']    ?? '',
                $row['amount_paid']   ?? '',
                $row['balance_due']   ?? '',
                $row['bucket']        ?? '',
            ];
        }

        return [
            new AccountingSheet('AP Aging Summary', $summaryRows),
            new AccountingSheet('AP Receiving Detail', $detailRows),
        ];
    }

    private function cashFlowSheet(array $meta): AccountingSheet
    {
        $rows = array_merge($meta, [
            ['Activity', 'Description', 'Note', 'Entries', 'Direction', 'Amount'],
        ]);

        foreach ($this->data['sections'] ?? [] as $section) {
            $rows[] = [];
            $rows[] = [strtoupper($section['title']), '', '', '', '', ''];

            foreach ($section['rows'] ?? [] as $row) {
                $sign   = ($row['direction'] ?? 'inflow') === 'inflow' ? 1 : -1;
                $rows[] = [
                    $section['title'],
                    $row['label']   ?? '',
                    $row['note']    ?? '',
                    $row['entries'] ?? 0,
                    ucfirst($row['direction'] ?? ''),
                    (float) ($row['amount_raw'] ?? 0) * $sign,
                ];
            }

            $rows[] = ['', '', '', '', 'Net ' . $section['title'], (float) ($section['net_raw'] ?? 0)];
        }

        $totals = $this->data['totals'] ?? [];
        $rows[] = [];
        $rows[] = ['', '', '', '', 'NET CHANGE IN CASH', (float) ($totals['net_change'] ?? 0)];

        return new AccountingSheet('Cash Flow', $rows);
    }
}

class AccountingSheet implements FromArray, WithTitle, ShouldAutoSize
{
    public function __construct(
        private string $title,
        private array $rows
    ) {}

    public function array(): array { return $this->rows; }
    public function title(): string { return $this->title; }
}
