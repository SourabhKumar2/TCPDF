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
    public function can_recieve_pdf_as_string(): void
    {
        $output = $this->pdf->Output('', 'S');

        $this->assertStringStartsWith('%PDF-', $output);
    }

    /** @test */
    public function it_throws_an_exception_when_provided_invalid_destination(): void
    {
        $destination = 'invalid';
        try {
            $this->pdf->Output('', $destination);
        } catch (\Exception $exception) {
            $this->assertEquals('TCPDF ERROR: Incorrect output destination: ' . strtoupper($destination), $exception->getMessage());
            return;
        }

        $this->fail('Exception is not thrown when given destination ' . $destination);
    }

    /** @test */
    public function print_pdf_data_at_command_line(): void
    {
        ob_start();
        $this->pdf->Output('', 'I');

        $pdf = ob_get_contents();

        $this->assertNotNull($pdf);

        ob_end_clean();
    }

    /**
     * @test
     * @runInSeparateProcess
     * @dataProvider destinationDataProvider
     */
    public function pdf_can_be_downloaded_to_browser($destination, $name): void
    {
        ob_start();

        $this->pdf->Output($name, $destination);

        $output = ob_get_contents();

        ob_end_clean();

        $expectedHeaders = [
            'Content-Description: File Transfer',
            'Cache-Control: private, must-revalidate, post-check=0, pre-check=0, max-age=1',
            'Pragma: public',
            'Expires: Sat, 26 Jul 1997 05:00:00 GMT',
            'Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT',
            'Content-Type: application/pdf',
            'Content-Type: application/force-download',
            'Content-Type: application/octet-stream',
            'Content-Type: application/download',
            'Content-Disposition: attachment; filename="' . basename('doc.pdf') . '"',
            'Content-Transfer-Encoding: binary',
            'Content-Length: ' . strlen($output)
        ];

        $headers = xdebug_get_headers();

        $this->assertEquals($expectedHeaders, $headers);

    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function pdf_can_be_saved_to_disk(): void
    {

        ob_start();
        $name = __DIR__ . '/doc.pdf';
        $this->pdf->Output($name, 'FI');

        $pdfContent = ob_get_contents();

        ob_end_clean();

        $this->assertFileExists($name);

        $this->assertEquals($pdfContent, file_get_contents($name));

        unlink($name);
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function get_pdf_data_as_email_attachent_string(): void
    {
        $name = 'doc.pdf';
        $email = $this->pdf->Output($name, 'E');

        ob_start();
        $this->pdf->Output($name, 'D');

        $buffer = ob_get_contents();

        ob_end_clean();

        $contents = 'Content-Type: application/pdf;' . "\r\n";
        $contents .= ' name="' . $name . '"' . "\r\n";
        $contents .= 'Content-Transfer-Encoding: base64' . "\r\n";
        $contents .= 'Content-Disposition: attachment;' . "\r\n";
        $contents .= ' filename="' . $name . '"' . "\r\n\r\n";
        $contents .= chunk_split(base64_encode($buffer), 76, "\r\n");

        $this->assertEquals($contents, $email);
    }

    /**
     * @test
     */
    public function throws_an_exceptions_if_directory_write_protected(): void
    {
        $this->expectException(\RuntimeException::class);
        try {
            $this->pdf->Output('doc.pdf', 'F');
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception);
        }

        $this->fail('Exception not thrown when directory does not have write permission to save pdf file');
    }

    public function destinationDataProvider(): array
    {
        return [
            'Download' => ['D', 'doc.pdf'],
            'Save and Download' => ['FD', __DIR__ . '/doc.pdf'],
        ];
    }

}
