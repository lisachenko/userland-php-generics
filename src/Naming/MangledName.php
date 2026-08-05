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

namespace Lisachenko\Generics\Naming;

/**
 * A specialized class name taken apart again
 *
 * The result of running a mangler backwards: which template a runtime class came from, and
 * what it was specialized for. Manglers produce this; nothing else does, which is why the
 * constructor is private and why there is no validation here - a `NameMangler::parse()` that
 * cannot recognise a name returns `null` rather than an unusable instance.
 */
final class MangledName
{
    /**
     * @param class-string $templateName
     * @param list<string> $typeArguments In declaration order, exactly as they were mangled
     */
    private function __construct(
        public readonly string $templateName,
        public readonly array $typeArguments,
    ) {}

    /**
     * @param class-string $templateName
     * @param list<string> $typeArguments
     */
    public static function of(string $templateName, array $typeArguments): self
    {
        return new self($templateName, $typeArguments);
    }

    public function arity(): int
    {
        return count($this->typeArguments);
    }
}
