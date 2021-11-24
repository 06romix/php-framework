<?php
declare(strict_types=1);

namespace Dev256\Framework;

class Response
{
    private array $headers = [];
    private string $content = '';
    private int $responseCode = 200;

    public function setResponseCode(int $responseCode): Response
    {
        $this->responseCode = $responseCode;
        return $this;
    }

    public function setHeader(string $name, string|int $value): Response
    {
        $this->headers[] = [$name, $value];
        return $this;
    }

    public function setContent(string $content): Response
    {
        $this->content = $content;
        return $this;
    }

    public function send(): void
    {
        http_response_code($this->responseCode);
        $this->renderHeaders($this->headers);
        echo $this->content;
    }

    private function renderHeaders(array $headers): void
    {
        foreach ($headers as $header) {
            header("$header[0]: $header[1]");
        }
    }
}
