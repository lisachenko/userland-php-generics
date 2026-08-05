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

namespace Lisachenko\Generics\Naming;

/**
 * Derives the runtime class name of a specialization
 *
 * The engine's class table is a plain hash keyed by the lowercased name, so a mangled name
 * only has to be unique and stable. Implementations decide the trade-off between staying a
 * legal PHP identifier and being impossible to collide with an autoloadable class.
 */
interface NameMangler
{
    /**
     * @param class-string  $templateName
     * @param list<string>  $typeArguments Already-normalized type argument names
     */
    public function mangle(string $templateName, array $typeArguments): string;

    /**
     * Whether the given class name was produced by this mangler
     *
     * Used to reject `Box<int>::of('string')`: a specialization is not itself a template.
     */
    public function isMangled(string $className): bool;
}
