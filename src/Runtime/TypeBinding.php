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

namespace Lisachenko\Generics\Runtime;

/**
 * One specialization, recorded as the factory made it
 *
 * Deliberately not `MangledName`, and the difference is the whole reason this exists.
 * `MangledName` is what a parser *recovered* from a string, so it is only ever as good as the
 * mangler's ability to run backwards; a `TypeBinding` is what the factory *knows*, because it
 * held the template and the resolved arguments in its hands when it minted the name. For
 * `AngleBracketNameMangler` the two agree. For a mangler that cannot round-trip exactly - see
 * `IdentifierSafeNameMangler` - only this one is authoritative.
 */
final class TypeBinding
{
    /**
     * @param class-string $templateName
     * @param list<string> $typeArguments  Resolved type names, in declaration order
     * @param class-string $specializedName
     */
    private function __construct(
        public readonly string $templateName,
        public readonly array $typeArguments,
        public readonly string $specializedName,
    ) {}

    /**
     * @param class-string $templateName
     * @param list<string> $typeArguments
     * @param class-string $specializedName
     */
    public static function of(string $templateName, array $typeArguments, string $specializedName): self
    {
        return new self($templateName, $typeArguments, $specializedName);
    }

    public function arity(): int
    {
        return count($this->typeArguments);
    }
}
