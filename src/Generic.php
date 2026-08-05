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

use Lisachenko\Generics\Runtime\Bootstrap;

/**
 * Static entry point to the generics runtime
 *
 * A thin facade over a lazily-created default GenericFactory. Frameworks and tests that need
 * a differently-configured factory install it with setFactory(); everything else can just
 * call the static methods, or use the GenericTemplate trait and never name this class at all.
 */
final class Generic
{
    private static ?GenericFactory $factory = null;

    /**
     * Boots the engine now rather than on first specialization
     *
     * Optional - every specialization boots on demand - but calling it at application start
     * turns "this host cannot do generics" into a startup error instead of a surprise later.
     */
    public static function bootstrap(): void
    {
        Bootstrap::boot();
    }

    /**
     * Returns the class name of `$templateName<...$typeArguments>`
     *
     * @param  class-string $templateName
     * @return class-string
     */
    public static function specialize(string $templateName, string ...$typeArguments): string
    {
        return self::factory()->specialize($templateName, ...$typeArguments);
    }

    /**
     * Specializes and instantiates in one step
     *
     * @param class-string $templateName
     * @param list<string> $typeArguments
     */
    public static function new(string $templateName, array $typeArguments, mixed ...$constructorArguments): object
    {
        return self::factory()->instantiate($templateName, $typeArguments, ...$constructorArguments);
    }

    public static function factory(): GenericFactory
    {
        return self::$factory ??= new GenericFactory();
    }

    /**
     * Installs a differently-configured factory, or restores the default when given null
     */
    public static function setFactory(?GenericFactory $factory): void
    {
        self::$factory = $factory;
    }
}
