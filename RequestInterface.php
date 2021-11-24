<?php
declare(strict_types=1);

namespace Dev256\Framework;

interface RequestInterface
{
    /**
     * @param string $name
     * @param mixed  $defaultValue
     * @return mixed
     */
    public function getParam(string $name, mixed $defaultValue): mixed;

    /**
     * @return string
     */
    public function getPath(): string;

    /**
     * @return string
     */
    public function getHttpMethod(): string;
}
