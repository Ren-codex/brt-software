<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Accounts Payable Aging</title>
    <style>
        @page { size: A4 landscape; margin: 18px 22px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; color: #111827; }
        .header { border-bottom: 2px solid #1a3a32; padding-bottom: 7px; margin-bottom: 10px; }
        .header h1 { margin: 0; font-size: 14px; color: #1a3a32; }
        .header p  { margin: 2px 0 0; color: #4b5563; font-size: 8px; }
        .meta { margin-bottom: 10px; font-size: 8px; color: #374151; }
        .meta span { margin-right: 20px; }
        .meta span strong { color: #111827; }
        .section-title { font-size: 9px; font-weight: bold; text-transform: uppercase;
                         letter-spacing: .05em; color: #335c52; background: #edf5f2;
                         padding: 4px 6px; border-top: 1px solid #c8e3da; margin: 12px 0 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        thead th { background: #edf5f2; color: #527267; font-size: 8px; font-weight: bold;
                   text-transform: uppercase; padding: 4px 6px; border-bottom: 1px solid #c8e3da; }
        tbody td { padding: 3px 6px; border-bottom: 1px solid #f0f5f3; font-size: 8.5px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .fw-bold { font-weight: bold; }
        .has-overdue { background: #fffbf5; }
        .bucket-current  { color: #166534; }
        .bucket-1-30     { color: #854d0e; }
        .bucket-31-60    { color: #9a3412; }
        .bucket-61-90    { color: #7f1d1d; }
        .bucket-90plus   { color: #5b0f0f; }
        .chip { display: inline-block; padding: 1px 6px; border-radius: 999px; font-size: 7.5px; font-weight: bold; }
        .chip-current  { background: #dcfce7; color: #166534; }
        .chip-1-30     { background: #fef3c7; color: #92400e; }
        .chip-31-60    { background: #fed7aa; color: #9a3412; }
        .chip-61-90    { background: #fee2e2; color: #991b1b; }
        .chip-90plus   { background: #fce7f3; color: #831843; }
        .footer { margin-top: 10px; font-size: 7.5px; color: #9ca3af; text-align: right; }
        .empty-note { color: #9ca3af; font-style: italic; padding: 4px 6px; font-size: 8px; }
    </style>
</head>
<body>
<div class="header">
    <h1>Accounts Payable Aging</h1>
    <p>Outstanding supplier payables by days since receiving date.</p>
</div>
<div class="meta">
    <span><strong>As Of:</strong> {{ $filters['as_of'] ?? 'Today' }}</span>
    @if(!empty($filters['keyword']))
    <span><strong>Search:</strong> {{ $filters['keyword'] }}</span>
    @endif
    <span><strong>Generated:</strong> {{ now()->format('Y-m-d H:i') }}</span>
</div>

<div class="section-title">Aging Summary by Supplier</div>
@if(count($agingRows) === 0)
    <p class="empty-note">No outstanding payables for this period.</p>
@else
<table>
    <thead>
        <tr>
            <th>Supplier</th>
            <th class="text-center">Records</th>
            <th class="text-right bucket-current">Current</th>
            <th class="text-right bucket-1-30">1–30 days</th>
            <th class="text-right bucket-31-60">31–60 days</th>
            <th class="text-right bucket-61-90">61–90 days</th>
            <th class="text-right bucket-90plus">90+ days</th>
            <th class="text-right fw-bold">Total Outstanding</th>
        </tr>
    </thead>
    <tbody>
        @foreach($agingRows as $row)
        <tr class="{{ $row['has_overdue'] ? 'has-overdue' : '' }}">
            <td class="fw-bold">{{ $row['supplier_name'] }}</td>
            <td class="text-center">{{ $row['record_count'] }}</td>
            <td class="text-right">{{ $row['current'] }}</td>
            <td class="text-right">{{ $row['days_1_30'] }}</td>
            <td class="text-right">{{ $row['days_31_60'] }}</td>
            <td class="text-right">{{ $row['days_61_90'] }}</td>
            <td class="text-right">{{ $row['days_90_plus'] }}</td>
            <td class="text-right fw-bold">{{ $row['total_outstanding'] }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

<div class="section-title">Receiving Detail</div>
@if(count($receivingRows) === 0)
    <p class="empty-note">No open receiving records.</p>
@else
<table>
    <thead>
        <tr>
            <th>Received No.</th>
            <th>Supplier</th>
            <th>Received Date</th>
            <th class="text-center">Days Out</th>
            <th>PO No.</th>
            <th class="text-right">Total Cost</th>
            <th class="text-right">Paid</th>
            <th class="text-right fw-bold">Balance Due</th>
            <th class="text-center">Aging</th>
        </tr>
    </thead>
    <tbody>
        @foreach($receivingRows as $row)
        @php
            $bucket    = $row['bucket'];
            $chipClass = 'chip-' . str_replace('+', 'plus', $bucket);
        @endphp
        <tr>
            <td>{{ $row['received_no'] }}</td>
            <td>{{ $row['supplier_name'] }}</td>
            <td>{{ $row['received_date'] }}</td>
            <td class="text-center">{{ $row['days_out'] }}</td>
            <td>{{ $row['po_number'] }}</td>
            <td class="text-right">{{ $row['total_cost'] }}</td>
            <td class="text-right">{{ $row['amount_paid'] }}</td>
            <td class="text-right fw-bold">{{ $row['balance_due'] }}</td>
            <td class="text-center">
                <span class="chip {{ $chipClass }}">
                    {{ $bucket === 'current' ? 'Current' : $row['days_out'] . 'd out' }}
                </span>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

<div class="footer">BRT Accounting System &mdash; Accounts Payable Aging</div>
</body>
</html>
