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

    public static function malformed(string $className, string $argument, string $reason): self
    {
        return new self(sprintf(
            'Type argument "%s" for generic template %s cannot be parsed because %s.',
            $argument,
            $className,
            $reason,
        ));
    }

    public static function nullabilityNotExpressible(string $className, string $argument): self
    {
        return new self(sprintf(
            'Type argument "%s" for generic template %s cannot be nullable. Substitution preserves '
            . 'the nullability the template itself declared and cannot add it, so declare the slot '
            . 'as `?T` in %s instead of asking for a nullable argument.',
            $argument,
            $className,
            $className,
        ));
    }

    public static function unknownNestedTemplate(string $className, string $nestedName): self
    {
        return new self(sprintf(
            'Type argument for generic template %s refers to %s as a nested generic, but no such '
            . 'class exists.',
            $className,
            $nestedName,
        ));
    }

    public static function boundViolation(
        string $className,
        string $parameterName,
        string $bound,
        string $argument,
    ): self {
        return new self(sprintf(
            'Type parameter %s of generic template %s is bound to %s, which "%s" does not satisfy.',
            $parameterName,
            $className,
            $bound,
            $argument,
        ));
    }

    public static function recursionLimit(string $className, int $limit, string $chain): self
    {
        return new self(sprintf(
            'Nested specialization of generic template %s exceeded the depth limit of %d: %s',
            $className,
            $limit,
            $chain,
        ));
    }

}
