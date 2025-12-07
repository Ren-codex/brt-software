<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order - {{ $purchase_order->po_number }}</title>
    <style>
        @page {
            size: A4;
            margin: 20mm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            text-transform: uppercase;
        }
        .header p {
            margin: 5px 0;
            font-size: 14px;
        }
        .po-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .po-details, .supplier-details {
            width: 48%;
        }
        .po-details h3, .supplier-details h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            text-decoration: underline;
        }
        .po-details p, .supplier-details p {
            margin: 3px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            border: 1px solid #333;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .total-section {
            margin-top: 20px;
            text-align: right;
        }
        .total-section p {
            margin: 5px 0;
            font-size: 14px;
        }
        .total-amount {
            font-size: 16px;
            font-weight: bold;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Purchase Order</h1>
        <p>PO Number: {{ $purchase_order->po_number }}</p>
        <p>Date: {{ \Carbon\Carbon::parse($purchase_order->po_date)->format('M d, Y') }}</p>
    </div>

    <div class="po-info">
        <div class="supplier-details">
            <h3>Supplier Information</h3>
            <p><strong>{{ $purchase_order->supplier->name }}</strong></p>
            @if($purchase_order->supplier->address)
                <p>{{ $purchase_order->supplier->address }}</p>
            @endif
            @if($purchase_order->supplier->contact_person)
                <p>Contact: {{ $purchase_order->supplier->contact_person }}</p>
            @endif
            @if($purchase_order->supplier->phone)
                <p>Phone: {{ $purchase_order->supplier->phone }}</p>
            @endif
        </div>

        <div class="po-details">
            <h3>Purchase Order Details</h3>
            <p><strong>Status:</strong> {{ $purchase_order->status->name }}</p>
            <p><strong>Created By:</strong> {{ $purchase_order->created_by->profile->full_name ?? 'N/A' }}</p>
            <p><strong>Created Date:</strong> {{ \Carbon\Carbon::parse($purchase_order->created_at)->format('M d, Y H:i') }}</p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 40%;">Product</th>
                <th style="width: 15%;" class="text-center">Quantity</th>
                <th style="width: 15%;" class="text-center">Unit</th>
                <th style="width: 15%;" class="text-right">Unit Price</th>
                <th style="width: 15%;" class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $item->product->brand->name }}</td>
                <td class="text-center">{{ number_format($item->quantity) }}</td>
                <td class="text-center">{{ $item->product->unit->name ?? 'N/A' }}</td>
                <td class="text-right">{{ number_format($item->unit_cost, 2) }}</td>
                <td class="text-right">{{ number_format($item->total_cost, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-section">
        <p><strong>Subtotal:</strong> {{ number_format($purchase_order->items->sum(function($item) { return $item->quantity * $item->unit_price; }), 2) }}</p>
        @if($purchase_order->tax_amount > 0)
            <p><strong>Tax:</strong> {{ number_format($purchase_order->tax_amount, 2) }}</p>
        @endif
        @if($purchase_order->discount_amount > 0)
            <p><strong>Discount:</strong> {{ number_format($purchase_order->discount_amount, 2) }}</p>
        @endif
        <p class="total-amount"><strong>Total Amount:</strong> {{ number_format($purchase_order->total_amount, 2) }}</p>
    </div>

    <div class="footer">
        <p>This is a system-generated purchase order. Please retain for your records.</p>
    </div>
</body>
</html>
