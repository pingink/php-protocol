<?php
declare(strict_types=1);

namespace Pingink\Protocol\Http;


class Response
{
    public const STATUS_INIT = 0;
    public const STATUS_HEADER = 1;
    public const STATUS_BODY = 2;

    private int $status = self::STATUS_INIT;

    private string $method;

    private string $uri;

    private string $version;

    private array $headers;

    private string $body;

    private string $buffer;


    public string $content;

    public function __construct(string $body)
    {
        $this->content = "HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\nContent-Length: " . strlen($body) . "\r\n\r\n{$body}";
    }

    public function getContent(): string
    {
        return $this->content;
    }
}