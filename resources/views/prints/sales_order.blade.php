<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @page { size: 215.9mm 330.2mm; margin: 6mm 10mm; }
        body { margin: 0; padding: 0; }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.3;
        }

        /* Two-copies-per-sheet layout. The sheet is cut in half with scissors, so
           the first copy is given exactly half the printable height and carries
           the cut line on its own bottom edge: long bond is 330.2mm tall, the
           page margin takes 6mm off each end, so half of what is left is
           159.1mm — and 6mm + 159.1mm is 165.1mm, the middle of the paper
           itself. A separate spacer element for the line would add its own
           height and move the cut off centre.

           Orders too long to share a sheet start the second copy on a new sheet
           (.new-sheet) so neither copy is split across the cut; those are not
           halved, since there is nothing to cut. */
        .copy-cell { width: 100%; }
        /* Only the top copy is measured. Giving the bottom one a height too
           made the pair a fraction taller than the page once the dashed border
           was counted, and it fell onto a second sheet. */
        .half-sheet-top { height: 159.1mm; box-sizing: border-box; border-bottom: 1px dashed #999; }
        .new-sheet { page-break-before: always; }
        .keep-whole { page-break-inside: avoid; }

        /* Header */
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        /* Centred across the top, in place of the letterhead. */
        .doc-title { text-align: center; font-size: 18px; font-weight: bold; margin: 0 0 8px 0; }
        .doc-title-meta { font-size: 12px; font-weight: normal; }
        .order-info { text-align: right; font-size: 12px; }
        .order-meta-table { width: 100%; border-collapse: collapse; }
        /* No rule under the label: it used to underline a 150px column beside
           the letterhead, and with that column gone it drew a line across the
           whole page. */
        .order-meta-label {
            font-size: 10px;
            font-weight: bold;
            text-align: right;
            padding-bottom: 2px;
        }
        .order-meta-value { text-align: right; font-size: 12px; padding: 2px 0 5px 0; }

        /* Address & Total Section */
        .summary-container { width: 100%; display: table; margin-bottom: 6px; }
        .address-block { display: table-cell; width: 24%; vertical-align: top; }
        /* The crew reads as a list, not as the boxes it wore at the foot of
           the page: three names in a column beside two addresses. */
        .crew-block { padding-right: 8px; }
        .crew-line { font-size: 11px; padding: 1px 0; }
        .total-block {
            display: table-cell;
            width: 28%;
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
        .meta-table th { font-size: 10px; padding: 2px 3px; text-align: center; width: 20%; }
        .meta-table td { background-color: white; text-align: center; padding: 2px 4px; border: 1px solid #BDC3C7; }

        /* Items Table */
        .items-table { width: 100%; border-collapse: collapse; }
        /* 3px rather than 5px a side: ten rows need about 9mm that the old
           padding was spending, and the row still clears the text. */
        /* The items are what the warehouse and the customer actually read off
           this page, so they print a size up from everything around them. */
        .items-table th { background-color: #D5DBDB; border: 1px solid #BDC3C7; padding: 4px 5px; text-align: left; font-size: 15px; }
        .items-table td { padding: 4px 5px; border-bottom: 1px solid #eee; font-size: 15px; }
        /* Narrow and centred, sitting against the packaging it belongs to:
           "3 Sack" reads as one fact, not a number adrift in a wide column. */
        .items-table .qty-col { width: 60px; text-align: center; }
        .text-right { text-align: right; }
        .items-table .text-center { text-align: center; }

        /* Footer */
        .footer-table { width: 100%; margin-top: 8px; }
        .grand-total-box { background-color: #D5DBDB; padding: 7px; font-weight: bold; }

        /* Signature Section */
        .signature-section { width: 100%; margin-top: 8px; display: table; }
        .signature-box { display: table-cell; text-align: center; width: 50%; }
        .signature-line { border-bottom: 1px solid #333; margin-bottom: 4px; padding-top: 12px; padding-bottom: 4px; }

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
        // Measured off the rendered PDF on long bond: one copy stands 150.0mm
        // Measured in a browser at long-bond width, with the items at 15px:
        // eight rows stand 154.7mm against the 159.1mm half-sheet, 4.4mm clear
        // of the cut. A ninth lands on 162.2mm, over the line, so nine and up
        // get a sheet each instead of being cut through.
        $copiesShareSheet = count($items) <= 8;
    @endphp

    <div class="copy-cell {{ $copiesShareSheet ? 'half-sheet-top' : '' }}">
        @include('prints.partials.sales-order-copy', ['copyLabel' => 'Customer Copy'])
    </div>

    <div class="copy-cell {{ $copiesShareSheet ? 'keep-whole' : 'new-sheet' }}">
        @include('prints.partials.sales-order-copy', ['copyLabel' => 'BRT Copy'])
    </div>

</body>
</html>
