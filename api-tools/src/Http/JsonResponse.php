<?php

declare(strict_types=1);

namespace Bongo\Packages\ApiTools\Http;

use JsonSerializable;

class JsonResponse implements JsonSerializable
{
    protected mixed $data;
    protected int $status;
    protected array $headers;

    public function __construct(mixed $data = null, int $status = 200, array $headers = [])
    {
        $this->data = $data;
        $this->status = $status;
        $defaultHeaders = ['Content-Type' => 'application/json; charset=UTF-8'];
        $this->headers = array_merge($defaultHeaders, $headers);
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    public function setStatusCode(int $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function header(string $key, string $value): static
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function getContent(): string
    {
        if (is_string($this->data)) {
            return $this->data;
        }
        return (string) json_encode($this->data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $header => $value) {
                header("{$header}: {$value}");
            }
        }
        echo $this->getContent();
    }

    public function jsonSerialize(): mixed
    {
        return $this->data;
    }

    public function __toString(): string
    {
        return $this->getContent();
    }
}
