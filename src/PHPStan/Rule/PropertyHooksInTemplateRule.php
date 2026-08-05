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

use Lisachenko\Generics\PHPStan\TemplateReflection;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Reports a generic template that declares property hooks
 *
 * `ClassSpecializer` refuses a class with hooks outright, so this class can never be
 * specialized. That is a clear runtime error, but it arrives at the first `of()` call rather
 * than at the declaration that caused it - and the fix is always in the declaration.
 *
 * @implements Rule<InClassNode>
 */
final class PropertyHooksInTemplateRule implements Rule
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

        $reflection = $class->getNativeReflection();
        if (!method_exists($reflection, 'getProperties')) {
            return [];
        }

        $errors = [];
        foreach ($reflection->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }
            // getHooks() is PHP 8.4; the guard keeps the rule inert rather than fatal if the
            // analyser is running on a reflection adapter that has not caught up
            if (!method_exists($property, 'getHooks') || $property->getHooks() === []) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                'Generic template %s declares property hooks on $%s, so it cannot be specialized.',
                $class->getName(),
                $property->getName(),
            ))
                ->identifier('generics.propertyHooksInTemplate')
                ->tip('The class specializer refuses a class with property hooks; move the hook logic into a method.')
                ->build();
        }

        return $errors;
    }
}
