<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AR Invoice - {{ $ar_invoice->invoice_number }}</title>
    <style>
        @page { size: 215.9mm 330.2mm; margin: 6mm 10mm; }
        body { margin: 0; padding: 0; }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.3;
        }

        /* Two-copies-per-sheet layout, cut in half with scissors — the same
           design the sales order print uses. The first copy is given exactly
           half the printable height and carries the cut line on its own bottom
           edge (long bond is 330.2mm; less 6mm margin at each end, half of what
           is left is 159.1mm, and 6mm + 159.1mm = 165.1mm, the middle of the
           paper). Invoices too long to share a sheet start the second copy on a
           new sheet so neither copy is split across the cut. */
        .copy-cell { width: 100%; }
        .half-sheet-top { height: 159.1mm; box-sizing: border-box; border-bottom: 1px dashed #999; }
        .half-sheet-bottom { height: 158mm; box-sizing: border-box; }
        .single-copy { height: 316mm; box-sizing: border-box; }
        .new-sheet { page-break-before: always; }
        .keep-whole { page-break-inside: avoid; }

        /* The copy's inner table carries a definite height so DomPDF pushes the
           foot row (signatures) to the bottom. */
        .copy-layout { width: 100%; border-collapse: collapse; }
        .copy-body { vertical-align: top; }
        .copy-foot { vertical-align: bottom; }
        .copy-layout-half .copy-body { height: 140mm; }
        .copy-layout-full .copy-body { height: 290mm; }

        /* Header */
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        /* Centred across the top, in place of the letterhead. */
        .doc-title { text-align: center; font-size: 15px; font-weight: bold; margin: 0 0 4px 0; }
        .doc-title-meta { font-size: 12px; font-weight: normal; }
        .order-info { text-align: right; font-size: 12px; }

        /* Address & Total Section */
        .summary-container { width: 100%; display: table; margin-bottom: 3px; }
        .address-block { display: table-cell; width: 24%; vertical-align: top; }
        .crew-block { padding-right: 8px; }
        .crew-line { font-size: 11px; padding: 1px 0; }
        .total-block {
            display: table-cell;
            width: 28%;
            background-color: #E5E7E9;
            padding: 6px;
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
            margin-bottom: 4px;
            background-color: #D5DBDB;
            border: 1px solid #BDC3C7;
        }
        .meta-table th { font-size: 10px; padding: 1px 3px; text-align: center; width: 20%; }
        .meta-table td { background-color: white; text-align: center; padding: 1px 4px; border: 1px solid #BDC3C7; }

        /* Items Table — the items print a size up from everything around them. */
        .items-table { width: 100%; border-collapse: collapse; }
        .items-table th { background-color: #D5DBDB; border: 1px solid #BDC3C7; padding: 1px 5px; text-align: left; font-size: 13px; }
        .items-table td { padding: 1px 5px; border-bottom: 1px solid #eee; font-size: 15px; }
        .items-table .qty-col { width: 60px; text-align: center; }
        .text-right { text-align: right; }
        .items-table .text-center { text-align: center; }
        /* A longer invoice (13–15 items) on a shared half-sheet tightens the
           rows a step so the extra lines still clear the cut. */
        .items-compact th { font-size: 12px; }
        .items-compact td { font-size: 13px; line-height: 1.15; }

        /* Footer */
        .footer-table { width: 100%; margin-top: 3px; }
        .grand-total-box { background-color: #D5DBDB; padding: 4px; font-weight: bold; }

        /* Signature Section — sits in the copy's bottom-aligned foot row. */
        .signature-section { width: 100%; display: table; }
        .signature-box { display: table-cell; text-align: center; width: 50%; }
        .signature-line { border-bottom: 1px solid #333; margin-bottom: 4px; padding-top: 8px; padding-bottom: 4px; }

        /* Copy label */
        .copy-label {
            display: inline-block;
            font-size: 10px;
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
        // Same rule as the sales order print: up to 15 item rows share a sheet
        // (two copies, cut in half); 13–15 switch to tighter rows; 16+ take a
        // sheet each so no copy is cut through.
        $copiesShareSheet = count($items) <= 15;
        $compactItems = $copiesShareSheet && count($items) > 12;
        $layoutClass = $copiesShareSheet ? 'copy-layout-half' : 'copy-layout-full';
    @endphp

    <div class="copy-cell {{ $copiesShareSheet ? 'half-sheet-top' : 'single-copy' }}">
        @include('prints.partials.ar-invoice-copy', ['copyLabel' => 'Customer Copy', 'layoutClass' => $layoutClass, 'compact' => $compactItems])
    </div>

    <div class="copy-cell {{ $copiesShareSheet ? 'half-sheet-bottom keep-whole' : 'single-copy new-sheet' }}">
        @include('prints.partials.ar-invoice-copy', ['copyLabel' => 'BRT Copy', 'layoutClass' => $layoutClass, 'compact' => $compactItems])
    </div>

</body>
</html>
