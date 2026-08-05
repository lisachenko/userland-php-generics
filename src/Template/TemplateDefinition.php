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

namespace Lisachenko\Generics\Template;

/**
 * Everything the runtime needs to know about one generic template
 *
 * Parsed once per class and cached: reflection over a template is pure work that never
 * changes, while specializing it happens many times.
 */
final class TemplateDefinition
{
    /**
     * @param class-string                       $className  The template class
     * @param list<TemplateParameterDefinition>  $parameters Declared type parameters, in `of()` order
     * @param list<SlotDefinition>               $slots      Declaration slots carrying those parameters
     */
    public function __construct(
        public readonly string $className,
        public readonly array $parameters,
        public readonly array $slots,
    ) {}

    public function arity(): int
    {
        return count($this->parameters);
    }

    /**
     * @return list<string>
     */
    public function parameterNames(): array
    {
        return array_map(static fn(TemplateParameterDefinition $p): string => $p->name, $this->parameters);
    }

    /**
     * Returns the slots that carry the given parameter, in discovery order
     *
     * @return list<SlotDefinition>
     */
    public function slotsFor(string $parameterName): array
    {
        return array_values(array_filter(
            $this->slots,
            static fn(SlotDefinition $slot): bool => $slot->templateParameter === $parameterName,
        ));
    }
}
