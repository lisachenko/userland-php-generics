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

namespace Lisachenko\Generics\Type;

/**
 * Which builtin type names the engine can write into a declaration slot, and which it cannot
 *
 * This mirrors z-engine's own builtin table. The rejected list matters more than the accepted
 * one: `iterable` in particular is `array|Traversable`, so it has no single `MAY_BE_*` mask
 * and would otherwise be silently treated as the name of a class called "iterable".
 */
final class BuiltinTypes
{
    /**
     * @var list<string>
     */
    private const SUBSTITUTABLE = [
        'int', 'float', 'string', 'bool', 'true', 'false', 'null', 'array', 'object', 'mixed',
    ];

    /**
     * @var array<string, string>
     */
    private const REJECTED = [
        'void'     => 'void is not a value type and cannot be the type of a property or parameter',
        'never'    => 'never is not a value type and cannot be the type of a property or parameter',
        'callable' => 'callable is not expressible as a property type and has no engine type mask',
        'iterable' => 'iterable is a union of array and Traversable, which cannot be substituted',
        'resource' => 'resource is not a declarable type in PHP',
        'static'   => 'static is resolved relative to a call site and has no meaning as a type argument',
        'self'     => 'self is resolved relative to a declaration and has no meaning as a type argument',
        'parent'   => 'parent is resolved relative to a declaration and has no meaning as a type argument',
    ];

    public static function isSubstitutable(string $typeName): bool
    {
        return in_array(strtolower($typeName), self::SUBSTITUTABLE, true);
    }

    /**
     * Explains why a builtin cannot be used as a type argument, or null when it can
     */
    public static function rejectionReasonFor(string $typeName): ?string
    {
        return self::REJECTED[strtolower($typeName)] ?? null;
    }
}
