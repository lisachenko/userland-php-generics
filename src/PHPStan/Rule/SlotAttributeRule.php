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

namespace Lisachenko\Generics\PHPStan\Rule;

use Lisachenko\Generics\Attribute\Of;
use Lisachenko\Generics\Attribute\OfReturn;
use Lisachenko\Generics\PHPStan\TemplateReflection;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;

/**
 * Checks `#[Of]` and `#[OfReturn]` against the template that carries them
 *
 * The same three checks `TemplateParser` makes when it parses a template, moved to analysis
 * time: the named type parameter must be declared, the marked slot must have a declared type,
 * and that type must be a single one rather than a union or intersection - the engine cannot
 * substitute inside a type list.
 *
 * Duplicating the runtime's validation is the point rather than a smell. A mistake here is
 * otherwise found only by calling `of()`, which may be far from the declaration that is wrong.
 *
 * @implements Rule<InClassNode>
 */
final class SlotAttributeRule implements Rule
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $class = $node->getClassReflection();
        if (!TemplateReflection::isTemplate($class)) {
            return [];
        }

        $declared   = TemplateReflection::typeParameterNames($class);
        $reflection = $class->getNativeReflection();
        $errors     = [];

        foreach ($reflection->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }
            $errors = [...$errors, ...$this->check(
                $class->getName(),
                $declared,
                $property->getAttributes(Of::class),
                $property->getType(),
                sprintf('property $%s', $property->getName()),
            )];
        }

        foreach ($reflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }
            $errors = [...$errors, ...$this->checkMethod($class->getName(), $declared, $method)];
        }

        return $errors;
    }

    /**
     * @param  list<string>                            $declared
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function checkMethod(string $className, array $declared, ReflectionMethod $method): array
    {
        $errors = $this->check(
            $className,
            $declared,
            $method->getAttributes(OfReturn::class),
            $method->getReturnType(),
            sprintf('return type of %s()', $method->getName()),
        );

        foreach ($method->getParameters() as $parameter) {
            $errors = [...$errors, ...$this->check(
                $className,
                $declared,
                $parameter->getAttributes(Of::class),
                $parameter->getType(),
                sprintf('parameter $%s of %s()', $parameter->getName(), $method->getName()),
            )];
        }

        return $errors;
    }

    /**
     * @param  list<string>                             $declared
     * @param  array<mixed>                             $attributes Whatever the analyser's
     *                                                              reflection returned
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function check(
        string $className,
        array $declared,
        array $attributes,
        ?ReflectionType $type,
        string $slot,
    ): array {
        if ($attributes === []) {
            return [];
        }

        $parameter = TemplateReflection::markedParameter($attributes);

        if ($parameter !== null && !in_array($parameter, $declared, true)) {
            return [
                RuleErrorBuilder::message(sprintf(
                    'Slot %s of generic template %s is marked as carrying the type parameter %s, '
                    . 'which the class does not declare with #[TemplateParameter].',
                    $slot,
                    $className,
                    $parameter,
                ))->identifier('generics.unknownTypeParameter')->build(),
            ];
        }

        if ($type === null) {
            return [
                RuleErrorBuilder::message(sprintf(
                    'Slot %s of generic template %s is marked with an attribute but has no '
                    . 'declared type, so there is nothing for the engine to replace.',
                    $slot,
                    $className,
                ))->identifier('generics.untypedSlot')->build(),
            ];
        }

        if (!$type instanceof ReflectionNamedType) {
            return [
                RuleErrorBuilder::message(sprintf(
                    'Slot %s of generic template %s declares a union or intersection type. The '
                    . 'engine cannot substitute a type inside a type list, so a marked slot must '
                    . 'be a single type.',
                    $slot,
                    $className,
                ))->identifier('generics.compositeSlotType')->build(),
            ];
        }

        return [];
    }
}
