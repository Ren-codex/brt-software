<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order - {{ $purchase_order->po_number }}</title>
    <style>
        @page {
            size: A4;
            margin: 15mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #fff;
        }
        
        /* Header Section */
        .letterhead {
            border-bottom: 3px solid #2c3e50;
            padding-bottom: 15px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        .company-info h1 {
            margin: 0 0 5px 0;
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
        }
        .company-info .tagline {
            color: #7f8c8d;
            font-size: 12px;
            margin-bottom: 8px;
        }
        .company-details {
            font-size: 11px;
            color: #555;
        }
        .document-type {
            text-align: right;
        }
        .document-type h2 {
            margin: 0;
            font-size: 32px;
            font-weight: 300;
            color: #3498db;
            letter-spacing: 2px;
        }
        .document-type .subtitle {
            font-size: 14px;
            color: #7f8c8d;
            margin-top: 5px;
        }
        
        /* PO Number Badge */
        .po-header {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            margin: 20px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 3px 10px rgba(52, 152, 219, 0.2);
        }
        .po-number h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }
        .po-date {
            font-size: 14px;
            font-weight: 500;
        }
        
        /* Info Cards */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        .info-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            background: #f9f9f9;
        }
        .info-card h4 {
            margin: 0 0 12px 0;
            color: #2c3e50;
            font-size: 14px;
            font-weight: 600;
            padding-bottom: 8px;
            border-bottom: 2px solid #3498db;
        }
        .info-row {
            display: flex;
            margin-bottom: 6px;
        }
        .info-label {
            font-weight: 600;
            color: #555;
            min-width: 120px;
        }
        .info-value {
            color: #333;
        }
        
        /* Items Table */
        .items-section {
            margin: 30px 0;
        }
        .section-title {
            background: #2c3e50;
            color: white;
            padding: 8px 15px;
            border-radius: 6px 6px 0 0;
            font-weight: 600;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #e0e0e0;
        }
        thead {
            background: #f8f9fa;
        }
        th {
            padding: 12px 10px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #dee2e6;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        td {
            padding: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        tbody tr:hover {
            background-color: #f8f9fa;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .total-row {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        /* Totals Section */
        .totals-section {
            margin-top: 25px;
            display: flex;
            justify-content: flex-end;
        }
        .totals-card {
            width: 300px;
            border: 2px solid #2c3e50;
            border-radius: 8px;
            overflow: hidden;
        }
        .totals-header {
            background: #2c3e50;
            color: white;
            padding: 10px 15px;
            font-weight: 600;
            font-size: 14px;
        }
        .totals-body {
            padding: 15px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed #e0e0e0;
        }
        .total-row:last-child {
            border-bottom: none;
        }
        .grand-total {
            font-size: 16px;
            font-weight: 700;
            color: #2c3e50;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 2px solid #2c3e50;
        }
        
        /* Footer */
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            color: #7f8c8d;
            font-size: 10px;
        }
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin: 30px 0;
            padding: 20px;
        }
        .signature-box {
            text-align: center;
        }
        .signature-line {
            width: 200px;
            height: 1px;
            background: #333;
            margin: 40px auto 10px;
        }
        .signature-label {
            font-size: 11px;
            color: #555;
            margin-top: 5px;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-{{ strtolower($purchase_order->status->name) }} {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        /* Print Optimizations */
        @media print {
            body {
                font-size: 11px;
            }
            .po-header {
                background: #3498db !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .section-title {
                background: #2c3e50 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .totals-header {
                background: #2c3e50 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <!-- Letterhead -->
    <div class="letterhead">
        <div class="company-info">
            <h1>Your Company Name</h1>
            <div class="tagline">Quality Products & Services</div>
            <div class="company-details">
                123 Business Street, City, State 12345<br>
                Phone: (123) 456-7890 | Email: info@company.com<br>
                www.company.com
            </div>
        </div>
        <div class="document-type">
            <h2>PO</h2>
            <div class="subtitle">Purchase Order</div>
        </div>
    </div>
    
    <!-- PO Header -->
    <div class="po-header">
        <div class="po-number">
            <h3>PO: {{ $purchase_order->po_number }}</h3>
        </div>
        <div class="po-date">
            Date: {{ \Carbon\Carbon::parse($purchase_order->po_date)->format('F d, Y') }}
        </div>
    </div>
    
    <!-- Information Grid -->
    <div class="info-grid">
        <div class="info-card">
            <h4>Supplier Information</h4>
            <div class="info-row">
                <div class="info-label">Supplier:</div>
                <div class="info-value">{{ $purchase_order->supplier->name }}</div>
            </div>
            @if($purchase_order->supplier->address)
            <div class="info-row">
                <div class="info-label">Address:</div>
                <div class="info-value">{{ $purchase_order->supplier->address }}</div>
            </div>
            @endif
            @if($purchase_order->supplier->contact_person)
            <div class="info-row">
                <div class="info-label">Contact Person:</div>
                <div class="info-value">{{ $purchase_order->supplier->contact_person }}</div>
            </div>
            @endif
            @if($purchase_order->supplier->phone)
            <div class="info-row">
                <div class="info-label">Phone:</div>
                <div class="info-value">{{ $purchase_order->supplier->phone }}</div>
            </div>
            @endif
        </div>
        
        <div class="info-card">
            <h4>Order Details</h4>
            <div class="info-row">
                <div class="info-label">Status:</div>
                <div class="info-value">
                    <span class="status-badge status-{{ strtolower($purchase_order->status->name) }}">
                        {{ $purchase_order->status->name }}
                    </span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Prepared By:</div>
                <div class="info-value">{{ $purchase_order->created_by->profile->full_name ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Created Date:</div>
                <div class="info-value">{{ \Carbon\Carbon::parse($purchase_order->created_at)->format('M d, Y H:i') }}</div>
            </div>
            @if($purchase_order->expected_delivery)
            <div class="info-row">
                <div class="info-label">Expected Delivery:</div>
                <div class="info-value">{{ \Carbon\Carbon::parse($purchase_order->expected_delivery)->format('M d, Y') }}</div>
            </div>
            @endif
        </div>
    </div>
    
    <!-- Items Table -->
    <div class="items-section">
        <div class="section-title">Order Items</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 40%;">Product Description</th>
                    <th style="width: 10%;" class="text-center">Qty</th>
                    <th style="width: 15%;" class="text-center">Unit</th>
                    <th style="width: 15%;" class="text-right">Unit Price</th>
                    <th style="width: 15%;" class="text-right">Line Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $item->product->brand->name }}</strong><br>
                        <small style="color: #666;">SKU: {{ $item->product->sku ?? 'N/A' }}</small>
                    </td>
                    <td class="text-center">{{ number_format($item->quantity) }}</td>
                    <td class="text-center">{{ $item->product->unit->name ?? 'N/A' }}</td>
                    <td class="text-right">${{ number_format($item->unit_cost, 2) }}</td>
                    <td class="text-right">${{ number_format($item->total_cost, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    
    <!-- Totals Section -->
    <div class="totals-section">
        <div class="totals-card">
            <div class="totals-header">Order Summary</div>
            <div class="totals-body">
                <div class="total-row">
                    <span>Subtotal:</span>
                    <span>${{ number_format($purchase_order->items->sum(function($item) { return $item->quantity * $item->unit_price; }), 2) }}</span>
                </div>
                @if($purchase_order->tax_amount > 0)
                <div class="total-row">
                    <span>Tax ({{ $purchase_order->tax_rate ?? '0' }}%):</span>
                    <span>${{ number_format($purchase_order->tax_amount, 2) }}</span>
                </div>
                @endif
                @if($purchase_order->discount_amount > 0)
                <div class="total-row">
                    <span>Discount:</span>
                    <span>-${{ number_format($purchase_order->discount_amount, 2) }}</span>
                </div>
                @endif
                @if($purchase_order->shipping_amount > 0)
                <div class="total-row">
                    <span>Shipping:</span>
                    <span>${{ number_format($purchase_order->shipping_amount, 2) }}</span>
                </div>
                @endif
                <div class="total-row grand-total">
                    <span>TOTAL AMOUNT:</span>
                    <span>${{ number_format($purchase_order->total_amount, 2) }}</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Signatures Section -->
    <div class="signatures">
        <div class="signature-box">
            <div class="signature-label">Supplier's Signature & Stamp</div>
            <div class="signature-line"></div>
            <div class="signature-label">Date: ________________</div>
        </div>
        <div class="signature-box">
            <div class="signature-label">Authorized Signature</div>
            <div class="signature-line"></div>
            <div class="signature-label">Date: ________________</div>
        </div>
    </div>
    
    <!-- Footer -->
    <div class="footer">
        <p>This is a system-generated purchase order. Please retain for your records.</p>
        <p>Page 1 of 1 | Generated on {{ \Carbon\Carbon::now()->format('M d, Y H:i') }}</p>
    </div>
</body>
</html>