<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Balance Sheet</title>
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
        .section-label { font-weight: bold; font-size: 9px; text-transform: uppercase;
                         letter-spacing: .05em; padding: 5px 7px; }
        .section-assets     { background: #eff6ff; color: #1e4d8c; border-top: 1px solid #bfdbfe; }
        .section-liabilities{ background: #fff7ed; color: #92400e; border-top: 1px solid #fed7aa; }
        .section-equity     { background: #f5f3ff; color: #5b21b6; border-top: 1px solid #ddd6fe; }
        tbody td { padding: 4px 7px; border-bottom: 1px solid #f0f5f3; font-size: 9.5px; }
        .code { color: #94a3b8; font-size: 8.5px; width: 8%; }
        .num { text-align: right; width: 22%; }
        .subtotal td { background: #f4faf8; font-weight: bold; font-size: 9.5px;
                       border-top: 1px solid #dceee8; }
        .earnings-row td { background: #fafffe; font-style: italic; color: #3d6b5f; }
        .verify { margin-top: 14px; padding: 10px 12px; border-radius: 8px; font-size: 9.5px; }
        .verify.balanced   { background: #f0fdf4; border: 1px solid #86efac; color: #14532d; }
        .verify.unbalanced { background: #fef2f2; border: 1px solid #fca5a5; color: #7f1d1d; }
        .verify strong { font-size: 10px; }
        .footer { margin-top: 14px; font-size: 8.5px; color: #9ca3af; text-align: right; }
    </style>
</head>
<body>
<div class="header">
    <h1>Balance Sheet</h1>
    <p>Statement of Financial Position — Assets must equal Liabilities + Equity.</p>
</div>
<div class="meta">
    <span><strong>Period:</strong> {{ $filters['date_from'] ?? 'All' }} to {{ $filters['date_to'] ?? 'All' }}</span>
    <span><strong>Generated:</strong> {{ now()->format('Y-m-d H:i') }}</span>
</div>

<table>
    <tbody>
        <!-- ASSETS -->
        <tr><td colspan="3" class="section-label section-assets">Assets</td></tr>
        @foreach($assetAccounts as $acct)
        <tr>
            <td class="code">{{ $acct['code'] }}</td>
            <td>{{ $acct['name'] }}</td>
            <td class="num">{{ $acct['balance_formatted'] }}</td>
        </tr>
        @endforeach
        @if(count($assetAccounts) === 0)
        <tr><td colspan="3" style="color:#9ca3af;font-style:italic;padding:4px 7px">No asset accounts posted.</td></tr>
        @endif
        <tr class="subtotal"><td colspan="2" style="text-align:right;padding:4px 7px">Total Assets</td><td class="num">{{ $totals['assets'] }}</td></tr>

        <!-- LIABILITIES -->
        <tr><td colspan="3" class="section-label section-liabilities">Liabilities</td></tr>
        @foreach($liabilityAccounts as $acct)
        <tr>
            <td class="code">{{ $acct['code'] }}</td>
            <td>{{ $acct['name'] }}</td>
            <td class="num">{{ $acct['balance_formatted'] }}</td>
        </tr>
        @endforeach
        @if(count($liabilityAccounts) === 0)
        <tr><td colspan="3" style="color:#9ca3af;font-style:italic;padding:4px 7px">No liability accounts posted.</td></tr>
        @endif
        <tr class="subtotal"><td colspan="2" style="text-align:right;padding:4px 7px">Total Liabilities</td><td class="num">{{ $totals['liabilities'] }}</td></tr>

        <!-- EQUITY -->
        <tr><td colspan="3" class="section-label section-equity">Equity</td></tr>
        @foreach($equityAccounts as $acct)
        <tr>
            <td class="code">{{ $acct['code'] }}</td>
            <td>{{ $acct['name'] }}</td>
            <td class="num">{{ $acct['balance_formatted'] }}</td>
        </tr>
        @endforeach
        <tr class="earnings-row">
            <td class="code">—</td>
            <td>Current Period Earnings</td>
            <td class="num">{{ $totals['current_period_earnings'] }}</td>
        </tr>
        <tr class="subtotal"><td colspan="2" style="text-align:right;padding:4px 7px">Total Equity</td><td class="num">{{ $totals['total_equity'] }}</td></tr>
    </tbody>
</table>

<div class="verify {{ $totals['is_balanced'] ? 'balanced' : 'unbalanced' }}">
    @if($totals['is_balanced'])
        ✓ <strong>Books are balanced</strong> — Total Assets ({{ $totals['assets'] }}) = Liabilities + Equity ({{ $totals['liabilities_and_equity'] }})
    @else
        ✗ <strong>Out of balance</strong> — Total Assets ({{ $totals['assets'] }}) ≠ Liabilities + Equity ({{ $totals['liabilities_and_equity'] }})
    @endif
</div>
<div class="footer">BRT Accounting System &mdash; Balance Sheet Report</div>
</body>
</html>
