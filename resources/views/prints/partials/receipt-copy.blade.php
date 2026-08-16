<table class="header-table">
    <tr>
        <td style="width: 60px;">
            <div class="logo-box">
                @if(file_exists($logoPath))
                    <img src="{{ $logoPath }}" alt="System Logo" class="logo-img">
                @else
                    <div class="logo-fallback"></div>
                @endif
            </div>
        </td>
        <td>
            <h1 class="company-name">BOUYANT RICE TRADING</h1>
            Sinunoc, Zamboanga City Zamboanga del Sur, 7000<br>Philippines
        </td>
        <td class="order-info" style="width: 120px;">
            @isset($copyLabel)
                <div class="copy-label">{{ $copyLabel }}</div>
            @endisset
            <h2 class="order-title">{{ $isUpdatedReceipt ? 'Updated Receipt' : ($isRefundReceipt ? 'Refund Receipt' : 'Receipt') }}</h2>
            <table class="order-meta-table">
                <tr>
                    <td class="order-meta-label">Date</td>
                </tr>
                <tr>
                    <td class="order-meta-value">{{ $receiptDate }}</td>
                </tr>
                <tr>
                    <td class="order-meta-label">Receipt No.</td>
                </tr>
                <tr>
                    <td class="order-meta-value">{{ $receiptNumber }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="summary-container">
    <div class="address-block">
        <div class="address-label">Bill To</div>
        <strong>{{ optional($customer)->name ?? '---' }}</strong><br>
        {!! nl2br(e(optional($customer)->address ?? '---')) !!}
    </div>
    <div class="address-block">
        <div class="address-label">Ship To</div>
        <strong>{{ optional($customer)->name ?? '---' }}</strong><br>
        {!! nl2br(e(optional($customer)->address ?? '---')) !!}
    </div>
    <div class="total-block">
        <div style="text-align: left; font-weight: bold; font-size: 11px;">
            {{ $isUpdatedReceipt ? 'UPDATED AMOUNT PAID' : ($isRefundReceipt ? 'TOTAL REFUNDED' : 'TOTAL PAID') }}
        </div>
        <div style="font-size: 16px; font-weight: bold;">PHP {{ number_format($displayAmount, 2) }}</div>
    </div>
</div>

<table class="meta-table">
    <thead>
        <tr>
            <th>AR Invoice #</th>
            <th>Payment Terms</th>
            <th>Sales Order #</th>
            <th>Invoice Date</th>
            <th>Due Date</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ optional($ar_invoice)->invoice_number ?? '---' }}</td>
            <td>
                {{ $isUpdatedReceipt
                    ? ($receipt->payment_mode ?? optional($voided_receipt)->payment_mode ?? 'Updated Receipt')
                    : ($isRefundReceipt ? ($receipt->payment_mode ?? 'Refund') : (optional($sales_order)->payment_mode ?? $receipt->payment_mode ?? '---')) }}
            </td>
            <td>{{ optional($sales_order)->so_number ?? '---' }}</td>
            <td>{{ $invoiceDate }}</td>
            <td>{{ $dueDate }}</td>
        </tr>
    </tbody>
</table>

<table class="items-table">
    <thead>
        <tr>
            <th>Quantity</th>
            <th>Item</th>
            <th>Description</th>
            <th>UoM</th>
            <th class="text-right">Unit Price</th>
            <th class="text-right">Amount</th>
            <th class="text-right">VAT</th>
            <th class="text-right">Gross Amt</th>
        </tr>
    </thead>
    <tbody>
        @if($itemsCollection->count() > 0)
        @foreach($itemsCollection as $item)
        @php
            $lineTotal = ((float) $item->quantity * (float) $item->price) - ((float) ($item->discount_per_unit ?? 0) * (float) $item->quantity);
        @endphp
        <tr>
            <td>{{ number_format($item->quantity) }}</td>
            <td><strong>{{ trim((optional($item->product)->weight ?? '') . ' ' . (optional(optional($item->product)->unit)->name ?? '') . ' ' . (optional(optional($item->product)->brand)->name ?? '')) ?: '-' }}</strong></td>
            <td>{{ optional($item->product)->description ?? '-' }}</td>
            <td>{{ optional(optional($item->product)->unit)->name ?? '-' }}</td>
            <td class="text-right">{{ number_format($item->price, 2) }}</td>
            <td class="text-right">{{ number_format($lineTotal, 2) }}</td>
            <td class="text-right">0.00</td>
            <td class="text-right">{{ number_format($lineTotal, 2) }}</td>
        </tr>
        @endforeach
        @else
        <tr>
            <td colspan="8" class="text-right">No items found.</td>
        </tr>
        @endif
    </tbody>
</table>

<table class="footer-table">
    <tr>
        <td style="vertical-align: top;">
            <strong>Remarks:</strong><br>
            {{ $isUpdatedReceipt ? 'Updated Receipt Date' : ($isRefundReceipt ? 'Refund Receipt Date' : 'Receipt Date') }}: {{ $receiptDate }}<br>
            AR Invoice Date: {{ $invoiceDate }}<br>
            Sales Order Date: {{ $orderDate }}<br><br>
            @if(($isUpdatedReceipt || $isRefundReceipt) && $voided_receipt)
            <strong>Voided Receipt:</strong> {{ $voided_receipt->receipt_number }}<br>
            @endif
            @if($isUpdatedReceipt && $receipt->sourceReceipt)
            <strong>Connected Refund Receipt:</strong> {{ $receipt->sourceReceipt->receipt_number }}<br>
            <strong>Refund Deducted:</strong> PHP {{ number_format((float) (($receipt->sourceReceipt->amount_paid ?? 0)), 2) }}<br><br>
            @elseif($isRefundReceipt)
            <strong>Refund Amount:</strong> PHP {{ number_format((float) ($receipt->amount_paid ?? 0), 2) }}<br><br>
            @endif
            <div class="sales-rep-box" style="margin-top: 6px;">
                <strong>Sales Rep:</strong> {{ optional($sales_order)->salesRep->fullname ?? '---' }}
            </div>
        </td>
        <td style="width: 220px;">
            <table style="width: 100%; border-collapse: collapse;">
                @php
                    $subtotal = $itemsCollection->sum(function($item) {
                        return ((float) $item->quantity * (float) $item->price) - ((float) ($item->discount_per_unit ?? 0) * (float) $item->quantity);
                    });
                @endphp
                <tr>
                    <td style="padding: 4px 0;">Total Sales Before VAT</td>
                    <td class="text-right">PHP {{ number_format($subtotal, 2) }}</td>
                </tr>
                <tr>
                    <td style="padding: 4px 0;">
                        {{ $isUpdatedReceipt ? 'Updated Amount Paid' : ($isRefundReceipt ? 'Amount Refunded' : 'Amount Paid') }}
                    </td>
                    <td class="text-right">PHP {{ number_format($displayAmount, 2) }}</td>
                </tr>
                <tr class="grand-total-box">
                    <td style="padding: 7px 5px;">
                        {{ $isUpdatedReceipt ? 'Updated Amount Paid' : ($isRefundReceipt ? 'Total Refund Amount' : 'Total Amount Due') }}
                    </td>
                    <td class="text-right" style="padding: 7px 5px;">PHP {{ number_format(($isUpdatedReceipt || $isRefundReceipt) ? $displayAmount : $balanceDue, 2) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="signature-section">
    <div class="signature-box">
        <div class="signature-line"></div>
        <strong>Prepared By</strong>
    </div>
    <div class="signature-box">
        <div class="signature-line"></div>
        <strong>Received By</strong>
    </div>
</div>
