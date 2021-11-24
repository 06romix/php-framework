<?php
declare(strict_types=1);

namespace Dev256\Framework\Reflection;

use JetBrains\PhpStorm\Pure;
use Laminas\Code\Reflection\ClassReflection;

/**
 * Gathers method metadata information.
 */
class MethodsMap
{

    public const METHOD_META_NAME = 'name';
    public const METHOD_META_TYPE = 'type';
    public const METHOD_META_HAS_DEFAULT_VALUE = 'isDefaultValueAvailable';
    public const METHOD_META_DEFAULT_VALUE = 'defaultValue';

    private array $serviceInterfaceMethodsMap = [];

    public function __construct(
        private TypeProcessor $typeProcessor,
        private FieldNamer $fieldNamer
    ) {}

    /**
     * Get return type by type name and method name.
     */
    public function getMethodReturnType(string $typeName, string $methodName): string
    {
        return $this->getMethodsMap($typeName)[$methodName]['type'];
    }

    /**
     * Return service interface or Data interface methods loaded from cache
     *
     * @throws \InvalidArgumentException if methods don't have annotation
     * @throws \ReflectionException for missing DocBock or invalid reflection class
     */
    public function getMethodsMap(string $interfaceName): array
    {
        $key = md5($interfaceName);
        if (! isset($this->serviceInterfaceMethodsMap[$key])) {
                $methodMap = $this->getMethodMapViaReflection($interfaceName);
                $this->serviceInterfaceMethodsMap[$key] = $methodMap;
        }
        return $this->serviceInterfaceMethodsMap[$key];
    }

    /**
     * Use reflection to load the method information
     *
     * @param string $interfaceName
     * @return array
     * @throws \ReflectionException for missing DocBock or invalid reflection class
     * @throws \InvalidArgumentException if methods don't have annotation
     */
    private function getMethodMapViaReflection(string $interfaceName): array
    {
        $methodMap = [];
        $class = new ClassReflection($interfaceName);
        foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($this->isSuitableMethod($method)) {
                $methodMap[$method->getName()] = $this->typeProcessor->getGetterReturnType($method);
            }
        }
        return $methodMap;
    }

    /**
     * Determines if the method is suitable to be used by the processor.
     *
     * @param \ReflectionMethod $method
     * @return bool
     */
    #[Pure]
    private function isSuitableMethod(\ReflectionMethod $method): bool
    {
        $isSuitableMethodType = !($method->isConstructor() || $method->isFinal()
            || $method->isStatic() || $method->isDestructor());

        $isExcludedMagicMethod = str_starts_with($method->getName(), '__');
        return $isSuitableMethodType && !$isExcludedMagicMethod;
    }

    /**
     * Determines if the given method's on the given type is suitable for an output data array.
     */
    public function isMethodValidForDataField(string $type, string $methodName): bool
    {
        $methods = $this->getMethodsMap($type);
        if (isset($methods[$methodName])) {
            $methodMetadata = $methods[$methodName];
            // any method with parameter(s) gets ignored because we do not know the type and value of
            // the parameter(s), so we are not able to process
            if ($methodMetadata['parameterCount'] > 0) {
                return false;
            }

            return $this->fieldNamer->getFieldNameForMethodName($methodName) !== null;
        }

        return false;
    }

    /**
     * If the method has only non-null return types
     */
    public function isMethodReturnValueRequired(string $type, string $methodName): bool
    {
        $methods = $this->getMethodsMap($type);
        return $methods[$methodName]['isRequired'];
    }

    /**
     * Retrieve requested service method params metadata.
     */
    public function getMethodParams(string $serviceClassName, string $serviceMethodName): array
    {
        $serviceClass = new ClassReflection($serviceClassName);
        $serviceMethod = $serviceClass->getMethod($serviceMethodName);
        $params = [];
        foreach ($serviceMethod->getParameters() as $paramReflection) {
            $isDefaultValueAvailable = $paramReflection->isDefaultValueAvailable();
            $params[] = [
                self::METHOD_META_NAME => $paramReflection->getName(),
                self::METHOD_META_TYPE => $this->typeProcessor->getParamType($paramReflection),
                self::METHOD_META_HAS_DEFAULT_VALUE => $isDefaultValueAvailable,
                self::METHOD_META_DEFAULT_VALUE => $isDefaultValueAvailable ? $paramReflection->getDefaultValue() : null
            ];
        }
        return $params;
    }
}
