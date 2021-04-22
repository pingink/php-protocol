<?php
declare(strict_types=1);

namespace Pingink\Protocol\Http;


class Response
{
    public string $content;

    public function __construct(string $body)
    {
        $this->content = "HTTP/1.1 200 OK\r\nServer: vruan_web/1.0.0\r\nContent-Length: " . strlen($body) . "\r\n\r\n{$body}";
    }
}