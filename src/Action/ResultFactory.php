<?php
declare(strict_types=1);

namespace Dev256\Framework\Action;

use Di\ObjectManagerInterface;

class ResultFactory
{
    public const TYPE_JSON = 'json';

    public const RESULTS = [
        self::TYPE_JSON => JsonResult::class,
    ];

    public function __construct(private ObjectManagerInterface $objectManager) {}

    public function create(string $type): ResultInterface
    {
        if (! isset(self::RESULTS[$type])) {
            throw new \InvalidArgumentException('There are not result with type "' . $type . '"');
        }
        return $this->objectManager->create(self::RESULTS[$type]);
    }
}
