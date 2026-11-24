{{-- A table of a definite height holds the copy. DomPDF distributes that height
     across the rows, so the body row carries the content at the top and the foot
     row drops the signatures to the bottom. Same layout as the sales order copy. --}}
<table class="copy-layout {{ $layoutClass ?? 'copy-layout-half' }}">
<tr><td class="copy-body">
{{-- Names the document across the top, where the letterhead used to be. --}}
<div class="doc-title">
    AR INVOICE
    <span class="doc-title-meta">
        ({{ \Carbon\Carbon::parse($ar_invoice->invoice_date)->format('m/d/Y') }})
        (Invoice No. {{ $ar_invoice->invoice_number }})
    </span>
</div>

<table class="header-table">
    <tr>
        {{-- No logo, no company name, no address: these print on headed paper.
             The date and invoice number already sit in the centred title. --}}
        <td class="order-info">
            @isset($copyLabel)
                <div class="copy-label">{{ $copyLabel }}</div>
            @endisset
        </td>
    </tr>
</table>

<div class="summary-container">
    <div class="address-block">
        <div class="address-label">Bill To</div>
        <strong>{{ $sales_order->customer->name ?? '---' }}</strong><br>
        {!! nl2br(e($sales_order->customer->address ?? '---')) !!}
        @if($sales_order->customer->contact_number ?? false)
        <br>{{ $sales_order->customer->contact_number }}
        @endif
    </div>
    <div class="address-block">
        <div class="address-label">Ship To</div>
        <strong>{{ $sales_order->customer->name ?? '---' }}</strong><br>
        {!! nl2br(e($sales_order->customer->address ?? '---')) !!}
        @if($sales_order->customer->contact_number ?? false)
        <br>{{ $sales_order->customer->contact_number }}
        @endif
    </div>
    <div class="address-block crew-block">
        <div class="address-label">Assigned To</div>
        <div class="crew-line"><strong>Sales Rep:</strong> {{ $sales_order->salesRep->fullname ?? '---' }}</div>
    </div>
    <div class="total-block">
        <div style="text-align: left; font-weight: bold; font-size: 13px;">BALANCE DUE</div>
        <div style="font-size: 19px; font-weight: bold;">PHP {{ number_format($ar_invoice->balance_due, 2) }}</div>
    </div>
</div>

<table class="meta-table">
    <thead>
        <tr>
            <th>SO Number</th>
            <th>Payment Mode</th>
            <th>Invoice Date</th>
            <th>Due Date</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ $sales_order->so_number ?? '---' }}</td>
            <td>{{ $sales_order->payment_mode ?? '---' }}</td>
            <td>{{ $ar_invoice->invoice_date ? \Carbon\Carbon::parse($ar_invoice->invoice_date)->format('m/d/Y') : '---' }}</td>
            <td>{{ $ar_invoice->due_date ? \Carbon\Carbon::parse($ar_invoice->due_date)->format('m/d/Y') : '---' }}</td>
            <td>{{ $ar_invoice->status->name ?? 'N/A' }}</td>
        </tr>
    </tbody>
</table>

<table class="items-table{{ ($compact ?? false) ? ' items-compact' : '' }}">
    <thead>
        <tr>
            <th class="qty-col">Quantity</th>
            <th class="text-center">Packaging</th>
            <th>Product</th>
            <th>Batch Code</th>
            <th class="text-right">Unit Price</th>
            <th class="text-right">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($items as $item)
        <tr>
            <td class="qty-col"><strong>{{ number_format($item->quantity) }}</strong></td>
            <td class="text-center">{{ $item->product->packaging->name ?? '---' }}</td>
            <td><strong>{{ $item->product->brand->name ?? '' }} {{ $item->product->weight }}</strong> {{ $item->product->unit->name ?? '' }}</td>
            <td>{{ $item->batch_code ?? '---' }}</td>
            {{-- The unit price shown is what the customer actually pays: the
                 original price less any per-unit discount. --}}
            <td class="text-right">PHP {{ number_format($item->price - $item->discount_per_unit, 2) }}</td>
            <td class="text-right">PHP {{ number_format(($item->price - $item->discount_per_unit) * $item->quantity, 2) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<table class="footer-table">
    <tr>
        <td style="vertical-align: top;"></td>
        <td style="width: 220px;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 3px 5px;">Invoice Total</td>
                    <td class="text-right" style="padding: 3px 5px;">PHP {{ number_format($sales_order->total_amount, 2) }}</td>
                </tr>
                <tr>
                    <td style="padding: 3px 5px;">Amount Paid</td>
                    <td class="text-right" style="padding: 3px 5px;">PHP {{ number_format($ar_invoice->amount_paid, 2) }}</td>
                </tr>
                <tr class="grand-total-box">
                    <td style="padding: 7px 5px;">BALANCE DUE</td>
                    <td class="text-right" style="padding: 7px 5px;">PHP {{ number_format($ar_invoice->balance_due, 2) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

</td></tr>
<tr><td class="copy-foot">
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
</td></tr>
</table>
