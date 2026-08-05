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

namespace Lisachenko\Generics\PHPStan;

use Lisachenko\Generics\Generic;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Type;

/**
 * Narrows `Generic::specialize(Box::class, 'int')` to `class-string<Box<int>>`
 *
 * The facade equivalent of `Box::of('int')`, for templates that do not use the trait and for
 * code that holds the template name rather than writing it.
 */
final class GenericSpecializeReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension
{
    public function __construct(private readonly SpecializedTypeFactory $types) {}

    public function getClass(): string
    {
        return Generic::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'specialize';
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope,
    ): ?Type {
        $arguments = $this->types->literalStrings(array_values($methodCall->getArgs()), $scope);
        if ($arguments === null || $arguments === []) {
            return null;
        }

        // The first argument is the template; the rest are its type arguments
        $templateName = array_shift($arguments);

        return $this->types->classString($templateName, array_values($arguments));
    }
}
