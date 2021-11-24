<?php
declare(strict_types=1);

namespace Dev256\Framework\Action;

class JsonResult implements ResultInterface
{
    private array $data = [];

    public function __construct(
        private \Dev256\Framework\Response $response,
        private \Dev256\Framework\EnvConfig $config
    ) {}

    public function setData(array $data): ResultInterface
    {
        $this->data = $data;
        return $this;
    }

    /**
     * @throws \JsonException
     */
    public function render(): void
    {
        $this->response
            ->setHeader('Access-Control-Allow-Origin', $this->config->getFrontUrl())
            ->setHeader('Content-type', 'application/json; charset=utf-8')
            ->setContent(json_encode($this->data, JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE))
            ->send();
    }

    public function setResponseCode(int $code): void
    {
        $this->response->setResponseCode($code);
    }
}
