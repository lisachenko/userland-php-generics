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
 * How a slot announces which type parameter it carries
 *
 * The two forms exist because they are enforced by two different engine mechanisms, and a
 * template may mix them freely - see docs/design-notes.md.
 */
enum SlotForm
{
    /**
     * The slot's native type *is* the placeholder, e.g. `public ?T $value`
     *
     * Substitution is keyed by type name, which is what z-engine's TypeSubstitutionMap
     * already does, so this form needs nothing beyond the shipped API.
     */
    case Placeholder;

    /**
     * The slot is natively `mixed` and carries an #[Of] attribute naming the parameter
     *
     * `mixed` has no type name to key on, so this form is substituted by addressing the
     * declaration slot directly.
     */
    case Attribute;
}
