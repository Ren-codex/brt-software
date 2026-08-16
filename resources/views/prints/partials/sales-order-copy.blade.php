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
            <h2 class="order-title">Sales Order</h2>
            <table class="order-meta-table">
                <tr>
                    <td class="order-meta-value">{{ \Carbon\Carbon::parse($sales_order->order_date)->format('m/d/Y') }}</td>
                </tr>
                <tr>
                    <td class="order-meta-label">Sales Order No.</td>
                </tr>
                <tr>
                    <td class="order-meta-value">{{ $sales_order->so_number }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="summary-container">
    <div class="address-block">
        <div class="address-label">Bill To</div>
        <strong>{{ $sales_order->customer->name ?? '---' }}</strong><br>
        {!! nl2br(e($sales_order->customer->address ?? '---')) !!}
    </div>
    <div class="address-block">
        <div class="address-label">Ship To</div>
        <strong>{{ $sales_order->customer->name ?? '---' }}</strong><br>
        {!! nl2br(e($sales_order->customer->address ?? '---')) !!}
    </div>
    <div class="total-block">
        <div style="text-align: left; font-weight: bold; font-size: 11px;">TOTAL</div>
        <div style="font-size: 16px; font-weight: bold;">PHP {{ number_format($sales_order->total_amount, 2) }}</div>
    </div>
</div>

<table class="meta-table">
    <thead>
        <tr>
            <th>Delivery Location</th>
            <th>Shipping Date</th>
            <th>Delivery Date</th>
            <th>Payment Terms</th>
            <th>Due Date</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ $sales_order->location->name ?? '---' }}</td>
            <td>---</td>
            <td>---</td>
            <td>{{ $sales_order->payment_mode ?? '---' }}</td>
            <td>{{ $sales_order->due_date ? \Carbon\Carbon::parse($sales_order->due_date)->format('m/d/Y') : '---' }}</td>
        </tr>
    </tbody>
</table>

<table class="items-table">
    <thead>
        <tr>
            <th>Item #</th>
            <th>Product</th>
            <th>Unit of Measurement</th>
            <th class="text-right">Quantity</th>
            <th class="text-right">Unit Price</th>
            <th class="text-right">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($items as $index => $item)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td><strong>{{ $item->product->brand->name ?? '' }} {{ $item->product->weight }}</strong></td>
            <td>{{ $item->product->unit->name ?? '' }}</td>
            <td class="text-right">{{ number_format($item->quantity) }}</td>
            <td class="text-right">PHP {{ number_format($item->price, 2) }}</td>
            <td class="text-right">PHP {{ number_format(($item->price - $item->discount_per_unit) * $item->quantity, 2) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<table class="footer-table">
    <tr>
        <td style="vertical-align: top;">
            <div class="sales-rep-box">
                <strong>Sales Rep:</strong> {{ $sales_order->salesRep->fullname ?? '---' }}
            </div>
        </td>
        <td style="width: 220px;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 4px 0;">Subtotal</td>
                    <td class="text-right">PHP {{ number_format($sales_order->total_amount + $sales_order->total_discount, 2) }}</td>
                </tr>
                <tr>
                    <td style="padding: 4px 0;">Discount</td>
                    <td class="text-right">PHP {{ number_format($sales_order->total_discount, 2) }}</td>
                </tr>
                <tr class="grand-total-box">
                    <td style="padding: 7px 5px;">TOTAL</td>
                    <td class="text-right" style="padding: 7px 5px;">PHP {{ number_format($sales_order->total_amount, 2) }}</td>
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
