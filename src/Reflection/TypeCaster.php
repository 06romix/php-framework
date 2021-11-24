<?php
declare(strict_types=1);

namespace Dev256\Framework\Reflection;

/**
 * Casts values to the type given.
 */
class TypeCaster
{

    public function castValueToType(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value) && !interface_exists($type) && !class_exists($type)) {
            return $value;
        }

        if ($type === "int" || $type === "integer") {
            return (int)$value;
        }

        if ($type === "string") {
            return (string)$value;
        }

        if ($type === "bool" || $type === "boolean" || $type === "true" || $type === "false") {
            return (bool)$value;
        }

        if ($type === "float") {
            return (float)$value;
        }

        if ($type === "double") {
            return (double)$value;
        }

        return $value;
    }
}
