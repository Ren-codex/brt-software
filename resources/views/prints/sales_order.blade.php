<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @page { size: A4 portrait; margin: 6mm 10mm; }
        body { margin: 0; padding: 0; }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.3;
        }

        /* Two-copies-per-sheet layout. The sheet is cut in half with scissors, so
           the first copy is given exactly half the printable height and carries
           the cut line on its own bottom edge: A4 is 297mm, the page margin takes
           6mm off each end, so half of what is left is 142.5mm — and 6mm + 142.5mm
           is 148.5mm, the middle of the paper itself. A separate spacer element
           for the line would add its own height and move the cut off centre.

           Orders too long to share a sheet start the second copy on a new sheet
           (.new-sheet) so neither copy is split across the cut; those are not
           halved, since there is nothing to cut. */
        .copy-cell { width: 100%; }
        /* Only the top copy is measured. Giving the bottom one a height too
           made the pair a fraction taller than the page once the dashed border
           was counted, and it fell onto a second sheet. */
        .half-sheet-top { height: 142.5mm; box-sizing: border-box; border-bottom: 1px dashed #999; }
        .new-sheet { page-break-before: always; }
        .keep-whole { page-break-inside: avoid; }

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
        .company-name { font-size: 17px; font-weight: bold; color: #1a1a1a; margin: 0; }
        .order-title { font-size: 22px; font-weight: bold; text-align: right; margin: 0 0 6px 0; white-space: nowrap; }
        .order-info { text-align: right; font-size: 12px; }
        .order-meta-table { width: 100%; border-collapse: collapse; }
        .order-meta-label {
            font-size: 10px;
            font-weight: bold;
            text-align: right;
            border-bottom: 1px solid #333;
            padding-bottom: 2px;
        }
        .order-meta-value { text-align: right; font-size: 12px; padding: 2px 0 5px 0; }

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
        .meta-table th { font-size: 10px; padding: 3px; text-align: center; width: 20%; }
        .meta-table td { background-color: white; text-align: center; padding: 4px; border: 1px solid #BDC3C7; }

        /* Items Table */
        .items-table { width: 100%; border-collapse: collapse; }
        .items-table th { background-color: #D5DBDB; border: 1px solid #BDC3C7; padding: 5px; text-align: left; }
        .items-table td { padding: 5px; border-bottom: 1px solid #eee; }
        .text-right { text-align: right; }
        .items-table .text-center { text-align: center; }

        /* Footer */
        .footer-table { width: 100%; margin-top: 14px; }
        .grand-total-box { background-color: #D5DBDB; padding: 7px; font-weight: bold; }
        .sales-rep-box {
            display: inline-block;
            border: 1px solid #BDC3C7;
            padding: 5px 9px;
        }

        /* Signature Section */
        .signature-section { width: 100%; margin-top: 8px; display: table; }
        .signature-box { display: table-cell; text-align: center; width: 50%; }
        .signature-line { border-bottom: 1px solid #333; margin-bottom: 4px; padding-top: 24px; padding-bottom: 4px; }

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
        $logoPath = public_path('images/official-logo-mini.png');
        // Both copies fit on one A4 sheet up to 3 items; past that the second copy
        // would split across sheets, so each copy gets its own sheet instead.
        $copiesShareSheet = count($items) <= 3;
    @endphp

    <div class="copy-cell {{ $copiesShareSheet ? 'half-sheet-top' : '' }}">
        @include('prints.partials.sales-order-copy', ['copyLabel' => 'Customer Copy'])
    </div>

    <div class="copy-cell {{ $copiesShareSheet ? 'keep-whole' : 'new-sheet' }}">
        @include('prints.partials.sales-order-copy', ['copyLabel' => 'BRT Copy'])
    </div>

</body>
</html>
