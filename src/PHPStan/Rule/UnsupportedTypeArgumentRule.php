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

use Lisachenko\Generics\Generic;
use Lisachenko\Generics\GenericObject;
use Lisachenko\Generics\PHPStan\TemplateReflection;
use Lisachenko\Generics\Type\BuiltinTypes;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Reports a literal type argument the engine could never write into a declaration slot
 *
 * The messages come straight from `BuiltinTypes::rejectionReasonFor()` - the same table the
 * runtime throws from - so the static complaint and the runtime exception cannot drift into
 * saying different things about the same argument. `iterable` is the one worth having: it is
 * `array|Traversable`, has no single `MAY_BE_*` mask, and would otherwise read as the name of
 * a class called "iterable".
 *
 * @implements Rule<StaticCall>
 */
final class UnsupportedTypeArgumentRule implements Rule
{
    public function __construct(private readonly ReflectionProvider $reflectionProvider) {}

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $arguments = $this->typeArgumentsOf($node, $scope);
        if ($arguments === []) {
            return [];
        }

        $errors = [];
        foreach ($arguments as $argument) {
            $constants = $scope->getType($argument->value)->getConstantStrings();
            if (count($constants) !== 1) {
                // Not a literal, so nothing can be said about it here
                continue;
            }

            $reason = $this->rejectionReasonFor(trim($constants[0]->getValue()));
            if ($reason !== null) {
                $errors[] = RuleErrorBuilder::message($reason)
                    ->identifier('generics.unsupportedTypeArgument')
                    ->line($argument->getStartLine())
                    ->build();
            }
        }

        return $errors;
    }

    /**
     * The arguments of this call that are type arguments, or none if it is not one of ours
     *
     * @return list<Node\Arg>
     */
    private function typeArgumentsOf(StaticCall $node, Scope $scope): array
    {
        if (!$node->name instanceof Node\Identifier || !$node->class instanceof Node\Name) {
            return [];
        }

        $className = $scope->resolveName($node->class);
        $arguments = array_values($node->getArgs());

        if ($node->name->toString() === 'specialize' && $className === Generic::class) {
            // The first argument names the template, not a type
            return array_values(array_slice($arguments, 1));
        }

        if ($node->name->toString() !== 'of') {
            return [];
        }

        $class = TemplateReflection::find($this->reflectionProvider, $className);

        return $class !== null && $class->is(GenericObject::class) ? $arguments : [];
    }

    private function rejectionReasonFor(string $argument): ?string
    {
        if ($argument === '') {
            return 'A type argument cannot be empty.';
        }

        if (str_starts_with($argument, '?')) {
            return sprintf(
                'Type argument "%s" is nullable. Substitution preserves the nullability the '
                . 'template declared and cannot introduce it, so declare the slot as "?T" in the '
                . 'template instead.',
                $argument,
            );
        }

        if (str_contains($argument, '|') || str_contains($argument, '&')) {
            return sprintf(
                'Type argument "%s" is a union or intersection type, which cannot be written '
                . 'into a declaration slot.',
                $argument,
            );
        }

        // Only the outermost name is checked here; a nested Box<iterable> is reported when that
        // inner specialization is itself written out
        $outermost = strstr($argument, '<', true);
        $reason    = BuiltinTypes::rejectionReasonFor($outermost === false ? $argument : $outermost);

        return $reason === null ? null : sprintf('Type argument "%s" is unsupported: %s.', $argument, $reason);
    }
}
