
 <!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Order - {{ $purchase_order->po_number }}</title>
    <style>
        @page { size: A4; margin: 10mm; }
        body {
            font-family: 'Arial', sans-serif;
            font-size: 11px;
            color: #333;
            margin: 0;
            background-color: #fff;
        }

        /* Header Layout */
        .header-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .company-branding {
            display: flex;
            align-items: center;
        }
        .logo-placeholder {
            width: 60px;
            height: 60px;
            background-color: #C0392B; /* Red from the logo */
            margin-right: 15px;
            border-radius: 4px;
        }
        .company-info h1 {
            margin: 0;
            font-size: 18px;
            color: #2c3e50;
            text-transform: capitalize;
        }
        .order-title-block {
            text-align: right;
        }
        .order-title-block h2 {
            margin: 0;
            font-size: 24px;
            color: #333;
        }

        /* Addresses and Total Box */
        .address-total-row {
            display: flex;
            margin-bottom: 20px;
        }
        .address-col {
            flex: 1;
        }
        .address-label {
            font-weight: bold;
            border-bottom: 1px solid #ccc;
            margin-bottom: 5px;
            width: 80%;
        }
        .total-box {
            width: 300px;
            background-color: #E5E7E9;
            padding: 15px;
            text-align: right;
        }
        .total-box .total-label {
            font-size: 16px;
            font-weight: bold;
            display: block;
            text-align: left;
        }
        .total-box .total-amount {
            font-size: 22px;
            font-weight: bold;
        }

        /* Gray Metadata Bar */
        .meta-bar {
            background-color: #D5DBDB;
            display: flex;
            padding: 5px 0;
            margin-bottom: 10px;
            border: 1px solid #BDC3C7;
        }
        .meta-item {
            flex: 1;
            text-align: center;
            font-weight: bold;
            font-size: 10px;
        }

        /* Table Design */
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background-color: #D5DBDB;
            padding: 8px;
            text-align: left;
            border: 1px solid #BDC3C7;
        }
        td {
            padding: 8px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }
        .text-right { text-align: right; }

        /* Footer Summary */
        .footer-grid {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        .summary-table {
            width: 300px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
        }
        .grand-total-row {
            background-color: #D5DBDB;
            padding: 8px;
            font-weight: bold;
            margin-top: 5px;
        }
    </style>
</head>
<body>

    <div class="header-container">
        <div class="company-branding">
            <div class="logo-placeholder"></div>
            <div class="company-info">
                <h1>Molave Youngs Milling Corporation</h1>
                287 Mabini St. Maloloy-on, Molave ZBDS 7023<br>
                Philippines
            </div>
        </div>
        <div class="order-title-block">
            <h2>Sales Order</h2>
            <div>#{{ $purchase_order->po_number }}</div>
            <div>{{ \Carbon\Carbon::parse($purchase_order->po_date)->format('m/d/Y') }}</div>
        </div>
    </div>

    <div class="address-total-row">
        <div class="address-col">
            <div class="address-label">Bill To</div>
            <strong>{{ $purchase_order->supplier->name }}</strong><br>
            {!! nl2br(e($purchase_order->supplier->address)) !!}
        </div>
        <div class="address-col">
            <div class="address-label">Ship To</div>
            <strong>{{ $purchase_order->supplier->name }}</strong><br>
            {!! nl2br(e($purchase_order->supplier->address)) !!}
        </div>
        <div class="total-box">
            <span class="total-label">TOTAL</span>
            <span class="total-amount">PHP {{ number_format($purchase_order->total_amount, 2) }}</span>
        </div>
    </div>

    <div class="meta-bar">
        <div class="meta-item">Supplier SO #</div>
        <div class="meta-item">Payment Terms</div>
        <div class="meta-item">Load Order #</div>
        <div class="meta-item">Delivery Date</div>
        <div class="meta-item">Delivery Location</div>
    </div>
    <div class="meta-bar" style="background:none; border-top:none; margin-top:-11px;">
        <div class="meta-item" style="font-weight:normal;">---</div>
        <div class="meta-item" style="font-weight:normal;">7 Days</div>
        <div class="meta-item" style="font-weight:normal;">---</div>
        <div class="meta-item" style="font-weight:normal;">{{ \Carbon\Carbon::parse($purchase_order->expected_delivery)->format('m/d/Y') }}</div>
        <div class="meta-item" style="font-weight:normal;">---</div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 80px;">Quantity</th>
                <th>Item</th>
                <th>Description</th>
                <th>UoM</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Amount</th>
                <th class="text-right">VAT Amount</th>
                <th class="text-right">Gross Amt</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
            <tr>
                <td>{{ number_format($item->quantity) }}</td>
                <td>
                    <strong>{{ $item->product->sku }}</strong><br>
                    {{ $item->product->brand->name }}
                </td>
                <td>{{ $item->product->description ?? $item->product->brand->name }}</td>
                <td>Bg</td>
                <td class="text-right">PHP {{ number_format($item->unit_cost, 2) }}</td>
                <td class="text-right">PHP {{ number_format($item->total_cost, 2) }}</td>
                <td class="text-right">PHP 0.00</td>
                <td class="text-right">PHP {{ number_format($item->total_cost, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer-grid">
        <div class="remarks">
            <strong>Remarks:</strong><br>
            30,000 kg - p up<br><br>
            <strong>Sales Rep:</strong> {{ $purchase_order->created_by->profile->full_name ?? 'Jelsa Ayuda' }}
        </div>
        <div class="summary-table">
            <div class="summary-row">
                <span>Total Sales Before VAT</span>
                <span>PHP {{ number_format($purchase_order->total_amount, 2) }}</span>
            </div>
            <div class="summary-row">
                <span>VAT Amount</span>
                <span>PHP 0.00</span>
            </div>
            <div class="grand-total-row">
                <div class="summary-row" style="margin:0;">
                    <span>Total Amount Due</span>
                    <span>PHP {{ number_format($purchase_order->total_amount, 2) }}</span>
                </div>
            </div>
        </div>
    </div>

</body>
</html>