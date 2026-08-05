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
 * Narrows `Generic::new(Box::class, ['int'])` from `object` to `Box<int>`
 *
 * The only one of the three that returns an instance rather than a name, so it is also the
 * only one that turns a declared `object` into something worth having.
 */
final class GenericNewReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension
{
    public function __construct(private readonly SpecializedTypeFactory $types) {}

    public function getClass(): string
    {
        return Generic::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'new';
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope,
    ): ?Type {
        $arguments = $methodCall->getArgs();
        if (count($arguments) < 2) {
            return null;
        }

        $templateName = $this->types->literalStrings([$arguments[0]], $scope);

        // Unlike the other two, the type arguments arrive as one array rather than a variadic
        $typeArguments = $this->types->literalStringList($arguments[1], $scope);
        if ($templateName === null || $typeArguments === null) {
            return null;
        }

        return $this->types->object($templateName[0], $typeArguments);
    }
}
