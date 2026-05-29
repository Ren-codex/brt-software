<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense List Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
            font-size: 12px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
        }
        .header h2 {
            margin: 4px 0 0;
            font-size: 16px;
            font-weight: normal;
            color: #555;
        }
        .header p {
            margin: 4px 0 0;
            font-size: 12px;
            color: #666;
        }
        .filter-bar {
            background-color: #f5f5f5;
            border: 1px solid #ddd;
            padding: 6px 10px;
            margin-bottom: 16px;
            font-size: 11px;
            color: #444;
        }
        .filter-bar span {
            margin-right: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 6px 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
        }
        td {
            font-size: 12px;
        }
        .amount-col {
            text-align: right;
        }
        .status-col {
            text-transform: capitalize;
        }
        .total-row td {
            font-weight: bold;
            background-color: #f9f9f9;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 11px;
            color: #888;
        }
        .no-data {
            text-align: center;
            padding: 20px;
            color: #888;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>BRT Software</h1>
        <h2>Expense List Report</h2>
        <p>Generated: {{ now()->format('M d, Y H:i') }}</p>
    </div>

    <div class="filter-bar">
        <strong>Filters:</strong>
        @if($filters['date_from'] || $filters['date_to'])
            <span>Period: {{ $filters['date_from'] ? \Carbon\Carbon::parse($filters['date_from'])->format('M d, Y') : '—' }} to {{ $filters['date_to'] ? \Carbon\Carbon::parse($filters['date_to'])->format('M d, Y') : '—' }}</span>
        @endif
        @if($filters['fund_name'])
            <span>Fund: {{ $filters['fund_name'] }}</span>
        @endif
        @if($filters['status'])
            <span>Status: {{ ucfirst($filters['status']) }}</span>
        @endif
        @if($filters['expense_type'])
            <span>Type: {{ ucfirst($filters['expense_type']) }}</span>
        @endif
        @if(!$filters['date_from'] && !$filters['date_to'] && !$filters['fund_name'] && !$filters['status'] && !$filters['expense_type'])
            <span>All records (no filters applied)</span>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Type</th>
                <th>Fund</th>
                <th>Description</th>
                <th class="amount-col">Amount (₱)</th>
                <th>Status</th>
                <th>Recorded By</th>
            </tr>
        </thead>
        <tbody>
            @forelse($expenses as $index => $expense)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ \Carbon\Carbon::parse($expense->expense_date)->format('M d, Y') }}</td>
                <td>{{ ucfirst($expense->expense_type) }}</td>
                <td>{{ $expense->fund?->name ?? '—' }}</td>
                <td>{{ $expense->description ?? '—' }}</td>
                <td class="amount-col">{{ number_format($expense->amount, 2) }}</td>
                <td class="status-col">{{ $expense->status }}</td>
                <td>{{ $expense->added_by?->name ?? '—' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="no-data">No expenses found matching the selected filters.</td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="5" class="amount-col">Grand Total:</td>
                <td class="amount-col">{{ number_format($total, 2) }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <p>BRT Software — Expense List Report — {{ now()->format('Y') }}</p>
    </div>
</body>
</html>
