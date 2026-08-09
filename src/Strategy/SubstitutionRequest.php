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

/**
 * The substitution the runtime is going to ask for, in one value object
 *
 * A template may mix both forms freely, so a request can carry both: the name-keyed map for
 * slots that declare the placeholder as their type, and the slot-addressed list for slots
 * that announce it through an attribute. Expressed entirely in this package's own types -
 * `Monomorphizer` translates it into the engine's vocabulary at the single point where the
 * engine is asked.
 */
final class SubstitutionRequest
{
    /**
     * @param array<string, string>            $typeSubstitutions Placeholder type name => replacement
     * @param list<array{SlotAddress, string}> $slotSubstitutions Slot and the type it should carry
     */
    public function __construct(
        public readonly array $typeSubstitutions = [],
        public readonly array $slotSubstitutions = [],
    ) {}

    public function needsSlotSubstitution(): bool
    {
        return $this->slotSubstitutions !== [];
    }
}
