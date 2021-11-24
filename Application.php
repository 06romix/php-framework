<?php
declare(strict_types=1);

namespace Dev256\Framework;

class Application
{
    public function __construct(
        private \Dev256\Framework\RequestInterface $request,
        private \Dev256\Framework\RouterInterface $router
    ) {}

    public function run()
    {
        $action = $this->router->match($this->request);
        $result = $action->execute($this->request);
        $result->render();
    }
}
