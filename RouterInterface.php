<?php
declare(strict_types=1);

namespace Dev256\Framework;

interface RouterInterface
{

    public function match(RequestInterface $request): ActionInterface;
}
