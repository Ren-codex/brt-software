<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Loan Statement - {{ $loan->employee?->fullname }} - {{ $loan->loan_no }}</title>
    <style>
        /* DomPDF: tables for layout, no flexbox. One loan, one full page. */
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
        .notice { margin-bottom: 12px; padding: 8px 10px; border: 1px solid #c0392b; color: #c0392b; font-weight: bold; text-align: center; }
        .note { margin-top: 8px; font-size: 10px; color: #666; }
        .sign { margin-top: 56px; }
        .sign td { width: 33.33%; text-align: center; font-size: 10px; vertical-align: top; }
        .sign .name { font-weight: bold; font-size: 11px; }
        .sign .line { border-top: 1px solid #555; margin: 0 16px; padding-top: 4px; }
        .footer { margin-top: 30px; text-align: center; font-size: 9px; color: #888; }
        .new-sheet { page-break-before: always; }
        .copy-label {
            display: inline-block;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #fff;
            background-color: #7f8c8d;
            padding: 2px 7px;
            border-radius: 3px;
            margin-bottom: 4px;
        }
    </style>
</head>
<body>
@php
    $peso = fn ($value) => number_format((float) $value, 2);
    $label = fn ($value) => $value ? ucwords(str_replace(['_', '-'], ' ', $value)) : '—';
    $userName = fn ($user) => $user?->employee?->fullname ?? $user?->username;

    $employee = $loan->employee;
    $principal = (float) $loan->amount;
    $interest = $principal * ((float) $loan->interest_rate / 100);
    $total = $principal + $interest;
    // Deducted on the 15th and the 30th, so two cut-offs to every month.
    $cutoffs = (int) $loan->term_months * 2;
    $perCutoff = $cutoffs > 0 ? $total / $cutoffs : $total;
    $paid = $loan->amtpaid !== null ? (float) $loan->amtpaid : (float) $payments->sum('amount');
    $balance = $loan->remaining_balance !== null ? (float) $loan->remaining_balance : max($total - $paid, 0);
    $remainingCutoffs = $loan->remaining_term_to_pay ?? max($cutoffs - (int) $payments->sum('paid_term'), 0);
    $notYetApproved = in_array($loan->status, ['pending', 'rejected', 'disapproved'], true);
@endphp

{{-- Two copies, one kept by the employee and one filed by BRT, each a full
     page of its own: the statement already fills one. --}}
@foreach(['Employee Copy', 'BRT Copy'] as $copyIndex => $copyLabel)
<div class="{{ $copyIndex > 0 ? 'new-sheet' : '' }}">

<table>
    <tr>
        <td>
            <div class="company">BOUYANT RICE TRADING</div>
            <div class="muted">Sinunoc, Zamboanga City, Zamboanga del Sur 7000, Philippines</div>
        </td>
        <td class="title-cell">
            <div class="copy-label">{{ $copyLabel }}</div>
            <div class="title">LOAN STATEMENT</div>
            <div class="muted">Loan No. {{ $loan->loan_no ?? '—' }}</div>
            <div class="muted">Status: {{ $label($loan->status) }}</div>
        </td>
    </tr>
</table>
<div class="rule"></div>

@if($notYetApproved)
    <div class="notice">
        {{ $loan->status === 'pending' ? 'PENDING APPROVAL — NOT YET A RELEASED LOAN' : 'THIS LOAN WAS NOT APPROVED' }}
    </div>
@endif

<table class="info">
    <tr>
        <td class="label">Employee</td>
        <td class="value">{{ $employee?->fullname ?? 'N/A' }}</td>
        <td class="label">Date filed</td>
        <td class="value">{{ $loan->created_at?->format('M d, Y') ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Position</td>
        <td class="value">{{ $employee?->position?->title ?? '—' }}</td>
        <td class="label">Date approved</td>
        <td class="value">{{ $loan->approved_at?->format('M d, Y') ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Loan type</td>
        <td class="value">{{ $label($loan->loan_type) }}</td>
        <td class="label">Date released</td>
        <td class="value">{{ $released?->created_at?->format('M d, Y') ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Payment type</td>
        <td class="value">{{ $label($loan->payment_type) }}</td>
        <td class="label">Term</td>
        <td class="value">{{ (int) $loan->term_months }} month(s) · {{ $cutoffs }} cut-off(s)</td>
    </tr>
    @if($loan->purpose)
        <tr>
            <td class="label">Purpose</td>
            <td class="value" colspan="3">{{ $loan->purpose }}</td>
        </tr>
    @endif
</table>

<h3>Loan terms</h3>
<table class="lines">
    <tr><th>Description</th><th class="amount">Amount (PHP)</th></tr>
    <tr><td>Principal</td><td class="amount">{{ $peso($principal) }}</td></tr>
    <tr>
        <td>Interest <span class="muted">({{ rtrim(rtrim(number_format((float) $loan->interest_rate, 2), '0'), '.') }}%)</span></td>
        <td class="amount">{{ $peso($interest) }}</td>
    </tr>
    <tr class="subtotal"><td>Total payable</td><td class="amount">{{ $peso($total) }}</td></tr>
    <tr>
        <td>Deduction per cut-off <span class="muted">({{ $peso($total) }} ÷ {{ max($cutoffs, 1) }})</span></td>
        <td class="amount">{{ $peso($perCutoff) }}</td>
    </tr>
</table>

<h3>Payments</h3>
<table class="lines">
    <tr><th style="width: 5%;">#</th><th>Period covered</th><th>Recorded</th><th>Cut-off(s)</th><th>Received by</th><th>Remarks</th><th class="amount">Amount (PHP)</th></tr>
    @forelse($payments as $index => $payment)
        <tr>
            <td>{{ $index + 1 }}</td>
            {{-- paid_date is a free-text label, often a pay period such as
                 "October 1-15, 2026", so it is printed as written, never parsed. --}}
            <td>{{ $payment->paid_date ?: '—' }}</td>
            <td>{{ $payment->created_at?->format('M d, Y') ?? '—' }}</td>
            <td>{{ $payment->paid_term ?? '—' }}</td>
            <td>{{ $userName($payment->addedBy) ?? '—' }}</td>
            <td>{{ $payment->remarks ?: '—' }}</td>
            <td class="amount">{{ $peso($payment->amount) }}</td>
        </tr>
    @empty
        <tr><td colspan="7" class="muted">No payments recorded yet</td></tr>
    @endforelse
    <tr class="subtotal"><td colspan="6">Total paid</td><td class="amount">{{ $peso($paid) }}</td></tr>
</table>

<table class="net">
    <tr>
        <td>REMAINING BALANCE</td>
        <td class="amount">PHP {{ $peso($balance) }}</td>
    </tr>
</table>
<div class="note">{{ rtrim(rtrim(number_format((float) $remainingCutoffs, 2), '0'), '.') }} cut-off(s) left to pay. Remaining balance is the total payable less total paid.</div>

<table class="sign">
    <tr>
        <td>
            <div class="name">{!! e($userName($loan->added_by)) ?: '&nbsp;' !!}</div>
            <div class="line">Prepared by</div>
        </td>
        <td>
            <div class="name">{!! e($userName($loan->approved_by)) ?: '&nbsp;' !!}</div>
            <div class="line">Approved by</div>
        </td>
        <td>
            <div class="name">{{ $employee?->fullname ?? '' }}</div>
            <div class="line">Borrower / Date</div>
        </td>
    </tr>
</table>

<div class="footer">{{ $copyLabel }} · Generated on {{ now()->format('M d, Y h:i A') }}</div>
</div>
@endforeach
</body>
</html>
