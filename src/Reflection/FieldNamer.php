<?php
declare(strict_types=1);

namespace Dev256\Framework\Reflection;

/**
 * Determines the name to use for fields in a data output array given method metadata.
 */
class FieldNamer
{

    public function __construct(private Getter $getter) {}

    /**
     * Converts a method's name into a data field name.
     */
    public function getFieldNameForMethodName(string $methodName): string
    {
        $data = $this->getter->parseMethodName($methodName);
        if (! $data['isGetter']) {
            return '';
        }

        return $this->camelCaseToSnakeCase($data['nameWithoutPrefix']);
    }

    /**
     * Convert a CamelCase string read from method into field key in snake_case
     *
     * For example [DefaultShipping => default_shipping, Postcode => postcode]
     */
    public function camelCaseToSnakeCase(string $name): string
    {
        return strtolower(preg_replace('/(.)([A-Z])/', "$1_$2", $name));
    }
}
