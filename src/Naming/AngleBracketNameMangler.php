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

namespace Lisachenko\Generics\Naming;

/**
 * Names specializations the way everyone already writes them: `App\Box<int>`
 *
 * The angle brackets are the point, not decoration:
 *
 *  - no PHP source can declare a class whose name contains `<`, and no PSR-4 autoloader can
 *    resolve one, so a specialization can never collide with a real class - which is the one
 *    naming hazard z-engine's docs call out;
 *  - the name is still readable in `get_class()`, `var_dump()` and stack traces, which is
 *    worth a great deal when debugging a class that exists only at runtime;
 *  - nested arguments nest naturally: `App\Box<App\Box<int>>`.
 */
final class AngleBracketNameMangler implements NameMangler
{
    private const OPEN  = '<';
    private const CLOSE = '>';

    public function mangle(string $templateName, array $typeArguments): string
    {
        return $templateName . self::OPEN . implode(',', $typeArguments) . self::CLOSE;
    }

    public function isMangled(string $className): bool
    {
        return str_contains($className, self::OPEN);
    }
}
