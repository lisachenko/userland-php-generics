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

namespace Lisachenko\Generics\Strategy;

use Lisachenko\Generics\Template\SlotKind;

/**
 * One declaration slot, addressed by position rather than by type name
 *
 * This package's own way of saying "the return type of get()" or "parameter #0 of set()",
 * expressed entirely in its own vocabulary. `Monomorphizer` translates it into whatever the
 * engine wants at the single point where the engine is asked - which is what keeps the
 * strategies loadable and testable without z-engine on the autoload path.
 */
final class SlotAddress
{
    private function __construct(
        public readonly SlotKind $kind,
        public readonly string $memberName,
        public readonly ?int $parameterIndex = null,
    ) {}

    public static function property(string $propertyName): self
    {
        return new self(SlotKind::Property, $propertyName);
    }

    /**
     * @param int $parameterIndex Zero-based declaration position of the parameter
     */
    public static function parameter(string $methodName, int $parameterIndex): self
    {
        return new self(SlotKind::Parameter, $methodName, $parameterIndex);
    }

    public static function returnType(string $methodName): self
    {
        return new self(SlotKind::ReturnType, $methodName);
    }
}
