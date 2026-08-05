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
use Lisachenko\Generics\Template\SlotKind;
use Lisachenko\Generics\Template\TemplateDefinition;
use ZEngine\Reflection\TypeSlot;

/**
 * Handles slots that announce their type parameter with #[Of] or #[OfReturn]
 *
 * These are addressed by declaration rather than by type name, which is the only way to reach
 * a slot whose declared type is a builtin - `mixed` has no name for the engine to match on.
 */
final class SlotAttributeStrategy implements SubstitutionStrategy
{
    public function contribute(TemplateDefinition $template, array $bindings, SubstitutionPlan $plan): void
    {
        foreach ($template->slots as $slot) {
            if ($slot->form !== SlotForm::Attribute) {
                continue;
            }
            $replacement = $bindings[$slot->templateParameter] ?? null;
            if ($replacement === null) {
                continue;
            }
            $plan->substituteSlot(match ($slot->kind) {
                SlotKind::Property   => TypeSlot::property($slot->memberName),
                SlotKind::Parameter  => TypeSlot::parameter($slot->memberName, $slot->parameterIndex ?? 0),
                SlotKind::ReturnType => TypeSlot::returnType($slot->memberName),
            }, $slot->nullable ? '?' . $replacement : $replacement);
        }
    }
}
