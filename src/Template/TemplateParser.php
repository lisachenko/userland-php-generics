<?php

/**
 * Userland PHP Generics
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 */
declare(strict_types=1);

namespace Lisachenko\Generics\Template;

use Lisachenko\Generics\Attribute\TemplateParameter;
use Lisachenko\Generics\Exception\TemplateException;
use Lisachenko\Generics\GenericObject;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

/**
 * Turns a template class into a TemplateDefinition using native reflection only
 *
 * Nothing here reads a doc comment: `@template` tags are for static analysis, attributes and
 * declared types are for the runtime (see AGENTS.md).
 */
final class TemplateParser
{
    /**
     * @param class-string $className
     */
    public function parse(string $className): TemplateDefinition
    {
        $reflection = new ReflectionClass($className);
        $parameters = $this->parseParameters($reflection);

        if (!$reflection->implementsInterface(GenericObject::class)) {
            throw TemplateException::missingMarkerInterface($className, GenericObject::class);
        }

        return new TemplateDefinition($className, $parameters, $this->parseSlots($reflection, $parameters));
    }

    /**
     * @param  ReflectionClass<object>          $reflection
     * @return list<TemplateParameterDefinition>
     */
    private function parseParameters(ReflectionClass $reflection): array
    {
        $attributes = $reflection->getAttributes(TemplateParameter::class);
        if ($attributes === []) {
            throw TemplateException::notATemplate($reflection->getName());
        }

        $parameters = [];
        $seen       = [];
        foreach ($attributes as $position => $attribute) {
            $declaration = $attribute->newInstance();
            if (isset($seen[$declaration->name])) {
                throw TemplateException::duplicateParameter($reflection->getName(), $declaration->name);
            }
            if ($declaration->name === '') {
                throw TemplateException::noParameters($reflection->getName());
            }
            $seen[$declaration->name] = true;
            $parameters[]             = new TemplateParameterDefinition(
                $declaration->name,
                $position,
                $declaration->of,
            );
        }

        return $parameters;
    }

    /**
     * @param  ReflectionClass<object>          $reflection
     * @param  list<TemplateParameterDefinition> $parameters
     * @return list<SlotDefinition>
     */
    private function parseSlots(ReflectionClass $reflection, array $parameters): array
    {
        $byShortName = [];
        foreach ($parameters as $parameter) {
            $byShortName[$parameter->name] = $parameter->name;
        }

        $slots = [];
        foreach ($reflection->getProperties() as $property) {
            if (!$this->isOwnedBy($reflection, $property)) {
                continue;
            }
            $slot = $this->placeholderSlotForProperty($reflection, $property, $byShortName);
            if ($slot !== null) {
                $slots[] = $slot;
            }
        }

        foreach ($reflection->getMethods() as $method) {
            if (!$this->isOwnedBy($reflection, $method)) {
                continue;
            }
            foreach ($this->placeholderSlotsForMethod($reflection, $method, $byShortName) as $slot) {
                $slots[] = $slot;
            }
        }

        return $slots;
    }

    /**
     * Whether the member is declared by the template itself rather than inherited
     *
     * The engine shares inherited property_info and op_array entries with the declaring
     * class, so a substitution there would leak into the parent - z-engine rejects it, and
     * this filter makes sure we never even ask.
     *
     * @param ReflectionClass<object>            $reflection
     * @param ReflectionProperty|ReflectionMethod $member
     */
    private function isOwnedBy(ReflectionClass $reflection, ReflectionProperty|ReflectionMethod $member): bool
    {
        return $member->getDeclaringClass()->getName() === $reflection->getName();
    }

    /**
     * @param  ReflectionClass<object> $reflection
     * @param  array<string, string>   $parameterNames
     */
    private function placeholderSlotForProperty(
        ReflectionClass $reflection,
        ReflectionProperty $property,
        array $parameterNames,
    ): ?SlotDefinition {
        $match = $this->matchPlaceholder(
            $reflection,
            $property->getType(),
            $parameterNames,
            sprintf('property $%s', $property->getName()),
        );
        if ($match === null) {
            return null;
        }

        return SlotDefinition::placeholderProperty($property->getName(), $match[0], $match[1]);
    }

    /**
     * @param  ReflectionClass<object> $reflection
     * @param  array<string, string>   $parameterNames
     * @return list<SlotDefinition>
     */
    private function placeholderSlotsForMethod(
        ReflectionClass $reflection,
        ReflectionMethod $method,
        array $parameterNames,
    ): array {
        $slots = [];
        foreach ($method->getParameters() as $index => $parameter) {
            $match = $this->matchPlaceholder(
                $reflection,
                $parameter->getType(),
                $parameterNames,
                sprintf('parameter $%s of %s()', $parameter->getName(), $method->getName()),
            );
            if ($match !== null) {
                $slots[] = SlotDefinition::placeholderParameter(
                    $method->getName(),
                    $index,
                    $parameter->getName(),
                    $match[0],
                    $match[1],
                );
            }
        }

        $returnMatch = $this->matchPlaceholder(
            $reflection,
            $method->getReturnType(),
            $parameterNames,
            sprintf('return type of %s()', $method->getName()),
        );
        if ($returnMatch !== null) {
            $slots[] = SlotDefinition::placeholderReturnType($method->getName(), $returnMatch[0], $returnMatch[1]);
        }

        return $slots;
    }

    /**
     * Resolves a declared type to the type parameter it stands for, if any
     *
     * @param  ReflectionClass<object> $reflection
     * @param  array<string, string>   $parameterNames
     * @return array{string, string}|null Tuple of parameter name and fully-qualified placeholder name
     */
    private function matchPlaceholder(
        ReflectionClass $reflection,
        ?ReflectionType $type,
        array $parameterNames,
        string $slotDescription,
    ): ?array {
        if ($type instanceof ReflectionNamedType) {
            if ($type->isBuiltin()) {
                return null;
            }
            $shortName = $this->shortNameOf($type->getName());

            return isset($parameterNames[$shortName]) ? [$shortName, $type->getName()] : null;
        }

        // A composite type is a `zend_type` list, and the engine refuses to substitute inside
        // one; catching it here turns a confusing engine rejection into a precise message.
        foreach ($this->namedTypesWithin($type) as $member) {
            $shortName = $this->shortNameOf($member->getName());
            if (isset($parameterNames[$shortName])) {
                throw TemplateException::placeholderInCompositeType(
                    $reflection->getName(),
                    $slotDescription,
                    $shortName,
                );
            }
        }

        return null;
    }

    /**
     * Flattens a union, an intersection or a DNF combination of the two into its named types
     *
     * @return list<ReflectionNamedType>
     */
    private function namedTypesWithin(?ReflectionType $type): array
    {
        if (!$type instanceof ReflectionUnionType && !$type instanceof ReflectionIntersectionType) {
            return [];
        }

        $named = [];
        foreach ($type->getTypes() as $member) {
            if ($member instanceof ReflectionNamedType) {
                if (!$member->isBuiltin()) {
                    $named[] = $member;
                }

                continue;
            }
            foreach ($this->namedTypesWithin($member) as $nested) {
                $named[] = $nested;
            }
        }

        return $named;
    }

    private function shortNameOf(string $typeName): string
    {
        $position = strrpos($typeName, '\\');

        return $position === false ? $typeName : substr($typeName, $position + 1);
    }
}
