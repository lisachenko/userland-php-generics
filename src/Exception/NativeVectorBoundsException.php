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

use OutOfBoundsException;

/**
 * Raised when an element index falls outside the block of memory
 *
 * Separate from NativeVectorException on purpose, and extending the SPL class callers already
 * write `catch (OutOfBoundsException)` for: this is the one failure that ordinary, correct code
 * runs into (a loop off by one), while everything on NativeVectorException is a mistake about
 * the vector itself.
 *
 * It is also the check that stands between a userland typo and a wild pointer dereference, so
 * it is made *before* every read and every write, never after.
 */
final class NativeVectorBoundsException extends OutOfBoundsException implements GenericsException
{
    public static function index(string $vectorClass, int $index, int $count): self
    {
        return new self(sprintf(
            'Index %d is outside the %d element(s) of %s. %s',
            $index,
            $count,
            $vectorClass,
            $count === 0
                ? 'The vector is empty, so no index is valid; append() first.'
                : sprintf('Valid indices run from 0 to %d.', $count - 1),
        ));
    }
}
