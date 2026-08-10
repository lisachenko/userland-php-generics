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

use RuntimeException;

/**
 * Raised when a native vector cannot be built or can no longer be used
 *
 * These are all problems with the *block of memory*, never with an element: an element that is
 * the wrong type is rejected by the engine as a TypeError, which is the entire point of the
 * package and is never translated into one of these (AGENTS.md section 5).
 *
 * Element indices out of range get their own class, NativeVectorBoundsException, because a
 * caller iterating a vector wants to catch that one and nothing else.
 */
final class NativeVectorException extends RuntimeException implements GenericsException
{
    /**
     * The raw template has no element type, so it has no element size and no layout
     */
    public static function notSpecialized(string $templateName): self
    {
        return new self(sprintf(
            'A %s cannot be constructed without a type argument: the element type is what fixes '
            . 'the layout of the memory block. Specialize it first, for example '
            . 'new (%s::of(\'int\'))().',
            $templateName,
            $templateName,
        ));
    }

    /**
     * Only the two native scalar kinds have a machine layout this vector can address
     */
    public static function unsupportedElementType(string $vectorClass, string $typeArgument): self
    {
        return new self(sprintf(
            'Native vector %s was specialized for "%s", but a native block of memory can only '
            . 'hold "int" (a zend_long) or "float" (a double) for now. Sized scalar kinds '
            . '(int32, uint16, ...) and C structures are the next phase - see docs/native-vectors.md.',
            $vectorClass,
            $typeArgument,
        ));
    }

    /**
     * A binary string that is not a whole number of elements has no reading
     *
     * The element size is spelled out rather than imported: both native scalar kinds are
     * 8 bytes wide, and an exception class that had to reach into the vector to phrase its
     * own message would be the wrong dependency direction.
     */
    public static function misalignedBinary(string $vectorClass, int $byteLength): self
    {
        return new self(sprintf(
            'Native vector %s holds 8-byte elements, so a binary block of %d byte(s) cannot be '
            . 'cast to it: %d byte(s) would be left over. Trim or pad the block before casting it.',
            $vectorClass,
            $byteLength,
            $byteLength % 8,
        ));
    }

    public static function negativeCapacity(string $vectorClass, int $count): self
    {
        return new self(sprintf(
            'Native vector %s cannot be created with a capacity of %d: a block of memory has no '
            . 'negative size.',
            $vectorClass,
            $count,
        ));
    }

    /**
     * Every element accessor is a pointer dereference, and destroy() dropped the pointer
     */
    public static function destroyed(string $vectorClass): self
    {
        return new self(sprintf(
            'The memory block of this %s was released by destroy(), so it has no elements to '
            . 'read or write anymore. destroy() is final for an instance; build a new vector '
            . 'from a binary string instead.',
            $vectorClass,
        ));
    }

    /**
     * A native vector's layout is its whole identity, so an element cannot be removed
     *
     * Raised through a factory rather than as an inline LogicException because every failure
     * mode in this package is named on its exception class (AGENTS.md section 8).
     */
    public static function fixedLayout(string $vectorClass): self
    {
        return new self(sprintf(
            'Elements of a native vector cannot be unset: %s is a contiguous block of memory, '
            . 'not a hash table, and removing a slot from the middle of it has no meaning. '
            . 'Overwrite the element, or build a new vector from the bytes you want to keep.',
            $vectorClass,
        ));
    }
}
