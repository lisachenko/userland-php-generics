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

namespace Lisachenko\Generics\Attribute;

use Attribute;

/**
 * Declares one type parameter of a generic template, mirroring a PHPStan `@template` tag
 *
 * Repeat the attribute once per parameter; declaration order is the order type arguments
 * are supplied to `of()`:
 *
 * ```php
 * #[TemplateParameter('TKey', of: 'string')]
 * #[TemplateParameter('TValue')]
 * final class Map implements GenericObject { use GenericTemplate; }
 * ```
 *
 * This is an attribute rather than a doc comment on purpose: with `opcache.save_comments=0`
 * - a perfectly normal production setting - `zend_class_entry->doc_comment` is NULL, so a
 * doc-comment-driven runtime would fail exactly where it matters most. The `@template` tag
 * stays the source of truth for static analysis; a PHPStan rule keeps the two in sync.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class TemplateParameter
{
    /**
     * @param string      $name Parameter name as written in the `@template` tag, e.g. `T`
     * @param string|null $of   Optional bound, mirroring `@template T of Foo`
     */
    public function __construct(
        public string $name,
        public ?string $of = null,
    ) {}
}
