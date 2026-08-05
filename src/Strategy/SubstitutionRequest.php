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

use ZEngine\Reflection\SlotSubstitutionMap;
use ZEngine\Reflection\TypeSubstitutionMap;

/**
 * The engine-level substitution the runtime is going to ask for, in one value object
 *
 * A template may mix both forms freely, so a request can carry both maps: the name-keyed one
 * for slots that declare the placeholder as their type, the slot-addressed one for slots that
 * announce it through an attribute.
 */
final class SubstitutionRequest
{
    public function __construct(
        public readonly ?TypeSubstitutionMap $typeSubstitutions = null,
        public readonly ?SlotSubstitutionMap $slotSubstitutions = null,
    ) {}

    public function needsSlotSubstitution(): bool
    {
        return $this->slotSubstitutions !== null;
    }
}
