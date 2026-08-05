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
 * Turns "this parameter becomes this type" into whatever the engine can act on
 *
 * There is one strategy per template form: the placeholder form keys substitutions by type
 * name, the attribute form addresses declaration slots directly. A strategy only ever
 * describes the substitution - the Monomorphizer is the single place that talks to the
 * engine.
 */
interface SubstitutionStrategy
{
    /**
     * Whether this strategy can handle every slot of the given template
     */
    public function supports(TemplateDefinition $template): bool;

    /**
     * Builds the engine-level substitution request
     *
     * @param array<string, string> $bindings Type parameter name => concrete type name
     */
    public function buildRequest(TemplateDefinition $template, array $bindings): SubstitutionRequest;
}
