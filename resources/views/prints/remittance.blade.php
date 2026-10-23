<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Remittance - {{ $remittance->remittance_no }}</title>
    <style>
        /* DomPDF: tables for layout, no flexbox. Same look as the loan
           statement and pay statement, on long bond. */
        @page { size: 215.9mm 330.2mm; margin: 12mm 12mm; }
        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #222;
            font-size: 10px;
            line-height: 1.3;
        }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: #666; }
        .header-table td { vertical-align: top; }
        .company { font-size: 17px; font-weight: bold; }
        .title-cell { text-align: right; }
        .title { font-size: 15px; font-weight: bold; letter-spacing: 1px; }
        .status-pill {
            display: inline-block;
            margin-top: 3px;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #fff;
            background: #7f8c8d;
        }
        .status-pill.is-verified { background: #3d8d7a; }
        .status-pill.is-open { background: #d68910; }
        .status-pill.is-rejected { background: #c0392b; }
        .rule { border-top: 2px solid #333; margin: 8px 0 10px; }
        .notice { margin-bottom: 8px; padding: 5px 8px; border: 1px solid #c0392b; color: #c0392b; font-weight: bold; text-align: center; }

        .info td { padding: 2.5px 0; vertical-align: top; }
        .info .label { width: 13%; color: #666; }
        .info .value { width: 20.33%; font-weight: bold; padding-right: 8px; }

        h3 {
            font-size: 9.5px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 14px 0 5px;
            color: #444;
        }

        /* The money handed in, by kind, with the total standing out. */
        .tiles td { width: 25%; padding: 0 4px; vertical-align: top; }
        .tiles td:first-child { padding-left: 0; }
        .tiles td:last-child { padding-right: 0; }
        .tile { border: 1px solid #dcdcdc; border-radius: 4px; padding: 7px 9px; }
        .tile-label { font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; color: #666; }
        .tile-value { font-size: 13px; font-weight: bold; margin-top: 2px; }
        .tile.is-total { border: 2px solid #333; }

        .lines th {
            text-align: left;
            background: #ededed;
            border: 1px solid #c9c9c9;
            padding: 5px 6px;
            font-size: 8.5px;
            text-transform: uppercase;
        }
        .lines td { border: 1px solid #dcdcdc; padding: 3px 6px; }
        .amount { text-align: right; white-space: nowrap; }
        .subtotal td { font-weight: bold; background: #f6f6f6; }

        .check td { border: 1px solid #dcdcdc; padding: 6px 8px; }
        .check .label { width: 30%; color: #555; background: #fafafa; }
        .check .fill { height: 14px; }
        .variance-short { color: #c0392b; font-weight: bold; }
        .variance-over { color: #1e8449; font-weight: bold; }

        .remarks { border: 1px solid #dcdcdc; padding: 6px 8px; min-height: 18px; }

        .sign { margin-top: 44px; }
        .sign td { width: 33.33%; text-align: center; font-size: 9px; vertical-align: top; }
        .sign .name { font-weight: bold; font-size: 10px; }
        .sign .line { border-top: 1px solid #555; margin: 0 16px; padding-top: 3px; }
        .footer { margin-top: 22px; text-align: center; font-size: 8px; color: #888; }
    </style>
</head>
<body>
@php
    $peso = fn ($value) => number_format((float) $value, 2);
    $userName = fn ($user) => $user?->employee?->fullname ?? $user?->username;
    $date = fn ($value, $format = 'M d, Y') => $value ? \Carbon\Carbon::parse($value)->format($format) : '—';

    $slug = $remittance->status?->slug;
    $statusName = $remittance->status?->name ?? 'Pending';
    $isVerified = in_array($slug, ['liquidated', 'approved', 'verified'], true);
    $isRejected = $slug === 'disapproved';
    $pillClass = $isVerified ? 'is-verified' : ($isRejected ? 'is-rejected' : 'is-open');

    // The summary the remittance was saved with, in a fixed order, so the
    // three kinds always print even when one of them is nil.
    $summary = is_array($remittance->summary) ? $remittance->summary : [];
    $kinds = [
        'cash_sales' => 'Cash sales',
        'credit_sales' => 'Credit sales',
        'bank_transfer' => 'Bank transfer',
    ];

    $receiptTotal = (float) $receipts->sum('amount_paid');
    $expected = (float) ($remittance->total_amount ?? $receiptTotal);
    $received = $remittance->received_amount;
    $variance = $received !== null ? (float) ($remittance->variance ?? ((float) $received - $expected)) : null;
    $breakdown = collect($remittance->received_breakdown ?? [])->filter(fn ($amount) => (float) $amount != 0);
    $modes = collect(explode(',', (string) $remittance->received_via))->map(fn ($m) => ucwords(trim($m)))->filter();
@endphp

<table class="header-table">
    <tr>
        <td>
            <div class="company">BOUYANT RICE TRADING</div>
            <div class="muted">Sinunoc, Zamboanga City, Zamboanga del Sur 7000, Philippines</div>
        </td>
        <td class="title-cell">
            <div class="title">REMITTANCE</div>
            <div class="muted">No. {{ $remittance->remittance_no }}</div>
            <div class="status-pill {{ $pillClass }}">{{ $statusName }}</div>
        </td>
    </tr>
</table>
<div class="rule"></div>

@if($isRejected)
    <div class="notice">THIS REMITTANCE WAS DISAPPROVED — ITS RECEIPTS WERE RETURNED TO PENDING</div>
@endif

<table class="info">
    <tr>
        <td class="label">Remitted by</td>
        <td class="value">{{ $userName($remittance->createdBy) ?? '—' }}</td>
        <td class="label">Remittance date</td>
        <td class="value">{{ $date($remittance->remittance_date) }}</td>
        <td class="label">Verified by</td>
        <td class="value">{{ $userName($remittance->approvedBy) ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Receipts</td>
        <td class="value">{{ $receipts->count() }}</td>
        <td class="label">Prepared at</td>
        <td class="value">{{ $date($remittance->created_at, 'M d, Y h:i A') }}</td>
        <td class="label">Verified at</td>
        <td class="value">{{ $date($remittance->approved_at, 'M d, Y h:i A') }}</td>
    </tr>
</table>

<h3>Amount remitted</h3>
<table class="tiles">
    <tr>
        @foreach($kinds as $key => $kindLabel)
            <td>
                <div class="tile">
                    <div class="tile-label">{{ $kindLabel }}</div>
                    <div class="tile-value">PHP {{ $peso($summary[$key] ?? 0) }}</div>
                </div>
            </td>
        @endforeach
        <td>
            <div class="tile is-total">
                <div class="tile-label">Total remitted</div>
                <div class="tile-value">PHP {{ $peso($expected) }}</div>
            </div>
        </td>
    </tr>
</table>

<h3>Receipts included</h3>
<table class="lines">
    <tr>
        <th style="width: 4%;">#</th>
        <th style="width: 15%;">Receipt no.</th>
        <th style="width: 15%;">Invoice no.</th>
        <th>Customer</th>
        <th style="width: 16%;">Sales rep</th>
        <th style="width: 11%;">Date</th>
        <th style="width: 9%;">Mode</th>
        <th class="amount" style="width: 12%;">Amount</th>
    </tr>
    @forelse($receipts as $index => $receipt)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ $receipt->receipt_number ?? '—' }}</td>
            <td>{{ $receipt->arInvoice?->invoice_number ?? '—' }}</td>
            <td>{{ $receipt->customer?->name ?? $receipt->arInvoice?->sales_order?->customer?->name ?? '—' }}</td>
            <td>{{ $receipt->arInvoice?->sales_order?->salesRep?->fullname ?? '—' }}</td>
            <td>{{ $date($receipt->receipt_date) }}</td>
            <td>{{ $receipt->payment_mode ? ucwords($receipt->payment_mode) : '—' }}</td>
            <td class="amount">{{ $peso($receipt->amount_paid) }}</td>
        </tr>
    @empty
        <tr><td colspan="8" class="muted">No receipts on this remittance</td></tr>
    @endforelse
    <tr class="subtotal">
        <td colspan="7">Total of {{ $receipts->count() }} receipt(s)</td>
        <td class="amount">{{ $peso($receiptTotal) }}</td>
    </tr>
</table>

{{-- What the cashier counted against what the receipts say. Filled in from
     the verification once it is done; until then the lines are left blank,
     to be written in by hand as the money is counted. --}}
<h3>Verification</h3>
<table class="check">
    <tr>
        <td class="label">Amount expected</td>
        <td class="amount">PHP {{ $peso($expected) }}</td>
    </tr>
    <tr>
        <td class="label">Amount received</td>
        <td class="amount fill">{{ $received !== null ? 'PHP '.$peso($received) : '' }}</td>
    </tr>
    <tr>
        <td class="label">Variance</td>
        <td class="amount fill">
            @if($variance !== null)
                <span class="{{ $variance < 0 ? 'variance-short' : ($variance > 0 ? 'variance-over' : '') }}">
                    {{ $variance < 0 ? 'Short' : ($variance > 0 ? 'Over' : 'None') }}@if($variance != 0) · PHP {{ $peso(abs($variance)) }}@endif
                </span>
            @endif
        </td>
    </tr>
    <tr>
        <td class="label">Received via</td>
        <td class="fill">
            @if($breakdown->isNotEmpty())
                {{ $breakdown->map(fn ($amount, $mode) => ucwords(str_replace('_', ' ', $mode)).' PHP '.$peso($amount))->implode(' · ') }}
            @else
                {{ $modes->implode(', ') }}
            @endif
            @if($remittance->reference_no)
                <span class="muted"> · Ref. {{ $remittance->reference_no }}</span>
            @endif
        </td>
    </tr>
</table>

<h3>Remarks</h3>
<div class="remarks">{{ $remittance->remarks ?: '' }}</div>

<table class="sign">
    <tr>
        <td>
            <div class="name">{!! e($userName($remittance->createdBy)) ?: '&nbsp;' !!}</div>
            <div class="line">Remitted by</div>
        </td>
        <td>
            <div class="name">&nbsp;</div>
            <div class="line">Received by (cashier) / Date</div>
        </td>
        <td>
            <div class="name">{!! e($userName($remittance->approvedBy)) ?: '&nbsp;' !!}</div>
            <div class="line">Verified by</div>
        </td>
    </tr>
</table>

<div class="footer">Remittance {{ $remittance->remittance_no }} · Generated on {{ now()->format('M d, Y h:i A') }}</div>
</body>
</html>
