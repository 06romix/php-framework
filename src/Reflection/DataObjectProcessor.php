<?php
declare(strict_types=1);

namespace Dev256\Framework\Reflection;

/**
 * Data object processor for array serialization using class reflection
 *
 * @api
 * @since 100.0.2
 */
class DataObjectProcessor
{

    public function __construct(
        private MethodsMap $methodsMapProcessor,
        private TypeCaster $typeCaster,
        private FieldNamer $fieldNamer
    ) {}

    /**
     * Use class reflection on given data interface to build output data array
     *
     * @param mixed $dataObject
     * @param string $dataObjectType
     * @return array
     */
    public function buildOutputDataArray(mixed $dataObject, string $dataObjectType): array
    {
        $methods = $this->methodsMapProcessor->getMethodsMap($dataObjectType);
        $outputData = [];

        foreach (array_keys($methods) as $methodName) {
            if (!$this->methodsMapProcessor->isMethodValidForDataField($dataObjectType, $methodName)) {
                continue;
            }

            $value = $dataObject->{$methodName}();
            $isMethodReturnValueRequired = $this->methodsMapProcessor->isMethodReturnValueRequired(
                $dataObjectType,
                $methodName
            );
            if ($value === null && !$isMethodReturnValueRequired) {
                continue;
            }

            $returnType = $this->methodsMapProcessor->getMethodReturnType($dataObjectType, $methodName);
            $key = $this->fieldNamer->getFieldNameForMethodName($methodName);

            if (is_object($value)) {
                $value = $this->buildOutputDataArray($value, $returnType);
            } elseif (is_array($value)) {
                $valueResult = [];
                $arrayElementType = substr($returnType, 0, -2);
                foreach ($value as $singleValue) {
                    if (is_object($singleValue)) {
                        $singleValue = $this->buildOutputDataArray($singleValue, $arrayElementType);
                    }
                    $valueResult[] = $this->typeCaster->castValueToType($singleValue, $arrayElementType);
                }
                $value = $valueResult;
            } else {
                $value = $this->typeCaster->castValueToType($value, $returnType);
            }

            $outputData[$key] = $value;
        }

        return $outputData;
    }
}
