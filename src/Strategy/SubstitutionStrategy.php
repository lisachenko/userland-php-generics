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

use Lisachenko\Generics\Template\TemplateDefinition;

/**
 * Translates the slots of one template form into engine-level substitutions
 *
 * There is one strategy per form. They contribute to a shared plan instead of being selected
 * between, because a single template may use both forms.
 */
interface SubstitutionStrategy
{
    /**
     * @param array<string, string> $bindings Type parameter name => concrete type name
     */
    public function contribute(TemplateDefinition $template, array $bindings, SubstitutionPlan $plan): void;
}
