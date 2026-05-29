<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Trial Balance</title>
    <style>
        @page { size: A4 portrait; margin: 22px 24px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #111827; }
        .header { border-bottom: 2px solid #1a3a32; padding-bottom: 8px; margin-bottom: 12px; }
        .header h1 { margin: 0; font-size: 15px; color: #1a3a32; }
        .header p  { margin: 3px 0 0; color: #4b5563; font-size: 9px; }
        .meta { display: flex; gap: 24px; margin-bottom: 12px; font-size: 9px; color: #374151; }
        .meta span strong { color: #111827; }
        table { width: 100%; border-collapse: collapse; }
        thead th { background: #edf5f2; color: #335c52; font-size: 9px; text-transform: uppercase;
                   padding: 5px 7px; border-bottom: 1px solid #b8d9cc; text-align: left; }
        thead th.num { text-align: right; }
        tbody td { padding: 4px 7px; border-bottom: 1px solid #f0f5f3; font-size: 9.5px; }
        tbody td.num { text-align: right; }
        .group-row td { background: #f4faf8; font-weight: bold; font-size: 9px;
                        text-transform: uppercase; letter-spacing: .04em; padding: 4px 7px;
                        border-top: 1px solid #c8e3da; color: #335c52; }
        .subtotal-row td { background: #f9fcfb; font-style: italic; font-size: 9px;
                           color: #4a6b62; border-top: 1px dashed #c9e0d8; }
        .grand-total { background: #e4f0eb; }
        .grand-total td { font-weight: bold; font-size: 10px; padding: 6px 7px;
                          border-top: 2px solid #3d8d7a; }
        .code { color: #94a3b8; font-size: 8.5px; }
        .chip { display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 8px; font-weight: bold; }
        .asset     { background: #dbeafe; color: #1e4d8c; }
        .liability { background: #fee2e2; color: #7c2d12; }
        .equity    { background: #ede9fe; color: #5b21b6; }
        .revenue   { background: #dcfce7; color: #166534; }
        .expense   { background: #fef3c7; color: #92400e; }
        .balanced   { color: #166534; font-weight: bold; }
        .unbalanced { color: #b45309; font-weight: bold; }
        .footer { margin-top: 14px; font-size: 8.5px; color: #9ca3af; text-align: right; }
    </style>
</head>
<body>
<div class="header">
    <h1>Trial Balance</h1>
    <p>All accounts with debit and credit activity for the selected period.</p>
</div>
<div class="meta">
    <span><strong>Period:</strong> {{ $filters['date_from'] ?? 'All' }} to {{ $filters['date_to'] ?? 'All' }}</span>
    <span><strong>Accounts:</strong> {{ count($rows) }}</span>
    <span><strong>Status:</strong>
        <span class="{{ $totals['is_balanced'] ? 'balanced' : 'unbalanced' }}">
            {{ $totals['is_balanced'] ? 'Balanced ✓' : 'Unbalanced !' }}
        </span>
    </span>
    <span><strong>Generated:</strong> {{ now()->format('Y-m-d H:i') }}</span>
</div>

<table>
    <thead>
        <tr>
            <th style="width:8%">Code</th>
            <th>Account</th>
            <th style="width:12%">Type</th>
            <th class="num" style="width:14%">Debit</th>
            <th class="num" style="width:14%">Credit</th>
            <th class="num" style="width:14%">Balance</th>
        </tr>
    </thead>
    <tbody>
        @php $order = ['asset','liability','equity','revenue','expense']; @endphp
        @foreach($order as $type)
            @php $group = array_filter($rows, fn($r) => ($r['type'] ?? '') === $type); @endphp
            @if(count($group))
                <tr class="group-row"><td colspan="6">{{ ucfirst($type) }}</td></tr>
                @foreach($group as $row)
                <tr>
                    <td class="code">{{ $row['code'] }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td><span class="chip {{ $type }}">{{ ucfirst($type) }}</span></td>
                    <td class="num">{{ $row['debit_total_formatted'] }}</td>
                    <td class="num">{{ $row['credit_total_formatted'] }}</td>
                    <td class="num">{{ $row['balance_formatted'] }}</td>
                </tr>
                @endforeach
            @endif
        @endforeach
    </tbody>
    <tfoot>
        <tr class="grand-total">
            <td colspan="3">Grand Total</td>
            <td class="num">{{ $totals['debits'] }}</td>
            <td class="num">{{ $totals['credits'] }}</td>
            <td class="num">{{ $totals['difference'] }}</td>
        </tr>
    </tfoot>
</table>
<div class="footer">BRT Accounting System &mdash; Trial Balance Report</div>
</body>
</html>
