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

namespace Lisachenko\Generics\Benchmark\Fixture;

/**
 * How the synthesized properties declare the type they will be specialized to
 *
 * The two forms end up in different engine code paths on every write, which the dispatch
 * scenario measures: a builtin type is a bit test against the value's type code, while a class
 * type has to resolve a name to a `zend_class_entry`.
 */
enum PropertySlot: string
{
    /**
     * `private ?T $slot = null;` - the placeholder form, substituted to a class type
     */
    case Placeholder = 'class-typed';

    /**
     * `#[Of('T')] private mixed $slot = null;` - the attribute form, substituted to a builtin
     */
    case Attribute = 'builtin-typed';
}
