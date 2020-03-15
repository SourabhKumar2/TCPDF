<?php


namespace TCPDF;


use RuntimeException;
use TCPDF_STATIC;

class Output
{
    private $name;
    private $destination;
    private $buffer;
    /**
     * @var int
     */
    private $bufferlen;

    /**
     * Output constructor.
     * @param $destination
     * @param $name
     * @param $buffer
     */
    public function __construct($destination, $name, $buffer)
    {
        $this->destination = $destination;
        $this->name = $name;
        $this->buffer = $buffer;
        $this->bufferlen = strlen($this->buffer);
    }

    public function output()
    {
        switch ($this->destination) {
            case 'I':
            {
                $this->stream();
                break;
            }
            case 'D':
            {
                $this->download();
                break;
            }
            case 'F':
            case 'FI':
            case 'FD':
            {
                $this->save();
                break;
            }
            case 'E':
            {
                return $this->email();
            }
            case 'S':
            {
                // returns PDF as a string
                return $this->string();
            }
            default:
            {
                $this->Error('Incorrect output destination: ' . $this->destination);
            }
        }
    }

    private function getBuffer()
    {
        return $this->buffer;
    }

    private function Error(string $string): void
    {
        throw new RuntimeException('TCPDF ERROR: ' . $string);
    }

    /**
     * Send PDF to the standard output
     */
    private function stream(): void
    {
        if (ob_get_contents()) {
            $this->Error('Some data has already been output, can\'t send PDF file');
        }

        if ($this->isCli()) {
            echo $this->getBuffer();
            return;
        }

        // send output to a browser
        header('Content-Type: application/pdf');
        if (headers_sent()) {
            $this->Error('Some data has already been output to browser, can\'t send PDF file');
        }
        header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0, max-age=1');
        //header('Cache-Control: public, must-revalidate, max-age=0'); // HTTP/1.1
        header('Pragma: public');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Content-Disposition: inline; filename="' . basename($this->name) . '"');
        TCPDF_STATIC::sendOutputData($this->getBuffer(), $this->bufferlen);
    }

    private function download(): void
    {
// download PDF as file
        if (ob_get_contents()) {
            $this->Error('Some data has already been output, can\'t send PDF file');
        }
        header('Content-Description: File Transfer');
        if (headers_sent()) {
            $this->Error('Some data has already been output to browser, can\'t send PDF file');
        }
        header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0, max-age=1');
        //header('Cache-Control: public, must-revalidate, max-age=0'); // HTTP/1.1
        header('Pragma: public');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        // force download dialog
        if ($this->isCgi()) {
            header('Content-Type: application/pdf');
        } else {
            header('Content-Type: application/force-download');
            header('Content-Type: application/octet-stream', false);
            header('Content-Type: application/download', false);
            header('Content-Type: application/pdf', false);
        }
        // use the Content-Disposition header to supply a recommended filename
        header('Content-Disposition: attachment; filename="' . basename($this->name) . '"');
        header('Content-Transfer-Encoding: binary');
        TCPDF_STATIC::sendOutputData($this->getBuffer(), $this->bufferlen);
    }

    private function save(): void
    {
// save PDF to a local file
        $f = TCPDF_STATIC::fopenLocal($this->name, 'wb');
        if (!$f) {
            $this->Error('Unable to create output file: ' . $this->name);
        }
        fwrite($f, $this->getBuffer(), $this->bufferlen);
        fclose($f);
        if ($this->destination == 'FI') {
            // send headers to browser
            header('Content-Type: application/pdf');
            header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0, max-age=1');
            //header('Cache-Control: public, must-revalidate, max-age=0'); // HTTP/1.1
            header('Pragma: public');
            header('Expires: Sat, 26 Jul 1997 05:00:00 GMT'); // Date in the past
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            header('Content-Disposition: inline; filename="' . basename($this->name) . '"');
            TCPDF_STATIC::sendOutputData(file_get_contents($this->name), filesize($this->name));
        } elseif ($this->destination == 'FD') {
            // send headers to browser
            if (ob_get_contents()) {
                $this->Error('Some data has already been output, can\'t send PDF file');
            }
            header('Content-Description: File Transfer');
            if (headers_sent()) {
                $this->Error('Some data has already been output to browser, can\'t send PDF file');
            }
            header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0, max-age=1');
            header('Pragma: public');
            header('Expires: Sat, 26 Jul 1997 05:00:00 GMT'); // Date in the past
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            // force download dialog
            if ($this->isCgi()) {
                header('Content-Type: application/pdf');
            } else {
                header('Content-Type: application/force-download');
                header('Content-Type: application/octet-stream', false);
                header('Content-Type: application/download', false);
                header('Content-Type: application/pdf', false);
            }
            // use the Content-Disposition header to supply a recommended filename
            header('Content-Disposition: attachment; filename="' . basename($this->name) . '"');
            header('Content-Transfer-Encoding: binary');
            TCPDF_STATIC::sendOutputData(file_get_contents($this->name), filesize($this->name));
        }
    }

    /**
     * @return string PDF as base64 mime multi-part email attachment (RFC 2045)
     */
    private function email(): string
    {
        $email = 'Content-Type: application/pdf;' . "\r\n";
        $email .= ' name="' . $this->name . '"' . "\r\n";
        $email .= 'Content-Transfer-Encoding: base64' . "\r\n";
        $email .= 'Content-Disposition: attachment;' . "\r\n";
        $email .= ' filename="' . $this->name . '"' . "\r\n\r\n";
        $email .= chunk_split(base64_encode($this->getBuffer()), 76, "\r\n");
        return $email;
    }

    /**
     * @return mixed
     */
    private function string()
    {
        return $this->getBuffer();
    }

    /**
     * @return bool
     */
    private function isCli(): bool
    {
        return PHP_SAPI === 'cli';
    }

    /**
     * @return bool
     */
    private function isCgi(): bool
    {
        return strpos(PHP_SAPI, 'cgi') !== false;
    }
}