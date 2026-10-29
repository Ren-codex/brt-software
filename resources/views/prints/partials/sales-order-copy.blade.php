{{-- Names the document across the top, where the letterhead used to be. --}}
<div class="doc-title">
    SALES ORDER
    <span class="doc-title-meta">
        ({{ \Carbon\Carbon::parse($sales_order->order_date)->format('m/d/Y') }})
        (Sales Order No. {{ $sales_order->so_number }})
    </span>
</div>

<table class="header-table">
    <tr>
        {{-- No logo, no company name, no address: these print on headed paper,
             which carries all three already. --}}
        {{-- No "Sales Order" heading: the copy label and the numbered block
             below already say what this is. --}}
        <td class="order-info">
            @isset($copyLabel)
                <div class="copy-label">{{ $copyLabel }}</div>
            @endisset
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
        @if($sales_order->customer->contact_number)
        <br>{{ $sales_order->customer->contact_number }}
        @endif
    </div>
    <div class="address-block">
        <div class="address-label">Ship To</div>
        <strong>{{ $sales_order->customer->name ?? '---' }}</strong><br>
        {!! nl2br(e($sales_order->customer->address ?? '---')) !!}
        @if($sales_order->customer->contact_number)
        <br>{{ $sales_order->customer->contact_number }}
        @endif
    </div>
    {{-- Beside the addresses, where whoever reads the page is already
         looking: who sold it, who is carrying it, who takes the money. --}}
    <div class="address-block crew-block">
        <div class="address-label">Assigned To</div>
        <div class="crew-line"><strong>Sales Rep:</strong> {{ $sales_order->salesRep->fullname ?? '---' }}</div>
        <div class="crew-line"><strong>Driver:</strong> {{ $sales_order->driver->fullname ?? '---' }}</div>
        {{-- Only when one rides along: the customer pays the checker, so
             their name belongs on the copy the customer keeps. --}}
        @if ($sales_order->checker)
        <div class="crew-line"><strong>Checker:</strong> {{ $sales_order->checker->fullname }}</div>
        @endif
    </div>
    <div class="total-block">
        <div style="text-align: left; font-weight: bold; font-size: 13px;">TOTAL</div>
        <div style="font-size: 19px; font-weight: bold;">PHP {{ number_format($sales_order->total_amount, 2) }}</div>
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
            <td>{{ $sales_order->delivery_location ?: ($sales_order->location->name ?? '---') }}</td>
            <td>{{ $sales_order->shipping_date ? \Carbon\Carbon::parse($sales_order->shipping_date)->format('m/d/Y') : '---' }}</td>
            <td>{{ $sales_order->delivery_date ? \Carbon\Carbon::parse($sales_order->delivery_date)->format('m/d/Y') : '---' }}</td>
            <td>{{ $sales_order->payment_mode ?? '---' }}</td>
            <td>{{ $sales_order->due_date ? \Carbon\Carbon::parse($sales_order->due_date)->format('m/d/Y') : '---' }}</td>
        </tr>
    </tbody>
</table>

<table class="items-table">
    <thead>
        <tr>
            <th class="qty-col">Quantity</th>
            {{-- Beside the count, because the two are read as one thing:
                 "2 sacks", not a quantity here and its unit three columns
                 away. --}}
            <th class="text-center">Packaging</th>
            <th>Product</th>
            <th>Batch Code</th>
            <th class="text-right">Unit Price</th>
            <th class="text-right">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($items as $index => $item)
        <tr>
            <td class="qty-col"><strong>{{ number_format($item->quantity) }}</strong></td>
            <td class="text-center">{{ $item->product->packaging->name ?? '---' }}</td>
            {{-- The unit belongs to the weight: "Princess Bea Red 25 Kg" is the
                 product's name, the way it is written everywhere else. What
                 goes in its own column is how it is packed. --}}
            <td><strong>{{ $item->product->brand->name ?? '' }} {{ $item->product->weight }}</strong> {{ $item->product->unit->name ?? '' }}</td>
            <td>{{ $item->batch_code ?? '---' }}</td>
            <td class="text-right">PHP {{ number_format($item->price, 2) }}</td>
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
        <strong>Checker</strong>
    </div>
    <div class="signature-box">
        <div class="signature-line"></div>
        <strong>Received By</strong>
    </div>
</div>
