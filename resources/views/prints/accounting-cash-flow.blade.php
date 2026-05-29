<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cash Flow Statement</title>
    <style>
        @page { size: A4 portrait; margin: 22px 24px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #111827; }
        .header { border-bottom: 2px solid #1a3a32; padding-bottom: 8px; margin-bottom: 12px; }
        .header h1 { margin: 0; font-size: 15px; color: #1a3a32; }
        .header p  { margin: 3px 0 0; color: #4b5563; font-size: 9px; }
        .meta { margin-bottom: 12px; font-size: 9px; color: #374151; }
        .meta span { margin-right: 20px; }
        .meta span strong { color: #111827; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .section-label { font-weight: bold; font-size: 9px; text-transform: uppercase;
                         letter-spacing: .05em; padding: 5px 7px; }
        .section-operating { background: #f0fdf4; color: #166534; border-top: 1px solid #d1fae5; }
        .section-investing  { background: #eff6ff; color: #1e4d8c; border-top: 1px solid #bfdbfe; }
        .section-financing  { background: #f5f3ff; color: #5b21b6; border-top: 1px solid #ddd6fe; }
        tbody td { padding: 4px 7px; border-bottom: 1px solid #f0f5f3; font-size: 9.5px; }
        .label-col { width: 52%; }
        .note-col  { width: 26%; color: #6b7280; font-style: italic; }
        .amt-col   { text-align: right; width: 22%; }
        .inflow  { color: #166534; }
        .outflow { color: #991b1b; }
        .subtotal td { background: #f4faf8; font-weight: bold; font-size: 9.5px;
                       border-top: 1px solid #dceee8; }
        .empty-note { color: #9ca3af; font-style: italic; padding: 4px 7px; font-size: 9px; }
        .net-change { margin-top: 14px; padding: 10px 12px; border-radius: 8px; font-size: 10px; }
        .net-change.positive { background: #f0fdf4; border: 1px solid #86efac; color: #14532d; }
        .net-change.negative { background: #fef2f2; border: 1px solid #fca5a5; color: #7f1d1d; }
        .net-change strong { font-size: 11px; }
        .footer { margin-top: 14px; font-size: 8.5px; color: #9ca3af; text-align: right; }
    </style>
</head>
<body>
<div class="header">
    <h1>Cash Flow Statement</h1>
    <p>Direct method — Operating, Investing, and Financing activities for the selected period.</p>
</div>
<div class="meta">
    <span><strong>Period:</strong> {{ $filters['date_from'] ?? 'All' }} to {{ $filters['date_to'] ?? 'All' }}</span>
    <span><strong>Generated:</strong> {{ now()->format('Y-m-d H:i') }}</span>
</div>

@foreach($sections as $section)
@php
    $labelClass = 'section-' . $section['id'];
    $netRaw = $section['net_raw'];
@endphp
<table>
    <tbody>
        <tr><td colspan="3" class="section-label {{ $labelClass }}">{{ $section['title'] }}</td></tr>
        @if(count($section['rows']) === 0)
            <tr><td colspan="3" class="empty-note">No {{ strtolower($section['title']) }} entries for this period.</td></tr>
        @else
            @foreach($section['rows'] as $row)
            <tr>
                <td class="label-col">{{ $row['label'] }}</td>
                <td class="note-col">{{ $row['note'] }}</td>
                <td class="amt-col {{ $row['direction'] }}">
                    {{ $row['direction'] === 'inflow' ? '' : '(' }}{{ $row['amount'] }}{{ $row['direction'] === 'outflow' ? ')' : '' }}
                </td>
            </tr>
            @endforeach
        @endif
        <tr class="subtotal">
            <td colspan="2" style="text-align:right;padding:4px 7px">Net {{ $section['title'] }}</td>
            <td class="amt-col {{ $netRaw >= 0 ? 'inflow' : 'outflow' }}">
                {{ $netRaw >= 0 ? '' : '(' }}{{ $section['net'] }}{{ $netRaw < 0 ? ')' : '' }}
            </td>
        </tr>
    </tbody>
</table>
@endforeach

@php
    $netChange = $totals['net_change'];
    $netClass  = $netChange >= 0 ? 'positive' : 'negative';
@endphp
<div class="net-change {{ $netClass }}">
    @if($netChange >= 0)
        ▲ <strong>Net Increase in Cash: {{ $totals['net_change_formatted'] }}</strong>
    @else
        ▼ <strong>Net Decrease in Cash: {{ $totals['net_change_formatted'] }}</strong>
    @endif
    &nbsp;&nbsp;
    Operating: {{ $totals['net_operating_formatted'] }} &nbsp;|&nbsp;
    Investing: {{ $totals['net_investing_formatted'] }} &nbsp;|&nbsp;
    Financing: {{ $totals['net_financing_formatted'] }}
</div>

<div class="footer">BRT Accounting System &mdash; Cash Flow Statement</div>
</body>
</html>
