<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Loan Statement - {{ $loan->employee?->fullname }} - {{ $loan->loan_no }}</title>
    <style>
        /* DomPDF: tables for layout, no flexbox.

           Two copies on one sheet, cut in half with scissors: the employee
           keeps the top, BRT files the bottom. The same layout the invoice and
           receipt prints use. The top copy is given exactly half the printable
           height (long bond is 330.2mm; less the 6mm margins at each end, half
           is 159.1mm) and carries the cut line on its bottom edge, so the cut
           falls on the middle of the paper.

           A loan with more payments than half a sheet holds gives each copy a
           sheet of its own instead (.new-sheet), so neither is cut through. */
        @page { size: 215.9mm 330.2mm; margin: 6mm 10mm; }
        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #222;
            font-size: 9px;
            line-height: 1.25;
        }
        .copy-cell { width: 100%; padding-top: 2mm; }
        .half-sheet-top { height: 159.1mm; box-sizing: border-box; border-bottom: 1px dashed #999; padding-top: 2mm; }
        .new-sheet { page-break-before: always; }
        .keep-whole { page-break-inside: avoid; }

        table { width: 100%; border-collapse: collapse; }
        .muted { color: #666; }
        .header-table td { vertical-align: top; }
        .company { font-size: 14px; font-weight: bold; }
        .title-cell { text-align: right; }
        .title { font-size: 12px; font-weight: bold; letter-spacing: 1px; }
        .copy-label {
            display: inline-block;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #fff;
            background-color: #7f8c8d;
            padding: 1px 6px;
            border-radius: 3px;
            margin-bottom: 2px;
        }
        .rule { border-top: 1.5px solid #333; margin: 5px 0 6px; }
        .notice { margin-bottom: 5px; padding: 3px 6px; border: 1px solid #c0392b; color: #c0392b; font-weight: bold; text-align: center; font-size: 8.5px; }

        .info td { padding: 1.5px 0; vertical-align: top; }
        .info .label { width: 10%; color: #666; }
        .info .value { width: 23.33%; font-weight: bold; padding-right: 6px; }

        .split { margin-top: 6px; }
        .split-left { width: 50%; padding-right: 5px; vertical-align: top; }
        .split-right { width: 50%; padding-left: 5px; vertical-align: top; }

        .section {
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #444;
            margin: 7px 0 3px;
        }
        .lines th {
            text-align: left;
            background: #ededed;
            border: 1px solid #c9c9c9;
            padding: 2.5px 5px;
            font-size: 8px;
            text-transform: uppercase;
        }
        .lines td { border: 1px solid #dcdcdc; padding: 2.5px 5px; }
        .payment-row td { padding: 1px 5px; font-size: 8.5px; white-space: nowrap; }
        .amount { text-align: right; white-space: nowrap; }
        .subtotal td { font-weight: bold; background: #f6f6f6; }
        .balance { margin-top: 5px; }
        .balance td { border: 1.5px solid #333; padding: 5px 7px; font-size: 11px; font-weight: bold; }

        .sign { margin-top: 16px; }
        .sign td { width: 33.33%; text-align: center; font-size: 8.5px; vertical-align: top; }
        .sign .name { font-weight: bold; font-size: 9px; }
        .sign .line { border-top: 1px solid #555; margin: 0 14px; padding-top: 2px; }
        .footer { margin-top: 8px; text-align: center; font-size: 7.5px; color: #888; }
    </style>
</head>
<body>
@php
    $peso = fn ($value) => number_format((float) $value, 2);
    $wholeOrDecimal = fn ($value) => rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
    $label = fn ($value) => $value ? ucwords(str_replace(['_', '-'], ' ', $value)) : '—';
    $userName = fn ($user) => $user?->employee?->fullname ?? $user?->username;

    $employee = $loan->employee;
    $principal = (float) $loan->amount;
    $rate = $wholeOrDecimal($loan->interest_rate);
    $interest = $principal * ((float) $loan->interest_rate / 100);
    $total = $principal + $interest;
    // Deducted on the 15th and the 30th, so two cut-offs to every month.
    $cutoffs = (int) $loan->term_months * 2;
    $perCutoff = $cutoffs > 0 ? $total / $cutoffs : $total;
    $paid = $loan->amtpaid !== null ? (float) $loan->amtpaid : (float) $payments->sum('amount');
    $balance = $loan->remaining_balance !== null ? (float) $loan->remaining_balance : max($total - $paid, 0);
    $remainingCutoffs = $loan->remaining_term_to_pay ?? max($cutoffs - (int) $payments->sum('paid_term'), 0);
    $notYetApproved = in_array($loan->status, ['pending', 'rejected', 'disapproved'], true);

    $copiesShareSheet = $payments->count() <= \App\Services\PrintClass::LOAN_PAYMENT_ROWS_PER_HALF_SHEET;
@endphp

<div class="copy-cell {{ $copiesShareSheet ? 'half-sheet-top' : '' }}">
    @include('prints.partials.loan-copy', ['copyLabel' => 'Employee Copy'])
</div>

<div class="copy-cell {{ $copiesShareSheet ? 'keep-whole' : 'new-sheet' }}">
    @include('prints.partials.loan-copy', ['copyLabel' => 'BRT Copy'])
</div>
</body>
</html>
