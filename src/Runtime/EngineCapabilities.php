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

use ZEngine\Reflection\SlotSubstitutionMap;

/**
 * What the installed z-engine can actually do
 *
 * Slot-addressed substitution is newer than the name-keyed kind, so a template written in the
 * attribute form can be unsupported by an otherwise working installation. Detecting that gives
 * a sentence of explanation instead of a fatal error about a missing class.
 *
 * The `class_exists()` probe is deliberate: it asks about a *public* z-engine class name -
 * API knowledge, not internals - and it is the only detection that also works on the old
 * z-engine lines it exists to detect, which predate any capability method one could call.
 */
final class EngineCapabilities
{
    public static function supportsSlotSubstitution(): bool
    {
        return class_exists(SlotSubstitutionMap::class);
    }
}
