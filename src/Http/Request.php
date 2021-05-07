<?php
declare(strict_types=1);

namespace Pingink\Protocol\Http;


class Request
{

    public string $method;

    public string $uri;

    public string $version;

    public array $headers;

    public $body;

    public string $buffer;

    public function __construct(string $buffer)
    {
        $this->buffer = $buffer;

        list($header, $body) = explode("\r\n\r\n", $this->buffer);

        $headers = explode("\r\n", $header);

        list($this->method, $this->uri, $this->version) = explode(" ", $headers[0]);

        unset($headers[0]);

        foreach ($headers as $item) {
            list($key, $val) = explode(": ", $item);

            $this->headers[$key] = $val;
        }

        $this->body = $body;
    }

    public function getContentLength(): int
    {
        $length = $this->headers['Content-Length'] ?? 0;

        return (int) $length;
    }

    public function getBodyLength(): int
    {
        return strlen($this->body);
    }
}