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
use PhpParser\Node\Expr\ClassConstFetch;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Reports `self::class` inside a generic template, which names the template
 *
 * A direct consequence of the memory model this package exists to demonstrate: method bodies
 * are **shared** with the template through the op_array refcount, and `self::class` was folded
 * into those opcodes by the compiler long before any specialization existed. So a
 * `Box<int>` instance running the shared body reports `Box`.
 *
 * `static::class` is late-bound and resolves correctly, which makes this a one-word fix that
 * nobody would think to make unaided.
 *
 * @implements Rule<ClassConstFetch>
 */
final class SelfClassInTemplateRule implements Rule
{
    public function getNodeType(): string
    {
        return ClassConstFetch::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->class instanceof Node\Name || !$node->name instanceof Node\Identifier) {
            return [];
        }
        if ($node->name->toLowerString() !== 'class' || $node->class->toLowerString() !== 'self') {
            return [];
        }

        $class = $scope->getClassReflection();
        if ($class === null || !TemplateReflection::isTemplate($class)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'self::class inside the generic template %s resolves to the template, not to the '
                . 'specialization the method is running on.',
                $class->getName(),
            ))
                ->identifier('generics.selfClassInTemplate')
                ->tip(
                    "Method bodies are shared with the template, and self::class was folded into\n"
                    . 'them at compile time. Use static::class, which is late-bound.',
                )
                ->build(),
        ];
    }
}
