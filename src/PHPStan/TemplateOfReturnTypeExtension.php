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

use Lisachenko\Generics\GenericObject;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Type;

/**
 * Narrows `Box::of('int')` from `class-string<Box>` to `class-string<Box<int>>`
 *
 * This is what makes `new (Box::of('int'))()` infer `Box<int>` without any annotation at the
 * call site: PHPStan resolves `new` on a `class-string<X>` to `X`, so getting the class-string
 * right is the whole job.
 *
 * Registered against `GenericObject` rather than `GenericTemplate`, and that is not
 * incidental. `of()` is a **trait** method, and PHPStan matches a dynamic return-type
 * extension against the called class and its ancestors - a trait is not one. The marker
 * interface is required on every template, so it is the one type that reliably sits in the
 * ancestry of anything that can answer `of()`.
 */
final class TemplateOfReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension
{
    public function __construct(private readonly SpecializedTypeFactory $types) {}

    public function getClass(): string
    {
        return GenericObject::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'of';
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope,
    ): ?Type {
        $templateName = $this->calledClass($methodCall, $scope);
        if ($templateName === null) {
            return null;
        }

        $arguments = $this->types->literalStrings(array_values($methodCall->getArgs()), $scope);

        return $arguments === null ? null : $this->types->classString($templateName, $arguments);
    }

    /**
     * The class `of()` was actually called on
     *
     * `$methodReflection->getDeclaringClass()` would name whichever class the trait was
     * flattened into, which is right for `Box::of()` but wrong for `static::of()` in a shared
     * base - so the call site is the authority when it names a class outright.
     */
    private function calledClass(StaticCall $methodCall, Scope $scope): ?string
    {
        if (!$methodCall->class instanceof Name) {
            return null;
        }

        $resolved = $scope->resolveName($methodCall->class);

        // A specialization is not itself a template: `Box<int>::of('string')` throws at run
        // time, so there is no type to infer for it here either
        return str_contains($resolved, '<') ? null : $resolved;
    }
}
