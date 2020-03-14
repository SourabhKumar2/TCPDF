<?php

namespace Feature;

use PHPUnit\Framework\TestCase;
use TCPDF;

class PdfTest extends TestCase
{
    /**
     * @var TCPDF
     */
    private $pdf;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    }

    /** @test */
    public function can_recieve_pdf_as_string()
    {
        $output = $this->pdf->Output('', 'S');

        $this->assertStringStartsWith('%PDF-', $output);
    }

    /** @test */
    public function it_throws_an_exception_when_provided_invalid_destination(): void
    {
        $destination= 'invalid';
        try {
            $this->pdf->Output('', $destination);
        } catch (\Exception $exception) {
            $this->assertEquals('TCPDF ERROR: Incorrect output destination: '. strtoupper($destination), $exception->getMessage());
            return;
        }

        $this->fail('Exception is not thrown when given destination '. $destination);
    }
}
