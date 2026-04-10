<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal Entries Report</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 18px 16px;
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            color: #000;
            margin: 0;
        }

        .entry-block {
            margin-bottom: 18px;
            page-break-inside: avoid;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        td, th {
            padding: 5px 7px;
            vertical-align: middle;
        }

        .je-row td {
            border-bottom: 1px solid #000;
        }

        .meta-label {
            width: 16%;
            font-weight: 700;
        }

        .meta-value {
            font-weight: 700;
        }

        .header-row th {
            font-weight: 700;
            text-align: left;
        }

        .date-col {
            width: 17%;
        }

        .account-col {
            width: 45%;
        }

        .amount-col {
            width: 19%;
        }

        .amount-cell {
            text-align: right;
        }

        .credit-account {
            padding-left: 36px;
        }

        .memo-row td {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            text-align: center;
            font-style: italic;
            letter-spacing: 0.2px;
            padding-top: 7px;
            padding-bottom: 7px;
        }

        .no-data {
            text-align: center;
            font-style: italic;
        }

        .range-note {
            margin-bottom: 10px;
            font-size: 10px;
        }
    </style>
</head>
<body>
    @php
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $rangeLabel = $dateFrom && $dateTo
            ? $dateFrom . ' to ' . $dateTo
            : ($dateFrom ? 'From ' . $dateFrom : ($dateTo ? 'Until ' . $dateTo : 'All Dates'));
    @endphp

    <div class="range-note">Report Range: {{ $rangeLabel }}</div>

    @forelse ($entries as $entry)
        <div class="entry-block">
            <table>
                <colgroup>
                    <col class="date-col">
                    <col class="account-col">
                    <col class="amount-col">
                    <col class="amount-col">
                </colgroup>
                <tr class="je-row">
                    <td class="meta-label">J/E NUMBER:</td>
                    <td class="meta-value" colspan="3">{{ $entry['journal_number'] ?? '#' }}</td>
                </tr>
                {{-- <tr class="header-row">
                    <th>DATE</th>
                    <th>ACCOUNT</th>
                    <th>DEBIT</th>
                    <th>CREDIT</th>
                </tr> --}}
                @forelse (($entry['lines'] ?? []) as $index => $line)
                    @php
                        $isCredit = strtolower((string) ($line['line_type'] ?? '')) === 'credit';
                        $amount = number_format((float) ($line['amount'] ?? 0), 2);
                    @endphp
                    <tr>
                        <td>{{ $index === 0 ? ($entry['entry_date'] ?? '') : '' }}</td>
                        <td class="{{ $isCredit ? 'credit-account' : '' }}">{{ $line['account'] ?? '' }}</td>
                        <td class="amount-cell">{{ $isCredit ? '' : $amount }}</td>
                        <td class="amount-cell">{{ $isCredit ? $amount : '' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td>{{ $entry['entry_date'] ?? '' }}</td>
                        <td class="no-data">No journal lines available</td>
                        <td></td>
                        <td></td>
                    </tr>
                @endforelse
                <tr class="memo-row">
                    <td colspan="4">{{ strtoupper(trim((string) ($entry['memo'] ?? ''))) ?: 'NO MEMO PROVIDED' }}</td>
                </tr>
            </table>
        </div>
    @empty
        <div class="entry-block">
            <table>
                <colgroup>
                    <col class="date-col">
                    <col class="account-col">
                    <col class="amount-col">
                    <col class="amount-col">
                </colgroup>
                <tr class="je-row">
                    <td class="meta-label">J/E NUMBER:</td>
                    <td class="meta-value" colspan="3">#</td>
                </tr>
                {{-- <tr class="header-row">
                    <th>DATE</th>
                    <th>ACCOUNT</th>
                    <th>DEBIT</th>
                    <th>CREDIT</th>
                </tr> --}}
                <tr>
                    <td>{{ $rangeLabel }}</td>
                    <td class="no-data">No journal entries found for the selected date range.</td>
                    <td></td>
                    <td></td>
                </tr>
                <tr class="memo-row">
                    <td colspan="4">NO JOURNAL ENTRIES AVAILABLE</td>
                </tr>
            </table>
        </div>
    @endforelse
</body>
</html>
