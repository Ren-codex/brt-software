<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pay Statement - {{ $item->employee?->fullname }} - {{ $payroll->payroll_no }}</title>
    <style>
        /* DomPDF: tables for layout, no flexbox. One employee, one full page. */
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            margin: 26px 30px;
            color: #222;
            font-size: 11.5px;
        }
        table { width: 100%; border-collapse: collapse; }
        .company { font-size: 18px; font-weight: bold; }
        .muted { color: #666; }
        .title-cell { text-align: right; vertical-align: top; }
        .title { font-size: 15px; font-weight: bold; letter-spacing: 1px; }
        .rule { border-top: 2px solid #333; margin: 10px 0 14px; }
        .info td { padding: 4px 0; vertical-align: top; }
        .info .label { width: 16%; color: #666; }
        .info .value { width: 34%; font-weight: bold; }
        h3 {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 18px 0 6px;
            color: #444;
        }
        .lines th {
            text-align: left;
            background: #ededed;
            border: 1px solid #c9c9c9;
            padding: 6px 8px;
            font-size: 10px;
            text-transform: uppercase;
        }
        .lines td { border: 1px solid #dcdcdc; padding: 6px 8px; }
        .amount { text-align: right; white-space: nowrap; }
        .subtotal td { font-weight: bold; background: #f6f6f6; }
        .net { margin-top: 16px; }
        .net td { border: 2px solid #333; padding: 10px 12px; font-size: 15px; font-weight: bold; }
        .note { margin-top: 8px; font-size: 10px; color: #666; }
        .sign { margin-top: 56px; }
        .sign td { width: 33.33%; text-align: center; font-size: 10px; vertical-align: top; }
        .sign .name { font-weight: bold; font-size: 11px; }
        .sign .line { border-top: 1px solid #555; margin: 0 16px; padding-top: 4px; }
        .footer { margin-top: 30px; text-align: center; font-size: 9px; color: #888; }
    </style>
</head>
<body>
@php
    $peso = fn ($value) => number_format((float) $value, 2);
    $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 2), '0'), '.');

    $earnings = collect($item->earnings ?? []);
    $deductions = collect($item->deductions ?? []);
    $loans = collect($item->loans ?? []);
    $otherEarnings = (float) $earnings->sum(fn ($e) => (float) ($e['amount'] ?? 0));
    // Total earnings is stored as basic pay plus every added earning, so basic
    // pay for the period is what is left once those are taken out.
    $basicPay = max((float) $item->total_earnings - $otherEarnings, 0);
    $employee = $item->employee;
@endphp

<table>
    <tr>
        <td>
            <div class="company">BOUYANT RICE TRADING</div>
            <div class="muted">Sinunoc, Zamboanga City, Zamboanga del Sur 7000, Philippines</div>
        </td>
        <td class="title-cell">
            <div class="title">PAY STATEMENT</div>
            <div class="muted">Payroll No. {{ $payroll->payroll_no }}</div>
            <div class="muted">Status: {{ $payroll->status?->name ?? '—' }}</div>
        </td>
    </tr>
</table>
<div class="rule"></div>

<table class="info">
    <tr>
        <td class="label">Employee</td>
        <td class="value">{{ $employee?->fullname ?? 'N/A' }}</td>
        <td class="label">Pay period</td>
        <td class="value">{{ $payroll->pay_period_start->format('M d, Y') }} – {{ $payroll->pay_period_end->format('M d, Y') }}</td>
    </tr>
    <tr>
        <td class="label">Position</td>
        <td class="value">{{ $employee?->position?->title ?? '—' }}</td>
        <td class="label">Daily rate</td>
        <td class="value">PHP {{ $peso($item->basic_salary) }}</td>
    </tr>
    <tr>
        <td class="label">Days worked</td>
        <td class="value">{{ $qty($item->total_days) }}</td>
        <td class="label">Hours per day</td>
        <td class="value">{{ $item->hours_per_day ? $qty($item->hours_per_day) : '—' }}</td>
    </tr>
</table>

<h3>Earnings</h3>
<table class="lines">
    <tr><th>Description</th><th>Basis</th><th class="amount">Amount (PHP)</th></tr>
    <tr>
        <td>Basic pay</td>
        <td class="muted">{{ $peso($item->basic_salary) }} × {{ $qty($item->total_days) }} day(s)</td>
        <td class="amount">{{ $peso($basicPay) }}</td>
    </tr>
    @foreach($earnings as $earning)
        <tr>
            <td>{{ $earning['description'] ?? 'Earning' }}</td>
            <td class="muted">
                @if(($earning['description'] ?? '') === 'Overtime' && (float) $item->overtime_hours > 0)
                    {{ $qty($item->overtime_hours) }} hour(s) × {{ $peso($item->overtime_rate) }}
                @endif
            </td>
            <td class="amount">{{ $peso($earning['amount'] ?? 0) }}</td>
        </tr>
    @endforeach
    <tr class="subtotal"><td colspan="2">Gross pay</td><td class="amount">{{ $peso($item->total_earnings) }}</td></tr>
</table>

<h3>Deductions</h3>
<table class="lines">
    <tr><th>Description</th><th class="amount">Amount (PHP)</th></tr>
    @forelse($deductions as $deduction)
        <tr>
            <td>{{ $deduction['description'] ?? 'Deduction' }}</td>
            <td class="amount">{{ $peso($deduction['amount'] ?? 0) }}</td>
        </tr>
    @empty
        <tr><td class="muted">No deductions this period</td><td class="amount">0.00</td></tr>
    @endforelse
    <tr class="subtotal"><td>Total deductions</td><td class="amount">{{ $peso($item->total_deductions) }}</td></tr>
</table>

@if($loans->isNotEmpty())
    <h3>Loan deductions on this payroll</h3>
    <table class="lines">
        <tr><th>Loan no.</th><th class="amount">Deducted this period</th><th class="amount">Balance before deduction</th></tr>
        @foreach($loans as $loan)
            <tr>
                <td>{{ $loan['loan_no'] ?? '—' }}</td>
                <td class="amount">{{ isset($loan['payroll_deduction']) ? $peso($loan['payroll_deduction']) : '—' }}</td>
                <td class="amount">{{ isset($loan['remaining_balance']) ? $peso($loan['remaining_balance']) : '—' }}</td>
            </tr>
        @endforeach
    </table>
@endif

<table class="net">
    <tr>
        <td>NET PAY</td>
        <td class="amount">PHP {{ $peso($item->net_salary) }}</td>
    </tr>
</table>
<div class="note">Net pay is gross pay less total deductions.</div>

<table class="sign">
    <tr>
        <td>
            <div class="name">{{ $payroll->creator?->employee?->fullname ?? $payroll->creator?->username ?? '&nbsp;' }}</div>
            <div class="line">Prepared by</div>
        </td>
        <td>
            <div class="name">{!! $payroll->approvedBy?->employee?->fullname ?? $payroll->approvedBy?->username ?? '&nbsp;' !!}</div>
            <div class="line">Approved by</div>
        </td>
        <td>
            <div class="name">{{ $employee?->fullname ?? '' }}</div>
            <div class="line">Received by / Date</div>
        </td>
    </tr>
</table>

<div class="footer">Generated on {{ now()->format('M d, Y h:i A') }}</div>
</body>
</html>
