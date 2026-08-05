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

namespace Lisachenko\Generics\Benchmark\Fixture;

/**
 * The dimensions of a synthesized template
 *
 * Everything the harness varies lives here, so a result row can name the exact class it
 * measured rather than "the big one".
 */
final class TemplateShape
{
    public function __construct(
        /**
         * Own methods (M) - the term the cost model says the total is linear in
         */
        public readonly int $methods,
        /**
         * Body statements per method (K) - the term the cost model says the total is
         * independent of, except for MethodSlot::BuiltinParameter
         */
        public readonly int $statements,
        public readonly MethodSlot $slot = MethodSlot::ClassParameter,
        /**
         * Own properties (P)
         */
        public readonly int $properties = 1,
        public readonly PropertySlot $propertySlot = PropertySlot::Placeholder,
    ) {}

    public function label(): string
    {
        return sprintf('M=%d K=%d P=%d %s', $this->methods, $this->statements, $this->properties, $this->slot->value);
    }
}
