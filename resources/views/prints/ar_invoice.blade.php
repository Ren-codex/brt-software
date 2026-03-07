<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AR Invoice - {{ $ar_invoice->invoice_number }}</title>
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
        .logo-box { width: 70px; height: 70px; background-color: #C0392B; border-radius: 4px; }
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
        .text-center { text-align: center; }

        /* Footer */
        .footer-table { width: 100%; margin-top: 30px; }
        .grand-total-box { background-color: #D5DBDB; padding: 10px; font-weight: bold; }

      /* Signature Section */
        .signature-section { width: 100%; margin-top: 40px; display: table; }
        .signature-box { display: table-cell; text-align: center; width: 50%; }
        .signature-line { border-bottom: 1px solid #333; margin-bottom: 5px; padding-bottom: 5px; }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td style="width: 80px;"><div class="logo-box"></div></td>
            <td>
                <h1 class="company-name">BOUYANT RICE TRADING</h1>
                Sinunoc, Zamboanga City Zamboanga del Sur, 7000<br>Philippines
            </td>
            <td class="order-info">
                <h2 class="order-title">AR Invoice</h2>
                #{{ $ar_invoice->invoice_number }}<br>
                {{ \Carbon\Carbon::parse($ar_invoice->invoice_date)->format('m/d/Y') }}
            </td>
        </tr>
    </table>

    <div class="summary-container">
        <div class="address-block">
            <div class="address-label">Bill To</div>
            <strong>{{ $sales_order->customer->name ?? 'N/A' }}</strong><br>
            {!! nl2br(e($sales_order->customer->address ?? '')) !!}
        </div>
        <div class="address-block">
            <div class="address-label">Ship To</div>
            <strong>{{ $sales_order->customer->name ?? 'N/A' }}</strong><br>
            {!! nl2br(e($sales_order->customer->address ?? '')) !!}
        </div>
        <div class="total-block">
            <div style="text-align: left; font-weight: bold; font-size: 14px;">Balance Due</div>
            <div style="font-size: 22px; font-weight: bold;">PHP {{ number_format($ar_invoice->balance_due, 2) }}</div>
        </div>
    </div>

    <table class="meta-table">
        <thead>
            <tr>
                <th>SO Number</th>
                <th>Payment Terms</th>
                <th>Payment Mode</th>
                <th>Status</th>
                <th>Amount Paid</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $sales_order->so_number ?? '---' }}</td>
                <td>{{ $sales_order->payment_term ?? '---' }}</td>
                <td>{{ $sales_order->payment_mode ?? '---' }}</td>
                <td>{{ $ar_invoice->status->name ?? 'N/A' }}</td>
                <td>PHP {{ number_format($ar_invoice->amount_paid, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="40%">Item</th>
                <th width="12%" class="text-center">Quantity</th>
                <th width="13%" class="text-right">Unit Price</th>
                <th width="15%" class="text-right">Discount</th>
                <th width="15%" class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td><strong>{{ $item->product->pack_size ?? '' }} {{ $item->product->unit?->name ?? '' }}</strong> {{ $item->product->brand?->name ?? '' }}</td>
                <td class="text-center">{{ number_format($item->quantity) }}</td>
                <td class="text-right">{{ number_format($item->price, 2) }}</td>
                <td class="text-right">{{ number_format($item->discount_per_unit * $item->quantity, 2) }}</td>
                <td class="text-right">{{ number_format(($item->price - $item->discount_per_unit) * $item->quantity, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="footer-table">
        <tr>
            <td style="vertical-align: top;">
                <strong>Remarks:</strong><br>
                <br><br>
                <strong>Sales Rep:</strong> {{ $sales_order->salesRep->fullname ?? '---' }}
            </td>
            <td style="width: 300px;">
                <table style="width: 100%; border-collapse: collapse;">
                    @php
                        $subtotal = $items->sum(function($item) { return ($item->price - $item->discount_per_unit) * $item->quantity; });
                    @endphp
                    <tr>
                        <td style="padding: 5px 0;">Subtotal</td>
                        <td class="text-right">PHP {{ number_format($sales_order->total_amount + $sales_order->total_discount, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;">Discount</td>
                        <td class="text-right">PHP {{ number_format($sales_order->total_discount, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;">Amount Paid</td>
                        <td class="text-right">PHP {{ number_format($ar_invoice->amount_paid, 2) }}</td>
                    </tr>
                    <tr class="grand-total-box">
                        <td style="padding: 10px 5px;">Balance Due</td>
                        <td class="text-right" style="padding: 10px 5px;">PHP {{ number_format($ar_invoice->balance_due, 2) }}</td>
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

</body>
</html>

