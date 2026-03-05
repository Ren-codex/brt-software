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

        /* Footer */
        .footer-table { width: 100%; margin-top: 30px; }
        .grand-total-box { background-color: #D5DBDB; padding: 10px; font-weight: bold; }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td style="width: 80px;"><div class="logo-box"></div></td>
            <td>
                <h1 class="company-name">BOUYANT RICE TRADING</h1>
                287 Mabini St. Maloloy-on, Molave ZBDS 7023<br>Philippines
            </td>
            <td class="order-info">
                <h2 class="order-title">Sales Order</h2>
                #{{ $sales_order->so_number }}<br>
                {{ \Carbon\Carbon::parse($sales_order->so_date)->format('m/d/Y') }}
            </td>
        </tr>
    </table>

    <div class="summary-container">
        <div class="address-block">
            <div class="address-label">Bill To</div>
            <strong>{{ $sales_order->customer->name }}</strong><br>
            {!! nl2br(e($sales_order->customer->address)) !!}
        </div>
        <div class="address-block">
            <div class="address-label">Ship To</div>
            <strong>{{ $sales_order->customer->name }}</strong><br>
            {!! nl2br(e($sales_order->customer->address)) !!}
        </div>
        <div class="total-block">
            <div style="text-align: left; font-weight: bold; font-size: 14px;">TOTAL</div>
            <div style="font-size: 22px; font-weight: bold;">PHP {{ number_format($sales_order->total_amount, 2) }}</div>
        </div>
    </div>

    <table class="meta-table">
        <thead>
            <tr>
                <th>Supplier SO #</th>
                <th>Payment Terms</th>
                <th>Load Order #</th>
                <th>Delivery Date</th>
                <th>Delivery Location</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{'---' }}</td>
                <td>7 Days</td>
                <td>---</td>
                <td>{{ \Carbon\Carbon::parse($sales_order->expected_delivery)->format('m/d/Y') }}</td>
                <td>---</td>
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
            @foreach($items as $item)
            <tr>
                <td>{{ number_format($item->quantity) }}</td>
                <td><strong>{{ $item->product->pack_size  }} {{ $item->product->unit?->name  }} {{ $item->product->brand?->name  }}</strong></td>
                <td>{{ $item->product->description }}</td>
                <td>Kg</td>
                <td class="text-right">{{ number_format($item->unit_cost, 2) }}</td>
                <td class="text-right">{{ number_format($item->total_cost, 2) }}</td>
                <td class="text-right">0.00</td>
                <td class="text-right">{{ number_format($item->total_cost, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="footer-table">
        <tr>
            <td style="vertical-align: top;">
                <strong>Remarks:</strong><br>
                30,000 kg - p up<br><br>
                <strong>Sales Rep:</strong> {{ $sales_order->salesRep->fullname ?? '---' }}
            </td>
            <td style="width: 300px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 5px 0;">Total Sales Before VAT</td>
                        <td class="text-right">PHP {{ number_format($sales_order->total_amount, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;">VAT Amount</td>
                        <td class="text-right">PHP 0.00</td>
                    </tr>
                    <tr class="grand-total-box">
                        <td style="padding: 10px 5px;">Total Amount Due</td>
                        <td class="text-right" style="padding: 10px 5px;">PHP {{ number_format($sales_order->total_amount, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

</body>
</html>