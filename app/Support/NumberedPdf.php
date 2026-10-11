<?php

namespace App\Support;

use Barryvdh\DomPDF\PDF;

/**
 * The PDF wrapper every print goes through, with "Page 1 of 2" stamped in the
 * bottom-right corner of each page.
 *
 * Done here rather than in each Blade view because the total page count is
 * only known once the whole document has been laid out, and because one place
 * covers every print the system has — and any added later — without each view
 * having to remember it.
 */
class NumberedPdf extends PDF
{
    private const FONT_SIZE = 8;

    /** Points in from the right edge and up from the bottom edge. */
    private const MARGIN_RIGHT = 24;

    private const MARGIN_BOTTOM = 22;

    public function render(): void
    {
        parent::render();

        $canvas = $this->dompdf->getCanvas();
        $metrics = $this->dompdf->getFontMetrics();
        $font = $metrics->getFont('DejaVu Sans', 'normal');

        // Measured on a two-digit sample so the text ends at the margin for
        // any document up to 99 pages, rather than running off the sheet.
        $width = $metrics->getTextWidth('Page 00 of 00', $font, self::FONT_SIZE);

        $canvas->page_text(
            $canvas->get_width() - self::MARGIN_RIGHT - $width,
            $canvas->get_height() - self::MARGIN_BOTTOM,
            'Page {PAGE_NUM} of {PAGE_COUNT}',
            $font,
            self::FONT_SIZE,
            [0.35, 0.35, 0.35]
        );
    }
}
