<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>
        {{ ($receipt->receipt_type ?? 'payment') === 'updated' ? 'Updated Receipt' : (($receipt->receipt_type ?? 'payment') === 'refund' ? 'Refund Receipt' : 'Receipt') }} - {{ $receipt->receipt_number ?? 'N/A' }}
    </title>
    <style>
        @page { size: A4 portrait; margin: 6mm 10mm; }
        body { margin: 0; padding: 0; }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 9px;
            color: #333;
            line-height: 1.3;
        }

        /* Two-copies-per-sheet layout — each copy takes only the height its content needs
           (no forced fixed height), so short documents don't leave a large dead gap before
           the cut line. Content that's too long for one page simply flows onto a second
           page rather than being clipped. */
        .copy-cell { width: 100%; }
        .cut-line { width: 100%; margin: 14mm 0; border-top: 1px dashed #999; }

        /* Header */
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .logo-box {
            width: 45px;
            height: 45px;
            border-radius: 4px;
            text-align: center;
            vertical-align: middle;
        }
        .logo-img {
            width: 45px;
            height: 45px;
            object-fit: contain;
        }
        .logo-fallback {
            width: 45px;
            height: 45px;
            background-color: #C0392B;
            border-radius: 4px;
        }
        .company-name { font-size: 15px; font-weight: bold; color: #1a1a1a; margin: 0; }
        .order-title { font-size: 19px; font-weight: bold; text-align: right; margin: 0 0 6px 0; }
        .order-info { text-align: right; font-size: 10px; }
        .order-meta-table { width: 100%; border-collapse: collapse; }
        .order-meta-label {
            font-size: 8px;
            font-weight: bold;
            text-align: right;
            border-bottom: 1px solid #333;
            padding-bottom: 2px;
        }
        .order-meta-value { text-align: right; font-size: 10px; padding: 2px 0 5px 0; }

        /* Address & Total Section */
        .summary-container { width: 100%; display: table; margin-bottom: 10px; }
        .address-block { display: table-cell; width: 33%; vertical-align: top; }
        .total-block {
            display: table-cell;
            width: 34%;
            background-color: #E5E7E9;
            padding: 10px;
            text-align: right;
            vertical-align: middle;
        }
        .address-label {
            font-weight: bold;
            border-bottom: 1px solid #ccc;
            margin-bottom: 4px;
            display: block;
            width: 90%;
        }

        /* Metadata Bar */
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            background-color: #D5DBDB;
            border: 1px solid #BDC3C7;
        }
        .meta-table th { font-size: 8px; padding: 3px; text-align: center; width: 20%; }
        .meta-table td { background-color: white; text-align: center; padding: 4px; border: 1px solid #BDC3C7; }

        /* Items Table */
        .items-table { width: 100%; border-collapse: collapse; }
        .items-table th { background-color: #D5DBDB; border: 1px solid #BDC3C7; padding: 5px; text-align: left; }
        .items-table td { padding: 5px; border-bottom: 1px solid #eee; }
        .text-right { text-align: right; }

        /* Footer */
        .footer-table { width: 100%; margin-top: 14px; }
        .grand-total-box { background-color: #D5DBDB; padding: 7px; font-weight: bold; }
        .sales-rep-box {
            display: inline-block;
            border: 1px solid #BDC3C7;
            padding: 5px 9px;
        }

        /* Signature Section */
        .signature-section { width: 100%; margin-top: 16px; display: table; }
        .signature-box { display: table-cell; text-align: center; width: 50%; }
        .signature-line { border-bottom: 1px solid #333; margin-bottom: 4px; padding-top: 24px; padding-bottom: 4px; }

        /* Copy label */
        .copy-label {
            display: inline-block;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #fff;
            background-color: #7f8c8d;
            padding: 2px 7px;
            border-radius: 3px;
            margin-bottom: 4px;
        }
    </style>
</head>
<body>
    @php
        $customer = $receipt->customer ?? optional($sales_order)->customer;
        $receiptNumber = $receipt->receipt_number ?? '---';
        $receiptType = $receipt->receipt_type ?? 'payment';
        $isRefundReceipt = $receiptType === 'refund';
        $isUpdatedReceipt = $receiptType === 'updated';
        $receiptDate = $receipt->receipt_date ? \Carbon\Carbon::parse($receipt->receipt_date)->format('m/d/Y') : '-';
        $invoiceDate = optional($ar_invoice)->invoice_date ? \Carbon\Carbon::parse($ar_invoice->invoice_date)->format('m/d/Y') : '-';
        $orderDate = optional($sales_order)->order_date ? \Carbon\Carbon::parse($sales_order->order_date)->format('m/d/Y') : '-';
        $dueDate = optional($sales_order)->due_date ? \Carbon\Carbon::parse($sales_order->due_date)->format('m/d/Y') : '-';
        $balanceDue = (float) (optional($ar_invoice)->balance_due ?? ($receipt->balance_due ?? 0));
        $displayAmount = (float) ($display_amount ?? $receipt->amount_paid ?? 0);
        $itemsCollection = collect($items ?? []);
        $logoPath = public_path('images/official-logo-mini.png');
    @endphp

    <div class="copy-cell">
        @include('prints.partials.receipt-copy', ['copyLabel' => 'Customer Copy'])
    </div>

    <div class="cut-line"></div>

    <div class="copy-cell">
        @include('prints.partials.receipt-copy', ['copyLabel' => 'BRT Copy'])
    </div>

</body>
</html>
