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

namespace Lisachenko\Generics;

/**
 * Gives a template the `of()` constructor-of-classes that reads like a generic instantiation
 *
 * ```php
 * $box = new (Box::of('int'))();
 * ```
 *
 * `new (expr)()` is ordinary PHP 8 syntax, and it is what lets static analysis follow along:
 * `of()` is declared to return `class-string<static>`, and the shipped PHPStan extension
 * narrows that to `class-string<Box<int>>` when the arguments are literals.
 */
trait GenericTemplate
{
    /**
     * Returns the class name of this template specialized for the given type arguments
     *
     * @param  string                ...$typeArguments Builtin or class names, in declaration order
     * @return class-string<static>
     */
    public static function of(string ...$typeArguments): string
    {
        /**
         * The trait's methods are copied onto every specialization too, so `static::class`
         * may well be `Box<int>` here; the factory rejects that rather than trying to
         * specialize a specialization.
         *
         * @var class-string<static> $specialized
         */
        $specialized = Generic::specialize(static::class, ...$typeArguments);

        return $specialized;
    }
}
