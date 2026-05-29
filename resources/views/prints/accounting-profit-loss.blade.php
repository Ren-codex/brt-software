<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profit & Loss</title>
    <style>
        @page { size: A4 portrait; margin: 22px 24px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #111827; }
        .header { border-bottom: 2px solid #1a3a32; padding-bottom: 8px; margin-bottom: 12px; }
        .header h1 { margin: 0; font-size: 15px; color: #1a3a32; }
        .header p  { margin: 3px 0 0; color: #4b5563; font-size: 9px; }
        .meta { margin-bottom: 12px; font-size: 9px; color: #374151; }
        .meta span { margin-right: 20px; }
        .meta span strong { color: #111827; }
        table { width: 100%; border-collapse: collapse; }
        .section-label { background: #f4faf8; font-weight: bold; font-size: 9px;
                         text-transform: uppercase; letter-spacing: .05em;
                         padding: 5px 7px; color: #335c52; border-top: 1px solid #c8e3da; }
        tbody td { padding: 4px 7px; border-bottom: 1px solid #f0f5f3; font-size: 9.5px; }
        .code { color: #94a3b8; font-size: 8.5px; width: 8%; }
        .num { text-align: right; width: 16%; }
        .subtotal td { background: #f4faf8; font-weight: bold; font-size: 9.5px;
                       border-top: 1px solid #dceee8; }
        .result-row td { font-weight: bold; font-size: 10.5px; padding: 7px; }
        .result-positive { background: #f0fdf4; color: #14532d; border-top: 2px solid #86efac; }
        .result-negative { background: #fef2f2; color: #7f1d1d; border-top: 2px solid #fca5a5; }
        .gross-row { background: #f0fdf4; color: #155e2e; border-top: 1px solid #d1fae5; }
        .footer { margin-top: 14px; font-size: 8.5px; color: #9ca3af; text-align: right; }
    </style>
</head>
<body>
<div class="header">
    <h1>Income Statement (Profit &amp; Loss)</h1>
    <p>Revenue, cost of sales, and operating expenses for the selected period.</p>
</div>
<div class="meta">
    <span><strong>Period:</strong> {{ $filters['date_from'] ?? 'All' }} to {{ $filters['date_to'] ?? 'All' }}</span>
    <span><strong>Generated:</strong> {{ now()->format('Y-m-d H:i') }}</span>
</div>

<table>
    <tbody>
        <!-- REVENUE -->
        <tr><td colspan="3" class="section-label">Revenue</td></tr>
        @foreach($revenueAccounts as $acct)
        <tr>
            <td class="code">{{ $acct['code'] }}</td>
            <td>{{ $acct['name'] }}</td>
            <td class="num">{{ $acct['balance_formatted'] }}</td>
        </tr>
        @endforeach
        @if(count($revenueAccounts) === 0)
        <tr><td colspan="3" style="color:#9ca3af;font-style:italic;padding:4px 7px">No revenue accounts posted.</td></tr>
        @endif
        <tr class="subtotal"><td colspan="2" style="text-align:right;padding:4px 7px">Total Revenue</td><td class="num">{{ $totals['revenue'] }}</td></tr>

        <!-- COST OF SALES -->
        <tr><td colspan="3" class="section-label">Cost of Sales</td></tr>
        @foreach($costOfSalesAccounts as $acct)
        <tr>
            <td class="code">{{ $acct['code'] }}</td>
            <td>{{ $acct['name'] }}</td>
            <td class="num">{{ $acct['balance_formatted'] }}</td>
        </tr>
        @endforeach
        @if(count($costOfSalesAccounts) === 0)
        <tr><td colspan="3" style="color:#9ca3af;font-style:italic;padding:4px 7px">No cost-of-sales accounts posted.</td></tr>
        @endif
        <tr class="subtotal"><td colspan="2" style="text-align:right;padding:4px 7px">Total Cost of Sales</td><td class="num">{{ $totals['cost_of_sales'] }}</td></tr>

        <!-- GROSS PROFIT -->
        <tr class="result-row gross-row">
            <td colspan="2">Gross Profit</td>
            <td class="num">{{ $totals['gross_profit'] }}</td>
        </tr>

        <!-- OPERATING EXPENSES -->
        <tr><td colspan="3" class="section-label">Operating Expenses</td></tr>
        @foreach($operatingExpenseAccounts as $acct)
        <tr>
            <td class="code">{{ $acct['code'] }}</td>
            <td>{{ $acct['name'] }}</td>
            <td class="num">{{ $acct['balance_formatted'] }}</td>
        </tr>
        @endforeach
        @if(count($operatingExpenseAccounts) === 0)
        <tr><td colspan="3" style="color:#9ca3af;font-style:italic;padding:4px 7px">No operating expense accounts posted.</td></tr>
        @endif
        <tr class="subtotal"><td colspan="2" style="text-align:right;padding:4px 7px">Total Operating Expenses</td><td class="num">{{ $totals['operating_expenses'] }}</td></tr>

        <!-- NET INCOME -->
        <tr class="result-row {{ ($totals['net_income_raw'] ?? 0) >= 0 ? 'result-positive' : 'result-negative' }}">
            <td colspan="2">Net Income</td>
            <td class="num">{{ $totals['net_income'] }}</td>
        </tr>
    </tbody>
</table>
<div class="footer">BRT Accounting System &mdash; Income Statement Report</div>
</body>
</html>
