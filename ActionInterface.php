<?php
declare(strict_types=1);

namespace Dev256\Framework;

use Dev256\Framework\Action\ResultInterface;

interface ActionInterface
{
    public function execute(RequestInterface $request): ResultInterface;
}
