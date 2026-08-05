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

use Lisachenko\Generics\Template\SlotForm;
use Lisachenko\Generics\Template\TemplateDefinition;

/**
 * Handles slots that declare the type parameter as their native type
 *
 * ```php
 * private ?T $value = null;
 * public function set(T $value): void {}
 * ```
 *
 * `T` is a class-like type name that is never defined, so the engine already keys on it: this
 * strategy only pairs each fully-qualified placeholder name with its concrete type.
 */
final class PlaceholderNameStrategy implements SubstitutionStrategy
{
    public function contribute(TemplateDefinition $template, array $bindings, SubstitutionPlan $plan): void
    {
        foreach ($template->slots as $slot) {
            if ($slot->form !== SlotForm::Placeholder || $slot->declaredTypeName === null) {
                continue;
            }
            $replacement = $bindings[$slot->templateParameter] ?? null;
            if ($replacement !== null) {
                $plan->substituteTypeName($slot->declaredTypeName, $replacement);
            }
        }
    }
}
