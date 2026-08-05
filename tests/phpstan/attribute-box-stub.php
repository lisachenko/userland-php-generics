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
 * PHPStan stub for the attribute-form fixture; see fixture-stubs.php for the rationale.
 *
 * One class per stub file: PHPStan indexes the first class declaration of a stub and ignores
 * the rest, so a shared file would silently stop describing everything after its first entry.
 */

declare(strict_types=1);

namespace Lisachenko\Generics\Fixture;

final class AttributeBox
{
    private mixed $value = null;

    /**
     * @return class-string<AttributeBox>
     */
    public static function of(string ...$typeArguments): string {}

    public function set(mixed $value): void {}

    public function get(): mixed {}
}
