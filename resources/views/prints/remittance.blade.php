<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remittance #{{ $remittance->remittance_no }}</title>
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
                <div class="title">REMITTANCE</div>
                <div class="document-info">
                    <table style="width: 100%; font-size: 11px;">
                        <tr>
                            <td class="b">Remittance Number:</td>
                            <td>{{ $remittance->remittance_no }}</td>
                        </tr>
                        <tr>
                            <td class="b">Remittance Date:</td>
                            <td>{{ $remittance->remittance_date }}</td>
                        </tr>
                        <tr>
                            <td class="b">Status:</td>
                            <td>
                                <span class="status-badge status-{{ strtolower($remittance->status->name ?? 'pending') }}">
                                    {{ strtoupper($remittance->status->name ?? 'PENDING') }}
                                </span>
                            </td>
                        </tr>
                        @if($remittance->approved_at)
                        <tr>
                            <td class="b">Approved Date:</td>
                            <td>{{ \Carbon\Carbon::parse($remittance->approved_at)->format('F d, Y') }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>

        <!-- Remittance Information -->
        <div class="two-columns">
            <div class="column">
                <div class="section">
                    <div class="section-title">REMITTANCE DETAILS</div>
                    <table class="info-table">
                        <tr>
                            <td class="label">Created By:</td>
                            <td>{{ $remittance->createdBy->username ?? 'N/A' }}</td>
                        </tr>
                        @if($remittance->approved_by)
                        <tr>
                            <td class="label">Approved By:</td>
                            <td>{{ $remittance->approvedBy->username }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td class="label">Total Amount:</td>
                            <td>₱{{ number_format($remittance->total_amount ?? 0, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="label">Total Receipts:</td>
                            <td>{{ count($receipts) }}</td>
                        </tr>
                        @if($remittance->remarks)
                        <tr>
                            <td class="label">Remarks:</td>
                            <td>{{ $remittance->remarks }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>

            <div class="column">
                <div class="section">
                    <div class="section-title">SUMMARY</div>
                    <div style="padding: 10px; border: 1px solid #ddd; min-height: 100px;">
                        @if(is_array($remittance->summary) && count($remittance->summary) > 0)
                            <table class="info-table" style="margin: 0;">
                                @foreach($remittance->summary as $key => $value)
                                    <tr>
                                        <td class="label" style="width: 50%;">{{ ucwords(str_replace('_', ' ', $key)) }}:</td>
                                        <td>₱{{ number_format($value, 2) }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        @elseif(is_object($remittance->summary) && count((array)$remittance->summary) > 0)
                            <table class="info-table" style="margin: 0;">
                                @foreach($remittance->summary as $key => $value)
                                    <tr>
                                        <td class="label" style="width: 50%;">{{ ucwords(str_replace('_', ' ', $key)) }}:</td>
                                        <td>₱{{ number_format($value, 2) }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        @else
                            <p style="margin: 0;">{{ $remittance->summary ?? 'No summary available' }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Receipts List -->
        <div class="section">
            <div class="section-title">RECEIPTS INCLUDED</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th width="5%">#</th>
                        <th width="15%">Receipt No.</th>
                        <th width="20%">Customer</th>
                        <th width="15%">Amount</th>
                        <th width="15%">Date</th>
                        <th width="15%">Payment Mode</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $totalAmount = 0;
                        $itemCount = count($receipts);
                    @endphp

                    @foreach($receipts as $index => $receipt)
                        @php
                            $totalAmount += $receipt->amount_paid ?? 0;
                        @endphp
                        <tr>
                            <td class="text-center">{{ $index + 1 }}</td>
                            <td class="text-center">{{ $receipt->receipt_number ?? '-' }}</td>
                            <td class="text-left">{{ $receipt->customer->name ?? '-' }}</td>
                            <td class="text-right">₱{{ number_format($receipt->amount_paid ?? 0, 2) }}</td>
                            <td class="text-center">{{ $receipt->receipt_date ?? '-' }}</td>
                            <td class="text-center">{{ $receipt->payment_mode ?? '-' }}</td>
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
                    <td class="label">Total Receipts:</td>
                    <td class="amount">{{ count($receipts) }}</td>
                </tr>
                <tr class="grand-total">
                    <td class="label">TOTAL AMOUNT:</td>
                    <td class="amount">₱{{ number_format($totalAmount, 2) }}</td>
                </tr>
            </table>
        </div>

        <div class="clearfix"></div>

        <!-- Footer -->
        <div class="footer">
            <div class="terms-conditions">
                <div class="section-title">REMITTANCE NOTES</div>
                <div style="font-size: 9px; line-height: 1.3;">
                    This remittance represents the collection of payments from the listed receipts.<br>
                    All receipts have been verified and processed according to company procedures.<br>
                    This document serves as official record of the remittance transaction.
                </div>
            </div>

            <!-- Signatures -->
            <div class="signature-section clearfix">
                <div class="signature-box">
                    <div class="b">Prepared By</div>
                    <div style="font-size: 9px; margin-top: 5px;">{{ $remittance->createdBy->username ?? 'N/A' }}</div>
                </div>

                @if($remittance->approved_by)
                <div class="signature-box">
                    <div class="b">Approved By</div>
                    <div style="font-size: 9px; margin-top: 5px;">{{ $remittance->approvedBy->username }}</div>
                </div>
                @else
                <div class="signature-box">
                    <div class="b">Authorized Signature</div>
                    <div style="font-size: 9px; margin-top: 5px;">{{ config('app.company_name', 'Your Company') }}</div>
                </div>
                @endif

                <div style="clear: both;"></div>
            </div>

            <!-- Footer notes -->
            <div class="text-center mt-20">
                <div style="border-top: 1px solid #ddd; padding-top: 10px; font-size: 9px; color: #777;">
                    This is a computer-generated document.<br>
                    Remittance #{{ $remittance->remittance_no }} |
                    Generated on: {{ \Carbon\Carbon::now()->format('F d, Y h:i A') }}
                </div>
            </div>
        </div>
    </div>
</body>
</html>
