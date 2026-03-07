<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.4;
        }

        /* Header */
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .logo-box {
            width: 70px;
            height: 70px;
            border-radius: 4px;
            text-align: center;
            vertical-align: middle;
        }
        .logo-img {
            width: 70px;
            height: 70px;
            object-fit: contain;
        }
        .logo-fallback {
            width: 70px;
            height: 70px;
            background-color: #C0392B;
            border-radius: 4px;
        }
        .company-name { font-size: 20px; font-weight: bold; color: #1a1a1a; margin: 0; }
        .order-title { font-size: 26px; font-weight: bold; text-align: right; margin: 0; }
        .order-info { text-align: right; font-size: 13px; }

        /* Address & Total Section */
        .summary-container { width: 100%; display: table; margin-bottom: 15px; }
        .address-block { display: table-cell; width: 33%; vertical-align: top; }
        .total-block {
            display: table-cell;
            width: 34%;
            background-color: #E5E7E9;
            padding: 15px;
            text-align: right;
            vertical-align: middle;
        }
        .address-label {
            font-weight: bold;
            border-bottom: 1px solid #ccc;
            margin-bottom: 5px;
            display: inline-block;
            width: 90%;
        }

        /* Metadata Bar */
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            background-color: #D5DBDB;
            border: 1px solid #BDC3C7;
        }
        .meta-table th { font-size: 10px; padding: 4px; text-align: center; width: 20%; }
        .meta-table td { background-color: white; text-align: center; padding: 6px; border: 1px solid #BDC3C7; }

        /* Items Table */
        .items-table { width: 100%; border-collapse: collapse; }
        .items-table th { background-color: #D5DBDB; border: 1px solid #BDC3C7; padding: 8px; text-align: left; }
        .items-table td { padding: 8px; border-bottom: 1px solid #eee; }
        .text-right { text-align: right; }

        /* Footer */
        .footer-table { width: 100%; margin-top: 30px; }
        .grand-total-box { background-color: #D5DBDB; padding: 10px; font-weight: bold; }
    </style>
</head>
<body>
    @php
        $customer = $receipt->customer ?? optional($sales_order)->customer;
        $receiptDate = $receipt->receipt_date ? \Carbon\Carbon::parse($receipt->receipt_date)->format('m/d/Y') : '-';
        $invoiceDate = optional($ar_invoice)->invoice_date ? \Carbon\Carbon::parse($ar_invoice->invoice_date)->format('m/d/Y') : '-';
        $orderDate = optional($sales_order)->order_date ? \Carbon\Carbon::parse($sales_order->order_date)->format('m/d/Y') : '-';
        $dueDate = optional($sales_order)->due_date ? \Carbon\Carbon::parse($sales_order->due_date)->format('m/d/Y') : '-';
        $balanceDue = (float) (optional($ar_invoice)->balance_due ?? ($receipt->balance_due ?? 0));
        $logoPath = public_path('images/brt-logo.png');
    @endphp

    <table class="header-table">
        <tr>
            <td style="width: 80px;">
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
            <td class="order-info">
                <h2 class="order-title">Sales Invoice</h2>
                #{{ $sales_order->so_number }}<br>
                {{ \Carbon\Carbon::parse($sales_order->so_date)->format('m/d/Y') }}
            </td>
        </tr>
    </table>

    <div class="summary-container">
        <div class="address-block">
            <div class="address-label">Bill To</div>
            <strong>{{ optional($customer)->name ?? '-' }}</strong><br>
            {!! nl2br(e(optional($customer)->address ?? '-')) !!}
        </div>
        <div class="address-block">
            <div class="address-label">Ship To</div>
            <strong>{{ optional($customer)->name ?? '-' }}</strong><br>
            {!! nl2br(e(optional($customer)->address ?? '-')) !!}
        </div>
        <div class="total-block">
            <div style="text-align: left; font-weight: bold; font-size: 14px;">TOTAL PAID</div>
            <div style="font-size: 22px; font-weight: bold;">PHP {{ number_format((float) ($receipt->amount_paid ?? 0), 2) }}</div>
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
                <td>{{ $receipt->payment_mode ?? optional($sales_order)->payment_mode ?? '---' }}</td>
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
            @forelse($items as $item)
            @php
                $lineTotal = ((float) $item->quantity * (float) $item->price) - ((float) ($item->discount_per_unit ?? 0) * (float) $item->quantity);
            @endphp
            <tr>
                <td>{{ number_format($item->quantity) }}</td>
                <td><strong>{{ $item->product->pack_size  }} {{ $item->product->unit?->name  }} {{ $item->product->brand?->name  }}</strong></td>
                <td>{{ $item->product->description }}</td>
                <td>Kg</td>
                <td class="text-right">{{ number_format($item->price, 2) }}</td>
                <td class="text-right">{{ number_format($item->quantity * $item->price, 2) }}</td>
                <td class="text-right">0.00</td>
                <td class="text-right">{{ number_format($item->quantity * $item->price, 2) }}</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <table class="footer-table">
        <tr>
            <td style="vertical-align: top;">
                <strong>Remarks:</strong><br>
                Receipt Date: {{ $receiptDate }}<br>
                AR Invoice Date: {{ $invoiceDate }}<br>
                Sales Order Date: {{ $orderDate }}<br><br>
                <strong>Sales Rep:</strong> {{ optional($sales_order)->salesRep->fullname ?? '---' }}
            </td>
            <td style="width: 300px;">
                <table style="width: 100%; border-collapse: collapse;">
                    @php
                        $subtotal = $items->sum(function($item) { return $item->quantity * $item->price; });
                    @endphp
                    <tr>
                        <td style="padding: 5px 0;">Total Sales Before VAT</td>
                        <td class="text-right">PHP {{ number_format($subtotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;">Amount Paid</td>
                        <td class="text-right">PHP {{ number_format((float) ($receipt->amount_paid ?? 0), 2) }}</td>
                    </tr>
                    <tr class="grand-total-box">
                        <td style="padding: 10px 5px;">Total Amount Due</td>
                        <td class="text-right" style="padding: 10px 5px;">PHP {{ number_format($subtotal, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

</body>
</html>
