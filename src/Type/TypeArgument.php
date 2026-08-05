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

namespace Lisachenko\Generics\Type;

/**
 * One parsed type argument, possibly nullable and possibly generic itself
 *
 * `Box<int>`, `?App\User` and `Map<string,Box<int>>` all land here as a tree, before any of
 * them is checked against reality.
 */
final class TypeArgument
{
    /**
     * @param string             $name      Type name as written, with `?` and `<...>` stripped
     * @param bool               $nullable  Whether the argument was written as `?X`
     * @param list<TypeArgument> $arguments Nested type arguments, empty for a plain type
     */
    public function __construct(
        public readonly string $name,
        public readonly bool $nullable = false,
        public readonly array $arguments = [],
    ) {}

    public function isGeneric(): bool
    {
        return $this->arguments !== [];
    }

    /**
     * Renders the argument back the way it was written, for error messages
     */
    public function toString(): string
    {
        $rendered = $this->name;
        if ($this->arguments !== []) {
            $rendered .= '<' . implode(',', array_map(
                static fn(self $argument): string => $argument->toString(),
                $this->arguments,
            )) . '>';
        }

        return ($this->nullable ? '?' : '') . $rendered;
    }
}
