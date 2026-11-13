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
        .half-sheet-top { height: 159.1mm; box-sizing: border-box; border-bottom: 1px dashed #999; }
        /* A hair under 159.1mm leaves room for rounding so the pair never spills
           past the dashed cut onto a second sheet. */
        .half-sheet-bottom { height: 158mm; box-sizing: border-box; }
        /* A copy that owns a whole sheet (a long order) fills the printable page. */
        .single-copy { height: 316mm; box-sizing: border-box; }
        .new-sheet { page-break-before: always; }
        .keep-whole { page-break-inside: avoid; }

        /* The copy's inner table carries a definite height so DomPDF pushes the
           foot row (signatures) to the bottom. A little under each copy's own
           height leaves a clear gap above the cut / page edge. A long item list
           simply grows the body row and the signatures ride down with it. */
        .copy-layout { width: 100%; border-collapse: collapse; }
        .copy-body { vertical-align: top; }
        .copy-foot { vertical-align: bottom; }
        /* The height goes on the body cell — DomPDF honours a cell height where
           it ignores a table height — so the body stretches and the foot row
           (signatures) drops to just above the cut / page edge. */
        .copy-layout-half .copy-body { height: 140mm; }
        .copy-layout-full .copy-body { height: 290mm; }

        /* Header */
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        /* Centred across the top, in place of the letterhead. */
        .doc-title { text-align: center; font-size: 15px; font-weight: bold; margin: 0 0 4px 0; }
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
        .summary-container { width: 100%; display: table; margin-bottom: 3px; }
        .address-block { display: table-cell; width: 24%; vertical-align: top; }
        /* The crew reads as a list, not as the boxes it wore at the foot of
           the page: three names in a column beside two addresses. */
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

        /* Items Table */
        .items-table { width: 100%; border-collapse: collapse; }
        /* 3px rather than 5px a side: ten rows need about 9mm that the old
           padding was spending, and the row still clears the text. */
        /* The items are what the warehouse and the customer actually read off
           this page, so they print a size up from everything around them. */
        .items-table th { background-color: #D5DBDB; border: 1px solid #BDC3C7; padding: 1px 5px; text-align: left; font-size: 13px; }
        /* The items are what the warehouse and the customer read off the page,
           so they stay at a readable 15px even though everything around them is
           compact; the row padding is kept tight so more still fit per sheet. */
        .items-table td { padding: 1px 5px; border-bottom: 1px solid #eee; font-size: 15px; }
        /* Narrow and centred, sitting against the packaging it belongs to:
           "3 Sack" reads as one fact, not a number adrift in a wide column. */
        .items-table .qty-col { width: 60px; text-align: center; }
        .text-right { text-align: right; }
        .items-table .text-center { text-align: center; }
        /* A longer order (13–15 items) on a shared half-sheet: tighten the rows
           a step so the extra lines still clear the cut. Twelve or fewer keep
           the larger 15px size above. */
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
        // With the signatures pinned to the foot of each copy, the body is given
        // a 140mm cell on a shared sheet. Up to twelve items print at the larger,
        // more readable 15px size. A longer list (13–15) switches to tighter 13px
        // rows, which take about the same height twelve of the big rows did — so
        // the signatures still land clear of the 165mm cut. Sixteen and up no
        // longer fit a half-sheet, so each copy takes its own sheet.
        $copiesShareSheet = count($items) <= 15;
        $compactItems = $copiesShareSheet && count($items) > 12;
    @endphp

    @php $layoutClass = $copiesShareSheet ? 'copy-layout-half' : 'copy-layout-full'; @endphp

    <div class="copy-cell {{ $copiesShareSheet ? 'half-sheet-top' : 'single-copy' }}">
        @include('prints.partials.sales-order-copy', ['copyLabel' => 'Customer Copy', 'layoutClass' => $layoutClass, 'compact' => $compactItems])
    </div>

    <div class="copy-cell {{ $copiesShareSheet ? 'half-sheet-bottom keep-whole' : 'single-copy new-sheet' }}">
        @include('prints.partials.sales-order-copy', ['copyLabel' => 'BRT Copy', 'layoutClass' => $layoutClass, 'compact' => $compactItems])
    </div>

</body>
</html>
