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

use ZEngine\Reflection\TypeSubstitutionMap;

/**
 * The engine-level substitution a strategy asks for, in one value object
 *
 * Keeping this separate from the strategies means the Monomorphizer has exactly one thing to
 * hand to `ClassSpecializer::specialize()`, no matter which form the template was written in.
 */
final class SubstitutionRequest
{
    private function __construct(
        public readonly ?TypeSubstitutionMap $typeSubstitutions,
    ) {}

    /**
     * Substitutes by placeholder type name, the mechanism z-engine ships today
     *
     * @param array<string, string> $substitutions Placeholder type name => replacement type name
     */
    public static function byTypeName(array $substitutions): self
    {
        return new self(new TypeSubstitutionMap($substitutions));
    }

    /**
     * Copies the template without rewriting any type
     *
     * Reached when a template declares parameters that no declaration slot uses; the copy is
     * still a distinct class, it just has nothing to enforce.
     */
    public static function none(): self
    {
        return new self(null);
    }
}
