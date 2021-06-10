<?php
declare(strict_types=1);

namespace Pingink\Protocol\Http;

/**
 * Class Request
 * @package Pingink\Protocol\Http
 */
class Request
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

    /**
     * 解析
     *
     * @param string $buffer
     * @return bool
     * @author: zhengchenping
     * @time: 2021/5/14 0:10
     */
    public function parse(string $buffer): bool
    {
        $this->buffer = $buffer;

        if ($this->status === self::STATUS_BODY) {
            return true;
        }

        if ($this->hasHeader()) {
            if ($this->getContentLength() === 0) {
                $this->status = self::STATUS_BODY;
                return true;
            }

            $arr = explode("\r\n\r\n", $this->buffer);
            if (! empty($arr[1])) {
                $this->body = $arr[1];

                if ($this->getContentLength() === $this->getBodyLength()) {
                    $this->status = self::STATUS_BODY;
                    return true;
                }
            }
        }

        return false;
    }

    public function hasHeader(): bool
    {
        if ($this->status === self::STATUS_HEADER) {
            return true;
        }

        if (strpos($this->buffer, "\r\n\r\n") !== false) {
            $arr = explode("\r\n\r\n", $this->buffer);

            $headers = $arr[0];

            [$this->method, $this->uri, $this->version] = explode(" ", $headers[0]);

            unset($headers[0]);

            foreach ($headers as $header) {
                [$key, $val] = explode(": ", $header);
                $this->headers[$key] = $val;
            }

            $this->status = self::STATUS_HEADER;
        }

        return false;
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

    public function getContent()
    {
        if (empty($this->body)) {
            return "";
        }

        if ($this->headers['Content-Type'] === 'application/json') {
            return json_decode($this->body, true);
        }
        return $this->body;
    }

    public function getUri(): string
    {
        return $this->uri;
    }
}