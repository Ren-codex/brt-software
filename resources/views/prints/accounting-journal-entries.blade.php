<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal Entries Report</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 12px 14px;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #183235;
            background: #ffffff;
            font-size: 11px;
        }

        .report {
            width: 100%;
        }

        .report-header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .report-header td {
            vertical-align: middle;
        }

        .report-branding {
            width: 42%;
        }

        .report-meta {
            text-align: right;
        }

        .report-brand-table {
            width: auto;
            border-collapse: collapse;
            table-layout: auto;
        }

        .report-brand-copy {
            padding-left: 6px;
        }

        .report-logo-cell {
            width: 72px;
            padding-right: 12px;
        }

        .report-logo-box {
            width: 60px;
            height: 60px;
            text-align: center;
            vertical-align: middle;
        }

        .report-logo {
            width: 60px;
            height: 60px;
            object-fit: contain;
        }

        .report-logo-fallback {
            width: 60px;
            height: 60px;
            border-radius: 4px;
            background: #d8e7da;
        }

        .report-company-name {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            color: #1a1a1a;
            line-height: 1.15;
        }

        .report-heading {
            margin: 0 0 8px;
            font-size: 21px;
            font-weight: 700;
            color: #1f6660;
        }

        .report-period {
            margin: 0 0 6px;
            font-size: 11px;
            color: #253746;
        }

        .report-range {
            margin: 0 0 12px;
            font-size: 10px;
            color: #5a6872;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .journal-table thead th {
            padding: 7px 8px;
            font-size: 10px;
            font-weight: 700;
            text-align: center;
            color: #235f59;
            background: #d8e7da;
            border-bottom: 1px solid #cfddd1;
        }

        .journal-table tbody td {
            padding: 6px 8px;
            vertical-align: top;
            border-bottom: 1px solid #d6dddf;
            color: #16212a;
            word-wrap: break-word;
        }

        .date-col {
            width: 10%;
        }

        .account-col {
            width: 15%;
        }

        .description-col {
            width: 55%;
        }

        .amount-col {
            width: 10%;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .account-credit {
            padding-left: 18px !important;
        }

        .journal-number {
            display: block;
            margin-top: 4px;
            font-size: 8px;
            letter-spacing: 0.5px;
            color: #4c6677;
        }

        .description-cell {
            font-size: 11px;
            line-height: 1.55;
        }

        .date-cell,
        .amount-cell {
            white-space: nowrap;
        }

        .muted {
            color: #8b98a1;
        }

        .journal-table tfoot td {
            padding: 14px 10px 0;
            font-size: 11px;
        }

        .total-label {
            text-align: right;
            font-weight: 700;
            color: #16212a;
        }

        .total-amount {
            text-align: right;
            font-weight: 700;
            font-size: 12px;
            color: #16212a;
            border-top: 2px solid #2f6f63;
        }

        .balance-note {
            margin-top: 26px;
            text-align: right;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.6px;
        }

        .is-balanced {
            color: #3e7f50;
        }

        .out-of-balance {
            color: #a94442;
        }

        .no-data {
            text-align: center;
            font-style: italic;
            padding: 18px 10px !important;
        }
    </style>
</head>
<body>
    @php
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $periodReference = $dateTo ?: $dateFrom;
        $periodLabel = $periodReference
            ? \Carbon\Carbon::parse($periodReference)->format('F d, Y')
            : 'All Dates';
        $rangeLabel = $dateFrom && $dateTo
            ? $dateFrom . ' to ' . $dateTo
            : ($dateFrom ? 'From ' . $dateFrom : ($dateTo ? 'Until ' . $dateTo : 'All Dates'));
        $rows = collect($entries)->flatMap(function ($entry) {
            $lines = collect($entry['lines'] ?? [])->values();
            $entryDescription = trim((string) ($entry['memo'] ?? ''));

            if ($entryDescription === '') {
                $entryDescription = (string) $lines
                    ->pluck('description')
                    ->map(function ($description) {
                        return trim((string) $description);
                    })
                    ->first(function ($description) {
                        return $description !== '';
                    });
            }

            if ($lines->isEmpty()) {
                return [[
                    'entry_date' => $entry['entry_date'] ?? '',
                    'journal_number' => $entry['journal_number'] ?? '#',
                    'account' => 'No journal lines available',
                    'description' => $entryDescription !== '' ? $entryDescription : 'No journal lines available.',
                    'debit' => null,
                    'credit' => null,
                    'is_credit' => false,
                    'is_first_row' => true,
                    'is_empty' => true,
                ]];
            }

            return $lines->map(function ($line, $index) use ($entry, $entryDescription) {
                $isCredit = strtolower((string) ($line['line_type'] ?? '')) === 'credit';
                $amount = (float) ($line['amount'] ?? 0);

                return [
                    'entry_date' => $index === 0 ? ($entry['entry_date'] ?? '') : '',
                    'journal_number' => $index === 0 ? ($entry['journal_number'] ?? '#') : '',
                    'account' => trim((string) ($line['account'] ?? '')) ?: 'Unmapped Account',
                    'description' => $index === 0 ? $entryDescription : '',
                    'debit' => $isCredit ? null : $amount,
                    'credit' => $isCredit ? $amount : null,
                    'is_credit' => $isCredit,
                    'is_first_row' => $index === 0,
                    'is_empty' => false,
                ];
            });
        })->values();
        $hasRows = $rows->contains(function ($row) {
            return !($row['is_empty'] ?? false);
        });
        $debitTotal = $rows->sum(function ($row) {
            return (float) ($row['debit'] ?? 0);
        });
        $creditTotal = $rows->sum(function ($row) {
            return (float) ($row['credit'] ?? 0);
        });
        $isBalanced = abs($debitTotal - $creditTotal) < 0.005;
        $logoPath = public_path('images/brt-logo.png');
        $companyName = 'BOUYANT RICE TRADING';
    @endphp

    <div class="report">
        <table class="report-header">
            <tr>
                <td class="report-branding">
                    <table class="report-brand-table">
                        <tr>
                            <td class="report-logo-cell">
                                <div class="report-logo-box">
                                    @if (file_exists($logoPath))
                                        <img src="{{ $logoPath }}" alt="Bouyant Rice Trading Logo" class="report-logo">
                                    @else
                                        <div class="report-logo-fallback"></div>
                                    @endif
                                </div>
                            </td>
                            <td class="report-brand-copy">
                                <h2 class="report-company-name">{{ $companyName }}</h2>
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="report-meta">
                    <h1 class="report-heading">Accounting Journal</h1>
                    <p class="report-period">For the Period Ending {{ $periodLabel }}</p>
                    <p class="report-range">Report Range: {{ $rangeLabel }}</p>
                </td>
            </tr>
        </table>

        <table class="journal-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Account</th>
                    <th colspan="2">Description</th>
                    <th>Debit</th>
                    <th>Credit</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="date-cell">
                            @if ($row['entry_date'])
                                {{ $row['entry_date'] }}
                            @else
                                <span class="muted">&nbsp;</span>
                            @endif

                            @if ($row['journal_number'])
                                <span class="journal-number">{{ $row['journal_number'] }}</span>
                            @endif
                        </td>
                        <td class="{{ $row['is_credit'] ? 'account-credit' : '' }}{{ $row['is_empty'] ? ' no-data' : '' }}">
                            {{ $row['account'] }}
                        </td>
                        <td class="description-cell{{ $row['is_empty'] ? ' no-data' : '' }}"  colspan="2">
                            @if ($row['description'] !== '')
                                {{ $row['description'] }}
                            @else
                                <span class="muted">-</span>
                            @endif
                        </td>
                        <td class="text-right amount-cell">
                            @if (is_null($row['debit']))
                                <span class="muted">-</span>
                            @else
                                {{ number_format((float) $row['debit'], 2) }}
                            @endif
                        </td>
                        <td class="text-right amount-cell">
                            @if (is_null($row['credit']))
                                <span class="muted">-</span>
                            @else
                                {{ number_format((float) $row['credit'], 2) }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="no-data">No journal entries found for the selected date range.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="total-label">Totals:</td>
                    <td class="total-amount">{{ number_format($debitTotal, 2) }}</td>
                    <td class="total-amount">{{ number_format($creditTotal, 2) }}</td>
                </tr>
            </tfoot>
        </table>

        <div class="balance-note {{ $hasRows && $isBalanced ? 'is-balanced' : 'out-of-balance' }}">
            {{ $hasRows ? ($isBalanced ? 'IN BALANCE' : 'OUT OF BALANCE') : 'NO JOURNAL ENTRIES' }}
        </div>
    </div>
</body>
</html>
