<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payslips - {{ $payroll->payroll_no }}</title>
    <style>
        /* DomPDF: tables for layout, no flexbox. */
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            margin: 18px 22px;
            color: #222;
            font-size: 11px;
        }
        .payslip {
            border: 1px solid #999;
            padding: 12px 14px;
            margin-bottom: 18px;
            page-break-inside: avoid;
        }
        .break { page-break-after: always; }
        table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; }
        .company { font-size: 15px; font-weight: bold; }
        .doc-title { font-size: 13px; font-weight: bold; letter-spacing: 2px; text-align: right; }
        .muted { color: #666; }
        .meta { margin-top: 8px; border-top: 1px solid #ccc; border-bottom: 1px solid #ccc; }
        .meta td { padding: 5px 0; width: 50%; }
        .cols { margin-top: 8px; }
        .cols > tbody > tr > td { width: 50%; vertical-align: top; }
        .cols > tbody > tr > td.left { padding-right: 8px; }
        .cols > tbody > tr > td.right { padding-left: 8px; }
        .lines th {
            text-align: left;
            background: #efefef;
            padding: 4px 6px;
            border: 1px solid #ccc;
            font-size: 10px;
            text-transform: uppercase;
        }
        .lines td { padding: 4px 6px; border: 1px solid #ddd; }
        .amount { text-align: right; white-space: nowrap; }
        .subtotal td { font-weight: bold; background: #f7f7f7; }
        .net { margin-top: 8px; }
        .net td { padding: 7px 8px; border: 1px solid #999; font-size: 13px; font-weight: bold; }
        .sign { margin-top: 26px; }
        .sign td { width: 50%; text-align: center; font-size: 10px; }
        .sign .line { border-top: 1px solid #555; margin: 0 26px; padding-top: 3px; }

        /* Four to a sheet: a 2 x 2 grid on long bond, each slip a quarter of
           the page with its earnings and deductions stacked, since a quarter
           is too narrow to set them side by side. */
        body.four-up { margin: 14px 16px; font-size: 8.5px; }
        .sheet { table-layout: fixed; }
        .sheet > tbody > tr > td {
            width: 50%;
            height: 410pt;
            vertical-align: top;
            padding: 5px;
        }
        .four-up .payslip { margin-bottom: 0; padding: 8px 9px; height: 385pt; }
        .four-up .company { font-size: 11px; }
        .four-up .doc-title { font-size: 10px; letter-spacing: 1px; }
        .four-up .meta { margin-top: 5px; }
        .four-up .meta td { width: auto; padding: 2px 0; }
        .four-up .lines { margin-top: 6px; }
        .four-up .lines th { font-size: 7.5px; padding: 2px 4px; }
        .four-up .lines td { padding: 2px 4px; }
        .four-up .net { margin-top: 6px; }
        .four-up .net td { font-size: 10px; padding: 4px 6px; }
        .four-up .sign { margin-top: 22px; }
        .four-up .sign td { font-size: 7.5px; }
        .four-up .sign .line { margin: 0 6px; }
    </style>
</head>
<body class="{{ ($fourUp ?? false) ? 'four-up' : '' }}">
@php
    $peso = fn ($value) => number_format((float) $value, 2);
@endphp

@if($fourUp ?? false)
    @forelse($items->chunk(4) as $page)
        <table class="sheet">
            @foreach($page->values()->chunk(2) as $pair)
                <tr>
                    @foreach($pair as $item)
                        @php
                            $earnings = collect($item->earnings ?? []);
                            $deductions = collect($item->deductions ?? []);
                            $otherEarnings = (float) $earnings->sum(fn ($e) => (float) ($e['amount'] ?? 0));
                            $basicPay = max((float) $item->total_earnings - $otherEarnings, 0);
                        @endphp
                        <td>
                            <div class="payslip">
                                <table class="head">
                                    <tr>
                                        <td>
                                            <div class="company">BOUYANT RICE TRADING</div>
                                            <div class="muted">Sinunoc, Zamboanga City</div>
                                            <div class="muted">Payroll No. {{ $payroll->payroll_no }}</div>
                                        </td>
                                        <td class="doc-title">PAYSLIP</td>
                                    </tr>
                                </table>

                                <table class="meta">
                                    <tr><td><strong>Employee:</strong> {{ $item->employee?->fullname ?? 'N/A' }}</td></tr>
                                    <tr><td><strong>Position:</strong> {{ $item->employee?->position?->title ?? '—' }}</td></tr>
                                    <tr>
                                        <td><strong>Pay period:</strong>
                                            {{ $payroll->pay_period_start->format('M d, Y') }} – {{ $payroll->pay_period_end->format('M d, Y') }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <strong>Daily rate:</strong> {{ $peso($item->basic_salary) }}
                                            &nbsp; <strong>Days:</strong> {{ rtrim(rtrim(number_format((float) $item->total_days, 2), '0'), '.') }}
                                        </td>
                                    </tr>
                                </table>

                                <table class="lines">
                                    <tr><th>Earnings</th><th class="amount">Amount</th></tr>
                                    <tr><td>Basic pay</td><td class="amount">{{ $peso($basicPay) }}</td></tr>
                                    @foreach($earnings as $earning)
                                        <tr>
                                            <td>{{ $earning['description'] ?? 'Earning' }}</td>
                                            <td class="amount">{{ $peso($earning['amount'] ?? 0) }}</td>
                                        </tr>
                                    @endforeach
                                    <tr class="subtotal"><td>Total earnings</td><td class="amount">{{ $peso($item->total_earnings) }}</td></tr>
                                </table>

                                <table class="lines">
                                    <tr><th>Deductions</th><th class="amount">Amount</th></tr>
                                    @forelse($deductions as $deduction)
                                        <tr>
                                            <td>{{ $deduction['description'] ?? 'Deduction' }}</td>
                                            <td class="amount">{{ $peso($deduction['amount'] ?? 0) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td class="muted">None</td><td class="amount">0.00</td></tr>
                                    @endforelse
                                    <tr class="subtotal"><td>Total deductions</td><td class="amount">{{ $peso($item->total_deductions) }}</td></tr>
                                </table>

                                <table class="net">
                                    <tr>
                                        <td>NET PAY</td>
                                        <td class="amount">PHP {{ $peso($item->net_salary) }}</td>
                                    </tr>
                                </table>

                                <table class="sign">
                                    <tr>
                                        <td><div class="line">Prepared by: {{ $payroll->creator?->employee?->fullname ?? $payroll->creator?->username ?? '' }}</div></td>
                                        <td><div class="line">Received by / Date</div></td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    @endforeach
                    {{-- An odd one out still gets its half of the row. --}}
                    @if($pair->count() === 1)
                        <td></td>
                    @endif
                </tr>
            @endforeach
        </table>

        @if(! $loop->last)
            <div class="break"></div>
        @endif
    @empty
        <p>No employees on this payroll.</p>
    @endforelse
@else
@forelse($items as $index => $item)
    @php
        $earnings = collect($item->earnings ?? []);
        $deductions = collect($item->deductions ?? []);
        $otherEarnings = (float) $earnings->sum(fn ($e) => (float) ($e['amount'] ?? 0));
        // Total earnings is stored as basic pay plus every added earning, so the
        // basic pay for the period is what is left once those are taken out.
        $basicPay = max((float) $item->total_earnings - $otherEarnings, 0);
    @endphp

    <div class="payslip">
        <table class="head">
            <tr>
                <td>
                    <div class="company">BOUYANT RICE TRADING</div>
                    <div class="muted">Sinunoc, Zamboanga City, Zamboanga del Sur 7000</div>
                    <div class="muted">Payroll No. {{ $payroll->payroll_no }}</div>
                </td>
                <td class="doc-title">PAYSLIP</td>
            </tr>
        </table>

        <table class="meta">
            <tr>
                <td><strong>Employee:</strong> {{ $item->employee?->fullname ?? 'N/A' }}</td>
                <td><strong>Pay period:</strong>
                    {{ $payroll->pay_period_start->format('M d, Y') }} – {{ $payroll->pay_period_end->format('M d, Y') }}
                </td>
            </tr>
            <tr>
                <td><strong>Position:</strong> {{ $item->employee?->position?->title ?? '—' }}</td>
                <td>
                    <strong>Daily rate:</strong> {{ $peso($item->basic_salary) }}
                    &nbsp;&nbsp; <strong>Days:</strong> {{ rtrim(rtrim(number_format((float) $item->total_days, 2), '0'), '.') }}
                </td>
            </tr>
        </table>

        <table class="cols">
            <tr>
                <td class="left">
                    <table class="lines">
                        <tr><th>Earnings</th><th class="amount">Amount</th></tr>
                        <tr><td>Basic pay</td><td class="amount">{{ $peso($basicPay) }}</td></tr>
                        @foreach($earnings as $earning)
                            <tr>
                                <td>{{ $earning['description'] ?? 'Earning' }}</td>
                                <td class="amount">{{ $peso($earning['amount'] ?? 0) }}</td>
                            </tr>
                        @endforeach
                        <tr class="subtotal"><td>Total earnings</td><td class="amount">{{ $peso($item->total_earnings) }}</td></tr>
                    </table>
                </td>
                <td class="right">
                    <table class="lines">
                        <tr><th>Deductions</th><th class="amount">Amount</th></tr>
                        @forelse($deductions as $deduction)
                            <tr>
                                <td>{{ $deduction['description'] ?? 'Deduction' }}</td>
                                <td class="amount">{{ $peso($deduction['amount'] ?? 0) }}</td>
                            </tr>
                        @empty
                            <tr><td class="muted">None</td><td class="amount">0.00</td></tr>
                        @endforelse
                        <tr class="subtotal"><td>Total deductions</td><td class="amount">{{ $peso($item->total_deductions) }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="net">
            <tr>
                <td>NET PAY</td>
                <td class="amount">PHP {{ $peso($item->net_salary) }}</td>
            </tr>
        </table>

        <table class="sign">
            <tr>
                <td><div class="line">Prepared by: {{ $payroll->creator?->employee?->fullname ?? $payroll->creator?->username ?? '' }}</div></td>
                <td><div class="line">Received by: {{ $item->employee?->fullname ?? '' }} &nbsp; / &nbsp; Date</div></td>
            </tr>
        </table>
    </div>

    {{-- Two to a page keeps each slip whole and easy to cut apart. --}}
    @if(($index + 1) % 2 === 0 && ! $loop->last)
        <div class="break"></div>
    @endif
@empty
    <p>No employees on this payroll.</p>
@endforelse
@endif
</body>
</html>
