<?php
declare(strict_types=1);

namespace Dev256\Framework\Action;

interface ResultInterface
{

    public function render(): void;

    public function setResponseCode(int $code): void;
}
