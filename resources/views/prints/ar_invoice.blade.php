<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AR Invoice - {{ $ar_invoice->invoice_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            font-size: 14px;
            line-height: 1.4;
            color: #333;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .document-title {
            font-size: 18px;
            font-weight: bold;
        }

        .order-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .info-section {
            flex: 1;
        }

        .info-section h3 {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .info-section p {
            margin: 3px 0;
            font-weight: bold;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        .items-table th,
        .items-table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }

        .items-table th {
            background-color: #000;
            color: white;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 12px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .total-section {
            float: right;
            width: 250px;
            margin-top: 20px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .total-label {
            font-weight: bold;
        }

        .total-amount {
            font-weight: bold;
        }

        .grand-total {
            border-top: 1px solid #000;
            padding-top: 8px;
            font-size: 16px;
        }

        .footer {
            margin-top: 80px;
            text-align: center;
            clear: both;
        }

        .signature-section {
            margin-top: 40px;
            display: flex;
            justify-content: space-around;
        }

        .signature-box {
            text-align: center;
            width: 120px;
        }

        .signature-line {
            border-bottom: 1px solid #000;
            margin-bottom: 3px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">BOUYANT RICE TRADING</div>
        <div class="document-title">INVOICE</div>
    </div>

    <div class="order-info">
        <div class="info-section">
            <h3>Invoice Details</h3>
            <p>Invoice Number: {{ $ar_invoice->invoice_number }}</p>
            <p>Invoice Date: {{ \Carbon\Carbon::parse($ar_invoice->invoice_date)->format('M d, Y') }}</p>
            <p>SO Number: {{ $sales_order->so_number }}</p>
            <p>Status: {{ $ar_invoice->status->name ?? 'N/A' }}</p>
        </div>
        <div class="info-section">
            <h3>Customer</h3>
            <p>Customer: {{ $sales_order->customer->name ?? 'N/A' }}</p>
            <p>Payment Mode: {{ $sales_order->payment_mode ?? 'N/A' }}</p>
            <p>Payment Term: {{ $sales_order->payment_term ?? 'N/A' }}</p>
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="45%">Product</th>
                <th width="15%" class="text-center">Quantity</th>
                <th width="17%" class="text-right">Unit Price</th>
                <th width="18%" class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $item->product->brand->name ?? '' }} {{ $item->product->pack_size }} {{ $item->product->unit->name }} </td>
                <td class="text-center">{{ number_format($item->quantity) }}</td>
                <td class="text-right">PHP {{ number_format($item->price, 2) }}</td>
                <td class="text-right">PHP {{ number_format(($item->price - $item->discount_per_unit) * $item->quantity, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-section">
        <div class="total-row">
            <span class="total-label">Subtotal:</span>
            <span class="total-amount">PHP {{ number_format($sales_order->total_amount + $sales_order->total_discount, 2) }}</span>
        </div>

        <div class="total-row">
            <span class="total-label">Discount:</span>
            <span class="total-amount">PHP {{ number_format($sales_order->total_discount, 2) }}</span>
        </div>

        <div class="total-row">
            <span class="total-label">Amount Paid:</span>
            <span class="total-amount">PHP {{ number_format($ar_invoice->amount_paid, 2) }}</span>
        </div>

        <div class="total-row grand-total">
            <span class="total-label">Balance Due:</span>
            <span class="total-amount">PHP {{ number_format($ar_invoice->balance_due, 2) }}</span>
        </div>
    </div>

    <div style="clear: both;"></div>

    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line"></div>
            <strong>Prepared By</strong>
        </div>
        <div class="signature-box">
            <div class="signature-line"></div>
            <strong>Approved By</strong>
        </div>
        <div class="signature-box">
            <div class="signature-line"></div>
            <strong>Received By</strong>
        </div>
    </div>

    <div class="footer">
        <p><strong>Thank you for your business!</strong></p>
        <p>Generated on {{ \Carbon\Carbon::now()->format('M d, Y H:i') }}</p>
    </div>
</body>
</html>
