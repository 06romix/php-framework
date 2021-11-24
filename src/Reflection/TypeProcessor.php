<?php
declare(strict_types=1);

namespace Dev256\Framework\Reflection;

use JetBrains\PhpStorm\ArrayShape;
use Laminas\Code\Reflection\ClassReflection;
use Laminas\Code\Reflection\DocBlock\Tag\ParamTag;
use Laminas\Code\Reflection\DocBlock\Tag\ReturnTag;
use Laminas\Code\Reflection\DocBlockReflection;
use Laminas\Code\Reflection\MethodReflection;
use Laminas\Code\Reflection\ParameterReflection;

/**
 * Type processor of config reader properties
 */
class TypeProcessor
{
    /**#@+
     * Pre-normalized type constants
     */
    public const STRING_TYPE = 'str';
    public const INT_TYPE = 'integer';
    public const BOOLEAN_TYPE = 'bool';
    public const ANY_TYPE = 'mixed';
    /**#@-*/

    /**#@+
     * Normalized type constants
     */
    public const NORMALIZED_STRING_TYPE = 'string';
    public const NORMALIZED_INT_TYPE = 'int';
    public const NORMALIZED_FLOAT_TYPE = 'float';
    public const NORMALIZED_DOUBLE_TYPE = 'double';
    public const NORMALIZED_BOOLEAN_TYPE = 'boolean';
    public const NORMALIZED_ANY_TYPE = 'anyType';
    /**#@-*/

    /**#@-*/
    protected array $types = [];

    public function __construct(private NameFinder $nameFinder, private Getter $getter) {}

    /**
     * Process type name. In case parameter type is a complex type (class) - process its properties.
     *
     * @return string|null Complex type name
     * @throws \LogicException
     */
    public function register(string $type): ?string
    {
        $typeName = $this->normalizeType($type);
        if (null === $typeName) {
            return null;
        }
        if (!$this->isTypeSimple($typeName) && !$this->isTypeAny($typeName)) {
            $typeSimple = $this->getArrayItemType($type);
            if (!(class_exists($typeSimple) || interface_exists($typeSimple))) {
                throw new \LogicException(
                    sprintf(
                        'The "%s" class doesn\'t exist and the namespace must be specified. Verify and try again.',
                        $type
                    )
                );
            }
            $complexTypeName = $this->translateTypeName($type);
            if (!isset($this->types[$complexTypeName])) {
                $this->processComplexType($type);
            }
            $typeName = $complexTypeName;
        }

        return $typeName;
    }

    /**
     * Retrieve complex type information from class public properties.
     *
     * @param string $class
     * @return array
     */
    protected function processComplexType($class)
    {
        $typeName = $this->translateTypeName($class);
        $this->types[$typeName] = [];
        if ($this->isArrayType($class)) {
            $this->register($this->getArrayItemType($class));
        } else {
            if (!(class_exists($class) || interface_exists($class))) {
                throw new \InvalidArgumentException(
                    sprintf('The "%s" class couldn\'t load as a parameter type.', $class)
                );
            }
            $reflection = new ClassReflection($class);
            $docBlock = $reflection->getDocBlock();
            $this->types[$typeName]['documentation'] = $docBlock ? $this->getDescription($docBlock) : '';
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $methodReflection) {
                $this->_processMethod($methodReflection, $typeName);
            }
        }

