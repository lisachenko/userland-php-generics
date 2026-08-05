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
use PhpParser\Node\Stmt\Catch_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Reports `catch (SomeTemplate $e)`, which never catches a specialization
 *
 * The same sibling-not-subclass problem as `instanceof`, in the place where it is most
 * dangerous: a `catch` that silently does not match does not fail loudly, it lets the exception
 * keep travelling.
 *
 * @implements Rule<Catch_>
 */
final class CatchGenericTemplateRule implements Rule
{
    public function __construct(private readonly ReflectionProvider $reflectionProvider) {}

    public function getNodeType(): string
    {
        return Catch_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];
        foreach ($node->types as $caught) {
            $class = TemplateReflection::find($this->reflectionProvider, $scope->resolveName($caught));
            if ($class === null || !TemplateReflection::isTemplate($class)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                'Catching the generic template %s never matches one of its specializations.',
                $class->getName(),
            ))
                ->identifier('generics.catchTemplate')
                ->tip(
                    "A specialization is a sibling of its template, not a subclass.\n"
                    . 'Catch an interface the template implements instead.',
                )
                ->line($caught->getStartLine())
                ->build();
        }

        return $errors;
    }
}
