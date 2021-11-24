<?php
declare(strict_types=1);

namespace Dev256\Framework\Reflection;

use JetBrains\PhpStorm\Pure;

/**
 * Reflection NameFinder
 */
class NameFinder
{

    public function __construct(private Getter $getter) {}

    /**
     * Convert Data Object getter name into field name.
     *
     * @param string $getterName
     * @return string
     */
    #[Pure]
    public function getFieldNameFromGetterName(string $getterName): string
    {
        $meta = $this->getter->parseMethodName($getterName);
        if ($meta['isGetter']) {
            $fieldName = $meta['nameWithoutPrefix'];
        } else {
            $fieldName = $getterName;
        }
        return lcfirst($fieldName);
    }

    /**
     * Convert Data Object getter short description into field description.
     *
     * @param string $shortDescription
     * @return string
     */
    public function getFieldDescriptionFromGetterDescription(string $shortDescription): string
    {
        return ucfirst(substr(strstr($shortDescription, " "), 1));
    }
}
