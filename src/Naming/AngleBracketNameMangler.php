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

    public function parse(string $className): ?MangledName
    {
        $open = strpos($className, self::OPEN);
        if ($open === false || !str_ends_with($className, self::CLOSE)) {
            return null;
        }

        $templateName = substr($className, 0, $open);
        $arguments    = $this->splitTopLevel(substr($className, $open + 1, -1));
        if ($templateName === '' || $arguments === null) {
            return null;
        }

        /** @var class-string $templateName */
        return MangledName::of($templateName, $arguments);
    }

    /**
     * Splits an argument list on the commas that are not inside a nested one
     *
     * Deliberately *not* `TypeArgumentParser`, which serves the opposite contract: that one
     * reads user input and throws a diagnostic exception naming what is wrong with it. This
     * one reads a name the mangler itself produced, so anything malformed simply is not ours
     * and the answer is `null`. Sharing them would mean either catching exceptions for control
     * flow or giving the parser a silent mode.
     *
     * @return list<string>|null Null when the brackets do not balance
     */
    private function splitTopLevel(string $arguments): ?array
    {
        $parts  = [];
        $depth  = 0;
        $start  = 0;
        $length = strlen($arguments);

        for ($index = 0; $index < $length; ++$index) {
            $character = $arguments[$index];
            if ($character === self::OPEN) {
                ++$depth;
            } elseif ($character === self::CLOSE) {
                if (--$depth < 0) {
                    return null;
                }
            } elseif ($character === ',' && $depth === 0) {
                $parts[] = substr($arguments, $start, $index - $start);
                $start   = $index + 1;
            }
        }
        if ($depth !== 0) {
            return null;
        }
        $parts[] = substr($arguments, $start);

        foreach ($parts as $part) {
            if (trim($part) === '') {
                return null;
            }
        }

        return $parts;
    }
}
