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

namespace Lisachenko\Generics\Exception;

use InvalidArgumentException;

/**
 * Raised when the type arguments supplied for a template cannot be used
 *
 * These are call-site problems: the wrong number of arguments, a type that does not exist,
 * or a type the engine has no way to write into a declaration slot.
 */
final class TypeArgumentException extends InvalidArgumentException implements GenericsException
{
    public static function arityMismatch(string $className, int $expected, int $given): self
    {
        return new self(sprintf(
            'Generic template %s declares %d type parameter(s) but %d type argument(s) were given.',
            $className,
            $expected,
            $given,
        ));
    }

    public static function emptyArgument(string $className, int $position): self
    {
        return new self(sprintf(
            'Type argument #%d for generic template %s is an empty string.',
            $position,
            $className,
        ));
    }

    public static function unknownType(string $className, string $typeName): self
    {
        return new self(sprintf(
            'Type argument "%s" for generic template %s is neither a builtin type nor an existing '
            . 'class, interface or enum.',
            $typeName,
            $className,
        ));
    }

    public static function unsupportedType(string $className, string $typeName, string $reason): self
    {
        return new self(sprintf(
            'Type argument "%s" for generic template %s cannot be used: %s',
            $typeName,
            $className,
            $reason,
        ));
    }
}
