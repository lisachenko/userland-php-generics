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
use PhpParser\Node\Expr\Instanceof_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\VerbosityLevel;

/**
 * Reports `$x instanceof SomeTemplate`, which is always false
 *
 * The highest-value rule in the extension, because it converts the single most surprising thing
 * about this design into a static error. A specialization is a **sibling** of its template - it
 * shares the template's parent and interfaces rather than extending it - so `$box instanceof
 * Box` is `false` for every `Box<T>` that exists. Nothing about the call site looks wrong, the
 * check simply never fires, and without this rule the only way to find out is to ship it.
 *
 * @implements Rule<Instanceof_>
 */
final class InstanceofGenericTemplateRule implements Rule
{
    public function __construct(private readonly ReflectionProvider $reflectionProvider) {}

    public function getNodeType(): string
    {
        return Instanceof_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->class instanceof Node\Name) {
            return [];
        }

        $className = $scope->resolveName($node->class);
        $class     = TemplateReflection::find($this->reflectionProvider, $className);
        if ($class === null || !TemplateReflection::isTemplate($class)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Instanceof between %s and the generic template %s is always false.',
                $scope->getType($node->expr)->describe(VerbosityLevel::typeOnly()),
                $class->getName(),
            ))
                ->identifier('generics.instanceofTemplate')
                ->tip(sprintf(
                    "A specialization is a sibling of its template, not a subclass.\n"
                    . 'Type-hint an interface the template implements, or ask '
                    . 'Generic::isSpecialization($value, %s::class).',
                    $class->getName(),
                ))
                ->build(),
        ];
    }
}
