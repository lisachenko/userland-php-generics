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
 * Raised when the engine refused to materialize a specialization
 *
 * Engine rejections arrive phrased in class-entry terms ("classes with property hooks are
 * not supported"). Callers of this library are writing generic templates, not manipulating
 * class entries, so every rejection is re-stated in template terms here, with the original
 * exception kept as $previous for anyone who needs it.
 */
final class SpecializationException extends RuntimeException implements GenericsException
{
    public static function engineRejectedTemplate(
        string $templateName,
        string $specializedName,
        Throwable $previous,
    ): self {
        return new self(
            sprintf(
                'Generic template %s cannot be monomorphized into %s: %s See docs/limitations.md '
                . 'for the kinds of class that can be used as a template.',
                $templateName,
                $specializedName,
                rtrim($previous->getMessage(), '.') . '.',
            ),
            0,
            $previous,
        );
    }

    public static function specializedClassVanished(string $specializedName): self
    {
        return new self(sprintf(
            'The engine reported that %s was registered, but it is not present in the class table. '
            . 'This usually means the class table was modified concurrently.',
            $specializedName,
        ));
    }
}