        return $this->types[$typeName];
    }

    /**
     * Collect metadata for virtual field corresponding to current method if it is a getter (used in WSDL generation).
     *
     * @param MethodReflection $methodReflection
     * @param string $typeName
     * @return void
     */
    protected function _processMethod(MethodReflection $methodReflection, string $typeName): void
    {
        $isGetter = $this->getter->parseMethodName($methodReflection->getName());
        /** Field will not be added to WSDL if getter has params */
        if ($isGetter && !$methodReflection->getNumberOfRequiredParameters()) {
            $returnMetadata = $this->getGetterReturnType($methodReflection);
            $fieldName = $this->nameFinder->getFieldNameFromGetterName($methodReflection->getName());
            if ($returnMetadata['description']) {
                $description = $returnMetadata['description'];
            } else {
                $description = $this->nameFinder->getFieldDescriptionFromGetterDescription(
                    $methodReflection->getDocBlock()->getShortDescription()
                );
            }
            $this->types[$typeName]['parameters'][$fieldName] = [
                'type' => $this->register($returnMetadata['type']),
                'required' => $returnMetadata['isRequired'],
                'documentation' => $description,
            ];
        }
    }

    /**
     * Get short and long description from docblock and concatenate.
     *
     * @param DocBlockReflection $doc
     * @return string
     */
    public function getDescription(DocBlockReflection $doc)
    {
        $shortDescription = $doc->getShortDescription();
        $longDescription = $doc->getLongDescription();

        $description = rtrim($shortDescription);
        $longDescription = str_replace(["\n", "\r"], '', $longDescription);
        if (!empty($longDescription) && !empty($description)) {
            $description .= " ";
        }
        $description .= ltrim($longDescription);

        return $description;
    }

    /**
     * Identify getter return type by its reflection.
     *
     * @param MethodReflection $methodReflection
     * @return array <pre>array(
     *     'type' => <string>$type,
     *     'isRequired' => $isRequired,
     *     'description' => $description
     *     'parameterCount' => $numberOfRequiredParameters
     * )</pre>
     * @throws \InvalidArgumentException
     */
    #[ArrayShape(['type' => "null|string", 'isRequired' => 'bool', 'description' => 'string', 'parameterCount' => 'int'])]
    public function getGetterReturnType($methodReflection)
    {
        $returnAnnotation = $this->getMethodReturnAnnotation($methodReflection);
        $types = $returnAnnotation->getTypes();
        $returnType = null;
        foreach ($types as $type) {
            if ($type !== 'null') {
                $returnType = $type;
                break;
            }
        }

        $nullable = in_array('null', $types, true);

        return [
            'type' => $returnType,
            'isRequired' => !$nullable,
            'description' => $returnAnnotation->getDescription(),
            'parameterCount' => $methodReflection->getNumberOfRequiredParameters()
        ];
    }

    /**
     * Normalize short type names to full type names.
     *
     * @param string $type
     * @return string
     */
    public function normalizeType(string $type)
    {
        if ($type === 'null') {
            return null;
        }
        $normalizationMap = [
            self::STRING_TYPE => self::NORMALIZED_STRING_TYPE,
            self::INT_TYPE => self::NORMALIZED_INT_TYPE,
            self::BOOLEAN_TYPE => self::NORMALIZED_BOOLEAN_TYPE,
            self::ANY_TYPE => self::NORMALIZED_ANY_TYPE,
        ];
        return is_string($type) && isset($normalizationMap[$type]) ? $normalizationMap[$type] : $type;
    }

    /**
     * Check if given type is a simple type.
     */
    public function isTypeSimple(string $type): bool
    {
        return in_array(
            $this->getNormalizedType($type),
            [
                self::NORMALIZED_STRING_TYPE,
                self::NORMALIZED_INT_TYPE,
                self::NORMALIZED_FLOAT_TYPE,
                self::NORMALIZED_DOUBLE_TYPE,
                self::NORMALIZED_BOOLEAN_TYPE,
            ],
            true
        );
    }

    /**
     * Check if given type is any type.
     *
     * @param string $type
     * @return bool
     */
    public function isTypeAny(string $type): bool
    {
        return $this->getNormalizedType($type) === self::NORMALIZED_ANY_TYPE;
    }

    /**
     * Check if given type is an array of type items.
     * Example:
     * <pre>
     *  ComplexType[] -> array of ComplexType items
     *  string[] -> array of strings
     * </pre>
     *
     * @param string $type
     * @return bool
     */
    public function isArrayType(string $type): bool
    {
        return (bool)preg_match('/(\[\]$|^ArrayOf)/', $type);
    }

    /**
     * Get item type of the array.
     *
     * @param string $arrayType
     * @return string
     */
    public function getArrayItemType($arrayType)
    {
        return $this->normalizeType(str_replace('[]', '', $arrayType));
    }

    /**
     * Translate complex type class name into type name.
     *
     * Example:
     * <pre>
     *  \Service\Api\Data\ServiceInterface => CustomerV1DataCustomer
     * </pre>
     *
     * @param string $class
     * @return string
     * @throws \InvalidArgumentException
     */
    public function translateTypeName(string $class): string
    {
        if (preg_match('/\\\\?(.*)\\\\(Service|Api)\\\\\2?(.*)/', $class, $matches)) {
            $moduleName = $matches[1];
            $typeNameParts = explode('\\', $matches[3]);

            return ucfirst($moduleName . implode('', $typeNameParts));
        }
        throw new \InvalidArgumentException(
            sprintf('The "%s" parameter type is invalid. Verify the parameter and try again.', $class)
        );
    }

    /**
     * Convert the value to the requested simple or any type
     *
     * @param int|string|float|int[]|string[]|float[] $value
     * @param string $type Convert given value to the this simple type
     * @return int|string|float|int[]|string[]|float[] Return the value which is converted to type
     */
    public function processSimpleAndAnyType($value, $type)
    {
        $isArrayType = $this->isArrayType($type);
        if ($isArrayType && is_array($value)) {
            $arrayItemType = $this->getArrayItemType($type);
            foreach (array_keys($value) as $key) {
                if ($value !== null && !settype($value[$key], $arrayItemType)) {
                    throw new \Exception(
                        new Phrase(
                            'The "%value" value\'s type is invalid. The "%type" type was expected. '
                            . 'Verify and try again.',
                            ['value' => $value, 'type' => $type]
                        )
                    );
                }
            }
        } elseif ($isArrayType && $value === null) {
            return null;
        } elseif (!$isArrayType && !is_array($value)) {
            if ($value !== null && !$this->isTypeAny($type) && !$this->setType($value, $type)) {
                throw new \Exception(
                    new Phrase(
                        'The "%value" value\'s type is invalid. The "%type" type was expected. Verify and try again.',
                        ['value' => (string)$value, 'type' => $type]
                    )
                );
            }
        } elseif (!$this->isTypeAny($type)) {
            throw new \Exception(
                new Phrase(
                    'The "%value" value\'s type is invalid. The "%type" type was expected. Verify and try again.',
                    ['value' => gettype($value), 'type' => $type]
                )
            );
        }
        return $value;
    }

    /**
     * Get the parameter type
     *
     * @param ParameterReflection $param
     * @return string
     * @throws \LogicException
     */
    public function getParamType(ParameterReflection $param)
    {
        $type = $param->detectType();
        if ($type === 'null') {
            throw new \LogicException(
                sprintf(
                    '@param annotation is incorrect for the parameter "%s" in the method "%s:%s".'
                    . ' First declared type should not be null. E.g. string|null',
                    $param->getName(),
                    $param->getDeclaringClass()->getName(),
                    $param->getDeclaringFunction()->name
                )
            );
        }
        if ($type === 'array') {
            // try to determine class, if it's array of objects
            $paramDocBlock = $this->getParamDocBlockTag($param);
            $paramTypes = $paramDocBlock->getTypes();
            $paramType = array_shift($paramTypes);

            $paramType = $this->resolveFullyQualifiedClassName($param->getDeclaringClass(), $paramType);

            return strpos($paramType, '[]') !== false ? $paramType : "{$paramType}[]";
        }

        return $this->resolveFullyQualifiedClassName($param->getDeclaringClass(), $type);
    }

    /**
     * Get alias mapping for source class
     *
     * @param ClassReflection $sourceClass
     * @return array
     */
    public function getAliasMapping(ClassReflection $sourceClass): array
    {
        $sourceFileName = $sourceClass->getDeclaringFile();
        $aliases = [];
        foreach ($sourceFileName->getUses() as $use) {
            if ($use['as'] !== null) {
                $aliases[$use['as']] = $use['use'];
            } else {
                $pos = strrpos($use['use'], '\\');

                $aliasName = substr($use['use'], $pos + 1);
                $aliases[$aliasName] = $use['use'];
            }
        }

        return $aliases;
    }

    /**
     * Return true if the passed type is a simple type
     *
     * Eg.:
     * Return true with; array, string, ...
     * Return false with: SomeClassName
     *
     * @param string $typeName
     * @return bool
     */
    public function isSimpleType(string $typeName): bool
    {
        return strtolower($typeName) === $typeName;
    }

    /**
     * Get basic type for a class name
     *
     * Eg.:
     * SomeClassName[] => SomeClassName
     *
     * @param string $className
     * @return string
     */
    public function getBasicClassName(string $className): string
    {
        $pos = strpos($className, '[');
        return ($pos === false) ? $className : substr($className, 0, $pos);
    }

    /**
     * Return true if it is a FQ class name
     *
     * Eg.:
     * SomeClassName => false
     * \My\NameSpace\SomeClassName => true
     *
     * @param string $className
     * @return bool
     */
    public function isFullyQualifiedClassName(string $className): bool
    {
        return strpos($className, '\\') === 0;
    }

    /**
     * Get aliased class name
     *
     * @param string $className
     * @param string $namespace
     * @param array $aliases
     * @return string
     */
    private function getAliasedClassName(string $className, string $namespace, array $aliases): string
    {
        $pos = strpos($className, '\\');
        if ($pos === false) {
            $namespacePrefix = $className;
            $partialClassName = '';
        } else {
            $namespacePrefix = substr($className, 0, $pos);
            $partialClassName = substr($className, $pos);
        }

        if (isset($aliases[$namespacePrefix])) {
            return $aliases[$namespacePrefix] . $partialClassName;
        }

        return $namespace . '\\' . $className;
    }

    /**
     * Resolve fully qualified type name in the class alias context
     *
     * @param ClassReflection $sourceClass
     * @param string $typeName
     * @return string
     */
    public function resolveFullyQualifiedClassName(ClassReflection $sourceClass, string $typeName): string
    {
        $typeName = trim($typeName);

        // Simple way to understand it is a basic type or a class name
        if ($this->isSimpleType($typeName)) {
            return $typeName;
        }

        $basicTypeName = $this->getBasicClassName($typeName);

        // Already a FQN class name
        if ($this->isFullyQualifiedClassName($basicTypeName)) {
            return '\\' . substr($typeName, 1);
        }

        $isArray = $this->isArrayType($typeName);
        $aliases = $this->getAliasMapping($sourceClass);

        $namespace = $sourceClass->getNamespaceName();
        $fqClassName = '\\' . $this->getAliasedClassName($basicTypeName, $namespace, $aliases);

        if (interface_exists($fqClassName) || class_exists($fqClassName)) {
            return $fqClassName . ($isArray ? '[]' : '');
        }

        return $typeName;
    }

    /**
     * Set value to a particular type
     *
     * @param mixed $value
     * @param string $type
     * @return true on successful type cast
     */
    protected function setType(&$value, $type)
    {
        // settype doesn't work for boolean string values.
        // ex: custom_attributes passed from SOAP client can have boolean values as string
        $booleanTypes = ['bool', 'boolean'];
        if (in_array($type, $booleanTypes)) {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            return true;
        }
        $numType = ['int', 'float'];
        if (in_array($type, $numType) && !is_numeric($value)) {
            return false;
        }
        return settype($value, $type);
    }

    /**
     * Get normalized type
     *
     * @param string $type
     * @return string
     */
    private function getNormalizedType($type)
    {
        $type = $this->normalizeType($type);
        if ($this->isArrayType($type)) {
            $type = $this->getArrayItemType($type);
        }
        return $type;
    }

    /**
     * Parses `return` annotation from reflection method.
     *
     * @param MethodReflection $methodReflection
     * @return ReturnTag
     * @throws \InvalidArgumentException if doc block is empty or `@return` annotation doesn't exist
     */
    private function getMethodReturnAnnotation(MethodReflection $methodReflection)
    {
        $methodName = $methodReflection->getName();
        $returnAnnotations = $this->getReturnFromDocBlock($methodReflection);
        if (empty($returnAnnotations)) {
            // method can inherit doc block from implemented interface, like for interceptors
            $implemented = $methodReflection->getDeclaringClass()->getInterfaces();
            /** @var ClassReflection $parentClassReflection */
            foreach ($implemented as $parentClassReflection) {
                if ($parentClassReflection->hasMethod($methodName)) {
                    $returnAnnotations = $this->getReturnFromDocBlock(
                        $parentClassReflection->getMethod($methodName)
                    );
                    break;
                }
            }
            // throw an exception if even implemented interface doesn't have return annotations
            if (empty($returnAnnotations)) {
                throw new \InvalidArgumentException(
                    "Method's return type must be specified using @return annotation. "
                    . "See {$methodReflection->getDeclaringClass()->getName()}::{$methodName}()"
                );
            }
        }
        return $returnAnnotations;
    }

    /**
     * Parses `return` annotation from doc block.
     *
     * @param MethodReflection $methodReflection
     * @return ReturnTag
     */
    private function getReturnFromDocBlock(MethodReflection $methodReflection)
    {
        $methodDocBlock = $methodReflection->getDocBlock();
        if (!$methodDocBlock) {
            throw new \InvalidArgumentException(
                "Each method must have a doc block. "
                . "See {$methodReflection->getDeclaringClass()->getName()}::{$methodReflection->getName()}()"
            );
        }
        return current($methodDocBlock->getTags('return'));
    }

    /**
     * Gets method's param doc block.
     *
     * @param ParameterReflection $param
     * @return ParamTag
     */
    private function getParamDocBlockTag(ParameterReflection $param): ParamTag
    {
        $docBlock = $param->getDeclaringFunction()
            ->getDocBlock();
        $paramsTag = $docBlock->getTags('param');
        return $paramsTag[$param->getPosition()];
    }
}
