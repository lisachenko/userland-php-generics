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
 * One declared type parameter of a template, after parsing
 */
final class TemplateParameterDefinition
{
    /**
     * @param string      $name     Parameter name as declared, e.g. `T`
     * @param int         $position Zero-based declaration order, which is `of()` argument order
     * @param string|null $bound    Optional bound, mirroring `@template T of Foo`
     */
    public function __construct(
        public readonly string $name,
        public readonly int $position,
        public readonly ?string $bound = null,
    ) {}
}
