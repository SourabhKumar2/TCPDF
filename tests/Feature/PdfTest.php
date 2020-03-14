<?php

namespace Feature;

use PHPUnit\Framework\TestCase;
use TCPDF;

class PdfTest extends TestCase
{
    /** @test */
    public function can_stream_pdf()
    {
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        $output = $pdf->Output('', 'S');

        $this->assertStringStartsWith('%PDF-', $output);
    }
}
