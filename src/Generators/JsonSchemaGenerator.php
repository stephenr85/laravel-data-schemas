<?php

namespace Rushing\LaravelDataSchemas\Generators;

use BackedEnum;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;
use Rushing\LaravelDataSchemas\Attributes\Description;
use Rushing\LaravelDataSchemas\Attributes\Example;
use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Enum;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Url;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Attributes\Validation\ValidationAttribute;
use Spatie\LaravelData\Data;

class JsonSchemaGenerator implements Generator
{
    public function __construct(protected array $config) {}

    public function canGenerate(ReflectionClass $class): bool
    {
        return $class->isSubclassOf(Data::class);
    }

    public function generate(ReflectionClass $class): array
    {
        $properties = $class->getProperties(ReflectionProperty::IS_PUBLIC);
        $schema = [
            'type' => 'object',
            'title' => $class->getShortName(),
            'properties' => [],
        ];

        // Add $schema if configured
        if ($this->config['schema_metadata']['$schema'] ?? false) {
            $schema['$schema'] = $this->config['schema_version'];
        }

        // Add $id if configured
        if ($this->config['schema_metadata']['$id'] ?? false) {
            $schema['$id'] = $this->generateId($class);
        }

        // Add class-level description if available
        $classDescription = $this->getClassDescription($class);
        if ($classDescription) {
            $schema['description'] = $classDescription;
        }

        $required = [];

        foreach ($properties as $property) {
            $propertySchema = $this->generatePropertySchema($property);
            $schema['properties'][$property->getName()] = $propertySchema;

            if (! $this->isNullable($property)) {
                $required[] = $property->getName();
            }
        }

        if (! empty($required)) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    protected function generateId(ReflectionClass $class): string
    {
        return $class->getShortName();
    }

    protected function getClassDescription(ReflectionClass $class): ?string
    {
        $descAttrs = $class->getAttributes(Description::class);

        return ! empty($descAttrs) ? $descAttrs[0]->newInstance()->value : null;
    }

    protected function generatePropertySchema(ReflectionProperty $property): array
    {
        $schema = [];

        // Determine JSON Schema type(s)
        $types = $this->resolveJsonTypes($property);
        if (count($types) === 1) {
            $schema['type'] = $types[0];
        } elseif (count($types) > 1) {
            $schema['type'] = $types;
        }

        // Add description from custom attribute
        $descAttrs = $property->getAttributes(Description::class);
        if (! empty($descAttrs)) {
            $schema['description'] = $descAttrs[0]->newInstance()->value;
        }

        // Add examples from custom attribute
        $exampleAttrs = $property->getAttributes(Example::class);
        if (! empty($exampleAttrs)) {
            $schema['examples'] = array_map(
                fn (ReflectionAttribute $attr) => $attr->newInstance()->value,
                $exampleAttrs
            );
        }

        // Map validation attributes to JSON Schema constraints
        $this->mapValidationAttributes($property, $schema);

        return $schema;
    }

    protected function resolveJsonTypes(ReflectionProperty $property): array
    {
        $type = $property->getType();
        $types = [];

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $unionType) {
                $types = array_merge($types, $this->mapPhpTypeToJsonSchema($unionType));
            }
        } elseif ($type instanceof ReflectionNamedType) {
            $types = $this->mapPhpTypeToJsonSchema($type);
        }

        // Check for ArrayType validation attribute
        $arrayTypeAttrs = $property->getAttributes(ArrayType::class, ReflectionAttribute::IS_INSTANCEOF);
        if (! empty($arrayTypeAttrs) && ! in_array('array', $types)) {
            $types[] = 'array';
        }

        return array_unique($types);
    }

    protected function mapPhpTypeToJsonSchema(ReflectionNamedType $type): array
    {
        $typeName = $type->getName();
        $types = [];

        $types[] = match ($typeName) {
            'int' => 'integer',
            'float' => 'number',
            'bool' => 'boolean',
            'string' => 'string',
            'array' => 'array',
            'object' => 'object',
            'null' => 'null',
            default => 'string', // Default for unknown types
        };

        if ($type->allowsNull() && ! in_array('null', $types)) {
            $types[] = 'null';
        }

        return $types;
    }

    protected function isNullable(ReflectionProperty $property): bool
    {
        // Check if property has Nullable attribute
        $nullableAttrs = $property->getAttributes(Nullable::class, ReflectionAttribute::IS_INSTANCEOF);
        if (! empty($nullableAttrs)) {
            return true;
        }

        // Check if type allows null
        $type = $property->getType();
        if ($type && $type->allowsNull()) {
            return true;
        }

        return false;
    }

    protected function mapValidationAttributes(ReflectionProperty $property, array &$schema): void
    {
        $validationAttrs = $property->getAttributes(
            ValidationAttribute::class,
            ReflectionAttribute::IS_INSTANCEOF
        );

        foreach ($validationAttrs as $attr) {
            $instance = $attr->newInstance();
            $attrClass = get_class($instance);

            // Check for custom mapping
            $customMapping = $this->config['validation_mapping'][$attrClass] ?? null;
            if (is_callable($customMapping)) {
                $result = $customMapping($instance);
                if (is_array($result)) {
                    $schema = array_merge($schema, $result);
                }

                continue;
            }

            // Default mappings
            match ($attrClass) {
                Max::class => $this->applyMaxConstraint($instance, $schema),
                Min::class => $this->applyMinConstraint($instance, $schema),
                Between::class => $this->applyBetweenConstraint($instance, $schema),
                Email::class => $schema['format'] = 'email',
                Uuid::class => $schema['format'] = 'uuid',
                Url::class => $schema['format'] = 'uri',
                Enum::class => $this->applyEnumConstraint($instance, $schema),
                default => null,
            };
        }
    }

    protected function applyMaxConstraint(Max $attr, array &$schema): void
    {
        $type = $schema['type'] ?? null;

        if ($type === 'string' || (is_array($type) && in_array('string', $type))) {
            $schema['maxLength'] = $attr->max;
        }

        if ($type === 'integer' || $type === 'number' || (is_array($type) && (in_array('integer', $type) || in_array('number', $type)))) {
            $schema['maximum'] = $attr->max;
        }

        if ($type === 'array' || (is_array($type) && in_array('array', $type))) {
            $schema['maxItems'] = $attr->max;
        }
    }

    protected function applyMinConstraint(Min $attr, array &$schema): void
    {
        $type = $schema['type'] ?? null;

        if ($type === 'string' || (is_array($type) && in_array('string', $type))) {
            $schema['minLength'] = $attr->min;
        }

        if ($type === 'integer' || $type === 'number' || (is_array($type) && (in_array('integer', $type) || in_array('number', $type)))) {
            $schema['minimum'] = $attr->min;
        }

        if ($type === 'array' || (is_array($type) && in_array('array', $type))) {
            $schema['minItems'] = $attr->min;
        }
    }

    protected function applyBetweenConstraint(Between $attr, array &$schema): void
    {
        $type = $schema['type'] ?? null;

        if ($type === 'integer' || $type === 'number' || (is_array($type) && (in_array('integer', $type) || in_array('number', $type)))) {
            $schema['minimum'] = $attr->min;
            $schema['maximum'] = $attr->max;
        }
    }

    protected function applyEnumConstraint(Enum $attr, array &$schema): void
    {
        $enumClass = $attr->enumClass;

        if (enum_exists($enumClass) && is_subclass_of($enumClass, BackedEnum::class)) {
            $schema['enum'] = array_map(
                fn (BackedEnum $case) => $case->value,
                $enumClass::cases()
            );
        }
    }
}