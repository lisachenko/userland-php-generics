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

/*
 * PHPStan stub declarations (phpstan.dist.neon stubFiles) for the placeholder-form fixtures.
 *
 * A placeholder-form template declares the type parameter as its *native* type:
 *
 *     private ?T $value = null;
 *     public function set(T $value): void {}
 *
 * That is exactly what the engine needs - z-engine's TypeSubstitutionMap keys on the type
 * name - but it is the opposite of what a static analyser needs. PHPStan resolves the native
 * `T` to an object type and lets it beat any `@param T`, so every call site on a
 * specialization becomes "expects Fixture\T, int given".
 *
 * A stub file *replaces* the declaration for analysis, which makes it the one mechanism that
 * can describe the class the way it actually behaves at runtime: `mixed` where the fiction
 * used to be. bin/generics-stubs generates the same thing for real templates, and the shipped
 * PHPStan extension then narrows `of()` to the precise specialization.
 *
 * Stub files are reflected before the analysed paths are indexed, so a stub cannot name the
 * library's own interfaces or traits; `of()` is therefore declared here directly instead of
 * being inherited from GenericTemplate.
 *
 * The file lives outside the PSR-4 layout, so the autoloader can never load it and redeclare
 * the fixtures: these declarations are visible to PHPStan only.
 */

declare(strict_types=1);

namespace Lisachenko\Generics\Fixture;

final class Box
{
    private mixed $value = null;

    /**
     * @return class-string<Box>
     */
    public static function of(string ...$typeArguments): string {}

    public function set(mixed $value): void {}

    public function get(): mixed {}

    public function describe(): string {}
}
