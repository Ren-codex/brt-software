{{-- One copy of the loan statement, sized to fit half a long bond sheet.
     Expects the figures worked out in prints/loan.blade.php, plus $copyLabel. --}}
<table class="header-table">
    <tr>
        <td>
            <div class="company">BOUYANT RICE TRADING</div>
            <div class="muted">Sinunoc, Zamboanga City, Zamboanga del Sur 7000, Philippines</div>
        </td>
        <td class="title-cell">
            <div class="copy-label">{{ $copyLabel }}</div>
            <div class="title">LOAN STATEMENT</div>
            <div class="muted">Loan No. {{ $loan->loan_no ?? '—' }} · {{ $label($loan->status) }}</div>
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
        <td class="label">Loan type</td>
        <td class="value">{{ $label($loan->loan_type) }}</td>
        <td class="label">Date filed</td>
        <td class="value">{{ $loan->created_at?->format('M d, Y') ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Position</td>
        <td class="value">{{ $employee?->position?->title ?? '—' }}</td>
        <td class="label">Payment type</td>
        <td class="value">{{ $label($loan->payment_type) }}</td>
        <td class="label">Approved</td>
        <td class="value">{{ $loan->approved_at?->format('M d, Y') ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Purpose</td>
        {{-- Capped at about two lines, so a long purpose cannot push the
             bottom copy off the sheet. --}}
        <td class="value">{{ $loan->purpose ? \Illuminate\Support\Str::limit($loan->purpose, 60) : '—' }}</td>
        <td class="label">Term</td>
        <td class="value">{{ (int) $loan->term_months }} mo · {{ $cutoffs }} cut-offs</td>
        <td class="label">Released</td>
        <td class="value">{{ $released?->created_at?->format('M d, Y') ?? '—' }}</td>
    </tr>
</table>

{{-- The terms on the left, where the loan stands on the right. --}}
<table class="split">
    <tr>
        <td class="split-left">
            <table class="lines">
                <tr><th>Loan terms</th><th class="amount">PHP</th></tr>
                <tr><td>Principal</td><td class="amount">{{ $peso($principal) }}</td></tr>
                <tr><td>Interest <span class="muted">({{ $rate }}%)</span></td><td class="amount">{{ $peso($interest) }}</td></tr>
                <tr class="subtotal"><td>Total payable</td><td class="amount">{{ $peso($total) }}</td></tr>
                <tr><td>Per cut-off <span class="muted">(÷ {{ max($cutoffs, 1) }})</span></td><td class="amount">{{ $peso($perCutoff) }}</td></tr>
            </table>
        </td>
        <td class="split-right">
            <table class="lines">
                <tr><th>Standing</th><th class="amount">PHP</th></tr>
                <tr><td>Total paid</td><td class="amount">{{ $peso($paid) }}</td></tr>
                <tr><td>Cut-offs left</td><td class="amount">{{ $wholeOrDecimal($remainingCutoffs) }}</td></tr>
            </table>
            <table class="balance">
                <tr>
                    <td>REMAINING BALANCE</td>
                    <td class="amount">PHP {{ $peso($balance) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="section">Payments</div>
<table class="lines">
    <tr><th style="width: 4%;">#</th><th>Period covered</th><th style="width: 13%;">Recorded</th><th style="width: 8%;">Cut-offs</th><th style="width: 19%;">Received by</th><th class="amount" style="width: 12%;">Amount</th></tr>
    @forelse($payments as $index => $payment)
        {{-- One line per payment, so a full 12-payment loan still fits half a
             sheet. Remarks are left off for the same reason. --}}
        <tr class="payment-row">
            <td>{{ $index + 1 }}</td>
            {{-- paid_date is a free-text label, often a pay period such as
                 "October 1-15, 2026", so it is printed as written, never parsed. --}}
            <td>{{ $payment->paid_date ?: '—' }}</td>
            <td>{{ $payment->created_at?->format('M d, Y') ?? '—' }}</td>
            <td>{{ $payment->paid_term ? $wholeOrDecimal($payment->paid_term) : '—' }}</td>
            <td>{{ $userName($payment->addedBy) ?? '—' }}</td>
            <td class="amount">{{ $peso($payment->amount) }}</td>
        </tr>
    @empty
        <tr><td colspan="6" class="muted">No payments recorded yet</td></tr>
    @endforelse
</table>

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
