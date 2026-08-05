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
use Throwable;

/**
 * Raised when the host environment cannot support runtime monomorphization at all
 *
 * These are environment problems, not usage problems: a missing extension, a disabled
 * ini setting, an unsupported PHP build. They are reported with an actionable message
 * instead of being allowed to become a segfault deep inside FFI.
 */
final class EngineException extends RuntimeException implements GenericsException
{
    public static function ffiExtensionMissing(): self
    {
        return new self(
            'The ffi extension is required for runtime monomorphization but is not loaded. '
            . 'Install it and enable it with ffi.enable=1.',
        );
    }

    public static function ffiDisabled(string $currentValue): self
    {
        return new self(sprintf(
            'FFI is restricted by ini setting ffi.enable="%s"; runtime monomorphization requires '
            . 'ffi.enable=1 (or a preload script that boots the engine, see preload.php).',
            $currentValue,
        ));
    }

    public static function engineBootFailed(Throwable $previous): self
    {
        return new self(
            'The Z-Engine core could not be initialized: ' . $previous->getMessage()
            . '. Check that the PHP minor version matches the installed z-engine branch and that '
            . 'opcache.jit is off.',
            0,
            $previous,
        );
    }
}
