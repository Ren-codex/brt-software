<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Order #{{ $sales_order->so_number }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 0.5in;
        }
        
        body {
            font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 0;
            font-size: 12px;
            line-height: 1.4;
            color: #000;
        }
        
        .container {
            max-width: 100%;
            margin: 0 auto;
        }
        
        /* Header Styles */
        .header {
            border-bottom: 3px solid #333;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .company-info {
            float: left;
            width: 60%;
        }
        
        .company-logo {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .company-details {
            font-size: 11px;
            color: #555;
        }
        
        .document-title {
            float: right;
            width: 35%;
            text-align: right;
        }
        
        .title {
            font-size: 28px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .document-info {
            border: 2px solid #333;
            padding: 10px;
            background-color: #f9f9f9;
        }
        
        /* Clear floats */
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
        
        /* Two-column layout */
        .two-columns {
            display: flex;
            margin-bottom: 20px;
            gap: 20px;
        }
        
        .column {
            flex: 1;
        }
        
        /* Section styles */
        .section {
            margin-bottom: 20px;
        }
        
        .section-title {
            background-color: #2c3e50;
            color: white;
            padding: 6px 10px;
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 13px;
        }
        
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .info-table td {
            padding: 4px 8px;
            vertical-align: top;
            border: 1px solid #ddd;
        }
        
        .info-table .label {
            font-weight: bold;
            background-color: #f5f5f5;
            width: 30%;
        }
        
        /* Items table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .items-table th {
            background-color: #2c3e50;
            color: white;
            padding: 8px;
            text-align: center;
            font-weight: bold;
            border: 1px solid #ddd;
        }
        
        .items-table td {
            padding: 6px 8px;
            border: 1px solid #ddd;
        }
        
        .items-table .text-left {
            text-align: left;
        }
        
        .items-table .text-right {
            text-align: right;
        }
        
        .items-table .text-center {
            text-align: center;
        }
        
        /* Totals section */
        .totals-section {
            float: right;
            width: 40%;
            margin-top: 20px;
        }
        
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .totals-table td {
            padding: 6px 10px;
            border: 1px solid #ddd;
        }
        
        .totals-table .label {
            font-weight: bold;
            text-align: right;
        }
        
        .totals-table .amount {
            text-align: right;
            width: 40%;
        }
        
        .grand-total {
            background-color: #2c3e50;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }
        
        /* Footer styles */
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #333;
            font-size: 10px;
            color: #666;
        }
        
        .terms-conditions {
            margin-bottom: 20px;
        }
        
        .signature-section {
            margin-top: 30px;
        }
        
        .signature-box {
            display: inline-block;
            width: 45%;
            margin-right: 5%;
            padding-top: 40px;
            border-top: 1px solid #333;
            text-align: center;
        }
        
        /* Utility classes */
        .b {
            font-weight: bold;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .mt-20 {
            margin-top: 20px;
        }
        
        .mb-10 {
            margin-bottom: 10px;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        /* Status badges */
        .status-badge {
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .status-pending {
            background-color: #f39c12;
            color: white;
        }
        
        .status-approved {
            background-color: #27ae60;
            color: white;
        }
        
        .status-cancelled {
            background-color: #e74c3c;
            color: white;
        }
        
        .status-completed {
            background-color: #3498db;
            color: white;
        }
        
        .status-draft {
            background-color: #95a5a6;
            color: white;
        }
        
        /* Company info - you might want to move this to config */
        .company-header {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header Section -->
        <div class="header clearfix">
            <div class="company-info">
                <div class="company-logo">{{ config('app.company_name', 'YOUR COMPANY') }}</div>
                <div class="company-details">
                    {{ config('app.company_address', '123 Business Avenue') }}<br>
                    {{ config('app.company_city', 'City, State 12345') }}<br>
                    Phone: {{ config('app.company_phone', '(555) 123-4567') }} | 
                    Email: {{ config('app.company_email', 'sales@company.com') }}<br>
                    Website: {{ config('app.company_website', 'www.company.com') }} | 
                    {{ config('app.company_vat', 'VAT: #123456789') }}
                </div>
            </div>
            
            <div class="document-title">
                <div class="title">SALES ORDER</div>
                <div class="document-info">
                    <table style="width: 100%; font-size: 11px;">
                        <tr>
                            <td class="b">Order Number:</td>
                            <td>{{ $sales_order->so_number }}</td>
                        </tr>
                        <tr>
                            <td class="b">Order Date:</td>
                            <td>{{ $sales_order->order_date }}</td>
                        </tr>
                        <tr>
                            <td class="b">Status:</td>
                            <td>
                                <span class="status-badge status-{{ strtolower($sales_order->status->name ?? 'pending') }}">
                                    {{ strtoupper($sales_order->status->name ?? 'PENDING') }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="b">Payment Terms:</td>
                            <td>{{ $sales_order->payment_terms ?? 'Net 30' }}</td>
                        </tr>
                        @if($sales_order->delivery_date)
                        <tr>
                            <td class="b">Delivery Date:</td>
                            <td>{{ \Carbon\Carbon::parse($sales_order->delivery_date)->format('F d, Y') }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Customer Information -->
        <div class="two-columns">
            <div class="column">
                <div class="section">
                    <div class="section-title">BILL TO</div>
                    <table class="info-table">
                        <tr>
                            <td class="label">Customer:</td>
                            <td>{{ $sales_order->customer->name ?? $sales_order->customer_name }}</td>
                        </tr>
                        @if($sales_order->customer->contact_person ?? $sales_order->contact_person)
                        <tr>
                            <td class="label">Attn:</td>
                            <td>{{ $sales_order->customer->contact_person ?? $sales_order->contact_person }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td class="label">Address:</td>
                            <td>
                                @if($sales_order->customer->address ?? $sales_order->billing_address)
                                    {{ $sales_order->customer->address ?? $sales_order->billing_address }}<br>
                                    {{ $sales_order->customer->city ?? '' }} 
                                    {{ $sales_order->customer->state ?? '' }} 
                                    {{ $sales_order->customer->zip_code ?? '' }}
                                @else
                                    Address not specified
                                @endif
                            </td>
                        </tr>
                        @if($sales_order->customer->phone ?? $sales_order->phone)
                        <tr>
                            <td class="label">Phone:</td>
                            <td>{{ $sales_order->customer->phone ?? $sales_order->phone }}</td>
                        </tr>
                        @endif
                        @if($sales_order->customer->email ?? $sales_order->email)
                        <tr>
                            <td class="label">Email:</td>
                            <td>{{ $sales_order->customer->email ?? $sales_order->email }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>
            
            <div class="column">
                <div class="section">
                    <div class="section-title">SHIP TO</div>
                    <table class="info-table">
                        @if($sales_order->shipping_address)
                        <tr>
                            <td class="label">Company:</td>
                            <td>{{ $sales_order->shipping_company ?? ($sales_order->customer->name ?? 'Same as Billing') }}</td>
                        </tr>
                        @if($sales_order->shipping_contact)
                        <tr>
                            <td class="label">Attn:</td>
                            <td>{{ $sales_order->shipping_contact }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td class="label">Address:</td>
                            <td>
                                {{ $sales_order->shipping_address ?? 'Same as billing address' }}
                                @if($sales_order->shipping_city)
                                <br>{{ $sales_order->shipping_city }}, {{ $sales_order->shipping_state }} {{ $sales_order->shipping_zip }}
                                @endif
                            </td>
                        </tr>
                        @if($sales_order->shipping_phone)
                        <tr>
                            <td class="label">Phone:</td>
                            <td>{{ $sales_order->shipping_phone }}</td>
                        </tr>
                        @endif
                        @else
                        <tr>
                            <td colspan="2" style="text-align: center; padding: 20px;">
                                <em>Same as billing address</em>
                            </td>
                        </tr>
                        @endif
                        @if($sales_order->delivery_date)
                        <tr>
                            <td class="label">Delivery Date:</td>
                            <td class="b">{{ \Carbon\Carbon::parse($sales_order->delivery_date)->format('F d, Y') }}</td>
                        </tr>
                        @endif
                        @if($sales_order->shipping_method)
                        <tr>
                            <td class="label">Shipping Method:</td>
                            <td>{{ $sales_order->shipping_method }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Order Items -->
        <div class="section">
            <div class="section-title">ORDER ITEMS</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th width="5%">#</th>
                        <th width="15%">Brand</th>
                        <th width="10%">QTY</th>
                        <th width="15%">UNIT PRICE</th>
                        <th width="10%">DISCOUNT</th>
                        <th width="15%">TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $subtotal = 0;
                        $itemCount = count($items);
                    @endphp
                    
                    @foreach($items as $index => $item)
                        @php
                            // Calculate item totals based on your model structure
                            $unitPrice = $item->unit_price ?? $item->price ?? 0;
                            $quantity = $item->quantity ?? 1;
                            $discountPercent = $item->discount_percent ?? $item->discount ?? 0;
                            
                            $itemTotal = $unitPrice * $quantity;
                            $itemDiscount = ($discountPercent / 100) * $itemTotal;
                            $itemNet = $itemTotal - $itemDiscount;
                            $subtotal += $itemNet;
                        @endphp
                        <tr>
                            <td class="text-center">{{ $index + 1 }}</td>
                            <td class="text-center">{{ $item->brand }}</td>
                            <td class="text-left">{{ $item->product_name ?? $item->description ?? 'Item ' . ($index + 1) }}</td>
                            <td class="text-center">{{ $quantity }}</td>
                            <td class="text-right">${{ number_format($unitPrice, 2) }}</td>
                            <td class="text-center">{{ $discountPercent > 0 ? number_format($discountPercent, 1).'%' : '-' }}</td>
                            <td class="text-right">${{ number_format($itemNet, 2) }}</td>
                        </tr>
                    @endforeach
                    
                    <!-- Empty rows if less than minimum items -->
                    @if($itemCount < 8)
                        @for($i = $itemCount; $i < 8; $i++)
                            <tr>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                            </tr>
                        @endfor
                    @endif
                </tbody>
            </table>
        </div>
        
        <!-- Totals Section -->
        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td class="label">Subtotal:</td>
                    <td class="amount">${{ number_format($subtotal, 2) }}</td>
                </tr>
                
                @php
                    $taxRate = $sales_order->tax_rate ?? 0;
                    $taxAmount = $sales_order->tax_amount ?? ($subtotal * $taxRate / 100);
                @endphp
                
                @if($taxRate > 0)
                <tr>
                    <td class="label">Tax ({{ number_format($taxRate, 2) }}%):</td>
                    <td class="amount">${{ number_format($taxAmount, 2) }}</td>
                </tr>
                @endif
                
                @php
                    $shippingCost = $sales_order->shipping_cost ?? 0;
                    $orderDiscount = $sales_order->order_discount ?? 0;
                    $otherCharges = $sales_order->other_charges ?? 0;
                @endphp
                
                @if($shippingCost > 0)
                <tr>
                    <td class="label">Shipping:</td>
                    <td class="amount">${{ number_format($shippingCost, 2) }}</td>
                </tr>
                @endif
                
                @if($otherCharges > 0)
                <tr>
                    <td class="label">Other Charges:</td>
                    <td class="amount">${{ number_format($otherCharges, 2) }}</td>
                </tr>
                @endif
                
                @if($orderDiscount > 0)
                <tr>
                    <td class="label">Order Discount:</td>
                    <td class="amount">-${{ number_format($orderDiscount, 2) }}</td>
                </tr>
                @endif
                
                @php
                    $grandTotal = $subtotal + $taxAmount + $shippingCost + $otherCharges - $orderDiscount;
                @endphp
                
                <tr class="grand-total">
                    <td class="label">GRAND TOTAL:</td>
                    <td class="amount">${{ number_format($grandTotal, 2) }}</td>
                </tr>
            </table>
            
            @if($sales_order->amount_paid > 0)
            <table class="totals-table" style="margin-top: 10px;">
                <tr>
                    <td class="label">Amount Paid:</td>
                    <td class="amount">${{ number_format($sales_order->amount_paid, 2) }}</td>
                </tr>
                <tr class="grand-total">
                    <td class="label">BALANCE DUE:</td>
                    <td class="amount">${{ number_format($grandTotal - $sales_order->amount_paid, 2) }}</td>
                </tr>
            </table>
            @endif
        </div>
        
        <div class="clearfix"></div>
        
        <!-- Notes -->
        @if($sales_order->notes)
        <div class="section mt-20">
            <div class="section-title">NOTES</div>
            <div style="padding: 10px; border: 1px solid #ddd; min-height: 50px; font-size: 11px;">
                {!! nl2br(e($sales_order->notes)) !!}
            </div>
        </div>
        @endif
        
        @if($sales_order->terms_conditions)
        <div class="section mt-10">
            <div class="section-title">SPECIAL INSTRUCTIONS</div>
            <div style="padding: 10px; border: 1px solid #ddd; min-height: 50px; font-size: 11px;">
                {!! nl2br(e($sales_order->terms_conditions)) !!}
            </div>
        </div>
        @endif
        
        <!-- Terms & Conditions -->
        <div class="footer">
            @if(!$sales_order->terms_conditions)
            <div class="terms-conditions">
                <div class="section-title">TERMS & CONDITIONS</div>
                <div style="font-size: 9px; line-height: 1.3;">
                    1. Payment is due within {{ $sales_order->payment_terms ?? '30' }} days of invoice date.<br>
                    2. 2% discount will apply if payment is made within 10 days.<br>
                    3. Prices are valid for 30 days from the order date.<br>
                    4. Returns accepted within 15 days with original packaging.<br>
                    5. Late payments are subject to 1.5% monthly interest.<br>
                    6. All disputes subject to jurisdiction of State courts.<br>
                </div>
            </div>
            @endif
            
            <!-- Signatures -->
            <div class="signature-section clearfix">
                <div class="signature-box">
                    <div class="b">Authorized Signature</div>
                    <div style="font-size: 9px; margin-top: 5px;">Customer</div>
                </div>
                
                <div class="signature-box">
                    <div class="b">Authorized Signature</div>
                    <div style="font-size: 9px; margin-top: 5px;">{{ config('app.company_name', 'Your Company') }}</div>
                </div>
                
                <div style="clear: both;"></div>
            </div>
            
            <!-- Footer notes -->
            <div class="text-center mt-20">
                <div style="border-top: 1px solid #ddd; padding-top: 10px; font-size: 9px; color: #777;">
                    This is a computer-generated document. No signature required for validation.<br>
                    Sales Order #{{ $sales_order->so_number }} | 
                    Generated on: {{ \Carbon\Carbon::now()->format('F d, Y h:i A') }}
                </div>
            </div>
        </div>
    </div>
</body>
</html>