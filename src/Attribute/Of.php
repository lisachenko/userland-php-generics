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
 * Marks a property or parameter as carrying the named type parameter
 *
 * The alternative to naming the type parameter in the declaration itself. Use it when the
 * declared type cannot be the placeholder - a `mixed` property is the common case - or when
 * one placeholder class is reused by slots that need different type parameters.
 *
 * ```php
 * #[Of('T')]
 * private mixed $first = null;
 * ```
 *
 * A promoted constructor property carrying this attribute produces **two** slots, the
 * parameter and the property, because native reflection reports the attribute on both.
 *
 * Properties, parameters and return types can all be marked, whatever they declare. A `mixed`
 * parameter costs slightly more than the others: the engine tests a type mask the compiler
 * cached into the `ZEND_RECV` opline, so re-typing one means un-sharing that method's opcode
 * array. The engine handles that; see docs/design.md.
 *
 * The one slot that cannot be marked is a return type the compiler emitted no check for - a
 * `mixed` return, or one it proved already satisfies the declaration. There is no opline to
 * make the substitution take effect, so it is rejected rather than silently unenforced.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Of
{
    public function __construct(public string $parameter) {}
}
