<?php


namespace TCPDF;


use ReflectionException;
use ReflectionMethod;
use RuntimeException;
use TCPDF;
use TCPDF_STATIC;

class Output
{
    /** @var string */
    private $name;
    /** @var string */
    private $destination;
    /** @var string */
    private $buffer;
    /** @var int */
    private $bufferLength;

    /**
     * @param string $name
     * @return Output
     */
    public function setName(string $name): Output
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @param string $destination
     * @return Output
     */
    public function setDestination(string $destination): Output
    {
        $this->destination = $destination;
        return $this;
    }


    /**
     * @return mixed|string
     */
    public function get()
    {
        switch ($this->destination) {
            case 'I':
                $this->stream();
                break;
            case 'D':
                $this->download();
                break;
            case 'F':
                $this->save();
                break;
            case 'FI':
                $this->save();
                $this->checkIfDataSentAlready();
                $this->sendToBrowser();
                break;
            case 'FD':
                $this->save();
                $this->download();
                break;
            case 'E':
                return $this->email();
            case 'S':
                // returns PDF as a string
                return $this->string();
            default:
                $this->error('Incorrect output destination: ' . $this->destination);
        }
    }

    /**
     * @return string Data from the buffer
     */
    private function getBuffer(): string
    {
        return $this->buffer;
    }

    private function error(string $string): void
    {
        throw new RuntimeException('TCPDF ERROR: ' . $string);
    }

    /**
     * Send PDF to the standard output
     */
    private function stream(): void
    {
        $this->checkIfDataSentAlready();

        if ($this->isCli()) {
            echo $this->getBuffer();
            return;
        }

        $this->sendToBrowser();
    }

    /**
     * Download PDF as file
     */
    private function download(): void
    {
        $this->checkIfDataSentAlready();
        $this->setHeaders();
        TCPDF_STATIC::sendOutputData($this->getBuffer(), $this->bufferLength);
    }

    /** Save PDF to local file */
    private function save(): void
    {
        $f = TCPDF_STATIC::fopenLocal($this->name, 'wb');
        if (!$f) {
            $this->error('Unable to create output file: ' . $this->name);
        }
        fwrite($f, $this->getBuffer(), $this->bufferLength);
        fclose($f);
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

    private function setHeaders(): void
    {
        header('Content-Description: File Transfer');
        $this->checkIfHeadersAlreadySent();

        header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0, max-age=1');
        //header('Cache-Control: public, must-revalidate, max-age=0'); // HTTP/1.1
        header('Pragma: public');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Content-Type: application/pdf');

        $this->forceDownloadHeaders();

        // use the Content-Disposition header to supply a recommended filename
        header('Content-Disposition: attachment; filename="' . basename($this->name) . '"');
        header('Content-Transfer-Encoding: binary');
    }

    private function forceDownloadHeaders(): void
    {
        if (!$this->isCgi()) {
            header('Content-Type: application/force-download', false);
            header('Content-Type: application/octet-stream', false);
            header('Content-Type: application/download', false);
        }
    }

    private function checkIfHeadersAlreadySent(): void
    {
        if (headers_sent()) {
            $this->error('Some data has already been output to browser, can\'t send PDF file');
        }
    }

    private function sendToBrowser(): void
    {
        header('Content-Type: application/pdf');
        $this->checkIfHeadersAlreadySent();
        header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0, max-age=1');
//        header('Cache-Control: public, must-revalidate, max-age=0'); // HTTP/1.1
        header('Pragma: public');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Content-Disposition: inline; filename="' . basename($this->name) . '"');
        TCPDF_STATIC::sendOutputData(file_get_contents($this->name), filesize($this->name));
    }

    private function checkIfDataSentAlready(): void
    {
        if (ob_get_contents()) {
            $this->error('Some data has already been output, can\'t send PDF file');
        }
    }

    /**
     * @param $buffer
     * @return Output
     */
    public function setBuffer($buffer): Output
    {
        $this->buffer = $buffer;
        $this->bufferLength = strlen($this->buffer);
        return $this;
    }
}