<?php
declare(strict_types=1);

namespace Dev256\Framework\Reflection;

use JetBrains\PhpStorm\ArrayShape;

class Getter
{

    public const IS_METHOD_PREFIX = 'is';
    public const HAS_METHOD_PREFIX = 'has';
    public const GETTER_PREFIX = 'get';

    #[ArrayShape(['isGetter' => 'bool', 'nameWithoutPrefix' => 'string'])]
    public function parseMethodName(string $methodName): array
    {
        if (str_starts_with($methodName, self::IS_METHOD_PREFIX)) {
            return ['isGetter' => true, 'nameWithoutPrefix' => substr($methodName, 2)];
        }

        if (str_starts_with($methodName, self::HAS_METHOD_PREFIX)
            || str_starts_with($methodName, self::GETTER_PREFIX)
        ) {
            return ['isGetter' => true, 'nameWithoutPrefix' => substr($methodName, 3)];
        }

        return ['isGetter' => false, 'nameWithoutPrefix' => ''];
    }
}
